<?php

class Usuario extends BaseModel
{
    public function listar(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(u.nombre_completo LIKE :q OR u.username LIKE :q)';
            $params['q'] = '%' . trim($filters['q']) . '%';
        }

        if (!empty($filters['id_rol']) && filter_var($filters['id_rol'], FILTER_VALIDATE_INT)) {
            $where[] = 'u.id_rol = :id_rol';
            $params['id_rol'] = (int) $filters['id_rol'];
        }

        if (isset($filters['estado']) && $filters['estado'] !== '') {
            $where[] = 'u.activo = :activo';
            $params['activo'] = (int) $filters['estado'];
        }

        $sql = 'SELECT u.id_usuario, u.nombre_completo, u.username, u.activo, u.ultimo_acceso, r.nombre AS rol_nombre, r.id_rol
            FROM usuarios u
            INNER JOIN roles r ON r.id_rol = u.id_rol';

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY u.nombre_completo ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function crear(array $data, string $password): bool
    {
        $stmt = $this->db->prepare('INSERT INTO usuarios (nombre_completo, username, password_hash, id_rol, activo) VALUES (:nombre_completo, :username, :password_hash, :id_rol, :activo)');

        return $stmt->execute([
            'nombre_completo' => $data['nombre_completo'],
            'username' => $data['username'],
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'id_rol' => $data['id_rol'],
            'activo' => $data['activo'],
        ]);
    }

    public function actualizar(int $idUsuario, array $data): bool
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET nombre_completo = :nombre_completo, username = :username, id_rol = :id_rol, activo = :activo WHERE id_usuario = :id_usuario');

        return $stmt->execute([
            'nombre_completo' => $data['nombre_completo'],
            'username' => $data['username'],
            'id_rol' => $data['id_rol'],
            'activo' => $data['activo'],
            'id_usuario' => $idUsuario,
        ]);
    }

    public function cambiarClave(int $idUsuario, string $password): bool
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET password_hash = :password_hash, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_usuario = :id_usuario');

        return $stmt->execute([
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'id_usuario' => $idUsuario,
        ]);
    }

    public function obtener(int $idUsuario): ?array
    {
        $stmt = $this->db->prepare('SELECT u.*, r.nombre AS rol_nombre FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol WHERE u.id_usuario = :id_usuario');
        $stmt->execute(['id_usuario' => $idUsuario]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function cambiarEstado(int $idUsuario, int $activo): bool
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET activo = :activo WHERE id_usuario = :id_usuario');

        return $stmt->execute([
            'activo' => $activo,
            'id_usuario' => $idUsuario,
        ]);
    }

    public function eliminar(int $idUsuario): bool
    {
        $stmt = $this->db->prepare('DELETE FROM usuarios WHERE id_usuario = :id_usuario');
        return $stmt->execute(['id_usuario' => $idUsuario]);
    }

    public function esUnicoAdministradorActivo(int $idUsuario): bool
    {
        $stmt = $this->db->prepare("SELECT r.nombre, u.activo FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol WHERE u.id_usuario = :id_usuario");
        $stmt->execute(['id_usuario' => $idUsuario]);
        $user = $stmt->fetch();

        if (!$user || $user['nombre'] !== 'Administrador' || (int) $user['activo'] !== 1) {
            return false;
        }

        $count = (int) $this->db->query("SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol WHERE r.nombre = 'Administrador' AND u.activo = 1")->fetchColumn();

        return $count <= 1;
    }
}