<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/database.php';
try {
    $db = getDB();
    $sql = "UPDATE ordenes_compra SET estado = 'ENVIADA' WHERE numero_orden = 'OC-202602-001'";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    echo "Rows affected: " . $stmt->rowCount();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
