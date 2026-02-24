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

            // Detalles con contexto de la OC (Precios y Cantidades Acumuladas)
            $stmtDet = $db->prepare("
                SELECT rd.*, od.precio_unitario as precio_oc, od.cantidad_recibida as cant_acumulada_oc,
                       od.unidad_medida as unidad_oc
                FROM recepciones_compra_detalle rd
                LEFT JOIN ordenes_compra_detalle od ON rd.id_detalle_oc = od.id_detalle_oc
                WHERE rd.id_recepcion = ?
            ");
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
                    ) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDIENTE', ?)
                ");

                // Generar número de recepción si no viene
                $numRecepcion = $data['numero_recepcion'] ?? 'REC-' . date('Ymd-His');

                // Determinar si es TOTAL o PARCIAL (esto se guarda como referencia inicial)
                $totalOrdenadoOC = 0;
                $totalRecibidoAhora = 0;
                foreach ($data['detalles'] as $det) {
                    $totalOrdenadoOC += (float) $det['cantidad_ordenada'];
                    $totalRecibidoAhora += (float) $det['cantidad_recibida'];
                }
                $tipoRecepcionAuto = ($totalRecibidoAhora >= $totalOrdenadoOC) ? 'TOTAL' : 'PARCIAL';

                $stmt->execute([
                    $numRecepcion,
                    $data['id_orden_compra'],
                    $data['numero_orden'] ?? '',
                    $data['id_proveedor'],
                    $data['nombre_proveedor'],
                    $data['numero_guia_remision'] ?? '',
                    $data['numero_factura'] ?? '',
                    $data['fecha_factura'] ?? null,
                    $data['id_almacen'] ?? 1,
                    $_SESSION['user_id'] ?? 1,
                    $tipoRecepcionAuto,
                    $data['observaciones'] ?? '',
                    $_SESSION['user_id'] ?? 1
                ]);

                $id_recepcion = $db->lastInsertId();

                // Preparar sentencias para detalles
                $stmtDet = $db->prepare("
                    INSERT INTO recepciones_compra_detalle (
                        id_recepcion, id_detalle_oc, numero_linea, id_producto,
                        id_tipo_inventario, codigo_producto, descripcion_producto,
                        cantidad_ordenada, cantidad_recibida, cantidad_aceptada,
                        cantidad_rechazada, numero_lote, fecha_vencimiento,
                        estado_calidad
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($data['detalles'] as $idx => $det) {
                    $cantRecibida = (float) $det['cantidad_recibida'];
                    $cantAceptada = (float) ($det['cantidad_aceptada'] ?? $cantRecibida);

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
                }

                $db->commit();
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Recepción registrada (Pendiente de Validación)', 'id' => $id_recepcion]);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }

        } elseif ($action === 'procesar') {
            $id_recepcion = $data['id_recepcion'];
            if (!$id_recepcion)
                throw new Exception("ID de recepción requerido");

            $db->beginTransaction();
            try {
                // 1. Obtener la recepción y sus detalles
                $stmt = $db->prepare("SELECT * FROM recepciones_compra WHERE id_recepcion = ? AND estado = 'PENDIENTE'");
                $stmt->execute([$id_recepcion]);
                $recepcion = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$recepcion)
                    throw new Exception("Recepción no encontrada o ya procesada");

                $stmtDet = $db->prepare("SELECT * FROM recepciones_compra_detalle WHERE id_recepcion = ?");
                $stmtDet->execute([$id_recepcion]);
                $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

                // Sentencias preparadas para actualizaciones
                $stmtUpdateOCDet = $db->prepare("
                    UPDATE ordenes_compra_detalle 
                    SET cantidad_recibida = cantidad_recibida + ?,
                        estado_recepcion = CASE 
                            WHEN (cantidad_recibida + ?) >= cantidad_ordenada THEN 'COMPLETA' 
                            ELSE 'PARCIAL' 
                        END
                    WHERE id_detalle_oc = ?
                ");

                // Función auxiliar para generar número de movimiento
                if (!function_exists('generarCodMov')) {
                    function generarCodMov($db)
                    {
                        $fecha = date('Ymd');
                        $stmt = $db->prepare("SELECT ultimo_numero FROM secuencias_documento WHERE tipo_documento = 'MOVIMIENTO' AND prefijo = 'MOV' AND anio = ? AND mes = ? FOR UPDATE");
                        $stmt->execute([date('Y'), date('m')]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        $siguiente = $row ? $row['ultimo_numero'] + 1 : 1;
                        if ($row) {
                            $db->prepare("UPDATE secuencias_documento SET ultimo_numero = ? WHERE tipo_documento = 'MOVIMIENTO' AND prefijo = 'MOV' AND anio = ? AND mes = ?")->execute([$siguiente, date('Y'), date('m')]);
                        } else {
                            $db->prepare("INSERT INTO secuencias_documento (tipo_documento, prefijo, anio, mes, ultimo_numero) VALUES ('MOVIMIENTO', 'MOV', ?, ?, 1)")->execute([date('Y'), date('m')]);
                        }
                        return 'MOV-' . $fecha . '-' . str_pad($siguiente, 4, '0', STR_PAD_LEFT);
                    }
                }

                foreach ($detalles as $det) {
                    $cantRecibida = (float) $det['cantidad_recibida'];

                    // Actualizar detalle OC (siempre que exista el vínculo)
                    if (!empty($det['id_detalle_oc'])) {
                        $stmtUpdateOCDet->execute([$cantRecibida, $cantRecibida, $det['id_detalle_oc']]);
                    }

                    // --- INTEGRACIÓN CON INVENTARIO ---

                    // VALIDACIÓN CRÍTICA: Si no hay id_producto, no podemos actualizar stock ni kardex
                    if (empty($det['id_producto'])) {
                        error_log("Aviso: Saltando actualización de inventario para ítem " . $det['codigo_producto'] . " por falta de id_producto.");
                        continue;
                    }

                    // 1. Obtener precio unitario (priorizando costo internado si existe)
                    $stmtOCP = $db->prepare("
                        SELECT COALESCE(precio_unitario_internacion, precio_unitario) as costo_real 
                        FROM ordenes_compra_detalle WHERE id_detalle_oc = ?
                    ");
                    $stmtOCP->execute([$det['id_detalle_oc']]);
                    $precioUnitOC = $stmtOCP->fetchColumn();

                    // 2. Obtener datos actuales de inventario
                    $stmtInv = $db->prepare("SELECT stock_actual, costo_promedio FROM inventarios WHERE id_inventario = ? FOR UPDATE");
                    $stmtInv->execute([$det['id_producto']]);
                    $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);

                    $stockAnterior = (float) ($inv['stock_actual'] ?? 0);
                    $cppAnterior = (float) ($inv['costo_promedio'] ?? 0);
                    $stockNuevo = $stockAnterior + $cantRecibida;

                    // Cálculo de nuevo costo promedio (CPP)
                    $cppNuevo = ($stockNuevo > 0)
                        ? (($stockAnterior * $cppAnterior) + ($cantRecibida * $precioUnitOC)) / $stockNuevo
                        : $precioUnitOC;

                    // 3. Actualizar Maestro de Inventario
                    $db->prepare("UPDATE inventarios SET stock_actual = ?, costo_promedio = ?, costo_unitario = ? WHERE id_inventario = ?")
                        ->execute([$stockNuevo, $cppNuevo, $precioUnitOC, $det['id_producto']]);

                    // 4. Registrar Movimiento (Kardex)
                    $codMov = generarCodMov($db);
                    $stmtMov = $db->prepare("
                        INSERT INTO movimientos_inventario (
                            id_inventario, id_tipo_inventario, fecha_movimiento, tipo_movimiento, 
                            codigo_movimiento, documento_tipo, documento_numero, documento_id,
                            cantidad, costo_unitario, costo_total,
                            stock_anterior, stock_posterior,
                            costo_promedio_anterior, costo_promedio_posterior,
                            estado, creado_por
                        ) VALUES (?, ?, NOW(), 'ENTRADA_COMPRA', ?, 'RECEPCION_OC', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?)
                    ");
                    $stmtMov->execute([
                        $det['id_producto'],
                        $det['id_tipo_inventario'],
                        $codMov,
                        $recepcion['numero_recepcion'],
                        $id_recepcion,
                        $cantRecibida,
                        $precioUnitOC,
                        ($cantRecibida * $precioUnitOC),
                        $stockAnterior,
                        $stockNuevo,
                        $cppAnterior,
                        $cppNuevo,
                        $_SESSION['user_id'] ?? 1
                    ]);
                }

                // Actualizar Estado General de OC si ya se recibió todo
                $stmtCheckOC = $db->prepare("
                    SELECT SUM(cantidad_ordenada) as tot_ord, SUM(cantidad_recibida) as tot_rec 
                    FROM ordenes_compra_detalle WHERE id_orden_compra = ?
                ");
                $stmtCheckOC->execute([$recepcion['id_orden_compra']]);
                $progreso = $stmtCheckOC->fetch(PDO::FETCH_ASSOC);

                $nuevoEstadoOC = ($progreso['tot_rec'] >= $progreso['tot_ord']) ? 'RECIBIDA' : 'RECIBIDA_PARCIAL';
                $db->prepare("UPDATE ordenes_compra SET estado = ? WHERE id_orden_compra = ?")
                    ->execute([$nuevoEstadoOC, $recepcion['id_orden_compra']]);

                // 5. Finalizar Recepción
                $db->prepare("UPDATE recepciones_compra SET 
                    estado = 'CONFIRMADA', 
                    procesado_por = ?, 
                    fecha_procesado = NOW(),
                    inventario_actualizado = 1,
                    fecha_actualizacion_inventario = NOW()
                    WHERE id_recepcion = ?")
                    ->execute([$_SESSION['user_id'] ?? 1, $id_recepcion]);

                $db->commit();
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Ingreso a inventario procesado correctamente']);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } elseif ($action === 'delete') {
            $id_recepcion = $data['id_recepcion'];
            if (!$id_recepcion)
                throw new Exception("ID de recepción requerido");

            $db->beginTransaction();
            try {
                // Verificar que esté PENDIENTE antes de borrar
                $stmt = $db->prepare("SELECT estado FROM recepciones_compra WHERE id_recepcion = ?");
                $stmt->execute([$id_recepcion]);
                $estado = $stmt->fetchColumn();

                if ($estado !== 'PENDIENTE') {
                    throw new Exception("Solo se pueden anular recepciones en estado PENDIENTE");
                }

                // 1. Borrar detalles
                $stmtDelDet = $db->prepare("DELETE FROM recepciones_compra_detalle WHERE id_recepcion = ?");
                $stmtDelDet->execute([$id_recepcion]);

                // 2. Borrar cabecera
                $stmtDelCab = $db->prepare("DELETE FROM recepciones_compra WHERE id_recepcion = ?");
                $stmtDelCab->execute([$id_recepcion]);

                $db->commit();
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Recepción anulada correctamente']);

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
