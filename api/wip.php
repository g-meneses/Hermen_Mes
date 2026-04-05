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
                p.codigo_producto,
                p.descripcion_completa,
                a.nombre AS area_actual_nombre,
                lp.nombre AS linea_produccion_nombre
            FROM lote_wip l
            INNER JOIN productos_tejidos p ON p.id_producto = l.id_producto
            LEFT JOIN areas_produccion a ON a.id_area = l.id_area_actual
            LEFT JOIN lineas_produccion_erp lp ON lp.id_linea_produccion = l.id_linea_produccion
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
            l.codigo_lote,
            l.referencia_externa,
            l.estado_lote,
            l.cantidad_docenas,
            l.cantidad_unidades,
            l.cantidad_base_unidades,
            l.costo_mp_acumulado,
            l.costo_unitario_promedio,
            l.fecha_inicio,
            p.codigo_producto,
            p.descripcion_completa,
            a.nombre AS area_actual_nombre
        FROM lote_wip l
        INNER JOIN productos_tejidos p ON p.id_producto = l.id_producto
        LEFT JOIN areas_produccion a ON a.id_area = l.id_area_actual
        ORDER BY l.fecha_inicio DESC, l.id_lote_wip DESC
        LIMIT 100
    ");

    jsonResponse(['success' => true, 'lotes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function manejarPost($db)
{
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? 'crear_lote';

    if ($action !== 'crear_lote') {
        jsonResponse(['success' => false, 'message' => 'Acción no válida'], 400);
    }

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
                codigo_lote, id_producto, id_linea_produccion,
                cantidad_docenas, cantidad_unidades, cantidad_base_unidades,
                id_area_actual, estado_lote,
                costo_mp_acumulado, costo_unitario_promedio,
                id_documento_consumo, referencia_externa,
                fecha_inicio, creado_por
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?, ?, ?, ?, NOW(), ?)
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
            $referenciaExterna,
            $_SESSION['user_id'] ?? null
        ]);

        $idLoteWip = (int) $db->lastInsertId();

        $stmtMovWip = $db->prepare("
            INSERT INTO movimientos_wip (
                id_lote_wip, tipo_movimiento, cantidad_docenas, cantidad_unidades,
                id_area_origen, id_area_destino, id_documento_inventario,
                referencia_externa, fecha, usuario, observaciones
            ) VALUES (?, 'CREACION', ?, ?, NULL, ?, ?, ?, NOW(), ?, ?)
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
            'id_documento_consumo' => $idDocumento,
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
