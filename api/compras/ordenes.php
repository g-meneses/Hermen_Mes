<?php
/**
 * API de Órdenes de Compra
 * Sistema MES Hermen Ltda.
 * Versión: 1.0
 */

ob_start();
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST');

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

    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';

        if ($action === 'list') {
            $estado = $_GET['estado'] ?? 'todos';
            $proveedor = $_GET['id_proveedor'] ?? null;
            $fecha_inicio = $_GET['fecha_inicio'] ?? null;
            $fecha_fin = $_GET['fecha_fin'] ?? null;

            $sql = "
                SELECT oc.*, p.razon_social as proveedor_nombre, u.nombre_completo as comprador_nombre
                FROM ordenes_compra oc
                JOIN proveedores p ON oc.id_proveedor = p.id_proveedor
                JOIN usuarios u ON oc.id_comprador = u.id_usuario
                WHERE 1=1
            ";
            $params = [];

            if ($estado !== 'todos') {
                $sql .= " AND oc.estado = ?";
                $params[] = $estado;
            }
            if ($proveedor) {
                $sql .= " AND oc.id_proveedor = ?";
                $params[] = $proveedor;
            }
            if ($fecha_inicio && $fecha_fin) {
                $sql .= " AND oc.fecha_orden BETWEEN ? AND ?";
                $params[] = $fecha_inicio . ' 00:00:00';
                $params[] = $fecha_fin . ' 23:59:59';
            }

            $sql .= " ORDER BY oc.fecha_orden DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            echo json_encode(['success' => true, 'ordenes' => $ordenes]);

        } elseif ($action === 'get') {
            $id = $_GET['id'] ?? null;
            if (!$id)
                throw new Exception("ID requerido");

            $stmt = $db->prepare("
                SELECT oc.*, p.razon_social as proveedor_nombre, 
                       p.nit as proveedor_nit, p.direccion as proveedor_direccion,
                       u.nombre_completo as comprador_nombre
                FROM ordenes_compra oc
                JOIN proveedores p ON oc.id_proveedor = p.id_proveedor
                JOIN usuarios u ON oc.id_comprador = u.id_usuario
                WHERE oc.id_orden_compra = ?
            ");
            $stmt->execute([$id]);
            $orden = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$orden)
                throw new Exception("Orden no encontrada");

            // Detalles
            $stmtDet = $db->prepare("SELECT * FROM ordenes_compra_detalle WHERE id_orden_compra = ?");
            $stmtDet->execute([$id]);
            $orden['detalles'] = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            echo json_encode(['success' => true, 'orden' => $orden]);

        } elseif ($action === 'siguiente_numero') {
            // Generar OC-YYYYMM-001
            $yearMonth = date('Ym');
            $prefix = "OC-$yearMonth-";

            $stmt = $db->prepare("SELECT numero_orden FROM ordenes_compra WHERE numero_orden LIKE ? ORDER BY id_orden_compra DESC LIMIT 1");
            $stmt->execute(["$prefix%"]);
            $last = $stmt->fetch(PDO::FETCH_ASSOC);

            $nextNum = 1;
            if ($last) {
                $parts = explode('-', $last['numero_orden']);
                $nextNum = intval(end($parts)) + 1;
            }

            $numero = $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
            ob_clean();
            echo json_encode(['success' => true, 'numero' => $numero]);
        }

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? 'create';

        $db->beginTransaction();
        try {
            if ($action === 'create' || $action === 'create_from_request') {
                // Obtener datos proveedor
                $stmtProv = $db->prepare("SELECT razon_social, nit FROM proveedores WHERE id_proveedor = ?");
                $stmtProv->execute([$data['id_proveedor']]);
                $prov = $stmtProv->fetch(PDO::FETCH_ASSOC);

                $nombreProveedor = $data['nombre_proveedor'] ?? $prov['razon_social'] ?? '';
                $nitProveedor = $data['nit_proveedor'] ?? $prov['nit'] ?? '';

                // Header - usando solo columnas que existen en la tabla
                $stmt = $db->prepare("
                    INSERT INTO ordenes_compra (
                        numero_orden, tipo_compra, fecha_orden, id_proveedor, nombre_proveedor, nit_proveedor,
                        id_solicitud, numero_solicitud, id_comprador, moneda, tipo_cambio,
                        condicion_pago, dias_credito, fecha_entrega_estimada, lugar_entrega,
                        subtotal, descuento_general, total, observaciones,
                        estado, creado_por
                    ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'BORRADOR', ?)
                ");

                $stmt->execute([
                    $data['numero_orden'],
                    $data['tipo_compra'] ?? 'LOCAL',
                    $data['id_proveedor'],
                    $nombreProveedor,
                    $nitProveedor,
                    $data['id_solicitud'] ?? null,
                    $data['numero_solicitud'] ?? null,
                    $_SESSION['user_id'] ?? 1,
                    $data['moneda'] ?? 'BOB',
                    $data['tipo_cambio'] ?? 1,
                    $data['condicion_pago'] ?? 'CONTADO',
                    $data['dias_credito'] ?? 0,
                    !empty($data['fecha_entrega_estimada']) ? $data['fecha_entrega_estimada'] : null,
                    $data['lugar_entrega'] ?? null,
                    $data['total'] ?? 0,
                    $data['descuento_general'] ?? 0,
                    $data['total'] ?? 0,
                    $data['observaciones'] ?? null,
                    $_SESSION['user_id'] ?? 1
                ]);

                $id_orden = $db->lastInsertId();

                // Detalles
                $stmtDet = $db->prepare("
                    INSERT INTO ordenes_compra_detalle (
                        id_orden_compra, numero_linea, id_producto, id_tipo_inventario,
                        codigo_producto, descripcion_producto, cantidad_ordenada,
                        unidad_medida, precio_unitario, subtotal_linea, total_linea,
                        especificaciones, id_detalle_solicitud
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($data['detalles'] as $idx => $det) {
                    $stmtDet->execute([
                        $id_orden,
                        $idx + 1,
                        $det['id_producto'] ?? null,
                        $det['id_tipo_inventario'],
                        $det['codigo_producto'] ?? '',
                        $det['descripcion_producto'],
                        $det['cantidad'], // Frontend should send 'cantidad'
                        $det['unidad_medida'],
                        $det['precio_unitario'],
                        $det['subtotal'] ?? 0,
                        $det['total'] ?? 0,
                        $det['especificaciones'] ?? null,
                        $det['id_detalle_solicitud'] ?? null // Link to request detail
                    ]);
                }

                // Si viene de solicitud, marcar solicitud
                if (!empty($data['id_solicitud'])) {
                    $db->prepare("UPDATE solicitudes_compra SET estado = 'APROBADA', convertida_oc = 1 WHERE id_solicitud = ?")
                        ->execute([$data['id_solicitud']]);
                }

                $db->commit();
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Orden creada', 'id' => $id_orden]);

            } elseif ($action === 'update') {
                // Actualizar orden existente (solo si está en BORRADOR)
                $id_orden = $data['id_orden_compra'];

                // Verificar que la orden existe y está en BORRADOR
                $stmtCheck = $db->prepare("SELECT estado FROM ordenes_compra WHERE id_orden_compra = ?");
                $stmtCheck->execute([$id_orden]);
                $ordenActual = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if (!$ordenActual) {
                    throw new Exception("Orden no encontrada");
                }
                if ($ordenActual['estado'] !== 'BORRADOR') {
                    throw new Exception("Solo se pueden editar órdenes en estado BORRADOR");
                }

                // Actualizar header
                $stmt = $db->prepare("
                    UPDATE ordenes_compra SET
                        tipo_compra = ?,
                        id_proveedor = ?,
                        fecha_entrega_estimada = ?,
                        lugar_entrega = ?,
                        condicion_pago = ?,
                        observaciones = ?,
                        total = ?,
                        subtotal = ?
                    WHERE id_orden_compra = ?
                ");
                $stmt->execute([
                    $data['tipo_compra'] ?? 'LOCAL',
                    $data['id_proveedor'],
                    !empty($data['fecha_entrega_estimada']) ? $data['fecha_entrega_estimada'] : null,
                    $data['lugar_entrega'] ?? null,
                    $data['condicion_pago'] ?? 'CONTADO',
                    $data['observaciones'] ?? null,
                    $data['total'] ?? 0,
                    $data['total'] ?? 0,
                    $id_orden
                ]);

                // Eliminar detalles existentes y reinsertar
                $db->prepare("DELETE FROM ordenes_compra_detalle WHERE id_orden_compra = ?")->execute([$id_orden]);

                $stmtDet = $db->prepare("
                    INSERT INTO ordenes_compra_detalle (
                        id_orden_compra, numero_linea, id_producto, id_tipo_inventario,
                        codigo_producto, descripcion_producto, cantidad_ordenada,
                        unidad_medida, precio_unitario, subtotal_linea, total_linea, especificaciones
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($data['detalles'] as $idx => $det) {
                    $subtotal = ($det['cantidad'] ?? 0) * ($det['precio_unitario'] ?? 0);
                    $stmtDet->execute([
                        $id_orden,
                        $idx + 1,
                        $det['id_producto'] ?? null,
                        $det['id_tipo_inventario'] ?? 1,
                        $det['codigo_producto'] ?? '',
                        $det['descripcion_producto'],
                        $det['cantidad'],
                        $det['unidad_medida'],
                        $det['precio_unitario'] ?? 0,
                        $subtotal,
                        $subtotal,
                        $det['especificaciones'] ?? null
                    ]);
                }

                $db->commit();
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Orden actualizada']);

            } elseif ($action === 'change_status') {
                $id = $data['id_orden_compra'];
                $estado = $data['estado'];
                // Validar transición...

                $stmt = $db->prepare("UPDATE ordenes_compra SET estado = ? WHERE id_orden_compra = ?");
                $stmt->execute([$estado, $id]);

                $db->commit();
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Estado actualizado']);
            }

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    } // Check transaction state safely
    error_log("Error ordenes.php: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
