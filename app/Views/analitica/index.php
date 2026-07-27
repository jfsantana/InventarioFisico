<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<?php
$series = $indicadores['series'] ?? [];
$maxMovimiento = max(1, ...array_map(fn ($dia) => max($dia['entrada'], $dia['salida']), $series ?: [['entrada' => 0, 'salida' => 0]]));
$diasCobertura = $indicadores['diasCobertura'] ?? null;
$coberturaTexto = $diasCobertura === null ? 'Sin salidas' : number_format($diasCobertura, 1) . ' dias';
$tendencia = $indicadores['tendencia'] ?? 'estable';
$lotesAgrupadosPorProducto = [];

foreach ($resumenLotes as $loteResumen) {
    $lotesAgrupadosPorProducto[$loteResumen['producto']][] = $loteResumen;
}
?>

<section class="panel report-panel executive-panel">
    <p class="eyebrow">Inteligencia de inventario</p>
    <h1>Seguimiento ejecutivo</h1>
    <p class="intro">Vision gerencial por producto y lote: entradas, salidas, cobertura proyectada y lotes que requieren atencion.</p>

    <?php if (!empty($loadError)) : ?>
        <div class="message message--error" role="alert">
            No se pudo cargar el tablero: <?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form class="entry-form entry-form--three-columns report-filter" method="get" action="<?= APP_URL ?>/analitica">
        <div class="form-field">
            <label for="idProducto">Producto</label>
            <select id="idProducto" name="idProducto" data-product-search data-search-placeholder="Escriba codigo o nombre del producto">
                <option value="">Resumen por producto y lote</option>
                <?php foreach ($productos as $producto) : ?>
                    <option value="<?= (int) $producto['idProducto'] ?>" <?= (string) $idProducto === (string) $producto['idProducto'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field">
            <label for="desde">Desde</label>
            <div class="date-picker-field">
                <input id="desde" name="desde" type="text" value="<?= htmlspecialchars($desdeDisplay, ENT_QUOTES, 'UTF-8') ?>" inputmode="numeric" pattern="\d{2}/\d{2}/\d{4}" placeholder="DD/MM/AAAA" data-date-display="desdeCalendar">
                <button class="date-picker-button" type="button" data-date-button="desdeCalendar" aria-label="Abrir calendario desde" title="Abrir calendario">
                    <svg class="date-picker-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M8 2v4M16 2v4M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" />
                    </svg>
                </button>
                <input id="desdeCalendar" class="native-date-input" type="date" value="<?= htmlspecialchars($desde, ENT_QUOTES, 'UTF-8') ?>" data-date-target="desde" aria-hidden="true" tabindex="-1">
            </div>
        </div>

        <div class="form-field">
            <label for="hasta">Hasta</label>
            <div class="date-picker-field">
                <input id="hasta" name="hasta" type="text" value="<?= htmlspecialchars($hastaDisplay, ENT_QUOTES, 'UTF-8') ?>" inputmode="numeric" pattern="\d{2}/\d{2}/\d{4}" placeholder="DD/MM/AAAA" data-date-display="hastaCalendar">
                <button class="date-picker-button" type="button" data-date-button="hastaCalendar" aria-label="Abrir calendario hasta" title="Abrir calendario">
                    <svg class="date-picker-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M8 2v4M16 2v4M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" />
                    </svg>
                </button>
                <input id="hastaCalendar" class="native-date-input" type="date" value="<?= htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8') ?>" data-date-target="hasta" aria-hidden="true" tabindex="-1">
            </div>
        </div>

        <div class="form-actions form-actions--full">
            <button class="button-link button-link--submit" type="submit">Actualizar tablero</button>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menu</a>
        </div>
    </form>

    <?php if (empty($idProducto)) : ?>
        <div class="message message--success" role="status">
            Seleccione un producto para ver tendencias y grafico detallado. Abajo se muestran indicadores separados por producto y lote, sin totalizar productos diferentes.
        </div>

        <section class="product-lot-groups">
            <?php foreach ($lotesAgrupadosPorProducto as $productoNombre => $lotesProducto) : ?>
                <?php $lotesAlerta = count(array_filter($lotesProducto, fn ($lote) => $lote['enRiesgo'])); ?>
                <?php $productoDetalleUrl = APP_URL . '/analitica?' . http_build_query([
                    'idProducto' => (int) ($lotesProducto[0]['idProducto'] ?? 0),
                    'desde' => $desdeDisplay,
                    'hasta' => $hastaDisplay,
                ]); ?>
                <article class="product-lot-group">
                    <header class="product-lot-header">
                        <div>
                            <span>Producto</span>
                            <h2>
                                <a class="product-detail-link" href="<?= htmlspecialchars($productoDetalleUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Ver detalles de <?= htmlspecialchars($productoNombre, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($productoNombre, ENT_QUOTES, 'UTF-8') ?>
                                    <i aria-hidden="true">⌕</i>
                                </a>
                            </h2>
                        </div>
                        <strong><?= count($lotesProducto) ?> lote<?= count($lotesProducto) === 1 ? '' : 's' ?> · <?= $lotesAlerta ?> alerta<?= $lotesAlerta === 1 ? '' : 's' ?></strong>
                    </header>

                    <div class="product-summary-grid product-summary-grid--inside-group">
                        <?php foreach ($lotesProducto as $loteResumen) : ?>
                            <?php $loteCobertura = $loteResumen['diasCobertura'] === null ? 'Sin salidas' : number_format($loteResumen['diasCobertura'], 1) . ' dias'; ?>
                            <article class="product-summary-card <?= $loteResumen['enRiesgo'] ? 'product-summary-card--risk' : '' ?>">
                                <span>Lote</span>
                                <h2><?= htmlspecialchars($loteResumen['NumLote'], ENT_QUOTES, 'UTF-8') ?></h2>
                                <dl>
                                    <div>
                                        <dt>Disponible</dt>
                                        <dd><?= htmlspecialchars(number_format($loteResumen['inventarioDisponible'], 2), ENT_QUOTES, 'UTF-8') ?></dd>
                                    </div>
                                    <div>
                                        <dt>Salidas del periodo</dt>
                                        <dd><?= htmlspecialchars(number_format($loteResumen['totalSalida'], 2), ENT_QUOTES, 'UTF-8') ?></dd>
                                    </div>
                                    <div>
                                        <dt>Cobertura</dt>
                                        <dd><?= htmlspecialchars($loteCobertura, ENT_QUOTES, 'UTF-8') ?></dd>
                                    </div>
                                    <div>
                                        <dt>Proyeccion 7 dias</dt>
                                        <dd><?= htmlspecialchars(number_format($loteResumen['proyeccionSalida7Dias'], 2), ENT_QUOTES, 'UTF-8') ?></dd>
                                    </div>
                                    <div>
                                        <dt>Estado</dt>
                                        <dd><?= $loteResumen['enRiesgo'] ? 'Alerta' : 'Controlado' ?></dd>
                                    </div>
                                </dl>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($indicadores) && !empty($idProducto)) : ?>
        <section class="product-lot-groups">
            <?php foreach ($lotesAgrupadosPorProducto as $productoNombre => $lotesProducto) : ?>
                <?php $lotesAlerta = count(array_filter($lotesProducto, fn ($lote) => $lote['enRiesgo'])); ?>
                <article class="product-lot-group">
                    <header class="product-lot-header">
                        <div>
                            <span>Producto seleccionado</span>
                            <h2><?= htmlspecialchars($productoNombre, ENT_QUOTES, 'UTF-8') ?></h2>
                        </div>
                        <strong><?= count($lotesProducto) ?> lote<?= count($lotesProducto) === 1 ? '' : 's' ?> · <?= $lotesAlerta ?> alerta<?= $lotesAlerta === 1 ? '' : 's' ?></strong>
                    </header>

                    <div class="lot-analytics-grid lot-analytics-grid--inside-group">
                        <?php foreach ($lotesProducto as $loteResumen) : ?>
                            <?php $loteCobertura = $loteResumen['diasCobertura'] === null ? 'Sin salidas' : number_format($loteResumen['diasCobertura'], 1) . ' dias'; ?>
                            <article class="lot-analytics-card <?= $loteResumen['enRiesgo'] ? 'lot-analytics-card--risk' : '' ?>">
                                <span>Lote</span>
                                <h2><?= htmlspecialchars($loteResumen['NumLote'], ENT_QUOTES, 'UTF-8') ?></h2>
                                <div class="lot-metrics">
                                    <div>
                                        <small>Disponible</small>
                                        <strong><?= htmlspecialchars(number_format($loteResumen['inventarioDisponible'], 2), ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                    <div>
                                        <small>Salidas periodo</small>
                                        <strong><?= htmlspecialchars(number_format($loteResumen['totalSalida'], 2), ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                    <div>
                                        <small>Cobertura</small>
                                        <strong><?= htmlspecialchars($loteCobertura, ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>
                                    <div>
                                        <small>Rotacion</small>
                                        <strong><?= htmlspecialchars(number_format($loteResumen['rotacionPeriodo'], 1), ENT_QUOTES, 'UTF-8') ?>%</strong>
                                    </div>
                                </div>
                                <p><?= $loteResumen['enRiesgo'] ? 'Atencion: lote sin stock o con cobertura menor a 7 dias.' : 'Lote con comportamiento controlado para el periodo.' ?></p>
                                <small><?= htmlspecialchars($loteResumen['ubicacion'], ENT_QUOTES, 'UTF-8') ?> · Entrada: <?= htmlspecialchars(date('d/m/Y', strtotime($loteResumen['fechaEntrada'])), ENT_QUOTES, 'UTF-8') ?></small>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="chart-card">
            <div class="chart-title">
                <h2>Entradas vs salidas por fecha del producto</h2>
                <p>Lectura rapida del producto seleccionado. Las tarjetas superiores mantienen la decision por lote.</p>
            </div>

            <div class="bar-chart" role="img" aria-label="Grafico de entradas y salidas por fecha">
                <?php foreach ($series as $dia) : ?>
                    <?php
                    $entradaHeight = max(4, ((float) $dia['entrada'] / $maxMovimiento) * 150);
                    $salidaHeight = max(4, ((float) $dia['salida'] / $maxMovimiento) * 150);
                    ?>
                    <div class="bar-day" title="<?= htmlspecialchars($dia['fecha'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="bar-pair">
                            <span class="bar bar--entrada" style="height: <?= htmlspecialchars((string) $entradaHeight, ENT_QUOTES, 'UTF-8') ?>px"></span>
                            <span class="bar bar--salida" style="height: <?= htmlspecialchars((string) $salidaHeight, ENT_QUOTES, 'UTF-8') ?>px"></span>
                        </div>
                        <small><?= htmlspecialchars(date('d/m', strtotime($dia['fecha'])), ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="chart-legend">
                <span><i class="legend-dot legend-dot--entrada"></i> Entradas</span>
                <span><i class="legend-dot legend-dot--salida"></i> Salidas</span>
            </div>
        </section>

        <section class="risk-grid">
            <article class="risk-panel">
                <h2>Lotes que requieren atencion</h2>
                <?php $lotesEnRiesgo = array_filter($resumenLotes, fn ($lote) => $lote['enRiesgo']); ?>
                <?php if (empty($lotesEnRiesgo)) : ?>
                    <p class="quiet-text">No hay lotes en alerta para el ritmo actual de salidas.</p>
                <?php else : ?>
                    <div class="risk-list">
                        <?php foreach ($lotesEnRiesgo as $lote) : ?>
                            <div class="risk-item">
                                <strong><?= htmlspecialchars($lote['producto'], ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars($lote['NumLote'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span>Disponible: <?= htmlspecialchars(number_format((float) $lote['inventarioDisponible'], 2), ENT_QUOTES, 'UTF-8') ?></span>
                                <small><?= htmlspecialchars($lote['ubicacion'], ENT_QUOTES, 'UTF-8') ?> · Entrada: <?= htmlspecialchars(date('d/m/Y', strtotime($lote['fechaEntrada'])), ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

            <article class="risk-panel">
                <h2>Lectura gerencial</h2>
                <p class="quiet-text">
                    <?php if (!empty($lotesEnRiesgo)) : ?>
                        Hay lotes del producto seleccionado con cobertura critica. Conviene priorizar reposicion o revisar el ritmo de despacho por lote.
                    <?php elseif ($tendencia === 'en aumento') : ?>
                        Las salidas del producto se estan acelerando. Revise cada lote antes de consolidar decisiones de compra.
                    <?php elseif ($indicadores['totalSalida'] == 0) : ?>
                        No hay salidas en el periodo. El tablero queda listo para comparar cuando empiece el movimiento.
                    <?php else : ?>
                        Los lotes del producto lucen controlados para el periodo seleccionado. Mantenga seguimiento de cobertura y rotacion por lote.
                    <?php endif; ?>
                </p>
            </article>
        </section>
    <?php endif; ?>
</section>

<script src="<?= APP_URL ?>/public/js/analitica.js"></script>
<script src="<?= APP_URL ?>/public/js/searchable-select.js"></script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
