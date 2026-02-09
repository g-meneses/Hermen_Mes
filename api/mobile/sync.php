<?php
/**
 * API de Sincronización Móvil
 * Sistema ERP Hermen Ltda.
 * 
 * Sincronización de salidas offline con el ERP
 */

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';

try {
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? ($method === 'POST' ? 'sync' : 'status');

    switch ($action) {

        // =====================================================
        // STATUS - Estado de conexión y pendientes
        // =====================================================
        case 'status':
            $stmt = $db->query("
                SELECT 
                    estado_sync,
                    COUNT(*) as total
                FROM salidas_moviles
                GROUP BY estado_sync
            ");
            $estados = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $estados[$row['estado_sync']] = (int) $row['total'];
            }

            ob_clean();
            echo json_encode([
                'success' => true,
                'online' => true,
                'server_time' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get(),
                'estados' => $estados,
                'pendientes' => ($estados['PENDIENTE_SYNC'] ?? 0) + ($estados['OBSERVADA'] ?? 0)
            ]);
            break;

        // =====================================================
        // SYNC - Sincronizar salidas pendientes
        // =====================================================
        case 'sync':
            if ($method !== 'POST') {
                throw new Exception('Método debe ser POST', 405);
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $salidas = $data['salidas'] ?? [];

            if (empty($salidas)) {
                // Procesar pendientes del servidor
                $stmt = $db->query("
                    SELECT id_salida_movil, uuid_local 
                    FROM salidas_moviles 
                    WHERE estado_sync = 'PENDIENTE_SYNC'
                    ORDER BY fecha_hora_local ASC
                    LIMIT 10
                ");
                $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $resultados = [];
                foreach ($pendientes as $p) {
                    $resultado = procesarSincronizacion($db, $p['id_salida_movil']);
                    $resultados[] = [
                        'uuid_local' => $p['uuid_local'],
                        'resultado' => $resultado
                    ];
                }

                ob_clean();
                echo json_encode([
                    'success' => true,
                    'procesados' => count($resultados),
                    'resultados' => $resultados
                ]);

            } else {
                // Procesar salidas enviadas desde el dispositivo
                $resultados = [];

                foreach ($salidas as $salidaData) {
                    try {
                        // Verificar si ya existe
                        $stmt = $db->prepare("SELECT id_salida_movil FROM salidas_moviles WHERE uuid_local = ?");
                        $stmt->execute([$salidaData['uuid_local']]);
                        $existente = $stmt->fetch();

                        if ($existente) {
                            // Ya existe, procesar sync
                            $resultado = procesarSincronizacion($db, $existente['id_salida_movil']);
                        } else {
                            // Crear nueva y procesar
                            $db->beginTransaction();

                            $stmt = $db->prepare("
                                INSERT INTO salidas_moviles (
                                    uuid_local, tipo_salida, id_area_destino, observaciones,
                                    usuario_entrega, usuario_recibe, fecha_hora_local,
                                    estado_sync, dispositivo_info
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDIENTE_SYNC', ?)
                            ");

                            $stmt->execute([
                                $salidaData['uuid_local'],
                                $salidaData['tipo_salida'],
                                $salidaData['id_area_destino'],
                                $salidaData['observaciones'] ?? null,
                                $salidaData['usuario_entrega'],
                                $salidaData['usuario_recibe'],
                                $salidaData['fecha_hora_local'],
                                $salidaData['dispositivo_info'] ?? null
                            ]);

                            $id_salida = $db->lastInsertId();

                            // Insertar detalle
                            if (!empty($salidaData['items'])) {
                                $stmtDet = $db->prepare("
                                    INSERT INTO salidas_moviles_detalle 
                                    (id_salida_movil, id_inventario, cantidad, stock_referencial, observaciones)
                                    VALUES (?, ?, ?, ?, ?)
                                ");

                                foreach ($salidaData['items'] as $item) {
                                    $stmtDet->execute([
                                        $id_salida,
                                        $item['id_inventario'],
                                        $item['cantidad'],
                                        $item['stock_referencial'] ?? null,
                                        $item['observaciones'] ?? null
                                    ]);
                                }
                            }

                            $db->commit();

                            // Procesar sincronización
                            $resultado = procesarSincronizacion($db, $id_salida);
                        }

                        $resultados[] = [
                            'uuid_local' => $salidaData['uuid_local'],
                            'success' => true,
                            'resultado' => $resultado
                        ];

                    } catch (Exception $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        $resultados[] = [
                            'uuid_local' => $salidaData['uuid_local'] ?? 'unknown',
                            'success' => false,
                            'error' => $e->getMessage()
                        ];
                    }
                }

                ob_clean();
                echo json_encode([
                    'success' => true,
                    'procesados' => count($resultados),
                    'resultados' => $resultados
                ]);
            }
            break;

        // =====================================================
        // CATALOGS_VERSION - Versión de catálogos
        // =====================================================
        case 'catalogos_version':
            // Obtener última actualización de catálogos principales
            $stmtU = $db->query("SELECT MAX(ultimo_acceso) FROM usuarios WHERE estado = 'activo'");
            $lastUsuarios = $stmtU->fetchColumn() ?: date('Y-m-d H:i:s');

            $stmtI = $db->query("SELECT MAX(fecha_actualizacion) FROM inventarios WHERE activo = 1");
            $lastInventarios = $stmtI->fetchColumn() ?: date('Y-m-d H:i:s');

            ob_clean();
            echo json_encode([
                'success' => true,
                'versions' => [
                    'usuarios' => $lastUsuarios,
                    'productos' => $lastInventarios,
                    'server' => date('Y-m-d H:i:s')
                ]
            ]);
            break;

        default:
            throw new Exception('Acción no válida', 400);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Procesa la sincronización de una salida móvil
 * Valida stock, crea documento de inventario, descuenta stock y registra movimientos
 */
function procesarSincronizacion($db, $id_salida_movil)
{
    // Obtener salida
    $stmt = $db->prepare("SELECT * FROM salidas_moviles WHERE id_salida_movil = ?");
    $stmt->execute([$id_salida_movil]);
    $salida = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$salida) {
        return ['estado' => 'ERROR', 'mensaje' => 'Salida no encontrada'];
    }

    if ($salida['estado_sync'] === 'SINCRONIZADA') {
        return ['estado' => 'SINCRONIZADA', 'mensaje' => 'Ya sincronizada previamente'];
    }

    // Obtener detalle con información del producto
    $stmtDet = $db->prepare("
        SELECT 
            smd.*, 
            i.stock_actual, 
            i.codigo, 
            i.nombre,
            i.costo_promedio,
            i.id_tipo_inventario
        FROM salidas_moviles_detalle smd
        JOIN inventarios i ON smd.id_inventario = i.id_inventario
        WHERE smd.id_salida_movil = ?
    ");
    $stmtDet->execute([$id_salida_movil]);
    $items = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        return ['estado' => 'ERROR', 'mensaje' => 'Salida sin productos'];
    }

    // Validar stock
    $observaciones = [];
    $hayProblemas = false;

    foreach ($items as $item) {
        if ($item['cantidad'] > $item['stock_actual']) {
            $observaciones[] = sprintf(
                "%s: solicitado %.2f, disponible %.2f",
                $item['codigo'],
                $item['cantidad'],
                $item['stock_actual']
            );
            $hayProblemas = true;
        }
    }

    if ($hayProblemas) {
        // Marcar como observada
        $stmt = $db->prepare("
            UPDATE salidas_moviles 
            SET estado_sync = 'OBSERVADA', 
                motivo_rechazo = ?,
                fecha_sincronizada = NOW()
            WHERE id_salida_movil = ?
        ");
        $motivoCompleto = implode("; ", $observaciones);
        $stmt->execute([$motivoCompleto, $id_salida_movil]);

        // Crear notificación en el ERP para seguimiento
        crearNotificacionSincronizacion($db, [
            'tipo' => 'ALERTA',
            'titulo' => 'Salida Móvil Observada - Stock Insuficiente',
            'mensaje' => "La salida móvil {$salida['uuid_local']} ({$salida['tipo_salida']}) no pudo sincronizarse por stock insuficiente.\n\nDetalles:\n" . $motivoCompleto,
            'id_referencia' => $id_salida_movil,
            'tabla_referencia' => 'salidas_moviles',
            'prioridad' => 'ALTA',
            'destinatario_rol' => 'almacen'
        ]);

        return [
            'estado' => 'OBSERVADA',
            'mensaje' => 'Stock insuficiente en algunos productos',
            'observaciones' => $observaciones
        ];
    }

    // =====================================================
    // PROCESAR INVENTARIO REAL
    // =====================================================

    $db->beginTransaction();

    try {
        // Determinar tipo de inventario del primer producto
        $idTipoInventario = $items[0]['id_tipo_inventario'];

        // Mapear tipos de salida
        $tipoSalidaMap = [
            'PRODUCCION' => 'PRODUCCION',
            'CONSUMO_INTERNO' => 'AJUSTE',
            'MUESTRA' => 'MUESTRAS',
            'MERMA' => 'AJUSTE',
            'AJUSTE' => 'AJUSTE'
        ];
        $tipoSalida = $tipoSalidaMap[$salida['tipo_salida']] ?? 'PRODUCCION';

        // Prefijos por tipo de inventario
        $prefijosTipo = [
            1 => 'MP',   // Materias Primas
            2 => 'CAQ',  // Colorantes
            3 => 'EMP',  // Empaque
            4 => 'ACC',  // Accesorios
            7 => 'REP'   // Repuestos
        ];
        $prefijoInv = $prefijosTipo[$idTipoInventario] ?? 'INV';

        // Códigos por tipo de salida
        $codigosTipo = [
            'PRODUCCION' => 'P',
            'MUESTRAS' => 'M',
            'AJUSTE' => 'A'
        ];
        $codigoTipo = $codigosTipo[$tipoSalida] ?? 'X';

        // Generar número de documento: OUT-MOVIL-MP-P-202602-0001
        $prefijo = "OUT-MOVIL-$prefijoInv-$codigoTipo";
        $numeroDoc = generarNumeroDocumentoMovil($db, $prefijo);

        // Calcular total
        $totalDocumento = 0;
        foreach ($items as $item) {
            $totalDocumento += $item['cantidad'] * floatval($item['costo_promedio'] ?? 0);
        }

        // Obtener área destino
        $stmtArea = $db->prepare("SELECT nombre FROM areas_produccion WHERE id_area = ?");
        $stmtArea->execute([$salida['id_area_destino']]);
        $areaNombre = $stmtArea->fetchColumn() ?: 'Sin especificar';

        // Crear referencia
        $referencia = "MOVIL - {$salida['tipo_salida']} - Destino: {$areaNombre}";
        if (!empty($salida['observaciones'])) {
            $referencia .= " - " . $salida['observaciones'];
        }

        // Insertar documento de inventario
        $stmtDoc = $db->prepare("
            INSERT INTO documentos_inventario (
                tipo_documento, tipo_salida, numero_documento, fecha_documento,
                id_tipo_inventario, referencia_externa, total,
                observaciones, estado, creado_por
            ) VALUES (
                'SALIDA', ?, ?, ?, ?, ?, ?, ?, 'CONFIRMADO', ?
            )
        ");

        $stmtDoc->execute([
            $tipoSalida,
            $numeroDoc,
            date('Y-m-d', strtotime($salida['fecha_hora_local'])),
            $idTipoInventario,
            $referencia,
            $totalDocumento,
            "Salida móvil UUID: {$salida['uuid_local']}",
            $salida['usuario_entrega']
        ]);

        $idDocumento = $db->lastInsertId();

        // Preparar sentencias
        $stmtLinea = $db->prepare("
            INSERT INTO documentos_inventario_detalle (
                id_documento, id_inventario, cantidad, costo_unitario, subtotal
            ) VALUES (?, ?, ?, ?, ?)
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
                observaciones, estado, creado_por
            ) VALUES (?, ?, ?, ?, ?, 'SALIDA', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?)
        ");

        $fechaMovimiento = $salida['fecha_hora_local'];
        $codigoMovBase = generarCodigoMovimientoMovil($db);

        // Tipo de movimiento con identificador MOVIL
        $tipoMov = 'SALIDA_MOVIL_' . $salida['tipo_salida'];

        // Procesar cada item
        foreach ($items as $item) {
            $cantidad = floatval($item['cantidad']);
            $costoUnit = floatval($item['costo_promedio'] ?? 0);
            $subtotal = $cantidad * $costoUnit;

            // Obtener stock actual (bloqueando fila)
            $stmtCheck = $db->prepare("
                SELECT stock_actual, costo_promedio 
                FROM inventarios 
                WHERE id_inventario = ?
                FOR UPDATE
            ");
            $stmtCheck->execute([$item['id_inventario']]);
            $infoInv = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            $stockActual = floatval($infoInv['stock_actual'] ?? 0);
            $cppActual = floatval($infoInv['costo_promedio'] ?? 0);

            // Insertar línea de documento
            $stmtLinea->execute([
                $idDocumento,
                $item['id_inventario'],
                $cantidad,
                $costoUnit,
                $subtotal
            ]);

            // Actualizar stock
            $stmtStock->execute([
                $cantidad,
                $item['id_inventario']
            ]);

            // Registrar movimiento
            $stmtMovimiento->execute([
                $item['id_inventario'],
                $idTipoInventario,
                $fechaMovimiento,
                $tipoMov,
                $codigoMovBase,
                $numeroDoc,
                $idDocumento,
                $cantidad,
                $costoUnit,
                $subtotal,
                $stockActual,
                $stockActual - $cantidad,
                $cppActual,
                $cppActual, // CPP no cambia en salidas
                "Salida móvil - {$salida['tipo_salida']} - Destino: {$areaNombre}",
                $salida['usuario_entrega']
            ]);
        }

        // Actualizar estado de salida móvil
        $stmtUpdate = $db->prepare("
            UPDATE salidas_moviles 
            SET estado_sync = 'SINCRONIZADA',
                fecha_sincronizada = NOW(),
                motivo_rechazo = NULL,
                id_documento_generado = ?
            WHERE id_salida_movil = ?
        ");
        $stmtUpdate->execute([$idDocumento, $id_salida_movil]);

        $db->commit();

        return [
            'estado' => 'SINCRONIZADA',
            'mensaje' => 'Sincronización exitosa',
            'documento' => $numeroDoc,
            'id_documento' => $idDocumento,
            'items_procesados' => count($items)
        ];

    } catch (Exception $e) {
        $db->rollBack();

        // Marcar como rechazada
        $stmtError = $db->prepare("
            UPDATE salidas_moviles 
            SET estado_sync = 'RECHAZADA',
                motivo_rechazo = ?
            WHERE id_salida_movil = ?
        ");
        $stmtError->execute([$e->getMessage(), $id_salida_movil]);

        // Crear notificación de error en el ERP
        crearNotificacionSincronizacion($db, [
            'tipo' => 'ERROR',
            'titulo' => 'Salida Móvil Rechazada - Error de Procesamiento',
            'mensaje' => "La salida móvil {$salida['uuid_local']} ({$salida['tipo_salida']}) fue rechazada.\n\nError: " . $e->getMessage(),
            'id_referencia' => $id_salida_movil,
            'tabla_referencia' => 'salidas_moviles',
            'prioridad' => 'URGENTE',
            'destinatario_rol' => 'admin'
        ]);

        return [
            'estado' => 'RECHAZADA',
            'mensaje' => 'Error al procesar: ' . $e->getMessage()
        ];
    }
}

/**
 * Genera número de documento para salidas móviles
 */
function generarNumeroDocumentoMovil($db, $prefijo)
{
    $anio = date('Y');
    $mes = date('m');

    $stmt = $db->prepare("
        SELECT ultimo_numero FROM secuencias_documento 
        WHERE tipo_documento = 'SALIDA' AND prefijo = ? AND anio = ? AND mes = ?
        FOR UPDATE
    ");
    $stmt->execute([$prefijo, $anio, $mes]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $siguiente = $row['ultimo_numero'] + 1;
        $stmtUp = $db->prepare("
            UPDATE secuencias_documento SET ultimo_numero = ?
            WHERE tipo_documento = 'SALIDA' AND prefijo = ? AND anio = ? AND mes = ?
        ");
        $stmtUp->execute([$siguiente, $prefijo, $anio, $mes]);
    } else {
        $siguiente = 1;
        $stmtIn = $db->prepare("
            INSERT INTO secuencias_documento (tipo_documento, prefijo, anio, mes, ultimo_numero)
            VALUES ('SALIDA', ?, ?, ?, 1)
        ");
        $stmtIn->execute([$prefijo, $anio, $mes]);
    }

    return $prefijo . '-' . $anio . $mes . '-' . str_pad($siguiente, 4, '0', STR_PAD_LEFT);
}

/**
 * Genera código de movimiento para salidas móviles
 */
function generarCodigoMovimientoMovil($db)
{
    $fecha = date('Ymd');

    $stmt = $db->prepare("
        SELECT ultimo_numero FROM secuencias_documento 
        WHERE tipo_documento = 'MOVIMIENTO' AND prefijo = 'MOV-MOVIL' AND anio = ? AND mes = ?
        FOR UPDATE
    ");
    $stmt->execute([date('Y'), date('m')]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $siguiente = $row['ultimo_numero'] + 1;
        $stmtUp = $db->prepare("
            UPDATE secuencias_documento SET ultimo_numero = ?
            WHERE tipo_documento = 'MOVIMIENTO' AND prefijo = 'MOV-MOVIL' AND anio = ? AND mes = ?
        ");
        $stmtUp->execute([$siguiente, date('Y'), date('m')]);
    } else {
        $siguiente = 1;
        $stmtIn = $db->prepare("
            INSERT INTO secuencias_documento (tipo_documento, prefijo, anio, mes, ultimo_numero)
            VALUES ('MOVIMIENTO', 'MOV-MOVIL', ?, ?, 1)
        ");
        $stmtIn->execute([date('Y'), date('m')]);
    }

    return 'MOV-MOVIL-' . $fecha . '-' . str_pad($siguiente, 4, '0', STR_PAD_LEFT);
}

/**
 * Crea una notificación en el sistema ERP para seguimiento
 */
function crearNotificacionSincronizacion($db, $datos)
{
    try {
        $stmt = $db->prepare("
            INSERT INTO notificaciones_sistema (
                tipo, modulo, titulo, mensaje, datos_adicionales,
                id_referencia, tabla_referencia, prioridad, destinatario_rol
            ) VALUES (?, 'MOVIL', ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $datos['tipo'] ?? 'INFO',
            $datos['titulo'] ?? 'Notificación de Sincronización',
            $datos['mensaje'] ?? '',
            isset($datos['datos_adicionales']) ? json_encode($datos['datos_adicionales']) : null,
            $datos['id_referencia'] ?? null,
            $datos['tabla_referencia'] ?? null,
            $datos['prioridad'] ?? 'MEDIA',
            $datos['destinatario_rol'] ?? null
        ]);

        return $db->lastInsertId();
    } catch (Exception $e) {
        error_log("[Sync] Error creando notificación: " . $e->getMessage());
        return false;
    }
}
?>