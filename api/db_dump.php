<?php
require_once '../config/database.php';
$db = getDB();
echo "== Lotes Activos ==\n";
$stmt = $db->query("SELECT COUNT(*) FROM lote_wip WHERE estado_lote != 'CERRADO' AND estado_lote != 'ANULADO'");
print_r($stmt->fetchColumn());
echo "\n== Movimientos WIP ==\n";
$stmt = $db->query('SELECT COUNT(*) FROM movimientos_wip');
print_r($stmt->fetchColumn());
