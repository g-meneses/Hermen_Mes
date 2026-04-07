<?php
// Mocking session to pass auth
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';

require_once __DIR__ . '/../config/database.php';
$db = getDB();

// 1. Get real data for simulation
$mach = $db->query("SELECT id_maquina, numero_maquina FROM maquinas LIMIT 1")->fetch();
$prod = $db->query("SELECT id_producto, codigo_producto FROM productos_tejidos LIMIT 1")->fetch();
$area = $db->query("SELECT id_area FROM areas_produccion WHERE codigo = 'TEJEDURIA' LIMIT 1")->fetch();
$turno = $db->query("SELECT id_turno FROM turnos LIMIT 1")->fetch();
$salTej = $db->query("SELECT id_documento, numero_documento FROM documentos_inventario WHERE tipo_documento = 'SALIDA' AND (tipo_consumo = 'TEJIDO' OR tipo_documento = 'SALIDA') LIMIT 1")->fetch();

if (!$mach || !$prod || !$area || !$turno || !$salTej) {
    die("Error: Missing master data for validation. Mach:".($mach?1:0)." Prod:".($prod?1:0)." Area:".($area?1:0)." Turno:".($turno?1:0)." SAL-TEJ:".($salTej?1:0));
}

// 2. Create a Lote for TODAY linked to SAL-TEJ
$codigoLote = "VAL-" . date('His');
$stmt = $db->prepare("INSERT INTO lote_wip 
    (codigo_lote, id_producto, cantidad_docenas, cantidad_unidades, cantidad_base_unidades, id_area_actual, id_documento_salida, id_maquina, id_turno, fecha_inicio, estado_lote)
    VALUES (?, ?, 10, 0, 120, ?, ?, ?, ?, NOW(), 'ACTIVO')");
$stmt->execute([$codigoLote, $prod['id_producto'], $area['id_area'], $salTej['id_documento'], $mach['id_maquina'], $turno['id_turno']]);
$idLote = $db->lastInsertId();

// 3. Inject filter for today and hit API logic
$_GET['fecha_desde'] = date('Y-m-d');
$_GET['fecha_hasta'] = date('Y-m-d');

// Include WIP Dashboard API logic
require_once __DIR__ . '/../api/wip_dashboard.php';
?>
