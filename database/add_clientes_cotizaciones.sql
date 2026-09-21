SET @add_activo_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE tbl_clientes_cotizacion ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER E_Mail',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tbl_clientes_cotizacion'
      AND COLUMN_NAME = 'activo'
);
PREPARE add_activo_column_statement FROM @add_activo_column;
EXECUTE add_activo_column_statement;
DEALLOCATE PREPARE add_activo_column_statement;

ALTER TABLE tbl_clientes_cotizacion
    MODIFY COLUMN activo TINYINT(1) NOT NULL DEFAULT 1;

INSERT INTO tbl_clientes_cotizacion (CardCode, CardName, LicTradNum, Address, E_Mail, Phone1, activo)
SELECT CONCAT('COT', LPAD(origen.idCliente, 5, '0')),
       origen.nombre,
       origen.rif,
       origen.direccion,
       origen.email,
       origen.telefono,
       origen.activo
FROM tbl_cliente origen
LEFT JOIN tbl_clientes_cotizacion destino ON destino.LicTradNum = origen.rif
WHERE destino.CardCode IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM tbl_clientes_cotizacion codigo
      WHERE codigo.CardCode = CONCAT('COT', LPAD(origen.idCliente, 5, '0'))
  );

ALTER TABLE tbl_cotizacion_cabecera
    DROP FOREIGN KEY tbl_cotizacion_cabecera_ibfk_1;

ALTER TABLE tbl_cotizacion_cabecera
    MODIFY COLUMN idCliente VARCHAR(15) NOT NULL;

UPDATE tbl_cotizacion_cabecera cotizacion
INNER JOIN tbl_cliente origen ON origen.idCliente = CAST(cotizacion.idCliente AS UNSIGNED)
INNER JOIN tbl_clientes_cotizacion destino ON destino.LicTradNum = origen.rif
SET cotizacion.idCliente = destino.CardCode;

ALTER TABLE tbl_cotizacion_cabecera
    ADD CONSTRAINT fk_cotizacion_cliente_cotizacion
        FOREIGN KEY (idCliente) REFERENCES tbl_clientes_cotizacion (CardCode)
        ON UPDATE CASCADE;