# Inventario Fisico

Aplicacion PHP basica con patron MVC y conexion PDO a MySQL local.

## Requisitos

- WAMP instalado y en ejecucion
- PHP con extension `pdo_mysql` habilitada
- MySQL local
- Composer

## Configurar la base de datos

Edita [config/config.php](config/config.php) y ajusta estos valores:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'inventariofisico');
define('DB_USER', 'root');
define('DB_PASS', '');
```

Instala las dependencias PHP con:

```text
composer install
```

Configura tambien `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME`,
`SMTP_PASSWORD`, `SMTP_FROM_EMAIL` y `SMTP_FROM_NAME` en el archivo local de
configuracion o mediante variables de entorno. No incluyas credenciales SMTP en Git.

Puedes crear la base de datos con:

```sql
CREATE DATABASE inventariofisico CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

O importa el archivo [database/schema.sql](database/schema.sql) desde phpMyAdmin o MySQL.

## Ejecutar

Con WAMP, abre:

```text
http://localhost/InventarioFisico/
```

Tambien puedes apuntar un VirtualHost de Apache a la carpeta [public](public) para usarla como raiz publica.

## Estructura

```text
app/
  Controllers/
  Core/
  Models/
  Views/
config/
public/
```

- [public/index.php](public/index.php): punto de entrada.
- [app/Core/App.php](app/Core/App.php): resuelve controlador, metodo y parametros desde la URL.
- [app/Core/Controller.php](app/Core/Controller.php): carga modelos y vistas.
- [app/Core/Database.php](app/Core/Database.php): conexion PDO a MySQL.
- [app/Controllers/HomeController.php](app/Controllers/HomeController.php): controlador inicial.