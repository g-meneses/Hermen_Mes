<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'No autorizado'], 401);
}

try {
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            manejarGet($db);
            break;
        case 'POST':
            manejarPost($db);
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Método no permitido'], 405);
    }
} catch (InvalidArgumentException $e) {
    error_log('Validación en wip.php: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
} catch (Exception $e) {
    error_log('Error en wip.php: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function manejarGet($db)
{
    $action = $_GET['action'] ?? 'list';

    if ($action === 'areas') {
        $stmt = $db->query("
            SELECT id_area, codigo, nombre
            FROM areas_produccion
            WHERE activo = 1
            ORDER BY id_area
        ");

        jsonResponse([
            'success' => true,
            'areas' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    if ($action === 'get_documentos_salida_tejido') {
        obtenerDocumentosSalidaTejido($db);
        return;
    }

    if ($action === 'historial') {
        $idLote = (int) ($_GET['id_lote_wip'] ?? 0);
        if ($idLote <= 0) {
            jsonResponse(['success' => false, 'message' => 'ID de lote requerido'], 400);
        }

        $lote = obtenerHistorialCabeceraLote($db, $idLote);
        if (!$lote) {
            jsonResponse(['success' => false, 'message' => 'Lote WIP no encontrado'], 404);
        }

        $documentoOrigen = obtenerDocumentoOrigenLote($db, $idLote);
        $movimientos = obtenerMovimientosHistorialLote($db, $idLote);

        $resumen = [
            'area_origen' => null,
            'area_actual' => $lote['area_actual_nombre'] ?? null,
            'numero_transferencias' => 0,
            'ultima_fecha_movimiento' => null,
            'documento_origen_asociado' => $documentoOrigen['numero_documento'] ?? null,
            'lote_padre' => $lote['codigo_lote_padre'] ?? null,
            'cantidad_hijos' => !empty($lote['lotes_hijos']) ? count($lote['lotes_hijos']) : 0
        ];

        if (!empty($movimientos)) {
            $resumen['ultima_fecha_movimiento'] = end($movimientos)['fecha'] ?? null;
            foreach ($movimientos as $movimiento) {
                if ($resumen['area_origen'] === null) {
                    $resumen['area_origen'] = $movimiento['area_origen_nombre'] ?? $movimiento['area_destino_nombre'] ?? null;
                }
                if (($movimiento['tipo_movimiento'] ?? '') === 'TRANSFERENCIA_ETAPA') {
                    $resumen['numero_transferencias']++;
                }
            }
        }

        jsonResponse([
            'success' => true,
            'lote' => $lote,
            'documento_origen' => $documentoOrigen,
            'movimientos' => $movimientos,
            'resumen' => $resumen
        ]);
    }

    if ($action === 'bom') {
        $idProducto = (int) ($_GET['id_producto'] ?? 0);
        if ($idProducto <= 0) {
            jsonResponse(['success' => false, 'message' => 'ID de producto requerido'], 400);
        }

        $bom = obtenerBomActivo($db, $idProducto);
        if (!$bom) {
            jsonResponse(['success' => false, 'message' => 'No existe BOM activo para el producto'], 404);
        }

        jsonResponse(['success' => true, 'bom' => $bom]);
    }

    if ($action === 'get') {
        $idLote = (int) ($_GET['id_lote_wip'] ?? 0);
        if ($idLote <= 0) {
            jsonResponse(['success' => false, 'message' => 'ID de lote requerido'], 400);
        }

        $stmt = $db->prepare("
            SELECT
                l.*,
                COALESCE(l.id_documento_salida, l.id_documento_consumo) AS id_documento_salida_resuelto,
                p.codigo_producto,
                p.descripcion_completa,
                a.nombre AS area_actual_nombre,
                lp.nombre AS linea_produccion_nombre,
                m.numero_maquina,
                t.codigo AS turno_codigo,
                t.nombre AS turno_nombre
            FROM lote_wip l
            INNER JOIN productos_tejidos p ON p.id_producto = l.id_producto
            LEFT JOIN areas_produccion a ON a.id_area = l.id_area_actual
            LEFT JOIN lineas_produccion_erp lp ON lp.id_linea_produccion = l.id_linea_produccion
            LEFT JOIN maquinas m ON m.id_maquina = l.id_maquina
            LEFT JOIN turnos t ON t.id_turno = l.id_turno
            WHERE l.id_lote_wip = ?
        ");
        $stmt->execute([$idLote]);
        $lote = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lote) {
            jsonResponse(['success' => false, 'message' => 'Lote WIP no encontrado'], 404);
        }

        $stmtMov = $db->prepare("
            SELECT
                mw.*,
                ao.nombre AS area_origen_nombre,
                ad.nombre AS area_destino_nombre
            FROM movimientos_wip mw
            LEFT JOIN areas_produccion ao ON ao.id_area = mw.id_area_origen
            LEFT JOIN areas_produccion ad ON ad.id_area = mw.id_area_destino
            WHERE mw.id_lote_wip = ?
            ORDER BY mw.fecha, mw.id_movimiento
        ");
        $stmtMov->execute([$idLote]);

        jsonResponse([
            'success' => true,
            'lote' => $lote,
            'movimientos' => $stmtMov->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    $stmt = $db->query("
        SELECT
            l.id_lote_wip,
            l.id_lote_padre,
            l.codigo_lote,
            l.referencia_externa,
            COALESCE(l.id_documento_salida, l.id_documento_consumo) AS id_documento_salida,
            l.estado_lote,
            l.id_area_actual,
            l.id_maquina,
            l.id_turno,
            l.cantidad_docenas,
            l.cantidad_unidades,
            l.cantidad_base_unidades,
            l.costo_mp_acumulado,
            l.costo_unitario_promedio,
            l.fecha_inicio,
            p.codigo_producto,
            p.descripcion_completa,
            a.nombre AS area_actual_nombre,
            m.numero_maquina,
            t.codigo AS turno_codigo,
            t.nombre AS turno_nombre
        FROM lote_wip l
        INNER JOIN productos_tejidos p ON p.id_producto = l.id_producto
        LEFT JOIN areas_produccion a ON a.id_area = l.id_area_actual
        LEFT JOIN maquinas m ON m.id_maquina = l.id_maquina
        LEFT JOIN turnos t ON t.id_turno = l.id_turno
        ORDER BY l.fecha_inicio DESC, l.id_lote_wip DESC
        LIMIT 100
    ");

    jsonResponse(['success' => true, 'lotes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function manejarPost($db)
{
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? 'crear_lote';

    if ($action === 'registrar_produccion_tejido') {
        registrarProduccionTejido($db, $data);
        return;
    }

    if ($action === 'crear_lote') {
        crearLoteWip($db, $data);
        return;
    }

    if ($action === 'transferir_lote') {
        transferirLoteWip($db, $data);
        return;
    }

    if ($action === 'transferir_parcial') {
        transferirParcialLoteWip($db, $data);
        return;
    }

    jsonResponse(['success' => false, 'message' => 'Acción no válida'], 400);
}

function obtenerDocumentosSalidaTejido($db)
{
    $stmt = $db->query("
        SELECT
            d.id_documento,
            d.numero_documento,
            d.fecha_documento,
            d.estado,
            d.tipo_consumo,
            COUNT(dd.id_detalle) AS total_lineas,
            COALESCE(SUM(dd.cantidad), 0) AS cantidad_total_salida
        FROM documentos_inventario d
        LEFT JOIN documentos_inventario_detalle dd ON dd.id_documento = d.id_documento
        WHERE d.tipo_documento = 'SALIDA'
          AND d.tipo_consumo = 'TEJIDO'
        GROUP BY d.id_documento, d.numero_documento, d.fecha_documento, d.estado, d.tipo_consumo
        ORDER BY d.fecha_documento DESC, d.id_documento DESC
    ");

    jsonResponse([
        'success' => true,
        'documentos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

function registrarProduccionTejido($db, $data)
{
    $idDocumentoSalida = (int) ($data['id_documento_salida'] ?? 0);
    $observaciones = trim((string) ($data['observaciones'] ?? ''));
    $lineas = $data['lineas_produccion'] ?? [];

    if ($idDocumentoSalida <= 0) {
        jsonResponse(['success' => false, 'message' => 'Documento SAL-TEJ requerido'], 400);
    }

    if (!is_array($lineas) || empty($lineas)) {
        jsonResponse(['success' => false, 'message' => 'Debe registrar al menos una linea de produccion'], 400);
    }

    $documentoSalida = obtenerDocumentoSalidaTejidoPorId($db, $idDocumentoSalida);
    if (!$documentoSalida) {
        jsonResponse(['success' => false, 'message' => 'El documento indicado no existe o no es una salida a tejido'], 400);
    }

    $idAreaTejeduria = obtenerAreaTejeduriaId($db);
    if (!$idAreaTejeduria) {
        jsonResponse(['success' => false, 'message' => 'No se encontro el area TEJEDURIA'], 500);
    }

    $db->beginTransaction();

    try {
        $lotesCreados = [];
        $consumoTeoricoTotal = [];
        $costoMpTotal = 0.0;
        $totalBase = 0;
        $fechasProduccion = [];

        foreach ($lineas as $indice => $linea) {
            $lineaNormalizada = validarLineaProduccionTejido($db, $linea, $indice + 1);
            $fechasProduccion[] = $lineaNormalizada['fecha'];

            $bom = obtenerBomActivo($db, $lineaNormalizada['id_producto']);
            if (!$bom || empty($bom['detalles'])) {
                throw new InvalidArgumentException('El producto de la linea ' . ($indice + 1) . ' no tiene BOM activo');
            }

            $cantidadBase = $lineaNormalizada['cantidad_docenas'] * 12 + $lineaNormalizada['cantidad_unidades'];
            if ($cantidadBase <= 0) {
                throw new InvalidArgumentException('La linea ' . ($indice + 1) . ' debe registrar una cantidad mayor a cero');
            }

            $resumenBom = calcularConsumoTeoricoYCosto($bom, $cantidadBase);
            foreach ($resumenBom['componentes'] as $componente) {
                $nombreComponente = $componente['nombre'];
                if (!isset($consumoTeoricoTotal[$nombreComponente])) {
                    $consumoTeoricoTotal[$nombreComponente] = 0.0;
                }
                $consumoTeoricoTotal[$nombreComponente] += $componente['consumo_kg'];
            }

            $loteInsertado = insertarLoteProduccionTejido($db, [
                'id_producto' => $lineaNormalizada['id_producto'],
                'id_maquina' => $lineaNormalizada['id_maquina'],
                'id_turno' => $lineaNormalizada['id_turno'],
                'id_area_actual' => $idAreaTejeduria,
                'id_documento_salida' => $idDocumentoSalida,
                'cantidad_docenas' => $lineaNormalizada['cantidad_docenas'],
                'cantidad_unidades' => $lineaNormalizada['cantidad_unidades'],
                'cantidad_base_unidades' => $cantidadBase,
                'costo_mp_acumulado' => $resumenBom['costo_total'],
                'costo_unitario_promedio' => $resumenBom['costo_unitario'],
                'fecha_produccion' => $lineaNormalizada['fecha']
            ]);

            $stmtMov = $db->prepare("
                INSERT INTO movimientos_wip (
                    id_lote_wip, id_lote_relacionado, tipo_movimiento, cantidad_docenas, cantidad_unidades,
                    id_area_origen, id_area_destino, id_documento_inventario,
                    referencia_externa, fecha, usuario, observaciones
                ) VALUES (?, NULL, 'CREACION_EN_TEJIDO', ?, ?, NULL, NULL, ?, ?, ?, ?, ?)
            ");
            $stmtMov->execute([
                $loteInsertado['id_lote_wip'],
                $lineaNormalizada['cantidad_docenas'],
                $lineaNormalizada['cantidad_unidades'],
                $idDocumentoSalida,
                $loteInsertado['codigo_lote'],
                $lineaNormalizada['fecha'] . ' 00:00:00',
                $_SESSION['user_id'] ?? null,
                'Produccion registrada desde documento ' . $documentoSalida['numero_documento']
            ]);

            $lotesCreados[] = [
                'id_lote_wip' => $loteInsertado['id_lote_wip'],
                'codigo_lote' => $loteInsertado['codigo_lote'],
                'fecha_produccion' => $lineaNormalizada['fecha'],
                'turno' => $lineaNormalizada['turno_codigo'],
                'maquina' => $lineaNormalizada['maquina_codigo'],
                'tejedor' => $lineaNormalizada['nombre_tejedor'],
                'producto' => $lineaNormalizada['producto_nombre'],
                'cantidad' => $lineaNormalizada['cantidad_docenas'] . '|' . str_pad((string) $lineaNormalizada['cantidad_unidades'], 2, '0', STR_PAD_LEFT),
                'cantidad_base' => $cantidadBase,
                'consumo_teorico_kg' => round($resumenBom['consumo_total_kg'], 4),
                'costo_mp' => round($resumenBom['costo_total'], 4),
                'estado' => 'ACTIVO',
                'id_documento_salida' => $idDocumentoSalida,
                'documento_referencia' => $documentoSalida['numero_documento']
            ];

            $costoMpTotal += $resumenBom['costo_total'];
            $totalBase += $cantidadBase;
        }

        sort($fechasProduccion);

        $stmtPlanilla = $db->prepare("
            INSERT INTO planillas_tejido (
                id_documento_salida, fecha_inicio, fecha_fin, detalles_json,
                registrado_por, observaciones, activo
            ) VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmtPlanilla->execute([
            $idDocumentoSalida,
            $fechasProduccion[0] ?? null,
            $fechasProduccion[count($fechasProduccion) - 1] ?? null,
            json_encode([
                'documento_salida' => $documentoSalida['numero_documento'],
                'lineas_produccion' => $lineas,
                'lotes_creados' => $lotesCreados
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $_SESSION['user_id'] ?? null,
            $observaciones !== '' ? $observaciones : null
        ]);

        $salidaDocumento = obtenerSalidaDocumentoKg($db, $idDocumentoSalida);
        $analisisDiferencia = construirAnalisisDiferencia($salidaDocumento, $consumoTeoricoTotal);

        $db->commit();

        jsonResponse([
            'success' => true,
            'message' => 'Produccion de tejido registrada exitosamente',
            'documento_salida' => [
                'id' => (int) $documentoSalida['id_documento'],
                'numero' => $documentoSalida['numero_documento'],
                'fecha' => $documentoSalida['fecha_documento'],
                'tipo_consumo' => $documentoSalida['tipo_consumo']
            ],
            'lotes_creados' => $lotesCreados,
            'analisis' => [
                'documento_salida' => $salidaDocumento,
                'consumo_teorico_total' => array_map(function ($valor) {
                    return round($valor, 4);
                }, $consumoTeoricoTotal),
                'diferencia' => $analisisDiferencia
            ],
            'resumen' => [
                'total_lotes_creados' => count($lotesCreados),
                'cantidad_total_producida' => formatearCantidadBase($totalBase),
                'costo_mp_total' => round($costoMpTotal, 4),
                'periodo' => !empty($fechasProduccion)
                    ? ($fechasProduccion[0] . ' a ' . $fechasProduccion[count($fechasProduccion) - 1])
                    : null
            ]
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function crearLoteWip($db, $data)
{
    $idProducto = (int) ($data['id_producto'] ?? 0);
    $cantidadDocenasIn = (int) ($data['cantidad_docenas'] ?? 0);
    $cantidadUnidadesIn = (int) ($data['cantidad_unidades'] ?? 0);
    $idLineaProduccion = !empty($data['id_linea_produccion']) ? (int) $data['id_linea_produccion'] : null;
    $idAreaActual = !empty($data['id_area_actual']) ? (int) $data['id_area_actual'] : null;
    $observaciones = trim($data['observaciones'] ?? '');
    $referenciaExterna = trim($data['referencia_externa'] ?? '');

    if ($idProducto <= 0) {
        jsonResponse(['success' => false, 'message' => 'Producto requerido'], 400);
    }

    $cantidades = normalizarCantidades($cantidadDocenasIn, $cantidadUnidadesIn);
    if ($cantidades['cantidad_base_unidades'] <= 0) {
        jsonResponse(['success' => false, 'message' => 'Debe registrar al menos una docena o unidad'], 400);
    }

    $db->beginTransaction();

    try {
        $stmtProducto = $db->prepare("
            SELECT id_producto, codigo_producto, descripcion_completa
            FROM productos_tejidos
            WHERE id_producto = ? AND activo = 1
        ");
        $stmtProducto->execute([$idProducto]);
        $producto = $stmtProducto->fetch(PDO::FETCH_ASSOC);

        if (!$producto) {
            throw new InvalidArgumentException('Producto no encontrado o inactivo');
        }

        $bom = obtenerBomActivo($db, $idProducto);
        if (!$bom || empty($bom['detalles'])) {
            throw new InvalidArgumentException('El producto no tiene BOM WIP activo');
        }

        if (!$idAreaActual) {
            $idAreaActual = obtenerAreaTejeduriaId($db);
        }

        if (!$idAreaActual) {
            throw new InvalidArgumentException('No se encontró el área TEJEDURIA para el lote WIP');
        }

        $idDocSubtipo = obtenerSubtipoConsumoWip($db);
        if (!$idDocSubtipo) {
            throw new InvalidArgumentException('No se encontró subtipo documental de consumo WIP');
        }

        $referenciaExterna = $referenciaExterna !== '' ? $referenciaExterna : generarCodigoLoteWip($db);
        $numeroDocumento = generarNumeroDocumento($db, 'SALIDA', 'OUT-MP-W');
        $codigoMovimientoBase = generarCodigoMovimiento($db);

        $componentesProcesados = [];
        $subtotalDocumento = 0.0;

        foreach ($bom['detalles'] as $detalle) {
            if ((int) $detalle['id_tipo_inventario'] !== 1) {
                throw new InvalidArgumentException('FASE 0 solo admite componentes MP en el BOM');
            }

            $factorDocena = $cantidades['cantidad_base_unidades'] / 12;
            $mermaTotalPct = (float) $bom['merma_pct'] + (float) $detalle['merma_pct'];
            $gramosRequeridos = (float) $detalle['gramos_por_docena'] * $factorDocena;
            $gramosConMerma = $gramosRequeridos * (1 + ($mermaTotalPct / 100));

            $cantidadInventario = convertirGramosAUnidadInventario(
                $gramosConMerma,
                $detalle['unidad_codigo'],
                $detalle['unidad_abreviatura']
            );

            $stmtLock = $db->prepare("
                SELECT stock_actual, costo_promedio, costo_unitario
                FROM inventarios
                WHERE id_inventario = ?
                FOR UPDATE
            ");
            $stmtLock->execute([$detalle['id_inventario']]);
            $inventario = $stmtLock->fetch(PDO::FETCH_ASSOC);

            if (!$inventario) {
                throw new InvalidArgumentException('Componente de inventario no encontrado: ' . $detalle['id_inventario']);
            }

            $stockActual = (float) $inventario['stock_actual'];
            $cppActual = (float) $inventario['costo_promedio'];
            $costoUnitario = $cppActual > 0 ? $cppActual : (float) $inventario['costo_unitario'];

            if ($cantidadInventario > $stockActual) {
                throw new InvalidArgumentException('Stock insuficiente para ' . $detalle['codigo'] . '. Requerido: ' . number_format($cantidadInventario, 4) . ' ' . $detalle['unidad_abreviatura'] . ', disponible: ' . number_format($stockActual, 4));
            }

            $subtotalLinea = round($cantidadInventario * $costoUnitario, 4);
            $subtotalDocumento += $subtotalLinea;

            $componentesProcesados[] = [
                'detalle_bom' => $detalle,
                'cantidad_inventario' => round($cantidadInventario, 4),
                'gramos_consumo' => round($gramosConMerma, 4),
                'stock_actual' => $stockActual,
                'stock_nuevo' => round($stockActual - $cantidadInventario, 4),
                'costo_unitario' => round($costoUnitario, 4),
                'subtotal' => $subtotalLinea
            ];
        }

        $stmtDoc = $db->prepare("
            INSERT INTO documentos_inventario (
                id_doc_tipo, id_doc_subtipo, id_doc_estado,
                tipo_documento, tipo_salida, numero_documento, fecha_documento,
                id_tipo_inventario, id_area_produccion, id_destino,
                referencia_externa, subtotal, iva, total,
                observaciones, estado, creado_por
            ) VALUES (
                2, ?, 2,
                'SALIDA', 'PRODUCCION', ?, CURDATE(),
                1, ?, ?, ?, ?, 0, ?, ?, 'CONFIRMADO', ?
            )
        ");

        $obsDocumento = 'Consumo WIP FASE 0 - Producto ' . $producto['codigo_producto'];
        if ($observaciones !== '') {
            $obsDocumento .= ' | ' . $observaciones;
        }

        $stmtDoc->execute([
            $idDocSubtipo,
            $numeroDocumento,
            $idAreaActual,
            $idAreaActual,
            $referenciaExterna,
            round($subtotalDocumento, 4),
            round($subtotalDocumento, 4),
            $obsDocumento,
            $_SESSION['user_id'] ?? null
        ]);

        $idDocumento = (int) $db->lastInsertId();

        $stmtDet = $db->prepare("
            INSERT INTO documentos_inventario_detalle (
                id_documento, id_inventario, id_unidad,
                cantidad, cantidad_original, costo_unitario,
                costo_adquisicion, subtotal, observaciones
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmtStock = $db->prepare("
            UPDATE inventarios
            SET stock_actual = ?
            WHERE id_inventario = ?
        ");

        $stmtMovInv = $db->prepare("
            INSERT INTO movimientos_inventario (
                id_inventario, id_tipo_inventario, fecha_movimiento, tipo_movimiento,
                codigo_movimiento, documento_tipo, documento_numero, documento_id, documento_detalle_id,
                cantidad, costo_unitario, costo_total,
                stock_anterior, stock_posterior,
                costo_promedio_anterior, costo_promedio_posterior,
                referencia_externa, observaciones, estado, creado_por
            ) VALUES (?, 1, NOW(), 'SALIDA_WIP_CONSUMO', ?, 'SALIDA', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?)
        ");

        foreach ($componentesProcesados as $item) {
            $detalleBom = $item['detalle_bom'];

            $stmtDet->execute([
                $idDocumento,
                $detalleBom['id_inventario'],
                $detalleBom['id_unidad'],
                $item['cantidad_inventario'],
                $item['gramos_consumo'],
                $item['costo_unitario'],
                $item['costo_unitario'],
                $item['subtotal'],
                'BOM ' . $bom['codigo_bom']
            ]);

            $idDetalleDocumento = (int) $db->lastInsertId();

            $stmtStock->execute([
                $item['stock_nuevo'],
                $detalleBom['id_inventario']
            ]);

            $stmtMovInv->execute([
                $detalleBom['id_inventario'],
                $codigoMovimientoBase,
                $numeroDocumento,
                $idDocumento,
                $idDetalleDocumento,
                $item['cantidad_inventario'],
                $item['costo_unitario'],
                $item['subtotal'],
                $item['stock_actual'],
                $item['stock_nuevo'],
                $item['costo_unitario'],
                $item['costo_unitario'],
                $referenciaExterna,
                'Consumo WIP FASE 0',
                $_SESSION['user_id'] ?? null
            ]);
        }

        $stmtLote = $db->prepare("
            INSERT INTO lote_wip (
                codigo_lote, id_producto, id_maquina, id_turno, id_linea_produccion,
                cantidad_docenas, cantidad_unidades, cantidad_base_unidades,
                id_area_actual, estado_lote,
                costo_mp_acumulado, costo_unitario_promedio,
                id_documento_consumo, id_documento_salida, referencia_externa,
                fecha_inicio, creado_por
            ) VALUES (?, ?, NULL, NULL, ?, ?, ?, ?, ?, 'ACTIVO', ?, ?, ?, ?, ?, NOW(), ?)
        ");

        $costoUnitarioPromedio = $cantidades['cantidad_base_unidades'] > 0
            ? round($subtotalDocumento / $cantidades['cantidad_base_unidades'], 6)
            : 0;

        $stmtLote->execute([
            $referenciaExterna,
            $idProducto,
            $idLineaProduccion,
            $cantidades['cantidad_docenas'],
            $cantidades['cantidad_unidades'],
            $cantidades['cantidad_base_unidades'],
            $idAreaActual,
            round($subtotalDocumento, 4),
            $costoUnitarioPromedio,
            $idDocumento,
            $idDocumento,
            $referenciaExterna,
            $_SESSION['user_id'] ?? null
        ]);

        $idLoteWip = (int) $db->lastInsertId();

        $stmtMovWip = $db->prepare("
            INSERT INTO movimientos_wip (
                id_lote_wip, id_lote_relacionado, tipo_movimiento, cantidad_docenas, cantidad_unidades,
                id_area_origen, id_area_destino, id_documento_inventario,
                referencia_externa, fecha, usuario, observaciones
            ) VALUES (?, NULL, 'CREACION_EN_TEJIDO', ?, ?, NULL, ?, ?, ?, NOW(), ?, ?)
        ");

        $stmtMovWip->execute([
            $idLoteWip,
            $cantidades['cantidad_docenas'],
            $cantidades['cantidad_unidades'],
            $idAreaActual,
            $idDocumento,
            $referenciaExterna,
            $_SESSION['user_id'] ?? null,
            $observaciones !== '' ? $observaciones : 'Creación inicial de lote WIP'
        ]);

        $db->commit();

        jsonResponse([
            'success' => true,
            'message' => 'WIP FASE 0 registrado correctamente',
            'id_lote_wip' => $idLoteWip,
            'codigo_lote' => $referenciaExterna,
            'id_documento_salida' => $idDocumento,
            'numero_documento' => $numeroDocumento,
            'resumen_consumo' => array_map(function ($item) {
                return [
                    'id_inventario' => (int) $item['detalle_bom']['id_inventario'],
                    'codigo' => $item['detalle_bom']['codigo'],
                    'nombre' => $item['detalle_bom']['nombre'],
                    'cantidad_consumida' => $item['cantidad_inventario'],
                    'unidad' => $item['detalle_bom']['unidad_abreviatura'],
                    'subtotal' => $item['subtotal']
                ];
            }, $componentesProcesados)
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function transferirLoteWip($db, $data)
{
    $idLoteWip = (int) ($data['id_lote_wip'] ?? 0);
    $idAreaDestino = (int) ($data['id_area_destino'] ?? 0);
    $cantidadDocenasIn = (int) ($data['cantidad_docenas'] ?? 0);
    $cantidadUnidadesIn = (int) ($data['cantidad_unidades'] ?? 0);
    $observaciones = trim($data['observaciones'] ?? '');

    if ($idLoteWip <= 0) {
        jsonResponse(['success' => false, 'message' => 'Lote WIP requerido'], 400);
    }

    if ($idAreaDestino <= 0) {
        jsonResponse(['success' => false, 'message' => 'Área destino requerida'], 400);
    }

    $cantidades = normalizarCantidades($cantidadDocenasIn, $cantidadUnidadesIn);
    if ($cantidades['cantidad_base_unidades'] <= 0) {
        jsonResponse(['success' => false, 'message' => 'Debe registrar una cantidad válida para transferir'], 400);
    }

    $db->beginTransaction();

    try {
        $lote = obtenerLoteWipBloqueado($db, $idLoteWip);
        if (!$lote) {
            throw new InvalidArgumentException('Lote WIP no encontrado');
        }

        if (in_array($lote['estado_lote'], ['CERRADO', 'ANULADO'], true)) {
            throw new InvalidArgumentException('No se puede transferir un lote cerrado o anulado');
        }

        $idAreaOrigen = (int) $lote['id_area_actual'];
        if ($idAreaOrigen === $idAreaDestino) {
            throw new InvalidArgumentException('No se puede transferir a la misma área');
        }

        $areaDestino = obtenerAreaProduccion($db, $idAreaDestino);
        if (!$areaDestino) {
            throw new InvalidArgumentException('Área destino no válida o inactiva');
        }

        if (
            (int) $lote['cantidad_docenas'] !== $cantidades['cantidad_docenas'] ||
            (int) $lote['cantidad_unidades'] !== $cantidades['cantidad_unidades'] ||
            (int) $lote['cantidad_base_unidades'] !== $cantidades['cantidad_base_unidades']
        ) {
            throw new InvalidArgumentException('FASE 1 solo admite transferencia total del lote; la transferencia parcial queda bloqueada');
        }

        $stmtUpd = $db->prepare("
            UPDATE lote_wip
            SET id_area_actual = ?
            WHERE id_lote_wip = ?
        ");
        $stmtUpd->execute([$idAreaDestino, $idLoteWip]);

        $stmtMov = $db->prepare("
            INSERT INTO movimientos_wip (
                id_lote_wip, id_lote_relacionado, tipo_movimiento, cantidad_docenas, cantidad_unidades,
                id_area_origen, id_area_destino, id_documento_inventario,
                referencia_externa, fecha, usuario, observaciones
            ) VALUES (?, NULL, 'TRANSFERENCIA_ETAPA', ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");

        $obs = $observaciones !== ''
            ? $observaciones
            : 'Transferencia interna WIP de ' . ($lote['area_actual_nombre'] ?? 'área origen') . ' a ' . $areaDestino['nombre'];

        $stmtMov->execute([
            $idLoteWip,
            (int) $lote['cantidad_docenas'],
            (int) $lote['cantidad_unidades'],
            $idAreaOrigen,
            $idAreaDestino,
            !empty($lote['id_documento_salida']) ? (int) $lote['id_documento_salida'] : (!empty($lote['id_documento_consumo']) ? (int) $lote['id_documento_consumo'] : null),
            $lote['referencia_externa'],
            $_SESSION['user_id'] ?? null,
            $obs
        ]);

        $db->commit();

        jsonResponse([
            'success' => true,
            'message' => 'Lote WIP transferido correctamente',
            'id_lote_wip' => $idLoteWip,
            'codigo_lote' => $lote['codigo_lote'],
            'area_origen' => $lote['area_actual_nombre'] ?? null,
            'area_destino' => $areaDestino['nombre'],
            'transferencia_parcial' => false
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function transferirParcialLoteWip($db, $data)
{
    $idLoteWip = (int) ($data['id_lote_wip'] ?? 0);
    $idAreaDestino = (int) ($data['id_area_destino'] ?? 0);
    $cantidadDocenasIn = (int) ($data['cantidad_docenas'] ?? 0);
    $cantidadUnidadesIn = (int) ($data['cantidad_unidades'] ?? 0);
    $observaciones = trim($data['observaciones'] ?? '');

    if ($idLoteWip <= 0) {
        jsonResponse(['success' => false, 'message' => 'Lote WIP requerido'], 400);
    }

    if ($idAreaDestino <= 0) {
        jsonResponse(['success' => false, 'message' => 'Área destino requerida'], 400);
    }

    $cantidades = normalizarCantidades($cantidadDocenasIn, $cantidadUnidadesIn);
    if ($cantidades['cantidad_base_unidades'] <= 0) {
        jsonResponse(['success' => false, 'message' => 'Debe registrar una cantidad válida para el split'], 400);
    }

    $db->beginTransaction();

    try {
        $lote = obtenerLoteWipBloqueado($db, $idLoteWip);
        if (!$lote) {
            throw new InvalidArgumentException('Lote WIP no encontrado');
        }

        if (in_array($lote['estado_lote'], ['CERRADO', 'ANULADO'], true)) {
            throw new InvalidArgumentException('No se puede dividir un lote cerrado o anulado');
        }

        $idAreaOrigen = (int) $lote['id_area_actual'];
        if ($idAreaOrigen === $idAreaDestino) {
            throw new InvalidArgumentException('No se puede transferir a la misma área');
        }

        $areaDestino = obtenerAreaProduccion($db, $idAreaDestino);
        if (!$areaDestino) {
            throw new InvalidArgumentException('Área destino no válida o inactiva');
        }

        $baseDisponible = (int) $lote['cantidad_base_unidades'];
        if ($cantidades['cantidad_base_unidades'] >= $baseDisponible) {
            throw new InvalidArgumentException('La transferencia parcial debe ser menor al saldo total del lote');
        }

        $restanteBase = $baseDisponible - $cantidades['cantidad_base_unidades'];
        if ($restanteBase <= 0) {
            throw new InvalidArgumentException('El lote original no puede quedar sin saldo en un split parcial');
        }
        $restante = normalizarCantidades(0, $restanteBase);

        $costoOriginal = (float) $lote['costo_mp_acumulado'];
        $costoUnitarioPromedio = (float) $lote['costo_unitario_promedio'];
        if ($costoUnitarioPromedio <= 0 && $baseDisponible > 0) {
            $costoUnitarioPromedio = round($costoOriginal / $baseDisponible, 6);
        }

        $costoDerivado = round($costoUnitarioPromedio * $cantidades['cantidad_base_unidades'], 4);
        $costoRestante = round($costoOriginal - $costoDerivado, 4);
        if ($costoRestante < 0) {
            $costoRestante = 0;
        }

        $codigoLoteDerivado = generarCodigoLoteDerivado($db, $lote['codigo_lote']);
        $referenciaDerivada = generarReferenciaDerivada($db, $lote['referencia_externa']);

        $stmtUpdateOrigen = $db->prepare("
            UPDATE lote_wip
            SET cantidad_docenas = ?, cantidad_unidades = ?, cantidad_base_unidades = ?,
                costo_mp_acumulado = ?, costo_unitario_promedio = ?
            WHERE id_lote_wip = ?
        ");
        $stmtUpdateOrigen->execute([
            $restante['cantidad_docenas'],
            $restante['cantidad_unidades'],
            $restante['cantidad_base_unidades'],
            $costoRestante,
            $restante['cantidad_base_unidades'] > 0 ? $costoUnitarioPromedio : 0,
            $idLoteWip
        ]);

        $stmtCrearDerivado = $db->prepare("
            INSERT INTO lote_wip (
                id_lote_padre, codigo_lote, id_producto, id_linea_produccion,
                cantidad_docenas, cantidad_unidades, cantidad_base_unidades,
                id_area_actual, estado_lote, costo_mp_acumulado, costo_unitario_promedio,
                id_documento_consumo, id_documento_salida, referencia_externa, fecha_inicio, creado_por
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmtCrearDerivado->execute([
            $idLoteWip,
            $codigoLoteDerivado,
            $lote['id_producto'],
            $lote['id_linea_produccion'],
            $cantidades['cantidad_docenas'],
            $cantidades['cantidad_unidades'],
            $cantidades['cantidad_base_unidades'],
            $idAreaDestino,
            $costoDerivado,
            $costoUnitarioPromedio,
            $lote['id_documento_consumo'] ?? $lote['id_documento_salida'],
            $lote['id_documento_salida'] ?? $lote['id_documento_consumo'],
            $referenciaDerivada,
            $_SESSION['user_id'] ?? null
        ]);
        $idLoteDerivado = (int) $db->lastInsertId();

        $stmtMov = $db->prepare("
            INSERT INTO movimientos_wip (
                id_lote_wip, id_lote_relacionado, tipo_movimiento, cantidad_docenas, cantidad_unidades,
                id_area_origen, id_area_destino, id_documento_inventario,
                referencia_externa, fecha, usuario, observaciones
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");

        $stmtMov->execute([
            $idLoteWip,
            $idLoteDerivado,
            'TRANSFERENCIA_ETAPA',
            $cantidades['cantidad_docenas'],
            $cantidades['cantidad_unidades'],
            $idAreaOrigen,
            $idAreaDestino,
            !empty($lote['id_documento_salida']) ? (int) $lote['id_documento_salida'] : (!empty($lote['id_documento_consumo']) ? (int) $lote['id_documento_consumo'] : null),
            $lote['referencia_externa'],
            $_SESSION['user_id'] ?? null,
            $observaciones !== '' ? $observaciones : 'Split parcial hacia lote derivado ' . $codigoLoteDerivado
        ]);

            $stmtMov->execute([
            $idLoteDerivado,
            $idLoteWip,
            'CREACION_EN_TEJIDO',
            $cantidades['cantidad_docenas'],
            $cantidades['cantidad_unidades'],
            $idAreaOrigen,
            $idAreaDestino,
            !empty($lote['id_documento_salida']) ? (int) $lote['id_documento_salida'] : (!empty($lote['id_documento_consumo']) ? (int) $lote['id_documento_consumo'] : null),
            $referenciaDerivada,
            $_SESSION['user_id'] ?? null,
            'Lote derivado creado por split parcial desde ' . $lote['codigo_lote']
        ]);

        $db->commit();

        jsonResponse([
            'success' => true,
            'message' => 'Split parcial ejecutado correctamente',
            'id_lote_origen' => $idLoteWip,
            'codigo_lote_origen' => $lote['codigo_lote'],
            'id_lote_derivado' => $idLoteDerivado,
            'codigo_lote_derivado' => $codigoLoteDerivado,
            'referencia_derivada' => $referenciaDerivada,
            'cantidad_transferida' => $cantidades,
            'area_destino' => $areaDestino['nombre'],
            'costo_transferido' => $costoDerivado,
            'costo_restante' => $costoRestante,
            'transferencia_parcial' => true
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function obtenerBomActivo($db, $idProducto)
{
    $stmt = $db->prepare("
        SELECT id_bom, id_producto, codigo_bom, version_bom, merma_pct, observaciones
        FROM bom_productos
        WHERE id_producto = ? AND estado = 'ACTIVO'
        ORDER BY fecha_vigencia_desde DESC, id_bom DESC
        LIMIT 1
    ");
    $stmt->execute([$idProducto]);
    $bom = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bom) {
        return null;
    }

    $stmtDet = $db->prepare("
        SELECT
            d.id_bom_detalle,
            d.id_inventario,
            d.gramos_por_docena,
            d.porcentaje_componente,
            d.merma_pct,
            d.es_principal,
            d.orden_visual,
            i.codigo,
            i.nombre,
            i.id_tipo_inventario,
            i.id_unidad,
            i.costo_promedio,
            i.costo_unitario,
            um.codigo AS unidad_codigo,
            um.abreviatura AS unidad_abreviatura
        FROM bom_productos_detalle d
        INNER JOIN inventarios i ON i.id_inventario = d.id_inventario
        INNER JOIN unidades_medida um ON um.id_unidad = i.id_unidad
        WHERE d.id_bom = ?
        ORDER BY d.orden_visual, d.es_principal DESC, i.nombre
    ");
    $stmtDet->execute([$bom['id_bom']]);
    $bom['detalles'] = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
    return $bom;
}

function obtenerLoteWipBloqueado($db, $idLoteWip)
{
    $stmt = $db->prepare("
        SELECT
            l.*,
            ao.nombre AS area_actual_nombre
        FROM lote_wip l
        LEFT JOIN areas_produccion ao ON ao.id_area = l.id_area_actual
        WHERE l.id_lote_wip = ?
        FOR UPDATE
    ");
    $stmt->execute([$idLoteWip]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function obtenerAreaProduccion($db, $idArea)
{
    $stmt = $db->prepare("
        SELECT id_area, codigo, nombre
        FROM areas_produccion
        WHERE id_area = ? AND activo = 1
        LIMIT 1
    ");
    $stmt->execute([$idArea]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function obtenerHistorialCabeceraLote($db, $idLoteWip)
{
    $stmt = $db->prepare("
        SELECT
            l.id_lote_wip,
            l.id_lote_padre,
            l.codigo_lote,
            l.id_producto,
            p.codigo_producto,
            p.descripcion_completa,
            l.id_linea_produccion,
            lp.nombre AS linea_produccion_nombre,
            l.id_area_actual,
            a.nombre AS area_actual_nombre,
            l.estado_lote,
            l.cantidad_docenas,
            l.cantidad_unidades,
            l.cantidad_base_unidades,
            l.costo_mp_acumulado,
            l.costo_unitario_promedio,
            COALESCE(l.id_documento_salida, l.id_documento_consumo) AS id_documento_salida,
            l.referencia_externa,
            l.fecha_inicio,
            l.fecha_actualizacion,
            l.creado_por,
            u.nombre_completo AS creado_por_nombre,
            lpadr.codigo_lote AS codigo_lote_padre,
            lpadr.id_area_actual AS id_area_padre,
            apadr.nombre AS area_padre_nombre,
            m.numero_maquina,
            t.codigo AS turno_codigo,
            t.nombre AS turno_nombre
        FROM lote_wip l
        INNER JOIN productos_tejidos p ON p.id_producto = l.id_producto
        LEFT JOIN lineas_produccion_erp lp ON lp.id_linea_produccion = l.id_linea_produccion
        LEFT JOIN areas_produccion a ON a.id_area = l.id_area_actual
        LEFT JOIN maquinas m ON m.id_maquina = l.id_maquina
        LEFT JOIN turnos t ON t.id_turno = l.id_turno
        LEFT JOIN usuarios u ON u.id_usuario = l.creado_por
        LEFT JOIN lote_wip lpadr ON lpadr.id_lote_wip = l.id_lote_padre
        LEFT JOIN areas_produccion apadr ON apadr.id_area = lpadr.id_area_actual
        WHERE l.id_lote_wip = ?
        LIMIT 1
    ");
    $stmt->execute([$idLoteWip]);
    $lote = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$lote) {
        return null;
    }
    $stmtHijos = $db->prepare("
        SELECT 
            l.id_lote_wip, 
            l.codigo_lote, 
            l.estado_lote,
            l.id_area_actual,
            a.nombre AS area_actual_nombre
        FROM lote_wip l
        LEFT JOIN areas_produccion a ON a.id_area = l.id_area_actual
        WHERE l.id_lote_padre = ?
        ORDER BY l.id_lote_wip
    ");
    $stmtHijos->execute([$idLoteWip]);
    $lote['lotes_hijos'] = $stmtHijos->fetchAll(PDO::FETCH_ASSOC);
    return $lote;
}

function obtenerDocumentoOrigenLote($db, $idLoteWip)
{
    $stmt = $db->prepare("
        SELECT
            d.id_documento,
            d.numero_documento,
            d.tipo_documento,
            s.nombre AS subtipo_documental,
            d.fecha_creacion,
            d.referencia_externa
        FROM lote_wip l
        INNER JOIN documentos_inventario d ON d.id_documento = COALESCE(l.id_documento_salida, l.id_documento_consumo)
        LEFT JOIN inv_doc_subtipos s ON s.id_subtipo = d.id_doc_subtipo
        WHERE l.id_lote_wip = ?
        LIMIT 1
    ");
    $stmt->execute([$idLoteWip]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function obtenerMovimientosHistorialLote($db, $idLoteWip)
{
    $stmt = $db->prepare("
        SELECT
            mw.fecha,
            mw.tipo_movimiento,
            mw.id_lote_relacionado,
            lr.codigo_lote AS lote_relacionado_codigo,
            ao.nombre AS area_origen_nombre,
            ad.nombre AS area_destino_nombre,
            mw.cantidad_docenas,
            mw.cantidad_unidades,
            mw.usuario,
            u.nombre_completo AS usuario_nombre,
            mw.observaciones,
            mw.referencia_externa
        FROM movimientos_wip mw
        LEFT JOIN lote_wip lr ON lr.id_lote_wip = mw.id_lote_relacionado
        LEFT JOIN areas_produccion ao ON ao.id_area = mw.id_area_origen
        LEFT JOIN areas_produccion ad ON ad.id_area = mw.id_area_destino
        LEFT JOIN usuarios u ON u.id_usuario = mw.usuario
        WHERE mw.id_lote_wip = ?
        ORDER BY mw.fecha ASC, mw.id_movimiento ASC
    ");
    $stmt->execute([$idLoteWip]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerDocumentoSalidaTejidoPorId($db, $idDocumentoSalida)
{
    $stmt = $db->prepare("
        SELECT id_documento, numero_documento, fecha_documento, estado, tipo_consumo
        FROM documentos_inventario
        WHERE id_documento = ?
          AND tipo_documento = 'SALIDA'
          AND tipo_consumo = 'TEJIDO'
        LIMIT 1
    ");
    $stmt->execute([$idDocumentoSalida]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function validarLineaProduccionTejido($db, $linea, $numeroLinea)
{
    $fecha = trim((string) ($linea['fecha'] ?? ''));
    $idTurno = (int) ($linea['id_turno'] ?? 0);
    $idMaquina = (int) ($linea['id_maquina'] ?? 0);
    $idProducto = (int) ($linea['id_producto'] ?? 0);
    $cantidadDocenas = max(0, (int) ($linea['cantidad_docenas'] ?? 0));
    $cantidadUnidades = (int) ($linea['cantidad_unidades'] ?? 0);
    $nombreTejedor = trim((string) ($linea['nombre_tejedor'] ?? ''));

    if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        throw new InvalidArgumentException('La linea ' . $numeroLinea . ' tiene una fecha invalida');
    }

    if ($idTurno <= 0 || !obtenerTurnoPorId($db, $idTurno)) {
        throw new InvalidArgumentException('La linea ' . $numeroLinea . ' tiene un turno invalido');
    }

    $maquina = obtenerMaquinaPorId($db, $idMaquina);
    if ($idMaquina <= 0 || !$maquina) {
        throw new InvalidArgumentException('La linea ' . $numeroLinea . ' tiene una maquina invalida');
    }

    $producto = obtenerProductoActivoPorId($db, $idProducto);
    if ($idProducto <= 0 || !$producto) {
        throw new InvalidArgumentException('La linea ' . $numeroLinea . ' tiene un producto invalido');
    }

    if ($cantidadUnidades < 0 || $cantidadUnidades > 11) {
        throw new InvalidArgumentException('La linea ' . $numeroLinea . ' debe tener unidades entre 0 y 11');
    }

    if (($cantidadDocenas * 12 + $cantidadUnidades) <= 0) {
        throw new InvalidArgumentException('La linea ' . $numeroLinea . ' debe registrar una cantidad mayor a cero');
    }

    $turno = obtenerTurnoPorId($db, $idTurno);

    return [
        'fecha' => $fecha,
        'id_turno' => $idTurno,
        'id_maquina' => $idMaquina,
        'id_producto' => $idProducto,
        'cantidad_docenas' => $cantidadDocenas,
        'cantidad_unidades' => $cantidadUnidades,
        'nombre_tejedor' => $nombreTejedor,
        'maquina_codigo' => $maquina['numero_maquina'],
        'turno_codigo' => $turno['codigo'] ?? ($turno['nombre'] ?? ''),
        'producto_nombre' => $producto['descripcion_completa']
    ];
}

function obtenerTurnoPorId($db, $idTurno)
{
    $stmt = $db->prepare("SELECT id_turno, codigo, nombre, hora_inicio, hora_fin FROM turnos WHERE id_turno = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$idTurno]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function obtenerMaquinaPorId($db, $idMaquina)
{
    $stmt = $db->prepare("SELECT id_maquina, numero_maquina, estado FROM maquinas WHERE id_maquina = ? LIMIT 1");
    $stmt->execute([$idMaquina]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function obtenerProductoActivoPorId($db, $idProducto)
{
    $stmt = $db->prepare("SELECT id_producto, codigo_producto, descripcion_completa FROM productos_tejidos WHERE id_producto = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$idProducto]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function calcularConsumoTeoricoYCosto($bom, $cantidadBase)
{
    $factorDocena = $cantidadBase / 12;
    $componentes = [];
    $costoTotal = 0.0;
    $consumoTotalKg = 0.0;

    foreach ($bom['detalles'] as $detalle) {
        $gramosBase = (float) $detalle['gramos_por_docena'] * $factorDocena;
        $mermaTotalPct = (float) $bom['merma_pct'] + (float) $detalle['merma_pct'];
        $gramosConMerma = $gramosBase * (1 + ($mermaTotalPct / 100));
        $consumoKg = round($gramosConMerma / 1000, 4);
        $cpp = obtenerCppComponente($detalle);
        $costoComponente = round($consumoKg * $cpp, 4);

        $componentes[] = [
            'id_inventario' => (int) $detalle['id_inventario'],
            'nombre' => $detalle['nombre'],
            'codigo' => $detalle['codigo'],
            'consumo_kg' => $consumoKg,
            'cpp' => $cpp,
            'costo' => $costoComponente
        ];

        $consumoTotalKg += $consumoKg;
        $costoTotal += $costoComponente;
    }

    return [
        'componentes' => $componentes,
        'consumo_total_kg' => round($consumoTotalKg, 4),
        'costo_total' => round($costoTotal, 4),
        'costo_unitario' => $cantidadBase > 0 ? round($costoTotal / $cantidadBase, 6) : 0.0
    ];
}

function obtenerCppComponente($detalleBom)
{
    if (isset($detalleBom['costo_promedio']) && (float) $detalleBom['costo_promedio'] > 0) {
        return (float) $detalleBom['costo_promedio'];
    }

    if (isset($detalleBom['costo_unitario']) && (float) $detalleBom['costo_unitario'] > 0) {
        return (float) $detalleBom['costo_unitario'];
    }

    return 0.0;
}

function insertarLoteProduccionTejido($db, $datos)
{
    $intentos = 0;
    $maxIntentos = 10;

    while ($intentos < $maxIntentos) {
        $codigoLote = generarCodigoLoteProduccionTejido($db, $datos['id_maquina'], $datos['fecha_produccion'], $intentos);

        try {
            $stmt = $db->prepare("
                INSERT INTO lote_wip (
                    codigo_lote, id_producto, id_maquina, id_turno, id_linea_produccion,
                    cantidad_docenas, cantidad_unidades, cantidad_base_unidades,
                    id_area_actual, estado_lote, costo_mp_acumulado, costo_unitario_promedio,
                    id_documento_consumo, id_documento_salida, referencia_externa, fecha_inicio, creado_por
                ) VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, 'ACTIVO', ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $codigoLote,
                $datos['id_producto'],
                $datos['id_maquina'],
                $datos['id_turno'],
                $datos['cantidad_docenas'],
                $datos['cantidad_unidades'],
                $datos['cantidad_base_unidades'],
                $datos['id_area_actual'],
                $datos['costo_mp_acumulado'],
                $datos['costo_unitario_promedio'],
                $datos['id_documento_salida'],
                $datos['id_documento_salida'],
                $codigoLote,
                $datos['fecha_produccion'] . ' 00:00:00',
                $_SESSION['user_id'] ?? null
            ]);

            return [
                'id_lote_wip' => (int) $db->lastInsertId(),
                'codigo_lote' => $codigoLote
            ];
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {
                $intentos++;
                continue;
            }

            throw $e;
        }
    }

    throw new RuntimeException('No se pudo generar un codigo OP-TEJ unico para la maquina seleccionada');
}

function generarCodigoLoteProduccionTejido($db, $idMaquina, $fechaProduccion, $offset = 0)
{
    $fecha = new DateTime($fechaProduccion);
    $periodo = $fecha->format('Ym');
    $maquina = 'M' . str_pad((string) $idMaquina, 2, '0', STR_PAD_LEFT);
    $prefijo = 'OP-TEJ-' . $periodo . '-' . $maquina . '-';

    $stmt = $db->prepare("
        SELECT codigo_lote
        FROM lote_wip
        WHERE codigo_lote LIKE ?
        ORDER BY codigo_lote DESC
        LIMIT 1
    ");
    $stmt->execute([$prefijo . '%']);
    $ultimoCodigo = $stmt->fetchColumn();

    $siguiente = 1;
    if ($ultimoCodigo) {
        $siguiente = (int) substr((string) $ultimoCodigo, -3) + 1;
    }

    $siguiente += (int) $offset;

    return $prefijo . str_pad((string) $siguiente, 3, '0', STR_PAD_LEFT);
}

function obtenerSalidaDocumentoKg($db, $idDocumentoSalida)
{
    $stmt = $db->prepare("
        SELECT
            i.nombre,
            dd.cantidad,
            um.codigo AS unidad_codigo,
            um.abreviatura AS unidad_abreviatura
        FROM documentos_inventario_detalle dd
        INNER JOIN inventarios i ON i.id_inventario = dd.id_inventario
        LEFT JOIN unidades_medida um ON um.id_unidad = dd.id_unidad
        WHERE dd.id_documento = ?
    ");
    $stmt->execute([$idDocumentoSalida]);

    $salida = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $kg = convertirCantidadAKilos((float) $fila['cantidad'], $fila['unidad_codigo'], $fila['unidad_abreviatura']);
        if (!isset($salida[$fila['nombre']])) {
            $salida[$fila['nombre']] = 0.0;
        }
        $salida[$fila['nombre']] += $kg;
    }

    return array_map(function ($valor) {
        return round($valor, 4);
    }, $salida);
}

function convertirCantidadAKilos($cantidad, $unidadCodigo, $unidadAbreviatura)
{
    $unidadCodigo = strtoupper((string) $unidadCodigo);
    $unidadAbreviatura = strtolower((string) $unidadAbreviatura);

    if ($unidadCodigo === 'KG' || $unidadAbreviatura === 'kg') {
        return $cantidad;
    }

    if ($unidadCodigo === 'GR' || $unidadAbreviatura === 'g') {
        return $cantidad / 1000;
    }

    return $cantidad;
}

function construirAnalisisDiferencia($salidaDocumento, $consumoTeoricoTotal)
{
    $nombres = array_unique(array_merge(array_keys($salidaDocumento), array_keys($consumoTeoricoTotal)));
    sort($nombres);
    $analisis = [];

    foreach ($nombres as $nombre) {
        $salidaKg = (float) ($salidaDocumento[$nombre] ?? 0);
        $teoricoKg = (float) ($consumoTeoricoTotal[$nombre] ?? 0);
        $diferenciaKg = round($salidaKg - $teoricoKg, 4);
        $porcentaje = $salidaKg > 0 ? round(($diferenciaKg / $salidaKg) * 100, 2) : 0.0;

        $analisis[$nombre] = [
            'salida_kg' => round($salidaKg, 4),
            'teorico_kg' => round($teoricoKg, 4),
            'diferencia_kg' => $diferenciaKg,
            'porcentaje' => $porcentaje,
            'concepto' => 'Residuo/desperdicio en tejido'
        ];
    }

    return $analisis;
}

function formatearCantidadBase($cantidadBase)
{
    $cantidadBase = max(0, (int) $cantidadBase);
    $docenas = (int) floor($cantidadBase / 12);
    $unidades = $cantidadBase % 12;
    return $docenas . '|' . str_pad((string) $unidades, 2, '0', STR_PAD_LEFT);
}

function generarCodigoLoteDerivado($db, $codigoPadre)
{
    $base = substr($codigoPadre, 0, 24);
    $stmt = $db->prepare("SELECT COUNT(*) FROM lote_wip WHERE codigo_lote LIKE ?");
    $stmt->execute([$base . '-D%']);
    $correlativo = (int) $stmt->fetchColumn() + 1;
    return $base . '-D' . str_pad((string) $correlativo, 2, '0', STR_PAD_LEFT);
}

function generarReferenciaDerivada($db, $referenciaPadre)
{
    $base = substr($referenciaPadre !== '' ? $referenciaPadre : 'WIP-SPLIT', 0, 44);
    $stmt = $db->prepare("SELECT COUNT(*) FROM lote_wip WHERE referencia_externa LIKE ?");
    $stmt->execute([$base . '-P%']);
    $correlativo = (int) $stmt->fetchColumn() + 1;
    return $base . '-P' . str_pad((string) $correlativo, 2, '0', STR_PAD_LEFT);
}

function normalizarCantidades($docenas, $unidades)
{
    $totalBaseUnidades = max(0, (int) $docenas) * 12 + max(0, (int) $unidades);
    return [
        'cantidad_docenas' => (int) floor($totalBaseUnidades / 12),
        'cantidad_unidades' => (int) ($totalBaseUnidades % 12),
        'cantidad_base_unidades' => $totalBaseUnidades
    ];
}

function convertirGramosAUnidadInventario($gramos, $unidadCodigo, $unidadAbreviatura)
{
    $unidadCodigo = strtoupper((string) $unidadCodigo);
    $unidadAbreviatura = strtolower((string) $unidadAbreviatura);

    if ($unidadCodigo === 'KG' || $unidadAbreviatura === 'kg') {
        return $gramos / 1000;
    }

    if ($unidadCodigo === 'GR' || $unidadAbreviatura === 'g') {
        return $gramos;
    }

    throw new InvalidArgumentException('Unidad de inventario no soportada para consumo WIP FASE 0');
}

function obtenerAreaTejeduriaId($db)
{
    $stmt = $db->prepare("SELECT id_area FROM areas_produccion WHERE codigo = 'TEJEDURIA' AND activo = 1 LIMIT 1");
    $stmt->execute();
    return $stmt->fetchColumn() ?: null;
}

function obtenerSubtipoConsumoWip($db)
{
    $stmt = $db->prepare("
        SELECT id_subtipo
        FROM inv_doc_subtipos
        WHERE id_tipo = 2
          AND codigo IN ('WIP-C', 'CONSUMO_WIP')
          AND activo = 1
        ORDER BY FIELD(codigo, 'WIP-C', 'CONSUMO_WIP')
        LIMIT 1
    ");
    $stmt->execute();
    return $stmt->fetchColumn() ?: null;
}

function generarNumeroDocumento($db, $tipo, $prefijo)
{
    $anio = date('Y');
    $mes = date('m');

    $stmt = $db->prepare("
        SELECT ultimo_numero
        FROM secuencias_documento
        WHERE tipo_documento = ? AND prefijo = ? AND anio = ? AND mes = ?
        FOR UPDATE
    ");
    $stmt->execute([$tipo, $prefijo, $anio, $mes]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $siguiente = (int) $row['ultimo_numero'] + 1;
        $stmtUp = $db->prepare("
            UPDATE secuencias_documento
            SET ultimo_numero = ?
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

    return $prefijo . '-' . $anio . $mes . '-' . str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
}

function generarCodigoMovimiento($db)
{
    $fecha = date('Ymd');
    $stmt = $db->prepare("
        SELECT ultimo_numero
        FROM secuencias_documento
        WHERE tipo_documento = 'MOVIMIENTO' AND prefijo = 'MOV' AND anio = ? AND mes = ?
        FOR UPDATE
    ");
    $stmt->execute([date('Y'), date('m')]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $siguiente = (int) $row['ultimo_numero'] + 1;
        $stmtUp = $db->prepare("
            UPDATE secuencias_documento
            SET ultimo_numero = ?
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

    return 'MOV-' . $fecha . '-' . str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
}

function generarCodigoLoteWip($db)
{
    $anio = date('Y');
    $mes = date('m');
    $prefijo = 'OP-TEJ';

    $stmt = $db->prepare("
        SELECT ultimo_numero
        FROM secuencias_documento
        WHERE tipo_documento = 'WIP' AND prefijo = ? AND anio = ? AND mes = ?
        FOR UPDATE
    ");
    $stmt->execute([$prefijo, $anio, $mes]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $siguiente = (int) $row['ultimo_numero'] + 1;
        $stmtUp = $db->prepare("
            UPDATE secuencias_documento
            SET ultimo_numero = ?
            WHERE tipo_documento = 'WIP' AND prefijo = ? AND anio = ? AND mes = ?
        ");
        $stmtUp->execute([$siguiente, $prefijo, $anio, $mes]);
    } else {
        $siguiente = 1;
        $stmtIn = $db->prepare("
            INSERT INTO secuencias_documento (tipo_documento, prefijo, anio, mes, ultimo_numero)
            VALUES ('WIP', ?, ?, ?, 1)
        ");
        $stmtIn->execute([$prefijo, $anio, $mes]);
    }

    return $prefijo . '-' . $anio . $mes . '-' . str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
}
