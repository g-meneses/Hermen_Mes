<?php
require_once 'config/database.php';
$db = getDB();
print_r($db->query("DESCRIBE ordenes_compra")->fetchAll(PDO::FETCH_ASSOC));
