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

## Ajustes de lote por sistema

Para actualizar una instalacion existente, haga una copia de seguridad y ejecute
[database/add_ajustes_lote.sql](database/add_ajustes_lote.sql) en la base de inventario,
despues de [database/add_control_silos.sql](database/add_control_silos.sql). Las tablas
de usuarios deben existir. El script usa los tipos del esquema activo:
`inventarioentrante.idInventarioEntrante` y `usuarios.id_usuario` son `INT` con signo.
Convierte `usuarios` de MyISAM a InnoDB para proteger la relacion con el responsable,
sin cambiar sus registros.
No ejecute una version antigua de la vista de disponibilidad despues de este script.
Si la actualizacion SQL esta pendiente o la vista es antigua, el listado de ajustes
muestra un aviso y no permite operar con saldos incompletos.
No se necesita un procedimiento almacenado ni un estado nuevo de predespacho.
Ejecute tambien [database/add_ajustes_lote_silos.sql](database/add_ajustes_lote_silos.sql)
despues del script anterior. Agrega el detalle por silo y conserva los ajustes
anteriores de un solo silo; puede repetirse sin duplicar esos detalles.

En **Correcciones > Auditar y ajustar > Ajustes de lote por sistema** se puede buscar
un producto/lote, ver la entrada original, ajustes acumulados, reservas y disponible,
y abrir **Ajustar**. Usa los permisos existentes de `corregir_entradas`: ver para
consultar y editar para registrar.
El listado muestra 10 lotes por pagina, conserva la busqueda al navegar y usa
acciones compactas con iconos para ajustar y consultar el historial.

- El positivo suma y el negativo resta definitivamente, sin modificar la entrada
  original ni crear una salida de mercancia o predespacho.
- Monto positivo con hasta tres decimales, observacion obligatoria, fecha/hora actual
  del servidor y responsable obtenido del usuario autenticado.
  El formulario exige elegir Positivo o Negativo mediante radios sin seleccion
  inicial; muestra fecha y responsable en campos de solo lectura.
  La fecha se muestra como dia/mes/ano, sin cambiar la fecha/hora guardada.
  La ventana se puede cerrar con la X, Cancelar o un clic fuera de ella.
- Los negativos no consumen reservas ni permiten superar el disponible. La
  validacion se realiza dentro de una transaccion con bloqueo del lote.
- En Sector3 se distribuye el monto entre uno o varios silos. Su ocupacion se actualiza en la misma
  transaccion, validando producto, capacidad y cantidad del lote en ese silo.
  El lote debe estar completamente distribuido antes de ajustarse. Para varios
  silos, use Agregar silo: la suma debe coincidir exactamente con el monto.
  Las opciones aparecen despues de elegir el tipo: positivos muestran silos vacios
  o del mismo producto con capacidad; negativos muestran solo saldo del lote elegido.
  Las filas y Agregar silo se habilitan solo con tipo y monto validos. Cada cantidad
  queda limitada por el saldo/capacidad del silo y el monto restante por distribuir.
- El historial es de solo consulta. Un error se compensa con otro ajuste de signo
  contrario, indicando el numero del registro original en la observacion. Los lotes
  con ajustes no se pueden eliminar ni cambiar de producto, sector o cantidad original.
- Movimientos por lote incorpora filas lila pastel en orden cronologico dentro de
  cada lote: positivos en Entrada, negativos en Salida, sin duplicar movimientos.
  El saldo de movimientos conserva su significado de saldo fisico; los predespachos
  solo reservan disponibilidad. PDF/impresion y Excel incluyen los ajustes y colores.
- Saldo de productos conserva Entrada original y Salidas reales, muestra Ajustes +
  y Ajustes -, y calcula saldo fisico y disponible incluyendo los ajustes.
- Inteligencia incorpora los ajustes en las entradas/salidas del periodo por fecha
  de creacion y usa el disponible libre de reservas.

Prueba de regresion: `php tests/ajustes-lote-regression.php`. Crea una base MySQL
aislada con nombre aleatorio, aplica el script y elimina esa base al terminar;
no modifica los datos de inventario. Requiere permisos para crear bases de prueba.
Para comprobar tambien los archivos Excel necesita las extensiones PHP `zip`,
`mbstring` y `pdo_mysql`, y las dependencias de Composer instaladas.

## Ejecutar

Para comprobar el cliente del PDF y consultar el seguimiento por web, ver
[Diagnostico de cotizaciones](docs/cotizaciones-diagnostico.md).

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