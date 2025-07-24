<?php
// procesar_exito.php - Maneja el flujo post-validación exitosa

// Función de auditoría (si no existe)
if (!function_exists('audit_log')) {
    function audit_log($user_id, $action) {
        // Implementación básica de registro
        $log_message = date('[Y-m-d H:i:s]') . " User $user_id: $action" . PHP_EOL;
        file_put_contents('../logs/security.log', $log_message, FILE_APPEND);
    }
}

// Registrar el cambio de contraseña
$stmt = $conn->prepare("INSERT INTO historico_contrasenas (usuario_id, fecha_cambio) VALUES (?, NOW())");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$stmt->close();

// Si era un cambio requerido, completar el login
if ($required_change) {
    $_SESSION['user_id'] = $_SESSION['temp_user']['usrId'];
    $_SESSION['user_name'] = $_SESSION['temp_user']['usr'];
    $_SESSION['rol'] = $_SESSION['temp_user']['rol'];
    unset($_SESSION['temp_user']);
    
    // Registrar evento de seguridad
    audit_log($user_id, "Cambio de contraseña obligatorio completado");
} else {
    audit_log($user_id, "Cambio de contraseña voluntario");
}

$_SESSION['success'] = "Contraseña actualizada exitosamente";
header("Location: ../plataforma/principal.php");
exit;