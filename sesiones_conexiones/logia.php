<?php
// Configuración de errores
require_once '../sesiones_conexiones/sesion_config.php';
require_once '../database/conexion.php';
// 4. Validación reforzada de sesión
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("Location: ../");
    exit;
}
// 5. Función con mayor seguridad y manejo de errores
function obtenerDatosLogia(): ?array {
    try {

        $database = Database::getInstance();
        $conn = $database->getConnection();

        // Consulta optimizada para obtener ambos datos
        $query = "SELECT l.logia, l.clave_logia, l.oriente
                 FROM usuarios u
                 INNER JOIN logias l ON u.logia = l.clave_logia
                 WHERE u.usrId = ? 
                 LIMIT 1";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $conn->error);
        }

        $stmt->bind_param("i", $_SESSION['user_id']);
        if (!$stmt->execute()) {
            throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
        }

        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            error_log("No se encontraron datos de logia para el usuario ID: ".$_SESSION['user_id']);
            return null;
        }

        $datos = $result->fetch_assoc();
        $stmt->close();

        // Depuración (eliminar esto después)
        error_log("Datos obtenidos de logia: ".print_r($datos, true));

        return [
            'logia' => $datos['logia'] ?? null,
            'clave_logia' => $datos['clave_logia'] ?? null,
            'oriente' => $datos['oriente'] ?? null
        ];

    } catch (Exception $e) {
        error_log("Error en obtenerDatosLogia: " . $e->getMessage());
        return null;
    }
}



// 6. Obtención del valor con validación
$logia_registro = obtenerDatosLogia();
// 7. Headers de seguridad adicionales
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");