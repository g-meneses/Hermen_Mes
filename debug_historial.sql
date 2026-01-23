
-- Check inventory types
SELECT * FROM tipos_inventario;

-- Check a few movements and their associated inventory type
SELECT 
    m.id_movimiento, 
    m.id_inventario, 
    i.codigo as item_code, 
    i.id_tipo_inventario, 
    t.codigo as tipo_code,
    m.fecha_movimiento
FROM movimientos_inventario m
JOIN inventarios i ON m.id_inventario = i.id_inventario
JOIN tipos_inventario t ON i.id_tipo_inventario = t.id_tipo_inventario
LIMIT 20;
