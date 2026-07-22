<?php

class AnaliticaInventario extends BaseModel
{
    public function obtenerProductos(): array
    {
        $statement = $this->db->query('SELECT idProducto, nombre FROM Producto ORDER BY nombre ASC');

        return $statement->fetchAll();
    }

    public function obtenerMovimientos(?int $idProducto, string $desde, string $hasta): array
    {
        $entradaProductFilter = $idProducto ? ' AND ie.idProducto = :idProductoEntrada' : '';
        $salidaProductFilter = $idProducto ? ' AND ie.idProducto = :idProductoSalida' : '';
        $sql = "SELECT ie.idInventarioEntrante,
                   p.nombre AS producto,
                       ie.NumLote,
                       DATE(ie.fecha) AS fecha,
                       ie.CantidadEntrante AS entrada,
                       0 AS salida,
                       'Entrada fisica' AS concepto
                FROM inventarioentrante ie
                INNER JOIN Producto p ON p.idProducto = ie.idProducto
                WHERE DATE(ie.fecha) BETWEEN :desdeEntrada AND :hastaEntrada
                  {$entradaProductFilter}
                UNION ALL
                  SELECT ie.idInventarioEntrante,
                      p.nombre AS producto,
                       ie.NumLote,
                       DATE(ins.fecha) AS fecha,
                       0 AS entrada,
                       ins.cantidadSaliente AS salida,
                       CONCAT('Salida NE ', ins.NE) AS concepto
                FROM inventariosaliente ins
                INNER JOIN inventarioentrante ie ON ie.idInventarioEntrante = ins.idInventarioEntrante
                INNER JOIN Producto p ON p.idProducto = ie.idProducto
                WHERE DATE(ins.fecha) BETWEEN :desdeSalida AND :hastaSalida
                {$salidaProductFilter}
                ORDER BY fecha ASC, concepto ASC";

        $statement = $this->db->prepare($sql);
        $params = [
            'desdeEntrada' => $desde,
            'hastaEntrada' => $hasta,
            'desdeSalida' => $desde,
            'hastaSalida' => $hasta,
        ];

        if ($idProducto) {
            $params['idProductoEntrada'] = $idProducto;
            $params['idProductoSalida'] = $idProducto;
        }

        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function obtenerResumenLotes(?int $idProducto): array
    {
        $productFilter = $idProducto ? ' WHERE ie.idProducto = :idProducto' : '';
        $statement = $this->db->prepare(
            "SELECT ie.idInventarioEntrante,
                ie.idProducto,
                p.nombre AS producto,
                    ie.NumLote,
                    pr.nombre AS presentacion,
                    u.nombre AS ubicacion,
                    ie.fecha AS fechaEntrada,
                    ie.CantidadEntrante,
                    COALESCE(SUM(ins.cantidadSaliente), 0) AS salidaTotal,
                    ie.CantidadEntrante - COALESCE(SUM(ins.cantidadSaliente), 0) AS disponible
             FROM inventarioentrante ie
             INNER JOIN Producto p ON p.idProducto = ie.idProducto
             INNER JOIN presentacion pr ON pr.idPresentacion = ie.idPresentacion
             INNER JOIN ubicacion u ON u.idUbicacion = ie.`idUbicación`
             LEFT JOIN inventariosaliente ins ON ins.idInventarioEntrante = ie.idInventarioEntrante
             {$productFilter}
               GROUP BY ie.idInventarioEntrante, ie.idProducto, p.nombre, ie.NumLote, pr.nombre, u.nombre, ie.fecha, ie.CantidadEntrante
               ORDER BY p.nombre ASC, ie.NumLote ASC, disponible ASC"
        );

        $params = $idProducto ? ['idProducto' => $idProducto] : [];
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function construirIndicadores(array $movimientos, array $lotes, string $desde, string $hasta): array
    {
        $series = $this->construirSerieDiaria($movimientos, $desde, $hasta);
        $totalEntrada = array_sum(array_column($series, 'entrada'));
        $totalSalida = array_sum(array_column($series, 'salida'));
        $inventarioDisponible = array_sum(array_map(fn ($lote) => max(0, (float) $lote['disponible']), $lotes));
        $diasPeriodo = max(1, (new DateTime($desde))->diff(new DateTime($hasta))->days + 1);
        $promedioSalidaDiaria = $totalSalida / $diasPeriodo;
        $diasCobertura = $promedioSalidaDiaria > 0 ? $inventarioDisponible / $promedioSalidaDiaria : null;
        $rotacionPeriodo = $totalEntrada > 0 ? ($totalSalida / $totalEntrada) * 100 : 0;
        $salidaPrimerTramo = 0;
        $salidaSegundoTramo = 0;
        $mitad = (int) floor(count($series) / 2);

        foreach ($series as $index => $dia) {
            if ($index < $mitad) {
                $salidaPrimerTramo += $dia['salida'];
                continue;
            }

            $salidaSegundoTramo += $dia['salida'];
        }

        $tendencia = 'estable';
        if ($salidaSegundoTramo > $salidaPrimerTramo * 1.1) {
            $tendencia = 'en aumento';
        } elseif ($salidaSegundoTramo < $salidaPrimerTramo * 0.9) {
            $tendencia = 'en descenso';
        }

        $lotesEnRiesgo = array_values(array_filter($lotes, function ($lote) use ($promedioSalidaDiaria) {
            $disponible = (float) $lote['disponible'];

            if ($disponible <= 0) {
                return true;
            }

            return $promedioSalidaDiaria > 0 && $disponible <= $promedioSalidaDiaria * 7;
        }));

        return [
            'series' => $series,
            'totalEntrada' => $totalEntrada,
            'totalSalida' => $totalSalida,
            'inventarioDisponible' => $inventarioDisponible,
            'promedioSalidaDiaria' => $promedioSalidaDiaria,
            'diasCobertura' => $diasCobertura,
            'rotacionPeriodo' => $rotacionPeriodo,
            'tendencia' => $tendencia,
            'proyeccionSalida7Dias' => $promedioSalidaDiaria * 7,
            'lotesEnRiesgo' => array_slice($lotesEnRiesgo, 0, 6),
        ];
    }

    public function construirResumenPorLote(array $movimientos, array $lotes, string $desde, string $hasta): array
    {
        $diasPeriodo = max(1, (new DateTime($desde))->diff(new DateTime($hasta))->days + 1);
        $resumen = [];

        foreach ($lotes as $lote) {
            $idInventarioEntrante = (int) $lote['idInventarioEntrante'];

            $resumen[$idInventarioEntrante] = [
                'idInventarioEntrante' => $idInventarioEntrante,
                'producto' => $lote['producto'],
                'NumLote' => $lote['NumLote'],
                'presentacion' => $lote['presentacion'],
                'ubicacion' => $lote['ubicacion'],
                'fechaEntrada' => $lote['fechaEntrada'],
                'cantidadEntrante' => (float) $lote['CantidadEntrante'],
                'inventarioDisponible' => max(0, (float) $lote['disponible']),
                'totalEntrada' => 0,
                'totalSalida' => 0,
                'promedioSalidaDiaria' => 0,
                'diasCobertura' => null,
                'proyeccionSalida7Dias' => 0,
                'rotacionPeriodo' => 0,
                'enRiesgo' => false,
            ];
        }

        foreach ($movimientos as $movimiento) {
            $idInventarioEntrante = (int) $movimiento['idInventarioEntrante'];

            if (!isset($resumen[$idInventarioEntrante])) {
                continue;
            }

            $resumen[$idInventarioEntrante]['totalEntrada'] += (float) $movimiento['entrada'];
            $resumen[$idInventarioEntrante]['totalSalida'] += (float) $movimiento['salida'];
        }

        foreach ($resumen as $idInventarioEntrante => $datos) {
            $promedioSalidaDiaria = $datos['totalSalida'] / $diasPeriodo;
            $resumen[$idInventarioEntrante]['promedioSalidaDiaria'] = $promedioSalidaDiaria;
            $resumen[$idInventarioEntrante]['diasCobertura'] = $promedioSalidaDiaria > 0
                ? $datos['inventarioDisponible'] / $promedioSalidaDiaria
                : null;
            $resumen[$idInventarioEntrante]['proyeccionSalida7Dias'] = $promedioSalidaDiaria * 7;
            $resumen[$idInventarioEntrante]['rotacionPeriodo'] = $datos['cantidadEntrante'] > 0
                ? ($datos['totalSalida'] / $datos['cantidadEntrante']) * 100
                : 0;
            $resumen[$idInventarioEntrante]['enRiesgo'] = $datos['inventarioDisponible'] <= 0
                || ($promedioSalidaDiaria > 0 && $datos['inventarioDisponible'] <= $promedioSalidaDiaria * 7);
        }

        usort($resumen, function ($a, $b) {
            return [$a['producto'], $a['NumLote']] <=> [$b['producto'], $b['NumLote']];
        });

        return array_values($resumen);
    }

    private function construirSerieDiaria(array $movimientos, string $desde, string $hasta): array
    {
        $series = [];
        $periodo = new DatePeriod(
            new DateTime($desde),
            new DateInterval('P1D'),
            (new DateTime($hasta))->modify('+1 day')
        );

        foreach ($periodo as $fecha) {
            $key = $fecha->format('Y-m-d');
            $series[$key] = [
                'fecha' => $key,
                'entrada' => 0,
                'salida' => 0,
                'neto' => 0,
            ];
        }

        foreach ($movimientos as $movimiento) {
            $fecha = $movimiento['fecha'];

            if (!isset($series[$fecha])) {
                continue;
            }

            $series[$fecha]['entrada'] += (float) $movimiento['entrada'];
            $series[$fecha]['salida'] += (float) $movimiento['salida'];
            $series[$fecha]['neto'] = $series[$fecha]['entrada'] - $series[$fecha]['salida'];
        }

        return array_values($series);
    }
}
