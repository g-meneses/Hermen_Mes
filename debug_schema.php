<?php
require_once 'config/database.php';
$db = getDB();

$tables = ['kardex_inventario', 'documentos_inventario', 'documentos_inventario_detalle', 'inventarios'];

foreach ($tables as $table) {
    echo "--- TABLE: $table ---\n";
    $stmt = $db->query("SHOW CREATE TABLE $table");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row['Create Table'] . "\n\n";
}
