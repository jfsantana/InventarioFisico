<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<section class="panel report-panel admin-page" data-predespacho-page data-api-url="<?= APP_URL ?>/public/predespacho_api.php">
    <div class="admin-heading">
        <div>
            <p class="eyebrow">Predespacho</p>
            <h1>Gestión de Predespachos</h1>
            <p class="intro">Clientes, retiros programados, estatus general y nota de entrega SAP.</p>
        </div>
        <button class="button-link button-link--submit" type="button" data-open-predespacho-modal>Nuevo Predespacho</button>
    </div>

    <div class="message message--success" role="status" data-predespacho-message hidden></div>
    <div class="message message--error" role="alert" data-predespacho-error hidden></div>

    <div class="admin-filters predespacho-filters">
        <input type="search" placeholder="Buscar por codigo, cliente o SAP" data-search-predespacho>
        <select data-status-filter>
            <option value="">Todos</option>
            <option value="abierto">abierto</option>
            <option value="pendiente">pendiente</option>
            <option value="cerrado">cerrado</option>
        </select>
        <button class="button-link button-link--secondary" type="button" data-refresh-predespachos>Actualizar</button>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table" data-predespacho-table>
            <thead>
                <tr>
                    <th>Código Interno</th>
                    <th>Cliente</th>
                    <th>Fecha Retiro</th>
                    <th>Código SAP</th>
                    <th>Status</th>
                    <th>Fecha Creación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody data-predespacho-rows>
                <tr>
                    <td colspan="7">Cargando predespachos...</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<div class="correction-modal" data-predespacho-modal hidden role="dialog" aria-modal="true" aria-labelledby="predespacho-modal-title">
    <form class="correction-modal-card admin-modal-card" data-predespacho-form novalidate>
        <header>
            <div>
                <h2 id="predespacho-modal-title">Nuevo Predespacho</h2>
                <p>Cabecera del retiro y datos de control SAP.</p>
            </div>
            <button type="button" class="modal-close" data-modal-close aria-label="Cerrar modal">×</button>
        </header>
        <div class="correction-modal-grid">
            <label class="predespacho-client-field">Cliente
                <span class="predespacho-inline-control">
                    <select name="idCliente" required data-clientes-select>
                        <option value="">Cargando clientes...</option>
                    </select>
                    <button type="button" class="button-link button-link--submit predespacho-add-button" data-open-cliente-modal aria-label="Crear cliente">+</button>
                </span>
            </label>
            <label>Fecha de retiro<input name="fechaRetiro" type="date" required></label>
            <label>Código Nota Entrega SAP<input name="codigoNotaEntregaSAP" type="text" autocomplete="off"></label>
            <label>Observaciones<textarea name="observaciones" rows="4"></textarea></label>
        </div>
        <footer class="modal-actions">
            <button type="button" class="button-link button-link--secondary" data-modal-close>Cancelar</button>
            <button type="submit" class="button-link button-link--submit">Guardar</button>
        </footer>
    </form>
</div>

<div class="correction-modal" data-cliente-modal hidden role="dialog" aria-modal="true" aria-labelledby="cliente-modal-title">
    <form class="correction-modal-card admin-modal-card" data-cliente-form novalidate>
        <header>
            <div>
                <h2 id="cliente-modal-title">Crear Cliente</h2>
                <p>Registro rapido para usarlo en el predespacho.</p>
            </div>
            <button type="button" class="modal-close" data-modal-close aria-label="Cerrar modal">×</button>
        </header>
        <div class="correction-modal-grid">
            <label>RIF<input name="rif" type="text" required autocomplete="off"></label>
            <label>Nombre<input name="nombre" type="text" required autocomplete="off"></label>
            <label>Dirección<input name="direccion" type="text" required autocomplete="off"></label>
            <label>Tipo<select name="tipo" required><option value="natural">natural</option><option value="juridico">jurídico</option></select></label>
        </div>
        <footer class="modal-actions">
            <button type="button" class="button-link button-link--secondary" data-modal-close>Cancelar</button>
            <button type="submit" class="button-link button-link--submit">Guardar</button>
        </footer>
    </form>
</div>

<div class="correction-modal" data-sap-modal hidden role="dialog" aria-modal="true" aria-labelledby="sap-modal-title">
    <form class="correction-modal-card admin-modal-card" data-sap-form novalidate>
        <header>
            <div>
                <h2 id="sap-modal-title">Editar SAP</h2>
                <p data-sap-target></p>
            </div>
            <button type="button" class="modal-close" data-modal-close aria-label="Cerrar modal">×</button>
        </header>
        <input type="hidden" name="idCabeceraPredespacho">
        <label>Código Nota Entrega SAP<input name="codigoNotaEntregaSAP" type="text" autocomplete="off"></label>
        <footer class="modal-actions">
            <button type="button" class="button-link button-link--secondary" data-modal-close>Cancelar</button>
            <button type="submit" class="button-link button-link--submit">Guardar</button>
        </footer>
    </form>
</div>

<div class="correction-modal" data-detalle-modal hidden role="dialog" aria-modal="true" aria-labelledby="detalle-modal-title">
    <div class="correction-modal-card admin-modal-card predespacho-detail-card">
        <header>
            <div>
                <h2 id="detalle-modal-title">Detalle Predespacho</h2>
                <p data-detalle-summary></p>
            </div>
            <button type="button" class="modal-close" data-modal-close aria-label="Cerrar modal">×</button>
        </header>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Lote</th>
                        <th>Producto</th>
                        <th>Presentación</th>
                        <th>Sector</th>
                        <th>Solicitada</th>
                        <th>Despachada</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody data-detalle-items></tbody>
            </table>
        </div>
        <footer class="modal-actions">
            <button type="button" class="button-link button-link--secondary" data-modal-close>Cerrar</button>
        </footer>
    </div>
</div>

<script src="<?= APP_URL ?>/public/js/predespacho.js?v=<?= filemtime(__DIR__ . '/../../../public/js/predespacho.js') ?>"></script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>