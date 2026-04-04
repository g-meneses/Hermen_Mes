<?php
require_once 'config/database.php';
$db = getDB();

echo "=== Diagnóstico de tabla usuarios ===\n\n";

$result = $db->query("SHOW CREATE TABLE usuarios");
$row = $result->fetch(PDO::FETCH_ASSOC);
echo $row['Create Table'] . "\n\n";

echo "=== Últimos usuarios ===\n";
$stmt = $db->query("SELECT id_usuario, usuario, codigo_usuario FROM usuarios ORDER BY id_usuario DESC LIMIT 10");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  ID: {$row['id_usuario']}, Usuario: {$row['usuario']}, Código: {$row['codigo_usuario']}\n";
}

echo "\n=== MAX ID actual ===\n";
$max = $db->query("SELECT MAX(id_usuario) as max_id FROM usuarios")->fetch();
echo "  MAX(id_usuario) = " . $max['max_id'] . "\n";
?>