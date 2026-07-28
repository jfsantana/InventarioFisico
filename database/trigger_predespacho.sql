DELIMITER $$

DROP TRIGGER IF EXISTS trg_codigo_interno_predespacho$$

CREATE TRIGGER trg_codigo_interno_predespacho
BEFORE INSERT ON tbl_cabecera_predespacho
FOR EACH ROW
BEGIN
    DECLARE siguienteCorrelativo INT DEFAULT 1;
    DECLARE anioActual CHAR(4);

    SET anioActual = DATE_FORMAT(CURDATE(), '%Y');

    IF NEW.codigoInterno IS NULL OR NEW.codigoInterno = '' THEN
        SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(codigoInterno, '-', -1) AS UNSIGNED)), 0) + 1
        INTO siguienteCorrelativo
        FROM tbl_cabecera_predespacho
        WHERE codigoInterno LIKE CONCAT('PRE-', anioActual, '-%');

        SET NEW.codigoInterno = CONCAT('PRE-', anioActual, '-', LPAD(siguienteCorrelativo, 5, '0'));
    END IF;
END$$

DELIMITER ;