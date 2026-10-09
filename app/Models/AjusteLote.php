<?php

class AjusteLote extends BaseModel
{
    public function obtenerLotes(string $busqueda = ''): array
    {
        $this->db->query('SELECT idAjuste, idSilo, monto FROM ajustes_lote_silos LIMIT 0');
        $sql = 'SELECT dl.idInventarioEntrante, dl.idProducto, dl.idPresentacion,
                       dl.NumLote, dl.`idUbicación`, dl.sector, dl.stock_total,
                       dl.ajuste_positivo, dl.ajuste_negativo,
                       dl.cantidad_reservada, dl.cantidad_disponible,
                       p.nombre AS producto, p.codigoInterno, pr.nombre AS presentacion,
                       ie.fecha, u.nombre AS ubicacion
                FROM v_disponibilidad_lotes dl
                INNER JOIN inventarioentrante ie ON ie.idInventarioEntrante = dl.idInventarioEntrante
                INNER JOIN Producto p ON p.idProducto = dl.idProducto
                LEFT JOIN presentacion pr ON pr.idPresentacion = dl.idPresentacion
                LEFT JOIN ubicacion u ON u.idUbicacion = dl.`idUbicación`';
        $params = [];
        if ($busqueda !== '') {
            $sql .= ' WHERE dl.NumLote LIKE :lote OR p.nombre LIKE :producto OR p.codigoInterno LIKE :codigo';
            $params = ['lote' => '%' . $busqueda . '%', 'producto' => '%' . $busqueda . '%', 'codigo' => '%' . $busqueda . '%'];
        }
        $statement = $this->db->prepare($sql . ' ORDER BY p.nombre, ie.fecha DESC, dl.idInventarioEntrante DESC');
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function obtenerHistorial(int $idEntrada): array
    {
        $statement = $this->db->prepare(
            'SELECT a.*, COALESCE(detalle.silos, s.codigo) AS silo
             FROM ajustes_lote a
             LEFT JOIN silos s ON s.idSilo = a.idSilo
             LEFT JOIN (
                 SELECT d.idAjuste,
                        GROUP_CONCAT(CONCAT(s.codigo, ": ", d.monto) ORDER BY s.codigo SEPARATOR "; ") AS silos
                 FROM ajustes_lote_silos d INNER JOIN silos s ON s.idSilo = d.idSilo
                 GROUP BY d.idAjuste
             ) detalle ON detalle.idAjuste = a.idAjuste
             WHERE a.idInventarioEntrante = :idEntrada
             ORDER BY a.fechaCreacion DESC, a.idAjuste DESC'
        );
        $statement->execute(['idEntrada' => $idEntrada]);
        return $statement->fetchAll();
    }

    public function obtenerSilosParaAjuste(int $idEntrada, string $tipo): array
    {
        if (!in_array($tipo, ['positivo', 'negativo'], true)) {
            throw new InvalidArgumentException('Seleccione el tipo de ajuste.');
        }
        $statement = $this->db->prepare(
            'SELECT idProducto, sector FROM inventarioentrante WHERE idInventarioEntrante = :id'
        );
        $statement->execute(['id' => $idEntrada]);
        $entrada = $statement->fetch();
        if (!$entrada || str_replace(' ', '', strtolower((string) $entrada['sector'])) !== 'sector3') {
            throw new DomainException('Seleccione un lote de Sector3.');
        }
        if ($tipo === 'positivo') {
            $statement = $this->db->prepare(
                'SELECT idSilo, codigo, nombre, capacidadDisponible AS cantidadDisponible
                 FROM v_estado_silos
                 WHERE activo = 1 AND capacidadDisponible > 0.0005
                   AND productosActivos <= 1
                   AND (cantidadOcupada <= 0.0005 OR idProductoActual = :producto)
                 ORDER BY codigo'
            );
            $statement->execute(['producto' => $entrada['idProducto']]);
        } else {
            $statement = $this->db->prepare(
                'SELECT s.idSilo, s.codigo, s.nombre,
                        SUM(a.cantidadAsignada - COALESCE(x.cantidad, 0)) AS cantidadDisponible
                 FROM silo_asignaciones a
                 INNER JOIN silos s ON s.idSilo = a.idSilo
                 LEFT JOIN (SELECT idAsignacion, SUM(cantidad) AS cantidad FROM silo_salidas GROUP BY idAsignacion) x
                    ON x.idAsignacion = a.idAsignacion
                 WHERE a.idInventarioEntrante = :entrada
                 GROUP BY s.idSilo, s.codigo, s.nombre
                 HAVING cantidadDisponible > 0.0005 ORDER BY s.codigo'
            );
            $statement->execute(['entrada' => $idEntrada]);
        }
        return $statement->fetchAll();
    }

    public function registrar(array $data, int $idUsuario): int
    {
        $monto = trim((string) ($data['monto'] ?? ''));
        $tipo = (string) ($data['tipo'] ?? '');
        $observacion = trim((string) ($data['observacion'] ?? ''));
        $idEntrada = filter_var($data['idInventarioEntrante'] ?? null, FILTER_VALIDATE_INT);
        $token = (string) ($data['tokenRegistro'] ?? '');
        if (!$idEntrada || $idEntrada < 1 || !in_array($tipo, ['positivo', 'negativo'], true)
            || !preg_match('/^\d{1,11}(?:\.\d{1,3})?$/D', $monto) || (float) $monto <= 0
            || $observacion === '' || mb_strlen($observacion) > 1000
            || !preg_match('/^[a-f0-9]{64}$/D', $token) || $idUsuario < 1) {
            throw new InvalidArgumentException('Seleccione un lote y tipo validos, monto mayor que cero (hasta 3 decimales) y observacion de hasta 1000 caracteres.');
        }

        // La capacidad de un silo puede cambiar mientras se espera su bloqueo.
        $this->db->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare('SELECT idProducto, sector, fecha, NOW() AS fechaActual FROM inventarioentrante WHERE idInventarioEntrante = :id FOR UPDATE');
            $statement->execute(['id' => $idEntrada]);
            $entrada = $statement->fetch();
            if (!$entrada) {
                throw new DomainException('El lote no existe.');
            }
            if ((string) $entrada['fecha'] > (string) $entrada['fechaActual']) {
                throw new DomainException('No se puede ajustar un lote cuya entrada tiene una fecha futura.');
            }
            $statement = $this->db->prepare('SELECT nombre_completo FROM usuarios WHERE id_usuario = :id AND activo = 1');
            $statement->execute(['id' => $idUsuario]);
            $responsable = $statement->fetchColumn();
            if ($responsable === false) {
                throw new DomainException('El responsable no es un usuario activo.');
            }
            $statement = $this->db->prepare('SELECT * FROM v_disponibilidad_lotes WHERE idInventarioEntrante = :id');
            $statement->execute(['id' => $idEntrada]);
            $stock = $statement->fetch();
            if (!$stock) {
                throw new DomainException('No se pudo consultar la disponibilidad del lote.');
            }
            if ($stock['stock_total'] === null) {
                throw new DomainException('El lote no tiene cantidad de entrada. Corrija ese dato antes de registrar ajustes.');
            }
            if ($tipo === 'negativo' && (float) $monto - (float) $stock['cantidad_disponible'] > 0.0005) {
                throw new DomainException('El ajuste supera el disponible libre de reservas: ' . number_format((float) $stock['cantidad_disponible'], 3) . '.');
            }
            $idSilo = null;
            $detalleSilos = [];
            if (str_replace(' ', '', strtolower((string) $entrada['sector'])) === 'sector3') {
                $detalleSilos = $data['asignacionesSilo'] ?? [];
                if (!$detalleSilos && !empty($data['idSilo'])) {
                    $detalleSilos = [['idSilo' => $data['idSilo'], 'cantidad' => $monto]];
                }
                $detalleSilos = $this->ajustarSilos($idEntrada, (int) $entrada['idProducto'], $detalleSilos, $tipo, (float) $monto, $stock);
                $idSilo = count($detalleSilos) === 1 ? $detalleSilos[0]['idSilo'] : null;
            } elseif (!empty($data['idSilo']) || !empty($data['asignacionesSilo'])) {
                throw new DomainException('Este lote no tiene control por silos.');
            }
            $statement = $this->db->prepare(
                'INSERT INTO ajustes_lote (idInventarioEntrante, tipo, monto, observacion, idUsuario, responsable, idSilo, tokenRegistro)
                 VALUES (:entrada, :tipo, :monto, :observacion, :usuario, :responsable, :silo, :token)'
            );
            $statement->execute([
                'entrada' => $idEntrada, 'tipo' => $tipo, 'monto' => $monto, 'observacion' => $observacion,
                'usuario' => $idUsuario, 'responsable' => $responsable, 'silo' => $idSilo, 'token' => $token,
            ]);
            $id = (int) $this->db->lastInsertId();
            $detalle = $this->db->prepare('INSERT INTO ajustes_lote_silos (idAjuste, idSilo, monto) VALUES (:ajuste, :silo, :monto)');
            foreach ($detalleSilos as $fila) {
                $detalle->execute(['ajuste' => $id, 'silo' => $fila['idSilo'], 'monto' => $fila['cantidad']]);
            }
            $this->db->commit();
            return $id;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function ajustarSilos(int $idEntrada, int $idProducto, array $detalle, string $tipo, float $monto, array $stock): array
    {
        $normalizadas = [];
        foreach ($detalle as $fila) {
            $id = filter_var($fila['idSilo'] ?? null, FILTER_VALIDATE_INT);
            $cantidad = trim((string) ($fila['cantidad'] ?? ''));
            if (!$id || $id < 1 || isset($normalizadas[$id])
                || !preg_match('/^\d{1,11}(?:\.\d{1,3})?$/D', $cantidad) || (float) $cantidad <= 0) {
                throw new DomainException('Seleccione silos sin repetir y cantidades positivas de hasta 3 decimales.');
            }
            $normalizadas[$id] = ['idSilo' => $id, 'cantidad' => $cantidad];
        }
        if (!$normalizadas || abs(array_sum(array_column($normalizadas, 'cantidad')) - $monto) > 0.0005) {
            throw new DomainException('Las cantidades de los silos deben sumar exactamente el monto del ajuste.');
        }
        $statement = $this->db->prepare(
            'SELECT a.idSilo, a.cantidadAsignada - COALESCE(x.cantidad, 0) AS cantidad
             FROM silo_asignaciones a
             LEFT JOIN (SELECT idAsignacion, SUM(cantidad) AS cantidad FROM silo_salidas GROUP BY idAsignacion) x
                    ON x.idAsignacion = a.idAsignacion
             WHERE a.idInventarioEntrante = :entrada ORDER BY a.idSilo FOR UPDATE'
        );
        $statement->execute(['entrada' => $idEntrada]);
        $asignaciones = [];
        foreach ($statement->fetchAll() as $fila) {
            $asignaciones[(int) $fila['idSilo']] = (float) $fila['cantidad'];
        }
        $saldoFisico = (float) $stock['cantidad_disponible'] + (float) $stock['cantidad_reservada'];
        if (abs(array_sum($asignaciones) - $saldoFisico) > 0.0005) {
            throw new DomainException('Distribuya primero todo el saldo fisico del lote entre silos antes de ajustarlo.');
        }
        foreach ($normalizadas as $idSilo => $fila) {
            $cantidadActual = $asignaciones[$idSilo] ?? 0;
            if ($tipo === 'negativo' && (float) $fila['cantidad'] - $cantidadActual > 0.0005) {
                throw new DomainException('El ajuste supera la cantidad de este lote en uno de los silos seleccionados.');
            }
            $asignaciones[$idSilo] = $cantidadActual + ($tipo === 'positivo' ? 1 : -1) * (float) $fila['cantidad'];
        }
        $delta = $tipo === 'positivo' ? $monto : -$monto;
        $distribucion = [];
        foreach ($asignaciones as $silo => $cantidad) {
            if ($cantidad > 0.0005) {
                $distribucion[] = ['idSilo' => $silo, 'cantidad' => $cantidad];
            }
        }
        (new SiloStockService($this->db))->redistribuirSaldoEntrada($idEntrada, $idProducto, $saldoFisico + $delta, $distribucion);
        return array_values($normalizadas);
    }
}
