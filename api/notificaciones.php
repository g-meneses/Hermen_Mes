<?php
/**
 * API de Notificaciones del Sistema
 * Sistema ERP Hermen Ltda.
 */

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    // if (!isLoggedIn()) {
    //     throw new Exception('No autorizado', 401);
    // }

    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'listar';

    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'listar':
                    $modulo = $_GET['modulo'] ?? null;
                    $tipo = $_GET['tipo'] ?? null;
                    $estado = $_GET['estado'] ?? null;
                    $limit = min((int) ($_GET['limit'] ?? 50), 100);

                    $sql = "
                        SELECT 
                            n.*,
                            u_leida.nombre_completo AS leida_por_nombre,
                            u_atendida.nombre_completo AS atendida_por_nombre
                        FROM notificaciones_sistema n
                        LEFT JOIN usuarios u_leida ON n.leida_por = u_leida.id_usuario
                        LEFT JOIN usuarios u_atendida ON n.atendida_por = u_atendida.id_usuario
                        WHERE 1=1
                    ";
                    $params = [];

                    if ($modulo) {
                        $sql .= " AND n.modulo = ?";
                        $params[] = $modulo;
                    }

                    if ($tipo) {
                        $sql .= " AND n.tipo = ?";
                        $params[] = $tipo;
                    }

                    if ($estado) {
                        $sql .= " AND n.estado = ?";
                        $params[] = $estado;
                    } else {
                        // Por defecto, mostrar solo las no archivadas
                        $sql .= " AND n.estado != 'ARCHIVADA'";
                    }

                    $sql .= " ORDER BY 
                        CASE n.prioridad 
                            WHEN 'URGENTE' THEN 1 
                            WHEN 'ALTA' THEN 2 
                            WHEN 'MEDIA' THEN 3 
                            ELSE 4 
                        END,
                        n.fecha_creacion DESC
                        LIMIT ?";
                    $params[] = $limit;

                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Contar por estado
                    $stmtCount = $db->query("
                        SELECT estado, COUNT(*) as total 
                        FROM notificaciones_sistema 
                        GROUP BY estado
                    ");
                    $conteo = [];
                    while ($row = $stmtCount->fetch(PDO::FETCH_ASSOC)) {
                        $conteo[$row['estado']] = (int) $row['total'];
                    }

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'notificaciones' => $notificaciones,
                        'total' => count($notificaciones),
                        'conteo' => $conteo,
                        'nuevas' => $conteo['NUEVA'] ?? 0
                    ]);
                    break;

                case 'pendientes':
                    // Solo notificaciones nuevas
                    $stmt = $db->query("
                        SELECT 
                            id_notificacion, tipo, modulo, titulo, prioridad, 
                            DATE_FORMAT(fecha_creacion, '%d/%m/%Y %H:%i') as fecha
                        FROM notificaciones_sistema 
                        WHERE estado = 'NUEVA'
                        ORDER BY 
                            CASE prioridad 
                                WHEN 'URGENTE' THEN 1 
                                WHEN 'ALTA' THEN 2 
                                WHEN 'MEDIA' THEN 3 
                                ELSE 4 
                            END,
                            fecha_creacion DESC
                        LIMIT 20
                    ");
                    $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'notificaciones' => $notificaciones,
                        'total' => count($notificaciones)
                    ]);
                    break;

                case 'detalle':
                    $id = $_GET['id'] ?? null;
                    if (!$id) {
                        throw new Exception('ID requerido', 400);
                    }

                    $stmt = $db->prepare("SELECT * FROM notificaciones_sistema WHERE id_notificacion = ?");
                    $stmt->execute([$id]);
                    $notificacion = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$notificacion) {
                        throw new Exception('Notificación no encontrada', 404);
                    }

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'notificacion' => $notificacion
                    ]);
                    break;

                default:
                    throw new Exception('Acción no válida', 400);
            }
            break;

        case 'POST':
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? $_GET['action'] ?? 'marcar_leida';

            switch ($action) {
                case 'marcar_leida':
                    $id = $data['id'] ?? null;
                    if (!$id) {
                        throw new Exception('ID requerido', 400);
                    }

                    $stmt = $db->prepare("
                        UPDATE notificaciones_sistema 
                        SET estado = 'LEIDA', leida_por = ?, fecha_leida = NOW()
                        WHERE id_notificacion = ? AND estado = 'NUEVA'
                    ");
                    $stmt->execute([$_SESSION['user_id'] ?? null, $id]);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Notificación marcada como leída'
                    ]);
                    break;

                case 'marcar_atendida':
                    $id = $data['id'] ?? null;
                    $observaciones = $data['observaciones'] ?? null;
                    if (!$id) {
                        throw new Exception('ID requerido', 400);
                    }

                    $stmt = $db->prepare("
                        UPDATE notificaciones_sistema 
                        SET estado = 'ATENDIDA', 
                            atendida_por = ?, 
                            fecha_atendida = NOW(),
                            mensaje = CONCAT(mensaje, '\n\n--- Atendida ---\n', COALESCE(?, ''))
                        WHERE id_notificacion = ?
                    ");
                    $stmt->execute([$_SESSION['user_id'] ?? null, $observaciones, $id]);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Notificación marcada como atendida'
                    ]);
                    break;

                case 'archivar':
                    $id = $data['id'] ?? null;
                    if (!$id) {
                        throw new Exception('ID requerido', 400);
                    }

                    $stmt = $db->prepare("
                        UPDATE notificaciones_sistema 
                        SET estado = 'ARCHIVADA'
                        WHERE id_notificacion = ?
                    ");
                    $stmt->execute([$id]);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Notificación archivada'
                    ]);
                    break;

                default:
                    throw new Exception('Acción no válida', 400);
            }
            break;

        default:
            throw new Exception('Método no permitido', 405);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
