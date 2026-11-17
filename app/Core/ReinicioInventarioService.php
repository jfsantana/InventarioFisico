<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ReinicioInventarioService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
    }

    /**
     * Genera un respaldo SQL completo de la base de datos en storage/backups/
     */
    public function generarBackup(string $prefijo = 'backup_previo_reinicio'): array
    {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio de backups: ' . $dir);
        }

        $nombreArchivo = $prefijo . '_' . date('Ymd_His') . '.sql';
        $rutaDestino = $dir . DIRECTORY_SEPARATOR . $nombreArchivo;

        $mysqldumpPath = $this->buscarMysqldump();
        $generado = false;

        if ($mysqldumpPath !== null) {
            $cmd = sprintf(
                '"%s" -h %s -u %s %s %s --routines --triggers --result-file="%s" 2>&1',
                $mysqldumpPath,
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_USER),
                DB_PASS !== '' ? '-p' . escapeshellarg(DB_PASS) : '',
                escapeshellarg(DB_NAME),
                $rutaDestino
            );

            @exec($cmd, $output, $returnCode);
            if ($returnCode === 0 && file_exists($rutaDestino) && filesize($rutaDestino) > 0) {
                $generado = true;
            }
        }

        // Respaldo de contingencia nativo en PHP si mysqldump no estuviese disponible
        if (!$generado) {
            $this->generarBackupPhp($rutaDestino);
        }

        return [
            'exito' => true,
            'archivo' => $nombreArchivo,
            'ruta' => $rutaDestino,
            'tamano' => filesize($rutaDestino),
            'tamano_formateado' => $this->formatearBytes((int) filesize($rutaDestino)),
            'fecha' => date('d/m/Y H:i:s'),
        ];
    }

    private function buscarMysqldump(): ?string
    {
        $posibles = [
            'C:\\wamp64\\bin\\mysql\\mysql9.1.0\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.0.27\\bin\\mysqldump.exe',
        ];

        foreach ($posibles as $ruta) {
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        // Búsqueda dinámica en carpetas de mysql de wamp
        $mysqlDir = 'C:\\wamp64\\bin\\mysql';
        if (is_dir($mysqlDir)) {
            $carpetas = glob($mysqlDir . DIRECTORY_SEPARATOR . 'mysql*');
            if (is_array($carpetas)) {
                rsort($carpetas);
                foreach ($carpetas as $carpeta) {
                    $candidato = $carpeta . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysqldump.exe';
                    if (is_file($candidato)) {
                        return $candidato;
                    }
                }
            }
        }

        return null;
    }

    private function generarBackupPhp(string $rutaDestino): void
    {
        $fp = fopen($rutaDestino, 'wb');
        if (!$fp) {
            throw new RuntimeException('No se pudo abrir el archivo para escribir el respaldo: ' . $rutaDestino);
        }

        fwrite($fp, "-- Backup generado por Sistema InventarioFisico (Adyarca)\n");
        fwrite($fp, "-- Fecha: " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

        $tablas = $this->db->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tablas as $tabla) {
            fwrite($fp, "-- Estructura y datos de `{$tabla}`\n");
            $createStmt = $this->db->query("SHOW CREATE TABLE `{$tabla}`")->fetch(PDO::FETCH_ASSOC);
            $createSql = $createStmt['Create Table'] ?? '';
            fwrite($fp, "DROP TABLE IF EXISTS `{$tabla}`;\n");
            fwrite($fp, $createSql . ";\n\n");

            $rowsStmt = $this->db->query("SELECT * FROM `{$tabla}`");
            $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $columnas = array_map(static fn($c) => "`{$c}`", array_keys($rows[0]));
                $colStr = implode(', ', $columnas);

                fwrite($fp, "INSERT INTO `{$tabla}` ({$colStr}) VALUES\n");
                $totalRows = count($rows);
                foreach ($rows as $i => $row) {
                    $vals = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = $this->db->quote((string) $val);
                        }
                    }
                    $linea = '(' . implode(', ', $vals) . ')' . ($i === $totalRows - 1 ? ";\n\n" : ",\n");
                    fwrite($fp, $linea);
                }
            }
        }

        fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($fp);
    }

    /**
     * Procesa y valida el archivo Excel cargado.
     */
    public function procesarExcel(string $rutaArchivo, int $idUsuario, string $username): array
    {
        if (!file_exists($rutaArchivo)) {
            throw new InvalidArgumentException('El archivo Excel no existe o no se pudo cargar.');
        }

        $spreadsheet = IOFactory::load($rutaArchivo);
        $hoja = $spreadsheet->getActiveSheet();
        $filas = $hoja->toArray(null, true, true, true);

        if (empty($filas)) {
            throw new DomainException('El archivo Excel se encuentra vacío.');
        }

        // 1. Detectar fila de encabezados
        $indiceEncabezado = null;
        $mapaColumnas = [];

        foreach ($filas as $numFila => $fila) {
            $filaNormalizada = array_map(static fn($val) => mb_strtolower(trim((string) $val), 'UTF-8'), $fila);
            if ($this->esFilaEncabezado($filaNormalizada)) {
                $indiceEncabezado = $numFila;
                $mapaColumnas = $this->mapearColumnas($filaNormalizada);
                break;
            }
        }

        if ($indiceEncabezado === null) {
            throw new DomainException(
                'No se encontraron las columnas requeridas en el archivo Excel. Verifique que existan: ' .
                '"Número de artículo", "Descripción del artículo", "Lote", "Almacén", "Cantidad", "ZONA", "PRESENTACION", "PESO", "TANQUE".'
            );
        }

        // Cargar maestros existentes para cotejar
        $productosExistentes = $this->obtenerMapaProductos();
        $ubicacionesExistentes = $this->obtenerMapaUbicaciones();
        $presentacionesExistentes = $this->obtenerMapaPresentaciones();
        $silosExistentes = $this->obtenerMapaSilos();

        $registrosValidos = [];
        $advertencias = [];
        $errores = [];

        $nuevosProductos = [];
        $nuevasUbicaciones = [];
        $nuevasPresentaciones = [];

        $totalKilos = 0.0;
        $conteoPorSector = ['Sector1' => 0, 'Sector2' => 0, 'Sector3' => 0];
        $conteoPorTanque = [];

        // 2. Iterar filas de datos
        foreach ($filas as $numFila => $fila) {
            if ($numFila <= $indiceEncabezado) {
                continue;
            }

            $numArticulo = trim((string) ($fila[$mapaColumnas['articulo']] ?? ''));
            $descArticulo = trim((string) ($fila[$mapaColumnas['descripcion']] ?? ''));
            $lote = trim((string) ($fila[$mapaColumnas['lote']] ?? ''));
            $almacenRaw = trim((string) ($fila[$mapaColumnas['almacen']] ?? ''));
            $nombreAlmacen = trim((string) ($fila[$mapaColumnas['nombre_almacen']] ?? ''));
            $cantidadRaw = trim((string) ($fila[$mapaColumnas['cantidad']] ?? ''));
            $zonaRaw = trim((string) ($fila[$mapaColumnas['zona']] ?? ''));
            $presentacionRaw = trim((string) ($fila[$mapaColumnas['presentacion']] ?? ''));
            $pesoRaw = trim((string) ($fila[$mapaColumnas['peso']] ?? ''));
            $tanqueRaw = trim((string) ($fila[$mapaColumnas['tanque']] ?? ''));

            // Si toda la fila está vacía, saltar
            if ($numArticulo === '' && $descArticulo === '' && $lote === '' && $cantidadRaw === '') {
                continue;
            }

            // Validar campos obligatorios básicos
            if ($numArticulo === '') {
                $errores[] = "Fila #{$numFila}: Falta el 'Número de artículo'.";
                continue;
            }
            if ($lote === '') {
                $errores[] = "Fila #{$numFila} (Artículo {$numArticulo}): Falta el 'Lote'.";
                continue;
            }

            // Normalizar cantidad
            $cantidadNormalizada = str_replace(['.', ','], ['.', '.'], $cantidadRaw);
            // Si tiene más de un punto, corregir formato miles
            if (substr_count($cantidadNormalizada, '.') > 1) {
                $partes = explode('.', $cantidadNormalizada);
                $decimal = array_pop($partes);
                $cantidadNormalizada = implode('', $partes) . '.' . $decimal;
            }
            $cantidad = (float) $cantidadNormalizada;
            if ($cantidad <= 0.0001) {
                $errores[] = "Fila #{$numFila} (Artículo {$numArticulo}, Lote {$lote}): La cantidad debe ser mayor a cero ({$cantidadRaw}).";
                continue;
            }

            // Normalizar Sector / Zona
            $sector = $this->normalizarSector($zonaRaw);
            if ($sector === null) {
                $errores[] = "Fila #{$numFila} (Artículo {$numArticulo}): Zona/Sector inválido '{$zonaRaw}'. Debe ser 1, 2 o 3.";
                continue;
            }

            // Normalizar Presentación ("TAMBOR (190 KG)", "GRANEL (KG)", etc.)
            $nombrePresentacionCompuesta = $this->construirNombrePresentacion($presentacionRaw, $pesoRaw);
            if ($nombrePresentacionCompuesta === '') {
                $errores[] = "Fila #{$numFila} (Artículo {$numArticulo}): Falta la presentación.";
                continue;
            }

            // Validar o registrar nuevo producto
            $idProducto = $productosExistentes[$numArticulo]['idProducto'] ?? null;
            if (!$idProducto) {
                if (!isset($nuevosProductos[$numArticulo])) {
                    $nuevosProductos[$numArticulo] = [
                        'codigoInterno' => $numArticulo,
                        'nombre' => $descArticulo !== '' ? $descArticulo : $numArticulo,
                    ];
                }
            }

            // Validar o registrar nueva ubicación / almacén
            $idUbicacion = null;
            $almacenKey = $almacenRaw !== '' ? $almacenRaw : $nombreAlmacen;
            if (isset($ubicacionesExistentes['por_id'][(int) $almacenRaw])) {
                $idUbicacion = (int) $almacenRaw;
            } elseif (isset($ubicacionesExistentes['por_nombre'][mb_strtoupper($nombreAlmacen, 'UTF-8')])) {
                $idUbicacion = (int) $ubicacionesExistentes['por_nombre'][mb_strtoupper($nombreAlmacen, 'UTF-8')];
            } else {
                if (!isset($nuevasUbicaciones[$almacenKey])) {
                    $nuevasUbicaciones[$almacenKey] = [
                        'idSugerido' => is_numeric($almacenRaw) ? (int) $almacenRaw : null,
                        'nombre' => $nombreAlmacen !== '' ? $nombreAlmacen : ('Almacén ' . $almacenRaw),
                    ];
                }
            }

            // Validar o registrar nueva presentación
            $idPresentacion = $presentacionesExistentes[mb_strtoupper($nombrePresentacionCompuesta, 'UTF-8')] ?? null;
            if (!$idPresentacion) {
                if (!isset($nuevasPresentaciones[$nombrePresentacionCompuesta])) {
                    $nuevasPresentaciones[$nombrePresentacionCompuesta] = [
                        'nombre' => $nombrePresentacionCompuesta,
                    ];
                }
            }

            // Validar Tanque para Sector 3
            $idSilo = null;
            $codigoSilo = null;
            if ($sector === 'Sector3') {
                if ($tanqueRaw === '') {
                    $errores[] = "Fila #{$numFila} (Artículo {$numArticulo}, Lote {$lote}): Pertenece al Sector 3 pero no tiene tanque asignado en la columna 'TANQUE'.";
                    continue;
                }

                $siloEncontrado = $this->resolverSilo($tanqueRaw, $silosExistentes);
                if (!$siloEncontrado) {
                    $errores[] = "Fila #{$numFila} (Artículo {$numArticulo}, Lote {$lote}): El tanque/silo '{$tanqueRaw}' no existe en el catálogo de silos de Sector 3.";
                    continue;
                }

                $idSilo = (int) $siloEncontrado['idSilo'];
                $codigoSilo = (string) $siloEncontrado['codigo'];
                $conteoPorTanque[$codigoSilo] = ($conteoPorTanque[$codigoSilo] ?? 0.0) + $cantidad;
            }

            $conteoPorSector[$sector]++;
            $totalKilos += $cantidad;

            $registrosValidos[] = [
                'fila_excel' => $numFila,
                'codigoInterno' => $numArticulo,
                'nombreProducto' => $descArticulo !== '' ? $descArticulo : $numArticulo,
                'lote' => $lote,
                'almacenRaw' => $almacenRaw,
                'nombreAlmacen' => $nombreAlmacen !== '' ? $nombreAlmacen : ('Almacén ' . $almacenRaw),
                'almacenKey' => $almacenKey,
                'cantidad' => $cantidad,
                'sector' => $sector,
                'presentacion' => $nombrePresentacionCompuesta,
                'tanqueRaw' => $tanqueRaw,
                'idSilo' => $idSilo,
                'codigoSilo' => $codigoSilo,
            ];
        }

        $resumen = [
            'total_filas_leidas' => count($filas) - $indiceEncabezado,
            'total_registros_validos' => count($registrosValidos),
            'total_kilos' => $totalKilos,
            'por_sector' => $conteoPorSector,
            'por_tanque' => $conteoPorTanque,
            'nuevos_productos' => array_values($nuevosProductos),
            'nuevas_ubicaciones' => array_values($nuevasUbicaciones),
            'nuevas_presentaciones' => array_values($nuevasPresentaciones),
            'errores' => $errores,
            'advertencias' => $advertencias,
            'usuario_carga' => [
                'id' => $idUsuario,
                'username' => $username,
            ],
            'fecha_carga' => date('Y-m-d H:i:s'),
            'fecha_carga_formateada' => date('d/m/Y H:i:s'),
        ];

        return [
            'exito' => empty($errores),
            'resumen' => $resumen,
            'registros' => $registrosValidos,
        ];
    }

    /**
     * Ejecuta el vaciado seguro y la carga del inventario inicial.
     */
    public function ejecutarVaciadoYCarga(array $staging, int $idUsuario, string $username): array
    {
        $registros = $staging['registros'] ?? [];
        if (empty($registros)) {
            throw new DomainException('No hay registros validados en memoria para cargar.');
        }

        // Generar respaldo obligatorio previo
        $backupInfo = $this->generarBackup('backup_previo_vaciado');

        $this->db->beginTransaction();
        try {
            // 1. Vaciar datos transaccionales de movimientos
            // Desactivar temporalmente foreign keys para evitar trabas y reestablecer al final
            $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');

            $this->db->exec('DELETE FROM silo_salidas');
            $this->db->exec('DELETE FROM ajustes_lote_silos');
            $this->db->exec('DELETE FROM ajustes_lote');
            $this->db->exec('DELETE FROM silo_asignaciones');
            $this->db->exec('DELETE FROM inventariosaliente');
            $this->db->exec('DELETE FROM tbl_items_predespacho');
            $this->db->exec('DELETE FROM tbl_cabecera_predespacho');
            $this->db->exec('DELETE FROM entrada_documentos');
            $this->db->exec('DELETE FROM inventarioentrante');

            $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');

            // 2. Crear maestros faltantes: Productos
            $nuevosProductos = $staging['resumen']['nuevos_productos'] ?? [];
            if (!empty($nuevosProductos)) {
                $stmtProd = $this->db->prepare('INSERT INTO producto (codigoInterno, nombre) VALUES (:codigo, :nombre)');
                foreach ($nuevosProductos as $prod) {
                    // Verificar si ya existe por si acaso
                    $chk = $this->db->prepare('SELECT idProducto FROM producto WHERE TRIM(codigoInterno) = :codigo LIMIT 1');
                    $chk->execute(['codigo' => $prod['codigoInterno']]);
                    if ($chk->fetchColumn() === false) {
                        $stmtProd->execute([
                            'codigo' => $prod['codigoInterno'],
                            'nombre' => $prod['nombre'],
                        ]);
                    }
                }
            }

            // 3. Crear maestros faltantes: Presentaciones
            $nuevasPresentaciones = $staging['resumen']['nuevas_presentaciones'] ?? [];
            if (!empty($nuevasPresentaciones)) {
                $stmtPres = $this->db->prepare('INSERT INTO presentacion (nombre) VALUES (:nombre)');
                foreach ($nuevasPresentaciones as $pres) {
                    $chk = $this->db->prepare('SELECT idPresentacion FROM presentacion WHERE UPPER(TRIM(nombre)) = UPPER(TRIM(:nombre)) LIMIT 1');
                    $chk->execute(['nombre' => $pres['nombre']]);
                    if ($chk->fetchColumn() === false) {
                        $stmtPres->execute(['nombre' => $pres['nombre']]);
                    }
                }
            }

            // 4. Crear maestros faltantes: Ubicaciones
            $nuevasUbicaciones = $staging['resumen']['nuevas_ubicaciones'] ?? [];
            if (!empty($nuevasUbicaciones)) {
                foreach ($nuevasUbicaciones as $ubi) {
                    $idSugerido = $ubi['idSugerido'] ?? null;
                    $nombre = $ubi['nombre'];

                    if ($idSugerido !== null) {
                        $chk = $this->db->prepare('SELECT idUbicacion FROM ubicacion WHERE idUbicacion = :id LIMIT 1');
                        $chk->execute(['id' => $idSugerido]);
                        if ($chk->fetchColumn() === false) {
                            $stmtUbi = $this->db->prepare('INSERT INTO ubicacion (idUbicacion, nombre) VALUES (:id, :nombre)');
                            $stmtUbi->execute(['id' => $idSugerido, 'nombre' => $nombre]);
                            continue;
                        }
                    }

                    $chkNombre = $this->db->prepare('SELECT idUbicacion FROM ubicacion WHERE UPPER(TRIM(nombre)) = UPPER(TRIM(:nombre)) LIMIT 1');
                    $chkNombre->execute(['nombre' => $nombre]);
                    if ($chkNombre->fetchColumn() === false) {
                        $stmtUbi = $this->db->prepare('INSERT INTO ubicacion (nombre) VALUES (:nombre)');
                        $stmtUbi->execute(['nombre' => $nombre]);
                    }
                }
            }

            // 5. Re-indexar mapas actualizados
            $mapaProd = $this->obtenerMapaProductos();
            $mapaPres = $this->obtenerMapaPresentaciones();
            $mapaUbi = $this->obtenerMapaUbicaciones();

            // 6. Insertar inventario entrante y asignación de silos
            $stmtEntrada = $this->db->prepare(
                'INSERT INTO inventarioentrante
                    (NumLote, idProducto, idPresentacion, `idUbicación`, CantidadEntrante, fecha, sector, idTipoCompra, CardCode, FabricanteCode, PaisCode, nro_factura, fecha_factura, peso_romana, observaciones)
                 VALUES
                    (:numLote, :idProducto, :idPresentacion, :idUbicacion, :cantidadEntrante, CURDATE(), :sector, :idTipoCompra, :cardCode, :fabricanteCode, :paisCode, :nroFactura, :fechaFactura, :pesoRomana, :observaciones)'
            );

            $stmtAsignacionSilo = $this->db->prepare(
                'INSERT INTO silo_asignaciones (idSilo, idInventarioEntrante, cantidadAsignada)
                 VALUES (:idSilo, :idInventarioEntrante, :cantidadAsignada)'
            );

            $totalInsertados = 0;
            $totalAsignadosSilo = 0;
            $fechaHoy = date('Y-m-d');
            $nroFactura = 'F-' . date('Ymd');
            $observaciones = 'Inventario cargado por un lote el dia ' . date('d/m/Y');

            foreach ($registros as $reg) {
                $idProducto = (int) ($mapaProd[$reg['codigoInterno']]['idProducto'] ?? 0);
                $idPresentacion = (int) ($mapaPres[mb_strtoupper($reg['presentacion'], 'UTF-8')] ?? 0);

                // Resolver idUbicacion
                $idUbicacion = null;
                if (is_numeric($reg['almacenRaw']) && isset($mapaUbi['por_id'][(int) $reg['almacenRaw']])) {
                    $idUbicacion = (int) $reg['almacenRaw'];
                } elseif (isset($mapaUbi['por_nombre'][mb_strtoupper($reg['nombreAlmacen'], 'UTF-8')])) {
                    $idUbicacion = (int) $mapaUbi['por_nombre'][mb_strtoupper($reg['nombreAlmacen'], 'UTF-8')];
                } else {
                    // Primer almacén por defecto o 1
                    $idUbicacion = !empty($mapaUbi['por_id']) ? array_key_first($mapaUbi['por_id']) : 1;
                }

                if ($idProducto <= 0 || $idPresentacion <= 0) {
                    throw new RuntimeException("Error al resolver producto o presentación para fila {$reg['fila_excel']}.");
                }

                $stmtEntrada->execute([
                    'numLote' => $reg['lote'],
                    'idProducto' => $idProducto,
                    'idPresentacion' => $idPresentacion,
                    'idUbicacion' => $idUbicacion,
                    'cantidadEntrante' => $reg['cantidad'],
                    'sector' => $reg['sector'],
                    'idTipoCompra' => 3,
                    'cardCode' => 'C99999',
                    'fabricanteCode' => 'C99999',
                    'paisCode' => 'XX',
                    'nroFactura' => $nroFactura,
                    'fechaFactura' => $fechaHoy,
                    'pesoRomana' => $reg['cantidad'],
                    'observaciones' => $observaciones,
                ]);

                $idEntrada = (int) $this->db->lastInsertId();
                $totalInsertados++;

                if ($reg['sector'] === 'Sector3' && !empty($reg['idSilo'])) {
                    $stmtAsignacionSilo->execute([
                        'idSilo' => (int) $reg['idSilo'],
                        'idInventarioEntrante' => $idEntrada,
                        'cantidadAsignada' => $reg['cantidad'],
                    ]);
                    $totalAsignadosSilo++;
                }
            }

            $this->db->commit();

            Auth::log(
                $idUsuario,
                $username,
                'administracion',
                'reinicio_inventario',
                'exitoso',
                "Vaciado y carga inicial de {$totalInsertados} entradas ({$totalAsignadosSilo} en tanques Sector 3)"
            );

            return [
                'exito' => true,
                'total_insertados' => $totalInsertados,
                'total_silos' => $totalAsignadosSilo,
                'backup' => $backupInfo,
                'mensaje' => "Inventario reiniciado exitosamente. Se cargaron {$totalInsertados} lotes y se asignaron {$totalAsignadosSilo} tanques.",
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function esFilaEncabezado(array $fila): bool
    {
        $textoFila = implode(' ', $fila);
        return (str_contains($textoFila, 'artículo') || str_contains($textoFila, 'articulo'))
            && (str_contains($textoFila, 'lote'))
            && (str_contains($textoFila, 'cantidad'));
    }

    private function mapearColumnas(array $fila): array
    {
        $mapa = [
            'articulo' => null,
            'descripcion' => null,
            'lote' => null,
            'almacen' => null,
            'nombre_almacen' => null,
            'cantidad' => null,
            'zona' => null,
            'presentacion' => null,
            'peso' => null,
            'tanque' => null,
        ];

        foreach ($fila as $col => $valor) {
            $v = mb_strtolower(trim($valor), 'UTF-8');
            if ($mapa['articulo'] === null && (str_contains($v, 'número de artículo') || str_contains($v, 'numero de articulo') || str_contains($v, 'código') || str_contains($v, 'itemcode') || $v === 'artículo' || $v === 'articulo')) {
                $mapa['articulo'] = $col;
            } elseif ($mapa['descripcion'] === null && (str_contains($v, 'descripción') || str_contains($v, 'descripcion') || str_contains($v, 'nombre del artículo') || str_contains($v, 'itemname'))) {
                $mapa['descripcion'] = $col;
            } elseif ($mapa['lote'] === null && (str_contains($v, 'lote') || str_contains($v, 'batch') || str_contains($v, 'numlote'))) {
                $mapa['lote'] = $col;
            } elseif ($mapa['almacen'] === null && ($v === 'almacén' || $v === 'almacen' || str_contains($v, 'whscode') || $v === 'código almacén' || $v === 'codigo almacen')) {
                $mapa['almacen'] = $col;
            } elseif ($mapa['nombre_almacen'] === null && (str_contains($v, 'nombre del almacén') || str_contains($v, 'nombre del almacen') || str_contains($v, 'nombre almacén') || str_contains($v, 'nombre almacen') || str_contains($v, 'whsname'))) {
                $mapa['nombre_almacen'] = $col;
            } elseif ($mapa['cantidad'] === null && (str_contains($v, 'cantidad') || str_contains($v, 'quantity') || str_contains($v, 'kilos'))) {
                $mapa['cantidad'] = $col;
            } elseif ($mapa['zona'] === null && (str_contains($v, 'zona') || str_contains($v, 'sector'))) {
                $mapa['zona'] = $col;
            } elseif ($mapa['presentacion'] === null && (str_contains($v, 'presentacion') || str_contains($v, 'presentación') || str_contains($v, 'empaque'))) {
                $mapa['presentacion'] = $col;
            } elseif ($mapa['peso'] === null && (str_contains($v, 'peso') || str_contains($v, 'peso empaque'))) {
                $mapa['peso'] = $col;
            } elseif ($mapa['tanque'] === null && (str_contains($v, 'tanque') || str_contains($v, 'silo'))) {
                $mapa['tanque'] = $col;
            }
        }

        // Si almacén o nombre_almacén quedaron juntos o cruzados:
        if ($mapa['almacen'] === null && $mapa['nombre_almacen'] !== null) {
            $mapa['almacen'] = $mapa['nombre_almacen'];
        }

        return $mapa;
    }

    private function normalizarSector(string $zonaRaw): ?string
    {
        $limpio = mb_strtolower(trim($zonaRaw), 'UTF-8');
        $limpio = str_replace([' ', '_', '-'], '', $limpio);

        if ($limpio === '1' || $limpio === 'sector1' || $limpio === 'zona1') {
            return 'Sector1';
        }
        if ($limpio === '2' || $limpio === 'sector2' || $limpio === 'zona2') {
            return 'Sector2';
        }
        if ($limpio === '3' || $limpio === 'sector3' || $limpio === 'zona3') {
            return 'Sector3';
        }

        return null;
    }

    private function construirNombrePresentacion(string $presentacionRaw, string $pesoRaw): string
    {
        $pres = mb_strtoupper(trim($presentacionRaw), 'UTF-8');
        $peso = trim($pesoRaw);

        if ($pres === '') {
            return '';
        }

        // Si ya viene con el formato completo ej: "TAMBOR (190 KG)"
        if (str_contains($pres, '(') && str_contains($pres, ')')) {
            return $pres;
        }

        // Si es GRANEL y no tiene peso o peso es 0
        if (str_contains($pres, 'GRANEL')) {
            return 'GRANEL (KG)';
        }

        // Limpiar peso numérico
        $pesoLimpio = preg_replace('/[^0-9.]/', '', str_replace(',', '.', $peso));
        if ($pesoLimpio !== '' && (float) $pesoLimpio > 0) {
            $pesoFormateado = (float) $pesoLimpio == (int) $pesoLimpio ? (string) (int) $pesoLimpio : (string) (float) $pesoLimpio;
            return "{$pres} ({$pesoFormateado} KG)";
        }

        return $pres;
    }

    private function resolverSilo(string $tanqueRaw, array $silos): ?array
    {
        $tanqueLimpio = trim($tanqueRaw);
        if ($tanqueLimpio === '') {
            return null;
        }

        // 1. Coincidencia exacta por código
        if (isset($silos['por_codigo'][$tanqueLimpio])) {
            return $silos['por_codigo'][$tanqueLimpio];
        }

        // 2. Coincidencia con relleno de ceros (ej. '15' -> '015', '1' -> '001')
        $padded = str_pad($tanqueLimpio, 3, '0', STR_PAD_LEFT);
        if (isset($silos['por_codigo'][$padded])) {
            return $silos['por_codigo'][$padded];
        }

        // 3. Coincidencia quitando ceros a la izquierda
        $sinCeros = ltrim($tanqueLimpio, '0');
        if (isset($silos['por_codigo_sin_ceros'][$sinCeros])) {
            return $silos['por_codigo_sin_ceros'][$sinCeros];
        }

        // 4. Coincidencia por idSilo directo si es numérico
        if (is_numeric($tanqueLimpio) && isset($silos['por_id'][(int) $tanqueLimpio])) {
            return $silos['por_id'][(int) $tanqueLimpio];
        }

        return null;
    }

    private function obtenerMapaProductos(): array
    {
        $stmt = $this->db->query('SELECT idProducto, codigoInterno, nombre FROM producto');
        $mapa = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $mapa[trim($row['codigoInterno'])] = $row;
        }
        return $mapa;
    }

    private function obtenerMapaUbicaciones(): array
    {
        $stmt = $this->db->query('SELECT idUbicacion, nombre FROM ubicacion');
        $porId = [];
        $porNombre = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) $row['idUbicacion'];
            $nombre = trim($row['nombre']);
            $porId[$id] = $row;
            $porNombre[mb_strtoupper($nombre, 'UTF-8')] = $id;
        }
        return ['por_id' => $porId, 'por_nombre' => $porNombre];
    }

    private function obtenerMapaPresentaciones(): array
    {
        $stmt = $this->db->query('SELECT idPresentacion, nombre FROM presentacion');
        $mapa = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $mapa[mb_strtoupper(trim($row['nombre']), 'UTF-8')] = (int) $row['idPresentacion'];
        }
        return $mapa;
    }

    private function obtenerMapaSilos(): array
    {
        $stmt = $this->db->query('SELECT idSilo, codigo, nombre, capacidad, activo FROM silos');
        $porCodigo = [];
        $porCodigoSinCeros = [];
        $porId = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $codigo = trim($row['codigo']);
            $id = (int) $row['idSilo'];
            $porCodigo[$codigo] = $row;
            $porCodigoSinCeros[ltrim($codigo, '0')] = $row;
            $porId[$id] = $row;
        }

        return [
            'por_codigo' => $porCodigo,
            'por_codigo_sin_ceros' => $porCodigoSinCeros,
            'por_id' => $porId,
        ];
    }

    private function formatearBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
