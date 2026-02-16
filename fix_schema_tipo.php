<?php
require_once 'config/database.php';
try {
    $db = getDB();
    $db->exec("ALTER TABLE ordenes_compra ADD COLUMN tipo_compra ENUM('LOCAL','IMPORTACION') DEFAULT 'LOCAL' AFTER numero_orden");
    echo "Column tipo_compra added successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
