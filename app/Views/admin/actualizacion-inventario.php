<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php
$text = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$staging = $staging ?? null;
$backupsRecientes = $backupsRecientes ?? [];
$messageType = $messageType ?? 'success';
$tieneStaging = is_array($staging) && !empty($staging['resumen']);
$esValido = $tieneStaging && !empty($staging['exito']);
$resumen = $staging['resumen'] ?? [];
?>

<section class="panel report-panel admin-page actualizacion-inventario-page">
    <div class="admin-heading">
        <div>
            <p class="eyebrow">Administración y Operaciones Especiales</p>
            <h1>Actualización de Inventario</h1>
            <p class="intro">Procedimiento de reinicio y carga masiva de inventario inicial desde archivo Excel.</p>
        </div>
        <a class="button-link button-link--secondary" href="<?= APP_URL ?>/">← Volver al Menú</a>
    </div>

    <?php if (!empty($message)) : ?>
        <div class="message message--<?= $messageType === 'error' ? 'error' : 'success' ?>" role="status">
            <?= $text($message) ?>
        </div>
    <?php endif; ?>

    <!-- Guía de Pasos -->
    <div class="process-stepper">
        <div class="step-card <?= !$tieneStaging ? 'step-card--active' : 'step-card--completed' ?>">
            <div class="step-number">1</div>
            <div class="step-info">
                <strong>Subir Archivo Excel</strong>
                <span>Carga del archivo de inventario exportado de SAP</span>
            </div>
        </div>
        <div class="step-card <?= $tieneStaging && !$esValido ? 'step-card--warning' : ($tieneStaging ? 'step-card--active' : '') ?>">
            <div class="step-number">2</div>
            <div class="step-info">
                <strong>Análisis y Validación</strong>
                <span>Cotejo de productos, lotes, presentaciones y tanques</span>
            </div>
        </div>
        <div class="step-card <?= $esValido ? 'step-card--danger' : '' ?>">
            <div class="step-number">3</div>
            <div class="step-info">
                <strong>Respaldo y Confirmación</strong>
                <span>Generación de backup SQL y autorización de vaciado</span>
            </div>
        </div>
        <div class="step-card">
            <div class="step-number">4</div>
            <div class="step-info">
                <strong>Carga de Inventario</strong>
                <span>Inserción en inventarioentrante y asignación a silos</span>
            </div>
        </div>
    </div>

    <?php if (!$tieneStaging) : ?>
        <!-- FASE 1: Formulario de Carga y Especificaciones -->
        <div class="admin-card-grid">
            <div class="admin-card-section">
                <h2>1. Cargar Archivo Excel de Inventario</h2>
                <p>Seleccione el archivo en formato <code>.xlsx</code> o <code>.xls</code>. El sistema leerá el archivo en memoria, verificará los datos y solicitará su confirmación antes de modificar cualquier registro.</p>

                <form class="upload-excel-form" method="post" action="<?= APP_URL ?>/admin/procesarExcelInventario" enctype="multipart/form-data">
                    <?= Auth::csrfField() ?>
                    <div class="file-dropzone">
                        <label for="archivo_excel" class="dropzone-label">
                            <span class="dropzone-icon">📊</span>
                            <strong>Haga clic aquí para seleccionar el archivo Excel</strong>
                            <small>Formatos permitidos: .xlsx, .xls (tamaño máximo recomendado: 10 MB)</small>
                        </label>
                        <input type="file" id="archivo_excel" name="archivo_excel" accept=".xlsx, .xls" required>
                    </div>

                    <div class="form-actions" style="margin-top: 18px;">
                        <button type="submit" class="button-link button-link--submit" style="font-size: 1rem; padding: 10px 24px;">
                            Subir y Validar Inventario →
                        </button>
                    </div>
                </form>
            </div>

            <div class="admin-card-section admin-card-section--info">
                <h3>Estructura Requerida del Archivo Excel</h3>
                <p>El archivo debe contener las siguientes columnas (en la primera o segunda fila de encabezado):</p>
                <div class="table-responsive">
                    <table class="spec-table">
                        <thead>
                            <tr><th>Columna en Excel</th><th>Destino en Sistema</th><th>Regla de Validación</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Número de artículo</strong></td>
                                <td><code>Producto.codigoInterno</code></td>
                                <td>Código del producto. Si no existe, se creará automáticamente en la tabla Producto.</td>
                            </tr>
                            <tr>
                                <td><strong>Descripción del artículo</strong></td>
                                <td><code>Producto.nombre</code></td>
                                <td>Nombre comercial del producto para la creación si no existe.</td>
                            </tr>
                            <tr>
                                <td><strong>Lote</strong></td>
                                <td><code>inventarioentrante.NumLote</code></td>
                                <td>Identificador del lote físico entrante. Obligatorio.</td>
                            </tr>
                            <tr>
                                <td><strong>Almacén</strong> y <strong>Nombre del almacén</strong></td>
                                <td><code>ubicacion.idUbicacion</code></td>
                                <td>Identifica el almacén físico. Si no existe, se registra automáticamente.</td>
                            </tr>
                            <tr>
                                <td><strong>Cantidad</strong></td>
                                <td><code>CantidadEntrante</code> / <code>peso_romana</code></td>
                                <td>Valor numérico mayor a cero (kg o unidades de entrada).</td>
                            </tr>
                            <tr>
                                <td><strong>ZONA</strong></td>
                                <td><code>inventarioentrante.sector</code></td>
                                <td>Debe ser <code>1</code> (Sector1), <code>2</code> (Sector2) o <code>3</code> (Sector3).</td>
                            </tr>
                            <tr>
                                <td><strong>PRESENTACION</strong> y <strong>PESO</strong></td>
                                <td><code>presentacion.nombre</code></td>
                                <td>Se concatenan como <code>PRESENTACION (PESO KG)</code> (ej. <em>TAMBOR (190 KG)</em>). Se crea si no existe.</td>
                            </tr>
                            <tr>
                                <td><strong>TANQUE</strong></td>
                                <td><code>silo_asignaciones.idSilo</code></td>
                                <td>Obligatorio únicamente para Sector 3. Debe coincidir con el código o número de tanque activo (ej. 15 -> 015).</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else : ?>
        <!-- FASE 2: Resumen de Validación y Confirmación de Vaciado -->
        <div class="validation-summary-box">
            <div class="summary-header">
                <div>
                    <h2>Resumen de Validación del Archivo Excel</h2>
                    <p>
                        Cargado por: <strong><?= $text($resumen['usuario_carga']['username'] ?? 'Usuario') ?></strong> |
                        Fecha: <strong><?= $text($resumen['fecha_carga_formateada'] ?? date('d/m/Y H:i:s')) ?></strong>
                    </p>
                </div>
                <form method="post" action="<?= APP_URL ?>/admin/cancelarReinicioInventario">
                    <?= Auth::csrfField() ?>
                    <button type="submit" class="button-link button-link--secondary">✕ Cancelar y Descartar</button>
                </form>
            </div>

            <!-- Contadores principales -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <span class="metric-val"><?= number_format($resumen['total_registros_validos'] ?? 0) ?></span>
                    <span class="metric-label">Registros de Entrada Válidos</span>
                </div>
                <div class="metric-card">
                    <span class="metric-val"><?= number_format($resumen['total_kilos'] ?? 0, 3) ?> kg</span>
                    <span class="metric-label">Total Kilos / Cantidad</span>
                </div>
                <div class="metric-card">
                    <span class="metric-val"><?= count($resumen['nuevos_productos'] ?? []) ?></span>
                    <span class="metric-label">Productos Nuevos a Crear</span>
                </div>
                <div class="metric-card">
                    <span class="metric-val"><?= count($resumen['nuevas_presentaciones'] ?? []) ?></span>
                    <span class="metric-label">Presentaciones Nuevas</span>
                </div>
                <div class="metric-card">
                    <span class="metric-val"><?= count($resumen['nuevas_ubicaciones'] ?? []) ?></span>
                    <span class="metric-label">Almacenes Nuevos</span>
                </div>
                <div class="metric-card">
                    <span class="metric-val"><?= count($resumen['por_tanque'] ?? []) ?></span>
                    <span class="metric-label">Tanques Sector 3 a Asignar</span>
                </div>
            </div>

            <!-- Errores bloqueantes si los hay -->
            <?php if (!empty($resumen['errores'])) : ?>
                <div class="error-details-box">
                    <h3>⚠️ Errores Encontrados (<?= count($resumen['errores']) ?>)</h3>
                    <p>El archivo presenta incongruencias que impiden continuar con el vaciado. Por favor corrija el Excel y vuelva a subirlo:</p>
                    <ul class="error-list">
                        <?php foreach (array_slice($resumen['errores'], 0, 25) as $err) : ?>
                            <li><?= $text($err) ?></li>
                        <?php endforeach; ?>
                        <?php if (count($resumen['errores']) > 25) : ?>
                            <li><em>... y <?= count($resumen['errores']) - 25 ?> errores adicionales.</em></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Si todo es válido: Respaldo y Autorización de Vaciado -->
            <?php if ($esValido) : ?>
                <!-- Notificación de Respaldo -->
                <div class="backup-notification-card">
                    <div class="backup-icon">🛡️</div>
                    <div>
                        <strong>Respaldo de Seguridad Preparado</strong>
                        <p>Se ha generado una copia de seguridad preventiva de la base de datos completa antes de realizar cualquier acción.</p>
                        <?php if (!empty($staging['backup_preventivo']['archivo'])) : ?>
                            <a class="button-link button-link--secondary" href="<?= APP_URL ?>/admin/descargarBackup?archivo=<?= urlencode($staging['backup_preventivo']['archivo']) ?>">
                                📥 Descargar Respaldo SQL (<?= $text($staging['backup_preventivo']['tamano_formateado'] ?? '') ?>)
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Detalle de nuevos maestros a crear -->
                <?php if (!empty($resumen['nuevos_productos'])) : ?>
                    <details class="accordion-section" open>
                        <summary><strong>Ver <?= count($resumen['nuevos_productos']) ?> productos nuevos que se registrarán automáticamente</strong></summary>
                        <div class="tag-cloud">
                            <?php foreach ($resumen['nuevos_productos'] as $prod) : ?>
                                <span class="tag-item"><strong><?= $text($prod['codigoInterno']) ?></strong>: <?= $text($prod['nombre']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>

                <?php if (!empty($resumen['por_tanque'])) : ?>
                    <details class="accordion-section" open>
                        <summary><strong>Distribución de carga en Tanques de Sector 3 (<?= count($resumen['por_tanque']) ?> tanques)</strong></summary>
                        <div class="silo-distribution-grid">
                            <?php foreach ($resumen['por_tanque'] as $siloCod => $kilos) : ?>
                                <div class="silo-badge">
                                    <strong>Tanque <?= $text($siloCod) ?></strong>
                                    <span><?= number_format($kilos, 3) ?> kg</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>

                <!-- Formulario de Ejecución Destructiva con Doble Factor -->
                <div class="critical-confirmation-card">
                    <div class="critical-header">
                        <span class="warning-triangle">⚠️</span>
                        <div>
                            <h3>CONFIRMACIÓN DE VACIADO DE MOVIMIENTOS Y REINICIO</h3>
                            <p>Esta acción es irreversible y realizará los siguientes cambios inmediatos:</p>
                        </div>
                    </div>

                    <ul class="danger-bullets">
                        <li><strong>ELIMINARÁ</strong> todos los registros históricos en: <em>Entradas de Mercancía, Salidas de Mercancía, Solicitudes e Ítems de Predespacho, Ajustes de Lote de Sistema, Salidas y Asignaciones de Silos, y Documentos de Entrada</em>.</li>
                        <li><strong>CONSERVARÁ</strong> intactas las tablas maestras: <em>Usuarios, Roles, Permisos, Tanques/Silos, Clientes, Catálogo de Productos y Cotizaciones</em>.</li>
                        <li><strong>CARGARÁ</strong> los <strong><?= number_format($resumen['total_registros_validos']) ?></strong> lotes del Excel como el nuevo inventario inicial.</li>
                    </ul>

                    <form class="confirmation-form" method="post" action="<?= APP_URL ?>/admin/ejecutarReinicioInventario">
                        <?= Auth::csrfField() ?>

                        <div class="confirmation-inputs">
                            <label>
                                Para confirmar el vaciado, escriba exactamente: <code>CONFIRMAR VACIADO</code>
                                <input type="text" name="confirmacion_texto" placeholder="CONFIRMAR VACIADO" required autocomplete="off">
                            </label>

                            <label>
                                Ingrese su contraseña de Administrador para autorizar:
                                <input type="password" name="admin_password" placeholder="Su contraseña actual" required autocomplete="current-password">
                            </label>
                        </div>

                        <div class="form-actions-danger">
                            <button type="submit" class="button-link button-link--danger" onclick="return confirm('¿Está COMPLETAMENTE SEGURO de vaciar todos los movimientos y reiniciar el inventario? Esta acción no se puede deshacer.');">
                                ⚠️ EJECUTAR VACIADO Y CARGAR INVENTARIO INICIAL
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Sección de Respaldos Recientes -->
    <?php if (!empty($backupsRecientes)) : ?>
        <div class="backups-history-card">
            <h3>Respaldos de Base de Datos Disponibles en Servidor</h3>
            <p class="intro">Copias de seguridad automáticas almacenadas de forma protegida en el servidor:</p>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr><th>Archivo SQL</th><th>Fecha de Generación</th><th>Tamaño</th><th>Acción</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backupsRecientes as $bkp) : ?>
                            <tr>
                                <td><code><?= $text($bkp['nombre']) ?></code></td>
                                <td><?= $text($bkp['fecha']) ?></td>
                                <td><?= $text($bkp['tamano']) ?></td>
                                <td>
                                    <a class="button-link button-link--secondary" href="<?= APP_URL ?>/admin/descargarBackup?archivo=<?= urlencode($bkp['nombre']) ?>">
                                        Descargar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>

<style>
.actualizacion-inventario-page .process-stepper {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    margin: 18px 0 24px;
}
.step-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    background: #f8faf9;
    border: 1px solid #d0dcd4;
    border-radius: 8px;
}
.step-card--active {
    background: #eaf5ee;
    border-color: #28743b;
}
.step-card--completed {
    background: #f0f8f3;
    border-color: #83bfa0;
    opacity: 0.85;
}
.step-card--danger {
    background: #fff5f5;
    border-color: #e53e3e;
}
.step-card--warning {
    background: #fffaf0;
    border-color: #dd6b20;
}
.step-number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    background: #28743b;
    color: #fff;
    border-radius: 50%;
    font-weight: 700;
    font-size: 0.85rem;
    flex-shrink: 0;
}
.step-card--danger .step-number { background: #e53e3e; }
.step-card--warning .step-number { background: #dd6b20; }
.step-info strong { display: block; font-size: 0.9rem; color: #1a202c; }
.step-info span { font-size: 0.75rem; color: #718096; }

.admin-card-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 14px;
}
@media (max-width: 900px) {
    .admin-card-grid { grid-template-columns: 1fr; }
}
.admin-card-section {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
}
.admin-card-section--info {
    background: #f7fafc;
}
.file-dropzone {
    border: 2px dashed #319795;
    border-radius: 8px;
    padding: 28px 16px;
    text-align: center;
    background: #f0fff4;
    cursor: pointer;
    margin: 14px 0;
}
.dropzone-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    cursor: pointer;
}
.dropzone-icon { font-size: 2.2rem; }
.file-dropzone input[type="file"] {
    margin-top: 10px;
}

.spec-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
    margin-top: 8px;
}
.spec-table th, .spec-table td {
    padding: 6px 10px;
    border: 1px solid #e2e8f0;
    text-align: left;
}
.spec-table th { background: #edf2f7; }

.validation-summary-box {
    background: #ffffff;
    border: 1px solid #cbd5e0;
    border-radius: 8px;
    padding: 22px;
    margin-top: 14px;
}
.summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 14px;
    margin-bottom: 16px;
}
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.metric-card {
    background: #f7fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 14px;
    text-align: center;
}
.metric-val {
    display: block;
    font-size: 1.35rem;
    font-weight: 700;
    color: #234e52;
}
.metric-label {
    font-size: 0.78rem;
    color: #4a5568;
}

.backup-notification-card {
    display: flex;
    align-items: center;
    gap: 14px;
    background: #ebf8ff;
    border: 1px solid #90cdf4;
    border-radius: 8px;
    padding: 14px 18px;
    margin-bottom: 18px;
}
.backup-icon { font-size: 1.8rem; }

.error-details-box {
    background: #fff5f5;
    border: 1px solid #feb2b2;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 20px;
}
.error-details-box h3 { color: #c53030; margin-top: 0; }
.error-list { margin: 8px 0 0 20px; font-size: 0.85rem; color: #9b2c2c; }

.accordion-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 14px;
    font-size: 0.85rem;
}
.accordion-section summary { cursor: pointer; color: #2d3748; }
.tag-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
}
.tag-item {
    background: #edf2f7;
    border: 1px solid #cbd5e0;
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 0.78rem;
}
.silo-distribution-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 8px;
    margin-top: 10px;
}
.silo-badge {
    background: #e6fffa;
    border: 1px solid #81e6d9;
    border-radius: 6px;
    padding: 8px;
    text-align: center;
}
.silo-badge strong { display: block; font-size: 0.85rem; color: #234e52; }
.silo-badge span { font-size: 0.75rem; color: #285e61; }

.critical-confirmation-card {
    background: #fffaf0;
    border: 2px solid #dd6b20;
    border-radius: 8px;
    padding: 20px;
    margin-top: 22px;
}
.critical-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}
.warning-triangle { font-size: 2rem; }
.critical-header h3 { color: #c05621; margin: 0; }
.danger-bullets {
    margin: 10px 0 16px 20px;
    font-size: 0.88rem;
    color: #4a5568;
}
.danger-bullets li { margin-bottom: 6px; }
.confirmation-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}
@media (max-width: 700px) {
    .confirmation-inputs { grid-template-columns: 1fr; }
}
.confirmation-inputs label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #2d3748;
}
.confirmation-inputs input {
    display: block;
    width: 100%;
    margin-top: 6px;
    padding: 8px 12px;
    border: 1px solid #cbd5e0;
    border-radius: 6px;
    font-size: 0.95rem;
}
.form-actions-danger {
    text-align: right;
    margin-top: 10px;
}
.button-link--danger {
    background: #e53e3e;
    color: #ffffff;
    border: none;
    padding: 12px 24px;
    font-weight: 700;
    font-size: 0.95rem;
    border-radius: 6px;
    cursor: pointer;
}
.button-link--danger:hover {
    background: #c53030;
}
.backups-history-card {
    margin-top: 26px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 18px;
}
</style>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
