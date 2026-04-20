<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query("SELECT * FROM tipos_inventario");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results, JSON_PRETTY_PRINT);
