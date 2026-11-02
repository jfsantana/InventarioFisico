# Reglas e Instrucciones del Agente - InventarioFisico

Eres un asistente experto en ingeniería de software trabajando en el proyecto "InventarioFisico" (Adyarca).

## 1. Stack Tecnológico y Entorno
- Lenguaje: PHP ^8.0 (nativo, tipado estricto donde sea aplicable).
- Arquitectura: MVC artesanal propio (NO es Laravel ni Symfony).
- Base de datos: MySQL / MariaDB mediante PDO (`DB_HOST`, `DB_NAME`, `DB_USER` en `config/config.php`).
- Servidor local: WAMP (`C:\wamp64\www\InventarioFisico`).
- Librerías principales (Composer):
  - PHPMailer: Envío de correos y notificaciones.
  - Dompdf: Generación de PDF (cotizaciones, reportes).
  - PhpOffice / PhpSpreadsheet: Exportación e importación Excel.

## 2. Estructura de Directorios y Convenciones
- `app/Core/`: Núcleo de la aplicación (`App.php`, `Database.php`, `helpers.php`, servicios de dominio como `SiloStockService.php`, `CotizacionPdf.php`).
- `app/Controllers/`: Controladores nombrados `[Nombre]Controller.php`, extienden `Controller`.
- `app/Models/`: Modelos nombrados en singular (`Producto.php`, `Silo.php`, `Cotizacion.php`), extienden `BaseModel`.
- `app/Views/`: Plantillas PHP para vistas e interfaces de usuario.
- `public/`: Punto de entrada (`index.php`), scripts API puntuales (`predespacho_api.php`) y assets (`css/`, `js/`).
- `database/`: Scripts SQL versionados para alteraciones y migraciones de esquema.
- `tests/`: Scripts PHP de regresión ejecutables por CLI (`php tests/...`).

## 3. Principios de Código y Buenas Prácticas
- **Enrutamiento**: Toda nueva ruta se registra en `app/Core/App.php` (en el array `$aliases` o por convención de nombre de controlador).
- **Seguridad en Base de Datos**:
  - SIEMPRE usar sentencias preparadas con PDO (`prepare` + `execute`).
  - NUNCA concatenar variables directamente en cadenas SQL.
- **Transacciones de Inventario**:
  - Operaciones críticas que alteren stock, lotes, silos o predespachos DEBEN ejecutarse dentro de transacciones PDO (`$db->beginTransaction()`, `$db->commit()`, `$db->rollBack()`).
- **Autenticación y Autorización**:
  - Usar la clase `Auth` (`Auth::check()`, `Auth::user()`, `Auth::requireRole()`).
  - Respetar los tiempos de expiración y el flujo de reautenticación para operaciones delicadas (ej. salidas de almacén).
- **Manejo de Errores**:
  - Capturar excepciones y devolver respuestas JSON consistentes en endpoints API (`['success' => false, 'message' => '...']`).

## 4. Estilo de Trabajo del Agente
- Modificaciones quirúrgicas: Cambia solo los archivos necesarios y evita refactorizaciones masivas no solicitadas.
- Scripts de base de datos: Si una tarea requiere una nueva tabla o columna, genera el script `.sql` en `database/`.
- No alterar configuraciones locales ni credenciales privadas (`config/smtp.local.php` o contraseñas en `config/config.php`).