<?php

class ContactoInternoEmail extends BaseModel
{
    public function listar(array $filters = []): array
    {
        $sql = 'SELECT id, nombre, email, cargo, proceso FROM contactosinternosemail';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' WHERE nombre LIKE :nombre OR email LIKE :email OR cargo LIKE :cargo OR proceso LIKE :proceso';
            $query = '%' . trim($filters['q']) . '%';
            $params = [
                'nombre' => $query,
                'email' => $query,
                'cargo' => $query,
                'proceso' => $query,
            ];
        }

        $sql .= ' ORDER BY proceso ASC, nombre ASC';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function obtener(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, nombre, email, cargo, proceso
             FROM contactosinternosemail
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $contacto = $statement->fetch();

        return $contacto ?: null;
    }

    public function crear(array $data): bool
    {
        $statement = $this->db->prepare(
            'INSERT INTO contactosinternosemail (nombre, email, cargo, proceso)
             VALUES (:nombre, :email, :cargo, :proceso)'
        );

        return $statement->execute($data);
    }

    public function actualizar(int $id, array $data): bool
    {
        $statement = $this->db->prepare(
            'UPDATE contactosinternosemail
             SET nombre = :nombre, email = :email, cargo = :cargo, proceso = :proceso
             WHERE id = :id'
        );

        return $statement->execute($data + ['id' => $id]);
    }

    public function eliminar(int $id): bool
    {
        $statement = $this->db->prepare('DELETE FROM contactosinternosemail WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }
}