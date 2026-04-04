<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query("DESCRIBE solicitudes_compra_detalle");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
