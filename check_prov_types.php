<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query("SELECT DISTINCT tipo FROM proveedores");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
