<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query("SELECT usuario, nombre_completo, rol FROM usuarios WHERE estado = 'activo' LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
