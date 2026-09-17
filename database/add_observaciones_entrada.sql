SET @observaciones_entrada_existe = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'inventarioentrante'
      AND COLUMN_NAME = 'observaciones'
);

SET @agregar_observaciones_entrada = IF(
    @observaciones_entrada_existe = 0,
    'ALTER TABLE inventarioentrante ADD COLUMN observaciones VARCHAR(256) NULL AFTER nro_factura',
    'SELECT 1'
);

PREPARE agregar_observaciones_entrada FROM @agregar_observaciones_entrada;
EXECUTE agregar_observaciones_entrada;
DEALLOCATE PREPARE agregar_observaciones_entrada;