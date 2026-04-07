<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$res = $db->query("DESCRIBE detalle_produccion_tejeduria")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_PRETTY_PRINT);
?>
