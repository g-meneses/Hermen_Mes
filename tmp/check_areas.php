<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$stmt = $db->query("SELECT * FROM areas_produccion");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
?>
