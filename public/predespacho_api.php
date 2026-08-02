<?php

require_once 'predespacho_crud.php';

try {
    AuthSchema::ensure();
} catch (Throwable $exception) {
}

Auth::boot();

header('Content-Type: application/json');

if (empty($_SESSION['usuario']) && !empty($_SESSION['username'])) {
    $_SESSION['usuario'] = $_SESSION['username'];
}

function responderJson(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function valorRequerido(array $source, string $key): string
{
    if (!isset($source[$key]) || trim((string) $source[$key]) === '') {
        throw new InvalidArgumentException('Campo requerido: ' . $key);
    }

    return trim((string) $source[$key]);
}

function valorOpcional(array $source, string $key): ?string
{
    if (!isset($source[$key]) || trim((string) $source[$key]) === '') {
        return null;
    }

    return trim((string) $source[$key]);
}

function enteroRequerido(array $source, string $key): int
{
    $value = valorRequerido($source, $key);
    $filtered = filter_var($value, FILTER_VALIDATE_INT);

    if ($filtered === false) {
        throw new InvalidArgumentException('Valor entero invalido: ' . $key);
    }

    return (int) $filtered;
}

function decimalRequerido(array $source, string $key): float
{
    $value = valorRequerido($source, $key);

    if (!is_numeric($value)) {
        throw new InvalidArgumentException('Valor numerico invalido: ' . $key);
    }

    return (float) $value;
}

if (!Auth::check()) {
    responderJson([
        'success' => false,
        'mensaje' => 'No autorizado',
    ], 401);
}

$requiredAction = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'editar' : 'ver';
if (!Auth::can('predespacho', $requiredAction)) {
    responderJson([
        'success' => false,
        'mensaje' => 'No tiene permiso para gestionar predespachos.',
    ], 403);
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        switch ($accion) {
            case 'crearCliente':
                $idCliente = crearCliente(
                    valorRequerido($_POST, 'rif'),
                    valorRequerido($_POST, 'nombre'),
                    valorRequerido($_POST, 'direccion'),
                    valorRequerido($_POST, 'tipo')
                );

                responderJson([
                    'success' => $idCliente !== false,
                    'idCliente' => $idCliente,
                    'mensaje' => $idCliente !== false ? 'Cliente creado correctamente.' : 'No se pudo crear el cliente.',
                ]);

            case 'actualizarCliente':
                $success = actualizarCliente(
                    enteroRequerido($_POST, 'idCliente'),
                    valorRequerido($_POST, 'rif'),
                    valorRequerido($_POST, 'nombre'),
                    valorRequerido($_POST, 'direccion'),
                    valorRequerido($_POST, 'tipo')
                );

                responderJson([
                    'success' => $success,
                    'mensaje' => $success ? 'Cliente actualizado correctamente.' : 'No se pudo actualizar el cliente.',
                ]);

            case 'desactivarCliente':
                $success = desactivarCliente(enteroRequerido($_POST, 'idCliente'));

                responderJson([
                    'success' => $success,
                    'mensaje' => $success ? 'Cliente desactivado correctamente.' : 'No se pudo desactivar el cliente.',
                ]);

            case 'crearPredespacho':
                $idCabeceraPredespacho = crearCabeceraPredespacho(
                    enteroRequerido($_POST, 'idCliente'),
                    valorRequerido($_POST, 'fechaRetiro'),
                    $_SESSION['usuario'],
                    valorOpcional($_POST, 'codigoNotaEntregaSAP'),
                    valorOpcional($_POST, 'observaciones')
                );

                responderJson([
                    'success' => $idCabeceraPredespacho !== false,
                    'idCabeceraPredespacho' => $idCabeceraPredespacho,
                    'mensaje' => $idCabeceraPredespacho !== false ? 'Predespacho creado correctamente.' : 'No se pudo crear el predespacho.',
                ]);

            case 'actualizarCodigoSAP':
                $success = actualizarCodigoSAP(
                    enteroRequerido($_POST, 'idCabeceraPredespacho'),
                    valorOpcional($_POST, 'codigoNotaEntregaSAP')
                );

                responderJson([
                    'success' => $success,
                    'mensaje' => $success ? 'Codigo SAP actualizado correctamente.' : 'No se pudo actualizar el codigo SAP.',
                ]);

            case 'agregarItem':
                responderJson(agregarItemPredespacho(
                    enteroRequerido($_POST, 'idCabeceraPredespacho'),
                    enteroRequerido($_POST, 'idInventarioEntrante'),
                    decimalRequerido($_POST, 'cantidadSolicitada'),
                    valorOpcional($_POST, 'tipo')
                ));

            case 'eliminarItem':
                responderJson(predespachoModel()->eliminarItemPredespacho(enteroRequerido($_POST, 'idItem')));

            case 'registrarSalida':
                responderJson(registrarSalida(
                    enteroRequerido($_POST, 'idItem'),
                    decimalRequerido($_POST, 'cantidadDespachada'),
                    enteroRequerido($_POST, 'idCabeceraPredespacho')
                ));

            case 'actualizarCantidadDespachada':
                responderJson([
                    'success' => false,
                    'mensaje' => 'La cantidad despachada es calculada desde las salidas registradas.',
                ], 400);

            case 'cerrarItem':
                $success = cerrarItem(enteroRequerido($_POST, 'idItem'));

                responderJson([
                    'success' => $success,
                    'mensaje' => $success ? 'Item cerrado correctamente.' : 'No se pudo cerrar el item.',
                ]);

            case 'cerrarItemConMerma':
                responderJson(cerrarItemConMerma(
                    enteroRequerido($_POST, 'idItem'),
                    enteroRequerido($_POST, 'idCabeceraPredespacho')
                ));

            case 'verificarCierrePredespacho':
                $idCabeceraPredespacho = enteroRequerido($_POST, 'idCabeceraPredespacho');
                $predespachoCerrado = verificarYCerrarPredespacho($idCabeceraPredespacho);
                responderJson([
                    'success' => true,
                    'predespacho_cerrado' => $predespachoCerrado,
                    'mensaje' => $predespachoCerrado ? 'Predespacho cerrado.' : 'Predespacho actualizado.',
                ]);

            case 'cerrarPredespacho':
                $idCabeceraPredespacho = enteroRequerido($_POST, 'idCabeceraPredespacho');
                $predespacho = obtenerPredespachoPorId($idCabeceraPredespacho);
                $success = actualizarStatusCabecera($idCabeceraPredespacho, 'cerrado');

                if ($success && $predespacho) {
                    try {
                        enviarAlertaTelegram(
                            "*Predespacho cerrado*\n" .
                            "Predespacho: " . (string) $predespacho['codigoInterno'] . "\n" .
                            "Cliente: " . (string) $predespacho['nombreCliente'] . "\n" .
                            "Fecha retiro: " . (string) $predespacho['fechaRetiro']
                        );
                    } catch (Throwable $exception) {
                    }
                }

                responderJson([
                    'success' => $success,
                    'mensaje' => $success ? 'Predespacho cerrado correctamente.' : 'No se pudo cerrar el predespacho.',
                ]);

            default:
                responderJson([
                    'success' => false,
                    'mensaje' => 'Accion POST no valida.',
                ], 400);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        switch ($accion) {
            case 'listarClientes':
                responderJson([
                    'success' => true,
                    'data' => obtenerTodosLosClientes(),
                ]);

            case 'listarPredespachos':
                responderJson([
                    'success' => true,
                    'data' => obtenerTodosLosPredespachos(),
                ]);

            case 'detallePredespacho':
                responderJson([
                    'success' => true,
                    'data' => obtenerPredespachoPorId(enteroRequerido($_GET, 'id')),
                ]);

            case 'itemsPredespacho':
                responderJson([
                    'success' => true,
                    'data' => obtenerItemsPorPredespacho(enteroRequerido($_GET, 'id')),
                ]);

            case 'buscarProductos':
                responderJson([
                    'success' => true,
                    'data' => buscarProductosDisponibles(valorRequerido($_GET, 'termino')),
                ]);

            case 'lotesPorProducto':
                responderJson([
                    'success' => true,
                    'data' => obtenerLotesPorProducto(enteroRequerido($_GET, 'idProducto')),
                ]);

            case 'disponibilidadLote':
                responderJson([
                    'success' => true,
                    'data' => obtenerDisponibilidadPorLote(enteroRequerido($_GET, 'idInventarioEntrante')),
                ]);

            case 'predespachosPorSector':
                responderJson([
                    'success' => true,
                    'data' => obtenerPredespachosPorSector(valorRequerido($_GET, 'sector')),
                ]);

            case 'sectoresPendientesPredespacho':
                responderJson([
                    'success' => true,
                    'data' => obtenerSectoresPendientesPredespacho(),
                ]);

            case 'predespachoPorCodigo':
                responderJson([
                    'success' => true,
                    'data' => obtenerPredespachoPorCodigo(valorRequerido($_GET, 'codigoInterno')),
                ]);

            case 'itemsPorSector':
                responderJson([
                    'success' => true,
                    'data' => obtenerItemsPorPredespachoYSector(
                        enteroRequerido($_GET, 'idCabeceraPredespacho'),
                        valorRequerido($_GET, 'sector')
                    ),
                ]);

            default:
                responderJson([
                    'success' => false,
                    'mensaje' => 'Accion GET no valida.',
                ], 400);
        }
    }

    responderJson([
        'success' => false,
        'mensaje' => 'Metodo HTTP no permitido.',
    ], 405);
} catch (InvalidArgumentException $exception) {
    responderJson([
        'success' => false,
        'mensaje' => $exception->getMessage(),
    ], 400);
} catch (Throwable $exception) {
    responderJson([
        'success' => false,
        'mensaje' => 'Error interno del servidor.',
    ], 500);
}