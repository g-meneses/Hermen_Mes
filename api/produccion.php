<?php
/**
 * API REST para Registro de Producción por Turno
 * Sistema MES Hermen Ltda.
 * Maneja las tablas: produccion_tejeduria y detalle_produccion_tejeduria
 * 
 * VERSIÓN CORREGIDA v1.2
 * - Reemplazada columna inexistente fecha_creacion por fecha_produccion
 * - Query de último registro optimizada con id_produccion
 * - Manejo de errores mejorado
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
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit();
    }
    
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['id_produccion'])) {
                // Obtener detalle de una producción específica
                $id_produccion = (int)$_GET['id_produccion'];
                
                $stmt = $db->prepare("
                    SELECT 
                        p.*,
                        t.nombre_turno,
                        t.hora_inicio,
                        t.hora_fin,
                        u1.nombre_completo as nombre_tejedor,
                        u2.nombre_completo as nombre_tecnico
                    FROM produccion_tejeduria p
                    JOIN turnos t ON p.id_turno = t.id_turno
                    JOIN usuarios u1 ON p.id_tejedor = u1.id_usuario
                    LEFT JOIN usuarios u2 ON p.id_tecnico = u2.id_usuario
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
                        dp.*,
                        m.numero_maquina,
                        pt.codigo_producto,
                        pt.descripcion_completa,
                        l.codigo_linea,
                        l.nombre_linea
                    FROM detalle_produccion_tejeduria dp
                    JOIN maquinas m ON dp.id_maquina = m.id_maquina
                    JOIN productos_tejidos pt ON dp.id_producto = pt.id_producto
                    JOIN lineas_producto l ON pt.id_linea = l.id_linea
                    WHERE dp.id_produccion = ?
                    ORDER BY m.numero_maquina
                ");
                $stmt->execute([$id_produccion]);
                $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'produccion' => $produccion,
                    'detalle' => $detalle
                ]);
                
            } elseif (isset($_GET['action']) && $_GET['action'] === 'turno_anterior') {
                // Endpoint para importar turno anterior
                $fecha = $_GET['fecha'] ?? date('Y-m-d');
                $id_turno = (int)($_GET['id_turno'] ?? 0);
                
                if (!$id_turno) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'ID de turno requerido']);
                    exit();
                }
                
                // Determinar el turno anterior
                $fecha_busqueda = $fecha;
                $turno_anterior = $id_turno;
                
                if ($id_turno == 1) {
                    $fecha_busqueda = date('Y-m-d', strtotime($fecha . ' -1 day'));
                    $turno_anterior = 3;
                } elseif ($id_turno == 2) {
                    $turno_anterior = 1;
                } elseif ($id_turno == 3) {
                    $turno_anterior = 2;
                }
                
                $stmt = $db->prepare("
                    SELECT p.id_produccion
                    FROM produccion_tejeduria p
                    WHERE p.fecha_produccion = ? 
                    AND p.id_turno = ?
                    ORDER BY p.id_produccion DESC
                    LIMIT 1
                ");
                $stmt->execute([$fecha_busqueda, $turno_anterior]);
                $produccion_anterior = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($produccion_anterior) {
                    $stmt = $db->prepare("
                        SELECT 
                            dp.id_maquina,
                            dp.id_producto,
                            m.numero_maquina,
                            pt.descripcion_completa
                        FROM detalle_produccion_tejeduria dp
                        JOIN maquinas m ON dp.id_maquina = m.id_maquina
                        JOIN productos_tejidos pt ON dp.id_producto = pt.id_producto
                        WHERE dp.id_produccion = ?
                        ORDER BY m.numero_maquina
                    ");
                    $stmt->execute([$produccion_anterior['id_produccion']]);
                    $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'detalle' => $detalle
                    ]);
                    
                } else {
                    ob_clean();
                    echo json_encode([
                        'success' => false,
                        'message' => 'No se encontró producción del turno anterior'
                    ]);
                }
                
            } elseif (isset($_GET['action']) && $_GET['action'] === 'ultimo_registro') {
                // CORREGIDO: Usar id_produccion en lugar de fecha_creacion
                $stmt = $db->prepare("
                    SELECT p.id_produccion
                    FROM produccion_tejeduria p
                    ORDER BY p.id_produccion DESC
                    LIMIT 1
                ");
                $stmt->execute();
                $ultimo_registro = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($ultimo_registro) {
                    $stmt = $db->prepare("
                        SELECT 
                            dp.id_maquina,
                            dp.id_producto,
                            m.numero_maquina,
                            pt.descripcion_completa
                        FROM detalle_produccion_tejeduria dp
                        JOIN maquinas m ON dp.id_maquina = m.id_maquina
                        JOIN productos_tejidos pt ON dp.id_producto = pt.id_producto
                        WHERE dp.id_produccion = ?
                        ORDER BY m.numero_maquina
                    ");
                    $stmt->execute([$ultimo_registro['id_produccion']]);
                    $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'detalle' => $detalle
                    ]);
                } else {
                    ob_clean();
                    echo json_encode([
                        'success' => false,
                        'message' => 'No se encontró ningún registro de producción anterior'
                    ]);
                }
            } else {
                // Listar producciones con filtros
                $where = [];
                $params = [];
                
                if (isset($_GET['fecha_desde'])) {
                    $where[] = "p.fecha_produccion >= ?";
                    $params[] = $_GET['fecha_desde'];
                }
                
                if (isset($_GET['fecha_hasta'])) {
                    $where[] = "p.fecha_produccion <= ?";
                    $params[] = $_GET['fecha_hasta'];
                }
                
                if (isset($_GET['id_turno'])) {
                    $where[] = "p.id_turno = ?";
                    $params[] = (int)$_GET['id_turno'];
                }
                
                if (isset($_GET['id_tejedor'])) {
                    $where[] = "p.id_tejedor = ?";
                    $params[] = (int)$_GET['id_tejedor'];
                }
                
                $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
                
                $sql = "
                    SELECT 
                        p.id_produccion,
                        p.codigo_lote_turno,
                        p.fecha_produccion,
                        p.estado,
                        p.observaciones,
                        t.nombre_turno,
                        u.nombre_completo as nombre_tejedor,
                        COUNT(dp.id_detalle_produccion) as num_maquinas,
                        SUM(dp.docenas_producidas) as total_docenas,
                        SUM(dp.unidades_producidas) as total_unidades,
                        SUM(dp.total_unidades_calculado) as total_unidades_calc
                    FROM produccion_tejeduria p
                    JOIN turnos t ON p.id_turno = t.id_turno
                    JOIN usuarios u ON p.id_tejedor = u.id_usuario
                    LEFT JOIN detalle_produccion_tejeduria dp ON p.id_produccion = dp.id_produccion
                    $whereClause
                    GROUP BY p.id_produccion
                    ORDER BY p.fecha_produccion DESC, p.id_turno
                    LIMIT 100
                ";
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $producciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Calcular estadísticas
                $fecha_hoy = date('Y-m-d');
                
                // Día
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(DISTINCT p.id_produccion) as total_registros,
                        COALESCE(SUM(dp.docenas_producidas), 0) as total_docenas,
                        COALESCE(SUM(dp.unidades_producidas), 0) as total_unidades,
                        COALESCE(SUM(dp.total_unidades_calculado), 0) as total_unidades_calculado,
                        COUNT(DISTINCT dp.id_maquina) as maquinas_activas
                    FROM produccion_tejeduria p
                    LEFT JOIN detalle_produccion_tejeduria dp ON p.id_produccion = dp.id_produccion
                    WHERE p.fecha_produccion = ?
                ");
                $stmt->execute([$fecha_hoy]);
                $estadisticas_dia = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $total_unidades_calc_dia = $estadisticas_dia['total_unidades_calculado'] ?? 0;
                $docenas_dia = intdiv($total_unidades_calc_dia, 12);
                $unidades_dia = $total_unidades_calc_dia % 12;
                
                // Año 2025
                $stmt = $db->prepare("
                    SELECT 
                        COALESCE(SUM(dp.total_unidades_calculado), 0) as total_unidades_anuales
                    FROM produccion_tejeduria p
                    LEFT JOIN detalle_produccion_tejeduria dp ON p.id_produccion = dp.id_produccion
                    WHERE p.fecha_produccion >= '2025-01-01'
                    AND p.fecha_produccion <= ?
                ");
                $stmt->execute([$fecha_hoy]);
                $estadisticas_anual = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $total_unidades_anuales = $estadisticas_anual['total_unidades_anuales'] ?? 0;
                $docenas_anuales = intdiv($total_unidades_anuales, 12);
                $unidades_anuales = $total_unidades_anuales % 12;
                
                // Mes actual
                $primer_dia_mes = date('Y-m-01', strtotime($fecha_hoy));
                $stmt = $db->prepare("
                    SELECT 
                        COALESCE(SUM(dp.total_unidades_calculado), 0) as total_unidades_mes_actual
                    FROM produccion_tejeduria p
                    LEFT JOIN detalle_produccion_tejeduria dp ON p.id_produccion = dp.id_produccion
                    WHERE p.fecha_produccion >= ?
                    AND p.fecha_produccion <= ?
                ");
                $stmt->execute([$primer_dia_mes, $fecha_hoy]);
                $estadisticas_mes = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $total_unidades_mes = $estadisticas_mes['total_unidades_mes_actual'] ?? 0;
                $docenas_mes = intdiv($total_unidades_mes, 12);
                $unidades_mes = $total_unidades_mes % 12;
                
                $estadisticas = [
                    'total_registros' => $estadisticas_dia['total_registros'] ?? 0,
                    'total_docenas' => $estadisticas_dia['total_docenas'] ?? 0,
                    'total_unidades' => $estadisticas_dia['total_unidades'] ?? 0,
                    'total_unidades_calc' => $estadisticas_dia['total_unidades_calculado'] ?? 0,
                    'maquinas_activas' => $estadisticas_dia['maquinas_activas'] ?? 0,
                    'total_unidades_anuales' => $total_unidades_anuales,
                    'docenas_anuales' => $docenas_anuales,
                    'unidades_anuales' => $unidades_anuales,
                    'docenas_dia' => $docenas_dia,
                    'unidades_dia' => $unidades_dia,
                    'docenas_mes_actual' => $docenas_mes,
                    'unidades_mes_actual' => $unidades_mes
                ];
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'producciones' => $producciones,
                    'estadisticas' => $estadisticas
                ]);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validaciones
            $fecha_produccion = $data['fecha_produccion'] ?? null;
            $id_turno = (int)($data['id_turno'] ?? 0);
            $id_tejedor = (int)($data['id_tejedor'] ?? 0);
            $id_tecnico = isset($data['id_tecnico']) && $data['id_tecnico'] ? (int)$data['id_tecnico'] : null;
            $observaciones = trim($data['observaciones'] ?? '');
            $detalle = $data['detalle'] ?? [];
            
            if (!$fecha_produccion || !$id_turno || !$id_tejedor) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
                exit();
            }
            
            if (empty($detalle)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Debe registrar producción de al menos una máquina']);
                exit();
            }
            
            // Generar código de lote
            $fecha_parts = explode('-', $fecha_produccion);
            $codigo_lote = $fecha_parts[2] . $fecha_parts[1] . substr($fecha_parts[0], 2) . '-' . $id_turno;
            
            // Verificar duplicado
            $stmt = $db->prepare("
                SELECT id_produccion 
                FROM produccion_tejeduria 
                WHERE fecha_produccion = ? AND id_turno = ?
            ");
            $stmt->execute([$fecha_produccion, $id_turno]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                ob_clean();
                echo json_encode([
                    'success' => false, 
                    'message' => 'Ya existe un registro de producción para esta fecha y turno'
                ]);
                exit();
            }
            
            // Iniciar transacción
            $db->beginTransaction();
            
            try {
                // Insertar registro principal
                $stmt = $db->prepare("
                    INSERT INTO produccion_tejeduria (
                        codigo_lote_turno, fecha_produccion, id_turno, 
                        id_tejedor, id_tecnico, estado, observaciones
                    ) VALUES (?, ?, ?, ?, ?, 'completado', ?)
                ");
                $stmt->execute([
                    $codigo_lote, 
                    $fecha_produccion, 
                    $id_turno, 
                    $id_tejedor, 
                    $id_tecnico, 
                    $observaciones
                ]);
                
                $id_produccion = $db->lastInsertId();
                
                // Insertar detalle
                $stmt_detalle = $db->prepare("
                    INSERT INTO detalle_produccion_tejeduria (
                        id_produccion, id_maquina, id_producto, 
                        docenas_producidas, unidades_producidas,
                        total_unidades_calculado,
                        calidad, observaciones
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $registros_insertados = 0;
                foreach ($detalle as $item) {
                    if (isset($item['id_producto']) && $item['id_producto'] > 0) {
                        $docenas = (int)($item['docenas_producidas'] ?? 0);
                        $unidades = (int)($item['unidades_producidas'] ?? 0);
                        
                        if ($unidades < 0 || $unidades > 11) {
                            throw new Exception('Las unidades deben estar entre 0 y 11');
                        }
                        
                        if ($docenas > 0 || $unidades > 0) {
                            $total_unidades_calculado = ($docenas * 12) + $unidades;
                            
                            $stmt_detalle->execute([
                                $id_produccion,
                                (int)$item['id_maquina'],
                                (int)$item['id_producto'],
                                $docenas,
                                $unidades,
                                $total_unidades_calculado,
                                $item['calidad'] ?? 'primera',
                                $item['observaciones'] ?? ''
                            ]);
                            $registros_insertados++;
                        }
                    }
                }
                
                if ($registros_insertados == 0) {
                    throw new Exception('No se registró producción de ninguna máquina');
                }
                
                $db->commit();
                
                // ⭐ INTEGRACIÓN: Registrar en inventario automáticamente
                registrarEnInventarioTejido($db, $id_produccion, $_SESSION['user_id']);  

                ob_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Producción registrada exitosamente',
                    'id_produccion' => $id_produccion,
                    'codigo_lote' => $codigo_lote,
                    'registros' => $registros_insertados
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
            
            $stmt = $db->prepare("DELETE FROM detalle_produccion_tejeduria WHERE id_produccion = ?");
            $stmt->execute([$id_produccion]);
            
            $stmt = $db->prepare("DELETE FROM produccion_tejeduria WHERE id_produccion = ?");
            $stmt->execute([$id_produccion]);
            
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Registro de producción eliminado']);
            break;
            
        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
    
} catch(PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error en produccion.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos',
        'error' => $e->getMessage()
    ]);
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error en produccion.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * ============================================
 * INTEGRACIÓN: Inventario Intermedio
 * ============================================
 */

/**
 * Registrar automáticamente en Inventario Intermedio (Tejido)
 */
function registrarEnInventarioTejido($db, $id_produccion, $id_usuario) {
    try {
        // Obtener los detalles de la producción recién guardada
        $stmt = $db->prepare("
            SELECT 
                d.id_producto,
                d.docenas_producidas,
                d.unidades_producidas,
                p.codigo_lote_turno
            FROM detalle_produccion_tejeduria d
            INNER JOIN produccion_tejeduria p ON d.id_produccion = p.id_produccion
            WHERE d.id_produccion = ?
        ");
        $stmt->execute([$id_produccion]);
        $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Registrar cada producto en inventario
        foreach ($detalles as $detalle) {
            // Solo registrar si hay cantidad producida
            if ($detalle['docenas_producidas'] > 0 || $detalle['unidades_producidas'] > 0) {
                
                // Usar el procedimiento almacenado para registrar el movimiento
                $stmt = $db->prepare("
                    CALL sp_registrar_movimiento(
                        ?, 'entrada', 'tejido', ?, ?, ?, 
                        'Inventario Tejido', ?, ?, 
                        'Entrada automática desde producción',
                        @resultado, @id_movimiento
                    )
                ");
                
                $stmt->execute([
                    $detalle['id_producto'],
                    $detalle['docenas_producidas'],
                    $detalle['unidades_producidas'],
                    'Producción ' . $detalle['codigo_lote_turno'],
                    $id_usuario,
                    $id_produccion
                ]);
                
                // Obtener resultado (opcional, para debug)
                $result = $db->query("SELECT @resultado as resultado")->fetch(PDO::FETCH_ASSOC);
                
                // Log del resultado
                if ($result['resultado'] !== 'EXITO') {
                    error_log("Advertencia inventario: " . $result['resultado']);
                }
            }
        }
        
        return true;
        
    } catch (PDOException $e) {
        // No fallar la producción si falla el inventario
        error_log("Error al registrar en inventario: " . $e->getMessage());
        return false;
    }
}

ob_end_flush();
exit();