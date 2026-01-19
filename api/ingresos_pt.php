<?php
/**
 * API para Gestión de Ingresos - Productos Terminados (PT)
 * ID de Inventario: 6
 */

require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$TIPO_INVENTARIO_PT = 6; // ID fijo para Productos Terminados

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
                    $stmt->execute([$TIPO_INVENTARIO_PT, $desde, $hasta]);
                    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode(['success' => true, 'data' => $documentos]);
                    break;

                case 'siguiente_numero':
                    // REDIRIGIR a API centralizada con modo preview
                    $tipo = $_GET['tipo'] ?? 'PRODUCCION';
                    $url = "obtener_siguiente_numero.php?tipo_inventario=6&operacion=INGRESO&tipo_movimiento={$tipo}&modo=preview";
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
                    $stmt->execute([$id, $TIPO_INVENTARIO_PT]);
                    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$doc)
                        throw new Exception("Documento no encontrado o no corresponde a PT");

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
                                        WHERE m.documento_id = ? AND m.documento_tipo = 'INGRESO' AND m.documento_numero = ?");
                    $stmtLines->execute([$id, $doc['numero_documento']]);
                    $lineas = $stmtLines->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'documento' => $doc,
                        'lineas' => $lineas
                    ]);
                    break;

                default:
                    throw new Exception("Acción no válida");
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? 'crear';

            switch ($action) {
                case 'crear':
                    $inputTipo = $input['id_tipo_ingreso'] ?? null;
                    $tipoCodigoString = is_numeric($inputTipo) ? 'PRODUCCION' : ($inputTipo ?? 'PRODUCCION');
                    // PT suele ser PRODUCCION, no COMPRA

                    $fecha = $input['fecha'] ?? date('Y-m-d');
                    $observaciones = $input['observaciones'] ?? '';
                    $lineas = $input['lineas'] ?? [];

                    if (empty($lineas))
                        throw new Exception("No hay líneas");

                    $db->beginTransaction();

                    try {
                        // Prefijos para PT
                        $codigosTipo = [
                            'PRODUCCION' => 'P', // Producción (Entrada principal d PT)
                            'INICIAL' => 'I',
                            'DEVOLUCION_VENTA' => 'D', // Devolución de cliente
                            'AJUSTE_POS' => 'A'
                        ];
                        // Mapeo si viene 'COMPRA' por error o default:
                        if ($tipoCodigoString == 'COMPRA')
                            $tipoCodigoString = 'PRODUCCION';

                        $codigoLetra = $codigosTipo[$tipoCodigoString] ?? 'P';
                        $prefijo = "IN-PT-$codigoLetra";

                        $numeroDoc = generarNumeroDocumento($db, 'INGRESO', $prefijo);

                        $totalDoc = 0;
                        foreach ($lineas as $l) {
                            $totalDoc += ($l['cantidad'] * $l['costo_unitario']);
                        }

                        $stmtDoc = $db->prepare("INSERT INTO documentos_inventario (
                            id_tipo_inventario, tipo_documento, numero_documento, fecha_documento, 
                            observaciones, estado, total_documento, creado_por, created_at
                        ) VALUES (?, 'INGRESO', ?, ?, ?, 'CONFIRMADO', ?, ?, NOW())");

                        $stmtDoc->execute([
                            $TIPO_INVENTARIO_PT,
                            $numeroDoc,
                            $fecha,
                            $observaciones,
                            $totalDoc,
                            $_SESSION['user_id'] ?? 1
                        ]);

                        $idDocumento = $db->lastInsertId();

                        // Tipo mov
                        $tipoMovDB = ($tipoCodigoString == 'INICIAL') ? 'ENTRADA_AJUSTE' : 'ENTRADA_PRODUCCION';

                        $stmtMov = $db->prepare("INSERT INTO movimientos_inventario (
                            id_inventario, id_tipo_inventario, fecha_movimiento, tipo_movimiento,
                            codigo_movimiento, documento_tipo, documento_numero, documento_id,
                            cantidad, costo_unitario, costo_total,
                            stock_anterior, stock_posterior,
                            costo_promedio_anterior, costo_promedio_posterior,
                            estado, creado_por
                        ) VALUES (?, ?, NOW(), ?, ?, 'INGRESO', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?)");

                        foreach ($lineas as $l) {
                            $stmtProd = $db->prepare("SELECT stock_actual, costo_promedio FROM inventario WHERE id_inventario = ?");
                            $stmtProd->execute([$l['id_inventario']]);
                            $prod = $stmtProd->fetch(PDO::FETCH_ASSOC);

                            $stockAnt = $prod['stock_actual'] ?? 0;
                            $cppAnt = $prod['costo_promedio'] ?? 0;

                            $cantidad = $l['cantidad'];
                            $costoUnit = $l['costo_unitario'];
                            $costoTotal = $cantidad * $costoUnit;

                            $stockNuevo = $stockAnt + $cantidad;
                            $nuevoCpp = (($stockAnt * $cppAnt) + $costoTotal) / $stockNuevo;

                            $codMov = generarCodigoMovimiento($db);

                            $stmtMov->execute([
                                $l['id_inventario'],
                                $TIPO_INVENTARIO_PT,
                                $tipoMovDB,
                                $codMov,
                                $numeroDoc,
                                $idDocumento,
                                $cantidad,
                                $costoUnit,
                                $costoTotal,
                                $stockAnt,
                                $stockNuevo,
                                $cppAnt,
                                $nuevoCpp,
                                $_SESSION['user_id'] ?? 1
                            ]);

                            $stmtUpd = $db->prepare("UPDATE inventario SET stock_actual = ?, costo_promedio = ? WHERE id_inventario = ?");
                            $stmtUpd->execute([$stockNuevo, $nuevoCpp, $l['id_inventario']]);
                        }

                        $db->commit();
                        echo json_encode(['success' => true, 'message' => "Ingreso PT $numeroDoc registrado"]);

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

function generarNumeroDocumento($db, $tipo, $prefijo)
{
    // Reuse
    $anio = date('Y');
    $mes = date('m');
    $stmt = $db->prepare("SELECT ultimo_numero FROM secuencias_documento WHERE tipo_documento = ? AND prefijo = ? AND anio = ? AND mes = ? FOR UPDATE");
    $stmt->execute([$tipo, $prefijo, $anio, $mes]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $sig = $row['ultimo_numero'] + 1;
        $db->prepare("UPDATE secuencias_documento SET ultimo_numero = ? WHERE tipo_documento = ? AND prefijo = ? AND anio = ? AND mes = ?")->execute([$sig, $tipo, $prefijo, $anio, $mes]);
    } else {
        $sig = 1;
        $db->prepare("INSERT INTO secuencias_documento (tipo_documento, prefijo, anio, mes, ultimo_numero) VALUES (?, ?, ?, ?, 1)")->execute([$tipo, $prefijo, $anio, $mes]);
    }
    return $prefijo . '-' . $anio . $mes . '-' . str_pad($sig, 4, '0', STR_PAD_LEFT);
}

function generarCodigoMovimiento($db)
{
    $fecha = date('Ymd');
    $stmt = $db->prepare("SELECT ultimo_numero FROM secuencias_documento WHERE tipo_documento = 'MOVIMIENTO' AND prefijo = 'MOV' AND anio = ? AND mes = ? FOR UPDATE");
    $stmt->execute([date('Y'), date('m')]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $sig = $row['ultimo_numero'] + 1;
        $db->prepare("UPDATE secuencias_documento SET ultimo_numero = ? WHERE tipo_documento = 'MOVIMIENTO' AND prefijo = 'MOV' AND anio = ? AND mes = ?")->execute([$sig, 'MOV', date('Y'), date('m')]);
    } else {
        $sig = 1;
        $db->prepare("INSERT INTO secuencias_documento (tipo_documento, prefijo, anio, mes, ultimo_numero) VALUES ('MOVIMIENTO', 'MOV', ?, ?, 1)")->execute([date('Y'), date('m')]);
    }
    return 'MOV-' . $fecha . '-' . str_pad($sig, 4, '0', STR_PAD_LEFT);
}
?>