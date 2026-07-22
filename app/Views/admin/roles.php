<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php $text = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>

<section class="panel report-panel admin-page">
    <p class="eyebrow">Administracion y seguridad</p>
    <h1>Roles y permisos</h1>
    <p class="intro">Matriz actual de permisos por modulo. Los cambios de permisos se gestionan desde base de datos por ahora.</p>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Rol</th><th>Modulo</th><th>Ver</th><th>Editar</th><th>Borrar</th></tr></thead>
            <tbody>
                <?php foreach ($roles as $role) : ?>
                    <?php foreach ($role['permisos'] as $permission) : ?>
                        <tr>
                            <td><?= $text($role['nombre']) ?></td>
                            <td><?= $text($permission['modulo']) ?></td>
                            <td><?= (int) $permission['puede_ver'] ? 'Si' : 'No' ?></td>
                            <td><?= (int) $permission['puede_editar'] ? 'Si' : 'No' ?></td>
                            <td><?= (int) $permission['puede_borrar'] ? 'Si' : 'No' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">Volver al menu</a>
</section>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>