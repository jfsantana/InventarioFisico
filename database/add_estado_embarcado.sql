ALTER TABLE tbl_cabecera_predespacho
    MODIFY COLUMN statusGeneralPredespacho
        ENUM('abierto', 'pendiente', 'embarcado', 'cerrado')
        NOT NULL DEFAULT 'abierto';