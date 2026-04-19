<?php
/**
 * API de Salidas de Materias Primas
 * Sistema ERP Hermen Ltda.
 * Versión: 1.0
 */

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
    $TIPO_INVENTARIO_MP = 1;

    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? 'list';

            switch ($action) {
                case 'list':
                    $desde = $_GET['desde'] ?? date('Y-m-01');
                    $hasta = $_GET['hasta'] ?? date('Y-m-d');
                    $tipo = $_GET['tipo'] ?? null;
                    $estado = $_GET['estado'] ?? 'todos';

                    $sql = "SELECT 
                                d.id_documento,
                                d.numero_documento,
                                d.fecha_documento,
                                d.id_doc_tipo,
                                dt.nombre AS doc_tipo_nombre,
                                d.id_doc_subtipo,
                                st.nombre AS doc_subtipo_nombre,
                                d.id_doc_estado,
                                es.nombre AS doc_estado_nombre,
                                es.color_hex AS doc_estado_color,
                                d.tipo_documento,
                                d.referencia_externa,
                                d.tipo_salida,
                                d.total,
                                d.estado,
                                d.observaciones,
                                d.fecha_creacion
                            FROM documentos_inventario d
                            LEFT JOIN inv_doc_tipos dt ON d.id_doc_tipo = dt.id_tipo
                            LEFT JOIN inv_doc_subtipos st ON d.id_doc_subtipo = st.id_subtipo
                            LEFT JOIN inv_doc_estados es ON d.id_doc_estado = es.id_estado
                            WHERE (d.tipo_documento = 'SALIDA' OR d.id_doc_tipo = 2)
                            AND d.id_tipo_inventario = ?
                            AND d.fecha_documento BETWEEN ? AND ?";
                    $params = [$TIPO_INVENTARIO_MP, $desde, $hasta];

                    if ($tipo && $tipo !== 'SALIDA') {
                        $sql .= " AND d.tipo_salida = ?";
                        $params[] = $tipo;
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

                case 'ingresos_devolucion':
                    // Listar ingresos disponibles para devolución
                    $proveedor = $_GET['proveedor'] ?? null;
                    $limit = $_GET['limit'] ?? 20;

                    $sql = "SELECT 
                                d.id_documento,
                                d.numero_documento,
                                d.fecha_documento,
                                d.id_proveedor,
                                COALESCE(p.nombre_comercial, p.razon_social) AS proveedor_nombre,
                                p.nombre_comercial AS proveedor_comercial,
                                d.con_factura,
                                d.total,
                                d.observaciones
                            FROM documentos_inventario d
                            LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor
                            WHERE (d.tipo_documento = 'INGRESO' OR d.id_doc_tipo = 1)
                            AND d.id_tipo_inventario = ?
                            AND (d.estado = 'CONFIRMADO' OR d.id_doc_estado = 2)
                            AND d.id_documento > 0";
                    $params = [$TIPO_INVENTARIO_MP];

                    if ($proveedor) {
                        $sql .= " AND d.id_proveedor = ?";
                        $params[] = $proveedor;
                    }

                    $sql .= " ORDER BY d.fecha_documento DESC, d.id_documento DESC LIMIT ?";
                    $params[] = (int) $limit;

                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $ingresos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'ingresos' => $ingresos
                    ]);
                    break;

                case 'detalle_ingreso':
                    // Obtener detalle de un ingreso para devolución
                    $id = $_GET['id'] ?? null;
                    if (!$id) {
                        echo json_encode(['success' => false, 'message' => 'ID requerido']);
                        exit();
                    }

                    // Información del documento
                    $stmt = $db->prepare("
                        SELECT 
                            d.*,
                            p.razon_social AS proveedor_nombre,
                            p.nombre_comercial AS proveedor_comercial
                        FROM documentos_inventario d
                        LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor
                        LEFT JOIN inv_doc_tipos dt ON d.id_doc_tipo = dt.id_tipo
                        LEFT JOIN inv_doc_subtipos st ON d.id_doc_subtipo = st.id_subtipo
                        LEFT JOIN inv_doc_estados es ON d.id_doc_estado = es.id_estado
                        WHERE d.id_documento = ? AND (d.tipo_documento = 'INGRESO' OR d.id_doc_tipo = 1)
                    ");
                    $stmt->execute([$id]);
                    $documento = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$documento) {
                        echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
                        exit();
                    }

                    // Detalle con información de devoluciones previas
                    $stmtDet = $db->prepare("
                        SELECT 
                            dd.id_detalle,
                            dd.id_inventario,
                            dd.cantidad AS cantidad_original,
                            dd.costo_unitario,
                            dd.costo_con_iva,
                            dd.subtotal,
                            i.codigo AS producto_codigo,
                            i.nombre AS producto_nombre,
                            um.abreviatura AS unidad,
                            COALESCE(
                                (SELECT SUM(dds.cantidad) 
                                 FROM documentos_inventario_detalle dds
                                 JOIN documentos_inventario ds ON dds.id_documento = ds.id_documento
                                 WHERE dds.id_detalle_origen = dd.id_detalle 
                                 AND (ds.tipo_documento = 'SALIDA' OR ds.id_doc_tipo = 2)
                                 AND (ds.estado = 'CONFIRMADO' OR ds.id_doc_estado = 2)
                                 AND ds.referencia_externa COLLATE utf8mb4_unicode_ci LIKE 'DEVOLUCION%'
                                ), 0
                            ) AS cantidad_devuelta
                        FROM documentos_inventario_detalle dd
                        JOIN inventarios i ON dd.id_inventario = i.id_inventario
                        LEFT JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                        WHERE dd.id_documento = ?
                    ");
                    $stmtDet->execute([$id]);
                    $detalle = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

                    // Calcular disponible para devolver
                    foreach ($detalle as &$linea) {
                        $linea['cantidad_disponible'] = $linea['cantidad_original'] - $linea['cantidad_devuelta'];
                    }

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'documento' => $documento,
                        'detalle' => $detalle
                    ]);
                    break;

                case 'get':
                    // Obtener un documento de salida con su detalle
                    $id = $_GET['id'] ?? null;
                    if (!$id) {
                        echo json_encode(['success' => false, 'message' => 'ID requerido']);
                        exit();
                    }

                    // Documento principal
                    $stmt = $db->prepare("
                        SELECT 
                            d.*,
                            dt.nombre AS doc_tipo_nombre,
                            st.nombre AS doc_subtipo_nombre,
                            es.nombre AS doc_estado_nombre
                        FROM documentos_inventario d
                        LEFT JOIN inv_doc_tipos dt ON d.id_doc_tipo = dt.id_tipo
                        LEFT JOIN inv_doc_subtipos st ON d.id_doc_subtipo = st.id_subtipo
                        LEFT JOIN inv_doc_estados es ON d.id_doc_estado = es.id_estado
                        WHERE d.id_documento = ? AND (d.tipo_documento = 'SALIDA' OR d.id_doc_tipo = 2)
                    ");
                    $stmt->execute([$id]);
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
                    if (empty($data['lineas']) || count($data['lineas']) === 0) {
                        echo json_encode(['success' => false, 'message' => 'Agregue al menos una línea']);
                        exit();
                    }

                    // Validar motivo para ajustes
                    $tipoSalida = $data['tipo_salida'] ?? 'PRODUCCION';
                    $tipoConsumo = $data['tipo_consumo'] ?? null;

                    if ($tipoSalida === 'AJUSTE' && empty(trim($data['observaciones'] ?? ''))) {
                        echo json_encode(['success' => false, 'message' => 'El motivo es obligatorio para ajustes de inventario']);
                        exit();
                    }

                    $db->beginTransaction();

                    try {
                        // 1. VERIFICAR SI REQUIERE AUTORIZACION (ES UN AJUSTE NEGATIVO)
                        if ($tipoSalida === 'AJUSTE') {
                            // -------------------------------------------------------------
                            // RUTA A: GUARDAR COMO BORRADOR DE AJUSTE (PENDIENTE DE APROBAR)
                            // -------------------------------------------------------------
                            
                            // Generar código único para el ajuste
                            $codigoAjuste = generarNumeroDocumento($db, 'SALIDA', 'AJ-NEG'); // Ajuste Negativo
                            
                            $stmtAjuste = $db->prepare("
                                INSERT INTO ajustes_inventario (
                                    codigo_ajuste, id_solicitante, tipo_ajuste, estado, motivo
                                ) VALUES (?, ?, 'SALIDA', 'PENDIENTE', ?)
                            ");
                            
                            $stmtAjuste->execute([
                                $codigoAjuste,
                                $_SESSION['user_id'] ?? null,
                                $data['observaciones'] ?? 'Ajuste negativo solicitado'
                            ]);
                            
                            $idAjuste = $db->lastInsertId();
                            
                            $stmtDetalleAjuste = $db->prepare("
                                INSERT INTO ajustes_inventario_detalle (
                                    id_ajuste, id_inventario, cantidad_solicitada, costo_unitario_guardado
                                ) VALUES (?, ?, ?, ?)
                            ");
                            
                            foreach ($data['lineas'] as $linea) {
                                $cantidad = floatval($linea['cantidad']);
                                $costoUnitario = floatval($linea['costo_unitario'] ?? 0);
                                
                                $stmtDetalleAjuste->execute([
                                    $idAjuste,
                                    $linea['id_inventario'],
                                    $cantidad,
                                    $costoUnitario
                                ]);
                            }
                            
                            $db->commit();
                            
                            ob_clean();
                            echo json_encode([
                                'success' => true,
                                'message' => "El Ajuste Negativo ($codigoAjuste) ha sido enviado a Gerencia/Administración para su aprobación.",
                                'is_ajuste' => true,
                                'codigo_ajuste' => $codigoAjuste
                            ]);
                            exit();
                        }

                        // -------------------------------------------------------------
                        // RUTA B: SALIDAS DIRECTAS (FLUJO NORMAL)
                        // -------------------------------------------------------------
                        
                        // Obtener configuración global del IVA
                        $ivaConfig = (float) getParametro('impuesto_iva', 0.13);
                        $netoConfig = 1 - $ivaConfig;

                        // Generar número de documento con Prefijo Inteligente
                        $codigosTipo = [
                            'PRODUCCION' => 'P',
                            'VENTA' => 'V',
                            'MUESTRAS' => 'M',
                            'AJUSTE' => 'A',
                            'DEVOLUCION' => 'R'
                        ];

                        $prefijo = null;
                        
                        // Lógica especial para SAL-TEJ, SAL-COS, SAL-TEN
                        if ($tipoSalida === 'PRODUCCION' && !empty($tipoConsumo)) {
                            $mapDestinos = [
                                'TEJIDO' => 'SAL-TEJ',
                                'COSTURA' => 'SAL-COS',
                                'TENIDO' => 'SAL-TEN'
                            ];
                            if (isset($mapDestinos[$tipoConsumo])) {
                                $prefijo = $mapDestinos[$tipoConsumo];
                            }
                        }

                        if (!$prefijo) {
                            $codigoTipo = $codigosTipo[$tipoSalida] ?? 'X';
                            $prefijo = "OUT-MP-$codigoTipo";
                        }

                        $numeroDoc = generarNumeroDocumento($db, 'SALIDA', $prefijo);

                        // Calcular totales
                        $totalDocumento = 0;
                        $totalNeto = 0;
                        $totalIVA = 0;

                        foreach ($data['lineas'] as $linea) {
                            $cantidad = floatval($linea['cantidad']);
                            $costo = floatval($linea['costo_unitario']);
                            $teniaIva = isset($linea['tenia_iva']) ? (bool) $linea['tenia_iva'] : false;

                            if ($teniaIva) {
                                // Para devoluciones con IVA: recalcular el total bruto
                                $subtotalNeto = $cantidad * $costo;
                                $iva = $subtotalNeto / $netoConfig * $ivaConfig; // IVA sobre el bruto
                                $subtotalBruto = $subtotalNeto / $netoConfig;

                                $totalNeto += $subtotalNeto;
                                $totalIVA += $iva;
                                $totalDocumento += $subtotalBruto;
                            } else {
                                // Sin IVA
                                $subtotal = $cantidad * $costo;
                                $totalNeto += $subtotal;
                                $totalDocumento += $subtotal;
                            }
                        }

                        // Obtener ID del subtipo desde el catálogo
                        $stmtSub = $db->prepare("SELECT id_subtipo FROM inv_doc_subtipos WHERE codigo = ? AND id_tipo = 2");
                        $stmtSub->execute([$tipoSalida]);
                        $idDocSubtipo = $stmtSub->fetchColumn() ?: null;

                        // Insertar documento con normalización
                        $stmt = $db->prepare("
                            INSERT INTO documentos_inventario (
                                id_doc_tipo, id_doc_subtipo, id_doc_estado,
                                tipo_documento, tipo_salida, tipo_consumo, numero_documento, fecha_documento,
                                id_tipo_inventario, id_proveedor, id_documento_origen,
                                referencia_externa, modo_asignacion, referencia_entidad_tipo, referencia_entidad_id,
                                subtotal, iva, total,
                                observaciones, estado, creado_por
                            ) VALUES (
                                2, ?, 2,
                                'SALIDA', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'CONFIRMADO', ?
                            )
                        ");

                        $referencia = $tipoSalida;
                        if (!empty($data['referencia'])) {
                            $referencia .= ' - ' . $data['referencia'] ;
                        }

                        $stmt->execute([
                            $idDocSubtipo,
                            $tipoSalida,           // backup
                            $tipoConsumo,
                            $numeroDoc,
                            $data['fecha'] ?? date('Y-m-d'),
                            $TIPO_INVENTARIO_MP,
                            $data['id_proveedor'] ?? null,
                            $data['id_documento_origen'] ?? null,
                            $referencia,
                            $data['modo_asignacion'] ?? 'GENERICO',
                            $data['referencia_entidad_tipo'] ?? null,
                            $data['referencia_entidad_id'] ?? null,
                            $totalNeto,
                            $totalIVA,
                            $totalDocumento,
                            $data['observaciones'] ?? null,
                            $_SESSION['user_id'] ?? null
                        ]);

                        $idDocumento = $db->lastInsertId();

                        // Insertar líneas y actualizar stock
                        $stmtLinea = $db->prepare("
                            INSERT INTO documentos_inventario_detalle (
                                id_documento, id_inventario, id_detalle_origen,
                                cantidad, cantidad_original, costo_unitario,
                                costo_adquisicion, tenia_iva, subtotal,
                                saldo_disponible
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");

                        $stmtStock = $db->prepare("
                            UPDATE inventarios 
                            SET stock_actual = stock_actual - ?
                            WHERE id_inventario = ?
                        ");

                        $stmtMovimiento = $db->prepare("
                            INSERT INTO movimientos_inventario (
                                id_inventario, id_tipo_inventario, fecha_movimiento, tipo_movimiento, 
                                codigo_movimiento, documento_tipo, documento_numero, documento_id,
                                cantidad, costo_unitario, costo_total,
                                stock_anterior, stock_posterior,
                                costo_promedio_anterior, costo_promedio_posterior,
                                estado, creado_por
                            ) VALUES (?, ?, ?, ?, ?, 'SALIDA', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?)
                        ");

                        $fechaMovimiento = ($data['fecha'] ?? date('Y-m-d')) . ' ' . date('H:i:s');
                        $codigoMovBase = generarCodigoMovimiento($db);

                        foreach ($data['lineas'] as $linea) {
                            $cantidad = floatval($linea['cantidad']);
                            $costoUnit = floatval($linea['costo_unitario']);
                            $costoAdq = isset($linea['costo_adquisicion']) ? floatval($linea['costo_adquisicion']) : $costoUnit;
                            $teniaIva = isset($linea['tenia_iva']) ? (int) (bool) $linea['tenia_iva'] : 0;
                            $cantidadOriginal = isset($linea['cantidad_original']) ? floatval($linea['cantidad_original']) : null;
                            $idDetalleOrigen = isset($linea['id_detalle_origen']) ? $linea['id_detalle_origen'] : null;

                            $subtotal = $cantidad * $costoUnit;

                            // Verificar stock disponible y obtener CPP actual
                            $stmtCheck = $db->prepare("
                                SELECT stock_actual, costo_promedio 
                                FROM inventarios 
                                WHERE id_inventario = ?
                                FOR UPDATE
                            ");
                            $stmtCheck->execute([$linea['id_inventario']]);
                            $infoInv = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                            $stockActual = floatval($infoInv['stock_actual'] ?? 0);
                            $cppActual = floatval($infoInv['costo_promedio'] ?? 0);

                            if ($cantidad > $stockActual) {
                                throw new Exception("Stock insuficiente para el producto ID " . $linea['id_inventario']);
                            }

                            // Para devoluciones, validar que no exceda lo disponible
                            if ($tipoSalida === 'DEVOLUCION' && $idDetalleOrigen) {
                                $stmtCheckDev = $db->prepare("
                                    SELECT 
                                        dd.cantidad AS cantidad_original,
                                        COALESCE(
                                            (SELECT SUM(dds.cantidad) 
                                             FROM documentos_inventario_detalle dds
                                             JOIN documentos_inventario ds ON dds.id_documento = ds.id_documento
                                             WHERE dds.id_detalle_origen = ?
                                             AND (ds.tipo_documento = 'SALIDA' OR ds.id_doc_tipo = 2)
                                             AND (ds.estado = 'CONFIRMADO' OR ds.id_doc_estado = 2)
                                             AND ds.referencia_externa COLLATE utf8mb4_unicode_ci LIKE 'DEVOLUCION%'
                                             ), 0
                                        ) AS cantidad_devuelta
                                    FROM documentos_inventario_detalle dd
                                    WHERE dd.id_detalle = ?
                                ");
                                $stmtCheckDev->execute([$idDetalleOrigen, $idDetalleOrigen]);
                                $detalleOrigen = $stmtCheckDev->fetch(PDO::FETCH_ASSOC);

                                if ($detalleOrigen) {
                                    $disponible = $detalleOrigen['cantidad_original'] - $detalleOrigen['cantidad_devuelta'];
                                    if ($cantidad > $disponible) {
                                        throw new Exception("No puede devolver más de lo comprado. Disponible: " . number_format($disponible, 2));
                                    }
                                }
                            }

                            // Insertar línea con saldo_disponible = cantidad
                            // El motor FIFO (wip.php) irá descontando saldo_disponible
                            // conforme se registren producciones.
                            $stmtLinea->execute([
                                $idDocumento,
                                $linea['id_inventario'],
                                $idDetalleOrigen,
                                $cantidad,
                                $cantidadOriginal,
                                $costoUnit,
                                $costoAdq,
                                $teniaIva,
                                $subtotal,
                                $cantidad  // saldo_disponible inicial = cantidad total enviada
                            ]);

                            // Actualizar stock
                            $stmtStock->execute([
                                $cantidad,
                                $linea['id_inventario']
                            ]);

                            // Registrar en movimientos_inventario
                            $tipoMov = 'SALIDA_' . $tipoSalida;

                            // DETERMINAR COSTO: Si es devolución, usar el costo original de la línea.
                            // Para otros casos (producción, etc), usar el CPP actual.
                            $costoMovimiento = ($tipoSalida === 'DEVOLUCION') ? $costoUnit : $cppActual;

                            $stmtMovimiento->execute([
                                $linea['id_inventario'],
                                $TIPO_INVENTARIO_MP,
                                $fechaMovimiento,
                                $tipoMov,
                                $codigoMovBase,
                                $numeroDoc,
                                $idDocumento,
                                $cantidad,
                                $costoMovimiento,
                                $cantidad * $costoMovimiento,
                                $stockActual,
                                $stockActual - $cantidad,
                                $cppActual,      // CPP anterior
                                    // El CPP posterior se recalcula aquí si es devolución
                                ($tipoSalida === 'DEVOLUCION' && ($stockActual - $cantidad) > 0)
                                ? (($stockActual * $cppActual) - ($cantidad * $costoMovimiento)) / ($stockActual - $cantidad)
                                : $cppActual,
                                $_SESSION['user_id'] ?? null
                            ]);
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
                        $stmt = $db->prepare("SELECT * FROM documentos_inventario WHERE id_documento = ? AND (estado = 'CONFIRMADO' OR id_doc_estado = 2)");
                        $stmt->execute([$id]);
                        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$doc) {
                            echo json_encode(['success' => false, 'message' => 'Documento no encontrado o ya anulado (solo se pueden anular documentos CONFIRMADOS)']);
                            exit();
                        }

                        // Obtener detalle para revertir stock (sumar de vuelta)
                        $stmtDet = $db->prepare("SELECT * FROM documentos_inventario_detalle WHERE id_documento = ?");
                        $stmtDet->execute([$id]);
                        $lineas = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

                        // Revertir stock (sumar porque fue una salida)
                        $stmtRevert = $db->prepare("UPDATE inventarios SET stock_actual = stock_actual + ? WHERE id_inventario = ?");

                        foreach ($lineas as $linea) {
                            $stmtRevert->execute([$linea['cantidad'], $linea['id_inventario']]);

                            // Registrar en Kardex la reversión
                            $stmtStockAct = $db->prepare("SELECT stock_actual FROM inventarios WHERE id_inventario = ?");
                            $stmtStockAct->execute([$linea['id_inventario']]);
                            $stockActual = floatval($stmtStockAct->fetchColumn());

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
                                $TIPO_INVENTARIO_MP,
                                $codigoMovAnulacion,
                                $doc['numero_documento'] . ' (ANULADO)',
                                $id,
                                $linea['cantidad'],
                                $linea['costo_unitario'],
                                $linea['subtotal'],
                                $stockActual - $linea['cantidad'],
                                $stockActual,
                                $cppActual, // CPP anterior
                                $cppActual, // CPP posterior (se asume igual al devolver al mismo costo, o se recalcula si es necesario, pero para anulacion simple basta reinvertir)
                                'Anulación: ' . $motivo,
                                $_SESSION['user_id'] ?? null
                            ]);
                        }

                        // Marcar documento como anulado
                        $stmtAnular = $db->prepare("
                            UPDATE documentos_inventario 
                            SET id_doc_estado = 3, estado = 'ANULADO', 
                                fecha_anulacion = NOW(), observaciones = CONCAT(COALESCE(observaciones, ''), '\nANULADO: ', ?), actualizado_por = ?
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

                case 'siguiente_numero':
                    // REDIRIGIR a API centralizada con modo preview usando include
                    $tipo = $_GET['tipo'] ?? 'PRODUCCION';
                    $destino = $_GET['destino'] ?? '';

                    // Configurar parámetros para la API centralizada
                    $_GET['tipo_inventario'] = '1';
                    $_GET['operacion'] = 'SALIDA';
                    $_GET['tipo_movimiento'] = $tipo;
                    $_GET['destino'] = $destino;
                    $_GET['modo'] = 'preview';

                    include 'obtener_siguiente_numero.php';
                    exit();
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
    error_log("Error en salidas_mp.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error en salidas_mp.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

function generarNumeroDocumento($db, $tipo, $prefijo)
{
    $anio = date('Y');
    $mes = date('m');

    $stmt = $db->prepare("
        SELECT ultimo_numero FROM secuencias_documento 
        WHERE tipo_documento COLLATE utf8mb4_unicode_ci = ? AND prefijo COLLATE utf8mb4_unicode_ci = ? AND anio = ? AND mes = ?
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