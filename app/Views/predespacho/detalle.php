<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$idCabeceraPredespacho = (int) ($idCabeceraPredespacho ?? 0);
$urlCierre = (string) ($urlCierre ?? '');
?>

<section class="panel report-panel admin-page" data-predespacho-detalle data-api-url="<?= APP_URL ?>/public/predespacho_api.php" data-id="<?= (int) $idCabeceraPredespacho ?>" data-close-url="<?= htmlspecialchars($urlCierre, ENT_QUOTES, 'UTF-8') ?>">
    <div class="admin-heading">
        <div>
            <p class="eyebrow">Predespacho</p>
            <h1>Detalle de Predespacho</h1>
            <p class="intro">Consulta de cabecera, items solicitados y disponibilidad para agregar nuevos lotes.</p>
        </div>
        <a class="button-link button-link--secondary predespacho-back-link" href="<?= APP_URL ?>/predespacho">Volver a la lista</a>
    </div>

    <div class="message message--success" role="status" data-detalle-message hidden></div>
    <div class="message message--error" role="alert" data-detalle-error hidden></div>

    <article class="status-card predespacho-summary-card" data-summary-card>
        <span>Código Interno</span>
        <strong data-summary-codigo>Cargando...</strong>
        <dl class="modal-summary predespacho-summary-list">
            <div><dt>Cliente</dt><dd data-summary-cliente>Sin dato</dd></div>
            <div><dt>Fecha Retiro</dt><dd data-summary-fecha>Sin dato</dd></div>
            <div><dt>Código SAP</dt><dd data-summary-sap>Sin dato</dd></div>
            <div><dt>Status</dt><dd data-summary-status>Sin dato</dd></div>
            <div><dt>Cerrado por</dt><dd data-summary-usuario-cierre>Sin cerrar</dd></div>
            <div><dt>Fecha de cierre</dt><dd data-summary-fecha-cierre>Sin cerrar</dd></div>
            <div class="predespacho-summary-wide"><dt>Observaciones</dt><dd data-summary-observaciones>Sin dato</dd></div>
        </dl>
        <form class="predespacho-inline-form" data-inline-sap-form>
            <input type="hidden" name="idCabeceraPredespacho" value="<?= (int) $idCabeceraPredespacho ?>">
            <label>Editar Código SAP<input class="predespacho-sap-input" name="codigoNotaEntregaSAP" type="text" maxlength="15" size="15" autocomplete="off"></label>
            <button class="button-link button-link--secondary predespacho-sap-button" type="submit">Guardar SAP</button>
        </form>
        <?php if ($urlCierre !== '') : ?>
            <aside class="predespacho-qr-panel" data-qr-panel aria-labelledby="predespacho-qr-title" hidden>
                <div>
                    <span class="eyebrow" id="predespacho-qr-title">Control de salida</span>
                    <h2>Código QR de cierre</h2>
                    <p>Escanee en la puerta para confirmar el despacho definitivo.</p>
                </div>
                <a class="predespacho-qr-link" href="<?= htmlspecialchars($urlCierre, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" aria-label="Abrir enlace de cierre del predespacho">
                    <span data-close-qr></span>
                </a>
                <button class="button-link button-link--submit" type="button" data-print-dispatch hidden>Imprimir informe</button>
            </aside>
        <?php endif; ?>
    </article>

    <section class="inventory-report">
        <div class="chart-title">
            <div>
                <h2>Items del predespacho</h2>
                <p class="quiet-text">Cantidades solicitadas, despacho registrado y cierre por item.</p>
            </div>
            <div class="form-actions">
                <button class="button-link button-link--submit" type="button" data-open-add-item-modal>Agregar item</button>
                <button class="button-link button-link--secondary" type="button" data-refresh-items>Actualizar items</button>
            </div>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>NumLote</th>
                        <th>Producto</th>
                        <th>Sector</th>
                        <th>Cant. Solicitada</th>
                        <th>Cant. Despachada</th>
                        <th>Status</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody data-items-rows>
                    <tr><td colspan="8">Cargando items...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</section>

<section class="dispatch-report" data-dispatch-report aria-label="Informe de despacho" hidden>
    <header class="dispatch-report__header">
        <img src="<?= APP_URL ?>/public/media/logoAdyarca.png" alt="Adyarca">
        <div>
            <p>Control de inventario</p>
            <h1>Informe de despacho</h1>
            <strong data-print-codigo>Predespacho</strong>
        </div>
        <div data-print-qr></div>
    </header>
    <dl class="dispatch-report__summary">
        <div><dt>Cliente</dt><dd data-print-cliente>Sin dato</dd></div>
        <div><dt>Fecha de retiro</dt><dd data-print-fecha>Sin dato</dd></div>
        <div><dt>Código SAP</dt><dd data-print-sap>Sin dato</dd></div>
        <div><dt>Estado</dt><dd data-print-status>Sin dato</dd></div>
        <div class="dispatch-report__wide"><dt>Observaciones</dt><dd data-print-observaciones>Sin observaciones</dd></div>
    </dl>
    <section class="dispatch-report__items">
        <h2>Productos despachados</h2>
        <table>
            <thead><tr><th>Producto</th><th>Cantidad despachada</th></tr></thead>
            <tbody data-print-items><tr><td colspan="2">Cargando productos...</td></tr></tbody>
        </table>
    </section>
    <footer class="dispatch-report__footer">
        <span>El código QR permite confirmar la salida definitiva del predespacho.</span>
        <span>Documento generado el <?= date('d/m/Y') ?></span>
    </footer>
</section>

<div class="correction-modal" data-add-item-modal hidden role="dialog" aria-modal="true" aria-labelledby="add-item-modal-title">
    <div class="correction-modal-card admin-modal-card predespacho-detail-card">
        <header>
            <div>
                <h2 id="add-item-modal-title">Agregar nuevo item</h2>
                <p>Selecciona un producto existente, el lote disponible y la cantidad solicitada.</p>
            </div>
            <button type="button" class="modal-close" data-modal-close aria-label="Cerrar modal">×</button>
        </header>

        <div class="message message--error" role="alert" data-add-item-error hidden></div>

        <div class="entry-form predespacho-search-grid">
            <div class="form-field predespacho-search-field predespacho-search-field--product">
                <label for="productoBusqueda">1. Producto</label>
                <input id="productoBusqueda" class="searchable-select-input predespacho-product-input" type="search" autocomplete="off" placeholder="Escriba codigo del producto" data-product-search-input>
                <div class="predespacho-results" data-product-results hidden></div>
            </div>
        </div>

        <div class="admin-table-wrap predespacho-lotes-wrap" data-lotes-wrap hidden>
            <p class="predespacho-step-title">2. Lote disponible</p>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>NumLote</th>
                        <th>Sector</th>
                        <th>Stock Total</th>
                        <th>Disponible</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody data-lotes-rows></tbody>
            </table>
        </div>

        <form class="entry-form entry-form--two-columns" data-add-item-form novalidate>
            <input type="hidden" name="idCabeceraPredespacho" value="<?= (int) $idCabeceraPredespacho ?>">
            <input type="hidden" name="idInventarioEntrante">
            <div class="form-field">
                <label>3. Disponibilidad</label>
                <input type="text" data-selected-disponible readonly placeholder="Seleccione un lote">
            </div>
            <div class="form-field">
                <label for="cantidadSolicitada">4. Cantidad solicitada</label>
                <input id="cantidadSolicitada" name="cantidadSolicitada" type="number" min="0.01" step="0.01" disabled required>
            </div>
            <div class="form-field">
                <label for="tipo">5. Tipo</label>
                <select id="tipo" name="tipo">
                    <option value="">Sin tipo</option>
                    <option value="REAL">REAL</option>
                    <option value="Custodio">Custodio</option>
                </select>
            </div>
            <div class="form-actions">
                <button class="button-link button-link--submit" type="submit" data-add-item-button disabled>Agregar Item</button>
                <button class="button-link button-link--secondary" type="button" data-clear-add-panel>Limpiar</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="<?= APP_URL ?>/public/js/predespacho-detalle.js?v=<?= filemtime(__DIR__ . '/../../../public/js/predespacho-detalle.js') ?>"></script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>