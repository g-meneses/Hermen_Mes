-- =============================================================================
-- Reparación de saldo_disponible NULL en documentos SAL-TEJ existentes
-- EJECUTAR EN: phpMyAdmin → mes_hermen → SQL
-- PROPÓSITO: 
--   Los SAL-TEJ creados antes del fix en salidas_mp.php tienen 
--   saldo_disponible = NULL porque el INSERT no lo incluía.
--   El motor FIFO filtra AND dd.saldo_disponible > 0, por lo que
--   NULL > 0 = FALSE → los documentos eran invisibles para el consumo.
-- SEGURIDAD:
--   Solo afecta documentos SAL-TEJ CONFIRMADOS con saldo_disponible IS NULL.
--   No toca documentos ya consumidos (saldo_disponible < cantidad).
--   No toca documentos del nuevo modelo (integrado_wip_nuevo_modelo = 1).
-- =============================================================================

-- Ver cuántos registros serán afectados (EJECUTAR PRIMERO para revisar)
SELECT 
    d.numero_documento,
    d.fecha_documento,
    i.nombre AS componente,
    dd.cantidad,
    dd.saldo_disponible AS saldo_actual
FROM documentos_inventario_detalle dd
JOIN documentos_inventario d ON d.id_documento = dd.id_documento
JOIN inventarios i ON i.id_inventario = dd.id_inventario
WHERE dd.saldo_disponible IS NULL
  AND d.tipo_documento = 'SALIDA'
  AND (d.tipo_consumo = 'TEJIDO' OR d.numero_documento LIKE 'SAL-TEJ%')
  AND d.estado = 'CONFIRMADO'
  AND COALESCE(d.integrado_wip_nuevo_modelo, 0) = 0
ORDER BY d.fecha_documento DESC, i.nombre;

-- =============================================================================
-- EJECUTAR SOLO SI LOS DATOS DE ARRIBA SON CORRECTOS:
-- =============================================================================

UPDATE documentos_inventario_detalle dd
JOIN documentos_inventario d ON d.id_documento = dd.id_documento
SET dd.saldo_disponible = dd.cantidad
WHERE dd.saldo_disponible IS NULL
  AND d.tipo_documento = 'SALIDA'
  AND (d.tipo_consumo = 'TEJIDO' OR d.numero_documento LIKE 'SAL-TEJ%')
  AND d.estado = 'CONFIRMADO'
  AND COALESCE(d.integrado_wip_nuevo_modelo, 0) = 0;

-- Verificar resultado
SELECT 
    d.numero_documento,
    i.nombre AS componente,
    dd.cantidad,
    dd.saldo_disponible
FROM documentos_inventario_detalle dd
JOIN documentos_inventario d ON d.id_documento = dd.id_documento
JOIN inventarios i ON i.id_inventario = dd.id_inventario
WHERE d.tipo_documento = 'SALIDA'
  AND (d.tipo_consumo = 'TEJIDO' OR d.numero_documento LIKE 'SAL-TEJ%')
  AND d.estado = 'CONFIRMADO'
ORDER BY d.fecha_documento DESC, i.nombre;
