<?php
/**
 * API para Gestión de Ingresos - Repuestos (REP)
 * ID de Inventario: 7
 */

require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$TIPO_INVENTARIO_REP = 7;

try {
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? 'list';
            switch ($action) {
                case 'list':
                    $desde = $_GET['desde'] ?? date('Y-m-01');
                    $hasta = $_GET['hasta'] ?? date('Y-m-t');

                    $stmt = $db->prepare("SELECT 
                                d.id_documento,
                                d.fecha_documento,
                                d.numero_documento,
                                d.tipo_documento,
                                d.estado,
                                d.total_documento,
                                d.creado_por,
                                u.nombre_usuario as usuario
                            FROM documentos_inventario d
                            LEFT JOIN usuarios u ON d.creado_por = u.id_usuario
                            WHERE d.tipo_documento = 'INGRESO' 
                            AND d.id_tipo_inventario = ?
                            AND d.fecha_documento BETWEEN ? AND ?
                            ORDER BY d.fecha_documento DESC, d.created_at DESC");
                    $stmt->execute([$TIPO_INVENTARIO_REP, $desde, $hasta]);
                    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode(['success' => true, 'data' => $documentos]);
                    break;

                case 'siguiente_numero':
                    // REDIRIGIR a API centralizada con modo preview
                    $tipo = $_GET['tipo'] ?? 'COMPRA';
                    $url = "obtener_siguiente_numero.php?tipo_inventario=7&operacion=INGRESO&tipo_movimiento={$tipo}&modo=preview";
                    $response = file_get_contents($url, false, stream_context_create(['http' => ['method' => 'GET']]));
                    echo $response;
                    break;

                case 'get':
                    $id = $_GET['id'] ?? null;
                    if (!$id)
                        throw new Exception("ID Requerido");

                    $stmt = $db->prepare("SELECT d.*, u.nombre_usuario 
                                        FROM documentos_inventario d
                                        LEFT JOIN usuarios u ON d.creado_por = u.id_usuario
                                        WHERE d.id_documento = ? AND d.id_tipo_inventario = ?");
                    $stmt->execute([$id, $TIPO_INVENTARIO_REP]);
                    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$doc)
                        throw new Exception("Documento no encontrado");

                    $stmtLines = $db->prepare("SELECT 
                                            m.id_movimiento,
                                            m.id_inventario,
                                            m.cantidad,
                                            m.costo_unitario,
                                            m.costo_total,
                                            i.codigo,
                                            i.nombre,
                                            u.abreviatura as unidad
                                        FROM movimientos_inventario m
                                        JOIN inventario i ON m.id_inventario = i.id_inventario
                                        LEFT JOIN unidades_medida u ON i.id_unidad_medida = u.id_unidad_medida
                                        WHERE m.documento_id = ? AND m.documento_tipo = 'INGRESO'");
                    $stmtLines->execute([$id]);
                    $lineas = $stmtLines->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode(['success' => true, 'documento' => $doc, 'lineas' => $lineas]);
                    break;
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? 'crear';

            switch ($action) {
                case 'crear':
                    $tipoInput = $input['id_tipo_ingreso'] ?? 'COMPRA';
                    $lineas = $input['lineas'] ?? [];

                    if (empty($lineas))
                        throw new Exception("No hay líneas");

                    $db->beginTransaction();

                    try {
                        $codigos = ['COMPRA' => 'C', 'INICIAL' => 'I', 'AJUSTE_POS' => 'A'];
                        $letra = $codigos[$tipoInput] ?? 'C';
                        $prefijo = "IN-REP-$letra";
                        $numeroDoc = generarNumeroDocumento($db, 'INGRESO', $prefijo);

                        $total = 0;
                        foreach ($lineas as $l)
                            $total += ($l['cantidad'] * $l['costo_unitario']);

                        $stmtDoc = $db->prepare("INSERT INTO documentos_inventario (
                            id_tipo_inventario, tipo_documento, numero_documento, fecha_documento, observaciones, estado, total_documento, creado_por, created_at
                        ) VALUES (?, 'INGRESO', ?, ?, ?, 'CONFIRMADO', ?, ?, NOW())");
                        $stmtDoc->execute([$TIPO_INVENTARIO_REP, $numeroDoc, $input['fecha'] ?? date('Y-m-d'), $input['observaciones'] ?? '', $total, $_SESSION['user_id'] ?? 1]);
                        $idDoc = $db->lastInsertId();

                        $stmtMov = $db->prepare("INSERT INTO movimientos_inventario (
                            id_inventario, id_tipo_inventario, fecha_movimiento, tipo_movimiento, codigo_movimiento,
                            documento_tipo, documento_numero, documento_id, cantidad, costo_unitario, costo_total,
                            stock_anterior, stock_posterior, costo_promedio_anterior, costo_promedio_posterior,
                            estado, creado_por
                        ) VALUES (?, ?, NOW(), 'ENTRADA_COMPRA', ?, 'INGRESO', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?)");

                        foreach ($lineas as $l) {
                            $prod = $db->prepare("SELECT stock_actual, costo_promedio FROM inventario WHERE id_inventario = ?");
                            $prod->execute([$l['id_inventario']]);
                            $curr = $prod->fetch(PDO::FETCH_ASSOC);

                            $stockAnt = $curr['stock_actual'] ?? 0;
                            $cppAnt = $curr['costo_promedio'] ?? 0;
                            $cant = $l['cantidad'];
                            $unit = $l['costo_unitario'];
                            $newStock = $stockAnt + $cant;
                            $newCpp = (($stockAnt * $cppAnt) + ($cant * $unit)) / $newStock;

                            $codMov = generarCodigoMovimiento($db);
                            $stmtMov->execute([
                                $l['id_inventario'],
                                $TIPO_INVENTARIO_REP,
                                $codMov,
                                $numeroDoc,
                                $idDoc,
                                $cant,
                                $unit,
                                $cant * $unit,
                                $stockAnt,
                                $newStock,
                                $cppAnt,
                                $newCpp,
                                $_SESSION['user_id'] ?? 1
                            ]);

                            $db->prepare("UPDATE inventario SET stock_actual = ?, costo_promedio = ? WHERE id_inventario = ?")->execute([$newStock, $newCpp, $l['id_inventario']]);
                        }

                        $db->commit();
                        echo json_encode(['success' => true, 'message' => "Ingreso REP $numeroDoc registrado"]);

                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;
            }
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function generarNumeroDocumento($db, $t, $p)
{
    $y = date('Y');
    $m = date('m');
    $stm = $db->prepare("SELECT ultimo_numero FROM secuencias_documento WHERE tipo_documento = ? AND prefijo = ? AND anio = ? AND mes = ? FOR UPDATE");
    $stm->execute([$t, $p, $y, $m]);
    $row = $stm->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $sig = $row['ultimo_numero'] + 1;
        $db->prepare("UPDATE secuencias_documento SET ultimo_numero = ? WHERE tipo_documento = ? AND prefijo = ? AND anio = ? AND mes = ?")->execute([$sig, $t, $p, $y, $m]);
    } else {
        $sig = 1;
        $db->prepare("INSERT INTO secuencias_documento (tipo_documento, prefijo, anio, mes, ultimo_numero) VALUES (?, ?, ?, ?, 1)")->execute([$t, $p, $y, $m]);
    }
    return $p . '-' . $y . $m . '-' . str_pad($sig, 4, '0', STR_PAD_LEFT);
}

function generarCodigoMovimiento($db)
{
    $fecha = date('Ymd');
    $stmt = $db->prepare("SELECT ultimo_numero FROM secuencias_documento WHERE tipo_documento = 'MOVIMIENTO' AND prefijo = 'MOV' AND anio = ? AND mes = ? FOR UPDATE");
    $stmt->execute([date('Y'), date('m')]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $sig = $row['ultimo_numero'] + 1;
        $db->prepare("UPDATE secuencias_documento SET ultimo_numero = ? WHERE tipo_documento = ? AND prefijo = ? AND anio = ? AND mes = ?")->execute([$sig, 'MOV', date('Y'), date('m')]);
    } else {
        $sig = 1;
        $db->prepare("INSERT INTO secuencias_documento (tipo_documento, prefijo, anio, mes, ultimo_numero) VALUES ('MOVIMIENTO', 'MOV', ?, ?, 1)")->execute([date('Y'), date('m')]);
    }
    return 'MOV-' . $fecha . '-' . str_pad($sig, 4, '0', STR_PAD_LEFT);
}
?>