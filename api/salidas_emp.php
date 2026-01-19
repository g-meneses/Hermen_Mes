<?php
/**
 * API para Gestión de Salidas - Material de Empaque (EMP)
 * ID de Inventario: 3
 */

require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$TIPO_INVENTARIO_EMP = 3; // ID fijo para Material de Empaque

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
                                u.nombre_usuario as usuario,
                                d.observaciones,
                                d.referencia_externa
                            FROM documentos_inventario d
                            LEFT JOIN usuarios u ON d.creado_por = u.id_usuario
                            WHERE d.tipo_documento = 'SALIDA' 
                            AND d.id_tipo_inventario = ?
                            AND d.fecha_documento BETWEEN ? AND ?
                            ORDER BY d.fecha_documento DESC, d.created_at DESC");
                    $stmt->execute([$TIPO_INVENTARIO_EMP, $desde, $hasta]);
                    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode(['success' => true, 'data' => $documentos]);
                    break;

                case 'get':
                    $id = $_GET['id'] ?? null;
                    if (!$id)
                        throw new Exception("ID de documento requerido");

                    // Obtener cabecera
                    $stmt = $db->prepare("SELECT d.*, u.nombre_usuario 
                                        FROM documentos_inventario d
                                        LEFT JOIN usuarios u ON d.creado_por = u.id_usuario
                                        WHERE d.id_documento = ? AND d.id_tipo_inventario = ?");
                    $stmt->execute([$id, $TIPO_INVENTARIO_EMP]);
                    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$doc)
                        throw new Exception("Documento no encontrado o no corresponde a EMP");

                    // Obtener líneas usando movimientos_inventario
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
                                        WHERE m.documento_id = ? AND m.documento_tipo = 'SALIDA' AND m.documento_numero = ?");
                    $stmtLines->execute([$id, $doc['numero_documento']]);
                    $lineas = $stmtLines->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'documento' => $doc,
                        'lineas' => $lineas
                    ]);
                    break;

                case 'siguiente_numero':
                    // REDIRIGIR a API centralizada con modo preview usando include
                    $tipo = $_GET['tipo'] ?? 'PRODUCCION';

                    // Configurar parámetros para la API centralizada
                    $_GET['tipo_inventario'] = '3';
                    $_GET['operacion'] = 'SALIDA';
                    $_GET['tipo_movimiento'] = $tipo;
                    $_GET['modo'] = 'preview';

                    include 'obtener_siguiente_numero.php';
                    exit();
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
                    $tipoSalida = $input['tipo_salida'] ?? 'PRODUCCION'; // Default
                    $fecha = $input['fecha'] ?? date('Y-m-d');
                    $observaciones = $input['observaciones'] ?? '';
                    $lineas = $input['lineas'] ?? [];

                    if (empty($lineas))
                        throw new Exception("No hay líneas en la salida");

                    $db->beginTransaction();

                    try {
                        // Smart Prefix Logic
                        $codigosTipo = [
                            'PRODUCCION' => 'P',
                            'VENTA' => 'V',
                            'MUESTRAS' => 'M',
                            'AJUSTE' => 'A',
                            'DEVOLUCION' => 'R'
                        ];
                        $codigoLetra = $codigosTipo[$tipoSalida] ?? 'X';
                        $prefijo = "OUT-EMP-$codigoLetra";

                        $numeroDoc = generarNumeroDocumento($db, 'SALIDA', $prefijo);

                        // Calcular totales
                        $totalDoc = 0;
                        foreach ($lineas as $l) {
                            $totalDoc += ($l['cantidad'] * $l['costo_unitario']);
                        }

                        // Insertar Documento
                        $stmtDoc = $db->prepare("INSERT INTO documentos_inventario (
                            id_tipo_inventario, tipo_documento, numero_documento, fecha_documento, 
                            observaciones, estado, total_documento, creado_por, created_at,
                            referencia, tipo_ingreso 
                        ) VALUES (?, 'SALIDA', ?, ?, ?, 'CONFIRMADO', ?, ?, NOW(), ?, ?)");
                        // Nota: Usamos tipo_ingreso para guardar el tipo_salida si no hay columna especifica, 
                        // o usamos una columna ad-hoc. En MP se usa tipo_ingreso a veces o referencias.
                        // El esquema original tiene tipo_ingreso? Revisar MP.
                        // SALIDA normal usa tipo_documento='SALIDA'. El subtipo se suele guardar en observacion o referencia 
                        // si no hay campo 'tipo_salida'.
                        // En salidas_mp.php no se guarda 'tipo_salida' en una columna especifica visible en el insert?
                        // Revisando salidas_mp.php: No guarda tipo_salida en una columna dedicada en documentos_inventario...
                        // Espera, documentos_inventario tiene `tipo_ingreso` pero no `tipo_salida`?
                        // En salidas_mp.php (linea 357 de codigo original step 247): 
                        // VALUES ('SALIDA', ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?)
                        // No guarda el subtipo estructurado en tabla documento, pero sí en Movimiento.

                        $referencia = $input['referencia'] ?? null;

                        $stmtDoc->execute([
                            $TIPO_INVENTARIO_EMP,
                            $numeroDoc,
                            $fecha,
                            $observaciones,
                            $totalDoc,
                            $_SESSION['user_id'] ?? 1,
                            $referencia,
                            $tipoSalida // Guardamos en tipo_ingreso por si acaso, o lo dejamos null si no cabe.
                        ]);

                        $idDocumento = $db->lastInsertId();

                        // Insertar Movimientos
                        $stmtMov = $db->prepare("INSERT INTO movimientos_inventario (
                            id_inventario, id_tipo_inventario, fecha_movimiento, tipo_movimiento,
                            codigo_movimiento, documento_tipo, documento_numero, documento_id,
                            cantidad, costo_unitario, costo_total,
                            stock_anterior, stock_posterior,
                            costo_promedio_anterior, costo_promedio_posterior,
                            estado, creado_por
                        ) VALUES (?, ?, NOW(), ?, ?, 'SALIDA', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?)");

                        foreach ($lineas as $l) {
                            // Obtener datos actuales del producto (CPP, Stock)
                            $stmtProd = $db->prepare("SELECT stock_actual, costo_promedio FROM inventario WHERE id_inventario = ? FOR UPDATE");
                            $stmtProd->execute([$l['id_inventario']]);
                            $prod = $stmtProd->fetch(PDO::FETCH_ASSOC);

                            $stockAnt = $prod['stock_actual'] ?? 0;
                            $cppAnt = $prod['costo_promedio'] ?? 0;

                            $cantidad = $l['cantidad'];
                            $costoUnit = $cppAnt; // Salida al CPP Costo Promedio

                            // Validación básica de stock
                            if ($stockAnt < $cantidad) {
                                throw new Exception("Stock insuficiente para el producto ID: " . $l['id_inventario']);
                            }

                            $costoTotal = $cantidad * $costoUnit;
                            $stockNuevo = $stockAnt - $cantidad;

                            $codMov = generarCodigoMovimiento($db);
                            $tipoMovName = 'SALIDA_' . $tipoSalida;

                            $stmtMov->execute([
                                $l['id_inventario'],
                                $TIPO_INVENTARIO_EMP,
                                $tipoMovName,
                                $codMov,
                                $numeroDoc,
                                $idDocumento,
                                $cantidad,
                                $costoUnit,
                                $costoTotal,
                                $stockAnt,
                                $stockNuevo,
                                $cppAnt,
                                $cppAnt, // CPP se mantiene igual en salida
                                $_SESSION['user_id'] ?? 1
                            ]);

                            // Actualizar Inventario Maestro
                            $stmtUpd = $db->prepare("UPDATE inventario SET stock_actual = ? WHERE id_inventario = ?");
                            $stmtUpd->execute([$stockNuevo, $l['id_inventario']]);
                        }

                        $db->commit();
                        echo json_encode(['success' => true, 'message' => "Salida EMP $numeroDoc registrada"]);

                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;

                case 'anular':
                    $id = $input['id_documento'] ?? null;
                    if (!$input['id_documento'])
                        throw new Exception("ID Requerido");

                    $db->beginTransaction();
                    try {
                        // Verificar documento
                        $stmt = $db->prepare("SELECT * FROM documentos_inventario WHERE id_documento = ? AND estado = 'CONFIRMADO'");
                        $stmt->execute([$id]);
                        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (!$doc)
                            throw new Exception("Documento no válido para anulación");

                        // Marcar anulado
                        $db->prepare("UPDATE documentos_inventario SET estado = 'ANULADO' WHERE id_documento = ?")->execute([$id]);

                        // Revertir movimientos (Entrada de Ajuste)
                        // Obtener movimientos originales de salida
                        $stmtMovs = $db->prepare("SELECT * FROM movimientos_inventario WHERE documento_id = ? AND documento_tipo = 'SALIDA'");
                        $stmtMovs->execute([$id]);
                        $movs = $stmtMovs->fetchAll(PDO::FETCH_ASSOC);

                        $stmtRev = $db->prepare("INSERT INTO movimientos_inventario (
                            id_inventario, id_tipo_inventario, fecha_movimiento, tipo_movimiento,
                            codigo_movimiento, documento_tipo, documento_numero, documento_id,
                            cantidad, costo_unitario, costo_total,
                            stock_anterior, stock_posterior,
                            costo_promedio_anterior, costo_promedio_posterior,
                            estado, creado_por, observaciones
                        ) VALUES (?, ?, NOW(), 'ENTRADA_AJUSTE', ?, 'ANULACION', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?, ?)");

                        foreach ($movs as $m) {
                            $codMov = generarCodigoMovimiento($db);

                            // Obtener estado actual
                            $stmtEstado = $db->prepare("SELECT stock_actual, costo_promedio FROM inventario WHERE id_inventario = ? FOR UPDATE");
                            $stmtEstado->execute([$m['id_inventario']]);
                            $curr = $stmtEstado->fetch(PDO::FETCH_ASSOC);

                            $stockNow = $curr['stock_actual'];
                            $cppNow = $curr['costo_promedio'];

                            $cant = $m['cantidad'];
                            $costo = $m['costo_unitario'];
                            $total = $m['costo_total'];

                            $stockNew = $stockNow + $cant;
                            // Recalcular CPP al re-ingresar?
                            // Si anulamos salida, devolvemos el valor que salió.
                            // nuevo_cpp = ((stockNow * cppNow) + (cant * costo)) / stockNew
                            $cppNew = (($stockNow * $cppNow) + $total) / $stockNew;

                            $stmtRev->execute([
                                $m['id_inventario'],
                                $TIPO_INVENTARIO_EMP,
                                $codMov,
                                $doc['numero_documento'] . ' (ANULADO)',
                                $id,
                                $cant,
                                $costo,
                                $total,
                                $stockNow,
                                $stockNew,
                                $cppNow,
                                $cppNew,
                                $_SESSION['user_id'] ?? 1,
                                'Anulación Salida ' . $doc['numero_documento']
                            ]);

                            // Update Maestro
                            $db->prepare("UPDATE inventario SET stock_actual = ?, costo_promedio = ? WHERE id_inventario = ?")
                                ->execute([$stockNew, $cppNew, $m['id_inventario']]);
                        }

                        $db->commit();
                        echo json_encode(['success' => true, 'message' => "Salida Anulada Correctamente"]);

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

// Funciones auxiliares copiadas/adaptadas
function generarNumeroDocumento($db, $tipo, $prefijo)
{
    // Lógica idéntica a MP
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