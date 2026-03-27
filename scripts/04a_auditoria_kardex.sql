-- ############################################################################
-- SCRIPT DE AUDITORÍA Y SNAPSHOTS DE KARDEX (FASE 3a)
-- ############################################################################

USE mes_hermen;

-- ============================================================================
-- 1. CREACIÓN DE SNAPSHOTS (BACKUP DE SEGURIDAD ANTES DE LIMPIEZA)
-- ============================================================================

SELECT 'CREANDO BACKUPS...' AS paso;

CREATE TABLE IF NOT EXISTS bkp_f3_documentos SELECT * FROM documentos_inventario;
CREATE TABLE IF NOT EXISTS bkp_f3_detalles SELECT * FROM documentos_inventario_detalle;
CREATE TABLE IF NOT EXISTS bkp_f3_movimientos SELECT * FROM movimientos_inventario;
CREATE TABLE IF NOT EXISTS bkp_f3_ajustes SELECT * FROM ajustes_inventario;

-- ============================================================================
-- 2. INVESTIGACIÓN DE VÍNCULOS Y HUÉRFANOS
-- ============================================================================

-- REPORTE 1: Movimientos sin vinculación a detalle (Fueron creados antes de la Fase 1 o por procesos incompletos)
SELECT '--- REPORTE 1: MOVIMIENTOS SIN DETALLE ---' AS reporte;
SELECT id_movimiento, id_inventario, cantidad, tipo_movimiento, fecha_movimiento, documento_numero
FROM movimientos_inventario
WHERE documento_detalle_id IS NULL OR documento_detalle_id = 0;

-- REPORTE 2: Inconsistencia entre tipo de movimiento (E/S) y tipo de documento (Normalizado)
-- id_tipo = 1 (INGRESO), id_tipo = 2 (SALIDA)
SELECT '--- REPORTE 2: INCONSISTENCIAS DE TIPO (E/S) ---' AS reporte;
SELECT m.id_movimiento, m.tipo_movimiento, t.nombre AS doc_tipo_esperado, d.numero_documento
FROM movimientos_inventario m
JOIN documentos_inventario d ON m.documento_id = d.id_documento
JOIN inv_doc_tipos t ON d.id_doc_tipo = t.id_tipo
WHERE (m.tipo_movimiento = 'ENTRADA' AND t.codigo != 'ING')
   OR (m.tipo_movimiento = 'SALIDA' AND t.codigo != 'OUT');

-- REPORTE 3: Vínculos rotos (Movimientos que apuntan a detalles que ya no existen)
SELECT '--- REPORTE 3: VINCULOS ROTOS (DETALLES FALTANTES) ---' AS reporte;
SELECT m.id_movimiento, m.documento_id, m.documento_detalle_id
FROM movimientos_inventario m
LEFT JOIN documentos_inventario_detalle dd ON m.documento_detalle_id = dd.id_detalle
WHERE m.documento_detalle_id IS NOT NULL 
  AND m.documento_detalle_id != 0 
  AND dd.id_detalle IS NULL;

-- ============================================================================
-- 3. INSPECCIÓN DE BASURA TÉCNICA (IDs 109 - 122)
-- ============================================================================

SELECT '--- REPORTE 4: INSPECCION DE REGISTROS 109-122 ---' AS reporte;
SELECT 
    d.id_documento, 
    d.numero_documento, 
    d.fecha_documento, 
    e.nombre AS estado_actual,
    s.nombre AS subtipo,
    d.observaciones,
    u.nombre AS usuario_creador
FROM documentos_inventario d
LEFT JOIN inv_doc_estados e ON d.id_doc_estado = e.id_estado
LEFT JOIN inv_doc_subtipos s ON d.id_doc_subtipo = s.id_subtipo
LEFT JOIN usuarios u ON d.creado_por = u.id_usuario
WHERE d.id_documento BETWEEN 109 AND 122;
