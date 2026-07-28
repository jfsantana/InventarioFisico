<?php

define('APP_NAME', 'Inventario Fisico');

// Determinar la URL base dinámicamente
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptName = str_replace('/public/index.php', '', $_SERVER['SCRIPT_NAME']);
define('APP_URL', $protocol . '://' . $host . $scriptName);

define('DB_HOST', 'localhost');
define('DB_NAME', 'inventariofisico');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('SESSION_IDLE_SECONDS', 14400);
define('SESSION_REMEMBER_SECONDS', 28800);
define('SALIDA_REAUTH_SECONDS', 900);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCK_SECONDS', 300);
