<?php
/**
 * API Plan Genérico de Tejido
 * Sistema MES Hermen Ltda.
 */

// Iniciar buffer de salida y limpiarlo
ob_start();
ob_clean();

// Configurar headers ANTES de cualquier output
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Desactivar visualización de errores
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
            if (isset($_GET['vigente'])) {
                // NUEVO: Obtener plan genérico vigente para módulo de producción
                $stmt = $db->prepare("
                    SELECT 
                        pg.*,
                        u.nombre_completo as nombre_usuario
                    FROM plan_generico_tejido pg
                    LEFT JOIN usuarios u ON pg.usuario_creacion = u.id_usuario
                    WHERE pg.estado = 'vigente'
                    ORDER BY pg.fecha_vigencia_inicio DESC
                    LIMIT 1
                ");
                $stmt->execute();
                $plan = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($plan) {
                    // Obtener detalle del plan
                    $stmt = $db->prepare("
                        SELECT 
                            dpg.*,
                            m.numero_maquina,
                            p.codigo_producto,
                            p.descripcion_completa,
                            pn.codigo_producto as codigo_producto_nuevo,
                            pn.descripcion_completa as descripcion_producto_nuevo
                        FROM detalle_plan_generico dpg
                        JOIN maquinas m ON dpg.id_maquina = m.id_maquina
                        LEFT JOIN productos_tejidos p ON dpg.id_producto = p.id_producto
                        LEFT JOIN productos_tejidos pn ON dpg.producto_nuevo = pn.id_producto
                        WHERE dpg.id_plan_generico = ?
                        ORDER BY m.numero_maquina
                    ");
                    $stmt->execute([$plan['id_plan_generico']]);
                    $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'plan' => $plan,
                        'detalle' => $detalle
                    ]);
                } else {
                    ob_clean();
                    echo json_encode([
                        'success' => false,
                        'message' => 'No hay plan genérico vigente'
                    ]);
                }
            } elseif (isset($_GET['action']) && $_GET['action'] === 'plan_actual') {
                // Obtener el plan genérico vigente con todos sus detalles
                $stmt = $db->query("
                    SELECT 
                        pg.id_plan_generico,
                        pg.codigo_plan_generico,
                        pg.fecha_vigencia_inicio,
                        pg.fecha_vigencia_fin,
                        pg.estado,
                        pg.observaciones as observaciones_plan,
                        pg.fecha_creacion,
                        u.nombre_completo as creado_por
                    FROM plan_generico_tejido pg
                    LEFT JOIN usuarios u ON pg.usuario_creacion = u.id_usuario
                    WHERE pg.estado = 'vigente'
                    ORDER BY pg.fecha_vigencia_inicio DESC
                    LIMIT 1
                ");
                $plan = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($plan) {
                    // Obtener los detalles del plan
                    $stmt = $db->prepare("
                        SELECT 
                            dpg.id_detalle_generico,
                            dpg.id_maquina,
                            dpg.id_producto,
                            dpg.accion,
                            dpg.producto_nuevo,
                            dpg.cantidad_objetivo_docenas,
                            dpg.observaciones,
                            m.numero_maquina,
                            m.estado as estado_maquina,
                            p.codigo_producto,
                            p.descripcion_completa,
                            p.talla,
                            l.nombre_linea,
                            l.codigo_linea,
                            tp.nombre_tipo,
                            pn.codigo_producto as codigo_producto_nuevo,
                            pn.descripcion_completa as descripcion_producto_nuevo
                        FROM detalle_plan_generico dpg
                        JOIN maquinas m ON dpg.id_maquina = m.id_maquina
                        JOIN productos_tejidos p ON dpg.id_producto = p.id_producto
                        JOIN lineas_producto l ON p.id_linea = l.id_linea
                        JOIN tipos_producto tp ON p.id_tipo_producto = tp.id_tipo_producto
                        LEFT JOIN productos_tejidos pn ON dpg.producto_nuevo = pn.id_producto
                        WHERE dpg.id_plan_generico = ?
                        ORDER BY m.numero_maquina
                    ");
                    $stmt->execute([$plan['id_plan_generico']]);
                    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $plan['detalles'] = $detalles;
                }
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'plan' => $plan
                ]);
            } elseif (isset($_GET['action']) && $_GET['action'] === 'detalle_plan') {
                // Obtener detalle de un plan específico (para historial)
                $id_plan = (int)($_GET['id_plan'] ?? 0);
                
                if (!$id_plan) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'ID de plan requerido']);
                    exit();
                }
                
                $stmt = $db->prepare("
                    SELECT 
                        pg.id_plan_generico,
                        pg.codigo_plan_generico,
                        pg.fecha_vigencia_inicio,
                        pg.fecha_vigencia_fin,
                        pg.estado,
                        pg.observaciones as observaciones_plan,
                        pg.fecha_creacion,
                        u.nombre_completo as creado_por
                    FROM plan_generico_tejido pg
                    LEFT JOIN usuarios u ON pg.usuario_creacion = u.id_usuario
                    WHERE pg.id_plan_generico = ?
                ");
                $stmt->execute([$id_plan]);
                $plan = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($plan) {
                    // Obtener los detalles del plan
                    $stmt = $db->prepare("
                        SELECT 
                            dpg.id_detalle_generico,
                            dpg.id_maquina,
                            dpg.id_producto,
                            dpg.accion,
                            dpg.producto_nuevo,
                            dpg.cantidad_objetivo_docenas,
                            dpg.observaciones,
                            m.numero_maquina,
                            m.estado as estado_maquina,
                            p.codigo_producto,
                            p.descripcion_completa,
                            p.talla,
                            l.nombre_linea,
                            l.codigo_linea,
                            tp.nombre_tipo,
                            pn.codigo_producto as codigo_producto_nuevo,
                            pn.descripcion_completa as descripcion_producto_nuevo
                        FROM detalle_plan_generico dpg
                        JOIN maquinas m ON dpg.id_maquina = m.id_maquina
                        JOIN productos_tejidos p ON dpg.id_producto = p.id_producto
                        JOIN lineas_producto l ON p.id_linea = l.id_linea
                        JOIN tipos_producto tp ON p.id_tipo_producto = tp.id_tipo_producto
                        LEFT JOIN productos_tejidos pn ON dpg.producto_nuevo = pn.id_producto
                        WHERE dpg.id_plan_generico = ?
                        ORDER BY m.numero_maquina
                    ");
                    $stmt->execute([$id_plan]);
                    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $plan['detalles'] = $detalles;
                }
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'plan' => $plan
                ]);
            } elseif (isset($_GET['action']) && $_GET['action'] === 'historial') {
                // Obtener historial de planes
                $stmt = $db->query("
                    SELECT 
                        pg.id_plan_generico,
                        pg.codigo_plan_generico,
                        pg.fecha_vigencia_inicio,
                        pg.fecha_vigencia_fin,
                        pg.estado,
                        pg.fecha_creacion,
                        u.nombre_completo as creado_por,
                        COUNT(dpg.id_detalle_generico) as total_asignaciones
                    FROM plan_generico_tejido pg
                    LEFT JOIN usuarios u ON pg.usuario_creacion = u.id_usuario
                    LEFT JOIN detalle_plan_generico dpg ON pg.id_plan_generico = dpg.id_plan_generico
                    GROUP BY pg.id_plan_generico
                    ORDER BY pg.fecha_vigencia_inicio DESC
                    LIMIT 20
                ");
                $planes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'planes' => $planes
                ]);
            } else {
                // Listar máquinas disponibles para asignar
                $stmt = $db->query("
                    SELECT 
                        id_maquina,
                        numero_maquina,
                        estado,
                        ubicacion
                    FROM maquinas
                    ORDER BY numero_maquina
                ");
                $maquinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'maquinas' => $maquinas
                ]);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['action']) && $data['action'] === 'crear_plan') {
                // Crear un nuevo plan genérico
                $codigo_plan = trim($data['codigo_plan_generico'] ?? '');
                $fecha_inicio = trim($data['fecha_vigencia_inicio'] ?? '');
                $observaciones = trim($data['observaciones'] ?? '');
                $id_usuario = $_SESSION['user_id'];
                
                if (empty($codigo_plan) || empty($fecha_inicio)) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Código y fecha de inicio son requeridos']);
                    exit();
                }
                
                // Poner en histórico el plan vigente actual
                $db->exec("UPDATE plan_generico_tejido SET estado = 'historico' WHERE estado = 'vigente'");
                
                // Crear el nuevo plan
                $stmt = $db->prepare("
                    INSERT INTO plan_generico_tejido (
                        codigo_plan_generico,
                        fecha_vigencia_inicio,
                        observaciones,
                        usuario_creacion,
                        estado
                    ) VALUES (?, ?, ?, ?, 'vigente')
                ");
                $stmt->execute([$codigo_plan, $fecha_inicio, $observaciones, $id_usuario]);
                $id_plan = $db->lastInsertId();
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Plan genérico creado exitosamente',
                    'id_plan_generico' => $id_plan
                ]);
            } elseif (isset($data['action']) && $data['action'] === 'asignar_maquina') {
                // Asignar o actualizar producto en una máquina
                $id_plan = (int)($data['id_plan_generico'] ?? 0);
                $id_maquina = (int)($data['id_maquina'] ?? 0);
                $id_producto = (int)($data['id_producto'] ?? 0);
                $accion = trim($data['accion'] ?? 'mantener');
                $producto_nuevo = !empty($data['producto_nuevo']) ? (int)$data['producto_nuevo'] : null;
                $cantidad_objetivo = !empty($data['cantidad_objetivo_docenas']) ? (int)$data['cantidad_objetivo_docenas'] : null;
                $observaciones = trim($data['observaciones'] ?? '');
                
                if (!$id_plan || !$id_maquina || !$id_producto) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                    exit();
                }
                
                // Verificar si ya existe una asignación para esta máquina en este plan
                $stmt = $db->prepare("
                    SELECT id_detalle_generico 
                    FROM detalle_plan_generico 
                    WHERE id_plan_generico = ? AND id_maquina = ?
                ");
                $stmt->execute([$id_plan, $id_maquina]);
                $existe = $stmt->fetch();
                
                if ($existe) {
                    // Actualizar
                    $stmt = $db->prepare("
                        UPDATE detalle_plan_generico SET
                            id_producto = ?,
                            accion = ?,
                            producto_nuevo = ?,
                            cantidad_objetivo_docenas = ?,
                            observaciones = ?
                        WHERE id_plan_generico = ? AND id_maquina = ?
                    ");
                    $stmt->execute([
                        $id_producto, $accion, $producto_nuevo, 
                        $cantidad_objetivo, $observaciones, $id_plan, $id_maquina
                    ]);
                    
                    ob_clean();
                    echo json_encode(['success' => true, 'message' => 'Asignación actualizada']);
                } else {
                    // Insertar
                    $stmt = $db->prepare("
                        INSERT INTO detalle_plan_generico (
                            id_plan_generico, id_maquina, id_producto, 
                            accion, producto_nuevo, cantidad_objetivo_docenas, observaciones
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $id_plan, $id_maquina, $id_producto, 
                        $accion, $producto_nuevo, $cantidad_objetivo, $observaciones
                    ]);
                    
                    ob_clean();
                    echo json_encode(['success' => true, 'message' => 'Máquina asignada exitosamente']);
                }
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
            }
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id_detalle = $data['id_detalle_generico'] ?? null;
            
            if (!$id_detalle) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'ID de detalle requerido']);
                exit();
            }
            
            $stmt = $db->prepare("DELETE FROM detalle_plan_generico WHERE id_detalle_generico = ?");
            $stmt->execute([$id_detalle]);
            
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Asignación eliminada']);
            break;
            
        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
    
} catch(PDOException $e) {
    error_log("Error en plan_generico.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos',
        'error' => $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("Error en plan_generico.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();
exit();