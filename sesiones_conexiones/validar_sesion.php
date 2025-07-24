<?php
require_once 'sesion_config.php';
require_once '../database/conexion.php';

// Definir constantes para los estados
define('ESTADO_INACTIVO', 0);
define('ESTADO_ACTIVO', 1);
define('ESTADO_SUSPENDIDO', 2);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave = trim($_POST['clave'] ?? '');

    if (empty($usuario) || empty($clave)) {
        header('Location: ../?error=Todos los campos son obligatorios');
        exit;
    }

    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();

        // Consulta mejorada: seleccionar solo los campos necesarios
        $stmt = $conn->prepare("
            SELECT usrId, usr, rol, clave, estado, password_reset_required 
            FROM usuarios 
            WHERE usr = ?
        ");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        $usuario_data = $result->fetch_assoc();
        $stmt->close();

        if ($usuario_data && password_verify($clave, $usuario_data['clave'])) {
            // Manejo de diferentes estados
            switch ($usuario_data['estado']) {
                case ESTADO_INACTIVO:
                    header("Location: ../?error=Tu cuenta está inactiva. Por favor, contacta al administrador.");
                    exit;
                
                case ESTADO_SUSPENDIDO:
                    header("Location: ../?error=Tu cuenta está suspendida. Contacta al administrador para más información.");
                    exit;
                
                case ESTADO_ACTIVO:
                    // Iniciar sesión exitosamente
                    $_SESSION['user_id'] = $usuario_data['usrId'];
                    $_SESSION['user_name'] = $usuario_data['usr'];
                    $_SESSION['rol'] = $usuario_data['rol'];
                    
                    // Verificar si se requiere cambio de contraseña
                    if ($usuario_data['password_reset_required']) {
                        $_SESSION['temp_user'] = $usuario_data;
                        header("Location: cambiar_contrasena.php");
                        exit;
                    }
                    
                    header("Location: ../plataforma/principal.php");
                    exit;
                
                default:
                    header("Location: ../?error=Estado de cuenta no reconocido");
                    exit;
            }
        } else {
            header("Location: ../?error=Usuario o contraseña incorrectos");
            exit;
        }
    } catch (Exception $e) {
        error_log("Error en el Inicio de Sesión: " . $e->getMessage());
        header("Location: ../?error=Ocurrió un error inesperado. Por favor, inténtalo nuevamente.");
        exit;
    }
} else {
    header("Location: ../");
    exit;
}