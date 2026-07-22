<?php

class ReporteInventario extends BaseModel
{
    public function obtenerProductos(): array
    {
        $statement = $this->db->query(
            'SELECT DISTINCT p.idProducto, p.nombre
             FROM Producto p
             INNER JOIN inventarioentrante ie ON ie.idProducto = p.idProducto
             ORDER BY p.nombre ASC'
        );

        return $statement->fetchAll();
    }

    public function obtenerLotesPorProducto(int $idProducto): array
    {
        $statement = $this->db->prepare(
            'SELECT idInventarioEntrante, NumLote, fecha
             FROM inventarioentrante
             WHERE idProducto = :idProducto
             ORDER BY fecha DESC, NumLote ASC'
        );
        $statement->execute(['idProducto' => $idProducto]);

        return $statement->fetchAll();
    }

    public function obtenerEncabezadoLote(int $idInventarioEntrante, int $idProducto): ?array
    {
        $statement = $this->db->prepare(
            'SELECT ie.idInventarioEntrante,
                    ie.NumLote,
                    ie.CantidadEntrante,
                    ie.fecha,
                    p.nombre AS producto,
                    pr.nombre AS presentacion,
                    u.nombre AS ubicacion
             FROM inventarioentrante ie
             INNER JOIN Producto p ON p.idProducto = ie.idProducto
             INNER JOIN presentacion pr ON pr.idPresentacion = ie.idPresentacion
             INNER JOIN ubicacion u ON u.idUbicacion = ie.`idUbicación`
             WHERE ie.idInventarioEntrante = :idInventarioEntrante
               AND ie.idProducto = :idProducto'
        );
        $statement->execute([
            'idInventarioEntrante' => $idInventarioEntrante,
            'idProducto' => $idProducto,
        ]);

        $encabezado = $statement->fetch();

        return $encabezado ?: null;
    }

    public function obtenerSalidasPorLote(int $idInventarioEntrante): array
    {
        $statement = $this->db->prepare(
            'SELECT idInventarioSaliente, NE, cantidadSaliente, fecha
             FROM inventariosaliente
             WHERE idInventarioEntrante = :idInventarioEntrante
             ORDER BY fecha ASC, idInventarioSaliente ASC'
        );
        $statement->execute(['idInventarioEntrante' => $idInventarioEntrante]);

        return $statement->fetchAll();
    }

    public function construirMovimientos(array $encabezado, array $salidas): array
    {
        $saldo = (float) $encabezado['CantidadEntrante'];
        $movimientos = [[
            'fecha' => $encabezado['fecha'],
            'ne' => '',
            'entrada' => $encabezado['CantidadEntrante'],
            'salida' => '',
            'saldo' => $saldo,
            'observaciones' => 'Entrada inicial del lote',
        ]];

        foreach ($salidas as $salida) {
            $cantidadSaliente = (float) $salida['cantidadSaliente'];
            $saldo -= $cantidadSaliente;

            $movimientos[] = [
                'fecha' => $salida['fecha'],
                'ne' => $salida['NE'],
                'entrada' => '',
                'salida' => $cantidadSaliente,
                'saldo' => $saldo,
                'observaciones' => 'Salida relacionada al lote',
            ];
        }

        return $movimientos;
    }
}
