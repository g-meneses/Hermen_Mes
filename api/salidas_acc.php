<?php
/**
 * API de Salidas de Accesorios de Confección (ACC)
 * Sistema MES Hermen Ltda.
 * Adaptado del módulo de Material de Empaque (EMP)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); // Modo producción

ob_start();
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once '../config/database.php';

    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit();
    }

    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];
    $TIPO_INVENTARIO_ACC = 4; // ID fijo para Accesorios

    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? 'list';

            switch ($action) {
                case 'list':
                    // Listar documentos de salida
                    $desde = $_GET['desde'] ?? date('Y-m-01');
                    $hasta = $_GET['hasta'] ?? date('Y-m-d');
                    $estado = $_GET['estado'] ?? 'todos';

                    $sql = "SELECT 
                                d.id_documento,
                                d.numero_documento,
                                d.fecha_documento,
                                d.tipo_documento,
                                d.tipo_salida,
                                d.id_tipo_salida,
                                d.id_tipo_inventario,
                                d.referencia_externa,
                                d.subtotal,
                                d.total,
                                d.estado,
                                d.observaciones,
                                d.fecha_creacion
                            FROM documentos_inventario d
                            WHERE d.tipo_documento = 'SALIDA' 
                            AND d.id_tipo_inventario = ?
                            AND d.fecha_documento BETWEEN ? AND ?";
                    $params = [$TIPO_INVENTARIO_ACC, $desde, $hasta];

                    $tipoFilter = $_GET['tipo'] ?? null;
                    if ($tipoFilter && $tipoFilter !== 'SALIDA') {
                        $sql .= " AND (d.tipo_salida = ? OR d.id_tipo_salida = ?)";
                        $params[] = $tipoFilter;
                        $params[] = $tipoFilter;
                    }

                    if ($estado !== 'todos') {
                        $sql .= " AND d.estado = ?";
                        $params[] = $estado;
                    }

                    $sql .= " ORDER BY d.fecha_documento DESC, d.id_documento DESC";

                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'documentos' => $documentos,
                        'total' => count($documentos)
                    ]);
                    break;

                case 'get':
                    // Obtener un documento con su detalle
                    $id = $_GET['id'] ?? null;
                    if (!$id) {
                        echo json_encode(['success' => false, 'message' => 'ID requerido']);
                        exit();
                    }

                    // Documento principal
                    $stmt = $db->prepare("
                        SELECT d.*
                        FROM documentos_inventario d
                        WHERE d.id_documento = ? AND d.id_tipo_inventario = ?
                    ");
                    $stmt->execute([$id, $TIPO_INVENTARIO_ACC]);
                    $documento = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$documento) {
                        echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
                        exit();
                    }

                    // Detalle del documento
                    $stmtDet = $db->prepare("
                        SELECT 
                            dd.*,
                            i.codigo AS producto_codigo,
                            i.nombre AS producto_nombre,
                            um.abreviatura AS unidad
                        FROM documentos_inventario_detalle dd
                        JOIN inventarios i ON dd.id_inventario = i.id_inventario
                        LEFT JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                        WHERE dd.id_documento = ?
                    ");
                    $stmtDet->execute([$id]);
                    $detalle = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'documento' => $documento,
                        'detalle' => $detalle
                    ]);
                    break;

                case 'siguiente_numero':
                    // REDIRIGIR a API centralizada con modo preview
                    $tipo = $_GET['tipo'] ?? 'PRODUCCION';

                    // Configurar parámetros para la API centralizada
                    $_GET['tipo_inventario'] = '4'; // Accesorios
                    $_GET['operacion'] = 'SALIDA';
                    $_GET['tipo_movimiento'] = $tipo;
                    $_GET['modo'] = 'preview';

                    include 'obtener_siguiente_numero.php';
                    exit();
                    break;

                case 'ingresos_devolucion':
                    // Obtener ingresos disponibles para devolución a proveedor
                    $stmt = $db->prepare("
                        SELECT 
                            d.id_documento,
                            d.numero_documento,
                            d.fecha_documento,
                            d.id_proveedor,
                            p.razon_social AS proveedor_nombre,
                            d.referencia_externa,
                            d.total,
                            d.observaciones
                        FROM documentos_inventario d
                        LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor
                        WHERE d.tipo_documento = 'INGRESO'
                        AND d.id_tipo_inventario = ?
                        AND d.estado = 'CONFIRMADO'
                        AND d.tipo_ingreso = 'COMPRA'
                        ORDER BY d.fecha_documento DESC
                        LIMIT 50
                    ");
                    $stmt->execute([$TIPO_INVENTARIO_ACC]);
                    $ingresos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'ingresos' => $ingresos
                    ]);
                    break;

                case 'detalle_ingreso':
                    // Obtener detalle de un ingreso para devolución
                    $idIngreso = $_GET['id_ingreso'] ?? null;
                    if (!$idIngreso) {
                        echo json_encode(['success' => false, 'message' => 'ID de ingreso requerido']);
                        exit();
                    }

                    $stmt = $db->prepare("
                        SELECT 
                            dd.id_detalle,
                            dd.id_inventario,
                            i.codigo AS producto_codigo,
                            i.nombre AS producto_nombre,
                            i.stock_actual,
                            um.abreviatura AS unidad,
                            dd.cantidad AS cantidad_ingresada,
                            dd.costo_unitario,
                            dd.subtotal
                        FROM documentos_inventario_detalle dd
                        JOIN inventarios i ON dd.id_inventario = i.id_inventario
                        LEFT JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                        WHERE dd.id_documento = ?
                    ");
                    $stmt->execute([$idIngreso]);
                    $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'detalle' => $detalle
                    ]);
                    break;

                default:
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? 'crear';

            switch ($action) {
                case 'crear':
                    // Validaciones
                    $tipoSalida = $data['tipo_salida'] ?? 'PRODUCCION';
                    $idTipoSalida = $data['id_tipo_salida'] ?? null;

                    // ✅ Validación de fecha futura
                    $fecha = $data['fecha'] ?? date('Y-m-d');
                    if ($fecha > date('Y-m-d')) {
                        echo json_encode(['success' => false, 'message' => 'No se permiten fechas futuras']);
                        exit();
                    }

                    if (empty($data['lineas']) || count($data['lineas']) === 0) {
                        echo json_encode(['success' => false, 'message' => 'Agregue al menos una línea']);
                        exit();
                    }

                    $db->beginTransaction();

                    try {
                        // Generar número de documento con Prefijo Inteligente
                        $codigosTipo = [
                            'PRODUCCION' => 'P',
                            'VENTA' => 'V',
                            'MERMA' => 'M',
                            'AJUSTE_NEG' => 'A',
                            'DEVOLUCION_PROV' => 'R'
                        ];
                        $codigoTipo = $codigosTipo[$tipoSalida] ?? 'X';
                        $prefijo = "OUT-ACC-$codigoTipo";

                        $numeroDoc = generarNumeroDocumento($db, 'SALIDA', $prefijo);

                        // Calcular total desde las líneas
                        $totalDocumento = 0;
                        foreach ($data['lineas'] as $linea) {
                            $subtotalLinea = floatval($linea['subtotal'] ?? ($linea['valor_total_item'] ?? ($linea['cantidad'] * ($linea['costo_unitario'] ?? 0))));
                            $totalDocumento += $subtotalLinea;
                        }

                        // Insertar documento
                        $stmt = $db->prepare("
                            INSERT INTO documentos_inventario (
                                tipo_documento, tipo_salida,
                                numero_documento, fecha_documento,
                                id_tipo_inventario, referencia_externa,
                                moneda, subtotal, total,
                                observaciones, estado, creado_por
                            ) VALUES (
                                'SALIDA', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'CONFIRMADO', ?
                            )
                        ");

                        $stmt->execute([
                            $tipoSalida,
                            $numeroDoc,
                            $fecha,
                            $TIPO_INVENTARIO_ACC,
                            $data['referencia'] ?? null,
                            $data['moneda'] ?? 'BOB',
                            $totalDocumento,
                            $totalDocumento,
                            $data['observaciones'] ?? null,
                            $_SESSION['user_id'] ?? null
                        ]);

                        $idDocumento = $db->lastInsertId();

                        // Generar código de movimiento base
                        $codigoMovBase = generarCodigoMovimiento($db);

                        // Insertar líneas y actualizar stock
                        $stmtLinea = $db->prepare("
                            INSERT INTO documentos_inventario_detalle (
                                id_documento, id_inventario, cantidad, costo_unitario, subtotal
                            ) VALUES (?, ?, ?, ?, ?)
                        ");

                        $stmtStock = $db->prepare("
                            UPDATE inventarios 
                            SET stock_actual = ?
                            WHERE id_inventario = ?
                        ");

                        // Determinar tipo de movimiento según el tipo de salida
                        $tiposMovimiento = [
                            'PRODUCCION' => 'SALIDA_PRODUCCION',
                            'VENTA' => 'SALIDA_VENTA',
                            'MERMA' => 'SALIDA_AJUSTE',
                            'AJUSTE_NEG' => 'SALIDA_AJUSTE',
                            'DEVOLUCION_PROV' => 'SALIDA_DEVOLUCION'
                        ];
                        $tipoMovimiento = $tiposMovimiento[$tipoSalida] ?? 'SALIDA_PRODUCCION';

                        $tiposDocumento = [
                            'PRODUCCION' => 'ORDEN DE PRODUCCION',
                            'VENTA' => 'FACTURA',
                            'MERMA' => 'MERMA',
                            'AJUSTE_NEG' => 'AJUSTE NEGATIVO',
                            'DEVOLUCION_PROV' => 'DEVOLUCION A PROVEEDOR'
                        ];
                        $tipoDocumento = $tiposDocumento[$tipoSalida] ?? 'SALIDA';

                        $stmtMovimiento = $db->prepare("
                            INSERT INTO movimientos_inventario (
                                id_inventario, id_tipo_inventario, fecha_movimiento, tipo_movimiento,
                                codigo_movimiento, documento_tipo, documento_numero, documento_id,
                                cantidad, costo_unitario, costo_total,
                                stock_anterior, stock_posterior,
                                costo_promedio_anterior, costo_promedio_posterior,
                                estado, creado_por
                            ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?)
                        ");

                        $lineaNumero = 1;
                        foreach ($data['lineas'] as $linea) {
                            $cantidad = floatval($linea['cantidad']);

                            // Obtener stock y CPP anterior
                            $stmtStockAnt = $db->prepare("
                                SELECT stock_actual, costo_promedio 
                                FROM inventarios 
                                WHERE id_inventario = ?
                                FOR UPDATE
                            ");
                            $stmtStockAnt->execute([$linea['id_inventario']]);
                            $datosAnt = $stmtStockAnt->fetch(PDO::FETCH_ASSOC);
                            $stockAnterior = floatval($datosAnt['stock_actual'] ?? 0);
                            $cppAnterior = floatval($datosAnt['costo_promedio'] ?? 0);

                            // ✅ Validación de stock suficiente
                            if ($stockAnterior < $cantidad) {
                                throw new Exception("Stock insuficiente para el producto ID {$linea['id_inventario']}. Stock actual: $stockAnterior, Cantidad solicitada: $cantidad");
                            }

                            // Calcular nuevo stock (CPP NO cambia en salidas)
                            $stockNuevo = $stockAnterior - $cantidad;
                            $cppNuevo = $cppAnterior; // ✅ El CPP se mantiene

                            // Usar el CPP actual para valorizar la salida
                            $costoUnit = $cppAnterior;
                            $subtotalLinea = $cantidad * $costoUnit;

                            // Insertar línea
                            $stmtLinea->execute([
                                $idDocumento,
                                $linea['id_inventario'],
                                $cantidad,
                                $costoUnit,
                                $subtotalLinea
                            ]);

                            // Actualizar stock (NO actualizar CPP)
                            $stmtStock->execute([
                                $stockNuevo,
                                $linea['id_inventario']
                            ]);

                            // Generar código de movimiento único para cada línea
                            if ($lineaNumero == 1) {
                                $codigoMovimiento = $codigoMovBase;
                            } else {
                                $codigoMovimiento = generarCodigoMovimiento($db);
                            }

                            // Registrar en movimientos_inventario con estado ACTIVO
                            $stmtMovimiento->execute([
                                $linea['id_inventario'],
                                $TIPO_INVENTARIO_ACC,
                                $tipoMovimiento,
                                $codigoMovimiento,
                                $tipoDocumento,
                                $numeroDoc,
                                $idDocumento,
                                $cantidad,
                                $costoUnit,
                                $subtotalLinea,
                                $stockAnterior,
                                $stockNuevo,
                                $cppAnterior,
                                $cppNuevo,
                                $_SESSION['user_id'] ?? null
                            ]);

                            $lineaNumero++;
                        }

                        $db->commit();

                        ob_clean();
                        echo json_encode([
                            'success' => true,
                            'message' => "Salida $numeroDoc registrada exitosamente",
                            'id_documento' => $idDocumento,
                            'numero_documento' => $numeroDoc
                        ]);

                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;

                case 'anular':
                    $id = $data['id_documento'] ?? null;
                    $motivo = $data['motivo'] ?? 'Sin especificar';

                    if (!$id) {
                        echo json_encode(['success' => false, 'message' => 'ID requerido']);
                        exit();
                    }

                    $db->beginTransaction();

                    try {
                        // Verificar que el documento existe y está confirmado
                        $stmt = $db->prepare("SELECT * FROM documentos_inventario WHERE id_documento = ? AND estado = 'CONFIRMADO'");
                        $stmt->execute([$id]);
                        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$doc) {
                            echo json_encode(['success' => false, 'message' => 'Documento no encontrado o ya anulado']);
                            exit();
                        }

                        // Obtener detalle para revertir stock
                        $stmtDet = $db->prepare("SELECT * FROM documentos_inventario_detalle WHERE id_documento = ?");
                        $stmtDet->execute([$id]);
                        $lineas = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

                        // Revertir stock (sumar lo que se había restado)
                        $stmtRevert = $db->prepare("UPDATE inventarios SET stock_actual = stock_actual + ? WHERE id_inventario = ?");

                        foreach ($lineas as $linea) {
                            $stmtRevert->execute([$linea['cantidad'], $linea['id_inventario']]);

                            // Registrar en movimientos_inventario la reversión
                            $stmtStockAct = $db->prepare("SELECT stock_actual, costo_promedio FROM inventarios WHERE id_inventario = ?");
                            $stmtStockAct->execute([$linea['id_inventario']]);
                            $inv = $stmtStockAct->fetch(PDO::FETCH_ASSOC);
                            $stockActual = floatval($inv['stock_actual'] ?? 0);
                            $cppActual = floatval($inv['costo_promedio'] ?? 0);

                            $codigoMovAnulacion = generarCodigoMovimiento($db);

                            $stmtMovAnular = $db->prepare("
                                INSERT INTO movimientos_inventario (
                                    id_inventario, id_tipo_inventario, fecha_movimiento, tipo_movimiento,
                                    codigo_movimiento, documento_tipo, documento_numero, documento_id,
                                    cantidad, costo_unitario, costo_total,
                                    stock_anterior, stock_posterior,
                                    costo_promedio_anterior, costo_promedio_posterior,
                                    observaciones, estado, creado_por
                                ) VALUES (?, ?, NOW(), 'ENTRADA_AJUSTE', ?, 'ANULACION', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?)
                            ");
                            $stmtMovAnular->execute([
                                $linea['id_inventario'],
                                $TIPO_INVENTARIO_ACC,
                                $codigoMovAnulacion,
                                $doc['numero_documento'] . ' (ANULADO)',
                                $id,
                                $linea['cantidad'],
                                $linea['costo_unitario'],
                                $linea['subtotal'],
                                $stockActual + $linea['cantidad'],
                                $stockActual,
                                $cppActual,
                                $cppActual,
                                'Anulación: ' . $motivo,
                                $_SESSION['user_id'] ?? null
                            ]);
                        }

                        // Marcar documento como anulado
                        $stmtAnular = $db->prepare("
                            UPDATE documentos_inventario 
                            SET estado = 'ANULADO', fecha_anulacion = NOW(), motivo_anulacion = ?, actualizado_por = ?
                            WHERE id_documento = ?
                        ");
                        $stmtAnular->execute([$motivo, $_SESSION['user_id'] ?? null, $id]);

                        $db->commit();

                        ob_clean();
                        echo json_encode([
                            'success' => true,
                            'message' => 'Documento anulado exitosamente'
                        ]);

                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;

                default:
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            }
            break;

        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }

} catch (PDOException $e) {
    error_log("Error en salidas_acc.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error en salidas_acc.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

/**
 * Genera el siguiente número de documento
 */
function generarNumeroDocumento($db, $tipo, $prefijo)
{
    $anio = date('Y');
    $mes = date('m');

    // Buscar o crear secuencia
    $stmt = $db->prepare("
        SELECT ultimo_numero FROM secuencias_documento 
        WHERE tipo_documento = ? AND prefijo = ? AND anio = ? AND mes = ?
        FOR UPDATE
    ");
    $stmt->execute([$tipo, $prefijo, $anio, $mes]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $siguiente = $row['ultimo_numero'] + 1;
        $stmtUp = $db->prepare("
            UPDATE secuencias_documento SET ultimo_numero = ?
            WHERE tipo_documento = ? AND prefijo = ? AND anio = ? AND mes = ?
        ");
        $stmtUp->execute([$siguiente, $tipo, $prefijo, $anio, $mes]);
    } else {
        $siguiente = 1;
        $stmtIn = $db->prepare("
            INSERT INTO secuencias_documento (tipo_documento, prefijo, anio, mes, ultimo_numero)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmtIn->execute([$tipo, $prefijo, $anio, $mes]);
    }

    return $prefijo . '-' . $anio . $mes . '-' . str_pad($siguiente, 4, '0', STR_PAD_LEFT);
}

/**
 * Genera el siguiente código de movimiento
 */
function generarCodigoMovimiento($db)
{
    $fecha = date('Ymd');

    // Buscar o crear secuencia de movimientos
    $stmt = $db->prepare("
        SELECT ultimo_numero FROM secuencias_documento 
        WHERE tipo_documento = 'MOVIMIENTO' AND prefijo = 'MOV' AND anio = ? AND mes = ?
        FOR UPDATE
    ");
    $stmt->execute([date('Y'), date('m')]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $siguiente = $row['ultimo_numero'] + 1;
        $stmtUp = $db->prepare("
            UPDATE secuencias_documento SET ultimo_numero = ?
            WHERE tipo_documento = 'MOVIMIENTO' AND prefijo = 'MOV' AND anio = ? AND mes = ?
        ");
        $stmtUp->execute([$siguiente, date('Y'), date('m')]);
    } else {
        $siguiente = 1;
        $stmtIn = $db->prepare("
            INSERT INTO secuencias_documento (tipo_documento, prefijo, anio, mes, ultimo_numero)
            VALUES ('MOVIMIENTO', 'MOV', ?, ?, 1)
        ");
        $stmtIn->execute([date('Y'), date('m')]);
    }

    return 'MOV-' . $fecha . '-' . str_pad($siguiente, 4, '0', STR_PAD_LEFT);
}

ob_end_flush();
exit();
?>