<?php
/**
 * API: Revisado Crudo
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
    error_log("Error en revisado.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos',
        'error' => $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("Error en revisado.php: " . $e->getMessage());
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
            listarRevisados($db);
            break;
            
        case 'stock_tejido':
            obtenerStockTejido($db);
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
 * Listar registros de revisado
 */
function listarRevisados($db) {
    $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
    $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
    $id_turno = $_GET['id_turno'] ?? null;
    
    $sql = "
        SELECT 
            r.id_revisado,
            r.codigo_lote_revisado,
            r.fecha_revisado,
            r.id_turno,
            t.nombre_turno,
            r.observaciones,
            COUNT(d.id_detalle_revisado) as total_productos,
            SUM(d.total_unidades_calculado) as total_unidades,
            SUM(CASE WHEN d.calidad = 'primera' THEN d.total_unidades_calculado ELSE 0 END) as primera,
            SUM(CASE WHEN d.calidad = 'segunda' THEN d.total_unidades_calculado ELSE 0 END) as segunda,
            SUM(CASE WHEN d.calidad = 'observada' THEN d.total_unidades_calculado ELSE 0 END) as observada,
            SUM(CASE WHEN d.calidad = 'desperdicio' THEN d.total_unidades_calculado ELSE 0 END) as desperdicio
        FROM produccion_revisado_crudo r
        INNER JOIN turnos t ON r.id_turno = t.id_turno
        LEFT JOIN detalle_revisado_crudo d ON r.id_revisado = d.id_revisado
        WHERE r.fecha_revisado >= ? AND r.fecha_revisado <= ?
    ";
    
    $params = [$fecha_desde, $fecha_hasta];
    
    if ($id_turno) {
        $sql .= " AND r.id_turno = ?";
        $params[] = $id_turno;
    }
    
    $sql .= " GROUP BY r.id_revisado ORDER BY r.fecha_revisado DESC, r.id_turno DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $revisados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convertir unidades a formato docenas|unidades
    foreach ($revisados as &$rev) {
        $rev['docenas'] = intdiv($rev['total_unidades'] ?? 0, 12);
        $rev['unidades'] = ($rev['total_unidades'] ?? 0) % 12;
    }
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'revisados' => $revisados
    ]);
}

/**
 * Obtener stock disponible en Tejido
 */
function obtenerStockTejido($db) {
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
            i.total_unidades_calculado
        FROM inventario_intermedio i
        INNER JOIN productos_tejidos p ON i.id_producto = p.id_producto
        INNER JOIN lineas_producto l ON p.id_linea = l.id_linea
        INNER JOIN tipos_producto tp ON p.id_tipo_producto = tp.id_tipo_producto
        WHERE i.tipo_inventario = 'tejido'
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
 * Obtener detalle de un revisado
 */
function obtenerDetalle($db) {
    $id_revisado = $_GET['id_revisado'] ?? null;
    
    if (!$id_revisado) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'ID requerido']);
        return;
    }
    
    // Obtener cabecera
    $stmt = $db->prepare("
        SELECT 
            r.*,
            t.nombre_turno
        FROM produccion_revisado_crudo r
        INNER JOIN turnos t ON r.id_turno = t.id_turno
        WHERE r.id_revisado = ?
    ");
    $stmt->execute([$id_revisado]);
    $revisado = $stmt->fetch(PDO::FETCH_ASSOC);
    
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
        FROM detalle_revisado_crudo d
        INNER JOIN productos_tejidos p ON d.id_producto = p.id_producto
        INNER JOIN lineas_producto l ON p.id_linea = l.id_linea
        INNER JOIN usuarios u ON d.id_revisadora = u.id_usuario
        WHERE d.id_revisado = ?
    ");
    $stmt->execute([$id_revisado]);
    $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'revisado' => $revisado,
        'detalle' => $detalle
    ]);
}

/**
 * Obtener estadísticas
 */
function obtenerEstadisticas($db) {
    // Total en inventario revisado
    $stmt = $db->query("
        SELECT 
            COUNT(DISTINCT id_producto) as productos,
            SUM(total_unidades_calculado) as total_unidades
        FROM inventario_intermedio
        WHERE tipo_inventario = 'revisado'
    ");
    $inventario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_calc = $inventario['total_unidades'] ?? 0;
    $docenas = intdiv($total_calc, 12);
    $unidades = $total_calc % 12;
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'estadisticas' => [
            'inventario_revisado' => [
                'productos' => $inventario['productos'] ?? 0,
                'docenas' => $docenas,
                'unidades' => $unidades,
                'total_unidades_calc' => $total_calc
            ]
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
    
    registrarRevisado($db, $data);
}

/**
 * Registrar revisado crudo
 */
function registrarRevisado($db, $data) {
    $fecha_revisado = $data['fecha_revisado'] ?? null;
    $id_turno = $data['id_turno'] ?? null;
    $observaciones = $data['observaciones'] ?? '';
    $detalle = $data['detalle'] ?? [];
    
    $id_usuario = $_SESSION['user_id'];
    
    // Validaciones
    if (!$fecha_revisado || !$id_turno) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
        return;
    }
    
    if (empty($detalle)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Debe incluir al menos un producto']);
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // Generar código de lote
        $fecha_corta = date('dmy', strtotime($fecha_revisado));
        $codigo_lote = "REV-$fecha_corta-$id_turno";
        
        // Insertar cabecera
        $stmt = $db->prepare("
            INSERT INTO produccion_revisado_crudo (
                codigo_lote_revisado, fecha_revisado, id_turno, observaciones
            ) VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$codigo_lote, $fecha_revisado, $id_turno, $observaciones]);
        $id_revisado = $db->lastInsertId();
        
        // Insertar detalle y mover inventario
        foreach ($detalle as $item) {
            $id_producto = $item['id_producto'];
            $id_revisadora = $item['id_revisadora'];
            $docenas = intval($item['docenas']);
            $unidades = intval($item['unidades']);
            $calidad = $item['calidad'] ?? 'primera';
            $obs = $item['observaciones'] ?? '';
            
            // Validar unidades
            if ($unidades < 0 || $unidades > 11) {
                throw new Exception("Unidades deben estar entre 0 y 11");
            }
            
            $total_unidades = ($docenas * 12) + $unidades;
            
            if ($total_unidades == 0) continue;
            
            // Insertar detalle
            $stmt = $db->prepare("
                INSERT INTO detalle_revisado_crudo (
                    id_revisado, id_producto, id_revisadora,
                    docenas_revisadas, unidades_revisadas, total_unidades_calculado,
                    calidad, observaciones
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id_revisado, $id_producto, $id_revisadora,
                $docenas, $unidades, $total_unidades,
                $calidad, $obs
            ]);
            
            // Movimiento 1: SALIDA de Tejido
            $stmt = $db->prepare("
                CALL sp_registrar_movimiento(
                    ?, 'salida', 'tejido', ?, ?, 
                    'Inventario Tejido', ?, ?, NULL,
                    'Salida para revisado crudo',
                    @resultado, @id_movimiento
                )
            ");
            $stmt->execute([
                $id_producto, $docenas, $unidades,
                'Revisado ' . $codigo_lote, $id_usuario
            ]);
            
            // Solo si calidad es Primera u Observada → va a Revisado
            if (in_array($calidad, ['primera', 'observada'])) {
                // Movimiento 2: ENTRADA a Revisado
                $stmt = $db->prepare("
                    CALL sp_registrar_movimiento(
                        ?, 'entrada', 'revisado', ?, ?, 
                        ?, 'Inventario Revisado', ?, NULL,
                        ?,
                        @resultado, @id_movimiento
                    )
                ");
                $stmt->execute([
                    $id_producto, $docenas, $unidades,
                    'Revisado ' . $codigo_lote, $id_usuario,
                    'Entrada desde revisado - Calidad: ' . $calidad
                ]);
            }
        }
        
        $db->commit();
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Revisado registrado exitosamente',
            'id_revisado' => $id_revisado,
            'codigo_lote' => $codigo_lote
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