<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query("DESCRIBE proveedores");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo $c['Field'] . " (" . $c['Type'] . ")\n";
}
?>