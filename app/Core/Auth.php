<?php

class Auth
{
    public static function boot(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        if (self::check() && self::isExpired()) {
            self::logout(false);
            $_SESSION['flash_error'] = 'Sesion expirada';
            return;
        }

        if (self::check()) {
            $_SESSION['last_activity'] = time();
        }
    }

    public static function check(): bool
    {
        return !empty($_SESSION['id_usuario']);
    }

    public static function user(): array
    {
        return [
            'id_usuario' => $_SESSION['id_usuario'] ?? null,
            'nombre_completo' => $_SESSION['nombre_completo'] ?? '',
            'username' => $_SESSION['username'] ?? '',
            'id_rol' => $_SESSION['id_rol'] ?? null,
            'rol_nombre' => $_SESSION['rol_nombre'] ?? '',
            'permisos' => $_SESSION['permisos'] ?? [],
        ];
    }

    public static function login(string $username, string $password, bool $remember): bool
    {
        $username = trim($username);
        $db = (new Database())->getConnection();
        $stmt = $db->prepare('SELECT u.*, r.nombre AS rol_nombre FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol WHERE u.username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user) {
            self::log(null, $username, 'login', 'login', 'fallo', 'Credenciales invalidas');
            return false;
        }

        if ((int) $user['activo'] !== 1) {
            self::log((int) $user['id_usuario'], $username, 'login', 'login', 'fallo', 'Usuario inactivo');
            return false;
        }

        if (!empty($user['bloqueado_hasta']) && strtotime($user['bloqueado_hasta']) > time()) {
            self::log((int) $user['id_usuario'], $username, 'login', 'login', 'fallo', 'Usuario bloqueado temporalmente');
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            self::registerFailedAttempt($db, $user);
            self::log((int) $user['id_usuario'], $username, 'login', 'login', 'fallo', 'Credenciales invalidas');
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['id_usuario'] = (int) $user['id_usuario'];
        $_SESSION['nombre_completo'] = $user['nombre_completo'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['usuario'] = $user['username'];
        $_SESSION['id_rol'] = (int) $user['id_rol'];
        $_SESSION['rol_nombre'] = $user['rol_nombre'];
        $_SESSION['permisos'] = self::loadPermissions((int) $user['id_rol']);
        $_SESSION['last_activity'] = time();
        $_SESSION['authenticated_at'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        if ($remember) {
            setcookie(session_name(), session_id(), [
                'expires' => time() + SESSION_REMEMBER_SECONDS,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        $update = $db->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW() WHERE id_usuario = :id_usuario');
        $update->execute(['id_usuario' => (int) $user['id_usuario']]);
        self::log((int) $user['id_usuario'], $username, 'login', 'login', 'exitoso', 'Ingreso correcto');

        return true;
    }

    public static function logout(bool $regenerate = true): void
    {
        $user = self::user();
        if (!empty($user['id_usuario'])) {
            self::log((int) $user['id_usuario'], $user['username'], 'login', 'logout', 'exitoso', 'Cierre de sesion');
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
        }
        session_destroy();

        if ($regenerate) {
            session_start();
        }
    }

    public static function can(string $module, string $action = 'ver'): bool
    {
        $key = 'puede_' . $action;
        return !empty($_SESSION['permisos'][$module][$key]);
    }

    public static function requireLogin(): void
    {
        if (self::check()) {
            return;
        }

        $_SESSION['flash_error'] = 'Sesion expirada';
        header('Location: ' . APP_URL . '/login');
        exit;
    }

    public static function requirePermission(string $module, string $action = 'ver'): void
    {
        self::requireLogin();
        if (self::can($module, $action)) {
            return;
        }

        http_response_code(403);
        $title = 'Acceso denegado';
        require __DIR__ . '/../Views/errors/403.php';
        exit;
    }

    public static function requireRecentLogin(int $seconds = SALIDA_REAUTH_SECONDS): void
    {
        self::requireLogin();

        $authenticatedAt = (int) ($_SESSION['authenticated_at'] ?? 0);
        if ($authenticatedAt > 0 && (time() - $authenticatedAt) <= $seconds) {
            return;
        }

        self::logout(false);
        session_start();
        $_SESSION['flash_error'] = 'Por seguridad, vuelve a iniciar sesión para usar Registrar entrega.';
        header('Location: ' . APP_URL . '/login');
        exit;
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (($_SESSION['rol_nombre'] ?? '') === 'Administrador') {
            return;
        }

        http_response_code(403);
        $title = 'Acceso denegado';
        require __DIR__ . '/../Views/errors/403.php';
        exit;
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function validateCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? '';
        return is_string($token) && hash_equals(self::csrfToken(), $token);
    }

    public static function flashError(): ?string
    {
        $message = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);
        return $message;
    }

    public static function log(?int $idUsuario, ?string $username, string $module, string $action, string $result, ?string $detail = null): void
    {
        try {
            $db = (new Database())->getConnection();
            $stmt = $db->prepare('INSERT INTO log_accesos (id_usuario, username, modulo, accion, ip, resultado, detalle, fecha) VALUES (:id_usuario, :username, :modulo, :accion, :ip, :resultado, :detalle, NOW())');
            $stmt->execute([
                'id_usuario' => $idUsuario,
                'username' => $username,
                'modulo' => $module,
                'accion' => $action,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'resultado' => $result,
                'detalle' => $detail,
            ]);
        } catch (Throwable $exception) {
        }
    }

    private static function isExpired(): bool
    {
        $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
        return $lastActivity > 0 && (time() - $lastActivity) > SESSION_IDLE_SECONDS;
    }

    private static function registerFailedAttempt(PDO $db, array $user): void
    {
        $attempts = (int) $user['intentos_fallidos'] + 1;
        $blockedUntil = $attempts >= LOGIN_MAX_ATTEMPTS ? date('Y-m-d H:i:s', time() + LOGIN_LOCK_SECONDS) : null;
        $stmt = $db->prepare('UPDATE usuarios SET intentos_fallidos = :intentos_fallidos, bloqueado_hasta = :bloqueado_hasta WHERE id_usuario = :id_usuario');
        $stmt->execute([
            'intentos_fallidos' => $attempts,
            'bloqueado_hasta' => $blockedUntil,
            'id_usuario' => (int) $user['id_usuario'],
        ]);
    }

    private static function loadPermissions(int $idRol): array
    {
        $db = (new Database())->getConnection();
        $stmt = $db->prepare('SELECT modulo, puede_ver, puede_editar, puede_borrar FROM permisos_modulo WHERE id_rol = :id_rol');
        $stmt->execute(['id_rol' => $idRol]);
        $permissions = [];

        foreach ($stmt->fetchAll() as $row) {
            $permissions[$row['modulo']] = [
                'puede_ver' => (bool) $row['puede_ver'],
                'puede_editar' => (bool) $row['puede_editar'],
                'puede_borrar' => (bool) $row['puede_borrar'],
            ];
        }

        return $permissions;
    }
}