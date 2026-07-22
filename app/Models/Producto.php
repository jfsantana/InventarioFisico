<?php

class Producto extends BaseModel
{
    public function listar(): array
    {
        $statement = $this->db->query('SELECT id, nombre, cantidad FROM productos ORDER BY id DESC');

        return $statement->fetchAll();
    }
}
