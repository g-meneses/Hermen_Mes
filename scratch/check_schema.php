<?php
require_once 'config/database.php';
$db = getDB();

$tables = ['planillas_tejido', 'lote_wip', 'movimientos_wip', 'consumos_wip_pendientes', 'maquinas', 'turnos', 'productos_tejidos'];

foreach ($tables as $table) {
    echo "\n--- Table: $table ---\n";
    try {
        $stmt = $db->query("DESCRIBE $table");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$row['Field']} - {$row['Type']}\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "\n--- Sample Data: planillas_tejido ---\n";
$stmt = $db->query("SELECT * FROM planillas_tejido ORDER BY id_planilla DESC LIMIT 1");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
