<?php
// Bypass auth for technical evidence generation
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    $fechaHoy = date('Y-m-d');
    
    // Exact SQL/Logic for KPIs
    // ---------------------------------------------------------
    // 1. mp_transferred_today
    $sqlMP = "SELECT COALESCE(SUM(dd.cantidad), 0) 
              FROM documentos_inventario d
              JOIN documentos_inventario_detalle dd ON d.id_documento = dd.id_documento
              WHERE d.tipo_documento = 'SALIDA' 
                AND d.tipo_consumo = 'TEJIDO'
                AND d.estado = 'CONFIRMADO'
                AND d.fecha_documento = ?";
    $stmtMP = $db->prepare($sqlMP);
    $stmtMP->execute([$fechaHoy]);
    $mpTransferred = (float)$stmtMP->fetchColumn();

    // 2. production_today
    $sqlProd = "SELECT SUM(cantidad_docenas) as doc, SUM(cantidad_unidades) as und
                FROM lote_wip
                WHERE DATE(fecha_inicio) = ?";
    $stmtProd = $db->prepare($sqlProd);
    $stmtProd->execute([$fechaHoy]);
    $prod = $stmtProd->fetch();
    $totalProdBase = ($prod['doc'] ?? 0) * 12 + ($prod['und'] ?? 0);

    // 3. wip_balance_current
    $sqlWip = "SELECT SUM(cantidad_docenas) as doc, SUM(cantidad_unidades) as und
               FROM lote_wip
               WHERE id_area_actual = (SELECT id_area FROM areas_produccion WHERE codigo = 'TEJ' LIMIT 1)
                 AND estado_lote NOT IN ('CERRADO', 'ANULADO')";
    $stmtWip = $db->query($sqlWip);
    $wip = $stmtWip->fetch();
    $totalWipBase = ($wip['doc'] ?? 0) * 12 + ($wip['und'] ?? 0);

    // Alert Detection Examples
    // 1. Producción sin SAL-TEJ
    $sqlAlert1 = "SELECT COUNT(*) FROM lote_wip WHERE id_documento_salida IS NULL AND DATE(fecha_inicio) = ?";
    $stmtA1 = $db->prepare($sqlAlert1);
    $stmtA1->execute([$fechaHoy]);
    $alert1Count = $stmtA1->fetchColumn();

    // Response Structure Construction
    $response = [
        'success' => true,
        'data' => [
            'kpis' => [
                'mp_transferred_today' => $mpTransferred,
                'production_today_fmt' => floor($totalProdBase/12) . "|" . ($totalProdBase%12),
                'wip_balance_current_fmt' => floor($totalWipBase/12) . "|" . ($totalWipBase%12),
                'active_machines' => 0,
                'active_lots' => 0
            ],
            'alerts' => [
                ['type' => 'danger', 'msg' => "$alert1Count lotes sin SAL-TEJ.", 'code' => 'PROD_NO_SAL']
            ],
            'debug_queries' => [
                'mp_sql' => $sqlMP,
                'prod_sql' => $sqlProd,
                'wip_sql' => $sqlWip
            ]
        ]
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
