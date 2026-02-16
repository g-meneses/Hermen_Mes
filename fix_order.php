<?php
require_once 'config/database.php';
$db = getDB();
$db->prepare("UPDATE ordenes_compra SET estado = 'ENVIADA' WHERE numero_orden = 'OC-202602-001'")->execute();
echo "Update complete";
