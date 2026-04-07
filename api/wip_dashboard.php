<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

if (!isLoggedIn() || !hasRole(['admin', 'gerencia', 'coordinador'])) {
    jsonResponse(['success' => false, 'message' => 'No autorizado'], 401);
}

try {
    $db = getDB();
    $fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-d');
    $fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
    $idTurno = !empty($_GET['id_turno']) ? (int)$_GET['id_turno'] : null;
    $idMaquina = !empty($_GET['id_maquina']) ? (int)$_GET['id_maquina'] : null;
    $idProducto = !empty($_GET['id_producto']) ? (int)$_GET['id_producto'] : null;

    // 1. KPIs
    $kpis = obtenerKPIs($db, $fechaDesde, $fechaHasta, $idTurno, $idMaquina, $idProducto);

    // 2. Producción por Máquina
    $productionByMachine = obtenerProduccionPorMaquina($db, $fechaDesde, $fechaHasta, $idTurno, $idMaquina, $idProducto);

    // 3. WIP por Lote (Detallado)
    $wipByLot = obtenerWipPorLote($db, $idTurno, $idMaquina, $idProducto);

    // 4. WIP por Producto (Resumen)
    $wipByProduct = obtenerWipPorProducto($db, $idProducto);

    // 5. Trazabilidad SAL-TEJ -> Lote -> Producción
    $traceability = obtenerTrazabilidad($db, $fechaDesde, $fechaHasta, $idProducto);

    // 6. Alertas / Inconsistencias
    $alerts = generarAlertas($db, $fechaDesde, $fechaHasta);

    // 7. Hilos - Control basado en BOM (Ajustes Obligatorios)
    $yarnData = obtenerControlHilos($db, $fechaDesde, $fechaHasta, $idProducto);

    // Fusionar KPIs de hilos en el objeto principal
    $kpis = array_merge($kpis, $yarnData['kpis']);

    jsonResponse([
        'success' => true,
        'data' => [
            'kpis' => $kpis,
            'production_by_machine' => $productionByMachine,
            'wip_by_lot' => $wipByLot,
            'wip_by_product' => $wipByProduct,
            'traceability' => $traceability,
            'alerts' => array_merge($alerts, $yarnData['alerts']),
            'hilos_detalle' => $yarnData['detalle'],
            'hilos_kardex' => $yarnData['kardex']
        ],
        'filters' => [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'id_turno' => $idTurno,
            'id_maquina' => $idMaquina,
            'id_producto' => $idProducto
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en wip_dashboard.php: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function obtenerKPIs($db, $fechaDesde, $fechaHasta, $idTurno, $idMaquina, $idProducto) {
    // MP Transferida (SAL-TEJ) en Kg - Solo hoy o según fecha
    $sqlMP = "SELECT COALESCE(SUM(dd.cantidad), 0) 
              FROM documentos_inventario d
              JOIN documentos_inventario_detalle dd ON d.id_documento = dd.id_documento
              WHERE d.tipo_documento = 'SALIDA' 
                AND d.tipo_consumo = 'TEJIDO'
                AND d.estado = 'CONFIRMADO'
                AND d.fecha_documento BETWEEN ? AND ?";
    $stmtMP = $db->prepare($sqlMP);
    $stmtMP->execute([$fechaDesde, $fechaHasta]);
    $mpTransferred = (float)$stmtMP->fetchColumn();

    // Producción Today (Doc/Und)
    $sqlProd = "SELECT SUM(cantidad_docenas) as doc, SUM(cantidad_unidades) as und
                FROM lote_wip
                WHERE DATE(fecha_inicio) BETWEEN ? AND ?";
    $paramsProd = [$fechaDesde, $fechaHasta];
    if ($idTurno) { $sqlProd .= " AND id_turno = ?"; $paramsProd[] = $idTurno; }
    if ($idMaquina) { $sqlProd .= " AND id_maquina = ?"; $paramsProd[] = $idMaquina; }
    if ($idProducto) { $sqlProd .= " AND id_producto = ?"; $paramsProd[] = $idProducto; }
    
    $stmtProd = $db->prepare($sqlProd);
    $stmtProd->execute($paramsProd);
    $prod = $stmtProd->fetch();
    $totalProdBase = ($prod['doc'] ?? 0) * 12 + ($prod['und'] ?? 0);

    // WIP Balance Current (TEJIDURIA area, NOT CLOSED/ANULADO)
    $sqlWip = "SELECT SUM(cantidad_docenas) as doc, SUM(cantidad_unidades) as und
               FROM lote_wip
               WHERE id_area_actual = (SELECT id_area FROM areas_produccion WHERE codigo = 'TEJEDURIA' LIMIT 1)
                 AND estado_lote NOT IN ('CERRADO', 'ANULADO')";
    $stmtWip = $db->query($sqlWip);
    $wip = $stmtWip->fetch();
    $totalWipBase = ($wip['doc'] ?? 0) * 12 + ($wip['und'] ?? 0);

    // Active Machines (based on filters)
    $sqlMach = "SELECT COUNT(DISTINCT id_maquina) FROM lote_wip WHERE DATE(fecha_inicio) BETWEEN ? AND ?";
    $paramsMach = [$fechaDesde, $fechaHasta];
    if ($idTurno) { $sqlMach .= " AND id_turno = ?"; $paramsMach[] = $idTurno; }
    if ($idProducto) { $sqlMach .= " AND id_producto = ?"; $paramsMach[] = $idProducto; }
    $stmtMach = $db->prepare($sqlMach);
    $stmtMach->execute($paramsMach);
    $activeMachines = (int)$stmtMach->fetchColumn();

    // Active Lots
    $sqlLots = "SELECT COUNT(*) FROM lote_wip WHERE estado_lote NOT IN ('CERRADO', 'ANULADO')";
    $activeLots = (int)$db->query($sqlLots)->fetchColumn();

    return [
        'mp_transferred_today' => $mpTransferred,
        'production_today_base' => $totalProdBase,
        'production_today_fmt' => floor($totalProdBase/12) . "|" . ($totalProdBase%12),
        'wip_balance_current_base' => $totalWipBase,
        'wip_balance_current_fmt' => floor($totalWipBase/12) . "|" . ($totalWipBase%12),
        'active_machines' => $activeMachines,
        'active_lots' => $activeLots
    ];
}

function obtenerProduccionPorMaquina($db, $fechaDesde, $fechaHasta, $idTurno, $idMaquina, $idProducto) {
    $sql = "SELECT m.numero_maquina, t.nombre as turno_nombre, p.codigo_producto, p.descripcion_completa,
                   SUM(l.cantidad_docenas) as docenas, SUM(l.cantidad_unidades) as unidades,
                   COUNT(l.id_lote_wip) as num_lotes
            FROM lote_wip l
            JOIN maquinas m ON l.id_maquina = m.id_maquina
            JOIN turnos t ON l.id_turno = t.id_turno
            JOIN productos_tejidos p ON l.id_producto = p.id_producto
            WHERE DATE(l.fecha_inicio) BETWEEN ? AND ?";
    
    $params = [$fechaDesde, $fechaHasta];
    if ($idTurno) { $sql .= " AND l.id_turno = ?"; $params[] = $idTurno; }
    if ($idMaquina) { $sql .= " AND l.id_maquina = ?"; $params[] = $idMaquina; }
    if ($idProducto) { $sql .= " AND l.id_producto = ?"; $params[] = $idProducto; }

    $sql .= " GROUP BY l.id_maquina, l.id_turno, l.id_producto
              ORDER BY m.numero_maquina, t.id_turno";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function obtenerWipPorLote($db, $idTurno, $idMaquina, $idProducto) {
    $sql = "SELECT l.codigo_lote, p.codigo_producto, p.descripcion_completa, a.nombre as area_nombre,
                   l.estado_lote, l.cantidad_docenas, l.cantidad_unidades, l.fecha_actualizacion,
                   d.numero_documento as sal_tej_ref
            FROM lote_wip l
            JOIN productos_tejidos p ON l.id_producto = p.id_producto
            JOIN areas_produccion a ON l.id_area_actual = a.id_area
            LEFT JOIN documentos_inventario d ON l.id_documento_salida = d.id_documento
            WHERE l.estado_lote NOT IN ('CERRADO', 'ANULADO')";
    
    $params = [];
    if ($idTurno) { $sql .= " AND l.id_turno = ?"; $params[] = $idTurno; }
    if ($idMaquina) { $sql .= " AND l.id_maquina = ?"; $params[] = $idMaquina; }
    if ($idProducto) { $sql .= " AND l.id_producto = ?"; $params[] = $idProducto; }

    $sql .= " ORDER BY l.fecha_actualizacion DESC LIMIT 100";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function obtenerWipPorProducto($db, $idProducto) {
    $sql = "SELECT p.codigo_producto, p.descripcion_completa, 
                   SUM(l.cantidad_docenas) as docenas, SUM(l.cantidad_unidades) as unidades,
                   COUNT(l.id_lote_wip) as num_lotes
            FROM lote_wip l
            JOIN productos_tejidos p ON l.id_producto = p.id_producto
            WHERE l.estado_lote NOT IN ('CERRADO', 'ANULADO')";
    
    $params = [];
    if ($idProducto) { $sql .= " AND l.id_producto = ?"; $params[] = $idProducto; }

    $sql .= " GROUP BY l.id_producto ORDER BY docenas DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function obtenerTrazabilidad($db, $fechaDesde, $fechaHasta, $idProducto) {
    $sql = "SELECT d.numero_documento as sal_tej, d.fecha_documento, 
                   l.codigo_lote, l.fecha_inicio, p.codigo_producto,
                   l.cantidad_docenas, l.cantidad_unidades,
                   m.numero_maquina, t.nombre as turno_nombre
            FROM documentos_inventario d
            JOIN lote_wip l ON l.id_documento_salida = d.id_documento
            JOIN productos_tejidos p ON l.id_producto = p.id_producto
            JOIN maquinas m ON l.id_maquina = m.id_maquina
            JOIN turnos t ON l.id_turno = t.id_turno
            WHERE d.fecha_documento BETWEEN ? AND ?";
    
    $params = [$fechaDesde, $fechaHasta];
    if ($idProducto) { $sql .= " AND l.id_producto = ?"; $params[] = $idProducto; }

    $sql .= " ORDER BY d.fecha_documento DESC, l.id_lote_wip DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function generarAlertas($db, $fechaDesde, $fechaHasta) {
    $alerts = [];

    // 1. Producción sin SAL-TEJ
    $stmt = $db->prepare("SELECT COUNT(*) FROM lote_wip WHERE id_documento_salida IS NULL AND DATE(fecha_inicio) BETWEEN ? AND ?");
    $stmt->execute([$fechaDesde, $fechaHasta]);
    if ($count = $stmt->fetchColumn()) {
        $alerts[] = ['type' => 'danger', 'msg' => "$count lotes de producción sin vinculación a SAL-TEJ.", 'code' => 'PROD_NO_SAL'];
    }

    // 2. Máquina duplicada en mismo turno (hoy)
    $stmt = $db->prepare("SELECT id_maquina, id_turno, COUNT(*) as c 
                          FROM lote_wip 
                          WHERE DATE(fecha_inicio) BETWEEN ? AND ?
                          GROUP BY id_maquina, id_turno, DATE(fecha_inicio) 
                          HAVING c > 1");
    $stmt->execute([$fechaDesde, $fechaHasta]);
    if ($dups = $stmt->fetchAll()) {
        $count = count($dups);
        $alerts[] = ['type' => 'warning', 'msg' => "Se detectaron $count casos de máquina duplicada en el mismo turno.", 'code' => 'DUP_MACH'];
    }

    // 3. SAL-TEJ sin producción asociada
    $stmt = $db->prepare("SELECT d.numero_documento 
                          FROM documentos_inventario d
                          LEFT JOIN lote_wip l ON l.id_documento_salida = d.id_documento
                          WHERE d.tipo_documento = 'SALIDA' AND d.tipo_consumo = 'TEJIDO' 
                            AND d.estado = 'CONFIRMADO' 
                            AND d.fecha_documento BETWEEN ? AND ?
                            AND l.id_lote_wip IS NULL");
    $stmt->execute([$fechaDesde, $fechaHasta]);
    if ($missing = $stmt->fetchAll()) {
        $count = count($missing);
        $alerts[] = ['type' => 'info', 'msg' => "Hay $count documentos SAL-TEJ que no tienen producción registrada.", 'code' => 'SAL_NO_PROD'];
    }

    return $alerts;
}

/**
 * Función principal para el control de hilos basado en BOM
 * Ajustes: Filtrado por tipo HILO, unificación de unidades a Kg
 */
function obtenerControlHilos($db, $fechaDesde, $fechaHasta, $idProducto) {
    // 1. Recibido Histórico y del Período
    // Solo materiales tipo HILO (id_tipo_inventario = 1)
    $sqlRecibido = "SELECT dd.id_inventario, i.codigo, i.nombre,
                        SUM(CASE WHEN d.fecha_documento < ? THEN dd.cantidad ELSE 0 END) as saldo_ant_rec,
                        SUM(CASE WHEN d.fecha_documento BETWEEN ? AND ? THEN dd.cantidad ELSE 0 END) as recibido_periodo
                    FROM documentos_inventario d
                    JOIN documentos_inventario_detalle dd ON d.id_documento = dd.id_documento
                    JOIN inventarios i ON dd.id_inventario = i.id_inventario
                    WHERE d.tipo_documento = 'SALIDA' 
                      AND d.tipo_consumo = 'TEJIDO' 
                      AND d.estado = 'CONFIRMADO'
                      AND i.id_tipo_inventario = 1
                    GROUP BY dd.id_inventario";
    $stmtRec = $db->prepare($sqlRecibido);
    $stmtRec->execute([$fechaDesde, $fechaDesde, $fechaHasta]);
    $recibidos = $stmtRec->fetchAll(PDO::FETCH_UNIQUE);

    // 2. Consumo Teórico (Producción * BOM)
    // Producción desde proyeccion_tejeduria y detalle_produccion_tejeduria
    $sqlConsumo = "SELECT bd.id_inventario,
                        SUM(CASE WHEN p.fecha_produccion < ? 
                            THEN (dp.docenas * 12 + dp.unidades) * bd.gramos_por_docena / 12000 
                            ELSE 0 END) as saldo_ant_cons,
                        SUM(CASE WHEN p.fecha_produccion BETWEEN ? AND ? 
                            THEN (dp.docenas * 12 + dp.unidades) * bd.gramos_por_docena / 12000 
                            ELSE 0 END) as consumo_periodo
                    FROM produccion_tejeduria p
                    JOIN detalle_produccion_tejeduria dp ON p.id_produccion = dp.id_produccion
                    JOIN bom_productos b ON dp.id_producto = b.id_producto AND b.estado = 'ACTIVO'
                    JOIN bom_productos_detalle bd ON b.id_bom = bd.id_bom
                    JOIN inventarios i ON bd.id_inventario = i.id_inventario
                    WHERE i.id_tipo_inventario = 1
                    GROUP BY bd.id_inventario";
    $stmtCons = $db->prepare($sqlConsumo);
    $stmtCons->execute([$fechaDesde, $fechaDesde, $fechaHasta]);
    $consumos = $stmtCons->fetchAll(PDO::FETCH_UNIQUE);

    // 3. Saldo en Máquinas (WIP Actual * BOM)
    $sqlSaldoMaq = "SELECT bd.id_inventario,
                           SUM((l.cantidad_docenas * 12 + l.cantidad_unidades) * bd.gramos_por_docena / 12000) as saldo_maquina
                    FROM lote_wip l
                    JOIN areas_produccion a ON l.id_area_actual = a.id_area
                    JOIN bom_productos b ON l.id_producto = b.id_producto AND b.estado = 'ACTIVO'
                    JOIN bom_productos_detalle bd ON b.id_bom = bd.id_bom
                    WHERE a.codigo = 'TEJEDURIA' AND l.estado_lote NOT IN ('CERRADO', 'ANULADO')
                    GROUP BY bd.id_inventario";
    $saldosMaq = $db->query($sqlSaldoMaq)->fetchAll(PDO::FETCH_UNIQUE);

    // 4. Consolidación de datos por Hilo
    $detalle = [];
    $kardex = [];
    $totalRecibido = 0;
    $totalConsumo = 0;
    $totalAnt = 0;
    $yarnAlerts = [];

    // Obtenemos lista maestra de hilos involucrados
    $allIds = array_unique(array_merge(array_keys($recibidos), array_keys($consumos), array_keys($saldosMaq)));
    
    foreach ($allIds as $id) {
        $rec = $recibidos[$id] ?? null;
        $cons = $consumos[$id] ?? null;
        $smaq = $saldosMaq[$id] ?? null;

        $nombre = $rec['nombre'] ?? ($cons['nombre'] ?? "Hilo ID $id");
        $codigo = $rec['codigo'] ?? ($cons['codigo'] ?? "N/A");

        $saldoAnt = ($rec['saldo_ant_rec'] ?? 0) - ($cons['saldo_ant_cons'] ?? 0);
        $recibido = (float)($rec['recibido_periodo'] ?? 0);
        $consumo = (float)($cons['consumo_periodo'] ?? 0);
        $sMaq = (float)($smaq['saldo_maquina'] ?? 0);
        
        $saldoFinal = $saldoAnt + $recibido - $consumo;
        $saldoSala = max(0, $saldoFinal - $sMaq);

        $totalRecibido += $recibido;
        $totalConsumo += $consumo;
        $totalAnt += $saldoAnt;

        $porcentaje_consumido = $recibido > 0 ? ($consumo / $recibido) * 100 : 0;
        $porcentaje_saldo = 100 - $porcentaje_consumido;

        $detalle[] = [
            'id_inventario' => $id,
            'codigo_hilo' => $codigo,
            'nombre' => $nombre,
            'recibido' => round($recibido, 2),
            'consumo_teorico' => round($consumo, 2),
            'saldo_maquina' => round($sMaq, 2),
            'saldo_sala' => round($saldoSala, 2),
            'saldo_total' => round($saldoFinal, 2),
            'porcentajes' => [
                'consumido' => round($porcentaje_consumido, 1),
                'saldo' => round(max(0, $porcentaje_saldo), 1)
            ]
        ];

        $kardex[] = [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'saldo_anterior' => round($saldoAnt, 2),
            'recibido' => round($recibido, 2),
            'consumo_teorico' => round($consumo, 2),
            'saldo_final' => round($saldoFinal, 2)
        ];

        // Alertas específicas
        if ($saldoSala > 500) {
            $yarnAlerts[] = ['type' => 'warning', 'msg' => "Hilo $codigo con saldo muy alto en sala ($saldoSala Kg).", 'code' => 'HIGH_HALL_STOCK'];
        }
        if ($recibido > 0 && $consumo == 0) {
            $yarnAlerts[] = ['type' => 'info', 'msg' => "Hilo $codigo recibido pero sin consumo registrado en el período.", 'code' => 'YARN_NOT_USED'];
        }
    }

    $saldoProcesoTotal = $totalAnt + $totalRecibido - $totalConsumo;
    $saldoMaquinasTotal = array_sum(array_column($detalle, 'saldo_maquina'));

    return [
        'kpis' => [
            'hilo_recibido' => round($totalRecibido, 2),
            'hilo_consumo_teorico' => round($totalConsumo, 2),
            'hilo_saldo_proceso' => round($saldoProcesoTotal, 2),
            'hilo_saldo_maquinas' => round($saldoMaquinasTotal, 2),
            'hilo_saldo_sala' => round(max(0, $saldoProcesoTotal - $saldoMaquinasTotal), 2)
        ],
        'detalle' => $detalle,
        'kardex' => $kardex,
        'alerts' => $yarnAlerts
    ];
}
?>
