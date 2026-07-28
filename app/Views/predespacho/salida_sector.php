<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$text = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$money = static fn ($value) => htmlspecialchars(number_format((float) $value, 2, '.', ''), ENT_QUOTES, 'UTF-8');
$sectorSeleccionado = $sectorSeleccionado ?? '';
$codigoPredespachoSeleccionado = $codigoPredespachoSeleccionado ?? '';
$sectores = $sectores ?? [];
$predespachos = $predespachos ?? [];
$items = $items ?? [];
$predespachoSeleccionado = $predespachoSeleccionado ?? null;
$idCabeceraSeleccionada = (int) ($predespachoSeleccionado['idCabeceraPredespacho'] ?? 0);
?>

<section class="panel report-panel admin-page" data-salida-sector data-api-url="<?= APP_URL ?>/public/predespacho_api.php" data-sector="<?= $text($sectorSeleccionado) ?>" data-predespacho-id="<?= $idCabeceraSeleccionada ?>" data-predespacho-codigo="<?= $text($codigoPredespachoSeleccionado) ?>">
    <div class="admin-heading">
        <div>
            <p class="eyebrow">Despacho fisico</p>
            <h1>Salida por Sector</h1>
            <p class="intro">Selecciona sector, elige un predespacho activo y registra entregas parciales o totales por producto.</p>
        </div>
        <a class="button-link button-link--secondary" href="<?= APP_URL ?>/predespacho">Volver a predespachos</a>
    </div>

    <div class="message message--success" role="status" data-sector-message hidden></div>
    <div class="message message--error" role="alert" data-sector-error hidden></div>

    <?php if (!empty($loadError)) : ?>
        <div class="message message--error" role="alert">No se pudo cargar la salida por sector: <?= $text($loadError) ?></div>
    <?php endif; ?>

    <section class="inventory-report">
        <div class="chart-title">
            <div>
                <h2>1. Selector de Sector</h2>
                <p class="quiet-text">Muestra solo sectores con productos pendientes en predespachos abiertos.</p>
            </div>
        </div>
        <form class="entry-form predespacho-sector-filter" method="get" action="<?= APP_URL ?>/predespacho/salida">
            <div class="form-field">
                <label for="sector">Sector</label>
                <select id="sector" name="sector" data-sector-select>
                    <option value="">Seleccione un sector</option>
                    <?php foreach ($sectores as $sector) : ?>
                        <option value="<?= $text($sector) ?>" <?= (string) $sectorSeleccionado === (string) $sector ? 'selected' : '' ?>><?= $text($sector) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions">
                <button class="button-link button-link--submit" type="submit">Consultar</button>
            </div>
        </form>
    </section>

    <?php if ($sectorSeleccionado !== '') : ?>
        <section class="inventory-report">
            <div class="chart-title">
                <div>
                    <h2>2. Predespachos del sector</h2>
                    <p class="quiet-text">Sector seleccionado: <?= $text($sectorSeleccionado) ?></p>
                </div>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Código Interno</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($predespachos)) : ?>
                            <tr><td colspan="5">No hay predespachos activos con productos pendientes en este sector.</td></tr>
                        <?php else : ?>
                            <?php foreach ($predespachos as $predespacho) : ?>
                                <?php $activo = (string) $codigoPredespachoSeleccionado === (string) $predespacho['codigoInterno']; ?>
                                <tr class="<?= $activo ? 'is-selected-row' : '' ?>">
                                    <td><strong><?= $text($predespacho['codigoInterno']) ?></strong></td>
                                    <td><?= $text($predespacho['nombreCliente']) ?></td>
                                    <td><?= $text($predespacho['fechaRetiro']) ?></td>
                                    <td><span class="status-pill <?= $predespacho['statusGeneralPredespacho'] === 'abierto' ? 'is-active' : 'is-pending' ?>"><?= $text($predespacho['statusGeneralPredespacho']) ?></span></td>
                                    <td class="table-actions">
                                        <a class="button-link button-link--secondary" href="<?= APP_URL ?>/predespacho/salida?sector=<?= rawurlencode((string) $sectorSeleccionado) ?>&predespacho=<?= rawurlencode((string) $predespacho['codigoInterno']) ?>">Ver productos</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($sectorSeleccionado !== '' && $predespachoSeleccionado) : ?>
        <section class="inventory-report predespacho-delivery-grid">
            <div class="predespacho-products-panel">
                <div class="chart-title">
                    <div>
                        <h2>Lista de Productos</h2>
                        <p class="quiet-text"><?= $text($predespachoSeleccionado['codigoInterno']) ?> | <?= $text($predespachoSeleccionado['nombreCliente']) ?></p>
                    </div>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Lote</th>
                                <th>Cant. Inicial</th>
                                <th>Entregado</th>
                                <th>Pendiente</th>
                                <th>Estado</th>
                                <th>✓</th>
                            </tr>
                        </thead>
                        <tbody data-products-body>
                            <?php if (empty($items)) : ?>
                                <tr><td colspan="7">Este predespacho no tiene productos para el sector seleccionado.</td></tr>
                            <?php else : ?>
                                <?php foreach ($items as $item) : ?>
                                    <?php
                                    $solicitada = (float) $item['cantidadSolicitada'];
                                    $despachada = (float) $item['cantidadDespachada'];
                                    $pendiente = max(0, (float) $item['cantidadPendiente']);
                                    $estadoClase = $despachada >= $solicitada ? 'is-complete' : ($despachada > 0 ? 'is-partial' : 'is-empty');
                                    ?>
                                    <tr class="predespacho-product-row <?= $estadoClase ?>" data-product-row data-item-id="<?= (int) $item['idItem'] ?>" data-inventario-id="<?= (int) $item['idInventarioEntrante'] ?>" data-producto="<?= $text($item['nombreProducto']) ?>" data-lote="<?= $text($item['NumLote']) ?>" data-disponible="<?= $money($pendiente) ?>">
                                        <td><strong><?= $text($item['nombreProducto']) ?></strong></td>
                                        <td><?= $text($item['NumLote']) ?></td>
                                        <td><?= $money($solicitada) ?></td>
                                        <td><?= $money($despachada) ?></td>
                                        <td><span class="stock-pill <?= $pendiente <= 0 ? 'stock-pill--risk' : '' ?>"><?= $money($pendiente) ?></span></td>
                                        <td><span class="delivery-dot <?= $estadoClase ?>" aria-hidden="true"></span><?= $estadoClase === 'is-complete' ? 'Completa' : ($estadoClase === 'is-partial' ? 'Parcial' : 'Sin movimiento') ?></td>
                                        <td><?= $item['estatusItemPredespacho'] === 'cerrado' ? '<span class="delivery-check">✓</span>' : '' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <aside class="predespacho-delivery-card">
                <h2>Registrar Entrega</h2>
                <p class="quiet-text" data-empty-delivery-message>← Selecciona un producto de la lista para registrar la entrega</p>
                <form class="entry-form" data-delivery-form novalidate>
                    <input type="hidden" name="idItem">
                    <input type="hidden" name="idCabeceraPredespacho" value="<?= $idCabeceraSeleccionada ?>">
                    <input type="hidden" name="sector" value="<?= $text($sectorSeleccionado) ?>">
                    <div class="form-field">
                        <label for="deliveryProducto">Producto</label>
                        <input id="deliveryProducto" type="text" data-delivery-producto disabled>
                    </div>
                    <div class="form-field">
                        <label for="deliveryLote">Lote</label>
                        <input id="deliveryLote" type="text" data-delivery-lote disabled>
                    </div>
                    <div class="form-field">
                        <label for="deliveryDisponible">Disponible</label>
                        <input id="deliveryDisponible" type="number" data-delivery-disponible disabled>
                    </div>
                    <div class="form-field">
                        <label for="cantidadEntregar">Cantidad a entregar</label>
                        <input id="cantidadEntregar" name="cantidadDespachada" type="number" min="0.01" step="0.01" data-delivery-cantidad disabled required>
                    </div>
                    <div class="form-actions">
                        <button class="button-link button-link--submit" type="submit" data-delivery-submit disabled>Guardar Entrega</button>
                    </div>
                </form>
            </aside>
        </section>
    <?php elseif ($sectorSeleccionado !== '' && $codigoPredespachoSeleccionado !== '') : ?>
        <div class="message message--error" role="alert">No se encontró el predespacho seleccionado.</div>
    <?php endif; ?>
</section>

<script src="<?= APP_URL ?>/public/js/predespacho-salida-sector.js"></script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
