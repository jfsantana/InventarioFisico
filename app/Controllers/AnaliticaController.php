<?php

class AnaliticaController extends Controller
{
    public function index(): void
    {
        $model = $this->model('AnaliticaInventario');
        $productos = [];
        $movimientos = [];
        $lotes = [];
        $indicadores = null;
        $resumenLotes = [];
        $loadError = null;

        $idProducto = filter_input(INPUT_GET, 'idProducto', FILTER_VALIDATE_INT) ?: null;
        $hasta = $this->normalizarFecha($_GET['hasta'] ?? date('d/m/Y'));
        $desde = $this->normalizarFecha($_GET['desde'] ?? date('d/m/Y', strtotime('-30 days')));

        if (!$this->esFechaValida($desde)) {
            $desde = date('Y-m-d', strtotime('-30 days'));
        }

        if (!$this->esFechaValida($hasta)) {
            $hasta = date('Y-m-d');
        }

        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        try {
            $productos = $model->obtenerProductos();
            $movimientos = $model->obtenerMovimientos($idProducto, $desde, $hasta);
            $lotes = $model->obtenerResumenLotes($idProducto);
            $resumenLotes = $model->construirResumenPorLote($movimientos, $lotes, $desde, $hasta);

            if ($idProducto) {
                $indicadores = $model->construirIndicadores($movimientos, $lotes, $desde, $hasta);
            }
        } catch (PDOException $exception) {
            $loadError = $exception->getMessage();
        }

        $this->view('analitica/index', [
            'title' => 'Inteligencia de inventario',
            'productos' => $productos,
            'idProducto' => $idProducto,
            'desde' => $desde,
            'hasta' => $hasta,
            'desdeDisplay' => $this->formatearFecha($desde),
            'hastaDisplay' => $this->formatearFecha($hasta),
            'movimientos' => $movimientos,
            'lotes' => $lotes,
            'indicadores' => $indicadores,
            'resumenLotes' => $resumenLotes,
            'loadError' => $loadError,
        ]);
    }

    private function esFechaValida(string $fecha): bool
    {
        $date = DateTime::createFromFormat('Y-m-d', $fecha);

        return $date && $date->format('Y-m-d') === $fecha;
    }

    private function normalizarFecha(string $fecha): string
    {
        $fecha = trim($fecha);
        $formatos = ['d/m/Y', 'Y-m-d'];

        foreach ($formatos as $formato) {
            $date = DateTime::createFromFormat($formato, $fecha);

            if ($date && $date->format($formato) === $fecha) {
                return $date->format('Y-m-d');
            }
        }

        return date('Y-m-d');
    }

    private function formatearFecha(string $fecha): string
    {
        $date = DateTime::createFromFormat('Y-m-d', $fecha);

        return $date ? $date->format('d/m/Y') : date('d/m/Y');
    }
}
