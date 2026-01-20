-- =====================================================
-- SCRIPT DE CORRECCIÓN DE FECHAS FUTURAS EN KARDEX
-- Sistema MES Hermen Ltda.
-- =====================================================

-- Este script corrige movimientos con fechas futuras
-- reemplazándolas por la fecha actual

-- 1. IDENTIFICAR MOVIMIENTOS CON FECHAS FUTURAS
SELECT 
    id_movimiento,
    id_inventario,
    fecha_movimiento,
    tipo_movimiento,
    documento_numero,
    DATEDIFF(fecha_movimiento, CURDATE()) as dias_futuro
FROM movimientos_inventario
WHERE DATE(fecha_movimiento) > CURDATE()
ORDER BY fecha_movimiento DESC;

-- 2. CORREGIR FECHAS FUTURAS (reemplazar por fecha actual)
UPDATE movimientos_inventario
SET fecha_movimiento = NOW()
WHERE DATE(fecha_movimiento) > CURDATE();

-- 3. VERIFICAR QUE NO QUEDEN FECHAS FUTURAS
SELECT COUNT(*) as movimientos_futuros
FROM movimientos_inventario
WHERE DATE(fecha_movimiento) > CURDATE();

-- 4. OPCIONAL: Ver el rango de fechas actual
SELECT 
    MIN(fecha_movimiento) as fecha_mas_antigua,
    MAX(fecha_movimiento) as fecha_mas_reciente,
    COUNT(*) as total_movimientos
FROM movimientos_inventario
WHERE estado = 'ACTIVO';
