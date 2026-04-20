<?php
/**
 * API WIP Operativo
 * Sistema ERP Hermen Ltda.
 * Versión: 1.0
 */

error_reporting(0);
ini_set('display_errors', 0);

if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

try {
    require_once '../config/database.php';

    if (!isLoggedIn()) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        ob_end_flush();
        exit();
    }

    session_write_close();
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'resumen';

        switch ($action) {
            case 'resumen':
                $stmt = $db->query("
                    SELECT 
                        COUNT(id_lote_wip) AS activos,
                        COALESCE(SUM(costo_mp_acumulado), 0) AS valor,
                        COALESCE(SUM(cantidad_docenas), 0) AS docenas,
                        COALESCE(SUM(cantidad_unidades), 0) AS unidades,
                        SUM(CASE 
                            WHEN estado_lote = 'PAUSADO' THEN 1
                            WHEN estado_revision IN ('OBSERVADO', 'PARCIAL') THEN 1
                            WHEN DATEDIFF(CURRENT_DATE, COALESCE(fecha_actualizacion, fecha_inicio)) >= 7 THEN 1
                            ELSE 0 
                        END) AS alertas,
                        MAX(DATEDIFF(CURRENT_DATE, fecha_inicio)) AS mas_antiguo
                    FROM lote_wip 
                    WHERE estado_lote NOT IN ('CERRADO', 'ANULADO')
                ");
                $totales = $stmt->fetch(PDO::FETCH_ASSOC);

                ob_clean();
                echo json_encode([
                    'success' => true,
                    'totales' => [
                        'activos' => (int)$totales['activos'],
                        'valor' => (float)$totales['valor'],
                        'docenas' => (int)$totales['docenas'],
                        'unidades' => (int)$totales['unidades'],
                        'alertas' => (int)$totales['alertas'],
                        'mas_antiguo' => (int)$totales['mas_antiguo']
                    ]
                ]);
                break;

            case 'areas':
                $sql = "
                    SELECT 
                        a.id_area,
                        a.nombre,
                        COUNT(l.id_lote_wip) AS lotes,
                        COALESCE(SUM(l.cantidad_docenas), 0) AS docenas,
                        COALESCE(SUM(l.cantidad_unidades), 0) AS unidades,
                        COALESCE(SUM(l.costo_mp_acumulado), 0) AS valor,
                        SUM(CASE 
                            WHEN l.estado_lote = 'PAUSADO' THEN 1
                            WHEN l.estado_revision IN ('OBSERVADO', 'PARCIAL') THEN 1
                            WHEN DATEDIFF(CURRENT_DATE, COALESCE(l.fecha_actualizacion, l.fecha_inicio)) >= 7 THEN 1
                            ELSE 0 
                        END) AS alertas
                    FROM areas_produccion a
                    JOIN lote_wip l ON a.id_area = l.id_area_actual 
                        AND l.estado_lote NOT IN ('CERRADO', 'ANULADO')
                    GROUP BY a.id_area, a.nombre
                    ORDER BY a.id_area ASC
                ";
                $stmt = $db->query($sql);
                $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach($areas as &$a) {
                    $a['total_unidades'] = ((int)$a['docenas'] * 12) + (int)$a['unidades'];
                }

                ob_clean();
                echo json_encode([
                    'success' => true,
                    'areas' => $areas
                ]);
                break;

            case 'lotes':
                $sql = "
                    SELECT 
                        l.id_lote_wip,
                        l.codigo_lote,
                        i.nombre AS producto,
                        a.nombre AS area,
                        l.cantidad_docenas AS docenas,
                        l.cantidad_unidades AS unidades,
                        (l.cantidad_docenas * 12) + l.cantidad_unidades AS total_unidades,
                        l.fecha_inicio,
                        COALESCE(l.fecha_actualizacion, l.fecha_inicio) AS ultimo_movimiento,
                        DATEDIFF(CURRENT_DATE, l.fecha_inicio) AS dias_proceso,
                        l.costo_mp_acumulado AS costo,
                        l.estado_lote,
                        l.estado_revision,
                        CASE 
                            WHEN l.estado_lote = 'PAUSADO' THEN 'PAUSADO'
                            WHEN l.estado_revision IN ('OBSERVADO', 'PARCIAL') THEN l.estado_revision
                            WHEN DATEDIFF(CURRENT_DATE, COALESCE(l.fecha_actualizacion, l.fecha_inicio)) >= 7 THEN 'INACTIVO'
                            ELSE 'ACTIVO'
                        END AS estado_alerta
                    FROM lote_wip l
                    LEFT JOIN inventarios i ON l.id_producto = i.id_inventario
                    LEFT JOIN areas_produccion a ON l.id_area_actual = a.id_area
                    WHERE l.estado_lote NOT IN ('CERRADO', 'ANULADO')
                    ORDER BY l.fecha_inicio DESC
                ";
                $stmt = $db->query($sql);
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'lotes' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                ]);
                break;

            case 'movimientos':
                $sql = "
                    SELECT 
                        m.id_movimiento,
                        m.tipo_movimiento,
                        m.cantidad_docenas,
                        m.cantidad_unidades,
                        (m.cantidad_docenas * 12) + m.cantidad_unidades AS total_unidades,
                        m.fecha,
                        m.observaciones,
                        l.codigo_lote,
                        ao.nombre AS area_origen,
                        ad.nombre AS area_destino,
                        u.nombre_completo AS usuario
                    FROM movimientos_wip m
                    JOIN lote_wip l ON m.id_lote_wip = l.id_lote_wip
                    LEFT JOIN areas_produccion ao ON m.id_area_origen = ao.id_area
                    LEFT JOIN areas_produccion ad ON m.id_area_destino = ad.id_area
                    LEFT JOIN usuarios u ON m.usuario = u.id_usuario
                    ORDER BY m.fecha DESC
                    LIMIT 20
                ";
                $stmt = $db->query($sql);

                ob_clean();
                echo json_encode([
                    'success' => true,
                    'movimientos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                ]);
                break;

            default:
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
                break;
        }
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Método no válido']);
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false, 
        'message' => 'Error de servidor: ' . $e->getMessage()
    ]);
}
ob_end_flush();
