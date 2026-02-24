<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query("SHOW CREATE TABLE recepciones_compra");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "SCHEMA:\n" . $row['Create Table'] . "\n\n";

$stmt = $db->query("SELECT id_recepcion, numero_recepcion, estado FROM recepciones_compra ORDER BY id_recepcion DESC LIMIT 3");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id_recepcion']} | Num: {$row['numero_recepcion']} | Status: '{$row['estado']}'\n";
}
?>