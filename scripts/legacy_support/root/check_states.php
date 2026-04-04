<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query("SELECT id_orden_compra, numero_orden, estado FROM ordenes_compra");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($rows, JSON_PRETTY_PRINT);
