# Diagnostico web de cotizaciones

## Acceso en PRD

1. Desplegar los cambios PHP, las vistas y `public/js/cotizacion.js` juntos.
   Si PRD usa OPcache sin validar fechas, invalidar OPcache o reiniciar PHP
   mediante el procedimiento habitual del servidor. Recargar el formulario.
2. Iniciar sesion con un usuario **Director** (el mismo rol que genera cotizaciones).
3. Abrir directamente `https://TU-DOMINIO/TU-RUTA/cotizacion/diagnosticoLog`.
   Si la aplicacion esta en la raiz, omitir `TU-RUTA`.
   No se agrega ningun enlace al menu. No hace falta consola ni acceso al archivo.
4. El usuario de PHP necesita escritura en `storage/cotizaciones`.
   La pagina realiza una prueba de escritura y muestra una alerta si falla.
   No otorgar permisos publicos de lectura ni habilitar listados de directorios.

La URL es discreta, **no es una clave de acceso**: exige sesion y rol Director.
El visor responde con `Cache-Control: no-store` y escapa el contenido del log.
Los archivos estan fuera de `public`, protegidos por las reglas de Apache de
`storage/.htaccess` y `storage/cotizaciones/.htaccess`. Si PRD usa Nginx/IIS,
configurar la denegacion equivalente para `/storage` antes de desplegar.

## Prueba de principio a fin, solo por web

1. Abrir una nueva cotizacion. Copiar el codigo **Seguimiento de cotizacion**.
2. Seleccionar un cliente distinto de ACTSA Venezuela, agregar un producto y
   elegir **Generar y descargar PDF**.
3. Comprobar visualmente nombre y RIF del PDF y nombre del archivo descargado.
4. Abrir el visor en otra pestana. Seleccionar la fecha del servidor, pegar el
   seguimiento y pulsar **Consultar / Actualizar**. Tambien se puede abrir
   `.../cotizacion/diagnosticoLog?flujo=CODIGO_DE_SEGUIMIENTO`.
5. Verificar los siguientes pasos:
   - `formulario.inicio`, `formulario.catalogos_cargados`, `formulario.listo`.
   - `formulario.cliente_seleccionado`, `formulario.productos_actualizados`,
     `formulario.generacion_elegida` (eventos informativos del navegador).
   - `guardar.recibido`, `cliente.validado`, `guardar.validado`, `bd.esquema`.
   - `bd.cabecera_insertada`, `bd.cotizacion_resuelta`, `bd.commit`.
   - `correo.omitido` para modo PDF.
   - `pdf.solicitado`, `pdf.render_inicio`, `pdf.render_completado`,
     `pdf.respuesta`, `pdf.descarga_completada`.
6. **Los codigos deben coincidir exactamente**: `idClienteSeleccionado`,
   `idClienteGuardado` e `idClienteResuelto`. Nombre y RIF en
   `cliente.validado`, `bd.cotizacion_resuelta` y `pdf.render_inicio` deben
   corresponder al cliente elegido, nunca a un cliente fijo.
7. Repetir con un segundo cliente. Sus datos deben ser diferentes y mantener
   la misma consistencia. El servidor evita almacenar/emparejar codigos mediante
   conversion numerica y deshabilita el cache de la respuesta PDF.
8. Probar **email** y **PDF + email** con una direccion de prueba autorizada.
   Buscar `correo.inicio`, `correo.contactos_cargados`,
   `correo.smtp_configurado`, `correo.envio_inicio`, `correo.envio_completado`.
   En modo solo email no se genera un PDF: se conserva el envio HTML existente.
   Verificar tambien la recepcion real; exito SMTP no garantiza entrega final.
9. Reenviar desde el historial: el nuevo seguimiento aparece en la respuesta
   JSON de esa solicitud, o consultar sin filtro y localizar `idCotizacion`.
10. Sin sesion el visor redirige a login; con un usuario que no es Director
    debe responder 403. El archivo de log no debe ser descargable por HTTP.

`pdf.descarga_completada` indica que el navegador recibio el PDF e inicio la
descarga; no garantiza que el usuario lo haya guardado en disco.
Los errores incluyen etapa, tipo, codigo y ubicacion, sin copiar mensajes
PDO/SMTP que puedan revelar credenciales. Los errores de negocio se muestran.
Cada peticion tiene su propio identificador `peticion`; las del formulario,
guardado y descarga comparten `flujo`. El JSON de las respuestas incluye `flujo`.

## Si PRD muestra un esquema incompatible

El visor consulta el esquema real de PRD. En desarrollo, `idCliente` y
`CardCode` son `varchar(15)`. Ambos deben conservar los codigos completos de
`tbl_clientes_cotizacion`, por ejemplo `COT00001`.

Una columna numerica puede truncar esos codigos a `0` si MySQL no esta en modo
estricto. Ademas, un JOIN entre numeros y codigos de texto puede emparejar
distintos clientes y devolver el primero. Esto es una causa reproducible, pero
**no se puede confirmar la configuracion de PRD desde desarrollo**.

Si `tipoIdCliente` no es `varchar`/`char`, el nuevo codigo bloquea el guardado y
registra el motivo; no modifica automaticamente el esquema ni los historicos.
Si al guardar la BD trunca/cambia el codigo (columna corta, trigger, etc.), se
verifica dentro de la transaccion y se revierte.

Antes de corregir PRD:

- Respaldar BD y revisar tipos, relaciones, triggers y codigos historicos.
- Revisar la migracion existente
  [add_clientes_cotizaciones.sql](../database/add_clientes_cotizaciones.sql)
  con el administrador de BD. **No ejecutarla a ciegas ni repetirla**:
  contiene un nombre fijo de clave foranea y una conversion de clientes antiguos.
- Adaptar la migracion al esquema encontrado para que `idCliente` sea texto y
  su relacion apunte a `tbl_clientes_cotizacion.CardCode`, preservando los datos.
- Las cotizaciones que ya perdieron su codigo original no pueden recuperarse
  adivinando un cliente. Corregirlas solo con evidencia/respaldo o recrearlas.
- Si el esquema ya es correcto, comparar los codigos en el log para localizar
  la primera etapa con diferencia. Confirmar que aparece la version
  `cotizacion-identidad-1`, evitando PHP antiguo en OPcache.

## Archivos y limites

Se escribe un JSON por linea en
`storage/cotizaciones/cotizaciones-AAAA-MM-DD.jsonl`, con hora y zona del servidor.
Rotacion a `.jsonl.1` al superar 5 MiB; se conserva un rotado por fecha.
El visor ofrece los ultimos 14 dias, leyendo hasta 256 KiB finales por archivo.
Un seguimiento antiguo dentro de un archivo muy grande puede quedar fuera de
esa ventana; consultar pronto o pedir al administrador el archivo completo.

No se registran contrasenas SMTP, tokens CSRF, cookies, cuerpos de correo ni
direcciones email. Se registran codigos, nombre/RIF del cliente, numero de
cotizacion, cantidad de detalles, estados, nombre y SHA-256 del PDF. Estos datos
son sensibles de negocio: acceso restringido a directores.
Los archivos no entran en Git. No hay borrado automatico por antiguedad:
programar la retencion segun la politica de PRD y controlar espacio en disco.

## Prueba de regresion de desarrollo

Ejecutar `php tests/cotizacion-regression.php` con PHP 8 y extensiones PDO MySQL,
mbstring y las dependencias Composer existentes. Usa la conexion local
configurada, pero solo tablas **temporales**, sin modificar tablas de negocio
ni enviar correos. Registra eventos `prueba.*` en el log local.
