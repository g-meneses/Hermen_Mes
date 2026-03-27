-- 02_fix_inventario_detalle.sql
-- Versión 4: Ejecución Controlada (Backup + Carga + Validaciones)
-- -------------------------------------------------------------------------
USE mes_hermen;

-- 1. BACKUP DE CONTEXTO
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS backup_DOC_DETALLE_FIX_SAFE AS SELECT * FROM documentos_inventario_detalle;
CREATE TABLE IF NOT EXISTS backup_DOC_HEADER_FIX_SAFE AS SELECT * FROM documentos_inventario;
CREATE TABLE IF NOT EXISTS backup_MOVIMIENTOS_FIX_SAFE AS SELECT * FROM movimientos_inventario;

-- 2. CREAR TABLA NUEVA (Clonando estructura)
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS documentos_inventario_detalle_NEW;
CREATE TABLE documentos_inventario_detalle_NEW LIKE documentos_inventario_detalle;

-- 3. AJUSTE ESTRUCTURAL DEFENSIVO
-- -------------------------------------------------------------------------
-- Primero revisamos si ya tiene PK. Si falla por "Multiple primary key", es que LIKE la trajo.
-- En ese caso, solo modificamos la columna para que sea AUTO_INCREMENT.
-- Usamos una técnica de "intento y error" o simplemente intentamos el cambio.
ALTER TABLE documentos_inventario_detalle_NEW 
    MODIFY COLUMN id_detalle INT(11) NOT NULL AUTO_INCREMENT,
    ADD PRIMARY KEY (id_detalle);

-- Añadir índice de trazabilidad
ALTER TABLE documentos_inventario_detalle_NEW 
    ADD INDEX idx_detalle_origen (id_detalle_origen);

-- 4. TRASPASO DE DATOS
-- -------------------------------------------------------------------------

-- A. Preservar registros con IDs válidos (> 0)
INSERT INTO documentos_inventario_detalle_NEW 
SELECT * FROM documentos_inventario_detalle WHERE id_detalle > 0;

-- B. Corregir registros con ID = 0 (Generación automática)
INSERT INTO documentos_inventario_detalle_NEW (
    id_documento, id_inventario, id_detalle_origen, 
    cantidad, cantidad_original, id_unidad, 
    costo_unitario, costo_adquisicion, tenia_iva, 
    costo_con_iva, subtotal, lote, fecha_vencimiento, observaciones
)
SELECT 
    id_documento, id_inventario, id_detalle_origen, 
    cantidad, cantidad_original, id_unidad, 
    costo_unitario, costo_adquisicion, tenia_iva, 
    costo_con_iva, subtotal, lote, fecha_vencimiento, observaciones
FROM documentos_inventario_detalle 
WHERE id_detalle = 0;

-- 5. TABLA DE MAPEO ROBUSTA
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS tmp_map_detalle_ids;
CREATE TABLE tmp_map_detalle_ids AS
SELECT 
    '0' as old_id_detalle,
    n.id_detalle as new_id_detalle,
    n.id_documento,
    n.id_inventario,
    n.cantidad,
    n.costo_unitario,
    n.subtotal,
    n.lote,
    n.fecha_vencimiento
FROM documentos_inventario_detalle_NEW n
WHERE NOT EXISTS (
    SELECT 1 FROM backup_DOC_DETALLE_FIX_SAFE b 
    WHERE b.id_detalle = n.id_detalle AND b.id_detalle > 0
);

-- 6. VALIDACIONES SOLICITADAS POR EL USUARIO
-- -------------------------------------------------------------------------

-- A. Conteo total
SELECT 
    (SELECT COUNT(*) FROM documentos_inventario_detalle) as original_rows,
    (SELECT COUNT(*) FROM documentos_inventario_detalle_NEW) as new_rows;

-- B. Ceros y Duplicados en la nueva
SELECT COUNT(*) AS zeros_new FROM documentos_inventario_detalle_NEW WHERE id_detalle = 0;

SELECT id_detalle, COUNT(*) AS repeticiones
FROM documentos_inventario_detalle_NEW
GROUP BY id_detalle
HAVING repeticiones > 1;

-- C. Max ID y Coherencia
SELECT MAX(id_detalle) AS max_id, COUNT(*) AS total_rows FROM documentos_inventario_detalle_NEW;

-- D. Validación Documental (Si devuelve vacío, es perfecto)
SELECT o.id_documento, o.cnt_orig, n.cnt_new, o.sum_orig, n.sum_new
FROM (
    SELECT id_documento, COUNT(*) as cnt_orig, SUM(subtotal) as sum_orig 
    FROM documentos_inventario_detalle GROUP BY id_documento
) o
JOIN (
    SELECT id_documento, COUNT(*) as cnt_new, SUM(subtotal) as sum_new 
    FROM documentos_inventario_detalle_NEW GROUP BY id_documento
) n ON o.id_documento = n.id_documento
WHERE o.cnt_orig != n.cnt_new OR ABS(o.sum_orig - n.sum_new) > 0.0001;

-- E. Estructura final
SHOW CREATE TABLE documentos_inventario_detalle_NEW;
