<?php

class AuthSchema
{
    public static function ensure(): void
    {
        $db = (new Database())->getConnection();

        $db->exec("CREATE TABLE IF NOT EXISTS roles (
            id_rol INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(60) NOT NULL UNIQUE,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS usuarios (
            id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre_completo VARCHAR(140) NOT NULL,
            username VARCHAR(60) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            id_rol INT UNSIGNED NOT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            ultimo_acceso DATETIME NULL,
            intentos_fallidos INT UNSIGNED NOT NULL DEFAULT 0,
            bloqueado_hasta DATETIME NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_usuarios_rol (id_rol),
            CONSTRAINT fk_usuarios_roles FOREIGN KEY (id_rol) REFERENCES roles (id_rol)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS permisos_modulo (
            id_permiso INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            id_rol INT UNSIGNED NOT NULL,
            modulo VARCHAR(80) NOT NULL,
            puede_ver TINYINT(1) NOT NULL DEFAULT 0,
            puede_editar TINYINT(1) NOT NULL DEFAULT 0,
            puede_borrar TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY uq_permiso_modulo (id_rol, modulo),
            CONSTRAINT fk_permisos_roles FOREIGN KEY (id_rol) REFERENCES roles (id_rol)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS log_accesos (
            id_log BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT UNSIGNED NULL,
            username VARCHAR(60) NULL,
            modulo VARCHAR(80) NOT NULL,
            accion VARCHAR(80) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            resultado VARCHAR(20) NOT NULL,
            detalle VARCHAR(255) NULL,
            fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_log_fecha (fecha),
            INDEX idx_log_usuario (id_usuario),
            INDEX idx_log_modulo (modulo),
            CONSTRAINT fk_log_usuarios FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::migrateExistingTables($db);
        self::seedRoles($db);
        self::seedPermissions($db);
        self::synchronizeRolePermissions($db);
        self::seedAdmin($db);
    }

    private static function migrateExistingTables(PDO $db): void
    {
        if (!self::hasColumn($db, 'roles', 'nombre')) {
            $db->exec('ALTER TABLE roles ADD COLUMN nombre VARCHAR(60) NULL');
        }

        if (self::hasColumn($db, 'roles', 'nombre_rol')) {
            $db->exec('UPDATE roles SET nombre = nombre_rol WHERE nombre IS NULL OR nombre = ""');
        }

        if (!self::hasColumn($db, 'usuarios', 'intentos_fallidos')) {
            $db->exec('ALTER TABLE usuarios ADD COLUMN intentos_fallidos INT UNSIGNED NOT NULL DEFAULT 0');
        }

        if (!self::hasColumn($db, 'usuarios', 'bloqueado_hasta')) {
            $db->exec('ALTER TABLE usuarios ADD COLUMN bloqueado_hasta DATETIME NULL');
        }

        if (!self::hasColumn($db, 'usuarios', 'actualizado_en')) {
            $db->exec('ALTER TABLE usuarios ADD COLUMN actualizado_en DATETIME NULL');
        }

        if (!self::hasColumn($db, 'log_accesos', 'ip')) {
            $db->exec('ALTER TABLE log_accesos ADD COLUMN ip VARCHAR(45) NULL');
        }

        if (self::hasColumn($db, 'log_accesos', 'ip_address')) {
            $db->exec('UPDATE log_accesos SET ip = ip_address WHERE ip IS NULL OR ip = ""');
        }

        if (!self::hasColumn($db, 'log_accesos', 'resultado')) {
            $db->exec('ALTER TABLE log_accesos ADD COLUMN resultado VARCHAR(20) NULL');
        }

        if (self::hasColumn($db, 'log_accesos', 'exitoso')) {
            $db->exec('UPDATE log_accesos SET resultado = CASE WHEN exitoso = 1 THEN "exitoso" ELSE "fallo" END WHERE resultado IS NULL OR resultado = ""');
        }

        if (!self::hasColumn($db, 'log_accesos', 'detalle')) {
            $db->exec('ALTER TABLE log_accesos ADD COLUMN detalle VARCHAR(255) NULL');
        }

        if (!self::hasColumn($db, 'log_accesos', 'fecha')) {
            $db->exec('ALTER TABLE log_accesos ADD COLUMN fecha DATETIME NULL');
        }

        if (self::hasColumn($db, 'log_accesos', 'fecha_hora')) {
            $db->exec('UPDATE log_accesos SET fecha = fecha_hora WHERE fecha IS NULL');
        }

        $db->exec('DELETE pm1 FROM permisos_modulo pm1 INNER JOIN permisos_modulo pm2 ON pm1.id_rol = pm2.id_rol AND pm1.modulo = pm2.modulo AND pm1.id_permiso > pm2.id_permiso');

        if (!self::hasIndex($db, 'permisos_modulo', 'uq_permiso_modulo')) {
            $db->exec('ALTER TABLE permisos_modulo ADD UNIQUE KEY uq_permiso_modulo (id_rol, modulo)');
        }
    }

    private static function seedRoles(PDO $db): void
    {
        $roles = ['Administrador', 'Supervisor', 'Operador', 'Solo lectura'];
        $hasNombreRol = self::hasColumn($db, 'roles', 'nombre_rol');
        $sql = $hasNombreRol
            ? 'INSERT IGNORE INTO roles (nombre, nombre_rol, activo) VALUES (:nombre, :nombre_rol, 1)'
            : 'INSERT IGNORE INTO roles (nombre, activo) VALUES (:nombre, 1)';
        $stmt = $db->prepare($sql);

        foreach ($roles as $rol) {
            $params = ['nombre' => $rol];
            if ($hasNombreRol) {
                $params['nombre_rol'] = $rol;
            }
            $stmt->execute($params);
        }
    }

    private static function seedPermissions(PDO $db): void
    {
        $roles = $db->query('SELECT id_rol, nombre FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
        $modules = ['entrada', 'salida', 'predespacho', 'corregir_entradas', 'corregir_salidas', 'reporte_lote', 'inteligencia', 'administracion'];
        $stmt = $db->prepare('INSERT IGNORE INTO permisos_modulo (id_rol, modulo, puede_ver, puede_editar, puede_borrar) VALUES (:id_rol, :modulo, :puede_ver, :puede_editar, :puede_borrar)');

        foreach ($roles as $idRol => $nombre) {
            foreach ($modules as $module) {
                $permission = self::permissionFor($nombre, $module);
                $exists = $db->prepare('SELECT COUNT(*) FROM permisos_modulo WHERE id_rol = :id_rol AND modulo = :modulo');
                $exists->execute([
                    'id_rol' => $idRol,
                    'modulo' => $module,
                ]);

                if ((int) $exists->fetchColumn() > 0) {
                    continue;
                }

                $stmt->execute([
                    'id_rol' => $idRol,
                    'modulo' => $module,
                    'puede_ver' => $permission[0],
                    'puede_editar' => $permission[1],
                    'puede_borrar' => $permission[2],
                ]);
            }
        }
    }

    private static function permissionFor(string $role, string $module): array
    {
        if ($role === 'Administrador') {
            return [1, 1, 1];
        }

        if ($role === 'Supervisor') {
            if (in_array($module, ['entrada', 'salida', 'predespacho', 'corregir_entradas', 'corregir_salidas'], true)) {
                return [1, 1, 0];
            }

            if (in_array($module, ['reporte_lote', 'inteligencia'], true)) {
                return [1, 0, 0];
            }

            return [0, 0, 0];
        }

        if ($role === 'Operador') {
            return $module === 'salida' ? [1, 1, 0] : [0, 0, 0];
        }

        if ($role === 'Solo lectura') {
            return in_array($module, ['reporte_lote', 'inteligencia'], true) ? [1, 0, 0] : [0, 0, 0];
        }

        return [0, 0, 0];
    }

    private static function synchronizeRolePermissions(PDO $db): void
    {
        $roles = $db->query('SELECT id_rol, nombre FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
        $stmt = $db->prepare(
            'UPDATE permisos_modulo
             SET puede_ver = :puede_ver,
                 puede_editar = :puede_editar,
                 puede_borrar = :puede_borrar
             WHERE id_rol = :id_rol AND modulo = :modulo'
        );

        foreach ($roles as $idRol => $role) {
            foreach (['entrada', 'salida', 'predespacho', 'corregir_entradas', 'corregir_salidas', 'reporte_lote', 'inteligencia', 'administracion'] as $module) {
                $permission = self::permissionFor($role, $module);
                $stmt->execute([
                    'puede_ver' => $permission[0],
                    'puede_editar' => $permission[1],
                    'puede_borrar' => $permission[2],
                    'id_rol' => $idRol,
                    'modulo' => $module,
                ]);
            }
        }
    }

    private static function seedAdmin(PDO $db): void
    {
        $exists = (int) $db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
        if ($exists > 0) {
            return;
        }

        $stmt = $db->prepare('SELECT id_rol FROM roles WHERE nombre = :nombre LIMIT 1');
        $stmt->execute(['nombre' => 'Administrador']);
        $idRol = (int) $stmt->fetchColumn();

        $insert = $db->prepare('INSERT INTO usuarios (nombre_completo, username, password_hash, id_rol, activo) VALUES (:nombre_completo, :username, :password_hash, :id_rol, 1)');
        $insert->execute([
            'nombre_completo' => 'Administrador del sistema',
            'username' => 'admin',
            'password_hash' => password_hash('Admin123!', PASSWORD_BCRYPT),
            'id_rol' => $idRol,
        ]);
    }

    private static function hasColumn(PDO $db, string $table, string $column): bool
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
        $stmt->execute([
            'table' => $table,
            'column' => $column,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function hasIndex(PDO $db, string $table, string $index): bool
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index_name');
        $stmt->execute([
            'table' => $table,
            'index_name' => $index,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}