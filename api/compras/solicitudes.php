<?php
/**
 * API de Solicitudes de Compra
 * Sistema MES Hermen Ltda.
 * Versión: 1.0
 */

ob_start();
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once '../../config/database.php';

    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit();
    }

    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? 'list';

            switch ($action) {
                case 'list':
                    $estado = $_GET['estado'] ?? 'todos';
                    $fecha_inicio = $_GET['fecha_inicio'] ?? null;
                    $fecha_fin = $_GET['fecha_fin'] ?? null;
                    $prioridad = $_GET['prioridad'] ?? 'todas';

                    $sql = "
                        SELECT s.*, 
                               u.nombre_completo as solicitante_nombre
                        FROM solicitudes_compra s
                        LEFT JOIN usuarios u ON s.id_usuario_solicitante = u.id_usuario
                        WHERE 1=1
                    ";
                    $params = [];

                    if ($estado !== 'todos') {
                        $sql .= " AND s.estado = ?";
                        $params[] = $estado;
                    }

                    if ($prioridad !== 'todas') {
                        $sql .= " AND s.prioridad = ?";
                        $params[] = $prioridad;
                    }

                    if ($fecha_inicio && $fecha_fin) {
                        $sql .= " AND s.fecha_solicitud BETWEEN ? AND ?";
                        $params[] = $fecha_inicio . ' 00:00:00';
                        $params[] = $fecha_fin . ' 23:59:59';
                    }

                    $sql .= " ORDER BY s.fecha_solicitud DESC";

                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'solicitudes' => $solicitudes
                    ]);
                    break;

                case 'get':
                    $id = $_GET['id'] ?? null;
                    if (!$id) {
                        throw new Exception("ID Requerido");
                    }

                    // Cabecera
                    $stmt = $db->prepare("
                        SELECT s.*, u.nombre_completo as solicitante_nombre
                        FROM solicitudes_compra s
                        LEFT JOIN usuarios u ON s.id_usuario_solicitante = u.id_usuario
                        WHERE s.id_solicitud = ?
                    ");
                    $stmt->execute([$id]);
                    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$solicitud) {
                        throw new Exception("Solicitud no encontrada");
                    }

                    // Detalle
                    $stmtDet = $db->prepare("
                        SELECT d.*, 
                               p.razon_social as proveedor_sugerido_nombre
                        FROM solicitudes_compra_detalle d
                        LEFT JOIN proveedores p ON d.id_proveedor_sugerido = p.id_proveedor
                        WHERE d.id_solicitud = ?
                    ");
                    $stmtDet->execute([$id]);
                    $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

                    // Adjuntar detalles
                    $solicitud['detalles'] = $detalles;

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'solicitud' => $solicitud
                    ]);
                    break;

                case 'siguiente_numero':
                    // Generar número de solicitud automático: SOL-YYYYMM-001
                    $yearMonth = date('Ym');
                    $prefix = "SOL-$yearMonth-";

                    $stmt = $db->prepare("SELECT numero_solicitud FROM solicitudes_compra WHERE numero_solicitud LIKE ? ORDER BY id_solicitud DESC LIMIT 1");
                    $stmt->execute(["$prefix%"]);
                    $last = $stmt->fetch(PDO::FETCH_ASSOC);

                    $nextNum = 1;
                    if ($last) {
                        $parts = explode('-', $last['numero_solicitud']);
                        $nextNum = intval(end($parts)) + 1;
                    }

                    $numero = $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

                    ob_clean();
                    echo json_encode(['success' => true, 'numero' => $numero]);
                    break;

                case 'aprobadas':
                    // Retornar solicitudes aprobadas que NO han sido convertidas a OC
                    $sql = "
                        SELECT s.id_solicitud, s.numero_solicitud, s.fecha_solicitud, 
                               s.motivo, s.prioridad, s.monto_estimado,
                               u.nombre_completo as solicitante_nombre
                        FROM solicitudes_compra s
                        LEFT JOIN usuarios u ON s.id_usuario_solicitante = u.id_usuario
                        WHERE s.estado = 'APROBADA' 
                          AND (s.convertida_oc = 0 OR s.convertida_oc IS NULL)
                        ORDER BY s.fecha_solicitud DESC
                    ";
                    $stmt = $db->query($sql);
                    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Para cada solicitud, obtener sus detalles
                    foreach ($solicitudes as &$sol) {
                        $stmtDet = $db->prepare("
                            SELECT d.id_detalle, d.id_producto, d.descripcion_producto, 
                                   d.cantidad_solicitada, d.unidad_medida, d.id_tipo_inventario,
                                   d.codigo_producto, d.precio_estimado
                            FROM solicitudes_compra_detalle d
                            WHERE d.id_solicitud = ?
                        ");
                        $stmtDet->execute([$sol['id_solicitud']]);
                        $sol['detalles'] = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
                    }

                    ob_clean();
                    echo json_encode(['success' => true, 'solicitudes' => $solicitudes]);
                    break;

                default:
                    throw new Exception("Acción no válida");
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? 'create';

            switch ($action) {
                case 'create':
                    // Validaciones básicas
                    if (empty($data['id_usuario_solicitante']) || empty($data['detalles'])) {
                        throw new Exception("Datos incompletos");
                    }

                    $db->beginTransaction();

                    try {
                        // Insertar cabecera
                        $stmt = $db->prepare("
                            INSERT INTO solicitudes_compra (
                                numero_solicitud, fecha_solicitud, id_usuario_solicitante,
                                area_solicitante, centro_costo, prioridad, tipo_compra,
                                motivo, observaciones, id_tipo_inventario, id_almacen,
                                moneda, monto_estimado, estado, creado_por
                            ) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDIENTE', ?)
                        ");

                        $stmt->execute([
                            $data['numero_solicitud'],
                            $data['id_usuario_solicitante'],
                            $data['area_solicitante'] ?? null,
                            $data['centro_costo'] ?? null,
                            $data['prioridad'] ?? 'NORMAL',
                            $data['tipo_compra'] ?? 'REPOSICION',
                            $data['motivo'],
                            $data['observaciones'] ?? null,
                            $data['id_tipo_inventario'] ?? null,
                            $data['id_almacen'] ?? null,
                            $data['moneda'] ?? 'BOB',
                            $data['monto_estimado'] ?? 0,
                            session_id() // TODO: Usar ID real de sesión
                        ]);

                        $id_solicitud = $db->lastInsertId();

                        // Insertar detalles
                        $stmtDet = $db->prepare("
                            INSERT INTO solicitudes_compra_detalle (
                                id_solicitud, numero_linea, id_producto,
                                id_tipo_inventario, codigo_producto, descripcion_producto,
                                cantidad_solicitada, id_unidad_medida, unidad_medida,
                                especificaciones, precio_estimado, subtotal_estimado,
                                id_proveedor_sugerido
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");

                        foreach ($data['detalles'] as $index => $det) {
                            $stmtDet->execute([
                                $id_solicitud,
                                $index + 1,
                                $det['id_producto'] ?? null,
                                $det['id_tipo_inventario'], // Requerido
                                $det['codigo_producto'] ?? '',
                                $det['descripcion_producto'],
                                $det['cantidad_solicitada'],
                                $det['id_unidad_medida'] ?? null,
                                $det['unidad_medida'] ?? '',
                                $det['especificaciones'] ?? null,
                                $det['precio_estimado'] ?? 0,
                                $det['subtotal_estimado'] ?? 0,
                                $det['id_proveedor_sugerido'] ?? null
                            ]);
                        }

                        $db->commit();

                        ob_clean();
                        echo json_encode(['success' => true, 'message' => 'Solicitud creada con éxito', 'id' => $id_solicitud]);

                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;

                case 'update':
                    $id_solicitud = $data['id_solicitud'];
                    if (!$id_solicitud || empty($data['detalles'])) {
                        throw new Exception("Datos insuficientes para actualizar");
                    }

                    $db->beginTransaction();
                    try {
                        // Actualizar cabecera
                        $stmt = $db->prepare("
                            UPDATE solicitudes_compra SET 
                                prioridad = ?, tipo_compra = ?, motivo = ?,
                                id_tipo_inventario = ?, centro_costo = ?,
                                monto_estimado = ?
                            WHERE id_solicitud = ?
                        ");
                        $stmt->execute([
                            $data['prioridad'],
                            $data['tipo_compra'],
                            $data['motivo'],
                            $data['id_tipo_inventario'],
                            $data['centro_costo'],
                            $data['monto_estimado'] ?? 0,
                            $id_solicitud
                        ]);

                        // Actualizar detalles (Borrar y re-insertar para simplicidad)
                        $db->prepare("DELETE FROM solicitudes_compra_detalle WHERE id_solicitud = ?")->execute([$id_solicitud]);

                        $stmtDet = $db->prepare("
                            INSERT INTO solicitudes_compra_detalle (
                                id_solicitud, numero_linea, id_producto,
                                id_tipo_inventario, codigo_producto, descripcion_producto,
                                cantidad_solicitada, id_unidad_medida, unidad_medida,
                                precio_estimado, subtotal_estimado
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");

                        foreach ($data['detalles'] as $index => $det) {
                            $stmtDet->execute([
                                $id_solicitud,
                                $index + 1,
                                $det['id_producto'] ?? null,
                                $det['id_tipo_inventario'],
                                $det['codigo_producto'] ?? '',
                                $det['descripcion_producto'],
                                $det['cantidad_solicitada'],
                                $det['id_unidad_medida'] ?? null,
                                $det['unidad_medida'] ?? '',
                                $det['precio_estimado'] ?? 0,
                                $det['subtotal_estimado'] ?? 0
                            ]);
                        }

                        $db->commit();
                        ob_clean();
                        echo json_encode(['success' => true, 'message' => 'Solicitud actualizada correctamente']);
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;

                case 'update_status':
                    // Aprobar/Rechazar
                    $id = $data['id_solicitud'];
                    $nuevo_estado = $data['estado'];
                    $motivo = $data['motivo'] ?? null; // Observación de aprobación/rechazo

                    if (!in_array($nuevo_estado, ['APROBADA', 'RECHAZADA', 'OBSERVADA'])) {
                        throw new Exception("Estado no válido");
                    }

                    $db->beginTransaction();
                    try {
                        $stmt = $db->prepare("UPDATE solicitudes_compra SET estado = ?, observaciones = CONCAT(IFNULL(observaciones,''), '\n', ?) WHERE id_solicitud = ?");
                        $stmt->execute([$nuevo_estado, "Motivo $nuevo_estado: $motivo", $id]);

                        // Registrar en historial aprobaciones para que aparezca en el centro de aprobaciones
                        $stmtLog = $db->prepare("
                            INSERT INTO aprobaciones (
                                tipo_documento, id_documento, id_usuario_aprobador,
                                fecha_aprobacion, accion, comentarios
                            ) VALUES ('SOLICITUD_COMPRA', ?, ?, NOW(), ?, ?)
                        ");
                        $stmtLog->execute([
                            $id,
                            $_SESSION['user_id'] ?? 1,
                            $nuevo_estado === 'APROBADA' ? 'APROBADO' : $nuevo_estado, // Match ENUM
                            $motivo ?? 'Actualización de estado desde módulo Solicitudes'
                        ]);

                        $db->commit();
                        ob_clean();
                        echo json_encode(['success' => true, 'message' => 'Estado actualizado y registrado en historial']);
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;

                default:
                    throw new Exception("Acción POST no válida");
            }
            break;

        default:
            throw new Exception("Método no permitido");
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error en compras/solicitudes.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
