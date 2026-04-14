<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query("DESCRIBE planillas_tejido");
echo json_encode($stmt->fetchAll(), JSON_PRETTY_PRINT);
