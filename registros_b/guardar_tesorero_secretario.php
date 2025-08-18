<?php
require_once '../database/conexion.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();

$nombre_tesorero = $_POST['nombre_tesorero'];
$nombre_secretario = $_POST['nombre_secretario'];
$clave_logia = $_POST['clave_logia'];
$anio = $_POST['anio'];
$trimestre = $_POST['trimestre'];

// Verificar si ya existe un registro
$queryCheck = "SELECT id FROM tabla_tesorero_secretario 
               WHERE clave_logia = ? AND anio = ? AND trimestre = ?";
$stmt = $conn->prepare($queryCheck);
$stmt->bind_param("iii", $clave_logia, $anio, $trimestre);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // Actualizar
    $stmt->close();
    $queryUpdate = "UPDATE tabla_tesorero_secretario 
                    SET nombre_tesorero = ?, nombre_secretario = ?, fecha_registro = NOW()
                    WHERE clave_logia = ? AND anio = ? AND trimestre = ?";
    $stmt = $conn->prepare($queryUpdate);
    $stmt->bind_param("ssiii", $nombre_tesorero, $nombre_secretario, $clave_logia, $anio, $trimestre);
    $stmt->execute();
    echo "Actualizado correctamente.";
} else {
    // Insertar nuevo
    $stmt->close();
    $queryInsert = "INSERT INTO tabla_tesorero_secretario 
                    (nombre_tesorero, nombre_secretario, clave_logia, anio, trimestre) 
                    VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($queryInsert);
    $stmt->bind_param("ssiii", $nombre_tesorero, $nombre_secretario, $clave_logia, $anio, $trimestre);
    $stmt->execute();
    echo "Guardado correctamente.";
}
