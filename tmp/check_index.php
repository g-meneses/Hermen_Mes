<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
$stmt = $db->query("SHOW INDEX FROM lote_wip");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
?>
