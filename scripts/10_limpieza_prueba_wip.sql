-- ==========================================================
-- SCRIPT DE LIMPIEZA: PURGA DE DATA DE PRUEBA WIP
-- ==========================================================

START TRANSACTION;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Eliminar datos en tablas de auditoría/conciliación
DELETE FROM auditorias_wip_tejido_detalle;
DELETE FROM auditorias_wip_tejido;

-- 2. Eliminar datos en tablas de consumo y pendientes
DELETE FROM consumos_wip_pendientes;
DELETE FROM consumos_wip_detalle;

-- 3. Eliminar planillas de producción histórica
DELETE FROM planillas_tejido;

-- 4. Eliminar movimientos y lotes WIP
DELETE FROM movimientos_wip;
DELETE FROM lote_wip;

-- 5. Restaurar saldos disponibles en SAL-TEJ
-- Reiniciamos el pozo de consumo para todas las entregas confirmadas.
UPDATE documentos_inventario_detalle dd
JOIN documentos_inventario d ON d.id_documento = dd.id_documento
SET dd.saldo_disponible = dd.cantidad
WHERE d.tipo_documento = 'SALIDA' 
  AND (d.tipo_consumo = 'TEJIDO' OR d.numero_documento LIKE 'SAL-TEJ%')
  AND d.estado = 'CONFIRMADO';

-- 6. RESETEAR AUTO-INCREMENTS (Opcional, para limpieza total)
ALTER TABLE lote_wip AUTO_INCREMENT = 1;
ALTER TABLE movimientos_wip AUTO_INCREMENT = 1;
ALTER TABLE consumos_wip_detalle AUTO_INCREMENT = 1;
ALTER TABLE consumos_wip_pendientes AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
