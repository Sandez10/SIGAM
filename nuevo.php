<?php
require_once 'database/conexion.php'; // Ajusta la ruta si es necesario

// Sanitizar inputs
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Datos del nuevo usuario (puedes recibirlos por formulario o definirlos aquí)
$usuario = sanitizeInput('Cres');
$clave_plana = 'Hola-7u7'; // La contraseña original
$logia = sanitizeInput('Amanecer Dorado');
$rol = 'admin'; // Puede ser: usuario, admin, supersu

// Hashear la contraseña
$clave_encriptada = password_hash($clave_plana, PASSWORD_DEFAULT);

// Conectar e insertar
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("INSERT INTO usuarios (
        usr, clave, logia, rol, password_reset_required
    ) VALUES (?, ?, ?, ?, ?)");

    $reset_required = 1; // Obliga al usuario a cambiar la contraseña en el primer inicio

    $stmt->bind_param("ssssi", $usuario, $clave_encriptada, $logia, $rol, $reset_required);

    if ($stmt->execute()) {
        echo "✅ Usuario insertado correctamente.";
    } else {
        echo "❌ Error al insertar usuario: " . $stmt->error;
    }

    $stmt->close();
} catch (Exception $e) {
    echo "❌ Error en la conexión o ejecución: " . $e->getMessage();
}
?>
