<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query("DESCRIBE proveedores");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
