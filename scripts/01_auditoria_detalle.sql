-- 01_auditoria_detalle.sql
-- Diagnóstico profundo del estado de la tabla documentos_inventario_detalle
-- -------------------------------------------------------------------------
USE mes_hermen;

-- 1. Resumen de salud de la tabla
SELECT 
    COUNT(*) as total_filas,
    SUM(CASE WHEN id_detalle = 0 THEN 1 ELSE 0 END) as filas_con_id_cero,
    COUNT(DISTINCT id_detalle) as ids_unicos
FROM documentos_inventario_detalle;

-- 2. Detección de duplicados específicos
SELECT id_detalle, COUNT(*) as cantidad
FROM documentos_inventario_detalle
GROUP BY id_detalle
HAVING cantidad > 1;

-- 3. Verificación de referencias externas (Kardex)
SELECT COUNT(*) as movimientos_huérfanos
FROM movimientos_inventario
WHERE documento_detalle_id IS NOT NULL 
AND documento_detalle_id != 0
AND documento_detalle_id NOT IN (SELECT id_detalle FROM documentos_inventario_detalle WHERE id_detalle != 0);

-- 4. Verificación de trazabilidad interna (id_detalle_origen)
SELECT COUNT(*) as usos_de_detalle_origen
FROM documentos_inventario_detalle
WHERE id_detalle_origen IS NOT NULL 
AND id_detalle_origen != 0;
