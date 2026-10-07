<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/Core/Database.php';

function comprobarMigracion(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
    echo "OK: $mensaje\n";
}

function ejecutarMigracion(PDO $db): void
{
    $sql = file_get_contents(__DIR__ . '/../database/repair_cotizacion_id_cliente.sql');
    if ($sql === false) {
        throw new RuntimeException('No se pudo leer el archivo de migracion.');
    }
    $inicio = strpos($sql, 'CREATE PROCEDURE');
    $fin = strpos($sql, 'END$$');
    if ($inicio === false || $fin === false) {
        throw new RuntimeException('Formato de migracion inesperado.');
    }
    $db->exec(substr($sql, $inicio, $fin + 3 - $inicio));
    try {
        $statement = $db->query('CALL reparar_cotizacion_id_cliente_prd_v1()');
        do {
            $statement->fetchAll();
        } while ($statement->nextRowset());
        $statement->closeCursor();
    } finally {
        $db->exec('DROP PROCEDURE reparar_cotizacion_id_cliente_prd_v1');
    }
}

$db = (new Database())->getConnection();
$esquemaPrueba = 'cotizacion_migration_test_' . bin2hex(random_bytes(8));
$creado = false;
try {
    $db->exec("CREATE DATABASE `$esquemaPrueba` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $creado = true;
    $db->exec("USE `$esquemaPrueba`");
    $db->exec('CREATE TABLE tbl_cliente (idCliente INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
    $db->exec("INSERT INTO tbl_cliente VALUES (0), (12)");
    $db->exec('CREATE TABLE tbl_clientes_cotizacion (CardCode VARCHAR(15) PRIMARY KEY) ENGINE=InnoDB');
    $db->exec("INSERT INTO tbl_clientes_cotizacion VALUES ('COT00001'), ('COT00002')");
    $db->exec('CREATE TABLE tbl_cotizacion_cabecera (
        idCotizacion INT PRIMARY KEY, idCliente INT UNSIGNED NOT NULL,
        CONSTRAINT nombre_fk_real_prd FOREIGN KEY (idCliente) REFERENCES tbl_cliente(idCliente)
    ) ENGINE=InnoDB');
    $db->exec('INSERT INTO tbl_cotizacion_cabecera VALUES (1, 0), (2, 12)');
    ejecutarMigracion($db);
    $columna = $db->query("SHOW COLUMNS FROM tbl_cotizacion_cabecera LIKE 'idCliente'")->fetch();
    comprobarMigracion($columna['Type'] === 'varchar(15)', 'Archivo SQL convierte INT UNSIGNED a VARCHAR(15)');
    comprobarMigracion($db->query('SELECT idCliente FROM tbl_cotizacion_cabecera ORDER BY idCotizacion')->fetchAll(PDO::FETCH_COLUMN) === ['0', '12'],
        'No reasigna ni elimina clientes historicos');
    $fkCount = static fn (): int => (int) $db->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_cotizacion_cabecera' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchColumn();
    comprobarMigracion($fkCount() === 0, 'Quita FK numerica por su nombre real y reporta historicos pendientes');
    $db->exec("INSERT INTO tbl_cotizacion_cabecera VALUES (3, 'COT00001')");
    comprobarMigracion($db->query('SELECT idCliente FROM tbl_cotizacion_cabecera WHERE idCotizacion = 3')->fetchColumn() === 'COT00001',
        'PRD corregido guarda el codigo alfanumerico completo');
    ejecutarMigracion($db);
    comprobarMigracion($fkCount() === 0, 'Se puede repetir la migracion con historicos pendientes');
    // Reconciliacion controlada de fixtures, no de datos reales.
    $db->exec("UPDATE tbl_cotizacion_cabecera SET idCliente = 'COT00001' WHERE idCotizacion = 1");
    $db->exec("UPDATE tbl_cotizacion_cabecera SET idCliente = 'COT00002' WHERE idCotizacion = 2");
    ejecutarMigracion($db);
    comprobarMigracion($fkCount() === 1, 'Crea relacion correcta cuando los historicos estan reconciliados');
    ejecutarMigracion($db);
    comprobarMigracion($fkCount() === 1, 'Migracion repetible con FK correcta existente');
    comprobarMigracion((int) $db->query('SELECT COUNT(*) FROM tbl_cotizacion_cabecera')->fetchColumn() === 3,
        'Preserva todas las cotizaciones');
    echo "Migracion validada en esquema aislado; no se modifico la BD de negocio.\n";
} finally {
    if ($creado) {
        $db->exec("DROP DATABASE `$esquemaPrueba`");
    }
}
