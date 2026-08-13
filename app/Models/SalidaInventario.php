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

    public function registrarSalida(int $idInventarioEntrante, string $sector, string $ne, float $cantidadSaliente): bool
    {
        $this->asegurarTablaInventarioSaliente();

        $statement = $this->db->prepare(
            'INSERT INTO inventariosaliente (idInventarioEntrante, sector, NE, cantidadSaliente, fecha)
             VALUES (:idInventarioEntrante, :sector, :ne, :cantidadSaliente, NOW())'
        );

        return $statement->execute([
            'idInventarioEntrante' => $idInventarioEntrante,
            'sector' => $sector,
            'ne' => $ne,
            'cantidadSaliente' => $cantidadSaliente,
        ]);
    }

    public function obtenerSalidas(): array
    {
        $this->asegurarTablaInventarioSaliente();

        $statement = $this->db->query(
            'SELECT ins.idInventarioSaliente,
                    ins.idInventarioEntrante,
                    ins.sector,
                    ins.NE,
                    ins.cantidadSaliente,
                    ins.fecha,
                    ie.NumLote,
                          ie.idPresentacion,
                          pr.nombre AS presentacion,
                          ie.`idUbicación` AS idUbicacion,
                          u.nombre AS ubicacion,
                    p.idProducto,
                    p.nombre AS producto,
                    ie.CantidadEntrante,
                    ie.CantidadEntrante - COALESCE(SUM(otras.cantidadSaliente), 0) AS disponibleSinEstaSalida
             FROM inventariosaliente ins
             INNER JOIN inventarioentrante ie ON ie.idInventarioEntrante = ins.idInventarioEntrante
             INNER JOIN Producto p ON p.idProducto = ie.idProducto
                      INNER JOIN presentacion pr ON pr.idPresentacion = ie.idPresentacion
                      INNER JOIN ubicacion u ON u.idUbicacion = ie.`idUbicación`
             LEFT JOIN inventariosaliente otras
                    ON otras.idInventarioEntrante = ins.idInventarioEntrante
                   AND otras.idInventarioSaliente <> ins.idInventarioSaliente
                      GROUP BY ins.idInventarioSaliente, ins.idInventarioEntrante, ins.sector, ins.NE, ins.cantidadSaliente, ins.fecha, ie.NumLote, ie.idPresentacion, pr.nombre, ie.`idUbicación`, u.nombre, p.idProducto, p.nombre, ie.CantidadEntrante
             ORDER BY ins.fecha DESC, ins.idInventarioSaliente DESC'
        );

        return $statement->fetchAll();
    }

    public function obtenerLotesParaCorreccion(): array
    {
        $this->asegurarTablaInventarioSaliente();

        $statement = $this->db->query(
            'SELECT ie.idInventarioEntrante,
                    ie.NumLote,
                      p.idProducto,
                    p.nombre AS producto,
                      ie.idPresentacion,
                      pr.nombre AS presentacion,
                      ie.`idUbicación` AS idUbicacion,
                      u.nombre AS ubicacion,
                    ie.CantidadEntrante,
                    ie.CantidadEntrante - COALESCE(SUM(ins.cantidadSaliente), 0) AS disponible
             FROM inventarioentrante ie
             INNER JOIN Producto p ON p.idProducto = ie.idProducto
                  INNER JOIN presentacion pr ON pr.idPresentacion = ie.idPresentacion
                  INNER JOIN ubicacion u ON u.idUbicacion = ie.`idUbicación`
             LEFT JOIN inventariosaliente ins ON ins.idInventarioEntrante = ie.idInventarioEntrante
                  GROUP BY ie.idInventarioEntrante, ie.NumLote, p.idProducto, p.nombre, ie.idPresentacion, pr.nombre, ie.`idUbicación`, u.nombre, ie.CantidadEntrante
             ORDER BY p.nombre ASC, ie.fecha DESC, ie.idInventarioEntrante DESC'
        );

        return $statement->fetchAll();
    }

    public function obtenerSalidaPorId(int $idInventarioSaliente): ?array
    {
        $this->asegurarTablaInventarioSaliente();

        $statement = $this->db->prepare(
            'SELECT idInventarioSaliente, idInventarioEntrante, sector, NE, cantidadSaliente
             FROM inventariosaliente
             WHERE idInventarioSaliente = :idInventarioSaliente'
        );
        $statement->execute(['idInventarioSaliente' => $idInventarioSaliente]);
        $salida = $statement->fetch();

        return $salida ?: null;
    }

    public function obtenerDisponibleParaCorreccion(int $idInventarioEntrante, int $idInventarioSaliente): float
    {
        $this->asegurarTablaInventarioSaliente();

        $statement = $this->db->prepare(
            'SELECT ie.CantidadEntrante - COALESCE(SUM(ins.cantidadSaliente), 0) AS disponible
             FROM inventarioentrante ie
             LEFT JOIN inventariosaliente ins
                    ON ins.idInventarioEntrante = ie.idInventarioEntrante
                   AND ins.idInventarioSaliente <> :idInventarioSaliente
             WHERE ie.idInventarioEntrante = :idInventarioEntrante
             GROUP BY ie.idInventarioEntrante, ie.CantidadEntrante'
        );
        $statement->execute([
            'idInventarioEntrante' => $idInventarioEntrante,
            'idInventarioSaliente' => $idInventarioSaliente,
        ]);
        $resultado = $statement->fetch();

        return $resultado ? (float) $resultado['disponible'] : 0;
    }

    public function actualizarSalida(int $idInventarioSaliente, int $idInventarioEntrante, string $ne, float $cantidadSaliente): bool
    {
        $this->asegurarTablaInventarioSaliente();

        $statement = $this->db->prepare(
            'UPDATE inventariosaliente
             SET idInventarioEntrante = :idInventarioEntrante,
                 NE = :ne,
                 cantidadSaliente = :cantidadSaliente
             WHERE idInventarioSaliente = :idInventarioSaliente'
        );

        return $statement->execute([
            'idInventarioEntrante' => $idInventarioEntrante,
            'ne' => $ne,
            'cantidadSaliente' => $cantidadSaliente,
            'idInventarioSaliente' => $idInventarioSaliente,
        ]);
    }

    public function eliminarSalida(int $idInventarioSaliente): bool
    {
        $this->asegurarTablaInventarioSaliente();

        $statement = $this->db->prepare(
            'DELETE FROM inventariosaliente
             WHERE idInventarioSaliente = :idInventarioSaliente'
        );

        $statement->execute(['idInventarioSaliente' => $idInventarioSaliente]);

        return $statement->rowCount() > 0;
    }

    public function sincronizarPredespachoPorCodigo(string $codigoInterno): bool
    {
        $codigoInterno = trim($codigoInterno);
        if ($codigoInterno === '') {
            return false;
        }

        $statement = $this->db->prepare(
            'SELECT idCabeceraPredespacho
             FROM tbl_cabecera_predespacho
             WHERE codigoInterno = :codigoInterno
             LIMIT 1'
        );
        $statement->execute(['codigoInterno' => $codigoInterno]);
        $idCabeceraPredespacho = (int) $statement->fetchColumn();

        if ($idCabeceraPredespacho <= 0) {
            return false;
        }

        $itemsStatement = $this->db->prepare(
            'SELECT ip.idItem,
                    ip.idInventarioEntrante,
                    ip.cantidadSolicitada,
                    COALESCE(SUM(ins.cantidadSaliente), 0) AS cantidadDespachada
             FROM tbl_items_predespacho ip
             LEFT JOIN inventariosaliente ins ON ins.idInventarioEntrante = ip.idInventarioEntrante
                AND ins.NE COLLATE utf8mb4_unicode_ci = :codigoInternoSalidas
             WHERE ip.idCabeceraPredespacho = :idCabeceraPredespacho
             GROUP BY ip.idItem, ip.idInventarioEntrante, ip.cantidadSolicitada'
        );
        $itemsStatement->execute([
            'codigoInternoSalidas' => $codigoInterno,
            'idCabeceraPredespacho' => $idCabeceraPredespacho,
        ]);
        $items = $itemsStatement->fetchAll();

        if (empty($items)) {
            return false;
        }

        $updateItem = $this->db->prepare(
            'UPDATE tbl_items_predespacho
             SET cantidadDespachada = :cantidadDespachada,
                 estatusItemPredespacho = :estatusItemPredespacho
             WHERE idItem = :idItem'
        );

        $todosCerrados = true;
        $tieneMovimiento = false;

        foreach ($items as $item) {
            $cantidadSolicitada = (float) $item['cantidadSolicitada'];
            $cantidadDespachada = (float) $item['cantidadDespachada'];
            $estatusItem = 'abierto';

            if ($cantidadDespachada > 0) {
                $tieneMovimiento = true;
                $estatusItem = $cantidadDespachada >= $cantidadSolicitada ? 'cerrado' : 'pendiente';
            }

            if ($estatusItem !== 'cerrado') {
                $todosCerrados = false;
            }

            $updateItem->execute([
                'cantidadDespachada' => $cantidadDespachada,
                'estatusItemPredespacho' => $estatusItem,
                'idItem' => (int) $item['idItem'],
            ]);
        }

        $estatusCabecera = $todosCerrados ? 'embarcado' : ($tieneMovimiento ? 'pendiente' : 'abierto');
        $statement = $this->db->prepare(
            'UPDATE tbl_cabecera_predespacho
             SET statusGeneralPredespacho = :statusGeneralPredespacho
             WHERE idCabeceraPredespacho = :idCabeceraPredespacho'
        );

        return $statement->execute([
            'statusGeneralPredespacho' => $estatusCabecera,
            'idCabeceraPredespacho' => $idCabeceraPredespacho,
        ]);
    }

    private function asegurarTablaInventarioSaliente(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS inventariosaliente (
                idInventarioSaliente INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                idInventarioEntrante INT UNSIGNED NOT NULL,
                sector VARCHAR(30) NULL,
                NE VARCHAR(80) NOT NULL,
                cantidadSaliente DECIMAL(12, 2) NOT NULL,
                fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_inventariosaliente_entrante (idInventarioEntrante)
            )'
        );

        if (!$this->existeColumna('inventariosaliente', 'sector')) {
            $this->db->exec('ALTER TABLE inventariosaliente ADD COLUMN sector VARCHAR(30) NULL AFTER idInventarioEntrante');
        }
    }

    private function existeColumna(string $tabla, string $columna): bool
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :tabla
               AND COLUMN_NAME = :columna'
        );
        $statement->execute([
            'tabla' => $tabla,
            'columna' => $columna,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }
}
