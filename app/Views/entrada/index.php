<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<?php
$productos = $productos ?? [];
$presentaciones = $presentaciones ?? [];
$ubicaciones = $ubicaciones ?? [];
$tiposCompra = $tiposCompra ?? [];
$proveedores = $proveedores ?? [];
$paises = $paises ?? [];
$sectores = $sectores ?? ['Sector1', 'Sector2', 'Sector3'];
$canCreateEntry = Auth::can('entrada', 'editar');
$isEntradaCompleta = !empty($formData['idProducto'])
    && !empty($formData['NumLote'])
    && !empty($formData['idPresentacion'])
    && !empty($formData['idUbicacion'])
    && !empty($formData['Sector'])
    && !empty($formData['idTipoCompra'])
    && !empty($formData['CardCode'])
    && !empty($formData['FabricanteCode'])
    && !empty($formData['PaisCode'])
    && !empty($formData['fecha_factura'])
    && is_numeric($formData['peso_romana'] ?? null)
    && (float) ($formData['peso_romana'] ?? 0) > 0
    && !empty($formData['nro_factura'])
    && ctype_digit((string) ($formData['CantidadEntrante'] ?? ''))
    && (int) ($formData['CantidadEntrante'] ?? 0) > 0;
?>

<section class="panel form-panel">
    <p class="eyebrow">Inventario fisico entrante</p>
    <h1>Registrar entrada de mercancia. </h1>

    <?php if (!empty($successMessage)) : ?>
        <div class="message message--success" role="status">
            <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($loadError)) : ?>
        <div class="message message--error" role="alert">
            No se pudieron cargar los datos: <?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors['general'])) : ?>
        <div class="message message--error" role="alert">
            <?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!$canCreateEntry) : ?>
        <div class="message" role="status">Tu rol permite consultar esta pantalla, pero no registrar entradas.</div>
    <?php endif; ?>

    <form class="entry-form entry-form--two-columns" method="post" action="<?= APP_URL ?>/entrada/guardar" enctype="multipart/form-data" data-entrada-form>
        <?= Auth::csrfField() ?>
        <fieldset class="entry-form-fieldset" <?= $canCreateEntry ? '' : 'disabled' ?>>
        <div class="form-field">
            <label for="idTipoCompra">1. Tipo de compra</label>
            <select id="idTipoCompra" name="idTipoCompra" required>
                <option value="">Seleccione un tipo de compra</option>
                <?php foreach ($tiposCompra as $tipoCompra) : ?>
                    <option value="<?= (int) $tipoCompra['id'] ?>" <?= (string) ($formData['idTipoCompra'] ?? '') === (string) $tipoCompra['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tipoCompra['descripcion'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['idTipoCompra'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['idTipoCompra'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="CardCode">2. Proveedor</label>
            <select id="CardCode" name="CardCode" required data-searchable-select data-search-placeholder="Escriba codigo o nombre del proveedor" data-search-result-label="proveedor">
                <option value="">Seleccione un proveedor</option>
                <?php foreach ($proveedores as $proveedor) : ?>
                    <option value="<?= htmlspecialchars($proveedor['CardCode'], ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($formData['CardCode'] ?? '') === (string) $proveedor['CardCode'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($proveedor['CardCode'] . ' - ' . ($proveedor['CardName'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['CardCode'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['CardCode'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="FabricanteCode">3. Fabricante</label>
            <select id="FabricanteCode" name="FabricanteCode" required data-searchable-select data-search-placeholder="Escriba codigo o nombre del fabricante" data-search-result-label="fabricante">
                <option value="">Seleccione un fabricante</option>
                <?php foreach ($proveedores as $proveedor) : ?>
                    <option value="<?= htmlspecialchars($proveedor['CardCode'], ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($formData['FabricanteCode'] ?? '') === (string) $proveedor['CardCode'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($proveedor['CardCode'] . ' - ' . ($proveedor['CardName'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['FabricanteCode'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['FabricanteCode'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="PaisCode">4. Pais</label>
            <select id="PaisCode" name="PaisCode" required>
                <option value="">Seleccione un pais</option>
                <?php foreach ($paises as $pais) : ?>
                    <option value="<?= htmlspecialchars($pais['Code'], ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($formData['PaisCode'] ?? '') === (string) $pais['Code'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pais['Name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['PaisCode'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['PaisCode'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="idProducto">5. Producto</label>
            <select id="idProducto" name="idProducto" required data-product-search data-search-placeholder="Escriba codigo o nombre del producto">
                <option value="">Seleccione un producto</option>
                <?php foreach ($productos as $producto) : ?>
                    <option value="<?= (int) $producto['idProducto'] ?>" <?= (string) ($formData['idProducto'] ?? '') === (string) $producto['idProducto'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['idProducto'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['idProducto'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="NumLote">6. Lote</label>
            <input id="NumLote" name="NumLote" type="text" value="<?= htmlspecialchars($formData['NumLote'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off" placeholder="Ejemplo: LOTE-001">
            <?php if (!empty($errors['NumLote'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['NumLote'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="idPresentacion">7. Presentacion (Efecto reporteria)</label>
            <select id="idPresentacion" name="idPresentacion" required>
                <option value="">Seleccione una presentacion</option>
                <?php foreach ($presentaciones as $presentacion) : ?>
                    <option value="<?= (int) $presentacion['idPresentacion'] ?>" <?= (string) ($formData['idPresentacion'] ?? '') === (string) $presentacion['idPresentacion'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($presentacion['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['idPresentacion'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['idPresentacion'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="idUbicacion">8. Ubicacion</label>
            <select id="idUbicacion" name="idUbicacion" required>
                <option value="">Seleccione una ubicacion</option>
                <?php foreach ($ubicaciones as $ubicacion) : ?>
                    <option value="<?= (int) $ubicacion['idUbicacion'] ?>" <?= (string) ($formData['idUbicacion'] ?? '') === (string) $ubicacion['idUbicacion'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ubicacion['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['idUbicacion'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['idUbicacion'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="Sector">9. Sector</label>
            <select id="Sector" name="Sector" required>
                <option value="">Seleccione un sector</option>
                <?php foreach ($sectores as $sector) : ?>
                    <option value="<?= htmlspecialchars($sector, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($formData['Sector'] ?? '') === (string) $sector ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sector, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['Sector'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['Sector'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="CantidadEntrante">10. Cantidad entrante (OBLIGATORIAMENTE SE DEBE CARGAR EN KILOS)</label>
            <input id="CantidadEntrante" name="CantidadEntrante" type="number" min="1" step="1" value="<?= htmlspecialchars($formData['CantidadEntrante'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required placeholder="0">
            <?php if (!empty($errors['CantidadEntrante'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['CantidadEntrante'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="fecha_factura">11. Fecha de factura (dd/mm/aaaa)</label>
            <input id="fecha_factura" name="fecha_factura" type="date" value="<?= htmlspecialchars($formData['fecha_factura'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            <?php if (!empty($errors['fecha_factura'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['fecha_factura'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="peso_romana">12. Peso de romana</label>
            <input id="peso_romana" name="peso_romana" type="number" min="0.01" step="any" inputmode="decimal" value="<?= htmlspecialchars($formData['peso_romana'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required placeholder="0.00">
            <?php if (!empty($errors['peso_romana'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['peso_romana'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="nro_factura">13. Numero de factura</label>
            <input id="nro_factura" name="nro_factura" type="text" maxlength="50" pattern="[A-Za-z0-9]+" value="<?= htmlspecialchars($formData['nro_factura'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off">
            <?php if (!empty($errors['nro_factura'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['nro_factura'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="ticketRomana">14. Ticket de romana</label>
            <input id="ticketRomana" name="ticketRomana" type="file" accept=".pdf,.jpg,.jpeg,.png">
            <?php if (!empty($errors['ticketRomana'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['ticketRomana'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="facturaProveedor">15. Factura del proveedor</label>
            <input id="facturaProveedor" name="facturaProveedor" type="file" accept=".pdf,.jpg,.jpeg,.png">
            <?php if (!empty($errors['facturaProveedor'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['facturaProveedor'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="documentoSeniat">16. Documento de Seniat</label>
            <input id="documentoSeniat" name="documentoSeniat" type="file" accept=".pdf,.jpg,.jpeg,.png">
            <?php if (!empty($errors['documentoSeniat'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['documentoSeniat'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <?php if (!empty($errors['documentos'])) : ?>
            <small class="field-error form-field-message"><?= htmlspecialchars($errors['documentos'], ENT_QUOTES, 'UTF-8') ?></small>
        <?php endif; ?>

        <div class="form-actions form-actions--full">
            <button id="guardarEntrada" class="button-link button-link--submit" type="submit" <?= $canCreateEntry && $isEntradaCompleta ? '' : 'disabled' ?>>Guardar entrada</button>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/entrada/detalle">Corregir entradas</a>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menu</a>
            <small id="entradaFormMessage" class="field-warning" aria-live="polite"></small>
        </div>
        </fieldset>
    </form>
</section>

<script src="<?= APP_URL ?>/public/js/entrada.js?v=<?= filemtime(__DIR__ . '/../../../public/js/entrada.js') ?>"></script>
<script src="<?= APP_URL ?>/public/js/searchable-select.js?v=<?= filemtime(__DIR__ . '/../../../public/js/searchable-select.js') ?>"></script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
