<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$formatDate = static fn ($date) => htmlspecialchars(date('d/m/Y', strtotime((string) $date)), ENT_QUOTES, 'UTF-8');
$dateValue = static fn ($date) => htmlspecialchars(date('Y-m-d', strtotime((string) $date)), ENT_QUOTES, 'UTF-8');
$money = static fn ($value) => htmlspecialchars(number_format((float) $value, 2), ENT_QUOTES, 'UTF-8');
$text = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$messageType = $messageType ?? 'success';
$entradas = $entradas ?? [];
$presentaciones = $presentaciones ?? [];
$ubicaciones = $ubicaciones ?? [];
$productos = $productos ?? [];
$tiposCompra = $tiposCompra ?? [];
$proveedores = $proveedores ?? [];
$paises = $paises ?? [];
$documentosPorEntrada = $documentosPorEntrada ?? [];
$sectores = $sectores ?? ['Sector1', 'Sector2', 'Sector3'];
$canResendEmail = Auth::can('corregir_entradas', 'editar');
?>

<section class="panel report-panel correction-panel correction-table-page" data-correction-page data-page-type="entrada" data-delete-endpoint="<?= APP_URL ?>/entrada/eliminar" data-email-resend-endpoint="<?= APP_URL ?>/entrada/reenviarCorreo" data-document-download-endpoint="<?= APP_URL ?>/entrada/descargarDocumento" data-csrf-token="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <p class="eyebrow">CORRECCION OPERATIVA</p>
    <h1>Entradas registradas</h1>
    <p class="intro">Revise cada entrada de inventario y corrija sus datos cuando sea necesario.</p>

    <?php if (!empty($message)) : ?>
        <div class="message message--<?= $messageType === 'error' ? 'error' : 'success' ?> correction-server-message" role="alert" data-server-message data-message-type="<?= $messageType === 'error' ? 'error' : 'success' ?>">
            <?= $text($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($loadError)) : ?>
        <div class="message message--error" role="alert">
            No se pudieron cargar las entradas: <?= $text($loadError) ?>
        </div>
    <?php endif; ?>

    <div class="form-actions correction-actions">
        <a class="button-link" href="<?= APP_URL ?>/entrada">Registrar nueva entrada</a>
        <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menú</a>
    </div>

    <button class="filter-toggle" type="button" data-filter-toggle aria-expanded="false">Mostrar filtros 🔽</button>

    <section class="correction-filters" data-filter-panel aria-label="Filtros de entradas registradas">
        <label>
            Buscar
            <input type="search" data-filter-search placeholder="Producto, lote, sector o ubicacion" aria-label="Buscar por producto, lote, sector o ubicacion">
        </label>
        <label>
            Presentacion
            <select data-filter-presentation aria-label="Filtrar por presentacion">
                <option value="">Todas</option>
                <?php foreach ($presentaciones as $presentacion) : ?>
                    <option value="<?= (int) $presentacion['idPresentacion'] ?>"><?= $text($presentacion['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Ubicacion
            <select data-filter-location aria-label="Filtrar por ubicacion">
                <option value="">Todas</option>
                <?php foreach ($ubicaciones as $ubicacion) : ?>
                    <option value="<?= (int) $ubicacion['idUbicacion'] ?>"><?= $text($ubicacion['nombre']) ?></option>
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
        <?php if (empty($entradas)) : ?>
            <div class="message message--error" role="status">No hay entradas registradas para corregir.</div>
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
                        <th><button type="button" data-sort="sector">Sector <span data-sort-indicator>↕</span></button></th>
                        <th><button type="button" data-sort="cantidad" data-type="number">Cantidad <span data-sort-indicator>↕</span></button></th>
                        <th><button type="button" data-sort="fechaFactura">Fecha factura <span data-sort-indicator>↕</span></button></th>
                        <th><button type="button" data-sort="pesoRomana" data-type="number">Peso romana <span data-sort-indicator>↕</span></button></th>
                        <th><button type="button" data-sort="nroFactura">Nro. factura <span data-sort-indicator>↕</span></button></th>
                        <th><button type="button" data-sort="salidas" data-type="number">Salidas <span data-sort-indicator>↕</span></button></th>
                        <th><button type="button" data-sort="disponible" data-type="number">Disponible <span data-sort-indicator>↕</span></button></th>
                        <th class="actions-column">Acciones</th>
                    </tr>
                </thead>
                <tbody data-correction-body>
                    <?php foreach ($entradas as $entrada) : ?>
                        <?php $danger = (float) $entrada['disponible'] <= 0; ?>
                        <?php $documentosEntrada = $documentosPorEntrada[(int) $entrada['idInventarioEntrante']] ?? []; ?>
                        <tr class="<?= $danger ? 'is-risk-row' : '' ?>"
                            data-id="<?= (int) $entrada['idInventarioEntrante'] ?>"
                            data-fecha="<?= $dateValue($entrada['fecha']) ?>"
                            data-product-id="<?= (int) $entrada['idProducto'] ?>"
                            data-producto="<?= $text($entrada['producto']) ?>"
                            data-lote="<?= $text($entrada['NumLote']) ?>"
                            data-presentation="<?= (int) $entrada['idPresentacion'] ?>"
                            data-presentacion="<?= $text($entrada['presentacion']) ?>"
                            data-location="<?= (int) $entrada['idUbicacion'] ?>"
                            data-ubicacion="<?= $text($entrada['ubicacion']) ?>"
                            data-sector="<?= $text($entrada['Sector'] ?? '') ?>"
                            data-cantidad="<?= $text($entrada['CantidadEntrante']) ?>"
                            data-fecha-factura="<?= !empty($entrada['fecha_factura']) ? $dateValue($entrada['fecha_factura']) : '' ?>"
                            data-peso-romana="<?= $text($entrada['peso_romana'] ?? '') ?>"
                            data-nro-factura="<?= $text($entrada['nro_factura'] ?? '') ?>"
                            data-tipo-compra-id="<?= (int) ($entrada['idTipoCompra'] ?? 0) ?>"
                            data-card-code="<?= $text($entrada['CardCode'] ?? '') ?>"
                            data-fabricante-code="<?= $text($entrada['FabricanteCode'] ?? '') ?>"
                            data-pais-code="<?= $text($entrada['PaisCode'] ?? '') ?>"
                            data-ticket-romana-id="<?= (int) ($documentosEntrada['ticket_romana']['idDocumento'] ?? 0) ?>"
                            data-ticket-romana-name="<?= $text($documentosEntrada['ticket_romana']['nombreOriginal'] ?? '') ?>"
                            data-factura-proveedor-id="<?= (int) ($documentosEntrada['factura_proveedor']['idDocumento'] ?? 0) ?>"
                            data-factura-proveedor-name="<?= $text($documentosEntrada['factura_proveedor']['nombreOriginal'] ?? '') ?>"
                            data-documento-seniat-id="<?= (int) ($documentosEntrada['documento_seniat']['idDocumento'] ?? 0) ?>"
                            data-documento-seniat-name="<?= $text($documentosEntrada['documento_seniat']['nombreOriginal'] ?? '') ?>"
                            data-salidas="<?= $text($entrada['salidaTotal']) ?>"
                            data-disponible="<?= $text($entrada['disponible']) ?>"
                            data-search="<?= $text($entrada['producto'] . ' ' . $entrada['NumLote'] . ' ' . ($entrada['Sector'] ?? '') . ' ' . $entrada['ubicacion'] . ' ' . ($entrada['tipoCompra'] ?? '') . ' ' . ($entrada['proveedor'] ?? '') . ' ' . ($entrada['fabricante'] ?? '') . ' ' . ($entrada['pais'] ?? '') . ' ' . ($entrada['nro_factura'] ?? '')) ?>">
                            <td data-label="#"><?= (int) $entrada['idInventarioEntrante'] ?></td>
                            <td data-label="Fecha"><?= $formatDate($entrada['fecha']) ?></td>
                            <td data-label="Producto"><strong><?= $text($entrada['producto']) ?></strong></td>
                            <td data-label="Lote"><?= $text($entrada['NumLote']) ?></td>
                            <td data-label="Presentacion"><?= $text($entrada['presentacion']) ?></td>
                            <td data-label="Ubicacion"><?= $text($entrada['ubicacion']) ?></td>
                            <td data-label="Sector"><?= $text($entrada['Sector'] ?? '') ?></td>
                            <td data-label="Cantidad"><?= $money($entrada['CantidadEntrante']) ?></td>
                            <td data-label="Fecha factura"><?= !empty($entrada['fecha_factura']) ? $formatDate($entrada['fecha_factura']) : 'No indicada' ?></td>
                            <td data-label="Peso romana"><?= $entrada['peso_romana'] !== null ? $money($entrada['peso_romana']) : 'No indicado' ?></td>
                            <td data-label="Nro. factura"><?= $text($entrada['nro_factura'] ?? 'No indicado') ?></td>
                            <td data-label="Salidas"><?= $money($entrada['salidaTotal']) ?></td>
                            <td data-label="Disponible"><span class="stock-pill <?= $danger ? 'stock-pill--risk' : '' ?>"><?= $money($entrada['disponible']) ?></span></td>
                            <td class="actions-column" data-label="Acciones">
                                <button class="icon-action icon-action--edit" type="button" data-edit-row aria-label="Editar entrada #<?= (int) $entrada['idInventarioEntrante'] ?>">✏️</button>
                                <?php if ($canResendEmail) : ?>
                                    <button class="icon-action icon-action--email" type="button" data-email-row aria-label="Reenviar correo de entrada #<?= (int) $entrada['idInventarioEntrante'] ?>" title="Reenviar correo">&#9993;</button>
                                <?php endif; ?>
                                <button class="icon-action icon-action--delete" type="button" data-delete-row aria-label="Eliminar entrada #<?= (int) $entrada['idInventarioEntrante'] ?>">🗑️</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <footer class="correction-pagination" aria-label="Paginacion de entradas">
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

    <div class="correction-modal" data-edit-modal hidden role="dialog" aria-modal="true" aria-labelledby="entrada-modal-title">
        <form class="correction-modal-card" method="post" action="<?= APP_URL ?>/entrada/actualizar" enctype="multipart/form-data" data-correction-modal-form novalidate>
            <?= Auth::csrfField() ?>
            <header>
                <div>
                    <h2 id="entrada-modal-title" data-modal-title>Editar Entrada</h2>
                    <p data-modal-subtitle></p>
                </div>
                <button type="button" class="modal-close" data-modal-close aria-label="Cerrar modal">×</button>
            </header>
            <input type="hidden" name="idInventarioEntrante">
            <div class="correction-modal-grid">
                <label>
                    Tipo de compra
                    <select name="idTipoCompra" required>
                        <option value="">Seleccione un tipo de compra</option>
                        <?php foreach ($tiposCompra as $tipoCompra) : ?>
                            <option value="<?= (int) $tipoCompra['id'] ?>"><?= $text($tipoCompra['descripcion']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Proveedor
                    <select name="CardCode" required data-searchable-select data-search-placeholder="Escriba codigo o nombre del proveedor" data-search-result-label="proveedor">
                        <option value="">Seleccione un proveedor</option>
                        <?php foreach ($proveedores as $proveedor) : ?>
                            <option value="<?= $text($proveedor['CardCode']) ?>"><?= $text($proveedor['CardCode'] . ' - ' . ($proveedor['CardName'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Fabricante
                    <select name="FabricanteCode" required data-searchable-select data-search-placeholder="Escriba codigo o nombre del fabricante" data-search-result-label="fabricante">
                        <option value="">Seleccione un fabricante</option>
                        <?php foreach ($proveedores as $proveedor) : ?>
                            <option value="<?= $text($proveedor['CardCode']) ?>"><?= $text($proveedor['CardCode'] . ' - ' . ($proveedor['CardName'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Pais
                    <select name="PaisCode" required>
                        <option value="">Seleccione un pais</option>
                        <?php foreach ($paises as $pais) : ?>
                            <option value="<?= $text($pais['Code']) ?>"><?= $text($pais['Name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Producto
                    <select id="entradaProductoSelect" name="idProducto" required data-product-search data-search-placeholder="Escriba codigo o nombre del producto">
                        <?php foreach ($productos as $producto) : ?>
                            <option value="<?= (int) $producto['idProducto'] ?>"><?= $text($producto['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Lote
                    <input name="NumLote" type="text" required>
                </label>
                <label>
                    Presentacion
                    <select name="idPresentacion" required>
                        <?php foreach ($presentaciones as $presentacion) : ?>
                            <option value="<?= (int) $presentacion['idPresentacion'] ?>"><?= $text($presentacion['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Ubicacion
                    <select name="idUbicacion" required>
                        <?php foreach ($ubicaciones as $ubicacion) : ?>
                            <option value="<?= (int) $ubicacion['idUbicacion'] ?>"><?= $text($ubicacion['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Sector
                    <select name="Sector" required>
                        <?php foreach ($sectores as $sector) : ?>
                            <option value="<?= $text($sector) ?>"><?= $text($sector) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Cantidad
                    <input name="CantidadEntrante" data-modal-quantity type="number" min="1" step="1" required>
                </label>
                <label>
                    Fecha de factura (dd/mm/aaaa)
                    <input name="fecha_factura" type="date" required>
                </label>
                <label>
                    Peso de romana
                    <input name="peso_romana" type="number" min="0.01" step="any" inputmode="decimal" required>
                </label>
                <label>
                    Numero de factura
                    <input name="nro_factura" type="text" maxlength="50" pattern="[A-Za-z0-9]+" required>
                </label>
                <div class="document-fields">
                    <label data-document-field="ticketRomana">
                        Ticket de romana
                        <span class="document-current"><a href="#" data-document-link>Descargar <span data-document-name></span></a><span data-document-empty>Sin documento cargado</span></span>
                        <input name="ticketRomana" type="file" accept=".pdf,.jpg,.jpeg,.png">
                    </label>
                    <label data-document-field="facturaProveedor">
                        Factura del proveedor
                        <span class="document-current"><a href="#" data-document-link>Descargar <span data-document-name></span></a><span data-document-empty>Sin documento cargado</span></span>
                        <input name="facturaProveedor" type="file" accept=".pdf,.jpg,.jpeg,.png">
                    </label>
                    <label data-document-field="documentoSeniat">
                        Documento de Seniat
                        <span class="document-current"><a href="#" data-document-link>Descargar <span data-document-name></span></a><span data-document-empty>Sin documento cargado</span></span>
                        <input name="documentoSeniat" type="file" accept=".pdf,.jpg,.jpeg,.png">
                    </label>
                    <small>Los documentos son opcionales. Seleccione un archivo solo para agregarlo o reemplazar el actual. Formatos: PDF, JPG o PNG. Maximo conjunto: 10 MB.</small>
                </div>
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

    <div class="correction-modal correction-modal--confirm" data-delete-modal hidden role="dialog" aria-modal="true" aria-labelledby="entrada-delete-title">
        <div class="correction-modal-card correction-confirm-card">
            <div class="warning-icon" aria-hidden="true">⚠️</div>
            <h2 id="entrada-delete-title" data-delete-title>¿Eliminar este registro?</h2>
            <p data-delete-message></p>
            <footer class="modal-actions">
                <button type="button" class="button-link button-link--secondary" data-delete-cancel>Cancelar</button>
                <button type="button" class="button-link button-link--danger" data-delete-confirm>Sí, eliminar</button>
            </footer>
        </div>
    </div>

    <div class="correction-toast-host" data-toast-host aria-live="polite" aria-atomic="true"></div>
</section>

<script src="<?= APP_URL ?>/public/js/correcciones.js?v=<?= filemtime(__DIR__ . '/../../../public/js/correcciones.js') ?>"></script>
<script src="<?= APP_URL ?>/public/js/searchable-select.js?v=<?= filemtime(__DIR__ . '/../../../public/js/searchable-select.js') ?>"></script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
