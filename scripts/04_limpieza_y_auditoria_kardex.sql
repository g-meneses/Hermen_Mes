-- ############################################################################
-- SCRIPT DE LIMPIEZA Y AUDITORÍA DE KARDEX (FASE 3)
-- ############################################################################

USE mes_hermen;

-- ============================================================================
-- 1. IDENTIFICACIÓN DE REGISTROS DE PRUEBA (MARZO 2026)
-- ============================================================================
-- Documentos 109 al 121 son pruebas de sistema confirmadas por observaciones.
-- El 122 es una devolución de prueba realizada en la verificación de Phase 2.

SET @min_test_id = 109;
SET @max_test_id = 122;

-- ============================================================================
-- 2. LIMPIEZA CONTROLADA
-- ============================================================================

-- A. Eliminar Movimientos de Inventario vinculados
DELETE FROM movimientos_inventario 
WHERE documento_id BETWEEN @min_test_id AND @max_test_id;

-- B. Eliminar Ajustes de Inventario vinculados
-- Nota: En ajustes_inventario se vinculan por documento_id o por id_inventario + fecha (si no tienen FK explícita)
DELETE FROM ajustes_inventario 
WHERE id_documento BETWEEN @min_test_id AND @max_test_id;

-- C. Eliminar Detalles de Documento
DELETE FROM documentos_inventario_detalle 
WHERE id_documento BETWEEN @min_test_id AND @max_test_id;

-- D. Eliminar Cabeceras de Documento
DELETE FROM documentos_inventario 
WHERE id_documento BETWEEN @min_test_id AND @max_test_id;

-- ============================================================================
-- 3. AUDITORÍA DE INTEGRIDAD (KARDEX)
-- ============================================================================

-- REPORTE 1: Movimientos sin documento detalle (Posibles huérfanos pre-Fase 1)
SELECT 'MOVIMIENTOS HUERFANOS' AS reporte;
SELECT id_movimiento, id_inventario, cantidad, tipo_movimiento, fecha_movimiento, documento_numero
FROM movimientos_inventario
WHERE documento_detalle_id IS NULL OR documento_detalle_id = 0;

-- REPORTE 2: Inconsistencia de tipo de movimiento vs cabecera
SELECT 'INCONSISTENCIAS TIPO MOV' AS reporte;
SELECT m.id_movimiento, m.tipo_movimiento, d.id_doc_tipo, d.numero_documento
FROM movimientos_inventario m
JOIN documentos_inventario d ON m.documento_id = d.id_documento
WHERE (m.tipo_movimiento = 'ENTRADA' AND d.id_doc_tipo != 1)
   OR (m.tipo_movimiento = 'SALIDA' AND d.id_doc_tipo != 2);

-- REPORTE 3: Movimientos vinculados a detalles inexistentes
SELECT 'VINCULOS ROTOS' AS reporte;
SELECT m.id_movimiento, m.documento_detalle_id
FROM movimientos_inventario m
LEFT JOIN documentos_inventario_detalle dd ON m.documento_detalle_id = dd.id_detalle
WHERE m.documento_detalle_id IS NOT NULL 
  AND m.documento_detalle_id != 0 
  AND dd.id_detalle IS NULL;
