<?php
ob_start();
ini_set('display_errors', 0); // Previene que warnings ensucien el JSON
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
} catch (Throwable $e) {
    error_log('Error FATAL en wip.php: ' . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    jsonResponse(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()], 500);
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

    if ($action === 'get_ultimo_registro_tejido') {
        obtenerUltimoRegistroTejido($db);
        return;
    }

    if ($action === 'get_incidencia_detalle') {
        getIncidenciasDetalle($db);
        return;
    }

    if ($action === 'get_historial_produccion_tejido') {
        getHistorialProduccionTejido($db);
        return;
    }

    if ($action === 'get_detalle_historial_produccion_tejido') {
        getDetalleHistorialProduccionTejido($db);
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

    if ($action === 'get_incidencias_consumo') {
        getIncidenciasConsumo($db);
        return;
    }

    if ($action === 'get_estado_auditoria_hilos') {
        getEstadoAuditoriaHilos($db);
        return;
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

    if ($action === 'registrar_auditoria_hilos') {
        registrarAuditoriaHilos($db, $data);
        return;
    }

    if ($action === 'resolver_incidencia') {
        resolverIncidencia($db, $data);
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

function obtenerUltimoRegistroTejido($db)
{
    $stmt = $db->query("
        SELECT id_planilla, detalles_json, fecha_registro
        FROM planillas_tejido
        WHERE activo = 1
        ORDER BY id_planilla DESC
        LIMIT 1
    ");

    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registro) {
        jsonResponse([
            'success' => true,
            'ultimo_registro' => null
        ]);
        return;
    }

    $detalles = json_decode($registro['detalles_json'], true);

    jsonResponse([
        'success' => true,
        'ultimo_registro' => [
            'id_planilla' => (int) $registro['id_planilla'],
            'fecha_registro' => $registro['fecha_registro'],
            'id_tecnico' => $detalles['id_tecnico'] ?? null,
            'id_tejedor' => $detalles['id_tejedor'] ?? null,
            'id_asistente' => $detalles['id_asistente'] ?? null,
            'lineas_produccion' => $detalles['lineas_produccion'] ?? []
        ]
    ]);
}

function registrarProduccionTejido($db, $data)
{
    $observaciones = trim((string) ($data['observaciones'] ?? ''));
    $lineas = $data['lineas_produccion'] ?? [];
    
    // Nuevos campos de personal (Selectores)
    $idTecnico = (int) ($data['id_tecnico'] ?? 0);
    $idTejedor = (int) ($data['id_tejedor'] ?? 0);
    $idAsistente = (int) ($data['id_asistente'] ?? 0);
    $desperdicio = $data['desperdicio'] ?? []; // [{familia, kg, pct}]

    if (!is_array($lineas) || empty($lineas)) {
        jsonResponse(['success' => false, 'message' => 'Debe registrar al menos una línea de producción'], 400);
    }

    $idAreaTejeduria = obtenerAreaTejeduriaId($db);
    if (!$idAreaTejeduria) {
        jsonResponse(['success' => false, 'message' => 'No se encontró el Área de TEJEDURÍA'], 500);
    }

    $db->beginTransaction();

    try {
        $lotesCreados = [];
        $consumoTeoricoTotal = [];
        $costoMpTotal = 0.0;
        $totalBaseUnidadesGlobal = 0;
        $fechasProduccion = [];
        $pendientesCount = 0;
        $consumoRealTotal = []; // Agregado para análisis

        foreach ($lineas as $indice => $linea) {
            // Conversión automática: 12 unidades = 1 docena adicional
            $docIn = (int) ($linea['cantidad_docenas'] ?? 0);
            $uniIn = (int) ($linea['cantidad_unidades'] ?? 0);
            $totalBaseFila = ($docIn * 12) + $uniIn;
            
            $linea['cantidad_docenas'] = (int) floor($totalBaseFila / 12);
            $linea['cantidad_unidades'] = (int) ($totalBaseFila % 12);

            $lineaNormalizada = validarLineaProduccionTejido($db, $linea, $indice + 1);
            $fechasProduccion[] = $lineaNormalizada['fecha'];

            $bom = obtenerBomActivo($db, $lineaNormalizada['id_producto']);
            if (!$bom || empty($bom['detalles'])) {
                throw new InvalidArgumentException('El producto de la línea ' . ($indice + 1) . ' no tiene BOM activo');
            }

            $cantidadBase = $lineaNormalizada['cantidad_docenas'] * 12 + $lineaNormalizada['cantidad_unidades'];
            if ($cantidadBase <= 0) {
                throw new InvalidArgumentException('La línea ' . ($indice + 1) . ' debe registrar una cantidad mayor a cero');
            }

            // A. Calcular requerimientos para FIFO
            $factorDocena = $cantidadBase / 12;
            $reqsFIFO = [];
            foreach ($bom['detalles'] as $detalle) {
                $gramosBase = (float) $detalle['gramos_por_docena'] * $factorDocena;
                $mermaTotalPct = (float) $bom['merma_pct'] + (float) $detalle['merma_pct'];
                $gramosConMerma = $gramosBase * (1 + ($mermaTotalPct / 100));
                $consumoKg = round($gramosConMerma / 1000, 4);

                $reqsFIFO[] = [
                    'id_inventario' => (int)$detalle['id_inventario'],
                    'cantidad_requerida' => $consumoKg,
                    'nombre' => $detalle['nombre']
                ];

                if (!isset($consumoTeoricoTotal[$detalle['nombre']])) {
                    $consumoTeoricoTotal[$detalle['nombre']] = 0.0;
                }
                $consumoTeoricoTotal[$detalle['nombre']] += $consumoKg;
            }

            // B. Insertar Lote (id_documento_salida ahora es NULL/Opcional)
            $loteInsertado = insertarLoteProduccionTejido($db, [
                'id_producto' => $lineaNormalizada['id_producto'],
                'id_maquina' => $lineaNormalizada['id_maquina'],
                'id_turno' => $lineaNormalizada['id_turno'],
                'id_area_actual' => $idAreaTejeduria,
                'id_documento_salida' => null, // Multi-lote manejado por consumos_wip_detalle
                'cantidad_docenas' => $lineaNormalizada['cantidad_docenas'],
                'cantidad_unidades' => $lineaNormalizada['cantidad_unidades'],
                'cantidad_base_unidades' => $cantidadBase,
                'costo_mp_acumulado' => 0.0, // Se actualizará tras FIFO
                'costo_unitario_promedio' => 0.0,
                'fecha_produccion' => $lineaNormalizada['fecha']
            ]);

            // C. EJECUTAR MOTOR FIFO
            $costoRealAsignado = consumirMateriaPrimaFIFO($db, $loteInsertado['id_lote_wip'], $reqsFIFO);

            // D. Actualizar Lote con el costo real obtenido de la materia prima consumida
            $stmtUpdLote = $db->prepare("
                UPDATE lote_wip 
                SET costo_mp_acumulado = ?, 
                    costo_unitario_promedio = ?
                WHERE id_lote_wip = ?
            ");
            $costoUniProm = $cantidadBase > 0 ? round($costoRealAsignado / $cantidadBase, 6) : 0;
            $stmtUpdLote->execute([$costoRealAsignado, $costoUniProm, $loteInsertado['id_lote_wip']]);

            // E. Registrar Movimiento WIP
            $stmtMov = $db->prepare("
                INSERT INTO movimientos_wip (
                    id_lote_wip, tipo_movimiento, cantidad_docenas, cantidad_unidades,
                    id_area_destino, referencia_externa, fecha, usuario, observaciones
                ) VALUES (?, 'CREACION_EN_TEJIDO', ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtMov->execute([
                $loteInsertado['id_lote_wip'],
                $lineaNormalizada['cantidad_docenas'],
                $lineaNormalizada['cantidad_unidades'],
                $idAreaTejeduria,
                $loteInsertado['codigo_lote'],
                $lineaNormalizada['fecha'] . ' 00:00:00',
                $_SESSION['user_id'] ?? null,
                'Producción registrada (Consumo FIFO Automático)'
            ]);

            $lotesCreados[] = [
                'id_lote_wip' => $loteInsertado['id_lote_wip'],
                'codigo_lote' => $loteInsertado['codigo_lote'],
                'producto' => $lineaNormalizada['producto_nombre'],
                'cantidad' => $lineaNormalizada['cantidad_docenas'] . '|' . str_pad((string) $lineaNormalizada['cantidad_unidades'], 2, '0', STR_PAD_LEFT),
                'costo_mp' => round($costoRealAsignado, 4)
            ];

            $costoMpTotal += $costoRealAsignado;
            $totalBaseUnidadesGlobal += $cantidadBase;

            // F. Acumular consumo real por componente para el análisis final
            $stmtReal = $db->prepare("
                SELECT i.nombre, SUM(cd.cantidad_consumida) as suma_real
                FROM consumos_wip_detalle cd
                JOIN inventarios i ON i.id_inventario = cd.id_inventario
                WHERE cd.id_lote_wip = ?
                GROUP BY i.id_inventario
            ");
            $stmtReal->execute([$loteInsertado['id_lote_wip']]);
            while ($cReal = $stmtReal->fetch(PDO::FETCH_ASSOC)) {
                $nombre = $cReal['nombre'];
                if (!isset($consumoRealTotal[$nombre])) $consumoRealTotal[$nombre] = 0.0;
                $consumoRealTotal[$nombre] += (float)$cReal['suma_real'];
            }
        }

        sort($fechasProduccion);

        $stmtPlanilla = $db->prepare("
            INSERT INTO planillas_tejido (
                fecha_inicio, fecha_fin, detalles_json, registrado_por, observaciones, activo
            ) VALUES (?, ?, ?, ?, ?, 1)
        ");
        
        $jsonPayload = [
            'id_tecnico' => $idTecnico,
            'id_tejedor' => $idTejedor,
            'id_asistente' => $idAsistente,
            'lineas_produccion' => $lineas,
            'lotes_creados' => $lotesCreados,
            'desperdicio' => $desperdicio
        ];

        $stmtPlanilla->execute([
            $fechasProduccion[0] ?? null,
            $fechasProduccion[count($fechasProduccion) - 1] ?? null,
            json_encode($jsonPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $_SESSION['user_id'] ?? null,
            $observaciones
        ]);

        $db->commit();

        // Vincular incidencias a la planilla recién creada (Trazabilidad Fase 4)
        try {
            $idPlanilla = $db->lastInsertId();
            if ($idPlanilla > 0 && !empty($lotesCreados)) {
                $idsLotes = array_column($lotesCreados, 'id_lote_wip');
                $placeholders = implode(',', array_fill(0, count($idsLotes), '?'));
                $stmtLink = $db->prepare("UPDATE consumos_wip_pendientes SET id_planilla = ? WHERE id_lote_wip IN ($placeholders)");
                $stmtLink->execute(array_merge([$idPlanilla], $idsLotes));
            }
        } catch (Throwable $e) {
            error_log("Error vinculando id_planilla a incidencias: " . $e->getMessage());
            // No revertimos transacción por esto, es secundario pero reportamos
        }

        // Contar incidencias totales de esta operación (Fase 4 FIX)
        $idPlanilla = $db->lastInsertId();
        $pendientesFinal = 0;
        if ($idPlanilla > 0) {
            $stmtCount = $db->prepare("SELECT COUNT(*) FROM consumos_wip_pendientes WHERE id_planilla = ?");
            $stmtCount->execute([$idPlanilla]);
            $pendientesFinal = (int)$stmtCount->fetchColumn();
        }

        // Generar Análisis Comparativo Consolidado
        $tablaAnalisis = [];
        foreach ($consumoTeoricoTotal as $componente => $teorico) {
            $real = $consumoRealTotal[$componente] ?? 0.0;
            $dif = round($real - $teorico, 4);
            $pct = $teorico > 0 ? round(($real / $teorico) * 100, 2) : 100;
            $pend = round($teorico - $real, 4);
            if ($pend < 0) $pend = 0;

            $tablaAnalisis[] = [
                'componente' => $componente,
                'teorico_kg' => round($teorico, 4),
                'real_kg' => round($real, 4),
                'pendiente_kg' => $pend,
                'diferencia_kg' => $dif,
                'porcentaje' => $pct
            ];
        }

        jsonResponse([
            'success' => true,
            'message' => 'Producción de tejido registrada exitosamente (' . (count($lotesCreados)) . ' lotes)',
            'id_planilla' => $idPlanilla,
            'lotes_creados' => $lotesCreados,
            'incidencias_generadas' => $pendientesFinal,
            'analisis_consumo' => $tablaAnalisis,
            'resumen' => [
                'total_base' => $totalBaseUnidadesGlobal,
                'costo_total' => round($costoMpTotal, 4),
                'fecha_desde' => $fechasProduccion[0] ?? null,
                'fecha_hasta' => end($fechasProduccion) ?: null
            ]
        ]);
    } catch (Exception $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
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
        SELECT id_documento, numero_documento, fecha_documento, estado, tipo_consumo,
               modo_asignacion, referencia_entidad_tipo, referencia_entidad_id
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
    $idDocumento = (int) ($datos['id_documento_salida'] ?? 0);

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
                $idDocumento > 0 ? $idDocumento : null,
                $idDocumento > 0 ? $idDocumento : null,
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

/**
 * Motor de resolución inteligente de documentos SAL-TEJ
 * Aplica jerarquía: Manual -> Exclusivo -> Genérico
 */
function resolverMejorDocumentoSalidaTejido($db, $idManual, $fechaProduccion, $entidadTipo = null, $entidadId = null)
{
    // PRIO 1: Selección Manual
    if ($idManual > 0) {
        $doc = obtenerDocumentoSalidaTejidoPorId($db, $idManual);
        if (!$doc) {
            throw new InvalidArgumentException('El documento seleccionado no existe o no es de tipo TEJIDO.');
        }

        if ($doc['estado'] !== 'CONFIRMADO') {
            throw new InvalidArgumentException('El documento seleccionado no ha sido CONFIRMADO en Inventarios. No puede utilizarse para producción.');
        }

        // Si es exclusivo, validar compatibilidad
        if ($doc['modo_asignacion'] === 'EXCLUSIVO') {
            if (!$entidadTipo || !$entidadId || 
                $doc['referencia_entidad_tipo'] !== $entidadTipo || 
                (int)$doc['referencia_entidad_id'] !== (int)$entidadId) {
                throw new InvalidArgumentException('La salida seleccionada está reservada para una orden o pedido específico y no coincide con este registro.');
            }
        }
        return (int) $idManual;
    }

    // PRIO 2: Resolución Automática Exclusiva
    if ($entidadTipo && $entidadId) {
        $stmt = $db->prepare("
            SELECT id_documento 
            FROM documentos_inventario 
            WHERE tipo_documento = 'SALIDA' 
              AND tipo_consumo = 'TEJIDO' 
              AND estado = 'CONFIRMADO' 
              AND modo_asignacion = 'EXCLUSIVO'
              AND referencia_entidad_tipo = ?
              AND referencia_entidad_id = ?
            ORDER BY fecha_documento DESC, id_documento DESC
            LIMIT 2
        ");
        $stmt->execute([$entidadTipo, $entidadId]);
        $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($candidatos) === 1) {
            return (int) $candidatos[0]['id_documento'];
        }
        if (count($candidatos) > 1) {
            throw new InvalidArgumentException('Existen múltiples salidas EXCLUSIVAS válidas para este registro. Seleccione manualmente la correcta.');
        }
        
        // Si no se encuentra exclusiva y se esperaba una, cortamos el flujo (no permitimos genérico para pedidos especiales)
        throw new InvalidArgumentException('No se encontró una salida EXCLUSIVA para ' . $entidadTipo . ' #' . $entidadId . '. Registre primero la salida en Inventarios.');
    }

    // PRIO 3: Resolución Automática Genérica (Bolsa WIP)
    // 1. Misma fecha
    $candidatos = buscarCandidatosGenericosTejido($db, $fechaProduccion, $fechaProduccion);
    
    if (empty($candidatos)) {
        // 2. Ventana de 3 días hacia atrás
        $fechaInicio = date('Y-m-d', strtotime($fechaProduccion . ' -3 days'));
        $candidatos = buscarCandidatosGenericosTejido($db, $fechaInicio, $fechaProduccion);
    }

    if (count($candidatos) === 1) {
        return (int) $candidatos[0]['id_documento'];
    }

    if (count($candidatos) > 1) {
        throw new InvalidArgumentException('Existen múltiples salidas genéricas válidas para este periodo. Seleccione manualmente la correcta.');
    }

    // FALLO ESTRICTO
    throw new InvalidArgumentException('No existe una salida a producción TEJIDO disponible para vincular este registro. Realice primero la salida de almacén Genérica.');
}

function buscarCandidatosGenericosTejido($db, $fechaDesde, $fechaHasta)
{
    $stmt = $db->prepare("
        SELECT id_documento 
        FROM documentos_inventario 
        WHERE tipo_documento = 'SALIDA' 
          AND tipo_consumo = 'TEJIDO' 
          AND estado = 'CONFIRMADO' 
          AND modo_asignacion = 'GENERICO'
          AND fecha_documento BETWEEN ? AND ?
        ORDER BY fecha_documento DESC, id_documento DESC
    ");
    $stmt->execute([$fechaDesde, $fechaHasta]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

/**
 * MOTOR FIFO: Consume materia prima automáticamente de documentos SAL-TEJ
 * Si no alcanza el stock, registra el faltante como PENDIENTE.
 */
function consumirMateriaPrimaFIFO($db, $idLoteWip, $consumosRequeridos)
{
    $costoTotalAcumulado = 0.0;

    foreach ($consumosRequeridos as $req) {
        $idInventario = (int) $req['id_inventario'];
        $cantidadPendiente = (float) $req['cantidad_requerida'];
        $cantidadConsumidaEfectiva = 0.0;

        // 1. Buscar documentos con saldo disponible para este item (FIFO)
        $stmtDocs = $db->prepare("
            SELECT dd.id_detalle, dd.id_documento, dd.id_inventario, dd.saldo_disponible, dd.costo_unitario
            FROM documentos_inventario_detalle dd
            JOIN documentos_inventario d ON d.id_documento = dd.id_documento
            WHERE dd.id_inventario = ?
              AND d.tipo_documento = 'SALIDA'
              AND (d.tipo_consumo = 'TEJIDO' OR d.numero_documento LIKE 'SAL-TEJ%')
              AND d.estado = 'CONFIRMADO'
              AND dd.saldo_disponible > 0
            ORDER BY d.fecha_documento ASC, d.id_documento ASC
            FOR UPDATE
        ");
        $stmtDocs->execute([$idInventario]);
        $candidatos = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

        foreach ($candidatos as $docDetalle) {
            if ($cantidadPendiente <= 0) break;

            $disponible = (float) $docDetalle['saldo_disponible'];
            $aConsumir = min($disponible, $cantidadPendiente);

            // A. Registrar consumo granular
            registrarConsumoGranular($db, $idLoteWip, $docDetalle, $aConsumir);

            // B. Actualizar saldo en el documento
            $nuevoSaldo = round($disponible - $aConsumir, 4);
            $stmtUpd = $db->prepare("UPDATE documentos_inventario_detalle SET saldo_disponible = ? WHERE id_detalle = ?");
            $stmtUpd->execute([$nuevoSaldo, $docDetalle['id_detalle']]);

            // C. Acumular costos
            $costoTotalAcumulado += round($aConsumir * (float)$docDetalle['costo_unitario'], 4);

            $cantidadConsumidaEfectiva += $aConsumir;
            $cantidadPendiente = round($cantidadPendiente - $aConsumir, 4);
        }

        // 2. Si todavía falta cantidad (Quiebre de Stock Teórico)
        if ($cantidadPendiente > 0) {
            registrarConsumoPendiente(
                $db, 
                $idLoteWip, 
                $idInventario, 
                (float)$req['cantidad_requerida'],
                $cantidadConsumidaEfectiva,
                $cantidadPendiente
            );
        }
    }

    return $costoTotalAcumulado;
}

/**
 * Registra el vínculo exacto entre el lote WIP y el documento de origen
 */
function registrarConsumoGranular($db, $idLoteWip, $docDetalle, $cantidad)
{
    $stmt = $db->prepare("
        INSERT INTO consumos_wip_detalle (
            id_lote_wip, id_documento_inventario, id_documento_detalle, 
            id_inventario, cantidad_consumida, costo_unitario_origen, usuario_registro
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $idLoteWip,
        $docDetalle['id_documento'],
        $docDetalle['id_detalle'],
        $docDetalle['id_inventario'],
        $cantidad,
        $docDetalle['costo_unitario'],
        $_SESSION['user_id'] ?? null
    ]);
}

/**
 * Registra faltantes para seguimiento del supervisor
 */
function registrarConsumoPendiente($db, $idLoteWip, $idInventario, $requerido, $consumido, $pendiente)
{
    $stmt = $db->prepare("
        INSERT INTO consumos_wip_pendientes (
            id_lote_wip, id_inventario, cantidad_requerida,
            cantidad_consumida, cantidad_pendiente, estado, usuario_registro
        ) VALUES (?, ?, ?, ?, ?, 'PENDIENTE', ?)
    ");
    
    $stmt->execute([
        $idLoteWip,
        $idInventario,
        $requerido,
        $consumido,
        $pendiente,
        $_SESSION['user_id'] ?? null
    ]);
}

/**
 * Obtiene el listado de incidencias (faltantes de stock) registradas por el motor FIFO
 */
function getIncidenciasConsumo($db)
{
    $stmt = $db->query("
        SELECT 
            p.id_pendiente as id_incidencia,
            p.fecha_registro,
            p.cantidad_requerida,
            p.cantidad_consumida,
            p.cantidad_pendiente,
            p.estado,
            l.codigo_lote,
            prod.codigo_producto,
            prod.descripcion_completa AS producto_nombre,
            inv.codigo AS item_codigo,
            inv.nombre AS item_nombre
        FROM consumos_wip_pendientes p
        JOIN lote_wip l ON l.id_lote_wip = p.id_lote_wip
        JOIN productos_tejidos prod ON prod.id_producto = l.id_producto
        JOIN inventarios inv ON inv.id_inventario = p.id_inventario
        WHERE p.estado = 'PENDIENTE'
        ORDER BY p.fecha_registro DESC
    ");
    
    jsonResponse([
        'success' => true,
        'incidencias' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

/**
 * Calcula el estado actual de los hilos en planta (Teórico WIP)
 * basándose en los saldos disponibles de los documentos SAL-TEJ
 */
function getEstadoAuditoriaHilos($db)
{
    // Calculamos el stock actual en WIP como la suma de saldos disponibles
    $stmt = $db->query("
        SELECT 
            inv.id_inventario,
            inv.codigo,
            inv.nombre,
            u.abreviatura as unidad,
            SUM(dd.saldo_disponible) as stock_teorico_wip
        FROM documentos_inventario_detalle dd
        JOIN documentos_inventario d ON d.id_documento = dd.id_documento
        JOIN inventarios inv ON inv.id_inventario = dd.id_inventario
        JOIN unidades_medida u ON u.id_unidad = inv.id_unidad
        WHERE d.tipo_documento = 'SALIDA'
          AND (d.tipo_consumo = 'TEJIDO' OR d.numero_documento LIKE 'SAL-TEJ%')
          AND d.estado = 'CONFIRMADO'
          AND dd.saldo_disponible > 0
        GROUP BY inv.id_inventario, inv.codigo, inv.nombre, u.abreviatura
        ORDER BY inv.nombre ASC
    ");
    
    jsonResponse([
        'success' => true,
        'inventario_wip' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

/**
 * Registra una nueva auditoría física de hilos en planta
 */
function registrarAuditoriaHilos($db, $data)
{
    $observaciones = trim((string)($data['observaciones'] ?? ''));
    $detalles = $data['detalles'] ?? [];

    if (empty($detalles)) {
        jsonResponse(['success' => false, 'message' => 'No se enviaron detalles para la auditoría'], 400);
    }

    $db->beginTransaction();
    try {
        $stmtAud = $db->prepare("
            INSERT INTO auditorias_wip_tejido (fecha_auditoria, usuario, observaciones)
            VALUES (NOW(), ?, ?)
        ");
        $stmtAud->execute([$_SESSION['user_id'] ?? null, $observaciones]);
        $idAuditoria = $db->lastInsertId();

        $stmtDet = $db->prepare("
            INSERT INTO auditorias_wip_tejido_detalle (
                id_auditoria, id_inventario, stock_teorico_wip, 
                conteo_fisico, diferencia, estado_ajuste
            ) VALUES (?, ?, ?, ?, ?, 'PENDIENTE')
        ");

        foreach ($detalles as $det) {
            $idInv = (int)$det['id_inventario'];
            $teorico = (float)$det['stock_teorico_wip'];
            $fisico = (float)$det['conteo_fisico'];
            $dif = round($fisico - $teorico, 4);

            $stmtDet->execute([
                $idAuditoria,
                $idInv,
                $teorico,
                $fisico,
                $dif
            ]);
        }

        $db->commit();
        jsonResponse([
            'success' => true, 
            'message' => 'Auditoría de hilos registrada correctamente. ID: ' . $idAuditoria,
            'id_auditoria' => $idAuditoria
        ]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

/**
 * Obtiene el detalle completo de una incidencia incluyendo contexto de producción
 */
function getIncidenciasDetalle($db)
{
    $id = (int)($_GET['id_incidencia'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'ID de incidencia requerido'], 400);
    }

    // 1. Datos base de la incidencia
    $stmt = $db->prepare("
        SELECT 
            p.*,
            l.codigo_lote,
            prod.codigo_producto,
            prod.descripcion_completa AS producto_nombre,
            inv.codigo AS item_codigo,
            inv.nombre AS item_nombre,
            u.abreviatura AS item_unidad,
            m.numero_maquina,
            t.nombre AS turno_nombre,
            us.nombre AS responsable_nombre
        FROM consumos_wip_pendientes p
        JOIN lote_wip l ON l.id_lote_wip = p.id_lote_wip
        JOIN productos_tejidos prod ON prod.id_producto = l.id_producto
        JOIN inventarios inv ON inv.id_inventario = p.id_inventario
        JOIN unidades_medida u ON u.id_unidad = inv.id_unidad
        LEFT JOIN maquinas m ON m.id_maquina = l.id_maquina
        LEFT JOIN turnos t ON t.id_turno = l.id_turno
        LEFT JOIN usuarios us ON us.id_usuario = p.usuario_registro
        WHERE p.id_pendiente = ?
    ");
    $stmt->execute([$id]);
    $incidencia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$incidencia) {
        jsonResponse(['success' => false, 'message' => 'Incidencia no encontrada'], 404);
    }

    // 2. Datos de la planilla asociada (si existe)
    $planilla = null;
    if ($incidencia['id_planilla']) {
        $stmtPlan = $db->prepare("
            SELECT id_planilla, fecha_registro, fecha_inicio, fecha_fin, observaciones, registrado_por
            FROM planillas_tejido
            WHERE id_planilla = ?
        ");
        $stmtPlan->execute([$incidencia['id_planilla']]);
        $planilla = $stmtPlan->fetch(PDO::FETCH_ASSOC);
    }

    // 3. Detalle de lo que SÍ se consumió vía FIFO (trazabilidad de origen)
    $stmtCons = $db->prepare("
        SELECT 
            cd.cantidad_consumida,
            cd.fecha_consumo,
            di.numero_documento,
            di.fecha_documento
        FROM consumos_wip_detalle cd
        JOIN documentos_inventario di ON di.id_documento = cd.id_documento_inventario
        WHERE cd.id_lote_wip = ? AND cd.id_inventario = ?
        ORDER BY cd.fecha_consumo ASC
    ");
    $stmtCons->execute([$incidencia['id_lote_wip'], $incidencia['id_inventario']]);
    $detallesConsumo = $stmtCons->fetchAll(PDO::FETCH_ASSOC);

    // 4. Datos de resolución (si existen)
    $resolucion = null;
    if ($incidencia['id_usuario_resolucion']) {
        $stmtUser = $db->prepare("SELECT nombre FROM usuarios WHERE id_usuario = ?");
        $stmtUser->execute([$incidencia['id_usuario_resolucion']]);
        $resolucion = [
            'usuario_nombre' => $stmtUser->fetchColumn(),
            'fecha' => $incidencia['fecha_resolucion'],
            'accion' => $incidencia['accion_resolucion'],
            'observacion' => $incidencia['observacion_resolucion']
        ];
    }

    jsonResponse([
        'success' => true,
        'incidencia' => $incidencia,
        'planilla' => $planilla,
        'fifo' => [
            'detalle' => $detallesConsumo,
            'tipo' => (float)$incidencia['cantidad_consumida'] > 0 ? 'PARCIAL' : 'SIN_STOCK'
        ],
        'resolucion' => $resolucion
    ]);
}

/**
 * Registra la resolución formal de una incidencia
 */
function resolverIncidencia($db, $data)
{
    $id = (int)($data['id_incidencia'] ?? 0);
    $accion = trim((string)($data['accion'] ?? ''));
    $observacion = trim((string)($data['observacion'] ?? ''));
    $estado = trim((string)($data['estado'] ?? 'RESUELTA'));

    if ($id <= 0 || empty($accion) || empty($observacion)) {
        jsonResponse(['success' => false, 'message' => 'ID, Acción y Observación son obligatorios'], 400);
    }

    // Validar estado
    $estadosValidos = ['PENDIENTE', 'EN_REVISION', 'RESUELTA', 'JUSTIFICADA', 'ANULADA'];
    if (!in_array($estado, $estadosValidos)) {
        jsonResponse(['success' => false, 'message' => 'Estado no válido'], 400);
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            UPDATE consumos_wip_pendientes
            SET 
                estado = ?,
                accion_resolucion = ?,
                observacion_resolucion = ?,
                id_usuario_resolucion = ?,
                fecha_resolucion = NOW()
            WHERE id_pendiente = ?
        ");
        
        $stmt->execute([
            $estado,
            $accion,
            $observacion,
            $_SESSION['user_id'] ?? null,
            $id
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("No se pudo actualizar la incidencia (ID inexistente o sin cambios)");
        }

        $db->commit();
        jsonResponse(['success' => true, 'message' => 'Incidencia actualizada correctamente']);

    } catch (Throwable $e) {
        $db->rollBack();
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

/**
 * Obtiene el historial resumido de planillas de tejido con filtros y paginación
 */
function getHistorialProduccionTejido($db)
{
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 20);
    $offset = ($page - 1) * $perPage;

    $desde = $_GET['fecha_desde'] ?? null;
    $hasta = $_GET['fecha_hasta'] ?? null;
    $turno = $_GET['id_turno'] ?? null;
    $responsable = $_GET['id_responsable'] ?? null;
    $search = trim($_GET['search'] ?? '');

    $where = ["p.activo = 1"];
    $params = [];

    if ($desde) {
        $where[] = "p.fecha_inicio >= ?";
        $params[] = $desde;
    }
    if ($hasta) {
        $where[] = "p.fecha_inicio <= ?";
        $params[] = $hasta;
    }

    if ($responsable) {
        // Buscamos en el JSON si el ID de responsable coincide con técnico, tejedor o asistente
        $where[] = "(JSON_EXTRACT(p.detalles_json, '$.id_tecnico') = ? OR JSON_EXTRACT(p.detalles_json, '$.id_tejedor') = ? OR JSON_EXTRACT(p.detalles_json, '$.id_asistente') = ?)";
        $params[] = $responsable;
        $params[] = $responsable;
        $params[] = $responsable;
    }

    if ($search) {
        $where[] = "(p.observaciones LIKE ? OR p.id_planilla LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $whereSql = implode(" AND ", $where);

    // Contar total para paginación
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM planillas_tejido p WHERE $whereSql");
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();

    // Obtener registros
    $stmt = $db->prepare("
        SELECT 
            p.id_planilla,
            p.fecha_inicio,
            p.fecha_fin,
            p.detalles_json,
            p.registrado_por,
            p.observaciones,
            p.fecha_registro,
            u.nombre_completo as usuario_registro_nombre,
            (SELECT COUNT(*) FROM consumos_wip_pendientes cp WHERE cp.id_planilla = p.id_planilla) as cantidad_incidencias
        FROM planillas_tejido p
        LEFT JOIN usuarios u ON u.id_usuario = p.registrado_por
        WHERE $whereSql
        ORDER BY p.fecha_inicio DESC, p.id_planilla DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($rows as $row) {
        $detalles = json_decode($row['detalles_json'], true);
        
        $totalDocenas = 0;
        $totalUnidades = 0;
        $maquinas = [];
        $turnosEncontrados = [];
        
        foreach ($detalles['lineas_produccion'] ?? [] as $linea) {
            $totalDocenas += (int)($linea['cantidad_docenas'] ?? 0);
            $totalUnidades += (int)($linea['cantidad_unidades'] ?? 0);
            if (!empty($linea['id_maquina'])) $maquinas[] = $linea['id_maquina'];
            if (!empty($linea['id_turno'])) $turnosEncontrados[] = $linea['id_turno'];
        }
        
        // Filtro por turno (se aplica en PHP si no se pudo por SQL)
        if ($turno && !in_array($turno, $turnosEncontrados)) {
            $total--; // Ajustamos el total si estamos filtrando en PHP (no ideal pero seguro)
            continue;
        }

        $totalBase = ($totalDocenas * 12) + $totalUnidades;
        $docenasFinal = floor($totalBase / 12);
        $unidadesFinal = $totalBase % 12;

        $data[] = [
            'id_planilla' => $row['id_planilla'],
            'fecha' => $row['fecha_inicio'],
            'fecha_fin' => $row['fecha_fin'],
            'total_docenas' => $docenasFinal,
            'total_unidades' => $unidadesFinal,
            'maquinas_activas' => count(array_unique($maquinas)),
            'cantidad_incidencias' => (int)$row['cantidad_incidencias'],
            'responsable_nombre' => $row['usuario_registro_nombre'] ?: 'SISTEMA',
            'observaciones' => $row['observaciones'],
            'fecha_registro' => $row['fecha_registro'],
            'estado' => 'ACTIVO'
        ];
    }

    jsonResponse([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => (int)$total,
            'total_pages' => ceil($total / $perPage)
        ]
    ]);
}

/**
 * Obtiene el detalle completo de una planilla con sus 6 bloques de información
 */
function getDetalleHistorialProduccionTejido($db)
{
    $id = (int)($_GET['id_planilla'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'ID de planilla requerido'], 400);
    }

    $stmt = $db->prepare("
        SELECT p.*, u.nombre_completo as registrado_por_nombre
        FROM planillas_tejido p
        LEFT JOIN usuarios u ON u.id_usuario = p.registrado_por
        WHERE p.id_planilla = ?
    ");
    $stmt->execute([$id]);
    $planilla = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$planilla) {
        jsonResponse(['success' => false, 'message' => 'Planilla no encontrada'], 404);
    }

    $detalles = json_decode($planilla['detalles_json'], true);

    // B. Resumen y C. Detalle Máquinas
    $totalDocenas = 0;
    $totalUnidades = 0;
    $maquinasArr = [];
    $lineasEnriquecidas = [];
    
    foreach ($detalles['lineas_produccion'] ?? [] as $linea) {
        $totalDocenas += (int)($linea['cantidad_docenas'] ?? 0);
        $totalUnidades += (int)($linea['cantidad_unidades'] ?? 0);
        if (!empty($linea['id_maquina'])) $maquinasArr[] = $linea['id_maquina'];

        // Enriquecer datos de máquina y producto
        $stmtM = $db->prepare("SELECT numero_maquina FROM maquinas WHERE id_maquina = ?");
        $stmtM->execute([$linea['id_maquina']]);
        $maquinaNombre = $stmtM->fetchColumn() ?: "M-".$linea['id_maquina'];

        $stmtP = $db->prepare("SELECT codigo_producto, descripcion_completa FROM productos_tejidos WHERE id_producto = ?");
        $stmtP->execute([$linea['id_producto']]);
        $prod = $stmtP->fetch(PDO::FETCH_ASSOC);

        $lineasEnriquecidas[] = array_merge($linea, [
            'maquina_nombre' => $maquinaNombre,
            'producto_codigo' => $prod['codigo_producto'] ?? '?',
            'producto_nombre' => $prod['descripcion_completa'] ?? '?'
        ]);
    }

    $totalBase = ($totalDocenas * 12) + $totalUnidades;

    // D. Incidencias
    $stmtInc = $db->prepare("
        SELECT cp.*, i.codigo as item_codigo, i.nombre as item_nombre, u.nombre_completo as usuario_nombre
        FROM consumos_wip_pendientes cp
        LEFT JOIN inventarios i ON i.id_inventario = cp.id_inventario
        LEFT JOIN usuarios u ON u.id_usuario = cp.id_usuario_resolucion
        WHERE cp.id_planilla = ?
    ");
    $stmtInc->execute([$id]);
    $incidencias = $stmtInc->fetchAll(PDO::FETCH_ASSOC);

    // E. Lotes WIP
    $idsLotes = !empty($detalles['lotes_creados']) ? array_column($detalles['lotes_creados'], 'id_lote_wip') : [];
    $lotesWip = [];
    if (!empty($idsLotes)) {
        $placeholders = implode(',', array_fill(0, count($idsLotes), '?'));
        $stmtLotes = $db->prepare("
            SELECT l.*, p.codigo_producto, p.descripcion_completa, a.nombre as area_nombre
            FROM lote_wip l
            JOIN productos_tejidos p ON p.id_producto = l.id_producto
            LEFT JOIN areas_produccion a ON a.id_area = l.id_area_actual
            WHERE l.id_lote_wip IN ($placeholders)
        ");
        $stmtLotes->execute($idsLotes);
        $lotesWip = $stmtLotes->fetchAll(PDO::FETCH_ASSOC);
    }

    // F. Movimientos posteriores (Trazabilidad)
    $movimientos = [];
    if (!empty($idsLotes)) {
        $placeholders = implode(',', array_fill(0, count($idsLotes), '?'));
        $stmtMovs = $db->prepare("
            SELECT m.*, ao.nombre as area_origen_nombre, ad.nombre as area_destino_nombre, u.nombre_completo as usuario_nombre
            FROM movimientos_wip m
            LEFT JOIN areas_produccion ao ON ao.id_area = m.id_area_origen
            LEFT JOIN areas_produccion ad ON ad.id_area = m.id_area_destino
            LEFT JOIN usuarios u ON u.id_usuario = m.usuario
            WHERE m.id_lote_wip IN ($placeholders)
            AND m.tipo_movimiento != 'CREACION_EN_TEJIDO'
            ORDER BY m.fecha ASC
        ");
        $stmtMovs->execute($idsLotes);
        $movimientos = $stmtMovs->fetchAll(PDO::FETCH_ASSOC);
    }

    // G. Análisis comparativo de consumo (NUEVO para Historial - Consistencia Bitácora)
    $analisisConsumo = [];
    if (!empty($idsLotes)) {
        $placeholders = implode(',', array_fill(0, count($idsLotes), '?'));
        
        // 1. Obtener consumos reales (vía lotes)
        $stmtReal = $db->prepare("
            SELECT i.nombre as componente, SUM(cd.cantidad_consumida) as real_kg
            FROM consumos_wip_detalle cd
            JOIN inventarios i ON i.id_inventario = cd.id_inventario
            WHERE cd.id_lote_wip IN ($placeholders)
            GROUP BY i.id_inventario
        ");
        $stmtReal->execute($idsLotes);
        $consumosReales = $stmtReal->fetchAll(PDO::FETCH_ASSOC);

        // 2. Obtener consumos pendientes (vía id_planilla - más directo)
        $stmtPend = $db->prepare("
            SELECT i.nombre as componente, SUM(cp.cantidad_pendiente) as pend_kg, SUM(cp.cantidad_requerida) as req_kg
            FROM consumos_wip_pendientes cp
            JOIN inventarios i ON i.id_inventario = cp.id_inventario
            WHERE cp.id_planilla = ?
            GROUP BY i.id_inventario
        ");
        $stmtPend->execute([$id]);
        $consumosPendientes = $stmtPend->fetchAll(PDO::FETCH_ASSOC);

        // 3. Consolidar mapeo
        $mapeo = [];
        foreach ($consumosReales as $r) {
            $mapeo[$r['componente']] = ['real' => (float)$r['real_kg'], 'pend' => 0, 'req' => (float)$r['real_kg']];
        }
        foreach ($consumosPendientes as $p) {
            $comp = $p['componente'];
            if (!isset($mapeo[$comp])) {
                $mapeo[$comp] = ['real' => 0, 'pend' => (float)$p['pend_kg'], 'req' => (float)$p['req_kg']];
            } else {
                $mapeo[$comp]['pend'] = (float)$p['pend_kg'];
                // El teórico es lo realmente consumido + lo que quedó pendiente para esta planilla
                $mapeo[$comp]['req'] = $mapeo[$comp]['real'] + (float)$p['pend_kg'];
            }
        }

        foreach ($mapeo as $comp => $vals) {
            $analisisConsumo[] = [
                'componente' => $comp,
                'teorico_kg' => round($vals['req'], 4),
                'real_kg' => round($vals['real'], 4),
                'pendiente_kg' => round($vals['pend'], 4),
                'diferencia_kg' => round($vals['real'] - $vals['req'], 4),
                'porcentaje' => $vals['req'] > 0 ? round(($vals['real'] / $vals['req']) * 100, 2) : 100
            ];
        }
    }

    jsonResponse([
        'success' => true,
        'data' => [
            'cabecera' => [
                'id_planilla' => $planilla['id_planilla'],
                'fecha' => $planilla['fecha_inicio'],
                'responsable_nombre' => $planilla['registrado_por_nombre'] ?: 'SISTEMA',
                'observaciones' => $planilla['observaciones'],
                'estado' => $planilla['activo'] ? 'CONFIRMADO' : 'ANULADO',
                'fecha_registro' => $planilla['fecha_registro']
            ],
            'resumen' => [
                'total_docenas' => floor($totalBase / 12),
                'total_unidades' => $totalBase % 12,
                'maquinas_activas' => count(array_unique($maquinasArr)),
                'total_incidencias' => count($incidencias),
                'total_lotes' => count($lotesWip),
                'desperdicio' => $detalles['desperdicio'] ?? []
            ],
            'maquinas' => $lineasEnriquecidas,
            'incidencias' => $incidencias,
            'lotes_wip' => $lotesWip,
            'movimientos' => $movimientos,
            'analisis_consumo' => $analisisConsumo
        ]
    ]);
}
