<?php
require_once 'c:/xampp/htdocs/mes_hermen/config/database.php';
$db = getDB();
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo implode("\n", $tables);
?>
