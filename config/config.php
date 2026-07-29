<?php

require_once __DIR__ . '/../app/Core/helpers.php';

define('APP_NAME', 'Inventario Fisico');

$appUrl = getenv('APP_URL');

if (!$appUrl) {
	$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
		|| (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
	$protocol = $isHttps ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/public/index.php');
	$basePath = str_replace('\\', '/', dirname($scriptName));
	$basePath = rtrim(str_replace('/public', '', $basePath), '/');
	$basePath = $basePath === '.' || $basePath === '/' ? '' : $basePath;
	$appUrl = $protocol . '://' . $host . ($basePath === '' ? '' : $basePath);
}

define('APP_URL', rtrim($appUrl, '/'));

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
