<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

// Check categories for yarns
$res = $db->query("SELECT * FROM categorias_inventario")->fetchAll(PDO::FETCH_ASSOC);
echo "CATEGORIES:\n";
echo json_encode($res, JSON_PRETTY_PRINT);

// Check inventory types
$res2 = $db->query("SELECT * FROM tipos_inventario")->fetchAll(PDO::FETCH_ASSOC);
echo "\nTYPES:\n";
echo json_encode($res2, JSON_PRETTY_PRINT);

// Check typical yarn item in inventarios
$res3 = $db->query("SELECT i.*, c.nombre as categoria_nombre FROM inventarios i LEFT JOIN categorias_inventario c ON i.id_categoria = c.id_categoria LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "\nINVENTARIOS SAMPLE:\n";
echo json_encode($res3, JSON_PRETTY_PRINT);

// Check indexes for production
$res4 = $db->query("SHOW INDEX FROM produccion_tejeduria")->fetchAll(PDO::FETCH_ASSOC);
echo "\nINDEXES produccion_tejeduria:\n";
echo json_encode($res4, JSON_PRETTY_PRINT);

$res5 = $db->query("SHOW INDEX FROM detalle_produccion_tejeduria")->fetchAll(PDO::FETCH_ASSOC);
echo "\nINDEXES detalle_produccion_tejeduria:\n";
echo json_encode($res5, JSON_PRETTY_PRINT);
?>
