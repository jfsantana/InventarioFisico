<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Models/BaseModel.php';
require_once __DIR__ . '/../app/Models/Cotizacion.php';
require_once __DIR__ . '/../app/Models/ClienteCotizacion.php';
require_once __DIR__ . '/../app/Core/CotizacionPdf.php';
require_once __DIR__ . '/../app/Core/CotizacionNotificador.php';

function verificar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
    echo "OK: $mensaje\n";
}

class CotizacionPrueba extends Cotizacion
{
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function diagnosticoEsquema(): array
    {
        $columna = $this->db->query("SHOW COLUMNS FROM tbl_cotizacion_cabecera LIKE 'idCliente'")->fetch();
        return ['tipoIdCliente' => preg_replace('/\(.*$/', '', $columna['Type']), 'columnas' => [$columna]];
    }
}

class ClienteCotizacionPrueba extends ClienteCotizacion
{
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
}

$db = (new Database())->getConnection();
$tablas = [
    'tbl_clientes_cotizacion' => 'CardCode VARCHAR(15) PRIMARY KEY, CardName VARCHAR(100), LicTradNum VARCHAR(32),
        MailAddres TEXT, Address TEXT, E_Mail VARCHAR(100), Phone1 VARCHAR(50), CardType VARCHAR(1), activo INT DEFAULT 1',
    'tbl_cotizacion_cabecera' => 'idCotizacion INT AUTO_INCREMENT PRIMARY KEY, idCliente VARCHAR(15),
        diasVigencia INT, condicionPago VARCHAR(100), observacion TEXT, subtotal DECIMAL(14,2), total DECIMAL(14,2),
        fechaEmision DATE DEFAULT "2026-10-07", fechaCreacion DATETIME DEFAULT "2026-10-07 10:00:00",
        fechaActualizacion DATETIME DEFAULT "2026-10-07 10:00:00", activo INT DEFAULT 1',
    'tbl_cotizacion_detalle' => 'idDetalle INT AUTO_INCREMENT PRIMARY KEY, idCotizacion INT, idProducto INT,
        idPresentacion INT, cantidad DECIMAL(14,2), precioUnitario DECIMAL(14,2), subtotal DECIMAL(14,2)',
    'Producto' => 'idProducto INT PRIMARY KEY, codigoInterno VARCHAR(20), nombre VARCHAR(100)',
    'presentacion' => 'idPresentacion INT PRIMARY KEY, nombre VARCHAR(100)',
];

try {
    foreach ($tablas as $tabla => $definicion) {
        $db->exec("CREATE TEMPORARY TABLE `$tabla` ($definicion) ENGINE=InnoDB");
    }
    $db->exec("INSERT INTO tbl_clientes_cotizacion (CardCode, CardName, LicTradNum)
        VALUES ('COT00001', 'ACTSA VENEZUELA', 'J-11111111-1'),
               ('COT00002', 'PROVEEDOR DE PRUEBA B', 'J-22222222-2')");
    $db->exec("INSERT INTO Producto VALUES (1, 'P01', 'PRODUCTO DE PRUEBA')");
    $db->exec("INSERT INTO presentacion VALUES (1, 'KG')");
    $modelo = new CotizacionPrueba($db);
    $clientes = new ClienteCotizacionPrueba($db);
    $flujo = CotizacionLog::iniciar();
    verificar(CotizacionLog::registrar('prueba.inicio'), 'Log escribible');

    $cabecera = ['idCliente' => 'COT00002', 'diasVigencia' => 1, 'condicionPago' => 'CONTADO USD', 'subtotal' => 10, 'total' => 11.6];
    $detalles = [['idProducto' => 1, 'idPresentacion' => 1, 'cantidad' => 2, 'precioUnitario' => 5, 'subtotal' => 10]];
    $idB = $modelo->crearCotizacion($cabecera, $detalles);
    $cotizacionB = $modelo->obtenerCotizacionPorId($idB);
    verificar($cotizacionB['nombreCliente'] === 'PROVEEDOR DE PRUEBA B'
        && $cotizacionB['idClienteResuelto'] === 'COT00002', 'Guardado y recuperacion del segundo cliente, no del primero');
    verificar($clientes->obtenerClientePorId('COT00002')['nombre'] === 'PROVEEDOR DE PRUEBA B', 'Busqueda exacta del cliente');
    verificar($clientes->obtenerClientePorId('cot00002') === null, 'No aceptar codigos distintos por collation');
    verificar(count($modelo->obtenerCotizacionesActivas('COT00002')) === 1
        && count($modelo->obtenerCotizacionesActivas('COT00001')) === 0, 'Filtro de historial por cliente exacto');

    $cabecera['idCliente'] = 'COT00001';
    $idA = $modelo->crearCotizacion($cabecera, $detalles);
    $cotizacionA = $modelo->obtenerCotizacionPorId($idA);
    $pdf = new CotizacionPdf();
    $html = new ReflectionMethod(CotizacionPdf::class, 'crearHtml');
    $htmlB = $html->invoke($pdf, $cotizacionB);
    verificar(str_contains($htmlB, 'PROVEEDOR DE PRUEBA B') && !str_contains($htmlB, 'ACTSA VENEZUELA')
        && str_contains($htmlB, 'J-22222222-2'), 'HTML del PDF usa nombre y RIF del cliente seleccionado');
    $pdfB = $pdf->generar($cotizacionB);
    $pdfA = $pdf->generar($cotizacionA);
    verificar(str_starts_with($pdfB, '%PDF-') && strlen($pdfB) > 1000
        && hash('sha256', $pdfB) !== hash('sha256', $pdfA), 'Dos clientes generan PDF validos diferentes');
    $streams = [];
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdfB, $streams);
    $textoPdf = '';
    foreach ($streams[1] as $stream) {
        $descomprimido = @gzuncompress($stream);
        if ($descomprimido !== false) {
            $textoPdf .= $descomprimido;
        }
    }
    verificar(str_contains($textoPdf, mb_convert_encoding('PROVEEDOR DE PRUEBA B', 'UTF-16BE', 'UTF-8'))
        && str_contains($textoPdf, mb_convert_encoding('J-22222222-2', 'UTF-16BE', 'UTF-8'))
        && !str_contains($textoPdf, mb_convert_encoding('ACTSA VENEZUELA', 'UTF-16BE', 'UTF-8')),
        'PDF final contiene nombre/RIF correctos, no solo el HTML');

    $htmlCorreo = new ReflectionMethod(CotizacionNotificador::class, 'crearHtml');
    $correoB = $htmlCorreo->invoke(new CotizacionNotificador(), $cotizacionB, false);
    verificar(str_contains($correoB, 'PROVEEDOR DE PRUEBA B') && !str_contains($correoB, 'ACTSA VENEZUELA'), 'Correo usa el mismo cliente, sin enviar SMTP');
    $incorrecta = $cotizacionB;
    $incorrecta['idClienteResuelto'] = 'COT00001';
    foreach ([$pdf, new CotizacionNotificador()] as $generador) {
        $rechazada = false;
        try {
            $generador instanceof CotizacionPdf ? $generador->generar($incorrecta) : $generador->enviar($incorrecta);
        } catch (InvalidArgumentException $exception) {
            $rechazada = true;
        }
        verificar($rechazada, 'PDF/correo rechaza identidad inconsistente');
    }

    $db->exec("SET SESSION sql_mode = ''");
    $db->exec("DELETE FROM tbl_cotizacion_detalle");
    $db->exec("DELETE FROM tbl_cotizacion_cabecera");
    $db->exec("ALTER TABLE tbl_cotizacion_cabecera MODIFY idCliente INT");
    $db->exec("INSERT INTO tbl_cotizacion_cabecera (idCliente) VALUES (0)");
    $idNumerico = (int) $db->lastInsertId();
    $coincidencias = (int) $db->query('SELECT COUNT(*) FROM tbl_cotizacion_cabecera c
        INNER JOIN tbl_clientes_cotizacion cli ON cli.CardCode = c.idCliente')->fetchColumn();
    verificar($coincidencias === 2, 'Reproduccion: JOIN numerico antiguo empareja ambos clientes');
    verificar($modelo->obtenerCotizacionPorId($idNumerico) === null, 'JOIN corregido no asigna el primer cliente a codigo corrupto');
    $rechazada = false;
    try {
        $modelo->crearCotizacion($cabecera, $detalles);
    } catch (CotizacionEsquemaException $exception) {
        $rechazada = str_contains($exception->getMessage(), 'idCliente numerico')
            && str_contains($exception->getMessage(), 'repair_cotizacion_id_cliente.sql');
    }
    verificar($rechazada && !$db->inTransaction(), 'Esquema numerico bloqueado antes de guardar');

    $db->exec("ALTER TABLE tbl_cotizacion_cabecera MODIFY idCliente VARCHAR(3)");
    $antes = (int) $db->query('SELECT COUNT(*) FROM tbl_cotizacion_cabecera')->fetchColumn();
    $rechazada = false;
    try {
        $modelo->crearCotizacion($cabecera, $detalles);
    } catch (RuntimeException $exception) {
        $rechazada = str_contains($exception->getMessage(), 'revertida');
    }
    verificar($rechazada && (int) $db->query('SELECT COUNT(*) FROM tbl_cotizacion_cabecera')->fetchColumn() === $antes,
        'Truncamiento en modo no estricto revierte la transaccion');
    $log = CotizacionLog::leer(date('Y-m-d'), $flujo);
    verificar(str_contains($log, '"paso":"bd.rollback"') && str_contains($log, '"paso":"pdf.render_completado"'), 'Log correlaciona BD, PDF y errores');
    verificar(CotizacionLog::leer(date('Y-m-d'), str_repeat('f', 32)) === '', 'Filtro de seguimiento no mezcla procesos');
    $rechazada = false;
    try {
        CotizacionLog::leer('../config/config.php');
    } catch (InvalidArgumentException $exception) {
        $rechazada = true;
    }
    verificar($rechazada, 'Visor rechaza rutas arbitrarias');
    $paginaLog = (static function (): string {
        $fecha = date('Y-m-d');
        $flujo = '';
        $esquema = ['tipoIdCliente' => 'varchar'];
        $logDisponible = true;
        $contenido = '<script>alert("prueba")</script>';
        ob_start();
        require __DIR__ . '/../app/Views/cotizacion/diagnostico-log.php';
        return ob_get_clean();
    })();
    verificar(!str_contains($paginaLog, '<script>') && str_contains($paginaLog, '&lt;script&gt;'), 'Visor web escapa el log antes de mostrarlo');
    echo "Regresion completada; sin modificaciones a tablas permanentes ni envio de correo.\n";
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    foreach (array_keys($tablas) as $tabla) {
        $db->exec("DROP TEMPORARY TABLE IF EXISTS `$tabla`");
    }
}
