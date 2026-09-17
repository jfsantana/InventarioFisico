<?php

class SiloStockService
{
    public function __construct(private PDO $db)
    {
    }

    public function asignarEntrada(int $idEntrada, int $idProducto, float $cantidadEsperada, array $asignaciones): void
    {
        $normalizadas = $this->normalizarAsignaciones($asignaciones);
        $total = array_sum(array_column($normalizadas, 'cantidad'));
        if ($normalizadas === [] || abs($total - $cantidadEsperada) > 0.0005) {
            throw new DomainException('La distribución entre silos debe sumar exactamente ' . number_format($cantidadEsperada, 3) . ' kg.');
        }

        $bloquear = $this->db->prepare(
            'SELECT idSilo, codigo, capacidad, activo
             FROM silos
             WHERE idSilo = :idSilo
             FOR UPDATE'
        );
        $consulta = $this->db->prepare(
            'SELECT idSilo, codigo, capacidadDisponible, cantidadOcupada,
                    idProductoActual, productosActivos, activo
             FROM v_estado_silos
             WHERE idSilo = :idSilo'
        );
        $insert = $this->db->prepare(
            'INSERT INTO silo_asignaciones (idSilo, idInventarioEntrante, cantidadAsignada)
             VALUES (:idSilo, :idInventarioEntrante, :cantidadAsignada)'
        );

        foreach ($normalizadas as $asignacion) {
            $bloquear->execute(['idSilo' => $asignacion['idSilo']]);
            if (!$bloquear->fetch()) {
                throw new DomainException('Uno de los silos seleccionados no existe.');
            }
            $consulta->execute(['idSilo' => $asignacion['idSilo']]);
            $silo = $consulta->fetch();
            if (!$silo || (int) $silo['activo'] !== 1) {
                throw new DomainException('Uno de los silos seleccionados no está disponible.');
            }
            if ((int) $silo['productosActivos'] > 1
                || ((float) $silo['cantidadOcupada'] > 0.0005 && (int) $silo['idProductoActual'] !== $idProducto)) {
                throw new DomainException('El silo ' . $silo['codigo'] . ' contiene otro producto.');
            }
            if ($asignacion['cantidad'] - (float) $silo['capacidadDisponible'] > 0.0005) {
                throw new DomainException('La cantidad supera el espacio disponible del silo ' . $silo['codigo'] . '.');
            }

            $insert->execute([
                'idSilo' => $asignacion['idSilo'],
                'idInventarioEntrante' => $idEntrada,
                'cantidadAsignada' => $asignacion['cantidad'],
            ]);
        }
    }

    public function descontarSalida(int $idEntrada, int $idSalida, float $cantidad): bool
    {
        $bloquear = $this->db->prepare(
            'SELECT idAsignacion
             FROM silo_asignaciones
             WHERE idInventarioEntrante = :idInventarioEntrante
             ORDER BY idAsignacion
             FOR UPDATE'
        );
        $bloquear->execute(['idInventarioEntrante' => $idEntrada]);
        if ($bloquear->fetchAll() === []) {
            return false;
        }

        $statement = $this->db->prepare(
            'SELECT a.idAsignacion,
                    a.cantidadAsignada - COALESCE(SUM(ss.cantidad), 0) AS disponible
             FROM silo_asignaciones a
             LEFT JOIN silo_salidas ss ON ss.idAsignacion = a.idAsignacion
             WHERE a.idInventarioEntrante = :idInventarioEntrante
             GROUP BY a.idAsignacion, a.cantidadAsignada, a.fechaAsignacion
             HAVING disponible > 0.0005
             ORDER BY a.fechaAsignacion ASC, a.idAsignacion ASC
             FOR UPDATE'
        );
        $statement->execute(['idInventarioEntrante' => $idEntrada]);
        $asignaciones = $statement->fetchAll();

        $disponible = array_sum(array_map(static fn (array $fila): float => (float) $fila['disponible'], $asignaciones));
        if ($cantidad - $disponible > 0.0005) {
            throw new DomainException('La cantidad supera el inventario disponible en los silos de este lote.');
        }

        $insert = $this->db->prepare(
            'INSERT INTO silo_salidas (idAsignacion, idInventarioSaliente, cantidad)
             VALUES (:idAsignacion, :idInventarioSaliente, :cantidad)'
        );
        $pendiente = $cantidad;
        foreach ($asignaciones as $asignacion) {
            if ($pendiente <= 0.0005) {
                break;
            }
            $consumo = min($pendiente, (float) $asignacion['disponible']);
            $insert->execute([
                'idAsignacion' => (int) $asignacion['idAsignacion'],
                'idInventarioSaliente' => $idSalida,
                'cantidad' => $consumo,
            ]);
            $pendiente -= $consumo;
        }

        return true;
    }

    public function redistribuirSaldoEntrada(int $idEntrada, int $idProducto, float $saldoFisico, array $asignaciones): void
    {
        $normalizadas = $saldoFisico > 0.0005 ? $this->normalizarAsignaciones($asignaciones) : [];
        $total = array_sum(array_column($normalizadas, 'cantidad'));
        if (abs($total - $saldoFisico) > 0.0005) {
            throw new DomainException('La distribución entre silos debe sumar exactamente el saldo físico: ' . number_format($saldoFisico, 3) . ' kg.');
        }

        $statement = $this->db->prepare(
            'SELECT a.idAsignacion, a.idSilo, a.cantidadAsignada,
                    COALESCE(SUM(ss.cantidad), 0) AS cantidadConsumida
             FROM silo_asignaciones a
             LEFT JOIN silo_salidas ss ON ss.idAsignacion = a.idAsignacion
             WHERE a.idInventarioEntrante = :idInventarioEntrante
             GROUP BY a.idAsignacion, a.idSilo, a.cantidadAsignada
             ORDER BY a.idSilo
             FOR UPDATE'
        );
        $statement->execute(['idInventarioEntrante' => $idEntrada]);
        $existentes = [];
        foreach ($statement->fetchAll() as $asignacion) {
            $existentes[(int) $asignacion['idSilo']] = $asignacion;
        }

        $deseadas = [];
        foreach ($normalizadas as $asignacion) {
            $deseadas[$asignacion['idSilo']] = $asignacion['cantidad'];
        }

        $idsSilo = array_values(array_unique(array_merge(array_keys($existentes), array_keys($deseadas))));
        sort($idsSilo);
        $bloquearSilo = $this->db->prepare('SELECT idSilo, codigo, capacidad, activo FROM silos WHERE idSilo = :idSilo FOR UPDATE');
        $estadoExterno = $this->db->prepare(
            'SELECT COALESCE(SUM(GREATEST(a.cantidadAsignada - COALESCE(x.cantidadSaliente, 0), 0)), 0) AS ocupado,
                    MAX(CASE WHEN a.cantidadAsignada - COALESCE(x.cantidadSaliente, 0) > 0.0005 THEN ie.idProducto END) AS idProducto,
                    COUNT(DISTINCT CASE WHEN a.cantidadAsignada - COALESCE(x.cantidadSaliente, 0) > 0.0005 THEN ie.idProducto END) AS productos
             FROM silo_asignaciones a
             INNER JOIN inventarioentrante ie ON ie.idInventarioEntrante = a.idInventarioEntrante
             LEFT JOIN (SELECT idAsignacion, SUM(cantidad) AS cantidadSaliente FROM silo_salidas GROUP BY idAsignacion) x
                    ON x.idAsignacion = a.idAsignacion
             WHERE a.idSilo = :idSilo
               AND a.idInventarioEntrante <> :idInventarioEntrante'
        );

        foreach ($idsSilo as $idSilo) {
            $bloquearSilo->execute(['idSilo' => $idSilo]);
            $silo = $bloquearSilo->fetch();
            if (!$silo) {
                throw new DomainException('Uno de los silos seleccionados no existe.');
            }

            $cantidadDeseada = (float) ($deseadas[$idSilo] ?? 0);
            if ($cantidadDeseada <= 0.0005) {
                continue;
            }
            if ((int) $silo['activo'] !== 1) {
                throw new DomainException('El silo ' . $silo['codigo'] . ' está inactivo.');
            }

            $estadoExterno->execute([
                'idSilo' => $idSilo,
                'idInventarioEntrante' => $idEntrada,
            ]);
            $externo = $estadoExterno->fetch();
            if ((int) $externo['productos'] > 1
                || ((float) $externo['ocupado'] > 0.0005 && (int) $externo['idProducto'] !== $idProducto)) {
                throw new DomainException('El silo ' . $silo['codigo'] . ' contiene otro producto.');
            }
            if ($cantidadDeseada + (float) $externo['ocupado'] - (float) $silo['capacidad'] > 0.0005) {
                throw new DomainException('La cantidad supera el espacio disponible del silo ' . $silo['codigo'] . '.');
            }
        }

        $update = $this->db->prepare('UPDATE silo_asignaciones SET cantidadAsignada = :cantidadAsignada WHERE idAsignacion = :idAsignacion');
        $delete = $this->db->prepare('DELETE FROM silo_asignaciones WHERE idAsignacion = :idAsignacion');
        $insert = $this->db->prepare(
            'INSERT INTO silo_asignaciones (idSilo, idInventarioEntrante, cantidadAsignada)
             VALUES (:idSilo, :idInventarioEntrante, :cantidadAsignada)'
        );

        foreach ($existentes as $idSilo => $existente) {
            $consumida = (float) $existente['cantidadConsumida'];
            $deseada = (float) ($deseadas[$idSilo] ?? 0);
            if ($consumida <= 0.0005 && $deseada <= 0.0005) {
                $delete->execute(['idAsignacion' => (int) $existente['idAsignacion']]);
                continue;
            }
            $update->execute([
                'cantidadAsignada' => $consumida + $deseada,
                'idAsignacion' => (int) $existente['idAsignacion'],
            ]);
        }

        foreach ($deseadas as $idSilo => $cantidadDeseada) {
            if (isset($existentes[$idSilo])) {
                continue;
            }
            $insert->execute([
                'idSilo' => $idSilo,
                'idInventarioEntrante' => $idEntrada,
                'cantidadAsignada' => $cantidadDeseada,
            ]);
        }
    }

    private function normalizarAsignaciones(array $asignaciones): array
    {
        $agrupadas = [];
        foreach ($asignaciones as $asignacion) {
            $idSilo = filter_var($asignacion['idSilo'] ?? null, FILTER_VALIDATE_INT);
            $cantidad = str_replace(',', '.', trim((string) ($asignacion['cantidad'] ?? '')));
            if (!$idSilo || !preg_match('/^\d+(?:\.\d{1,3})?$/', $cantidad) || (float) $cantidad <= 0) {
                throw new InvalidArgumentException('Seleccione silos y cantidades válidas, con máximo 3 decimales.');
            }
            $agrupadas[(int) $idSilo] = ($agrupadas[(int) $idSilo] ?? 0) + (float) $cantidad;
        }

        return array_map(
            static fn (int $idSilo, float $cantidad): array => ['idSilo' => $idSilo, 'cantidad' => $cantidad],
            array_keys($agrupadas),
            array_values($agrupadas)
        );
    }
}