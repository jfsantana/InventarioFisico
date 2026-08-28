<?php

class EntradaController extends Controller
{
    private const SECTORES = ['Sector1', 'Sector2', 'Sector3'];

    public function index(array $formData = [], array $errors = [], ?string $successMessage = null): void
    {
        $this->requierePermiso('entrada');

        $model = $this->model('EntradaInventario');
        $productos = [];
        $presentaciones = [];
        $ubicaciones = [];
        $tiposCompra = [];
        $proveedores = [];
        $paises = [];
        $loadError = null;

        try {
            $productos = $model->obtenerProductos();
            $presentaciones = $model->obtenerPresentaciones();
            $ubicaciones = $model->obtenerUbicaciones();
            $tiposCompra = $model->obtenerTiposCompra();
            $proveedores = $model->obtenerProveedores();
            $paises = $model->obtenerPaises();
        } catch (PDOException $exception) {
            $loadError = $exception->getMessage();
        }

        $this->view('entrada/index', [
            'title' => 'Registrar entrada',
            'productos' => $productos,
            'presentaciones' => $presentaciones,
            'ubicaciones' => $ubicaciones,
            'tiposCompra' => $tiposCompra,
            'proveedores' => $proveedores,
            'paises' => $paises,
            'sectores' => self::SECTORES,
            'formData' => $formData,
            'errors' => $errors,
            'successMessage' => $successMessage,
            'loadError' => $loadError,
        ]);
    }

    public function guardar(): void
    {
        $this->requierePermiso('entrada', 'editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/entrada');
            return;
        }

        $this->validarCsrf();

        $formData = [
            'NumLote' => trim($_POST['NumLote'] ?? ''),
            'idProducto' => $_POST['idProducto'] ?? '',
            'idPresentacion' => $_POST['idPresentacion'] ?? '',
            'idUbicacion' => $_POST['idUbicacion'] ?? '',
            'Sector' => trim($_POST['Sector'] ?? ''),
            'CantidadEntrante' => trim($_POST['CantidadEntrante'] ?? ''),
            'idTipoCompra' => $_POST['idTipoCompra'] ?? '',
            'CardCode' => trim($_POST['CardCode'] ?? ''),
            'FabricanteCode' => trim($_POST['FabricanteCode'] ?? ''),
            'PaisCode' => trim($_POST['PaisCode'] ?? ''),
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
                'Sector' => $formData['Sector'],
                'CantidadEntrante' => (int) $formData['CantidadEntrante'],
                'idTipoCompra' => (int) $formData['idTipoCompra'],
                'CardCode' => $formData['CardCode'],
                'FabricanteCode' => $formData['FabricanteCode'],
                'PaisCode' => $formData['PaisCode'],
            ]);

            $this->index([], [], 'La entrada de inventario fue registrada correctamente.');
        } catch (PDOException $exception) {
            $this->index($formData, ['general' => $exception->getMessage()]);
        }
    }

    public function detalle(?string $message = null, ?string $messageType = 'success'): void
    {
        $this->requierePermiso('corregir_entradas');

        $model = $this->model('EntradaInventario');
        $entradas = [];
        $productos = [];
        $presentaciones = [];
        $ubicaciones = [];
        $tiposCompra = [];
        $proveedores = [];
        $paises = [];
        $loadError = null;

        try {
            $entradas = $model->obtenerEntradas();
            $productos = $model->obtenerProductos();
            $presentaciones = $model->obtenerPresentaciones();
            $ubicaciones = $model->obtenerUbicaciones();
            $tiposCompra = $model->obtenerTiposCompra();
            $proveedores = $model->obtenerProveedores();
            $paises = $model->obtenerPaises();
        } catch (PDOException $exception) {
            $loadError = $exception->getMessage();
        }

        $this->view('entrada/detalle', [
            'title' => 'Corregir entradas',
            'entradas' => $entradas,
            'productos' => $productos,
            'presentaciones' => $presentaciones,
            'ubicaciones' => $ubicaciones,
            'tiposCompra' => $tiposCompra,
            'proveedores' => $proveedores,
            'paises' => $paises,
            'sectores' => self::SECTORES,
            'message' => $message,
            'messageType' => $messageType,
            'loadError' => $loadError,
        ]);
    }

    public function actualizar(): void
    {
        $this->requierePermiso('corregir_entradas', 'editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/entrada/detalle');
            return;
        }

        $this->validarCsrf();

        $idInventarioEntrante = filter_input(INPUT_POST, 'idInventarioEntrante', FILTER_VALIDATE_INT);
        $formData = [
            'NumLote' => trim($_POST['NumLote'] ?? ''),
            'idProducto' => $_POST['idProducto'] ?? '',
            'idPresentacion' => $_POST['idPresentacion'] ?? '',
            'idUbicacion' => $_POST['idUbicacion'] ?? '',
            'Sector' => trim($_POST['Sector'] ?? ''),
            'CantidadEntrante' => trim($_POST['CantidadEntrante'] ?? ''),
            'idTipoCompra' => $_POST['idTipoCompra'] ?? '',
            'CardCode' => trim($_POST['CardCode'] ?? ''),
            'FabricanteCode' => trim($_POST['FabricanteCode'] ?? ''),
            'PaisCode' => trim($_POST['PaisCode'] ?? ''),
        ];

        if (!$idInventarioEntrante) {
            $this->detalle('No se pudo identificar la entrada a corregir.', 'error');
            return;
        }

        $errors = $this->validarFormulario($formData);

        if (!empty($errors)) {
            $this->detalle(implode(' ', $errors), 'error');
            return;
        }

        try {
            $model = $this->model('EntradaInventario');
            $salidaTotal = $model->obtenerSalidaTotal($idInventarioEntrante);

            if ((int) $formData['CantidadEntrante'] < $salidaTotal) {
                $this->detalle('La cantidad entrante no puede ser menor que las salidas ya registradas: ' . number_format($salidaTotal, 2), 'error');
                return;
            }

            $model->actualizarEntrada($idInventarioEntrante, [
                'NumLote' => $formData['NumLote'],
                'idProducto' => (int) $formData['idProducto'],
                'idPresentacion' => (int) $formData['idPresentacion'],
                'idUbicacion' => (int) $formData['idUbicacion'],
                'Sector' => $formData['Sector'],
                'CantidadEntrante' => (int) $formData['CantidadEntrante'],
                'idTipoCompra' => (int) $formData['idTipoCompra'],
                'CardCode' => $formData['CardCode'],
                'FabricanteCode' => $formData['FabricanteCode'],
                'PaisCode' => $formData['PaisCode'],
            ]);

            $this->detalle('La entrada fue corregida correctamente.');
        } catch (PDOException $exception) {
            $this->detalle($exception->getMessage(), 'error');
        }
    }

    public function eliminar(): void
    {
        $this->requierePermiso('corregir_entradas', 'borrar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/entrada/detalle');
            return;
        }

        $this->validarCsrf();

        $idInventarioEntrante = filter_input(INPUT_POST, 'idInventarioEntrante', FILTER_VALIDATE_INT);

        if (!$idInventarioEntrante) {
            $this->detalle('No se pudo identificar la entrada a eliminar.', 'error');
            return;
        }

        try {
            $model = $this->model('EntradaInventario');
            $salidaTotal = $model->obtenerSalidaTotal($idInventarioEntrante);

            if ($salidaTotal > 0) {
                $this->detalle('No se puede eliminar una entrada con salidas registradas.', 'error');
                return;
            }

            if (!$model->eliminarEntrada($idInventarioEntrante)) {
                $this->detalle('La entrada seleccionada no existe.', 'error');
                return;
            }

            $this->detalle('La entrada fue eliminada correctamente.');
        } catch (PDOException $exception) {
            $this->detalle($exception->getMessage(), 'error');
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

        if (!in_array($formData['Sector'], self::SECTORES, true)) {
            $errors['Sector'] = 'Seleccione un sector.';
        }

        if (!ctype_digit($formData['CantidadEntrante']) || (int) $formData['CantidadEntrante'] <= 0) {
            $errors['CantidadEntrante'] = 'Escriba una cantidad mayor que cero.';
        }

        if (filter_var($formData['idTipoCompra'], FILTER_VALIDATE_INT) === false) {
            $errors['idTipoCompra'] = 'Seleccione un tipo de compra.';
        }

        if ($formData['CardCode'] === '' || strlen($formData['CardCode']) > 15) {
            $errors['CardCode'] = 'Seleccione un proveedor.';
        }

        if ($formData['FabricanteCode'] === '' || strlen($formData['FabricanteCode']) > 15) {
            $errors['FabricanteCode'] = 'Seleccione un fabricante.';
        }

        if ($formData['PaisCode'] === '' || strlen($formData['PaisCode']) > 3) {
            $errors['PaisCode'] = 'Seleccione un pais.';
        }

        return $errors;
    }
}
