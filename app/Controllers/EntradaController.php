<?php

class EntradaController extends Controller
{
    public function index(array $formData = [], array $errors = [], ?string $successMessage = null): void
    {
        $model = $this->model('EntradaInventario');
        $productos = [];
        $presentaciones = [];
        $ubicaciones = [];
        $loadError = null;

        try {
            $productos = $model->obtenerProductos();
            $presentaciones = $model->obtenerPresentaciones();
            $ubicaciones = $model->obtenerUbicaciones();
        } catch (PDOException $exception) {
            $loadError = $exception->getMessage();
        }

        $this->view('entrada/index', [
            'title' => 'Registrar entrada',
            'productos' => $productos,
            'presentaciones' => $presentaciones,
            'ubicaciones' => $ubicaciones,
            'formData' => $formData,
            'errors' => $errors,
            'successMessage' => $successMessage,
            'loadError' => $loadError,
        ]);
    }

    public function guardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/entrada');
            return;
        }

        $formData = [
            'NumLote' => trim($_POST['NumLote'] ?? ''),
            'idProducto' => $_POST['idProducto'] ?? '',
            'idPresentacion' => $_POST['idPresentacion'] ?? '',
            'idUbicacion' => $_POST['idUbicacion'] ?? '',
            'CantidadEntrante' => trim($_POST['CantidadEntrante'] ?? ''),
        ];

        $errors = $this->validarFormulario($formData);

        if (!empty($errors)) {
            $this->index($formData, $errors);
            return;
        }

        try {
            $model = $this->model('EntradaInventario');
            $model->registrarEntrada([
                'NumLote' => $formData['NumLote'],
                'idProducto' => (int) $formData['idProducto'],
                'idPresentacion' => (int) $formData['idPresentacion'],
                'idUbicacion' => (int) $formData['idUbicacion'],
                'CantidadEntrante' => (int) $formData['CantidadEntrante'],
            ]);

            $this->index([], [], 'La entrada de inventario fue registrada correctamente.');
        } catch (PDOException $exception) {
            $this->index($formData, ['general' => $exception->getMessage()]);
        }
    }

    private function validarFormulario(array $formData): array
    {
        $errors = [];

        if ($formData['NumLote'] === '') {
            $errors['NumLote'] = 'Escriba el numero de lote.';
        }

        if (filter_var($formData['idProducto'], FILTER_VALIDATE_INT) === false) {
            $errors['idProducto'] = 'Seleccione un producto.';
        }

        if (filter_var($formData['idPresentacion'], FILTER_VALIDATE_INT) === false) {
            $errors['idPresentacion'] = 'Seleccione una presentacion.';
        }

        if (filter_var($formData['idUbicacion'], FILTER_VALIDATE_INT) === false) {
            $errors['idUbicacion'] = 'Seleccione una ubicacion.';
        }

        if (!ctype_digit($formData['CantidadEntrante']) || (int) $formData['CantidadEntrante'] <= 0) {
            $errors['CantidadEntrante'] = 'Escriba una cantidad mayor que cero.';
        }

        return $errors;
    }
}
