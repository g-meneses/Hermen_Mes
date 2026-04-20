<?php
require_once 'config/database.php';
$db = getDB();
$sql = "
SELECT 
    ti.id_tipo_inventario, 
    ti.codigo, 
    ti.nombre, 
    ti.icono, 
    ti.color, 
    ti.orden,
    COUNT(i.id_inventario) AS total_items,
    SUM(CASE WHEN i.stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
    SUM(CASE WHEN i.stock_actual > 0 AND i.stock_actual <= i.stock_minimo THEN 1 ELSE 0 END) AS stock_critico,
    SUM(CASE WHEN i.stock_actual > i.stock_minimo THEN 1 ELSE 0 END) AS stock_ok,
    COALESCE(SUM(i.stock_actual * i.costo_unitario), 0) AS valor_total
FROM tipos_inventario ti
LEFT JOIN inventarios i ON ti.id_tipo_inventario = i.id_tipo_inventario AND i.activo = 1
WHERE ti.activo = 1 
GROUP BY ti.id_tipo_inventario, ti.codigo, ti.nombre, ti.icono, ti.color, ti.orden
ORDER BY ti.orden ";

$stmt = $db->query($sql);
$resumen = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($resumen, JSON_PRETTY_PRINT);
