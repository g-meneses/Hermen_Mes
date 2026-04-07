<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

try {
    $db->exec("CREATE INDEX idx_fecha_produccion ON produccion_tejeduria(fecha_produccion)");
    echo "Index idx_fecha_produccion created.\n";
} catch (Exception $e) { echo "Error or already exists: " . $e->getMessage() . "\n"; }

try {
    $db->exec("CREATE INDEX idx_det_prod_id ON detalle_produccion_tejeduria(id_produccion)");
    echo "Index idx_det_prod_id created.\n";
} catch (Exception $e) { echo "Error or already exists: " . $e->getMessage() . "\n"; }

try {
    $db->exec("CREATE INDEX idx_det_prod_producto ON detalle_produccion_tejeduria(id_producto)");
    echo "Index idx_det_prod_producto created.\n";
} catch (Exception $e) { echo "Error or already exists: " . $e->getMessage() . "\n"; }
?>
