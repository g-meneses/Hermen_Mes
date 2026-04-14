<?php
require_once 'config/database.php';
try {
    $db = getDB();
    
    echo "Iniciando migración de planillas_tejido...\n";
    
    // 1. Alterar id_documento_salida para permitir NULL
    $db->exec("ALTER TABLE planillas_tejido MODIFY COLUMN id_documento_salida INT(11) NULL");
    echo "Column 'id_documento_salida' en 'planillas_tejido' modificada a NULL exitosamente.\n";
    
    echo "Migración completada con éxito.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
