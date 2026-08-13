-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 28-07-2026 a las 22:12:10
-- Versión del servidor: 9.1.0
-- Versión de PHP: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `inventariofisico`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `invenatriosaliente`
--

DROP TABLE IF EXISTS `invenatriosaliente`;
CREATE TABLE IF NOT EXISTS `invenatriosaliente` (
  `idInventarioSaliente` int NOT NULL AUTO_INCREMENT,
  `idInventarioEntrante` int NOT NULL,
  `NE` varchar(50) NOT NULL,
  `cantidadSaliente` int NOT NULL,
  `fecha` date NOT NULL,
  `observacion` text NOT NULL,
  `sector` varchar(256) NOT NULL,
  PRIMARY KEY (`idInventarioSaliente`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventarioentrante`
--

DROP TABLE IF EXISTS `inventarioentrante`;
CREATE TABLE IF NOT EXISTS `inventarioentrante` (
  `idInventarioEntrante` int NOT NULL AUTO_INCREMENT,
  `NumLote` varchar(100) NOT NULL,
  `idProducto` int NOT NULL,
  `idPresentacion` int NOT NULL,
  `idUbicación` int NOT NULL,
  `CantidadEntrante` int NOT NULL,
  `fecha` date NOT NULL,
  `sector` varchar(50) NOT NULL,
  PRIMARY KEY (`idInventarioEntrante`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `inventarioentrante`
--

INSERT INTO `inventarioentrante` (`idInventarioEntrante`, `NumLote`, `idProducto`, `idPresentacion`, `idUbicación`, `CantidadEntrante`, `fecha`, `sector`) VALUES
(7, 'ejemplo00000', 140, 23, 1, 15000, '2026-07-27', 'Sector1'),
(6, '2222', 109, 24, 1, 3333333, '2026-07-22', 'Sector1'),
(8, '98', 140, 3, 1, 1700, '2026-07-27', 'Sector1'),
(9, 'xx54', 123, 3, 1, 29980, '2026-07-27', 'Sector2'),
(10, '567890', 128, 26, 1, 11750, '2026-07-27', 'Sector1');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventariosaliente`
--

DROP TABLE IF EXISTS `inventariosaliente`;
CREATE TABLE IF NOT EXISTS `inventariosaliente` (
  `idInventarioSaliente` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `idInventarioEntrante` int UNSIGNED NOT NULL,
  `sector` varchar(30) DEFAULT NULL,
  `NE` varchar(80) NOT NULL,
  `cantidadSaliente` decimal(12,2) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idInventarioSaliente`),
  KEY `idx_inventariosaliente_entrante` (`idInventarioEntrante`)
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `inventariosaliente`
--

INSERT INTO `inventariosaliente` (`idInventarioSaliente`, `idInventarioEntrante`, `sector`, `NE`, `cantidadSaliente`, `fecha`) VALUES
(14, 9, 'Sector2', 'PRE-2026-00002', 9980.00, '2026-07-28 17:35:13'),
(13, 8, 'Sector1', 'PRE-2026-00002', 200.00, '2026-07-28 17:34:20'),
(12, 8, 'Sector1', 'PRE-2026-00002', 300.00, '2026-07-28 17:34:04'),
(11, 8, 'Sector1', 'PRE-2026-00001', 700.00, '2026-07-28 17:10:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `log_accesos`
--

DROP TABLE IF EXISTS `log_accesos`;
CREATE TABLE IF NOT EXISTS `log_accesos` (
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
) ENGINE=MyISAM AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `log_accesos`
--

INSERT INTO `log_accesos` (`id_log`, `id_usuario`, `username`, `accion`, `modulo`, `ip_address`, `fecha_hora`, `exitoso`, `ip`, `resultado`, `detalle`, `fecha`) VALUES
(1, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:52:05', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-22 00:52:05'),
(2, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:52:14', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-22 00:52:14'),
(3, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:52:17', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-22 00:52:17'),
(4, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:52:42', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-22 00:52:42'),
(5, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:52:47', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-22 00:52:47'),
(6, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:52:53', 1, '::1', 'fallo', 'Usuario bloqueado temporalmente', '2026-07-22 00:52:53'),
(7, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:53:16', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-22 00:53:16'),
(8, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:53:21', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-22 00:53:21'),
(9, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:54:26', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 00:54:26'),
(10, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:54:43', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-22 00:54:43'),
(11, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:54:44', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-22 00:54:44'),
(12, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:54:44', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-22 00:54:44'),
(13, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:54:45', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-22 00:54:45'),
(14, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:54:46', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-22 00:54:46'),
(15, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:54:50', 1, '::1', 'fallo', 'Usuario bloqueado temporalmente', '2026-07-22 00:54:50'),
(16, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:55:14', 1, '::1', 'fallo', 'Usuario bloqueado temporalmente', '2026-07-22 00:55:14'),
(17, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:56:21', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 00:56:21'),
(18, 1, 'admin', 'logout', 'login', NULL, '2026-07-22 00:56:24', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-22 00:56:24'),
(19, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:56:56', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 00:56:56'),
(20, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:57:27', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-22 00:57:27'),
(21, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:57:32', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-22 00:57:32'),
(22, 1, 'admin', 'login', 'login', NULL, '2026-07-22 00:57:50', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 00:57:50'),
(23, 1, 'admin', 'logout', 'login', NULL, '2026-07-22 00:59:38', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-22 00:59:38'),
(24, 1, 'admin', 'login', 'login', NULL, '2026-07-22 01:00:38', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 01:00:38'),
(25, 1, 'admin', 'login', 'login', NULL, '2026-07-22 01:00:54', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 01:00:54'),
(26, 1, 'admin', 'login', 'login', NULL, '2026-07-22 01:03:39', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 01:03:39'),
(27, 1, 'admin', 'login', 'login', NULL, '2026-07-22 01:03:55', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 01:03:55'),
(28, 1, 'admin', 'login', 'login', NULL, '2026-07-22 01:06:23', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 01:06:23'),
(29, 1, 'admin', 'logout', 'login', NULL, '2026-07-22 01:10:30', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-22 01:10:30'),
(30, 1, 'admin', 'login', 'login', NULL, '2026-07-22 01:10:38', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-22 01:10:38'),
(31, 1, 'admin', 'login', 'login', NULL, '2026-07-22 01:10:43', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 01:10:43'),
(32, 1, 'admin', 'login', 'login', NULL, '2026-07-22 01:13:36', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 01:13:36'),
(33, 1, 'admin', 'crear_usuario', 'administracion', NULL, '2026-07-22 01:14:34', 1, '::1', 'exitoso', 'jsantana', '2026-07-22 01:14:34'),
(34, 1, 'admin', 'logout', 'login', NULL, '2026-07-22 01:14:39', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-22 01:14:39'),
(35, 2, 'jsantana', 'login', 'login', NULL, '2026-07-22 01:14:47', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 01:14:47'),
(36, 2, 'jsantana', 'editar_usuario', 'administracion', NULL, '2026-07-22 01:14:58', 1, '::1', 'exitoso', 'admin', '2026-07-22 01:14:58'),
(37, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-22 01:15:02', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-22 01:15:02'),
(38, 1, 'admin', 'login', 'login', NULL, '2026-07-22 01:15:05', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-22 01:15:05'),
(39, 2, 'jsantana', 'login', 'login', NULL, '2026-07-22 01:15:13', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 01:15:13'),
(40, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-22 01:19:27', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-22 01:19:27'),
(41, 2, 'jsantana', 'login', 'login', NULL, '2026-07-22 01:19:37', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 01:19:37'),
(42, 2, 'jsantana', 'login', 'login', NULL, '2026-07-22 09:18:55', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 09:18:55'),
(43, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-22 09:34:30', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-22 09:34:30'),
(44, 1, 'admin', 'login', 'login', NULL, '2026-07-22 09:37:01', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-22 09:37:01'),
(45, 2, 'jsantana', 'login', 'login', NULL, '2026-07-22 09:37:10', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 09:37:10'),
(46, 2, 'jsantana', 'toggle_usuario', 'administracion', NULL, '2026-07-22 09:37:25', 1, '::1', 'exitoso', 'admin', '2026-07-22 09:37:25'),
(47, 2, 'jsantana', 'toggle_usuario', 'administracion', NULL, '2026-07-22 09:37:35', 1, '::1', 'exitoso', 'admin', '2026-07-22 09:37:35'),
(48, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-22 09:38:10', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-22 09:38:10'),
(49, 2, 'jsantana', 'login', 'login', NULL, '2026-07-22 09:40:10', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 09:40:10'),
(50, 2, 'jsantana', 'toggle_usuario', 'administracion', NULL, '2026-07-22 09:41:17', 1, '::1', 'exitoso', 'admin', '2026-07-22 09:41:17'),
(51, 2, 'jsantana', 'cambiar_clave', 'administracion', NULL, '2026-07-22 09:43:29', 1, '::1', 'exitoso', 'Usuario #1', '2026-07-22 09:43:29'),
(52, 2, 'jsantana', 'toggle_usuario', 'administracion', NULL, '2026-07-22 09:43:35', 1, '::1', 'exitoso', 'admin', '2026-07-22 09:43:35'),
(53, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-22 09:43:41', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-22 09:43:41'),
(54, 1, 'admin', 'login', 'login', NULL, '2026-07-22 09:43:45', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 09:43:45'),
(55, 1, 'admin', 'logout', 'login', NULL, '2026-07-22 09:44:28', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-22 09:44:28'),
(56, 1, 'admin', 'login', 'login', NULL, '2026-07-22 09:45:18', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 09:45:18'),
(57, 1, 'admin', 'logout', 'login', NULL, '2026-07-22 09:46:36', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-22 09:46:36'),
(58, 1, 'admin', 'login', 'login', NULL, '2026-07-22 09:47:22', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-22 09:47:22'),
(59, 2, 'jsantana', 'login', 'login', NULL, '2026-07-27 10:38:39', 1, '::1', 'fallo', 'Credenciales invalidas', '2026-07-27 10:38:39'),
(60, 2, 'jsantana', 'login', 'login', NULL, '2026-07-27 10:38:48', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-27 10:38:48'),
(61, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-27 10:56:10', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-27 10:56:10'),
(62, 2, 'jsantana', 'login', 'login', NULL, '2026-07-27 10:56:36', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-27 10:56:36'),
(63, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-27 10:57:24', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-27 10:57:24'),
(64, 2, 'jsantana', 'login', 'login', NULL, '2026-07-27 10:59:09', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-27 10:59:09'),
(65, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-27 11:14:02', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-27 11:14:02'),
(66, 2, 'jsantana', 'login', 'login', NULL, '2026-07-27 11:19:16', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-27 11:19:16'),
(67, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-27 11:27:21', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-27 11:27:21'),
(68, 2, 'jsantana', 'login', 'login', NULL, '2026-07-27 11:27:59', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-27 11:27:59'),
(69, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-27 11:29:10', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-27 11:29:10'),
(70, 2, 'jsantana', 'login', 'login', NULL, '2026-07-27 11:29:31', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-27 11:29:31'),
(71, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-27 11:29:35', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-27 11:29:35'),
(72, 2, 'jsantana', 'login', 'login', NULL, '2026-07-27 11:30:43', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-27 11:30:43'),
(73, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-27 12:14:08', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-27 12:14:08'),
(74, 2, 'jsantana', 'login', 'login', NULL, '2026-07-27 12:15:01', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-27 12:15:01'),
(75, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-27 12:40:28', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-27 12:40:28'),
(76, 2, 'jsantana', 'login', 'login', NULL, '2026-07-27 12:40:30', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-27 12:40:30'),
(77, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-27 13:00:58', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-27 13:00:58'),
(78, 2, 'jsantana', 'login', 'login', NULL, '2026-07-27 13:01:41', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-27 13:01:41'),
(79, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-27 13:07:45', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-27 13:07:45'),
(80, 2, 'jsantana', 'login', 'login', NULL, '2026-07-27 13:46:15', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-27 13:46:15'),
(81, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-27 16:11:27', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-27 16:11:27'),
(82, 2, 'jsantana', 'login', 'login', NULL, '2026-07-27 16:11:43', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-27 16:11:43'),
(83, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-27 16:58:36', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-27 16:58:36'),
(84, 2, 'jsantana', 'login', 'login', NULL, '2026-07-27 16:58:38', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-27 16:58:38'),
(85, 2, 'jsantana', 'login', 'login', NULL, '2026-07-28 16:20:37', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-28 16:20:37'),
(86, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-28 16:41:01', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-28 16:41:01'),
(87, 2, 'jsantana', 'login', 'login', NULL, '2026-07-28 16:41:03', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-28 16:41:03'),
(88, 2, 'jsantana', 'login', 'login', NULL, '2026-07-28 16:43:39', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-28 16:43:39'),
(89, 2, 'jsantana', 'logout', 'login', NULL, '2026-07-28 17:35:23', 1, '::1', 'exitoso', 'Cierre de sesion', '2026-07-28 17:35:23'),
(90, 2, 'jsantana', 'login', 'login', NULL, '2026-07-28 17:35:25', 1, '::1', 'exitoso', 'Ingreso correcto', '2026-07-28 17:35:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos_modulo`
--

DROP TABLE IF EXISTS `permisos_modulo`;
CREATE TABLE IF NOT EXISTS `permisos_modulo` (
  `id_permiso` int NOT NULL AUTO_INCREMENT,
  `id_rol` int NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `puede_ver` tinyint(1) DEFAULT '1',
  `puede_editar` tinyint(1) DEFAULT '0',
  `puede_borrar` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id_permiso`),
  UNIQUE KEY `uq_rol_modulo` (`id_rol`,`modulo`),
  UNIQUE KEY `uq_permiso_modulo` (`id_rol`,`modulo`)
) ENGINE=MyISAM AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `permisos_modulo`
--

INSERT INTO `permisos_modulo` (`id_permiso`, `id_rol`, `modulo`, `puede_ver`, `puede_editar`, `puede_borrar`) VALUES
(1, 1, 'entrada', 1, 1, 1),
(2, 1, 'salida', 1, 1, 1),
(3, 1, 'corregir_entradas', 1, 1, 1),
(4, 1, 'corregir_salidas', 1, 1, 1),
(5, 1, 'reporte_lote', 1, 1, 1),
(6, 1, 'inteligencia', 1, 1, 1),
(7, 1, 'administracion', 1, 1, 1),
(8, 2, 'entrada', 1, 1, 0),
(9, 2, 'salida', 1, 1, 0),
(10, 2, 'corregir_entradas', 1, 1, 0),
(11, 2, 'corregir_salidas', 1, 1, 0),
(12, 2, 'reporte_lote', 1, 0, 0),
(13, 2, 'inteligencia', 1, 0, 0),
(14, 2, 'administracion', 0, 0, 0),
(15, 3, 'entrada', 0, 0, 0),
(16, 3, 'salida', 1, 1, 0),
(17, 3, 'corregir_entradas', 0, 0, 0),
(18, 3, 'corregir_salidas', 0, 0, 0),
(19, 3, 'reporte_lote', 0, 0, 0),
(20, 3, 'inteligencia', 0, 0, 0),
(21, 3, 'administracion', 0, 0, 0),
(22, 4, 'entrada', 0, 0, 0),
(23, 4, 'salida', 0, 0, 0),
(24, 4, 'corregir_entradas', 0, 0, 0),
(25, 4, 'corregir_salidas', 0, 0, 0),
(26, 4, 'reporte_lote', 1, 0, 0),
(27, 4, 'inteligencia', 1, 0, 0),
(28, 4, 'administracion', 0, 0, 0),
(29, 1, 'predespacho', 1, 1, 1),
(30, 2, 'predespacho', 1, 1, 0),
(31, 3, 'predespacho', 0, 0, 0),
(32, 4, 'predespacho', 0, 0, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `presentacion`
--

DROP TABLE IF EXISTS `presentacion`;
CREATE TABLE IF NOT EXISTS `presentacion` (
  `idPresentacion` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  PRIMARY KEY (`idPresentacion`)
) ENGINE=MyISAM AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `presentacion`
--

INSERT INTO `presentacion` (`idPresentacion`, `nombre`) VALUES
(3, 'GRANEL (KG)'),
(4, 'TAMBOR (200 KG)'),
(5, 'SACOS (25 KG)'),
(6, 'TAMBOR (190 KG)'),
(7, 'TAMBOR (215 KG)'),
(8, 'TAMBOR (270 KG)'),
(9, 'TAMBOR (180 KG)'),
(10, 'CARBOYA (50 KG)'),
(11, 'TAMBOR (250 KG)'),
(12, 'TAMBOR (170 KG)'),
(13, 'TAMBOR (210 KG)'),
(14, 'TAMBOR (230 KG)'),
(15, 'BULTO (18 KG)'),
(16, 'TAMBOR (220 KG)'),
(17, 'SACOS (20 KG)'),
(18, 'TOTEM (1.100 KG)'),
(19, 'TAMBOR (275 KG)'),
(20, 'PAILA (15 KG)'),
(21, 'TAMBOR (225 KG)'),
(22, 'SACOS (50 KG)'),
(23, 'SACOS (40 KG)'),
(24, 'TAMBOR (186 KG)'),
(25, 'TOTEM (1.050 KG)'),
(26, 'TAMBOR (205 KG)'),
(27, 'TAMBOR (330 KG)'),
(28, 'TAMBOR (160 KG)'),
(29, 'CARBOYA (30 KG)');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto`
--

DROP TABLE IF EXISTS `producto`;
CREATE TABLE IF NOT EXISTS `producto` (
  `idProducto` int NOT NULL AUTO_INCREMENT,
  `codigoInterno` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  PRIMARY KEY (`idProducto`)
) ENGINE=MyISAM AUTO_INCREMENT=165 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `producto`
--

INSERT INTO `producto` (`idProducto`, `codigoInterno`, `nombre`) VALUES
(125, 'IND00103', '(IND00103) - KIMICELL KEC100000B'),
(124, 'IND00123', '(IND00123) - VARSOL'),
(123, 'IND00046', '(IND00046) - XILENO'),
(122, 'IND00042', '(IND00042) - NIPACIDEX 15'),
(121, 'IND00055', '(IND00055) - XILENO'),
(120, 'IND00120', '(IND00120) - MONOETILENGLICOL (MEG)'),
(119, 'HIG00054', '(HIG00054) - ACIDO ESTEARICO'),
(118, 'HIG00071', '(HIG00071) - SAL INDUSTRIAL'),
(117, 'HIG00087', '(HIG00087) - CLORURO DE BENZALCONIO AL 80%'),
(116, 'IND00124', '(IND00124) - KEROSENE'),
(115, 'HIG00057', '(HIG00057) - CERA EN ESCAMA'),
(114, 'IND00078', '(IND00078) - GLUTARALDEHYDE 50%'),
(113, 'IND00085', '(IND00085) - ACIDO CITRICO'),
(112, 'HIG00091', '(HIG00091) - DETERGENTE EN POLVO 3B'),
(111, 'IND00019', '(IND00019) - SOLVENTE-190'),
(110, 'HIG00045', '(HIG00045) - SODA CAUSTICA EN ESCAMA ROKITA'),
(109, 'HIG00077', '(HIG00077) - GLICERINA USP'),
(108, 'HIG00056', '(HIG00056) - GOMA XANTAL (GRADO ALIMENTICIO)'),
(107, 'IND00040', '(IND00040) - METANOL'),
(106, 'IND00110', '(IND00110) - KIMICELL KEC100000BS'),
(105, 'HIG00063', '(HIG00063) - ALCOHOL ISOPROPILICO'),
(104, 'HIG00065', '(HIG00065) - SODA CAUSTICA LIQUIDA'),
(103, 'HIG00055', '(HIG00055) - ALCOHOL CETILICO'),
(102, 'IND00061', '(IND00061) - TRIETILENGLICOL (TEG)'),
(101, 'IND00072', '(IND00072) - DIETANOLAMINA (DEA)'),
(100, 'IND00073', '(IND00073) - SILICONA 12500'),
(99, 'IND00047', '(IND00047) - NONIL FENOL 10 MOL'),
(98, 'HIG00042', '(HIG00042) - GENAPOL'),
(97, 'HIG00090', '(HIG00090) - FORMOL AL 37%'),
(96, 'HIG00072', '(HIG00072) - CMC - CARBOXYMETHYL CELLULOSE'),
(95, 'HIG00078', '(HIG00078) - CLORO GRANULADO AL 90%'),
(94, 'IND00063', '(IND00063) - BUTIL GLICOL'),
(93, 'IND00037', '(IND00037) - BUTIL ACETATO'),
(92, 'IND00084', '(IND00084) - CLORURO DE METILENO'),
(91, 'IND00068', '(IND00068) - PROPILENGLICOL / MPG USP'),
(90, 'IND00056', '(IND00056) - BUTIL GLICOL'),
(89, 'IND00086', '(IND00086) - NONIL FENOL 10 MOL'),
(88, 'IND00099', '(IND00099) - BENZOATO DE SODIO'),
(87, 'HIG00044', '(HIG00044) - SODA CAUSTICA EN ESCAMA'),
(86, 'IND00039', '(IND00039) - AROMATICO PESADO'),
(85, 'HIG00094', '(HIG00094) - BETAINA AL 30%'),
(84, 'IND00117', '(IND00117) - SOLVESSO 100'),
(126, 'IND00062', '(IND00062) - SULFATO DE COBRE'),
(127, 'IND00125', '(IND00125) - PARAFINA SEMIREFINADA WAX 58-60'),
(128, 'IND00121', '(IND00121) - CREOSOTA'),
(129, 'IND00115', '(IND00115) - ACIDO ACETICO GRADO ALIMENTICIO'),
(130, 'HIG00088', '(HIG00088) - DETERGENTE EN POLVO OXY'),
(131, 'IND00095', '(IND00095) - SOLVENTE-100'),
(132, 'IND00035', '(IND00035) - GLICERINA USP'),
(133, 'IND00066', '(IND00066) - N PROPIL ACETATO'),
(134, 'IND00044', '(IND00044) - TRIETANOLAMINA (TEA)'),
(135, 'HIG00025', '(HIG00025) - PREPAGEN'),
(136, 'HIG00058', '(HIG00058) - FORMOL AL 37%'),
(137, 'HIG00098', '(HIG00098) - GENAPOL (TEXAPON)'),
(138, 'HIG00053', '(HIG00053) - METASILICATO DE SODIO'),
(139, 'IND00021', '(IND00021) - DIOXIDO DE TITANIO'),
(140, 'HIG00038', '(HIG00038) - ACIDO SULFONICO'),
(141, 'IND00111', '(IND00111) - PARAFINA SEMIREFINADA WAX 58-60'),
(142, 'IND00108', '(IND00108) - PLURASOLV'),
(143, 'IND00081', '(IND00081) - SULFATO DE ALUMINIO'),
(144, 'IND00109', '(IND00109) - KIMICELL KEM75000S'),
(145, 'IND00064', '(IND00064) - EDTA'),
(146, 'IND00112', '(IND00112) - BUTIL GLICOL'),
(147, 'IND00053', '(IND00053) - MONOETANOLAMINA (MEA)'),
(148, 'HIG00089', '(HIG00089) - ACIDO SULFONICO 96% LABSA'),
(149, 'HIG00093', '(HIG00093) - GENAPOL PERLADO'),
(150, 'IND00049', '(IND00049) - NONIL FENOL 6 MOL'),
(151, 'IND00083', '(IND00083) - NIPACIDEX'),
(152, 'HIG00068', '(HIG00068) - GENAPOL'),
(153, 'IND00126', '(IND00126) - NONIL FENOL 6 MOL'),
(154, 'HIG00047', '(HIG00047) - TRIPOLIFOSFATO DE SODIO'),
(155, 'HIG00086', '(HIG00086) - PEROXIDO DE HIDROGENO'),
(156, 'IND00069', '(IND00069) - NONIL FENOL 10 MOL'),
(157, 'HIG00040', '(HIG00040) - ACIDO SULFONICO'),
(158, 'IND00036', '(IND00036) - SODA CAUSTICA LIQUIDA'),
(159, 'IND00080', '(IND00080) - ACIDO FOSFORICO 85%'),
(160, 'HIG00050', '(HIG00050) - ALCOHOL ISOPROPILICO'),
(161, 'HIG00076', '(HIG00076) - PEROXIDO DE HIDROGENO'),
(162, 'IND00105', '(IND00105) - ACEITE BLANCO'),
(163, 'IND00051', '(IND00051) - DIETILENGLICOL (DEG)'),
(164, 'IND00114', '(IND00114) - ACIDO ACETICO GRADO ALIMENTICIO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id_rol` int NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `nombre` varchar(60) DEFAULT NULL,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre_rol` (`nombre_rol`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre_rol`, `descripcion`, `activo`, `fecha_creacion`, `nombre`) VALUES
(1, 'Administrador', 'Acceso total al sistema incluyendo gestión de usuarios', 1, '2026-07-22 00:44:08', 'Administrador'),
(2, 'Supervisor', 'Acceso completo sin administración de usuarios', 1, '2026-07-22 00:44:08', 'Supervisor'),
(3, 'Operador', 'Unicamente puede registrar salidas de inventario', 1, '2026-07-22 00:44:08', 'Operador'),
(4, 'Solo lectura', 'Solo puede consultar reportes', 1, '2026-07-22 00:44:08', 'Solo lectura');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_cabecera_predespacho`
--

DROP TABLE IF EXISTS `tbl_cabecera_predespacho`;
CREATE TABLE IF NOT EXISTS `tbl_cabecera_predespacho` (
  `idCabeceraPredespacho` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `idCliente` int UNSIGNED NOT NULL,
  `fechaRetiro` date NOT NULL,
  `codigoInterno` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigoNotaEntregaSAP` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `userCreador` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statusGeneralPredespacho` enum('abierto','pendiente','embarcado','cerrado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'abierto',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `fechaCreacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fechaActualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idCabeceraPredespacho`),
  UNIQUE KEY `uq_codigo_interno` (`codigoInterno`),
  KEY `idx_cab_cliente` (`idCliente`),
  KEY `idx_cab_status` (`statusGeneralPredespacho`),
  KEY `idx_cab_fecha_retiro` (`fechaRetiro`),
  KEY `idx_cab_nota_sap` (`codigoNotaEntregaSAP`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tbl_cabecera_predespacho`
--

INSERT INTO `tbl_cabecera_predespacho` (`idCabeceraPredespacho`, `idCliente`, `fechaRetiro`, `codigoInterno`, `codigoNotaEntregaSAP`, `userCreador`, `statusGeneralPredespacho`, `observaciones`, `fechaCreacion`, `fechaActualizacion`) VALUES
(5, 1, '2026-07-28', 'PRE-2026-00001', 'ssssssss', 'jsantana', 'cerrado', 'asdadasd', '2026-07-28 20:45:06', '2026-07-28 21:47:05'),
(6, 1, '2026-07-30', 'PRE-2026-00002', NULL, 'jsantana', 'cerrado', 'wrewrerwerwerw', '2026-07-28 21:20:37', '2026-07-28 21:35:13'),
(7, 1, '2026-07-31', 'PRE-2026-00003', NULL, 'jsantana', 'abierto', '23423423423', '2026-07-28 21:43:35', '2026-07-28 21:43:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_cliente`
--

DROP TABLE IF EXISTS `tbl_cliente`;
CREATE TABLE IF NOT EXISTS `tbl_cliente` (
  `idCliente` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `rif` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` text COLLATE utf8mb4_unicode_ci,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ej: nacional, exportacion, especial',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fechaCreacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fechaActualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idCliente`),
  UNIQUE KEY `uq_cliente_rif` (`rif`),
  KEY `idx_cliente_nombre` (`nombre`),
  KEY `idx_cliente_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Clientes del módulo Predespacho';

--
-- Volcado de datos para la tabla `tbl_cliente`
--

INSERT INTO `tbl_cliente` (`idCliente`, `rif`, `nombre`, `direccion`, `tipo`, `activo`, `fechaCreacion`, `fechaActualizacion`) VALUES
(1, 'j133367680', 'Jesus Santana', 'Prebo', 'natural', 1, '2026-07-27 20:18:45', '2026-07-27 20:18:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tbl_items_predespacho`
--

DROP TABLE IF EXISTS `tbl_items_predespacho`;
CREATE TABLE IF NOT EXISTS `tbl_items_predespacho` (
  `idItem` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `idCabeceraPredespacho` int UNSIGNED NOT NULL,
  `idInventarioEntrante` int UNSIGNED NOT NULL,
  `cantidadSolicitada` decimal(14,4) NOT NULL,
  `cantidadDespachada` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estatusItemPredespacho` enum('abierto','pendiente','cerrado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'abierto',
  `fechaCreacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fechaActualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idItem`),
  KEY `idx_item_cabecera` (`idCabeceraPredespacho`),
  KEY `idx_item_inventario` (`idInventarioEntrante`),
  KEY `idx_item_estatus` (`estatusItemPredespacho`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tbl_items_predespacho`
--

INSERT INTO `tbl_items_predespacho` (`idItem`, `idCabeceraPredespacho`, `idInventarioEntrante`, `cantidadSolicitada`, `cantidadDespachada`, `tipo`, `estatusItemPredespacho`, `fechaCreacion`, `fechaActualizacion`) VALUES
(5, 5, 8, 700.0000, 700.0000, 'REAL', 'cerrado', '2026-07-28 20:45:30', '2026-07-28 21:10:30'),
(6, 6, 8, 500.0000, 500.0000, 'REAL', 'cerrado', '2026-07-28 21:30:38', '2026-07-28 21:34:20'),
(7, 6, 9, 9980.0000, 9980.0000, 'REAL', 'cerrado', '2026-07-28 21:33:15', '2026-07-28 21:35:13'),
(8, 7, 9, 1500.0000, 0.0000, 'REAL', 'abierto', '2026-07-28 21:43:53', '2026-07-28 21:43:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ubicacion`
--

DROP TABLE IF EXISTS `ubicacion`;
CREATE TABLE IF NOT EXISTS `ubicacion` (
  `idUbicacion` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) NOT NULL,
  PRIMARY KEY (`idUbicacion`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `ubicacion`
--

INSERT INTO `ubicacion` (`idUbicacion`, `nombre`) VALUES
(1, 'San Diego'),
(2, 'Prebo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id_usuario` int NOT NULL AUTO_INCREMENT,
  `nombre_completo` varchar(150) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `id_rol` int NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `ultimo_acceso` datetime DEFAULT NULL,
  `creado_por` int DEFAULT NULL,
  `intentos_fallidos` int UNSIGNED NOT NULL DEFAULT '0',
  `bloqueado_hasta` datetime DEFAULT NULL,
  `actualizado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `username` (`username`),
  KEY `id_rol` (`id_rol`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre_completo`, `username`, `password_hash`, `id_rol`, `activo`, `fecha_creacion`, `ultimo_acceso`, `creado_por`, `intentos_fallidos`, `bloqueado_hasta`, `actualizado_en`) VALUES
(1, 'Administrador Sistema', 'admin', '$2y$10$JpOzd4XqRqYr93DnYYbGleyHmfupE5OXPqYiUoWDNSUA9I2vRTToC', 1, 1, '2026-07-22 00:44:09', '2026-07-22 09:47:22', NULL, 0, NULL, NULL),
(2, 'Jesus Santana', 'jsantana', '$2y$10$XGMn3sDvQIhzxGm.ThgLf.NBkICKcBC.3JN.y6GvW94FGpl6q7vwi', 1, 1, '2026-07-22 01:14:34', '2026-07-28 17:35:25', NULL, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_disponibilidad_lotes`
-- (Véase abajo para la vista actual)
--
DROP VIEW IF EXISTS `v_disponibilidad_lotes`;
CREATE TABLE IF NOT EXISTS `v_disponibilidad_lotes` (
`idInventarioEntrante` int
,`idProducto` int
,`idPresentacion` int
,`NumLote` varchar(100)
,`idUbicación` int
,`sector` varchar(50)
,`stock_total` int
,`cantidad_reservada` decimal(59,4)
,`cantidad_disponible` decimal(60,4)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `v_disponibilidad_lotes`
--
DROP TABLE IF EXISTS `v_disponibilidad_lotes`;

DROP VIEW IF EXISTS `v_disponibilidad_lotes`;
CREATE ALGORITHM=UNDEFINED  SQL SECURITY DEFINER VIEW `v_disponibilidad_lotes`  AS SELECT `ie`.`idInventarioEntrante` AS `idInventarioEntrante`, `ie`.`idProducto` AS `idProducto`, `ie`.`idPresentacion` AS `idPresentacion`, `ie`.`NumLote` AS `NumLote`, `ie`.`idUbicación` AS `idUbicación`, `ie`.`sector` AS `sector`, `ie`.`CantidadEntrante` AS `stock_total`, coalesce(`reservas`.`cantidad_reservada`,0) AS `cantidad_reservada`, ((`ie`.`CantidadEntrante` - coalesce(`salidas`.`cantidad_saliente`,0)) - coalesce(`reservas`.`cantidad_reservada`,0)) AS `cantidad_disponible` FROM ((`inventarioentrante` `ie` left join (select `inventariosaliente`.`idInventarioEntrante` AS `idInventarioEntrante`,sum(`inventariosaliente`.`cantidadSaliente`) AS `cantidad_saliente` from `inventariosaliente` group by `inventariosaliente`.`idInventarioEntrante`) `salidas` on((`salidas`.`idInventarioEntrante` = `ie`.`idInventarioEntrante`))) left join (select `it`.`idInventarioEntrante` AS `idInventarioEntrante`,sum(greatest((`it`.`cantidadSolicitada` - coalesce(`item_salidas`.`cantidad_saliente`,0)),0)) AS `cantidad_reservada` from ((`tbl_items_predespacho` `it` join `tbl_cabecera_predespacho` `cp` on((`cp`.`idCabeceraPredespacho` = `it`.`idCabeceraPredespacho`))) left join (select `it2`.`idItem` AS `idItem`,sum(`ins`.`cantidadSaliente`) AS `cantidad_saliente` from ((`tbl_items_predespacho` `it2` join `tbl_cabecera_predespacho` `cp2` on((`cp2`.`idCabeceraPredespacho` = `it2`.`idCabeceraPredespacho`))) join `inventariosaliente` `ins` on(((`ins`.`idInventarioEntrante` = `it2`.`idInventarioEntrante`) and ((`ins`.`NE` collate utf8mb4_unicode_ci) = `cp2`.`codigoInterno`)))) group by `it2`.`idItem`) `item_salidas` on((`item_salidas`.`idItem` = `it`.`idItem`))) where ((`cp`.`statusGeneralPredespacho` <> 'cerrado') and (`it`.`estatusItemPredespacho` <> 'cerrado')) group by `it`.`idInventarioEntrante`) `reservas` on((`reservas`.`idInventarioEntrante` = `ie`.`idInventarioEntrante`))) ;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `tbl_cabecera_predespacho`
--
ALTER TABLE `tbl_cabecera_predespacho`
  ADD CONSTRAINT `fk_cab_cliente` FOREIGN KEY (`idCliente`) REFERENCES `tbl_cliente` (`idCliente`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `tbl_items_predespacho`
--
ALTER TABLE `tbl_items_predespacho`
  ADD CONSTRAINT `fk_item_cabecera` FOREIGN KEY (`idCabeceraPredespacho`) REFERENCES `tbl_cabecera_predespacho` (`idCabeceraPredespacho`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
