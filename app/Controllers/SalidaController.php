<?php

class SalidaController extends Controller
{
    public function index(array $formData = [], array $errors = [], ?string $successMessage = null): void
    {
        $model = $this->model('SalidaInventario');
        $productos = [];
        $lotes = [];
        $loadError = null;

        try {
            $productos = $model->obtenerProductos();

            if (!empty($formData['idProducto'])) {
                $lotes = $model->obtenerLotesPorProducto((int) $formData['idProducto']);
            }
        } catch (PDOException $exception) {
            $loadError = $exception->getMessage();
        }

        $this->view('salida/index', [
            'title' => 'Registrar salida',
            'productos' => $productos,
            'lotes' => $lotes,
            'formData' => $formData,
            'errors' => $errors,
            'successMessage' => $successMessage,
            'loadError' => $loadError,
        ]);
    }

    public function guardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/salida');
            return;
        }

        $this->validarCsrf();

        $formData = [
            'sector' => trim($_POST['sector'] ?? ''),
            'idProducto' => $_POST['idProducto'] ?? '',
            'idInventarioEntrante' => $_POST['idInventarioEntrante'] ?? '',
            'NE' => trim($_POST['NE'] ?? ''),
            'cantidadSaliente' => trim($_POST['cantidadSaliente'] ?? ''),
        ];

        $errors = $this->validarFormulario($formData);

        if (!empty($errors)) {
            $this->index($formData, $errors);
            return;
        }

        $model = $this->model('SalidaInventario');
        $idProducto = (int) $formData['idProducto'];
        $idInventarioEntrante = (int) $formData['idInventarioEntrante'];

        try {
            $lote = $model->obtenerLoteParaProducto($idInventarioEntrante, $idProducto);

            if (!$lote) {
                $this->index($formData, ['idInventarioEntrante' => 'Seleccione un lote valido para el producto elegido.']);
                return;
            }

            $cantidadSaliente = (float) $formData['cantidadSaliente'];
            $disponible = (float) $lote['Disponible'];

            if ($cantidadSaliente > $disponible) {
                $this->index($formData, [
                    'cantidadSaliente' => 'La cantidad no puede exceder el disponible del lote: ' . number_format($disponible, 2, '.', ''),
                ]);
                return;
            }

            $model->registrarSalida(
                $idInventarioEntrante,
                $formData['sector'],
                $formData['NE'],
                $cantidadSaliente
            );

            $this->index([], [], 'La salida fue registrada correctamente.');
        } catch (PDOException $exception) {
            $this->index($formData, ['general' => $exception->getMessage()]);
        }
    }

    public function lotes(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $idProducto = filter_input(INPUT_GET, 'idProducto', FILTER_VALIDATE_INT);

        if (!$idProducto) {
            echo json_encode([]);
            return;
        }

        try {
            $model = $this->model('SalidaInventario');
            echo json_encode($model->obtenerLotesPorProducto($idProducto));
        } catch (PDOException $exception) {
            http_response_code(500);
            echo json_encode(['error' => $exception->getMessage()]);
        }
    }

    public function detalle(?string $message = null, ?string $messageType = 'success'): void
    {
        Auth::requireRecentLogin();
        $this->requierePermiso('corregir_salidas');

        $model = $this->model('SalidaInventario');
        $salidas = [];
        $lotes = [];
        $loadError = null;

        try {
            $salidas = $model->obtenerSalidas();
            $lotes = $model->obtenerLotesParaCorreccion();
        } catch (PDOException $exception) {
            $loadError = $exception->getMessage();
        }

        $this->view('salida/detalle', [
            'title' => 'Corregir salidas',
            'salidas' => $salidas,
            'lotes' => $lotes,
            'message' => $message,
            'messageType' => $messageType,
            'loadError' => $loadError,
        ]);
    }

    public function actualizar(): void
    {
        Auth::requireRecentLogin();
        $this->requierePermiso('corregir_salidas', 'editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/salida/detalle');
            return;
        }

        $this->validarCsrf();

        $idInventarioSaliente = filter_input(INPUT_POST, 'idInventarioSaliente', FILTER_VALIDATE_INT);
        $formData = [
            'idInventarioEntrante' => $_POST['idInventarioEntrante'] ?? '',
            'NE' => trim($_POST['NE'] ?? ''),
            'cantidadSaliente' => trim($_POST['cantidadSaliente'] ?? ''),
        ];

        if (!$idInventarioSaliente) {
            $this->detalle('No se pudo identificar la salida a corregir.', 'error');
            return;
        }

        $errors = [];

        if (filter_var($formData['idInventarioEntrante'], FILTER_VALIDATE_INT) === false) {
            $errors[] = 'Seleccione un lote valido.';
        }

        if ($formData['NE'] === '') {
            $errors[] = 'Escriba la Nota de Entrega.';
        }

        if (!is_numeric($formData['cantidadSaliente']) || (float) $formData['cantidadSaliente'] <= 0) {
            $errors[] = 'Escriba una cantidad mayor que cero.';
        }

        if (!empty($errors)) {
            $this->detalle(implode(' ', $errors), 'error');
            return;
        }

        try {
            $model = $this->model('SalidaInventario');
            $salida = $model->obtenerSalidaPorId($idInventarioSaliente);

            if (!$salida) {
                $this->detalle('La salida seleccionada no existe.', 'error');
                return;
            }

            $idInventarioEntrante = (int) $formData['idInventarioEntrante'];
            $cantidadSaliente = (float) $formData['cantidadSaliente'];
            $disponible = $model->obtenerDisponibleParaCorreccion($idInventarioEntrante, $idInventarioSaliente);

            if ($cantidadSaliente > $disponible) {
                $this->detalle('La cantidad no puede exceder el disponible del lote: ' . number_format($disponible, 2), 'error');
                return;
            }

            $model->actualizarSalida(
                $idInventarioSaliente,
                $idInventarioEntrante,
                $formData['NE'],
                $cantidadSaliente
            );

            $this->detalle('La salida fue corregida correctamente.');
        } catch (PDOException $exception) {
            $this->detalle($exception->getMessage(), 'error');
        }
    }

    public function eliminar(): void
    {
        Auth::requireRecentLogin();
        $this->requierePermiso('corregir_salidas', 'borrar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/salida/detalle');
            return;
        }

        $this->validarCsrf();

        $idInventarioSaliente = filter_input(INPUT_POST, 'idInventarioSaliente', FILTER_VALIDATE_INT);

        if (!$idInventarioSaliente) {
            $this->detalle('No se pudo identificar la salida a eliminar.', 'error');
            return;
        }

        try {
            $model = $this->model('SalidaInventario');
            $salida = $model->obtenerSalidaPorId($idInventarioSaliente);

            if (!$salida) {
                $this->detalle('La salida seleccionada no existe.', 'error');
                return;
            }

            if (!$model->eliminarSalida($idInventarioSaliente)) {
                $this->detalle('La salida seleccionada no existe.', 'error');
                return;
            }

            $this->detalle('La salida fue eliminada correctamente.');
        } catch (PDOException $exception) {
            $this->detalle($exception->getMessage(), 'error');
        }
    }

    private function validarFormulario(array $formData): array
    {
        $errors = [];
        $sectoresPermitidos = ['Sector1', 'Sector 2', 'Sector 3'];

        if (!in_array($formData['sector'] ?? '', $sectoresPermitidos, true)) {
            $errors['sector'] = 'Seleccione un sector valido.';
        }

        if (filter_var($formData['idProducto'], FILTER_VALIDATE_INT) === false) {
            $errors['idProducto'] = 'Seleccione un producto.';
        }

        if (filter_var($formData['idInventarioEntrante'], FILTER_VALIDATE_INT) === false) {
            $errors['idInventarioEntrante'] = 'Seleccione un lote.';
        }

        if ($formData['NE'] === '') {
            $errors['NE'] = 'Escriba la Nota de Entrega.';
        }

        if (!is_numeric($formData['cantidadSaliente']) || (float) $formData['cantidadSaliente'] <= 0) {
            $errors['cantidadSaliente'] = 'Escriba una cantidad mayor que cero.';
        }

        return $errors;
    }
}
