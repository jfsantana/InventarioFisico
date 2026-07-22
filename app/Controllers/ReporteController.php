<?php

class ReporteController extends Controller
{
    public function index(): void
    {
        $model = $this->model('ReporteInventario');
        $productos = [];
        $lotes = [];
        $encabezado = null;
        $movimientos = [];
        $loadError = null;

        $idProducto = filter_input(INPUT_GET, 'idProducto', FILTER_VALIDATE_INT);
        $idInventarioEntrante = filter_input(INPUT_GET, 'idInventarioEntrante', FILTER_VALIDATE_INT);

        try {
            $productos = $model->obtenerProductos();

            if ($idProducto) {
                $lotes = $model->obtenerLotesPorProducto($idProducto);
            }

            if ($idProducto && $idInventarioEntrante) {
                $encabezado = $model->obtenerEncabezadoLote($idInventarioEntrante, $idProducto);

                if ($encabezado) {
                    $salidas = $model->obtenerSalidasPorLote($idInventarioEntrante);
                    $movimientos = $model->construirMovimientos($encabezado, $salidas);
                }
            }
        } catch (PDOException $exception) {
            $loadError = $exception->getMessage();
        }

        $this->view('reporte/index', [
            'title' => 'Reporte de movimientos',
            'productos' => $productos,
            'lotes' => $lotes,
            'idProducto' => $idProducto,
            'idInventarioEntrante' => $idInventarioEntrante,
            'encabezado' => $encabezado,
            'movimientos' => $movimientos,
            'loadError' => $loadError,
        ]);
    }
}
