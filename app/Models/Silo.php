<?php

class Silo extends BaseModel
{
    public function listar(array $filters = []): array
    {
        $sql = 'SELECT es.idSilo, es.codigo, es.nombre, es.capacidad, es.activo,
                       es.cantidadOcupada, es.capacidadDisponible, es.idProductoActual,
                       p.nombre AS productoActual
                FROM v_estado_silos es
                LEFT JOIN Producto p ON p.idProducto = es.idProductoActual';
        $params = [];
        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $sql .= ' WHERE es.codigo LIKE :codigo OR es.nombre LIKE :nombre OR p.nombre LIKE :producto';
            $like = '%' . $query . '%';
            $params = ['codigo' => $like, 'nombre' => $like, 'producto' => $like];
        }
        $sql .= ' ORDER BY es.codigo ASC';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function disponiblesParaProducto(int $idProducto): array
    {
        $statement = $this->db->prepare(
            'SELECT es.idSilo, es.codigo, es.nombre, es.capacidad,
                    es.cantidadOcupada, es.capacidadDisponible, es.idProductoActual,
                    p.nombre AS productoActual
             FROM v_estado_silos es
             LEFT JOIN Producto p ON p.idProducto = es.idProductoActual
             WHERE es.activo = 1
               AND es.capacidadDisponible > 0.0005
               AND (es.cantidadOcupada <= 0.0005 OR es.idProductoActual = :idProducto)
             ORDER BY (es.idProductoActual = :idProductoOrden) DESC, es.codigo ASC'
        );
        $statement->execute([
            'idProducto' => $idProducto,
            'idProductoOrden' => $idProducto,
        ]);

        return $statement->fetchAll();
    }

    public function crear(array $data): int
    {
        $data = $this->normalizar($data);
        $statement = $this->db->prepare(
            'INSERT INTO silos (codigo, nombre, capacidad, activo)
             VALUES (:codigo, :nombre, :capacidad, :activo)'
        );
        $statement->execute($data);

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $idSilo, array $data): bool
    {
        $data = $this->normalizar($data);
        $statement = $this->db->prepare(
            'SELECT cantidadOcupada FROM v_estado_silos WHERE idSilo = :idSilo'
        );
        $statement->execute(['idSilo' => $idSilo]);
        $ocupada = $statement->fetchColumn();
        if ($ocupada === false) {
            throw new InvalidArgumentException('El silo no existe.');
        }
        if ((float) $data['capacidad'] + 0.0005 < (float) $ocupada) {
            throw new DomainException('La capacidad no puede ser menor que la cantidad ocupada (' . number_format((float) $ocupada, 3) . ' kg).');
        }

        $data['idSilo'] = $idSilo;
        $statement = $this->db->prepare(
            'UPDATE silos
             SET codigo = :codigo, nombre = :nombre, capacidad = :capacidad, activo = :activo
             WHERE idSilo = :idSilo'
        );

        return $statement->execute($data);
    }

    public function eliminar(int $idSilo): bool
    {
        $statement = $this->db->prepare('SELECT COUNT(*) FROM silo_asignaciones WHERE idSilo = :idSilo');
        $statement->execute(['idSilo' => $idSilo]);
        if ((int) $statement->fetchColumn() > 0) {
            throw new DomainException('El silo tiene historial de inventario. Desactívelo en lugar de eliminarlo.');
        }

        $statement = $this->db->prepare('DELETE FROM silos WHERE idSilo = :idSilo');
        $statement->execute(['idSilo' => $idSilo]);

        return $statement->rowCount() === 1;
    }

    public function entradasPendientes(): array
    {
        $statement = $this->db->query(
            "SELECT ie.idInventarioEntrante, ie.idProducto, ie.NumLote, p.nombre AS producto,
                    ie.CantidadEntrante,
                    ie.CantidadEntrante - COALESCE(sa.cantidadSaliente, 0) AS saldoFisico,
                    COALESCE(si.cantidadEnSilos, 0) AS cantidadEnSilos,
                    ie.CantidadEntrante - COALESCE(sa.cantidadSaliente, 0) - COALESCE(si.cantidadEnSilos, 0) AS cantidadPendiente
             FROM inventarioentrante ie
             INNER JOIN Producto p ON p.idProducto = ie.idProducto
             LEFT JOIN (
                 SELECT idInventarioEntrante, SUM(cantidadSaliente) AS cantidadSaliente
                 FROM inventariosaliente GROUP BY idInventarioEntrante
             ) sa ON sa.idInventarioEntrante = ie.idInventarioEntrante
             LEFT JOIN (
                 SELECT a.idInventarioEntrante,
                        SUM(a.cantidadAsignada - COALESCE(x.cantidadSaliente, 0)) AS cantidadEnSilos
                 FROM silo_asignaciones a
                 LEFT JOIN (SELECT idAsignacion, SUM(cantidad) AS cantidadSaliente FROM silo_salidas GROUP BY idAsignacion) x
                        ON x.idAsignacion = a.idAsignacion
                 GROUP BY a.idInventarioEntrante
             ) si ON si.idInventarioEntrante = ie.idInventarioEntrante
             WHERE REPLACE(LOWER(ie.sector), ' ', '') = 'sector3'
             HAVING cantidadPendiente > 0.0005
             ORDER BY ie.fecha ASC, ie.idInventarioEntrante ASC"
        );

        return $statement->fetchAll();
    }

    public function asignarEntradaPendiente(int $idEntrada, array $asignaciones): void
    {
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                "SELECT ie.idProducto,
                        ie.CantidadEntrante - COALESCE(SUM(ins.cantidadSaliente), 0) AS saldoFisico
                 FROM inventarioentrante ie
                 LEFT JOIN inventariosaliente ins ON ins.idInventarioEntrante = ie.idInventarioEntrante
                 WHERE ie.idInventarioEntrante = :idInventarioEntrante
                   AND REPLACE(LOWER(ie.sector), ' ', '') = 'sector3'
                 GROUP BY ie.idInventarioEntrante, ie.idProducto, ie.CantidadEntrante
                 FOR UPDATE"
            );
            $statement->execute(['idInventarioEntrante' => $idEntrada]);
            $entrada = $statement->fetch();
            if (!$entrada) {
                throw new InvalidArgumentException('La entrada de Sector3 no existe.');
            }

            $statement = $this->db->prepare(
                'SELECT COALESCE(SUM(a.cantidadAsignada - COALESCE(x.cantidadSaliente, 0)), 0)
                 FROM silo_asignaciones a
                 LEFT JOIN (SELECT idAsignacion, SUM(cantidad) AS cantidadSaliente FROM silo_salidas GROUP BY idAsignacion) x
                        ON x.idAsignacion = a.idAsignacion
                 WHERE a.idInventarioEntrante = :idInventarioEntrante'
            );
            $statement->execute(['idInventarioEntrante' => $idEntrada]);
            $pendiente = (float) $entrada['saldoFisico'] - (float) $statement->fetchColumn();
            if ($pendiente <= 0.0005) {
                throw new DomainException('Esta entrada ya está completamente distribuida.');
            }

            (new SiloStockService($this->db))->asignarEntrada(
                $idEntrada,
                (int) $entrada['idProducto'],
                $pendiente,
                $asignaciones
            );
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function normalizar(array $data): array
    {
        $codigo = strtoupper(trim((string) ($data['codigo'] ?? '')));
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $capacidad = str_replace(',', '.', trim((string) ($data['capacidad'] ?? '')));
        if (!preg_match('/^[A-Z0-9_-]{1,30}$/', $codigo)) {
            throw new InvalidArgumentException('El código admite hasta 30 letras, números, guiones y guion bajo.');
        }
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            throw new InvalidArgumentException('Escriba un nombre de hasta 100 caracteres.');
        }
        if (!preg_match('/^\d+(?:\.\d{1,3})?$/', $capacidad) || (float) $capacidad <= 0) {
            throw new InvalidArgumentException('La capacidad debe ser mayor que cero y tener máximo 3 decimales.');
        }

        return [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'capacidad' => (float) $capacidad,
            'activo' => !empty($data['activo']) ? 1 : 0,
        ];
    }
}