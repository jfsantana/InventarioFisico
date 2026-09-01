<?php

class EntradaController extends Controller
{
    private const SECTORES = ['Sector1', 'Sector2', 'Sector3'];
    private const MAX_DOCUMENTOS_BYTES = 10485760;
    private const DOCUMENTOS = [
        'ticketRomana' => ['tipo' => 'ticket_romana', 'label' => 'Ticket de romana'],
        'facturaProveedor' => ['tipo' => 'factura_proveedor', 'label' => 'Factura del proveedor'],
        'documentoSeniat' => ['tipo' => 'documento_seniat', 'label' => 'Documento de Seniat'],
    ];
    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

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
            'fecha_factura' => trim($_POST['fecha_factura'] ?? ''),
            'peso_romana' => trim($_POST['peso_romana'] ?? ''),
            'nro_factura' => trim($_POST['nro_factura'] ?? ''),
        ];

        $errors = array_merge(
            $this->validarFormulario($formData),
            $this->validarDocumentos([], false)
        );

        if (!empty($errors)) {
            $this->index($formData, $errors);
            return;
        }

        $idNuevaEntrada = null;

        try {
            $model = $this->model('EntradaInventario');
            $idNuevaEntrada = $model->registrarEntrada([
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
                'fecha_factura' => $formData['fecha_factura'],
                'peso_romana' => (float) $formData['peso_romana'],
                'nro_factura' => $formData['nro_factura'],
            ]);
            $this->guardarDocumentos($model, $idNuevaEntrada, []);
            $correoEnviado = $this->notificarEntrada($model, $idNuevaEntrada, 'creacion');

            $mensaje = 'La entrada de inventario fue registrada correctamente.';
            if (!$correoEnviado) {
                $mensaje .= ' No se pudo enviar el correo de notificacion; revise el log del servidor.';
            }
            $this->index([], [], $mensaje);
        } catch (Throwable $exception) {
            if ($idNuevaEntrada !== null && isset($model)) {
                $this->eliminarArchivos($model->obtenerDocumentosEntrada($idNuevaEntrada));
                $model->eliminarDocumentosEntrada($idNuevaEntrada);
                $model->eliminarEntrada($idNuevaEntrada);
            }
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
        $documentosPorEntrada = [];
        $loadError = null;

        try {
            $entradas = $model->obtenerEntradas();
            $productos = $model->obtenerProductos();
            $presentaciones = $model->obtenerPresentaciones();
            $ubicaciones = $model->obtenerUbicaciones();
            $tiposCompra = $model->obtenerTiposCompra();
            $proveedores = $model->obtenerProveedores();
            $paises = $model->obtenerPaises();
            $documentosPorEntrada = $model->obtenerTodosDocumentos();
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
            'documentosPorEntrada' => $documentosPorEntrada,
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
            'fecha_factura' => trim($_POST['fecha_factura'] ?? ''),
            'peso_romana' => trim($_POST['peso_romana'] ?? ''),
            'nro_factura' => trim($_POST['nro_factura'] ?? ''),
        ];

        if (!$idInventarioEntrante) {
            $this->detalle('No se pudo identificar la entrada a corregir.', 'error');
            return;
        }

        $model = $this->model('EntradaInventario');
        $documentosExistentes = $model->obtenerDocumentosEntrada($idInventarioEntrante);
        $errors = array_merge(
            $this->validarFormulario($formData),
            $this->validarDocumentos($documentosExistentes, false)
        );

        if (!empty($errors)) {
            $this->detalle(implode(' ', $errors), 'error');
            return;
        }

        try {
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
                'fecha_factura' => $formData['fecha_factura'],
                'peso_romana' => (float) $formData['peso_romana'],
                'nro_factura' => $formData['nro_factura'],
            ]);
            $this->guardarDocumentos($model, $idInventarioEntrante, $documentosExistentes);
            $correoEnviado = $this->notificarEntrada($model, $idInventarioEntrante, 'edicion');

            $mensaje = 'La entrada fue corregida correctamente.';
            if (!$correoEnviado) {
                $mensaje .= ' No se pudo enviar el correo de notificacion; revise el log del servidor.';
            }
            $this->detalle($mensaje, $correoEnviado ? 'success' : 'error');
        } catch (Throwable $exception) {
            $this->detalle($exception->getMessage(), 'error');
        }
    }

    public function descargarDocumento(?string $idDocumento = null): void
    {
        $this->requierePermiso('corregir_entradas');

        $id = filter_var($idDocumento, FILTER_VALIDATE_INT);
        if (!$id) {
            http_response_code(404);
            return;
        }

        $model = $this->model('EntradaInventario');
        $documento = $model->obtenerDocumentoPorId((int) $id);
        if (!$documento) {
            http_response_code(404);
            return;
        }

        $basePath = realpath($this->rutaDocumentos());
        $filePath = realpath($this->rutaDocumentos() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $documento['rutaRelativa']));
        if ($basePath === false || $filePath === false || !str_starts_with($filePath, $basePath . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
            http_response_code(404);
            return;
        }

        $nombreAscii = preg_replace('/[^A-Za-z0-9._-]/', '_', $documento['nombreOriginal']) ?: 'documento';
        header('Content-Type: ' . $documento['mimeType']);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: attachment; filename="' . $nombreAscii . '"; filename*=UTF-8\'\'' . rawurlencode($documento['nombreOriginal']));
        readfile($filePath);
        exit;
    }

    public function reenviarCorreo(): void
    {
        $this->requierePermiso('corregir_entradas', 'editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . APP_URL . '/entrada/detalle');
            return;
        }

        $this->validarCsrf();

        $idInventarioEntrante = filter_input(INPUT_POST, 'idInventarioEntrante', FILTER_VALIDATE_INT);
        if (!$idInventarioEntrante) {
            $this->detalle('No se pudo identificar la entrada para reenviar el correo.', 'error');
            return;
        }

        $model = $this->model('EntradaInventario');
        if (!$this->notificarEntrada($model, $idInventarioEntrante, 'reenvio')) {
            $this->detalle('No se pudo reenviar el correo de notificacion; revise el log del servidor.', 'error');
            return;
        }

        $this->detalle('El correo de la entrada #' . $idInventarioEntrante . ' fue reenviado correctamente.');
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
            $documentos = $model->obtenerDocumentosEntrada($idInventarioEntrante);

            if ($salidaTotal > 0) {
                $this->detalle('No se puede eliminar una entrada con salidas registradas.', 'error');
                return;
            }

            if (!$model->eliminarEntrada($idInventarioEntrante)) {
                $this->detalle('La entrada seleccionada no existe.', 'error');
                return;
            }

            $model->eliminarDocumentosEntrada($idInventarioEntrante);
            $this->eliminarArchivos($documentos);

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

        $fechaFactura = DateTimeImmutable::createFromFormat('!Y-m-d', $formData['fecha_factura']);
        if (!$fechaFactura || $fechaFactura->format('Y-m-d') !== $formData['fecha_factura']) {
            $errors['fecha_factura'] = 'Seleccione una fecha de factura valida.';
        }

        if (!is_numeric($formData['peso_romana']) || (float) $formData['peso_romana'] <= 0) {
            $errors['peso_romana'] = 'Escriba un peso de romana mayor que cero.';
        }

        if (!preg_match('/^[A-Za-z0-9]+$/', $formData['nro_factura']) || strlen($formData['nro_factura']) > 50) {
            $errors['nro_factura'] = 'El numero de factura debe contener solo letras y numeros (maximo 50).';
        }

        return $errors;
    }

    private function validarDocumentos(array $existentes, bool $requeridos): array
    {
        $errors = [];
        $totalBytes = array_sum(array_map(
            static fn (array $documento): int => (int) $documento['tamanoBytes'],
            $existentes
        ));
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        foreach (self::DOCUMENTOS as $campo => $configuracion) {
            $archivo = $_FILES[$campo] ?? null;
            $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($error === UPLOAD_ERR_NO_FILE) {
                if ($requeridos) {
                    $errors[$campo] = 'Adjunte ' . strtolower($configuracion['label']) . '.';
                }
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                $errors[$campo] = 'No se pudo cargar ' . strtolower($configuracion['label']) . '.';
                continue;
            }

            if (!is_uploaded_file($archivo['tmp_name'])) {
                $errors[$campo] = 'El archivo recibido no es valido.';
                continue;
            }

            $mimeType = $finfo->file($archivo['tmp_name']);
            if (!isset(self::MIME_EXTENSIONS[$mimeType])) {
                $errors[$campo] = 'Solo se permiten archivos PDF, JPG o PNG.';
                continue;
            }

            $tipo = $configuracion['tipo'];
            $totalBytes -= (int) ($existentes[$tipo]['tamanoBytes'] ?? 0);
            $totalBytes += (int) $archivo['size'];
        }

        if ($totalBytes > self::MAX_DOCUMENTOS_BYTES) {
            $errors['documentos'] = 'Los tres documentos no pueden superar 10 MB en total.';
        }

        return $errors;
    }

    private function guardarDocumentos(EntradaInventario $model, int $idInventarioEntrante, array $existentes): void
    {
        $directorioEntrada = $this->rutaDocumentos() . DIRECTORY_SEPARATOR . $idInventarioEntrante;
        if (!is_dir($directorioEntrada) && !mkdir($directorioEntrada, 0770, true) && !is_dir($directorioEntrada)) {
            throw new RuntimeException('No se pudo crear la carpeta para los documentos.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $usuario = Auth::user();

        foreach (self::DOCUMENTOS as $campo => $configuracion) {
            $archivo = $_FILES[$campo] ?? null;
            if ((int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $mimeType = $finfo->file($archivo['tmp_name']);
            $extension = self::MIME_EXTENSIONS[$mimeType];
            $nombreAlmacenado = bin2hex(random_bytes(16)) . '.' . $extension;
            $rutaRelativa = $idInventarioEntrante . '/' . $nombreAlmacenado;
            $destino = $directorioEntrada . DIRECTORY_SEPARATOR . $nombreAlmacenado;

            if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
                throw new RuntimeException('No se pudo guardar ' . strtolower($configuracion['label']) . '.');
            }

            try {
                $model->guardarDocumento($idInventarioEntrante, [
                    'tipoDocumento' => $configuracion['tipo'],
                    'nombreOriginal' => basename($archivo['name']),
                    'nombreAlmacenado' => $nombreAlmacenado,
                    'rutaRelativa' => $rutaRelativa,
                    'mimeType' => $mimeType,
                    'tamanoBytes' => (int) $archivo['size'],
                    'idUsuario' => (int) ($usuario['id_usuario'] ?? 0) ?: null,
                ]);
            } catch (Throwable $exception) {
                @unlink($destino);
                throw $exception;
            }

            $anterior = $existentes[$configuracion['tipo']] ?? null;
            if ($anterior) {
                $this->eliminarArchivo($anterior);
            }
        }
    }

    private function eliminarArchivos(array $documentos): void
    {
        foreach ($documentos as $documento) {
            $this->eliminarArchivo($documento);
        }
    }

    private function eliminarArchivo(array $documento): void
    {
        $ruta = $this->rutaDocumentos() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $documento['rutaRelativa']);
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }

    private function rutaDocumentos(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'entradas';
    }

    private function notificarEntrada(EntradaInventario $model, int $idInventarioEntrante, string $evento): bool
    {
        try {
            $entrada = $model->obtenerDetalleEntradaParaCorreo($idInventarioEntrante);
            if (!$entrada) {
                throw new RuntimeException('No se encontro el detalle de la entrada.');
            }

            $notificador = new EntradaNotificador();
            $notificador->enviar(
                $entrada,
                $model->obtenerDestinatariosEntrada(),
                $model->obtenerDocumentosEntrada($idInventarioEntrante),
                $this->rutaDocumentos(),
                $evento
            );

            return true;
        } catch (Throwable $exception) {
            error_log('No se pudo notificar la entrada #' . $idInventarioEntrante . ': ' . $exception->getMessage());

            return false;
        }
    }
}
