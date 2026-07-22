<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<section class="panel report-panel">
    <p class="eyebrow">Reporte</p>
    <h1>Movimientos por lote</h1>
    <p class="intro">Seleccione un producto y un lote para ver su entrada, salidas relacionadas y saldo.</p>

    <?php if (!empty($loadError)) : ?>
        <div class="message message--error" role="alert">
            No se pudo cargar el reporte: <?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form class="entry-form entry-form--two-columns report-filter" method="get" action="<?= APP_URL ?>/reporte">
        <div class="form-field">
            <label for="idProducto">Producto</label>
            <select id="idProducto" name="idProducto" required onchange="this.form.submit()">
                <option value="">Seleccione un producto</option>
                <?php foreach ($productos as $producto) : ?>
                    <option value="<?= (int) $producto['idProducto'] ?>" <?= (string) $idProducto === (string) $producto['idProducto'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field">
            <label for="idInventarioEntrante">Lote</label>
            <select id="idInventarioEntrante" name="idInventarioEntrante" required <?= empty($idProducto) ? 'disabled' : '' ?>>
                <option value="">Seleccione un lote</option>
                <?php foreach ($lotes as $lote) : ?>
                    <option value="<?= (int) $lote['idInventarioEntrante'] ?>" <?= (string) $idInventarioEntrante === (string) $lote['idInventarioEntrante'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lote['NumLote'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions form-actions--full">
            <button class="button-link button-link--submit" type="submit">Ver reporte</button>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menu</a>
        </div>
    </form>

    <?php if (!empty($idProducto) && !empty($idInventarioEntrante) && empty($encabezado) && empty($loadError)) : ?>
        <div class="message message--error" role="alert">
            No se encontro informacion para el producto y lote seleccionados.
        </div>
    <?php endif; ?>

    <?php if (!empty($encabezado)) : ?>
        <article class="inventory-report">
            <div class="report-heading">
                <div><span>Producto:</span> <?= htmlspecialchars($encabezado['producto'], ENT_QUOTES, 'UTF-8') ?></div>
                <div><span>Lote:</span> <?= htmlspecialchars($encabezado['NumLote'], ENT_QUOTES, 'UTF-8') ?></div>
                <div><span>Presentacion:</span> <?= htmlspecialchars($encabezado['presentacion'], ENT_QUOTES, 'UTF-8') ?></div>
                <div><span>Ubicacion:</span> <?= htmlspecialchars($encabezado['ubicacion'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <div class="report-table-wrap">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>NE</th>
                            <th>Entrada</th>
                            <th>Salida</th>
                            <th>Saldo</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos as $movimiento) : ?>
                            <tr>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($movimiento['fecha'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($movimiento['ne'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= $movimiento['entrada'] !== '' ? htmlspecialchars(number_format((float) $movimiento['entrada'], 2), ENT_QUOTES, 'UTF-8') : '' ?></td>
                                <td><?= $movimiento['salida'] !== '' ? htmlspecialchars(number_format((float) $movimiento['salida'], 2), ENT_QUOTES, 'UTF-8') : '' ?></td>
                                <td><?= htmlspecialchars(number_format((float) $movimiento['saldo'], 2), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($movimiento['observaciones'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
