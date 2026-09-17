<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$productos = $productos ?? [];
$idsProducto = $idsProducto ?? [];
$saldos = $saldos ?? [];
$resumen = $resumen ?? [];
$loadError = $loadError ?? null;
$fechaEmision = $fechaEmision ?? date('d/m/Y H:i');
$paginaActual = (int) ($paginaActual ?? 1);
$totalPaginas = (int) ($totalPaginas ?? 1);
$porPagina = (int) ($porPagina ?? 30);
$porPaginaPermitidos = $porPaginaPermitidos ?? [20, 30, 50, 100];
$totalRegistros = (int) ($totalRegistros ?? 0);
$desdeRegistro = (int) ($desdeRegistro ?? 0);
$hastaRegistro = (int) ($hastaRegistro ?? 0);
$totalFisico = (float) ($resumen['total_fisico'] ?? 0);
$totalReservado = (float) ($resumen['total_reservado'] ?? 0);
$totalDisponible = (float) ($resumen['total_disponible'] ?? 0);
$exportQuery = http_build_query(['idProducto' => $idsProducto, 'export' => 'excel']);
$productosPorId = [];
foreach ($productos as $producto) {
    $productosPorId[(int) $producto['idProducto']] = $producto;
}
$productosJson = array_map(static fn (array $producto): array => [
    'id' => (int) $producto['idProducto'],
    'nombre' => (string) $producto['nombre'],
], $productos);
$buildPageUrl = static function (int $paginaDestino) use ($idsProducto, $porPagina): string {
    return APP_URL . '/reporte/saldos?' . http_build_query([
        'idProducto' => $idsProducto,
        'porPagina' => $porPagina,
        'pagina' => max(1, $paginaDestino),
    ]);
};
?>

<section class="panel report-panel lot-balance-panel">
    <p class="eyebrow">Reporte</p>
    <h1>Saldo de Productos por Lote</h1>
    <p class="intro">Existencia física, mercancía reservada y cantidad disponible de cada lote.</p>

    <?php if (!empty($loadError)) : ?>
        <div class="message message--error" role="alert">
            No se pudo cargar el reporte: <?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form class="lot-balance-filter" method="get" action="<?= APP_URL ?>/reporte/saldos" data-lot-balance-filter>
        <input type="hidden" name="porPagina" value="<?= $porPagina ?>">
        <div class="lot-product-picker">
            <div class="lot-product-picker__header">
                <div>
                    <label for="lot-product-search">Productos</label>
                    <span data-selection-summary><?= empty($idsProducto) ? 'Todos los productos' : count($idsProducto) . (count($idsProducto) === 1 ? ' seleccionado' : ' seleccionados') ?></span>
                </div>
                <button type="button" class="lot-picker-clear" data-clear-products>Mostrar todos</button>
            </div>
            <div class="lot-product-combobox">
                <input id="lot-product-search" class="lot-product-search" type="search" placeholder="Escriba código o nombre para agregar" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="lot-product-results" data-product-filter>
                <div id="lot-product-results" class="lot-product-results" role="listbox" data-product-results hidden></div>
            </div>
            <div class="lot-product-selected" data-selected-products aria-live="polite">
                <?php foreach ($idsProducto as $idSeleccionado) : ?>
                    <?php if (isset($productosPorId[$idSeleccionado])) : ?>
                        <span class="lot-product-chip" data-selected-id="<?= (int) $idSeleccionado ?>">
                            <input type="hidden" name="idProducto[]" value="<?= (int) $idSeleccionado ?>">
                            <span><?= htmlspecialchars($productosPorId[$idSeleccionado]['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                            <button type="button" data-remove-product aria-label="Quitar <?= htmlspecialchars($productosPorId[$idSeleccionado]['nombre'], ENT_QUOTES, 'UTF-8') ?>">&times;</button>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-actions lot-balance-actions">
            <button class="button-link button-link--submit" type="submit">Aplicar filtro</button>
            <a class="button-link button-link--submit report-export-button report-export-button--excel" href="<?= APP_URL ?>/reporte/saldos?<?= htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8') ?>">Exportar Excel</a>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/reporte">Movimientos por lote</a>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menú</a>
        </div>
    </form>

    <?php if (empty($loadError)) : ?>
        <div class="lot-balance-summary" aria-label="Resumen del reporte">
            <div><span>Lotes</span><strong><?= $totalRegistros ?></strong></div>
            <div><span>Saldo físico</span><strong><?= number_format($totalFisico, 3) ?></strong></div>
            <div><span>Reservado</span><strong><?= number_format($totalReservado, 3) ?></strong></div>
            <div><span>Disponible</span><strong><?= number_format($totalDisponible, 3) ?></strong></div>
        </div>

        <?php if (empty($saldos)) : ?>
            <div class="message message--error" role="status">No se encontraron lotes para los productos seleccionados.</div>
        <?php else : ?>
            <article class="inventory-report lot-balance-report">
                <div class="report-pagination-toolbar">
                    <div class="report-pagination-summary">Mostrando <?= $desdeRegistro ?>-<?= $hastaRegistro ?> de <?= $totalRegistros ?> lotes · Emitido el <?= htmlspecialchars($fechaEmision, ENT_QUOTES, 'UTF-8') ?></div>
                    <form method="get" action="<?= APP_URL ?>/reporte/saldos" class="report-per-page-form">
                        <?php foreach ($idsProducto as $idSeleccionado) : ?>
                            <input type="hidden" name="idProducto[]" value="<?= (int) $idSeleccionado ?>">
                        <?php endforeach; ?>
                        <input type="hidden" name="pagina" value="1">
                        <label for="saldoPorPagina">Filas por página</label>
                        <select id="saldoPorPagina" name="porPagina" onchange="this.form.submit()">
                            <?php foreach ($porPaginaPermitidos as $opcionPorPagina) : ?>
                                <option value="<?= (int) $opcionPorPagina ?>" <?= $porPagina === (int) $opcionPorPagina ? 'selected' : '' ?>><?= (int) $opcionPorPagina ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="report-table-wrap">
                    <table class="report-table lot-balance-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Lote</th>
                                <th>Fecha entrada</th>
                                <th>Presentación</th>
                                <th>Ubicación</th>
                                <th>Entrada</th>
                                <th>Salidas</th>
                                <th>Reservado</th>
                                <th>Saldo físico</th>
                                <th>Disponible</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($saldos as $saldo) : ?>
                                <tr>
                                    <td data-label="Producto">
                                        <strong><?= htmlspecialchars($saldo['producto'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <small><?= htmlspecialchars($saldo['codigoInterno'], ENT_QUOTES, 'UTF-8') ?></small>
                                    </td>
                                    <td data-label="Lote"><?= htmlspecialchars($saldo['NumLote'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Fecha entrada"><?= htmlspecialchars(date('d/m/Y', strtotime($saldo['fechaEntrada'])), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Presentación"><?= htmlspecialchars($saldo['presentacion'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Ubicación">
                                        <?= htmlspecialchars($saldo['ubicacion'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($saldo['sector'])) : ?><small><?= htmlspecialchars($saldo['sector'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                    </td>
                                    <td data-label="Entrada"><?= number_format((float) $saldo['stock_total'], 3) ?></td>
                                    <td data-label="Salidas"><?= number_format((float) $saldo['cantidad_saliente'], 3) ?></td>
                                    <td data-label="Reservado"><?= number_format((float) $saldo['cantidad_reservada'], 3) ?></td>
                                    <td data-label="Saldo físico"><strong><?= number_format((float) $saldo['saldo_fisico'], 3) ?></strong></td>
                                    <td data-label="Disponible"><strong><?= number_format((float) $saldo['cantidad_disponible'], 3) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPaginas > 1) : ?>
                    <nav class="report-pagination" aria-label="Paginación del saldo por lote">
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
            </article>
        <?php endif; ?>
    <?php endif; ?>
</section>

<script type="application/json" id="lot-balance-products"><?= json_encode($productosJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= APP_URL ?>/public/js/reporte-saldos.js?v=<?= filemtime(__DIR__ . '/../../../public/js/reporte-saldos.js') ?>"></script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>