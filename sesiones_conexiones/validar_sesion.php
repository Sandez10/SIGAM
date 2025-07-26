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

    // Configurar respuesta JSON
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => '', 'redirect' => ''];

    if (empty($usuario) || empty($clave)) {
        $response['message'] = 'Todos los campos son obligatorios';
        echo json_encode($response);
        exit;
    }

    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();

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
            switch ($usuario_data['estado']) {
                case ESTADO_INACTIVO:
                    $response['message'] = 'Tu cuenta está inactiva. Por favor, contacta al administrador.';
                    break;
                
                case ESTADO_SUSPENDIDO:
                    $response['message'] = 'Tu cuenta está suspendida. Contacta al administrador para más información.';
                    break;
                
                case ESTADO_ACTIVO:
                    $_SESSION['user_id'] = $usuario_data['usrId'];
                    $_SESSION['user_name'] = $usuario_data['usr'];
                    $_SESSION['rol'] = $usuario_data['rol'];
                    
                    if ($usuario_data['password_reset_required']) {
                        $_SESSION['temp_user'] = $usuario_data;
                        $response['success'] = true;
  //                      $response['redirect'] = 'cambiar_contrasena.php';
                        // Redirección a cambiar contraseña (si existe)
//                        $response['redirect'] = '/SIGAM/cambiar-contrasena';
                        $response['redirect'] = 'cambio/';
 
                    } else {
                        $response['success'] = true;
//                        $response['redirect'] = "plataforma/principal.php";
                        // Redirección a plataforma principal
                        $response['redirect'] = 'plataforma/';

                     }
                    break;
                
                default:
                    $response['message'] = 'Estado de cuenta no reconocido';
                    break;
            }
        } else {
            $response['message'] = 'Usuario o contraseña incorrectos';
        }
    } catch (Exception $e) {
        error_log("Error en el Inicio de Sesión: " . $e->getMessage());
        $response['message'] = 'Ocurrió un error inesperado. Por favor, inténtalo nuevamente.';
    }

    echo json_encode($response);
    exit;
} else {
    header("Location: ../");
    exit;
}