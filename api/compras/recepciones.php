<?php
/**
 * API de Recepciones de Compra
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
            $sql = "
                SELECT r.*, p.razon_social as proveedor_nombre, oc.numero_orden as orden_numero
                FROM recepciones_compra r
                JOIN proveedores p ON r.id_proveedor = p.id_proveedor
                JOIN ordenes_compra oc ON r.id_orden_compra = oc.id_orden_compra
                ORDER BY r.fecha_recepcion DESC
            ";
            $stmt = $db->query($sql);
            $recepciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            echo json_encode(['success' => true, 'recepciones' => $recepciones]);

        } elseif ($action === 'get') {
            $id = $_GET['id'];
            $stmt = $db->prepare("
                SELECT r.*, p.razon_social, oc.numero_orden
                FROM recepciones_compra r
                JOIN proveedores p ON r.id_proveedor = p.id_proveedor
                JOIN ordenes_compra oc ON r.id_orden_compra = oc.id_orden_compra
                WHERE r.id_recepcion = ?
            ");
            $stmt->execute([$id]);
            $recepcion = $stmt->fetch(PDO::FETCH_ASSOC);

            // Detalles
            $stmtDet = $db->prepare("SELECT * FROM recepciones_compra_detalle WHERE id_recepcion = ?");
            $stmtDet->execute([$id]);
            $recepcion['detalles'] = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            echo json_encode(['success' => true, 'recepcion' => $recepcion]);
        }

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? 'create';

        if ($action === 'create') {
            // Validar
            if (empty($data['id_orden_compra']))
                throw new Exception("Orden de compra requerida");

            $db->beginTransaction();
            try {
                // Insertar Cabecera
                $stmt = $db->prepare("
                    INSERT INTO recepciones_compra (
                        numero_recepcion, fecha_recepcion, id_orden_compra, numero_orden,
                        id_proveedor, nombre_proveedor, numero_guia_remision, numero_factura,
                        fecha_factura, id_almacen, id_usuario_recibe, tipo_recepcion,
                        observaciones, estado, creado_por
                    ) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'CONFIRMADA', ?)
                ");

                // Generar número de recepción si no viene
                $numRecepcion = $data['numero_recepcion'] ?? 'REC-' . date('Ymd-His');

                $stmt->execute([
                    $numRecepcion,
                    $data['id_orden_compra'],
                    $data['numero_orden'] ?? '', // Debería venir de frontend o lookup
                    $data['id_proveedor'],
                    $data['nombre_proveedor'],
                    $data['numero_guia_remision'] ?? '',
                    $data['numero_factura'] ?? '',
                    $data['fecha_factura'] ?? null,
                    $data['id_almacen'] ?? 1, // Default main warehouse
                    $_SESSION['user_id'] ?? 1,
                    $data['tipo_recepcion'] ?? 'PARCIAL',
                    $data['observaciones'] ?? '',
                    $_SESSION['user_id'] ?? 1
                ]);

                $id_recepcion = $db->lastInsertId();

                // Insertar Detalles y Actualizar OC
                $stmtDet = $db->prepare("
                    INSERT INTO recepciones_compra_detalle (
                        id_recepcion, id_detalle_oc, numero_linea, id_producto,
                        id_tipo_inventario, codigo_producto, descripcion_producto,
                        cantidad_ordenada, cantidad_recibida, cantidad_aceptada,
                        cantidad_rechazada, numero_lote, fecha_vencimiento,
                        estado_calidad
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmtUpdateOCDet = $db->prepare("
                    UPDATE ordenes_compra_detalle 
                    SET cantidad_recibida = cantidad_recibida + ?,
                        estado_recepcion = CASE 
                            WHEN cantidad_recibida >= cantidad_ordenada THEN 'COMPLETA' 
                            ELSE 'PARCIAL' 
                        END
                    WHERE id_detalle_oc = ?
                ");

                foreach ($data['detalles'] as $idx => $det) {
                    $cantRecibida = $det['cantidad_recibida'];
                    $cantAceptada = $det['cantidad_aceptada'] ?? $cantRecibida;

                    $stmtDet->execute([
                        $id_recepcion,
                        $det['id_detalle_oc'],
                        $idx + 1,
                        $det['id_producto'],
                        $det['id_tipo_inventario'],
                        $det['codigo_producto'],
                        $det['descripcion_producto'],
                        $det['cantidad_ordenada'],
                        $cantRecibida,
                        $cantAceptada,
                        $det['cantidad_rechazada'] ?? 0,
                        $det['numero_lote'] ?? null,
                        $det['fecha_vencimiento'] ?? null,
                        $det['estado_calidad'] ?? 'APROBADO'
                    ]);

                    // Actualizar detalle OC
                    $stmtUpdateOCDet->execute([$cantRecibida, $det['id_detalle_oc']]);

                    // TODO: Integración con Inventario (Movimiento de Entrada)
                    // require_once '../../models/Inventario.php';
                    // Inventario::registrarIngreso(...);
                }

                // Actualizar Estado General de OC
                // Logica simple: Si todos los detalles están COMPLETOS -> OC RECIBIDA_TOTAL
                // Esto podría ser un trigger o stored procedure, o lógica PHP aquí.

                $db->commit();
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Recepción registrada', 'id' => $id_recepcion]);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        }
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error recepciones.php: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
