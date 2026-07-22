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
            'INSERT INTO inventarioentrante (NumLote, idProducto, idPresentacion, `idUbicación`, CantidadEntrante, fecha)
             VALUES (:numLote, :idProducto, :idPresentacion, :idUbicacion, :cantidadEntrante, CURDATE())'
        );

        return $statement->execute([
            'numLote' => $data['NumLote'],
            'idProducto' => $data['idProducto'],
            'idPresentacion' => $data['idPresentacion'],
            'idUbicacion' => $data['idUbicacion'],
            'cantidadEntrante' => $data['CantidadEntrante'],
        ]);
    }
}
