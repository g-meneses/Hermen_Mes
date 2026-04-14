<?php
require_once 'config/database.php';
$db = getDB();

$q = "SELECT id_producto, codigo_producto, descripcion_completa FROM productos_tejidos WHERE descripcion_completa LIKE '%Pantyhose Lujo Puntera Reforzada Talla S%'";
$prod = $db->query($q)->fetch(PDO::FETCH_ASSOC);

if ($prod) {
    echo "PRODUCT FOUND: {$prod['id_producto']} ({$prod['descripcion_completa']})\n";
    $id = $prod['id_producto'];
    
    // Check BOM
    $stmt = $db->prepare("
        SELECT b.id_bom, b.descripcion, bd.id_inventario, bd.gramos_por_docena, i.nombre, i.stock_actual,
        (SELECT SUM(saldo_disponible) FROM documentos_inventario_detalle WHERE id_inventario = i.id_inventario) as saldo_wip
        FROM bom_tejidos b
        JOIN bom_tejidos_detalle bd ON bd.id_bom = b.id_bom
        JOIN inventarios i ON i.id_inventario = bd.id_inventario
        WHERE b.id_producto = ? AND b.activo = 1
    ");
    $stmt->execute([$id]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "BOM DETAILS:\n";
    print_r($details);

} else {
    echo "PRODUCT NOT FOUND\n";
}
