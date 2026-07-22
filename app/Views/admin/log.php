<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php $text = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>

<section class="panel report-panel admin-page">
    <div class="admin-heading">
        <div>
            <p class="eyebrow">Administracion y seguridad</p>
            <h1>Auditoría</h1>
            <p class="intro">Intentos de acceso, cierres de sesión y acciones administrativas.</p>
        </div>
        <a class="button-link" href="<?= APP_URL ?>/admin/log?<?= http_build_query(array_merge($filters, ['export' => 'csv'])) ?>">Exportar CSV</a>
    </div>

    <form class="admin-filters" method="get" action="<?= APP_URL ?>/admin/log">
        <select name="usuario"><option value="">Todos los usuarios</option><?php foreach ($usuarios as $usuario) : ?><option value="<?= $text($usuario['username']) ?>" <?= ($filters['usuario'] ?? '') === $usuario['username'] ? 'selected' : '' ?>><?= $text($usuario['username']) ?></option><?php endforeach; ?></select>
        <input name="modulo" type="text" value="<?= $text($filters['modulo'] ?? '') ?>" placeholder="Modulo">
        <input name="desde" type="date" value="<?= $text($filters['desde'] ?? '') ?>">
        <input name="hasta" type="date" value="<?= $text($filters['hasta'] ?? '') ?>">
        <select name="resultado"><option value="">Exito/fallo</option><option value="exitoso" <?= ($filters['resultado'] ?? '') === 'exitoso' ? 'selected' : '' ?>>Exitoso</option><option value="fallo" <?= ($filters['resultado'] ?? '') === 'fallo' ? 'selected' : '' ?>>Fallo</option></select>
        <button class="button-link button-link--submit" type="submit">Filtrar</button>
    </form>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Fecha/hora</th><th>Usuario</th><th>Modulo</th><th>Accion</th><th>IP</th><th>Resultado</th></tr></thead>
            <tbody>
                <?php foreach ($logs as $log) : ?>
                    <tr class="<?= $log['resultado'] === 'fallo' ? 'log-row--fail' : '' ?>">
                        <td><?= $text($log['fecha']) ?></td><td><?= $text($log['username'] ?? 'anonimo') ?></td><td><?= $text($log['modulo']) ?></td><td><?= $text($log['accion']) ?></td><td><?= $text($log['ip']) ?></td><td><?= $text($log['resultado']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions">
        <?php $page = max(1, (int) ($filters['page'] ?? 1)); $baseFilters = $filters; unset($baseFilters['page']); ?>
        <a class="button-link button-link--secondary" href="<?= APP_URL ?>/admin/log?<?= http_build_query(array_merge($baseFilters, ['page' => max(1, $page - 1)])) ?>">Anterior</a>
        <span class="field-help">Pagina <?= $page ?></span>
        <a class="button-link button-link--secondary" href="<?= APP_URL ?>/admin/log?<?= http_build_query(array_merge($baseFilters, ['page' => $page + 1])) ?>">Siguiente</a>
    </div>
</section>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>