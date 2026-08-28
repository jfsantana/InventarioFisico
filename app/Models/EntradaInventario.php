<?php

class EntradaInventario extends BaseModel
{
    public function obtenerProductos(): array
    {
        $statement = $this->db->query('SELECT idProducto, nombre FROM Producto ORDER BY nombre ASC');

        return $statement->fetchAll();
    }

    public function obtenerPresentaciones(): array
    {
        $statement = $this->db->query('SELECT idPresentacion, nombre FROM presentacion ORDER BY nombre ASC');

        return $statement->fetchAll();
    }

    public function obtenerUbicaciones(): array
    {
        $statement = $this->db->query('SELECT idUbicacion, nombre FROM ubicacion ORDER BY nombre ASC');

        return $statement->fetchAll();
    }

    public function obtenerTiposCompra(): array
    {
        $statement = $this->db->query('SELECT id, descripcion FROM tipo_compra ORDER BY descripcion ASC');

        return $statement->fetchAll();
    }

    public function obtenerProveedores(): array
    {
        $statement = $this->db->query('SELECT CardCode, CardName FROM proveedores ORDER BY CardName ASC, CardCode ASC');

        return $statement->fetchAll();
    }

    public function obtenerPaises(): array
    {
        $statement = $this->db->query('SELECT Code, Name FROM paises ORDER BY Name ASC');

        return $statement->fetchAll();
    }

    public function registrarEntrada(array $data): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO inventarioentrante
                (NumLote, idProducto, idPresentacion, `idUbicación`, CantidadEntrante, fecha, sector, idTipoCompra, CardCode, FabricanteCode, PaisCode)
             VALUES
                (:numLote, :idProducto, :idPresentacion, :idUbicacion, :cantidadEntrante, CURDATE(), :sector, :idTipoCompra, :cardCode, :fabricanteCode, :paisCode)'
        );

        $statement->execute([
            'numLote' => $data['NumLote'],
            'idProducto' => $data['idProducto'],
            'idPresentacion' => $data['idPresentacion'],
            'idUbicacion' => $data['idUbicacion'],
            'cantidadEntrante' => $data['CantidadEntrante'],
            'sector' => $data['Sector'],
            'idTipoCompra' => $data['idTipoCompra'],
            'cardCode' => $data['CardCode'],
            'fabricanteCode' => $data['FabricanteCode'],
            'paisCode' => $data['PaisCode'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function obtenerDocumentosEntrada(int $idInventarioEntrante): array
    {
        $statement = $this->db->prepare(
            'SELECT idDocumento, idInventarioEntrante, tipoDocumento, nombreOriginal,
                    nombreAlmacenado, rutaRelativa, mimeType, tamanoBytes, idUsuario, fechaCarga
             FROM entrada_documentos
             WHERE idInventarioEntrante = :idInventarioEntrante
             ORDER BY idDocumento ASC'
        );
        $statement->execute(['idInventarioEntrante' => $idInventarioEntrante]);

        $documentos = [];
        foreach ($statement->fetchAll() as $documento) {
            $documentos[$documento['tipoDocumento']] = $documento;
        }

        return $documentos;
    }

    public function obtenerTodosDocumentos(): array
    {
        $statement = $this->db->query(
            'SELECT idDocumento, idInventarioEntrante, tipoDocumento, nombreOriginal,
                    nombreAlmacenado, rutaRelativa, mimeType, tamanoBytes, idUsuario, fechaCarga
             FROM entrada_documentos
             ORDER BY idInventarioEntrante ASC, idDocumento ASC'
        );

        $documentosPorEntrada = [];
        foreach ($statement->fetchAll() as $documento) {
            $documentosPorEntrada[(int) $documento['idInventarioEntrante']][$documento['tipoDocumento']] = $documento;
        }

        return $documentosPorEntrada;
    }

    public function obtenerDocumentoPorId(int $idDocumento): ?array
    {
        $statement = $this->db->prepare(
            'SELECT idDocumento, idInventarioEntrante, tipoDocumento, nombreOriginal,
                    nombreAlmacenado, rutaRelativa, mimeType, tamanoBytes, idUsuario, fechaCarga
             FROM entrada_documentos
             WHERE idDocumento = :idDocumento
             LIMIT 1'
        );
        $statement->execute(['idDocumento' => $idDocumento]);
        $documento = $statement->fetch();

        return $documento ?: null;
    }

    public function guardarDocumento(int $idInventarioEntrante, array $documento): bool
    {
        $statement = $this->db->prepare(
            'INSERT INTO entrada_documentos
                (idInventarioEntrante, tipoDocumento, nombreOriginal, nombreAlmacenado, rutaRelativa, mimeType, tamanoBytes, idUsuario)
             VALUES
                (:idInventarioEntrante, :tipoDocumento, :nombreOriginal, :nombreAlmacenado, :rutaRelativa, :mimeType, :tamanoBytes, :idUsuario)
             ON DUPLICATE KEY UPDATE
                nombreOriginal = VALUES(nombreOriginal),
                nombreAlmacenado = VALUES(nombreAlmacenado),
                rutaRelativa = VALUES(rutaRelativa),
                mimeType = VALUES(mimeType),
                tamanoBytes = VALUES(tamanoBytes),
                idUsuario = VALUES(idUsuario),
                fechaCarga = CURRENT_TIMESTAMP'
        );

        return $statement->execute([
            'idInventarioEntrante' => $idInventarioEntrante,
            'tipoDocumento' => $documento['tipoDocumento'],
            'nombreOriginal' => $documento['nombreOriginal'],
            'nombreAlmacenado' => $documento['nombreAlmacenado'],
            'rutaRelativa' => $documento['rutaRelativa'],
            'mimeType' => $documento['mimeType'],
            'tamanoBytes' => $documento['tamanoBytes'],
            'idUsuario' => $documento['idUsuario'],
        ]);
    }

    public function eliminarDocumentosEntrada(int $idInventarioEntrante): bool
    {
        $statement = $this->db->prepare(
            'DELETE FROM entrada_documentos WHERE idInventarioEntrante = :idInventarioEntrante'
        );

        return $statement->execute(['idInventarioEntrante' => $idInventarioEntrante]);
    }

    public function obtenerEntradas(): array
    {
        $statement = $this->db->query(
            'SELECT ie.idInventarioEntrante,
                    ie.NumLote,
                    ie.idProducto,
                    p.nombre AS producto,
                    ie.idPresentacion,
                    pr.nombre AS presentacion,
                    ie.`idUbicación` AS idUbicacion,
                    u.nombre AS ubicacion,
                    ie.sector AS Sector,
                    ie.CantidadEntrante,
                    ie.fecha,
                    ie.idTipoCompra,
                    tc.descripcion AS tipoCompra,
                    ie.CardCode,
                    proveedor.CardName AS proveedor,
                    ie.FabricanteCode,
                    fabricante.CardName AS fabricante,
                    ie.PaisCode,
                    pais.Name AS pais,
                    COALESCE(SUM(ins.cantidadSaliente), 0) AS salidaTotal,
                    ie.CantidadEntrante - COALESCE(SUM(ins.cantidadSaliente), 0) AS disponible
             FROM inventarioentrante ie
             INNER JOIN Producto p ON p.idProducto = ie.idProducto
             INNER JOIN presentacion pr ON pr.idPresentacion = ie.idPresentacion
             INNER JOIN ubicacion u ON u.idUbicacion = ie.`idUbicación`
                         LEFT JOIN tipo_compra tc ON tc.id = ie.idTipoCompra
                         LEFT JOIN proveedores proveedor ON proveedor.CardCode = ie.CardCode
                         LEFT JOIN proveedores fabricante ON fabricante.CardCode = ie.FabricanteCode
                         LEFT JOIN paises pais ON pais.Code = ie.PaisCode
             LEFT JOIN inventariosaliente ins ON ins.idInventarioEntrante = ie.idInventarioEntrante
                             GROUP BY ie.idInventarioEntrante, ie.NumLote, ie.idProducto, p.nombre, ie.idPresentacion, pr.nombre, ie.`idUbicación`, u.nombre, ie.sector, ie.CantidadEntrante, ie.fecha, ie.idTipoCompra, tc.descripcion, ie.CardCode, proveedor.CardName, ie.FabricanteCode, fabricante.CardName, ie.PaisCode, pais.Name
             ORDER BY ie.fecha DESC, ie.idInventarioEntrante DESC'
        );

        return $statement->fetchAll();
    }

    public function obtenerSalidaTotal(int $idInventarioEntrante): float
    {
        $statement = $this->db->prepare(
            'SELECT COALESCE(SUM(cantidadSaliente), 0) AS salidaTotal
             FROM inventariosaliente
             WHERE idInventarioEntrante = :idInventarioEntrante'
        );
        $statement->execute(['idInventarioEntrante' => $idInventarioEntrante]);
        $resultado = $statement->fetch();

        return (float) ($resultado['salidaTotal'] ?? 0);
    }

    public function actualizarEntrada(int $idInventarioEntrante, array $data): bool
    {
        $statement = $this->db->prepare(
            'UPDATE inventarioentrante
             SET NumLote = :numLote,
                 idProducto = :idProducto,
                 idPresentacion = :idPresentacion,
                 `idUbicación` = :idUbicacion,
                 sector = :sector,
                 CantidadEntrante = :cantidadEntrante,
                 idTipoCompra = :idTipoCompra,
                 CardCode = :cardCode,
                 FabricanteCode = :fabricanteCode,
                 PaisCode = :paisCode
             WHERE idInventarioEntrante = :idInventarioEntrante'
        );

        return $statement->execute([
            'numLote' => $data['NumLote'],
            'idProducto' => $data['idProducto'],
            'idPresentacion' => $data['idPresentacion'],
            'idUbicacion' => $data['idUbicacion'],
            'sector' => $data['Sector'],
            'cantidadEntrante' => $data['CantidadEntrante'],
            'idTipoCompra' => $data['idTipoCompra'],
            'cardCode' => $data['CardCode'],
            'fabricanteCode' => $data['FabricanteCode'],
            'paisCode' => $data['PaisCode'],
            'idInventarioEntrante' => $idInventarioEntrante,
        ]);
    }

    public function eliminarEntrada(int $idInventarioEntrante): bool
    {
        $statement = $this->db->prepare(
            'DELETE FROM inventarioentrante
             WHERE idInventarioEntrante = :idInventarioEntrante'
        );

        $statement->execute(['idInventarioEntrante' => $idInventarioEntrante]);

        return $statement->rowCount() > 0;
    }
}
