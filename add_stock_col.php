<?php
require_once 'config/database.php';
try {
    $db = getDB();
    $db->exec("ALTER TABLE solicitudes_compra_detalle ADD COLUMN stock_solicitud DECIMAL(15,4) DEFAULT 0 AFTER cantidad_solicitada");
    echo "Column stock_solicitud added successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
