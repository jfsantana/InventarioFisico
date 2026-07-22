<?php

class Rol extends BaseModel
{
    public function listarActivos(): array
    {
        return $this->db->query('SELECT id_rol, nombre FROM roles WHERE activo = 1 ORDER BY nombre ASC')->fetchAll();
    }

    public function listarConPermisos(): array
    {
        $roles = $this->db->query('SELECT id_rol, nombre, activo FROM roles ORDER BY id_rol ASC')->fetchAll();
        $stmt = $this->db->query('SELECT id_rol, modulo, puede_ver, puede_editar, puede_borrar FROM permisos_modulo ORDER BY modulo ASC');
        $permissions = [];

        foreach ($stmt->fetchAll() as $permission) {
            $permissions[(int) $permission['id_rol']][] = $permission;
        }

        foreach ($roles as &$role) {
            $role['permisos'] = $permissions[(int) $role['id_rol']] ?? [];
        }

        return $roles;
    }
}