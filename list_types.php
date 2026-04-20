<?php
require_once 'config/database.php';
$db = getDB();
$stmt = $db->query("SELECT id_tipo_inventario, codigo, nombre, activo FROM tipos_inventario");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id_tipo_inventario'] . " | Código: " . $row['codigo'] . " | Nombre: " . $row['nombre'] . " | Activo: " . $row['activo'] . "\n";
}
