CREATE DATABASE IF NOT EXISTS inventariofisico
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE inventariofisico;

CREATE TABLE IF NOT EXISTS Producto (
    idProducto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL
);

CREATE TABLE IF NOT EXISTS inventarioentrante (
    idInventarioEntrante INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    NumLote VARCHAR(80) NOT NULL,
    idProducto INT UNSIGNED NOT NULL,
    idPresentacion INT UNSIGNED NULL,
    idUbicacion INT UNSIGNED NULL,
    CantidadEntrante DECIMAL(12, 2) NOT NULL DEFAULT 0,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sector VARCHAR(50) NOT NULL,
    INDEX idx_inventarioentrante_producto (idProducto),
    CONSTRAINT fk_inventarioentrante_producto
        FOREIGN KEY (idProducto) REFERENCES Producto (idProducto)
);

CREATE TABLE IF NOT EXISTS inventariosaliente (
    idInventarioSaliente INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    idInventarioEntrante INT UNSIGNED NOT NULL,
    NE VARCHAR(80) NOT NULL,
    cantidadSaliente DECIMAL(12, 2) NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inventariosaliente_entrante (idInventarioEntrante),
    CONSTRAINT fk_inventariosaliente_entrante
        FOREIGN KEY (idInventarioEntrante) REFERENCES inventarioentrante (idInventarioEntrante)
);

INSERT INTO Producto (nombre)
VALUES
    ('Producto de ejemplo')
ON DUPLICATE KEY UPDATE nombre = nombre;