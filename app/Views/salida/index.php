<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$text = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$money = static fn ($value) => htmlspecialchars(number_format((float) $value, 2, '.', ''), ENT_QUOTES, 'UTF-8');
$sectores = $sectores ?? [];
$sectorSeleccionado = $sectorSeleccionado ?? '';
$predespachos = $predespachos ?? [];
$codigoPredespachoSeleccionado = $codigoPredespachoSeleccionado ?? '';
$predespachoSeleccionado = $predespachoSeleccionado ?? null;
$items = $items ?? [];
$idCabeceraSeleccionada = (int) ($predespachoSeleccionado['idCabeceraPredespacho'] ?? 0);
?>

<section class="panel report-panel admin-page salida-panel" data-salida-predespacho data-submit-url="<?= APP_URL ?>/salida/guardar" data-predespacho-codigo="<?= $text($codigoPredespachoSeleccionado) ?>">
    <div class="admin-heading">
        <div>
            <p class="eyebrow">Inventario saliente</p>
            <h1>Registrar entrega</h1>
            <p class="intro">Selecciona el predespacho, revisa sus productos por sector y registra la cantidad entregada por producto/lote.</p>
        </div>
        <?php if (Auth::check()) : ?>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/salida/detalle">Corregir salidas</a>
        <?php endif; ?>
        <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menu</a>
    </div>

    <div class="message message--success" role="status" data-salida-message <?= empty($successMessage) ? 'hidden' : '' ?>><?= $text($successMessage ?? '') ?></div>
    <div class="message message--error" role="alert" data-salida-error hidden></div>

    <?php if (!empty($loadError)) : ?>
        <div class="message message--error" role="alert">No se pudieron cargar los datos: <?= $text($loadError) ?></div>
    <?php endif; ?>

    <section class="inventory-report">
        <div class="chart-title">
            <div>
                <h2>1. Seleccione el Predespacho</h2>
                <p class="quiet-text">Solo aparecen cabeceras abiertas o pendientes por entrega.</p>
            </div>
        </div>
        <?php if (empty($predespachos)) : ?>
            <div class="message message--error" role="status">No hay predespachos abiertos o pendientes por entrega.</div>
        <?php else : ?>
            <form class="entry-form predespacho-sector-filter" method="get" action="<?= APP_URL ?>/salida">
                <div class="form-field">
                    <label for="predespacho">Predespacho</label>
                    <select id="predespacho" name="predespacho" onchange="this.form.submit()">
                        <option value="">-- Seleccione un predespacho --</option>
                        <?php foreach ($predespachos as $predespacho) : ?>
                            <option value="<?= $text($predespacho['codigoInterno']) ?>" <?= (string) $codigoPredespachoSeleccionado === (string) $predespacho['codigoInterno'] ? 'selected' : '' ?>>
                                <?= $text($predespacho['codigoInterno']) ?> | <?= $text($predespacho['nombreCliente']) ?> | <?= $text($predespacho['fechaRetiro']) ?> | <?= $text($predespacho['statusGeneralPredespacho']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <?php if ($predespachoSeleccionado) : ?>
        <section class="inventory-report predespacho-delivery-grid" data-delivery-section>
            <div class="predespacho-products-panel">
                <div class="chart-title">
                    <div>
                        <h2>Productos del predespacho</h2>
                        <p class="quiet-text"><?= $text($predespachoSeleccionado['codigoInterno']) ?> | <?= $text($predespachoSeleccionado['nombreCliente']) ?></p>
                    </div>
                </div>
                <form class="entry-form predespacho-sector-filter" method="get" action="<?= APP_URL ?>/salida">
                    <input type="hidden" name="predespacho" value="<?= $text($codigoPredespachoSeleccionado) ?>">
                    <div class="form-field">
                        <label for="sector">Sector</label>
                        <select id="sector" name="sector" onchange="this.form.submit()">
                            <option value="">Todos los Sectores</option>
                            <?php foreach ($sectores as $sector) : ?>
                                <option value="<?= $text($sector) ?>" <?= (string) $sectorSeleccionado === (string) $sector ? 'selected' : '' ?>><?= $text($sector) ?></option>
                            <?php endforeach; ?>
                        </select>
                        </br>
                    </div>
                </form>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Lote</th>
                                <th>Sector</th>
                                <th>Presentacion</th>
                                <th>Unidad</th>
                                <th>Inicial</th>
                                <th>Entregado</th>
                                <th>Pendiente</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)) : ?>
                                <tr><td colspan="9">Este predespacho no tiene productos para el sector seleccionado.</td></tr>
                            <?php else : ?>
                                <?php foreach ($items as $item) : ?>
                                    <?php
                                    $solicitada = (float) $item['cantidadSolicitada'];
                                    $entregada = (float) $item['cantidadDespachada'];
                                    $pendiente = max(0, (float) $item['cantidadPendiente']);
                                    $unidad = $item['unidad'] ?? null;
                                    $estadoClase = $entregada >= $solicitada ? 'is-complete' : ($entregada > 0 ? 'is-partial' : 'is-empty');
                                    $estadoTexto = $estadoClase === 'is-complete' ? 'Comp.' : ($estadoClase === 'is-partial' ? 'Parc.' : 'Pend.');
                                    ?>
                                    <tr id="fila-<?= (int) $item['idItem'] ?>" class="fila-producto predespacho-product-row <?= $estadoClase ?>" onclick="cargarProducto(<?= (int) $item['idItem'] ?>, <?= htmlspecialchars(json_encode((string) $item['nombreProducto'], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode((string) $item['NumLote'], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>, <?= $money($pendiente) ?>)">
                                        <td><strong><?= $text($item['nombreProducto']) ?></strong></td>
                                        <td><?= $text($item['NumLote']) ?></td>
                                        <td><?= $text($item['sector']) ?></td>
                                        <td><?= $text($item['presentacion'] ?? '') ?></td>
                                        <td><?= $unidad === null ? 'N/D' : $money($unidad) ?></td>
                                        <td><?= $money($solicitada) ?></td>
                                        <td><?= $money($entregada) ?></td>
                                        <td><span class="stock-pill <?= $pendiente <= 0 ? 'stock-pill--risk' : '' ?>"><?= $money($pendiente) ?></span></td>
                                        <td><span class="delivery-dot <?= $estadoClase ?>" aria-hidden="true"></span><?= $text($estadoTexto) ?> <?= $item['estatusItemPredespacho'] === 'cerrado' ? '<span class="delivery-check">✓</span>' : '' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <aside class="predespacho-delivery-card">
                <h2>Registrar Entrega</h2>
                <p class="quiet-text" id="mensaje-seleccion">← Haz clic en un producto de la lista</p>
                <form id="form-entrega" class="entry-form" method="post" action="<?= APP_URL ?>/salida/guardar">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" id="hid_inventarioId" name="idItem">
                    <input type="hidden" name="idCabeceraPredespacho" value="<?= $idCabeceraSeleccionada ?>">
                    <input type="hidden" name="predespachoId" value="<?= $text($codigoPredespachoSeleccionado) ?>">
                    <input type="hidden" name="sector" value="<?= $text($sectorSeleccionado) ?>">

                    <div class="form-field">
                        <label for="txt_producto">Producto</label>
                        <input readonly id="txt_producto" type="text">
                    </div>
                    <div class="delivery-card-pair">
                        <div class="form-field">
                            <label for="txt_lote">Lote</label>
                            <input readonly id="txt_lote" type="text">
                        </div>
                        <div class="form-field">
                            <label for="txt_disponible">Disponible</label>
                            <input readonly id="txt_disponible" type="number">
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="txt_cantidad">Cantidad</label>
                        <input type="number" id="txt_cantidad" name="cantidadDespachada" min="0.01" step="0.01" disabled>
                    </div>
                    <div class="form-actions">
                        <button class="button-link button-link--submit" type="submit" disabled>Guardar Entrega</button>
                    </div>
                </form>
            </aside>
        </section>
    <?php elseif ($codigoPredespachoSeleccionado !== '') : ?>
        <div class="message message--error" role="alert">No se encontró el predespacho seleccionado.</div>
    <?php endif; ?>
</section>

<script src="<?= APP_URL ?>/public/js/salida.js?v=<?= filemtime(__DIR__ . '/../../../public/js/salida.js') ?>"></script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
