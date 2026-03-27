-- ############################################################################
-- SCRIPT DE LIMPIEZA LEGACY REC- (FASE 5) - VERSION SEGURA
-- ############################################################################
USE mes_hermen;

-- ============================================================================
-- 0. PARAMETROS
-- ============================================================================
SET @rec_pattern = 'REC-%';

-- ============================================================================
-- 1. BACKUP DE SEGURIDAD
-- ============================================================================
SELECT 'FASE 5 - CREANDO BACKUPS DE SEGURIDAD...' AS paso;

DROP TABLE IF EXISTS bkp_f5_movimientos_rec;
CREATE TABLE bkp_f5_movimientos_rec AS
SELECT *
FROM movimientos_inventario
WHERE documento_numero LIKE @rec_pattern;

DROP TABLE IF EXISTS bkp_f5_inventarios_afectados;
CREATE TABLE bkp_f5_inventarios_afectados AS
SELECT *
FROM inventarios
WHERE id_inventario IN (
    SELECT DISTINCT id_inventario
    FROM movimientos_inventario
    WHERE documento_numero LIKE @rec_pattern
);

-- Validacion previa de alcance
SELECT 'REPORTE PREVIO - MOVIMIENTOS REC- A LIMPIAR' AS reporte;
SELECT COUNT(*) AS total_movimientos_rec
FROM movimientos_inventario
WHERE documento_numero LIKE @rec_pattern;

SELECT 'REPORTE PREVIO - ITEMS AFECTADOS' AS reporte;
SELECT DISTINCT id_inventario
FROM movimientos_inventario
WHERE documento_numero LIKE @rec_pattern
ORDER BY id_inventario;

-- ============================================================================
-- 2. LIMPIEZA TRANSACCIONAL
-- ============================================================================
START TRANSACTION;

-- --------------------------------------------------------------------------
-- 2A. Captura de stock y costo promedio previo
-- --------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_stock_pre_rec;
CREATE TEMPORARY TABLE tmp_stock_pre_rec AS
SELECT 
    i.id_inventario,
    i.stock_actual,
    i.costo_promedio,
    i.costo_unitario
FROM inventarios i
WHERE i.id_inventario IN (
    SELECT DISTINCT id_inventario
    FROM movimientos_inventario
    WHERE documento_numero LIKE @rec_pattern
);

-- --------------------------------------------------------------------------
-- 2B. Reverso de stock
-- Regla:
-- - Si el movimiento REC- fue ENTRADA, restamos esa cantidad del stock actual
-- - Si por algun motivo hubiera SALIDA, la devolvemos sumando
-- --------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_ajuste_rec;
CREATE TEMPORARY TABLE tmp_ajuste_rec AS
SELECT 
    id_inventario,
    SUM(
        CASE
            WHEN tipo_movimiento LIKE 'ENTRADA%' THEN -cantidad
            WHEN tipo_movimiento LIKE 'SALIDA%' THEN  cantidad
            ELSE 0
        END
    ) AS ajuste_stock
FROM movimientos_inventario
WHERE documento_numero LIKE @rec_pattern
GROUP BY id_inventario;

UPDATE inventarios i
JOIN tmp_ajuste_rec a
    ON i.id_inventario = a.id_inventario
SET i.stock_actual = i.stock_actual + a.ajuste_stock;

-- --------------------------------------------------------------------------
-- 2C. Eliminacion fisica de movimientos REC-
-- --------------------------------------------------------------------------
DELETE FROM movimientos_inventario
WHERE documento_numero LIKE @rec_pattern;

SELECT ROW_COUNT() AS movimientos_rec_borrados;

-- ============================================================================
-- 3. VALIDACIONES DENTRO DE LA TRANSACCION
-- ============================================================================

-- --------------------------------------------------------------------------
-- 3A. Verificacion de stock antes/despues
-- --------------------------------------------------------------------------
SELECT 'VALIDACION - STOCK ANTES/DESPUES' AS reporte;
SELECT 
    i.id_inventario,
    pre.stock_actual AS stock_antes,
    i.stock_actual AS stock_despues,
    (i.stock_actual - pre.stock_actual) AS diferencia,
    a.ajuste_stock AS ajuste_esperado
FROM inventarios i
JOIN tmp_stock_pre_rec pre
    ON i.id_inventario = pre.id_inventario
LEFT JOIN tmp_ajuste_rec a
    ON i.id_inventario = a.id_inventario
ORDER BY i.id_inventario;

-- --------------------------------------------------------------------------
-- 3B. Validacion de costos (solo inspeccion, no modifica)
-- --------------------------------------------------------------------------
SELECT 'VALIDACION - COSTOS PROMEDIO EN ITEMS AFECTADOS' AS reporte;
SELECT 
    i.id_inventario,
    pre.costo_promedio AS cpp_antes,
    i.costo_promedio AS cpp_despues,
    pre.costo_unitario AS costo_unitario_antes,
    i.costo_unitario AS costo_unitario_despues
FROM inventarios i
JOIN tmp_stock_pre_rec pre
    ON i.id_inventario = pre.id_inventario
ORDER BY i.id_inventario;

-- --------------------------------------------------------------------------
-- 3C. Confirmar que no quedan movimientos REC-
-- --------------------------------------------------------------------------
SELECT 'VALIDACION - MOVIMIENTOS REC- RESTANTES' AS reporte;
SELECT COUNT(*) AS rec_restantes
FROM movimientos_inventario
WHERE documento_numero LIKE @rec_pattern;

-- --------------------------------------------------------------------------
-- 3D. Auditoria general de huerfanos (diagnostico, no bloquea por si sola)
-- --------------------------------------------------------------------------
SELECT 'AUDITORIA - HUERFANOS GENERALES POST-LIMPIEZA' AS reporte;
SELECT COUNT(*) AS huerfanos_generales
FROM movimientos_inventario
WHERE documento_id IS NULL OR documento_detalle_id IS NULL;

-- --------------------------------------------------------------------------
-- 3E. Muestra de huerfanos generales (opcional diagnostico)
-- --------------------------------------------------------------------------
SELECT 'AUDITORIA - DETALLE DE HUERFANOS GENERALES' AS reporte;
SELECT 
    id_movimiento,
    documento_numero,
    documento_id,
    documento_detalle_id,
    id_inventario,
    tipo_movimiento,
    cantidad,
    fecha_movimiento
FROM movimientos_inventario
WHERE documento_id IS NULL OR documento_detalle_id IS NULL
ORDER BY id_movimiento
LIMIT 50;

-- ============================================================================
-- 4. FINALIZACION
-- ============================================================================

COMMIT;

SELECT 'LIMPIEZA LEGACY REC- COMPLETADA Y PERSISTIDA' AS estado_final;
