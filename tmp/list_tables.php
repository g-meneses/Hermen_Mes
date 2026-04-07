<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($tables, JSON_PRETTY_PRINT);
?>
