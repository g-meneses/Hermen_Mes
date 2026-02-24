<?php
require_once 'config/database.php';
$db = getDB();

try {
    echo "Modificando estructura de tabla...\n";
    $db->exec("ALTER TABLE recepciones_compra MODIFY COLUMN estado ENUM('BORRADOR','PENDIENTE','CONFIRMADA','PROCESADA','ANULADA') DEFAULT 'PENDIENTE'");
    echo "Estructura modificada con éxito.\n";

    echo "Actualizando registros existentes...\n";
    // Forzamos las últimas 2 recepciones a PENDIENTE para que el usuario las pruebe
    $db->exec("UPDATE recepciones_compra SET estado = 'PENDIENTE' WHERE estado = '' OR id_recepcion >= 10");
    echo "Registros actualizados.\n";

    $stmt = $db->query("SELECT id_recepcion, numero_recepcion, estado FROM recepciones_compra ORDER BY id_recepcion DESC LIMIT 3");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id_recepcion']} | Num: {$row['numero_recepcion']} | Status: '{$row['estado']}'\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>