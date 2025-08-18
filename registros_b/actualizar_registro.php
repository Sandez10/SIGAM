<?php
require_once '../sesiones_conexiones/sesion_config.php'; 
require_once '../database/conexion.php';
require_once '../sesiones_conexiones/logia.php';

// Utilidad: sanitizar inputs
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// 1) Método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Método no permitido';
    header('Location: ../actualizar/');
    exit();
}

// 2) CSRF
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    error_log("Intento de CSRF detectado. Token esperado: ".$_SESSION['csrf_token'].", Token recibido: ".($_POST['csrf_token'] ?? ''));
    $_SESSION['error_message'] = 'Token de seguridad inválido o expirado. Por favor recarga el formulario.';
    header('Location: ../secretaria/');
    exit();
}
// 3) Invalidar token
unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);

// 4) ID registro
if (empty($_POST['id_registro'])) {
    $_SESSION['error_message'] = 'ID de registro no proporcionado';
    header('Location: ../actualizar/');
    exit();
}

$id_registro = (int)$_POST['id_registro'];

// 5) Permiso por logia
$datosLogia = obtenerDatosLogia();
if ($datosLogia === null) {
    $_SESSION['error_message'] = 'No se pudieron obtener los datos de la logia.';
    header('Location: ../secretaria/');
    exit();
}
$clave_logia = (int)$datosLogia['clave_logia'];

$db   = Database::getInstance();
$conn = $db->getConnection();

// Verificar que el registro pertenezca a la logia
$stmt_check_logia = $conn->prepare("SELECT id FROM registros WHERE id = ? AND clave_logia = ?");
$stmt_check_logia->bind_param("ii", $id_registro, $clave_logia);
$stmt_check_logia->execute();
$result_check_logia = $stmt_check_logia->get_result();
$stmt_check_logia->close();

if ($result_check_logia->num_rows === 0) {
    $_SESSION['error_message'] = 'No tienes permiso para editar este registro.';
    header('Location: ../secretaria/');
    exit();
}

// 6) Validaciones base
$requiredFields = [
    'nombre_completo','estado_hermano','domicilio','nacionalidad','estado_civil',
    'ocupacion','religion','numero_contacto','numero_emergencia',
    'correo_electronico','grado_masonico','tipo_ingreso'
];
$errors = [];
foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || (is_string($_POST[$field]) && trim($_POST[$field]) === '')) {
        $errors[] = "El campo $field es requerido";
    }
}

// 7) Validar archivo de foto si viene
$nueva_foto  = false;
$fotoBinaria = null;

if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg','image/png','image/gif'];
    $fileType     = $_FILES['foto']['type'];
    if (!in_array($fileType, $allowedTypes)) {
        $errors[] = "El formato de imagen no es válido. Use JPEG, PNG o GIF";
    }
    if ($_FILES['foto']['size'] > 5 * 1024 * 1024) {
        $errors[] = "La imagen es demasiado grande. Tamaño máximo: 5MB";
    }
    if (empty($errors)) {
        $fotoBinaria = file_get_contents($_FILES['foto']['tmp_name']);
        $nueva_foto  = true;
    }
}

if (!empty($errors)) {
    $_SESSION['error_message'] = implode('\n', $errors);
    header('Location: ../secretaria/?editar=1&id='.$id_registro);
    exit();
}

try {
    $conn->begin_transaction();

    // 8) Sanitizar inputs personales
    $logia             = isset($_POST['logia']) ? sanitizeInput($_POST['logia']) : null;
    if (empty($logia)) { throw new Exception("El campo logia es requerido"); }

    $nombre_completo   = sanitizeInput($_POST['nombre_completo']);
    $estado_hermano    = sanitizeInput($_POST['estado_hermano']);
    $domicilio         = sanitizeInput($_POST['domicilio']);
    $nacionalidad      = sanitizeInput($_POST['nacionalidad']);
    $estado_civil      = sanitizeInput($_POST['estado_civil']);
    $ocupacion         = sanitizeInput($_POST['ocupacion']);
    $religion          = sanitizeInput($_POST['religion']);
    $numero_contacto   = sanitizeInput($_POST['numero_contacto']);
    $numero_emergencia = sanitizeInput($_POST['numero_emergencia']);
    $correo_electronico= sanitizeInput($_POST['correo_electronico']);
    $observaciones     = sanitizeInput($_POST['observaciones'] ?? '');

    // 9) FIRMA (canvas -> PNG en /firmas -> guardar ruta en firma_path)
    $firmaPath       = null;   // nombre del archivo si llega firma nueva
    $actualizarFirma = false;  // tocar o no firma_path en el UPDATE

    if (!empty($_POST['firma_base64'])) {
        $firma_base64 = $_POST['firma_base64'];
        if (strpos($firma_base64, 'data:image/png;base64,') === 0) {
            $firma_base64 = substr($firma_base64, strlen('data:image/png;base64,'));
        }
        $firma_base64 = str_replace(' ', '+', $firma_base64);

        $nombreFirma = 'firma_' . time() . '_' . rand(1000,9999) . '.png';
        $rutaFirma   = __DIR__ . '/../firmas/' . $nombreFirma;

        if (file_put_contents($rutaFirma, base64_decode($firma_base64))) {
            $firmaPath       = $nombreFirma;
            $actualizarFirma = true;
        } else {
            error_log("No se pudo guardar la firma PNG en: $rutaFirma");
        }
    }

    $eliminarFirma = (isset($_POST['eliminar_firma']) && $_POST['eliminar_firma'] == '1');
    if ($eliminarFirma) {
        $actualizarFirma = true; // se pondrá firma_path = NULL
    }

    // 10) FOTO (fragmento SQL)
    $sqlFotoFragment = "";
    if ($nueva_foto) {
        $sqlFotoFragment = ", fotografia = ?";
    } elseif (isset($_POST['eliminar_foto']) && $_POST['eliminar_foto'] == '1') {
        $sqlFotoFragment = ", fotografia = NULL";
    }

    // 11) UPDATE registros con construcción segura (sin coma antes de WHERE)
    $campos = "
      nombre_completo = ?,
      domicilio = ?,
      nacionalidad = ?,
      estado_civil = ?,
      ocupacion = ?,
      religion = ?,
      numero_contacto = ?,
      numero_emergencia = ?,
      correo_electronico = ?,
      logia = ?,
      estado_hermano = ?,
      observaciones = ?
    ";
    $types  = "ssssssssssss";
    $params = [
      $nombre_completo, $domicilio, $nacionalidad, $estado_civil,
      $ocupacion, $religion, $numero_contacto, $numero_emergencia,
      $correo_electronico, $logia, $estado_hermano, $observaciones
    ];

    // Foto
    if ($nueva_foto) {
        $campos .= ", fotografia = ?";
        $types  .= "b";
        $params[] = $fotoBinaria;
    } elseif (isset($_POST['eliminar_foto']) && $_POST['eliminar_foto'] == '1') {
        $campos .= ", fotografia = NULL";
    }

    // Firma
    if ($actualizarFirma) {
        if ($firmaPath) {
            $campos .= ", firma_path = ?";
            $types  .= "s";
            $params[] = $firmaPath;
        } else {
            $campos .= ", firma_path = NULL";
        }
    }

    $campos .= " WHERE id = ?";
    $types  .= "i";
    $params[] = $id_registro;

    $sql = "UPDATE registros SET $campos";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    // Enviar binario de foto si aplica
    if ($nueva_foto) {
        $binIndex = array_search($fotoBinaria, $params, true);
        if ($binIndex !== false) { $stmt->send_long_data($binIndex, $fotoBinaria); }
    }

    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar datos personales: " . $stmt->error);
    }
    $stmt->close();

    // 12) Información masónica (upsert)
    $grado_masonico        = sanitizeInput($_POST['grado_masonico']);
    $tipo_ingreso          = sanitizeInput($_POST['tipo_ingreso']);
    $fecha_inicio          = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
    $fecha_aumento_salario = !empty($_POST['fecha_aumento_salario']) ? $_POST['fecha_aumento_salario'] : null;
    $fecha_exaltacion      = !empty($_POST['fecha_exaltacion']) ? $_POST['fecha_exaltacion'] : null;
    $fecha_afiliacion      = !empty($_POST['fecha_afiliacion']) ? $_POST['fecha_afiliacion'] : null;

    $stmt_check = $conn->prepare("SELECT id FROM informacion_masonica WHERE id_registro = ?");
    $stmt_check->bind_param("i", $id_registro);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $stmt_check->close();

    if ($result_check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE informacion_masonica SET 
            grado_masonico = ?, tipo_ingreso = ?, fecha_inicio = ?, 
            fecha_aumento_salario = ?, fecha_exaltacion = ?, fecha_afiliacion = ?
            WHERE id_registro = ?");
        $stmt->bind_param("ssssssi",
            $grado_masonico, $tipo_ingreso, $fecha_inicio,
            $fecha_aumento_salario, $fecha_exaltacion, $fecha_afiliacion,
            $id_registro
        );
    } else {
        $stmt = $conn->prepare("INSERT INTO informacion_masonica (
            id_registro, grado_masonico, tipo_ingreso,
            fecha_inicio, fecha_aumento_salario, fecha_exaltacion, fecha_afiliacion
        ) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss",
            $id_registro, $grado_masonico, $tipo_ingreso,
            $fecha_inicio, $fecha_aumento_salario, $fecha_exaltacion, $fecha_afiliacion
        );
    }
    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar información masónica: " . $stmt->error);
    }
    $stmt->close();

    // 13) Sincronizar tesorería (grado/estado)
    $stmt = $conn->prepare("UPDATE tesoreria SET grado = ?, estado_hermano = ? WHERE id_hermano = ?");
    $stmt->bind_param("sii", $grado_masonico, $estado_hermano, $id_registro);
    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar grado en tesoreria: " . $stmt->error);
    }
    $stmt->close();

    // 14) Cargos (reset + insertar si aplica)
    $stmt = $conn->prepare("DELETE FROM cargos WHERE id_registro = ?");
    $stmt->bind_param("i", $id_registro);
    $stmt->execute();
    $stmt->close();

    if (isset($_POST['dignatario_oficial']) && $_POST['dignatario_oficial'] === 'si' && !empty($_POST['dignatorio'])) {
        $esDignatario = 1;
        $tipo_cargo   = sanitizeInput($_POST['dignatorio']);
        $stmt = $conn->prepare("INSERT INTO cargos (id_registro, es_dignatario, tipo_cargo) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $id_registro, $esDignatario, $tipo_cargo);
        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar cargos: " . $stmt->error);
        }
        $stmt->close();
    }

    // 15) Past Masters (reset + insertar si aplica)
    $stmt = $conn->prepare("DELETE FROM past_masters WHERE id_registro = ?");
    $stmt->bind_param("i", $id_registro);
    $stmt->execute();
    $stmt->close();

    if (isset($_POST['past_master']) && $_POST['past_master'] === 'si' && !empty($_POST['past_master_fechas'])) {
        $periodo = !empty($_POST['periodo_vm']) ? sanitizeInput($_POST['periodo_vm']) : '';
        $stmt = $conn->prepare("INSERT INTO past_masters (id_registro, fecha, periodo) VALUES (?, ?, ?)");
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

    // 16) Commit
    $conn->commit();

    $_SESSION['success_message'] = 'El registro se actualizó con éxito';
    header('Location: ../secretaria/?editar=1&id='.$id_registro);
    exit();

} catch (Exception $e) {
    if ($conn) { $conn->rollback(); }
    $_SESSION['error_message'] = 'Error al actualizar el registro: ' . $e->getMessage();
    header('Location: ../secretaria/?editar=1&id='.$id_registro);
    exit();
} finally {
    if ($conn) { $conn->autocommit(true); }
}
