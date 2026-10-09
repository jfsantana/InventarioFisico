-- Ejecutar despues de add_control_silos.sql y de habilitar los usuarios.
-- No modifica entradas, salidas ni predespachos existentes.
-- usuarios es MyISAM en la instalacion actual; InnoDB permite proteger el responsable con FK.
ALTER TABLE usuarios ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS ajustes_lote (
    idAjuste BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idInventarioEntrante INT NOT NULL,
    tipo ENUM('positivo', 'negativo') NOT NULL,
    monto DECIMAL(14,3) NOT NULL,
    observacion VARCHAR(1000) NOT NULL,
    idUsuario INT NOT NULL,
    responsable VARCHAR(140) NOT NULL,
    idSilo INT UNSIGNED NULL,
    fechaCreacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tokenRegistro CHAR(64) NOT NULL,
    PRIMARY KEY (idAjuste),
    UNIQUE KEY uq_ajuste_token (tokenRegistro),
    KEY idx_ajuste_lote_fecha (idInventarioEntrante, fechaCreacion, idAjuste),
    CONSTRAINT fk_ajuste_lote FOREIGN KEY (idInventarioEntrante)
        REFERENCES inventarioentrante (idInventarioEntrante) ON DELETE RESTRICT,
    CONSTRAINT fk_ajuste_usuario FOREIGN KEY (idUsuario)
        REFERENCES usuarios (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT fk_ajuste_silo FOREIGN KEY (idSilo)
        REFERENCES silos (idSilo) ON DELETE RESTRICT,
    CONSTRAINT chk_ajuste_monto CHECK (monto > 0),
    CONSTRAINT chk_ajuste_observacion CHECK (CHAR_LENGTH(TRIM(observacion)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW v_disponibilidad_lotes AS
SELECT
    ie.idInventarioEntrante,
    ie.idProducto,
    ie.idPresentacion,
    ie.NumLote,
    ie.`idUbicación`,
    ie.sector,
    ie.CantidadEntrante AS stock_total,
    COALESCE(ajustes.ajuste_positivo, 0) AS ajuste_positivo,
    COALESCE(ajustes.ajuste_negativo, 0) AS ajuste_negativo,
    COALESCE(salidas.cantidad_saliente, 0) AS cantidad_saliente,
    COALESCE(reservas.cantidad_reservada, 0) AS cantidad_reservada,
    ie.CantidadEntrante
        + COALESCE(ajustes.ajuste_positivo, 0)
        - COALESCE(ajustes.ajuste_negativo, 0)
        - COALESCE(salidas.cantidad_saliente, 0)
        - COALESCE(reservas.cantidad_reservada, 0) AS cantidad_disponible
FROM inventarioentrante ie
LEFT JOIN (
    SELECT idInventarioEntrante,
           SUM(CASE WHEN tipo = 'positivo' THEN monto ELSE 0 END) AS ajuste_positivo,
           SUM(CASE WHEN tipo = 'negativo' THEN monto ELSE 0 END) AS ajuste_negativo
    FROM ajustes_lote
    GROUP BY idInventarioEntrante
) ajustes ON ajustes.idInventarioEntrante = ie.idInventarioEntrante
LEFT JOIN (
    SELECT idInventarioEntrante, SUM(cantidadSaliente) AS cantidad_saliente
    FROM inventariosaliente
    GROUP BY idInventarioEntrante
) salidas ON salidas.idInventarioEntrante = ie.idInventarioEntrante
LEFT JOIN (
    SELECT it.idInventarioEntrante,
           SUM(GREATEST(it.cantidadSolicitada - COALESCE(item_salidas.cantidad_saliente, 0), 0)) AS cantidad_reservada
    FROM tbl_items_predespacho it
    INNER JOIN tbl_cabecera_predespacho cp ON cp.idCabeceraPredespacho = it.idCabeceraPredespacho
    LEFT JOIN (
        SELECT it2.idItem, SUM(ins.cantidadSaliente) AS cantidad_saliente
        FROM tbl_items_predespacho it2
        INNER JOIN tbl_cabecera_predespacho cp2 ON cp2.idCabeceraPredespacho = it2.idCabeceraPredespacho
        INNER JOIN inventariosaliente ins ON ins.idInventarioEntrante = it2.idInventarioEntrante
            AND ins.NE COLLATE utf8mb4_unicode_ci = cp2.codigoInterno
        GROUP BY it2.idItem
    ) item_salidas ON item_salidas.idItem = it.idItem
    WHERE cp.statusGeneralPredespacho <> 'cerrado'
      AND it.estatusItemPredespacho <> 'cerrado'
    GROUP BY it.idInventarioEntrante
) reservas ON reservas.idInventarioEntrante = ie.idInventarioEntrante;
