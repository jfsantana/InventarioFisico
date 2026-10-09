-- Ejecutar despues de add_ajustes_lote.sql en la base de inventario.
CREATE TABLE IF NOT EXISTS ajustes_lote_silos (
    idAjuste BIGINT UNSIGNED NOT NULL,
    idSilo INT UNSIGNED NOT NULL,
    monto DECIMAL(14,3) NOT NULL,
    PRIMARY KEY (idAjuste, idSilo),
    CONSTRAINT fk_ajuste_detalle FOREIGN KEY (idAjuste)
        REFERENCES ajustes_lote (idAjuste) ON DELETE RESTRICT,
    CONSTRAINT fk_ajuste_detalle_silo FOREIGN KEY (idSilo)
        REFERENCES silos (idSilo) ON DELETE RESTRICT,
    CONSTRAINT chk_ajuste_silo_monto CHECK (monto > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ajustes_lote_silos (idAjuste, idSilo, monto)
SELECT a.idAjuste, a.idSilo, a.monto
FROM ajustes_lote a
WHERE a.idSilo IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM ajustes_lote_silos d WHERE d.idAjuste = a.idAjuste
  );
