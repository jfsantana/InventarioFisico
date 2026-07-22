<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<section class="panel form-panel salida-panel">
    <p class="eyebrow">Inventario saliente</p>
    <h1>Registrar entrega</h1>
    

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

    <?php $hasSelectedLot = !empty($formData['idInventarioEntrante']); ?>

    <form class="entry-form" method="post" action="<?= APP_URL ?>/salida/guardar" data-lotes-url="<?= APP_URL ?>/salida/lotes">
        <?= Auth::csrfField() ?>
        <div class="form-field">
            <label for="sector">1. Sector</label>
            <select id="sector" name="sector" required>
                <option value="">Seleccione un sector</option>
                <?php foreach (['Sector1', 'Sector 2', 'Sector 3'] as $sector) : ?>
                    <option value="<?= htmlspecialchars($sector, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($formData['sector'] ?? '') === $sector ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sector, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['sector'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['sector'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="idProducto">2. Producto</label>
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
            <label for="idInventarioEntrante">3. Lote</label>
            <select id="idInventarioEntrante" name="idInventarioEntrante" required <?= empty($lotes) ? 'disabled' : '' ?>>
                <option value="">Seleccione primero un producto</option>
                <?php foreach ($lotes as $lote) : ?>
                    <option value="<?= (int) $lote['idInventarioEntrante'] ?>" data-disponible="<?= htmlspecialchars($lote['Disponible'], ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($formData['idInventarioEntrante'] ?? '') === (string) $lote['idInventarioEntrante'] ? 'selected' : '' ?>>
                        Lote <?= htmlspecialchars($lote['NumLote'], ENT_QUOTES, 'UTF-8') ?> - Disponible: <?= htmlspecialchars($lote['Disponible'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="field-help">La lista se actualiza segun el producto seleccionado.</small>
            <small id="lotMessage" class="field-warning" aria-live="polite"></small>
            <?php if (!empty($errors['idInventarioEntrante'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['idInventarioEntrante'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="NE">4. Nota de Entrega (NE)</label>
            <input id="NE" name="NE" type="text" value="<?= htmlspecialchars($formData['NE'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off" placeholder="Ejemplo: NE-001245" <?= $hasSelectedLot ? '' : 'disabled' ?>>
            <?php if (!empty($errors['NE'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['NE'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="cantidadSaliente">5. Cantidad entregada</label>
            <input id="cantidadSaliente" name="cantidadSaliente" type="number" min="0.01" step="0.01" value="<?= htmlspecialchars($formData['cantidadSaliente'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required placeholder="0" <?= $hasSelectedLot ? '' : 'disabled' ?>>
            <small id="quantityMessage" class="field-warning" aria-live="polite"></small>
            <?php if (!empty($errors['cantidadSaliente'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['cantidadSaliente'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button id="guardarSalida" class="button-link button-link--submit" type="submit" disabled>Guardar entrega</button>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/salida/detalle">Corregir salidas</a>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menu</a>
        </div>
    </form>
</section>

<script src="<?= APP_URL ?>/public/js/salida.js"></script>
<script src="<?= APP_URL ?>/public/js/searchable-select.js"></script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
