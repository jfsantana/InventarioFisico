<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$urlPagina = static fn (int $pagina): string => APP_URL . '/ajuste?' . http_build_query([
    'q' => $busqueda, 'pagina' => $pagina, 'historial' => $idHistorial,
]);
?>
<section class="panel ajuste-panel">
    <p class="eyebrow">Correcciones / Auditar y ajustar</p>
    <h1>Ajustes de lote por sistema.</h1>
    <p>El ajuste no cambia la entrada original. El positivo aumenta y el negativo disminuye definitivamente el inventario, sin crear un predespacho.</p>
    <?php if ($error) : ?><div class="message message--error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
    <?php if ($successMessage) : ?><div class="message message--success" role="status"><?= $escape($successMessage) ?></div><?php endif; ?>
    <form class="entry-form" method="get" action="<?= APP_URL ?>/ajuste">
        <div class="form-field">
            <label for="ajusteBusqueda">Buscar producto, codigo o lote</label>
            <input id="ajusteBusqueda" name="q" value="<?= $escape($busqueda) ?>">
        </div>
        <div class="form-actions">
            <button class="button-link button-link--submit" type="submit">Buscar</button>
            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menu</a>
        </div>
    </form>
    <div class="report-table-wrap">
        <table class="report-table">
            <thead><tr><th>Producto</th><th>Lote</th><th>Presentacion / Sector</th><th>Total entrante original</th><th>Ajustes +</th><th>Ajustes -</th><th>Reservado</th><th>Disponible</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($lotes as $lote) : ?>
                <tr>
                    <td><?= $escape($lote['producto']) ?><small><?= $escape($lote['codigoInterno']) ?></small></td>
                    <td><?= $escape($lote['NumLote']) ?> <small>#<?= (int) $lote['idInventarioEntrante'] ?></small></td>
                    <td><?= $escape($lote['presentacion']) ?> / <?= $escape($lote['sector']) ?></td>
                    <td><?= number_format((float) $lote['stock_total'], 3) ?></td>
                    <td><?= number_format((float) $lote['ajuste_positivo'], 3) ?></td>
                    <td><?= number_format((float) $lote['ajuste_negativo'], 3) ?></td>
                    <td><?= number_format((float) $lote['cantidad_reservada'], 3) ?></td>
                    <td><strong><?= number_format((float) $lote['cantidad_disponible'], 3) ?></strong></td>
                    <td>
                        <?php if (Auth::can('corregir_entradas', 'editar')) : ?>
                        <button class="button-link button-link--submit ajuste-icon-action" type="button" data-ajustar-lote
                            title="Ajustar lote" aria-label="<?= $escape('Ajustar lote ' . $lote['NumLote']) ?>"
                            data-id="<?= (int) $lote['idInventarioEntrante'] ?>"
                            data-lote="<?= $escape($lote['NumLote']) ?>"
                            data-producto="<?= $escape($lote['producto']) ?>"
                            data-sector="<?= $escape($lote['sector']) ?>"
                            data-disponible="<?= $escape($lote['cantidad_disponible']) ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14"/></svg>
                        </button>
                        <?php endif; ?>
                        <a class="button-link button-link--secondary ajuste-icon-action"
                            title="Ver historial" aria-label="<?= $escape('Ver historial del lote ' . $lote['NumLote']) ?>"
                            href="<?= APP_URL ?>/ajuste?<?= $escape(http_build_query(['q' => $busqueda, 'pagina' => $paginaActual, 'historial' => $lote['idInventarioEntrante']])) ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 11a9 9 0 1 1 2.6 7.4M3 4v7h7M12 7v5l3 2"/></svg>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$lotes) : ?><tr><td colspan="9">No hay lotes para mostrar.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <nav class="ajuste-pagination" aria-label="Paginacion de lotes">
        <span>Mostrando <?= $totalRegistros ? ($paginaActual - 1) * $porPagina + 1 : 0 ?>-<?= min($paginaActual * $porPagina, $totalRegistros) ?> de <?= $totalRegistros ?> lotes</span>
        <div>
            <?php if ($paginaActual > 1) : ?>
            <a class="button-link button-link--secondary" href="<?= $escape($urlPagina($paginaActual - 1)) ?>">Anterior</a>
            <?php endif; ?>
            <span aria-current="page">Pagina <?= $paginaActual ?> de <?= $totalPaginas ?></span>
            <?php if ($paginaActual < $totalPaginas) : ?>
            <a class="button-link button-link--secondary" href="<?= $escape($urlPagina($paginaActual + 1)) ?>">Siguiente</a>
            <?php endif; ?>
        </div>
    </nav>

    <?php if ($idHistorial > 0) : ?>
    <h2>Historial de ajustes del lote #<?= (int) $idHistorial ?></h2>
    <p>Los registros son de solo consulta. Para revertir un ajuste, registre uno de signo contrario e indique el numero del ajuste en la observacion.</p>
    <div class="report-table-wrap">
        <table class="report-table">
            <thead><tr><th>Registro</th><th>Fecha de creacion</th><th>Estado</th><th>Monto</th><th>Silo</th><th>Observacion</th><th>Responsable</th></tr></thead>
            <tbody>
                <?php foreach ($historial as $ajuste) : ?>
                <tr class="report-row report-row--ajuste">
                    <td>#<?= (int) $ajuste['idAjuste'] ?></td>
                    <td><?= $escape(date('d/m/Y H:i:s', strtotime($ajuste['fechaCreacion']))) ?></td>
                    <td><?= $escape($ajuste['tipo']) ?></td>
                    <td><?= number_format((float) $ajuste['monto'], 3) ?></td>
                    <td><?= $escape($ajuste['silo'] ?? '-') ?></td>
                    <td><?= $escape($ajuste['observacion']) ?></td>
                    <td><?= $escape($ajuste['responsable']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$historial) : ?><tr><td colspan="7">Este lote no tiene ajustes registrados.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php if (Auth::can('corregir_entradas', 'editar')) : ?>
<dialog class="ajuste-dialog" id="ajusteDialog" aria-labelledby="ajusteTitulo">
    <header class="ajuste-dialog-header">
        <h2 id="ajusteTitulo">Registrar ajuste de lote por sistema</h2>
        <button type="button" class="ajuste-dialog-close" id="ajusteCerrar" aria-label="Cerrar ventana" title="Cerrar">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </header>
    <form method="post" action="<?= APP_URL ?>/ajuste/guardar" class="entry-form" id="ajusteForm">
        <?php if ($error && $formData) : ?><div class="message message--error" role="alert"><?= $escape($error) ?></div><?php endif; ?>
        <?= Auth::csrfField() ?>
        <input type="hidden" name="tokenRegistro" value="<?= $escape($tokenRegistro) ?>">
        <input type="hidden" name="q" value="<?= $escape($busqueda) ?>">
        <input type="hidden" name="pagina" value="<?= $paginaActual ?>">
        <input type="hidden" name="idInventarioEntrante" id="ajusteEntrada" value="<?= $escape($formData['idInventarioEntrante'] ?? '') ?>">
        <fieldset class="ajuste-tipo">
            <legend>Estado del ajuste (obligatorio)</legend>
            <div class="ajuste-tipo-options">
                <label class="ajuste-tipo-option ajuste-tipo-option--positivo">
                    <input type="radio" name="tipo" value="positivo" required <?= ($formData['tipo'] ?? '') === 'positivo' ? 'checked' : '' ?>>
                    <span><strong>+ Positivo</strong><small>Aumentar inventario</small></span>
                </label>
                <label class="ajuste-tipo-option ajuste-tipo-option--negativo">
                    <input type="radio" name="tipo" value="negativo" required <?= ($formData['tipo'] ?? '') === 'negativo' ? 'checked' : '' ?>>
                    <span><strong>- Negativo</strong><small>Disminuir inventario</small></span>
                </label>
            </div>
        </fieldset>
        <div class="ajuste-datos-row">
            <div class="form-field"><label for="ajusteFecha">Fecha de creacion</label><input id="ajusteFecha" readonly value="<?= $escape(date('d/m/Y')) ?>"></div>
            <div class="form-field"><label for="ajusteResponsable">Responsable</label><input id="ajusteResponsable" readonly value="<?= $escape(Auth::user()['nombre_completo']) ?>"></div>
        </div>
        <div class="form-field"><label for="ajusteLote">Lote / Producto</label><input id="ajusteLote" readonly></div>
        <p id="ajusteDisponible"></p>
        <div class="ajuste-datos-row ajuste-cantidad-row">
        <div class="form-field ajuste-monto-field"><label for="ajusteMonto">Monto</label><input type="number" inputmode="decimal" min="0.001" max="99999999999.999" step="0.001" name="monto" id="ajusteMonto" required value="<?= $escape($formData['monto'] ?? '') ?>"><small>Unidad del lote; hasta 3 decimales.</small></div>
        <div class="form-field" id="ajusteSiloCampo" hidden>
            <label for="ajusteSilo">Silo afectado (Sector3)</label>
            <select id="ajusteSilo" name="idSilo">
                <option value="">Seleccione un silo</option>
                <?php foreach ($silos as $silo) : ?>
                <option value="<?= (int) $silo['idSilo'] ?>" <?= (int) ($formData['idSilo'] ?? 0) === (int) $silo['idSilo'] ? 'selected' : '' ?>><?= $escape($silo['codigo'] . ' - ' . $silo['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <small>Se valida la cantidad del lote en el silo, su capacidad y producto. Para afectar varios silos, registre un ajuste por silo.</small>
        </div>
        </div>
        <div class="form-field"><label for="ajusteObservacion">Observacion</label><textarea id="ajusteObservacion" name="observacion" rows="3" maxlength="1000" required><?= $escape($formData['observacion'] ?? '') ?></textarea></div>
        <div class="form-actions">
            <button class="button-link button-link--submit" type="submit" id="ajusteGuardar">Guardar ajuste</button>
            <button class="button-link button-link--secondary" type="button" id="ajusteCancelar">Cancelar</button>
        </div>
        <p>Los ajustes negativos no pueden consumir cantidades reservadas. La fecha y el responsable se asignan automaticamente en el servidor.</p>
    </form>
</dialog>
<script src="<?= APP_URL ?>/public/js/ajustes-lote.js?v=<?= filemtime(__DIR__ . '/../../../public/js/ajustes-lote.js') ?>"></script>
<?php endif; ?>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
