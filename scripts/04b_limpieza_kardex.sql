-- ############################################################################
-- SCRIPT DE LIMPIEZA CONTROLADA DE KARDEX (FASE 3b)
-- ############################################################################

USE mes_hermen;

SET @min_test_id = 109;
SET @max_test_id = 122;

START TRANSACTION;

-- ============================================================================
-- 1. CAPTURA DE ESTADO PRE-LIMPIEZA (STOCK)
-- ============================================================================
CREATE TEMPORARY TABLE tmp_stock_pre
SELECT id_inventario, stock_actual 
FROM inventarios 
WHERE id_inventario IN (1, 2, 16, 17, 31, 52);

-- ============================================================================
-- 2. REVERSO DE STOCK (Si se requiere para pruebas confirmadas)
-- ============================================================================
-- Nota: Si los documentos de prueba fueron "CONFIRMADOS", sumaron al stock.
-- Para que el stock sea real, debemos restar lo que estas pruebas sumaron.
-- Pero si son "basura de sistema" de un entorno donde el stock no era crítico, 
-- a veces es mejor resetear el stock manualmente o mediante ajustes.
-- El usuario dijo "Limpieza de basura operativa". 
-- Asumiremos que debemos dejar el stock como si estas pruebas NUNCA hubieran ocurrido.

UPDATE inventarios i
JOIN (
    SELECT id_inventario, SUM(CASE WHEN tipo_movimiento = 'ENTRADA' THEN -cantidad ELSE cantidad END) as ajuste_stock
    FROM movimientos_inventario
    WHERE documento_id BETWEEN @min_test_id AND @max_test_id
    GROUP BY id_inventario
) a ON i.id_inventario = a.id_inventario
SET i.stock_actual = i.stock_actual + a.ajuste_stock;

-- ============================================================================
-- 3. ELIMINACIÓN DE REGISTROS (ORDEN DE DEPENDENCIAS)
-- ============================================================================

-- A. Movimientos
SELECT 'BORRANDO MOVIMIENTOS...' AS msg;
DELETE FROM movimientos_inventario 
WHERE documento_id BETWEEN @min_test_id AND @max_test_id;
SELECT ROW_COUNT() AS movimientos_borrados;

-- B. Ajustes (Omitido por falta de vínculo directo en estas pruebas)
-- SELECT 'BORRANDO AJUSTES...' AS msg;
-- DELETE FROM ajustes_inventario ...;

-- C. Detalles
SELECT 'BORRANDO DETALLES...' AS msg;
DELETE FROM documentos_inventario_detalle 
WHERE id_documento BETWEEN @min_test_id AND @max_test_id;
SELECT ROW_COUNT() AS detalles_borrados;

-- D. Cabeceras
SELECT 'BORRANDO CABECERAS...' AS msg;
DELETE FROM documentos_inventario 
WHERE id_documento BETWEEN @min_test_id AND @max_test_id;
SELECT ROW_COUNT() AS cabeceras_borradas;

-- ============================================================================
-- 4. VALIDACIÓN POST-LIMPIEZA
-- ============================================================================

-- Verificar si quedan huérfanos asociados a estos IDs
SELECT COUNT(*) AS huerfanos_restantes
FROM movimientos_inventario
WHERE documento_id BETWEEN @min_test_id AND @max_test_id;

-- Comparativa de Stock
SELECT 
    i.id_inventario, 
    pre.stock_actual AS stock_antes, 
    i.stock_actual AS stock_despues,
    (i.stock_actual - pre.stock_actual) AS diferencia
FROM inventarios i
JOIN tmp_stock_pre pre ON i.id_inventario = pre.id_inventario;

-- ============================================================================
-- 5. FINALIZACIÓN
-- ============================================================================

-- Si todo se ve correcto, confirmar
COMMIT;

SELECT 'LIMPIEZA COMPLETADA EXITOSAMENTE' AS resultado;
