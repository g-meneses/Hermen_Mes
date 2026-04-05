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
                if (($movimiento['tipo_movimiento'] ?? '') === 'TRANSFERENCIA') {
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
            l.id_lote_padre,
            l.codigo_lote,
            l.referencia_externa,
            l.estado_lote,
            l.id_area_actual,
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
                id_lote_wip, id_lote_relacionado, tipo_movimiento, cantidad_docenas, cantidad_unidades,
                id_area_origen, id_area_destino, id_documento_inventario,
                referencia_externa, fecha, usuario, observaciones
            ) VALUES (?, NULL, 'CREACION', ?, ?, NULL, ?, ?, ?, NOW(), ?, ?)
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
            ) VALUES (?, NULL, 'TRANSFERENCIA', ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
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
            !empty($lote['id_documento_consumo']) ? (int) $lote['id_documento_consumo'] : null,
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
                id_documento_consumo, referencia_externa, fecha_inicio, creado_por
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?, ?, ?, ?, NOW(), ?)
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
            $lote['id_documento_consumo'],
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
            'TRANSFERENCIA',
            $cantidades['cantidad_docenas'],
            $cantidades['cantidad_unidades'],
            $idAreaOrigen,
            $idAreaDestino,
            !empty($lote['id_documento_consumo']) ? (int) $lote['id_documento_consumo'] : null,
            $lote['referencia_externa'],
            $_SESSION['user_id'] ?? null,
            $observaciones !== '' ? $observaciones : 'Split parcial hacia lote derivado ' . $codigoLoteDerivado
        ]);

        $stmtMov->execute([
            $idLoteDerivado,
            $idLoteWip,
            'CREACION',
            $cantidades['cantidad_docenas'],
            $cantidades['cantidad_unidades'],
            $idAreaOrigen,
            $idAreaDestino,
            !empty($lote['id_documento_consumo']) ? (int) $lote['id_documento_consumo'] : null,
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
            l.id_documento_consumo,
            l.referencia_externa,
            l.fecha_inicio,
            l.fecha_actualizacion,
            l.creado_por,
            u.nombre_completo AS creado_por_nombre,
            lpadr.codigo_lote AS codigo_lote_padre,
            lpadr.id_area_actual AS id_area_padre,
            apadr.nombre AS area_padre_nombre
        FROM lote_wip l
        INNER JOIN productos_tejidos p ON p.id_producto = l.id_producto
        LEFT JOIN lineas_produccion_erp lp ON lp.id_linea_produccion = l.id_linea_produccion
        LEFT JOIN areas_produccion a ON a.id_area = l.id_area_actual
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
        INNER JOIN documentos_inventario d ON d.id_documento = l.id_documento_consumo
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
