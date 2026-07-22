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

        $formData = [
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

    private function validarFormulario(array $formData): array
    {
        $errors = [];

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
