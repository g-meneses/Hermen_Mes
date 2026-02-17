<?php
require_once 'config/database.php';
try {
    $db = getDB();
    $db->exec("ALTER TABLE ordenes_compra MODIFY COLUMN condicion_pago ENUM('CONTADO','CREDITO_15','CREDITO_30','CREDITO_45','CREDITO_60','CREDITO_90','A_CONVENIR')");
    echo "Column condicion_pago updated successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
