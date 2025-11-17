<?php
/**
 * API: Vaporizado
 * Sistema MES Hermen Ltda.
 * Fecha: 16 de Noviembre de 2025
 * Versión: 1.0
 */

ob_start();
ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once '../config/database.php';
    
    if (!isLoggedIn()) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit();
    }
    
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
            
        case 'POST':
            handlePost($db);
            break;
            
        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
    
} catch(PDOException $e) {
    error_log("Error en vaporizado.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos',
        'error' => $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("Error en vaporizado.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();
exit();


/**
 * Manejar peticiones GET
 */
function handleGet($db) {
    $action = $_GET['action'] ?? 'listar';
    
    switch ($action) {
        case 'listar':
            listarVaporizados($db);
            break;
            
        case 'stock_revisado':
            obtenerStockRevisado($db);
            break;
            
        case 'detalle':
            obtenerDetalle($db);
            break;
            
        case 'estadisticas':
            obtenerEstadisticas($db);
            break;
            
        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
}

/**
 * Listar registros de vaporizado
 */
function listarVaporizados($db) {
    $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
    $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
    $id_turno = $_GET['id_turno'] ?? null;
    
    $sql = "
        SELECT 
            v.id_vaporizado,
            v.codigo_lote_vaporizado,
            v.fecha_vaporizado,
            v.id_turno,
            t.nombre_turno,
            v.tiempo_vapor,
            u.nombre_completo as operario,
            v.observaciones,
            COUNT(d.id_detalle_vaporizado) as total_productos,
            SUM(d.total_unidades_calculado) as total_unidades,
            SUM(CASE WHEN d.tiene_observaciones = 1 THEN d.total_unidades_calculado ELSE 0 END) as con_observaciones
        FROM produccion_vaporizado v
        INNER JOIN turnos t ON v.id_turno = t.id_turno
        INNER JOIN usuarios u ON v.id_operario = u.id_usuario
        LEFT JOIN detalle_vaporizado d ON v.id_vaporizado = d.id_vaporizado
        WHERE v.fecha_vaporizado >= ? AND v.fecha_vaporizado <= ?
    ";
    
    $params = [$fecha_desde, $fecha_hasta];
    
    if ($id_turno) {
        $sql .= " AND v.id_turno = ?";
        $params[] = $id_turno;
    }
    
    $sql .= " GROUP BY v.id_vaporizado ORDER BY v.fecha_vaporizado DESC, v.id_turno DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $vaporizados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convertir unidades a formato docenas|unidades
    foreach ($vaporizados as &$vap) {
        $total = $vap['total_unidades'] ?? 0;
        $vap['docenas'] = intdiv($total, 12);
        $vap['unidades'] = $total % 12;
        
        // Validar rango ideal (40-90 docenas)
        if ($vap['docenas'] >= 40 && $vap['docenas'] <= 90) {
            $vap['rango_estado'] = 'ideal';
        } elseif ($vap['docenas'] < 40) {
            $vap['rango_estado'] = 'bajo';
        } else {
            $vap['rango_estado'] = 'alto';
        }
    }
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'vaporizados' => $vaporizados
    ]);
}

/**
 * Obtener stock disponible en Revisado
 */
function obtenerStockRevisado($db) {
    $id_linea = $_GET['id_linea'] ?? null;
    
    $sql = "
        SELECT 
            i.id_inventario,
            i.id_producto,
            p.codigo_producto,
            p.descripcion_completa,
            p.talla,
            l.id_linea,
            l.nombre_linea,
            l.codigo_linea,
            tp.nombre_tipo,
            i.docenas,
            i.unidades,
            i.total_unidades_calculado,
            i.fecha_actualizacion
        FROM inventario_intermedio i
        INNER JOIN productos_tejidos p ON i.id_producto = p.id_producto
        INNER JOIN lineas_producto l ON p.id_linea = l.id_linea
        INNER JOIN tipos_producto tp ON p.id_tipo_producto = tp.id_tipo_producto
        WHERE i.tipo_inventario = 'revisado'
        AND i.total_unidades_calculado > 0
        AND p.activo = 1
    ";
    
    $params = [];
    
    if ($id_linea) {
        $sql .= " AND l.id_linea = ?";
        $params[] = $id_linea;
    }
    
    $sql .= " ORDER BY l.nombre_linea, p.descripcion_completa";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'stock' => $stock
    ]);
}

/**
 * Obtener detalle de un vaporizado
 */
function obtenerDetalle($db) {
    $id_vaporizado = $_GET['id_vaporizado'] ?? null;
    
    if (!$id_vaporizado) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        return;
    }
    
    // Obtener cabecera
    $stmt = $db->prepare("
        SELECT 
            v.*,
            t.nombre_turno,
            u.nombre_completo as operario
        FROM produccion_vaporizado v
        INNER JOIN turnos t ON v.id_turno = t.id_turno
        INNER JOIN usuarios u ON v.id_operario = u.id_usuario
        WHERE v.id_vaporizado = ?
    ");
    $stmt->execute([$id_vaporizado]);
    $vaporizado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener detalle
    $stmt = $db->prepare("
        SELECT 
            d.*,
            p.codigo_producto,
            p.descripcion_completa,
            p.talla,
            l.nombre_linea,
            l.codigo_linea,
            u.nombre_completo as revisadora
        FROM detalle_vaporizado d
        INNER JOIN productos_tejidos p ON d.id_producto = p.id_producto
        INNER JOIN lineas_producto l ON p.id_linea = l.id_linea
        INNER JOIN usuarios u ON d.id_revisadora = u.id_usuario
        WHERE d.id_vaporizado = ?
    ");
    $stmt->execute([$id_vaporizado]);
    $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'vaporizado' => $vaporizado,
        'detalle' => $detalle
    ]);
}

/**
 * Obtener estadísticas
 */
function obtenerEstadisticas($db) {
    // Total en inventario vaporizado
    $stmt = $db->query("
        SELECT 
            COUNT(DISTINCT id_producto) as productos,
            SUM(total_unidades_calculado) as total_unidades
        FROM inventario_intermedio
        WHERE tipo_inventario = 'vaporizado'
    ");
    $inventario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Batches del mes actual
    $stmt = $db->query("
        SELECT COUNT(*) as total_batches
        FROM produccion_vaporizado
        WHERE MONTH(fecha_vaporizado) = MONTH(CURRENT_DATE())
        AND YEAR(fecha_vaporizado) = YEAR(CURRENT_DATE())
    ");
    $batches = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_calc = $inventario['total_unidades'] ?? 0;
    $docenas = intdiv($total_calc, 12);
    $unidades = $total_calc % 12;
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'estadisticas' => [
            'inventario_vaporizado' => [
                'productos' => $inventario['productos'] ?? 0,
                'docenas' => $docenas,
                'unidades' => $unidades,
                'total_unidades_calc' => $total_calc
            ],
            'batches_mes' => $batches['total_batches'] ?? 0
        ]
    ]);
}

/**
 * Manejar peticiones POST
 */
function handlePost($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        return;
    }
    
    registrarVaporizado($db, $data);
}

/**
 * Registrar batch de vaporizado
 */
function registrarVaporizado($db, $data) {
    $fecha_vaporizado = $data['fecha_vaporizado'] ?? null;
    $id_turno = $data['id_turno'] ?? null;
    $id_operario = $data['id_operario'] ?? null;
    $tiempo_vapor = $data['tiempo_vapor'] ?? 35;
    $observaciones = $data['observaciones'] ?? '';
    $detalle = $data['detalle'] ?? [];
    
    $id_usuario = $_SESSION['user_id'];
    
    // Validaciones
    if (!$fecha_vaporizado || !$id_turno || !$id_operario) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
        return;
    }
    
    if (empty($detalle)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Debe incluir al menos un producto']);
        return;
    }
    
    // Calcular total del batch
    $total_batch_unidades = 0;
    foreach ($detalle as $item) {
        $docenas = intval($item['docenas'] ?? 0);
        $unidades = intval($item['unidades'] ?? 0);
        $total_batch_unidades += ($docenas * 12) + $unidades;
    }
    
    $total_batch_docenas = intdiv($total_batch_unidades, 12);
    
    // Validar rango ideal (40-90 docenas)
    if ($total_batch_docenas < 40 || $total_batch_docenas > 90) {
        // Advertencia pero no bloquear
        error_log("Advertencia: Batch fuera de rango ideal (40-90): $total_batch_docenas docenas");
    }
    
    try {
        $db->beginTransaction();
        
        // Generar código de lote
        $fecha_corta = date('dmy', strtotime($fecha_vaporizado));
        $codigo_lote = "VAP-$fecha_corta-$id_turno";
        
        // Insertar cabecera
        $stmt = $db->prepare("
            INSERT INTO produccion_vaporizado (
                codigo_lote_vaporizado, fecha_vaporizado, id_turno, 
                id_operario, tiempo_vapor, observaciones
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $codigo_lote, $fecha_vaporizado, $id_turno, 
            $id_operario, $tiempo_vapor, $observaciones
        ]);
        $id_vaporizado = $db->lastInsertId();
        
        // Insertar detalle y mover inventario
        foreach ($detalle as $item) {
            $id_producto = $item['id_producto'];
            $id_revisadora = $item['id_revisadora'] ?? $id_usuario;
            $docenas = intval($item['docenas']);
            $unidades = intval($item['unidades']);
            $tiene_obs = $item['tiene_observaciones'] ?? 0;
            $obs = $item['observaciones'] ?? '';
            
            // Validar unidades
            if ($unidades < 0 || $unidades > 11) {
                throw new Exception("Unidades deben estar entre 0 y 11");
            }
            
            $total_unidades = ($docenas * 12) + $unidades;
            
            if ($total_unidades == 0) continue;
            
            // Insertar detalle
            $stmt = $db->prepare("
                INSERT INTO detalle_vaporizado (
                    id_vaporizado, id_producto, id_revisadora,
                    docenas_vaporizadas, unidades_vaporizadas, total_unidades_calculado,
                    tiene_observaciones, observaciones
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id_vaporizado, $id_producto, $id_revisadora,
                $docenas, $unidades, $total_unidades,
                $tiene_obs, $obs
            ]);
            
            // Movimiento 1: SALIDA de Revisado
            $stmt = $db->prepare("
                CALL sp_registrar_movimiento(
                    ?, 'salida', 'revisado', ?, ?, 
                    'Inventario Revisado', ?, ?, NULL,
                    'Salida para vaporizado',
                    @resultado, @id_movimiento
                )
            ");
            $stmt->execute([
                $id_producto, $docenas, $unidades,
                'Vaporizado ' . $codigo_lote, $id_usuario
            ]);
            
            // Movimiento 2: ENTRADA a Vaporizado
            $obs_vap = "Batch vaporizado $tiempo_vapor min";
            if ($tiene_obs) {
                $obs_vap .= " - CON OBSERVACIONES";
            }
            
            $stmt = $db->prepare("
                CALL sp_registrar_movimiento(
                    ?, 'entrada', 'vaporizado', ?, ?, 
                    ?, 'Inventario Vaporizado', ?, NULL,
                    ?,
                    @resultado, @id_movimiento
                )
            ");
            $stmt->execute([
                $id_producto, $docenas, $unidades,
                'Vaporizado ' . $codigo_lote, $id_usuario,
                $obs_vap
            ]);
        }
        
        $db->commit();
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Vaporizado registrado exitosamente',
            'id_vaporizado' => $id_vaporizado,
            'codigo_lote' => $codigo_lote,
            'total_docenas' => $total_batch_docenas,
            'rango_estado' => $total_batch_docenas >= 40 && $total_batch_docenas <= 90 ? 'ideal' : 'advertencia'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Error al registrar: ' . $e->getMessage()
        ]);
    }
}
?>