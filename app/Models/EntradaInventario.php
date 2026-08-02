<?php

class EntradaInventario extends BaseModel
{
    public function obtenerProductos(): array
    {
        $statement = $this->db->query('SELECT idProducto, nombre FROM Producto ORDER BY nombre ASC');

        return $statement->fetchAll();
    }

    public function obtenerPresentaciones(): array
    {
        $statement = $this->db->query('SELECT idPresentacion, nombre FROM presentacion ORDER BY nombre ASC');

        return $statement->fetchAll();
    }

    public function obtenerUbicaciones(): array
    {
        $statement = $this->db->query('SELECT idUbicacion, nombre FROM ubicacion ORDER BY nombre ASC');

        return $statement->fetchAll();
    }

    public function registrarEntrada(array $data): bool
    {
        $statement = $this->db->prepare(
            'INSERT INTO inventarioentrante (NumLote, idProducto, idPresentacion, `idUbicación`, CantidadEntrante, fecha, sector)
             VALUES (:numLote, :idProducto, :idPresentacion, :idUbicacion, :cantidadEntrante, CURDATE(), :sector)'
        );

        return $statement->execute([
            'numLote' => $data['NumLote'],
            'idProducto' => $data['idProducto'],
            'idPresentacion' => $data['idPresentacion'],
            'idUbicacion' => $data['idUbicacion'],
            'cantidadEntrante' => $data['CantidadEntrante'],
            'sector' => $data['Sector'],
        ]);
    }

    public function obtenerEntradas(): array
    {
        $statement = $this->db->query(
            'SELECT ie.idInventarioEntrante,
                    ie.NumLote,
                    ie.idProducto,
                    p.nombre AS producto,
                    ie.idPresentacion,
                    pr.nombre AS presentacion,
                    ie.`idUbicación` AS idUbicacion,
                    u.nombre AS ubicacion,
                    ie.sector AS Sector,
                    ie.CantidadEntrante,
                    ie.fecha,
                    COALESCE(SUM(ins.cantidadSaliente), 0) AS salidaTotal,
                    ie.CantidadEntrante - COALESCE(SUM(ins.cantidadSaliente), 0) AS disponible
             FROM inventarioentrante ie
             INNER JOIN Producto p ON p.idProducto = ie.idProducto
             INNER JOIN presentacion pr ON pr.idPresentacion = ie.idPresentacion
             INNER JOIN ubicacion u ON u.idUbicacion = ie.`idUbicación`
             LEFT JOIN inventariosaliente ins ON ins.idInventarioEntrante = ie.idInventarioEntrante
               GROUP BY ie.idInventarioEntrante, ie.NumLote, ie.idProducto, p.nombre, ie.idPresentacion, pr.nombre, ie.`idUbicación`, u.nombre, ie.sector, ie.CantidadEntrante, ie.fecha
             ORDER BY ie.fecha DESC, ie.idInventarioEntrante DESC'
        );

        return $statement->fetchAll();
    }

    public function obtenerSalidaTotal(int $idInventarioEntrante): float
    {
        $statement = $this->db->prepare(
            'SELECT COALESCE(SUM(cantidadSaliente), 0) AS salidaTotal
             FROM inventariosaliente
             WHERE idInventarioEntrante = :idInventarioEntrante'
        );
        $statement->execute(['idInventarioEntrante' => $idInventarioEntrante]);
        $resultado = $statement->fetch();

        return (float) ($resultado['salidaTotal'] ?? 0);
    }

    public function actualizarEntrada(int $idInventarioEntrante, array $data): bool
    {
        $statement = $this->db->prepare(
            'UPDATE inventarioentrante
             SET NumLote = :numLote,
                 idProducto = :idProducto,
                 idPresentacion = :idPresentacion,
                 `idUbicación` = :idUbicacion,
                 sector = :sector,
                 CantidadEntrante = :cantidadEntrante
             WHERE idInventarioEntrante = :idInventarioEntrante'
        );

        return $statement->execute([
            'numLote' => $data['NumLote'],
            'idProducto' => $data['idProducto'],
            'idPresentacion' => $data['idPresentacion'],
            'idUbicacion' => $data['idUbicacion'],
            'sector' => $data['Sector'],
            'cantidadEntrante' => $data['CantidadEntrante'],
            'idInventarioEntrante' => $idInventarioEntrante,
        ]);
    }

    public function eliminarEntrada(int $idInventarioEntrante): bool
    {
        $statement = $this->db->prepare(
            'DELETE FROM inventarioentrante
             WHERE idInventarioEntrante = :idInventarioEntrante'
        );

        $statement->execute(['idInventarioEntrante' => $idInventarioEntrante]);

        return $statement->rowCount() > 0;
    }
}
