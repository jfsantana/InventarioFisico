ALTER TABLE tbl_cabecera_predespacho
    ADD COLUMN tokenCierre CHAR(64) NULL AFTER statusGeneralPredespacho;

UPDATE tbl_cabecera_predespacho
SET tokenCierre = LOWER(HEX(RANDOM_BYTES(32)))
WHERE tokenCierre IS NULL OR tokenCierre = '';

ALTER TABLE tbl_cabecera_predespacho
    MODIFY COLUMN tokenCierre CHAR(64) NOT NULL,
    ADD UNIQUE KEY uq_predespacho_token_cierre (tokenCierre);