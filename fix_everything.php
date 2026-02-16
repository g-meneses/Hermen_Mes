<?php
require_once 'config/database.php';
try {
    $db = getDB();
    $db->exec("ALTER TABLE ordenes_compra MODIFY COLUMN estado ENUM('BORRADOR','EMITIDA','ENVIADA','CONFIRMADA','EN_RECEPCION','RECIBIDA','RECIBIDA_PARCIAL','RECIBIDA_TOTAL','CERRADA','CANCELADA') DEFAULT 'BORRADOR'");
    echo "Table altered successfully\n";
    $db->prepare("UPDATE ordenes_compra SET estado = 'ENVIADA' WHERE id_orden_compra = 2")->execute();
    echo "State updated to ENVIADA for OC 2";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
