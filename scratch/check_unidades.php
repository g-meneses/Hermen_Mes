<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query("DESCRIBE unidades_medida");
echo json_encode($stmt->fetchAll(), JSON_PRETTY_PRINT);
