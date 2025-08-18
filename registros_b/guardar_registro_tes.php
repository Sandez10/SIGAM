<?php
// 1. Configuración inicial y seguridad
require_once '../sesiones_conexiones/sesion_config.php';
require_once '../database/conexion.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Verificación de sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../");
    exit;
}

// 3. Validación CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode([
        'success' => false,
        'message' => 'Token CSRF inválido'
    ]));
}

// 4. Conexión a base de datos
try {
    $database = Database::getInstance();
    $conn = $database->getConnection();
} catch (Exception $e) {
    die(json_encode([
        'success' => false,
        'message' => 'Error de conexión: ' . $e->getMessage()
    ]));
}

// 5. Función para sanitizar y validar datos
function procesarDatos($data, $tipo = 'string') {
    $data = trim($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    switch ($tipo) {
        case 'int':
            return filter_var($data, FILTER_VALIDATE_INT) ? (int)$data : null;
        case 'float':
            return filter_var($data, FILTER_VALIDATE_FLOAT) ? (float)$data : 0.0;
        default:
            return !empty($data) ? $data : null;
    }
}

try {
    // 6.1. Recoger y validar datos principales
    $logia = procesarDatos($_POST['logia'] ?? '');
    $oriente = procesarDatos($_POST['oriente'] ?? '');
    $clave_logia = procesarDatos($_POST['clave_logia'] ?? '');
    $id_hermano = procesarDatos($_POST['id_hermano'] ?? '', 'int');
    $grado = procesarDatos($_POST['grado'] ?? '');
    $estado = procesarDatos($_POST['estado'] ?? '', 'int');    
    // Procesar valores numéricos
    $iniciacion = procesarDatos($_POST['iniciacion'] ?? '', 'float');
    $capitas = procesarDatos($_POST['capitas'] ?? '', 'float');
    $seguro = procesarDatos($_POST['seguro'] ?? '', 'float');
    $afiliacion = procesarDatos($_POST['afiliacion'] ?? '', 'float');
    $exaltacion = procesarDatos($_POST['exaltacion'] ?? '', 'float');

    // 6.2. Validaciones básicas
    if (!$id_hermano) throw new Exception("Debe seleccionar un hermano válido");
    if (!$grado) throw new Exception("El grado es requerido");
    if (!$logia || !$clave_logia) throw new Exception("Datos de logia incompletos");

    // 7. Obtener nombre completo del hermano
    $sqlHermano = "SELECT nombre_completo FROM registros WHERE id = ?";
    $stmtHermano = $conn->prepare($sqlHermano);
    $stmtHermano->bind_param("i", $id_hermano);
    $stmtHermano->execute();
    $stmtHermano->bind_result($nombre_hermano);
    $stmtHermano->fetch();
    $stmtHermano->close();

    if (empty($nombre_hermano)) {
        throw new Exception("No se pudo obtener el nombre del hermano.");
    }

// 8. Preparar consulta principal actualizada
$query = "INSERT INTO tesoreria (
    id_hermano, 
    nombre_hermano,
    grado, 
    logia, 
    clave_logia, 
    capitas, 
    seguro, 
    iniciacion, 
    afiliacion,
    exaltacion,
    fecha_registro, 
    usuario_registro,
    oriente,
    estado_hermano
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";

$stmt = $conn->prepare($query);
if (!$stmt) {
    throw new Exception("Error en prepare: " . $conn->error);
}

$usuario_registro = $_SESSION['user_id'];

$stmt->bind_param(
    "issssddddissi",
    $id_hermano,
    $nombre_hermano,
    $grado,
    $logia,
    $clave_logia,
    $capitas,
    $seguro,
    $iniciacion,
    $afiliacion,
    $exaltacion,
    $usuario_registro,
    $oriente,
    $estado
);

    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar el INSERT: " . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception("No se insertó ningún registro.");
    }
// 9. Actualizar el campo estado_hermano en la tabla registros
$updateEstado = $conn->prepare("UPDATE registros SET estado_hermano = ? WHERE id = ?");
if (!$updateEstado) {
    throw new Exception("Error en prepare del UPDATE: " . $conn->error);
}

$updateEstado->bind_param("ii", $estado, $id_hermano);

if (!$updateEstado->execute()) {
    throw new Exception("Error al actualizar el estado del hermano: " . $updateEstado->error);
}

$updateEstado->close();

    
    // Éxito - redireccionar con mensaje
    $_SESSION['success_message'] = 'El registro se guardó con éxito';
    header('Location: ../tesoreria/');
    exit;

} catch (Exception $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Error en tesoreria: " . $e->getMessage() . "\nDatos: " . print_r($_POST, true));

    $_SESSION['toast'] = [
        'success' => false,
        'type' => 'error',
        'message' => 'Error: ' . $e->getMessage(),
        'debug' => [
            'post_data' => $_POST,
            'error' => $e->getTraceAsString()
        ]
    ];

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}