-- inventariofisico.contactosinternosemail definition

CREATE TABLE `contactosinternosemail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `cargo` varchar(50) DEFAULT NULL,
  `proceso` enum('Entrada','PreDespacho','Salida','En_Camino','COTIZACION') DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- inventariofisico.entrada_documentos definition

CREATE TABLE `entrada_documentos` (
  `idDocumento` int unsigned NOT NULL AUTO_INCREMENT,
  `idInventarioEntrante` int NOT NULL,
  `tipoDocumento` enum('ticket_romana','factura_proveedor','documento_seniat') NOT NULL,
  `nombreOriginal` varchar(255) NOT NULL,
  `nombreAlmacenado` varchar(80) NOT NULL,
  `rutaRelativa` varchar(255) NOT NULL,
  `mimeType` varchar(100) NOT NULL,
  `tamanoBytes` int unsigned NOT NULL,
  `idUsuario` int DEFAULT NULL,
  `fechaCarga` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idDocumento`),
  UNIQUE KEY `uq_entrada_tipo_documento` (`idInventarioEntrante`,`tipoDocumento`),
  KEY `idx_entrada_documentos_entrada` (`idInventarioEntrante`),
  KEY `idx_entrada_documentos_usuario` (`idUsuario`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- inventariofisico.inventarioentrante definition

CREATE TABLE `inventarioentrante` (
  `idInventarioEntrante` int NOT NULL AUTO_INCREMENT,
  `NumLote` varchar(100) NOT NULL,
  `idProducto` int NOT NULL,
  `idPresentacion` int NOT NULL,
  `idUbicación` int NOT NULL,
  `CantidadEntrante` decimal(14,3) DEFAULT NULL,
  `fecha` date NOT NULL,
  `sector` varchar(50) NOT NULL,
  `idTipoCompra` int DEFAULT NULL,
  `CardCode` varchar(15) DEFAULT NULL,
  `FabricanteCode` varchar(15) DEFAULT NULL,
  `PaisCode` varchar(3) DEFAULT NULL,
  `nro_factura` varchar(50) DEFAULT NULL,
  `fecha_factura` date DEFAULT NULL,
  `peso_romana` float DEFAULT NULL,
  `observaciones` varchar(256) DEFAULT NULL,
  PRIMARY KEY (`idInventarioEntrante`),
  KEY `fk_inv_tipo_compra` (`idTipoCompra`),
  KEY `fk_inv_proveedor` (`CardCode`),
  KEY `fk_inv_fabricante` (`FabricanteCode`),
  KEY `fk_inv_pais` (`PaisCode`)
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- inventariofisico.inventariosaliente definition

CREATE TABLE `inventariosaliente` (
  `idInventarioSaliente` int unsigned NOT NULL AUTO_INCREMENT,
  `idInventarioEntrante` int unsigned NOT NULL,
  `sector` varchar(30) DEFAULT NULL,
  `NE` varchar(80) NOT NULL,
  `cantidadSaliente` decimal(14,3) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idInventarioSaliente`),
  KEY `idx_inventariosaliente_entrante` (`idInventarioEntrante`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- inventariofisico.log_accesos definition

CREATE TABLE `log_accesos` (
  `id_log` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `accion` varchar(100) DEFAULT NULL,
  `modulo` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `fecha_hora` datetime DEFAULT CURRENT_TIMESTAMP,
  `exitoso` tinyint(1) DEFAULT '1',
  `ip` varchar(45) DEFAULT NULL,
  `resultado` varchar(20) DEFAULT NULL,
  `detalle` varchar(255) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL,
  PRIMARY KEY (`id_log`),
  KEY `id_usuario` (`id_usuario`)
) ENGINE=MyISAM AUTO_INCREMENT=250 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- inventariofisico.paises definition

CREATE TABLE `paises` (
  `Code` varchar(3) NOT NULL,
  `Name` varchar(150) NOT NULL,
  PRIMARY KEY (`Code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- inventariofisico.permisos_modulo definition

CREATE TABLE `permisos_modulo` (
  `id_permiso` int NOT NULL AUTO_INCREMENT,
  `id_rol` int NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `puede_ver` tinyint(1) DEFAULT '1',
  `puede_editar` tinyint(1) DEFAULT '0',
  `puede_borrar` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id_permiso`),
  UNIQUE KEY `uq_rol_modulo` (`id_rol`,`modulo`),
  UNIQUE KEY `uq_permiso_modulo` (`id_rol`,`modulo`)
) ENGINE=MyISAM AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- inventariofisico.presentacion definition

CREATE TABLE `presentacion` (
  `idPresentacion` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  PRIMARY KEY (`idPresentacion`)
) ENGINE=MyISAM AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- inventariofisico.producto definition

CREATE TABLE `producto` (
  `idProducto` int NOT NULL AUTO_INCREMENT,
  `codigoInterno` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  PRIMARY KEY (`idProducto`)
) ENGINE=MyISAM AUTO_INCREMENT=166 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- inventariofisico.proveedores definition

CREATE TABLE `proveedores` (
  `CardCode` varchar(15) NOT NULL,
  `CardName` varchar(100) DEFAULT NULL,
  `GroupCode` int DEFAULT NULL,
  `MailAddres` varchar(254) DEFAULT NULL,
  `MailZipCod` varchar(20) DEFAULT NULL,
  `CntctPrsn` varchar(90) DEFAULT NULL,
  `Notes` text,
  `Balance` decimal(19,6) DEFAULT NULL,
  `LicTradNum` varchar(32) DEFAULT NULL,
  `Country` varchar(100) DEFAULT NULL,
  `MailCity` varchar(100) DEFAULT NULL,
  `MailCounty` varchar(100) DEFAULT NULL,
  `MailCountr` varchar(100) DEFAULT NULL,
  `E_Mail` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`CardCode`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- inventariofisico.roles definition

CREATE TABLE `roles` (
  `id_rol` int NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `nombre` varchar(60) DEFAULT NULL,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre_rol` (`nombre_rol`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- inventariofisico.silos definition

CREATE TABLE `silos` (
  `idSilo` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacidad` decimal(14,3) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fechaCreacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fechaActualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idSilo`),
  UNIQUE KEY `uq_silos_codigo` (`codigo`),
  CONSTRAINT `chk_silos_capacidad` CHECK ((`capacidad` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- inventariofisico.stock_al definition

CREATE TABLE `stock_al` (
  `codAriculo` varchar(50) DEFAULT NULL,
  `descripcionArticulo` varchar(50) DEFAULT NULL,
  `lote` varchar(50) DEFAULT NULL,
  `cantidadEntrante` varchar(50) DEFAULT NULL,
  `Sector` int DEFAULT NULL,
  `PRESENTACION` varchar(50) DEFAULT NULL,
  `PESO` int DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- inventariofisico.tbl_cliente definition

CREATE TABLE `tbl_cliente` (
  `idCliente` int unsigned NOT NULL AUTO_INCREMENT,
  `rif` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` text COLLATE utf8mb4_unicode_ci,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ej: nacional, exportacion, especial',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fechaCreacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fechaActualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idCliente`),
  UNIQUE KEY `uq_cliente_rif` (`rif`),
  KEY `idx_cliente_nombre` (`nombre`),
  KEY `idx_cliente_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=358 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Clientes del módulo Predespacho';


-- inventariofisico.tbl_clientes_cotizacion definition

CREATE TABLE `tbl_clientes_cotizacion` (
  `CardCode` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `CardName` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CardType` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CmpPrivate` char(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Address` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ZipCode` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MailAddres` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MailZipCod` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Phone1` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Phone2` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `LicTradNum` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Free_Text` longtext COLLATE utf8mb4_unicode_ci,
  `City` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `County` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Country` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MailCity` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MailCounty` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MailCountr` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `E_Mail` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `CreateDate` datetime DEFAULT NULL,
  `State1` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `State2` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Block` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MailBlock` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`CardCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- inventariofisico.tipo_compra definition

CREATE TABLE `tipo_compra` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descripcion` varchar(50) NOT NULL,
  `Detalle` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- inventariofisico.ubicacion definition

CREATE TABLE `ubicacion` (
  `idUbicacion` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) NOT NULL,
  PRIMARY KEY (`idUbicacion`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- inventariofisico.usuarios definition

CREATE TABLE `usuarios` (
  `id_usuario` int NOT NULL AUTO_INCREMENT,
  `nombre_completo` varchar(150) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `id_rol` int NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `ultimo_acceso` datetime DEFAULT NULL,
  `creado_por` int DEFAULT NULL,
  `intentos_fallidos` int unsigned NOT NULL DEFAULT '0',
  `bloqueado_hasta` datetime DEFAULT NULL,
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `username` (`username`),
  KEY `id_rol` (`id_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- inventariofisico.ajustes_lote definition

CREATE TABLE `ajustes_lote` (
  `idAjuste` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idInventarioEntrante` int NOT NULL,
  `tipo` enum('positivo','negativo') COLLATE utf8mb4_unicode_ci NOT NULL,
  `monto` decimal(14,3) NOT NULL,
  `observacion` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `idUsuario` int NOT NULL,
  `responsable` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL,
  `idSilo` int unsigned DEFAULT NULL,
  `fechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tokenRegistro` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`idAjuste`),
  UNIQUE KEY `uq_ajuste_token` (`tokenRegistro`),
  KEY `idx_ajuste_lote_fecha` (`idInventarioEntrante`,`fechaCreacion`,`idAjuste`),
  KEY `fk_ajuste_usuario` (`idUsuario`),
  KEY `fk_ajuste_silo` (`idSilo`),
  CONSTRAINT `fk_ajuste_lote` FOREIGN KEY (`idInventarioEntrante`) REFERENCES `inventarioentrante` (`idInventarioEntrante`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ajuste_silo` FOREIGN KEY (`idSilo`) REFERENCES `silos` (`idSilo`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ajuste_usuario` FOREIGN KEY (`idUsuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE RESTRICT,
  CONSTRAINT `chk_ajuste_monto` CHECK ((`monto` > 0)),
  CONSTRAINT `chk_ajuste_observacion` CHECK ((char_length(trim(`observacion`)) > 0))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- inventariofisico.ajustes_lote_silos definition

CREATE TABLE `ajustes_lote_silos` (
  `idAjuste` bigint unsigned NOT NULL,
  `idSilo` int unsigned NOT NULL,
  `monto` decimal(14,3) NOT NULL,
  PRIMARY KEY (`idAjuste`,`idSilo`),
  KEY `fk_ajuste_detalle_silo` (`idSilo`),
  CONSTRAINT `fk_ajuste_detalle` FOREIGN KEY (`idAjuste`) REFERENCES `ajustes_lote` (`idAjuste`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ajuste_detalle_silo` FOREIGN KEY (`idSilo`) REFERENCES `silos` (`idSilo`) ON DELETE RESTRICT,
  CONSTRAINT `chk_ajuste_silo_monto` CHECK ((`monto` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- inventariofisico.silo_asignaciones definition

CREATE TABLE `silo_asignaciones` (
  `idAsignacion` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idSilo` int unsigned NOT NULL,
  `idInventarioEntrante` int NOT NULL,
  `cantidadAsignada` decimal(14,3) NOT NULL,
  `fechaAsignacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idAsignacion`),
  UNIQUE KEY `uq_silo_entrada` (`idSilo`,`idInventarioEntrante`),
  KEY `idx_silo_asignacion_entrada` (`idInventarioEntrante`),
  CONSTRAINT `fk_silo_asignacion_entrada` FOREIGN KEY (`idInventarioEntrante`) REFERENCES `inventarioentrante` (`idInventarioEntrante`) ON DELETE CASCADE,
  CONSTRAINT `fk_silo_asignacion_silo` FOREIGN KEY (`idSilo`) REFERENCES `silos` (`idSilo`),
  CONSTRAINT `chk_silo_asignacion_cantidad` CHECK ((`cantidadAsignada` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- inventariofisico.silo_salidas definition

CREATE TABLE `silo_salidas` (
  `idSiloSalida` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idAsignacion` bigint unsigned NOT NULL,
  `idInventarioSaliente` int unsigned NOT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  `fechaMovimiento` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idSiloSalida`),
  UNIQUE KEY `uq_silo_salida_asignacion` (`idInventarioSaliente`,`idAsignacion`),
  KEY `idx_silo_salida_asignacion` (`idAsignacion`),
  CONSTRAINT `fk_silo_salida_asignacion` FOREIGN KEY (`idAsignacion`) REFERENCES `silo_asignaciones` (`idAsignacion`) ON DELETE CASCADE,
  CONSTRAINT `fk_silo_salida_inventario` FOREIGN KEY (`idInventarioSaliente`) REFERENCES `inventariosaliente` (`idInventarioSaliente`) ON DELETE CASCADE,
  CONSTRAINT `chk_silo_salida_cantidad` CHECK ((`cantidad` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- inventariofisico.tbl_cabecera_predespacho definition

CREATE TABLE `tbl_cabecera_predespacho` (
  `idCabeceraPredespacho` int unsigned NOT NULL AUTO_INCREMENT,
  `idCliente` int unsigned NOT NULL,
  `fechaRetiro` date NOT NULL,
  `codigoInterno` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigoNotaEntregaSAP` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `userCreador` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statusGeneralPredespacho` enum('abierto','pendiente','embarcado','cerrado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'abierto',
  `tokenCierre` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `idUsuarioCierre` int DEFAULT NULL,
  `usuarioCierre` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fechaCierre` datetime DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `fechaCreacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fechaActualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idCabeceraPredespacho`),
  UNIQUE KEY `uq_codigo_interno` (`codigoInterno`),
  UNIQUE KEY `uq_predespacho_token_cierre` (`tokenCierre`),
  KEY `idx_cab_cliente` (`idCliente`),
  KEY `idx_cab_status` (`statusGeneralPredespacho`),
  KEY `idx_cab_fecha_retiro` (`fechaRetiro`),
  KEY `idx_cab_nota_sap` (`codigoNotaEntregaSAP`),
  KEY `idx_predespacho_usuario_cierre` (`idUsuarioCierre`),
  KEY `idx_predespacho_fecha_cierre` (`fechaCierre`),
  CONSTRAINT `fk_cab_cliente` FOREIGN KEY (`idCliente`) REFERENCES `tbl_cliente` (`idCliente`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- inventariofisico.tbl_cotizacion_cabecera definition

CREATE TABLE `tbl_cotizacion_cabecera` (
  `idCotizacion` int unsigned NOT NULL AUTO_INCREMENT,
  `idCliente` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fechaEmision` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `diasVigencia` int NOT NULL DEFAULT '1',
  `condicionPago` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'CONTADO BSS, CONTADO USD, TRANSFERENCIA BSS, TRANSFERENCIA USD, ZELLE, BINANCE',
  `observacion` text COLLATE utf8mb4_unicode_ci,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=Activa, 0=Eliminada (Soft Delete)',
  `fechaCreacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fechaActualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idCotizacion`),
  KEY `fk_cotizacion_cliente_cotizacion` (`idCliente`),
  CONSTRAINT `fk_cotizacion_cliente_cotizacion` FOREIGN KEY (`idCliente`) REFERENCES `tbl_clientes_cotizacion` (`CardCode`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- inventariofisico.tbl_cotizacion_detalle definition

CREATE TABLE `tbl_cotizacion_detalle` (
  `idDetalle` int unsigned NOT NULL AUTO_INCREMENT,
  `idCotizacion` int unsigned NOT NULL,
  `idProducto` int NOT NULL,
  `idPresentacion` int NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `precioUnitario` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  PRIMARY KEY (`idDetalle`),
  KEY `idCotizacion` (`idCotizacion`),
  CONSTRAINT `tbl_cotizacion_detalle_ibfk_1` FOREIGN KEY (`idCotizacion`) REFERENCES `tbl_cotizacion_cabecera` (`idCotizacion`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- inventariofisico.tbl_items_predespacho definition

CREATE TABLE `tbl_items_predespacho` (
  `idItem` int unsigned NOT NULL AUTO_INCREMENT,
  `idCabeceraPredespacho` int unsigned NOT NULL,
  `idInventarioEntrante` int unsigned NOT NULL,
  `cantidadSolicitada` decimal(14,4) NOT NULL,
  `cantidadDespachada` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estatusItemPredespacho` enum('abierto','pendiente','cerrado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'abierto',
  `fechaCreacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fechaActualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idItem`),
  KEY `idx_item_cabecera` (`idCabeceraPredespacho`),
  KEY `idx_item_inventario` (`idInventarioEntrante`),
  KEY `idx_item_estatus` (`estatusItemPredespacho`),
  CONSTRAINT `fk_item_cabecera` FOREIGN KEY (`idCabeceraPredespacho`) REFERENCES `tbl_cabecera_predespacho` (`idCabeceraPredespacho`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;