<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<section class="panel form-panel">
    <p class="eyebrow">Inventario entrante</p>
    <h1>Registrar entrada</h1>
    <p class="intro">Complete los datos del inventario recibido. La fecha se guarda automaticamente con la fecha actual.</p>

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

    <form class="entry-form entry-form--two-columns" method="post" action="<?= APP_URL ?>/entrada/guardar">
        <div class="form-field">
            <label for="idProducto">1. Producto</label>
            <select id="idProducto" name="idProducto" required>
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
            <label for="NumLote">2. Lote</label>
            <input id="NumLote" name="NumLote" type="text" value="<?= htmlspecialchars($formData['NumLote'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off" placeholder="Ejemplo: LOTE-001">
            <?php if (!empty($errors['NumLote'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['NumLote'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="idPresentacion">3. Presentacion</label>
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
            <label for="idUbicacion">4. Ubicacion</label>
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
            <label for="CantidadEntrante">5. Cantidad entrante</label>
            <input id="CantidadEntrante" name="CantidadEntrante" type="number" min="1" step="1" value="<?= htmlspecialchars($formData['CantidadEntrante'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required placeholder="0">
            <?php if (!empty($errors['CantidadEntrante'])) : ?>
                <small class="field-error"><?= htmlspecialchars($errors['CantidadEntrante'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>

        <div class="form-actions form-actions--full">
            <button class="button-link button-link--submit" type="submit">Guardar entrada</button>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menu</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
