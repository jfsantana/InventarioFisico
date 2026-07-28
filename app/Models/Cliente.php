<?php

class Cliente extends BaseModel
{
    public function obtenerTodosLosClientes(): array
    {
        $statement = $this->db->prepare(
            'SELECT idCliente, rif, nombre, direccion, tipo, activo
             FROM tbl_cliente
             WHERE activo = :activo
             ORDER BY nombre ASC'
        );
        $statement->execute(['activo' => 1]);

        return $statement->fetchAll();
    }

    public function obtenerClientePorId(int $idCliente): ?array
    {
        $statement = $this->db->prepare(
            'SELECT idCliente, rif, nombre, direccion, tipo, activo
             FROM tbl_cliente
             WHERE idCliente = :idCliente
             LIMIT 1'
        );
        $statement->execute(['idCliente' => $idCliente]);
        $cliente = $statement->fetch();

        return $cliente ?: null;
    }

    public function crearCliente(string $rif, string $nombre, string $direccion, string $tipo): int|false
    {
        try {
            $statement = $this->db->prepare(
                'INSERT INTO tbl_cliente (rif, nombre, direccion, tipo)
                 VALUES (:rif, :nombre, :direccion, :tipo)'
            );
            $statement->execute([
                'rif' => $rif,
                'nombre' => $nombre,
                'direccion' => $direccion,
                'tipo' => $tipo,
            ]);

            return (int) $this->db->lastInsertId();
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function actualizarCliente(int $idCliente, string $rif, string $nombre, string $direccion, string $tipo): bool
    {
        try {
            $statement = $this->db->prepare(
                'UPDATE tbl_cliente
                 SET rif = :rif,
                     nombre = :nombre,
                     direccion = :direccion,
                     tipo = :tipo
                 WHERE idCliente = :idCliente'
            );

            return $statement->execute([
                'rif' => $rif,
                'nombre' => $nombre,
                'direccion' => $direccion,
                'tipo' => $tipo,
                'idCliente' => $idCliente,
            ]);
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function desactivarCliente(int $idCliente): bool
    {
        try {
            $statement = $this->db->prepare(
                'UPDATE tbl_cliente
                 SET activo = :activo
                 WHERE idCliente = :idCliente'
            );

            return $statement->execute([
                'activo' => 0,
                'idCliente' => $idCliente,
            ]);
        } catch (Throwable $exception) {
            return false;
        }
    }
}