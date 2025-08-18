<?php
require_once '../sesiones_conexiones/sesion_config.php';
require_once '../database/conexion.php';

// Inicializar la variable toast
$toast = null;
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Validar que sea una petición POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de petición no válido.');
    }

    // Validar campos requeridos
    if (
        empty($_POST['clave_logia']) ||
        empty($_POST['anio']) ||
        empty($_POST['trimestre']) ||
        empty($_POST['nombre_tesorero']) ||
        empty($_POST['nombre_secretario'])
    ) {
        throw new Exception('Faltan datos requeridos para guardar el registro.');
    }

    // Sanitizar y validar datos
    $clave_logia = trim($_POST['clave_logia']); //
    $anio = intval($_POST['anio']);
    $trimestre = intval($_POST['trimestre']);
    $nombre_tesorero = trim($_POST['nombre_tesorero']);
    $nombre_secretario = trim($_POST['nombre_secretario']);
    $fecha_registro = date('Y-m-d H:i:s');

    // Validar que año y trimestre sean válidos
    if ($anio < 1900 || $anio > 2100) {
        throw new Exception('El año debe estar entre 1900 y 2100.');
    }

    if ($trimestre < 1 || $trimestre > 4) {
        throw new Exception('El trimestre debe estar entre 1 y 4.');
    }

    // Verificar que no se repita la misma persona en ambos cargos
    if ($nombre_tesorero === $nombre_secretario) {
        throw new Exception('La misma persona no puede ser Tesorero y Secretario al mismo tiempo.');
    }

    // Verificar si ya existe un registro para esa logia, año y trimestre
    $sql_check = "SELECT id FROM tabla_tesorero_secretario 
                  WHERE clave_logia = ? AND anio = ? AND trimestre = ?";
    $stmt_check = $conn->prepare($sql_check);
    
    if (!$stmt_check) {
        throw new Exception('Error al preparar la consulta de verificación: ' . $conn->error);
    }

    $stmt_check->bind_param("sii", $clave_logia, $anio, $trimestre);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result && $result->num_rows > 0) {
        // Ya existe: hacer UPDATE
        $row = $result->fetch_assoc();
        $id_existente = $row['id'];

        $sql_update = "UPDATE tabla_tesorero_secretario
                       SET nombre_tesorero = ?, nombre_secretario = ?, fecha_registro = ?
                       WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        
        if (!$stmt_update) {
            throw new Exception('Error al preparar la consulta de actualización: ' . $conn->error);
        }

        $stmt_update->bind_param("sssi", $nombre_tesorero, $nombre_secretario, $fecha_registro, $id_existente);
        
        if (!$stmt_update->execute()) {
            throw new Exception('Error al actualizar el registro: ' . $stmt_update->error);
        }

        $toast = [
            'type' => 'success',
            'message' => 'Registro actualizado correctamente.'
        ];
    } else {
        // No existe: hacer INSERT
        $sql_insert = "INSERT INTO tabla_tesorero_secretario 
                       (clave_logia, anio, trimestre, nombre_tesorero, nombre_secretario, fecha_registro)
                       VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        
        if (!$stmt_insert) {
            throw new Exception('Error al preparar la consulta de inserción: ' . $conn->error);
        }

        $stmt_insert->bind_param("siisss", $clave_logia, $anio, $trimestre, $nombre_tesorero, $nombre_secretario, $fecha_registro);
        
        if (!$stmt_insert->execute()) {
            throw new Exception('Error al guardar el registro: ' . $stmt_insert->error);
        }

        $toast = [
            'type' => 'success',
            'message' => 'Registro guardado correctamente.'
        ];
    }

} catch (Exception $e) {
    // Manejo de errores
    $toast = [
        'type' => 'error',
        'message' => $e->getMessage()
    ];
    
    // Log del error (opcional)
    error_log("Error en guardar_tesorero_secretario.php: " . $e->getMessage());
}

// Redirigir de vuelta al formulario con el mensaje
// Usar session para pasar el mensaje si es necesario
session_start();
$_SESSION['toast'] = $toast;

// Redirigir a la página del formulario
header('Location: /sigam/secretaria/');
?>