ALTER TABLE tbl_cabecera_predespacho
    ADD COLUMN idUsuarioCierre INT NULL AFTER tokenCierre,
    ADD COLUMN usuarioCierre VARCHAR(150) NULL AFTER idUsuarioCierre,
    ADD COLUMN fechaCierre DATETIME NULL AFTER usuarioCierre,
    ADD INDEX idx_predespacho_usuario_cierre (idUsuarioCierre),
    ADD INDEX idx_predespacho_fecha_cierre (fechaCierre);
