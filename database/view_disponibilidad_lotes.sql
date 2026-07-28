CREATE OR REPLACE VIEW v_disponibilidad_lotes AS
SELECT
    ie.idInventarioEntrante,
    ie.idProducto,
    ie.idPresentacion,
    ie.NumLote,
    ie.`idUbicación`,
    ie.sector,
    ie.CantidadEntrante AS stock_total,
    COALESCE(reservas.cantidad_reservada, 0) AS cantidad_reservada,
    ie.CantidadEntrante
        - COALESCE(salidas.cantidad_saliente, 0)
        - COALESCE(reservas.cantidad_reservada, 0) AS cantidad_disponible
FROM inventarioentrante ie
LEFT JOIN (
    SELECT idInventarioEntrante, SUM(cantidadSaliente) AS cantidad_saliente
    FROM inventariosaliente
    GROUP BY idInventarioEntrante
) salidas ON salidas.idInventarioEntrante = ie.idInventarioEntrante
LEFT JOIN (
    SELECT
        it.idInventarioEntrante,
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