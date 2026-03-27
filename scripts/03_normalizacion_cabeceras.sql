-- FASE 2: Normalización de Cabeceras
-- Sistema ERP Hermen Ltda.
USE `mes_hermen`;

-- 1. Crear tablas de catálogo
CREATE TABLE IF NOT EXISTS `inv_doc_tipos` (
  `id_tipo` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `codigo` varchar(10) NOT NULL,
  PRIMARY KEY (`id_tipo`),
  UNIQUE KEY `uk_tipo_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inv_doc_subtipos` (
  `id_subtipo` int(11) NOT NULL AUTO_INCREMENT,
  `id_tipo` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_subtipo`),
  UNIQUE KEY `uk_subtipo_codigo` (`id_tipo`, `codigo`),
  CONSTRAINT `fk_subtipo_tipo` FOREIGN KEY (`id_tipo`) REFERENCES `inv_doc_tipos` (`id_tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inv_doc_estados` (
  `id_estado` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `color_hex` varchar(10) DEFAULT '#6c757d',
  PRIMARY KEY (`id_estado`),
  UNIQUE KEY `uk_estado_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Población Inicial
INSERT IGNORE INTO `inv_doc_tipos` (id_tipo, nombre, codigo) VALUES
(1, 'INGRESO', 'ING'),
(2, 'SALIDA', 'OUT'),
(3, 'AJUSTE', 'AJU'),
(4, 'TRANSFERENCIA', 'TRA');

INSERT IGNORE INTO `inv_doc_estados` (id_estado, codigo, nombre, color_hex) VALUES
(1, 'BORRADOR', 'Borrador', '#6c757d'),
(2, 'CONFIRMADO', 'Confirmado', '#28a745'),
(3, 'ANULADO', 'Anulado', '#dc3545');

-- Subtipos para INGRESO (id_tipo = 1)
INSERT IGNORE INTO `inv_doc_subtipos` (id_tipo, nombre, codigo) VALUES
(1, 'Compra', 'COMPRA'),
(1, 'Devolución de Producción', 'DEVOLUCION_PROD'),
(1, 'Ajuste Positivo', 'AJUSTE_POS'),
(1, 'Inventario Inicial', 'INICIAL'),
(1, 'Otros Ingresos', 'OTRO');

-- Subtipos para SALIDA (id_tipo = 2)
INSERT IGNORE INTO `inv_doc_subtipos` (id_tipo, nombre, codigo) VALUES
(2, 'Entrega a Producción', 'PRODUCCION'),
(2, 'Devolución a Proveedor', 'DEVOLUCION'),
(2, 'Desarrollo de Muestras', 'MUESTRAS'),
(2, 'Venta de Materiales', 'VENTA'),
(2, 'Ajuste Negativo', 'AJUSTE_NEG'),
(2, 'Otras Salidas', 'OTRO');

-- 3. Modificar documentos_inventario
ALTER TABLE `documentos_inventario`
ADD COLUMN `id_doc_tipo` int(11) DEFAULT NULL AFTER `id_documento`,
ADD COLUMN `id_doc_subtipo` int(11) DEFAULT NULL AFTER `id_doc_tipo`,
ADD COLUMN `id_doc_estado` int(11) DEFAULT NULL AFTER `id_doc_subtipo`,
ADD INDEX `idx_doc_tipo` (`id_doc_tipo`),
ADD INDEX `idx_doc_subtipo` (`id_doc_subtipo`),
ADD INDEX `idx_doc_estado` (`id_doc_estado`);

-- 4. Migración de datos existentes
-- Mapear Tipos
UPDATE documentos_inventario SET id_doc_tipo = 1 WHERE tipo_documento = 'INGRESO';
UPDATE documentos_inventario SET id_doc_tipo = 2 WHERE tipo_documento = 'SALIDA';

-- Mapear Estados
UPDATE documentos_inventario SET id_doc_estado = 2 WHERE estado = 'CONFIRMADO';
UPDATE documentos_inventario SET id_doc_estado = 3 WHERE estado = 'ANULADO';

-- Mapear Subtipos INGRESOS
UPDATE documentos_inventario d
INNER JOIN inv_doc_subtipos s ON d.tipo_ingreso = s.codigo AND s.id_tipo = 1
SET d.id_doc_subtipo = s.id_subtipo
WHERE d.tipo_documento = 'INGRESO';

-- Mapear Subtipos SALIDAS (especial para DEVOLUCION que en db es varchar libre)
UPDATE documentos_inventario d
INNER JOIN inv_doc_subtipos s ON d.tipo_salida = s.codigo AND s.id_tipo = 2
SET d.id_doc_subtipo = s.id_subtipo
WHERE d.tipo_documento = 'SALIDA';

-- Casos especiales o nulos a OTRO
UPDATE documentos_inventario 
SET id_doc_subtipo = (SELECT id_subtipo FROM inv_doc_subtipos WHERE id_tipo = 1 AND codigo = 'OTRO')
WHERE id_doc_tipo = 1 AND id_doc_subtipo IS NULL;

UPDATE documentos_inventario 
SET id_doc_subtipo = (SELECT id_subtipo FROM inv_doc_subtipos WHERE id_tipo = 2 AND codigo = 'OTRO')
WHERE id_doc_tipo = 2 AND id_doc_subtipo IS NULL;
