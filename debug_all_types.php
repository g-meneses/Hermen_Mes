<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query("SELECT * FROM tipos_inventario");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode($row) . "\n";
}
