-- Script para resetear secuencias de documentos de salida
-- Sistema MES Hermen Ltda.
-- Uso: Ejecutar solo si se desea reiniciar los contadores de documentos

-- IMPORTANTE: Este script eliminará los contadores actuales.
-- Asegúrate de hacer un backup antes de ejecutar.

-- Ver estado actual de las secuencias
SELECT 
    tipo_documento,
    prefijo,
    anio,
    mes,
    ultimo_numero,
    CONCAT(prefijo, '-', anio, mes, '-', LPAD(ultimo_numero, 4, '0')) AS ultimo_generado
FROM secuencias_documento
WHERE tipo_documento = 'SALIDA'
ORDER BY anio DESC, mes DESC, prefijo;

-- OPCIÓN 1: Eliminar TODAS las secuencias de salida (reinicia todo a 0001)
-- DESCOMENTAR SOLO SI ESTÁS SEGURO:
-- DELETE FROM secuencias_documento WHERE tipo_documento = 'SALIDA';

-- OPCIÓN 2: Eliminar solo secuencias del mes actual
-- DESCOMENTAR SOLO SI ESTÁS SEGURO:
-- DELETE FROM secuencias_documento 
-- WHERE tipo_documento = 'SALIDA' 
-- AND anio = YEAR(CURDATE()) 
-- AND mes = LPAD(MONTH(CURDATE()), 2, '0');

-- OPCIÓN 3: Resetear un prefijo específico (ejemplo: OUT-CAQ-P)
-- DESCOMENTAR Y MODIFICAR SEGÚN NECESIDAD:
-- DELETE FROM secuencias_documento 
-- WHERE tipo_documento = 'SALIDA' 
-- AND prefijo = 'OUT-CAQ-P'
-- AND anio = YEAR(CURDATE()) 
-- AND mes = LPAD(MONTH(CURDATE()), 2, '0');

-- VERIFICACIÓN: Ver qué secuencias quedan después del reset
SELECT 
    tipo_documento,
    prefijo,
    anio,
    mes,
    ultimo_numero
FROM secuencias_documento
WHERE tipo_documento = 'SALIDA'
ORDER BY anio DESC, mes DESC, prefijo;

-- NOTA: Después de ejecutar el DELETE, el próximo documento 
-- de ese tipo/prefijo/mes comenzará automáticamente en 0001
