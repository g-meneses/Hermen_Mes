<?php
require_once 'config/database.php';
try {
    $db = getDB();
    foreach(['consumos_wip_detalle', 'consumos_wip_pendientes', 'auditorias_wip_tejido', 'auditorias_wip_tejido_detalle'] as $table) {
        echo "=== SCHEMA FOR $table ===\n";
        $stmt = $db->query("DESCRIBE $table");
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
