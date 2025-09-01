<?php
require_once 'sesion_config.php';
require_once '../database/conexion.php';

function obtenerLogiasYRegistros($conn) {
    $rolUsuario = $_SESSION['rol'] ?? null;
    $claveLogia = $_SESSION['clave_logia'] ?? null;

    $resultado = [
        'logias' => [],
        'hermanos' => []
    ];

    try {
        if ($rolUsuario === 'fullmaester') {
            $stmtLogias = $conn->prepare("SELECT DISTINCT clave_logia, logia FROM registros ORDER BY logia ASC");
            $stmtLogias->execute();
            $resultado['logias'] = $stmtLogias->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtLogias->close();

            $stmtHermanos = $conn->prepare("SELECT id, nombre_completo, clave_logia FROM registros ORDER BY nombre_completo ASC");
            $stmtHermanos->execute();
            $resultado['hermanos'] = $stmtHermanos->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtHermanos->close();
        } elseif ($claveLogia) {
            $stmtLogia = $conn->prepare("SELECT DISTINCT clave_logia, logia FROM registros WHERE clave_logia = ?");
            $stmtLogia->bind_param("i", $claveLogia);
            $stmtLogia->execute();
            $resultado['logias'] = $stmtLogia->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtLogia->close();

            $stmtHermanos = $conn->prepare("SELECT id, nombre_completo, clave_logia FROM registros WHERE clave_logia = ? ORDER BY nombre_completo ASC");
            $stmtHermanos->bind_param("i", $claveLogia);
            $stmtHermanos->execute();
            $resultado['hermanos'] = $stmtHermanos->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtHermanos->close();
        }
    } catch (Exception $e) {
        error_log("Error al obtener logias y registros: " . $e->getMessage());
    }

    return $resultado;
}
?>