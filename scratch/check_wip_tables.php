<?php
require_once 'config/database.php';
$db = getDB();
foreach(['movimientos_wip', 'planillas_tejido'] as $t) {
    echo "=== $t ===\n";
    $stmt = $db->query("DESCRIBE $t");
    echo json_encode($stmt->fetchAll(), JSON_PRETTY_PRINT) . "\n";
}
