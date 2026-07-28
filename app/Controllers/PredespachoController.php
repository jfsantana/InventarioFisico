<?php

class PredespachoController extends Controller
{
    public function index(): void
    {
        $this->requiereLogin();

        $this->view('predespacho/lista', [
            'title' => 'Gestion de Predespachos',
        ]);
    }

    public function detalle(): void
    {
        $this->requiereLogin();

        $idCabeceraPredespacho = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

        $this->view('predespacho/detalle', [
            'title' => 'Detalle de Predespacho',
            'idCabeceraPredespacho' => $idCabeceraPredespacho,
        ]);
    }

    public function salida(): void
    {
        $this->requiereLogin();

        $model = $this->model('Predespacho');
        $sectorSeleccionado = trim($_GET['sector'] ?? '');
        $codigoPredespachoSeleccionado = trim($_GET['predespacho'] ?? '');
        $sectores = [];
        $predespachos = [];
        $predespachoSeleccionado = null;
        $items = [];
        $loadError = null;

        try {
            $sectores = $model->obtenerSectoresPendientesPredespacho();

            if ($sectorSeleccionado !== '') {
                $predespachos = $model->obtenerPredespachosPorSector($sectorSeleccionado);
            }

            if ($sectorSeleccionado !== '' && $codigoPredespachoSeleccionado !== '') {
                $predespachoSeleccionado = $model->obtenerPredespachoPorCodigo($codigoPredespachoSeleccionado);

                if ($predespachoSeleccionado) {
                    $items = $model->obtenerItemsPorPredespachoYSector(
                        (int) $predespachoSeleccionado['idCabeceraPredespacho'],
                        $sectorSeleccionado
                    );
                }
            }
        } catch (PDOException $exception) {
            $loadError = $exception->getMessage();
        }

        $this->view('predespacho/salida_sector', [
            'title' => 'Salida por Sector',
            'sectores' => $sectores,
            'sectorSeleccionado' => $sectorSeleccionado,
            'predespachos' => $predespachos,
            'codigoPredespachoSeleccionado' => $codigoPredespachoSeleccionado,
            'predespachoSeleccionado' => $predespachoSeleccionado,
            'items' => $items,
            'loadError' => $loadError,
        ]);
    }

    public function salidaSector(): void
    {
        $this->salida();
    }
}