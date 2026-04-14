<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query('SHOW TABLES');
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo implode("\n", $tables);
