<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$text = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $value): string => number_format((float) $value, 2, ',', '.');
$cotizaciones = $cotizaciones ?? [];
?>

<section
    class="panel report-panel cotizacion-history"
    data-cotizacion-history
    data-resend-endpoint="<?= APP_URL ?>/cotizacion/reenviarCorreo"
    data-delete-endpoint="<?= APP_URL ?>/cotizacion/desactivar"
    data-csrf-token="<?= $text($csrfToken ?? '') ?>"
>
    <header class="cotizacion-history-heading">
        <div>
            <p class="eyebrow">Junta Directiva</p>
            <h1>Cotizaciones</h1>
            <p class="intro">Consulta, imprime o reenvía las cotizaciones generadas.</p>
        </div>
        <div class="cotizacion-history-heading-actions">
            <a class="button-link button-link--submit" href="<?= APP_URL ?>/cotizacion/crear">+ Nueva cotización</a>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menú</a>
        </div>
    </header>

    <div class="message" data-history-message role="status" hidden></div>

    <label class="cotizacion-history-search">
        Buscar cotización
        <input type="search" placeholder="Número, cliente o RIF" data-history-search>
    </label>

    <div class="cotizacion-history-empty" data-history-empty <?= $cotizaciones ? 'hidden' : '' ?>>
        No hay cotizaciones activas registradas.
    </div>

    <div class="cotizacion-history-list" data-history-list>
        <?php foreach ($cotizaciones as $cotizacion) : ?>
            <?php
            $fechaCreacion = new DateTimeImmutable((string) $cotizacion['fechaCreacion']);
            $fechaEmision = new DateTimeImmutable((string) $cotizacion['fechaEmision']);
            $fechaVencimiento = $fechaEmision->modify('+' . (int) $cotizacion['diasVigencia'] . ' days');
            $numeroCotizacion = $fechaCreacion->format('YmdH');
            $vencida = $fechaVencimiento < new DateTimeImmutable('today');
            $search = $numeroCotizacion . ' ' . $cotizacion['nombreCliente'] . ' ' . $cotizacion['rifCliente'];
            ?>
            <article class="cotizacion-history-item" data-history-item data-search="<?= $text(mb_strtolower($search, 'UTF-8')) ?>" data-id="<?= (int) $cotizacion['idCotizacion'] ?>">
                <div class="cotizacion-history-main">
                    <span class="cotizacion-history-number">N° <?= $text($numeroCotizacion) ?></span>
                    <h2><?= $text($cotizacion['nombreCliente']) ?></h2>
                    <p><?= $text($cotizacion['rifCliente']) ?></p>
                </div>

                <dl class="cotizacion-history-meta">
                    <div><dt>Emisión</dt><dd><?= $text($fechaEmision->format('d/m/Y')) ?></dd></div>
                    <div><dt>Vencimiento</dt><dd class="<?= $vencida ? 'is-expired' : '' ?>"><?= $text($fechaVencimiento->format('d/m/Y')) ?></dd></div>
                    <div><dt>Productos</dt><dd><?= (int) $cotizacion['cantidadProductos'] ?></dd></div>
                    <div><dt>Total</dt><dd><?= $money($cotizacion['total']) ?></dd></div>
                </dl>

                <div class="cotizacion-history-actions">
                    <a href="<?= APP_URL ?>/cotizacion/verPdf/<?= (int) $cotizacion['idCotizacion'] ?>" target="_blank" rel="noopener" title="Ver o imprimir PDF">Ver / imprimir</a>
                    <a href="<?= APP_URL ?>/cotizacion/descargarPdf/<?= (int) $cotizacion['idCotizacion'] ?>" title="Descargar PDF">Descargar</a>
                    <button type="button" data-resend-quotation title="Reenviar por email">Reenviar</button>
                    <button type="button" class="is-danger" data-delete-quotation title="Desactivar cotización">Eliminar</button>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<script src="<?= APP_URL ?>/public/js/cotizacion-historial.js?v=<?= filemtime(__DIR__ . '/../../../public/js/cotizacion-historial.js') ?>"></script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>