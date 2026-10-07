-- Ejecutar en la BD de PRD seleccionada en phpMyAdmin, con respaldo verificado
-- y la aplicacion en mantenimiento. DDL hace commit: no se revierte con ROLLBACK.
-- No convierte numeros antiguos a clientes ni actualiza filas historicas.
-- Requiere permisos CREATE ROUTINE, ALTER, REFERENCES y SELECT.
DELIMITER $$

CREATE PROCEDURE reparar_cotizacion_id_cliente_prd_v1()
BEGIN
    DECLARE tipo_cliente VARCHAR(64);
    DECLARE tipo_codigo VARCHAR(64);
    DECLARE longitud_codigo INT;
    DECLARE collation_codigo VARCHAR(64);
    DECLARE charset_codigo VARCHAR(64);
    DECLARE fk_drops TEXT;
    DECLARE huerfanos BIGINT DEFAULT 0;

    SELECT DATA_TYPE INTO tipo_cliente
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tbl_cotizacion_cabecera' AND COLUMN_NAME = 'idCliente';

    SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLLATION_NAME, CHARACTER_SET_NAME
    INTO tipo_codigo, longitud_codigo, collation_codigo, charset_codigo
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tbl_clientes_cotizacion' AND COLUMN_NAME = 'CardCode';

    IF tipo_cliente IS NULL OR tipo_cliente NOT IN ('tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'varchar', 'char')
       OR tipo_codigo IS NULL OR tipo_codigo <> 'varchar' OR longitud_codigo <> 15 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Esquema inesperado. Revisar columnas antes de migrar; no se modifico la BD.';
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
        WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
          AND REFERENCED_TABLE_NAME = 'tbl_cotizacion_cabecera'
          AND REFERENCED_COLUMN_NAME = 'idCliente'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Hay tablas que referencian idCliente. Revisar dependencias antes de migrar.';
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k
        WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = 'tbl_cotizacion_cabecera'
          AND k.COLUMN_NAME = 'idCliente' AND k.REFERENCED_TABLE_NAME IS NOT NULL
          AND (k.REFERENCED_TABLE_SCHEMA <> DATABASE()
            OR k.REFERENCED_TABLE_NAME NOT IN ('tbl_cliente', 'tbl_clientes_cotizacion')
            OR (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE partes
                WHERE partes.TABLE_SCHEMA = k.TABLE_SCHEMA AND partes.TABLE_NAME = k.TABLE_NAME
                  AND partes.CONSTRAINT_NAME = k.CONSTRAINT_NAME) <> 1)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Relacion de cliente inesperada o compuesta. Revisar antes de migrar.';
    END IF;

    SELECT GROUP_CONCAT(CONCAT('DROP FOREIGN KEY `', REPLACE(CONSTRAINT_NAME, '`', '``'), '`') SEPARATOR ', ')
    INTO fk_drops
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_cotizacion_cabecera'
      AND COLUMN_NAME = 'idCliente' AND REFERENCED_TABLE_NAME IS NOT NULL;

    -- Quitar la relacion numerica antigua y cambiar tipo en una sola sentencia.
    SET @cotizacion_repair_sql = CONCAT(
        'ALTER TABLE tbl_cotizacion_cabecera ',
        IF(fk_drops IS NULL, '', CONCAT(fk_drops, ', ')),
        'MODIFY COLUMN idCliente VARCHAR(15) CHARACTER SET `',
        REPLACE(charset_codigo, '`', '``'), '` COLLATE `',
        REPLACE(collation_codigo, '`', '``'), '` NOT NULL'
    );
    PREPARE cotizacion_repair_stmt FROM @cotizacion_repair_sql;
    EXECUTE cotizacion_repair_stmt;
    DEALLOCATE PREPARE cotizacion_repair_stmt;

    SELECT COUNT(*) INTO huerfanos
    FROM tbl_cotizacion_cabecera c
    WHERE NOT EXISTS (
        SELECT 1 FROM tbl_clientes_cotizacion cliente
        WHERE BINARY cliente.CardCode = BINARY c.idCliente
    );

    IF huerfanos = 0 THEN
        ALTER TABLE tbl_cotizacion_cabecera
            ADD CONSTRAINT fk_cotizacion_cliente_cotizacion
            FOREIGN KEY (idCliente) REFERENCES tbl_clientes_cotizacion (CardCode)
            ON UPDATE CASCADE;
    END IF;

    SELECT 'Columna corregida: las nuevas cotizaciones pueden guardar el codigo completo.' AS resultado,
           huerfanos AS cotizaciones_historicas_sin_cliente_exacto,
           IF(huerfanos = 0, 'Clave foranea creada.',
              'Clave foranea pendiente: reconciliar historicos con evidencia y volver a ejecutar este script.') AS relacion;
    SELECT idCotizacion, idCliente AS codigo_historico_pendiente
    FROM tbl_cotizacion_cabecera c
    WHERE NOT EXISTS (
        SELECT 1 FROM tbl_clientes_cotizacion cliente
        WHERE BINARY cliente.CardCode = BINARY c.idCliente
    )
    ORDER BY idCotizacion;
END$$

DELIMITER ;
CALL reparar_cotizacion_id_cliente_prd_v1();
DROP PROCEDURE reparar_cotizacion_id_cliente_prd_v1;
