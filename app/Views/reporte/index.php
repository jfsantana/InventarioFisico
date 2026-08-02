<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$productos = $productos ?? [];
$lotes = $lotes ?? [];
$idProducto = $idProducto ?? null;
$idInventarioEntrante = $idInventarioEntrante ?? null;
$modoExport = (bool) ($modoExport ?? false);
$fechaEmision = $fechaEmision ?? date('d/m/Y H:i');
$encabezado = $encabezado ?? null;
$movimientos = $movimientos ?? [];
$movimientosPaginados = $movimientosPaginados ?? $movimientos;
$paginaActual = (int) ($paginaActual ?? 1);
$totalPaginas = (int) ($totalPaginas ?? 1);
$porPagina = (int) ($porPagina ?? 30);
$porPaginaPermitidos = $porPaginaPermitidos ?? [20, 30, 50, 100];
$totalRegistros = (int) ($totalRegistros ?? count($movimientos));
$desdeRegistro = (int) ($desdeRegistro ?? 0);
$hastaRegistro = (int) ($hastaRegistro ?? 0);
$loadError = $loadError ?? null;

$buildPageUrl = static function (int $paginaDestino) use ($idProducto, $idInventarioEntrante, $porPagina): string {
    $query = [
        'idProducto' => $idProducto,
        'porPagina' => $porPagina,
        'pagina' => max(1, $paginaDestino),
    ];

    if (!empty($idInventarioEntrante)) {
        $query['idInventarioEntrante'] = $idInventarioEntrante;
    }

    return APP_URL . '/reporte?' . http_build_query($query);
};

$buildExportUrl = static function () use ($idProducto, $idInventarioEntrante): string {
    $query = [
        'idProducto' => $idProducto,
        'export' => 'pdf',
    ];

    if (!empty($idInventarioEntrante)) {
        $query['idInventarioEntrante'] = $idInventarioEntrante;
    }

    return APP_URL . '/reporte?' . http_build_query($query);
};

$movimientosRender = $modoExport ? $movimientos : $movimientosPaginados;
?>

<section class="panel report-panel">
    <p class="eyebrow">Reporte</p>
    <h1>Movimientos por lote</h1>
    <p class="intro">Seleccione un producto para ver todos sus movimientos cronológicos, incluyendo entradas, predespachos y salidas.</p>

    <?php if (!empty($loadError)) : ?>
        <div class="message message--error" role="alert">
            No se pudo cargar el reporte: <?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form class="entry-form entry-form--two-columns report-filter" method="get" action="<?= APP_URL ?>/reporte">
        <input type="hidden" name="porPagina" value="<?= (int) $porPagina ?>">
        <div class="form-field">
            <label for="idProducto">Producto</label>
            <select id="idProducto" name="idProducto" required onchange="this.form.submit()" data-product-search data-search-placeholder="Escriba codigo o nombre del producto">
                <option value="">Seleccione un producto</option>
                <?php foreach ($productos as $producto) : ?>
                    <option value="<?= (int) $producto['idProducto'] ?>" <?= (string) $idProducto === (string) $producto['idProducto'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field">
            <label for="idInventarioEntrante">Lote</label>
            <select id="idInventarioEntrante" name="idInventarioEntrante" <?= empty($idProducto) ? 'disabled' : '' ?>>
                <option value="">Todos los lotes</option>
                <?php foreach ($lotes as $lote) : ?>
                    <option value="<?= (int) $lote['idInventarioEntrante'] ?>" <?= (string) $idInventarioEntrante === (string) $lote['idInventarioEntrante'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lote['NumLote'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions form-actions--full">
            <button class="button-link button-link--submit" type="submit">Ver reporte</button>
            <?php if (!empty($encabezado)) : ?>
                <a class="button-link button-link--submit report-export-button" href="<?= htmlspecialchars($buildExportUrl(), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Generar PDF</a>
            <?php endif; ?>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menu</a>
        </div>
    </form>

    <?php if (!empty($idProducto) && empty($loadError) && empty($movimientos)) : ?>
        <div class="message message--error" role="alert">
            No se encontro informacion para el producto seleccionado.
        </div>
    <?php endif; ?>

    <?php if (!empty($encabezado) && !empty($movimientos)) : ?>
        <article class="inventory-report">
            <header class="report-print-header">
                <div class="print-brand"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="print-title">Reporte de Movimientos de Inventario</div>
                <div class="print-meta-grid">
                    <div><strong>Fecha de emisión:</strong> <?= htmlspecialchars($fechaEmision, ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Producto:</strong> <?= htmlspecialchars($encabezado['producto'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Lote:</strong> <?= htmlspecialchars($encabezado['NumLote'] ?? 'Todos los lotes', ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Registros:</strong> <?= (int) $totalRegistros ?></div>
                </div>
            </header>

            <div class="report-heading">
                <div><span>Producto:</span> <?= htmlspecialchars($encabezado['producto'], ENT_QUOTES, 'UTF-8') ?></div>
                <div><span>Lote:</span> <?= htmlspecialchars($encabezado['NumLote'] ?? 'Todos los lotes', ENT_QUOTES, 'UTF-8') ?></div>
                <?php if (!empty($idInventarioEntrante)) : ?>
                    <div><span>Presentacion:</span> <?= htmlspecialchars($encabezado['presentacion'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <div><span>Ubicacion:</span> <?= htmlspecialchars($encabezado['ubicacion'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <?php if (!$modoExport) : ?>
                <div class="report-pagination-toolbar">
                    <div class="report-pagination-summary">
                        Mostrando <?= (int) $desdeRegistro ?>-<?= (int) $hastaRegistro ?> de <?= (int) $totalRegistros ?> movimientos
                    </div>
                    <form method="get" action="<?= APP_URL ?>/reporte" class="report-per-page-form">
                        <input type="hidden" name="idProducto" value="<?= (int) $idProducto ?>">
                        <?php if (!empty($idInventarioEntrante)) : ?>
                            <input type="hidden" name="idInventarioEntrante" value="<?= (int) $idInventarioEntrante ?>">
                        <?php endif; ?>
                        <input type="hidden" name="pagina" value="1">
                        <label for="porPagina">Filas por página</label>
                        <select id="porPagina" name="porPagina" onchange="this.form.submit()">
                            <?php foreach ($porPaginaPermitidos as $opcionPorPagina) : ?>
                                <option value="<?= (int) $opcionPorPagina ?>" <?= (int) $porPagina === (int) $opcionPorPagina ? 'selected' : '' ?>><?= (int) $opcionPorPagina ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            <?php endif; ?>

            <div class="report-table-wrap">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cod de Predespacho</th>
                            <th>Monto Predespacho</th>
                            <th>Entrada</th>
                            <th>Salida</th>
                            <th>Saldo</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientosRender as $movimiento) : ?>
                            <?php
                            $tipoMovimiento = (string) ($movimiento['tipo'] ?? '');
                            $rowClass = $tipoMovimiento === 'entrada'
                                ? 'report-row report-row--entrada'
                                : ($tipoMovimiento === 'predespacho' ? 'report-row report-row--predespacho' : 'report-row report-row--salida');
                            ?>
                            <tr class="<?= htmlspecialchars($rowClass, ENT_QUOTES, 'UTF-8') ?>">
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($movimiento['fecha'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($movimiento['codPredespacho'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= $movimiento['montoPredespacho'] !== '' ? htmlspecialchars(number_format((float) $movimiento['montoPredespacho'], 2), ENT_QUOTES, 'UTF-8') : '' ?></td>
                                <td><?= $movimiento['entrada'] !== '' ? htmlspecialchars(number_format((float) $movimiento['entrada'], 2), ENT_QUOTES, 'UTF-8') : '' ?></td>
                                <td><?= $movimiento['salida'] !== '' ? htmlspecialchars(number_format((float) $movimiento['salida'], 2), ENT_QUOTES, 'UTF-8') : '' ?></td>
                                <td><?= htmlspecialchars(number_format((float) $movimiento['saldo'], 2), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($movimiento['observaciones'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!$modoExport && $totalPaginas > 1) : ?>
                <nav class="report-pagination" aria-label="Paginación del reporte">
                    <a class="button-link button-link--secondary <?= $paginaActual <= 1 ? 'is-disabled' : '' ?>" href="<?= $paginaActual <= 1 ? '#' : htmlspecialchars($buildPageUrl($paginaActual - 1), ENT_QUOTES, 'UTF-8') ?>" <?= $paginaActual <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Anterior</a>

                    <div class="report-pagination-pages">
                        <?php for ($page = 1; $page <= $totalPaginas; $page++) : ?>
                            <?php if ($page === 1 || $page === $totalPaginas || abs($page - $paginaActual) <= 1) : ?>
                                <a class="report-page-link <?= $page === $paginaActual ? 'is-active' : '' ?>" href="<?= htmlspecialchars($buildPageUrl($page), ENT_QUOTES, 'UTF-8') ?>"><?= $page ?></a>
                            <?php elseif ($page === 2 || $page === $totalPaginas - 1) : ?>
                                <span class="report-page-dots">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>

                    <a class="button-link button-link--secondary <?= $paginaActual >= $totalPaginas ? 'is-disabled' : '' ?>" href="<?= $paginaActual >= $totalPaginas ? '#' : htmlspecialchars($buildPageUrl($paginaActual + 1), ENT_QUOTES, 'UTF-8') ?>" <?= $paginaActual >= $totalPaginas ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Siguiente</a>
                </nav>
            <?php endif; ?>

            <footer class="report-print-footer">
                <div><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?> · Documento interno de control</div>
                <div>Fecha de emisión: <?= htmlspecialchars($fechaEmision, ENT_QUOTES, 'UTF-8') ?></div>
            </footer>
        </article>
    <?php endif; ?>
</section>

<script src="<?= APP_URL ?>/public/js/searchable-select.js"></script>
<?php if ($modoExport) : ?>
<script>
window.addEventListener('load', function () {
    window.print();
});
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
