<?php

class Predespacho extends BaseModel
{
    private const STATUS_VALIDOS = ['abierto', 'pendiente', 'embarcado', 'cerrado'];

    public function crearCabeceraPredespacho(
        int $idCliente,
        string $fechaRetiro,
        int|string $userCreador,
        ?string $codigoNotaEntregaSAP = null,
        ?string $observaciones = null
    ): int|false {
        try {
            $this->db->beginTransaction();

            $anioActual = date('Y');
            $codigoStatement = $this->db->prepare(
                "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(codigoInterno, '-', -1) AS UNSIGNED)), 0) + 1
                 FROM tbl_cabecera_predespacho
                 WHERE codigoInterno LIKE :prefijo"
            );
            $codigoStatement->execute(['prefijo' => 'PRE-' . $anioActual . '-%']);
            $codigoInterno = 'PRE-' . $anioActual . '-' . str_pad((string) $codigoStatement->fetchColumn(), 5, '0', STR_PAD_LEFT);

            $statement = $this->db->prepare(
                'INSERT INTO tbl_cabecera_predespacho
                    (idCliente, fechaRetiro, codigoInterno, codigoNotaEntregaSAP, userCreador, statusGeneralPredespacho, observaciones)
                 VALUES
                    (:idCliente, :fechaRetiro, :codigoInterno, :codigoNotaEntregaSAP, :userCreador, :statusGeneralPredespacho, :observaciones)'
            );
            $statement->execute([
                'idCliente' => $idCliente,
                'fechaRetiro' => $fechaRetiro,
                'codigoInterno' => $codigoInterno,
                'codigoNotaEntregaSAP' => $codigoNotaEntregaSAP,
                'userCreador' => $userCreador,
                'statusGeneralPredespacho' => 'abierto',
                'observaciones' => $observaciones,
            ]);

            $idCabeceraPredespacho = (int) $this->db->lastInsertId();
            if ($idCabeceraPredespacho <= 0) {
                $idStatement = $this->db->prepare(
                    'SELECT idCabeceraPredespacho
                     FROM tbl_cabecera_predespacho
                     WHERE codigoInterno = :codigoInterno
                     LIMIT 1'
                );
                $idStatement->execute(['codigoInterno' => $codigoInterno]);
                $idCabeceraPredespacho = (int) $idStatement->fetchColumn();
            }

            if ($idCabeceraPredespacho <= 0) {
                $this->db->rollBack();

                return false;
            }

            $this->db->commit();

            return $idCabeceraPredespacho;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return false;
        }
    }

    public function obtenerTodosLosPredespachos(): array
    {
        $statement = $this->db->prepare(
            'SELECT cp.idCabeceraPredespacho,
                    cp.idCliente,
                    c.nombre AS nombreCliente,
                    c.rif AS rifCliente,
                    cp.fechaRetiro,
                    cp.codigoInterno,
                    cp.codigoNotaEntregaSAP,
                    cp.userCreador,
                    cp.statusGeneralPredespacho,
                    cp.observaciones,
                    cp.fechaCreacion,
                    cp.fechaActualizacion
             FROM tbl_cabecera_predespacho cp
             INNER JOIN tbl_cliente c ON c.idCliente = cp.idCliente
             ORDER BY cp.fechaCreacion DESC'
        );
        $statement->execute();

        return $statement->fetchAll();
    }

    public function obtenerPredespachoPorId(int $idCabeceraPredespacho): ?array
    {
        $statement = $this->db->prepare(
            'SELECT cp.idCabeceraPredespacho,
                    cp.idCliente,
                    c.nombre AS nombreCliente,
                    c.rif AS rifCliente,
                    cp.fechaRetiro,
                    cp.codigoInterno,
                    cp.codigoNotaEntregaSAP,
                    cp.userCreador,
                    cp.statusGeneralPredespacho,
                    cp.observaciones,
                    cp.fechaCreacion,
                    cp.fechaActualizacion
             FROM tbl_cabecera_predespacho cp
             INNER JOIN tbl_cliente c ON c.idCliente = cp.idCliente
             WHERE cp.idCabeceraPredespacho = :idCabeceraPredespacho
             LIMIT 1'
        );
        $statement->execute(['idCabeceraPredespacho' => $idCabeceraPredespacho]);
        $predespacho = $statement->fetch();

        return $predespacho ?: null;
    }

    public function actualizarCodigoSAP(int $idCabeceraPredespacho, ?string $codigoNotaEntregaSAP): bool
    {
        try {
            $statement = $this->db->prepare(
                'UPDATE tbl_cabecera_predespacho
                 SET codigoNotaEntregaSAP = :codigoNotaEntregaSAP
                 WHERE idCabeceraPredespacho = :idCabeceraPredespacho'
            );

            return $statement->execute([
                'codigoNotaEntregaSAP' => $codigoNotaEntregaSAP,
                'idCabeceraPredespacho' => $idCabeceraPredespacho,
            ]);
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function actualizarStatusCabecera(int $idCabeceraPredespacho, string $nuevoStatus): bool
    {
        if (!in_array($nuevoStatus, self::STATUS_VALIDOS, true)) {
            return false;
        }

        try {
            $manejaTransaccion = !$this->db->inTransaction();
            if ($manejaTransaccion) {
                $this->db->beginTransaction();
            }

            if ($nuevoStatus === 'cerrado') {
                $itemsStatement = $this->db->prepare(
                    'UPDATE tbl_items_predespacho
                     SET estatusItemPredespacho = :estatusCerradoSet
                     WHERE idCabeceraPredespacho = :idCabeceraPredespacho
                       AND estatusItemPredespacho <> :estatusCerradoFiltro'
                );
                $itemsStatement->execute([
                    'estatusCerradoSet' => 'cerrado',
                    'estatusCerradoFiltro' => 'cerrado',
                    'idCabeceraPredespacho' => $idCabeceraPredespacho,
                ]);
            }

            $statement = $this->db->prepare(
                'UPDATE tbl_cabecera_predespacho
                 SET statusGeneralPredespacho = :statusGeneralPredespacho
                 WHERE idCabeceraPredespacho = :idCabeceraPredespacho'
            );

            $statement->execute([
                'statusGeneralPredespacho' => $nuevoStatus,
                'idCabeceraPredespacho' => $idCabeceraPredespacho,
            ]);

            if ($manejaTransaccion) {
                $this->db->commit();
            }

            return true;
        } catch (Throwable $exception) {
            if (($manejaTransaccion ?? false) && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return false;
        }
    }

    public function verificarYEmbarcarPredespacho(int $idCabeceraPredespacho): bool
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) AS itemsAbiertos
             FROM tbl_items_predespacho
             WHERE idCabeceraPredespacho = :idCabeceraPredespacho
               AND estatusItemPredespacho <> :estatusCerrado'
        );
        $statement->execute([
            'idCabeceraPredespacho' => $idCabeceraPredespacho,
            'estatusCerrado' => 'cerrado',
        ]);

        $itemsAbiertos = (int) $statement->fetchColumn();
        if ($itemsAbiertos > 0) {
            return false;
        }

        return $this->actualizarStatusCabecera($idCabeceraPredespacho, 'embarcado');
    }

    public function verificarYCerrarPredespacho(int $idCabeceraPredespacho): bool
    {
        return $this->verificarYEmbarcarPredespacho($idCabeceraPredespacho);
    }

    public function agregarItemPredespacho(
        int $idCabeceraPredespacho,
        int $idInventarioEntrante,
        float $cantidadSolicitada,
        ?string $tipo = null
    ): array {
        try {
            $statement = $this->db->prepare(
                'SELECT cantidad_disponible
                 FROM v_disponibilidad_lotes
                 WHERE idInventarioEntrante = :idInventarioEntrante
                 LIMIT 1'
            );
            $statement->execute(['idInventarioEntrante' => $idInventarioEntrante]);
            $disponible = (float) ($statement->fetchColumn() ?: 0);

            if ($cantidadSolicitada > $disponible) {
                return [
                    'success' => false,
                    'mensaje' => 'Cantidad excede el disponible. Disponible: ' . number_format($disponible, 2, '.', ''),
                ];
            }

            $statement = $this->db->prepare(
                'SELECT COUNT(*)
                 FROM tbl_items_predespacho
                 WHERE idCabeceraPredespacho = :idCabeceraPredespacho
                   AND idInventarioEntrante = :idInventarioEntrante'
            );
            $statement->execute([
                'idCabeceraPredespacho' => $idCabeceraPredespacho,
                'idInventarioEntrante' => $idInventarioEntrante,
            ]);

            if ((int) $statement->fetchColumn() > 0) {
                return [
                    'success' => false,
                    'mensaje' => 'Este lote ya esta agregado al predespacho.',
                ];
            }

            $statement = $this->db->prepare(
                'INSERT INTO tbl_items_predespacho
                    (idCabeceraPredespacho, idInventarioEntrante, cantidadSolicitada, cantidadDespachada, tipo, estatusItemPredespacho)
                 VALUES
                    (:idCabeceraPredespacho, :idInventarioEntrante, :cantidadSolicitada, :cantidadDespachada, :tipo, :estatusItemPredespacho)'
            );
            $statement->execute([
                'idCabeceraPredespacho' => $idCabeceraPredespacho,
                'idInventarioEntrante' => $idInventarioEntrante,
                'cantidadSolicitada' => $cantidadSolicitada,
                'cantidadDespachada' => 0,
                'tipo' => $tipo,
                'estatusItemPredespacho' => 'abierto',
            ]);

            return [
                'success' => true,
                'idItem' => (int) $this->db->lastInsertId(),
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'mensaje' => 'No se pudo agregar el item al predespacho.',
            ];
        }
    }

    public function obtenerItemsPorPredespacho(int $idCabeceraPredespacho): array
    {
        $statement = $this->db->prepare(
            'SELECT ip.idItem,
                    ie.NumLote,
                    ie.idProducto,
                    p.nombre AS nombreProducto,
                    ie.idPresentacion,
                    COALESCE(dl.sector, "Sin sector") AS sector,
                    ip.cantidadSolicitada,
                    COALESCE(salidas.cantidadDespachada, 0) AS cantidadDespachada,
                    ip.estatusItemPredespacho
             FROM tbl_items_predespacho ip
             INNER JOIN tbl_cabecera_predespacho cp ON cp.idCabeceraPredespacho = ip.idCabeceraPredespacho
             INNER JOIN inventarioentrante ie ON ie.idInventarioEntrante = ip.idInventarioEntrante
             INNER JOIN Producto p ON p.idProducto = ie.idProducto
             LEFT JOIN v_disponibilidad_lotes dl ON dl.idInventarioEntrante = ip.idInventarioEntrante
             LEFT JOIN (
                 SELECT idInventarioEntrante, NE, SUM(cantidadSaliente) AS cantidadDespachada
                 FROM inventariosaliente
                 GROUP BY idInventarioEntrante, NE
             ) salidas ON salidas.idInventarioEntrante = ip.idInventarioEntrante
                 AND salidas.NE COLLATE utf8mb4_unicode_ci = cp.codigoInterno
             WHERE ip.idCabeceraPredespacho = :idCabeceraPredespacho
             ORDER BY ip.fechaCreacion ASC, ip.idItem ASC'
        );
        $statement->execute(['idCabeceraPredespacho' => $idCabeceraPredespacho]);

        return $statement->fetchAll();
    }

    public function eliminarItemPredespacho(int $idItem): array
    {
        try {
            $statement = $this->db->prepare(
                'SELECT ip.idInventarioEntrante, cp.codigoInterno
                 FROM tbl_items_predespacho ip
                 INNER JOIN tbl_cabecera_predespacho cp ON cp.idCabeceraPredespacho = ip.idCabeceraPredespacho
                 WHERE ip.idItem = :idItem
                 LIMIT 1'
            );
            $statement->execute(['idItem' => $idItem]);
            $item = $statement->fetch();

            if (!$item) {
                return [
                    'success' => false,
                    'mensaje' => 'Item de predespacho no encontrado.',
                ];
            }

            $statement = $this->db->prepare(
                'SELECT COUNT(*)
                 FROM inventariosaliente
                 WHERE idInventarioEntrante = :idInventarioEntrante
                                     AND NE COLLATE utf8mb4_unicode_ci = :ne'
            );
            $statement->execute([
                'idInventarioEntrante' => (int) $item['idInventarioEntrante'],
                'ne' => (string) $item['codigoInterno'],
            ]);

            if ((int) $statement->fetchColumn() > 0) {
                return [
                    'success' => false,
                    'mensaje' => 'No se puede eliminar un item con salidas registradas.',
                ];
            }

            $statement = $this->db->prepare(
                'DELETE FROM tbl_items_predespacho
                 WHERE idItem = :idItem'
            );
            $statement->execute(['idItem' => $idItem]);

            return [
                'success' => $statement->rowCount() > 0,
                'mensaje' => $statement->rowCount() > 0 ? 'Item eliminado correctamente.' : 'No se pudo eliminar el item.',
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'mensaje' => 'No se pudo eliminar el item.',
            ];
        }
    }

    public function actualizarCantidadDespachada(int $idItem, float $cantidadDespachada): bool
    {
        try {
            if ($cantidadDespachada > 0) {
                $statement = $this->db->prepare(
                    'UPDATE tbl_items_predespacho
                     SET cantidadDespachada = :cantidadDespachada,
                         estatusItemPredespacho = :estatusItemPredespacho
                     WHERE idItem = :idItem'
                );

                return $statement->execute([
                    'cantidadDespachada' => $cantidadDespachada,
                    'estatusItemPredespacho' => 'pendiente',
                    'idItem' => $idItem,
                ]);
            }

            $statement = $this->db->prepare(
                'UPDATE tbl_items_predespacho
                 SET cantidadDespachada = :cantidadDespachada
                 WHERE idItem = :idItem'
            );

            return $statement->execute([
                'cantidadDespachada' => $cantidadDespachada,
                'idItem' => $idItem,
            ]);
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function cerrarItem(int $idItem): bool
    {
        try {
            $statement = $this->db->prepare(
                'SELECT ip.idInventarioEntrante, cp.codigoInterno
                 FROM tbl_items_predespacho ip
                 INNER JOIN tbl_cabecera_predespacho cp ON cp.idCabeceraPredespacho = ip.idCabeceraPredespacho
                 WHERE ip.idItem = :idItem
                 LIMIT 1'
            );
            $statement->execute(['idItem' => $idItem]);
            $item = $statement->fetch();

            if (!$item) {
                return false;
            }

            $statement = $this->db->prepare(
                'SELECT COALESCE(SUM(cantidadSaliente), 0)
                 FROM inventariosaliente
                 WHERE idInventarioEntrante = :idInventarioEntrante
                   AND NE COLLATE utf8mb4_unicode_ci = :ne'
            );
            $statement->execute([
                'idInventarioEntrante' => (int) $item['idInventarioEntrante'],
                'ne' => (string) $item['codigoInterno'],
            ]);

            if ((float) $statement->fetchColumn() > 0) {
                return false;
            }

            $statement = $this->db->prepare(
                'UPDATE tbl_items_predespacho
                 SET estatusItemPredespacho = :estatusItemPredespacho
                 WHERE idItem = :idItem'
            );

            return $statement->execute([
                'estatusItemPredespacho' => 'cerrado',
                'idItem' => $idItem,
            ]);
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function obtenerDisponibilidadPorLote(int $idInventarioEntrante): ?array
    {
        $statement = $this->db->prepare(
            'SELECT stock_total, cantidad_reservada, cantidad_disponible
             FROM v_disponibilidad_lotes
             WHERE idInventarioEntrante = :idInventarioEntrante
             LIMIT 1'
        );
        $statement->execute(['idInventarioEntrante' => $idInventarioEntrante]);
        $disponibilidad = $statement->fetch();

        return $disponibilidad ?: null;
    }

    public function buscarProductosDisponibles(string $terminoBusqueda): array
    {
        $statement = $this->db->prepare(
                        'SELECT DISTINCT
                                        dl.idProducto,
                                        p.codigoInterno,
                                        p.nombre AS nombreProducto,
                                        dl.idPresentacion,
                                        dl.sector
                         FROM v_disponibilidad_lotes dl
                         INNER JOIN Producto p ON p.idProducto = dl.idProducto
                         WHERE dl.cantidad_disponible > 0
                             AND (
                                    CAST(dl.idProducto AS CHAR) LIKE :terminoId
                                    OR p.codigoInterno LIKE :terminoCodigo
                                    OR p.nombre LIKE :terminoNombre
                             )
                         ORDER BY p.nombre ASC, dl.idPresentacion ASC, dl.sector ASC'
        );
                        $termino = '%' . $terminoBusqueda . '%';
                        $statement->execute([
                            'terminoId' => $termino,
                            'terminoCodigo' => $termino,
                            'terminoNombre' => $termino,
                        ]);

        return $statement->fetchAll();
    }

    public function obtenerLotesPorProducto(int $idProducto): array
    {
        $statement = $this->db->prepare(
                        'SELECT idInventarioEntrante, NumLote, sector, stock_total, cantidad_disponible
             FROM v_disponibilidad_lotes
             WHERE idProducto = :idProducto
               AND cantidad_disponible > 0
             ORDER BY NumLote ASC, sector ASC'
        );
        $statement->execute(['idProducto' => $idProducto]);

        return $statement->fetchAll();
    }

    public function obtenerPredespachosPorSector(string $sector): array
    {
        $statement = $this->db->prepare(
            'SELECT DISTINCT cp.idCabeceraPredespacho,
                    cp.idCliente,
                    c.nombre AS nombreCliente,
                    c.rif AS rifCliente,
                    cp.fechaRetiro,
                    cp.codigoInterno,
                    cp.codigoNotaEntregaSAP,
                    cp.userCreador,
                    cp.statusGeneralPredespacho,
                    cp.observaciones,
                    cp.fechaCreacion,
                    cp.fechaActualizacion
             FROM tbl_cabecera_predespacho cp
             INNER JOIN tbl_cliente c ON c.idCliente = cp.idCliente
             INNER JOIN tbl_items_predespacho ip ON ip.idCabeceraPredespacho = cp.idCabeceraPredespacho
             INNER JOIN inventarioentrante ie ON ie.idInventarioEntrante = ip.idInventarioEntrante
             WHERE ie.sector = :sector
                             AND ip.estatusItemPredespacho <> :estatusItemCerrado
                             AND cp.statusGeneralPredespacho <> :estatusCabeceraCerrado
             ORDER BY cp.fechaCreacion DESC'
        );
        $statement->execute([
            'sector' => $sector,
                        'estatusItemCerrado' => 'cerrado',
                        'estatusCabeceraCerrado' => 'cerrado',
        ]);

        return $statement->fetchAll();
    }

    public function obtenerPredespachosPendientesEntrega(): array
    {
        $statement = $this->db->prepare(
            'SELECT cp.idCabeceraPredespacho,
                    cp.idCliente,
                    c.nombre AS nombreCliente,
                    c.rif AS rifCliente,
                    cp.fechaRetiro,
                    cp.codigoInterno,
                    cp.codigoNotaEntregaSAP,
                    cp.userCreador,
                    cp.statusGeneralPredespacho,
                    cp.observaciones,
                    cp.fechaCreacion,
                    cp.fechaActualizacion
             FROM tbl_cabecera_predespacho cp
             INNER JOIN tbl_cliente c ON c.idCliente = cp.idCliente
             WHERE cp.statusGeneralPredespacho IN (:estatusAbierto, :estatusPendiente)
             ORDER BY cp.fechaCreacion DESC'
        );
        $statement->execute([
            'estatusAbierto' => 'abierto',
            'estatusPendiente' => 'pendiente',
        ]);

        return $statement->fetchAll();
    }

        public function obtenerSectoresPendientesPredespacho(): array
        {
            $sectoresBase = ['Sector1', 'Sector2', 'Sector3'];
            $statement = $this->db->query(
                "SELECT DISTINCT ie.sector
                 FROM inventarioentrante ie
                 WHERE ie.sector IS NOT NULL
                     AND ie.sector <> ''
                 ORDER BY ie.sector ASC"
            );

            return array_values(array_unique(array_merge($sectoresBase, array_column($statement->fetchAll(), 'sector'))));
        }

    public function obtenerSectoresPorPredespacho(int $idCabeceraPredespacho): array
    {
        $statement = $this->db->prepare(
            'SELECT DISTINCT ie.sector
             FROM tbl_items_predespacho ip
             INNER JOIN inventarioentrante ie ON ie.idInventarioEntrante = ip.idInventarioEntrante
             WHERE ip.idCabeceraPredespacho = :idCabeceraPredespacho
                 AND ie.sector IS NOT NULL
                 AND ie.sector <> :sectorVacio
             ORDER BY ie.sector ASC'
        );
        $statement->execute([
            'idCabeceraPredespacho' => $idCabeceraPredespacho,
            'sectorVacio' => '',
        ]);

        return array_column($statement->fetchAll(), 'sector');
    }

        public function obtenerPredespachoPorCodigo(string $codigoInterno): ?array
        {
                $statement = $this->db->prepare(
                        'SELECT cp.idCabeceraPredespacho,
                                        cp.idCliente,
                                        c.nombre AS nombreCliente,
                                        c.rif AS rifCliente,
                                        cp.fechaRetiro,
                                        cp.codigoInterno,
                                        cp.codigoNotaEntregaSAP,
                                        cp.userCreador,
                                        cp.statusGeneralPredespacho,
                                        cp.observaciones,
                                        cp.fechaCreacion,
                                        cp.fechaActualizacion
                         FROM tbl_cabecera_predespacho cp
                         INNER JOIN tbl_cliente c ON c.idCliente = cp.idCliente
                         WHERE cp.codigoInterno = :codigoInterno
                         LIMIT 1'
                );
                $statement->execute(['codigoInterno' => $codigoInterno]);
                $predespacho = $statement->fetch();

                return $predespacho ?: null;
        }

    public function obtenerItemsPorPredespachoParaEntrega(int $idCabeceraPredespacho, ?string $sector = null): array
    {
        $whereSector = $sector !== null && $sector !== '' ? ' AND ie.sector = :sector' : '';
        $statement = $this->db->prepare(
            'SELECT ip.idItem,
                    ip.idInventarioEntrante,
                    ie.NumLote,
                    ie.idProducto,
                    p.nombre AS nombreProducto,
                    COALESCE(ie.sector, "Sin sector") AS sector,
                    pr.nombre AS presentacion,
                    ip.cantidadSolicitada,
                    COALESCE(salidas.cantidadDespachada, 0) AS cantidadDespachada,
                    GREATEST(ip.cantidadSolicitada - COALESCE(salidas.cantidadDespachada, 0), 0) AS cantidadPendiente,
                    ip.estatusItemPredespacho,
                    CASE WHEN COALESCE(salidas.cantidadDespachada, 0) >= ip.cantidadSolicitada THEN 1 ELSE 0 END AS coincide
             FROM tbl_items_predespacho ip
             INNER JOIN tbl_cabecera_predespacho cp ON cp.idCabeceraPredespacho = ip.idCabeceraPredespacho
             INNER JOIN inventarioentrante ie ON ie.idInventarioEntrante = ip.idInventarioEntrante
             INNER JOIN Producto p ON p.idProducto = ie.idProducto
             LEFT JOIN presentacion pr ON pr.idPresentacion = ie.idPresentacion
             LEFT JOIN (
                 SELECT idInventarioEntrante, NE, SUM(cantidadSaliente) AS cantidadDespachada
                 FROM inventariosaliente
                 GROUP BY idInventarioEntrante, NE
             ) salidas ON salidas.idInventarioEntrante = ip.idInventarioEntrante
                 AND salidas.NE COLLATE utf8mb4_unicode_ci = cp.codigoInterno
             WHERE ip.idCabeceraPredespacho = :idCabeceraPredespacho' . $whereSector . '
             ORDER BY ie.sector ASC, ip.fechaCreacion ASC, ip.idItem ASC'
        );

        $params = ['idCabeceraPredespacho' => $idCabeceraPredespacho];
        if ($whereSector !== '') {
            $params['sector'] = $sector;
        }

        $statement->execute($params);

        $items = $statement->fetchAll();
        foreach ($items as &$item) {
            $item['coincide'] = (bool) $item['coincide'];
            $item['unidad'] = $this->calcularUnidadDesdePresentacion(
                (float) $item['cantidadSolicitada'],
                (string) ($item['presentacion'] ?? '')
            );
        }

        return $items;
    }

    private function calcularUnidadDesdePresentacion(float $cantidadSolicitada, string $presentacion): ?float
    {
        // Numeric weight inside parentheses, e.g. "TAMBOR (250 KG)" → divide by that number
        if (preg_match('/\(([0-9]+(?:[\.,][0-9]+)?)\s*[^)]*\)/', $presentacion, $matches)) {
            $valorPresentacion = (float) str_replace(',', '.', $matches[1]);
            return $valorPresentacion > 0 ? $cantidadSolicitada / $valorPresentacion : null;
        }

        // Unit-only parentheses, e.g. "GRANEL (KG)" → divide by 1
        if (preg_match('/\([^)]+\)/', $presentacion)) {
            return $cantidadSolicitada;
        }

        return null;
    }

    public function cerrarItemConMerma(int $idItem, int $idCabeceraPredespacho): array
    {
        try {
            $this->db->beginTransaction();

            $statement = $this->db->prepare(
                'SELECT ip.idItem,
                        ip.cantidadSolicitada,
                        ip.cantidadDespachada,
                        ip.estatusItemPredespacho,
                        ie.idProducto,
                        p.nombre AS nombreProducto,
                        p.codigoInterno AS codigoProducto
                 FROM tbl_items_predespacho ip
                 INNER JOIN inventarioentrante ie ON ie.idInventarioEntrante = ip.idInventarioEntrante
                 INNER JOIN Producto p ON p.idProducto = ie.idProducto
                 WHERE ip.idItem = :idItem
                   AND ip.idCabeceraPredespacho = :idCabeceraPredespacho
                 LIMIT 1
                 FOR UPDATE'
            );
            $statement->execute([
                'idItem' => $idItem,
                'idCabeceraPredespacho' => $idCabeceraPredespacho,
            ]);
            $item = $statement->fetch();

            if (!$item) {
                $this->db->rollBack();
                return ['success' => false, 'mensaje' => 'Item no encontrado.'];
            }

            if ($item['estatusItemPredespacho'] === 'cerrado') {
                $this->db->rollBack();
                return ['success' => false, 'mensaje' => 'Este item ya está cerrado.'];
            }

            $cantidadDespachada = (float) $item['cantidadDespachada'];
            if ($cantidadDespachada <= 0) {
                $this->db->rollBack();
                return ['success' => false, 'mensaje' => 'El item no tiene despacho registrado. Use Eliminar en su lugar.'];
            }

            $diferencia = round((float) $item['cantidadSolicitada'] - $cantidadDespachada, 4);

            $statement = $this->db->prepare(
                'UPDATE tbl_items_predespacho
                 SET cantidadSolicitada = :cantidadSolicitada,
                     estatusItemPredespacho = :estatusItemPredespacho
                 WHERE idItem = :idItem'
            );
            $statement->execute([
                'cantidadSolicitada' => $cantidadDespachada,
                'estatusItemPredespacho' => 'cerrado',
                'idItem' => $idItem,
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'mensaje' => 'Item cerrado con merma correctamente.',
                'diferencia' => $diferencia,
                'idProducto' => (int) $item['idProducto'],
                'nombreProducto' => (string) $item['nombreProducto'],
                'codigoProducto' => (string) $item['codigoProducto'],
            ];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'mensaje' => 'No se pudo cerrar el item con merma.'];
        }
    }

    public function obtenerItemsPorPredespachoYSector(int $idCabeceraPredespacho, string $sector): array
    {
        $statement = $this->db->prepare(
            'SELECT ip.idItem,
                                        ip.idInventarioEntrante,
                    ie.NumLote,
                    ie.idProducto,
                                        p.nombre AS nombreProducto,
                    ip.cantidadSolicitada,
                    COALESCE(salidas.cantidadDespachada, 0) AS cantidadDespachada,
                                        GREATEST(ip.cantidadSolicitada - COALESCE(salidas.cantidadDespachada, 0), 0) AS cantidadPendiente,
                    ip.estatusItemPredespacho,
                    CASE WHEN COALESCE(salidas.cantidadDespachada, 0) >= ip.cantidadSolicitada THEN 1 ELSE 0 END AS coincide
             FROM tbl_items_predespacho ip
             INNER JOIN tbl_cabecera_predespacho cp ON cp.idCabeceraPredespacho = ip.idCabeceraPredespacho
             INNER JOIN inventarioentrante ie ON ie.idInventarioEntrante = ip.idInventarioEntrante
                         INNER JOIN Producto p ON p.idProducto = ie.idProducto
             LEFT JOIN (
                 SELECT idInventarioEntrante, NE, SUM(cantidadSaliente) AS cantidadDespachada
                 FROM inventariosaliente
                 GROUP BY idInventarioEntrante, NE
             ) salidas ON salidas.idInventarioEntrante = ip.idInventarioEntrante
                                 AND salidas.NE COLLATE utf8mb4_unicode_ci = cp.codigoInterno
             WHERE ip.idCabeceraPredespacho = :idCabeceraPredespacho
               AND ie.sector = :sector
             ORDER BY ip.fechaCreacion ASC, ip.idItem ASC'
        );
        $statement->execute([
            'idCabeceraPredespacho' => $idCabeceraPredespacho,
            'sector' => $sector,
        ]);

        $items = $statement->fetchAll();
        foreach ($items as &$item) {
            $item['coincide'] = (bool) $item['coincide'];
        }

        return $items;
    }

    public function registrarSalida(int $idItem, float $cantidadDespachada, int $idCabeceraPredespacho): array
    {
        try {
            $this->db->beginTransaction();

            $statement = $this->db->prepare(
                                'SELECT ip.idInventarioEntrante,
                                                ip.cantidadSolicitada,
                                                ip.cantidadDespachada,
                                                ie.sector,
                                                cp.codigoInterno,
                                                cp.codigoNotaEntregaSAP,
                                                c.nombre AS nombreCliente
                                 FROM tbl_items_predespacho ip
                                 INNER JOIN inventarioentrante ie ON ie.idInventarioEntrante = ip.idInventarioEntrante
                                 INNER JOIN tbl_cabecera_predespacho cp ON cp.idCabeceraPredespacho = ip.idCabeceraPredespacho
                                 INNER JOIN tbl_cliente c ON c.idCliente = cp.idCliente
                                 WHERE ip.idItem = :idItem
                                     AND ip.idCabeceraPredespacho = :idCabeceraPredespacho
                 LIMIT 1
                 FOR UPDATE'
            );
            $statement->execute([
                'idItem' => $idItem,
                'idCabeceraPredespacho' => $idCabeceraPredespacho,
            ]);
            $item = $statement->fetch();

            if (!$item) {
                $this->db->rollBack();

                return [
                    'success' => false,
                    'mensaje' => 'Item de predespacho no encontrado.',
                    'predespachoEmbarcado' => false,
                ];
            }

            if ($cantidadDespachada <= 0) {
                $this->db->rollBack();

                return [
                    'success' => false,
                    'mensaje' => 'La cantidad debe ser mayor que cero.',
                    'predespachoEmbarcado' => false,
                    'predespacho_embarcado' => false,
                    'productoCerrado' => false,
                    'producto_cerrado' => false,
                ];
            }

            $statement = $this->db->prepare(
                'SELECT COALESCE(SUM(cantidadSaliente), 0)
                 FROM inventariosaliente
                 WHERE idInventarioEntrante = :idInventarioEntrante
                                     AND NE COLLATE utf8mb4_unicode_ci = :ne'
            );
            $statement->execute([
                'idInventarioEntrante' => (int) $item['idInventarioEntrante'],
                'ne' => (string) $item['codigoInterno'],
            ]);

            $cantidadDespachadaActual = (float) $statement->fetchColumn();
            $nuevaCantidadDespachada = $cantidadDespachadaActual + $cantidadDespachada;
            $cantidadPendiente = (float) $item['cantidadSolicitada'] - $cantidadDespachadaActual;

            if ($cantidadDespachada > $cantidadPendiente || $nuevaCantidadDespachada > (float) $item['cantidadSolicitada']) {
                $this->db->rollBack();

                return [
                    'success' => false,
                    'mensaje' => 'Cantidad supera el disponible.',
                    'predespachoEmbarcado' => false,
                    'predespacho_embarcado' => false,
                    'productoCerrado' => false,
                    'producto_cerrado' => false,
                ];
            }

            $estatusItemPredespacho = $nuevaCantidadDespachada >= (float) $item['cantidadSolicitada'] ? 'cerrado' : 'pendiente';
            $productoCerrado = $estatusItemPredespacho === 'cerrado';

            $statement = $this->db->prepare(
                'INSERT INTO inventariosaliente (idInventarioEntrante, sector, NE, cantidadSaliente, fecha)
                 VALUES (:idInventarioEntrante, :sector, :ne, :cantidadSaliente, NOW())'
            );
            $statement->execute([
                'idInventarioEntrante' => (int) $item['idInventarioEntrante'],
                'sector' => (string) $item['sector'],
                'ne' => (string) $item['codigoInterno'],
                'cantidadSaliente' => $cantidadDespachada,
            ]);

            $statement = $this->db->prepare(
                'UPDATE tbl_items_predespacho
                 SET cantidadDespachada = :cantidadDespachada,
                     estatusItemPredespacho = :estatusItemPredespacho
                 WHERE idItem = :idItem'
            );
            $statement->execute([
                'cantidadDespachada' => $nuevaCantidadDespachada,
                'estatusItemPredespacho' => $estatusItemPredespacho,
                'idItem' => $idItem,
            ]);

            $predespachoEmbarcado = $this->verificarYEmbarcarPredespacho($idCabeceraPredespacho);
            $this->db->commit();

            if ($predespachoEmbarcado) {
                try {
                    $detalleStmt = $this->db->prepare(
                        'SELECT p.nombre AS nombreProducto,
                                ie.NumLote,
                                ip.cantidadSolicitada,
                                ip.cantidadDespachada
                         FROM tbl_items_predespacho ip
                         INNER JOIN inventarioentrante ie ON ie.idInventarioEntrante = ip.idInventarioEntrante
                         INNER JOIN Producto p ON p.idProducto = ie.idProducto
                         WHERE ip.idCabeceraPredespacho = :idCabeceraPredespacho
                         ORDER BY ip.idItem ASC'
                    );
                    $detalleStmt->execute(['idCabeceraPredespacho' => $idCabeceraPredespacho]);
                    $itemsDetalle = $detalleStmt->fetchAll();

                    $codigoSAP = trim((string) ($item['codigoNotaEntregaSAP'] ?? ''));
                    $sapLinea = $codigoSAP !== ''
                        ? '*SAP:* ' . $codigoSAP
                        : '⚠️ *Sin código SAP registrado*';

                    $lineasProductos = [];
                    foreach ($itemsDetalle as $i => $detalle) {
                        $lineasProductos[] =
                            ($i + 1) . '. *' . $detalle['nombreProducto'] . "*\n"
                            . '   Lote: ' . $detalle['NumLote']
                            . ' | Sol: ' . number_format((float) $detalle['cantidadSolicitada'], 2, '.', '')
                            . ' | Ent: ' . number_format((float) $detalle['cantidadDespachada'], 2, '.', '');
                    }

                    $mensaje =
                        "✅ *Predespacho Embarcado*\n"
                        . "────────────────────\n"
                        . '*Código:* ' . $item['codigoInterno'] . "\n"
                        . '*Fecha de embarque:* ' . date('d/m/Y H:i') . "\n"
                        . '*Cliente:* ' . $item['nombreCliente'] . "\n"
                        . $sapLinea . "\n\n"
                        . '*Productos entregados (' . count($itemsDetalle) . '):*' . "\n"
                        . implode("\n", $lineasProductos);

                    enviarAlertaTelegram($mensaje);
                } catch (Throwable $exception) {
                }
            }

            return [
                'success' => true,
                'mensaje' => 'Entrega registrada correctamente',
                'predespachoEmbarcado' => $predespachoEmbarcado,
                'predespacho_embarcado' => $predespachoEmbarcado,
                'productoCerrado' => $productoCerrado,
                'producto_cerrado' => $productoCerrado,
            ];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'success' => false,
                'mensaje' => 'No se pudo registrar la salida.',
                'predespachoEmbarcado' => false,
                'predespacho_embarcado' => false,
                'productoCerrado' => false,
                'producto_cerrado' => false,
            ];
        }
    }
}