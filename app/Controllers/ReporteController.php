<?php

class ReporteController extends Controller
{
    public function index(): void
    {
        $this->requierePermiso('reporte_lote');

        $model = $this->model('ReporteInventario');
        $productos = [];
        $lotes = [];
        $encabezado = null;
        $movimientos = [];
        $movimientosPaginados = [];
        $loadError = null;

        $idProducto = filter_input(INPUT_GET, 'idProducto', FILTER_VALIDATE_INT);
        $idInventarioEntrante = filter_input(INPUT_GET, 'idInventarioEntrante', FILTER_VALIDATE_INT);
        $modoExport = (string) ($_GET['export'] ?? '') === 'pdf';
        $paginaActual = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
        $porPaginaSolicitado = filter_input(INPUT_GET, 'porPagina', FILTER_VALIDATE_INT) ?: 30;
        $porPaginaPermitidos = [20, 30, 50, 100];
        $porPagina = in_array($porPaginaSolicitado, $porPaginaPermitidos, true) ? $porPaginaSolicitado : 30;

        if ($paginaActual < 1) {
            $paginaActual = 1;
        }

        try {
            $productos = $model->obtenerProductos();
            $productoSeleccionado = null;
            $loteSeleccionado = null;

            if ($idProducto) {
                $lotes = $model->obtenerLotesPorProducto($idProducto);

                foreach ($productos as $producto) {
                    if ((int) $producto['idProducto'] === (int) $idProducto) {
                        $productoSeleccionado = $producto;
                        break;
                    }
                }

                foreach ($lotes as $lote) {
                    if ((int) $lote['idInventarioEntrante'] === (int) $idInventarioEntrante) {
                        $loteSeleccionado = $lote;
                        break;
                    }
                }

                if ($idInventarioEntrante && $loteSeleccionado === null) {
                    $idInventarioEntrante = null;
                }
            }

            if ($idProducto) {
                $movimientos = $model->obtenerMovimientosPorProducto($idProducto);

                if ($idInventarioEntrante) {
                    $movimientos = array_values(array_filter(
                        $movimientos,
                        static fn (array $movimiento): bool => (int) ($movimiento['idInventarioEntrante'] ?? 0) === (int) $idInventarioEntrante
                    ));
                }

                $encabezado = [
                    'producto' => $productoSeleccionado['nombre'] ?? '',
                    'NumLote' => $loteSeleccionado['NumLote'] ?? 'Todos los lotes',
                    'presentacion' => $idInventarioEntrante ? (string) ($loteSeleccionado['presentacion'] ?? '') : '',
                    'ubicacion' => '',
                ];
            }

            $totalRegistros = count($movimientos);
            if ($modoExport) {
                $paginaActual = 1;
                $totalPaginas = 1;
                $movimientosPaginados = $movimientos;
                $desdeRegistro = $totalRegistros > 0 ? 1 : 0;
                $hastaRegistro = $totalRegistros;
            } else {
                $totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
                if ($paginaActual > $totalPaginas) {
                    $paginaActual = $totalPaginas;
                }

                $offset = ($paginaActual - 1) * $porPagina;
                $movimientosPaginados = array_slice($movimientos, $offset, $porPagina);

                $desdeRegistro = $totalRegistros > 0 ? $offset + 1 : 0;
                $hastaRegistro = min($offset + $porPagina, $totalRegistros);
            }
        } catch (PDOException $exception) {
            $loadError = $exception->getMessage();
            $totalRegistros = 0;
            $totalPaginas = 1;
            $desdeRegistro = 0;
            $hastaRegistro = 0;
        }

        $this->view('reporte/index', [
            'title' => 'Reporte de movimientos',
            'productos' => $productos,
            'lotes' => $lotes,
            'idProducto' => $idProducto,
            'idInventarioEntrante' => $idInventarioEntrante,
            'modoExport' => $modoExport,
            'fechaEmision' => date('d/m/Y H:i'),
            'encabezado' => $encabezado,
            'movimientos' => $movimientos,
            'movimientosPaginados' => $movimientosPaginados,
            'paginaActual' => $paginaActual,
            'totalPaginas' => $totalPaginas,
            'porPagina' => $porPagina,
            'porPaginaPermitidos' => $porPaginaPermitidos,
            'totalRegistros' => $totalRegistros,
            'desdeRegistro' => $desdeRegistro,
            'hastaRegistro' => $hastaRegistro,
            'loadError' => $loadError,
        ]);
    }
}
