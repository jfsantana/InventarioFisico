<?php

class Producto extends BaseModel
{
    public function listar(): array
    {
        $statement = $this->db->query('SELECT idProducto, codigoInterno, nombre FROM Producto ORDER BY idProducto DESC');

        return $statement->fetchAll();
    }
}
