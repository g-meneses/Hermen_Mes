<?php
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
            // Endpoint: Obtener último registro
            if (isset($_GET['ultimo']) && $_GET['ultimo'] === 'true') {
                $stmt = $db->query("
                    SELECT p.*, 
                           t.nombre_turno,
                           u.nombre_completo as nombre_tejedor
                    FROM produccion_tejeduria p
                    LEFT JOIN turnos t ON p.id_turno = t.id_turno
                    LEFT JOIN usuarios u ON p.id_tejedor = u.id_usuario
                    ORDER BY p.fecha_produccion DESC, p.id_produccion DESC 
                    LIMIT 1
                ");
                $produccion = $stmt->fetch();
                
                if ($produccion) {
                    $stmtDetalles = $db->prepare("
                        SELECT d.*,
                               m.numero_maquina,
                               p.codigo_producto,
                               p.descripcion_completa
                        FROM detalle_produccion_tejeduria d
                        INNER JOIN maquinas m ON d.id_maquina = m.id_maquina
                        INNER JOIN productos_tejidos p ON d.id_producto = p.id_producto
                        WHERE d.id_produccion = ?
                        ORDER BY m.numero_maquina
                    ");
                    $stmtDetalles->execute([$produccion['id_produccion']]);
                    $detalles = $stmtDetalles->fetchAll();
                    
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'produccion' => $produccion,
                        'detalles' => $detalles
                    ]);
                } else {
                    ob_clean();
                    echo json_encode([
                        'success' => false,
                        'message' => 'No hay registros previos'
                    ]);
                }
                exit();
            }
            
            // Endpoint: Obtener detalle de una producción específica
            if (isset($_GET['id_produccion']) && $_GET['id_produccion']) {
                $id_produccion = (int)$_GET['id_produccion'];
                
                $stmt = $db->prepare("
                    SELECT 
                        p.id_produccion,
                        p.codigo_lote,
                        p.fecha_produccion,
                        p.id_turno,
                        p.id_tejedor,
                        p.observaciones,
                        p.fecha_creacion,
                        t.nombre_turno,
                        t.hora_inicio,
                        t.hora_fin,
                        u.nombre_completo as nombre_tejedor
                    FROM produccion_tejeduria p
                    JOIN turnos t ON p.id_turno = t.id_turno
                    LEFT JOIN usuarios u ON p.id_tejedor = u.id_usuario
                    WHERE p.id_produccion = ?
                ");
                $stmt->execute([$id_produccion]);
                $produccion = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$produccion) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Producción no encontrada']);
                    exit();
                }
                
                $stmt = $db->prepare("
                    SELECT 
                        dp.id_detalle,
                        dp.id_maquina,
                        dp.id_producto,
                        dp.docenas,
                        dp.unidades,
                        (dp.docenas * 12 + dp.unidades) as total_unidades,
                        m.numero_maquina,
                        pt.descripcion_completa,
                        pt.codigo_producto,
                        l.nombre_linea,
                        l.codigo_linea
                    FROM detalle_produccion_tejeduria dp
                    JOIN maquinas m ON dp.id_maquina = m.id_maquina
                    JOIN productos_tejidos pt ON dp.id_producto = pt.id_producto
                    JOIN lineas_producto l ON pt.id_linea = l.id_linea
                    WHERE dp.id_produccion = ?
                    ORDER BY m.numero_maquina
                ");
                $stmt->execute([$id_produccion]);
                $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'produccion' => $produccion,
                    'detalles' => $detalles
                ]);
                break;
            }
            
            // Endpoint: Listar producciones con filtros
            $fecha_desde = $_GET['fecha_desde'] ?? '';
            $fecha_hasta = $_GET['fecha_hasta'] ?? '';
            $id_turno = $_GET['id_turno'] ?? '';
            
            $sql = "
                SELECT 
                    p.id_produccion,
                    p.codigo_lote,
                    p.fecha_produccion,
                    p.observaciones,
                    t.nombre_turno,
                    t.hora_inicio,
                    t.hora_fin,
                    u.nombre_completo as nombre_tejedor,
                    COUNT(dp.id_detalle) as num_maquinas,
                    FLOOR(COALESCE(SUM(dp.docenas * 12 + dp.unidades), 0) / 12) as total_docenas,
                    COALESCE(SUM(dp.docenas * 12 + dp.unidades), 0) % 12 as total_unidades,
                    COALESCE(SUM(dp.docenas * 12 + dp.unidades), 0) as total_unidades_calc
                FROM produccion_tejeduria p
                JOIN turnos t ON p.id_turno = t.id_turno
                LEFT JOIN usuarios u ON p.id_tejedor = u.id_usuario
                LEFT JOIN detalle_produccion_tejeduria dp ON p.id_produccion = dp.id_produccion
                WHERE 1=1
            ";
            
            $params = [];
            
            if ($fecha_desde) {
                $sql .= " AND p.fecha_produccion >= ?";
                $params[] = $fecha_desde;
            }
            
            if ($fecha_hasta) {
                $sql .= " AND p.fecha_produccion <= ?";
                $params[] = $fecha_hasta;
            }
            
            if ($id_turno) {
                $sql .= " AND p.id_turno = ?";
                $params[] = $id_turno;
            }
            
            $sql .= " GROUP BY p.id_produccion, p.codigo_lote, p.fecha_produccion, 
                      p.observaciones, t.nombre_turno, t.hora_inicio, t.hora_fin, u.nombre_completo
                      ORDER BY p.fecha_produccion DESC, p.id_produccion DESC
                      LIMIT 100";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $producciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Estadísticas
            $stmt = $db->query("
                SELECT 
                    FLOOR(COALESCE(SUM(dp.docenas * 12 + dp.unidades), 0) / 12) as docenas_anuales,
                    COALESCE(SUM(dp.docenas * 12 + dp.unidades), 0) % 12 as unidades_anuales,
                    FLOOR(COALESCE(SUM(CASE WHEN DATE(p.fecha_produccion) = CURDATE() THEN dp.docenas * 12 + dp.unidades ELSE 0 END), 0) / 12) as docenas_hoy,
                    COALESCE(SUM(CASE WHEN DATE(p.fecha_produccion) = CURDATE() THEN dp.docenas * 12 + dp.unidades ELSE 0 END), 0) % 12 as unidades_hoy,
                    FLOOR(COALESCE(SUM(CASE WHEN YEARWEEK(p.fecha_produccion, 1) = YEARWEEK(CURDATE(), 1) THEN dp.docenas * 12 + dp.unidades ELSE 0 END), 0) / 12) as docenas_semana,
                    COALESCE(SUM(CASE WHEN YEARWEEK(p.fecha_produccion, 1) = YEARWEEK(CURDATE(), 1) THEN dp.docenas * 12 + dp.unidades ELSE 0 END), 0) % 12 as unidades_semana,
                    FLOOR(COALESCE(SUM(CASE WHEN YEAR(p.fecha_produccion) = YEAR(CURDATE()) AND MONTH(p.fecha_produccion) = MONTH(CURDATE()) THEN dp.docenas * 12 + dp.unidades ELSE 0 END), 0) / 12) as docenas_mes,
                    COALESCE(SUM(CASE WHEN YEAR(p.fecha_produccion) = YEAR(CURDATE()) AND MONTH(p.fecha_produccion) = MONTH(CURDATE()) THEN dp.docenas * 12 + dp.unidades ELSE 0 END), 0) % 12 as unidades_mes,
                    COUNT(DISTINCT p.id_produccion) as total_registros,
                    COUNT(DISTINCT dp.id_maquina) as maquinas_activas
                FROM produccion_tejeduria p
                LEFT JOIN detalle_produccion_tejeduria dp ON p.id_produccion = dp.id_produccion
                WHERE YEAR(p.fecha_produccion) = YEAR(CURDATE())
            ");
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$stats) {
                $stats = [
                    'docenas_anuales' => 0, 'unidades_anuales' => 0,
                    'docenas_hoy' => 0, 'unidades_hoy' => 0,
                    'docenas_semana' => 0, 'unidades_semana' => 0,
                    'docenas_mes' => 0, 'unidades_mes' => 0,
                    'total_registros' => 0, 'maquinas_activas' => 0
                ];
            }
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'producciones' => $producciones,
                'estadisticas' => $stats
            ]);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $id_produccion = $data['id_produccion'] ?? null;
            $codigo_lote = trim($data['codigo_lote'] ?? '');
            $fecha_produccion = $data['fecha_produccion'] ?? '';
            $id_turno = (int)($data['id_turno'] ?? 0);
            $id_tejedor = $data['id_tejedor'] ? (int)$data['id_tejedor'] : null;
            $observaciones = trim($data['observaciones'] ?? '');
            $detalles = $data['detalles'] ?? [];
            
            if (empty($codigo_lote) || empty($fecha_produccion) || !$id_turno) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
                exit();
            }
            
            if (empty($detalles) || !is_array($detalles)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Debe agregar al menos una máquina']);
                exit();
            }
            
            $db->beginTransaction();
            
            try {
                if ($id_produccion) {
                    $stmt = $db->prepare("
                        UPDATE produccion_tejeduria SET
                            codigo_lote = ?, fecha_produccion = ?, id_turno = ?,
                            id_tejedor = ?, observaciones = ?
                        WHERE id_produccion = ?
                    ");
                    $stmt->execute([$codigo_lote, $fecha_produccion, $id_turno, $id_tejedor, $observaciones, $id_produccion]);
                    
                    $stmt = $db->prepare("DELETE FROM detalle_produccion_tejeduria WHERE id_produccion = ?");
                    $stmt->execute([$id_produccion]);
                } else {
                    $stmt = $db->prepare("SELECT id_produccion FROM produccion_tejeduria WHERE codigo_lote = ?");
                    $stmt->execute([$codigo_lote]);
                    if ($stmt->fetch()) {
                        throw new Exception('Ya existe una producción con ese código de lote');
                    }
                    
                    $stmt = $db->prepare("
                        INSERT INTO produccion_tejeduria (codigo_lote, fecha_produccion, id_turno, id_tejedor, observaciones)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$codigo_lote, $fecha_produccion, $id_turno, $id_tejedor, $observaciones]);
                    $id_produccion = $db->lastInsertId();
                }
                
                $stmt_detalle = $db->prepare("
                    INSERT INTO detalle_produccion_tejeduria (id_produccion, id_maquina, id_producto, docenas, unidades)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt_inv_check = $db->prepare("
                    SELECT id_inventario, docenas, unidades 
                    FROM inventario_intermedio 
                    WHERE id_producto = ? AND tipo_inventario = 'tejido'
                ");
                
                $stmt_inv_update = $db->prepare("
                    UPDATE inventario_intermedio SET docenas = ?, unidades = ? WHERE id_inventario = ?
                ");
                
                $stmt_inv_insert = $db->prepare("
                    INSERT INTO inventario_intermedio (id_producto, tipo_inventario, docenas, unidades) 
                    VALUES (?, 'tejido', ?, ?)
                ");
                
                $count = 0;
                foreach ($detalles as $detalle) {
                    $id_maquina = (int)($detalle['id_maquina'] ?? 0);
                    $id_producto = (int)($detalle['id_producto'] ?? 0);
                    $docenas = (int)($detalle['docenas'] ?? 0);
                    $unidades = (int)($detalle['unidades'] ?? 0);
                    
                    if ($unidades > 11) {
                        throw new Exception("Unidades no pueden ser mayores a 11");
                    }
                    
                    if ($id_maquina && $id_producto && ($docenas > 0 || $unidades > 0)) {
                        $stmt_detalle->execute([$id_produccion, $id_maquina, $id_producto, $docenas, $unidades]);
                        
                        $stmt_inv_check->execute([$id_producto]);
                        $inv = $stmt_inv_check->fetch(PDO::FETCH_ASSOC);
                        
                        if ($inv) {
                            $total_act = ($inv['docenas'] * 12) + $inv['unidades'];
                            $total_new = ($docenas * 12) + $unidades;
                            $total_fin = $total_act + $total_new;
                            $doc_fin = floor($total_fin / 12);
                            $uni_fin = $total_fin % 12;
                            $stmt_inv_update->execute([$doc_fin, $uni_fin, $inv['id_inventario']]);
                        } else {
                            $stmt_inv_insert->execute([$id_producto, $docenas, $unidades]);
                        }
                        
                        $count++;
                    }
                }
                
                if ($count == 0) {
                    throw new Exception('Debe registrar producción en al menos una máquina');
                }
                
                $db->commit();
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'message' => $id_produccion ? 'Producción actualizada' : 'Producción registrada',
                    'id_produccion' => $id_produccion
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id_produccion = $data['id_produccion'] ?? null;
            
            if (!$id_produccion) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'ID requerido']);
                exit();
            }
            
            $db->beginTransaction();
            
            try {
                $stmt = $db->prepare("SELECT id_producto, docenas, unidades FROM detalle_produccion_tejeduria WHERE id_produccion = ?");
                $stmt->execute([$id_produccion]);
                $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt_inv_check = $db->prepare("SELECT id_inventario, docenas, unidades FROM inventario_intermedio WHERE id_producto = ? AND tipo_inventario = 'tejido'");
                $stmt_inv_update = $db->prepare("UPDATE inventario_intermedio SET docenas = ?, unidades = ? WHERE id_inventario = ?");
                
                foreach ($detalles as $det) {
                    $stmt_inv_check->execute([$det['id_producto']]);
                    $inv = $stmt_inv_check->fetch(PDO::FETCH_ASSOC);
                    
                    if ($inv) {
                        $total_act = ($inv['docenas'] * 12) + $inv['unidades'];
                        $total_res = ($det['docenas'] * 12) + $det['unidades'];
                        $total_fin = max(0, $total_act - $total_res);
                        $doc_fin = floor($total_fin / 12);
                        $uni_fin = $total_fin % 12;
                        $stmt_inv_update->execute([$doc_fin, $uni_fin, $inv['id_inventario']]);
                    }
                }
                
                $stmt = $db->prepare("DELETE FROM detalle_produccion_tejeduria WHERE id_produccion = ?");
                $stmt->execute([$id_produccion]);
                
                $stmt = $db->prepare("DELETE FROM produccion_tejeduria WHERE id_produccion = ?");
                $stmt->execute([$id_produccion]);
                
                $db->commit();
                
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Producción eliminada']);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
    
} catch(PDOException $e) {
    error_log("Error PDO: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
} catch(Exception $e) {
    error_log("Error: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

ob_end_flush();
exit();