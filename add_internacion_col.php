<?php
require_once 'config/database.php';
try {
    $db = getDB();
    $db->exec("ALTER TABLE ordenes_compra_detalle ADD COLUMN precio_unitario_internacion DECIMAL(15,4) DEFAULT NULL AFTER precio_unitario");
    echo "Column added successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
