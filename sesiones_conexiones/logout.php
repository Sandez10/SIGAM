<?php
require_once 'sesion_config.php';
session_destroy(); // Destruir la sesión

// Redirige al inicio de sesión
header("Location: ../");
exit();
?>
