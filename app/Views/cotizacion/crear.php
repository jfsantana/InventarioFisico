<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$text = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$clientes = $clientes ?? [];
$productos = $productos ?? [];
$presentaciones = $presentaciones ?? [];
$condicionesPago = $condicionesPago ?? [];
$fechaEmisionVista = (string) ($fechaEmision ?? date('Y-m-d'));
$fechaVencimientoVista = (string) ($fechaVencimiento ?? '');
?>

<section
    class="panel report-panel cotizacion-page"
    data-cotizacion-page
    data-save-endpoint="<?= APP_URL ?>/cotizacion/guardar"
    data-email-endpoint="<?= APP_URL ?>/cotizacion/actualizarEmail"
    data-csrf-token="<?= $text($csrfToken ?? '') ?>"
>
    <header class="cotizacion-heading">
        <div>
            <p class="eyebrow">Junta Directiva</p>
            <h1>Nueva cotización</h1>
        </div>
        <div class="cotizacion-heading-actions">
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/cotizacion">Ver cotizaciones</a>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menú</a>
        </div>
    </header>

    <div class="message" data-cotizacion-message role="status" hidden></div>

    <form class="cotizacion-form" data-cotizacion-form novalidate>
        <label class="cotizacion-field cotizacion-field--client">
            Cliente
            <select
                name="idCliente"
                required
                data-client-select
                data-searchable-select
                data-search-placeholder="Buscar cliente por nombre o RIF"
                data-search-result-label="cliente"
            >
                <option value="">Seleccione un cliente</option>
                <?php foreach ($clientes as $cliente) : ?>
                    <option value="<?= (int) $cliente['idCliente'] ?>" data-email="<?= $text($cliente['email'] ?? '') ?>">
                        <?= $text($cliente['rif'] . ' - ' . $cliente['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <fieldset class="cotizacion-dependent-fields" data-quotation-fields disabled>
            <section class="cotizacion-fields" aria-label="Datos de la cotización">
                <label class="cotizacion-field cotizacion-field--date">
                    Fecha de emisión
                    <time class="cotizacion-date-value" datetime="<?= $text($fechaEmisionVista) ?>" data-issue-date data-value="<?= $text($fechaEmisionVista) ?>">
                        <?= $text(date('d/m/Y', strtotime($fechaEmisionVista))) ?>
                    </time>
                </label>

                <label class="cotizacion-field cotizacion-field--days">
                    Días de vigencia
                    <input name="diasVigencia" type="number" min="1" max="999" step="1" value="<?= max(1, min(999, (int) ($diasVigencia ?? 1))) ?>" required data-validity-days>
                </label>

                <label class="cotizacion-field cotizacion-field--date">
                    Fecha de vencimiento
                    <time class="cotizacion-date-value" datetime="<?= $text($fechaVencimientoVista) ?>" data-expiration-date>
                        <?= $fechaVencimientoVista !== '' ? $text(date('d/m/Y', strtotime($fechaVencimientoVista))) : '' ?>
                    </time>
                </label>

                <label class="cotizacion-field cotizacion-field--payment">
                    Condición de pago
                    <select name="condicionPago" required>
                        <option value="">Seleccione una condición</option>
                        <?php foreach ($condicionesPago as $condicionPago) : ?>
                            <option value="<?= $text($condicionPago) ?>"><?= $text($condicionPago) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </section>

            <section class="cotizacion-products" aria-labelledby="cotizacion-products-title">
                <header>
                    <h2 id="cotizacion-products-title">Productos</h2>
                    <button class="button-link cotizacion-add-product" type="button" data-add-detail>
                        <span aria-hidden="true">+</span> Agregar producto
                    </button>
                </header>

                <div class="cotizacion-products-empty" data-detail-empty>
                    Aún no has agregado productos.
                </div>

                <div class="cotizacion-product-list" data-detail-rows role="list"></div>

                <div class="cotizacion-total-bar">
                    <dl>
                        <div><dt>Subtotal</dt><dd data-quotation-subtotal>0,00</dd></div>
                        <div><dt>IVA 16%</dt><dd data-quotation-tax>0,00</dd></div>
                        <div><dt>Total</dt><dd data-grand-total>0,00</dd></div>
                    </dl>
                </div>
            </section>

            <label class="cotizacion-field cotizacion-observation">
                Nota / Observación
                <textarea name="observacion" rows="4" maxlength="2000"></textarea>
            </label>

            <footer class="cotizacion-actions">
                <button class="button-link button-link--submit" type="submit" data-save-quotation>Generar cotización</button>
            </footer>
        </fieldset>
    </form>
</section>

<template data-detail-template>
    <article class="cotizacion-product-item" role="listitem">
        <div class="cotizacion-product-summary">
            <span>Producto</span>
            <strong data-detail-product-name></strong>
            <small data-detail-presentation-name></small>
        </div>
        <dl class="cotizacion-product-values">
            <div><dt>Cantidad</dt><dd data-detail-quantity></dd></div>
            <div><dt>Precio unitario</dt><dd data-detail-price></dd></div>
            <div><dt>Subtotal</dt><dd data-detail-subtotal></dd></div>
        </dl>
        <div class="cotizacion-product-actions">
            <button type="button" data-edit-detail aria-label="Editar producto" title="Editar producto">&#9998;</button>
            <button type="button" data-remove-detail aria-label="Eliminar producto" title="Eliminar producto">&#128465;</button>
        </div>
    </article>
</template>

<div class="correction-modal cotizacion-product-modal" data-product-modal hidden role="dialog" aria-modal="true" aria-labelledby="cotizacion-product-title">
    <form class="correction-modal-card cotizacion-product-card" data-product-form novalidate>
        <header>
            <div>
                <h2 id="cotizacion-product-title" data-product-modal-title tabindex="-1">Agregar producto</h2>
                <p>Completa los datos del producto para incluirlo en la cotización.</p>
            </div>
            <button class="modal-close" type="button" data-product-close aria-label="Cerrar modal">×</button>
        </header>

        <div class="cotizacion-product-form-grid">
            <label class="cotizacion-product-form-field cotizacion-product-form-field--wide">
                Producto
                <select required data-product-input data-searchable-select data-search-placeholder="Buscar producto" data-search-result-label="producto">
                    <option value="">Seleccione un producto</option>
                    <?php foreach ($productos as $producto) : ?>
                        <option value="<?= (int) $producto['idProducto'] ?>">
                            <?= $text(($producto['codigoInterno'] ?? '') . ' - ' . $producto['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="cotizacion-product-form-field cotizacion-product-form-field--wide">
                Presentación
                <select required data-presentation-input>
                    <option value="">Seleccione una presentación</option>
                    <?php foreach ($presentaciones as $presentacion) : ?>
                        <option value="<?= (int) $presentacion['idPresentacion'] ?>"><?= $text($presentacion['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="cotizacion-product-form-field">
                Cantidad
                <input type="number" min="0.01" step="0.01" inputmode="decimal" required data-quantity-input>
            </label>

            <label class="cotizacion-product-form-field">
                Precio unitario
                <input type="number" min="0.01" step="0.01" inputmode="decimal" required data-price-input>
            </label>
        </div>

        <div class="cotizacion-product-preview">
            <span>Subtotal</span>
            <strong data-product-subtotal>0,00</strong>
        </div>

        <div class="message" data-product-message role="alert" hidden></div>

        <footer class="modal-actions">
            <button class="button-link button-link--secondary" type="button" data-product-close>Cancelar</button>
            <button class="button-link button-link--submit" type="submit" data-product-save>Agregar producto</button>
        </footer>
    </form>
</div>

<div class="correction-modal cotizacion-generation-modal" data-generation-modal hidden role="dialog" aria-modal="true" aria-labelledby="cotizacion-generation-title">
    <section class="correction-modal-card cotizacion-generation-card">
        <header>
            <div>
                <h2 id="cotizacion-generation-title" data-generation-title tabindex="-1">Generar cotización</h2>
                <p>Selecciona cómo deseas completar la cotización.</p>
            </div>
            <button class="modal-close" type="button" data-generation-close aria-label="Cerrar modal">×</button>
        </header>

        <div class="cotizacion-generation-options">
            <button type="button" data-generation-mode="pdf">
                <span class="cotizacion-generation-icon" aria-hidden="true">&#8681;</span>
                <strong>Generar y descargar PDF</strong>
                <small>Genera la cotización y descarga el archivo en este dispositivo.</small>
            </button>
            <button type="button" data-generation-mode="email">
                <span class="cotizacion-generation-icon" aria-hidden="true">&#9993;</span>
                <strong>Enviar solo por email</strong>
                <small>Genera la cotización y la envía al cliente por correo.</small>
            </button>
            <button type="button" data-generation-mode="pdf_email">
                <span class="cotizacion-generation-icon" aria-hidden="true">&#8681;&nbsp;&#9993;</span>
                <strong>Descargar PDF y enviar por email</strong>
                <small>Descarga el archivo y también envía la cotización por correo.</small>
            </button>
        </div>

        <div class="message" data-generation-message role="alert" hidden></div>

        <footer class="modal-actions">
            <button class="button-link button-link--secondary" type="button" data-generation-close>Cancelar</button>
        </footer>
    </section>
</div>

<div class="correction-modal cotizacion-email-modal" data-email-modal hidden role="dialog" aria-modal="true" aria-labelledby="cotizacion-email-title">
    <form class="correction-modal-card cotizacion-email-card" data-email-form novalidate>
        <header>
            <div>
                <h2 id="cotizacion-email-title">Email del cliente</h2>
                <p data-email-client-name></p>
            </div>
            <button class="modal-close" type="button" data-email-close aria-label="Cerrar modal">×</button>
        </header>
        <label>
            Email
            <input name="email" type="email" maxlength="150" autocomplete="email" required>
        </label>
        <div class="message" data-email-message role="alert" hidden></div>
        <footer class="modal-actions">
            <button class="button-link button-link--secondary" type="button" data-email-close>Cancelar</button>
            <button class="button-link button-link--submit" type="submit">Guardar email</button>
        </footer>
    </form>
</div>

<script src="<?= APP_URL ?>/public/js/searchable-select.js?v=<?= filemtime(__DIR__ . '/../../../public/js/searchable-select.js') ?>"></script>
<script src="<?= APP_URL ?>/public/js/cotizacion.js?v=<?= filemtime(__DIR__ . '/../../../public/js/cotizacion.js') ?>"></script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>