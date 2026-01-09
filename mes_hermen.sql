-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 08-01-2026 a las 19:38:33
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

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_movimiento` (IN `p_id_producto` INT, IN `p_tipo_movimiento` VARCHAR(10), IN `p_tipo_inventario` VARCHAR(20), IN `p_docenas` INT, IN `p_unidades` INT, IN `p_origen` VARCHAR(100), IN `p_destino` VARCHAR(100), IN `p_id_usuario` INT, IN `p_id_produccion` INT, IN `p_observaciones` TEXT, OUT `p_resultado` VARCHAR(100), OUT `p_id_movimiento` INT)   BEGIN
    DECLARE v_total_unidades INT;
    DECLARE v_stock_actual INT;
    DECLARE v_nuevas_docenas INT;
    DECLARE v_nuevas_unidades INT;
    
    -- Variables para manejo de errores
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_resultado = 'ERROR: No se pudo registrar el movimiento';
        SET p_id_movimiento = NULL;
    END;
    
    START TRANSACTION;
    
    -- Validar unidades
    IF p_unidades < 0 OR p_unidades > 11 THEN
        SET p_resultado = 'ERROR: Unidades deben estar entre 0 y 11';
        SET p_id_movimiento = NULL;
        ROLLBACK;
    ELSE
        -- Calcular total de unidades
        SET v_total_unidades = (p_docenas * 12) + p_unidades;
        
        -- Si es SALIDA, validar que hay stock suficiente
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
                -- Registrar el movimiento
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
                
                -- Actualizar inventario (restar)
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
            -- Es ENTRADA
            -- Registrar el movimiento
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
            
            -- Actualizar inventario (sumar)
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

--
-- Funciones
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_calcular_cpp` (`p_stock_anterior` DECIMAL(12,4), `p_cpp_anterior` DECIMAL(14,4), `p_cantidad_entrada` DECIMAL(12,4), `p_costo_entrada` DECIMAL(14,4)) RETURNS DECIMAL(14,4) DETERMINISTIC BEGIN
    DECLARE valor_anterior DECIMAL(18,4);
    DECLARE valor_entrada DECIMAL(18,4);
    DECLARE stock_nuevo DECIMAL(12,4);
    DECLARE cpp_nuevo DECIMAL(14,4);
    
    -- Calcular valores
    SET valor_anterior = p_stock_anterior * p_cpp_anterior;
    SET valor_entrada = p_cantidad_entrada * p_costo_entrada;
    SET stock_nuevo = p_stock_anterior + p_cantidad_entrada;
    
    -- Calcular CPP nuevo
    IF stock_nuevo > 0 THEN
        SET cpp_nuevo = (valor_anterior + valor_entrada) / stock_nuevo;
    ELSE
        SET cpp_nuevo = 0;
    END IF;
    
    RETURN cpp_nuevo;
END$$

DELIMITER ;

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

--
-- Volcado de datos para la tabla `areas_produccion`
--

INSERT INTO `areas_produccion` (`id_area`, `codigo`, `nombre`, `descripcion`, `responsable`, `activo`, `fecha_creacion`) VALUES
(1, 'TEJEDURIA', 'Tejeduría', 'Área de tejido circular - 60 máquinas', NULL, 1, '2025-12-29 01:52:21'),
(2, 'REVISADO', 'Revisado Crudo', 'Control de calidad de tejido', NULL, 1, '2025-12-29 01:52:21'),
(3, 'VAPORIZADO', 'Vaporizado', 'Proceso de vaporizado de productos', NULL, 1, '2025-12-29 01:52:21'),
(4, 'CORTADO', 'Cortado', 'Corte de piezas para ensamblaje', NULL, 1, '2025-12-29 01:52:21'),
(5, 'COSTURA', 'Costura', 'Ensamblaje y costura de productos', NULL, 1, '2025-12-29 01:52:21'),
(6, 'PRE_TENIDO', 'Pre-Teñido', 'Preparación para tintorería', NULL, 1, '2025-12-29 01:52:21'),
(7, 'TENIDO', 'Teñido', 'Tintorería y coloración', NULL, 1, '2025-12-29 01:52:21'),
(8, 'REVISADO_TEN', 'Revisado Teñido', 'Control de calidad post-teñido', NULL, 1, '2025-12-29 01:52:21'),
(9, 'EMPAQUE', 'Empaque', 'Empaque de productos terminados', NULL, 1, '2025-12-29 01:52:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias_backup_20260105`
--

CREATE TABLE `categorias_backup_20260105` (
  `id_categoria` int(11) NOT NULL,
  `id_tipo_inventario` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `orden` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias_backup_20260105`
--

INSERT INTO `categorias_backup_20260105` (`id_categoria`, `id_tipo_inventario`, `codigo`, `nombre`, `descripcion`, `orden`, `activo`) VALUES
(1, 1, 'MP-HILO-POLI', 'Hilos de Poliamida', 'DTY, HTY, FDY, SP y variantes de poliamida', 1, 1),
(2, 1, 'MP-HILO-ALG', 'Hilos de Algodón', 'Hilos de algodón para calcetería y confección', 2, 1),
(3, 1, 'MP-ELAST', 'Elastico Poliamida Spandex', 'Fibras elásticas Lycra y Spandex', 1, 1),
(4, 1, 'MP-ELASTICO', 'Elásticos Básicos', 'Elásticos genéricos para pretinas y bordes', 4, 1),
(5, 1, 'MP-TELA', 'Telas', 'Telas para confección (algodón, etc.)', 5, 1),
(6, 2, 'CAQ-COLOR', 'Colorantes', 'Colorantes para teñido', 1, 1),
(7, 2, 'CAQ-FIJADOR', 'Fijadores', 'Fijadores de color', 2, 1),
(8, 2, 'CAQ-SUAVIZ', 'Suavizantes', 'Suavizantes para acabado', 3, 1),
(9, 2, 'CAQ-BLANQ', 'Blanqueadores', 'Blanqueadores ópticos', 4, 1),
(10, 2, 'CAQ-OTROS', 'Otros Químicos', 'Otros auxiliares químicos', 5, 1),
(11, 3, 'EMP-BOLSA', 'Bolsas', 'Bolsas individuales y de empaque', 1, 1),
(12, 3, 'EMP-CARTUL', 'Cartulinas / Centrales', 'Cartulinas y centrales de presentación', 2, 1),
(13, 3, 'EMP-CAJA', 'Cajas', 'Cajas de empaque y transporte', 3, 1),
(14, 3, 'EMP-ETIQ', 'Etiquetas Impresas', 'Etiquetas y stickers de identificación (código, precio)', 4, 1),
(15, 3, 'EMP-FILM', 'Film / Stretch', 'Film y stretch para embalaje', 5, 1),
(16, 3, 'EMP-CINTA', 'Cintas de Embalaje', 'Cintas adhesivas y de embalaje', 6, 1),
(17, 4, 'ACC-ETIQ-TEJ', 'Etiquetas Tejidas', 'Etiquetas tejidas con marca Hermen', 1, 1),
(18, 4, 'ACC-ELAST-TEJ', 'Elásticos Tejidos con Marca', 'Elásticos tejidos con logo/marca', 2, 1),
(19, 4, 'ACC-SESGO', 'Sesgos Elásticos', 'Sesgos elásticos para acabados', 3, 1),
(20, 4, 'ACC-PASADOR', 'Pasadores', 'Pasadores para ropa interior', 4, 1),
(21, 4, 'ACC-BROCHE', 'Broches / Ganchos', 'Broches, ganchos y cierres', 5, 1),
(22, 4, 'ACC-CINTA-DEC', 'Cintas Decorativas', 'Cintas decorativas y de acabado', 6, 1),
(23, 4, 'ACC-PARCHE', 'Parches / Gusset', 'Parches de algodón para pantymedias', 7, 1),
(24, 4, 'ACC-OTROS', 'Otros Accesorios', 'Otros accesorios de confección', 8, 1),
(25, 5, 'WIP-TEJIDO', 'Inventario Tejido', 'Producto después de tejeduría, antes de revisado', 1, 1),
(26, 5, 'WIP-VAPOR', 'Inventario Vaporizado', 'Producto después de vaporizado, antes de costura', 2, 1),
(27, 5, 'WIP-PRETEN', 'Inventario Pre-Teñido', 'Producto después de costura, antes de teñido', 3, 1),
(28, 5, 'WIP-TENIDO', 'Inventario Teñido', 'Producto después de teñido, antes de empaque', 4, 1),
(29, 5, 'WIP-CORTADO', 'Inventario Cortado', 'Piezas cortadas listas para costura (confección)', 5, 1),
(30, 6, 'PT-MEDIAS', 'Medias', 'Medias de todas las líneas', 1, 1),
(31, 6, 'PT-PANTY', 'Pantymedias', 'Pantymedias de todas las líneas', 2, 1),
(32, 6, 'PT-CAMIS', 'Camisetas Poliamida', 'Camisetas de poliamida', 3, 1),
(33, 6, 'PT-CALCET', 'Calcetines', 'Calcetines de algodón', 4, 1),
(34, 6, 'PT-LEGGINGS', 'Leggings', 'Leggings de algodón', 5, 1),
(35, 6, 'PT-INTERIOR', 'Ropa Interior', 'Ropa interior de confección', 6, 1),
(36, 6, 'PT-POLERAS', 'Poleras', 'Poleras de algodón', 7, 1),
(37, 7, 'REP-AGUJAS', 'Agujas', 'Agujas para máquinas circulares', 1, 1),
(38, 7, 'REP-PLATIN', 'Platinas', 'Platinas para máquinas', 2, 1),
(39, 7, 'REP-CORREA', 'Correas / Bandas', 'Correas y bandas de transmisión', 3, 1),
(40, 7, 'REP-RODAM', 'Rodamientos', 'Rodamientos y balineras', 4, 1),
(41, 7, 'REP-ELECT', 'Componentes Eléctricos', 'Componentes eléctricos y electrónicos', 5, 1),
(42, 7, 'REP-OTROS', 'Otros Repuestos', 'Otros repuestos y accesorios', 6, 1),
(43, 1, 'MP-SR', 'Spandex Recubierto', NULL, 1, 1),
(0, 7, 'REP-CILINDROS', 'Cilindros', 'Cilindros y partes rotativas', 2, 1),
(0, 7, 'REP-ENGRANAJES', 'Engranajes', 'Engranajes y transmisiones', 3, 1),
(0, 7, 'REP-RODAMIENTOS', 'Rodamientos', 'Rodamientos y cojinetes', 4, 1),
(0, 7, 'REP-CORREAS', 'Correas', 'Correas de transmisión', 5, 1),
(0, 7, 'REP-MOTORES', 'Motores', 'Motores eléctricos y partes', 6, 1),
(0, 7, 'REP-SENSORES', 'Sensores', 'Sensores y detectores', 7, 1),
(0, 7, 'REP-ELECTRICO', 'Componentes Eléctricos', 'Cables, conectores, interruptores', 8, 1),
(0, 7, 'REP-LUBRICANTES', 'Lubricantes', 'Aceites y lubricantes', 9, 1),
(0, 6, 'PT-POLI-MEDIAS', 'Medias Poliamida', 'Medias de poliamida terminadas', 1, 1),
(0, 6, 'PT-POLI-PANTY', 'Pantymedias Poliamida', 'Pantymedias de poliamida terminadas', 2, 1),
(0, 6, 'PT-POLI-CAMISETA', 'Camisetas Poliamida', 'Camisetas de poliamida terminadas', 3, 1),
(0, 6, 'PT-ALG-CALCETINES', 'Calcetines Algodón', 'Calcetines de algodón terminados', 4, 1),
(0, 6, 'PT-ALG-MEDIAS', 'Medias Algodón', 'Medias de algodón terminadas', 5, 1),
(0, 6, 'PT-ALG-PANTY', 'Pantymedias Algodón', 'Pantymedias de algodón terminadas', 6, 1),
(0, 6, 'PT-ALG-CALZAS', 'Calzas Algodón', 'Calzas de algodón terminadas', 7, 1),
(0, 6, 'PT-CONF-GENERAL', 'Confección General', 'Prendas de confección terminadas', 8, 1),
(0, 6, 'PT-SEGUNDA', 'Segunda Calidad', 'Productos de segunda calidad', 9, 1);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias_inventario`
--

INSERT INTO `categorias_inventario` (`id_categoria`, `id_tipo_inventario`, `codigo`, `nombre`, `descripcion`, `orden`, `activo`) VALUES
(1, 1, 'MP-HILO-POLI', 'Hilos de Poliamida', 'DTY, HTY, FDY, SP y variantes de poliamida', 1, 1),
(2, 1, 'MP-HILO-ALG', 'Hilos de Algodón', 'Hilos de algodón para calcetería y confección', 2, 1),
(3, 1, 'MP-ELAST', 'Elastico Poliamida Spandex', 'Fibras elásticas Lycra y Spandex', 1, 1),
(4, 1, 'MP-ELASTICO', 'Elásticos Básicos', 'Elásticos genéricos para pretinas y bordes', 4, 1),
(5, 1, 'MP-TELA', 'Telas', 'Telas para confección (algodón, etc.)', 5, 1),
(6, 2, 'CAQ-COLOR', 'Colorantes', 'Colorantes para teñido', 1, 1),
(7, 2, 'CAQ-FIJADOR', 'Fijadores', 'Fijadores de color', 2, 1),
(8, 2, 'CAQ-SUAVIZ', 'Suavizantes', 'Suavizantes para acabado', 3, 1),
(9, 2, 'CAQ-BLANQ', 'Blanqueadores', 'Blanqueadores ópticos', 4, 1),
(10, 2, 'CAQ-OTROS', 'Otros Químicos', 'Otros auxiliares químicos', 5, 1),
(11, 3, 'EMP-BOLSA', 'Bolsas', 'Bolsas individuales y de empaque', 1, 1),
(12, 3, 'EMP-CARTUL', 'Cartulinas / Centrales', 'Cartulinas y centrales de presentación', 2, 1),
(13, 3, 'EMP-CAJA', 'Cajas', 'Cajas de empaque y transporte', 3, 1),
(14, 3, 'EMP-ETIQ', 'Etiquetas Impresas', 'Etiquetas y stickers de identificación (código, precio)', 4, 1),
(15, 3, 'EMP-FILM', 'Film / Stretch', 'Film y stretch para embalaje', 5, 1),
(16, 3, 'EMP-CINTA', 'Cintas de Embalaje', 'Cintas adhesivas y de embalaje', 6, 1),
(17, 4, 'ACC-ETIQ-TEJ', 'Etiquetas Tejidas', 'Etiquetas tejidas con marca Hermen', 1, 1),
(18, 4, 'ACC-ELAST-TEJ', 'Elásticos Tejidos con Marca', 'Elásticos tejidos con logo/marca', 2, 1),
(19, 4, 'ACC-SESGO', 'Sesgos Elásticos', 'Sesgos elásticos para acabados', 3, 1),
(20, 4, 'ACC-PASADOR', 'Pasadores', 'Pasadores para ropa interior', 4, 1),
(21, 4, 'ACC-BROCHE', 'Broches / Ganchos', 'Broches, ganchos y cierres', 5, 1),
(22, 4, 'ACC-CINTA-DEC', 'Cintas Decorativas', 'Cintas decorativas y de acabado', 6, 1),
(23, 4, 'ACC-PARCHE', 'Parches / Gusset', 'Parches de algodón para pantymedias', 7, 1),
(24, 4, 'ACC-OTROS', 'Otros Accesorios', 'Otros accesorios de confección', 8, 1),
(25, 5, 'WIP-TEJIDO', 'Inventario Tejido', 'Producto después de tejeduría, antes de revisado', 1, 1),
(26, 5, 'WIP-VAPOR', 'Inventario Vaporizado', 'Producto después de vaporizado, antes de costura', 2, 1),
(27, 5, 'WIP-PRETEN', 'Inventario Pre-Teñido', 'Producto después de costura, antes de teñido', 3, 1),
(28, 5, 'WIP-TENIDO', 'Inventario Teñido', 'Producto después de teñido, antes de empaque', 4, 1),
(29, 5, 'WIP-CORTADO', 'Inventario Cortado', 'Piezas cortadas listas para costura (confección)', 5, 1),
(30, 6, 'PT-MEDIAS', 'Medias', 'Medias de todas las líneas', 1, 1),
(31, 6, 'PT-PANTY', 'Pantymedias', 'Pantymedias de todas las líneas', 2, 1),
(32, 6, 'PT-CAMIS', 'Camisetas Poliamida', 'Camisetas de poliamida', 3, 1),
(33, 6, 'PT-CALCET', 'Calcetines', 'Calcetines de algodón', 4, 1),
(34, 6, 'PT-LEGGINGS', 'Leggings', 'Leggings de algodón', 5, 1),
(35, 6, 'PT-INTERIOR', 'Ropa Interior', 'Ropa interior de confección', 6, 1),
(36, 6, 'PT-POLERAS', 'Poleras', 'Poleras de algodón', 7, 1),
(37, 7, 'REP-AGUJAS', 'Agujas', 'Agujas para máquinas circulares', 1, 1),
(38, 7, 'REP-PLATIN', 'Platinas', 'Platinas para máquinas', 2, 1),
(39, 7, 'REP-CORREA', 'Correas / Bandas', 'Correas y bandas de transmisión', 3, 1),
(40, 7, 'REP-RODAM', 'Rodamientos', 'Rodamientos y balineras', 4, 1),
(41, 7, 'REP-ELECT', 'Componentes Eléctricos', 'Componentes eléctricos y electrónicos', 5, 1),
(42, 7, 'REP-OTROS', 'Otros Repuestos', 'Otros repuestos y accesorios', 6, 1),
(43, 1, 'MP-SR', 'Spandex Recubierto', NULL, 1, 1),
(0, 7, 'REP-CILINDROS', 'Cilindros', 'Cilindros y partes rotativas', 2, 1),
(0, 7, 'REP-ENGRANAJES', 'Engranajes', 'Engranajes y transmisiones', 3, 1),
(0, 7, 'REP-RODAMIENTOS', 'Rodamientos', 'Rodamientos y cojinetes', 4, 1),
(0, 7, 'REP-CORREAS', 'Correas', 'Correas de transmisión', 5, 1),
(0, 7, 'REP-MOTORES', 'Motores', 'Motores eléctricos y partes', 6, 1),
(0, 7, 'REP-SENSORES', 'Sensores', 'Sensores y detectores', 7, 1),
(0, 7, 'REP-ELECTRICO', 'Componentes Eléctricos', 'Cables, conectores, interruptores', 8, 1),
(0, 7, 'REP-LUBRICANTES', 'Lubricantes', 'Aceites y lubricantes', 9, 1),
(0, 6, 'PT-POLI-MEDIAS', 'Medias Poliamida', 'Medias de poliamida terminadas', 1, 1),
(0, 6, 'PT-POLI-PANTY', 'Pantymedias Poliamida', 'Pantymedias de poliamida terminadas', 2, 1),
(0, 6, 'PT-POLI-CAMISETA', 'Camisetas Poliamida', 'Camisetas de poliamida terminadas', 3, 1),
(0, 6, 'PT-ALG-CALCETINES', 'Calcetines Algodón', 'Calcetines de algodón terminados', 4, 1),
(0, 6, 'PT-ALG-MEDIAS', 'Medias Algodón', 'Medias de algodón terminadas', 5, 1),
(0, 6, 'PT-ALG-PANTY', 'Pantymedias Algodón', 'Pantymedias de algodón terminadas', 6, 1),
(0, 6, 'PT-ALG-CALZAS', 'Calzas Algodón', 'Calzas de algodón terminadas', 7, 1),
(0, 6, 'PT-CONF-GENERAL', 'Confección General', 'Prendas de confección terminadas', 8, 1),
(0, 6, 'PT-SEGUNDA', 'Segunda Calidad', 'Productos de segunda calidad', 9, 1);

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

--
-- Volcado de datos para la tabla `colores`
--

INSERT INTO `colores` (`id_color`, `codigo_color`, `nombre_color`, `codigo_hex`, `orden`, `descripcion`, `activo`) VALUES
(1, 'NEGRO', 'Negro', NULL, 0, NULL, 1),
(2, 'COGNAC', 'Coñac', NULL, 0, NULL, 1),
(3, 'BEIGE', 'Beige', NULL, 0, NULL, 1),
(4, 'BLANCO', 'Blanco', NULL, 0, NULL, 1),
(5, 'GRIS', 'Gris', NULL, 0, NULL, 1),
(6, 'AZUL', 'Azul', NULL, 0, NULL, 1),
(7, 'VERDE', 'Verde', NULL, 0, NULL, 1),
(0, 'TABACO', 'Tabaco', NULL, 0, NULL, 1),
(0, 'ALMENDRA', 'Almendra', NULL, 0, NULL, 1),
(0, 'ACACIA', 'Acacia', NULL, 0, NULL, 1),
(0, 'TORCAZ', 'Torcaz', NULL, 0, NULL, 1),
(0, 'CENIZA', 'Ceniza', NULL, 0, NULL, 1),
(0, 'CALIPSO', 'Calipso', NULL, 0, NULL, 1),
(0, 'HUESO', 'Hueso', NULL, 0, NULL, 1),
(0, 'CARBON', 'Carbón', NULL, 0, NULL, 1),
(0, 'HUMO', 'Humo', NULL, 0, NULL, 1),
(0, 'GRAFITO', 'Grafito', NULL, 0, NULL, 1),
(0, 'AZUL-MARINO', 'Azul Marino', NULL, 0, NULL, 1),
(0, 'ROSADO', 'Rosado', NULL, 0, NULL, 1),
(0, 'ROJO', 'Rojo', NULL, 0, NULL, 1),
(0, 'AMARILLO', 'Amarillo', NULL, 0, NULL, 1),
(0, 'FUCSIA', 'Fucsia', NULL, 0, NULL, 1),
(0, 'CELESTE', 'Celeste', NULL, 0, NULL, 1),
(0, 'TABACO', 'Tabaco', NULL, 0, NULL, 1),
(0, 'ALMENDRA', 'Almendra', NULL, 0, NULL, 1),
(0, 'ACACIA', 'Acacia', NULL, 0, NULL, 1),
(0, 'TORCAZ', 'Torcaz', NULL, 0, NULL, 1),
(0, 'CENIZA', 'Ceniza', NULL, 0, NULL, 1),
(0, 'CALIPSO', 'Calipso', NULL, 0, NULL, 1),
(0, 'HUESO', 'Hueso', NULL, 0, NULL, 1),
(0, 'CARBON', 'Carbón', NULL, 0, NULL, 1),
(0, 'HUMO', 'Humo', NULL, 0, NULL, 1),
(0, 'GRAFITO', 'Grafito', NULL, 0, NULL, 1),
(0, 'AZUL-MARINO', 'Azul Marino', NULL, 0, NULL, 1),
(0, 'BLANCO', 'Blanco', NULL, 0, NULL, 1),
(0, 'ROSADO', 'Rosado', NULL, 0, NULL, 1),
(0, 'ROJO', 'Rojo', NULL, 0, NULL, 1),
(0, 'AMARILLO', 'Amarillo', NULL, 0, NULL, 1),
(0, 'FUCSIA', 'Fucsia', NULL, 0, NULL, 1),
(0, 'CELESTE', 'Celeste', NULL, 0, NULL, 1),
(0, 'TABACO', 'Tabaco', NULL, 0, NULL, 1),
(0, 'ALMENDRA', 'Almendra', NULL, 0, NULL, 1),
(0, 'ACACIA', 'Acacia', NULL, 0, NULL, 1),
(0, 'TORCAZ', 'Torcaz', NULL, 0, NULL, 1),
(0, 'CENIZA', 'Ceniza', NULL, 0, NULL, 1),
(0, 'CALIPSO', 'Calipso', NULL, 0, NULL, 1),
(0, 'HUESO', 'Hueso', NULL, 0, NULL, 1),
(0, 'CARBON', 'Carbón', NULL, 0, NULL, 1),
(0, 'HUMO', 'Humo', NULL, 0, NULL, 1),
(0, 'GRAFITO', 'Grafito', NULL, 0, NULL, 1),
(0, 'AZUL-MARINO', 'Azul Marino', NULL, 0, NULL, 1),
(0, 'BLANCO', 'Blanco', NULL, 0, NULL, 1),
(0, 'ROSADO', 'Rosado', NULL, 0, NULL, 1),
(0, 'ROJO', 'Rojo', NULL, 0, NULL, 1),
(0, 'AMARILLO', 'Amarillo', NULL, 0, NULL, 1),
(0, 'FUCSIA', 'Fucsia', NULL, 0, NULL, 1),
(0, 'CELESTE', 'Celeste', NULL, 0, NULL, 1),
(0, 'TABACO', 'Tabaco', NULL, 0, NULL, 1),
(0, 'ALMENDRA', 'Almendra', NULL, 0, NULL, 1),
(0, 'ACACIA', 'Acacia', NULL, 0, NULL, 1),
(0, 'TORCAZ', 'Torcaz', NULL, 0, NULL, 1),
(0, 'CENIZA', 'Ceniza', NULL, 0, NULL, 1),
(0, 'CALIPSO', 'Calipso', NULL, 0, NULL, 1),
(0, 'HUESO', 'Hueso', NULL, 0, NULL, 1),
(0, 'CARBON', 'Carbón', NULL, 0, NULL, 1),
(0, 'HUMO', 'Humo', NULL, 0, NULL, 1),
(0, 'GRAFITO', 'Grafito', NULL, 0, NULL, 1),
(0, 'AZUL-MARINO', 'Azul Marino', NULL, 0, NULL, 1),
(0, 'BLANCO', 'Blanco', NULL, 0, NULL, 1),
(0, 'ROSADO', 'Rosado', NULL, 0, NULL, 1),
(0, 'ROJO', 'Rojo', NULL, 0, NULL, 1),
(0, 'AMARILLO', 'Amarillo', NULL, 0, NULL, 1),
(0, 'FUCSIA', 'Fucsia', NULL, 0, NULL, 1),
(0, 'CELESTE', 'Celeste', NULL, 0, NULL, 1),
(0, 'TABACO', 'Tabaco', NULL, 0, NULL, 1),
(0, 'ALMENDRA', 'Almendra', NULL, 0, NULL, 1),
(0, 'ACACIA', 'Acacia', NULL, 0, NULL, 1),
(0, 'TORCAZ', 'Torcaz', NULL, 0, NULL, 1),
(0, 'CENIZA', 'Ceniza', NULL, 0, NULL, 1),
(0, 'CALIPSO', 'Calipso', NULL, 0, NULL, 1),
(0, 'HUESO', 'Hueso', NULL, 0, NULL, 1),
(0, 'CARBON', 'Carbón', NULL, 0, NULL, 1),
(0, 'HUMO', 'Humo', NULL, 0, NULL, 1),
(0, 'GRAFITO', 'Grafito', NULL, 0, NULL, 1),
(0, 'AZUL-MARINO', 'Azul Marino', NULL, 0, NULL, 1),
(0, 'BLANCO', 'Blanco', NULL, 0, NULL, 1),
(0, 'ROSADO', 'Rosado', NULL, 0, NULL, 1),
(0, 'ROJO', 'Rojo', NULL, 0, NULL, 1),
(0, 'AMARILLO', 'Amarillo', NULL, 0, NULL, 1),
(0, 'FUCSIA', 'Fucsia', NULL, 0, NULL, 1),
(0, 'CELESTE', 'Celeste', NULL, 0, NULL, 1);

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

--
-- Volcado de datos para la tabla `detalle_flujos`
--

INSERT INTO `detalle_flujos` (`id_detalle_flujo`, `id_flujo`, `id_etapa`, `orden_secuencia`, `es_obligatoria`, `permite_salto`, `etapa_alternativa`, `tiempo_estimado_minutos`, `requiere_inspeccion`, `observaciones`, `activo`) VALUES
(1, 1, 1, 1, 1, 0, NULL, 45, 0, NULL, 1),
(2, 1, 2, 2, 1, 0, NULL, 15, 1, NULL, 1),
(3, 1, 3, 3, 1, 0, NULL, 35, 0, NULL, 1),
(4, 1, 4, 4, 1, 0, NULL, 10, 0, NULL, 1),
(5, 1, 17, 5, 1, 0, NULL, NULL, 0, NULL, 1),
(6, 1, 9, 6, 1, 0, NULL, 20, 0, NULL, 1),
(7, 1, 10, 7, 1, 0, NULL, 90, 0, NULL, 1),
(8, 1, 11, 8, 1, 0, NULL, 15, 1, NULL, 1),
(9, 1, 12, 9, 1, 0, NULL, 10, 0, NULL, 1),
(16, 2, 1, 1, 1, 0, NULL, 45, 0, NULL, 1),
(17, 2, 2, 2, 1, 0, NULL, 15, 1, NULL, 1),
(18, 2, 3, 3, 1, 0, NULL, 35, 0, NULL, 1),
(19, 2, 4, 4, 1, 0, NULL, 10, 0, NULL, 1),
(20, 2, 17, 5, 1, 0, NULL, NULL, 0, NULL, 1),
(21, 2, 7, 6, 1, 0, NULL, 8, 0, NULL, 1),
(22, 2, 6, 7, 1, 0, NULL, 15, 0, NULL, 1),
(23, 2, 9, 8, 1, 0, NULL, 20, 0, NULL, 1),
(24, 2, 10, 9, 1, 0, NULL, 90, 0, NULL, 1),
(25, 2, 11, 10, 1, 0, NULL, 15, 1, NULL, 1),
(26, 2, 12, 11, 1, 0, NULL, 10, 0, NULL, 1),
(31, 3, 1, 1, 1, 0, NULL, 40, 0, NULL, 1),
(32, 3, 2, 2, 1, 0, NULL, 15, 1, NULL, 1),
(33, 3, 3, 3, 1, 0, NULL, 35, 0, NULL, 1),
(34, 3, 17, 4, 1, 0, NULL, NULL, 0, NULL, 1),
(35, 3, 7, 5, 1, 0, NULL, 12, 0, NULL, 1),
(36, 3, 5, 6, 1, 0, NULL, 15, 0, NULL, 1),
(37, 3, 6, 7, 1, 0, NULL, 20, 0, NULL, 1),
(38, 3, 9, 8, 1, 0, NULL, 20, 0, NULL, 1),
(39, 3, 10, 9, 1, 0, NULL, 90, 0, NULL, 1),
(40, 3, 11, 10, 1, 0, NULL, 15, 1, NULL, 1),
(46, 3, 12, 11, 1, 0, NULL, 10, 0, NULL, 1),
(47, 4, 1, 1, 1, 0, NULL, 35, 0, NULL, 1),
(48, 4, 2, 2, 1, 0, NULL, 12, 1, NULL, 1),
(49, 4, 13, 3, 1, 0, NULL, 8, 0, NULL, 1),
(50, 4, 15, 4, 1, 0, NULL, 5, 0, NULL, 1),
(51, 4, 12, 5, 1, 0, NULL, 8, 0, NULL, 1),
(54, 5, 1, 1, 1, 0, NULL, 40, 0, NULL, 1),
(55, 5, 2, 2, 1, 0, NULL, 15, 1, NULL, 1),
(56, 5, 13, 3, 1, 0, NULL, 10, 0, NULL, 1),
(57, 5, 17, 4, 1, 0, NULL, NULL, 0, NULL, 1),
(58, 5, 6, 5, 1, 0, NULL, 15, 0, NULL, 1),
(59, 5, 14, 6, 1, 0, NULL, 25, 0, NULL, 1),
(60, 5, 11, 7, 1, 0, NULL, 12, 1, NULL, 1),
(61, 5, 15, 8, 1, 0, NULL, 5, 0, NULL, 1),
(62, 5, 12, 9, 1, 0, NULL, 10, 0, NULL, 1),
(69, 6, 1, 1, 1, 0, NULL, 50, 0, NULL, 1),
(70, 6, 2, 2, 1, 0, NULL, 15, 1, NULL, 1),
(71, 6, 17, 3, 1, 0, NULL, NULL, 0, NULL, 1),
(72, 6, 7, 4, 1, 0, NULL, 10, 0, NULL, 1),
(73, 6, 6, 5, 1, 0, NULL, 20, 0, NULL, 1),
(74, 6, 11, 6, 1, 0, NULL, 12, 1, NULL, 1),
(75, 6, 15, 7, 1, 0, NULL, 5, 0, NULL, 1),
(76, 6, 12, 8, 1, 0, NULL, 10, 0, NULL, 1),
(84, 7, 8, 1, 1, 0, NULL, 15, 0, NULL, 1),
(85, 7, 5, 2, 1, 0, NULL, 25, 0, NULL, 1),
(86, 7, 6, 3, 1, 0, NULL, 30, 0, NULL, 1),
(87, 7, 11, 4, 1, 0, NULL, 10, 1, NULL, 1),
(88, 7, 16, 5, 1, 0, NULL, 8, 0, NULL, 1),
(89, 7, 12, 6, 1, 0, NULL, 10, 0, NULL, 1);

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

--
-- Volcado de datos para la tabla `detalle_plan_generico`
--

INSERT INTO `detalle_plan_generico` (`id_detalle_generico`, `id_plan_generico`, `id_maquina`, `id_producto`, `accion`, `producto_nuevo`, `cantidad_objetivo_docenas`, `observaciones`) VALUES
(1, 1, 1, 19, 'mantener', NULL, NULL, ''),
(2, 1, 2, 57, 'cambiar', 56, 50, ''),
(3, 1, 3, 39, 'mantener', NULL, NULL, ''),
(4, 1, 4, 43, 'mantener', NULL, NULL, ''),
(5, 1, 7, 11, 'mantener', NULL, NULL, ''),
(6, 2, 1, 19, 'cambiar', 41, 100, ''),
(7, 2, 2, 57, 'mantener', NULL, NULL, ''),
(8, 2, 3, 39, 'mantener', NULL, NULL, ''),
(9, 2, 4, 43, 'cambiar', 19, 200, ''),
(10, 2, 7, 7, 'mantener', NULL, NULL, ''),
(11, 2, 8, 12, 'mantener', NULL, NULL, ''),
(12, 2, 9, 51, 'mantener', NULL, NULL, ''),
(13, 2, 10, 53, 'mantener', NULL, NULL, ''),
(14, 2, 11, 65, 'mantener', NULL, NULL, ''),
(15, 2, 5, 56, 'mantener', NULL, NULL, '');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `detalle_produccion_tejeduria`
--

INSERT INTO `detalle_produccion_tejeduria` (`id_detalle`, `id_produccion`, `id_maquina`, `id_producto`, `docenas`, `unidades`) VALUES
(12, 2, 1, 19, 8, 0),
(13, 2, 2, 57, 8, 0),
(14, 2, 3, 39, 8, 0),
(15, 2, 4, 43, 8, 0),
(16, 2, 5, 56, 8, 0),
(17, 2, 6, 36, 8, 0),
(18, 2, 7, 7, 8, 0),
(19, 2, 8, 12, 8, 0),
(20, 2, 9, 51, 8, 0),
(21, 2, 10, 53, 8, 6),
(22, 2, 11, 65, 4, 6),
(33, 3, 2, 57, 8, 0),
(34, 3, 3, 39, 2, 0),
(35, 3, 4, 19, 4, 6),
(36, 3, 4, 43, 8, 0),
(37, 3, 5, 56, 10, 0),
(38, 3, 7, 7, 7, 6),
(39, 3, 8, 12, 8, 0),
(40, 3, 9, 51, 7, 0),
(41, 3, 10, 53, 3, 0),
(42, 3, 11, 65, 8, 0),
(53, 5, 2, 57, 3, 1),
(54, 5, 3, 39, 4, 2),
(55, 5, 4, 19, 2, 0),
(56, 5, 4, 43, 3, 0),
(57, 5, 5, 56, 2, 0),
(58, 5, 7, 7, 2, 0),
(59, 5, 8, 12, 2, 0),
(60, 5, 9, 51, 2, 0),
(61, 5, 10, 53, 2, 0),
(62, 5, 11, 65, 3, 0),
(63, 4, 2, 57, 4, 0),
(64, 4, 3, 39, 3, 0),
(65, 4, 4, 19, 8, 0),
(66, 4, 4, 43, 9, 0),
(67, 4, 5, 56, 10, 10),
(68, 4, 7, 7, 7, 9),
(69, 4, 8, 12, 15, 2),
(70, 4, 8, 51, 5, 0),
(71, 4, 10, 53, 8, 0),
(72, 4, 11, 65, 10, 0);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

--
-- Volcado de datos para la tabla `disenos`
--

INSERT INTO `disenos` (`id_diseno`, `nombre_diseno`, `descripcion`, `activo`) VALUES
(1, 'PUNTERA REFORZADA', 'Con refuerzo en la puntera', 1),
(2, 'SIN PUNTERA', 'Sin puntera reforzada', 1),
(3, 'NUDA', 'Diseño nudo/transparente', 1),
(4, 'BASICO', 'Diseño básico estándar', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos_inventario`
--

CREATE TABLE `documentos_inventario` (
  `id_documento` int(11) NOT NULL,
  `tipo_documento` varchar(50) DEFAULT NULL,
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
  `estado` varchar(20) DEFAULT NULL,
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

--
-- Volcado de datos para la tabla `etapas_produccion`
--

INSERT INTO `etapas_produccion` (`id_etapa`, `codigo_etapa`, `nombre_etapa`, `descripcion`, `area_responsable`, `tipo_etapa`, `requiere_maquina`, `requiere_operario`, `permite_rechazo`, `afecta_inventario_wip`, `icono`, `color_badge`, `orden_visual`, `activo`, `fecha_creacion`) VALUES
(1, 'TEJIDO', 'Tejido', 'Tejido en máquinas circulares', 'Tejeduría', 'PRODUCCION', 1, 1, 1, 1, 'fa-industry', '#007bff', 1, 1, '2025-12-19 13:40:49'),
(2, 'REVISADO_CRUDO', 'Revisado Crudo', 'Revisión en hormas antes de vaporizado', 'Control Calidad', 'CONTROL_CALIDAD', 0, 1, 1, 1, 'fa-search', '#28a745', 2, 1, '2025-12-19 13:40:49'),
(3, 'VAPORIZADO', 'Vaporizado', 'Proceso de vapor para compactar fibra', 'Vaporizado', 'PROCESO', 1, 1, 0, 1, 'fa-cloud', '#17a2b8', 3, 1, '2025-12-19 13:40:49'),
(4, 'COSTURA_PUNTA', 'Costura de Puntera', 'Costura de la punta del producto', 'Costura', 'PRODUCCION', 1, 1, 1, 1, 'fa-scissors', '#ffc107', 4, 1, '2025-12-19 13:40:49'),
(5, 'COSTURA_CUERPO', 'Costura de Cuerpo', 'Unión del cuerpo del producto', 'Costura', 'PRODUCCION', 1, 1, 1, 1, 'fa-cut', '#fd7e14', 5, 1, '2025-12-19 13:40:49'),
(6, 'COSTURA_ENSAMBLAJE', 'Costura Ensamblaje Final', 'Ensamblaje de todas las partes', 'Costura', 'ENSAMBLE', 1, 1, 1, 1, 'fa-puzzle-piece', '#e83e8c', 6, 1, '2025-12-19 13:40:49'),
(7, 'CORTE', 'Corte', 'Corte desde elástico hasta parche (pantymedias)', 'Corte', 'PROCESO', 0, 1, 1, 1, 'fa-cut', '#dc3545', 7, 1, '2025-12-19 13:40:49'),
(8, 'CORTE_TELA', 'Corte de Tela', 'Corte de piezas de tela (confección)', 'Corte', 'PROCESO', 0, 1, 1, 1, 'fa-cut', '#dc3545', 8, 1, '2025-12-19 13:40:49'),
(9, 'PRE_TENIDO', 'Pre-Teñido', 'Recepción y preparación para teñido', 'Tintorería', 'PROCESO', 0, 1, 1, 1, 'fa-folder-open', '#6f42c1', 9, 1, '2025-12-19 13:40:49'),
(10, 'TENIDO', 'Teñido', 'Proceso de teñido en barcas', 'Tintorería', 'PROCESO', 1, 1, 1, 1, 'fa-tint', '#6610f2', 10, 1, '2025-12-19 13:40:49'),
(11, 'REVISADO_TENIDO', 'Revisado Teñido', 'Control de calidad post-teñido', 'Control Calidad', 'CONTROL_CALIDAD', 0, 1, 1, 1, 'fa-check-circle', '#20c997', 11, 1, '2025-12-19 13:40:49'),
(12, 'EMPAQUE', 'Empaque', 'Empaque individual con etiqueta', 'Empaque', 'ACABADO', 0, 1, 0, 1, 'fa-box', '#6c757d', 12, 1, '2025-12-19 13:40:49'),
(13, 'CERRADO_PUNTA', 'Cerrado de Punta', 'Cerrado de punta (específico algodón)', 'Costura', 'PRODUCCION', 1, 1, 1, 1, 'fa-circle', '#ffc107', 13, 1, '2025-12-19 13:40:49'),
(14, 'HORMADO', 'Hormado', 'Hormado y definición de forma', 'Hormado', 'PROCESO', 1, 1, 0, 1, 'fa-th-large', '#17a2b8', 14, 1, '2025-12-19 13:40:49'),
(15, 'APAREADO', 'Apareado', 'Emparejamiento de unidades', 'Empaque', 'PROCESO', 0, 1, 0, 1, 'fa-layer-group', '#28a745', 15, 1, '2025-12-19 13:40:49'),
(16, 'PLANCHADO', 'Planchado', 'Planchado de prendas', 'Acabado', 'ACABADO', 1, 1, 0, 1, 'fa-fire', '#fd7e14', 16, 1, '2025-12-19 13:40:49'),
(17, 'ESPERA_LOTE', 'Espera de Asignación a Lote', 'Productos terminados esperando plan semanal', 'Almacén Intermedio', 'ALMACEN', 0, 0, 0, 1, 'fa-clock', '#6c757d', 99, 1, '2025-12-19 13:40:49');

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

--
-- Volcado de datos para la tabla `flujos_produccion`
--

INSERT INTO `flujos_produccion` (`id_flujo`, `codigo_flujo`, `nombre_flujo`, `descripcion`, `linea_produccion`, `tipo_flujo`, `total_etapas`, `tiempo_estimado_horas`, `requiere_corte`, `requiere_ensamble`, `requiere_tenido`, `diagrama_url`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'POLI_MEDIAS', 'Flujo Medias Poliamida', 'Flujo para producción de medias: sin corte ni ensamblaje adicional, solo costura de puntera', 'POLIAMIDA', 'Medias', 9, NULL, 0, 0, 1, NULL, 1, '2025-12-19 13:41:35', '2025-12-19 13:41:35'),
(2, 'POLI_PANTY', 'Flujo Pantymedias Poliamida', 'Flujo para pantymedias: incluye corte y ensamblaje de piernas + parche', 'POLIAMIDA', 'Pantymedias', 11, NULL, 1, 1, 1, NULL, 1, '2025-12-19 13:41:35', '2025-12-19 13:41:35'),
(3, 'POLI_CAMISETA', 'Flujo Camisetas Poliamida', 'Flujo para camisetas: incluye corte de cuerpo tubular y ensamblaje de partes', 'POLIAMIDA', 'Camisetas', 11, NULL, 1, 1, 1, NULL, 1, '2025-12-19 13:41:35', '2025-12-19 14:42:33'),
(4, 'ALG_CALCETINES', 'Flujo Calcetines Algodón', 'Flujo simplificado para calcetines: tejido, cerrado, apareado', 'ALGODON', 'Calcetines', 5, NULL, 0, 0, 0, NULL, 1, '2025-12-19 13:41:35', '2025-12-19 13:41:35'),
(5, 'ALG_MEDIAS_PANTY', 'Flujo Medias/Pantymedias Algodón', 'Flujo completo con hormado para medias y pantymedias de algodón', 'ALGODON', 'Medias/Pantymedias', 9, NULL, 0, 1, 0, NULL, 1, '2025-12-19 13:41:35', '2025-12-19 13:41:35'),
(6, 'ALG_CALZAS', 'Flujo Calzas Algodón', 'Flujo para calzas: incluye corte y ensamblaje', 'ALGODON', 'Calzas', 8, NULL, 1, 1, 0, NULL, 1, '2025-12-19 13:41:35', '2025-12-19 13:41:35'),
(7, 'CONF_GENERAL', 'Flujo Confección General', 'Flujo para prendas de confección: corte tela, ensamblaje, acabado', 'CONFECCION', 'General', 6, NULL, 1, 1, 0, NULL, 1, '2025-12-19 13:41:35', '2025-12-19 13:41:35');

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

--
-- Volcado de datos para la tabla `insumos`
--

INSERT INTO `insumos` (`id_insumo`, `codigo_insumo`, `id_tipo_insumo`, `nombre_insumo`, `tipo_insumo`, `descripcion`, `unidad_medida`, `stock_minimo`, `stock_actual`, `precio_unitario`, `proveedor`, `activo`, `fecha_creacion`, `costo_unitario`) VALUES
(100, 'DTY-22-10F-S', 1, 'DTY 22 DTEX / 10 F, TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 50.00, NULL, NULL, 1, '2025-11-09 18:11:49', 81.97),
(101, 'DTY-22-10F-Z', 1, 'DTY 22 DTEX / 10 F, TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 50.00, NULL, NULL, 1, '2025-11-09 18:11:49', 81.97),
(102, 'DTY-44-12F-S', 1, 'DTY 44 DTEX / 12 F, TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 20.00, 100.00, NULL, NULL, 1, '2025-11-09 18:11:49', 52.84),
(103, 'DTY-44-12F-Z', 1, 'DTY 44 DTEX / 12 F, TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 20.00, 100.00, NULL, NULL, 1, '2025-11-09 18:11:49', 52.84),
(104, 'DTY-44-34F-S', 1, 'DTY 44 DTEX / 34 F, TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 15.00, 80.00, NULL, NULL, 1, '2025-11-09 18:11:49', 41.38),
(105, 'DTY-44-34F-Z', 1, 'DTY 44 DTEX / 34 F, TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 15.00, 80.00, NULL, NULL, 1, '2025-11-09 18:11:49', 41.38),
(106, 'DTY-78-24F-S', 1, 'DTY 78 DTEX / 24 F TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 25.00, 120.00, NULL, NULL, 1, '2025-11-09 18:11:49', 31.22),
(107, 'DTY-78-24F-Z', 1, 'DTY 78 DTEX / 24 F TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 25.00, 120.00, NULL, NULL, 1, '2025-11-09 18:11:49', 31.22),
(108, 'DTY-78-48F-S', 1, 'DTY 78 DTEX / 48 F TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 20.00, 100.00, NULL, NULL, 1, '2025-11-09 18:11:49', 32.17),
(109, 'DTY-78-48F-Z', 1, 'DTY 78 DTEX / 48 F TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 20.00, 100.00, NULL, NULL, 1, '2025-11-09 18:11:49', 32.17),
(110, 'DTY-78-68F-S', 1, 'DTY 78 DTEX / 68 F, TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 15.00, 60.00, NULL, NULL, 1, '2025-11-09 18:11:49', 45.01),
(111, 'DTY-78-68F-Z', 1, 'DTY 78 DTEX / 68 F, TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 15.00, 60.00, NULL, NULL, 1, '2025-11-09 18:11:49', 45.02),
(112, 'DTY-78-24F-X2', 1, 'DTY 78 DTEX / 24 F X 2', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 40.00, NULL, NULL, 1, '2025-11-09 18:11:49', 62.40),
(113, 'HTY-17-2F-S', 1, 'HTY 17 DTEX / 2 F, TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 30.00, NULL, NULL, 1, '2025-11-09 18:11:49', 62.40),
(114, 'HTY-17-2F-Z', 1, 'HTY 17 DTEX / 2 F, TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 30.00, NULL, NULL, 1, '2025-11-09 18:11:49', 53.86),
(115, 'HTY-15-1F-S', 1, 'HTY 15/1 F, TORSIÓN \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 8.00, 25.00, NULL, NULL, 1, '2025-11-09 18:11:49', 53.86),
(116, 'HTY-15-1F-Z', 1, 'HTY 15/1 F, TORSIÓN \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 8.00, 25.00, NULL, NULL, 1, '2025-11-09 18:11:49', 53.86),
(117, 'SP30-FDY12-S', 1, 'SP 30 + FDY 12/5F, TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 40.00, NULL, NULL, 1, '2025-11-09 18:11:49', 53.86),
(118, 'SP30-FDY12-Z', 1, 'SP 30 + FDY 12/5F, TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 40.00, NULL, NULL, 1, '2025-11-09 18:11:49', 120.48),
(119, 'SP40-FDY20-S', 1, 'SP 40 + FDY 20/7F, TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 35.00, NULL, NULL, 1, '2025-11-09 18:11:49', 120.48),
(120, 'SP40-FDY20-Z', 1, 'SP 40 + FDY 20/7F, TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 35.00, NULL, NULL, 1, '2025-11-09 18:11:49', 80.62),
(121, 'SP30-FDY12-X2', 1, 'SP 30+ FDY 12/5F * 2', 'HILO_POLIAMIDA', NULL, 'kilogramos', 8.00, 30.00, NULL, NULL, 1, '2025-11-09 18:11:49', 80.62),
(122, 'SP40-DTY40-S', 1, 'SP 40+ DTY 40/12F S TORSION \"S\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 12.00, 45.00, NULL, NULL, 1, '2025-11-09 18:11:49', 76.67),
(123, 'SP40-DTY40-Z', 1, 'SP 40+ DTY 40/12F S TORSION \"Z\"', 'HILO_POLIAMIDA', NULL, 'kilogramos', 12.00, 45.00, NULL, NULL, 1, '2025-11-09 18:11:49', 76.67),
(124, 'SP20-70DEN-NEG', 1, 'SP 20 + 70 DEN NEGRO A-270 (Z) NEGRO', 'HILO_POLIAMIDA', NULL, 'kilogramos', 5.00, 20.00, NULL, NULL, 1, '2025-11-09 18:11:49', 80.62),
(125, 'FDY-22-24F', 1, 'FDY RIGIDO 22 DTEX /24 F', 'HILO_POLIAMIDA', NULL, 'kilogramos', 15.00, 50.00, NULL, NULL, 1, '2025-11-09 18:11:49', 42.44),
(126, 'FDY-22-7F', 1, 'FDY RIGIDO 22DTEX / 7F', 'HILO_POLIAMIDA', NULL, 'kilogramos', 10.00, 30.00, NULL, NULL, 1, '2025-11-09 18:11:49', 95.00),
(127, 'FDY-15-3F', 1, 'FDY RIGIDO 15/3', 'HILO_POLIAMIDA', NULL, 'kilogramos', 12.00, 40.00, NULL, NULL, 1, '2025-11-09 18:11:49', 42.01),
(128, 'FDY-16-5F', 1, 'FDY RIGIDO 16/5', 'HILO_POLIAMIDA', NULL, 'kilogramos', 12.00, 40.00, NULL, NULL, 1, '2025-11-09 18:11:49', 42.44),
(129, 'FDY-44-12F', 1, 'FDY RIGIDO 44/12', 'HILO_POLIAMIDA', NULL, 'kilogramos', 15.00, 60.00, NULL, NULL, 1, '2025-11-09 18:11:49', 42.44),
(130, 'FDY-44-12F-BRIL', 1, 'FDY RIGIDO 44/12 BRILLO', 'HILO_POLIAMIDA', NULL, 'kilogramos', 15.00, 50.00, NULL, NULL, 1, '2025-11-09 18:11:49', 42.44),
(131, 'SPANDEX-100', 2, 'SPANDEX 100 DEN', 'LYCRA', NULL, 'kilogramos', 20.00, 80.00, NULL, NULL, 1, '2025-11-09 18:11:49', 108.00),
(132, 'SPANDEX-140', 2, 'SPANDEX 140 DEN', 'LYCRA', NULL, 'kilogramos', 20.00, 70.00, NULL, NULL, 1, '2025-11-09 18:11:49', 111.63),
(133, 'DTY-22-7F-S', 1, 'DTY 22 DTEX, 7 F, S', 'HILO_POLIAMIDA', '', 'kilogramos', 50.00, 150.00, NULL, '', 1, '2025-11-09 20:49:36', 45.80);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `inventarios`
--

INSERT INTO `inventarios` (`id_inventario`, `codigo`, `nombre`, `descripcion`, `id_tipo_inventario`, `id_categoria`, `id_subcategoria`, `id_linea_produccion`, `id_unidad`, `stock_actual`, `stock_minimo`, `stock_maximo`, `id_ubicacion`, `costo_unitario`, `costo_promedio`, `precio_venta`, `requiere_lote`, `es_inventariable`, `punto_reorden`, `talla`, `id_color`, `proveedor_principal`, `especificaciones`, `activo`, `fecha_creacion`, `fecha_actualizacion`, `creado_por`) VALUES
(1, 'MP-DTY-22-10F-S', 'DTY 22 DTEX / 10 F, TORSION \"S\"', '', 1, 1, 1, NULL, 2, 0.00, 10.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 100', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(2, 'MP-DTY-22-10F-Z', 'DTY 22 DTEX / 10 F, TORSION \"Z\"', '', 1, 1, 1, NULL, 2, 0.00, 10.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 101', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(3, 'MP-DTY-44-12F-S', 'DTY 44 DTEX / 12 F, TORSION \"S\"', '', 1, 1, 1, NULL, 2, 0.00, 20.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 102', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(4, 'MP-DTY-44-12F-Z', 'DTY 44 DTEX / 12 F, TORSION \"Z\"', '', 1, 1, 1, NULL, 2, 0.00, 20.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 103', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(5, 'MP-DTY-44-34F-S', 'DTY 44 DTEX / 34 F, TORSION \"S\"', '', 1, 1, 1, NULL, 2, 0.00, 15.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 104', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(6, 'MP-DTY-44-34F-Z', 'DTY 44 DTEX / 34 F, TORSION \"Z\"', '', 1, 1, 1, NULL, 2, 0.00, 15.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 105', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(7, 'MP-DTY-78-24F-S', 'DTY 78 DTEX / 24 F TORSION \"S\"', '', 1, 1, 1, NULL, 2, 0.00, 25.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 106', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(8, 'MP-DTY-78-24F-Z', 'DTY 78 DTEX / 24 F TORSION \"Z\"', '', 1, 1, 1, NULL, 2, 0.00, 25.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 107', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(9, 'MP-DTY-78-48F-S', 'DTY 78 DTEX / 48 F TORSION \"S\"', '', 1, 1, 1, NULL, 2, 0.00, 20.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 108', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(10, 'MP-DTY-78-48F-Z', 'DTY 78 DTEX / 48 F TORSION \"Z\"', '', 1, 1, 1, NULL, 2, 0.00, 20.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 109', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(11, 'MP-DTY-78-68F-S', 'DTY 78 DTEX / 68 F, TORSION \"S\"', '', 1, 1, 1, NULL, 2, 0.00, 15.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 110', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(12, 'MP-DTY-78-68F-Z', 'DTY 78 DTEX / 68 F, TORSION \"Z\"', '', 1, 1, 1, NULL, 2, 0.00, 15.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 111', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(13, 'MP-DTY-78-24F-X2', 'DTY 78 DTEX / 24 F X 2', '', 1, 1, 1, NULL, 2, 0.00, 10.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 112', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(14, 'MP-HTY-17-2F-S', 'HTY 17 DTEX / 2 F, TORSION \"S\"', '', 1, 1, 2, NULL, 2, 0.00, 10.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 113', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(15, 'MP-HTY-17-2F-Z', 'HTY 17 DTEX / 2 F, TORSION \"Z\"', '', 1, 1, 2, NULL, 2, 0.00, 10.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 114', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(16, 'MP-HTY-15-1F-S', 'HTY 15/1 F, TORSIÓN \"S\"', '', 1, 1, 2, NULL, 2, 0.00, 8.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 115', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(17, 'MP-HTY-15-1F-Z', 'HTY 15/1 F, TORSIÓN \"Z\"', '', 1, 1, 2, NULL, 2, 0.00, 8.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 116', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(18, 'MP-SP30-FDY12-S', 'SP 30 + FDY 12/5F, TORSION \"S\"', '', 1, 43, 5, NULL, 2, 0.00, 10.00, NULL, NULL, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 117', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(19, 'MP-SP30-FDY12-Z', 'SP 30 + FDY 12/5F, TORSION \"Z\"', '', 1, 43, 5, NULL, 2, 0.00, 10.00, NULL, NULL, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 118', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(20, 'MP-SP40-FDY20-S', 'SP 40 + FDY 20/7F, TORSION \"S\"', '', 1, 43, 5, NULL, 2, 0.00, 10.00, NULL, NULL, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 119', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(21, 'MP-SP40-FDY20-Z', 'SP 40 + FDY 20/7F, TORSION \"Z\"', '', 1, 43, 5, NULL, 2, 0.00, 10.00, NULL, NULL, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 120', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(22, 'MP-SP30-FDY12-X2', 'SP 30+ FDY 12/5F * 2', '', 1, 43, 5, NULL, 2, 0.00, 8.00, NULL, NULL, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 121', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(23, 'MP-SP40-DTY40-S', 'SP 40+ DTY 40/12F S TORSION \"S\"', '', 1, 43, 5, NULL, 2, 0.00, 12.00, NULL, NULL, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 122', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(24, 'MP-SP40-DTY40-Z', 'SP 40+ DTY 40/12F S TORSION \"Z\"', '', 1, 43, 5, NULL, 2, 0.00, 12.00, NULL, NULL, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 123', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(25, 'MP-SP20-70DEN-NEG', 'SP 20 + 70 DEN NEGRO A-270 (Z) NEGRO', '', 1, 43, 5, NULL, 2, 0.00, 5.00, NULL, NULL, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 124', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(26, 'MP-FDY-22-24F', 'FDY RIGIDO 22 DTEX /24 F', '', 1, 1, 3, NULL, 2, 0.00, 15.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 125', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(27, 'MP-FDY-22-7F', 'FDY RIGIDO 22DTEX / 7F', '', 1, 1, 3, NULL, 2, 0.00, 10.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 126', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(28, 'MP-FDY-15-3F', 'FDY RIGIDO 15/3', '', 1, 1, 3, NULL, 2, 0.00, 12.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 127', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(29, 'MP-FDY-16-5F', 'FDY RIGIDO 16/5', '', 1, 1, 3, NULL, 2, 0.00, 12.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 128', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(30, 'MP-FDY-44-12F', 'FDY RIGIDO 44/12', '', 1, 1, 3, NULL, 2, 0.00, 15.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 129', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(31, 'MP-FDY-44-12F-BRIL', 'FDY RIGIDO 44/12 BRILLO', '', 1, 1, 3, NULL, 2, 0.00, 15.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 130', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(32, 'MP-ELASTANO-100', 'ELASTANO 100 DEN', '', 1, 3, NULL, NULL, 2, 0.00, 20.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 131', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(33, 'MP-ELASTANO-140', 'SPANDEX 140 DEN', '', 1, 3, NULL, NULL, 2, 0.00, 20.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 132', 1, '2025-11-09 18:11:49', '2026-01-05 19:56:06', NULL),
(34, 'MP-DTY-22-7F-S', 'DTY 22 DTEX, 7 F, S', '', 1, 1, 1, NULL, 2, 0.00, 50.00, NULL, 1, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 133', 1, '2025-11-09 20:49:36', '2026-01-05 19:56:06', NULL),
(35, 'MP-REC-T-30-15-S', 'LYCRA RECUBIERTA TEXTURIZADA 30+20/15F-Z', '', 1, 43, 5, NULL, 2, 0.00, 250.00, NULL, NULL, 0.0000, 0.0000, NULL, 0, 1, NULL, NULL, NULL, '', NULL, 1, '2025-12-02 19:58:03', '2026-01-05 19:56:06', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventarios_backup_20260105`
--

CREATE TABLE `inventarios_backup_20260105` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `inventarios_backup_20260105`
--

INSERT INTO `inventarios_backup_20260105` (`id_inventario`, `codigo`, `nombre`, `descripcion`, `id_tipo_inventario`, `id_categoria`, `id_subcategoria`, `id_linea_produccion`, `id_unidad`, `stock_actual`, `stock_minimo`, `stock_maximo`, `id_ubicacion`, `costo_unitario`, `costo_promedio`, `precio_venta`, `requiere_lote`, `es_inventariable`, `punto_reorden`, `talla`, `id_color`, `proveedor_principal`, `especificaciones`, `activo`, `fecha_creacion`, `fecha_actualizacion`, `creado_por`) VALUES
(1, 'MP-DTY-22-10F-S', 'DTY 22 DTEX / 10 F, TORSION \"S\"', '', 1, 1, 1, NULL, 2, 1465.00, 10.00, NULL, 1, 21.7500, 47.2254, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 100', 1, '2025-11-09 18:11:49', '2025-12-28 01:36:30', NULL),
(2, 'MP-DTY-22-10F-Z', 'DTY 22 DTEX / 10 F, TORSION \"Z\"', '', 1, 1, 1, NULL, 2, 1300.00, 10.00, NULL, 1, 21.7500, 39.7399, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 101', 1, '2025-11-09 18:11:49', '2025-12-11 18:26:30', NULL),
(3, 'MP-DTY-44-12F-S', 'DTY 44 DTEX / 12 F, TORSION \"S\"', '', 1, 1, 1, NULL, 2, 1400.00, 20.00, NULL, 1, 20.0100, 58.5339, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 102', 1, '2025-11-09 18:11:49', '2025-12-27 21:49:22', NULL),
(4, 'MP-DTY-44-12F-Z', 'DTY 44 DTEX / 12 F, TORSION \"Z\"', '', 1, 1, 1, NULL, 2, 1256.50, 20.00, NULL, 1, 20.0100, 46.0693, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 103', 1, '2025-11-09 18:11:49', '2025-12-27 21:49:22', NULL),
(5, 'MP-DTY-44-34F-S', 'DTY 44 DTEX / 34 F, TORSION \"S\"', '', 1, 1, 1, NULL, 2, 80.00, 15.00, NULL, 1, 41.3800, 41.3800, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 104', 1, '2025-11-09 18:11:49', '2025-12-03 19:50:01', NULL),
(6, 'MP-DTY-44-34F-Z', 'DTY 44 DTEX / 34 F, TORSION \"Z\"', '', 1, 1, 1, NULL, 2, 80.00, 15.00, NULL, 1, 41.3800, 41.3800, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 105', 1, '2025-11-09 18:11:49', '2025-12-03 19:50:53', NULL),
(7, 'MP-DTY-78-24F-S', 'DTY 78 DTEX / 24 F TORSION \"S\"', '', 1, 1, 1, NULL, 2, 120.00, 25.00, NULL, 1, 31.2200, 31.2200, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 106', 1, '2025-11-09 18:11:49', '2025-12-03 19:51:45', NULL),
(8, 'MP-DTY-78-24F-Z', 'DTY 78 DTEX / 24 F TORSION \"Z\"', '', 1, 1, 1, NULL, 2, 200.00, 25.00, NULL, 1, 38.7320, 38.7320, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 107', 1, '2025-11-09 18:11:49', '2025-12-03 19:51:56', NULL),
(9, 'MP-DTY-78-48F-S', 'DTY 78 DTEX / 48 F TORSION \"S\"', '', 1, 1, 1, NULL, 2, 180.00, 20.00, NULL, 1, 40.0944, 40.0944, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 108', 1, '2025-11-09 18:11:49', '2025-12-03 19:59:54', NULL),
(10, 'MP-DTY-78-48F-Z', 'DTY 78 DTEX / 48 F TORSION \"Z\"', '', 1, 1, 1, NULL, 2, 100.00, 20.00, NULL, 1, 32.1700, 32.1700, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 109', 1, '2025-11-09 18:11:49', '2025-12-03 20:00:03', NULL),
(11, 'MP-DTY-78-68F-S', 'DTY 78 DTEX / 68 F, TORSION \"S\"', '', 1, 1, 1, NULL, 2, 60.00, 15.00, NULL, 1, 45.0100, 45.0100, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 110', 1, '2025-11-09 18:11:49', '2025-12-03 19:52:44', NULL),
(12, 'MP-DTY-78-68F-Z', 'DTY 78 DTEX / 68 F, TORSION \"Z\"', '', 1, 1, 1, NULL, 2, 60.00, 15.00, NULL, 1, 45.0200, 45.0200, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 111', 1, '2025-11-09 18:11:49', '2025-12-03 19:52:58', NULL),
(13, 'MP-DTY-78-24F-X2', 'DTY 78 DTEX / 24 F X 2', '', 1, 1, 1, NULL, 2, 40.00, 10.00, NULL, 1, 62.4000, 62.4000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 112', 1, '2025-11-09 18:11:49', '2025-12-03 19:52:07', NULL),
(14, 'MP-HTY-17-2F-S', 'HTY 17 DTEX / 2 F, TORSION \"S\"', '', 1, 1, 2, NULL, 2, 580.00, 10.00, NULL, 1, 14.5000, 66.1410, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 113', 1, '2025-11-09 18:11:49', '2025-12-14 03:42:09', NULL),
(15, 'MP-HTY-17-2F-Z', 'HTY 17 DTEX / 2 F, TORSION \"Z\"', '', 1, 1, 2, NULL, 2, 580.00, 10.00, NULL, 1, 14.5000, 62.6257, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 114', 1, '2025-11-09 18:11:49', '2025-12-14 03:42:09', NULL),
(16, 'MP-HTY-15-1F-S', 'HTY 15/1 F, TORSIÓN \"S\"', '', 1, 1, 2, NULL, 2, 420.00, 8.00, NULL, 1, 34.1667, 52.0869, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 115', 1, '2025-11-09 18:11:49', '2025-12-29 00:58:09', NULL),
(17, 'MP-HTY-15-1F-Z', 'HTY 15/1 F, TORSIÓN \"Z\"', '', 1, 1, 2, NULL, 2, 418.00, 8.00, NULL, 1, 34.1667, 52.0679, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 116', 1, '2025-11-09 18:11:49', '2025-12-29 00:58:09', NULL),
(18, 'MP-SP30-FDY12-S', 'SP 30 + FDY 12/5F, TORSION \"S\"', '', 1, 43, 5, NULL, 2, 650.00, 10.00, NULL, NULL, 30.0000, 31.2955, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 117', 1, '2025-11-09 18:11:49', '2025-12-26 01:52:17', NULL),
(19, 'MP-SP30-FDY12-Z', 'SP 30 + FDY 12/5F, TORSION \"Z\"', '', 1, 43, 5, NULL, 2, 660.00, 10.00, NULL, NULL, 29.0625, 50.0105, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 118', 1, '2025-11-09 18:11:49', '2025-12-26 01:52:17', NULL),
(20, 'MP-SP40-FDY20-S', 'SP 40 + FDY 20/7F, TORSION \"S\"', '', 1, 43, 5, NULL, 2, 34.50, 10.00, NULL, NULL, 120.4800, 120.4800, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 119', 1, '2025-11-09 18:11:49', '2025-12-27 02:26:40', NULL),
(21, 'MP-SP40-FDY20-Z', 'SP 40 + FDY 20/7F, TORSION \"Z\"', '', 1, 43, 5, NULL, 2, 35.00, 10.00, NULL, NULL, 80.6200, 80.6200, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 120', 1, '2025-11-09 18:11:49', '2025-12-10 19:42:11', NULL),
(22, 'MP-SP30-FDY12-X2', 'SP 30+ FDY 12/5F * 2', '', 1, 43, 5, NULL, 2, 30.00, 8.00, NULL, NULL, 80.6200, 80.6200, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 121', 1, '2025-11-09 18:11:49', '2025-12-10 19:41:51', NULL),
(23, 'MP-SP40-DTY40-S', 'SP 40+ DTY 40/12F S TORSION \"S\"', '', 1, 43, 5, NULL, 2, 44.00, 12.00, NULL, NULL, 76.6700, 76.6700, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 122', 1, '2025-11-09 18:11:49', '2025-12-27 02:24:53', NULL),
(24, 'MP-SP40-DTY40-Z', 'SP 40+ DTY 40/12F S TORSION \"Z\"', '', 1, 43, 5, NULL, 2, 44.00, 12.00, NULL, NULL, 76.6700, 76.6700, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 123', 1, '2025-11-09 18:11:49', '2025-12-27 02:24:53', NULL),
(25, 'MP-SP20-70DEN-NEG', 'SP 20 + 70 DEN NEGRO A-270 (Z) NEGRO', '', 1, 43, 5, NULL, 2, 20.00, 5.00, NULL, NULL, 80.6200, 80.6200, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 124', 1, '2025-11-09 18:11:49', '2025-12-10 19:40:57', NULL),
(26, 'MP-FDY-22-24F', 'FDY RIGIDO 22 DTEX /24 F', '', 1, 1, 3, NULL, 2, 200.00, 15.00, NULL, 1, 23.2000, 34.1943, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 125', 1, '2025-11-09 18:11:49', '2025-12-27 21:44:03', NULL),
(27, 'MP-FDY-22-7F', 'FDY RIGIDO 22DTEX / 7F', '', 1, 1, 3, NULL, 2, 180.00, 10.00, NULL, 1, 23.2000, 62.3636, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 126', 1, '2025-11-09 18:11:49', '2025-12-27 21:44:03', NULL),
(28, 'MP-FDY-15-3F', 'FDY RIGIDO 15/3', '', 1, 1, 3, NULL, 2, 50.00, 12.00, NULL, 1, 16.0000, 30.4500, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 127', 1, '2025-11-09 18:11:49', '2025-12-27 21:44:53', NULL),
(29, 'MP-FDY-16-5F', 'FDY RIGIDO 16/5', '', 1, 1, 3, NULL, 2, 20.00, 12.00, NULL, 1, 42.4400, 42.4400, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 128', 1, '2025-11-09 18:11:49', '2025-12-27 02:21:58', NULL),
(30, 'MP-FDY-44-12F', 'FDY RIGIDO 44/12', '', 1, 1, 3, NULL, 2, 210.00, 15.00, NULL, 1, 60.0000, 50.0748, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 129', 1, '2025-11-09 18:11:49', '2025-12-27 21:44:53', NULL),
(31, 'MP-FDY-44-12F-BRIL', 'FDY RIGIDO 44/12 BRILLO', '', 1, 1, 3, NULL, 2, 150.00, 15.00, NULL, 1, 23.2000, 34.1943, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 130', 1, '2025-11-09 18:11:49', '2025-12-27 21:44:53', NULL),
(32, 'MP-ELASTANO-100', 'ELASTANO 100 DEN', '', 1, 3, NULL, NULL, 2, 320.00, 20.00, NULL, 1, 14.5000, 66.7500, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 131', 1, '2025-11-09 18:11:49', '2025-12-27 04:14:16', NULL),
(33, 'MP-ELASTANO-140', 'SPANDEX 140 DEN', '', 1, 3, NULL, NULL, 2, 70.00, 20.00, NULL, 1, 111.6300, 111.6300, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 132', 1, '2025-11-09 18:11:49', '2025-12-03 15:58:57', NULL),
(34, 'MP-DTY-22-7F-S', 'DTY 22 DTEX, 7 F, S', '', 1, 1, 1, NULL, 2, 150.00, 50.00, NULL, 1, 45.8000, 45.8000, NULL, 0, 1, NULL, NULL, NULL, '', 'Migrado de insumos. ID original: 133', 1, '2025-11-09 20:49:36', '2025-12-03 19:49:37', NULL),
(35, 'MP-REC-T-30-15-S', 'LYCRA RECUBIERTA TEXTURIZADA 30+20/15F-Z', '', 1, 43, 5, NULL, 2, 250.00, 250.00, NULL, NULL, 35.3982, 35.3982, NULL, 0, 1, NULL, NULL, NULL, '', NULL, 1, '2025-12-02 19:58:03', '2025-12-10 19:40:33', NULL);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `inventario_intermedio`
--

INSERT INTO `inventario_intermedio` (`id_inventario`, `id_producto`, `tipo_inventario`, `docenas`, `unidades`, `fecha_actualizacion`) VALUES
(1, 19, 'tejido', 32, 0, '2025-11-26 18:58:35'),
(2, 57, 'tejido', 33, 1, '2025-11-26 18:58:35'),
(3, 39, 'tejido', 22, 2, '2025-11-26 18:58:35'),
(4, 43, 'tejido', 41, 2, '2025-11-26 18:58:35'),
(5, 56, 'tejido', 49, 8, '2025-11-26 18:58:35'),
(6, 45, 'tejido', 7, 8, '2025-11-16 21:45:58'),
(7, 7, 'tejido', 47, 8, '2025-11-26 18:58:35'),
(8, 12, 'tejido', 51, 6, '2025-11-26 18:58:35'),
(9, 51, 'tejido', 28, 0, '2025-11-26 18:58:35'),
(10, 53, 'tejido', 31, 8, '2025-11-26 18:58:35'),
(11, 65, 'tejido', 43, 5, '2025-11-26 18:58:35');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `lineas_produccion_erp`
--

INSERT INTO `lineas_produccion_erp` (`id_linea_produccion`, `codigo`, `nombre`, `descripcion`, `activo`, `fecha_creacion`) VALUES
(1, 'POLI', 'Poliamida', 'Línea de producción de medias, pantymedias y camisetas de poliamida', 1, '2025-11-27 15:16:20'),
(2, 'CALC', 'Calcetería', 'Línea de producción de calcetines y medias de algodón', 1, '2025-11-27 15:16:20'),
(3, 'CONF', 'Confección', 'Línea de confección de ropa interior y poleras', 1, '2025-11-27 15:16:20');

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

--
-- Volcado de datos para la tabla `lineas_producto`
--

INSERT INTO `lineas_producto` (`id_linea`, `codigo_linea`, `nombre_linea`, `descripcion`, `activo`) VALUES
(1, 'LUJO', 'LUJO', 'Productos de hilo de poliamida con torsión y sin texturizar', 1),
(2, 'STRETCH', 'STRETCH', 'Productos de hilo texturizado de poliamida', 1),
(3, 'LYCRA20', 'LYCRA 20', 'Productos con Lycra denier 20', 1),
(4, 'LYCRA40', 'LYCRA 40', 'Productos con Lycra denier 40', 1),
(5, 'CAMISETAS', 'CAMISETAS', 'Camisetas de poliamida para diferentes edades', 1);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

--
-- Volcado de datos para la tabla `maquinas`
--

INSERT INTO `maquinas` (`id_maquina`, `numero_maquina`, `descripcion`, `diametro_pulgadas`, `numero_agujas`, `estado`, `ubicacion`, `fecha_instalacion`, `observaciones`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'M-01', 'Máquina Circular 4\" 400 agujas', 4.0, 400, 'operativa', 'ZONA A', NULL, NULL, '2025-11-03 23:44:06', '2025-11-03 23:44:06'),
(2, 'M-02', 'Máquina Circular 4&quot; 400 agujas', 4.0, 400, 'operativa', 'ZONA B', NULL, '', '2025-11-03 23:44:06', '2025-11-20 15:03:33'),
(3, 'M-03', 'Máquina Circular 4\" 400 agujas', 4.0, 400, 'operativa', 'ZONA A', NULL, NULL, '2025-11-03 23:44:06', '2025-11-03 23:44:06'),
(4, 'M-04', 'Máquina Circular 4\" 400 agujas', 4.0, 400, 'operativa', 'ZONA A', NULL, NULL, '2025-11-03 23:44:06', '2025-11-03 23:44:06'),
(5, 'M-05', 'Máquina Circular 4\" 400 agujas', 4.0, 400, 'operativa', 'ZONA A', NULL, NULL, '2025-11-03 23:44:06', '2025-11-03 23:44:06'),
(6, 'M-06', 'Máquina Circular 4\" 400 agujas', 4.0, 400, 'mantenimiento', 'ZONA B', NULL, NULL, '2025-11-03 23:44:06', '2025-11-03 23:44:06'),
(7, 'M-07', 'Máquina Circular 4\" 400 agujas', 4.0, 400, 'operativa', 'ZONA B', NULL, NULL, '2025-11-03 23:44:06', '2025-11-03 23:44:06'),
(8, 'M-08', 'Máquina Circular 4\" 400 agujas', 4.0, 400, 'operativa', 'ZONA B', NULL, NULL, '2025-11-03 23:44:06', '2025-11-03 23:44:06'),
(9, 'M-09', 'Máquina Circular 4&quot; 400 agujas', 4.0, 400, 'mantenimiento', 'ZONA B', NULL, '', '2025-11-03 23:44:06', '2025-11-26 18:16:19'),
(10, 'M-10', 'Máquina Circular 4\" 400 agujas', 4.0, 400, 'operativa', 'ZONA B', NULL, NULL, '2025-11-03 23:44:06', '2025-11-03 23:44:06'),
(11, 'M-11', 'Máquina de prueba', 4.0, 400, 'operativa', 'ZONA TEST', NULL, '', '2025-11-04 01:27:03', '2025-11-04 01:27:03');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_inventario`
--

CREATE TABLE `movimientos_inventario` (
  `id_movimiento` int(11) NOT NULL,
  `codigo_movimiento` varchar(50) DEFAULT NULL,
  `id_inventario` int(11) NOT NULL,
  `id_tipo_inventario` int(11) DEFAULT NULL,
  `id_categoria` int(11) DEFAULT NULL,
  `id_subcategoria` int(11) DEFAULT NULL,
  `fecha_movimiento` datetime NOT NULL,
  `tipo_movimiento` varchar(50) DEFAULT NULL,
  `cantidad` decimal(12,4) NOT NULL,
  `stock_anterior` decimal(12,4) NOT NULL,
  `stock_posterior` decimal(12,4) NOT NULL,
  `costo_unitario` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `costo_total` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `costo_promedio_anterior` decimal(14,4) DEFAULT 0.0000,
  `costo_promedio_posterior` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `id_ubicacion_origen` int(11) DEFAULT NULL,
  `id_ubicacion_destino` int(11) DEFAULT NULL,
  `documento_tipo` varchar(50) DEFAULT NULL,
  `documento_numero` varchar(50) DEFAULT NULL,
  `documento_id` int(11) DEFAULT NULL,
  `documento_detalle_id` int(11) DEFAULT NULL,
  `referencia_externa` varchar(100) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `usuario_responsable` varchar(100) DEFAULT NULL,
  `id_lote` int(11) DEFAULT NULL,
  `numero_lote` varchar(50) DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `creado_por` int(11) NOT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `actualizado_por` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `estado` varchar(20) DEFAULT NULL,
  `fecha_anulacion` datetime DEFAULT NULL,
  `motivo_anulacion` text DEFAULT NULL,
  `anulado_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Disparadores `movimientos_inventario`
--
DELIMITER $$
CREATE TRIGGER `trg_movimiento_codigo` BEFORE INSERT ON `movimientos_inventario` FOR EACH ROW BEGIN
    DECLARE siguiente_numero INT;
    DECLARE nuevo_codigo VARCHAR(50);
    DECLARE fecha_str VARCHAR(8);
    
    -- Generar fecha en formato YYYYMMDD
    SET fecha_str = DATE_FORMAT(NEW.fecha_movimiento, '%Y%m%d');
    
    -- Obtener siguiente número para el día
    SELECT COALESCE(MAX(CAST(SUBSTRING(codigo_movimiento, -4) AS UNSIGNED)), 0) + 1
    INTO siguiente_numero
    FROM movimientos_inventario
    WHERE codigo_movimiento LIKE CONCAT('MOV-', fecha_str, '%');
    
    -- Generar código
    SET nuevo_codigo = CONCAT('MOV-', fecha_str, '-', LPAD(siguiente_numero, 4, '0'));
    
    -- Asignar al nuevo registro
    SET NEW.codigo_movimiento = nuevo_codigo;
END
$$
DELIMITER ;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

--
-- Volcado de datos para la tabla `plan_generico_tejido`
--

INSERT INTO `plan_generico_tejido` (`id_plan_generico`, `codigo_plan_generico`, `fecha_vigencia_inicio`, `fecha_vigencia_fin`, `estado`, `observaciones`, `usuario_creacion`, `fecha_creacion`, `fecha_aprobacion`) VALUES
(1, 'PLAN-2025-1', '2025-11-14', NULL, 'historico', 'PARA REQUERIMIENTO DE TEMPORADA', 2, '2025-11-14 02:47:47', NULL),
(2, 'PLAN-2025-1113', '2025-11-14', NULL, 'vigente', '', 2, '2025-11-14 03:39:02', NULL);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `produccion_tejeduria`
--

INSERT INTO `produccion_tejeduria` (`id_produccion`, `codigo_lote`, `fecha_produccion`, `id_turno`, `id_tejedor`, `observaciones`, `fecha_creacion`) VALUES
(2, '211125-1', '2025-11-21', 1, 3, '', '2025-11-21 16:23:43'),
(3, '251125-1', '2025-11-25', 1, 3, '', '2025-11-25 20:14:52'),
(4, '251125-2', '2025-11-25', 2, 4, 'Se tuvo un parpadeos a Hrs 17.30  afectando la maquina 2', '2025-11-25 22:22:32'),
(5, '021025-1', '2025-10-02', 2, 4, '', '2025-11-26 14:45:11');

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

--
-- Volcado de datos para la tabla `productos_tejidos`
--

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

--
-- Volcado de datos para la tabla `producto_insumos`
--

INSERT INTO `producto_insumos` (`id_producto_insumo`, `id_producto`, `id_insumo`, `cantidad_por_docena`, `es_principal`, `observaciones`) VALUES
(1, 18, 131, 3.000, 0, 'Elasticidad'),
(2, 18, 113, 65.000, 1, 'Hilo principal LUJO 17/2'),
(3, 18, 102, 55.000, 0, 'Hilo complementario'),
(4, 2, 131, 3.000, 0, 'Elasticidad'),
(5, 2, 113, 130.000, 1, 'Hilo principal LUJO 17/2'),
(6, 2, 102, 122.000, 0, 'Hilo complementario'),
(7, 32, 131, 3.000, 0, 'Elasticidad'),
(8, 32, 100, 80.000, 1, 'Hilo principal DTY'),
(9, 32, 102, 40.000, 0, 'Hilo complementario'),
(10, 47, 131, 6.000, 0, 'Lycra 20'),
(11, 47, 126, 96.000, 1, 'Filamento principal 20/7'),
(12, 47, 104, 34.000, 0, 'Hilo complementario DTY 40/34'),
(13, 47, 102, 117.000, 0, 'Hilo Lycra base'),
(14, 57, 132, 6.000, 0, 'Spandex 140 para Lycra 40'),
(15, 57, 129, 96.000, 1, 'Filamento principal 40/12'),
(16, 57, 104, 34.000, 0, 'Hilo complementario DTY 40/34'),
(17, 57, 102, 117.000, 0, 'Hilo Lycra base'),
(22, 3, 113, 85.000, 0, ''),
(23, 3, 114, 85.000, 1, ''),
(24, 3, 102, 45.000, 0, ''),
(25, 3, 103, 45.000, 0, ''),
(26, 3, 132, 3.000, 0, ''),
(27, 50, 117, 50.000, 0, ''),
(28, 50, 118, 50.000, 0, ''),
(29, 50, 102, 15.000, 0, ''),
(30, 50, 103, 30.000, 0, ''),
(31, 50, 132, 3.000, 0, ''),
(32, 54, 117, 50.000, 0, ''),
(33, 54, 118, 50.000, 0, '');

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

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id_proveedor`, `codigo`, `razon_social`, `nombre_comercial`, `tipo`, `nit`, `nombre_contacto`, `contacto_telefono`, `telefono`, `email`, `direccion`, `ciudad`, `pais`, `moneda`, `condicion_pago`, `dias_credito`, `observaciones`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'PROV-001', 'Textiles del Sur S.A.', NULL, 'LOCAL', '1234567890', 'Juan Pérez', NULL, '+591 2 2123456', NULL, NULL, 'La Paz', 'Bolivia', 'BOB', 'Contado', 0, NULL, 1, '2025-12-04 09:55:24', '2025-12-04 09:55:24'),
(2, 'PROV-002', 'Hilos Nacionales Ltda.', NULL, 'LOCAL', '0987654321', 'María García', NULL, '+591 2 2789012', NULL, NULL, 'Cochabamba', 'Bolivia', 'BOB', 'Contado', 0, NULL, 1, '2025-12-04 09:55:24', '2025-12-04 09:55:24'),
(3, 'PROV-003', 'Importadora de Fibras', NULL, 'LOCAL', '5678901234', 'Carlos López', NULL, '+591 2 2345678', NULL, NULL, 'Santa Cruz', 'Bolivia', 'BOB', 'Contado', 0, NULL, 1, '2025-12-04 09:55:24', '2025-12-04 09:55:24'),
(4, 'PROV-004', 'Químicos Industriales S.R.L.', NULL, 'LOCAL', '4321098765', 'Ana Rodríguez', NULL, '+591 2 2567890', NULL, NULL, 'La Paz', 'Bolivia', 'BOB', 'Contado', 0, NULL, 1, '2025-12-04 09:55:24', '2025-12-04 09:55:24'),
(5, 'PROV-005', 'Empaques y Más', NULL, 'LOCAL', '6789012345', 'Pedro Martínez', NULL, '+591 2 2901234', NULL, NULL, 'El Alto', 'Bolivia', 'BOB', 'Contado', 0, NULL, 1, '2025-12-04 09:55:24', '2025-12-04 09:55:24'),
(6, 'PROV-006', 'Veckway International Yarns', 'Veckway', 'IMPORTACION', '45786515612', '', '', '', '', '345 Room, Principal Avenue', 'Shanghai', 'China', 'USD', '30 días T/T', 0, '', 1, '2025-12-11 14:38:25', '2025-12-11 14:38:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores_backup_20260105`
--

CREATE TABLE `proveedores_backup_20260105` (
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

--
-- Volcado de datos para la tabla `proveedores_backup_20260105`
--

INSERT INTO `proveedores_backup_20260105` (`id_proveedor`, `codigo`, `razon_social`, `nombre_comercial`, `tipo`, `nit`, `nombre_contacto`, `contacto_telefono`, `telefono`, `email`, `direccion`, `ciudad`, `pais`, `moneda`, `condicion_pago`, `dias_credito`, `observaciones`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'PROV-001', 'Textiles del Sur S.A.', NULL, 'LOCAL', '1234567890', 'Juan Pérez', NULL, '+591 2 2123456', NULL, NULL, 'La Paz', 'Bolivia', 'BOB', 'Contado', 0, NULL, 1, '2025-12-04 09:55:24', '2025-12-04 09:55:24'),
(2, 'PROV-002', 'Hilos Nacionales Ltda.', NULL, 'LOCAL', '0987654321', 'María García', NULL, '+591 2 2789012', NULL, NULL, 'Cochabamba', 'Bolivia', 'BOB', 'Contado', 0, NULL, 1, '2025-12-04 09:55:24', '2025-12-04 09:55:24'),
(3, 'PROV-003', 'Importadora de Fibras', NULL, 'LOCAL', '5678901234', 'Carlos López', NULL, '+591 2 2345678', NULL, NULL, 'Santa Cruz', 'Bolivia', 'BOB', 'Contado', 0, NULL, 1, '2025-12-04 09:55:24', '2025-12-04 09:55:24'),
(4, 'PROV-004', 'Químicos Industriales S.R.L.', NULL, 'LOCAL', '4321098765', 'Ana Rodríguez', NULL, '+591 2 2567890', NULL, NULL, 'La Paz', 'Bolivia', 'BOB', 'Contado', 0, NULL, 1, '2025-12-04 09:55:24', '2025-12-04 09:55:24'),
(5, 'PROV-005', 'Empaques y Más', NULL, 'LOCAL', '6789012345', 'Pedro Martínez', NULL, '+591 2 2901234', NULL, NULL, 'El Alto', 'Bolivia', 'BOB', 'Contado', 0, NULL, 1, '2025-12-04 09:55:24', '2025-12-04 09:55:24'),
(6, 'PROV-006', 'Veckway International Yarns', 'Veckway', 'IMPORTACION', '45786515612', '', '', '', '', '345 Room, Principal Avenue', 'Shanghai', 'China', 'USD', '30 días T/T', 0, '', 1, '2025-12-11 14:38:25', '2025-12-11 14:38:25');

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

--
-- Volcado de datos para la tabla `secuencias_documento`
--

INSERT INTO `secuencias_documento` (`id_secuencia`, `tipo_documento`, `subtipo`, `prefijo`, `anio`, `mes`, `ultimo_numero`, `fecha_creacion`) VALUES
(1, 'INGRESO', NULL, 'ING-MP', 2026, 1, 22, '2026-01-07 22:08:19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `subcategorias_backup_20260105`
--

CREATE TABLE `subcategorias_backup_20260105` (
  `id_subcategoria` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `orden` int(11) DEFAULT 1,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `subcategorias_backup_20260105`
--

INSERT INTO `subcategorias_backup_20260105` (`id_subcategoria`, `id_categoria`, `codigo`, `nombre`, `descripcion`, `orden`, `activo`, `fecha_creacion`) VALUES
(1, 1, 'MP-HILO-DTY', 'Hilos DTY (Draw Textured Yarn)', NULL, 1, 1, '2025-12-03 14:57:56'),
(2, 1, 'MP-HILO-HTY', 'Hilos HTY (High Tenacity Yarn)', NULL, 2, 1, '2025-12-03 14:57:57'),
(3, 1, 'MP-HILO-FDY', 'Hilos FDY (Fully Drawn Yarn)', NULL, 3, 1, '2025-12-03 14:57:57'),
(4, 1, 'MP-HILO-DTY-COL', 'Hilos DTY Colores', NULL, 4, 1, '2025-12-03 14:57:57'),
(5, 43, 'MP-ELAS', 'Spandex Recubierto Crudo', NULL, 1, 1, '2025-12-03 14:57:57'),
(6, 43, 'MP-LYCRA-COLOR', 'Spandex Recubierto Colores', NULL, 2, 1, '2025-12-03 14:57:57'),
(7, 2, 'MP-ALG-CRUDO', 'Algodón Crudo', NULL, 1, 1, '2025-12-03 14:57:57'),
(8, 2, 'MP-ALG-COLOR', 'Algodón Colores', NULL, 2, 1, '2025-12-03 14:57:57');

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

--
-- Volcado de datos para la tabla `subcategorias_inventario`
--

INSERT INTO `subcategorias_inventario` (`id_subcategoria`, `id_categoria`, `codigo`, `nombre`, `descripcion`, `orden`, `activo`, `fecha_creacion`) VALUES
(1, 1, 'MP-HILO-DTY', 'Hilos DTY (Draw Textured Yarn)', NULL, 1, 1, '2025-12-03 14:57:56'),
(2, 1, 'MP-HILO-HTY', 'Hilos HTY (High Tenacity Yarn)', NULL, 2, 1, '2025-12-03 14:57:57'),
(3, 1, 'MP-HILO-FDY', 'Hilos FDY (Fully Drawn Yarn)', NULL, 3, 1, '2025-12-03 14:57:57'),
(4, 1, 'MP-HILO-DTY-COL', 'Hilos DTY Colores', NULL, 4, 1, '2025-12-03 14:57:57'),
(5, 43, 'MP-ELAS', 'Spandex Recubierto Crudo', NULL, 1, 1, '2025-12-03 14:57:57'),
(6, 43, 'MP-LYCRA-COLOR', 'Spandex Recubierto Colores', NULL, 2, 1, '2025-12-03 14:57:57'),
(7, 2, 'MP-ALG-CRUDO', 'Algodón Crudo', NULL, 1, 1, '2025-12-03 14:57:57'),
(8, 2, 'MP-ALG-COLOR', 'Algodón Colores', NULL, 2, 1, '2025-12-03 14:57:57');

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

--
-- Volcado de datos para la tabla `tipos_ingreso`
--

INSERT INTO `tipos_ingreso` (`id_tipo_ingreso`, `codigo`, `nombre`, `descripcion`, `requiere_proveedor`, `requiere_factura`, `requiere_area_produccion`, `requiere_motivo`, `requiere_autorizacion`, `permite_iva`, `permite_moneda_extranjera`, `observaciones_obligatorias`, `minimo_caracteres_obs`, `afecta_cpp`, `tipo_kardex`, `icono`, `color`, `orden`, `activo`, `fecha_creacion`) VALUES
(1, 'COMPRA', 'Compra a Proveedor', 'Ingreso por compra de materias primas a proveedores externos. Permite registro con o sin factura, en BOB o USD.', 1, 1, 0, 0, 0, 1, 1, 0, 0, 1, 'ENTRADA', 'fa-shopping-cart', '#28a745', 1, 1, '2025-12-29 01:55:46'),
(2, 'INICIAL', 'Inventario Inicial', 'Registro de inventario existente al momento de iniciar el sistema. Establece stock y costos base.', 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 'ENTRADA', 'fa-clipboard-list', '#17a2b8', 2, 1, '2025-12-29 01:55:46'),
(3, 'DEVOLUCION_PROD', 'Devolución de Producción', 'Material devuelto por áreas de producción que no fue utilizado. No afecta el CPP.', 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 'ENTRADA', 'fa-undo', '#ffc107', 3, 1, '2025-12-29 01:55:46'),
(4, 'AJUSTE_POS', 'Ajuste Positivo', 'Ajuste de inventario por conteo físico o corrección de errores. Requiere autorización y justificación detallada.', 0, 0, 0, 1, 1, 0, 0, 1, 20, 0, 'ENTRADA', 'fa-plus-circle', '#6f42c1', 4, 1, '2025-12-29 01:55:46');

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

--
-- Volcado de datos para la tabla `tipos_insumo`
--

INSERT INTO `tipos_insumo` (`id_tipo_insumo`, `nombre_tipo`, `descripcion`, `activo`) VALUES
(1, 'HILO POLIAMIDA', 'Hilos de poliamida en diferentes deniers', 1),
(2, 'LYCRA', 'Fibra elástica Lycra', 1),
(3, 'ELASTICO', 'Elásticos para pretina', 1),
(4, 'ALGODON', 'Algodón para parches', 1);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tipos_inventario`
--

INSERT INTO `tipos_inventario` (`id_tipo_inventario`, `codigo`, `nombre`, `descripcion`, `icono`, `color`, `orden`, `activo`) VALUES
(1, 'MP', 'Materias Primas', 'Hilos, fibras y materiales base para producción', 'fa-industry', '#007bff', 1, 1),
(2, 'CAQ', 'Colorantes y Aux. Químicos', 'Colorantes, fijadores, suavizantes y químicos de teñido', 'fa-flask', '#6f42c1', 2, 1),
(3, 'EMP', 'Material de Empaque', 'Bolsas, cartulinas, cajas, etiquetas y materiales de empaque', 'fa-box-open', '#fd7e14', 3, 1),
(4, 'ACC', 'Accesorios de Confección', 'Etiquetas tejidas, elásticos con marca, sesgos, pasadores', 'fa-tags', '#e83e8c', 4, 1),
(5, 'WIP', 'Productos en Proceso', 'Inventario intermedio entre etapas de producción', 'fa-cogs', '#17a2b8', 5, 1),
(6, 'PT', 'Productos Terminados', 'Productos listos para venta', 'fa-check-circle', '#28a745', 7, 1),
(7, 'REP', 'Repuestos y Accesorios Máquinas', 'Repuestos de máquinas y accesorios de mantenimiento', 'fa-wrench', '#6c757d', 6, 1),
(0, 'PT', 'Productos Terminados', 'Productos terminados listos para venta', 'fa-box-check', '#28a745', 7, 1);

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

--
-- Volcado de datos para la tabla `tipos_producto`
--

INSERT INTO `tipos_producto` (`id_tipo_producto`, `nombre_tipo`, `categoria`, `descripcion`, `activo`) VALUES
(1, 'PANTYHOSE', 'ensamblaje', 'Pantymedias que requieren ensamblaje de piernas y parche', 1),
(2, 'MEDIA SOPORTE', 'directo', 'Medias de soporte que solo requieren costura de puntera', 1),
(3, 'MEDIA PANTALÓN', 'directo', 'Medias tipo pantalón que solo requieren costura de puntera', 1),
(4, 'MEDIA SOCKET', 'directo', 'Medias tipo socket', 1),
(5, 'COBERTOR DE PIE', 'directo', 'Cobertor de pie', 1),
(6, 'CUERPO', 'ensamblaje', 'Cuerpo de camiseta (pecho y espalda)', 1),
(7, 'MANGA', 'ensamblaje', 'Manga de camiseta', 1),
(8, 'RIBETE', 'ensamblaje', 'Ribete para cuello de camiseta', 1),
(9, 'CLASICO', 'ensamblaje', 'Camiseta clásica completa', 1);

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

--
-- Volcado de datos para la tabla `turnos`
--

INSERT INTO `turnos` (`id_turno`, `nombre_turno`, `hora_inicio`, `hora_fin`, `activo`) VALUES
(1, 'Mañana', '06:00:00', '14:00:00', 1),
(2, 'Tarde', '14:00:00', '22:00:00', 1),
(3, 'Noche', '22:00:00', '06:00:00', 1);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ubicaciones_almacen`
--

INSERT INTO `ubicaciones_almacen` (`id_ubicacion`, `codigo`, `nombre`, `tipo`, `descripcion`, `responsable`, `activo`) VALUES
(1, 'ALM-MP', 'Almacén Materias Primas', 'ALMACEN', 'Almacén principal de hilos y materias primas', NULL, 1),
(2, 'ALM-CAQ', 'Almacén Químicos', 'ALMACEN', 'Almacén de colorantes y químicos', NULL, 1),
(3, 'ALM-EMP', 'Almacén Empaque', 'ALMACEN', 'Almacén de materiales de empaque', NULL, 1),
(4, 'ALM-ACC', 'Almacén Accesorios', 'ALMACEN', 'Almacén de accesorios de confección', NULL, 1),
(5, 'ALM-PT', 'Almacén Productos Terminados', 'ALMACEN', 'Almacén principal de productos terminados', NULL, 1),
(6, 'ALM-REP', 'Almacén Repuestos', 'ALMACEN', 'Almacén de repuestos y herramientas', NULL, 1),
(7, 'PROD-TEJ-POLI', 'Tejeduría Poliamida', 'AREA_PRODUCCION', 'Área de tejeduría - Línea Poliamida', NULL, 1),
(8, 'PROD-REV-POLI', 'Revisado Poliamida', 'AREA_PRODUCCION', 'Área de revisado crudo', NULL, 1),
(9, 'PROD-VAP-POLI', 'Vaporizado', 'AREA_PRODUCCION', 'Área de vaporizado', NULL, 1),
(10, 'PROD-COST-POLI', 'Costura Poliamida', 'AREA_PRODUCCION', 'Área de costura', NULL, 1),
(11, 'PROD-TEN', 'Tintorería', 'AREA_PRODUCCION', 'Área de teñido', NULL, 1),
(12, 'PROD-EMP', 'Empaque', 'AREA_PRODUCCION', 'Área de empaque', NULL, 1),
(13, 'PROD-TEJ-CALC', 'Tejeduría Calcetería', 'AREA_PRODUCCION', 'Área de tejeduría - Línea Calcetería', NULL, 1),
(14, 'PROD-CORTE', 'Corte', 'AREA_PRODUCCION', 'Área de corte - Confección', NULL, 1),
(15, 'PROD-COST-CONF', 'Costura Confección', 'AREA_PRODUCCION', 'Área de costura - Confección', NULL, 1),
(16, 'TRANS-RECEP', 'Recepción', 'TRANSITO', 'Área de recepción de mercadería', NULL, 1),
(17, 'TRANS-DESP', 'Despacho', 'TRANSITO', 'Área de despacho', NULL, 1),
(0, 'ALM-MP-02', 'Almacén Materia Prima 2', 'ALMACEN', 'Almacén secundario de hilos', NULL, 1),
(0, 'ALM-WIP-01', 'WIP - En Proceso', 'AREA_PRODUCCION', 'Inventario en proceso', NULL, 1);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `unidades_medida`
--

INSERT INTO `unidades_medida` (`id_unidad`, `codigo`, `nombre`, `abreviatura`, `tipo`, `activo`) VALUES
(1, 'GR', 'Gramos', 'g', 'PESO', 1),
(2, 'KG', 'Kilogramos', 'kg', 'PESO', 1),
(3, 'LB', 'Libras', 'lb', 'PESO', 1),
(4, 'MT', 'Metros', 'm', 'LONGITUD', 1),
(5, 'CM', 'Centímetros', 'cm', 'LONGITUD', 1),
(6, 'YD', 'Yardas', 'yd', 'LONGITUD', 1),
(7, 'UN', 'Unidades', 'un', 'CANTIDAD', 1),
(8, 'DOC', 'Docenas', 'doc', 'CANTIDAD', 1),
(9, 'PAR', 'Pares', 'par', 'CANTIDAD', 1),
(10, 'CIENTO', 'Cientos', 'cto', 'CANTIDAD', 1),
(11, 'MILLAR', 'Millares', 'mil', 'CANTIDAD', 1),
(12, 'ROLLO', 'Rollos', 'rollo', 'CANTIDAD', 1),
(13, 'LT', 'Litros', 'lt', 'VOLUMEN', 1),
(14, 'ML', 'Mililitros', 'ml', 'VOLUMEN', 1),
(15, 'GL', 'Galones', 'gal', 'VOLUMEN', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `unidades_medida_backup_20260105`
--

CREATE TABLE `unidades_medida_backup_20260105` (
  `id_unidad` int(11) NOT NULL,
  `codigo` varchar(10) NOT NULL,
  `nombre` varchar(30) NOT NULL,
  `abreviatura` varchar(10) NOT NULL,
  `tipo` enum('PESO','LONGITUD','CANTIDAD','VOLUMEN') NOT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `unidades_medida_backup_20260105`
--

INSERT INTO `unidades_medida_backup_20260105` (`id_unidad`, `codigo`, `nombre`, `abreviatura`, `tipo`, `activo`) VALUES
(1, 'GR', 'Gramos', 'g', 'PESO', 1),
(2, 'KG', 'Kilogramos', 'kg', 'PESO', 1),
(3, 'LB', 'Libras', 'lb', 'PESO', 1),
(4, 'MT', 'Metros', 'm', 'LONGITUD', 1),
(5, 'CM', 'Centímetros', 'cm', 'LONGITUD', 1),
(6, 'YD', 'Yardas', 'yd', 'LONGITUD', 1),
(7, 'UN', 'Unidades', 'un', 'CANTIDAD', 1),
(8, 'DOC', 'Docenas', 'doc', 'CANTIDAD', 1),
(9, 'PAR', 'Pares', 'par', 'CANTIDAD', 1),
(10, 'CIENTO', 'Cientos', 'cto', 'CANTIDAD', 1),
(11, 'MILLAR', 'Millares', 'mil', 'CANTIDAD', 1),
(12, 'ROLLO', 'Rollos', 'rollo', 'CANTIDAD', 1),
(13, 'LT', 'Litros', 'lt', 'VOLUMEN', 1),
(14, 'ML', 'Mililitros', 'ml', 'VOLUMEN', 1),
(15, 'GL', 'Galones', 'gal', 'VOLUMEN', 1);

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

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `codigo_usuario`, `nombre_completo`, `usuario`, `password`, `rol`, `area`, `estado`, `fecha_creacion`, `ultimo_acceso`) VALUES
(2, 'ADMIN001', 'Administrador del Sistema', 'admin', '$2y$10$jmbV/zRpoGYAQNdNC99Y7u/liKAEWFV2VBCCvWjoeZ7M2Cx71QHu2', 'admin', 'SISTEMAS', 'activo', '2025-11-04 00:35:39', '2026-01-08 14:31:14'),
(3, 'TEJ001', 'Cosme Morales', 'cosme', '$2y$10$jmbV/zRpoGYAQNdNC99Y7u/liKAEWFV2VBCCvWjoeZ7M2Cx71QHu2', 'tejedor', 'TEJEDURIA', 'activo', '2025-11-15 16:00:09', NULL),
(4, 'TEJ002', 'Maria Condori', 'maria', '$2y$10$jmbV/zRpoGYAQNdNC99Y7u/liKAEWFV2VBCCvWjoeZ7M2Cx71QHu2', 'tejedor', 'TEJEDURIA', 'activo', '2025-11-15 16:00:09', NULL),
(5, 'TEJ003', 'Juan Mamani', 'juan', '$2y$10$jmbV/zRpoGYAQNdNC99Y7u/liKAEWFV2VBCCvWjoeZ7M2Cx71QHu2', 'tejedor', 'TEJEDURIA', 'activo', '2025-11-15 16:00:09', NULL);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_kardex_valorado`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_kardex_valorado` (
`id_movimiento` int(11)
,`codigo_movimiento` varchar(50)
,`fecha_movimiento` datetime
,`tipo_movimiento` varchar(50)
,`direccion` varchar(7)
,`producto_codigo` varchar(30)
,`producto_nombre` varchar(150)
,`categoria` varchar(100)
,`subcategoria` varchar(100)
,`unidad` varchar(10)
,`cantidad` decimal(12,4)
,`stock_anterior` decimal(12,4)
,`stock_posterior` decimal(12,4)
,`costo_unitario` decimal(14,4)
,`costo_total` decimal(14,4)
,`cpp_anterior` decimal(14,4)
,`cpp_posterior` decimal(14,4)
,`documento_tipo` varchar(50)
,`documento_numero` varchar(50)
,`observaciones` text
,`estado` varchar(20)
,`usuario` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `v_kardex_valorado`
--
DROP TABLE IF EXISTS `v_kardex_valorado`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_kardex_valorado`  AS SELECT `m`.`id_movimiento` AS `id_movimiento`, `m`.`codigo_movimiento` AS `codigo_movimiento`, `m`.`fecha_movimiento` AS `fecha_movimiento`, `m`.`tipo_movimiento` AS `tipo_movimiento`, CASE END FROM (((((`movimientos_inventario` `m` join `inventarios` `i` on(`m`.`id_inventario` = `i`.`id_inventario`)) left join `categorias_inventario` `c` on(`i`.`id_categoria` = `c`.`id_categoria`)) left join `subcategorias_inventario` `sc` on(`i`.`id_subcategoria` = `sc`.`id_subcategoria`)) left join `unidades_medida` `um` on(`i`.`id_unidad` = `um`.`id_unidad`)) left join `usuarios` `u` on(`m`.`creado_por` = `u`.`id_usuario`)) WHERE `m`.`estado` ORDER BY `m`.`fecha_movimiento` DESC, `m`.`id_movimiento` DESC ;

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
-- Indices de la tabla `movimientos_inventario`
--
ALTER TABLE `movimientos_inventario`
  ADD PRIMARY KEY (`id_movimiento`),
  ADD UNIQUE KEY `codigo_movimiento` (`codigo_movimiento`),
  ADD KEY `idx_inventario` (`id_inventario`),
  ADD KEY `idx_fecha` (`fecha_movimiento`),
  ADD KEY `idx_tipo` (`tipo_movimiento`),
  ADD KEY `idx_codigo` (`codigo_movimiento`);

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
  MODIFY `id_area` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `detalle_flujos`
--
ALTER TABLE `detalle_flujos`
  MODIFY `id_detalle_flujo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT de la tabla `documentos_inventario`
--
ALTER TABLE `documentos_inventario`
  MODIFY `id_documento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de la tabla `etapas_produccion`
--
ALTER TABLE `etapas_produccion`
  MODIFY `id_etapa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `flujos_produccion`
--
ALTER TABLE `flujos_produccion`
  MODIFY `id_flujo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
-- AUTO_INCREMENT de la tabla `movimientos_inventario`
--
ALTER TABLE `movimientos_inventario`
  MODIFY `id_movimiento` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id_secuencia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tipos_ingreso`
--
ALTER TABLE `tipos_ingreso`
  MODIFY `id_tipo_ingreso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
