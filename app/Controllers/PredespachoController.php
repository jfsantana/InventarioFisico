<?php

class PredespachoController extends Controller
{
    public function index(): void
    {
        $this->requierePermiso('predespacho');

        $this->view('predespacho/lista', [
            'title' => 'Gestion de Predespachos',
        ]);
    }

    public function detalle(): void
    {
        $this->requierePermiso('predespacho');

        $idCabeceraPredespacho = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
        $model = $this->model('Predespacho');
        $predespacho = $idCabeceraPredespacho > 0 ? $model->obtenerPredespachoPorId($idCabeceraPredespacho) : null;
        $tokenCierre = $predespacho ? $model->obtenerTokenCierre($idCabeceraPredespacho) : null;
        $estadoAdmiteQr = $predespacho
            && in_array($predespacho['statusGeneralPredespacho'], ['embarcado', 'cerrado'], true);
        $urlCierre = $estadoAdmiteQr && $tokenCierre
            ? APP_URL . '/predespacho/confirmarSalida?' . http_build_query([
                'codigo' => $predespacho['codigoInterno'],
                'token' => $tokenCierre,
            ])
            : '';

        $this->view('predespacho/detalle', [
            'title' => 'Detalle de Predespacho',
            'idCabeceraPredespacho' => $idCabeceraPredespacho,
            'urlCierre' => $urlCierre,
        ]);
    }

    public function confirmarSalida(): void
    {
        $codigoInterno = trim((string) ($_POST['codigo'] ?? $_GET['codigo'] ?? ''));
        $tokenCierre = strtolower(trim((string) ($_POST['token'] ?? $_GET['token'] ?? '')));
        $accion = (string) ($_POST['accion'] ?? '');
        $model = $this->model('Predespacho');
        $resumen = $model->obtenerResumenCierrePorQr($codigoInterno, $tokenCierre);
        $estadoActual = (string) ($resumen['statusGeneralPredespacho'] ?? '');
        $predespachoCerrado = $estadoActual === 'cerrado';
        $qrDisponible = $resumen
            && in_array($estadoActual, ['embarcado', 'cerrado'], true);
        $resultado = null;
        $errorAutenticacion = null;
        $claveAutorizacion = hash('sha256', $codigoInterno . '|' . $tokenCierre);
        $autorizacion = $_SESSION['cierres_qr_autorizados'][$claveAutorizacion] ?? null;
        $usuarioActual = Auth::user();
        $autorizado = is_array($autorizacion)
            && Auth::check()
            && (int) ($autorizacion['idUsuario'] ?? 0) === (int) ($usuarioActual['id_usuario'] ?? 0)
            && (int) ($autorizacion['vence'] ?? 0) >= time();

        if (!$resumen) {
            http_response_code(404);
        } elseif ($predespachoCerrado) {
            unset($_SESSION['cierres_qr_autorizados'][$claveAutorizacion]);
        } elseif (!$qrDisponible) {
            unset($_SESSION['cierres_qr_autorizados'][$claveAutorizacion]);

            if ($model->debeEnviarAlertaQrUrgente($codigoInterno, $estadoActual)) {
                $mensajeAlerta = "🚨 *VERIFICACIÓN URGENTE DE SALIDA*\n"
                    . "────────────────────\n"
                    . '*Predespacho:* ' . $codigoInterno . "\n"
                    . '*Cliente:* ' . $resumen['nombreCliente'] . "\n"
                    . '*Estado actual:* ' . strtoupper($estadoActual) . "\n"
                    . '*IP del intento:* ' . ($_SERVER['REMOTE_ADDR'] ?? 'No disponible') . "\n"
                    . '*Fecha:* ' . date('d/m/Y H:i') . "\n\n"
                    . 'Se intentó utilizar el QR de salida sin que la carga estuviera verificada y/o completada.';
                $alertaEnviada = enviarAlertaTelegram($mensajeAlerta);

                Auth::log(
                    null,
                    null,
                    'predespacho',
                    'alerta_qr_estado_invalido',
                    $alertaEnviada ? 'exitoso' : 'fallo',
                    $codigoInterno . '|' . $estadoActual
                );
            }

            http_response_code(409);
        } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $accion === 'autenticar') {
            $this->validarCsrf();
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if (Auth::login($username, $password, false)) {
                $usuarioActual = Auth::user();
                $_SESSION['cierres_qr_autorizados'][$claveAutorizacion] = [
                    'idUsuario' => (int) $usuarioActual['id_usuario'],
                    'nombre' => (string) $usuarioActual['nombre_completo'],
                    'username' => (string) $usuarioActual['username'],
                    'vence' => time() + 300,
                ];
                $this->redirect('/predespacho/confirmarSalida?' . http_build_query([
                    'codigo' => $codigoInterno,
                    'token' => $tokenCierre,
                ]));
            }

            unset($_SESSION['cierres_qr_autorizados'][$claveAutorizacion]);
            $errorAutenticacion = 'Usuario o contraseña incorrectos.';
            $autorizado = false;
        } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $accion === 'confirmar') {
            if (!$autorizado) {
                unset($_SESSION['cierres_qr_autorizados'][$claveAutorizacion]);
                $errorAutenticacion = 'La autorización venció. Ingrese nuevamente sus credenciales.';
            } else {
                $this->validarCsrf();
                $resultado = $model->cerrarPredespachoPorQr(
                    $codigoInterno,
                    $tokenCierre,
                    (int) $autorizacion['idUsuario'],
                    (string) $autorizacion['nombre']
                );

                Auth::log(
                    (int) $autorizacion['idUsuario'],
                    (string) $autorizacion['username'],
                    'predespacho',
                    'confirmar_salida_qr',
                    !empty($resultado['success']) ? 'exitoso' : 'fallo',
                    $codigoInterno . ': ' . (string) ($resultado['mensaje'] ?? '')
                );

                if (!empty($resultado['cerradoAhora'])) {
                    $lineasProductos = [];
                    foreach ($resumen['items'] as $indice => $item) {
                        $lineasProductos[] = ($indice + 1) . '. ' . $item['nombreProducto']
                            . ' | Entregado: ' . number_format((float) $item['cantidadDespachada'], 2, '.', '');
                    }

                    $mensaje = "🚚 *DESPACHO Y CERRO*\n"
                        . "────────────────────\n"
                        . '*Código:* ' . $resumen['codigoInterno'] . "\n"
                        . '*Cliente:* ' . $resumen['nombreCliente'] . "\n"
                        . '*Responsable:* ' . $autorizacion['nombre'] . ' (' . $autorizacion['username'] . ")\n"
                        . '*Fecha de salida:* ' . date('d/m/Y H:i') . "\n"
                        . '*Código SAP:* ' . ($resumen['codigoNotaEntregaSAP'] ?: 'Sin código') . "\n\n"
                        . '*Productos (' . count($resumen['items']) . '):*' . "\n"
                        . implode("\n", $lineasProductos);

                    enviarAlertaTelegram($mensaje);
                }

                unset($_SESSION['cierres_qr_autorizados'][$claveAutorizacion]);
                $resumen = $model->obtenerResumenCierrePorQr($codigoInterno, $tokenCierre);
            }
        }

        $this->view('predespacho/confirmar_salida', [
            'title' => 'Confirmar salida de predespacho',
            'codigoInterno' => $codigoInterno,
            'tokenCierre' => $tokenCierre,
            'resumen' => $resumen,
            'resultado' => $resultado,
            'errorAutenticacion' => $errorAutenticacion,
            'requiereAutenticacion' => $estadoActual === 'embarcado' && !$autorizado && !$resultado,
            'usuarioAutorizado' => $autorizado ? $autorizacion : null,
            'qrDisponible' => $qrDisponible,
            'predespachoCerrado' => $predespachoCerrado,
        ]);
    }

    public function salida(): void
    {
        $this->requierePermiso('predespacho');

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