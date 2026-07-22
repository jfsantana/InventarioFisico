<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$formatDate = static fn ($date) => htmlspecialchars(date('d/m/Y', strtotime((string) $date)), ENT_QUOTES, 'UTF-8');
$dateValue = static fn ($date) => htmlspecialchars(date('Y-m-d', strtotime((string) $date)), ENT_QUOTES, 'UTF-8');
$money = static fn ($value) => htmlspecialchars(number_format((float) $value, 2), ENT_QUOTES, 'UTF-8');
$text = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$productosSalida = [];
$presentacionesSalida = [];
$ubicacionesSalida = [];
foreach ($lotes as $lote) {
    if (isset($lote['idProducto'])) {
        $productosSalida[(int) $lote['idProducto']] = $lote['producto'];
    }
    if (isset($lote['idPresentacion'])) {
        $presentacionesSalida[(int) $lote['idPresentacion']] = $lote['presentacion'] ?? '';
    }
    if (isset($lote['idUbicacion'])) {
        $ubicacionesSalida[(int) $lote['idUbicacion']] = $lote['ubicacion'] ?? '';
    }
}
?>

<section class="panel report-panel correction-panel correction-table-page" data-correction-page data-page-type="salida" data-delete-endpoint="<?= APP_URL ?>/salida/eliminar" data-csrf-token="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <p class="eyebrow">CORRECCION OPERATIVA</p>
    <h1>Salidas registradas</h1>
    <p class="intro">Revise las entregas registradas y corrija el lote asociado, la Nota de Entrega o la cantidad saliente.</p>

    <?php if (!empty($message)) : ?>
        <div class="message message--<?= $messageType === 'error' ? 'error' : 'success' ?> correction-server-message" role="alert" data-server-message data-message-type="<?= $messageType === 'error' ? 'error' : 'success' ?>">
            <?= $text($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($loadError)) : ?>
        <div class="message message--error" role="alert">
            No se pudieron cargar las salidas: <?= $text($loadError) ?>
        </div>
    <?php endif; ?>

    <div class="form-actions correction-actions">
        <a class="button-link" href="<?= APP_URL ?>/salida">Registrar nueva salida</a>
        <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menú</a>
    </div>

    <button class="filter-toggle" type="button" data-filter-toggle aria-expanded="false">Mostrar filtros 🔽</button>

    <section class="correction-filters" data-filter-panel aria-label="Filtros de salidas registradas">
        <label>
            Buscar
            <input type="search" data-filter-search placeholder="Producto, lote o ubicacion" aria-label="Buscar por producto, lote o ubicacion">
        </label>
        <label>
            Presentacion
            <select data-filter-presentation aria-label="Filtrar por presentacion">
                <option value="">Todas</option>
                <?php foreach ($presentacionesSalida as $idPresentacion => $presentacionNombre) : ?>
                    <option value="<?= (int) $idPresentacion ?>"><?= $text($presentacionNombre) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Ubicacion
            <select data-filter-location aria-label="Filtrar por ubicacion">
                <option value="">Todas</option>
                <?php foreach ($ubicacionesSalida as $idUbicacion => $ubicacionNombre) : ?>
                    <option value="<?= (int) $idUbicacion ?>"><?= $text($ubicacionNombre) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Desde
            <input type="date" data-filter-from aria-label="Fecha desde">
        </label>
        <label>
            Hasta
            <input type="date" data-filter-to aria-label="Fecha hasta">
        </label>
        <button class="filter-clear" type="button" data-clear-filters>Limpiar filtros</button>
        <strong class="filter-count" data-visible-count>Mostrando 0 de 0 registros</strong>
    </section>

    <div class="correction-table-shell">
        <?php if (empty($salidas)) : ?>
            <div class="message message--error" role="status">No hay salidas registradas para corregir.</div>
        <?php else : ?>
            <table class="correction-table">
                <thead>
                    <tr>
                        <th><button type="button" data-sort="id" data-type="number"># <span data-sort-indicator>↕</span></button></th>
                        <th><button type="button" data-sort="fecha">Fecha <span data-sort-indicator>↕</span></button></th>
                        <th><button type="button" data-sort="producto">Producto <span data-sort-indicator>↕</span></button></th>
                        <th><button type="button" data-sort="lote">Lote <span data-sort-indicator>↕</span></button></th>
                        <th><button type="button" data-sort="presentacion">Presentacion <span data-sort-indicator>↕</span></button></th>
                        <th><button type="button" data-sort="ubicacion">Ubicacion <span data-sort-indicator>↕</span></button></th>
                        <th><button type="button" data-sort="cantidad" data-type="number">Cantidad entregada <span data-sort-indicator>↕</span></button></th>
                        <th class="actions-column">Acciones</th>
                    </tr>
                </thead>
                <tbody data-correction-body>
                    <?php foreach ($salidas as $salida) : ?>
                        <tr data-id="<?= (int) $salida['idInventarioSaliente'] ?>"
                            data-entrada-id="<?= (int) $salida['idInventarioEntrante'] ?>"
                            data-fecha="<?= $dateValue($salida['fecha']) ?>"
                            data-product-id="<?= (int) $salida['idProducto'] ?>"
                            data-producto="<?= $text($salida['producto']) ?>"
                            data-lote="<?= $text($salida['NumLote']) ?>"
                            data-presentation="<?= (int) ($salida['idPresentacion'] ?? 0) ?>"
                            data-presentacion="<?= $text($salida['presentacion'] ?? '') ?>"
                            data-location="<?= (int) ($salida['idUbicacion'] ?? 0) ?>"
                            data-ubicacion="<?= $text($salida['ubicacion'] ?? '') ?>"
                            data-cantidad="<?= $text($salida['cantidadSaliente']) ?>"
                            data-ne="<?= $text($salida['NE']) ?>"
                            data-disponible="<?= $text($salida['disponibleSinEstaSalida']) ?>"
                            data-search="<?= $text($salida['producto'] . ' ' . $salida['NumLote'] . ' ' . ($salida['ubicacion'] ?? '')) ?>">
                            <td data-label="#"><?= (int) $salida['idInventarioSaliente'] ?></td>
                            <td data-label="Fecha"><?= $formatDate($salida['fecha']) ?></td>
                            <td data-label="Producto"><strong><?= $text($salida['producto']) ?></strong></td>
                            <td data-label="Lote"><?= $text($salida['NumLote']) ?></td>
                            <td data-label="Presentacion"><?= $text($salida['presentacion'] ?? '') ?></td>
                            <td data-label="Ubicacion"><?= $text($salida['ubicacion'] ?? '') ?></td>
                            <td data-label="Cantidad entregada"><?= $money($salida['cantidadSaliente']) ?></td>
                            <td class="actions-column" data-label="Acciones">
                                <button class="icon-action icon-action--edit" type="button" data-edit-row aria-label="Editar salida #<?= (int) $salida['idInventarioSaliente'] ?>">✏️</button>
                                <button class="icon-action icon-action--delete" type="button" data-delete-row aria-label="Eliminar salida #<?= (int) $salida['idInventarioSaliente'] ?>">🗑️</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <footer class="correction-pagination" aria-label="Paginacion de salidas">
        <label>
            Registros por página:
            <select data-per-page>
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </label>
        <div class="pagination-buttons" data-pagination-buttons></div>
        <span data-pagination-info>Pagina 1 de 1 | Total: 0 registros</span>
    </footer>

    <div class="correction-modal" data-edit-modal hidden role="dialog" aria-modal="true" aria-labelledby="salida-modal-title">
        <form class="correction-modal-card" method="post" action="<?= APP_URL ?>/salida/actualizar" data-correction-modal-form novalidate>
            <?= Auth::csrfField() ?>
            <header>
                <div>
                    <h2 id="salida-modal-title" data-modal-title>Editar Salida</h2>
                    <p data-modal-subtitle></p>
                </div>
                <button type="button" class="modal-close" data-modal-close aria-label="Cerrar modal">×</button>
            </header>
            <input type="hidden" name="idInventarioSaliente">
            <input type="hidden" name="NE">
            <div class="correction-modal-grid">
                <label>
                    Producto
                    <select id="salidaProductoSelect" name="productoVista" required data-product-search data-search-placeholder="Escriba codigo o nombre del producto">
                        <?php foreach ($productosSalida as $idProducto => $productoNombre) : ?>
                            <option value="<?= (int) $idProducto ?>"><?= $text($productoNombre) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Lote
                    <select name="idInventarioEntrante" required>
                        <?php foreach ($lotes as $lote) : ?>
                            <option value="<?= (int) $lote['idInventarioEntrante'] ?>"
                                data-product-id="<?= (int) ($lote['idProducto'] ?? 0) ?>"
                                data-presentation-id="<?= (int) ($lote['idPresentacion'] ?? 0) ?>"
                                data-location-id="<?= (int) ($lote['idUbicacion'] ?? 0) ?>"
                                data-disponible="<?= $text($lote['disponible']) ?>">
                                <?= $text($lote['NumLote']) ?> / <?= $text($lote['producto']) ?> / Disponible: <?= $money($lote['disponible']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Presentacion
                    <select name="presentacionVista" required>
                        <?php foreach ($presentacionesSalida as $idPresentacion => $presentacionNombre) : ?>
                            <option value="<?= (int) $idPresentacion ?>"><?= $text($presentacionNombre) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Ubicacion
                    <select name="ubicacionVista" required>
                        <?php foreach ($ubicacionesSalida as $idUbicacion => $ubicacionNombre) : ?>
                            <option value="<?= (int) $idUbicacion ?>"><?= $text($ubicacionNombre) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Cantidad
                    <input name="cantidadSaliente" data-modal-quantity type="number" min="0.01" step="0.01" required>
                </label>
            </div>
            <dl class="modal-summary">
                <div><dt>Salidas registradas</dt><dd data-summary-salidas>0.00</dd></div>
                <div><dt>Disponible calculado</dt><dd data-summary-disponible>0.00</dd></div>
            </dl>
            <footer class="modal-actions">
                <button type="button" class="button-link button-link--secondary" data-modal-close>Cancelar</button>
                <button type="submit" class="button-link button-link--submit">Guardar corrección</button>
            </footer>
        </form>
    </div>

    <div class="correction-modal correction-modal--confirm" data-delete-modal hidden role="dialog" aria-modal="true" aria-labelledby="salida-delete-title">
        <div class="correction-modal-card correction-confirm-card">
            <div class="warning-icon" aria-hidden="true">⚠️</div>
            <h2 id="salida-delete-title" data-delete-title>¿Eliminar este registro?</h2>
            <p data-delete-message></p>
            <footer class="modal-actions">
                <button type="button" class="button-link button-link--secondary" data-delete-cancel>Cancelar</button>
                <button type="button" class="button-link button-link--danger" data-delete-confirm>Sí, eliminar</button>
            </footer>
        </div>
    </div>

    <div class="correction-toast-host" data-toast-host aria-live="polite" aria-atomic="true"></div>
</section>

<script src="<?= APP_URL ?>/public/js/correcciones.js"></script>
<script src="<?= APP_URL ?>/public/js/searchable-select.js"></script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
