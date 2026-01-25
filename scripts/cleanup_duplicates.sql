-- =====================================================
-- Script de Limpieza de Duplicados y Aplicación de Restricción UNIQUE
-- Sistema ERP Hermen Ltda.
-- Fecha: 2026-01-24
-- =====================================================

USE mes_hermen;

-- =====================================================
-- PASO 1: IDENTIFICAR DUPLICADOS
-- =====================================================
SELECT 
    codigo,
    COUNT(*) as cantidad,
    GROUP_CONCAT(id_inventario ORDER BY id_inventario) as ids_duplicados,
    MIN(id_inventario) as id_a_conservar
FROM inventarios
WHERE codigo IS NOT NULL AND codigo != ''
GROUP BY codigo
HAVING COUNT(*) > 1
ORDER BY cantidad DESC;

-- =====================================================
-- PASO 2: BACKUP DE SEGURIDAD (RECOMENDADO)
-- =====================================================
-- Crear tabla de respaldo antes de eliminar
CREATE TABLE IF NOT EXISTS inventarios_backup_duplicados AS
SELECT * FROM inventarios
WHERE codigo IN (
    SELECT codigo 
    FROM inventarios 
    WHERE codigo IS NOT NULL AND codigo != ''
    GROUP BY codigo 
    HAVING COUNT(*) > 1
);

-- =====================================================
-- PASO 3: ELIMINAR DUPLICADOS (CONSERVAR EL MÁS ANTIGUO)
-- =====================================================
-- Esta query elimina todos los duplicados excepto el que tiene el ID más bajo
DELETE i1 FROM inventarios i1
INNER JOIN inventarios i2 
WHERE 
    i1.codigo = i2.codigo 
    AND i1.id_inventario > i2.id_inventario
    AND i1.codigo IS NOT NULL 
    AND i1.codigo != '';

-- =====================================================
-- PASO 4: VERIFICAR QUE NO QUEDEN DUPLICADOS
-- =====================================================
SELECT 
    codigo,
    COUNT(*) as cantidad
FROM inventarios
WHERE codigo IS NOT NULL AND codigo != ''
GROUP BY codigo
HAVING COUNT(*) > 1;

-- Si la query anterior no devuelve resultados, procedemos con la restricción

-- =====================================================
-- PASO 5: APLICAR RESTRICCIÓN UNIQUE
-- =====================================================
-- Primero verificar si ya existe la restricción
SELECT 
    CONSTRAINT_NAME, 
    CONSTRAINT_TYPE 
FROM information_schema.TABLE_CONSTRAINTS 
WHERE 
    TABLE_SCHEMA = 'mes_hermen' 
    AND TABLE_NAME = 'inventarios' 
    AND CONSTRAINT_TYPE = 'UNIQUE';

-- Aplicar la restricción (solo si no existe)
ALTER TABLE inventarios 
ADD UNIQUE KEY unique_codigo (codigo);

-- =====================================================
-- PASO 6: VERIFICACIÓN FINAL
-- =====================================================
-- Intentar insertar un duplicado (debe fallar)
-- INSERT INTO inventarios (codigo, nombre, id_tipo_inventario, id_categoria, id_unidad, activo) 
-- VALUES ('TEST-DUP-001', 'Test Duplicado', 1, 1, 1, 1);
-- INSERT INTO inventarios (codigo, nombre, id_tipo_inventario, id_categoria, id_unidad, activo) 
-- VALUES ('TEST-DUP-001', 'Test Duplicado 2', 1, 1, 1, 1);
-- La segunda inserción debe fallar con: Duplicate entry 'TEST-DUP-001' for key 'unique_codigo'

-- =====================================================
-- NOTAS IMPORTANTES
-- =====================================================
-- 1. Este script debe ejecutarse en un entorno de prueba primero
-- 2. Se recomienda hacer un backup completo de la BD antes de ejecutar
-- 3. Los duplicados eliminados se guardan en inventarios_backup_duplicados
-- 4. Si necesitas restaurar algún registro, consulta la tabla de backup
-- 5. La restricción UNIQUE impedirá duplicados a nivel de motor de BD
-- =====================================================
