<?php
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';

// Simular el entorno del API
require_once 'config/database.php';

// Mock de jsonResponse para que no haga exit() y podamos ver el resultado
function jsonResponse($data, $status = 200) {
    echo json_encode($data, JSON_PRETTY_PRINT);
}

require_once 'api/wip.php';

$db = getDB();

echo "--- Testing getHistorialProduccionTejido ---\n";
$_GET['action'] = 'get_historial_produccion_tejido';
$_GET['page'] = 1;
getHistorialProduccionTejido($db);

echo "\n\n--- Testing getDetalleHistorialProduccionTejido (ID=1) ---\n";
$_GET['action'] = 'get_detalle_historial_produccion_tejido';
$_GET['id_planilla'] = 1;
getDetalleHistorialProduccionTejido($db);
