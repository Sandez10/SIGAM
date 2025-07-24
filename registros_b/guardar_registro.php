<?php
require_once '../sesiones_conexiones/sesion_config.php'; 
require_once '../database/conexion.php';

// Función para sanitizar inputs
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// 1. Verificar método de envío
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Método no permitido';
    header('Location: ../formulario/form_sec.php');
    exit();
}

// 2. Verificar token CSRF de forma segura
if (!isset($_POST['csrf_token']) || 
    !isset($_SESSION['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    
    // Registrar el intento fallido (opcional)
    error_log("Intento de CSRF detectado. Token esperado: ".$_SESSION['csrf_token'].", Token recibido: ".($_POST['csrf_token'] ?? ''));
    
    $_SESSION['error_message'] = 'Token de seguridad inválido o expirado. Por favor recarga el formulario.';
    header('Location: ../formulario/form_sec.php');
    exit();
}

// 3. Invalidar el token después de su uso (opcional pero recomendado)
unset($_SESSION['csrf_token']);
unset($_SESSION['csrf_token_time']);

// Validar campos requeridos
$requiredFields = [
    'nombre_completo', 'estado_hermano','domicilio', 'nacionalidad', 'estado_civil',
    'ocupacion', 'religion', 'numero_contacto', 'numero_emergencia',
    'correo_electronico', 'grado_masonico', 'tipo_ingreso'
];

$errors = [];
foreach ($requiredFields as $field) {
    if (empty($_POST[$field])) {
        $errors[] = "El campo $field es requerido";
    }
}

// Validar archivo de foto si se subió
if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $fileType = $_FILES['foto']['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        $errors[] = "El formato de imagen no es válido. Use JPEG, PNG o GIF";
    }
    
    if ($_FILES['foto']['size'] > 5 * 1024 * 1024) { // 5MB máximo
        $errors[] = "La imagen es demasiado grande. Tamaño máximo: 5MB";
    }
}

if (!empty($errors)) {
    $_SESSION['error_message'] = implode('\n', $errors);
    header('Location: ../formulario/form_sec.php');
    exit();
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $conn->begin_transaction();

    // Procesar la imagen
    $fotoBinaria = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $fotoBinaria = file_get_contents($_FILES['foto']['tmp_name']);
    }

    // Sanitizar inputs
    $logia = isset($_POST['logia']) ? sanitizeInput($_POST['logia']) : null;
    if (empty($logia)) {
        $errors[] = "El campo logia es requerido";
    }
    $nombre_completo = sanitizeInput($_POST['nombre_completo']);
    $estado_hermano = sanitizeInput($_POST['estado_hermano']);
    $domicilio = sanitizeInput($_POST['domicilio']);
    $nacionalidad = sanitizeInput($_POST['nacionalidad']);
    $estado_civil = sanitizeInput($_POST['estado_civil']);
    $ocupacion = sanitizeInput($_POST['ocupacion']);
    $religion = sanitizeInput($_POST['religion']);
    $numero_contacto = sanitizeInput($_POST['numero_contacto']);
    $numero_emergencia = sanitizeInput($_POST['numero_emergencia']);
    $correo_electronico = sanitizeInput($_POST['correo_electronico']);

    // Insertar datos personales
    $stmt = $conn->prepare("INSERT INTO registros (
        nombre_completo, domicilio, nacionalidad, estado_civil,
        ocupacion, religion, numero_contacto, numero_emergencia,
        correo_electronico, fotografia, logia,estado_hermano
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?)");

    $null = NULL;
    $stmt->bind_param(
        "sssssssssbsi",
        $nombre_completo,
        $domicilio,
        $nacionalidad,
        $estado_civil,
        $ocupacion,
        $religion,
        $numero_contacto,
        $numero_emergencia,
        $correo_electronico,
        $null,
        $logia,
        $estado_hermano
    );

    if ($fotoBinaria !== null) {
        $stmt->send_long_data(9, $fotoBinaria);
    }

    if (!$stmt->execute()) {
        throw new Exception("Error al guardar datos personales: " . $stmt->error);
    }

    $idRegistro = $stmt->insert_id;
    $stmt->close();

    // Insertar información masónica
    $grado_masonico = sanitizeInput($_POST['grado_masonico']);
    $tipo_ingreso = sanitizeInput($_POST['tipo_ingreso']);
    $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
    $fecha_aumento_salario = !empty($_POST['fecha_aumento_salario']) ? $_POST['fecha_aumento_salario'] : null;
    $fecha_exaltacion = !empty($_POST['fecha_exaltacion']) ? $_POST['fecha_exaltacion'] : null;
    $fecha_afiliacion = !empty($_POST['fecha_afiliacion']) ? $_POST['fecha_afiliacion'] : null;

    $stmt = $conn->prepare("INSERT INTO informacion_masonica (
        id_registro, grado_masonico, tipo_ingreso,
        fecha_inicio, fecha_aumento_salario, fecha_exaltacion, fecha_afiliacion
    ) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "issssss",
        $idRegistro,
        $grado_masonico,
        $tipo_ingreso,
        $fecha_inicio,
        $fecha_aumento_salario,
        $fecha_exaltacion,
        $fecha_afiliacion
    );

    if (!$stmt->execute()) {
        throw new Exception("Error al guardar información masónica: " . $stmt->error);
    }
    $stmt->close();

    // Insertar información de cargos (si aplica)
    if (isset($_POST['dignatario_oficial']) && $_POST['dignatario_oficial'] === 'si' && !empty($_POST['dignatorio'])) {
        $stmt = $conn->prepare("INSERT INTO cargos (
            id_registro, es_dignatario, tipo_cargo
        ) VALUES (?, ?, ?)");

        $esDignatario = 1;
        $tipo_cargo = sanitizeInput($_POST['dignatorio']);

        $stmt->bind_param(
            "iis",
            $idRegistro,
            $esDignatario,
            $tipo_cargo
        );

        if (!$stmt->execute()) {
            throw new Exception("Error al guardar cargos: " . $stmt->error);
        }
        $stmt->close();
    }

    // Insertar fechas de Past Master (si aplica)
    if (isset($_POST['past_master']) && $_POST['past_master'] === 'si' && !empty($_POST['past_master_fechas'])) {
        $stmt = $conn->prepare("INSERT INTO past_masters (
            id_registro, fecha, periodo
        ) VALUES (?, ?, ?)");

        $periodo = !empty($_POST['periodo_vm']) ? sanitizeInput($_POST['periodo_vm']) : '';

        foreach ($_POST['past_master_fechas'] as $fecha) {
            if (!empty($fecha)) {
                $stmt->bind_param(
                    "iss",
                    $idRegistro,
                    $fecha,
                    $periodo
                );

                if (!$stmt->execute()) {
                    throw new Exception("Error al guardar fechas de Past Master: " . $stmt->error);
                }
            }
        }
        $stmt->close();
    }

    $conn->commit();

    // Éxito - redireccionar con mensaje
    $_SESSION['success_message'] = 'El registro se guardó con éxito';
    header('Location: ../formulario/form_sec.php');
    exit();

} catch (Exception $e) {
    // Intenta hacer rollback pero no falles si ya se perdió la conexión
    try {
        if (isset($conn)) $conn->rollback();
    } catch (Exception $rollbackEx) {
        error_log("Error en rollback: ".$rollbackEx->getMessage());
    }
    
    $_SESSION['error_message'] = 'Ocurrió un error. Por favor intenta nuevamente.';
    error_log("Error en guardar_registro: ".$e->getMessage());
    header('Location: ../formulario/form_sec.php');
    exit();
} finally {
    if (isset($conn)) {
        $conn->autocommit(true);
    }
}