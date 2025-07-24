<?php
// validar_contrasena.php - Lógica de validación de contraseñas

/**
 * Valida los requisitos para cambio de contraseña
 * @throws Exception Mensajes de error descriptivos
 */

// Verificar que las variables necesarias estén definidas
if (!isset($new_pass, $confirm_pass, $usuario_data)) {
    throw new Exception("Datos incompletos para la validación");
}

// Verificar que las contraseñas no estén vacías
if (empty($new_pass) || empty($confirm_pass)) {
    throw new Exception("Todos los campos son obligatorios");
}

// Validar coincidencia de contraseñas
if ($new_pass !== $confirm_pass) {
    throw new Exception("Las contraseñas no coinciden");
}

// Validar longitud mínima
if (strlen($new_pass) < 8) {
    throw new Exception("La contraseña debe tener al menos 8 caracteres");
}

// Validar complejidad
$has_uppercase = preg_match('/[A-Z]/', $new_pass);
$has_lowercase = preg_match('/[a-z]/', $new_pass);
$has_number = preg_match('/[0-9]/', $new_pass);
$has_special = preg_match('/[^A-Za-z0-9]/', $new_pass);

$complexity_score = ($has_uppercase ? 1 : 0) + 
                   ($has_lowercase ? 1 : 0) + 
                   ($has_number ? 1 : 0) + 
                   ($has_special ? 1 : 0);

if ($complexity_score < 3) {
    throw new Exception("La contraseña debe contener al menos 3 de estos elementos: mayúsculas, minúsculas, números o caracteres especiales");
}

// Verificar contraseñas comunes
$common_passwords = [
    'password', '12345678', 'qwerty123', 'admin123', 'bienvenido'
];

if (in_array(strtolower($new_pass), $common_passwords)) {
    throw new Exception("La contraseña es demasiado común. Elija una más segura");
}

// Validar si la nueva contraseña es diferente a la actual (solo para cambios no obligatorios)
if (!$required_change && isset($current_pass) && password_verify($new_pass, $current_pass)) {
    throw new Exception("La nueva contraseña debe ser diferente a la actual");
}

// Validar similitud con el nombre de usuario
if (isset($usuario_data['usr'])) {
    $similarity_threshold = 70;
    similar_text(strtolower($new_pass), strtolower($usuario_data['usr']), $similarity);
    if ($similarity >= $similarity_threshold) {
        throw new Exception("La contraseña es demasiado similar a tu nombre de usuario");
    }
}