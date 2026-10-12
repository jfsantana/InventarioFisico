<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Models/BaseModel.php';
require_once __DIR__ . '/../app/Models/EntradaInventario.php';

class EntradaPresentacionPrueba extends EntradaInventario
{
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
}

function verificarPresentacion(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
    echo "OK: $mensaje\n";
}

function rechazarPresentacion(EntradaPresentacionPrueba $modelo, array $datos, string $clase): void
{
    try {
        $modelo->crearPresentacion($datos);
    } catch (InvalidArgumentException | DomainException $exception) {
        verificarPresentacion($exception instanceof $clase, 'Rechazo esperado: ' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('Se acepto una presentacion invalida o duplicada.');
}

$db = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

if (($argv[1] ?? '') === '--crear') {
    $base = $argv[2] ?? '';
    if (!preg_match('/^inv_presentacion_test_[a-f0-9]{12}$/D', $base)) {
        throw new RuntimeException('Solo se admiten bases aisladas de prueba.');
    }
    $db->exec("USE `$base`");
    try {
        (new EntradaPresentacionPrueba($db))->crearPresentacion(['nombre' => 'Concurrente']);
        echo 'creada';
    } catch (DomainException $exception) {
        echo 'duplicada';
    }
    exit;
}

$base = 'inv_presentacion_test_' . bin2hex(random_bytes(6));
$db->exec("CREATE DATABASE `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
try {
    $db->exec("USE `$base`");
    $db->exec('CREATE TABLE presentacion (
        idPresentacion INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(50) NOT NULL
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci');
    $modelo = new EntradaPresentacionPrueba($db);
    foreach ([[], ['nombre' => '   '], ['nombre' => []], ['nombre' => 123], ['nombre' => str_repeat('a', 51)]] as $datos) {
        rechazarPresentacion($modelo, $datos, InvalidArgumentException::class);
    }

    $creada = $modelo->crearPresentacion(['nombre' => '  Saco  ']);
    verificarPresentacion($creada['nombre'] === 'Saco' && $creada['idPresentacion'] > 0, 'Alta con ID y nombre sin espacios extremos.');
    verificarPresentacion($modelo->obtenerPresentaciones() === [$creada], 'La presentacion se persiste y aparece en el catalogo.');
    foreach (['Saco', 'saco', '  SACO  ', 'Sáco'] as $nombre) {
        rechazarPresentacion($modelo, ['nombre' => $nombre], DomainException::class);
    }
    $db->exec("INSERT INTO presentacion (nombre) VALUES ('  Caja  ')");
    rechazarPresentacion($modelo, ['nombre' => 'caja'], DomainException::class);
    $limite = $modelo->crearPresentacion(['nombre' => str_repeat('á', 50)]);
    verificarPresentacion(mb_strlen($limite['nombre'], 'UTF-8') === 50, 'Se aceptan 50 caracteres Unicode.');

    $procesos = [];
    for ($i = 0; $i < 4; $i++) {
        $comando = [PHP_BINARY, '-n', '-d', 'extension_dir=' . ini_get('extension_dir'),
            '-d', 'extension=pdo_mysql', '-d', 'extension=mbstring', __FILE__, '--crear', $base];
        $proceso = proc_open($comando, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proceso)) {
            throw new RuntimeException('No se pudo iniciar la prueba concurrente.');
        }
        fclose($pipes[0]);
        $procesos[] = [$proceso, $pipes];
    }
    $resultados = [];
    foreach ($procesos as [$proceso, $pipes]) {
        $resultados[] = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        verificarPresentacion(proc_close($proceso) === 0, 'Solicitud concurrente sin error: ' . $error);
    }
    verificarPresentacion(count(array_filter($resultados, static fn (string $resultado): bool => $resultado === 'creada')) === 1,
        'Solo una solicitud concurrente crea la presentacion.');
    verificarPresentacion(count(array_filter($resultados, static fn (string $resultado): bool => $resultado === 'duplicada')) === 3,
        'Las otras solicitudes concurrentes rechazan el duplicado.');
    verificarPresentacion((int) $db->query("SELECT COUNT(*) FROM presentacion WHERE nombre = 'Concurrente'")->fetchColumn() === 1,
        'No hay duplicados en la tabla MyISAM.');
} finally {
    $db->exec("DROP DATABASE `$base`");
}
