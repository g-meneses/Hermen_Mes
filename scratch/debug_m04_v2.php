<?php
require_once 'config/database.php';
$db = getDB();

$idProducto = 1; // Pantyhose Lujo Puntera Reforzada Talla S

// 1. Get BOM
$stmt = $db->prepare("
    SELECT id_bom, id_producto, codigo_bom, version_bom, merma_pct
    FROM bom_productos
    WHERE id_producto = ? AND estado = 'ACTIVO'
    ORDER BY fecha_vigencia_desde DESC, id_bom DESC
    LIMIT 1
");
$stmt->execute([$idProducto]);
$bom = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bom) {
    die("BOM NOT FOUND FOR PRODUCT $idProducto\n");
}

echo "BOM FOUND: {$bom['codigo_bom']} (ID: {$bom['id_bom']})\n";

// 2. Get Details and Stock
$stmtDet = $db->prepare("
    SELECT d.id_inventario, d.gramos_por_docena, i.nombre, i.stock_actual,
    (SELECT SUM(saldo_disponible) FROM documentos_inventario_detalle WHERE id_inventario = i.id_inventario) as saldo_wip
    FROM bom_productos_detalle d
    JOIN inventarios i ON i.id_inventario = d.id_inventario
    WHERE d.id_bom = ?
");
$stmtDet->execute([$bom['id_bom']]);
$details = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

echo "BOM DETAILS & STOCK:\n";
foreach ($details as $d) {
    echo "- Componente: {$d['nombre']} (ID: {$d['id_inventario']})\n";
    echo "  Gramos/Docena: {$d['gramos_por_docena']}\n";
    echo "  Stock Actual (Inventarios): {$d['stock_actual']}\n";
    echo "  Saldo Disponible (SAL-TEJ): " . ($d['saldo_wip'] ?: '0.0000') . "\n";
}
