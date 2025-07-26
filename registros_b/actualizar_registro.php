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
    header('Location: ../actualizar/');
    exit();
}

// 2. Verificar token CSRF de forma segura
if (!isset($_POST['csrf_token']) || 
    !isset($_SESSION['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    
    error_log("Intento de CSRF detectado. Token esperado: ".$_SESSION['csrf_token'].", Token recibido: ".($_POST['csrf_token'] ?? ''));
    
    $_SESSION['error_message'] = 'Token de seguridad inválido o expirado. Por favor recarga el formulario.';
    header('Location: ../secretaria/');
    exit();
}

// 3. Invalidar el token después de su uso
unset($_SESSION['csrf_token']);
unset($_SESSION['csrf_token_time']);

// 4. Verificar que se proporcione el ID del registro
if (!isset($_POST['id_registro']) || empty($_POST['id_registro'])) {
    $_SESSION['error_message'] = 'ID de registro no proporcionado';
    header('Location: ../actualizar/');
    exit();
}

$id_registro = intval($_POST['id_registro']);

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
$nueva_foto = false;
$fotoBinaria = null;

if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $fileType = $_FILES['foto']['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        $errors[] = "El formato de imagen no es válido. Use JPEG, PNG o GIF";
    }
    
    if ($_FILES['foto']['size'] > 5 * 1024 * 1024) { // 5MB máximo
        $errors[] = "La imagen es demasiado grande. Tamaño máximo: 5MB";
    }
    
    if (empty($errors)) {
        $fotoBinaria = file_get_contents($_FILES['foto']['tmp_name']);
        $nueva_foto = true;
    }
}

if (!empty($errors)) {
    $_SESSION['error_message'] = implode('\n', $errors);
    header('Location: ../secretaria/?editar=1&id=' . $id_registro);
    exit();
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $conn->begin_transaction();

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

    // Actualizar datos personales
    $sql_foto = "";
    $params_foto = "";
    
    // Manejar la fotografía
    if ($nueva_foto) {
        $sql_foto = ", fotografia = ?";
        $params_foto = "b";
    } elseif (isset($_POST['eliminar_foto']) && $_POST['eliminar_foto'] == '1') {
        $sql_foto = ", fotografia = NULL";
    }

    $stmt = $conn->prepare("UPDATE registros SET 
        nombre_completo = ?, domicilio = ?, nacionalidad = ?, estado_civil = ?,
        ocupacion = ?, religion = ?, numero_contacto = ?, numero_emergencia = ?,
        correo_electronico = ?, logia = ?, estado_hermano = ?
        $sql_foto
        WHERE id = ?");

    $base_params = "sssssssssis";
    $params = [
        $nombre_completo, $domicilio, $nacionalidad, $estado_civil,
        $ocupacion, $religion, $numero_contacto, $numero_emergencia,
        $correo_electronico, $logia, $estado_hermano
    ];

    if ($nueva_foto) {
        $base_params .= $params_foto;
        $params[] = $fotoBinaria;
    }
    
    $base_params .= "i";
    $params[] = $id_registro;

    $stmt->bind_param($base_params, ...$params);

    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar datos personales: " . $stmt->error);
    }
    $stmt->close();

    // Actualizar información masónica
    $grado_masonico = sanitizeInput($_POST['grado_masonico']);
    $tipo_ingreso = sanitizeInput($_POST['tipo_ingreso']);
    $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
    $fecha_aumento_salario = !empty($_POST['fecha_aumento_salario']) ? $_POST['fecha_aumento_salario'] : null;
    $fecha_exaltacion = !empty($_POST['fecha_exaltacion']) ? $_POST['fecha_exaltacion'] : null;
    $fecha_afiliacion = !empty($_POST['fecha_afiliacion']) ? $_POST['fecha_afiliacion'] : null;

    // Verificar si ya existe información masónica
    $stmt_check = $conn->prepare("SELECT id FROM informacion_masonica WHERE id_registro = ?");
    $stmt_check->bind_param("i", $id_registro);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        // Actualizar información masónica existente
        $stmt = $conn->prepare("UPDATE informacion_masonica SET 
            grado_masonico = ?, tipo_ingreso = ?, fecha_inicio = ?, 
            fecha_aumento_salario = ?, fecha_exaltacion = ?, fecha_afiliacion = ?
            WHERE id_registro = ?");
        
        $stmt->bind_param(
            "ssssssi",
            $grado_masonico, $tipo_ingreso, $fecha_inicio,
            $fecha_aumento_salario, $fecha_exaltacion, $fecha_afiliacion,
            $id_registro
        );
    } else {
        // Insertar nueva información masónica
        $stmt = $conn->prepare("INSERT INTO informacion_masonica (
            id_registro, grado_masonico, tipo_ingreso,
            fecha_inicio, fecha_aumento_salario, fecha_exaltacion, fecha_afiliacion
        ) VALUES (?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param(
            "issssss",
            $id_registro, $grado_masonico, $tipo_ingreso,
            $fecha_inicio, $fecha_aumento_salario, $fecha_exaltacion, $fecha_afiliacion
        );
    }

    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar información masónica: " . $stmt->error);
    }
    $stmt->close();

    // Manejar información de cargos
    // Primero eliminar cargos existentes
    $stmt_del_cargos = $conn->prepare("DELETE FROM cargos WHERE id_registro = ?");
    $stmt_del_cargos->bind_param("i", $id_registro);
    $stmt_del_cargos->execute();
    $stmt_del_cargos->close();

    // Insertar nuevos cargos si aplica
    if (isset($_POST['dignatario_oficial']) && $_POST['dignatario_oficial'] === 'si' && !empty($_POST['dignatorio'])) {
        $stmt = $conn->prepare("INSERT INTO cargos (
            id_registro, es_dignatario, tipo_cargo
        ) VALUES (?, ?, ?)");

        $esDignatario = 1;
        $tipo_cargo = sanitizeInput($_POST['dignatorio']);

        $stmt->bind_param("iis", $id_registro, $esDignatario, $tipo_cargo);

        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar cargos: " . $stmt->error);
        }
        $stmt->close();
    }

    // Manejar fechas de Past Master
    // Primero eliminar fechas existentes
    $stmt_del_pm = $conn->prepare("DELETE FROM past_masters WHERE id_registro = ?");
    $stmt_del_pm->bind_param("i", $id_registro);
    $stmt_del_pm->execute();
    $stmt_del_pm->close();

    // Insertar nuevas fechas de Past Master si aplica
    if (isset($_POST['past_master']) && $_POST['past_master'] === 'si' && !empty($_POST['past_master_fechas'])) {
        $stmt = $conn->prepare("INSERT INTO past_masters (
            id_registro, fecha, periodo
        ) VALUES (?, ?, ?)");

        $periodo = !empty($_POST['periodo_vm']) ? sanitizeInput($_POST['periodo_vm']) : '';

        foreach ($_POST['past_master_fechas'] as $fecha) {
            if (!empty($fecha)) {
                $stmt->bind_param("iss", $id_registro, $fecha, $periodo);

                if (!$stmt->execute()) {
                    throw new Exception("Error al actualizar fechas de Past Master: " . $stmt->error);
                }
            }
        }
        $stmt->close();
    }

    $conn->commit();

    // Éxito - redireccionar con mensaje
    $_SESSION['success_message'] = 'El registro se actualizó con éxito';
    header('Location: ../secretaria/?editar=1&id=' . $id_registro);
    exit();

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    $_SESSION['error_message'] = 'Error al actualizar el registro: ' . $e->getMessage();
    header('Location: ../secretaria/?editar=1&id=' . $id_registro);
    exit();
} finally {
    if (isset($conn)) {
        $conn->autocommit(true);
    }
}
?>

