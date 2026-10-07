<?php
$text = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Diagnostico de cotizaciones</title>
    <style>
        body{font-family:system-ui,sans-serif;margin:24px;background:#f5f5f5;color:#222}
        pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#fff;padding:16px;border:1px solid #ccc}
        label{display:inline-block;margin:8px}input{padding:8px}button{padding:8px}
    </style>
</head>
<body>
    <h1>Log exclusivo de cotizaciones</h1>
    <p>Acceso exclusivo para directores. Disponible en Accesos y auditoría. Actualice para consultar los nuevos pasos.</p>
    <p><a href="<?= $text(APP_URL . '/') ?>">Volver al menú principal</a></p>
    <?php if (!$logDisponible) : ?>
        <p role="alert"><strong>LOG NO DISPONIBLE:</strong> no se pudo escribir. Otorgue permisos de escritura al usuario de PHP sobre storage/cotizaciones. Este fallo tambien se informa al log de errores de PHP.</p>
    <?php endif; ?>
    <form method="get" action="<?= $text(APP_URL . '/cotizacion/diagnosticoLog') ?>">
        <label>Fecha <input type="date" name="fecha" value="<?= $text($fecha) ?>" min="<?= date('Y-m-d', strtotime('-13 days')) ?>" max="<?= date('Y-m-d') ?>" required></label>
        <label>Seguimiento <input name="flujo" value="<?= $text($flujo) ?>" maxlength="32" pattern="[a-f0-9]{32}" placeholder="Opcional"></label>
        <button type="submit">Consultar / Actualizar</button>
    </form>
    <h2>Esquema actual de este servidor</h2>
    <?php if (!in_array($esquema['tipoIdCliente'], ['varchar', 'char'], true)) : ?>
        <p role="alert"><strong>ESQUEMA INCOMPATIBLE:</strong> idCliente debe ser texto, no numero. La creacion se bloquea para evitar asignaciones incorrectas. No cambie datos historicos sin respaldo y verificacion.</p>
    <?php endif; ?>
    <pre><?= $text(json_encode($esquema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    <h2>Eventos del <?= $text($fecha) ?></h2>
    <p>Hasta los ultimos 256 KiB de cada archivo diario (actual y rotado). Los eventos del navegador son informativos; la BD y el servidor determinan el cliente real. No se muestran credenciales, tokens, cuerpos de correo ni direcciones email.</p>
    <pre><?= $text($contenido !== '' ? $contenido : 'No hay eventos para este filtro. Genere una cotizacion y actualice esta pagina.') ?></pre>
</body>
</html>
