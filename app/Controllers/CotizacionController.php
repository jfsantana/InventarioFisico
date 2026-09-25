<?php

require_once __DIR__ . '/../Core/CotizacionNotificador.php';
require_once __DIR__ . '/../Core/CotizacionPdf.php';

class CotizacionController extends Controller
{
    private const CONDICIONES_PAGO = [
        'CONTADO BSS',
        'CONTADO USD',
        'CONTADO TRANSFERENCIA BSS',
        'CONTADO TRANSFERENCIA USD',
        'ZELLE',
        'BINANCE',
    ];

    public function index(): void
    {
        Auth::requireDirector();

        try {
            $model = $this->model('Cotizacion');
            $this->view('cotizacion/index', [
                'title' => 'Cotizaciones',
                'cotizaciones' => $model->obtenerCotizacionesActivas(isset($_GET['cliente']) ? (string) $_GET['cliente'] : null),
                'csrfToken' => Auth::csrfToken(),
            ]);
        } catch (Throwable $exception) {
            http_response_code(500);
            echo 'No se pudo cargar el historial de cotizaciones.';
        }
    }

    public function crear(): void
    {
        Auth::requireDirector();

        try {
            $clienteModel = $this->model('ClienteCotizacion');
            $inventarioModel = $this->model('EntradaInventario');
            $diasVigencia = $this->diasVigenciaConfigurados();

            $this->view('cotizacion/crear', [
                'title' => 'Nueva cotizacion',
                'bodyClass' => 'cotizacion-creation-mode',
                'clientes' => $clienteModel->obtenerTodosLosClientes(),
                'productos' => $inventarioModel->obtenerProductos(),
                'presentaciones' => $inventarioModel->obtenerPresentaciones(),
                'condicionesPago' => self::CONDICIONES_PAGO,
                'diasVigencia' => $diasVigencia,
                'fechaEmision' => date('Y-m-d'),
                'fechaVencimiento' => date('Y-m-d', strtotime('+' . $diasVigencia . ' days')),
                'clienteSeleccionado' => trim((string) ($_GET['idCliente'] ?? '')),
                'csrfToken' => Auth::csrfToken(),
            ]);
        } catch (Throwable $exception) {
            http_response_code(500);
            echo 'No se pudo inicializar la creacion de cotizaciones.';
        }
    }

    public function guardar(): void
    {
        $payload = $this->iniciarPeticionJson();
        if ($payload === null) {
            return;
        }

        $cabecera = $payload['cabecera'] ?? null;
        $detalles = $payload['detalles'] ?? null;
        $modo = (string) ($payload['modo'] ?? '');
        if (!is_array($cabecera) || !is_array($detalles) || $detalles === []) {
            $this->responderJson(422, false, 'La cabecera y al menos un detalle son obligatorios.');
            return;
        }

        if (!in_array($modo, ['pdf', 'email', 'pdf_email'], true)) {
            $this->responderJson(422, false, 'Seleccione como desea generar la cotizacion.');
            return;
        }

        try {
            [$cabeceraValidada, $detallesValidados, $clienteTieneEmail] = $this->validarCotizacion($cabecera, $detalles);
            if (in_array($modo, ['email', 'pdf_email'], true) && !$clienteTieneEmail) {
                $this->responderJson(422, false, 'El cliente no tiene un email válido. Genere la cotización para descargarla en PDF.');
                return;
            }
            $model = $this->model('Cotizacion');
            $idCotizacion = $model->crearCotizacion($cabeceraValidada, $detallesValidados);

            if ($idCotizacion === false) {
                $this->responderJson(500, false, 'No se pudo guardar la cotizacion.');
                return;
            }

            $debeEnviarCorreo = in_array($modo, ['email', 'pdf_email'], true);
            $debeDescargarPdf = in_array($modo, ['pdf', 'pdf_email'], true);
            $correoEnviado = $debeEnviarCorreo
                ? $this->notificarCotizacion($model, $idCotizacion, false)
                : null;
            $mensaje = 'Cotizacion creada correctamente.';
            if ($debeEnviarCorreo && !$correoEnviado) {
                $mensaje .= ' No se pudo enviar el correo; puede reenviarlo desde el listado de cotizaciones.';
            }

            $this->responderJson(201, true, $mensaje, [
                'idCotizacion' => $idCotizacion,
                'subtotal' => $cabeceraValidada['subtotal'],
                'total' => $cabeceraValidada['total'],
                'correoEnviado' => $correoEnviado,
                'pdfUrl' => $debeDescargarPdf ? APP_URL . '/cotizacion/descargarPdf/' . $idCotizacion : null,
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->responderJson(422, false, $exception->getMessage());
        } catch (Throwable $exception) {
            $this->responderJson(500, false, 'No se pudo guardar la cotizacion.');
        }
    }

    public function descargarPdf(?string $idCotizacion = null): void
    {
        $this->responderPdf($idCotizacion, true);
    }

    public function verPdf(?string $idCotizacion = null): void
    {
        $this->responderPdf($idCotizacion, false);
    }

    private function responderPdf(?string $idCotizacion, bool $descargar): void
    {
        Auth::requireDirector();

        $id = filter_var($idCotizacion, FILTER_VALIDATE_INT);
        if (!$id) {
            http_response_code(404);
            return;
        }

        $model = $this->model('Cotizacion');
        $cotizacion = $model->obtenerCotizacionPorId((int) $id);
        if (!$cotizacion) {
            http_response_code(404);
            return;
        }

        try {
            $pdf = (new CotizacionPdf())->generar($cotizacion);
            $fechaNumero = new DateTimeImmutable((string) ($cotizacion['fechaCreacion'] ?? $cotizacion['fechaEmision']));
            $nombre = 'cotizacion-' . $fechaNumero->format('YmdH') . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . ($descargar ? 'attachment' : 'inline') . '; filename="' . $nombre . '"');
            header('Content-Length: ' . strlen($pdf));
            header('X-Content-Type-Options: nosniff');
            echo $pdf;
        } catch (Throwable $exception) {
            http_response_code(500);
            echo 'No se pudo generar el PDF de la cotizacion.';
        }
    }

    public function actualizarEmail(): void
    {
        $payload = $this->iniciarPeticionJson();
        if ($payload === null) {
            return;
        }

        if (($payload['emailFaltante'] ?? false) !== true) {
            $this->responderJson(200, true, 'No fue necesario actualizar el email.');
            return;
        }

        $idCliente = trim((string) ($payload['idCliente'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        if ($idCliente === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->responderJson(422, false, 'El cliente y un email valido son obligatorios.');
            return;
        }

        $clienteModel = $this->model('ClienteCotizacion');
        $cliente = $clienteModel->obtenerClientePorId($idCliente);
        if (!$cliente) {
            $this->responderJson(404, false, 'Cliente no encontrado.');
            return;
        }

        if (trim((string) ($cliente['email'] ?? '')) !== '') {
            $this->responderJson(409, false, 'El cliente ya tiene un email registrado.');
            return;
        }

        if (!$clienteModel->actualizarEmailSiVacio($idCliente, $email)) {
            $this->responderJson(409, false, 'El email no pudo actualizarse porque ya fue registrado.');
            return;
        }

        $this->responderJson(200, true, 'Email del cliente actualizado correctamente.');
    }

    public function crearCliente(): void
    {
        $payload = $this->iniciarPeticionJson();
        if ($payload === null) {
            return;
        }

        try {
            $clienteModel = $this->model('ClienteCotizacion');
            $idCliente = $clienteModel->crear($payload);
            $cliente = $clienteModel->obtenerClientePorId($idCliente);
            $this->responderJson(201, true, 'Cliente creado correctamente.', ['cliente' => $cliente]);
        } catch (PDOException $exception) {
            $message = ((int) $exception->errorInfo[1] === 1062)
                ? 'Ya existe un cliente de Cotizaciones con ese RIF.'
                : 'No se pudo crear el cliente.';
            $this->responderJson(422, false, $message);
        } catch (InvalidArgumentException $exception) {
            $this->responderJson(422, false, $exception->getMessage());
        } catch (Throwable $exception) {
            $this->responderJson(500, false, 'No se pudo crear el cliente.');
        }
    }

    public function desactivar(): void
    {
        $payload = $this->iniciarPeticionJson();
        if ($payload === null) {
            return;
        }

        $idCotizacion = filter_var($payload['idCotizacion'] ?? null, FILTER_VALIDATE_INT);
        if (!$idCotizacion) {
            $this->responderJson(422, false, 'El identificador de la cotizacion es invalido.');
            return;
        }

        $model = $this->model('Cotizacion');
        if (!$model->desactivarCotizacion((int) $idCotizacion)) {
            $this->responderJson(404, false, 'Cotizacion activa no encontrada.');
            return;
        }

        $this->responderJson(200, true, 'Cotizacion desactivada correctamente.');
    }

    public function reenviarCorreo(): void
    {
        $payload = $this->iniciarPeticionJson();
        if ($payload === null) {
            return;
        }

        $idCotizacion = filter_var($payload['idCotizacion'] ?? null, FILTER_VALIDATE_INT);
        if (!$idCotizacion) {
            $this->responderJson(422, false, 'El identificador de la cotizacion es invalido.');
            return;
        }

        try {
            $model = $this->model('Cotizacion');
            $cotizacion = $model->obtenerCotizacionPorId((int) $idCotizacion);
            if (!$cotizacion) {
                $this->responderJson(404, false, 'Cotizacion activa no encontrada.');
                return;
            }

            if (!$this->notificarCotizacion($model, (int) $idCotizacion, true, $cotizacion)) {
                $this->responderJson(500, false, 'No se pudo reenviar el correo de la cotizacion.');
                return;
            }

            $this->responderJson(200, true, 'Correo reenviado correctamente.');
        } catch (Throwable $exception) {
            $this->responderJson(500, false, 'No se pudo reenviar el correo de la cotizacion.');
        }
    }

    private function validarCotizacion(array $cabecera, array $detalles): array
    {
        $idCliente = trim((string) ($cabecera['idCliente'] ?? ''));
        $diasVigencia = filter_var($cabecera['diasVigencia'] ?? $this->diasVigenciaConfigurados(), FILTER_VALIDATE_INT);
        $condicionPago = strtoupper(trim((string) ($cabecera['condicionPago'] ?? '')));

        if ($idCliente === '' || !$diasVigencia || $diasVigencia < 1 || $diasVigencia > 999) {
            throw new InvalidArgumentException('El cliente y los dias de vigencia son obligatorios.');
        }

        if (!in_array($condicionPago, self::CONDICIONES_PAGO, true)) {
            throw new InvalidArgumentException('La condicion de pago no es valida.');
        }

        $clienteModel = $this->model('ClienteCotizacion');
        $cliente = $clienteModel->obtenerClientePorId($idCliente);
        if (!$cliente || (int) $cliente['activo'] !== 1) {
            throw new InvalidArgumentException('El cliente seleccionado no esta activo.');
        }

        $clienteTieneEmail = filter_var($cliente['email'] ?? '', FILTER_VALIDATE_EMAIL) !== false;

        $inventarioModel = $this->model('EntradaInventario');
        $productosValidos = array_fill_keys(array_column($inventarioModel->obtenerProductos(), 'idProducto'), true);
        $presentacionesValidas = array_fill_keys(array_column($inventarioModel->obtenerPresentaciones(), 'idPresentacion'), true);
        $detallesValidados = [];
        $subtotal = 0.0;

        foreach ($detalles as $indice => $detalle) {
            if (!is_array($detalle)) {
                throw new InvalidArgumentException('El detalle ' . ($indice + 1) . ' no es valido.');
            }

            $idProducto = filter_var($detalle['idProducto'] ?? null, FILTER_VALIDATE_INT);
            $idPresentacion = filter_var($detalle['idPresentacion'] ?? null, FILTER_VALIDATE_INT);
            $cantidad = $detalle['cantidad'] ?? null;
            $precioUnitario = $detalle['precioUnitario'] ?? null;

            if (!$idProducto || !isset($productosValidos[$idProducto])) {
                throw new InvalidArgumentException('El producto del detalle ' . ($indice + 1) . ' no existe.');
            }

            if (!$idPresentacion || !isset($presentacionesValidas[$idPresentacion])) {
                throw new InvalidArgumentException('La presentacion del detalle ' . ($indice + 1) . ' no existe.');
            }

            if (!is_numeric($cantidad) || !is_numeric($precioUnitario) || (float) $cantidad <= 0 || (float) $precioUnitario <= 0) {
                throw new InvalidArgumentException('La cantidad y el precio del detalle ' . ($indice + 1) . ' deben ser mayores que cero.');
            }

            $cantidad = round((float) $cantidad, 2);
            $precioUnitario = round((float) $precioUnitario, 2);
            $subtotalDetalle = round($cantidad * $precioUnitario, 2);

            if (isset($detalle['subtotal']) && (!is_numeric($detalle['subtotal']) || abs((float) $detalle['subtotal'] - $subtotalDetalle) >= 0.01)) {
                throw new InvalidArgumentException('El subtotal del detalle ' . ($indice + 1) . ' no coincide con cantidad por precio.');
            }

            $subtotal = round($subtotal + $subtotalDetalle, 2);
            $detallesValidados[] = [
                'idProducto' => (int) $idProducto,
                'idPresentacion' => (int) $idPresentacion,
                'cantidad' => $cantidad,
                'precioUnitario' => $precioUnitario,
                'subtotal' => $subtotalDetalle,
            ];
        }

        $iva = round($subtotal * 0.16, 2);
        $total = round($subtotal + $iva, 2);
        $totalesEsperados = ['subtotal' => $subtotal, 'total' => $total];

        foreach ($totalesEsperados as $campo => $valorEsperado) {
            if (isset($cabecera[$campo]) && (!is_numeric($cabecera[$campo]) || abs((float) $cabecera[$campo] - $valorEsperado) >= 0.01)) {
                throw new InvalidArgumentException('El ' . $campo . ' enviado no coincide con el importe calculado.');
            }
        }

        return [[
            'idCliente' => $idCliente,
            'diasVigencia' => (int) $diasVigencia,
            'condicionPago' => $condicionPago,
            'observacion' => trim((string) ($cabecera['observacion'] ?? '')) ?: null,
            'subtotal' => $subtotal,
            'total' => $total,
        ], $detallesValidados, $clienteTieneEmail];
    }

    private function iniciarPeticionJson(): ?array
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responderJson(405, false, 'Metodo HTTP no permitido.');
            return null;
        }

        if (!Auth::check()) {
            $this->responderJson(401, false, 'Debe iniciar sesion.');
            return null;
        }

        if (!Auth::isDirector()) {
            $this->responderJson(403, false, 'Acceso exclusivo para directores.');
            return null;
        }

        try {
            $payload = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->responderJson(400, false, 'El cuerpo JSON no es valido.');
            return null;
        }

        if (!is_array($payload)) {
            $this->responderJson(400, false, 'El cuerpo JSON no es valido.');
            return null;
        }

        $csrfToken = $payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($csrfToken) || !hash_equals(Auth::csrfToken(), $csrfToken)) {
            $this->responderJson(419, false, 'Token CSRF invalido.');
            return null;
        }

        return $payload;
    }

    private function diasVigenciaConfigurados(): int
    {
        return defined('COTIZACION_DIAS_VIGENCIA')
            ? max(1, (int) constant('COTIZACION_DIAS_VIGENCIA'))
            : 1;
    }

    private function notificarCotizacion(Cotizacion $model, int $idCotizacion, bool $esReenvio, ?array $cotizacion = null): bool
    {
        try {
            $cotizacion ??= $model->obtenerCotizacionPorId($idCotizacion);
            if (!$cotizacion) {
                return false;
            }

            $contactoModel = $this->model('ContactoInternoEmail');
            $contactosInternos = $contactoModel->obtenerPorProceso('COTIZACION');
            (new CotizacionNotificador())->enviar($cotizacion, $contactosInternos, $esReenvio);

            Auth::log(
                (int) ($_SESSION['id_usuario'] ?? 0),
                $_SESSION['username'] ?? null,
                'cotizacion',
                $esReenvio ? 'reenviar_correo' : 'enviar_correo',
                'exitoso',
                'Cotizacion #' . $idCotizacion
            );

            return true;
        } catch (Throwable $exception) {
            Auth::log(
                (int) ($_SESSION['id_usuario'] ?? 0),
                $_SESSION['username'] ?? null,
                'cotizacion',
                $esReenvio ? 'reenviar_correo' : 'enviar_correo',
                'fallo',
                'Cotizacion #' . $idCotizacion . ': ' . $exception->getMessage()
            );

            return false;
        }
    }

    private function responderJson(int $status, bool $success, string $mensaje, array $data = []): void
    {
        http_response_code($status);
        echo json_encode([
            'success' => $success,
            $success ? 'mensaje' : 'error' => $mensaje,
        ] + $data, JSON_UNESCAPED_UNICODE);
    }
}