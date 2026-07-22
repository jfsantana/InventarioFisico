<?php

class SalidaInventario extends BaseModel
{
    public function obtenerProductos(): array
    {
        $statement = $this->db->query('SELECT idProducto, nombre FROM Producto ORDER BY nombre ASC');

        return $statement->fetchAll();
    }

    public function obtenerLotesPorProducto(int $idProducto): array
    {
        $this->asegurarTablaInventarioSaliente();

        $statement = $this->db->prepare(
            'SELECT ie.idInventarioEntrante,
                    ie.NumLote,
                    ie.CantidadEntrante,
                    ie.fecha,
                    ie.CantidadEntrante - COALESCE(SUM(ins.cantidadSaliente), 0) AS Disponible
             FROM inventarioentrante ie
             LEFT JOIN inventariosaliente ins
                    ON ins.idInventarioEntrante = ie.idInventarioEntrante
             WHERE ie.idProducto = :idProducto
             GROUP BY ie.idInventarioEntrante, ie.NumLote, ie.CantidadEntrante, ie.fecha
             HAVING Disponible > 0
             ORDER BY ie.fecha DESC, ie.idInventarioEntrante DESC'
        );
        $statement->execute(['idProducto' => $idProducto]);

        return $statement->fetchAll();
    }

    public function obtenerLoteParaProducto(int $idInventarioEntrante, int $idProducto): ?array
    {
        $this->asegurarTablaInventarioSaliente();

        $statement = $this->db->prepare(
            'SELECT ie.idInventarioEntrante,
                    ie.NumLote,
                    ie.CantidadEntrante,
                    ie.CantidadEntrante - COALESCE(SUM(ins.cantidadSaliente), 0) AS Disponible
             FROM inventarioentrante ie
             LEFT JOIN inventariosaliente ins
                    ON ins.idInventarioEntrante = ie.idInventarioEntrante
             WHERE ie.idInventarioEntrante = :idInventarioEntrante
               AND ie.idProducto = :idProducto
             GROUP BY ie.idInventarioEntrante, ie.NumLote, ie.CantidadEntrante'
        );
        $statement->execute([
            'idInventarioEntrante' => $idInventarioEntrante,
            'idProducto' => $idProducto,
        ]);

        $lote = $statement->fetch();

        return $lote ?: null;
    }

    public function registrarSalida(int $idInventarioEntrante, string $ne, float $cantidadSaliente): bool
    {
        $this->asegurarTablaInventarioSaliente();

        $statement = $this->db->prepare(
            'INSERT INTO inventariosaliente (idInventarioEntrante, NE, cantidadSaliente, fecha)
             VALUES (:idInventarioEntrante, :ne, :cantidadSaliente, NOW())'
        );

        return $statement->execute([
            'idInventarioEntrante' => $idInventarioEntrante,
            'ne' => $ne,
            'cantidadSaliente' => $cantidadSaliente,
        ]);
    }

    private function asegurarTablaInventarioSaliente(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS inventariosaliente (
                idInventarioSaliente INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                idInventarioEntrante INT UNSIGNED NOT NULL,
                NE VARCHAR(80) NOT NULL,
                cantidadSaliente DECIMAL(12, 2) NOT NULL,
                fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_inventariosaliente_entrante (idInventarioEntrante)
            )'
        );
    }
}
