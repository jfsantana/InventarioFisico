<?php

class EntradaInventario extends BaseModel
{
    public function obtenerProductos(): array
    {
        $statement = $this->db->query('SELECT idProducto, codigoInterno, nombre FROM Producto ORDER BY nombre ASC');

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

    public function obtenerTiposCompra(): array
    {
        $statement = $this->db->query('SELECT id, descripcion FROM tipo_compra ORDER BY descripcion ASC');

        return $statement->fetchAll();
    }

    public function obtenerProveedores(): array
    {
        $statement = $this->db->query('SELECT CardCode, CardName FROM proveedores ORDER BY CardName ASC, CardCode ASC');

        return $statement->fetchAll();
    }

    public function obtenerPaises(): array
    {
        $statement = $this->db->query('SELECT Code, Name FROM paises ORDER BY Name ASC');

        return $statement->fetchAll();
    }

    public function obtenerDestinatariosEntrada(): array
    {
        $statement = $this->db->query(
            "SELECT nombre, email
             FROM contactosinternosemail
             WHERE LOWER(TRIM(proceso)) = 'entrada'
               AND TRIM(email) <> ''
             ORDER BY nombre ASC"
        );

        return array_values(array_filter(
            $statement->fetchAll(),
            static fn (array $contacto): bool => filter_var($contacto['email'], FILTER_VALIDATE_EMAIL) !== false
        ));
    }

    public function obtenerDetalleEntradaParaCorreo(int $idInventarioEntrante): ?array
    {
        $statement = $this->db->prepare(
            'SELECT ie.idInventarioEntrante,
                    ie.fecha,
                    tc.descripcion AS tipoCompra,
                    p.nombre AS producto,
                    proveedor.CardName AS proveedor,
                    fabricante.CardName AS fabricante,
                    pais.Name AS pais,
                    ie.NumLote,
                    ie.CantidadEntrante,
                    ie.fecha_factura,
                    ie.peso_romana,
                    ie.nro_factura,
                    presentacion.nombre AS presentacion
             FROM inventarioentrante ie
             INNER JOIN Producto p ON p.idProducto = ie.idProducto
             INNER JOIN presentacion ON presentacion.idPresentacion = ie.idPresentacion
             LEFT JOIN tipo_compra tc ON tc.id = ie.idTipoCompra
             LEFT JOIN proveedores proveedor ON proveedor.CardCode = ie.CardCode
             LEFT JOIN proveedores fabricante ON fabricante.CardCode = ie.FabricanteCode
             LEFT JOIN paises pais ON pais.Code = ie.PaisCode
             WHERE ie.idInventarioEntrante = :idInventarioEntrante
             LIMIT 1'
        );
        $statement->execute(['idInventarioEntrante' => $idInventarioEntrante]);
        $entrada = $statement->fetch();

        return $entrada ?: null;
    }

    public function registrarEntrada(array $data, array $asignacionesSilo = []): int
    {
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                'INSERT INTO inventarioentrante
                    (NumLote, idProducto, idPresentacion, `idUbicación`, CantidadEntrante, fecha, sector, idTipoCompra, CardCode, FabricanteCode, PaisCode, fecha_factura, peso_romana, nro_factura)
                 VALUES
                    (:numLote, :idProducto, :idPresentacion, :idUbicacion, :cantidadEntrante, CURDATE(), :sector, :idTipoCompra, :cardCode, :fabricanteCode, :paisCode, :fechaFactura, :pesoRomana, :nroFactura)'
            );

            $statement->execute([
                'numLote' => $data['NumLote'],
                'idProducto' => $data['idProducto'],
                'idPresentacion' => $data['idPresentacion'],
                'idUbicacion' => $data['idUbicacion'],
                'cantidadEntrante' => $data['CantidadEntrante'],
                'sector' => $data['Sector'],
                'idTipoCompra' => $data['idTipoCompra'],
                'cardCode' => $data['CardCode'],
                'fabricanteCode' => $data['FabricanteCode'],
                'paisCode' => $data['PaisCode'],
                'fechaFactura' => $data['fecha_factura'],
                'pesoRomana' => $data['peso_romana'],
                'nroFactura' => $data['nro_factura'],
            ]);

            $idEntrada = (int) $this->db->lastInsertId();
            if ($this->esSector3((string) $data['Sector'])) {
                (new SiloStockService($this->db))->asignarEntrada(
                    $idEntrada,
                    (int) $data['idProducto'],
                    (float) $data['CantidadEntrante'],
                    $asignacionesSilo
                );
            }
            $this->db->commit();

            return $idEntrada;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function esSector3(string $sector): bool
    {
        return str_replace(' ', '', strtolower(trim($sector))) === 'sector3';
    }

    public function obtenerDocumentosEntrada(int $idInventarioEntrante): array
    {
        $statement = $this->db->prepare(
            'SELECT idDocumento, idInventarioEntrante, tipoDocumento, nombreOriginal,
                    nombreAlmacenado, rutaRelativa, mimeType, tamanoBytes, idUsuario, fechaCarga
             FROM entrada_documentos
             WHERE idInventarioEntrante = :idInventarioEntrante
             ORDER BY idDocumento ASC'
        );
        $statement->execute(['idInventarioEntrante' => $idInventarioEntrante]);

        $documentos = [];
        foreach ($statement->fetchAll() as $documento) {
            $documentos[$documento['tipoDocumento']] = $documento;
        }

        return $documentos;
    }

    public function obtenerTodosDocumentos(): array
    {
        $statement = $this->db->query(
            'SELECT idDocumento, idInventarioEntrante, tipoDocumento, nombreOriginal,
                    nombreAlmacenado, rutaRelativa, mimeType, tamanoBytes, idUsuario, fechaCarga
             FROM entrada_documentos
             ORDER BY idInventarioEntrante ASC, idDocumento ASC'
        );

        $documentosPorEntrada = [];
        foreach ($statement->fetchAll() as $documento) {
            $documentosPorEntrada[(int) $documento['idInventarioEntrante']][$documento['tipoDocumento']] = $documento;
        }

        return $documentosPorEntrada;
    }

    public function obtenerAsignacionesSiloPorEntradas(): array
    {
        $statement = $this->db->query(
            'SELECT a.idInventarioEntrante, a.idSilo, s.codigo, s.nombre,
                    a.cantidadAsignada,
                    COALESCE(SUM(ss.cantidad), 0) AS cantidadConsumida,
                    GREATEST(a.cantidadAsignada - COALESCE(SUM(ss.cantidad), 0), 0) AS cantidadDisponible
             FROM silo_asignaciones a
             INNER JOIN silos s ON s.idSilo = a.idSilo
             LEFT JOIN silo_salidas ss ON ss.idAsignacion = a.idAsignacion
             GROUP BY a.idAsignacion, a.idInventarioEntrante, a.idSilo, s.codigo, s.nombre, a.cantidadAsignada
             ORDER BY a.idInventarioEntrante, a.fechaAsignacion, a.idAsignacion'
        );

        $porEntrada = [];
        foreach ($statement->fetchAll() as $asignacion) {
            $porEntrada[(int) $asignacion['idInventarioEntrante']][] = $asignacion;
        }

        return $porEntrada;
    }

    public function obtenerDocumentoPorId(int $idDocumento): ?array
    {
        $statement = $this->db->prepare(
            'SELECT idDocumento, idInventarioEntrante, tipoDocumento, nombreOriginal,
                    nombreAlmacenado, rutaRelativa, mimeType, tamanoBytes, idUsuario, fechaCarga
             FROM entrada_documentos
             WHERE idDocumento = :idDocumento
             LIMIT 1'
        );
        $statement->execute(['idDocumento' => $idDocumento]);
        $documento = $statement->fetch();

        return $documento ?: null;
    }

    public function guardarDocumento(int $idInventarioEntrante, array $documento): bool
    {
        $statement = $this->db->prepare(
            'INSERT INTO entrada_documentos
                (idInventarioEntrante, tipoDocumento, nombreOriginal, nombreAlmacenado, rutaRelativa, mimeType, tamanoBytes, idUsuario)
             VALUES
                (:idInventarioEntrante, :tipoDocumento, :nombreOriginal, :nombreAlmacenado, :rutaRelativa, :mimeType, :tamanoBytes, :idUsuario)
             ON DUPLICATE KEY UPDATE
                nombreOriginal = VALUES(nombreOriginal),
                nombreAlmacenado = VALUES(nombreAlmacenado),
                rutaRelativa = VALUES(rutaRelativa),
                mimeType = VALUES(mimeType),
                tamanoBytes = VALUES(tamanoBytes),
                idUsuario = VALUES(idUsuario),
                fechaCarga = CURRENT_TIMESTAMP'
        );

        return $statement->execute([
            'idInventarioEntrante' => $idInventarioEntrante,
            'tipoDocumento' => $documento['tipoDocumento'],
            'nombreOriginal' => $documento['nombreOriginal'],
            'nombreAlmacenado' => $documento['nombreAlmacenado'],
            'rutaRelativa' => $documento['rutaRelativa'],
            'mimeType' => $documento['mimeType'],
            'tamanoBytes' => $documento['tamanoBytes'],
            'idUsuario' => $documento['idUsuario'],
        ]);
    }

    public function eliminarDocumentosEntrada(int $idInventarioEntrante): bool
    {
        $statement = $this->db->prepare(
            'DELETE FROM entrada_documentos WHERE idInventarioEntrante = :idInventarioEntrante'
        );

        return $statement->execute(['idInventarioEntrante' => $idInventarioEntrante]);
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
                    ie.idTipoCompra,
                    tc.descripcion AS tipoCompra,
                    ie.CardCode,
                    proveedor.CardName AS proveedor,
                    ie.FabricanteCode,
                    fabricante.CardName AS fabricante,
                    ie.PaisCode,
                    pais.Name AS pais,
                    ie.fecha_factura,
                    ie.peso_romana,
                    ie.nro_factura,
                    COALESCE(SUM(ins.cantidadSaliente), 0) AS salidaTotal,
                    ie.CantidadEntrante - COALESCE(SUM(ins.cantidadSaliente), 0) AS disponible
             FROM inventarioentrante ie
             INNER JOIN Producto p ON p.idProducto = ie.idProducto
             INNER JOIN presentacion pr ON pr.idPresentacion = ie.idPresentacion
             INNER JOIN ubicacion u ON u.idUbicacion = ie.`idUbicación`
                         LEFT JOIN tipo_compra tc ON tc.id = ie.idTipoCompra
                         LEFT JOIN proveedores proveedor ON proveedor.CardCode = ie.CardCode
                         LEFT JOIN proveedores fabricante ON fabricante.CardCode = ie.FabricanteCode
                         LEFT JOIN paises pais ON pais.Code = ie.PaisCode
             LEFT JOIN inventariosaliente ins ON ins.idInventarioEntrante = ie.idInventarioEntrante
                             GROUP BY ie.idInventarioEntrante, ie.NumLote, ie.idProducto, p.nombre, ie.idPresentacion, pr.nombre, ie.`idUbicación`, u.nombre, ie.sector, ie.CantidadEntrante, ie.fecha, ie.idTipoCompra, tc.descripcion, ie.CardCode, proveedor.CardName, ie.FabricanteCode, fabricante.CardName, ie.PaisCode, pais.Name, ie.fecha_factura, ie.peso_romana, ie.nro_factura
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

    public function actualizarEntrada(int $idInventarioEntrante, array $data, array $asignacionesSilo = []): bool
    {
        $this->db->beginTransaction();
        try {
        $statement = $this->db->prepare(
            'SELECT ie.idProducto, ie.sector, ie.CantidadEntrante,
                    EXISTS(SELECT 1 FROM silo_asignaciones sa WHERE sa.idInventarioEntrante = ie.idInventarioEntrante) AS tieneAsignaciones
             FROM inventarioentrante ie
             WHERE ie.idInventarioEntrante = :idInventarioEntrante
             FOR UPDATE'
        );
        $statement->execute(['idInventarioEntrante' => $idInventarioEntrante]);
        $actual = $statement->fetch();
        if (!$actual) {
            throw new InvalidArgumentException('La entrada no existe.');
        }

        $eraSector3 = $this->esSector3((string) $actual['sector']);
        $seraSector3 = $this->esSector3((string) $data['Sector']);
        if (!$eraSector3 && $seraSector3) {
            throw new DomainException('No puede mover una entrada existente a Sector3 sin distribuirla entre silos. Registre una nueva entrada o use el módulo de silos.');
        }
        if ((int) $actual['tieneAsignaciones'] === 1
            && (!$seraSector3 || (int) $actual['idProducto'] !== (int) $data['idProducto'])) {
            throw new DomainException('Una entrada distribuida en silos no permite cambiar el producto ni salir de Sector3.');
        }

        $statement = $this->db->prepare(
            'UPDATE inventarioentrante
             SET NumLote = :numLote,
                 idProducto = :idProducto,
                 idPresentacion = :idPresentacion,
                 `idUbicación` = :idUbicacion,
                 sector = :sector,
                 CantidadEntrante = :cantidadEntrante,
                 idTipoCompra = :idTipoCompra,
                 CardCode = :cardCode,
                 FabricanteCode = :fabricanteCode,
                 PaisCode = :paisCode,
                 fecha_factura = :fechaFactura,
                 peso_romana = :pesoRomana,
                 nro_factura = :nroFactura,
                 observaciones = :observaciones
             WHERE idInventarioEntrante = :idInventarioEntrante'
        );

        $updated = $statement->execute([
            'numLote' => $data['NumLote'],
            'idProducto' => $data['idProducto'],
            'idPresentacion' => $data['idPresentacion'],
            'idUbicacion' => $data['idUbicacion'],
            'sector' => $data['Sector'],
            'cantidadEntrante' => $data['CantidadEntrante'],
            'idTipoCompra' => $data['idTipoCompra'],
            'cardCode' => $data['CardCode'],
            'fabricanteCode' => $data['FabricanteCode'],
            'paisCode' => $data['PaisCode'],
            'fechaFactura' => $data['fecha_factura'],
            'pesoRomana' => $data['peso_romana'],
            'nroFactura' => $data['nro_factura'],
            'observaciones' => $data['observaciones'],
            'idInventarioEntrante' => $idInventarioEntrante,
        ]);

        if ($seraSector3) {
            $statement = $this->db->prepare(
                'SELECT COALESCE(SUM(cantidadSaliente), 0)
                 FROM inventariosaliente
                 WHERE idInventarioEntrante = :idInventarioEntrante'
            );
            $statement->execute(['idInventarioEntrante' => $idInventarioEntrante]);
            $saldoFisico = (float) $data['CantidadEntrante'] - (float) $statement->fetchColumn();
            (new SiloStockService($this->db))->redistribuirSaldoEntrada(
                $idInventarioEntrante,
                (int) $data['idProducto'],
                $saldoFisico,
                $asignacionesSilo
            );
        }

        $this->db->commit();
        return $updated;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
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
