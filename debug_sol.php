<?php
require 'config/database.php';
$db = getDB();

$sql = "
SELECT s.id_solicitud, s.numero_solicitud, s.fecha_solicitud, 
       s.motivo, s.prioridad, s.monto_estimado,
       u.nombre_completo as solicitante_nombre
FROM solicitudes_compra s
LEFT JOIN usuarios u ON s.id_usuario_solicitante = u.id_usuario
WHERE s.estado = 'APROBADA' 
  AND (s.convertida_oc = 0 OR s.convertida_oc IS NULL)
ORDER BY s.fecha_solicitud DESC
";
$stmt = $db->query($sql);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($solicitudes as &$sol) {
    $stmtDet = $db->prepare("
        SELECT d.id_detalle, d.id_producto, d.descripcion_producto, 
               d.cantidad_solicitada, d.unidad_medida, d.id_tipo_inventario,
               d.codigo_producto, d.precio_estimado
        FROM solicitudes_compra_detalle d
        WHERE d.id_solicitud = ?
    ");
    $stmtDet->execute([$sol['id_solicitud']]);
    $sol['detalles'] = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
}
echo json_encode(["success" => true, "count" => count($solicitudes), "data" => $solicitudes]);
?>