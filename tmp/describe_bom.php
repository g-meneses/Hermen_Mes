<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$res = $db->query("DESCRIBE bom_productos")->fetchAll(PDO::FETCH_ASSOC);
echo "BOM CABECERA:\n";
echo json_encode($res, JSON_PRETTY_PRINT);
$res2 = $db->query("DESCRIBE bom_productos_detalle")->fetchAll(PDO::FETCH_ASSOC);
echo "\nBOM DETALLE:\n";
echo json_encode($res2, JSON_PRETTY_PRINT);
?>
