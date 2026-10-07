<?php

class CotizacionLog
{
    private static ?string $peticion = null;
    private static ?string $flujo = null;

    public static function iniciar(?string $flujo = null): string
    {
        self::$peticion ??= bin2hex(random_bytes(16));
        if ($flujo !== null && preg_match('/^[a-f0-9]{32}$/D', $flujo)) {
            self::$flujo = $flujo;
        }
        self::$flujo ??= self::$peticion;

        return self::$flujo;
    }

    public static function registrar(string $paso, array $datos = []): bool
    {
        self::iniciar();
        try {
            $directorio = self::directorio();
            if (!is_dir($directorio) && !@mkdir($directorio, 0700, true) && !is_dir($directorio)) {
                throw new RuntimeException('No se pudo crear el directorio del log.');
            }
            $linea = json_encode([
                'fecha' => date(DATE_ATOM),
                'version' => 'cotizacion-identidad-1',
                'flujo' => self::$flujo,
                'peticion' => self::$peticion,
                'usuario' => (int) ($_SESSION['id_usuario'] ?? 0),
                'paso' => $paso,
                'datos' => $datos,
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . PHP_EOL;
            // Un bloqueo separado mantiene atomica la rotacion entre peticiones concurrentes.
            $lock = @fopen($directorio . DIRECTORY_SEPARATOR . 'escritura.lock', 'c');
            if ($lock === false) {
                throw new RuntimeException('No se pudo abrir el bloqueo del log.');
            }
            try {
                if (!flock($lock, LOCK_EX)) {
                    throw new RuntimeException('No se pudo bloquear el log.');
                }
                $archivo = self::archivo(date('Y-m-d'));
                clearstatcache(true, $archivo);
                if (is_file($archivo) && filesize($archivo) >= 5 * 1024 * 1024) {
                    if (is_file($archivo . '.1') && !@unlink($archivo . '.1')) {
                        throw new RuntimeException('No se pudo rotar el log anterior.');
                    }
                    if (!@rename($archivo, $archivo . '.1')) {
                        throw new RuntimeException('No se pudo rotar el log.');
                    }
                }
                if (@file_put_contents($archivo, $linea, FILE_APPEND | LOCK_EX) === false) {
                    throw new RuntimeException('No se pudo escribir el log.');
                }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
            return true;
        } catch (Throwable $exception) {
            error_log('CotizacionLog: ' . $exception->getMessage());
            if (!headers_sent()) {
                header('X-Cotizacion-Log: error');
            }
            return false;
        }
    }

    public static function error(string $paso, Throwable $exception, array $datos = []): void
    {
        // No incluir mensajes PDO/SMTP que pueden contener datos o credenciales.
        self::registrar($paso, $datos + [
            'error_tipo' => get_class($exception),
            'error_codigo' => (string) $exception->getCode(),
            'error_archivo' => basename($exception->getFile()),
            'error_linea' => $exception->getLine(),
            'error' => $exception instanceof PDOException
                ? 'Error de base de datos; revisar esquema, restricciones y conexion.'
                : ($exception instanceof InvalidArgumentException || $exception instanceof CotizacionEsquemaException
                    || $exception instanceof RuntimeException && get_class($exception) === RuntimeException::class
                    ? mb_substr($exception->getMessage(), 0, 500)
                    : 'Fallo en el proceso; revisar tipo, codigo y ubicacion del error.'),
        ]);
    }

    public static function leer(string $fecha, string $flujo = ''): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $fecha)
            || !in_array($fecha, array_map(static fn (int $dia): string => date('Y-m-d', strtotime("-$dia days")), range(0, 13)), true)) {
            throw new InvalidArgumentException('Seleccione una fecha de los ultimos 14 dias.');
        }
        if ($flujo !== '' && !preg_match('/^[a-f0-9]{32}$/D', $flujo)) {
            throw new InvalidArgumentException('El identificador de seguimiento no es valido.');
        }
        $resultado = '';
        foreach ([self::archivo($fecha) . '.1', self::archivo($fecha)] as $archivo) {
            if (!is_file($archivo)) {
                continue;
            }
            $handle = @fopen($archivo, 'rb');
            if ($handle === false) {
                throw new RuntimeException('No se pudo leer el log de cotizaciones.');
            }
            try {
                if (!flock($handle, LOCK_SH)) {
                    throw new RuntimeException('No se pudo bloquear la lectura del log.');
                }
                $stat = fstat($handle);
                if ($stat === false) {
                    throw new RuntimeException('No se pudo consultar el tamano del log.');
                }
                $tamano = $stat['size'];
                if ($tamano > 256 * 1024) {
                    if (fseek($handle, -256 * 1024, SEEK_END) !== 0) {
                        throw new RuntimeException('No se pudo leer el tramo final del log.');
                    }
                    fgets($handle);
                }
                while (($linea = fgets($handle)) !== false) {
                    $registro = json_decode($linea, true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($registro)) {
                        throw new RuntimeException('El log contiene una linea invalida.');
                    }
                    if ($flujo === '' || ($registro['flujo'] ?? '') === $flujo) {
                        $resultado .= $linea;
                    }
                }
                if (!feof($handle)) {
                    throw new RuntimeException('La lectura del log no pudo completarse.');
                }
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
        return $resultado;
    }

    private static function directorio(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cotizaciones';
    }

    private static function archivo(string $fecha): string
    {
        return self::directorio() . DIRECTORY_SEPARATOR . 'cotizaciones-' . $fecha . '.jsonl';
    }
}
