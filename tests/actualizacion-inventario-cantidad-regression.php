<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/ReinicioInventarioService.php';

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function verificarCantidad(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
    echo "OK: $mensaje\n";
}

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE Producto (idProducto INTEGER, codigoInterno TEXT, nombre TEXT)');
$db->exec('CREATE TABLE ubicacion (idUbicacion INTEGER, nombre TEXT)');
$db->exec('CREATE TABLE presentacion (idPresentacion INTEGER, nombre TEXT)');
$db->exec('CREATE TABLE silos (idSilo INTEGER, codigo TEXT, nombre TEXT, capacidad REAL, activo INTEGER)');
$db->exec("INSERT INTO Producto VALUES (1, 'P001', 'Producto prueba')");
$db->exec("INSERT INTO ubicacion VALUES (1, 'Almacen')");
$db->exec("INSERT INTO presentacion VALUES (1, 'GRANEL (KG)')");
$db->exec("INSERT INTO silos VALUES (1, '015', 'Tanque', 100000, 1)");

$libro = new Spreadsheet();
$hoja = $libro->getActiveSheet();
$hoja->fromArray([['Número de artículo', 'Descripción del artículo', 'Lote', 'Almacén', 'Nombre del almacén', 'Cantidad', 'ZONA', 'PRESENTACION', 'PESO', 'TANQUE']]);
$valores = [
    ['30.069,50', DataType::TYPE_STRING],
    [30069.5, DataType::TYPE_NUMERIC],
    ['1.234', DataType::TYPE_STRING],
    ['12,345', DataType::TYPE_STRING],
    ['=30000+69.5', DataType::TYPE_FORMULA],
    ['12,34kg', DataType::TYPE_STRING],
    ['1.23,45', DataType::TYPE_STRING],
    ['1,2345', DataType::TYPE_STRING],
    ['0', DataType::TYPE_STRING],
    [1.2345, DataType::TYPE_NUMERIC],
    ['100.000.000.000', DataType::TYPE_STRING],
];
foreach ($valores as $indice => [$valor, $tipo]) {
    $fila = $indice + 2;
    $hoja->fromArray([['P001', 'Producto prueba', 'L' . $fila, 1, 'Almacen', null, 3, 'GRANEL', 0, '015']], null, 'A' . $fila);
    $hoja->setCellValueExplicit('F' . $fila, $valor, $tipo);
}
$hoja->getStyle('F3')->getNumberFormat()->setFormatCode('#,##0.00');

$ruta = tempnam(sys_get_temp_dir(), 'inv_excel_');
if ($ruta === false) {
    throw new RuntimeException('No se pudo crear el archivo temporal de prueba.');
}
try {
    (new Xlsx($libro))->save($ruta);
    $servicio = new ReinicioInventarioService($db);
    $resultado = $servicio->procesarExcel($ruta, 1, 'prueba');
    $cantidades = array_column($resultado['registros'], 'cantidad');

    verificarCantidad($cantidades === [30069.5, 30069.5, 1234.0, 12.345, 30069.5], 'Texto venezolano, celda numérica y fórmula conservan sus cantidades');
    verificarCantidad(abs($resultado['resumen']['por_tanque']['015'] - 91454.845) < 0.00001, 'El resumen del tanque usa las cantidades corregidas');
    verificarCantidad(!$resultado['exito'] && count($resultado['resumen']['errores']) === 6, 'Cantidades inválidas bloquean la carga');
    verificarCantidad(str_contains($resultado['resumen']['errores'][0], 'Fila #7'), 'El error identifica la fila inválida');

    try {
        $servicio->ejecutarVaciadoYCarga(['registros' => [['cantidad' => 30.0695]]], 1, 'prueba');
        throw new RuntimeException('Se aceptó una carga validada con la versión anterior.');
    } catch (DomainException $exception) {
        verificarCantidad(str_contains($exception->getMessage(), 'Vuelva a subir'), 'Se rechaza una carga pendiente validada con la versión anterior');
    }
} finally {
    $libro->disconnectWorksheets();
    unlink($ruta);
}
