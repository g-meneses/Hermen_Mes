<?php
require_once 'config/database.php';
$db = getDB();

echo "--- Estados de Recepción actualizados ---\n";
$stmt = $db->query("SELECT id_recepcion, numero_recepcion, estado FROM recepciones_compra ORDER BY fecha_recepcion DESC LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id_recepcion']} | Numero: {$row['numero_recepcion']} | Estado: {$row['estado']}\n";
}

// Opcional: Cambiar la última a PENDIENTE para prueba
$lastId = $db->query("SELECT id_recepcion FROM recepciones_compra ORDER BY id_recepcion DESC LIMIT 1")->fetchColumn();
if ($lastId) {
    $db->prepare("UPDATE recepciones_compra SET estado = 'PENDIENTE' WHERE id_recepcion = ?")->execute([$lastId]);
    echo "\n>>> Se ha cambiado la recepción ID $lastId a PENDIENTE para que puedas ver el botón de validación.\n";
}
?>