<?php
/**
 * API de Salidas Móviles
 * Sistema ERP Hermen Ltda.
 * 
 * Gestión de salidas de inventario desde app móvil
 */

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';

try {
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {

        // =====================================================
        // GET - Consultas
        // =====================================================
        case 'GET':
            $action = $_GET['action'] ?? 'historial';

            switch ($action) {

                // Historial de salidas del día
                case 'historial':
                    $usuario_id = $_GET['usuario_id'] ?? null;
                    $fecha = $_GET['fecha'] ?? null; // Si no se especifica, mostrar los últimos 30 días
                    $limit = min((int) ($_GET['limit'] ?? 50), 100);

                    $sql = "
                        SELECT
                            sm.id_salida_movil AS id,
                            sm.uuid_local,
                            sm.tipo_salida,
                            sm.fecha_hora_local,
                            sm.estado_sync,
                            sm.motivo_rechazo,
                            sm.id_documento_generado,
                            ap.nombre AS area_destino,
                            ue.nombre_completo AS usuario_entrega,
                            ur.nombre_completo AS usuario_recibe,
                            (SELECT COUNT(*) FROM salidas_moviles_detalle WHERE id_salida_movil = sm.id_salida_movil) AS total_items,
                            (SELECT ti.codigo FROM salidas_moviles_detalle smd2
                                JOIN inventarios i2 ON smd2.id_inventario = i2.id_inventario
                                JOIN tipos_inventario ti ON i2.id_tipo_inventario = ti.id_tipo_inventario
                                WHERE smd2.id_salida_movil = sm.id_salida_movil LIMIT 1) AS tipo_inventario,
                            (SELECT ti.nombre FROM salidas_moviles_detalle smd2
                                JOIN inventarios i2 ON smd2.id_inventario = i2.id_inventario
                                JOIN tipos_inventario ti ON i2.id_tipo_inventario = ti.id_tipo_inventario
                                WHERE smd2.id_salida_movil = sm.id_salida_movil LIMIT 1) AS tipo_inventario_nombre
                        FROM salidas_moviles sm
                        LEFT JOIN areas_produccion ap ON sm.id_area_destino = ap.id_area
                        LEFT JOIN usuarios ue ON sm.usuario_entrega = ue.id_usuario
                        LEFT JOIN usuarios ur ON sm.usuario_recibe = ur.id_usuario
                    ";

                    $params = [];

                    if ($fecha) {
                        $sql .= " WHERE DATE(sm.fecha_hora_local) = ?";
                        $params[] = $fecha;
                    } else {
                        // Últimos 30 días
                        $sql .= " WHERE sm.fecha_hora_local >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    }

                    if ($usuario_id) {
                        $sql .= " AND (sm.usuario_entrega = ? OR sm.usuario_recibe = ?)";
                        $params[] = $usuario_id;
                        $params[] = $usuario_id;
                    }

                    $sql .= " ORDER BY sm.fecha_hora_local DESC LIMIT ?";
                    $params[] = $limit;

                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $salidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'salidas' => $salidas,
                        'total' => count($salidas),
                        'fecha' => $fecha
                    ]);
                    break;

                // Detalle de una salida
                case 'detalle':
                    $id = $_GET['id'] ?? null;
                    $uuid = $_GET['uuid'] ?? null;

                    if (!$id && !$uuid) {
                        throw new Exception('Se requiere id o uuid', 400);
                    }

                    // Obtener encabezado
                    $sql = "
                        SELECT 
                            sm.*,
                            ap.nombre AS area_destino_nombre,
                            ue.nombre_completo AS usuario_entrega_nombre,
                            ur.nombre_completo AS usuario_recibe_nombre
                        FROM salidas_moviles sm
                        LEFT JOIN areas_produccion ap ON sm.id_area_destino = ap.id_area
                        LEFT JOIN usuarios ue ON sm.usuario_entrega = ue.id_usuario
                        LEFT JOIN usuarios ur ON sm.usuario_recibe = ur.id_usuario
                        WHERE " . ($id ? "sm.id_salida_movil = ?" : "sm.uuid_local = ?");

                    $stmt = $db->prepare($sql);
                    $stmt->execute([$id ?? $uuid]);
                    $salida = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$salida) {
                        throw new Exception('Salida no encontrada', 404);
                    }

                    // Obtener detalle
                    $stmtDet = $db->prepare("
                        SELECT 
                            smd.*,
                            i.codigo AS producto_codigo,
                            i.nombre AS producto_nombre,
                            um.abreviatura AS unidad
                        FROM salidas_moviles_detalle smd
                        LEFT JOIN inventarios i ON smd.id_inventario = i.id_inventario
                        LEFT JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                        WHERE smd.id_salida_movil = ?
                    ");
                    $stmtDet->execute([$salida['id_salida_movil']]);
                    $detalle = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'salida' => $salida,
                        'detalle' => $detalle
                    ]);
                    break;

                // Pendientes de sincronización
                case 'pendientes':
                    $stmt = $db->query("
                        SELECT 
                            sm.id_salida_movil,
                            sm.uuid_local,
                            sm.tipo_salida,
                            sm.fecha_hora_local,
                            sm.estado_sync
                        FROM salidas_moviles sm
                        WHERE sm.estado_sync IN ('PENDIENTE_SYNC', 'OBSERVADA')
                        ORDER BY sm.fecha_hora_local ASC
                    ");
                    $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'pendientes' => $pendientes,
                        'total' => count($pendientes)
                    ]);
                    break;

                default:
                    throw new Exception('Acción GET no válida', 400);
            }
            break;

        // =====================================================
        // POST - Crear salida
        // =====================================================
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? 'crear';

            switch ($action) {

                case 'crear':
                    // Validar campos requeridos
                    $required = ['uuid_local', 'tipo_salida', 'id_area_destino', 'usuario_entrega', 'usuario_recibe', 'fecha_hora_local', 'items'];
                    foreach ($required as $field) {
                        if (empty($data[$field])) {
                            throw new Exception("Campo requerido: $field", 400);
                        }
                    }

                    if (!is_array($data['items']) || count($data['items']) === 0) {
                        throw new Exception('Debe incluir al menos un producto', 400);
                    }

                    $db->beginTransaction();

                    try {
                        // Insertar encabezado
                        $stmt = $db->prepare("
                            INSERT INTO salidas_moviles (
                                uuid_local, tipo_salida, id_area_destino, observaciones,
                                usuario_entrega, usuario_recibe, fecha_hora_local,
                                estado_sync, dispositivo_info
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDIENTE_SYNC', ?)
                        ");

                        $stmt->execute([
                            $data['uuid_local'],
                            $data['tipo_salida'],
                            $data['id_area_destino'],
                            $data['observaciones'] ?? null,
                            $data['usuario_entrega'],
                            $data['usuario_recibe'],
                            $data['fecha_hora_local'],
                            $data['dispositivo_info'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null
                        ]);

                        $id_salida = $db->lastInsertId();

                        // Insertar detalle
                        $stmtDet = $db->prepare("
                            INSERT INTO salidas_moviles_detalle (
                                id_salida_movil, id_inventario, cantidad, stock_referencial, observaciones
                            ) VALUES (?, ?, ?, ?, ?)
                        ");

                        foreach ($data['items'] as $item) {
                            $stmtDet->execute([
                                $id_salida,
                                $item['id_inventario'],
                                $item['cantidad'],
                                $item['stock_referencial'] ?? null,
                                $item['observaciones'] ?? null
                            ]);
                        }

                        $db->commit();

                        // ============================================
                        // AUTO-SINCRONIZAR: Ejecutar proceso de inventario
                        // ============================================
                        require_once __DIR__ . '/sync.php';

                        $resultadoSync = procesarSincronizacion($db, $id_salida);

                        ob_clean();
                        echo json_encode([
                            'success' => true,
                            'message' => 'Salida registrada correctamente',
                            'id_salida_movil' => (int) $id_salida,
                            'uuid_local' => $data['uuid_local'],
                            'estado_sync' => $resultadoSync['estado'] ?? 'PENDIENTE_SYNC',
                            'sync_resultado' => $resultadoSync
                        ]);

                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;

                default:
                    throw new Exception('Acción POST no válida', 400);
            }
            break;

        default:
            throw new Exception('Método no permitido', 405);
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code($e->getCode() ?: 500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>