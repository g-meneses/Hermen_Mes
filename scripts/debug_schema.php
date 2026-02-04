<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$stmt = $db->query("SHOW CREATE TABLE proveedores");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($row);
