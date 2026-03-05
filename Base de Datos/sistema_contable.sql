-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 05-03-2026 a las 16:36:55
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `sistema_contable`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `activos_fijos`
--

CREATE TABLE `activos_fijos` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `factura_id` int(11) DEFAULT NULL,
  `plan_cuenta_id` int(11) NOT NULL,
  `nombre_activo` varchar(150) NOT NULL,
  `monto_adquisicion` decimal(15,2) NOT NULL,
  `fecha_adquisicion` date NOT NULL,
  `estado` enum('PENDIENTE','ACTIVO','DEPRECIADO','DADO_DE_BAJA') DEFAULT 'PENDIENTE',
  `categoria_sii_id` int(11) DEFAULT NULL,
  `tipo_depreciacion` enum('NORMAL','ACELERADA') DEFAULT NULL,
  `vida_util_meses` int(11) NOT NULL DEFAULT 0,
  `fecha_activacion` date DEFAULT NULL,
  `meses_depreciados` int(11) DEFAULT 0,
  `depreciacion_acumulada` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `activos_fijos`
--

INSERT INTO `activos_fijos` (`id`, `empresa_id`, `factura_id`, `plan_cuenta_id`, `nombre_activo`, `monto_adquisicion`, `fecha_adquisicion`, `estado`, `categoria_sii_id`, `tipo_depreciacion`, `vida_util_meses`, `fecha_activacion`, `meses_depreciados`, `depreciacion_acumulada`) VALUES
(1, 1, 6, 35, 'Equipo Fac. N° 494150', 664697.00, '2026-02-20', 'ACTIVO', 7, 'NORMAL', 72, NULL, 1, 9232.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asientos_contables`
--

CREATE TABLE `asientos_contables` (
  `id` int(11) NOT NULL,
  `codigo_unico` bigint(20) UNSIGNED DEFAULT NULL,
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  `fecha` date NOT NULL,
  `glosa` varchar(255) NOT NULL,
  `tipo_asiento` enum('ingreso','egreso','traspaso') NOT NULL DEFAULT 'traspaso',
  `origen_modulo` varchar(50) DEFAULT 'manual',
  `origen_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `asientos_contables`
--

INSERT INTO `asientos_contables` (`id`, `codigo_unico`, `empresa_id`, `fecha`, `glosa`, `tipo_asiento`, `origen_modulo`, `origen_id`, `created_at`) VALUES
(7, 26260000004, 1, '2026-01-06', 'Compra Fac. 1 - Procesadora Insuban Spa', 'egreso', 'COMPRA', 5, '2026-01-06 20:51:01'),
(8, 26260000005, 1, '2026-02-20', 'Compra Fac. 494150 - Ingenieria Informatica Asociada Limitada (Tecnomas) | [Reclasificado: Lenovo ThinkBook 14 F-494150 TecnoMas] | [Reclasificado: Lenovo ThinkBook 14 F-494150 TecnoMas]', 'egreso', 'COMPRA', 6, '2026-02-20 19:16:23'),
(9, 26260000006, 1, '2026-02-20', 'Compra Fac. 20896 - Premium Hosting Solutions Spa', 'egreso', 'COMPRA', 7, '2026-02-20 19:16:58'),
(10, 26260000007, 1, '2026-02-20', 'REVERSO NULO: Factura N° 1. Motivo: Documento de pruebas', '', 'COMPRA', 5, '2026-02-20 19:55:07'),
(12, 26260000008, 1, '2026-02-20', 'Compra Fac. 2 - Procesadora Insuban Spa', 'egreso', 'COMPRA', 9, '2026-02-20 20:04:08'),
(13, 26260000009, 1, '2026-02-20', 'REVERSO NULO: Factura N° 2. Motivo: prueba', '', 'COMPRA', 9, '2026-02-20 20:10:06'),
(14, 26260000010, 1, '2026-02-20', 'Compra Fac. 3 - Procesadora Insuban Spa', 'egreso', 'COMPRA', 10, '2026-02-20 20:19:10'),
(15, 26260000011, 1, '2026-02-20', 'Compra Fac. 4 - Procesadora Insuban Spa', 'egreso', 'COMPRA', 11, '2026-02-20 20:23:22'),
(16, 26260000012, 1, '2026-02-20', 'Compra Fac. 5 - Procesadora Insuban Spa', 'egreso', 'COMPRA', 12, '2026-02-20 20:24:47'),
(17, 26260000013, 1, '2026-02-20', 'REVERSO NULO: Factura N° 4. Motivo: Prueba', '', 'COMPRA', 11, '2026-02-20 22:06:25'),
(21, 26120000001, 1, '2026-02-21', 'Depreciación de Activos Fijos - Periodo 02/2026', 'traspaso', 'ACTIVOS_FIJOS', 0, '2026-02-21 22:54:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria_facturas`
--

CREATE TABLE `auditoria_facturas` (
  `id` int(11) NOT NULL,
  `id_factura` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `timestamp_operacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `tipo_operacion` varchar(100) NOT NULL,
  `estado_anterior` varchar(50) DEFAULT NULL,
  `estado_nuevo` varchar(50) DEFAULT NULL,
  `detalle_cambio` text NOT NULL,
  `referencia_asiento` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalogo_bancos`
--

CREATE TABLE `catalogo_bancos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `catalogo_bancos`
--

INSERT INTO `catalogo_bancos` (`id`, `nombre`) VALUES
(1, 'Banco Estado'),
(2, 'Banco de Chile'),
(3, 'Banco Santander'),
(4, 'Banco BCI'),
(5, 'Banco Itaú'),
(6, 'Scotiabank'),
(7, 'Banco Falabella'),
(8, 'Banco Ripley'),
(9, 'Banco Security'),
(10, 'Banco Consorcio'),
(11, 'Banco Internacional'),
(12, 'Banco Bice'),
(13, 'HSBC Bank'),
(14, 'JP Morgan'),
(15, 'Tenpo (Prepaid)'),
(16, 'Mercado Pago'),
(17, 'Coopeuch');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalogo_plan_maestro`
--

CREATE TABLE `catalogo_plan_maestro` (
  `id` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `tipo` enum('ACTIVO','PASIVO','PATRIMONIO','INGRESO','GASTO') NOT NULL,
  `nivel` int(11) DEFAULT 1,
  `imputable` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `catalogo_plan_maestro`
--

INSERT INTO `catalogo_plan_maestro` (`id`, `codigo`, `nombre`, `tipo`, `nivel`, `imputable`) VALUES
(1, '100000', 'ACTIVOS', 'ACTIVO', 1, 0),
(2, '110000', 'Activo Corriente', 'ACTIVO', 2, 0),
(3, '110100', 'Efectivo y Equivalentes', 'ACTIVO', 3, 0),
(4, '110101', 'Caja General (Efectivo)', 'ACTIVO', 4, 1),
(5, '110102', 'Caja Chica', 'ACTIVO', 4, 1),
(6, '110103', 'Banco Estado', 'ACTIVO', 4, 1),
(7, '110104', 'Banco de Chile', 'ACTIVO', 4, 1),
(8, '110105', 'Banco Santander', 'ACTIVO', 4, 1),
(9, '110106', 'Banco BCI', 'ACTIVO', 4, 1),
(10, '110107', 'Banco Scotiabank', 'ACTIVO', 4, 1),
(11, '110108', 'Fondos Mutuos (Inversión CP)', 'ACTIVO', 4, 1),
(12, '110200', 'Clientes y Deudores', 'ACTIVO', 3, 0),
(13, '110201', 'Clientes Nacionales', 'ACTIVO', 4, 1),
(14, '110202', 'Clientes Extranjeros', 'ACTIVO', 4, 1),
(15, '110203', 'Deudores Varios / Préstamos al Personal', 'ACTIVO', 4, 1),
(16, '110204', 'Documentos por Cobrar (Cheques)', 'ACTIVO', 4, 1),
(17, '110300', 'Existencias (Inventario)', 'ACTIVO', 3, 0),
(18, '110301', 'Mercaderías para Reventa', 'ACTIVO', 4, 1),
(19, '110302', 'Materias Primas', 'ACTIVO', 4, 1),
(20, '110303', 'Productos en Proceso', 'ACTIVO', 4, 1),
(21, '110304', 'Insumos y Materiales', 'ACTIVO', 4, 1),
(22, '110305', 'Envases y Embalajes', 'ACTIVO', 4, 1),
(23, '110400', 'Impuestos por Recuperar', 'ACTIVO', 3, 0),
(24, '110001', 'IVA Crédito Fiscal', 'ACTIVO', 4, 1),
(25, '110402', 'IVA Remanente', 'ACTIVO', 4, 1),
(26, '110403', 'PPM (Pagos Provisionales Mensuales)', 'ACTIVO', 4, 1),
(27, '110404', 'Impuesto a la Renta por Recuperar', 'ACTIVO', 4, 1),
(28, '120000', 'Activo No Corriente', 'ACTIVO', 2, 0),
(29, '120100', 'Propiedad, Planta y Equipo', 'ACTIVO', 3, 0),
(30, '120101', 'Terrenos', 'ACTIVO', 4, 1),
(31, '120102', 'Construcciones y Obras', 'ACTIVO', 4, 1),
(32, '120103', 'Maquinarias y Equipos', 'ACTIVO', 4, 1),
(33, '120104', 'Vehículos', 'ACTIVO', 4, 1),
(34, '120105', 'Muebles y Útiles', 'ACTIVO', 4, 1),
(35, '120106', 'Equipos Computacionales', 'ACTIVO', 4, 1),
(36, '120107', 'Herramientas', 'ACTIVO', 4, 1),
(37, '120200', 'Activos Intangibles', 'ACTIVO', 3, 0),
(38, '120201', 'Software y Licencias', 'ACTIVO', 4, 1),
(39, '120202', 'Marcas y Patentes', 'ACTIVO', 4, 1),
(40, '120203', 'Sitios Web y Dominios', 'ACTIVO', 4, 1),
(41, '120300', 'Depreciación Acumulada (Contra-Activo)', 'ACTIVO', 3, 0),
(42, '120301', 'Deprec. Acum. Maquinaria', 'ACTIVO', 4, 1),
(43, '120302', 'Deprec. Acum. Vehículos', 'ACTIVO', 4, 1),
(44, '120303', 'Amort. Acum. Intangibles', 'ACTIVO', 4, 1),
(45, '200000', 'PASIVOS', 'PASIVO', 1, 0),
(46, '210000', 'Pasivo Corriente', 'PASIVO', 2, 0),
(47, '210100', 'Cuentas por Pagar Comerciales', 'PASIVO', 3, 0),
(48, '352130', 'Facturas por Pagar (Proveedores Nacionales)', 'PASIVO', 4, 1),
(49, '210102', 'Proveedores Extranjeros', 'PASIVO', 4, 1),
(50, '210103', 'Honorarios por Pagar', 'PASIVO', 4, 1),
(51, '210104', 'Anticipos de Clientes', 'PASIVO', 4, 1),
(52, '210200', 'Obligaciones con Personal', 'PASIVO', 3, 0),
(53, '210201', 'IVA Débito Fiscal', 'PASIVO', 4, 1),
(54, '210202', 'Leyes Sociales por Pagar (Previred)', 'PASIVO', 4, 1),
(55, '210203', 'Retenciones Judiciales', 'PASIVO', 4, 1),
(56, '210204', 'Provisión Vacaciones', 'PASIVO', 4, 1),
(57, '210300', 'Impuestos por Pagar', 'PASIVO', 3, 0),
(58, '210302', 'Impuesto Único a los Trabajadores', 'PASIVO', 4, 1),
(59, '210303', 'Retención 2da Categoría (Honorarios)', 'PASIVO', 4, 1),
(60, '210304', 'Impuesto a la Renta por Pagar', 'PASIVO', 4, 1),
(61, '210400', 'Obligaciones Financieras CP', 'PASIVO', 3, 0),
(62, '210401', 'Préstamos Bancarios CP', 'PASIVO', 4, 1),
(63, '210402', 'Línea de Crédito', 'PASIVO', 4, 1),
(64, '210403', 'Tarjeta de Crédito Empresa', 'PASIVO', 4, 1),
(65, '220000', 'Pasivo No Corriente', 'PASIVO', 2, 0),
(66, '220100', 'Obligaciones Financieras LP', 'PASIVO', 3, 0),
(67, '220101', 'Créditos Bancarios LP', 'PASIVO', 4, 1),
(68, '220102', 'Leasing por Pagar', 'PASIVO', 4, 1),
(69, '300000', 'PATRIMONIO', 'PATRIMONIO', 1, 0),
(70, '310000', 'Capital y Reservas', 'PATRIMONIO', 2, 0),
(71, '310101', 'Capital Pagado', 'PATRIMONIO', 4, 1),
(72, '310102', 'Revalorización Capital Propio', 'PATRIMONIO', 4, 1),
(73, '310200', 'Resultados', 'PATRIMONIO', 3, 0),
(74, '310201', 'Utilidades Acumuladas', 'PATRIMONIO', 4, 1),
(75, '310202', 'Pérdidas Acumuladas', 'PATRIMONIO', 4, 1),
(76, '310203', 'Utilidad del Ejercicio', 'PATRIMONIO', 4, 1),
(77, '310204', 'Pérdida del Ejercicio', 'PATRIMONIO', 4, 1),
(78, '310300', 'Retiros y Dividendos', 'PATRIMONIO', 3, 0),
(79, '310301', 'Cuenta Particular Socio A', 'PATRIMONIO', 4, 1),
(80, '310302', 'Cuenta Particular Socio B', 'PATRIMONIO', 4, 1),
(81, '400000', 'INGRESOS', 'INGRESO', 1, 0),
(82, '410000', 'Ingresos de Explotación', 'INGRESO', 2, 0),
(83, '410101', 'Ventas Netas (Afectas)', 'INGRESO', 4, 1),
(84, '410102', 'Ventas Exentas', 'INGRESO', 4, 1),
(85, '410103', 'Servicios Prestados', 'INGRESO', 4, 1),
(86, '410104', 'Exportaciones', 'INGRESO', 4, 1),
(87, '420000', 'Otros Ingresos (No Operacionales)', 'INGRESO', 2, 0),
(88, '420101', 'Intereses Ganados (Bancarios)', 'INGRESO', 4, 1),
(89, '420102', 'Diferencia de Cambio (Ganancia)', 'INGRESO', 4, 1),
(90, '420103', 'Venta de Activo Fijo (Ganancia)', 'INGRESO', 4, 1),
(91, '420104', 'Descuentos Obtenidos', 'INGRESO', 4, 1),
(92, '500000', 'COSTOS DE VENTA', 'GASTO', 1, 0),
(93, '510000', 'Costo Directo', 'GASTO', 2, 0),
(94, '500001', 'Costo de Mercadería Vendida', 'GASTO', 4, 1),
(95, '510101', 'Costo de Materias Primas', 'GASTO', 4, 1),
(96, '510102', 'Mano de Obra Directa', 'GASTO', 4, 1),
(97, '510103', 'Costos Indirectos de Fabricación', 'GASTO', 4, 1),
(98, '510104', 'Costo por Servicios Subcontratados', 'GASTO', 4, 1),
(99, '600000', 'GASTOS OPERACIONALES', 'GASTO', 1, 0),
(100, '610000', 'Gastos de Personal', 'GASTO', 2, 0),
(101, '610101', 'Sueldos Base', 'GASTO', 4, 1),
(102, '610102', 'Comisiones de Venta', 'GASTO', 4, 1),
(103, '610103', 'Gratificaciones', 'GASTO', 4, 1),
(104, '610104', 'Horas Extras', 'GASTO', 4, 1),
(105, '610105', 'Bonos y Aguinaldos', 'GASTO', 4, 1),
(106, '610106', 'Aporte Patronal (SIS/Cesantía)', 'GASTO', 4, 1),
(107, '610107', 'Colación y Movilización', 'GASTO', 4, 1),
(108, '610108', 'Indemnizaciones por Años de Servicio', 'GASTO', 4, 1),
(109, '610109', 'Capacitación (Sence)', 'GASTO', 4, 1),
(110, '610110', 'Ropa de Trabajo y EPP', 'GASTO', 4, 1),
(111, '610111', 'Sala Cuna', 'GASTO', 4, 1),
(112, '620000', 'Infraestructura y Oficina', 'GASTO', 2, 0),
(113, '620101', 'Arriendo Oficina / Local', 'GASTO', 4, 1),
(114, '620102', 'Gastos Comunes', 'GASTO', 4, 1),
(115, '620103', 'Electricidad', 'GASTO', 4, 1),
(116, '620104', 'Agua Potable', 'GASTO', 4, 1),
(117, '620105', 'Gas y Calefacción', 'GASTO', 4, 1),
(118, '620106', 'Internet y Telefonía Fija', 'GASTO', 4, 1),
(119, '620107', 'Celulares Planes Empresa', 'GASTO', 4, 1),
(120, '620108', 'Artículos de Aseo y Limpieza', 'GASTO', 4, 1),
(121, '620109', 'Artículos de Librería y Oficina', 'GASTO', 4, 1),
(122, '620110', 'Seguridad y Alarmas', 'GASTO', 4, 1),
(123, '620111', 'Mantenimiento Oficina (Reparaciones)', 'GASTO', 4, 1),
(124, '630000', 'Tecnología e Informática', 'GASTO', 2, 0),
(125, '630101', 'Licencias de Software (Microsoft, Adobe)', 'GASTO', 4, 1),
(126, '630102', 'Servicios Cloud (AWS, Azure, Google)', 'GASTO', 4, 1),
(127, '630103', 'Hosting y Dominios', 'GASTO', 4, 1),
(128, '630104', 'Soporte Informático Externo', 'GASTO', 4, 1),
(129, '630105', 'Insumos Computacionales (Toner, Cables)', 'GASTO', 4, 1),
(130, '640000', 'Marketing y Ventas', 'GASTO', 2, 0),
(131, '640101', 'Publicidad Digital (Google/Meta Ads)', 'GASTO', 4, 1),
(132, '640102', 'Agencia de Marketing / Community Manager', 'GASTO', 4, 1),
(133, '640103', 'Merchandising y Regalos Corp', 'GASTO', 4, 1),
(134, '640104', 'Gastos de Representación (Comidas)', 'GASTO', 4, 1),
(135, '640105', 'Viajes y Estadía', 'GASTO', 4, 1),
(136, '640106', 'Ferias y Eventos', 'GASTO', 4, 1),
(137, '650000', 'Vehículos y Logística', 'GASTO', 2, 0),
(138, '650101', 'Combustibles y Lubricantes', 'GASTO', 4, 1),
(139, '650102', 'Peajes y Estacionamientos (TAG)', 'GASTO', 4, 1),
(140, '650103', 'Mantención y Reparación Vehículos', 'GASTO', 4, 1),
(141, '650104', 'Seguros de Vehículos', 'GASTO', 4, 1),
(142, '650105', 'Fletes y Despachos (Courier)', 'GASTO', 4, 1),
(143, '650106', 'Permisos de Circulación', 'GASTO', 4, 1),
(144, '660000', 'Servicios Profesionales', 'GASTO', 2, 0),
(145, '660101', 'Servicio Contable', 'GASTO', 4, 1),
(146, '660102', 'Asesoría Legal (Abogados)', 'GASTO', 4, 1),
(147, '660103', 'Notaría y Trámites', 'GASTO', 4, 1),
(148, '660104', 'Consultorías Varias', 'GASTO', 4, 1),
(149, '670000', 'Impuestos y Patentes (Gasto)', 'GASTO', 2, 0),
(150, '670101', 'Patente Municipal', 'GASTO', 4, 1),
(151, '670102', 'Contribuciones (Bienes Raíces)', 'GASTO', 4, 1),
(152, '670103', 'Multas e Intereses Fiscales', 'GASTO', 4, 1),
(153, '680000', 'Gastos Financieros', 'GASTO', 2, 0),
(154, '680101', 'Intereses Bancarios', 'GASTO', 4, 1),
(155, '680102', 'Comisiones Bancarias (Mantención Cta)', 'GASTO', 4, 1),
(156, '680103', 'Comisiones Transbank / Webpay', 'GASTO', 4, 1),
(157, '680104', 'Diferencia de Cambio (Pérdida)', 'GASTO', 4, 1),
(158, '690000', 'Otros Gastos', 'GASTO', 2, 0),
(159, '690101', 'Gastos Menores (Sin boleta/Vale)', 'GASTO', 4, 1),
(160, '690102', 'Suscripciones y Membresías', 'GASTO', 4, 1),
(161, '690103', 'Donaciones', 'GASTO', 4, 1),
(162, '690104', 'Mermas y Castigos', 'GASTO', 4, 1),
(163, '690199', 'Compras por Clasificar (Cuenta Puente)', 'GASTO', 4, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `rut` varchar(20) NOT NULL,
  `razon_social` varchar(255) NOT NULL,
  `contacto_nombre` varchar(255) DEFAULT NULL,
  `contacto_email` varchar(100) DEFAULT NULL,
  `contacto_telefono` varchar(50) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'ACTIVO',
  `empresa_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `rut`, `razon_social`, `contacto_nombre`, `contacto_email`, `contacto_telefono`, `direccion`, `telefono`, `email`, `estado`, `empresa_id`, `created_at`) VALUES
(1, '78.730.890-2', 'Procesadora Insuban Spa', 'Nestor Cerdan', 'ncerdan@insuban.cl', '', 'Antillanca Norte 391', NULL, 'finanzas@insuban.cl', 'ACTIVO', 1, '2026-01-07 18:55:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_secuencias`
--

CREATE TABLE `configuracion_secuencias` (
  `empresa_id` int(11) NOT NULL,
  `entidad` varchar(50) NOT NULL,
  `ultimo_valor` bigint(20) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `configuracion_secuencias`
--

INSERT INTO `configuracion_secuencias` (`empresa_id`, `entidad`, `ultimo_valor`) VALUES
(1, 'ASIENTO', 26100000001),
(1, 'ASIENTO_FACTURA', 260000001),
(1, 'ASIENTO_MANUAL', 100000001),
(1, 'FACTURA', 26260000000),
(1, 'facturas', 26260000013),
(1, 'PROVEEDOR', 0),
(1, 'proveedores', 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizaciones`
--

CREATE TABLE `cotizaciones` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `nombre_cliente` varchar(255) NOT NULL,
  `fecha_emision` date NOT NULL,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `estado_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `es_afecta` tinyint(1) DEFAULT 1,
  `validez` int(11) DEFAULT 15
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cotizaciones`
--

INSERT INTO `cotizaciones` (`id`, `cliente_id`, `nombre_cliente`, `fecha_emision`, `total`, `estado_id`, `empresa_id`, `created_at`, `es_afecta`, `validez`) VALUES
(7, 1, 'Procesadora Insuban Spa', '2026-01-08', 2000000.00, 2, 1, '2026-01-08 02:15:01', 0, 7),
(8, 1, 'Procesadora Insuban Spa', '2026-02-18', 600000.00, 2, 1, '2026-02-20 18:45:51', 0, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizacion_detalles`
--

CREATE TABLE `cotizacion_detalles` (
  `id` int(11) NOT NULL,
  `cotizacion_id` int(11) NOT NULL,
  `producto_nombre` varchar(255) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cotizacion_detalles`
--

INSERT INTO `cotizacion_detalles` (`id`, `cotizacion_id`, `producto_nombre`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
(7, 7, 'Sistema de Gestión - InsuOrders\nPlataforma web para gestión de bodega, compras y mantenciones, con control de inventarios, proveedores y trazabilidad de operaciones.', 1, 2000000.00, 2000000.00),
(8, 8, 'Sistema de gestion general InsuOrders - Creación nuevos módulos de Clientes y modificación de modelos de datos', 1, 600000.00, 600000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuentas_bancarias_empresa`
--

CREATE TABLE `cuentas_bancarias_empresa` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `banco` varchar(100) NOT NULL,
  `tipo_cuenta` varchar(50) NOT NULL,
  `numero_cuenta` varchar(50) NOT NULL,
  `titular` varchar(150) NOT NULL,
  `rut_titular` varchar(20) NOT NULL,
  `email_notificacion` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cuentas_bancarias_empresa`
--

INSERT INTO `cuentas_bancarias_empresa` (`id`, `empresa_id`, `banco`, `tipo_cuenta`, `numero_cuenta`, `titular`, `rut_titular`, `email_notificacion`, `created_at`) VALUES
(1, 1, 'Scotiabank', 'Corriente', '000991980431', 'Tecnologias Nicolas Salas E.I.R.L', '78.149.179-9', '', '2026-01-08 01:40:43');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuentas_bancarias_proveedores`
--

CREATE TABLE `cuentas_bancarias_proveedores` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `banco` varchar(100) NOT NULL,
  `numero_cuenta` varchar(50) NOT NULL,
  `tipo_cuenta` varchar(50) DEFAULT NULL,
  `pais_iso` char(2) NOT NULL,
  `swift_bic` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cuentas_bancarias_proveedores`
--

INSERT INTO `cuentas_bancarias_proveedores` (`id`, `proveedor_id`, `banco`, `numero_cuenta`, `tipo_cuenta`, `pais_iso`, `swift_bic`, `activo`) VALUES
(3, 2, 'Banco Itau', '215674042', 'Corriente', 'CL', NULL, 1),
(4, 3, 'Banco Estado', '21670128111', 'Vista', 'CL', NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `depreciaciones_mensuales`
--

CREATE TABLE `depreciaciones_mensuales` (
  `id` int(11) NOT NULL,
  `proyecto_id` int(11) NOT NULL,
  `fecha_proceso` date NOT NULL,
  `monto` decimal(15,2) NOT NULL,
  `asiento_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalles_asiento`
--

CREATE TABLE `detalles_asiento` (
  `id` int(11) NOT NULL,
  `asiento_id` int(11) NOT NULL,
  `cuenta_contable` varchar(100) NOT NULL,
  `fecha` date DEFAULT NULL,
  `tipo_operacion` varchar(100) DEFAULT NULL,
  `debe` decimal(15,2) DEFAULT 0.00,
  `haber` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `detalles_asiento`
--

INSERT INTO `detalles_asiento` (`id`, `asiento_id`, `cuenta_contable`, `fecha`, `tipo_operacion`, `debe`, `haber`) VALUES
(11, 7, '210101', '2026-01-06', 'ORIGINAL', 0.00, 1000000.00),
(12, 7, '690199', '2026-01-06', 'ORIGINAL', 1000000.00, 0.00),
(13, 8, '210101', '2026-02-20', 'ORIGINAL', 0.00, 790989.00),
(14, 8, '110001', '2026-02-20', 'ORIGINAL', 126292.00, 0.00),
(15, 8, '690199', '2026-02-20', 'ORIGINAL', 664697.00, 0.00),
(16, 9, '210101', '2026-02-20', 'ORIGINAL', 0.00, 34510.00),
(17, 9, '110001', '2026-02-20', 'ORIGINAL', 5510.00, 0.00),
(18, 9, '690199', '2026-02-20', 'ORIGINAL', 29000.00, 0.00),
(19, 10, '210101', '2026-02-20', 'ORIGINAL', 1000000.00, 0.00),
(20, 10, '690199', '2026-02-20', 'ORIGINAL', 0.00, 1000000.00),
(21, 12, '210101', '2026-02-20', 'ORIGINAL', 0.00, 1850000.00),
(22, 12, '110001', '2026-02-20', 'ORIGINAL', 295378.00, 0.00),
(23, 12, '690199', '2026-02-20', 'ORIGINAL', 1554622.00, 0.00),
(24, 13, '210101', '2026-02-20', 'ORIGINAL', 1850000.00, 0.00),
(25, 13, '110001', '2026-02-20', 'ORIGINAL', 0.00, 295378.00),
(26, 13, '690199', '2026-02-20', 'ORIGINAL', 0.00, 1554622.00),
(27, 14, '210101', '2026-02-20', 'ORIGINAL', 0.00, 1850000.00),
(28, 14, '110001', '2026-02-20', 'ORIGINAL', 295378.00, 0.00),
(29, 14, '690199', '2026-02-20', 'ORIGINAL', 1554622.00, 0.00),
(30, 15, '210101', '2026-02-20', 'ORIGINAL', 0.00, 2000000.00),
(31, 15, '110001', '2026-02-20', 'ORIGINAL', 319328.00, 0.00),
(32, 15, '690199', '2026-02-20', 'ORIGINAL', 1680672.00, 0.00),
(33, 16, '210101', '2026-02-20', 'ORIGINAL', 0.00, 2000000.00),
(34, 16, '110001', '2026-02-20', 'ORIGINAL', 319328.00, 0.00),
(35, 16, '690199', '2026-02-20', 'ORIGINAL', 1680672.00, 0.00),
(36, 17, '210101', '2026-02-20', 'ORIGINAL', 2000000.00, 0.00),
(37, 17, '110001', '2026-02-20', 'ORIGINAL', 0.00, 319328.00),
(38, 17, '690199', '2026-02-20', 'ORIGINAL', 0.00, 1680672.00),
(41, 8, '690199', '2026-02-21', 'SALIDA: Lenovo ThinkBook 14 F-494150 TecnoMas', 0.00, 664697.00),
(42, 8, '120106', '2026-02-21', 'ENTRADA: Lenovo ThinkBook 14 F-494150 TecnoMas', 664697.00, 0.00),
(43, 8, '690199', '2026-02-21', 'SALIDA: Lenovo ThinkBook 14 F-494150 TecnoMas', 0.00, 664697.00),
(44, 8, '120106', '2026-02-21', 'ENTRADA: Lenovo ThinkBook 14 F-494150 TecnoMas', 664697.00, 0.00),
(53, 21, '690105', NULL, NULL, 9232.00, 0.00),
(54, 21, '120304', NULL, NULL, 0.00, 9232.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresas`
--

CREATE TABLE `empresas` (
  `id` int(11) NOT NULL,
  `rut` varchar(20) NOT NULL,
  `razon_social` varchar(150) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email` varchar(100) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `color_primario` varchar(7) DEFAULT '#10b981',
  `regimen_tributario` enum('14_D3','14_D8','14_A') DEFAULT '14_D3',
  `tasa_impuesto` decimal(5,2) DEFAULT 25.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `empresas`
--

INSERT INTO `empresas` (`id`, `rut`, `razon_social`, `direccion`, `created_at`, `email`, `telefono`, `logo_path`, `color_primario`, `regimen_tributario`, `tasa_impuesto`) VALUES
(1, '78.149.179-9', 'Tecnologías Nicolas Salas E.I.R.L', 'Antonio Bellet 193', '2026-01-04 19:04:33', 'nicolas.salas.contacto@gmail.com', '+56 9 3709 4271', 'uploads/logos/logo_695f098840c13.png', '#2492f9', '14_D3', 25.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados_suscripcion`
--

CREATE TABLE `estados_suscripcion` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estados_suscripcion`
--

INSERT INTO `estados_suscripcion` (`id`, `nombre`) VALUES
(1, 'Activo'),
(2, 'Inactivo'),
(3, 'Pago Pendiente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estado_cotizaciones`
--

CREATE TABLE `estado_cotizaciones` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estado_cotizaciones`
--

INSERT INTO `estado_cotizaciones` (`id`, `nombre`, `descripcion`) VALUES
(1, 'PENDIENTE', 'Cotización enviada al cliente, a la espera de respuesta'),
(2, 'ACEPTADA', 'El cliente ha aprobado la propuesta'),
(3, 'RECHAZADA', 'El cliente no aceptó la propuesta'),
(4, 'FACTURADA', 'La cotización ya se convirtió en una factura de venta'),
(7, 'ANULADA', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `facturas`
--

CREATE TABLE `facturas` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  `codigo_unico` bigint(20) UNSIGNED NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `cuenta_bancaria_id` int(11) DEFAULT NULL,
  `numero_factura` varchar(50) NOT NULL,
  `fecha_emision` date NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `monto_bruto` decimal(15,2) NOT NULL,
  `monto_neto` decimal(15,2) NOT NULL,
  `monto_iva` decimal(15,2) DEFAULT 0.00,
  `motivo_correccion_iva` varchar(255) DEFAULT NULL,
  `autorizador_id` int(11) DEFAULT NULL,
  `estado` enum('BORRADOR','REGISTRADA','PAGADA','ANULADA') DEFAULT 'REGISTRADA',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `facturas`
--

INSERT INTO `facturas` (`id`, `empresa_id`, `codigo_unico`, `proveedor_id`, `cuenta_bancaria_id`, `numero_factura`, `fecha_emision`, `fecha_vencimiento`, `monto_bruto`, `monto_neto`, `monto_iva`, `motivo_correccion_iva`, `autorizador_id`, `estado`, `created_at`) VALUES
(5, 1, 26260000004, 1, NULL, '1', '2026-01-06', '2026-01-22', 1000000.00, 1000000.00, 0.00, NULL, NULL, 'ANULADA', '2026-01-06 20:51:01'),
(6, 1, 26260000005, 2, 3, '494150', '2026-02-19', '2026-02-19', 790989.00, 664697.00, 126292.00, NULL, NULL, 'REGISTRADA', '2026-02-20 19:16:23'),
(7, 1, 26260000006, 3, NULL, '20896', '2026-02-01', '2026-02-01', 34510.00, 29000.00, 5510.00, NULL, NULL, 'REGISTRADA', '2026-02-20 19:16:58'),
(9, 1, 26260000008, 1, NULL, '2', '2026-02-20', '2027-01-01', 1850000.00, 1554622.00, 295378.00, NULL, NULL, 'ANULADA', '2026-02-20 20:04:08'),
(10, 1, 26260000010, 1, NULL, '3', '2026-02-01', '2026-02-20', 1850000.00, 1554622.00, 295378.00, NULL, NULL, 'REGISTRADA', '2026-02-20 20:19:10'),
(11, 1, 26260000011, 1, NULL, '4', '2026-02-01', '2026-02-20', 2000000.00, 1680672.00, 319328.00, NULL, NULL, 'ANULADA', '2026-02-20 20:23:22'),
(12, 1, 26260000012, 1, NULL, '5', '2026-02-05', '2026-02-20', 2000000.00, 1680672.00, 319328.00, NULL, NULL, 'REGISTRADA', '2026-02-20 20:24:47');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos_facturas`
--

CREATE TABLE `pagos_facturas` (
  `id` int(11) NOT NULL,
  `factura_id` int(11) NOT NULL,
  `cuenta_bancaria_empresa_id` int(11) NOT NULL,
  `asiento_id` int(11) DEFAULT NULL,
  `fecha_pago` date NOT NULL,
  `monto_pagado` decimal(15,2) NOT NULL,
  `metodo_pago` varchar(50) DEFAULT 'Transferencia',
  `numero_operacion` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paises`
--

CREATE TABLE `paises` (
  `iso` char(2) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `moneda_defecto` char(3) NOT NULL,
  `etiqueta_id` varchar(20) DEFAULT 'Identificador',
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `paises`
--

INSERT INTO `paises` (`iso`, `nombre`, `moneda_defecto`, `etiqueta_id`, `activo`) VALUES
('AR', 'Argentina', 'ARS', 'CUIT', 1),
('BO', 'Bolivia', 'BOB', 'NIT', 1),
('BR', 'Brasil', 'BRL', 'CNPJ', 1),
('CL', 'Chile', 'CLP', 'RUT', 1),
('CO', 'Colombia', 'COP', 'NIT', 1),
('DE', 'Alemania', 'EUR', 'Steuer-ID', 1),
('DK', 'Dinamarca', 'DKK', 'CVR', 1),
('EC', 'Ecuador', 'USD', 'RUC', 1),
('ES', 'España', 'EUR', 'NIF', 1),
('FR', 'Francia', 'EUR', 'SIREN', 1),
('MX', 'México', 'MXN', 'RFC', 1),
('PE', 'Perú', 'PEN', 'RUC', 1),
('US', 'Estados Unidos', 'USD', 'EIN', 1),
('UY', 'Uruguay', 'UYU', 'RUT', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plan_cuentas`
--

CREATE TABLE `plan_cuentas` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `tipo` enum('ACTIVO','PASIVO','PATRIMONIO','INGRESO','GASTO') NOT NULL,
  `nivel` int(11) DEFAULT 1,
  `imputable` tinyint(1) DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `plan_cuentas`
--

INSERT INTO `plan_cuentas` (`id`, `empresa_id`, `codigo`, `nombre`, `tipo`, `nivel`, `imputable`, `activo`, `created_at`) VALUES
(1, 1, '100000', 'ACTIVOS', 'ACTIVO', 1, 0, 1, '2026-01-04 05:42:36'),
(2, 1, '110000', 'Activo Corriente', 'ACTIVO', 2, 0, 1, '2026-01-04 05:42:36'),
(3, 1, '110100', 'Efectivo y Equivalentes', 'ACTIVO', 3, 0, 1, '2026-01-04 05:42:36'),
(4, 1, '110101', 'Caja General (Efectivo)', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(5, 1, '110102', 'Caja Chica', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(6, 1, '110103', 'Banco Estado', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(7, 1, '110104', 'Banco de Chile', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(8, 1, '110105', 'Banco Santander', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(9, 1, '110106', 'Banco BCI', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(10, 1, '110107', 'Banco Scotiabank', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(11, 1, '110108', 'Fondos Mutuos (Inversión CP)', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(12, 1, '110200', 'Clientes y Deudores', 'ACTIVO', 3, 0, 1, '2026-01-04 05:42:36'),
(13, 1, '110201', 'Clientes Nacionales', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(14, 1, '110202', 'Clientes Extranjeros', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(15, 1, '110203', 'Deudores Varios / Préstamos al Personal', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(16, 1, '110204', 'Documentos por Cobrar (Cheques)', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(17, 1, '110300', 'Existencias (Inventario)', 'ACTIVO', 3, 0, 1, '2026-01-04 05:42:36'),
(18, 1, '110301', 'Mercaderías para Reventa', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(19, 1, '110302', 'Materias Primas', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(20, 1, '110303', 'Productos en Proceso', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(21, 1, '110304', 'Insumos y Materiales', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(22, 1, '110305', 'Envases y Embalajes', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(23, 1, '110400', 'Impuestos por Recuperar', 'ACTIVO', 3, 0, 1, '2026-01-04 05:42:36'),
(24, 1, '110001', 'IVA Crédito Fiscal', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(25, 1, '110402', 'IVA Remanente', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(26, 1, '110403', 'PPM (Pagos Provisionales Mensuales)', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(27, 1, '110404', 'Impuesto a la Renta por Recuperar', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(28, 1, '120000', 'Activo No Corriente', 'ACTIVO', 2, 0, 1, '2026-01-04 05:42:36'),
(29, 1, '120100', 'Propiedad, Planta y Equipo', 'ACTIVO', 3, 0, 1, '2026-01-04 05:42:36'),
(30, 1, '120101', 'Terrenos', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(31, 1, '120102', 'Construcciones y Obras', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(32, 1, '120103', 'Maquinarias y Equipos', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(33, 1, '120104', 'Vehículos', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(34, 1, '120105', 'Muebles y Útiles', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(35, 1, '120106', 'Equipos Computacionales', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(36, 1, '120107', 'Herramientas', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(37, 1, '120200', 'Activos Intangibles', 'ACTIVO', 3, 0, 1, '2026-01-04 05:42:36'),
(38, 1, '120201', 'Software y Licencias', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(39, 1, '120202', 'Marcas y Patentes', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(40, 1, '120203', 'Sitios Web y Dominios', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(41, 1, '120300', 'Depreciación Acumulada (Contra-Activo)', 'ACTIVO', 3, 0, 1, '2026-01-04 05:42:36'),
(42, 1, '120301', 'Deprec. Acum. Maquinaria', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(43, 1, '120302', 'Deprec. Acum. Vehículos', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(44, 1, '120303', 'Amort. Acum. Intangibles', 'ACTIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(45, 1, '200000', 'PASIVOS', 'PASIVO', 1, 0, 1, '2026-01-04 05:42:36'),
(46, 1, '210000', 'Pasivo Corriente', 'PASIVO', 2, 0, 1, '2026-01-04 05:42:36'),
(47, 1, '210100', 'Cuentas por Pagar Comerciales', 'PASIVO', 3, 0, 1, '2026-01-04 05:42:36'),
(48, 1, '210101', 'Facturas por Pagar (Proveedores Nacionales)', 'PASIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(49, 1, '210102', 'Proveedores Extranjeros', 'PASIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(50, 1, '210103', 'Honorarios por Pagar', 'PASIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(51, 1, '210104', 'Anticipos de Clientes', 'PASIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(52, 1, '210200', 'Obligaciones con Personal', 'PASIVO', 3, 0, 1, '2026-01-04 05:42:36'),
(53, 1, '210201', 'IVA Débito Fiscal', 'PASIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(54, 1, '210202', 'Leyes Sociales por Pagar (Previred)', 'PASIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(55, 1, '210203', 'Retenciones Judiciales', 'PASIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(56, 1, '210204', 'Provisión Vacaciones', 'PASIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(57, 1, '210300', 'Impuestos por Pagar', 'PASIVO', 3, 0, 1, '2026-01-04 05:42:36'),
(58, 1, '210302', 'Impuesto Único a los Trabajadores', 'PASIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(59, 1, '210303', 'Retención 2da Categoría (Honorarios)', 'PASIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(60, 1, '210304', 'Impuesto a la Renta por Pagar', 'PASIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(61, 1, '210400', 'Obligaciones Financieras CP', 'PASIVO', 3, 0, 1, '2026-01-04 05:42:36'),
(62, 1, '210401', 'Préstamos Bancarios CP', 'PASIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(63, 1, '210402', 'Línea de Crédito', 'PASIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(64, 1, '210403', 'Tarjeta de Crédito Empresa', 'PASIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(65, 1, '220000', 'Pasivo No Corriente', 'PASIVO', 2, 0, 1, '2026-01-04 05:42:36'),
(66, 1, '220100', 'Obligaciones Financieras LP', 'PASIVO', 3, 0, 1, '2026-01-04 05:42:36'),
(67, 1, '220101', 'Créditos Bancarios LP', 'PASIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(68, 1, '220102', 'Leasing por Pagar', 'PASIVO', 4, 1, 1, '2026-01-04 05:42:36'),
(69, 1, '300000', 'PATRIMONIO', 'PATRIMONIO', 1, 0, 1, '2026-01-04 05:42:36'),
(70, 1, '310000', 'Capital y Reservas', 'PATRIMONIO', 2, 0, 1, '2026-01-04 05:42:36'),
(71, 1, '310101', 'Capital Pagado', 'PATRIMONIO', 4, 1, 1, '2026-01-04 05:42:36'),
(72, 1, '310102', 'Revalorización Capital Propio', 'PATRIMONIO', 4, 1, 1, '2026-01-04 05:42:36'),
(73, 1, '310200', 'Resultados', 'PATRIMONIO', 3, 0, 1, '2026-01-04 05:42:36'),
(74, 1, '310201', 'Utilidades Acumuladas', 'PATRIMONIO', 4, 1, 1, '2026-01-04 05:42:36'),
(75, 1, '310202', 'Pérdidas Acumuladas', 'PATRIMONIO', 4, 1, 1, '2026-01-04 05:42:36'),
(76, 1, '310203', 'Utilidad del Ejercicio', 'PATRIMONIO', 4, 1, 1, '2026-01-04 05:42:36'),
(77, 1, '310204', 'Pérdida del Ejercicio', 'PATRIMONIO', 4, 1, 1, '2026-01-04 05:42:36'),
(78, 1, '310300', 'Retiros y Dividendos', 'PATRIMONIO', 3, 0, 1, '2026-01-04 05:42:36'),
(79, 1, '310301', 'Cuenta Particular Socio A', 'PATRIMONIO', 4, 1, 1, '2026-01-04 05:42:36'),
(80, 1, '310302', 'Cuenta Particular Socio B', 'PATRIMONIO', 4, 1, 1, '2026-01-04 05:42:36'),
(81, 1, '400000', 'INGRESOS', 'INGRESO', 1, 0, 1, '2026-01-04 05:42:36'),
(82, 1, '410000', 'Ingresos de Explotación', 'INGRESO', 2, 0, 1, '2026-01-04 05:42:36'),
(83, 1, '410101', 'Ventas Netas (Afectas)', 'INGRESO', 4, 1, 1, '2026-01-04 05:42:36'),
(84, 1, '410102', 'Ventas Exentas', 'INGRESO', 4, 1, 1, '2026-01-04 05:42:36'),
(85, 1, '410103', 'Servicios Prestados', 'INGRESO', 4, 1, 1, '2026-01-04 05:42:36'),
(86, 1, '410104', 'Exportaciones', 'INGRESO', 4, 1, 1, '2026-01-04 05:42:36'),
(87, 1, '420000', 'Otros Ingresos (No Operacionales)', 'INGRESO', 2, 0, 1, '2026-01-04 05:42:36'),
(88, 1, '420101', 'Intereses Ganados (Bancarios)', 'INGRESO', 4, 1, 1, '2026-01-04 05:42:36'),
(89, 1, '420102', 'Diferencia de Cambio (Ganancia)', 'INGRESO', 4, 1, 1, '2026-01-04 05:42:36'),
(90, 1, '420103', 'Venta de Activo Fijo (Ganancia)', 'INGRESO', 4, 1, 1, '2026-01-04 05:42:36'),
(91, 1, '420104', 'Descuentos Obtenidos', 'INGRESO', 4, 1, 1, '2026-01-04 05:42:36'),
(92, 1, '500000', 'COSTOS DE VENTA', 'GASTO', 1, 0, 1, '2026-01-04 05:42:36'),
(93, 1, '510000', 'Costo Directo', 'GASTO', 2, 0, 1, '2026-01-04 05:42:36'),
(94, 1, '500001', 'Costo de Mercadería Vendida', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(95, 1, '510101', 'Costo de Materias Primas', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(96, 1, '510102', 'Mano de Obra Directa', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(97, 1, '510103', 'Costos Indirectos de Fabricación', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(98, 1, '510104', 'Costo por Servicios Subcontratados', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(99, 1, '600000', 'GASTOS OPERACIONALES', 'GASTO', 1, 0, 1, '2026-01-04 05:42:36'),
(100, 1, '610000', 'Gastos de Personal', 'GASTO', 2, 0, 1, '2026-01-04 05:42:36'),
(101, 1, '610101', 'Sueldos Base', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(102, 1, '610102', 'Comisiones de Venta', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(103, 1, '610103', 'Gratificaciones', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(104, 1, '610104', 'Horas Extras', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(105, 1, '610105', 'Bonos y Aguinaldos', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(106, 1, '610106', 'Aporte Patronal (SIS/Cesantía)', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(107, 1, '610107', 'Colación y Movilización', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(108, 1, '610108', 'Indemnizaciones por Años de Servicio', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(109, 1, '610109', 'Capacitación (Sence)', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(110, 1, '610110', 'Ropa de Trabajo y EPP', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(111, 1, '610111', 'Sala Cuna', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(112, 1, '620000', 'Infraestructura y Oficina', 'GASTO', 2, 0, 1, '2026-01-04 05:42:36'),
(113, 1, '620101', 'Arriendo Oficina / Local', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(114, 1, '620102', 'Gastos Comunes', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(115, 1, '620103', 'Electricidad', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(116, 1, '620104', 'Agua Potable', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(117, 1, '620105', 'Gas y Calefacción', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(118, 1, '620106', 'Internet y Telefonía Fija', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(119, 1, '620107', 'Celulares Planes Empresa', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(120, 1, '620108', 'Artículos de Aseo y Limpieza', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(121, 1, '620109', 'Artículos de Librería y Oficina', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(122, 1, '620110', 'Seguridad y Alarmas', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(123, 1, '620111', 'Mantenimiento Oficina (Reparaciones)', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(124, 1, '630000', 'Tecnología e Informática', 'GASTO', 2, 0, 1, '2026-01-04 05:42:36'),
(125, 1, '630101', 'Licencias de Software (Microsoft, Adobe)', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(126, 1, '630102', 'Servicios Cloud (AWS, Azure, Google)', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(127, 1, '630103', 'Hosting y Dominios', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(128, 1, '630104', 'Soporte Informático Externo', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(129, 1, '630105', 'Insumos Computacionales (Toner, Cables)', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(130, 1, '640000', 'Marketing y Ventas', 'GASTO', 2, 0, 1, '2026-01-04 05:42:36'),
(131, 1, '640101', 'Publicidad Digital (Google/Meta Ads)', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(132, 1, '640102', 'Agencia de Marketing / Community Manager', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(133, 1, '640103', 'Merchandising y Regalos Corp', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(134, 1, '640104', 'Gastos de Representación (Comidas)', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(135, 1, '640105', 'Viajes y Estadía', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(136, 1, '640106', 'Ferias y Eventos', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(137, 1, '650000', 'Vehículos y Logística', 'GASTO', 2, 0, 1, '2026-01-04 05:42:36'),
(138, 1, '650101', 'Combustibles y Lubricantes', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(139, 1, '650102', 'Peajes y Estacionamientos (TAG)', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(140, 1, '650103', 'Mantención y Reparación Vehículos', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(141, 1, '650104', 'Seguros de Vehículos', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(142, 1, '650105', 'Fletes y Despachos (Courier)', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(143, 1, '650106', 'Permisos de Circulación', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(144, 1, '660000', 'Servicios Profesionales', 'GASTO', 2, 0, 1, '2026-01-04 05:42:36'),
(145, 1, '660101', 'Servicio Contable', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(146, 1, '660102', 'Asesoría Legal (Abogados)', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(147, 1, '660103', 'Notaría y Trámites', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(148, 1, '660104', 'Consultorías Varias', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(149, 1, '670000', 'Impuestos y Patentes (Gasto)', 'GASTO', 2, 0, 1, '2026-01-04 05:42:36'),
(150, 1, '670101', 'Patente Municipal', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(151, 1, '670102', 'Contribuciones (Bienes Raíces)', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(152, 1, '670103', 'Multas e Intereses Fiscales', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(153, 1, '680000', 'Gastos Financieros', 'GASTO', 2, 0, 1, '2026-01-04 05:42:36'),
(154, 1, '680101', 'Intereses Bancarios', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(155, 1, '680102', 'Comisiones Bancarias (Mantención Cta)', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(156, 1, '680103', 'Comisiones Transbank / Webpay', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(157, 1, '680104', 'Diferencia de Cambio (Pérdida)', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(158, 1, '690000', 'Otros Gastos', 'GASTO', 2, 0, 1, '2026-01-04 05:42:36'),
(159, 1, '690101', 'Gastos Menores (Sin boleta/Vale)', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(160, 1, '690102', 'Suscripciones y Membresías', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(161, 1, '690103', 'Donaciones', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(162, 1, '690104', 'Mermas y Castigos', 'GASTO', 4, 1, 1, '2026-01-04 05:42:36'),
(163, 1, '690199', 'Compras por Clasificar (Cuenta Puente)', 'GASTO', 4, 1, 1, '2026-02-20 19:43:10'),
(164, 1, '120304', 'Deprec. Acum. Equipos Computacionales', 'ACTIVO', 4, 1, 1, '2026-02-21 22:53:38'),
(165, 1, '690105', 'Gasto por Depreciación de Activos', 'GASTO', 4, 1, 1, '2026-02-21 22:53:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  `codigo_interno` varchar(50) NOT NULL,
  `rut` varchar(20) DEFAULT NULL,
  `razon_social` varchar(150) NOT NULL,
  `pais_iso` char(2) NOT NULL,
  `moneda_defecto` char(3) NOT NULL,
  `region` varchar(100) DEFAULT NULL,
  `comuna` varchar(100) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email_contacto` varchar(100) DEFAULT NULL,
  `nombre_contacto` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id`, `empresa_id`, `codigo_interno`, `rut`, `razon_social`, `pais_iso`, `moneda_defecto`, `region`, `comuna`, `direccion`, `telefono`, `email_contacto`, `nombre_contacto`, `created_at`) VALUES
(1, 1, '1', '78.730.890-2', 'Procesadora Insuban Spa', 'CL', 'CLP', NULL, NULL, '', '', 'ncerdan@insuban.cl', 'Nestor Cerdan', '2026-01-05 02:58:39'),
(2, 1, '2', '79.882.360-4', 'Ingenieria Informatica Asociada Limitada (Tecnomas)', 'CL', 'CLP', NULL, NULL, '', '', '', '', '2026-02-20 19:10:31'),
(3, 1, '3', '76.457.436-2', 'Premium Hosting Solutions Spa', 'CL', 'CLP', NULL, NULL, '', '', '', '', '2026-02-20 19:12:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proyectos_activos`
--

CREATE TABLE `proyectos_activos` (
  `id_proyecto` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `tipo_activo_id` int(11) NOT NULL,
  `anio_fabricacion` int(11) DEFAULT NULL,
  `vida_util_meses` int(11) NOT NULL,
  `metodo_depreciacion` enum('LINEAL','ACELERADA') DEFAULT 'LINEAL',
  `centro_costo_id` int(11) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `estado` enum('EN_CONSTRUCCION','ACTIVO_OPERATIVO','VENDIDO','DADO_DE_BAJA') DEFAULT 'EN_CONSTRUCCION',
  `valor_total_original` decimal(15,2) DEFAULT 0.00,
  `depreciacion_acumulada` decimal(15,2) DEFAULT 0.00,
  `fecha_activacion` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proyectos_activos`
--

INSERT INTO `proyectos_activos` (`id_proyecto`, `empresa_id`, `nombre`, `tipo_activo_id`, `anio_fabricacion`, `vida_util_meses`, `metodo_depreciacion`, `centro_costo_id`, `empleado_id`, `estado`, `valor_total_original`, `depreciacion_acumulada`, `fecha_activacion`, `created_at`) VALUES
(1, 1, 'Prueba Compra', 33, 2026, 120, 'LINEAL', 1, 1, 'EN_CONSTRUCCION', 0.00, 0.00, NULL, '2026-03-05 12:36:40');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proyectos_facturas`
--

CREATE TABLE `proyectos_facturas` (
  `id` int(11) NOT NULL,
  `proyecto_id` int(11) NOT NULL,
  `factura_id` int(11) NOT NULL,
  `monto_imputado` decimal(15,2) NOT NULL,
  `fecha_vinculacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`) VALUES
(1, 'Administrador'),
(2, 'Contador'),
(3, 'Auditor');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sii_categorias_activos`
--

CREATE TABLE `sii_categorias_activos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `vida_util_normal` int(11) NOT NULL,
  `vida_util_acelerada` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `sii_categorias_activos`
--

INSERT INTO `sii_categorias_activos` (`id`, `nombre`, `vida_util_normal`, `vida_util_acelerada`) VALUES
(1, 'Edificios, casas y otras construcciones', 50, 16),
(2, 'Instalaciones en general', 10, 3),
(3, 'Camiones de uso general', 7, 2),
(4, 'Automóviles', 7, 2),
(5, 'Maquinarias y equipos en general', 15, 5),
(6, 'Muebles y enseres', 7, 2),
(7, 'Sistemas computacionales y periféricos', 6, 2),
(8, 'Herramientas livianas', 3, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sii_mapeo_cuentas`
--

CREATE TABLE `sii_mapeo_cuentas` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `codigo_cuenta` varchar(20) NOT NULL,
  `concepto_sii` enum('INGRESOS_DEL_GIRO','OTROS_INGRESOS','REMUNERACIONES_PAGADAS','HONORARIOS_PAGADOS','ARRIENDOS_PAGADOS','GASTOS_GENERALES') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sii_mapeo_cuentas`
--

INSERT INTO `sii_mapeo_cuentas` (`id`, `empresa_id`, `codigo_cuenta`, `concepto_sii`) VALUES
(1, 1, '410101', 'INGRESOS_DEL_GIRO'),
(2, 1, '410102', 'INGRESOS_DEL_GIRO'),
(3, 1, '410103', 'INGRESOS_DEL_GIRO'),
(4, 1, '420101', 'OTROS_INGRESOS'),
(5, 1, '610101', 'REMUNERACIONES_PAGADAS'),
(6, 1, '610105', 'REMUNERACIONES_PAGADAS'),
(7, 1, '660101', 'HONORARIOS_PAGADOS'),
(8, 1, '660102', 'HONORARIOS_PAGADOS'),
(9, 1, '620101', 'ARRIENDOS_PAGADOS'),
(10, 1, '620103', 'GASTOS_GENERALES'),
(11, 1, '620106', 'GASTOS_GENERALES');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `reset_token` varchar(10) DEFAULT NULL,
  `reset_expires_at` datetime DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `estado_suscripcion_id` int(11) NOT NULL DEFAULT 2,
  `fecha_fin_suscripcion` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `empresa_id`, `email`, `password`, `reset_token`, `reset_expires_at`, `nombre`, `rol_id`, `estado_suscripcion_id`, `fecha_fin_suscripcion`, `created_at`) VALUES
(1, 1, 'admin@erp.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'Super Admin', 1, 1, '2030-12-31', '2026-01-04 19:03:19');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `activos_fijos`
--
ALTER TABLE `activos_fijos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categoria_sii_id` (`categoria_sii_id`),
  ADD KEY `fk_activo_factura` (`factura_id`);

--
-- Indices de la tabla `asientos_contables`
--
ALTER TABLE `asientos_contables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_codigo_asiento` (`codigo_unico`),
  ADD KEY `fk_asiento_empresa` (`empresa_id`);

--
-- Indices de la tabla `auditoria_facturas`
--
ALTER TABLE `auditoria_facturas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_factura` (`id_factura`);

--
-- Indices de la tabla `catalogo_bancos`
--
ALTER TABLE `catalogo_bancos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `catalogo_plan_maestro`
--
ALTER TABLE `catalogo_plan_maestro`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_codigo_maestro` (`codigo`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_rut_empresa` (`rut`,`empresa_id`),
  ADD KEY `FK_cliente_empresa` (`empresa_id`);

--
-- Indices de la tabla `configuracion_secuencias`
--
ALTER TABLE `configuracion_secuencias`
  ADD PRIMARY KEY (`empresa_id`,`entidad`);

--
-- Indices de la tabla `cotizaciones`
--
ALTER TABLE `cotizaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_cotizacion_estado` (`estado_id`),
  ADD KEY `FK_cotizacion_empresa` (`empresa_id`),
  ADD KEY `FK_cotizacion_cliente` (`cliente_id`);

--
-- Indices de la tabla `cotizacion_detalles`
--
ALTER TABLE `cotizacion_detalles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_detalle_cotizacion` (`cotizacion_id`);

--
-- Indices de la tabla `cuentas_bancarias_empresa`
--
ALTER TABLE `cuentas_bancarias_empresa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cuenta_empresa` (`empresa_id`);

--
-- Indices de la tabla `cuentas_bancarias_proveedores`
--
ALTER TABLE `cuentas_bancarias_proveedores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proveedor_id` (`proveedor_id`);

--
-- Indices de la tabla `depreciaciones_mensuales`
--
ALTER TABLE `depreciaciones_mensuales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_deprec_proyecto` (`proyecto_id`),
  ADD KEY `fk_deprec_asiento` (`asiento_id`);

--
-- Indices de la tabla `detalles_asiento`
--
ALTER TABLE `detalles_asiento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asiento_id` (`asiento_id`);

--
-- Indices de la tabla `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `estados_suscripcion`
--
ALTER TABLE `estados_suscripcion`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `estado_cotizaciones`
--
ALTER TABLE `estado_cotizaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `facturas`
--
ALTER TABLE `facturas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_unico` (`codigo_unico`),
  ADD UNIQUE KEY `unique_factura_proveedor` (`proveedor_id`,`numero_factura`),
  ADD KEY `cuenta_bancaria_id` (`cuenta_bancaria_id`),
  ADD KEY `fk_fact_empresa` (`empresa_id`);

--
-- Indices de la tabla `pagos_facturas`
--
ALTER TABLE `pagos_facturas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `factura_id` (`factura_id`),
  ADD KEY `cuenta_bancaria_empresa_id` (`cuenta_bancaria_empresa_id`),
  ADD KEY `asiento_id` (`asiento_id`);

--
-- Indices de la tabla `paises`
--
ALTER TABLE `paises`
  ADD PRIMARY KEY (`iso`);

--
-- Indices de la tabla `plan_cuentas`
--
ALTER TABLE `plan_cuentas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `fk_puc_empresa` (`empresa_id`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_interno` (`codigo_interno`),
  ADD KEY `fk_prov_empresa` (`empresa_id`);

--
-- Indices de la tabla `proyectos_activos`
--
ALTER TABLE `proyectos_activos`
  ADD PRIMARY KEY (`id_proyecto`),
  ADD KEY `fk_proyecto_empresa` (`empresa_id`);

--
-- Indices de la tabla `proyectos_facturas`
--
ALTER TABLE `proyectos_facturas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pf_proyecto` (`proyecto_id`),
  ADD KEY `fk_pf_factura` (`factura_id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `sii_categorias_activos`
--
ALTER TABLE `sii_categorias_activos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `sii_mapeo_cuentas`
--
ALTER TABLE `sii_mapeo_cuentas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_mapeo` (`empresa_id`,`codigo_cuenta`),
  ADD KEY `fk_mapeo_cuenta` (`codigo_cuenta`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_user_rol` (`rol_id`),
  ADD KEY `fk_user_estado` (`estado_suscripcion_id`),
  ADD KEY `fk_usuario_empresa` (`empresa_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `activos_fijos`
--
ALTER TABLE `activos_fijos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `asientos_contables`
--
ALTER TABLE `asientos_contables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `auditoria_facturas`
--
ALTER TABLE `auditoria_facturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `catalogo_bancos`
--
ALTER TABLE `catalogo_bancos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `catalogo_plan_maestro`
--
ALTER TABLE `catalogo_plan_maestro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=164;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `cotizaciones`
--
ALTER TABLE `cotizaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `cotizacion_detalles`
--
ALTER TABLE `cotizacion_detalles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `cuentas_bancarias_empresa`
--
ALTER TABLE `cuentas_bancarias_empresa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `cuentas_bancarias_proveedores`
--
ALTER TABLE `cuentas_bancarias_proveedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `depreciaciones_mensuales`
--
ALTER TABLE `depreciaciones_mensuales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalles_asiento`
--
ALTER TABLE `detalles_asiento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT de la tabla `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `estados_suscripcion`
--
ALTER TABLE `estados_suscripcion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `estado_cotizaciones`
--
ALTER TABLE `estado_cotizaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `facturas`
--
ALTER TABLE `facturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `pagos_facturas`
--
ALTER TABLE `pagos_facturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `plan_cuentas`
--
ALTER TABLE `plan_cuentas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=166;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `proyectos_activos`
--
ALTER TABLE `proyectos_activos`
  MODIFY `id_proyecto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `proyectos_facturas`
--
ALTER TABLE `proyectos_facturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `sii_categorias_activos`
--
ALTER TABLE `sii_categorias_activos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `sii_mapeo_cuentas`
--
ALTER TABLE `sii_mapeo_cuentas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `activos_fijos`
--
ALTER TABLE `activos_fijos`
  ADD CONSTRAINT `activos_fijos_ibfk_1` FOREIGN KEY (`categoria_sii_id`) REFERENCES `sii_categorias_activos` (`id`),
  ADD CONSTRAINT `fk_activo_factura` FOREIGN KEY (`factura_id`) REFERENCES `facturas` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `asientos_contables`
--
ALTER TABLE `asientos_contables`
  ADD CONSTRAINT `fk_asiento_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `auditoria_facturas`
--
ALTER TABLE `auditoria_facturas`
  ADD CONSTRAINT `auditoria_facturas_ibfk_1` FOREIGN KEY (`id_factura`) REFERENCES `facturas` (`id`);

--
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `FK_cliente_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);

--
-- Filtros para la tabla `configuracion_secuencias`
--
ALTER TABLE `configuracion_secuencias`
  ADD CONSTRAINT `fk_secuencia_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cotizaciones`
--
ALTER TABLE `cotizaciones`
  ADD CONSTRAINT `FK_cotizacion_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `FK_cotizacion_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  ADD CONSTRAINT `FK_cotizacion_estado` FOREIGN KEY (`estado_id`) REFERENCES `estado_cotizaciones` (`id`);

--
-- Filtros para la tabla `cotizacion_detalles`
--
ALTER TABLE `cotizacion_detalles`
  ADD CONSTRAINT `FK_detalle_cotizacion` FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cuentas_bancarias_empresa`
--
ALTER TABLE `cuentas_bancarias_empresa`
  ADD CONSTRAINT `fk_cuenta_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cuentas_bancarias_proveedores`
--
ALTER TABLE `cuentas_bancarias_proveedores`
  ADD CONSTRAINT `cuentas_bancarias_proveedores_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `depreciaciones_mensuales`
--
ALTER TABLE `depreciaciones_mensuales`
  ADD CONSTRAINT `fk_deprec_asiento` FOREIGN KEY (`asiento_id`) REFERENCES `asientos_contables` (`id`),
  ADD CONSTRAINT `fk_deprec_proyecto` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos_activos` (`id_proyecto`);

--
-- Filtros para la tabla `detalles_asiento`
--
ALTER TABLE `detalles_asiento`
  ADD CONSTRAINT `detalles_asiento_ibfk_1` FOREIGN KEY (`asiento_id`) REFERENCES `asientos_contables` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `facturas`
--
ALTER TABLE `facturas`
  ADD CONSTRAINT `facturas_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  ADD CONSTRAINT `facturas_ibfk_2` FOREIGN KEY (`cuenta_bancaria_id`) REFERENCES `cuentas_bancarias_proveedores` (`id`),
  ADD CONSTRAINT `fk_fact_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `pagos_facturas`
--
ALTER TABLE `pagos_facturas`
  ADD CONSTRAINT `pagos_facturas_ibfk_1` FOREIGN KEY (`factura_id`) REFERENCES `facturas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pagos_facturas_ibfk_2` FOREIGN KEY (`cuenta_bancaria_empresa_id`) REFERENCES `cuentas_bancarias_empresa` (`id`),
  ADD CONSTRAINT `pagos_facturas_ibfk_3` FOREIGN KEY (`asiento_id`) REFERENCES `asientos_contables` (`id`);

--
-- Filtros para la tabla `plan_cuentas`
--
ALTER TABLE `plan_cuentas`
  ADD CONSTRAINT `fk_puc_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD CONSTRAINT `fk_prov_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `proyectos_activos`
--
ALTER TABLE `proyectos_activos`
  ADD CONSTRAINT `fk_proyecto_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);

--
-- Filtros para la tabla `proyectos_facturas`
--
ALTER TABLE `proyectos_facturas`
  ADD CONSTRAINT `fk_pf_factura` FOREIGN KEY (`factura_id`) REFERENCES `facturas` (`id`),
  ADD CONSTRAINT `fk_pf_proyecto` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos_activos` (`id_proyecto`);

--
-- Filtros para la tabla `sii_mapeo_cuentas`
--
ALTER TABLE `sii_mapeo_cuentas`
  ADD CONSTRAINT `fk_mapeo_cuenta` FOREIGN KEY (`codigo_cuenta`) REFERENCES `plan_cuentas` (`codigo`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mapeo_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_user_estado` FOREIGN KEY (`estado_suscripcion_id`) REFERENCES `estados_suscripcion` (`id`),
  ADD CONSTRAINT `fk_user_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `fk_usuario_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
