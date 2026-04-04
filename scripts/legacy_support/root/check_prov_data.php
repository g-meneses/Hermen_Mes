<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query("SELECT * FROM proveedores LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
