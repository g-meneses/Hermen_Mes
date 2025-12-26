-- phpMyAdmin SQL Dump
-- Solución DEFINITIVA - Error #1068 Corregido
-- Se eliminó la doble definición de Primary Key en produccion_vaporizado

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
DROP DATABASE IF EXISTS `mes_hermen`;
CREATE DATABASE `mes_hermen` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `mes_hermen`;

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_movimiento` (IN `p_id_producto` INT, IN `p_tipo_movimiento` VARCHAR(10), IN `p_tipo_inventario` VARCHAR(20), IN `p_docenas` INT, IN `p_unidades` INT, IN `p_origen` VARCHAR(100), IN `p_destino` VARCHAR(100), IN `p_id_usuario` INT, IN `p_id_produccion` INT, IN `p_observaciones` TEXT, OUT `p_resultado` VARCHAR(100), OUT `p_id_movimiento` INT)   BEGIN
    DECLARE v_total_unidades INT;
    DECLARE v_stock_actual INT;
    DECLARE v_nuevas_docenas INT;
    DECLARE v_nuevas_unidades INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_resultado = 'ERROR: No se pudo registrar el movimiento';
        SET p_id_movimiento = NULL;
    END;
    
    START TRANSACTION;
    
    IF p_unidades < 0 OR p_unidades > 11 THEN
        SET p_resultado = 'ERROR: Unidades deben estar entre 0 y 11';
        SET p_id_movimiento = NULL;
        ROLLBACK;
    ELSE
        SET v_total_unidades = (p_docenas * 12) + p_unidades;
        
        IF p_tipo_movimiento = 'salida' THEN
            SELECT COALESCE(total_unidades_calculado, 0) 
            INTO v_stock_actual
            FROM inventario_intermedio
            WHERE id_producto = p_id_producto 
            AND tipo_inventario = p_tipo_inventario;
            
            IF v_stock_actual < v_total_unidades THEN
                SET p_resultado = CONCAT('ERROR: Stock insuficiente. Disponible: ', 
                                       FLOOR(v_stock_actual / 12), '|', 
                                       (v_stock_actual % 12));
                SET p_id_movimiento = NULL;
                ROLLBACK;
            ELSE
                INSERT INTO movimientos_inventario (
                    id_producto, tipo_movimiento, tipo_inventario,
                    docenas, unidades, total_unidades_calculado,
                    origen, destino, id_usuario, id_produccion, observaciones
                ) VALUES (
                    p_id_producto, p_tipo_movimiento, p_tipo_inventario,
                    p_docenas, p_unidades, v_total_unidades,
                    p_origen, p_destino, p_id_usuario, p_id_produccion, p_observaciones
                );
                
                SET p_id_movimiento = LAST_INSERT_ID();
                
                SET v_stock_actual = v_stock_actual - v_total_unidades;
                SET v_nuevas_docenas = FLOOR(v_stock_actual / 12);
                SET v_nuevas_unidades = v_stock_actual % 12;
                
                UPDATE inventario_intermedio
                SET docenas = v_nuevas_docenas,
                    unidades = v_nuevas_unidades
                WHERE id_producto = p_id_producto 
                AND tipo_inventario = p_tipo_inventario;
                
                SET p_resultado = 'EXITO';
                COMMIT;
            END IF;
        ELSE
            INSERT INTO movimientos_inventario (
                id_producto, tipo_movimiento, tipo_inventario,
                docenas, unidades, total_unidades_calculado,
                origen, destino, id_usuario, id_produccion, observaciones
            ) VALUES (
                p_id_producto, p_tipo_movimiento, p_tipo_inventario,
                p_docenas, p_unidades, v_total_unidades,
                p_origen, p_destino, p_id_usuario, p_id_produccion, p_observaciones
            );
            
            SET p_id_movimiento = LAST_INSERT_ID();
            
            INSERT INTO inventario_intermedio (
                id_producto, tipo_inventario, docenas, unidades
            ) VALUES (
                p_id_producto, p_tipo_inventario, p_docenas, p_unidades
            )
            ON DUPLICATE KEY UPDATE
                docenas = docenas + p_docenas + FLOOR((unidades + p_unidades) / 12),
                unidades = (unidades + p_unidades) % 12;
            
            SET p_resultado = 'EXITO';
            COMMIT;
        END IF;
    END IF;
END$$
DELIMITER ;

-- --------------------------------------------------------

CREATE TABLE `colores` (
  `id_color` int(11) NOT NULL,
  `codigo_color` varchar(20) NOT NULL,
  `nombre_color` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `colores` VALUES (1, 'NEGRO', 'Negro', NULL, 1), (2, 'COGNAC', 'Coñac', NULL, 1), (3, 'BEIGE', 'Beige', NULL, 1), (4, 'BLANCO', 'Blanco', NULL, 1), (5, 'GRIS', 'Gris', NULL, 1), (6, 'AZUL', 'Azul', NULL, 1), (7, 'VERDE', 'Verde', NULL, 1);

-- --------------------------------------------------------

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

INSERT INTO `detalle_plan_generico` VALUES (1, 1, 1, 19, 'mantener', NULL, NULL, ''), (2, 1, 2, 57, 'cambiar', 56, 50, ''), (3, 1, 3, 39, 'mantener', NULL, NULL, ''), (4, 1, 4, 43, 'mantener', NULL, NULL, ''), (5, 1, 7, 11, 'mantener', NULL, NULL, ''), (6, 2, 1, 19, 'cambiar', 41, 100, ''), (7, 2, 2, 57, 'mantener', NULL, NULL, ''), (8, 2, 3, 39, 'mantener', NULL, NULL, ''), (9, 2, 4, 43, 'cambiar', 19, 200, ''), (10, 2, 7, 7, 'mantener', NULL, NULL, ''), (11, 2, 8, 12, 'mantener', NULL, NULL, ''), (12, 2, 9, 51, 'mantener', NULL, NULL, ''), (13, 2, 10, 53, 'mantener', NULL, NULL, ''), (14, 2, 11, 65, 'mantener', NULL, NULL, ''), (15, 2, 5, 56, 'mantener', NULL, NULL, '');

-- --------------------------------------------------------

CREATE TABLE `detalle_produccion_tejeduria` (
  `id_detalle` int(11) NOT NULL,
  `id_produccion` int(11) NOT NULL,
  `id_maquina` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `docenas` int(11) NOT NULL DEFAULT 0,
  `unidades` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `detalle_produccion_tejeduria` VALUES (12, 2, 1, 19, 8, 0), (13, 2, 2, 57, 8, 0), (14, 2, 3, 39, 8, 0), (15, 2, 4, 43, 8, 0), (16, 2, 5, 56, 8, 0), (17, 2, 6, 36, 8, 0), (18, 2, 7, 7, 8, 0), (19, 2, 8, 12, 8, 0), (20, 2, 9, 51, 8, 0), (21, 2, 10, 53, 8, 6), (22, 2, 11, 65, 4, 6);

-- --------------------------------------------------------

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

CREATE TABLE `produccion_vaporizado` (
  `id_produccion_vaporizado` int(11) NOT NULL,
  `fecha_produccion` date NOT NULL,
  `id_turno` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

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

CREATE TABLE `disenos` (
  `id_diseno` int(11) NOT NULL,
  `nombre_diseno` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `disenos` VALUES (1, 'PUNTERA REFORZADA', 'Con refuerzo en la puntera', 1), (2, 'SIN PUNTERA', 'Sin puntera reforzada', 1), (3, 'NUDA', 'Diseño nudo/transparente', 1), (4, 'BASICO', 'Diseño básico estándar', 1);

-- --------------------------------------------------------

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

INSERT INTO `insumos` VALUES (100, 'DTY-22-10F-S', 1, 'DTY 22 DTEX / 10 F, TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 50.00, NULL, NULL, 1, '2025-11-09 18:11:49', 81.97), (101, 'DTY-22-10F-Z', 1, 'DTY 22 DTEX / 10 F, TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 50.00, NULL, NULL, 1, '2025-11-09 18:11:49', 81.97), (102, 'DTY-44-12F-S', 1, 'DTY 44 DTEX / 12 F, TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 20.00, 100.00, NULL, NULL, 1, '2025-11-09 18:11:49', 52.84), (103, 'DTY-44-12F-Z', 1, 'DTY 44 DTEX / 12 F, TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 20.00, 100.00, NULL, NULL, 1, '2025-11-09 18:11:49', 52.84), (104, 'DTY-44-34F-S', 1, 'DTY 44 DTEX / 34 F, TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 15.00, 80.00, NULL, NULL, 1, '2025-11-09 18:11:49', 41.38), (105, 'DTY-44-34F-Z', 1, 'DTY 44 DTEX / 34 F, TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 15.00, 80.00, NULL, NULL, 1, '2025-11-09 18:11:49', 41.38), (106, 'DTY-78-24F-S', 1, 'DTY 78 DTEX / 24 F TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 25.00, 120.00, NULL, NULL, 1, '2025-11-09 18:11:49', 31.22), (107, 'DTY-78-24F-Z', 1, 'DTY 78 DTEX / 24 F TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 25.00, 120.00, NULL, NULL, 1, '2025-11-09 18:11:49', 31.22), (108, 'DTY-78-48F-S', 1, 'DTY 78 DTEX / 48 F TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 20.00, 100.00, NULL, NULL, 1, '2025-11-09 18:11:49', 32.17), (109, 'DTY-78-48F-Z', 1, 'DTY 78 DTEX / 48 F TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 20.00, 100.00, NULL, NULL, 1, '2025-11-09 18:11:49', 32.17), (110, 'DTY-78-68F-S', 1, 'DTY 78 DTEX / 68 F, TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 15.00, 60.00, NULL, NULL, 1, '2025-11-09 18:11:49', 45.01), (111, 'DTY-78-68F-Z', 1, 'DTY 78 DTEX / 68 F, TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 15.00, 60.00, NULL, NULL, 1, '2025-11-09 18:11:49', 45.02), (112, 'DTY-78-24F-X2', 1, 'DTY 78 DTEX / 24 F X 2', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 40.00, NULL, NULL, 1, '2025-11-09 18:11:49', 62.40), (113, 'HTY-17-2F-S', 1, 'HTY 17 DTEX / 2 F, TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 30.00, NULL, NULL, 1, '2025-11-09 18:11:49', 62.40), (114, 'HTY-17-2F-Z', 1, 'HTY 17 DTEX / 2 F, TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 30.00, NULL, NULL, 1, '2025-11-09 18:11:49', 53.86), (115, 'HTY-15-1F-S', 1, 'HTY 15/1 F, TORSIÓN \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 8.00, 25.00, NULL, NULL, 1, '2025-11-09 18:11:49', 53.86), (116, 'HTY-15-1F-Z', 1, 'HTY 15/1 F, TORSIÓN \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 8.00, 25.00, NULL, NULL, 1, '2025-11-09 18:11:49', 53.86), (117, 'SP30-FDY12-S', 1, 'SP 30 + FDY 12/5F, TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 40.00, NULL, NULL, 1, '2025-11-09 18:11:49', 53.86), (118, 'SP30-FDY12-Z', 1, 'SP 30 + FDY 12/5F, TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 40.00, NULL, NULL, 1, '2025-11-09 18:11:49', 120.48), (119, 'SP40-FDY20-S', 1, 'SP 40 + FDY 20/7F, TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 35.00, NULL, NULL, 1, '2025-11-09 18:11:49', 120.48), (120, 'SP40-FDY20-Z', 1, 'SP 40 + FDY 20/7F, TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 35.00, NULL, NULL, 1, '2025-11-09 18:11:49', 80.62), (121, 'SP30-FDY12-X2', 1, 'SP 30+ FDY 12/5F * 2', 'HILO_POLIAMIDA', NULL, 'kilogramos', 8.00, 30.00, NULL, NULL, 1, '2025-11-09 18:11:49', 80.62), (122, 'SP40-DTY40-S', 1, 'SP 40+ DTY 40/12F S TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 12.00, 45.00, NULL, NULL, 1, '2025-11-09 18:11:49', 76.67), (123, 'SP40-DTY40-Z', 1, 'SP 40+ DTY 40/12F S TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 12.00, 45.00, NULL, NULL, 1, '2025-11-09 18:11:49', 76.67), (124, 'SP20-70DEN-NEG', 1, 'SP 20 + 70 DEN NEGRO A-270 (Z) NEGRO', 'HILO_POLIAMIDA', NULL, 'kilogramos', 5.00, 20.00, NULL, NULL, 1, '2025-11-09 18:11:49', 80.62), (125, 'FDY-22-24F', 1, 'FDY RIGIDO 22 DTEX /24 F', 'HILO_POLIAMIDA', NULL, 'kilogramos', 15.00, 50.00, NULL, NULL, 1, '2025-11-09 18:11:49', 42.44), (126, 'FDY-22-7F', 1, 'FDY RIGIDO 22DTEX / 7F', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 30.00, NULL, NULL, 1, '2025-11-09 18:11:49', 95.00), (127, 'FDY-15-3F', 1, 'FDY RIGIDO 15/3', 'HILO_POLIAMIDA', NULL, 'kilogramos', 12.00, 40.00, NULL, NULL, 1, '2025-11-09 18:11:49', 42.01), (128, 'FDY-16-5F', 1, 'FDY RIGIDO 16/5', 'HILO_POLIAMIDA', NULL, 'kilogramos', 12.00, 40.00, NULL, NULL, 1, '2025-11-09 18:11:49', 42.44), (129, 'FDY-44-12F', 1, 'FDY RIGIDO 44/12', 'HILO_POLIAMIDA', NULL, 'kilogramos', 15.00, 60.00, NULL, NULL, 1, '2025-11-09 18:11:49', 42.44), (130, 'FDY-44-12F-BRIL', 1, 'FDY RIGIDO 44/12 BRILLO', 'HILO_POLIAMIDA', NULL, 'kilogramos', 15.00, 50.00, NULL, NULL, 1, '2025-11-09 18:11:49', 42.44), (131, 'SPANDEX-100', 2, 'SPANDEX 100 DEN', 'LYCRA', NULL, 'kilogramos', 20.00, 80.00, NULL, NULL, 1, '2025-11-09 18:11:49', 108.00), (132, 'SPANDEX-140', 2, 'SPANDEX 140 DEN', 'LYCRA', NULL, 'kilogramos', 20.00, 70.00, NULL, NULL, 1, '2025-11-09 18:11:49', 111.63), (133, 'DTY-22-7F-S', 1, 'DTY 22 DTEX, 7 F, S', 'HILO_POLIAMIDA', '', 'kilogramos', 50.00, 150.00, NULL, '', 1, '2025-11-09 20:49:36', 45.80);

-- --------------------------------------------------------

CREATE TABLE `inventario_intermedio` (
  `id_inventario` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL COMMENT 'FK a productos_tejidos',
  `tipo_inventario` enum('tejido','revisado','vaporizado','preteñido','teñido') NOT NULL,
  `docenas` int(11) NOT NULL DEFAULT 0 COMMENT 'Cantidad en docenas',
  `unidades` int(11) NOT NULL DEFAULT 0 COMMENT 'Unidades sueltas (0-11)',
  `total_unidades_calculado` int(11) GENERATED ALWAYS AS (`docenas` * 12 + `unidades`) STORED COMMENT 'Total en unidades',
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Última actualización'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `inventario_intermedio` (`id_inventario`, `id_producto`, `tipo_inventario`, `docenas`, `unidades`, `fecha_actualizacion`) VALUES (1, 19, 'tejido', 5, 0, '2025-11-16 21:45:58'), (2, 57, 'tejido', 6, 0, '2025-11-16 21:45:58'), (3, 39, 'tejido', 8, 0, '2025-11-16 21:45:58'), (4, 43, 'tejido', 4, 2, '2025-11-16 21:45:58'), (5, 56, 'tejido', 6, 0, '2025-11-16 21:45:58'), (6, 45, 'tejido', 7, 8, '2025-11-16 21:45:58'), (7, 7, 'tejido', 15, 3, '2025-11-16 21:45:58'), (8, 12, 'tejido', 3, 2, '2025-11-16 21:45:58'), (9, 51, 'tejido', 2, 0, '2025-11-16 21:45:58'), (10, 53, 'tejido', 7, 8, '2025-11-16 21:45:58'), (11, 65, 'tejido', 4, 5, '2025-11-16 21:45:58');

-- --------------------------------------------------------

CREATE TABLE `lineas_producto` (
  `id_linea` int(11) NOT NULL,
  `codigo_linea` varchar(20) NOT NULL,
  `nombre_linea` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `lineas_producto` VALUES (1, 'LUJO', 'LUJO', 'Productos de hilo de poliamida con torsión y sin texturizar', 1), (2, 'STRETCH', 'STRETCH', 'Productos de hilo texturizado de poliamida', 1), (3, 'LYCRA20', 'LYCRA 20', 'Productos con Lycra denier 20', 1), (4, 'LYCRA40', 'LYCRA 40', 'Productos con Lycra denier 40', 1), (5, 'CAMISETAS', 'CAMISETAS', 'Camisetas de poliamida para diferentes edades', 1);

-- --------------------------------------------------------

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

INSERT INTO `maquinas` VALUES (1, 'M-01', 'Máquina Circular 4\" 400 agujas', 4.0, 400, 'operativa', 'ZONA A', NULL, NULL, '2025-11-03 23:44:06', '2025-11-03 23:44:06'), (2, 'M-02', 'Máquina Circular 4&quot; 400 agujas', 4.0, 400, 'operativa', 'ZONA B', NULL, '', '2025-11-03 23:44:06', '2025-11-20 15:03:33'), (3, 'M-03', 'Máquina Circular 4\" 400 agujas', 4.0, 400, 'operativa', 'ZONA A', NULL, NULL, '2025-11-03 23:44:06', '2025-11-03 23:44:06'), (4, 'M-04', 'Máquina Circular 4\" 400 agujas', 4.0, 400, 'operativa', 'ZONA A', NULL, NULL, '2025-11-03 23:44:06', '2025-11-03 23:44:06'), (5, 'M-05', 'Máquina Circular 4\" 400 agujas', 4.0, 400, 'operativa', 'ZONA A', NULL, NULL, '2025-11-03 23:44:06', '2025-11-03 23:44:06'), (6, 'M-06', 'Máquina Circular 4\" 400 agujas', 4.0, 400, 'mantenimiento', 'ZONA B', NULL, NULL, '2025-11-03 23:44:06', '2025-11-03 23:44:06'), (7, 'M-07', 'Máquina Circular 4\" 400 agujas', 4.0, 400, 'operativa', 'ZONA B', NULL, NULL, '2025-11-03 23:44:06', '2025-11-03 23:44:06'), (8, 'M-08', 'Máquina Circular 4\" 400 agujas', 4.0, 400, 'operativa', 'ZONA B', NULL, NULL, '2025-11-03 23:44:06', '2025-11-03 23:44:06'), (9, 'M-09', 'Máquina Circular 4\" 400 agujas', 4.0, 400, 'operativa', 'ZONA B', NULL, NULL, '2025-11-03 23:44:06', '2025-11-03 23:44:06'), (10, 'M-10', 'Máquina Circular 4\" 400 agujas', 4.0, 400, 'operativa', 'ZONA B', NULL, NULL, '2025-11-03 23:44:06', '2025-11-03 23:44:06'), (11, 'M-11', 'Máquina de prueba', 4.0, 400, 'operativa', 'ZONA TEST', NULL, '', '2025-11-04 01:27:03', '2025-11-04 01:27:03');

-- --------------------------------------------------------

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

INSERT INTO `movimientos_inventario` (`id_movimiento`, `id_producto`, `tipo_movimiento`, `tipo_inventario`, `docenas`, `unidades`, `total_unidades_calculado`, `origen`, `destino`, `id_produccion`, `id_usuario`, `fecha_movimiento`, `observaciones`) VALUES (1, 19, 'entrada', 'tejido', 5, 0, 60, 'Producción 161125-3', 'Inventario Tejido', 11, 2, '2025-11-16 17:45:58', 'Entrada automática desde producción'), (2, 57, 'entrada', 'tejido', 6, 0, 72, 'Producción 161125-3', 'Inventario Tejido', 11, 2, '2025-11-16 17:45:58', 'Entrada automática desde producción'), (3, 39, 'entrada', 'tejido', 8, 0, 96, 'Producción 161125-3', 'Inventario Tejido', 11, 2, '2025-11-16 17:45:58', 'Entrada automática desde producción'), (4, 43, 'entrada', 'tejido', 4, 2, 50, 'Producción 161125-3', 'Inventario Tejido', 11, 2, '2025-11-16 17:45:58', 'Entrada automática desde producción'), (5, 56, 'entrada', 'tejido', 6, 0, 72, 'Producción 161125-3', 'Inventario Tejido', 11, 2, '2025-11-16 17:45:58', 'Entrada automática desde producción'), (6, 45, 'entrada', 'tejido', 7, 8, 92, 'Producción 161125-3', 'Inventario Tejido', 11, 2, '2025-11-16 17:45:58', 'Entrada automática desde producción'), (7, 7, 'entrada', 'tejido', 15, 3, 183, 'Producción 161125-3', 'Inventario Tejido', 11, 2, '2025-11-16 17:45:58', 'Entrada automática desde producción'), (8, 12, 'entrada', 'tejido', 3, 2, 38, 'Producción 161125-3', 'Inventario Tejido', 11, 2, '2025-11-16 17:45:58', 'Entrada automática desde producción'), (9, 51, 'entrada', 'tejido', 2, 0, 24, 'Producción 161125-3', 'Inventario Tejido', 11, 2, '2025-11-16 17:45:58', 'Entrada automática desde producción'), (10, 53, 'entrada', 'tejido', 7, 8, 92, 'Producción 161125-3', 'Inventario Tejido', 11, 2, '2025-11-16 17:45:58', 'Entrada automática desde producción'), (11, 65, 'entrada', 'tejido', 4, 5, 53, 'Producción 161125-3', 'Inventario Tejido', 11, 2, '2025-11-16 17:45:58', 'Entrada automática desde producción');

-- --------------------------------------------------------

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

INSERT INTO `plan_generico_tejido` VALUES (1, 'PLAN-2025-1', '2025-11-14', NULL, 'historico', 'PARA REQUERIMIENTO DE TEMPORADA', 2, '2025-11-14 02:47:47', NULL), (2, 'PLAN-2025-1113', '2025-11-14', NULL, 'vigente', '', 2, '2025-11-14 03:39:02', NULL);

-- --------------------------------------------------------

CREATE TABLE `produccion_revisado_crudo` (
  `id_revisado` int(11) NOT NULL,
  `codigo_lote_revisado` varchar(50) DEFAULT NULL,
  `fecha_revisado` date NOT NULL,
  `id_turno` int(11) NOT NULL,
  `id_revisor` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

CREATE TABLE `produccion_tejeduria` (
  `id_produccion` int(11) NOT NULL,
  `codigo_lote` varchar(50) NOT NULL,
  `fecha_produccion` date NOT NULL,
  `id_turno` int(11) NOT NULL,
  `id_tejedor` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `produccion_tejeduria` (`id_produccion`, `codigo_lote`, `fecha_produccion`, `id_turno`, `id_tejedor`, `observaciones`, `fecha_creacion`) VALUES (2, '211125-1', '2025-11-21', 1, 3, '', '2025-11-21 16:23:43');

-- --------------------------------------------------------

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

INSERT INTO `productos_tejidos` (`id_producto`, `codigo_producto`, `id_linea`, `id_tipo_producto`, `id_diseno`, `talla`, `descripcion_completa`, `peso_promedio_docena`, `tiempo_estimado_docena`, `activo`, `fecha_creacion`) VALUES
(1, 'LUJO-PH-PR-S', 1, 1, 1, 'S', 'Pantyhose Lujo Puntera Reforzada Talla S', 850.00, 45, 1, '2025-11-06 01:34:23'),
(2, 'LUJO-PH-PR-M', 1, 1, 1, 'M', 'Pantyhose Lujo Puntera Reforzada Talla M', 900.00, 45, 1, '2025-11-06 01:34:23'),
(3, 'LUJO-PH-PR-L', 1, 1, 1, 'L', 'Pantyhose Lujo Puntera Reforzada Talla L', 950.00, 45, 1, '2025-11-06 01:34:23'),
(4, 'LUJO-PH-PR-XL', 1, 1, 1, 'XL', 'Pantyhose Lujo Puntera Reforzada Talla XL', 1000.00, 45, 1, '2025-11-06 01:34:23'),
(5, 'LUJO-PH-SP-S', 1, 1, 2, 'S', 'Pantyhose Lujo Sin Puntera Talla S', 820.00, 42, 1, '2025-11-06 01:34:23'),
(6, 'LUJO-PH-SP-M', 1, 1, 2, 'M', 'Pantyhose Lujo Sin Puntera Talla M', 870.00, 42, 1, '2025-11-06 01:34:23'),
(7, 'LUJO-PH-SP-L', 1, 1, 2, 'L', 'Pantyhose Lujo Sin Puntera Talla L', 920.00, 42, 1, '2025-11-06 01:34:23'),
(8, 'LUJO-PH-SP-XL', 1, 1, 2, 'XL', 'Pantyhose Lujo Sin Puntera Talla XL', 970.00, 42, 1, '2025-11-06 01:34:23'),
(9, 'LUJO-PH-ND-S', 1, 1, 3, 'S', 'Pantyhose Lujo Nuda Talla S', 800.00, 40, 1, '2025-11-06 01:34:23'),
(10, 'LUJO-PH-ND-M', 1, 1, 3, 'M', 'Pantyhose Lujo Nuda Talla M', 850.00, 40, 1, '2025-11-06 01:34:23'),
(11, 'LUJO-PH-ND-L', 1, 1, 3, 'L', 'Pantyhose Lujo Nuda Talla L', 900.00, 40, 1, '2025-11-06 01:34:23'),
(12, 'LUJO-PH-ND-XL', 1, 1, 3, 'XL', 'Pantyhose Lujo Nuda Talla XL', 950.00, 40, 1, '2025-11-06 01:34:23'),
(13, 'LUJO-PH-NIÑA-68', 1, 1, 1, '6-8', 'Pantyhose Lujo Niña Talla 6-8', 650.00, 38, 1, '2025-11-06 01:34:23'),
(14, 'LUJO-PH-NIÑA-810', 1, 1, 1, '8-10', 'Pantyhose Lujo Niña Talla 8-10', 700.00, 38, 1, '2025-11-06 01:34:23'),
(15, 'LUJO-PH-NIÑA-1012', 1, 1, 1, '10-12', 'Pantyhose Lujo Niña Talla 10-12', 750.00, 38, 1, '2025-11-06 01:34:23'),
(16, 'LUJO-MS-PR-TU', 1, 2, 1, 'TU', 'Media Soporte Lujo Puntera Reforzada', 700.00, 35, 1, '2025-11-06 01:34:23'),
(17, 'LUJO-MS-SP-TU', 1, 2, 2, 'TU', 'Media Soporte Lujo Sin Puntera', 680.00, 35, 1, '2025-11-06 01:34:23'),
(18, 'LUJO-MP-PR-TU', 1, 3, 1, 'TU', 'Media Pantalón Lujo Puntera Reforzada', 640.00, 33, 1, '2025-11-06 01:34:23'),
(19, 'LUJO-MP-SP-TU', 1, 3, 2, 'TU', 'Media Pantalón Lujo Sin Puntera', 630.00, 33, 1, '2025-11-06 01:34:23'),
(20, 'STRETCH-PH-PR-S', 2, 1, 1, 'S', 'Pantyhose Stretch Puntera Reforzada Talla S', 800.00, 42, 1, '2025-11-06 01:34:23'),
(21, 'STRETCH-PH-PR-M', 2, 1, 1, 'M', 'Pantyhose Stretch Puntera Reforzada Talla M', 850.00, 42, 1, '2025-11-06 01:34:23'),
(22, 'STRETCH-PH-PR-L', 2, 1, 1, 'L', 'Pantyhose Stretch Puntera Reforzada Talla L', 900.00, 42, 1, '2025-11-06 01:34:23'),
(23, 'STRETCH-PH-PR-XL', 2, 1, 1, 'XL', 'Pantyhose Stretch Puntera Reforzada Talla XL', 950.00, 42, 0, '2025-11-06 01:34:23'),
(24, 'STRETCH-PH-NIÑA-01', 2, 1, 4, '0-1', 'Pantyhose Stretch Niña Talla 0-1', 450.00, 30, 1, '2025-11-06 01:34:23'),
(25, 'STRETCH-PH-NIÑA-24', 2, 1, 4, '2-4', 'Pantyhose Stretch Niña Talla 2-4', 500.00, 30, 1, '2025-11-06 01:34:23'),
(26, 'STRETCH-PH-NIÑA-46', 2, 1, 4, '4-6', 'Pantyhose Stretch Niña Talla 4-6', 550.00, 32, 1, '2025-11-06 01:34:23'),
(27, 'STRETCH-PH-NIÑA-68', 2, 1, 4, '6-8', 'Pantyhose Stretch Niña Talla 6-8', 600.00, 35, 1, '2025-11-06 01:34:23'),
(28, 'STRETCH-PH-NIÑA-810', 2, 1, 4, '8-10', 'Pantyhose Stretch Niña Talla 8-10', 650.00, 35, 1, '2025-11-06 01:34:23'),
(29, 'STRETCH-PH-NIÑA-1012', 2, 1, 4, '10-12', 'Pantyhose Stretch Niña Talla 10-12', 700.00, 38, 1, '2025-11-06 01:34:23'),
(30, 'STRETCH-PH-NIÑA-1214', 2, 1, 4, '12-14', 'Pantyhose Stretch Niña Talla 12-14', 750.00, 38, 1, '2025-11-06 01:34:23'),
(31, 'STRETCH-MS-PR-TU', 2, 2, 1, 'TU', 'Media Soporte Stretch Puntera Reforzada', 680.00, 33, 1, '2025-11-06 01:34:23'),
(32, 'STRETCH-MP-PR-TU', 2, 3, 1, 'TU', 'Media Pantalón Stretch Puntera Reforzada', 630.00, 32, 1, '2025-11-06 01:34:23'),
(33, 'STRETCH-MSK-PR-TU', 2, 4, 1, 'TU', 'Media Socket Stretch Puntera Reforzada', 450.00, 28, 1, '2025-11-06 01:34:23'),
(34, 'STRETCH-CP-BASICO-TU', 2, 5, 4, 'TU', 'Cobertor de Pie Stretch Básico', 300.00, 25, 1, '2025-11-06 01:34:23'),
(35, 'CAM-H-CUERPO-TU', 5, 6, 4, 'TU', 'Camiseta Hombre Cuerpo Básico', 1200.00, 50, 1, '2025-11-06 01:34:23'),
(36, 'CAM-H-MANGA-TU', 5, 7, 4, 'TU', 'Camiseta Hombre Manga Básica', 350.00, 25, 1, '2025-11-06 01:34:23'),
(37, 'CAM-M-CUERPO-TU', 5, 6, 4, 'TU', 'Camiseta Mujer Cuerpo Básico', 1100.00, 48, 1, '2025-11-06 01:34:23'),
(38, 'CAM-M-MANGA-TU', 5, 7, 4, 'TU', 'Camiseta Mujer Manga Básica', 320.00, 24, 1, '2025-11-06 01:34:23'),
(39, 'CAM-I-CUERPO-47', 5, 6, 4, '4-7', 'Camiseta Infantil Cuerpo Básico Talla 4-7', 800.00, 40, 1, '2025-11-06 01:34:23'),
(40, 'CAM-I-CUERPO-79', 5, 6, 4, '7-9', 'Camiseta Infantil Cuerpo Básico Talla 7-9', 900.00, 42, 1, '2025-11-06 01:34:23'),
(41, 'CAM-I-CUERPO-912', 5, 6, 4, '9-12', 'Camiseta Infantil Cuerpo Básico Talla 9-12', 1000.00, 45, 1, '2025-11-06 01:34:23'),
(42, 'CAM-I-MANGA-47', 5, 7, 4, '4-7', 'Camiseta Infantil Manga Básica Talla 4-7', 250.00, 22, 1, '2025-11-06 01:34:23'),
(43, 'CAM-I-MANGA-79', 5, 7, 4, '7-9', 'Camiseta Infantil Manga Básica Talla 7-9', 280.00, 23, 1, '2025-11-06 01:34:23'),
(44, 'CAM-I-MANGA-912', 5, 7, 4, '9-12', 'Camiseta Infantil Manga Básica Talla 9-12', 310.00, 24, 1, '2025-11-06 01:34:23'),
(45, 'CAM-RIBETE-TU', 5, 8, 4, 'TU', 'Ribete para Cuello Básico', 150.00, 20, 1, '2025-11-06 01:34:23'),
(46, 'LY20-PH-ND-S', 3, 1, 3, 'S', 'Pantyhose Lycra 20 Nuda Talla S', 780.00, 40, 1, '2025-11-06 01:34:23'),
(47, 'LY20-PH-ND-M', 3, 1, 3, 'M', 'Pantyhose Lycra 20 Nuda Talla M', 830.00, 40, 1, '2025-11-06 01:34:23'),
(48, 'LY20-PH-ND-L', 3, 1, 3, 'L', 'Pantyhose Lycra 20 Nuda Talla L', 880.00, 40, 1, '2025-11-06 01:34:23'),
(49, 'LY20-PH-ND-XL', 3, 1, 3, 'XL', 'Pantyhose Lycra 20 Nuda Talla XL', 930.00, 40, 1, '2025-11-06 01:34:23'),
(50, 'LY20-MP-SP-TU', 3, 3, 2, 'TU', 'Media Pantalón Lycra 20 Sin Puntera', 620.00, 32, 1, '2025-11-06 01:34:23'),
(51, 'LY20-PH-NIÑA-46', 3, 1, 3, '4-6', 'Pantyhose Lycra 20 Niña Talla 4-6', 550.00, 32, 1, '2025-11-06 01:34:23'),
(52, 'LY20-PH-NIÑA-68', 3, 1, 3, '6-8', 'Pantyhose Lycra 20 Niña Talla 6-8', 600.00, 35, 1, '2025-11-06 01:34:23'),
(53, 'LY20-PH-NIÑA-810', 3, 1, 3, '8-10', 'Pantyhose Lycra 20 Niña Talla 8-10', 650.00, 35, 1, '2025-11-06 01:34:23'),
(54, 'LY20-PH-NIÑA-1012', 3, 1, 3, '10-12', 'Pantyhose Lycra 20 Niña Talla 10-12', 700.00, 38, 1, '2025-11-06 01:34:23'),
(55, 'LY20-PH-NIÑA-1214', 3, 1, 3, '12-14', 'Pantyhose Lycra 20 Niña Talla 12-14', 750.00, 38, 1, '2025-11-06 01:34:23'),
(56, 'LY40-PH-ND-S', 4, 1, 3, 'S', 'Pantyhose Lycra 40 Nuda Talla S', 820.00, 42, 1, '2025-11-06 01:34:23'),
(57, 'LY40-PH-ND-M', 4, 1, 3, 'M', 'Pantyhose Lycra 40 Nuda Talla M', 870.00, 42, 1, '2025-11-06 01:34:23'),
(58, 'LY40-PH-ND-L', 4, 1, 3, 'L', 'Pantyhose Lycra 40 Nuda Talla L', 920.00, 42, 1, '2025-11-06 01:34:23'),
(59, 'LY40-PH-ND-XL', 4, 1, 3, 'XL', 'Pantyhose Lycra 40 Nuda Talla XL', 970.00, 42, 1, '2025-11-06 01:34:23'),
(60, 'LY40-MP-SP-TU', 4, 3, 2, 'TU', 'Media Pantalón Lycra 40 Sin Puntera', 650.00, 33, 1, '2025-11-06 01:34:23'),
(61, 'LY40-PH-NIÑA-46', 4, 1, 3, '4-6', 'Pantyhose Lycra 40 Niña Talla 4-6', 570.00, 32, 1, '2025-11-06 01:34:23'),
(62, 'LY40-PH-NIÑA-68', 4, 1, 3, '6-8', 'Pantyhose Lycra 40 Niña Talla 6-8', 620.00, 35, 1, '2025-11-06 01:34:23'),
(63, 'LY40-PH-NIÑA-810', 4, 1, 3, '8-10', 'Pantyhose Lycra 40 Niña Talla 8-10', 670.00, 35, 1, '2025-11-06 01:34:23'),
(64, 'LY40-PH-NIÑA-1012', 4, 1, 3, '10-12', 'Pantyhose Lycra 40 Niña Talla 10-12', 720.00, 38, 1, '2025-11-06 01:34:23'),
(65, 'LY40-PH-NIÑA-1214', 4, 1, 3, '12-14', 'Pantyhose Lycra 40 Niña Talla 12-14', 770.00, 38, 1, '2025-11-06 01:34:23');

-- --------------------------------------------------------

CREATE TABLE `producto_insumos` (
  `id_producto_insumo` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `id_insumo` int(11) NOT NULL,
  `cantidad_por_docena` decimal(10,3) NOT NULL,
  `es_principal` tinyint(1) DEFAULT 0,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `producto_insumos` VALUES (1, 18, 131, 3.000, 0, 'Elasticidad'), (2, 18, 113, 65.000, 1, 'Hilo principal LUJO 17/2');

-- --------------------------------------------------------

CREATE TABLE `tipos_insumo` (
  `id_tipo_insumo` int(11) NOT NULL,
  `nombre_tipo` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tipos_insumo` VALUES (1, 'HILO POLIAMIDA', 'Hilos de poliamida en diferentes deniers', 1), (2, 'LYCRA', 'Fibra elástica Lycra', 1), (3, 'ELASTICO', 'Elásticos para pretina', 1), (4, 'ALGODON', 'Algodón para parches', 1);

-- --------------------------------------------------------

CREATE TABLE `tipos_producto` (
  `id_tipo_producto` int(11) NOT NULL,
  `nombre_tipo` varchar(50) NOT NULL,
  `categoria` enum('directo','ensamblaje') NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tipos_producto` VALUES (1, 'PANTYHOSE', 'ensamblaje', 'Pantymedias que requieren ensamblaje de piernas y parche', 1), (2, 'MEDIA SOPORTE', 'directo', 'Medias de soporte que solo requieren costura de puntera', 1), (3, 'MEDIA PANTALÓN', 'directo', 'Medias tipo pantalón que solo requieren costura de puntera', 1), (4, 'MEDIA SOCKET', 'directo', 'Medias tipo socket', 1), (5, 'COBERTOR DE PIE', 'directo', 'Cobertor de pie', 1), (6, 'CUERPO', 'ensamblaje', 'Cuerpo de camiseta (pecho y espalda)', 1), (7, 'MANGA', 'ensamblaje', 'Manga de camiseta', 1), (8, 'RIBETE', 'ensamblaje', 'Ribete para cuello de camiseta', 1), (9, 'CLASICO', 'ensamblaje', 'Camiseta clásica completa', 1);

-- --------------------------------------------------------

CREATE TABLE `turnos` (
  `id_turno` int(11) NOT NULL,
  `nombre_turno` varchar(50) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `turnos` VALUES (1, 'Mañana', '06:00:00', '14:00:00', 1), (2, 'Tarde', '14:00:00', '22:00:00', 1), (3, 'Noche', '22:00:00', '06:00:00', 1);

-- --------------------------------------------------------

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

INSERT INTO `usuarios` (`id_usuario`, `codigo_usuario`, `nombre_completo`, `usuario`, `password`, `rol`, `area`, `estado`, `fecha_creacion`, `ultimo_acceso`) VALUES (2, 'ADMIN001', 'Administrador del Sistema', 'admin', '$2y$10$jmbV/zRpoGYAQNdNC99Y7u/liKAEWFV2VBCCvWjoeZ7M2Cx71QHu2', 'admin', 'SISTEMAS', 'activo', '2025-11-04 00:35:39', '2025-11-22 11:51:52'), (3, 'TEJ001', 'Cosme Morales', 'cosme', '$2y$10$jmbV/zRpoGYAQNdNC99Y7u/liKAEWFV2VBCCvWjoeZ7M2Cx71QHu2', 'tejedor', 'TEJEDURIA', 'activo', '2025-11-15 16:00:09', NULL), (4, 'TEJ002', 'Maria Condori', 'maria', '$2y$10$jmbV/zRpoGYAQNdNC99Y7u/liKAEWFV2VBCCvWjoeZ7M2Cx71QHu2', 'tejedor', 'TEJEDURIA', 'activo', '2025-11-15 16:00:09', NULL), (5, 'TEJ003', 'Juan Mamani', 'juan', '$2y$10$jmbV/zRpoGYAQNdNC99Y7u/liKAEWFV2VBCCvWjoeZ7M2Cx71QHu2', 'tejedor', 'TEJEDURIA', 'activo', '2025-11-15 16:00:09', NULL);

-- --------------------------------------------------------
-- VISTAS REPARADAS
-- --------------------------------------------------------

CREATE TABLE `v_detalle_revisado_completo` ( `dummy_col` INT );
CREATE TABLE `v_inventario_actual` ( `id_inventario` int(11), `id_producto` int(11), `codigo_producto` varchar(30), `descripcion_completa` varchar(200), `nombre_linea` varchar(50), `codigo_linea` varchar(20), `nombre_tipo` varchar(50), `tipo_inventario` enum('tejido','revisado','vaporizado','preteñido','teñido'), `docenas` int(11), `unidades` int(11), `total_unidades_calculado` int(11), `fecha_actualizacion` timestamp, `peso_estimado_kg` decimal(19,2) );
CREATE TABLE `v_movimientos_detallado` ( `dummy_col` INT );
CREATE TABLE `v_revisado_pendiente_vaporizar` ( `id_inventario` int(11), `id_producto` int(11), `codigo_producto` varchar(30), `descripcion_completa` varchar(200), `talla` varchar(20), `nombre_linea` varchar(50), `codigo_linea` varchar(20), `docenas` int(11), `unidades` int(11), `total_unidades_calculado` int(11), `fecha_actualizacion` timestamp );

DROP TABLE IF EXISTS `v_detalle_revisado_completo`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_detalle_revisado_completo` AS 
SELECT 
    `r`.`id_revisado` AS `id_revisado`, 
    `r`.`codigo_lote_revisado` AS `codigo_lote_revisado`, 
    `r`.`fecha_revisado` AS `fecha_revisado`, 
    `t`.`nombre_turno` AS `nombre_turno`, 
    `d`.`id_detalle_revisado` AS `id_detalle_revisado`, 
    `d`.`id_producto` AS `id_producto`, 
    `p`.`codigo_producto` AS `codigo_producto`, 
    `p`.`descripcion_completa` AS `descripcion_completa`, 
    `l`.`nombre_linea` AS `nombre_linea`, 
    `u`.`nombre_completo` AS `revisadora`, 
    `d`.`docenas_primera` AS `docenas_revisadas`, 
    `d`.`unidades_primera` AS `unidades_revisadas`, 
    (`d`.`docenas_primera` * 12 + `d`.`unidades_primera`) AS `total_unidades_calculado`, 
    'primera' AS `calidad`, 
    NULL AS `va_vaporizado`, 
    `d`.`observaciones` AS `observaciones` 
FROM (((((`produccion_revisado_crudo` `r` 
    JOIN `detalle_revisado_crudo` `d` ON(`r`.`id_revisado` = `d`.`id_revisado`)) 
    JOIN `productos_tejidos` `p` ON(`d`.`id_producto` = `p`.`id_producto`)) 
    JOIN `lineas_producto` `l` ON(`p`.`id_linea` = `l`.`id_linea`)) 
    LEFT JOIN `usuarios` `u` ON(`r`.`id_revisor` = `u`.`id_usuario`)) 
    JOIN `turnos` `t` ON(`r`.`id_turno` = `t`.`id_turno`)) 
ORDER BY `r`.`fecha_revisado` DESC, `r`.`id_revisado` DESC;

DROP TABLE IF EXISTS `v_inventario_actual`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_inventario_actual` AS SELECT `i`.`id_inventario` AS `id_inventario`, `i`.`id_producto` AS `id_producto`, `p`.`codigo_producto` AS `codigo_producto`, `p`.`descripcion_completa` AS `descripcion_completa`, `l`.`nombre_linea` AS `nombre_linea`, `l`.`codigo_linea` AS `codigo_linea`, `tp`.`nombre_tipo` AS `nombre_tipo`, `i`.`tipo_inventario` AS `tipo_inventario`, `i`.`docenas` AS `docenas`, `i`.`unidades` AS `unidades`, `i`.`total_unidades_calculado` AS `total_unidades_calculado`, `i`.`fecha_actualizacion` AS `fecha_actualizacion`, CASE WHEN `p`.`peso_promedio_docena` is not null THEN round(`i`.`total_unidades_calculado` / 12 * `p`.`peso_promedio_docena`,2) ELSE NULL END AS `peso_estimado_kg` FROM (((`inventario_intermedio` `i` join `productos_tejidos` `p` on(`i`.`id_producto` = `p`.`id_producto`)) join `lineas_producto` `l` on(`p`.`id_linea` = `l`.`id_linea`)) join `tipos_producto` `tp` on(`p`.`id_tipo_producto` = `tp`.`id_tipo_producto`)) WHERE `p`.`activo` = 1 ;

DROP TABLE IF EXISTS `v_movimientos_detallado`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_movimientos_detallado` AS SELECT `m`.`id_movimiento` AS `id_movimiento`, `m`.`tipo_movimiento` AS `tipo_movimiento`, `m`.`tipo_inventario` AS `tipo_inventario`, `m`.`docenas` AS `docenas`, `m`.`unidades` AS `unidades`, `m`.`total_unidades_calculado` AS `total_unidades_calculado`, `m`.`origen` AS `origen`, `m`.`destino` AS `destino`, `m`.`fecha_movimiento` AS `fecha_movimiento`, `m`.`observaciones` AS `observaciones`, `p`.`codigo_producto` AS `codigo_producto`, `p`.`descripcion_completa` AS `descripcion_completa`, `l`.`nombre_linea` AS `nombre_linea`, `l`.`codigo_linea` AS `codigo_linea`, `u`.`nombre_completo` AS `responsable`, `u`.`rol` AS `rol_responsable`, `m`.`id_produccion` AS `id_produccion`, CASE WHEN `m`.`id_produccion` is not null THEN `pr`.`codigo_lote` ELSE NULL END AS `lote_origen` FROM ((((`movimientos_inventario` `m` join `productos_tejidos` `p` on(`m`.`id_producto` = `p`.`id_producto`)) join `lineas_producto` `l` on(`p`.`id_linea` = `l`.`id_linea`)) join `usuarios` `u` on(`m`.`id_usuario` = `u`.`id_usuario`)) left join `produccion_tejeduria` `pr` on(`m`.`id_produccion` = `pr`.`id_produccion`)) ORDER BY `m`.`fecha_movimiento` DESC ;

DROP TABLE IF EXISTS `v_revisado_pendiente_vaporizar`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_revisado_pendiente_vaporizar` AS SELECT `i`.`id_inventario` AS `id_inventario`, `i`.`id_producto` AS `id_producto`, `p`.`codigo_producto` AS `codigo_producto`, `p`.`descripcion_completa` AS `descripcion_completa`, `p`.`talla` AS `talla`, `l`.`nombre_linea` AS `nombre_linea`, `l`.`codigo_linea` AS `codigo_linea`, `i`.`docenas` AS `docenas`, `i`.`unidades` AS `unidades`, `i`.`total_unidades_calculado` AS `total_unidades_calculado`, `i`.`fecha_actualizacion` AS `fecha_actualizacion` FROM ((`inventario_intermedio` `i` join `productos_tejidos` `p` on(`i`.`id_producto` = `p`.`id_producto`)) join `lineas_producto` `l` on(`p`.`id_linea` = `l`.`id_linea`)) WHERE `i`.`tipo_inventario` = 'revisado' AND `i`.`total_unidades_calculado` > 0 AND `p`.`activo` = 1 ORDER BY `l`.`nombre_linea` ASC, `p`.`descripcion_completa` ASC ;

-- --------------------------------------------------------
-- INDICES Y AUTOINCREMENTOS
-- --------------------------------------------------------

ALTER TABLE `colores` ADD PRIMARY KEY (`id_color`), ADD UNIQUE KEY `codigo_color` (`codigo_color`);
ALTER TABLE `detalle_plan_generico` ADD PRIMARY KEY (`id_detalle_generico`), ADD UNIQUE KEY `unique_plan_maquina` (`id_plan_generico`,`id_maquina`), ADD KEY `id_maquina` (`id_maquina`), ADD KEY `id_producto` (`id_producto`), ADD KEY `producto_nuevo` (`producto_nuevo`);
ALTER TABLE `detalle_produccion_tejeduria` ADD PRIMARY KEY (`id_detalle`), ADD KEY `idx_produccion` (`id_produccion`), ADD KEY `idx_maquina` (`id_maquina`), ADD KEY `idx_producto` (`id_producto`);
ALTER TABLE `detalle_produccion_vaporizado` ADD PRIMARY KEY (`id_detalle_vaporizado`), ADD KEY `id_produccion_vaporizado` (`id_produccion_vaporizado`), ADD KEY `id_producto` (`id_producto`);
ALTER TABLE `produccion_vaporizado` ADD PRIMARY KEY (`id_produccion_vaporizado`);
ALTER TABLE `detalle_revisado_crudo` ADD PRIMARY KEY (`id_detalle_revisado`), ADD KEY `idx_revisado` (`id_revisado`), ADD KEY `idx_producto` (`id_producto`);
ALTER TABLE `disenos` ADD PRIMARY KEY (`id_diseno`);
ALTER TABLE `insumos` ADD PRIMARY KEY (`id_insumo`), ADD UNIQUE KEY `codigo_insumo` (`codigo_insumo`), ADD KEY `id_tipo_insumo` (`id_tipo_insumo`);
ALTER TABLE `inventario_intermedio` ADD PRIMARY KEY (`id_inventario`), ADD UNIQUE KEY `unique_producto_tipo` (`id_producto`,`tipo_inventario`), ADD KEY `idx_tipo_inventario` (`tipo_inventario`), ADD KEY `idx_producto` (`id_producto`);
ALTER TABLE `lineas_producto` ADD PRIMARY KEY (`id_linea`), ADD UNIQUE KEY `codigo_linea` (`codigo_linea`);
ALTER TABLE `lotes_produccion` ADD PRIMARY KEY (`id_lote`), ADD UNIQUE KEY `codigo_lote` (`codigo_lote`), ADD KEY `id_plan` (`id_plan`), ADD KEY `id_producto` (`id_producto`);
ALTER TABLE `maquinas` ADD PRIMARY KEY (`id_maquina`), ADD UNIQUE KEY `numero_maquina` (`numero_maquina`), ADD KEY `idx_estado` (`estado`);
ALTER TABLE `movimientos_inventario` ADD PRIMARY KEY (`id_movimiento`), ADD KEY `id_usuario` (`id_usuario`), ADD KEY `idx_fecha` (`fecha_movimiento`), ADD KEY `idx_tipo_inventario` (`tipo_inventario`), ADD KEY `idx_producto` (`id_producto`), ADD KEY `idx_tipo_movimiento` (`tipo_movimiento`), ADD KEY `idx_produccion` (`id_produccion`);
ALTER TABLE `planes_semanales` ADD PRIMARY KEY (`id_plan`), ADD UNIQUE KEY `codigo_plan` (`codigo_plan`);
ALTER TABLE `plan_generico_tejido` ADD PRIMARY KEY (`id_plan_generico`), ADD UNIQUE KEY `codigo_plan_generico` (`codigo_plan_generico`);
ALTER TABLE `produccion_revisado_crudo` ADD PRIMARY KEY (`id_revisado`), ADD KEY `idx_fecha` (`fecha_revisado`), ADD KEY `idx_turno` (`id_turno`), ADD KEY `fk_revisado_revisor` (`id_revisor`);
ALTER TABLE `produccion_tejeduria` ADD PRIMARY KEY (`id_produccion`), ADD UNIQUE KEY `codigo_lote` (`codigo_lote`), ADD KEY `idx_fecha` (`fecha_produccion`), ADD KEY `idx_turno` (`id_turno`), ADD KEY `idx_tejedor` (`id_tejedor`);
ALTER TABLE `productos_tejidos` ADD PRIMARY KEY (`id_producto`), ADD UNIQUE KEY `codigo_producto` (`codigo_producto`), ADD KEY `id_tipo_producto` (`id_tipo_producto`), ADD KEY `id_diseno` (`id_diseno`), ADD KEY `idx_linea` (`id_linea`);
ALTER TABLE `producto_insumos` ADD PRIMARY KEY (`id_producto_insumo`), ADD UNIQUE KEY `unique_producto_insumo` (`id_producto`,`id_insumo`), ADD KEY `id_insumo` (`id_insumo`);
ALTER TABLE `tipos_insumo` ADD PRIMARY KEY (`id_tipo_insumo`);
ALTER TABLE `tipos_producto` ADD PRIMARY KEY (`id_tipo_producto`);
ALTER TABLE `turnos` ADD PRIMARY KEY (`id_turno`);
ALTER TABLE `usuarios` ADD PRIMARY KEY (`id_usuario`), ADD UNIQUE KEY `codigo_usuario` (`codigo_usuario`), ADD UNIQUE KEY `usuario` (`usuario`);

ALTER TABLE `colores` MODIFY `id_color` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
ALTER TABLE `detalle_plan_generico` MODIFY `id_detalle_generico` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
ALTER TABLE `detalle_produccion_tejeduria` MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;
ALTER TABLE `detalle_produccion_vaporizado` MODIFY `id_detalle_vaporizado` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `produccion_vaporizado` MODIFY `id_produccion_vaporizado` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `detalle_revisado_crudo` MODIFY `id_detalle_revisado` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `disenos` MODIFY `id_diseno` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `insumos` MODIFY `id_insumo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134;
ALTER TABLE `inventario_intermedio` MODIFY `id_inventario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
ALTER TABLE `lineas_producto` MODIFY `id_linea` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `lotes_produccion` MODIFY `id_lote` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `maquinas` MODIFY `id_maquina` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
ALTER TABLE `movimientos_inventario` MODIFY `id_movimiento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
ALTER TABLE `planes_semanales` MODIFY `id_plan` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `plan_generico_tejido` MODIFY `id_plan_generico` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `produccion_revisado_crudo` MODIFY `id_revisado` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `produccion_tejeduria` MODIFY `id_produccion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `productos_tejidos` MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;
ALTER TABLE `producto_insumos` MODIFY `id_producto_insumo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;
ALTER TABLE `tipos_insumo` MODIFY `id_tipo_insumo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `tipos_producto` MODIFY `id_tipo_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;
ALTER TABLE `turnos` MODIFY `id_turno` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `usuarios` MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `detalle_plan_generico` ADD CONSTRAINT `detalle_plan_generico_ibfk_1` FOREIGN KEY (`id_plan_generico`) REFERENCES `plan_generico_tejido` (`id_plan_generico`) ON DELETE CASCADE, ADD CONSTRAINT `detalle_plan_generico_ibfk_2` FOREIGN KEY (`id_maquina`) REFERENCES `maquinas` (`id_maquina`), ADD CONSTRAINT `detalle_plan_generico_ibfk_3` FOREIGN KEY (`id_producto`) REFERENCES `productos_tejidos` (`id_producto`), ADD CONSTRAINT `detalle_plan_generico_ibfk_4` FOREIGN KEY (`producto_nuevo`) REFERENCES `productos_tejidos` (`id_producto`);
ALTER TABLE `detalle_produccion_tejeduria` ADD CONSTRAINT `fk_detalle_maquina` FOREIGN KEY (`id_maquina`) REFERENCES `maquinas` (`id_maquina`), ADD CONSTRAINT `fk_detalle_produccion` FOREIGN KEY (`id_produccion`) REFERENCES `produccion_tejeduria` (`id_produccion`) ON DELETE CASCADE, ADD CONSTRAINT `fk_detalle_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos_tejidos` (`id_producto`);
ALTER TABLE `detalle_produccion_vaporizado` ADD CONSTRAINT `detalle_produccion_vaporizado_ibfk_1` FOREIGN KEY (`id_produccion_vaporizado`) REFERENCES `produccion_vaporizado` (`id_produccion_vaporizado`), ADD CONSTRAINT `detalle_produccion_vaporizado_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos_tejidos` (`id_producto`);
ALTER TABLE `detalle_revisado_crudo` ADD CONSTRAINT `fk_detalle_revisado` FOREIGN KEY (`id_revisado`) REFERENCES `produccion_revisado_crudo` (`id_revisado`) ON DELETE CASCADE, ADD CONSTRAINT `fk_detalle_revisado_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos_tejidos` (`id_producto`);
ALTER TABLE `insumos` ADD CONSTRAINT `insumos_ibfk_1` FOREIGN KEY (`id_tipo_insumo`) REFERENCES `tipos_insumo` (`id_tipo_insumo`);
ALTER TABLE `inventario_intermedio` ADD CONSTRAINT `inventario_intermedio_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos_tejidos` (`id_producto`);
ALTER TABLE `lotes_produccion` ADD CONSTRAINT `lotes_produccion_ibfk_1` FOREIGN KEY (`id_plan`) REFERENCES `planes_semanales` (`id_plan`) ON DELETE CASCADE, ADD CONSTRAINT `lotes_produccion_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos_tejidos` (`id_producto`);
ALTER TABLE `movimientos_inventario` ADD CONSTRAINT `movimientos_inventario_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos_tejidos` (`id_producto`), ADD CONSTRAINT `movimientos_inventario_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`), ADD CONSTRAINT `movimientos_inventario_ibfk_3` FOREIGN KEY (`id_produccion`) REFERENCES `produccion_tejeduria` (`id_produccion`) ON DELETE SET NULL;
ALTER TABLE `planes_semanales` ADD CONSTRAINT `planes_semanales_ibfk_1` FOREIGN KEY (`usuario_creacion`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `plan_generico_tejido` ADD CONSTRAINT `plan_generico_tejido_ibfk_1` FOREIGN KEY (`usuario_creacion`) REFERENCES `usuarios` (`id_usuario`);
ALTER TABLE `produccion_revisado_crudo` ADD CONSTRAINT `fk_revisado_revisor` FOREIGN KEY (`id_revisor`) REFERENCES `usuarios` (`id_usuario`), ADD CONSTRAINT `fk_revisado_turno` FOREIGN KEY (`id_turno`) REFERENCES `turnos` (`id_turno`);
ALTER TABLE `produccion_tejeduria` ADD CONSTRAINT `fk_produccion_tejedor` FOREIGN KEY (`id_tejedor`) REFERENCES `usuarios` (`id_usuario`), ADD CONSTRAINT `fk_produccion_turno` FOREIGN KEY (`id_turno`) REFERENCES `turnos` (`id_turno`);
ALTER TABLE `productos_tejidos` ADD CONSTRAINT `productos_tejidos_ibfk_1` FOREIGN KEY (`id_linea`) REFERENCES `lineas_producto` (`id_linea`), ADD CONSTRAINT `productos_tejidos_ibfk_2` FOREIGN KEY (`id_tipo_producto`) REFERENCES `tipos_producto` (`id_tipo_producto`), ADD CONSTRAINT `productos_tejidos_ibfk_3` FOREIGN KEY (`id_diseno`) REFERENCES `disenos` (`id_diseno`);
ALTER TABLE `producto_insumos` ADD CONSTRAINT `producto_insumos_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos_tejidos` (`id_producto`) ON DELETE CASCADE, ADD CONSTRAINT `producto_insumos_ibfk_2` FOREIGN KEY (`id_insumo`) REFERENCES `insumos` (`id_insumo`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;