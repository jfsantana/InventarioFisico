<?php

class Cotizacion extends BaseModel
{
    public function crearCotizacion(array $cabecera, array $detalles): int|false
    {
        if (empty($detalles)) {
            return false;
        }

        try {
            $this->db->beginTransaction();
            $idCotizacion = $this->insertarCabecera($cabecera);
            if ($idCotizacion <= 0) {
                throw new RuntimeException('No se pudo obtener el identificador de la cotizacion.');
            }

            $this->insertarDetalles($idCotizacion, $detalles);

            $this->db->commit();

            return $idCotizacion;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return false;
        }
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

    public function obtenerCotizacionesActivas(): array
    {
        $statement = $this->db->prepare(
            'SELECT cotizacion.idCotizacion,
                    cotizacion.idCliente,
                    cliente.rif AS rifCliente,
                    cliente.nombre AS nombreCliente,
                    cliente.email AS emailCliente,
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
             INNER JOIN tbl_cliente cliente ON cliente.idCliente = cotizacion.idCliente
             WHERE cotizacion.activo = :activo
             ORDER BY cotizacion.fechaEmision DESC, cotizacion.idCotizacion DESC'
        );
        $statement->execute(['activo' => 1]);

        return $statement->fetchAll();
    }

    public function obtenerCotizacionPorId(int $idCotizacion): ?array
    {
        $statement = $this->db->prepare(
            'SELECT cotizacion.idCotizacion,
                    cotizacion.idCliente,
                    cliente.rif AS rifCliente,
                    cliente.nombre AS nombreCliente,
                    cliente.direccion AS direccionCliente,
                    cliente.email AS emailCliente,
                    cliente.telefono AS telefonoCliente,
                    cotizacion.fechaEmision,
                    cotizacion.fechaCreacion,
                    cotizacion.diasVigencia,
                    cotizacion.condicionPago,
                    cotizacion.observacion,
                    cotizacion.subtotal,
                    cotizacion.total
             FROM tbl_cotizacion_cabecera cotizacion
             INNER JOIN tbl_cliente cliente ON cliente.idCliente = cotizacion.idCliente
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
            return null;
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