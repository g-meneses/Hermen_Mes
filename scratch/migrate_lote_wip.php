<?php
require_once 'config/database.php';
try {
    $db = getDB();
    
    echo "Iniciando migración de esquema...\n";
    
    // 1. Alterar id_documento_consumo para permitir NULL
    $db->exec("ALTER TABLE lote_wip MODIFY COLUMN id_documento_consumo INT(11) NULL");
    echo "Column 'id_documento_consumo' modificada a NULL exitosamente.\n";
    
    // 2. Alterar id_documento_salida para permitir NULL
    $db->exec("ALTER TABLE lote_wip MODIFY COLUMN id_documento_salida INT(11) NULL");
    echo "Column 'id_documento_salida' modificada a NULL exitosamente.\n";
    
    echo "Migración completada con éxito.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
