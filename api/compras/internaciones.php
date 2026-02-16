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
                SELECT id_orden_compra, numero_orden, nombre_proveedor, fecha_orden, total 
                FROM ordenes_compra 
                WHERE tipo_compra = 'IMPORTACION' AND estado != 'BORRADOR' 
                ORDER BY fecha_orden DESC
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
        }

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? 'add_gasto';

        if ($action === 'add_gasto') {
            $stmt = $db->prepare("
                INSERT INTO ordenes_compra_gastos (
                    id_orden_compra, tipo_gasto, descripcion, monto, moneda, fecha_gasto, numero_factura_gasto
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['id_orden_compra'],
                $data['tipo_gasto'],
                $data['descripcion'],
                $data['monto'],
                $data['moneda'] ?? 'BOB',
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
        }
    }

} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
