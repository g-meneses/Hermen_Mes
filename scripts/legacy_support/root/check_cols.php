<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query("DESCRIBE ordenes_compra");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
