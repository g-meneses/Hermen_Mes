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
            // Verificar si es solicitud de detalle
            if (isset($_GET['id_produccion']) && $_GET['id_produccion']) {
                $id_produccion = (int)$_GET['id_produccion'];
                
                // Obtener datos de la producción
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
                
                // Obtener detalle de producción
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
            
            // Listar producciones con filtros
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
            
            // Obtener estadísticas normalizadas
            $stmt = $db->query("
                SELECT 
                    -- Anuales normalizadas
                    FLOOR(COALESCE(SUM(dp.docenas * 12 + dp.unidades), 0) / 12) as docenas_anuales,
                    COALESCE(SUM(dp.docenas * 12 + dp.unidades), 0) % 12 as unidades_anuales,
                    
                    -- Hoy normalizadas
                    FLOOR(COALESCE(SUM(CASE WHEN DATE(p.fecha_produccion) = CURDATE() THEN dp.docenas * 12 + dp.unidades ELSE 0 END), 0) / 12) as docenas_hoy,
                    COALESCE(SUM(CASE WHEN DATE(p.fecha_produccion) = CURDATE() THEN dp.docenas * 12 + dp.unidades ELSE 0 END), 0) % 12 as unidades_hoy,
                    
                    -- Semana normalizadas
                    FLOOR(COALESCE(SUM(CASE WHEN YEARWEEK(p.fecha_produccion, 1) = YEARWEEK(CURDATE(), 1) THEN dp.docenas * 12 + dp.unidades ELSE 0 END), 0) / 12) as docenas_semana,
                    COALESCE(SUM(CASE WHEN YEARWEEK(p.fecha_produccion, 1) = YEARWEEK(CURDATE(), 1) THEN dp.docenas * 12 + dp.unidades ELSE 0 END), 0) % 12 as unidades_semana,
                    
                    -- Mes normalizadas
                    FLOOR(COALESCE(SUM(CASE WHEN YEAR(p.fecha_produccion) = YEAR(CURDATE()) AND MONTH(p.fecha_produccion) = MONTH(CURDATE()) THEN dp.docenas * 12 + dp.unidades ELSE 0 END), 0) / 12) as docenas_mes,
                    COALESCE(SUM(CASE WHEN YEAR(p.fecha_produccion) = YEAR(CURDATE()) AND MONTH(p.fecha_produccion) = MONTH(CURDATE()) THEN dp.docenas * 12 + dp.unidades ELSE 0 END), 0) % 12 as unidades_mes,
                    
                    -- Contadores
                    COUNT(DISTINCT p.id_produccion) as total_registros,
                    COUNT(DISTINCT dp.id_maquina) as maquinas_activas
                FROM produccion_tejeduria p
                LEFT JOIN detalle_produccion_tejeduria dp ON p.id_produccion = dp.id_produccion
                WHERE YEAR(p.fecha_produccion) = YEAR(CURDATE())
            ");
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Si no hay estadísticas, devolver valores en cero
            if (!$stats) {
                $stats = [
                    'docenas_anuales' => 0,
                    'unidades_anuales' => 0,
                    'total_registros' => 0,
                    'docenas_hoy' => 0,
                    'unidades_hoy' => 0,
                    'docenas_semana' => 0,
                    'unidades_semana' => 0,
                    'docenas_mes' => 0,
                    'unidades_mes' => 0,
                    'maquinas_activas' => 0
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
            
            // Validaciones
            if (empty($codigo_lote) || empty($fecha_produccion) || !$id_turno) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos: código de lote, fecha y turno']);
                exit();
            }
            
            if (empty($detalles) || !is_array($detalles)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Debe agregar al menos una máquina con producción']);
                exit();
            }
            
            $db->beginTransaction();
            
            try {
                if ($id_produccion) {
                    // Actualizar
                    $stmt = $db->prepare("
                        UPDATE produccion_tejeduria SET
                            codigo_lote = ?,
                            fecha_produccion = ?,
                            id_turno = ?,
                            id_tejedor = ?,
                            observaciones = ?
                        WHERE id_produccion = ?
                    ");
                    $stmt->execute([
                        $codigo_lote, $fecha_produccion, $id_turno, 
                        $id_tejedor, $observaciones, $id_produccion
                    ]);
                    
                    // Eliminar detalles anteriores
                    $stmt = $db->prepare("DELETE FROM detalle_produccion_tejeduria WHERE id_produccion = ?");
                    $stmt->execute([$id_produccion]);
                    
                } else {
                    // Verificar si ya existe ese código de lote
                    $stmt = $db->prepare("SELECT id_produccion FROM produccion_tejeduria WHERE codigo_lote = ?");
                    $stmt->execute([$codigo_lote]);
                    if ($stmt->fetch()) {
                        throw new Exception('Ya existe una producción con ese código de lote');
                    }
                    
                    // Crear
                    $stmt = $db->prepare("
                        INSERT INTO produccion_tejeduria (
                            codigo_lote, fecha_produccion, id_turno, id_tejedor, observaciones
                        ) VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $codigo_lote, $fecha_produccion, $id_turno, $id_tejedor, $observaciones
                    ]);
                    $id_produccion = $db->lastInsertId();
                }
                
                // Insertar detalles y actualizar inventario
                $stmt_detalle = $db->prepare("
                    INSERT INTO detalle_produccion_tejeduria (
                        id_produccion, id_maquina, id_producto, docenas, unidades
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                
                // Preparar statement para actualizar/crear inventario
                $stmt_inv_check = $db->prepare("
                    SELECT id_inventario, docenas, unidades 
                    FROM inventario_intermedio 
                    WHERE id_producto = ? AND tipo_inventario = 'tejido'
                ");
                
                $stmt_inv_update = $db->prepare("
                    UPDATE inventario_intermedio 
                    SET docenas = ?, unidades = ? 
                    WHERE id_inventario = ?
                ");
                
                $stmt_inv_insert = $db->prepare("
                    INSERT INTO inventario_intermedio (id_producto, tipo_inventario, docenas, unidades) 
                    VALUES (?, 'tejido', ?, ?)
                ");
                
                $count_inserted = 0;
                foreach ($detalles as $detalle) {
                    $id_maquina = (int)($detalle['id_maquina'] ?? 0);
                    $id_producto = (int)($detalle['id_producto'] ?? 0);
                    $docenas = (int)($detalle['docenas'] ?? 0);
                    $unidades = (int)($detalle['unidades'] ?? 0);
                    
                    // Validar que unidades no excedan 11
                    if ($unidades > 11) {
                        throw new Exception("Las unidades no pueden ser mayores a 11. Máquina $id_maquina tiene $unidades unidades.");
                    }
                    
                    // Solo insertar si hay producción
                    if ($id_maquina && $id_producto && ($docenas > 0 || $unidades > 0)) {
                        // Insertar detalle de producción
                        $stmt_detalle->execute([
                            $id_produccion, $id_maquina, $id_producto, $docenas, $unidades
                        ]);
                        
                        // Actualizar inventario intermedio
                        $stmt_inv_check->execute([$id_producto]);
                        $inventario_actual = $stmt_inv_check->fetch(PDO::FETCH_ASSOC);
                        
                        if ($inventario_actual) {
                            // Sumar a inventario existente
                            $total_unidades_actual = ($inventario_actual['docenas'] * 12) + $inventario_actual['unidades'];
                            $total_unidades_nuevas = ($docenas * 12) + $unidades;
                            $total_unidades_final = $total_unidades_actual + $total_unidades_nuevas;
                            
                            $docenas_final = floor($total_unidades_final / 12);
                            $unidades_final = $total_unidades_final % 12;
                            
                            $stmt_inv_update->execute([
                                $docenas_final, 
                                $unidades_final, 
                                $inventario_actual['id_inventario']
                            ]);
                        } else {
                            // Crear nuevo registro de inventario
                            $stmt_inv_insert->execute([$id_producto, $docenas, $unidades]);
                        }
                        
                        $count_inserted++;
                    }
                }
                
                if ($count_inserted == 0) {
                    throw new Exception('Debe registrar producción en al menos una máquina');
                }
                
                $db->commit();
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'message' => $id_produccion ? 'Producción actualizada exitosamente' : 'Producción registrada exitosamente',
                    'id_produccion' => $id_produccion,
                    'detalles_insertados' => $count_inserted
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
                echo json_encode(['success' => false, 'message' => 'ID de producción requerido']);
                exit();
            }
            
            $db->beginTransaction();
            
            try {
                // Obtener detalles antes de eliminar para restar del inventario
                $stmt = $db->prepare("
                    SELECT id_producto, docenas, unidades 
                    FROM detalle_produccion_tejeduria 
                    WHERE id_produccion = ?
                ");
                $stmt->execute([$id_produccion]);
                $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Restar del inventario
                $stmt_inv_check = $db->prepare("
                    SELECT id_inventario, docenas, unidades 
                    FROM inventario_intermedio 
                    WHERE id_producto = ? AND tipo_inventario = 'tejido'
                ");
                
                $stmt_inv_update = $db->prepare("
                    UPDATE inventario_intermedio 
                    SET docenas = ?, unidades = ? 
                    WHERE id_inventario = ?
                ");
                
                foreach ($detalles as $detalle) {
                    $stmt_inv_check->execute([$detalle['id_producto']]);
                    $inventario = $stmt_inv_check->fetch(PDO::FETCH_ASSOC);
                    
                    if ($inventario) {
                        $total_actual = ($inventario['docenas'] * 12) + $inventario['unidades'];
                        $total_restar = ($detalle['docenas'] * 12) + $detalle['unidades'];
                        $total_final = max(0, $total_actual - $total_restar);
                        
                        $docenas_final = floor($total_final / 12);
                        $unidades_final = $total_final % 12;
                        
                        $stmt_inv_update->execute([
                            $docenas_final,
                            $unidades_final,
                            $inventario['id_inventario']
                        ]);
                    }
                }
                
                // Eliminar detalles
                $stmt = $db->prepare("DELETE FROM detalle_produccion_tejeduria WHERE id_produccion = ?");
                $stmt->execute([$id_produccion]);
                
                // Eliminar producción
                $stmt = $db->prepare("DELETE FROM produccion_tejeduria WHERE id_produccion = ?");
                $stmt->execute([$id_produccion]);
                
                $db->commit();
                
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Producción eliminada exitosamente']);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Método HTTP no permitido']);
    }
    
} catch(PDOException $e) {
    error_log("Error PDO en produccion.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("Error en produccion.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
exit();