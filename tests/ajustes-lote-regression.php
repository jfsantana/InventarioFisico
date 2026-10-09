<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
spl_autoload_register(static function (string $clase): void {
    foreach (['Core', 'Models'] as $carpeta) {
        $ruta = __DIR__ . '/../app/' . $carpeta . '/' . $clase . '.php';
        if (is_file($ruta)) {
            require_once $ruta;
            return;
        }
    }
});

trait ConexionAjustePrueba
{
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
}
class AjustePrueba extends AjusteLote { use ConexionAjustePrueba; }
class ReporteAjustePrueba extends ReporteInventario { use ConexionAjustePrueba; }
class AnaliticaAjustePrueba extends AnaliticaInventario { use ConexionAjustePrueba; }
class SalidaAjustePrueba extends SalidaInventario { use ConexionAjustePrueba; }
class SiloAjustePrueba extends Silo { use ConexionAjustePrueba; }
class PredespachoAjustePrueba extends Predespacho { use ConexionAjustePrueba; }
class EntradaAjustePrueba extends EntradaInventario { use ConexionAjustePrueba; }

$pruebas = 0;
function verificarAjuste(bool $condicion, string $mensaje): void
{
    global $pruebas;
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
    $pruebas++;
    echo "OK: $mensaje\n";
}
function rechazarAjuste(callable $accion, string $mensaje, string $clase = DomainException::class): void
{
    try {
        $accion();
    } catch (Throwable $error) {
        verificarAjuste($error instanceof $clase, $mensaje);
        return;
    }
    throw new RuntimeException('No se rechazo: ' . $mensaje);
}
function datosAjuste(int $entrada, string $tipo, string $monto, ?int $silo = null): array
{
    return [
        'idInventarioEntrante' => $entrada, 'tipo' => $tipo, 'monto' => $monto,
        'observacion' => 'Conteo fisico de prueba', 'tokenRegistro' => bin2hex(random_bytes(32)),
        'idSilo' => $silo,
    ];
}

$conexion = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
if (($argv[1] ?? '') === '--exportar') {
    $base = $argv[2] ?? '';
    if (!preg_match('/^inv_ajuste_test_[a-f0-9]{12}$/D', $base)) {
        throw new RuntimeException('La exportacion solo admite la base aislada de pruebas.');
    }
    $conexion->exec("USE `$base`");
    $modeloExport = new ReporteAjustePrueba($conexion);
    $exportador = new ReporteExcel();
    if (($argv[3] ?? '') === 'movimientos') {
        $exportador->descargarMovimientos($modeloExport->obtenerMovimientosPorProducto(1), ['Prueba' => 'Ajustes']);
    } elseif (($argv[3] ?? '') === 'saldos') {
        $exportador->descargarSaldos($modeloExport->obtenerSaldosPorLote([1]), ['Prueba' => 'Ajustes']);
    }
    throw new RuntimeException('Formato de prueba no valido.');
}

function leerExportacionAjuste(string $base, string $formato): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $archivo = tempnam(sys_get_temp_dir(), 'ajuste-xlsx-');
    $errorArchivo = tempnam(sys_get_temp_dir(), 'ajuste-error-');
    try {
        $comando = [PHP_BINARY, '-n', '-d', 'extension_dir=' . ini_get('extension_dir'),
            '-d', 'extension=pdo_mysql', '-d', 'extension=mbstring', '-d', 'extension=zip',
            __FILE__, '--exportar', $base, $formato];
        $proceso = proc_open($comando, [0 => ['pipe', 'r'], 1 => ['file', $archivo, 'wb'], 2 => ['file', $errorArchivo, 'wb']], $pipes);
        if (!is_resource($proceso)) {
            throw new RuntimeException('No se pudo ejecutar la exportacion de prueba.');
        }
        fclose($pipes[0]);
        $codigo = proc_close($proceso);
        if ($codigo !== 0) {
            throw new RuntimeException('Exportacion fallo con codigo ' . $codigo . ': ' . file_get_contents($errorArchivo));
        }
        return \PhpOffice\PhpSpreadsheet\IOFactory::load($archivo);
    } finally {
        unlink($archivo);
        unlink($errorArchivo);
    }
}

$esquema = 'inv_ajuste_test_' . bin2hex(random_bytes(6));
$creado = false;
$mantener = in_array('--mantener', $argv, true);
$exitoso = false;
try {
    $conexion->exec("CREATE DATABASE `$esquema` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $creado = true;
    $conexion->exec("USE `$esquema`");
    $tablas = [
        'Producto' => 'idProducto INT PRIMARY KEY, codigoInterno VARCHAR(50), nombre VARCHAR(100)',
        'presentacion' => 'idPresentacion INT PRIMARY KEY, nombre VARCHAR(50)',
        'ubicacion' => 'idUbicacion INT PRIMARY KEY, nombre VARCHAR(200)',
        'tipo_compra' => 'id INT PRIMARY KEY, descripcion VARCHAR(100)',
        'proveedores' => 'CardCode VARCHAR(50) PRIMARY KEY, CardName VARCHAR(100)',
        'paises' => 'Code VARCHAR(50) PRIMARY KEY, Name VARCHAR(100)',
        'inventarioentrante' => 'idInventarioEntrante INT AUTO_INCREMENT PRIMARY KEY, idProducto INT,
            idPresentacion INT, `idUbicación` INT, NumLote VARCHAR(100), sector VARCHAR(50),
            CantidadEntrante DECIMAL(14,3), fecha DATE, idTipoCompra INT, CardCode VARCHAR(50),
            FabricanteCode VARCHAR(50), PaisCode VARCHAR(50), fecha_factura DATE,
            peso_romana DECIMAL(14,3), nro_factura VARCHAR(50), observaciones VARCHAR(256)',
        'inventariosaliente' => 'idInventarioSaliente INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            idInventarioEntrante INT UNSIGNED, sector VARCHAR(30), NE VARCHAR(80),
            cantidadSaliente DECIMAL(14,3), fecha DATETIME',
        'usuarios' => 'id_usuario INT AUTO_INCREMENT PRIMARY KEY, nombre_completo VARCHAR(140),
            username VARCHAR(60), password_hash VARCHAR(255), id_rol INT, activo INT DEFAULT 1,
            intentos_fallidos INT DEFAULT 0, bloqueado_hasta DATETIME, ultimo_acceso DATETIME',
        'roles' => 'id_rol INT PRIMARY KEY, nombre VARCHAR(60), activo INT DEFAULT 1',
        'permisos_modulo' => 'id_permiso INT AUTO_INCREMENT PRIMARY KEY, id_rol INT, modulo VARCHAR(80),
            puede_ver INT, puede_editar INT, puede_borrar INT',
        'log_accesos' => 'id_log INT AUTO_INCREMENT PRIMARY KEY, id_usuario INT, username VARCHAR(60),
            modulo VARCHAR(80), accion VARCHAR(80), ip VARCHAR(45), resultado VARCHAR(20),
            detalle VARCHAR(255), fecha DATETIME DEFAULT CURRENT_TIMESTAMP',
        'tbl_cliente' => 'idCliente INT UNSIGNED PRIMARY KEY, nombre VARCHAR(150)',
        'tbl_cabecera_predespacho' => 'idCabeceraPredespacho INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            idCliente INT UNSIGNED, codigoInterno VARCHAR(50), statusGeneralPredespacho VARCHAR(20),
            fechaRetiro DATE, fechaCreacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            codigoNotaEntregaSAP VARCHAR(50), observaciones TEXT',
        'tbl_items_predespacho' => 'idItem INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            idCabeceraPredespacho INT UNSIGNED, idInventarioEntrante INT UNSIGNED,
            cantidadSolicitada DECIMAL(14,3), cantidadDespachada DECIMAL(14,3) DEFAULT 0,
            tipo VARCHAR(30), estatusItemPredespacho VARCHAR(20)',
    ];
    foreach ($tablas as $nombre => $definicion) {
        $motor = $nombre === 'usuarios' ? 'MyISAM' : 'InnoDB';
        $conexion->exec("CREATE TABLE `$nombre` ($definicion) ENGINE=$motor");
    }
    $conexion->exec(file_get_contents(__DIR__ . '/../database/add_control_silos.sql'));
    $conexion->exec('CREATE VIEW v_disponibilidad_lotes AS
        SELECT idInventarioEntrante, idProducto, idPresentacion, NumLote, `idUbicación`,
               sector, CantidadEntrante AS stock_total, 0 AS cantidad_reservada,
               CantidadEntrante AS cantidad_disponible FROM inventarioentrante');
    rechazarAjuste(
        static fn () => (new AjustePrueba($conexion))->obtenerLotes(),
        'Esquema antiguo rechazado antes de devolver lotes incompletos',
        PDOException::class
    );
    $migration = file_get_contents(__DIR__ . '/../database/add_ajustes_lote.sql');
    $conexion->exec($migration);
    $conexion->exec($migration);
    $silosMigration = file_get_contents(__DIR__ . '/../database/add_ajustes_lote_silos.sql');
    $conexion->exec($silosMigration);
    $conexion->exec($silosMigration);
    verificarAjuste(true, 'Script SQL ejecutable e idempotente, incluido cambio de motor de usuarios');
    $conexion->exec(file_get_contents(__DIR__ . '/../database/view_disponibilidad_lotes.sql'));

    $conexion->exec("INSERT INTO Producto VALUES (1, 'P001', 'Producto prueba'), (2, 'P002', 'Otro producto');
        INSERT INTO presentacion VALUES (1, 'KG');
        INSERT INTO ubicacion VALUES (1, 'Deposito');
        INSERT INTO roles VALUES (1, 'Administrador', 1), (2, 'Solo lectura', 1);
        INSERT INTO tbl_cliente VALUES (1, 'Cliente prueba');
        INSERT INTO inventarioentrante (idInventarioEntrante, idProducto, idPresentacion, `idUbicación`, NumLote, sector, CantidadEntrante, fecha)
        VALUES (1,1,1,1,'LOTE-A','Sector1',100,'2026-01-01'), (2,1,1,1,'LOTE-SILO','Sector3',50,'2026-01-02'),
               (3,2,1,1,'LOTE-OTRO','Sector1',10,'2026-01-01'), (4,1,1,1,'LOTE-PENDIENTE','Sector3',10,'2026-01-01');
        INSERT INTO tbl_cabecera_predespacho (idCabeceraPredespacho, idCliente, codigoInterno, statusGeneralPredespacho, fechaRetiro)
        VALUES (1,1,'PRE-TEST','abierto','2026-01-03'), (2,1,'PRE-NUEVO','abierto','2026-01-03');
        INSERT INTO tbl_items_predespacho (idItem,idCabeceraPredespacho,idInventarioEntrante,cantidadSolicitada,estatusItemPredespacho)
        VALUES (1,1,1,30,'pendiente');
        INSERT INTO inventariosaliente (idInventarioEntrante,sector,NE,cantidadSaliente,fecha)
        VALUES (1,'Sector1','PRE-TEST',10,'2026-01-04 09:00:00');
        INSERT INTO silos (idSilo,codigo,nombre,capacidad) VALUES (1,'S-1','Silo uno',80),(2,'S-2','Silo dos',100);
        INSERT INTO silo_asignaciones (idSilo,idInventarioEntrante,cantidadAsignada) VALUES (1,2,50)");
    $lotesListado = (new AjustePrueba($conexion))->obtenerLotes();
    verificarAjuste(count($lotesListado) === 4
        && array_key_exists('ajuste_positivo', $lotesListado[0])
        && array_key_exists('ajuste_negativo', $lotesListado[0]),
        'Listado actualizado incluye ambas columnas de ajustes requeridas por la tabla');
    $statement = $conexion->prepare('INSERT INTO usuarios (id_usuario,nombre_completo,username,password_hash,id_rol,activo) VALUES (?,?,?,?,?,1)');
    $statement->execute([1, 'Responsable prueba', 'ajuste-test', password_hash('Solo-Prueba-2026', PASSWORD_DEFAULT), 1]);
    $statement->execute([2, 'Consulta prueba', 'consulta-test', password_hash('Solo-Prueba-2026', PASSWORD_DEFAULT), 2]);
    foreach ([1 => [1, 1], 2 => [1, 0]] as $rol => $permisos) {
        foreach (['corregir_entradas', 'reporte_lote', 'inteligencia'] as $modulo) {
            $conexion->exec("INSERT INTO permisos_modulo (id_rol,modulo,puede_ver,puede_editar,puede_borrar)
                VALUES ($rol,'$modulo',{$permisos[0]},{$permisos[1]},0)");
        }
    }
    $modelo = new AjustePrueba($conexion);
    $reporte = new ReporteAjustePrueba($conexion);
    $stock = static function (int $id) use ($conexion): array {
        return $conexion->query('SELECT * FROM v_disponibilidad_lotes WHERE idInventarioEntrante=' . $id)->fetch();
    };
    verificarAjuste((float) $stock(1)['cantidad_disponible'] === 70.0 && (float) $stock(1)['cantidad_reservada'] === 20.0, 'Reserva descuenta solo lo pendiente de una entrega parcial');
    $original = $conexion->query('SELECT * FROM inventarioentrante WHERE idInventarioEntrante=1')->fetch();
    $idPositivo = $modelo->registrar(datosAjuste(1, 'positivo', '5.125'), 1);
    $negativo = datosAjuste(1, 'negativo', '2.001');
    $idNegativo = $modelo->registrar($negativo, 1);
    verificarAjuste(abs((float) $stock(1)['cantidad_disponible'] - 73.124) < 0.00001, 'Positivo y negativo afectan disponible con tres decimales');
    verificarAjuste($original === $conexion->query('SELECT * FROM inventarioentrante WHERE idInventarioEntrante=1')->fetch(), 'Entrada original intacta, todos sus campos');
    verificarAjuste((int) $conexion->query('SELECT COUNT(*) FROM inventariosaliente')->fetchColumn() === 1
        && (int) $conexion->query('SELECT COUNT(*) FROM tbl_items_predespacho')->fetchColumn() === 1, 'Ajustes no crean salidas ni reservas duplicadas');
    $registro = $modelo->obtenerHistorial(1)[0];
    verificarAjuste($registro['responsable'] === 'Responsable prueba' && (int) $registro['idUsuario'] === 1
        && substr($registro['fechaCreacion'], 0, 10) === $conexion->query('SELECT CURDATE()')->fetchColumn(), 'Fecha actual y responsable persistidos por servidor');
    rechazarAjuste(fn () => $modelo->registrar(datosAjuste(1, 'negativo', '73.125'), 1), 'Negativo superior al disponible rechazado, reservas protegidas');
    rechazarAjuste(fn () => $modelo->registrar(datosAjuste(1, 'negativo', '90'), 1), 'No permite consumir reservas');
    foreach (['0', '-1', '1.0001', 'NaN', '1e3', '100000000000'] as $monto) {
        rechazarAjuste(fn () => $modelo->registrar(datosAjuste(1, 'positivo', $monto), 1), 'Monto invalido rechazado: ' . $monto, InvalidArgumentException::class);
    }
    rechazarAjuste(fn () => $modelo->registrar(datosAjuste(999, 'positivo', '1'), 1), 'Lote inexistente rechazado');
    rechazarAjuste(fn () => $modelo->registrar(datosAjuste(1, 'positivo', '1'), 999), 'Responsable inexistente rechazado');
    $sinObservacion = datosAjuste(1, 'positivo', '1');
    $sinObservacion['observacion'] = '   ';
    rechazarAjuste(fn () => $modelo->registrar($sinObservacion, 1), 'Observacion obligatoria en servidor', InvalidArgumentException::class);
    rechazarAjuste(fn () => $modelo->registrar($negativo, 1), 'Token duplicado rechazado por base de datos', PDOException::class);
    verificarAjuste(abs((float) $stock(1)['cantidad_disponible'] - 73.124) < 0.00001, 'Fallo y envio duplicado no alteran disponible');
    rechazarAjuste(fn () => $conexion->exec('DELETE FROM inventarioentrante WHERE idInventarioEntrante=1'), 'FK conserva historial del lote', PDOException::class);

    $modelo->registrar(datosAjuste(2, 'positivo', '5', 1), 1);
    $modelo->registrar(datosAjuste(2, 'negativo', '3', 1), 1);
    verificarAjuste((float) $stock(2)['cantidad_disponible'] === 52.0
        && (float) $conexion->query('SELECT cantidadOcupada FROM v_estado_silos WHERE idSilo=1')->fetchColumn() === 52.0, 'Sector3: ajustes sincronizan lote y ocupacion del silo');
    rechazarAjuste(fn () => $modelo->registrar(datosAjuste(2, 'positivo', '29', 1), 1), 'Positivo no supera capacidad del silo');
    rechazarAjuste(fn () => $modelo->registrar(datosAjuste(2, 'negativo', '1', 2), 1), 'Negativo solo consume el lote del silo elegido');
    rechazarAjuste(fn () => $modelo->registrar(datosAjuste(2, 'positivo', '1'), 1), 'Sector3 exige silo');
    rechazarAjuste(fn () => $modelo->registrar(datosAjuste(4, 'positivo', '1', 2), 1), 'Lote pendiente de distribucion no se ajusta');
    verificarAjuste((float) $stock(2)['cantidad_disponible'] === 52.0, 'Fallo de silo revierte toda la transaccion');
    $modelo->registrar(datosAjuste(2, 'positivo', '1.001', 2), 1);
    verificarAjuste((float) $conexion->query('SELECT cantidadOcupada FROM v_estado_silos WHERE idSilo=2')->fetchColumn() === 1.001, 'Positivo permite un nuevo silo compatible');
    $conexion->exec("INSERT INTO silo_asignaciones (idSilo,idInventarioEntrante,cantidadAsignada) VALUES (2,3,2)");
    rechazarAjuste(fn () => $modelo->registrar(datosAjuste(2, 'positivo', '1', 2), 1), 'No mezcla otro producto en un silo');
    $conexion->exec('DELETE FROM silo_asignaciones WHERE idInventarioEntrante=3');
    $modelo->registrar(datosAjuste(2, 'negativo', '1.001', 2), 1);
    verificarAjuste((float) $conexion->query('SELECT cantidadOcupada FROM v_estado_silos WHERE idSilo=2')->fetchColumn() === 0.0, 'Negativo libera totalmente el silo');

    $saldos = $reporte->obtenerSaldosPorLote([1]);
    $saldoA = array_values(array_filter($saldos, static fn ($fila) => (int) $fila['idInventarioEntrante'] === 1))[0];
    verificarAjuste((float) $saldoA['stock_total'] === 100.0 && (float) $saldoA['cantidad_saliente'] === 10.0
        && (float) $saldoA['ajuste_positivo'] === 5.125 && (float) $saldoA['ajuste_negativo'] === 2.001, 'Reporte de saldos distingue entrada original, salidas reales y ajustes');
    verificarAjuste(abs((float) $saldoA['saldo_fisico'] - 93.124) < 0.00001
        && abs((float) $saldoA['cantidad_disponible'] - 73.124) < 0.00001, 'Reporte de saldos: fisico y disponible coherentes');
    $excel = leerExportacionAjuste($esquema, 'saldos');
    $hoja = $excel->getActiveSheet();
    $filaSaldo = null;
    foreach ($hoja->toArray(null, true, true, true) as $fila) {
        if ($fila['C'] === 'LOTE-A') {
            $filaSaldo = $fila;
            break;
        }
    }
    verificarAjuste($filaSaldo !== null && (float) $filaSaldo['H'] === 100.0
        && (float) $filaSaldo['I'] === 5.125 && (float) $filaSaldo['J'] === 2.001
        && (float) $filaSaldo['K'] === 10.0 && abs((float) $filaSaldo['N'] - 73.124) < 0.00001,
        'Excel de saldos contiene columnas originales, ajustes y disponible correctos');
    $excel->disconnectWorksheets();
    $excel = leerExportacionAjuste($esquema, 'movimientos');
    $hoja = $excel->getActiveSheet();
    $positivaExcel = false;
    $negativaExcel = false;
    foreach ($hoja->toArray(null, true, true, true) as $numero => $fila) {
        if (strpos((string) $fila['G'], 'Ajuste de lote por sistema #' . $idPositivo . ' ') === 0) {
            $positivaExcel = (float) $fila['D'] === 5.125 && $fila['E'] === null
                && $hoja->getStyle('D' . $numero)->getFill()->getStartColor()->getARGB() === 'FFF1E9FB';
        }
        if (strpos((string) $fila['G'], 'Ajuste de lote por sistema #' . $idNegativo . ' ') === 0) {
            $negativaExcel = (float) $fila['E'] === 2.001 && $fila['D'] === null;
        }
    }
    verificarAjuste($positivaExcel && $negativaExcel, 'Excel de movimientos conserva columnas, tres decimales y color pastel');
    $excel->disconnectWorksheets();
    verificarAjuste(count($reporte->obtenerSaldosPorLotePaginados([1], 1, 0)) === 1
        && (int) $reporte->obtenerResumenSaldosPorLote([1])['total_lotes'] === 3, 'Filtros, paginacion y resumen incluyen lotes ajustados');
    $movimientos = $reporte->obtenerMovimientosPorProducto(1);
    $movimientosA = array_values(array_filter($movimientos, static fn ($fila) => (int) $fila['idInventarioEntrante'] === 1));
    $filasAjuste = array_values(array_filter($movimientosA, static fn ($fila) => $fila['tipo'] === 'ajuste'));
    verificarAjuste(count($filasAjuste) === 2 && (float) $filasAjuste[0]['entrada'] === 5.125
        && $filasAjuste[0]['salida'] === '' && (float) $filasAjuste[1]['salida'] === 2.001
        && $filasAjuste[1]['entrada'] === '', 'Reporte movimientos coloca positivos en Entrada y negativos en Salida');
    verificarAjuste(abs((float) end($movimientosA)['saldo'] - 93.124) < 0.00001, 'Saldo de movimientos incluye ambos ajustes');
    $cronologia = $reporte->construirMovimientosProducto(
        [['idInventarioEntrante' => 1, 'NumLote' => 'A', 'CantidadEntrante' => 100, 'fecha' => '2026-01-01']],
        [['idInventarioEntrante' => 1, 'codigoInterno' => 'PRE', 'cantidadSolicitada' => 10, 'fechaRetiro' => '2026-01-05']],
        [['idInventarioEntrante' => 1, 'idInventarioSaliente' => 1, 'NE' => 'PRE', 'cantidadSaliente' => 10, 'fecha' => '2026-01-07']],
        [['idInventarioEntrante' => 1, 'idAjuste' => 1, 'tipo' => 'positivo', 'monto' => 2,
            'fechaCreacion' => '2026-01-06 12:00:00', 'responsable' => 'Prueba', 'observacion' => 'Conteo']]
    );
    verificarAjuste(array_column($cronologia, 'tipo') === ['entrada', 'predespacho', 'ajuste', 'salida', 'saldo']
        && (float) $cronologia[2]['saldo'] === 102.0 && (float) $cronologia[3]['saldo'] === 92.0, 'Ajuste entre predespacho y salida conserva cronologia y saldo historico');
    $analitica = new AnaliticaAjustePrueba($conexion);
    $hoy = $conexion->query('SELECT CURDATE()')->fetchColumn();
    $movimientosHoy = $analitica->obtenerMovimientos(1, $hoy, $hoy);
    verificarAjuste(count($movimientosHoy) === 6 && abs(array_sum(array_column($movimientosHoy, 'entrada')) - 11.126) < 0.00001
        && abs(array_sum(array_column($movimientosHoy, 'salida')) - 6.002) < 0.00001, 'Inteligencia filtra ajustes por fecha real y producto');
    $lotesAnalitica = $analitica->obtenerResumenLotes(1);
    verificarAjuste(abs((float) $lotesAnalitica[0]['disponible'] - 73.124) < 0.00001, 'Inteligencia usa disponible con ajustes y reservas');
    verificarAjuste($analitica->obtenerMovimientos(2, $hoy, $hoy) === [], 'Filtro de otro producto no incluye ajustes ajenos');
    $salidas = new SalidaAjustePrueba($conexion);
    verificarAjuste(abs((float) $salidas->obtenerLoteParaProducto(1, 1)['Disponible'] - 73.124) < 0.00001, 'Seleccion de salida usa el disponible ajustado');
    verificarAjuste(count($salidas->obtenerLotesPorProducto(1)) === 3
        && count($salidas->obtenerLotesParaCorreccion()) === 4 && count($salidas->obtenerSalidas()) === 1,
        'Consultas de salida y correcciones conservan filas sin duplicar ajustes');
    verificarAjuste(abs($salidas->obtenerDisponibleParaCorreccion(1, 1) - 103.124) < 0.00001,
        'Correccion calcula saldo incluyendo ajustes y excluyendo su propia salida');
    rechazarAjuste(fn () => $salidas->registrarSalida(1, 'Sector1', 'EXCESO', 74), 'Salida directa no ignora ajustes ni reservas');
    $silosModelo = new SiloAjustePrueba($conexion);
    rechazarAjuste(fn () => $silosModelo->eliminar(2), 'Silo vacio con historial de ajustes no se elimina');
    verificarAjuste(count($silosModelo->entradasPendientes()) === 1, 'Control de silos no considera los ajustes distribuidos como pendientes');
    $predespacho = new PredespachoAjustePrueba($conexion);
    $resultado = $predespacho->agregarItemPredespacho(2, 1, 73.125);
    verificarAjuste(!$resultado['success'] && strpos($resultado['mensaje'], 'Cantidad excede el disponible') !== false,
        'Predespacho no reserva por encima del saldo ajustado');
    $resultado = $predespacho->agregarItemPredespacho(2, 1, 1);
    verificarAjuste($resultado['success'] && abs((float) $stock(1)['cantidad_disponible'] - 72.124) < 0.00001,
        'Predespacho puede reservar el saldo ajustado y actualiza disponibilidad');
    $conexion->exec('DELETE FROM tbl_items_predespacho WHERE idCabeceraPredespacho=2');
    $modelo->registrar(datosAjuste(1, 'negativo', '73.124'), 1);
    verificarAjuste((float) $stock(1)['cantidad_disponible'] === 0.0
        && (float) $stock(1)['cantidad_reservada'] === 20.0, 'Negativo exacto deja disponible cero sin perder reservas');
    $modelo->registrar(datosAjuste(1, 'positivo', '73.124'), 1);
    verificarAjuste(abs((float) $stock(1)['cantidad_disponible'] - 73.124) < 0.00001, 'Ajuste compensatorio restaura saldo conservando historial');
    $segunda = new PDO('mysql:host=' . DB_HOST . ';dbname=' . $esquema . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $segunda->beginTransaction();
    $segunda->query('SELECT idInventarioEntrante FROM inventarioentrante WHERE idInventarioEntrante=1 FOR UPDATE')->fetch();
    $conexion->exec('SET SESSION innodb_lock_wait_timeout=1');
    try {
        rechazarAjuste(fn () => $modelo->registrar(datosAjuste(1, 'negativo', '1'), 1),
            'Ajuste concurrente respeta bloqueo del lote', PDOException::class);
    } finally {
        $segunda->rollBack();
    }
    verificarAjuste(!$conexion->inTransaction() && abs((float) $stock(1)['cantidad_disponible'] - 73.124) < 0.00001,
        'Conflicto concurrente revierte sin movimiento parcial');
    $entradas = new EntradaAjustePrueba($conexion);
    $detalle = array_values(array_filter($entradas->obtenerEntradas(), static fn ($fila) => (int) $fila['idInventarioEntrante'] === 1))[0];
    verificarAjuste(abs((float) $detalle['disponible'] - 73.124) < 0.00001
        && abs((float) $detalle['ajusteNeto'] - 3.124) < 0.00001 && (float) $detalle['reservado'] === 20.0,
        'Correccion de entradas informa saldo, reservas y ajustes para el modal');
    $dataEntrada = [
        'NumLote' => 'LOTE-A', 'idProducto' => 1, 'idPresentacion' => 1, 'idUbicacion' => 1,
        'Sector' => 'Sector1', 'CantidadEntrante' => 99, 'idTipoCompra' => null, 'CardCode' => null,
        'FabricanteCode' => null, 'PaisCode' => null, 'fecha_factura' => null, 'peso_romana' => null,
        'nro_factura' => null, 'observaciones' => 'Correccion de prueba',
    ];
    rechazarAjuste(fn () => $entradas->actualizarEntrada(1, $dataEntrada), 'No corrige cantidad original de un lote ajustado');
    $dataEntrada['CantidadEntrante'] = 100;
    $dataEntrada['idProducto'] = 2;
    rechazarAjuste(fn () => $entradas->actualizarEntrada(1, $dataEntrada), 'No cambia producto de un lote ajustado');
    $dataEntrada['idProducto'] = 1;
    verificarAjuste($entradas->actualizarEntrada(1, $dataEntrada), 'Permite corregir metadatos sin borrar el efecto del ajuste');
    $dataEntrada['NumLote'] = 'LOTE-SILO';
    $dataEntrada['Sector'] = 'Sector3';
    $dataEntrada['CantidadEntrante'] = 50;
    verificarAjuste($entradas->actualizarEntrada(2, $dataEntrada, [['idSilo' => 1, 'cantidad' => 52]]),
        'Correccion de Sector3 mantiene distribucion incluyendo ajustes');
    verificarAjuste((float) $stock(2)['cantidad_disponible'] === 52.0, 'Correccion no pierde saldo ajustado del silo');
    verificarAjuste(count($modelo->obtenerSilosParaAjuste(2, 'positivo')) === 2,
        'Positivo lista silos vacios y del mismo producto con capacidad');
    verificarAjuste(array_column($modelo->obtenerSilosParaAjuste(2, 'negativo'), 'idSilo') === [1],
        'Negativo lista solo silos con saldo del lote elegido');
    $conexion->exec('INSERT INTO silo_asignaciones (idSilo,idInventarioEntrante,cantidadAsignada) VALUES (2,3,2)');
    verificarAjuste(count($modelo->obtenerSilosParaAjuste(2, 'positivo')) === 1,
        'Positivo excluye silos de otro producto');
    verificarAjuste(count($modelo->obtenerSilosParaAjuste(2, 'negativo')) === 1,
        'Negativo no ofrece inventario de otros lotes');
    $conexion->exec('DELETE FROM silo_asignaciones WHERE idInventarioEntrante=3');
    $multi = datosAjuste(2, 'positivo', '10.001');
    $multi['asignacionesSilo'] = [['idSilo' => 1, 'cantidad' => '5'], ['idSilo' => 2, 'cantidad' => '5.001']];
    $idMulti = $modelo->registrar($multi, 1);
    verificarAjuste((float) $stock(2)['cantidad_disponible'] === 62.001
        && (int) $conexion->query("SELECT COUNT(*) FROM ajustes_lote_silos WHERE idAjuste=$idMulti")->fetchColumn() === 2,
        'Positivo multisilo crea un solo ajuste con dos detalles y monto correcto');
    verificarAjuste(str_contains($modelo->obtenerHistorial(2)[0]['silo'], 'S-1: 5.000')
        && str_contains($modelo->obtenerHistorial(2)[0]['silo'], 'S-2: 5.001'),
        'Historial conserva cantidad y codigo de cada silo');
    $mal = datosAjuste(2, 'negativo', '10.001');
    $mal['asignacionesSilo'] = [['idSilo' => 1, 'cantidad' => '1'], ['idSilo' => 2, 'cantidad' => '9.001']];
    rechazarAjuste(fn () => $modelo->registrar($mal, 1), 'Negativo valida saldo de cada silo aunque el total del lote alcance');
    $mal['asignacionesSilo'] = [['idSilo' => 1, 'cantidad' => '1'], ['idSilo' => 2, 'cantidad' => '1']];
    rechazarAjuste(fn () => $modelo->registrar($mal, 1), 'Distribucion incompleta no se registra');
    $mal['asignacionesSilo'] = [['idSilo' => 1, 'cantidad' => '5'], ['idSilo' => 1, 'cantidad' => '5.001']];
    rechazarAjuste(fn () => $modelo->registrar($mal, 1), 'No permite repetir un silo');
    verificarAjuste((float) $stock(2)['cantidad_disponible'] === 62.001,
        'Fallos multisilo revierten todo el movimiento');
    $multi = datosAjuste(2, 'negativo', '10.001');
    $multi['asignacionesSilo'] = [['idSilo' => 1, 'cantidad' => '5'], ['idSilo' => 2, 'cantidad' => '5.001']];
    $idNegativo = $modelo->registrar($multi, 1);
    verificarAjuste((float) $stock(2)['cantidad_disponible'] === 52.0
        && (float) $conexion->query('SELECT cantidadOcupada FROM v_estado_silos WHERE idSilo=2')->fetchColumn() === 0.0,
        'Negativo mayor al saldo de un silo se reparte entre varios y libera ocupacion');
    $movimientosMulti = array_values(array_filter($reporte->obtenerMovimientosPorProducto(1),
        static fn ($fila) => (int) $fila['idInventarioEntrante'] === 2 && $fila['tipo'] === 'ajuste'));
    verificarAjuste(count($movimientosMulti) === 6
        && abs(array_sum(array_map('floatval', array_column($movimientosMulti, 'entrada'))) - 16.002) < 0.00001
        && abs(array_sum(array_map('floatval', array_column($movimientosMulti, 'salida'))) - 14.002) < 0.00001,
        'Reportes no duplican movimientos por cada detalle de silo');
    $conexion->exec('UPDATE silos SET capacidad=52 WHERE idSilo=1');
    verificarAjuste(array_column($modelo->obtenerSilosParaAjuste(2, 'positivo'), 'idSilo') === [2],
        'Positivo excluye silo lleno aunque sea del mismo producto');
    $conexion->exec('UPDATE silos SET capacidad=80 WHERE idSilo=1');
    rechazarAjuste(fn () => $silosModelo->eliminar(2), 'No elimina silo vacio con historial multisilo');
    $conexion->exec('DELETE FROM ajustes_lote_silos WHERE idAjuste IN (SELECT idAjuste FROM ajustes_lote WHERE idSilo IS NOT NULL)');
    $conexion->exec($silosMigration);
    $conexion->exec($silosMigration);
    verificarAjuste((int) $conexion->query('SELECT COUNT(*) FROM ajustes_lote a LEFT JOIN ajustes_lote_silos d ON d.idAjuste=a.idAjuste WHERE a.idSilo IS NOT NULL AND d.idAjuste IS NULL')->fetchColumn() === 0,
        'Migracion conserva ajustes anteriores de un silo y no duplica detalles');
    $exitoso = true;
    echo "PASS: $pruebas verificaciones. Base aislada: $esquema\n";
} finally {
    if ($creado && (!$mantener || !$exitoso)) {
        $conexion->exec("DROP DATABASE `$esquema`");
    } elseif ($creado) {
        echo "BASE_PRUEBAS=$esquema\n";
    }
}
