<?php
require_once '../config/database.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'No autorizado'], 401);
}

try {
    $db = getDB();
    
    // Contar máquinas operativas
    $stmtMaquinas = $db->query("SELECT COUNT(*) as total FROM maquinas WHERE estado = 'operativa'");
    $maquinasOperativas = $stmtMaquinas->fetch()['total'];
    
    // Producción de hoy
    $stmtProduccion = $db->prepare("
        SELECT COALESCE(SUM(docenas_producidas), 0) as total 
        FROM detalle_produccion_tejeduria dpt
        JOIN produccion_tejeduria pt ON dpt.id_produccion = pt.id_produccion
        WHERE DATE(pt.fecha_produccion) = CURDATE()
    ");
    $stmtProduccion->execute();
    $produccionHoy = $stmtProduccion->fetch()['total'];
    
    // Inventario intermedio
    $stmtInventario = $db->query("
        SELECT COALESCE(SUM(stock_vaporizado_docenas), 0) as total 
        FROM inventario_intermedio
    ");
    $inventarioIntermedio = $stmtInventario->fetch()['total'];
    
    // Plan semanal actual
    $stmtPlan = $db->query("
        SELECT codigo_plan 
        FROM planes_semanales 
        WHERE CURDATE() BETWEEN fecha_inicio AND fecha_fin 
        AND estado IN ('aprobado', 'en_proceso')
        LIMIT 1
    ");
    $plan = $stmtPlan->fetch();
    $planSemanal = $plan ? $plan['codigo_plan'] : null;
    
    jsonResponse([
        'success' => true,
        'maquinas_operativas' => $maquinasOperativas,
        'produccion_hoy' => $produccionHoy,
        'inventario_intermedio' => $inventarioIntermedio,
        'plan_semanal' => $planSemanal
    ]);
    
} catch(Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Error al cargar estadísticas'], 500);
}
?>
