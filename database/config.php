<?php
// config.php

// Configuración de entorno (desarrollo/producción)
define('ENVIRONMENT', 'development'); // Cambiar a 'production' en servidor real más adelante

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_DATABASE', 'sigam');
define('DB_CHARSET', 'utf8mb4');

// Configuración de sesión
/*define('SESSION_NAME', 'SIGAM_SESSION');
define('SESSION_LIFETIME', 1800); // 30 minutos*/