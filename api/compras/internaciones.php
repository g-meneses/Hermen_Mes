<?php
/**
 * API de Internaciones y Liquidación de Gastos
 * Sistema MES Hermen Ltda.
 */

ob_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../../config/database.php';
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list_pending';

        if ($action === 'list_pending') {
            // Listar órdenes de importación que no han sido liquidadas
            // Nota: Se corrigió la consulta para usar campos directos de la tabla
            $stmt = $db->query("
                SELECT oc.id_orden_compra, oc.numero_orden, oc.nombre_proveedor, oc.fecha_orden, oc.total,
                (SELECT COUNT(*) FROM ordenes_compra_detalle d WHERE d.id_orden_compra = oc.id_orden_compra AND d.precio_unitario_internacion IS NOT NULL) as items_liquidados,
                (SELECT COUNT(*) FROM ordenes_compra_detalle d2 WHERE d2.id_orden_compra = oc.id_orden_compra) as total_items
                FROM ordenes_compra oc
                WHERE oc.tipo_compra = 'IMPORTACION' AND oc.estado != 'BORRADOR' 
                ORDER BY oc.fecha_orden DESC
            ");
            $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'ordenes' => $ordenes]);

        } elseif ($action === 'get_details') {
            $id = $_GET['id_orden_compra'];

            // 1. Datos de la Orden (Aseguramos obtener el nombre del proveedor guardado)
            $stmt = $db->prepare("SELECT * FROM ordenes_compra WHERE id_orden_compra = ?");
            $stmt->execute([$id]);
            $orden = $stmt->fetch(PDO::FETCH_ASSOC);

            // 2. Items de la Orden (Aseguramos id_tipo_inventario)
            $stmt = $db->prepare("SELECT * FROM ordenes_compra_detalle WHERE id_orden_compra = ?");
            $stmt->execute([$id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Gastos Registrados
            $stmt = $db->prepare("SELECT * FROM ordenes_compra_gastos WHERE id_orden_compra = ?");
            $stmt->execute([$id]);
            $gastos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'orden' => $orden,
                'items' => $items,
                'gastos' => $gastos
            ]);
        } elseif ($action === 'list_history') {
            // Listar órdenes de importación que YA han sido liquidadas
            $stmt = $db->query("
                SELECT oc.id_orden_compra, oc.numero_orden, oc.nombre_proveedor, oc.fecha_orden, oc.estado,
                       (SELECT SUM(d.cantidad_ordenada * d.precio_unitario) FROM ordenes_compra_detalle d WHERE d.id_orden_compra = oc.id_orden_compra) as fob_total_bob,
                       (SELECT COALESCE(SUM(g.monto_bob), 0) FROM ordenes_compra_gastos g WHERE g.id_orden_compra = oc.id_orden_compra) as gastos_totales,
                       (SELECT SUM(d.cantidad_ordenada * d.precio_unitario_internacion) FROM ordenes_compra_detalle d WHERE d.id_orden_compra = oc.id_orden_compra) as total_internado,
                       (SELECT MAX(fecha_gasto) FROM ordenes_compra_gastos g WHERE g.id_orden_compra = oc.id_orden_compra) as fecha_ultima_liq
                FROM ordenes_compra oc
                WHERE oc.tipo_compra = 'IMPORTACION' AND oc.estado != 'BORRADOR'
                HAVING total_internado IS NOT NULL AND total_internado > 0
                ORDER BY fecha_ultima_liq DESC, oc.fecha_orden DESC
            ");
            $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'historial' => $historial]);
        }

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? 'add_gasto';

        if ($action === 'add_gasto') {
            $stmt = $db->prepare("
                INSERT INTO ordenes_compra_gastos (
                    id_orden_compra, tipo_gasto, descripcion, monto, moneda, tipo_cambio, monto_bob, fecha_gasto, numero_factura_gasto
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['id_orden_compra'],
                $data['tipo_gasto'],
                $data['descripcion'],
                $data['monto'],
                $data['moneda'] ?? 'BOB',
                $data['tipo_cambio'] ?? 1.0000,
                $data['monto_bob'] ?? $data['monto'],
                $data['fecha'] ?? date('Y-m-d'),
                $data['factura'] ?? null
            ]);
            echo json_encode(['success' => true, 'message' => 'Gasto registrado']);

        } elseif ($action === 'remove_gasto') {
            $stmt = $db->prepare("DELETE FROM ordenes_compra_gastos WHERE id_gasto = ?");
            $stmt->execute([$data['id_gasto']]);
            echo json_encode(['success' => true, 'message' => 'Gasto eliminado']);

        } elseif ($action === 'finalizar_liquidacion') {
            // Este proceso guarda el costo final en el detalle de la OC
            $idOC = $data['id_orden_compra'];
            $itemsNuevos = $data['items']; // Array con [id_detalle_oc, costo_internacion]

            $db->beginTransaction();
            try {
                $stmt = $db->prepare("UPDATE ordenes_compra_detalle SET precio_unitario_internacion = ? WHERE id_detalle_oc = ?");
                foreach ($itemsNuevos as $item) {
                    $stmt->execute([$item['costo_internacion'], $item['id_detalle_oc']]);
                }

                // Actualizar estado de la OC (podemos crear uno nuevo si gustas, ej: 'LIQUIDADA')
                // Por ahora solo marcamos que tiene liquidación.

                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Liquidación finalizada correctamente']);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } elseif ($action === 'guardar_packing') {
            $itemsPaquete = $data['items']; // Array con [id_detalle_oc, cantidad_embarcada]

            $db->beginTransaction();
            try {
                $stmt = $db->prepare("UPDATE ordenes_compra_detalle SET cantidad_embarcada = ? WHERE id_detalle_oc = ?");
                foreach ($itemsPaquete as $item) {
                    $stmt->execute([$item['cantidad_embarcada'], $item['id_detalle_oc']]);
                }

                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Packing List guardado exitosamente']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error guardando Packing List: ' . $e->getMessage()]);
            }
        }
    }

} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
