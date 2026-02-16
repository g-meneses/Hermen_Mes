<?php
require_once 'config/database.php';
$db = getDB();
$db->prepare("UPDATE ordenes_compra SET estado = 'ENVIADA' WHERE id_orden_compra = 2")->execute();
echo "State updated for ID 2";
