<?php
require_once 'c:\xampp\htdocs\mes_hermen\config\database.php';
$db = getDB();

echo "--- Lineas ---\n";
$stmt = $db->query("SELECT * FROM lineas_producto limit 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- Tipos ---\n";
$stmt = $db->query("SELECT * FROM tipos_producto limit 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
