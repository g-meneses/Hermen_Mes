-- ==========================================================
-- SCRIPT DE REVERSIÓN (ROLLBACK): SISTEMA WIP INTEGRAL
-- ==========================================================

START TRANSACTION;

-- 1. ELIMINAR TABLAS NUEVAS (CUIDADO: ESTO BORRARÁ DATOS SI YA SE USARON)
DROP TABLE IF EXISTS auditorias_wip_tejido_detalle;
DROP TABLE IF EXISTS auditorias_wip_tejido;
DROP TABLE IF EXISTS consumos_wip_pendientes;
DROP TABLE IF EXISTS consumos_wip_detalle;

-- 2. ELIMINAR CAMPO ADICIONAL
-- Nota: En algunas versiones de MySQL/MariaDB no se puede hacer DROP COLUMN IF EXISTS fácilmente, 
-- pero aquí asumimos ejecución estándar.
ALTER TABLE documentos_inventario_detalle DROP COLUMN IF EXISTS saldo_disponible;

COMMIT;
