<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$text = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$money = static fn ($value) => htmlspecialchars(number_format((float) $value, 2, '.', ''), ENT_QUOTES, 'UTF-8');
$sectores = $sectores ?? [];
$sectorSeleccionado = $sectorSeleccionado ?? '';
$predespachos = $predespachos ?? [];
$codigoPredespachoSeleccionado = $codigoPredespachoSeleccionado ?? '';
$predespachoSeleccionado = $predespachoSeleccionado ?? null;
$items = $items ?? [];
$soloAbiertos = $soloAbiertos ?? true;
$soloConsulta = $predespachoSeleccionado && !in_array($predespachoSeleccionado['statusGeneralPredespacho'], ['abierto', 'pendiente'], true);
$idCabeceraSeleccionada = (int) ($predespachoSeleccionado['idCabeceraPredespacho'] ?? 0);
$requiresAuthentication = $requiresAuthentication ?? false;
$gruposPredespachos = ['abiertos' => [], 'despachados' => []];
foreach ($predespachos as $predespacho) {
    $grupo = in_array($predespacho['statusGeneralPredespacho'], ['abierto', 'pendiente'], true) ? 'abiertos' : 'despachados';
    $gruposPredespachos[$grupo][] = $predespacho;
}
?>

<?php if ($requiresAuthentication) : ?>
<section class="panel salida-auth-page" aria-hidden="true">
    <p class="eyebrow">Inventario saliente</p>
    <h1>Registrar entrega</h1>
</section>

<div class="delivery-auth-modal" role="dialog" aria-modal="true" aria-label="Iniciar sesión para registrar salida">
    <section class="delivery-auth-card">
        <?php if (!empty($authError)) : ?>
            <div class="message message--error" role="alert"><?= $text($authError) ?></div>
        <?php endif; ?>

        <form class="delivery-auth-form" method="post" action="<?= APP_URL ?>/salida/autenticar">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="return_query" value="<?= $text($returnQuery ?? '') ?>">
            <label>
                Usuario
                <input name="username" type="text" value="<?= $text($authUsername ?? '') ?>" required autocomplete="username" autofocus>
            </label>
            <label>
                Contraseña
                <span class="delivery-password-field">
                    <input id="deliveryLoginPassword" name="password" type="password" required autocomplete="current-password">
                    <button type="button" class="password-toggle" data-password-toggle="deliveryLoginPassword">Mostrar</button>
                </span>
            </label>
            <button class="button-link button-link--submit" type="submit">Ingresar</button>
        </form>
    </section>
</div>
<?php else : ?>
<section class="panel report-panel admin-page salida-panel" data-salida-predespacho data-submit-url="<?= APP_URL ?>/salida/guardar" data-predespacho-codigo="<?= $text($codigoPredespachoSeleccionado) ?>">
    <div class="admin-heading">
        <div>
            <p class="eyebrow">Inventario saliente</p>
            <h1>Registrar entrega</h1>
            <p class="intro">Selecciona el predespacho, revisa sus productos por sector y registra la cantidad entregada por producto/lote.</p>
        </div>
        <?php if (Auth::can('corregir_salidas')) : ?>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/salida/detalle">Corregir salidas</a>
        <?php endif; ?>
        <?php if (($_SESSION['rol_nombre'] ?? '') !== 'Operador') : ?>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menu</a>
        <?php endif; ?>
    </div>

    <div class="message message--success" role="status" data-salida-message <?= empty($successMessage) ? 'hidden' : '' ?>><?= $text($successMessage ?? '') ?></div>
    <div class="message message--error" role="alert" data-salida-error hidden></div>

    <?php if (!empty($loadError)) : ?>
        <div class="message message--error" role="alert">No se pudieron cargar los datos: <?= $text($loadError) ?></div>
    <?php endif; ?>

    <section class="inventory-report">
        <div class="chart-title">
            <div>
                <h2>1. Seleccione el Predespacho</h2>
                <p class="quiet-text"><?= $soloAbiertos ? 'Abiertos o pendientes por entrega.' : 'Abiertos, pendientes y despachados (embarcados).' ?></p>
            </div>
        </div>
            <form class="entry-form predespacho-sector-filter" method="get" action="<?= APP_URL ?>/salida">
                <input type="hidden" name="solo_abiertos" value="0">
                <input type="hidden" name="sector" value="<?= $text($sectorSeleccionado) ?>">
                <div class="form-field">
                    <label for="predespacho">Predespacho</label>
                    <select id="predespacho" name="predespacho" class="<?= $predespachoSeleccionado ? ($soloConsulta ? 'salida-status-readonly' : 'salida-status-open') : '' ?>" onchange="this.form.submit()">
                        <option value="">-- Seleccione un predespacho --</option>
                        <?php foreach ($gruposPredespachos as $grupo => $predespachosGrupo) : ?>
                            <?php if (empty($predespachosGrupo)) { continue; } ?>
                            <?php $esConsulta = $grupo === 'despachados'; ?>
                            <optgroup class="<?= $esConsulta ? 'salida-status-readonly' : 'salida-status-open' ?>" label="<?= $esConsulta ? 'DESPACHADOS - SOLO CONSULTA' : 'ABIERTOS / PENDIENTES - POR ENTREGAR' ?> (<?= count($predespachosGrupo) ?>)">
                                <?php foreach ($predespachosGrupo as $predespacho) : ?>
                                    <option class="<?= $esConsulta ? 'salida-status-readonly' : 'salida-status-open' ?>" value="<?= $text($predespacho['codigoInterno']) ?>" <?= (string) $codigoPredespachoSeleccionado === (string) $predespacho['codigoInterno'] ? 'selected' : '' ?>>
                                        [<?= $esConsulta ? 'DESPACHADO' : $text(strtoupper($predespacho['statusGeneralPredespacho'])) ?>] <?= $text($predespacho['codigoInterno']) ?> | <?= $text($predespacho['nombreCliente']) ?> | <?= $text($predespacho['fechaRetiro']) ?><?= $esConsulta ? ' | Solo consulta' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <label class="salida-open-filter">
                    <input type="checkbox" name="solo_abiertos" value="1" <?= $soloAbiertos ? 'checked' : '' ?> onchange="this.form.submit()">
                    Ver solamente Pre-Despachos
                </label>
            </form>
        <?php if (empty($predespachos)) : ?>
            <div class="message message--error" role="status">No hay predespachos con productos para este filtro.</div>
        <?php endif; ?>
    </section>

    <?php if ($predespachoSeleccionado) : ?>
        <section class="inventory-report predespacho-delivery-grid <?= $soloConsulta ? 'salida-readonly' : '' ?>" data-delivery-section>
            <div class="predespacho-products-panel">
                <div class="chart-title">
                    <div>
                        <h2>Productos del predespacho</h2>
                        <p class="quiet-text"><?= $text($predespachoSeleccionado['codigoInterno']) ?> | <?= $text($predespachoSeleccionado['nombreCliente']) ?></p>
                        <?php if ($soloConsulta) : ?>
                            <p class="salida-consultation-status" role="status">Despachado (embarcado) - Solo consulta</p>
                        <?php endif; ?>
                        <?php if (trim((string) ($predespachoSeleccionado['observaciones'] ?? '')) !== '') : ?>
                            <p class="predespacho-selected-observation">
                                <strong>Observación:</strong>
                                <?= nl2br($text($predespachoSeleccionado['observaciones'])) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <form class="entry-form predespacho-sector-filter" method="get" action="<?= APP_URL ?>/salida">
                    <input type="hidden" name="predespacho" value="<?= $text($codigoPredespachoSeleccionado) ?>">
                    <input type="hidden" name="solo_abiertos" value="<?= $soloAbiertos ? '1' : '0' ?>">
                    <div class="form-field">
                        <label for="sector">Sector</label>
                        <select id="sector" name="sector" onchange="this.form.submit()">
                            <option value="">Todos los Sectores</option>
                            <?php foreach ($sectores as $sector) : ?>
                                <option value="<?= $text($sector) ?>" <?= (string) $sectorSeleccionado === (string) $sector ? 'selected' : '' ?>><?= $text($sector) ?></option>
                            <?php endforeach; ?>
                        </select>
                        </br>
                    </div>
                </form>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Lote</th>
                                <th>Sector</th>
                                <th>Silos disponibles</th>
                                <th>Presentacion</th>
                                <th>Unidad</th>
                                <th>Inicial</th>
                                <th>Entregado</th>
                                <th>Pendiente</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)) : ?>
                                <tr><td colspan="10">Este predespacho no tiene productos para el sector seleccionado.</td></tr>
                            <?php else : ?>
                                <?php foreach ($items as $item) : ?>
                                    <?php
                                    $solicitada = (float) $item['cantidadSolicitada'];
                                    $entregada = (float) $item['cantidadDespachada'];
                                    $pendiente = max(0, (float) $item['cantidadPendiente']);
                                    $unidad = $item['unidad'] ?? null;
                                    $estadoClase = $entregada >= $solicitada ? 'is-complete' : ($entregada > 0 ? 'is-partial' : 'is-empty');
                                    $estadoTexto = $estadoClase === 'is-complete' ? 'Comp.' : ($estadoClase === 'is-partial' ? 'Parc.' : 'Pend.');
                                    $puedeEntregar = !$soloConsulta && $pendiente > 0 && $item['estatusItemPredespacho'] !== 'cerrado';
                                    ?>
                                    <tr id="fila-<?= (int) $item['idItem'] ?>" class="fila-producto <?= $puedeEntregar ? 'predespacho-product-row' : '' ?> <?= $estadoClase ?>" <?php if ($puedeEntregar) : ?>onclick="cargarProducto(<?= (int) $item['idItem'] ?>, <?= htmlspecialchars(json_encode((string) $item['nombreProducto'], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode((string) $item['NumLote'], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>, <?= $money($pendiente) ?>)"<?php endif; ?>>
                                        <td><strong><?= $text($item['nombreProducto']) ?></strong></td>
                                        <td><?= $text($item['NumLote']) ?></td>
                                        <td><?= $text($item['sector']) ?></td>
                                        <td><?= $text($item['silosDisponibles'] ?: (str_replace(' ', '', strtolower((string) $item['sector'])) === 'sector3' ? 'Pendiente de distribuir' : 'No aplica')) ?></td>
                                        <td><?= $text($item['presentacion'] ?? '') ?></td>
                                        <td><?= $unidad === null ? 'N/D' : $money($unidad) ?></td>
                                        <td><?= $money($solicitada) ?></td>
                                        <td><?= $money($entregada) ?></td>
                                        <td><span class="stock-pill <?= $pendiente <= 0 ? 'stock-pill--risk' : '' ?>"><?= $money($pendiente) ?></span></td>
                                        <td><span class="delivery-dot <?= $estadoClase ?>" aria-hidden="true"></span><?= $text($estadoTexto) ?> <?= $item['estatusItemPredespacho'] === 'cerrado' ? '<span class="delivery-check">✓</span>' : '' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (!$soloConsulta) : ?>
            <aside class="predespacho-delivery-card">
                <h2>Registrar Entrega</h2>
                <p class="quiet-text" id="mensaje-seleccion">← Haz clic en un producto de la lista</p>
                <form id="form-entrega" class="entry-form" method="post" action="<?= APP_URL ?>/salida/guardar">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" id="hid_inventarioId" name="idItem">
                    <input type="hidden" name="idCabeceraPredespacho" value="<?= $idCabeceraSeleccionada ?>">
                    <input type="hidden" name="predespachoId" value="<?= $text($codigoPredespachoSeleccionado) ?>">
                    <input type="hidden" name="sector" value="<?= $text($sectorSeleccionado) ?>">

                    <div class="form-field">
                        <label for="txt_producto">Producto</label>
                        <input readonly id="txt_producto" type="text">
                    </div>
                    <div class="delivery-card-pair">
                        <div class="form-field">
                            <label for="txt_lote">Lote</label>
                            <input readonly id="txt_lote" type="text">
                        </div>
                        <div class="form-field">
                            <label for="txt_disponible">Disponible</label>
                            <input readonly id="txt_disponible" type="number">
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="txt_cantidad">Cantidad</label>
                        <input type="number" id="txt_cantidad" name="cantidadDespachada" min="0.01" step="0.01" disabled>
                    </div>
                    <div class="form-actions">
                        <button class="button-link button-link--submit" type="submit" disabled>Guardar Entrega</button>
                    </div>
                </form>
            </aside>
            <?php endif; ?>
        </section>
    <?php elseif ($codigoPredespachoSeleccionado !== '') : ?>
        <div class="message message--error" role="alert">El predespacho seleccionado no está disponible con el filtro actual.</div>
    <?php endif; ?>
</section>

<script src="<?= APP_URL ?>/public/js/salida.js?v=<?= filemtime(__DIR__ . '/../../../public/js/salida.js') ?>"></script>
<?php endif; ?>
<script src="<?= APP_URL ?>/public/js/login.js"></script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
