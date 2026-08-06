=== ESTRUCTURA DE BASE DE DATOS ===

Fuente: esquema activo `inventariofisico`, consultado mediante `information_schema` el 2026-08-06.
Nota: una "FK física" existe como constraint en MySQL. Una "FK lógica" se usa en los JOIN de la aplicación, pero no está protegida por un constraint. La mayoría de las tablas son MyISAM, por lo que sus relaciones son lógicas.

TABLA: producto
Propósito: Catálogo maestro de productos o artículos manejados en el inventario.
Columnas relevantes:
  - idProducto (int) [PK, NOT NULL]
  - codigoInterno (varchar(50)) [NOT NULL]
  - nombre (varchar(100)) [NOT NULL]
Se relaciona con:
  - inventarioentrante a través de inventarioentrante.idProducto → producto.idProducto [FK lógica, 1 a muchos]

TABLA: presentacion
Propósito: Catálogo de presentaciones o unidades de empaque de los productos, por ejemplo sacos, tambores o granel.
Columnas relevantes:
  - idPresentacion (int) [PK, NOT NULL]
  - nombre (varchar(50)) [NOT NULL]
Se relaciona con:
  - inventarioentrante a través de inventarioentrante.idPresentacion → presentacion.idPresentacion [FK lógica, 1 a muchos]

TABLA: ubicacion
Propósito: Catálogo de ubicaciones físicas donde se almacena el inventario.
Columnas relevantes:
  - idUbicacion (int) [PK, NOT NULL]
  - nombre (varchar(200)) [NOT NULL]
Se relaciona con:
  - inventarioentrante a través de inventarioentrante.idUbicación → ubicacion.idUbicacion [FK lógica, 1 a muchos]

TABLA: inventarioentrante
Propósito: Registra cada entrada física de inventario. Cada fila representa un lote de un producto con presentación, ubicación, sector, cantidad inicial y fecha de entrada.
Columnas relevantes:
  - idInventarioEntrante (int) [PK, NOT NULL]
  - NumLote (varchar(100)) [NOT NULL]
  - idProducto (int) [FK lógica, NOT NULL]
  - idPresentacion (int) [FK lógica, NOT NULL]
  - idUbicación (int) [FK lógica, NOT NULL]
  - CantidadEntrante (int) [NOT NULL]
  - fecha (date) [NOT NULL]
  - sector (varchar(50)) [NOT NULL]
Se relaciona con:
  - producto a través de idProducto → idProducto [muchos a 1]
  - presentacion a través de idPresentacion → idPresentacion [muchos a 1]
  - ubicacion a través de idUbicación → idUbicacion [muchos a 1]
  - inventariosaliente a través de inventariosaliente.idInventarioEntrante → inventarioentrante.idInventarioEntrante [FK lógica, 1 a muchos]
  - tbl_items_predespacho a través de tbl_items_predespacho.idInventarioEntrante → inventarioentrante.idInventarioEntrante [FK lógica, 1 a muchos]
  - v_disponibilidad_lotes a través de idInventarioEntrante → idInventarioEntrante [1 a 1 derivada]

TABLA: inventariosaliente
Propósito: Registra salidas físicas de inventario contra un lote de entrada; NE identifica la nota de entrega o código de predespacho asociado.
Columnas relevantes:
  - idInventarioSaliente (int unsigned) [PK, NOT NULL]
  - idInventarioEntrante (int unsigned) [FK lógica, NOT NULL]
  - sector (varchar(30)) [nullable]
  - NE (varchar(80)) [NOT NULL]
  - cantidadSaliente (decimal(12,2)) [NOT NULL]
  - fecha (datetime) [NOT NULL]
Se relaciona con:
  - inventarioentrante a través de idInventarioEntrante → idInventarioEntrante [muchos a 1]
  - tbl_cabecera_predespacho mediante NE → codigoInterno y el lote indicado por tbl_items_predespacho.idInventarioEntrante [relación lógica, muchos a 1]

TABLA: tbl_cliente
Propósito: Catálogo de clientes utilizados como destinatarios de los predespachos.
Columnas relevantes:
  - idCliente (int unsigned) [PK, NOT NULL]
  - rif (varchar(20)) [NOT NULL, UNIQUE]
  - nombre (varchar(150)) [NOT NULL]
  - direccion (text) [nullable]
  - tipo (varchar(50)) [nullable]
  - activo (tinyint(1)) [NOT NULL]
  - fechaCreacion (timestamp) [NOT NULL]
  - fechaActualizacion (timestamp) [NOT NULL]
Se relaciona con:
  - tbl_cabecera_predespacho a través de tbl_cabecera_predespacho.idCliente → tbl_cliente.idCliente [FK física, 1 a muchos]

TABLA: tbl_cabecera_predespacho
Propósito: Cabecera de una solicitud de predespacho para un cliente; contiene código, fecha prevista de retiro, estado y datos de seguimiento.
Columnas relevantes:
  - idCabeceraPredespacho (int unsigned) [PK, NOT NULL]
  - idCliente (int unsigned) [FK física, NOT NULL]
  - fechaRetiro (date) [NOT NULL]
  - codigoInterno (varchar(20)) [NOT NULL, UNIQUE]
  - codigoNotaEntregaSAP (varchar(50)) [nullable]
  - userCreador (varchar(100)) [NOT NULL]
  - statusGeneralPredespacho (enum('abierto','pendiente','cerrado')) [NOT NULL]
  - observaciones (text) [nullable]
  - fechaCreacion (timestamp) [NOT NULL]
  - fechaActualizacion (timestamp) [NOT NULL]
Se relaciona con:
  - tbl_cliente a través de idCliente → idCliente [muchos a 1]
  - tbl_items_predespacho a través de tbl_items_predespacho.idCabeceraPredespacho → tbl_cabecera_predespacho.idCabeceraPredespacho [FK física, 1 a muchos]
  - inventariosaliente mediante inventariosaliente.NE → codigoInterno [relación lógica, 1 a muchos; requiere también validar el lote del item]
  - usuarios mediante userCreador → usuarios.username [relación lógica, muchos a 1]

TABLA: tbl_items_predespacho
Propósito: Detalle de lotes y cantidades solicitadas dentro de cada predespacho. Actúa como tabla puente entre predespachos y lotes de inventario.
Columnas relevantes:
  - idItem (int unsigned) [PK, NOT NULL]
  - idCabeceraPredespacho (int unsigned) [FK física, NOT NULL]
  - idInventarioEntrante (int unsigned) [FK lógica, NOT NULL]
  - cantidadSolicitada (decimal(14,4)) [NOT NULL]
  - cantidadDespachada (decimal(14,4)) [NOT NULL]
  - tipo (varchar(50)) [nullable]
  - estatusItemPredespacho (enum('abierto','pendiente','cerrado')) [NOT NULL]
  - fechaCreacion (timestamp) [NOT NULL]
  - fechaActualizacion (timestamp) [NOT NULL]
Se relaciona con:
  - tbl_cabecera_predespacho a través de idCabeceraPredespacho → idCabeceraPredespacho [muchos a 1]
  - inventarioentrante a través de idInventarioEntrante → idInventarioEntrante [FK lógica, muchos a 1]
  - inventariosaliente mediante idInventarioEntrante y el codigoInterno de la cabecera comparado con NE [relación lógica]

TABLA: roles
Propósito: Catálogo de roles de seguridad asignables a usuarios.
Columnas relevantes:
  - id_rol (int) [PK, NOT NULL]
  - nombre_rol (varchar(50)) [NOT NULL, UNIQUE]
  - descripcion (varchar(255)) [nullable]
  - activo (tinyint(1)) [nullable]
  - fecha_creacion (datetime) [nullable]
  - nombre (varchar(60)) [nullable]
Se relaciona con:
  - usuarios a través de usuarios.id_rol → roles.id_rol [FK lógica, 1 a muchos]
  - permisos_modulo a través de permisos_modulo.id_rol → roles.id_rol [FK lógica, 1 a muchos]

TABLA: usuarios
Propósito: Cuentas de acceso a la aplicación, credenciales, rol, estado y control de bloqueos.
Columnas relevantes:
  - id_usuario (int) [PK, NOT NULL]
  - nombre_completo (varchar(150)) [NOT NULL]
  - username (varchar(50)) [NOT NULL, UNIQUE]
  - password_hash (varchar(255)) [NOT NULL]
  - id_rol (int) [FK lógica, NOT NULL]
  - activo (tinyint(1)) [nullable]
  - fecha_creacion (datetime) [nullable]
  - ultimo_acceso (datetime) [nullable]
  - creado_por (int) [FK lógica autorreferente, nullable]
  - intentos_fallidos (int unsigned) [NOT NULL]
  - bloqueado_hasta (datetime) [nullable]
  - actualizado_en (datetime) [nullable]
Se relaciona con:
  - roles a través de id_rol → id_rol [muchos a 1]
  - usuarios a través de creado_por → id_usuario [relación lógica autorreferente, muchos a 1]
  - log_accesos a través de log_accesos.id_usuario → usuarios.id_usuario [FK lógica, 1 a muchos]
  - tbl_cabecera_predespacho mediante username → userCreador [relación lógica, 1 a muchos]

TABLA: permisos_modulo
Propósito: Define permisos de visualización, edición y borrado para cada combinación de rol y módulo.
Columnas relevantes:
  - id_permiso (int) [PK, NOT NULL]
  - id_rol (int) [FK lógica, NOT NULL]
  - modulo (varchar(50)) [NOT NULL]
  - puede_ver (tinyint(1)) [nullable]
  - puede_editar (tinyint(1)) [nullable]
  - puede_borrar (tinyint(1)) [nullable]
Se relaciona con:
  - roles a través de id_rol → id_rol [muchos a 1]
Nota: la combinación id_rol + modulo tiene índice UNIQUE.

TABLA: log_accesos
Propósito: Bitácora de accesos y acciones de usuarios para auditoría, incluyendo módulo, resultado, IP, detalle y fecha.
Columnas relevantes:
  - id_log (int) [PK, NOT NULL]
  - id_usuario (int) [FK lógica, nullable]
  - username (varchar(50)) [nullable]
  - accion (varchar(100)) [nullable]
  - modulo (varchar(50)) [nullable]
  - ip_address (varchar(45)) [nullable]
  - fecha_hora (datetime) [nullable]
  - exitoso (tinyint(1)) [nullable]
  - ip (varchar(45)) [nullable]
  - resultado (varchar(20)) [nullable]
  - detalle (varchar(255)) [nullable]
  - fecha (datetime) [nullable]
Se relaciona con:
  - usuarios a través de id_usuario → id_usuario [muchos a 1, FK lógica]
  - usuarios alternativamente mediante username → username [relación lógica]
Nota: ip_address/fecha_hora/exitoso son columnas heredadas; el código actual usa principalmente ip/fecha/resultado.

TABLA: v_disponibilidad_lotes (VISTA)
Propósito: Vista calculada de disponibilidad por lote. Parte de la cantidad entrante y descuenta las salidas físicas y las cantidades todavía reservadas en predespachos abiertos o pendientes.
Columnas relevantes:
  - idInventarioEntrante (int) [clave lógica, NOT NULL]
  - idProducto (int) [FK lógica, NOT NULL]
  - idPresentacion (int) [FK lógica, NOT NULL]
  - NumLote (varchar(100)) [NOT NULL]
  - idUbicación (int) [FK lógica, NOT NULL]
  - sector (varchar(50)) [NOT NULL]
  - stock_total (int) [NOT NULL, calculada]
  - cantidad_reservada (decimal(59,4)) [NOT NULL, calculada]
  - cantidad_disponible (decimal(60,4)) [NOT NULL, calculada]
Se relaciona con:
  - inventarioentrante a través de idInventarioEntrante → idInventarioEntrante [1 a 1 derivada]
  - producto a través de idProducto → idProducto [muchos a 1]
  - presentacion a través de idPresentacion → idPresentacion [muchos a 1]
  - ubicacion a través de idUbicación → idUbicacion [muchos a 1]
  - inventariosaliente a través de idInventarioEntrante [agregación de salidas]
  - tbl_items_predespacho y tbl_cabecera_predespacho a través de idInventarioEntrante e idCabeceraPredespacho [agregación de reservas activas]

=== RELACIONES PRINCIPALES ===
producto ──< inventarioentrante : a través de inventarioentrante.idProducto → producto.idProducto [FK lógica]
presentacion ──< inventarioentrante : a través de inventarioentrante.idPresentacion → presentacion.idPresentacion [FK lógica]
ubicacion ──< inventarioentrante : a través de inventarioentrante.idUbicación → ubicacion.idUbicacion [FK lógica]
inventarioentrante ──< inventariosaliente : a través de inventariosaliente.idInventarioEntrante → inventarioentrante.idInventarioEntrante [FK lógica]
tbl_cliente ──< tbl_cabecera_predespacho : a través de tbl_cabecera_predespacho.idCliente → tbl_cliente.idCliente [FK física]
tbl_cabecera_predespacho ──< tbl_items_predespacho : a través de tbl_items_predespacho.idCabeceraPredespacho → tbl_cabecera_predespacho.idCabeceraPredespacho [FK física]
inventarioentrante ──< tbl_items_predespacho : a través de tbl_items_predespacho.idInventarioEntrante → inventarioentrante.idInventarioEntrante [FK lógica]
tbl_cabecera_predespacho >──< inventarioentrante : muchos a muchos mediante tbl_items_predespacho
roles ──< usuarios : a través de usuarios.id_rol → roles.id_rol [FK lógica]
roles ──< permisos_modulo : a través de permisos_modulo.id_rol → roles.id_rol [FK lógica]
usuarios ──< log_accesos : a través de log_accesos.id_usuario → usuarios.id_usuario [FK lógica]
tbl_cabecera_predespacho ──< inventariosaliente : vínculo lógico por inventariosaliente.NE → tbl_cabecera_predespacho.codigoInterno, validado además por idInventarioEntrante
inventarioentrante ── v_disponibilidad_lotes : relación derivada 1 a 1 por idInventarioEntrante

=== TABLAS PARA REPORTE DE IA ===
Para calcular STOCK ACTUAL necesito: inventarioentrante, inventariosaliente, tbl_items_predespacho, tbl_cabecera_predespacho y preferiblemente v_disponibilidad_lotes; producto, presentacion y ubicacion aportan dimensiones descriptivas. Fórmula usada por la vista: cantidad_disponible = CantidadEntrante - salidas acumuladas - reservas activas.
Para calcular MOVIMIENTOS necesito: inventarioentrante para entradas, inventariosaliente para salidas, producto para identificar artículos y tbl_cabecera_predespacho + tbl_items_predespacho para solicitudes y reservas previas al despacho.
Para calcular ALERTAS necesito: v_disponibilidad_lotes para agotamiento o bajo stock; inventarioentrante.fecha e inventariosaliente.fecha para inactividad, antigüedad y ritmo de consumo; tbl_cabecera_predespacho.fechaRetiro, statusGeneralPredespacho y tbl_items_predespacho.estatusItemPredespacho para retiros vencidos o pendientes. No existen fecha de vencimiento/caducidad, stock mínimo, proveedor, categoría ni precio, por lo que esas alertas no pueden calcularse con el esquema actual.
Para calcular KPIs necesito: inventarioentrante, inventariosaliente, v_disponibilidad_lotes, producto, presentacion, ubicacion, tbl_cabecera_predespacho, tbl_items_predespacho y tbl_cliente. Permiten calcular entradas, salidas, saldo disponible, reservas, rotación, cobertura estimada, cumplimiento de predespachos y actividad por cliente/producto/lote/sector.

Cobertura de datos clave:
  - Stock / cantidades: inventarioentrante.CantidadEntrante, inventariosaliente.cantidadSaliente, tbl_items_predespacho.cantidadSolicitada, tbl_items_predespacho.cantidadDespachada y cantidades calculadas de v_disponibilidad_lotes.
  - Movimientos: inventarioentrante, inventariosaliente, tbl_cabecera_predespacho y tbl_items_predespacho.
  - Productos o artículos: producto.
  - Lotes: inventarioentrante.NumLote; cada idInventarioEntrante identifica el registro físico del lote.
  - Proveedores: no existe tabla ni columna de proveedor.
  - Categorías: no existe tabla ni columna de categoría de producto.
  - Precios de costo y venta: no existen tablas ni columnas de precios.
  - Fechas de vencimiento: no existe columna de vencimiento o caducidad; inventarioentrante.fecha es fecha de entrada, no fecha de vencimiento.
