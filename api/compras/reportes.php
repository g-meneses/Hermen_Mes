<?php
/**
 * API de Reportes y Dashboard de Compras
 * Sistema MES Hermen Ltda.
 * Versión: 1.0
 */

ob_start();
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET');

ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once '../../config/database.php';

    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit();
    }

    $db = getDB();
    $action = $_GET['action'] ?? 'dashboard';

    if ($action === 'dashboard') {
        // 1. Stats Cards
        // Pendientes de Aprobación
        $stmt = $db->query("SELECT COUNT(*) as count FROM solicitudes_compra WHERE estado = 'PENDIENTE' OR estado = 'EN_APROBACION'");
        $pendientes = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Órdenes del Mes (Monto)
        $mesActual = date('Y-m');
        $stmt = $db->prepare("SELECT SUM(total) as total FROM ordenes_compra WHERE fecha_orden LIKE ? AND estado != 'CANCELADA'");
        $stmt->execute(["$mesActual%"]);
        $comprasMes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // Recepciones Pendientes
        $stmt = $db->query("SELECT COUNT(*) as count FROM ordenes_compra WHERE estado IN ('EMITIDA', 'CONFIRMADA', 'PARCIAL')");
        $recepcionesPend = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // 2. Gráfico Compras últimos 6 meses
        $chartLabels = [];
        $chartData = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = date('Y-m', strtotime("-$i months"));
            $chartLabels[] = date('M Y', strtotime("-$i months"));

            $stmt = $db->prepare("SELECT SUM(total) as total FROM ordenes_compra WHERE fecha_orden LIKE ? AND estado != 'CANCELADA'");
            $stmt->execute(["$date%"]);
            $chartData[] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        }

        ob_clean();
        echo json_encode([
            'success' => true,
            'stats' => [
                'solicitudes_pendientes' => $pendientes,
                'compras_mes' => number_format($comprasMes, 2),
                'recepciones_pendientes' => $recepcionesPend
            ],
            'chart' => [
                'labels' => $chartLabels,
                'data' => $chartData
            ]
        ]);

    } elseif ($action === 'kpis') {
        // Implementar KPIs avanzados
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'KPIs coming soon']);
    }

} catch (Exception $e) {
    error_log("Error reportes.php: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
