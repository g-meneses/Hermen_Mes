<?php
require_once 'config/database.php';
$db = getDB();

try {
    $db->beginTransaction();

    // 1. Crear Proveedor Directo
    $codigoProv = 'PROV-DIR-' . rand(100, 999);
    $stmt = $db->prepare("INSERT INTO proveedores (codigo, razon_social, tipo, nit, regimen_tributario, activo) VALUES (?, ?, 'LOCAL', '0', 'DIRECTO_SIN_FACTURA', 1)");
    $stmt->execute([$codigoProv, 'PROVEEDOR TEST DIRECTO (WHATSAPP)']);
    $idProv = $db->lastInsertId();

    // 2. Crear Orden de Compra manual para este proveedor
    $stmtNum = $db->query("SELECT COUNT(*) as total FROM ordenes_compra");
    $total = $stmtNum->fetch()['total'] + 1;
    $numOC = 'OC-TEST-' . str_pad($total, 3, '0', STR_PAD_LEFT);

    $stmtOC = $db->prepare("INSERT INTO ordenes_compra (numero_orden, tipo_compra, fecha_orden, id_proveedor, nombre_proveedor, total, estado, creado_por) VALUES (?, 'LOCAL', NOW(), ?, 'PROVEEDOR TEST DIRECTO (WHATSAPP)', 1000.00, 'EMITIDA', 1)");
    $stmtOC->execute([$numOC, $idProv]);
    $idOC = $db->lastInsertId();

    // 3. Agregar un ítem al detalle para que sea seleccionable en la recepción
    $stmtDet = $db->prepare("INSERT INTO ordenes_compra_detalle (id_orden_compra, codigo_producto, descripcion_producto, cantidad_ordenada, precio_unitario, total_linea, id_tipo_inventario) VALUES (?, 'TEST-001', 'PRODUCTO DE PRUEBA DIRECTO', 10.00, 100.00, 1000.00, 1)");
    $stmtDet->execute([$idOC]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'id_proveedor' => $idProv,
        'id_orden_compra' => $idOC,
        'numero_orden' => $numOC,
        'nombre_proveedor' => 'PROVEEDOR TEST DIRECTO (WHATSAPP)'
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>