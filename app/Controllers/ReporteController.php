<?php

require_once __DIR__ . '/../Core/ReporteExcel.php';

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
        $formatoExport = (string) ($_GET['export'] ?? '');
        $modoExport = $formatoExport === 'pdf';
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

            if ($formatoExport === 'excel' && $idProducto && $encabezado) {
                (new ReporteExcel())->descargarMovimientos($movimientos, [
                    'Fecha de emisión' => date('d/m/Y H:i'),
                    'Producto' => $encabezado['producto'],
                    'Lote' => $encabezado['NumLote'],
                ]);
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

    public function saldos(): void
    {
        $this->requierePermiso('reporte_lote');

        $model = $this->model('ReporteInventario');
        $productos = [];
        $saldos = [];
        $resumen = [];
        $loadError = null;
        $idsProducto = $this->normalizarIdsProducto($_GET['idProducto'] ?? []);
        $paginaActual = max(1, filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1);
        $porPaginaSolicitado = filter_input(INPUT_GET, 'porPagina', FILTER_VALIDATE_INT) ?: 30;
        $porPaginaPermitidos = [20, 30, 50, 100];
        $porPagina = in_array($porPaginaSolicitado, $porPaginaPermitidos, true) ? $porPaginaSolicitado : 30;
        $totalPaginas = 1;
        $desdeRegistro = 0;
        $hastaRegistro = 0;

        try {
            $productos = $model->obtenerProductos();
            $idsDisponibles = array_map('intval', array_column($productos, 'idProducto'));
            $idsProducto = array_values(array_intersect($idsProducto, $idsDisponibles));

            if ((string) ($_GET['export'] ?? '') === 'excel') {
            $saldos = $model->obtenerSaldosPorLote($idsProducto);
                $nombresSeleccionados = array_values(array_map(
                    static fn (array $producto): string => (string) $producto['nombre'],
                    array_filter(
                        $productos,
                        static fn (array $producto): bool => in_array((int) $producto['idProducto'], $idsProducto, true)
                    )
                ));
                (new ReporteExcel())->descargarSaldos($saldos, [
                    'Fecha de emisión' => date('d/m/Y H:i'),
                    'Productos' => $nombresSeleccionados ? implode(', ', $nombresSeleccionados) : 'Todos los productos',
                    'Lotes' => count($saldos),
                ]);
            }

            $resumen = $model->obtenerResumenSaldosPorLote($idsProducto);
            $totalRegistros = (int) ($resumen['total_lotes'] ?? 0);
            $totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
            $paginaActual = min($paginaActual, $totalPaginas);
            $offset = ($paginaActual - 1) * $porPagina;
            $saldos = $model->obtenerSaldosPorLotePaginados($idsProducto, $porPagina, $offset);
            $desdeRegistro = $totalRegistros > 0 ? $offset + 1 : 0;
            $hastaRegistro = min($offset + $porPagina, $totalRegistros);
        } catch (Throwable $exception) {
            $loadError = $exception->getMessage();
            $totalRegistros = 0;
        }

        $this->view('reporte/saldos', [
            'title' => 'Saldo de Productos por Lote',
            'productos' => $productos,
            'idsProducto' => $idsProducto,
            'saldos' => $saldos,
            'resumen' => $resumen,
            'paginaActual' => $paginaActual,
            'totalPaginas' => $totalPaginas,
            'porPagina' => $porPagina,
            'porPaginaPermitidos' => $porPaginaPermitidos,
            'totalRegistros' => $totalRegistros,
            'desdeRegistro' => $desdeRegistro,
            'hastaRegistro' => $hastaRegistro,
            'fechaEmision' => date('d/m/Y H:i'),
            'loadError' => $loadError,
        ]);
    }

    private function normalizarIdsProducto(mixed $value): array
    {
        $values = is_array($value) ? $value : ($value === '' ? [] : [$value]);
        $ids = [];

        foreach ($values as $candidate) {
            $id = filter_var($candidate, FILTER_VALIDATE_INT);
            if ($id !== false && $id > 0) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
