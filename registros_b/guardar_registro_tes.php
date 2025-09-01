<?php
// 1) Configuración inicial y seguridad
require_once '../sesiones_conexiones/sesion_config.php';
require_once '../database/conexion.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2) Verificación de sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../");
    exit;
}

// 3) Validación CSRF (más segura)
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die(json_encode([
        'success' => false,
        'message' => 'Token CSRF inválido'
    ]));
}

// (Opcional) invalidar el token tras uso
unset($_SESSION['csrf_token']);

// 4) Conexión a base de datos
try {
    $database = Database::getInstance();
    $conn = $database->getConnection();
} catch (Exception $e) {
    die(json_encode([
        'success' => false,
        'message' => 'Error de conexión: ' . $e->getMessage()
    ]));
}

// 5) Utilidades de saneo
function procesarDatos($data, $tipo = 'string') {
    $data = trim((string)$data);
    switch ($tipo) {
        case 'int':
            return (filter_var($data, FILTER_VALIDATE_INT) !== false) ? (int)$data : null;
        case 'float':
            // Acepta "100", "100.00"
            return (filter_var($data, FILTER_VALIDATE_FLOAT) !== false) ? (float)$data : 0.0;
        default:
            // string
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
            return ($data !== '') ? $data : null;
    }
}

/**
 * Trae el monto por ID de catalogo_precios, validando categoría.
 * Si $validarTrimestre es true (para capitas), garantiza que el registro pertenece al TRIMESTRE vigente.
 */
function montoPorIdCategoria(mysqli $conn, int $idPrecio, string $categoria, bool $validarTrimestre = false): float {
    if ($validarTrimestre) {
        // Capitas: validar que el periodo coincida con el trimestre actual (T1..T4)
        $sql = "SELECT monto
            FROM catalogo_precios WHERE id = ?
            AND categoria = ? AND vigente = 1 AND periodo = CONCAT('T', QUARTER(CURDATE()))
            LIMIT 1";
    } else {
        $sql = "SELECT monto
            FROM catalogo_precios WHERE id = ? AND categoria = ? AND vigente = 1
            LIMIT 1";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error al preparar consulta de precio ($categoria): " . $conn->error);
    }
    $stmt->bind_param("is", $idPrecio, $categoria);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        // No existe ese ID/categoría/vigencia (o no corresponde al trimestre actual)
        return 0.0;
    }
    return (float)$row['monto'];
}

try {
    // 6.1) Recoger y validar datos principales
    $logia       = procesarDatos($_POST['logia'] ?? '');
    $oriente     = procesarDatos($_POST['oriente'] ?? '');
    $clave_logia = procesarDatos($_POST['clave_logia'] ?? '', 'int');
    $id_hermano  = procesarDatos($_POST['id_hermano'] ?? '', 'int');
    $grado       = procesarDatos($_POST['grado'] ?? '');
    $estado      = procesarDatos($_POST['estado'] ?? '', 'int');


    if (!$id_hermano)            throw new Exception("Debe seleccionar un hermano válido.");
    if (!$grado)                 throw new Exception("El grado es requerido.");
    if (!$logia || !$clave_logia) throw new Exception("Datos de logia incompletos.");

    // 6.2) Recoger IDs seleccionados desde el formulario (NO montos directos)
    $iniciacion_id = procesarDatos($_POST['iniciacion_id'] ?? '', 'int');
    $capitas_id    = procesarDatos($_POST['capitas_id'] ?? '', 'int');
    $seguro_id     = procesarDatos($_POST['seguro_id'] ?? '', 'int');
    $afiliacion_id = procesarDatos($_POST['afiliacion_id'] ?? '', 'int');
    $exaltacion_id = procesarDatos($_POST['exaltacion_id'] ?? '', 'int');
    $regularizacion_id = procesarDatos($_POST['regularizacion_id'] ?? '', 'int');

    // 7) Obtener nombre completo del hermano
    $sqlHermano = "SELECT nombre_completo FROM registros WHERE id = ?";
    $stmtHermano = $conn->prepare($sqlHermano);
    if (!$stmtHermano) {
        throw new Exception("Error preparando consulta de hermano: " . $conn->error);
    }
    $stmtHermano->bind_param("i", $id_hermano);
    $stmtHermano->execute();
    $stmtHermano->bind_result($nombre_hermano);
    $stmtHermano->fetch();
    $stmtHermano->close();

    if (empty($nombre_hermano)) {
        throw new Exception("No se pudo obtener el nombre del hermano.");
    }

    // 8) Resolver montos desde catalogo_precios (seguro)
    // Capitas: validar que pertenezca al trimestre vigente
    $capitas    = $capitas_id    ? montoPorIdCategoria($conn, $capitas_id,    'capitas',   true)  : 0.0;
    $seguro     = $seguro_id     ? montoPorIdCategoria($conn, $seguro_id,     'seguro')            : 0.0;
    $iniciacion = $iniciacion_id ? montoPorIdCategoria($conn, $iniciacion_id, 'iniciacion')        : 0.0;
    $afiliacion = $afiliacion_id ? montoPorIdCategoria($conn, $afiliacion_id, 'afiliacion')        : 0.0;
    $exaltacion = $exaltacion_id ? montoPorIdCategoria($conn, $exaltacion_id, 'exaltacion')        : 0.0;
    $regularizacion = $regularizacion_id ? montoPorIdCategoria($conn, $regularizacion_id, 'regularizacion')        : 0.0;
    // Si todos son cero y esperas al menos uno, podrías validar aquí:
    // if ($capitas == 0 && $seguro == 0 && $iniciacion == 0 && $afiliacion == 0 && $exaltacion == 0) {
    //     throw new Exception("Debe seleccionar al menos un concepto válido.");
    // }

    // 9) Insertar en tesoreria
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
        regularizacion,
        fecha_registro, 
        usuario_registro,
        oriente,
        estado_hermano
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Error en prepare del INSERT: " . $conn->error);
    }

    $usuario_registro = (int)$_SESSION['user_id'];

    // Tipos:
    // i (id_hermano)
    // s (nombre_hermano)
    // s (grado)
    // s (logia)
    // i (clave_logia)
    // d (capitas)
    // d (seguro)
    // d (iniciacion)
    // d (afiliacion)
    // d (exaltacion)
    // i (usuario_registro)
    // s (oriente)
    // i (estado)

    // Re-preparar correctamente (sin espacios en los tipos)
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Error en prepare del INSERT (2): " . $conn->error);
    }
    $tipos = "isssiddddddisi"; // <- sin espacios
    $stmt->bind_param(
        $tipos,
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
        $regularizacion,
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
    $stmt->close();

    // 10) Actualizar estado_hermano en registros
    $updateEstado = $conn->prepare("UPDATE registros SET estado_hermano = ? WHERE id = ?");
    if (!$updateEstado) {
        throw new Exception("Error en prepare del UPDATE: " . $conn->error);
    }
    $updateEstado->bind_param("ii", $estado, $id_hermano);
    if (!$updateEstado->execute()) {
        throw new Exception("Error al actualizar el estado del hermano: " . $updateEstado->error);
    }
    $updateEstado->close();

    // Éxito
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

    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../tesoreria/'));
    exit;
}
