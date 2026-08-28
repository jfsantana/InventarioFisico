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

$smtpLocalFile = __DIR__ . '/smtp.local.php';
$smtpLocal = file_exists($smtpLocalFile) ? require $smtpLocalFile : [];

define('SMTP_HOST', getenv('SMTP_HOST') ?: ($smtpLocal['host'] ?? 'mail.adyarca.com'));
define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: ($smtpLocal['port'] ?? 465)));
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: ($smtpLocal['encryption'] ?? 'ssl'));
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: ($smtpLocal['username'] ?? 'notificaciones@adyarca.com'));
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: ($smtpLocal['password'] ?? ''));
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: ($smtpLocal['from_email'] ?? SMTP_USERNAME));
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: ($smtpLocal['from_name'] ?? 'Inventario Fisico - Adyarca'));

define('SESSION_IDLE_SECONDS', 14400);
define('SESSION_REMEMBER_SECONDS', 28800);
define('SALIDA_REAUTH_SECONDS', 900);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCK_SECONDS', 300);
