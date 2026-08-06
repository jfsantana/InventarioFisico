<?php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../app/Core/Database.php';
require_once __DIR__ . '/../../../app/Models/BaseModel.php';

class IaModel extends BaseModel
{
    public function generarContexto(): string
    {
        $kpis = $this->consultarVista('SELECT * FROM vw_kpis_ejecutivos', 'vw_kpis_ejecutivos');
        $stock = $this->consultarVista(
            'SELECT * FROM vw_stock_por_producto ORDER BY stock_disponible ASC',
            'vw_stock_por_producto'
        );
        $movimientos = $this->consultarVista(
            'SELECT * FROM vw_movimientos_ultimos_90_dias',
            'vw_movimientos_ultimos_90_dias'
        );
        $lotes = $this->consultarVista(
            'SELECT * FROM vw_lotes_sin_rotacion ORDER BY dias_en_almacen DESC',
            'vw_lotes_sin_rotacion'
        );
        $predespachos = $this->consultarVista(
            'SELECT * FROM vw_predespachos_y_cumplimiento',
            'vw_predespachos_y_cumplimiento'
        );
        $alertas = $this->consultarVista(
            'SELECT * FROM vw_alertas_inventario ORDER BY nivel, tipo_alerta',
            'vw_alertas_inventario'
        );

        $fechaReporte = $this->valor($kpis[0] ?? [], ['fecha_reporte'], date('Y-m-d H:i:s'));
        $contexto = '=== KPIs GENERALES - ' . $fechaReporte . " ===\n";
        $contexto .= $this->formatearKpis($kpis[0] ?? []);
        $contexto .= "\n=== STOCK POR PRODUCTO ===\n";
        $contexto .= $this->formatearStock($stock);
        $contexto .= "\n=== MOVIMIENTOS ÚLTIMOS 90 DÍAS ===\n";
        $contexto .= $this->formatearMovimientos($movimientos);
        $contexto .= "\n=== LOTES SIN ROTACIÓN ===\n";
        $contexto .= $this->formatearLotes($lotes);
        $contexto .= "\n=== PREDESPACHOS ACTIVOS ===\n";
        $contexto .= $this->formatearPredespachos($predespachos);
        $contexto .= "\n=== ALERTAS ACTIVAS ===\n";
        $contexto .= $this->formatearAlertas($alertas);

        return $contexto;
    }

    private function consultarVista(string $sql, string $nombreVista): array
    {
        try {
            return $this->db->query($sql)->fetchAll();
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'No se pudo consultar la vista ' . $nombreVista . ': ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    private function formatearKpis(array $kpis): string
    {
        if ($kpis === []) {
            return "- No hay datos de KPIs disponibles.\n";
        }

        $etiquetas = [
            'total_productos_activos' => 'Productos activos',
            'productos_con_stock' => 'Productos con stock',
            'productos_agotados' => 'Productos agotados',
            'productos_estado_critico' => 'Productos en estado crítico',
            'total_unidades_disponibles' => 'Unidades disponibles',
            'total_unidades_reservadas' => 'Unidades reservadas',
            'total_lotes_en_sistema' => 'Lotes en el sistema',
            'lotes_sin_movimiento_90dias' => 'Lotes sin movimiento en 90 días',
            'entradas_ultimo_mes' => 'Entradas del último mes',
            'salidas_ultimo_mes' => 'Salidas del último mes',
            'predespachos_abiertos' => 'Predespachos abiertos',
            'predespachos_vencidos' => 'Predespachos vencidos',
            'predespachos_para_hoy' => 'Predespachos para hoy',
            'clientes_activos' => 'Clientes activos',
            'total_alertas_criticas' => 'Alertas críticas',
            'total_alertas_altas' => 'Alertas altas',
        ];
        $lineas = [];
        foreach ($kpis as $campo => $valor) {
            if ($campo === 'fecha_reporte') {
                continue;
            }

            $etiqueta = $etiquetas[$campo] ?? ucfirst(str_replace('_', ' ', (string) $campo));
            $lineas[] = '- ' . $etiqueta . ': ' . $this->textoPlano($valor);
        }

        return implode("\n", $lineas) . "\n";
    }

    private function formatearStock(array $registros): string
    {
        if ($registros === []) {
            return "- No hay productos en el reporte de stock.\n";
        }

        $lineas = [];
        foreach ($registros as $registro) {
            $lineas[] = sprintf(
                '- %s | Presentación: %s | Disponible: %s | Reservado: %s | Estado: %s',
                $this->valor($registro, ['nombre_producto', 'producto', 'nombre']),
                $this->valor($registro, ['presentacion', 'nombre_presentacion']),
                $this->valor($registro, ['stock_disponible', 'cantidad_disponible', 'disponible']),
                $this->valor($registro, ['stock_reservado', 'cantidad_reservada', 'total_reservado', 'reservado']),
                $this->valor($registro, ['estado', 'estado_stock'])
            );
        }

        return implode("\n", $lineas) . "\n";
    }

    private function formatearMovimientos(array $registros): string
    {
        if ($registros === []) {
            return "- No hay movimientos registrados en los últimos 90 días.\n";
        }

        $lineas = [];
        foreach ($registros as $registro) {
            $lineas[] = sprintf(
                '- %s | Mes: %s | Entradas: %s | Salidas: %s | Balance: %s',
                $this->valor($registro, ['nombre_producto', 'producto', 'nombre']),
                $this->valor($registro, ['anio_mes']),
                $this->valor($registro, ['entradas', 'total_entradas', 'total_entradas_unidades']),
                $this->valor($registro, ['salidas', 'total_salidas', 'total_salidas_unidades']),
                $this->valor($registro, ['balance', 'balance_mes'])
            );
        }

        return implode("\n", $lineas) . "\n";
    }

    private function formatearLotes(array $registros): string
    {
        if ($registros === []) {
            return "- No hay lotes sin rotación.\n";
        }

        $lineas = [];
        foreach ($registros as $registro) {
            $lineas[] = sprintf(
                '- Lote: %s | Producto: %s | Días en almacén: %s | Disponible: %s | Clasificación: %s',
                $this->valor($registro, ['NumLote', 'num_lote', 'lote']),
                $this->valor($registro, ['nombre_producto', 'producto', 'nombre']),
                $this->valor($registro, ['dias_en_almacen']),
                $this->valor($registro, ['stock_disponible', 'cantidad_disponible', 'disponible']),
                $this->valor($registro, ['clasificacion', 'clasificacion_antiguedad'])
            );
        }

        return implode("\n", $lineas) . "\n";
    }

    private function formatearPredespachos(array $registros): string
    {
        if ($registros === []) {
            return "- No hay predespachos activos.\n";
        }

        $lineas = [];
        foreach ($registros as $registro) {
            $lineas[] = sprintf(
                '- Código: %s | Cliente: %s | Retiro: %s | Estado retiro: %s | Cumplimiento: %s%%',
                $this->valor($registro, ['codigoInterno', 'codigo_interno']),
                $this->valor($registro, ['nombre_cliente', 'cliente']),
                $this->valor($registro, ['fechaRetiro', 'fecha_retiro']),
                $this->valor($registro, ['estado_retiro']),
                $this->valor($registro, ['porcentaje_cumplimiento'])
            );
        }

        return implode("\n", $lineas) . "\n";
    }

    private function formatearAlertas(array $registros): string
    {
        if ($registros === []) {
            return "- No hay alertas activas.\n";
        }

        $lineas = [];
        foreach ($registros as $registro) {
            $lineas[] = sprintf(
                '- [%s] %s: %s',
                $this->valor($registro, ['nivel']),
                $this->valor($registro, ['tipo_alerta']),
                $this->valor($registro, ['detalle'])
            );
        }

        return implode("\n", $lineas) . "\n";
    }

    private function valor(array $registro, array $campos, string $predeterminado = 'Sin dato'): string
    {
        foreach ($campos as $campo) {
            if (array_key_exists($campo, $registro) && $registro[$campo] !== null && $registro[$campo] !== '') {
                return $this->textoPlano($registro[$campo]);
            }
        }

        return $predeterminado;
    }

    private function textoPlano(mixed $valor): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $valor));
    }
}
