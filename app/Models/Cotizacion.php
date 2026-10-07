<?php

require_once __DIR__ . '/../Core/CotizacionLog.php';
require_once __DIR__ . '/../Core/CotizacionEsquemaException.php';

class Cotizacion extends BaseModel
{
    public function crearCotizacion(array $cabecera, array $detalles): int|false
    {
        if (empty($detalles)) {
            return false;
        }

        try {
            $esquema = $this->diagnosticoEsquema();
            CotizacionLog::registrar('bd.esquema', $esquema);
            if (!in_array($esquema['tipoIdCliente'], ['varchar', 'char'], true)) {
                throw new CotizacionEsquemaException(
                    'La base de datos tiene idCliente numerico y no puede guardar el codigo del cliente seleccionado. '
                    . 'No se guardo la cotizacion. Aplique la migracion repair_cotizacion_id_cliente.sql con respaldo previo '
                    . 'y compruebe en /cotizacion/diagnosticoLog que idCliente sea varchar(15).'
                );
            }
            $this->db->beginTransaction();
            $idCotizacion = $this->insertarCabecera($cabecera);
            if ($idCotizacion <= 0) {
                throw new RuntimeException('No se pudo obtener el identificador de la cotizacion.');
            }

            $verificacion = $this->db->prepare('SELECT idCliente FROM tbl_cotizacion_cabecera WHERE idCotizacion = :id');
            $verificacion->execute(['id' => $idCotizacion]);
            $idGuardado = (string) $verificacion->fetchColumn();
            CotizacionLog::registrar('bd.cabecera_insertada', [
                'idCotizacion' => $idCotizacion,
                'idClienteSeleccionado' => (string) $cabecera['idCliente'],
                'idClienteGuardado' => $idGuardado,
            ]);
            if ($idGuardado !== (string) $cabecera['idCliente']) {
                throw new RuntimeException('El cliente guardado no coincide con el seleccionado. La cotizacion fue revertida.');
            }
            $this->insertarDetalles($idCotizacion, $detalles);
            $cotizacion = $this->obtenerCotizacionPorId($idCotizacion);
            if (!$cotizacion || (string) $cotizacion['idClienteResuelto'] !== (string) $cabecera['idCliente']) {
                throw new RuntimeException('No se pudo resolver exactamente el cliente de la cotizacion.');
            }

            $this->db->commit();
            CotizacionLog::registrar('bd.commit', ['idCotizacion' => $idCotizacion, 'detalles' => count($detalles)]);

            return $idCotizacion;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            CotizacionLog::error('bd.rollback', $exception);
            throw $exception;
        }
    }

    public function diagnosticoEsquema(): array
    {
        $statement = $this->db->query(
            "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, COLLATION_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND ((TABLE_NAME = 'tbl_cotizacion_cabecera' AND COLUMN_NAME = 'idCliente')
                 OR (TABLE_NAME = 'tbl_clientes_cotizacion' AND COLUMN_NAME = 'CardCode'))"
        );
        $columnas = $statement->fetchAll();
        $tipo = '';
        foreach ($columnas as $columna) {
            if ($columna['TABLE_NAME'] === 'tbl_cotizacion_cabecera') {
                $tipo = strtolower((string) $columna['DATA_TYPE']);
            }
        }
        return [
            'tipoIdCliente' => $tipo,
            'columnas' => $columnas,
            'sqlMode' => (string) $this->db->query('SELECT @@SESSION.sql_mode')->fetchColumn(),
        ];
    }

    private function insertarCabecera(array $cabecera): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO tbl_cotizacion_cabecera
                (idCliente, diasVigencia, condicionPago, observacion, subtotal, total)
             VALUES
                (:idCliente, :diasVigencia, :condicionPago, :observacion, :subtotal, :total)'
        );
        $statement->execute([
            'idCliente' => $cabecera['idCliente'],
            'diasVigencia' => $cabecera['diasVigencia'] ?? 1,
            'condicionPago' => $cabecera['condicionPago'],
            'observacion' => $cabecera['observacion'] ?? null,
            'subtotal' => $cabecera['subtotal'] ?? 0,
            'total' => $cabecera['total'] ?? 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function insertarDetalles(int $idCotizacion, array $detalles): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO tbl_cotizacion_detalle
                (idCotizacion, idProducto, idPresentacion, cantidad, precioUnitario, subtotal)
             VALUES
                (:idCotizacion, :idProducto, :idPresentacion, :cantidad, :precioUnitario, :subtotal)'
        );

        foreach ($detalles as $detalle) {
            $statement->execute([
                'idCotizacion' => $idCotizacion,
                'idProducto' => $detalle['idProducto'],
                'idPresentacion' => $detalle['idPresentacion'],
                'cantidad' => $detalle['cantidad'],
                'precioUnitario' => $detalle['precioUnitario'],
                'subtotal' => $detalle['subtotal'],
            ]);
        }
    }

    public function obtenerCotizacionesActivas(?string $idCliente = null): array
    {
        $filtroCliente = $idCliente !== null && trim($idCliente) !== '' ? ' AND BINARY CAST(cotizacion.idCliente AS CHAR) = BINARY :idCliente' : '';
        $statement = $this->db->prepare(
            'SELECT cotizacion.idCotizacion,
                    cotizacion.idCliente,
                    cliente.LicTradNum AS rifCliente,
                    cliente.CardName AS nombreCliente,
                    cliente.E_Mail AS emailCliente,
                    cotizacion.fechaEmision,
                    cotizacion.diasVigencia,
                    cotizacion.condicionPago,
                    cotizacion.observacion,
                    cotizacion.subtotal,
                    cotizacion.total,
                    cotizacion.fechaCreacion,
                    cotizacion.fechaActualizacion,
                    (SELECT COUNT(*)
                     FROM tbl_cotizacion_detalle detalle
                     WHERE detalle.idCotizacion = cotizacion.idCotizacion) AS cantidadProductos
             FROM tbl_cotizacion_cabecera cotizacion
             INNER JOIN tbl_clientes_cotizacion cliente ON BINARY cliente.CardCode = BINARY CAST(cotizacion.idCliente AS CHAR)
               WHERE cotizacion.activo = :activo' . $filtroCliente . '
             ORDER BY cotizacion.fechaEmision DESC, cotizacion.idCotizacion DESC'
        );
           $params = ['activo' => 1];
           if ($filtroCliente !== '') {
              $params['idCliente'] = trim($idCliente);
           }
           $statement->execute($params);

        return $statement->fetchAll();
    }

    public function obtenerCotizacionPorId(int $idCotizacion): ?array
    {
        $statement = $this->db->prepare(
            'SELECT cotizacion.idCotizacion,
                    cotizacion.idCliente,
                    cliente.CardCode AS idClienteResuelto,
                    cliente.LicTradNum AS rifCliente,
                    cliente.CardName AS nombreCliente,
                    COALESCE(cliente.MailAddres, cliente.Address) AS direccionCliente,
                    cliente.E_Mail AS emailCliente,
                    cliente.Phone1 AS telefonoCliente,
                    cotizacion.fechaEmision,
                    cotizacion.fechaCreacion,
                    cotizacion.diasVigencia,
                    cotizacion.condicionPago,
                    cotizacion.observacion,
                    cotizacion.subtotal,
                    cotizacion.total
             FROM tbl_cotizacion_cabecera cotizacion
             INNER JOIN tbl_clientes_cotizacion cliente ON BINARY cliente.CardCode = BINARY CAST(cotizacion.idCliente AS CHAR)
             WHERE cotizacion.idCotizacion = :idCotizacion
               AND cotizacion.activo = :activo
             LIMIT 1'
        );
        $statement->execute([
            'idCotizacion' => $idCotizacion,
            'activo' => 1,
        ]);
        $cotizacion = $statement->fetch();

        if (!$cotizacion) {
            $cabeceraStatement = $this->db->prepare(
                'SELECT idCliente, activo FROM tbl_cotizacion_cabecera WHERE idCotizacion = :id'
            );
            $cabeceraStatement->execute(['id' => $idCotizacion]);
            $cabecera = $cabeceraStatement->fetch();
            CotizacionLog::registrar('bd.cotizacion_no_resuelta', [
                'idCotizacion' => $idCotizacion,
                'idClienteGuardado' => $cabecera ? (string) $cabecera['idCliente'] : null,
                'activo' => $cabecera ? (int) $cabecera['activo'] : null,
                'motivo' => $cabecera ? 'Sin cliente exacto o cotizacion inactiva.' : 'Cabecera inexistente.',
            ]);
            return null;
        }
        if ((string) $cotizacion['idCliente'] !== (string) $cotizacion['idClienteResuelto']) {
            throw new RuntimeException('El cliente recuperado no coincide con el codigo guardado.');
        }

        $detalleStatement = $this->db->prepare(
            'SELECT detalle.idDetalle,
                    detalle.idProducto,
                    producto.codigoInterno AS codigoProducto,
                    producto.nombre AS producto,
                    detalle.idPresentacion,
                    presentacion.nombre AS presentacion,
                    detalle.cantidad,
                    detalle.precioUnitario,
                    detalle.subtotal
             FROM tbl_cotizacion_detalle detalle
             INNER JOIN Producto producto ON producto.idProducto = detalle.idProducto
             INNER JOIN presentacion ON presentacion.idPresentacion = detalle.idPresentacion
             WHERE detalle.idCotizacion = :idCotizacion
             ORDER BY detalle.idDetalle ASC'
        );
        $detalleStatement->execute(['idCotizacion' => $idCotizacion]);
        $cotizacion['detalles'] = $detalleStatement->fetchAll();
        CotizacionLog::registrar('bd.cotizacion_resuelta', [
            'idCotizacion' => $idCotizacion,
            'idClienteGuardado' => (string) $cotizacion['idCliente'],
            'idClienteResuelto' => (string) $cotizacion['idClienteResuelto'],
            'nombreCliente' => $cotizacion['nombreCliente'],
            'rifCliente' => $cotizacion['rifCliente'],
            'detalles' => count($cotizacion['detalles']),
        ]);

        return $cotizacion;
    }

    public function desactivarCotizacion(int $idCotizacion): bool
    {
        try {
            $statement = $this->db->prepare(
                'UPDATE tbl_cotizacion_cabecera
                 SET activo = :activo
                 WHERE idCotizacion = :idCotizacion
                   AND activo = :activoActual'
            );
            $statement->execute([
                'activo' => 0,
                'activoActual' => 1,
                'idCotizacion' => $idCotizacion,
            ]);

            return $statement->rowCount() === 1;
        } catch (Throwable $exception) {
            return false;
        }
    }
}