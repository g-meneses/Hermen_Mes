-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 03-01-2026 a las 02:59:42
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
-- Base de datos: `mes_hermen`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areas_produccion`
--

CREATE TABLE `areas_produccion` (
  `id_area` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `responsable` varchar(100) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias_inventario`
--

CREATE TABLE `categorias_inventario` (
  `id_categoria` int(11) NOT NULL,
  `id_tipo_inventario` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `orden` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `colores`
--

CREATE TABLE `colores` (
  `id_color` int(11) NOT NULL,
  `codigo_color` varchar(20) NOT NULL,
  `nombre_color` varchar(50) NOT NULL,
  `codigo_hex` varchar(7) DEFAULT NULL,
  `orden` int(11) DEFAULT 0,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_flujos`
--

CREATE TABLE `detalle_flujos` (
  `id_detalle_flujo` int(11) NOT NULL,
  `id_flujo` int(11) NOT NULL,
  `id_etapa` int(11) NOT NULL,
  `orden_secuencia` int(11) NOT NULL,
  `es_obligatoria` tinyint(1) DEFAULT 1,
  `permite_salto` tinyint(1) DEFAULT 0,
  `etapa_alternativa` int(11) DEFAULT NULL,
  `tiempo_estimado_minutos` int(11) DEFAULT NULL,
  `requiere_inspeccion` tinyint(1) DEFAULT 0,
  `observaciones` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_plan_generico`
--

CREATE TABLE `detalle_plan_generico` (
  `id_detalle_generico` int(11) NOT NULL,
  `id_plan_generico` int(11) NOT NULL,
  `id_maquina` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `accion` enum('mantener','cambiar','parar') NOT NULL,
  `producto_nuevo` int(11) DEFAULT NULL,
  `cantidad_objetivo_docenas` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_produccion_tejeduria`
--

CREATE TABLE `detalle_produccion_tejeduria` (
  `id_detalle` int(11) NOT NULL,
  `id_produccion` int(11) NOT NULL,
  `id_maquina` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `docenas` int(11) NOT NULL DEFAULT 0,
  `unidades` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_produccion_vaporizado`
--

CREATE TABLE `detalle_produccion_vaporizado` (
  `id_detalle_vaporizado` int(11) NOT NULL,
  `id_produccion_vaporizado` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `docenas_vaporizadas` int(11) NOT NULL DEFAULT 0,
  `unidades_vaporizadas` int(11) NOT NULL DEFAULT 0,
  `calidad` enum('primera','segunda','rechazado') DEFAULT 'primera',
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_revisado_crudo`
--

CREATE TABLE `detalle_revisado_crudo` (
  `id_detalle_revisado` int(11) NOT NULL,
  `id_revisado` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `docenas_primera` int(11) NOT NULL DEFAULT 0,
  `unidades_primera` int(11) NOT NULL DEFAULT 0,
  `docenas_segunda` int(11) NOT NULL DEFAULT 0,
  `unidades_segunda` int(11) NOT NULL DEFAULT 0,
  `docenas_defecto` int(11) NOT NULL DEFAULT 0,
  `unidades_defecto` int(11) NOT NULL DEFAULT 0,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disenos`
--

CREATE TABLE `disenos` (
  `id_diseno` int(11) NOT NULL,
  `nombre_diseno` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos_inventario`
--

CREATE TABLE `documentos_inventario` (
  `id_documento` int(11) NOT NULL,
  `tipo_documento` enum('INGRESO','SALIDA','AJUSTE','TRANSFERENCIA') NOT NULL,
  `tipo_salida` varchar(50) DEFAULT NULL,
  `tipo_ingreso` varchar(50) DEFAULT NULL,
  `id_tipo_ingreso` int(11) DEFAULT NULL COMMENT 'FK a tipos_ingreso',
  `numero_documento` varchar(30) NOT NULL,
  `fecha_documento` date NOT NULL,
  `id_tipo_inventario` int(11) NOT NULL COMMENT 'FK a tipos_inventario (1=Materias Primas)',
  `id_proveedor` int(11) DEFAULT NULL COMMENT 'Solo para ingresos',
  `id_area_produccion` int(11) DEFAULT NULL COMMENT 'Para devoluciones de producción',
  `id_documento_origen` int(11) DEFAULT NULL COMMENT 'ID del documento de ingreso original (para devoluciones)',
  `id_destino` int(11) DEFAULT NULL COMMENT 'Para salidas: área de producción',
  `referencia_externa` varchar(50) DEFAULT NULL COMMENT 'Nº Factura, Guía, etc.',
  `motivo_ingreso` varchar(200) DEFAULT NULL COMMENT 'Motivo del ingreso (devolución, ajuste)',
  `ubicacion_almacen` varchar(100) DEFAULT NULL COMMENT 'Ubicación física (para inventario inicial)',
  `con_factura` tinyint(1) DEFAULT 0,
  `moneda` enum('BOB','USD') DEFAULT 'BOB',
  `tipo_cambio` decimal(10,4) DEFAULT 1.0000,
  `subtotal` decimal(14,4) DEFAULT 0.0000,
  `iva` decimal(14,4) DEFAULT 0.0000,
  `total` decimal(14,4) DEFAULT 0.0000,
  `observaciones` text DEFAULT NULL,
  `estado` enum('BORRADOR','CONFIRMADO','ANULADO') DEFAULT 'CONFIRMADO',
  `fecha_anulacion` datetime DEFAULT NULL,
  `motivo_anulacion` varchar(255) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `autorizado_por` int(11) DEFAULT NULL COMMENT 'Usuario que autorizó (para ajustes)',
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos_inventario_detalle`
--

CREATE TABLE `documentos_inventario_detalle` (
  `id_detalle` int(11) NOT NULL,
  `id_documento` int(11) NOT NULL,
  `id_inventario` int(11) NOT NULL COMMENT 'FK a inventarios',
  `id_detalle_origen` int(11) DEFAULT NULL COMMENT 'ID de la línea de ingreso original',
  `cantidad` decimal(12,4) NOT NULL,
  `cantidad_original` decimal(10,2) DEFAULT NULL COMMENT 'Cantidad comprada originalmente',
  `id_unidad` int(11) DEFAULT NULL,
  `costo_unitario` decimal(14,4) NOT NULL COMMENT 'Costo sin IVA',
  `costo_adquisicion` decimal(10,4) DEFAULT NULL COMMENT 'Costo de adquisición original (sin IVA)',
  `tenia_iva` tinyint(1) DEFAULT 0 COMMENT 'Si la compra original tenía IVA',
  `costo_con_iva` decimal(14,4) DEFAULT NULL COMMENT 'Costo con IVA (si aplica)',
  `subtotal` decimal(14,4) NOT NULL,
  `lote` varchar(50) DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `etapas_produccion`
--

CREATE TABLE `etapas_produccion` (
  `id_etapa` int(11) NOT NULL,
  `codigo_etapa` varchar(30) NOT NULL,
  `nombre_etapa` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `area_responsable` varchar(50) DEFAULT NULL,
  `tipo_etapa` enum('PRODUCCION','CONTROL_CALIDAD','PROCESO','ENSAMBLE','ACABADO','ALMACEN') NOT NULL DEFAULT 'PRODUCCION',
  `requiere_maquina` tinyint(1) DEFAULT 0,
  `requiere_operario` tinyint(1) DEFAULT 1,
  `permite_rechazo` tinyint(1) DEFAULT 1,
  `afecta_inventario_wip` tinyint(1) DEFAULT 1,
  `icono` varchar(50) DEFAULT 'fa-cog',
  `color_badge` varchar(20) DEFAULT '#6c757d',
  `orden_visual` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `flujos_produccion`
--

CREATE TABLE `flujos_produccion` (
  `id_flujo` int(11) NOT NULL,
  `codigo_flujo` varchar(30) NOT NULL,
  `nombre_flujo` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `linea_produccion` enum('POLIAMIDA','ALGODON','CONFECCION') NOT NULL,
  `tipo_flujo` varchar(50) DEFAULT NULL,
  `total_etapas` int(11) DEFAULT 0,
  `tiempo_estimado_horas` decimal(8,2) DEFAULT NULL,
  `requiere_corte` tinyint(1) DEFAULT 0,
  `requiere_ensamble` tinyint(1) DEFAULT 0,
  `requiere_tenido` tinyint(1) DEFAULT 1,
  `diagrama_url` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `insumos`
--

CREATE TABLE `insumos` (
  `id_insumo` int(11) NOT NULL,
  `codigo_insumo` varchar(30) NOT NULL,
  `id_tipo_insumo` int(11) NOT NULL,
  `nombre_insumo` varchar(100) NOT NULL,
  `tipo_insumo` enum('HILO_POLIAMIDA','LYCRA','AUXILIAR_QUIMICO','OTRO') NOT NULL DEFAULT 'HILO_POLIAMIDA',
  `descripcion` text DEFAULT NULL,
  `unidad_medida` enum('gramos','kilogramos','metros','unidades') DEFAULT 'gramos',
  `stock_minimo` decimal(10,2) DEFAULT NULL,
  `stock_actual` decimal(10,2) DEFAULT 0.00,
  `precio_unitario` decimal(10,2) DEFAULT NULL,
  `proveedor` varchar(100) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `costo_unitario` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventarios`
--

CREATE TABLE `inventarios` (
  `id_inventario` int(11) NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `id_tipo_inventario` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `id_subcategoria` int(11) DEFAULT NULL,
  `id_linea_produccion` int(11) DEFAULT NULL,
  `id_unidad` int(11) NOT NULL,
  `stock_actual` decimal(12,2) DEFAULT 0.00,
  `stock_minimo` decimal(12,2) DEFAULT 0.00,
  `stock_maximo` decimal(12,2) DEFAULT NULL,
  `id_ubicacion` int(11) DEFAULT NULL,
  `costo_unitario` decimal(12,4) DEFAULT 0.0000,
  `costo_promedio` decimal(12,4) DEFAULT 0.0000,
  `precio_venta` decimal(12,2) DEFAULT NULL,
  `requiere_lote` tinyint(1) DEFAULT 0,
  `es_inventariable` tinyint(1) DEFAULT 1,
  `punto_reorden` decimal(12,2) DEFAULT NULL,
  `talla` varchar(20) DEFAULT NULL,
  `id_color` int(11) DEFAULT NULL,
  `proveedor_principal` varchar(100) DEFAULT NULL,
  `especificaciones` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `creado_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario_intermedio`
--

CREATE TABLE `inventario_intermedio` (
  `id_inventario` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL COMMENT 'FK a productos_tejidos',
  `tipo_inventario` enum('tejido','revisado','vaporizado','preteñido','teñido') NOT NULL,
  `docenas` int(11) NOT NULL DEFAULT 0 COMMENT 'Cantidad en docenas',
  `unidades` int(11) NOT NULL DEFAULT 0 COMMENT 'Unidades sueltas (0-11)',
  `total_unidades_calculado` int(11) GENERATED ALWAYS AS (`docenas` * 12 + `unidades`) STORED COMMENT 'Total en unidades',
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Última actualización'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario_materia_prima`
--

CREATE TABLE `inventario_materia_prima` (
  `id_inventario_mp` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `codigo_item` varchar(50) NOT NULL,
  `nombre_item` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `unidad_medida` varchar(20) DEFAULT 'kilogramos',
  `stock_actual` decimal(12,3) DEFAULT 0.000,
  `stock_minimo` decimal(12,3) DEFAULT 0.000,
  `stock_maximo` decimal(12,3) DEFAULT 0.000,
  `costo_unitario` decimal(12,4) DEFAULT 0.0000,
  `costo_promedio` decimal(12,4) DEFAULT 0.0000,
  `id_ubicacion` int(11) DEFAULT NULL,
  `lote_actual` varchar(50) DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario_productos_terminados`
--

CREATE TABLE `inventario_productos_terminados` (
  `id_inventario_pt` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `id_producto_tejido` int(11) NOT NULL,
  `id_color` int(11) NOT NULL,
  `talla` varchar(20) NOT NULL,
  `codigo_completo` varchar(100) NOT NULL,
  `stock_docenas` decimal(10,2) DEFAULT 0.00,
  `stock_unidades` int(11) DEFAULT 0,
  `stock_total_unidades` int(11) DEFAULT 0,
  `costo_promedio_docena` decimal(12,4) DEFAULT 0.0000,
  `costo_promedio_unidad` decimal(12,4) DEFAULT 0.0000,
  `precio_venta_docena` decimal(12,2) DEFAULT 0.00,
  `precio_venta_unidad` decimal(12,2) DEFAULT 0.00,
  `id_ubicacion` int(11) DEFAULT NULL,
  `calidad` varchar(20) DEFAULT 'Primera',
  `observaciones` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `kardex_inventario`
--

CREATE TABLE `kardex_inventario` (
  `id_kardex` int(11) NOT NULL,
  `id_inventario` int(11) NOT NULL,
  `fecha_movimiento` datetime NOT NULL,
  `tipo_movimiento` enum('ENTRADA','SALIDA','AJUSTE_POSITIVO','AJUSTE_NEGATIVO') NOT NULL,
  `id_documento` int(11) DEFAULT NULL,
  `documento_referencia` varchar(50) DEFAULT NULL,
  `cantidad` decimal(12,4) NOT NULL,
  `costo_unitario` decimal(14,4) NOT NULL,
  `costo_total` decimal(14,4) NOT NULL,
  `stock_anterior` decimal(12,4) NOT NULL,
  `stock_posterior` decimal(12,4) NOT NULL,
  `costo_promedio_anterior` decimal(14,4) DEFAULT NULL,
  `costo_promedio_posterior` decimal(14,4) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `kardex_valorado`
--

CREATE TABLE `kardex_valorado` (
  `id_kardex` int(11) NOT NULL,
  `id_movimiento` int(11) NOT NULL,
  `tipo_inventario` varchar(10) NOT NULL,
  `id_item` int(11) NOT NULL,
  `fecha_movimiento` datetime NOT NULL,
  `tipo_movimiento` varchar(20) NOT NULL,
  `cantidad_entrada` decimal(12,3) DEFAULT 0.000,
  `costo_unitario_entrada` decimal(12,4) DEFAULT 0.0000,
  `costo_total_entrada` decimal(14,2) DEFAULT 0.00,
  `cantidad_salida` decimal(12,3) DEFAULT 0.000,
  `costo_unitario_salida` decimal(12,4) DEFAULT 0.0000,
  `costo_total_salida` decimal(14,2) DEFAULT 0.00,
  `saldo_cantidad` decimal(12,3) NOT NULL,
  `costo_unitario_saldo` decimal(12,4) NOT NULL,
  `saldo_total` decimal(14,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lineas_produccion_erp`
--

CREATE TABLE `lineas_produccion_erp` (
  `id_linea_produccion` int(11) NOT NULL,
  `codigo` varchar(10) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lineas_producto`
--

CREATE TABLE `lineas_producto` (
  `id_linea` int(11) NOT NULL,
  `codigo_linea` varchar(20) NOT NULL,
  `nombre_linea` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lotes_inventario`
--

CREATE TABLE `lotes_inventario` (
  `id_lote` int(11) NOT NULL,
  `id_inventario` int(11) NOT NULL,
  `numero_lote` varchar(50) NOT NULL,
  `fecha_ingreso` date NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `cantidad_inicial` decimal(12,2) NOT NULL,
  `cantidad_actual` decimal(12,2) NOT NULL,
  `costo_unitario` decimal(12,4) DEFAULT 0.0000,
  `proveedor` varchar(100) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lotes_produccion`
--

CREATE TABLE `lotes_produccion` (
  `id_lote` int(11) NOT NULL,
  `codigo_lote` varchar(30) NOT NULL,
  `id_plan` int(11) NOT NULL,
  `dia_programado` date NOT NULL,
  `id_producto` int(11) NOT NULL,
  `talla` varchar(20) NOT NULL,
  `color` varchar(50) NOT NULL,
  `cantidad_docenas` int(11) NOT NULL,
  `cantidad_unidades` int(11) DEFAULT 0,
  `estado` enum('programado','en_tejido','en_proceso','completado','cancelado') DEFAULT 'programado',
  `prioridad` enum('baja','media','alta','urgente') DEFAULT 'media',
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `maquinas`
--

CREATE TABLE `maquinas` (
  `id_maquina` int(11) NOT NULL,
  `numero_maquina` varchar(10) NOT NULL,
  `descripcion` varchar(100) DEFAULT NULL,
  `diametro_pulgadas` decimal(3,1) DEFAULT 4.0,
  `numero_agujas` int(11) DEFAULT 400,
  `estado` enum('operativa','mantenimiento','inactiva','sin_asignacion','sin_repuestos') DEFAULT 'operativa',
  `ubicacion` varchar(50) DEFAULT NULL,
  `fecha_instalacion` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_inventario`
--

CREATE TABLE `movimientos_inventario` (
  `id_movimiento` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL COMMENT 'FK a productos_tejidos',
  `tipo_movimiento` enum('entrada','salida') NOT NULL COMMENT 'Entrada o salida',
  `tipo_inventario` enum('tejido','revisado','vaporizado','preteñido','teñido') NOT NULL,
  `docenas` int(11) NOT NULL COMMENT 'Docenas movidas',
  `unidades` int(11) NOT NULL COMMENT 'Unidades sueltas (0-11)',
  `total_unidades_calculado` int(11) NOT NULL COMMENT 'Total en unidades',
  `origen` varchar(100) DEFAULT NULL COMMENT 'Descripción del origen',
  `destino` varchar(100) DEFAULT NULL COMMENT 'Descripción del destino',
  `id_produccion` int(11) DEFAULT NULL COMMENT 'FK si viene de producción',
  `id_usuario` int(11) NOT NULL COMMENT 'Usuario que registró el movimiento',
  `fecha_movimiento` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha y hora del movimiento',
  `observaciones` text DEFAULT NULL COMMENT 'Observaciones adicionales'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_inventario_erp`
--

CREATE TABLE `movimientos_inventario_erp` (
  `id_movimiento` int(11) NOT NULL,
  `id_inventario` int(11) NOT NULL,
  `fecha_movimiento` timestamp NOT NULL DEFAULT current_timestamp(),
  `tipo_movimiento` enum('ENTRADA_COMPRA','ENTRADA_PRODUCCION','ENTRADA_AJUSTE','ENTRADA_DEVOLUCION','ENTRADA_INICIAL','SALIDA_PRODUCCION','SALIDA_VENTA','SALIDA_AJUSTE','SALIDA_MERMA','SALIDA_MUESTRA','TRANSFERENCIA_ENTRADA','TRANSFERENCIA_SALIDA') NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `stock_anterior` decimal(12,2) NOT NULL,
  `stock_nuevo` decimal(12,2) NOT NULL,
  `costo_unitario` decimal(12,4) DEFAULT 0.0000,
  `costo_total` decimal(12,2) DEFAULT 0.00,
  `costo_promedio_resultado` decimal(15,4) DEFAULT NULL,
  `id_ubicacion_origen` int(11) DEFAULT NULL,
  `id_ubicacion_destino` int(11) DEFAULT NULL,
  `documento_tipo` varchar(50) DEFAULT NULL,
  `documento_numero` varchar(50) DEFAULT NULL,
  `documento_id` int(11) DEFAULT NULL,
  `numero_lote` varchar(50) DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` enum('ACTIVO','ANULADO') DEFAULT 'ACTIVO',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_proveedor` int(11) DEFAULT NULL,
  `documento_referencia` varchar(50) DEFAULT NULL,
  `es_devolucion` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_inventario_general`
--

CREATE TABLE `movimientos_inventario_general` (
  `id_movimiento` int(11) NOT NULL,
  `tipo_inventario` varchar(10) NOT NULL,
  `id_item` int(11) NOT NULL,
  `tipo_movimiento` varchar(20) NOT NULL,
  `tipo_documento` varchar(20) NOT NULL,
  `numero_documento` varchar(50) DEFAULT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `unidad_medida` varchar(20) DEFAULT NULL,
  `costo_unitario` decimal(12,4) DEFAULT NULL,
  `costo_total` decimal(14,2) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `id_ubicacion_origen` int(11) DEFAULT NULL,
  `id_ubicacion_destino` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_movimiento` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `planes_semanales`
--

CREATE TABLE `planes_semanales` (
  `id_plan` int(11) NOT NULL,
  `codigo_plan` varchar(20) NOT NULL,
  `semana` int(11) NOT NULL,
  `anio` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `estado` enum('borrador','aprobado','en_proceso','completado','cancelado') DEFAULT 'borrador',
  `observaciones` text DEFAULT NULL,
  `usuario_creacion` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_aprobacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plan_generico_tejido`
--

CREATE TABLE `plan_generico_tejido` (
  `id_plan_generico` int(11) NOT NULL,
  `codigo_plan_generico` varchar(30) NOT NULL,
  `fecha_vigencia_inicio` date NOT NULL,
  `fecha_vigencia_fin` date DEFAULT NULL,
  `estado` enum('vigente','historico') DEFAULT 'vigente',
  `observaciones` text DEFAULT NULL,
  `usuario_creacion` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_aprobacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `produccion_revisado_crudo`
--

CREATE TABLE `produccion_revisado_crudo` (
  `id_revisado` int(11) NOT NULL,
  `fecha_revisado` date NOT NULL,
  `id_turno` int(11) NOT NULL,
  `id_revisor` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `produccion_tejeduria`
--

CREATE TABLE `produccion_tejeduria` (
  `id_produccion` int(11) NOT NULL,
  `codigo_lote` varchar(50) NOT NULL,
  `fecha_produccion` date NOT NULL,
  `id_turno` int(11) NOT NULL,
  `id_tejedor` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_flujos`
--

CREATE TABLE `productos_flujos` (
  `id_producto_flujo` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL COMMENT 'Referencia a productos_tejidos.id_producto',
  `id_flujo` int(11) NOT NULL COMMENT 'Referencia a flujos_produccion.id_flujo',
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `activo` tinyint(1) DEFAULT 1 COMMENT '1=vigente, 0=histórico',
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Asignación de flujos de producción a productos';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_tejidos`
--

CREATE TABLE `productos_tejidos` (
  `id_producto` int(11) NOT NULL,
  `codigo_producto` varchar(30) NOT NULL,
  `id_linea` int(11) NOT NULL,
  `id_tipo_producto` int(11) NOT NULL,
  `id_diseno` int(11) NOT NULL,
  `talla` varchar(20) NOT NULL,
  `descripcion_completa` varchar(200) DEFAULT NULL,
  `peso_promedio_docena` decimal(8,2) DEFAULT NULL,
  `tiempo_estimado_docena` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto_insumos`
--

CREATE TABLE `producto_insumos` (
  `id_producto_insumo` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `id_insumo` int(11) NOT NULL,
  `cantidad_por_docena` decimal(10,3) NOT NULL,
  `es_principal` tinyint(1) DEFAULT 0,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id_proveedor` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `razon_social` varchar(150) NOT NULL,
  `nombre_comercial` varchar(100) DEFAULT NULL,
  `tipo` enum('LOCAL','IMPORTACION') NOT NULL DEFAULT 'LOCAL',
  `nit` varchar(20) DEFAULT NULL,
  `nombre_contacto` varchar(100) DEFAULT NULL,
  `contacto_telefono` varchar(30) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `ciudad` varchar(50) DEFAULT NULL,
  `pais` varchar(50) DEFAULT 'Bolivia',
  `moneda` enum('BOB','USD') NOT NULL DEFAULT 'BOB',
  `condicion_pago` varchar(50) DEFAULT 'Contado',
  `dias_credito` int(11) DEFAULT 0,
  `observaciones` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `secuencias_documento`
--

CREATE TABLE `secuencias_documento` (
  `id_secuencia` int(11) NOT NULL,
  `tipo_documento` varchar(20) NOT NULL,
  `subtipo` varchar(50) DEFAULT NULL,
  `prefijo` varchar(20) NOT NULL,
  `anio` int(4) NOT NULL,
  `mes` int(2) NOT NULL,
  `ultimo_numero` int(6) NOT NULL DEFAULT 0,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `subcategorias_inventario`
--

CREATE TABLE `subcategorias_inventario` (
  `id_subcategoria` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `orden` int(11) DEFAULT 1,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_ingreso`
--

CREATE TABLE `tipos_ingreso` (
  `id_tipo_ingreso` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `requiere_proveedor` tinyint(1) DEFAULT 0 COMMENT 'Mostrar campos de proveedor',
  `requiere_factura` tinyint(1) DEFAULT 0 COMMENT 'Mostrar campo de factura',
  `requiere_area_produccion` tinyint(1) DEFAULT 0 COMMENT 'Mostrar selección de área',
  `requiere_motivo` tinyint(1) DEFAULT 0 COMMENT 'Campo motivo obligatorio',
  `requiere_autorizacion` tinyint(1) DEFAULT 0 COMMENT 'Requiere autorización',
  `permite_iva` tinyint(1) DEFAULT 0 COMMENT 'Permite checkbox IVA',
  `permite_moneda_extranjera` tinyint(1) DEFAULT 0 COMMENT 'Permite USD',
  `observaciones_obligatorias` tinyint(1) DEFAULT 0,
  `minimo_caracteres_obs` int(11) DEFAULT 0 COMMENT 'Mínimo caracteres en observaciones',
  `afecta_cpp` tinyint(1) DEFAULT 1 COMMENT 'Afecta Costo Promedio Ponderado',
  `tipo_kardex` varchar(50) DEFAULT 'ENTRADA' COMMENT 'Tipo en kardex',
  `icono` varchar(50) DEFAULT 'fa-arrow-down',
  `color` varchar(20) DEFAULT '#28a745',
  `orden` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_insumo`
--

CREATE TABLE `tipos_insumo` (
  `id_tipo_insumo` int(11) NOT NULL,
  `nombre_tipo` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_inventario`
--

CREATE TABLE `tipos_inventario` (
  `id_tipo_inventario` int(11) NOT NULL,
  `codigo` varchar(10) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `icono` varchar(50) DEFAULT 'fa-box',
  `color` varchar(20) DEFAULT '#6c757d',
  `orden` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_producto`
--

CREATE TABLE `tipos_producto` (
  `id_tipo_producto` int(11) NOT NULL,
  `nombre_tipo` varchar(50) NOT NULL,
  `categoria` enum('directo','ensamblaje') NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `turnos`
--

CREATE TABLE `turnos` (
  `id_turno` int(11) NOT NULL,
  `nombre_turno` varchar(50) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ubicaciones_almacen`
--

CREATE TABLE `ubicaciones_almacen` (
  `id_ubicacion` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo` enum('ALMACEN','AREA_PRODUCCION','TRANSITO') DEFAULT 'ALMACEN',
  `descripcion` text DEFAULT NULL,
  `responsable` varchar(100) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `unidades_medida`
--

CREATE TABLE `unidades_medida` (
  `id_unidad` int(11) NOT NULL,
  `codigo` varchar(10) NOT NULL,
  `nombre` varchar(30) NOT NULL,
  `abreviatura` varchar(10) NOT NULL,
  `tipo` enum('PESO','LONGITUD','CANTIDAD','VOLUMEN') NOT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `codigo_usuario` varchar(20) NOT NULL,
  `nombre_completo` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('tejedor','revisor','tintorero','coordinador','gerencia','admin') NOT NULL,
  `area` varchar(50) DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_acceso` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `v_documentos_ingreso`
--

CREATE TABLE `v_documentos_ingreso` (
  `id_documento` int(11) DEFAULT NULL,
  `numero_documento` varchar(30) DEFAULT NULL,
  `fecha_documento` date DEFAULT NULL,
  `tipo_documento` enum('INGRESO','SALIDA','AJUSTE','TRANSFERENCIA') DEFAULT NULL,
  `id_tipo_inventario` int(11) DEFAULT NULL,
  `tipo_inventario_nombre` varchar(50) DEFAULT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `proveedor_codigo` varchar(20) DEFAULT NULL,
  `proveedor_nombre` varchar(150) DEFAULT NULL,
  `proveedor_comercial` varchar(100) DEFAULT NULL,
  `proveedor_tipo` enum('LOCAL','IMPORTACION') DEFAULT NULL,
  `referencia_externa` varchar(50) DEFAULT NULL,
  `con_factura` tinyint(1) DEFAULT NULL,
  `moneda` enum('BOB','USD') DEFAULT NULL,
  `subtotal` decimal(14,4) DEFAULT NULL,
  `iva` decimal(14,4) DEFAULT NULL,
  `total` decimal(14,4) DEFAULT NULL,
  `estado` enum('BORRADOR','CONFIRMADO','ANULADO') DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT NULL,
  `fecha_anulacion` datetime DEFAULT NULL,
  `motivo_anulacion` varchar(255) DEFAULT NULL,
  `creado_por_nombre` varchar(100) DEFAULT NULL,
  `total_lineas` bigint(21) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `areas_produccion`
--
ALTER TABLE `areas_produccion`
  ADD PRIMARY KEY (`id_area`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Indices de la tabla `detalle_flujos`
--
ALTER TABLE `detalle_flujos`
  ADD PRIMARY KEY (`id_detalle_flujo`),
  ADD UNIQUE KEY `uk_flujo_etapa_orden` (`id_flujo`,`orden_secuencia`),
  ADD KEY `fk_detalle_flujo_flujo` (`id_flujo`),
  ADD KEY `fk_detalle_flujo_etapa` (`id_etapa`),
  ADD KEY `fk_detalle_flujo_alternativa` (`etapa_alternativa`);

--
-- Indices de la tabla `documentos_inventario`
--
ALTER TABLE `documentos_inventario`
  ADD PRIMARY KEY (`id_documento`),
  ADD KEY `idx_documento_origen` (`id_documento_origen`),
  ADD KEY `idx_tipo_ingreso` (`id_tipo_ingreso`),
  ADD KEY `idx_area_produccion` (`id_area_produccion`),
  ADD KEY `idx_autorizado_por` (`autorizado_por`);

--
-- Indices de la tabla `documentos_inventario_detalle`
--
ALTER TABLE `documentos_inventario_detalle`
  ADD KEY `idx_detalle_origen` (`id_detalle_origen`);

--
-- Indices de la tabla `etapas_produccion`
--
ALTER TABLE `etapas_produccion`
  ADD PRIMARY KEY (`id_etapa`),
  ADD UNIQUE KEY `uk_codigo_etapa` (`codigo_etapa`);

--
-- Indices de la tabla `flujos_produccion`
--
ALTER TABLE `flujos_produccion`
  ADD PRIMARY KEY (`id_flujo`),
  ADD UNIQUE KEY `uk_codigo_flujo` (`codigo_flujo`);

--
-- Indices de la tabla `inventario_materia_prima`
--
ALTER TABLE `inventario_materia_prima`
  ADD PRIMARY KEY (`id_inventario_mp`),
  ADD UNIQUE KEY `codigo_item` (`codigo_item`),
  ADD KEY `idx_codigo` (`codigo_item`),
  ADD KEY `idx_categoria` (`id_categoria`),
  ADD KEY `idx_stock` (`stock_actual`);

--
-- Indices de la tabla `inventario_productos_terminados`
--
ALTER TABLE `inventario_productos_terminados`
  ADD PRIMARY KEY (`id_inventario_pt`),
  ADD UNIQUE KEY `codigo_completo` (`codigo_completo`),
  ADD UNIQUE KEY `unique_producto_color_talla` (`id_producto_tejido`,`id_color`,`talla`,`calidad`),
  ADD KEY `idx_codigo` (`codigo_completo`),
  ADD KEY `idx_categoria` (`id_categoria`),
  ADD KEY `idx_producto` (`id_producto_tejido`),
  ADD KEY `idx_color` (`id_color`),
  ADD KEY `idx_talla` (`talla`),
  ADD KEY `idx_stock` (`stock_total_unidades`);

--
-- Indices de la tabla `kardex_valorado`
--
ALTER TABLE `kardex_valorado`
  ADD PRIMARY KEY (`id_kardex`),
  ADD KEY `idx_tipo_item` (`tipo_inventario`,`id_item`),
  ADD KEY `idx_fecha` (`fecha_movimiento`);

--
-- Indices de la tabla `movimientos_inventario_general`
--
ALTER TABLE `movimientos_inventario_general`
  ADD PRIMARY KEY (`id_movimiento`),
  ADD KEY `idx_tipo_inventario` (`tipo_inventario`),
  ADD KEY `idx_tipo_movimiento` (`tipo_movimiento`),
  ADD KEY `idx_fecha` (`fecha_movimiento`);

--
-- Indices de la tabla `productos_flujos`
--
ALTER TABLE `productos_flujos`
  ADD PRIMARY KEY (`id_producto_flujo`),
  ADD KEY `idx_producto` (`id_producto`),
  ADD KEY `idx_flujo` (`id_flujo`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indices de la tabla `secuencias_documento`
--
ALTER TABLE `secuencias_documento`
  ADD PRIMARY KEY (`id_secuencia`),
  ADD UNIQUE KEY `uk_secuencia` (`tipo_documento`,`subtipo`,`prefijo`,`anio`,`mes`);

--
-- Indices de la tabla `tipos_ingreso`
--
ALTER TABLE `tipos_ingreso`
  ADD PRIMARY KEY (`id_tipo_ingreso`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_codigo` (`codigo`),
  ADD KEY `idx_activo` (`activo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `areas_produccion`
--
ALTER TABLE `areas_produccion`
  MODIFY `id_area` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_flujos`
--
ALTER TABLE `detalle_flujos`
  MODIFY `id_detalle_flujo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `documentos_inventario`
--
ALTER TABLE `documentos_inventario`
  MODIFY `id_documento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `etapas_produccion`
--
ALTER TABLE `etapas_produccion`
  MODIFY `id_etapa` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `flujos_produccion`
--
ALTER TABLE `flujos_produccion`
  MODIFY `id_flujo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inventario_materia_prima`
--
ALTER TABLE `inventario_materia_prima`
  MODIFY `id_inventario_mp` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inventario_productos_terminados`
--
ALTER TABLE `inventario_productos_terminados`
  MODIFY `id_inventario_pt` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `kardex_valorado`
--
ALTER TABLE `kardex_valorado`
  MODIFY `id_kardex` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `movimientos_inventario_general`
--
ALTER TABLE `movimientos_inventario_general`
  MODIFY `id_movimiento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos_flujos`
--
ALTER TABLE `productos_flujos`
  MODIFY `id_producto_flujo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `secuencias_documento`
--
ALTER TABLE `secuencias_documento`
  MODIFY `id_secuencia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipos_ingreso`
--
ALTER TABLE `tipos_ingreso`
  MODIFY `id_tipo_ingreso` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `detalle_flujos`
--
ALTER TABLE `detalle_flujos`
  ADD CONSTRAINT `fk_detalle_flujo_alternativa` FOREIGN KEY (`etapa_alternativa`) REFERENCES `etapas_produccion` (`id_etapa`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_detalle_flujo_etapa` FOREIGN KEY (`id_etapa`) REFERENCES `etapas_produccion` (`id_etapa`),
  ADD CONSTRAINT `fk_detalle_flujo_flujo` FOREIGN KEY (`id_flujo`) REFERENCES `flujos_produccion` (`id_flujo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `documentos_inventario`
--
ALTER TABLE `documentos_inventario`
  ADD CONSTRAINT `fk_doc_area_produccion` FOREIGN KEY (`id_area_produccion`) REFERENCES `areas_produccion` (`id_area`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_doc_tipo_ingreso` FOREIGN KEY (`id_tipo_ingreso`) REFERENCES `tipos_ingreso` (`id_tipo_ingreso`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `productos_flujos`
--
ALTER TABLE `productos_flujos`
  ADD CONSTRAINT `fk_productos_flujos_flujo` FOREIGN KEY (`id_flujo`) REFERENCES `flujos_produccion` (`id_flujo`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
