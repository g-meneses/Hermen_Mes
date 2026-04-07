<?php
require_once 'C:/xampp/htdocs/mes_hermen/config/database.php';
$db = getDB();
$q = $db->query("DESCRIBE movimientos_wip");
while($r = $q->fetch()) echo $r['Field'].PHP_EOL;
echo "---".PHP_EOL;
$q = $db->query("DESCRIBE lote_wip");
while($r = $q->fetch()) echo $r['Field'].PHP_EOL;
?>
