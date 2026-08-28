CREATE TABLE IF NOT EXISTS entrada_documentos (
    idDocumento INT UNSIGNED NOT NULL AUTO_INCREMENT,
    idInventarioEntrante INT NOT NULL,
    tipoDocumento ENUM('ticket_romana', 'factura_proveedor', 'documento_seniat') NOT NULL,
    nombreOriginal VARCHAR(255) NOT NULL,
    nombreAlmacenado VARCHAR(80) NOT NULL,
    rutaRelativa VARCHAR(255) NOT NULL,
    mimeType VARCHAR(100) NOT NULL,
    tamanoBytes INT UNSIGNED NOT NULL,
    idUsuario INT DEFAULT NULL,
    fechaCarga TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (idDocumento),
    UNIQUE KEY uq_entrada_tipo_documento (idInventarioEntrante, tipoDocumento),
    KEY idx_entrada_documentos_entrada (idInventarioEntrante),
    KEY idx_entrada_documentos_usuario (idUsuario)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
