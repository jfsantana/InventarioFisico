<?php

class SalidaController extends Controller
{
    public function index(
        array $formData = [],
        array $errors = [],
        ?string $successMessage = null,
        ?string $authError = null,
        string $authUsername = ''
    ): void
    {
        $canRegisterDelivery = Auth::check() && Auth::can('salida', 'editar');

        if (!$canRegisterDelivery) {
            $this->view('salida/index', [
                'title' => 'Registrar salida',
                'requiresAuthentication' => true,
                'authError' => $authError,
                'authUsername' => $authUsername,
                'returnQuery' => http_build_query(array_intersect_key($_GET, array_flip(['predespacho', 'sector']))),
            ]);
            return;
        }

        $model = $this->model('Predespacho');
        $sectorSeleccionado = trim($_GET['sector'] ?? '');
        $codigoPredespachoSeleccionado = trim($_GET['predespacho'] ?? '');
        $sectores = [];
        $predespachos = [];
        $predespachoSeleccionado = null;
        $items = [];
        $loadError = null;

        if (($_GET['embarcado'] ?? '') === '1') {
            $successMessage = 'Predespacho embarcado correctamente.';
        }

        try {
            $predespachos = $model->obtenerPredespachosPendientesEntrega();

            if ($codigoPredespachoSeleccionado !== '') {
                $predespachoSeleccionado = $model->obtenerPredespachoPorCodigo($codigoPredespachoSeleccionado);

                if ($predespachoSeleccionado && !in_array($predespachoSeleccionado['statusGeneralPredespacho'], ['abierto', 'pendiente'], true)) {
                    $predespachoSeleccionado = null;
                }

                if ($predespachoSeleccionado) {
                    $sectores = $model->obtenerSectoresPorPredespacho((int) $predespachoSeleccionado['idCabeceraPredespacho']);
                    $items = $model->obtenerItemsPorPredespachoParaEntrega(
                        (int) $predespachoSeleccionado['idCabeceraPredespacho'],
                        $sectorSeleccionado !== '' ? $sectorSeleccionado : null
                    );
                }
            }
        } catch (PDOException $exception) {
            $loadError = $exception->getMessage();
        }

        $this->view('salida/index', [
            'title' => 'Registrar salida',
            'sectores' => $sectores,
            'sectorSeleccionado' => $sectorSeleccionado,
            'predespachos' => $predespachos,
            'codigoPredespachoSeleccionado' => $codigoPredespachoSeleccionado,
            'predespachoSeleccionado' => $predespachoSeleccionado,
            'items' => $items,
            'formData' => $formData,
            'errors' => $errors,
            'successMessage' => $successMessage,
            'loadError' => $loadError,
            'requiresAuthentication' => false,
        ]);
    }

    public function autenticar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/salida');
        }

        $this->validarCsrf();

        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $returnQuery = trim((string) ($_POST['return_query'] ?? ''));

        if (!Auth::login($username, $password, false)) {
            $this->index([], [], null, 'Usuario o contraseña incorrectos.', $username);
            return;
        }

        if (!Auth::can('salida', 'editar')) {
            Auth::logout();
            $this->index([], [], null, 'Este usuario no tiene permiso para registrar entregas.', $username);
            return;
        }

        header('Location: ' . APP_URL . '/salida' . ($returnQuery !== '' ? '?' . $returnQuery : ''));
        exit;
    }

    public function guardar(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Metodo HTTP no permitido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Debe iniciar sesión para registrar entregas.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!Auth::can('salida', 'editar')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No tiene permiso para registrar entregas.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!Auth::validateCsrf()) {
            http_response_code(419);
            echo json_encode(['success' => false, 'error' => 'Token CSRF invalido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $idItem = filter_input(INPUT_POST, 'idItem', FILTER_VALIDATE_INT);
        $idCabeceraPredespacho = filter_input(INPUT_POST, 'idCabeceraPredespacho', FILTER_VALIDATE_INT);
        $cantidadDespachada = $_POST['cantidadDespachada'] ?? null;

        if (!$idItem || !$idCabeceraPredespacho || !is_numeric($cantidadDespachada) || (float) $cantidadDespachada <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Datos incompletos o cantidad invalida.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $model = $this->model('Predespacho');
            $resultado = $model->registrarSalida($idItem, (float) $cantidadDespachada, $idCabeceraPredespacho);

            if (empty($resultado['success'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $resultado['mensaje'] ?? 'No se pudo registrar la entrega.',
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            echo json_encode([
                'success' => true,
                'mensaje' => $resultado['mensaje'] ?? 'Entrega registrada correctamente.',
                'predespacho_embarcado' => !empty($resultado['predespacho_embarcado']) || !empty($resultado['predespachoEmbarcado']),
                'producto_cerrado' => !empty($resultado['producto_cerrado']) || !empty($resultado['productoCerrado']),
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $exception) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
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

            $model->sincronizarPredespachoPorCodigo((string) $salida['NE']);
            if ((string) $salida['NE'] !== $formData['NE']) {
                $model->sincronizarPredespachoPorCodigo($formData['NE']);
            }

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

            $model->sincronizarPredespachoPorCodigo((string) $salida['NE']);

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
