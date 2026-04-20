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
    COALESCE(SUM(i.stock_actual * i.costo_unitario), 0) AS valor_total
FROM tipos_inventario ti
LEFT JOIN inventarios i ON ti.id_tipo_inventario = i.id_tipo_inventario AND i.activo = 1
WHERE ti.activo = 1
GROUP BY ti.id_tipo_inventario, ti.codigo, ti.nombre, ti.icono, ti.color, ti.orden
ORDER BY ti.orden ";

$stmt = $db->query($sql);
$resumen = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Parche WIP (Copiado de api/centro_inventarios.php)
foreach ($resumen as &$tipo) {
    if (strtoupper($tipo['codigo']) === 'WIP') {
        $stmtLotes = $db->query("SELECT COUNT(id_lote_wip) AS activos, COALESCE(SUM(costo_mp_acumulado), 0) AS valor FROM lote_wip WHERE estado_lote NOT IN ('CERRADO', 'ANULADO')");
        $wipStats = $stmtLotes->fetch(PDO::FETCH_ASSOC);
        $tipo['total_items'] = (int)$wipStats['activos'];
        $tipo['valor_total'] = (float)$wipStats['valor'];
    }
}

echo json_encode($resumen, JSON_PRETTY_PRINT);
