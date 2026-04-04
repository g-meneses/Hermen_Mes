<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query("DESCRIBE ordenes_compra");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
