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
            'SELECT ie.idInventarioEntrante,
                    ie.NumLote,
                    ie.fecha,
                    pr.nombre AS presentacion
             FROM inventarioentrante ie
             LEFT JOIN presentacion pr ON pr.idPresentacion = ie.idPresentacion
             WHERE idProducto = :idProducto
             ORDER BY ie.fecha DESC, ie.NumLote ASC'
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

    public function obtenerMovimientosPorProducto(int $idProducto): array
    {
        $entradas = $this->obtenerEntradasPorProducto($idProducto);
        $predespachos = $this->obtenerPredespachosPorProducto($idProducto);
        $salidas = $this->obtenerSalidasPorProducto($idProducto);

        return $this->construirMovimientosProducto($entradas, $predespachos, $salidas);
    }

    public function obtenerEntradasPorProducto(int $idProducto): array
    {
        $statement = $this->db->prepare(
            'SELECT ie.idInventarioEntrante,
                    ie.NumLote,
                    ie.CantidadEntrante,
                    ie.fecha,
                    pr.nombre AS presentacion
             FROM inventarioentrante ie
             LEFT JOIN presentacion pr ON pr.idPresentacion = ie.idPresentacion
             WHERE ie.idProducto = :idProducto
             ORDER BY ie.fecha ASC, ie.idInventarioEntrante ASC'
        );
        $statement->execute(['idProducto' => $idProducto]);

        return $statement->fetchAll();
    }

    public function obtenerPredespachosPorProducto(int $idProducto): array
    {
        $statement = $this->db->prepare(
            'SELECT ip.idInventarioEntrante,
                    ie.NumLote,
                    cp.codigoInterno,
                    cp.fechaRetiro,
                    ip.cantidadSolicitada,
                    cp.observaciones
             FROM tbl_items_predespacho ip
             INNER JOIN tbl_cabecera_predespacho cp ON cp.idCabeceraPredespacho = ip.idCabeceraPredespacho
             INNER JOIN inventarioentrante ie ON ie.idInventarioEntrante = ip.idInventarioEntrante
             WHERE ie.idProducto = :idProducto
             ORDER BY cp.fechaRetiro ASC, cp.fechaCreacion ASC, ip.idItem ASC'
        );
        $statement->execute(['idProducto' => $idProducto]);

        return $statement->fetchAll();
    }

    public function obtenerSalidasPorProducto(int $idProducto): array
    {
        $statement = $this->db->prepare(
            'SELECT ins.idInventarioSaliente,
                    ins.idInventarioEntrante,
                    ie.NumLote,
                    ins.NE,
                    ins.cantidadSaliente,
                    ins.fecha,
                    ip.cantidadSolicitada AS montoPredespacho
             FROM inventariosaliente ins
             INNER JOIN inventarioentrante ie ON ie.idInventarioEntrante = ins.idInventarioEntrante
             LEFT JOIN tbl_cabecera_predespacho cp ON cp.codigoInterno COLLATE utf8mb4_unicode_ci = ins.NE COLLATE utf8mb4_unicode_ci
             LEFT JOIN tbl_items_predespacho ip ON ip.idCabeceraPredespacho = cp.idCabeceraPredespacho
                AND ip.idInventarioEntrante = ins.idInventarioEntrante
             WHERE ie.idProducto = :idProducto
             ORDER BY ins.fecha ASC, ins.idInventarioSaliente ASC'
        );
        $statement->execute(['idProducto' => $idProducto]);

        return $statement->fetchAll();
    }

    public function construirMovimientos(array $encabezado, array $salidas): array
    {
        $saldo = (float) $encabezado['CantidadEntrante'];
        $movimientos = [[
            'idInventarioEntrante' => (int) ($encabezado['idInventarioEntrante'] ?? 0),
            'fecha' => $encabezado['fecha'],
            'codPredespacho' => '',
            'montoPredespacho' => '',
            'entrada' => $encabezado['CantidadEntrante'],
            'salida' => '',
            'saldo' => $saldo,
            'observaciones' => 'Entrada inicial del lote',
            'tipo' => 'entrada',
        ]];

        foreach ($salidas as $salida) {
            $cantidadSaliente = (float) $salida['cantidadSaliente'];
            $saldo -= $cantidadSaliente;

            $movimientos[] = [
                'idInventarioEntrante' => (int) ($encabezado['idInventarioEntrante'] ?? 0),
                'fecha' => $salida['fecha'],
                'codPredespacho' => '',
                'montoPredespacho' => '',
                'entrada' => '',
                'salida' => $cantidadSaliente,
                'saldo' => $saldo,
                'observaciones' => 'Salida relacionada al lote',
                'tipo' => 'salida',
            ];
        }

        return $movimientos;
    }

    public function construirMovimientosProducto(array $entradas, array $predespachos, array $salidas): array
    {
        $movimientos = [];

        usort($entradas, function (array $left, array $right): int {
            $dateComparison = strcmp((string) ($left['fecha'] ?? ''), (string) ($right['fecha'] ?? ''));
            if ($dateComparison !== 0) {
                return $dateComparison;
            }

            return ((int) ($left['idInventarioEntrante'] ?? 0)) <=> ((int) ($right['idInventarioEntrante'] ?? 0));
        });

        $predespachosPorLote = [];
        foreach ($predespachos as $predespacho) {
            $idInventarioEntrante = (int) ($predespacho['idInventarioEntrante'] ?? 0);
            $predespachosPorLote[$idInventarioEntrante][] = $predespacho;
        }

        foreach ($predespachosPorLote as &$listaPredespachos) {
            usort($listaPredespachos, function (array $left, array $right): int {
                $dateComparison = strcmp((string) ($left['fechaRetiro'] ?? ''), (string) ($right['fechaRetiro'] ?? ''));
                if ($dateComparison !== 0) {
                    return $dateComparison;
                }

                return strcmp((string) ($left['codigoInterno'] ?? ''), (string) ($right['codigoInterno'] ?? ''));
            });
        }
        unset($listaPredespachos);

        $salidasPorLote = [];
        foreach ($salidas as $salida) {
            $idInventarioEntrante = (int) ($salida['idInventarioEntrante'] ?? 0);
            $salidasPorLote[$idInventarioEntrante][] = $salida;
        }

        foreach ($salidasPorLote as &$listaSalidas) {
            usort($listaSalidas, function (array $left, array $right): int {
                $dateComparison = strcmp((string) ($left['fecha'] ?? ''), (string) ($right['fecha'] ?? ''));
                if ($dateComparison !== 0) {
                    return $dateComparison;
                }

                return ((int) ($left['idInventarioSaliente'] ?? 0)) <=> ((int) ($right['idInventarioSaliente'] ?? 0));
            });
        }
        unset($listaSalidas);

        foreach ($entradas as $entrada) {
            $idInventarioEntrante = (int) ($entrada['idInventarioEntrante'] ?? 0);
            $numLote = (string) ($entrada['NumLote'] ?? '');
            $presentacion = trim((string) ($entrada['presentacion'] ?? ''));
            $saldoDelLote = (float) $entrada['CantidadEntrante'];

            $movimientos[] = [
                'idInventarioEntrante' => $idInventarioEntrante,
                'fecha' => $entrada['fecha'],
                'codPredespacho' => '',
                'montoPredespacho' => '',
                'entrada' => (float) $entrada['CantidadEntrante'],
                'salida' => '',
                'saldo' => 0.0,
                'observaciones' => 'Entrada inicial del lote ' . $numLote . ($presentacion !== '' ? ' | Presentacion ' . $presentacion : ''),
                'tipo' => 'entrada',
            ];

            $salidasDelLote = $salidasPorLote[$idInventarioEntrante] ?? [];
            $indicesSalidaUsados = [];
            $predespachosDelLote = $predespachosPorLote[$idInventarioEntrante] ?? [];

            foreach ($predespachosDelLote as $predespacho) {
                $codigoPredespacho = (string) ($predespacho['codigoInterno'] ?? '');

                $movimientos[] = [
                    'idInventarioEntrante' => $idInventarioEntrante,
                    'fecha' => $predespacho['fechaRetiro'],
                    'codPredespacho' => $codigoPredespacho,
                    'montoPredespacho' => (float) $predespacho['cantidadSolicitada'],
                    'entrada' => '',
                    'salida' => '',
                    'saldo' => 0.0,
                    'observaciones' => 'Predespacho ' . $codigoPredespacho . ' | Lote ' . $numLote,
                    'tipo' => 'predespacho',
                ];

                foreach ($salidasDelLote as $indexSalida => $salida) {
                    if (isset($indicesSalidaUsados[$indexSalida])) {
                        continue;
                    }

                    $neSalida = trim((string) ($salida['NE'] ?? ''));
                    if ($neSalida !== $codigoPredespacho) {
                        continue;
                    }

                    $saldoDelLote -= (float) $salida['cantidadSaliente'];
                    $movimientos[] = [
                        'idInventarioEntrante' => $idInventarioEntrante,
                        'fecha' => $salida['fecha'],
                        'codPredespacho' => $neSalida,
                        'montoPredespacho' => '',
                        'entrada' => '',
                        'salida' => (float) $salida['cantidadSaliente'],
                        'saldo' => 0.0,
                        'observaciones' => 'Salida de predespacho ' . $neSalida . ' | Lote ' . $numLote,
                        'tipo' => 'salida',
                    ];
                    $indicesSalidaUsados[$indexSalida] = true;
                }
            }

            foreach ($salidasDelLote as $indexSalida => $salida) {
                if (isset($indicesSalidaUsados[$indexSalida])) {
                    continue;
                }

                $neSalida = trim((string) ($salida['NE'] ?? ''));
                $saldoDelLote -= (float) $salida['cantidadSaliente'];
                $movimientos[] = [
                    'idInventarioEntrante' => $idInventarioEntrante,
                    'fecha' => $salida['fecha'],
                    'codPredespacho' => $neSalida,
                    'montoPredespacho' => '',
                    'entrada' => '',
                    'salida' => (float) $salida['cantidadSaliente'],
                    'saldo' => 0.0,
                    'observaciones' => $neSalida !== ''
                        ? 'Salida de predespacho ' . $neSalida . ' | Lote ' . $numLote
                        : 'Salida relacionada al lote ' . $numLote,
                    'tipo' => 'salida',
                ];
            }

            $movimientos[] = [
                'idInventarioEntrante' => $idInventarioEntrante,
                'fecha' => '',
                'codPredespacho' => '',
                'montoPredespacho' => '',
                'entrada' => '',
                'salida' => '',
                'saldo' => $saldoDelLote,
                'observaciones' => '',
                'tipo' => 'saldo',
            ];
        }

        foreach ($salidasPorLote as $idInventarioEntrante => $salidasDelLote) {
            $loteRegistradoEnEntrada = false;
            foreach ($entradas as $entrada) {
                if ((int) ($entrada['idInventarioEntrante'] ?? 0) === (int) $idInventarioEntrante) {
                    $loteRegistradoEnEntrada = true;
                    break;
                }
            }

            if ($loteRegistradoEnEntrada) {
                continue;
            }

            foreach ($salidasDelLote as $salida) {
                $neSalida = trim((string) ($salida['NE'] ?? ''));
                $numLote = (string) ($salida['NumLote'] ?? '');
                $movimientos[] = [
                    'idInventarioEntrante' => (int) $idInventarioEntrante,
                    'fecha' => $salida['fecha'],
                    'codPredespacho' => $neSalida,
                    'montoPredespacho' => '',
                    'entrada' => '',
                    'salida' => (float) $salida['cantidadSaliente'],
                    'saldo' => 0.0,
                    'observaciones' => $neSalida !== ''
                        ? 'Salida de predespacho ' . $neSalida . ' | Lote ' . $numLote
                        : 'Salida relacionada al lote ' . $numLote,
                    'tipo' => 'salida',
                ];
            }
        }

        $saldo = 0.0;
        foreach ($movimientos as &$movimiento) {
            if ($movimiento['tipo'] === 'entrada') {
                $saldo += (float) $movimiento['entrada'];
            } elseif ($movimiento['tipo'] === 'salida') {
                $saldo -= (float) $movimiento['salida'];
            }

            $movimiento['saldo'] = $saldo;
        }
        unset($movimiento);

        return $movimientos;
    }
}
