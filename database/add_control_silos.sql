ALTER TABLE inventarioentrante ENGINE = InnoDB;
ALTER TABLE inventariosaliente ENGINE = InnoDB;
ALTER TABLE inventarioentrante MODIFY CantidadEntrante DECIMAL(14,3) NULL;
ALTER TABLE inventariosaliente MODIFY cantidadSaliente DECIMAL(14,3) NOT NULL;

CREATE TABLE IF NOT EXISTS silos (
    idSilo INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(30) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    capacidad DECIMAL(14,3) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fechaCreacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fechaActualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idSilo),
    UNIQUE KEY uq_silos_codigo (codigo),
    CONSTRAINT chk_silos_capacidad CHECK (capacidad > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS silo_asignaciones (
    idAsignacion BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idSilo INT UNSIGNED NOT NULL,
    idInventarioEntrante INT NOT NULL,
    cantidadAsignada DECIMAL(14,3) NOT NULL,
    fechaAsignacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (idAsignacion),
    UNIQUE KEY uq_silo_entrada (idSilo, idInventarioEntrante),
    KEY idx_silo_asignacion_entrada (idInventarioEntrante),
    CONSTRAINT fk_silo_asignacion_silo FOREIGN KEY (idSilo) REFERENCES silos (idSilo),
    CONSTRAINT fk_silo_asignacion_entrada FOREIGN KEY (idInventarioEntrante) REFERENCES inventarioentrante (idInventarioEntrante) ON DELETE CASCADE,
    CONSTRAINT chk_silo_asignacion_cantidad CHECK (cantidadAsignada > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS silo_salidas (
    idSiloSalida BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idAsignacion BIGINT UNSIGNED NOT NULL,
    idInventarioSaliente INT UNSIGNED NOT NULL,
    cantidad DECIMAL(14,3) NOT NULL,
    fechaMovimiento TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (idSiloSalida),
    UNIQUE KEY uq_silo_salida_asignacion (idInventarioSaliente, idAsignacion),
    KEY idx_silo_salida_asignacion (idAsignacion),
    CONSTRAINT fk_silo_salida_asignacion FOREIGN KEY (idAsignacion) REFERENCES silo_asignaciones (idAsignacion) ON DELETE CASCADE,
    CONSTRAINT fk_silo_salida_inventario FOREIGN KEY (idInventarioSaliente) REFERENCES inventariosaliente (idInventarioSaliente) ON DELETE CASCADE,
    CONSTRAINT chk_silo_salida_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW v_estado_silos AS
SELECT s.idSilo,
       s.codigo,
       s.nombre,
       s.capacidad,
       s.activo,
       COALESCE(SUM(GREATEST(a.cantidadAsignada - COALESCE(x.cantidadSaliente, 0), 0)), 0) AS cantidadOcupada,
       s.capacidad - COALESCE(SUM(GREATEST(a.cantidadAsignada - COALESCE(x.cantidadSaliente, 0), 0)), 0) AS capacidadDisponible,
       MAX(CASE WHEN a.cantidadAsignada - COALESCE(x.cantidadSaliente, 0) > 0.0005 THEN ie.idProducto END) AS idProductoActual,
       COUNT(DISTINCT CASE WHEN a.cantidadAsignada - COALESCE(x.cantidadSaliente, 0) > 0.0005 THEN ie.idProducto END) AS productosActivos
FROM silos s
LEFT JOIN silo_asignaciones a ON a.idSilo = s.idSilo
LEFT JOIN inventarioentrante ie ON ie.idInventarioEntrante = a.idInventarioEntrante
LEFT JOIN (
    SELECT idAsignacion, SUM(cantidad) AS cantidadSaliente
    FROM silo_salidas
    GROUP BY idAsignacion
) x ON x.idAsignacion = a.idAsignacion
GROUP BY s.idSilo, s.codigo, s.nombre, s.capacidad, s.activo;