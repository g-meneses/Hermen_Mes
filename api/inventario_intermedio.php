<?php
/**
 * API: Inventario Intermedio
 * Sistema MES Hermen Ltda.
 * Fecha: 16 de Noviembre de 2025
 * Versión: 1.0
 */

// Iniciar buffer de salida y limpiarlo
ob_start();
ob_clean();

// Configurar headers ANTES de cualquier output
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Desactivar visualización de errores
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
    
    // Manejar diferentes métodos HTTP
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
            
        case 'POST':
            handlePost($db);
            break;
            
        case 'DELETE':
            handleDelete($db);
            break;
            
        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
    
} catch(PDOException $e) {
    error_log("Error en inventario.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos',
        'error' => $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("Error en inventario.php: " . $e->getMessage());
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
 * ============================================
 * FUNCIONES GET
 * ============================================
 */

function handleGet($db) {
    $action = $_GET['action'] ?? 'listar';
    
    switch ($action) {
        case 'listar':
            listarInventarios($db);
            break;
            
        case 'resumen':
        case 'estadisticas':
            obtenerEstadisticas($db);
            break;
            
        case 'movimientos':
            listarMovimientos($db);
            break;
            
        case 'por_tipo':
            listarPorTipo($db);
            break;
            
        case 'stock_producto':
            obtenerStockProducto($db);
            break;
            
        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
}

/**
 * Listar todos los inventarios con información completa
 */
function listarInventarios($db) {
    $tipo = $_GET['tipo'] ?? null;
    $id_linea = $_GET['id_linea'] ?? null;
    $busqueda = $_GET['busqueda'] ?? null;
    
    $sql = "
        SELECT 
            i.id_inventario,
            i.id_producto,
            i.tipo_inventario,
            i.docenas,
            i.unidades,
            i.total_unidades_calculado,
            i.fecha_actualizacion,
            p.codigo_producto,
            p.descripcion_completa,
            p.talla,
            l.id_linea,
            l.nombre_linea,
            l.codigo_linea,
            tp.nombre_tipo,
            tp.categoria
        FROM inventario_intermedio i
        INNER JOIN productos_tejidos p ON i.id_producto = p.id_producto
        INNER JOIN lineas_producto l ON p.id_linea = l.id_linea
        INNER JOIN tipos_producto tp ON p.id_tipo_producto = tp.id_tipo_producto
        WHERE p.activo = 1
    ";
    
    $params = [];
    
    // Filtrar por tipo de inventario
    if ($tipo) {
        $sql .= " AND i.tipo_inventario = ?";
        $params[] = $tipo;
    }
    
    // Filtrar por línea
    if ($id_linea) {
        $sql .= " AND l.id_linea = ?";
        $params[] = $id_linea;
    }
    
    // Búsqueda por texto
    if ($busqueda) {
        $sql .= " AND (p.codigo_producto LIKE ? OR p.descripcion_completa LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }
    
    $sql .= " ORDER BY l.nombre_linea, p.descripcion_completa";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $inventarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'inventarios' => $inventarios,
        'total' => count($inventarios)
    ]);
}

/**
 * Obtener estadísticas generales de inventarios
 */
function obtenerEstadisticas($db) {
    // Estadísticas por tipo de inventario
    $stmt = $db->query("
        SELECT 
            tipo_inventario,
            COUNT(DISTINCT id_producto) as productos,
            SUM(docenas) as total_docenas,
            SUM(unidades) as total_unidades,
            SUM(total_unidades_calculado) as total_unidades_calc
        FROM inventario_intermedio
        GROUP BY tipo_inventario
    ");
    
    $estadisticas_tipo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convertir a formato docenas|unidades
    $stats = [];
    foreach ($estadisticas_tipo as $stat) {
        $total_calc = $stat['total_unidades_calc'];
        $docenas = intdiv($total_calc, 12);
        $unidades = $total_calc % 12;
        
        $stats[$stat['tipo_inventario']] = [
            'productos' => $stat['productos'],
            'docenas' => $docenas,
            'unidades' => $unidades,
            'total_unidades_calc' => $total_calc
        ];
    }
    
    // Total general
    $stmt = $db->query("
        SELECT 
            COUNT(DISTINCT id_producto) as productos_total,
            SUM(total_unidades_calculado) as unidades_totales
        FROM inventario_intermedio
    ");
    
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_calc = $total['unidades_totales'] ?? 0;
    $total_docenas = intdiv($total_calc, 12);
    $total_unidades = $total_calc % 12;
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'estadisticas' => [
            'por_tipo' => $stats,
            'total' => [
                'productos' => $total['productos_total'] ?? 0,
                'docenas' => $total_docenas,
                'unidades' => $total_unidades,
                'total_unidades_calc' => $total_calc
            ]
        ]
    ]);
}

/**
 * Listar movimientos de inventario con filtros
 */
function listarMovimientos($db) {
    $tipo_inventario = $_GET['tipo_inventario'] ?? null;
    $tipo_movimiento = $_GET['tipo_movimiento'] ?? null;
    $fecha_desde = $_GET['fecha_desde'] ?? null;
    $fecha_hasta = $_GET['fecha_hasta'] ?? null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    
    $sql = "
        SELECT 
            m.id_movimiento,
            m.tipo_movimiento,
            m.tipo_inventario,
            m.docenas,
            m.unidades,
            m.total_unidades_calculado,
            m.origen,
            m.destino,
            m.fecha_movimiento,
            m.observaciones,
            p.codigo_producto,
            p.descripcion_completa,
            l.nombre_linea,
            u.nombre_completo as responsable,
            pr.codigo_lote_turno as lote_origen
        FROM movimientos_inventario m
        INNER JOIN productos_tejidos p ON m.id_producto = p.id_producto
        INNER JOIN lineas_producto l ON p.id_linea = l.id_linea
        INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
        LEFT JOIN produccion_tejeduria pr ON m.id_produccion = pr.id_produccion
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($tipo_inventario) {
        $sql .= " AND m.tipo_inventario = ?";
        $params[] = $tipo_inventario;
    }
    
    if ($tipo_movimiento) {
        $sql .= " AND m.tipo_movimiento = ?";
        $params[] = $tipo_movimiento;
    }
    
    if ($fecha_desde) {
        $sql .= " AND DATE(m.fecha_movimiento) >= ?";
        $params[] = $fecha_desde;
    }
    
    if ($fecha_hasta) {
        $sql .= " AND DATE(m.fecha_movimiento) <= ?";
        $params[] = $fecha_hasta;
    }
    
    $sql .= " ORDER BY m.fecha_movimiento DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'movimientos' => $movimientos,
        'total' => count($movimientos)
    ]);
}

/**
 * Listar inventario por tipo específico
 */
function listarPorTipo($db) {
    $tipo = $_GET['tipo'] ?? null;
    
    if (!$tipo) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Tipo de inventario requerido']);
        return;
    }
    
    $stmt = $db->prepare("
        SELECT 
            i.*,
            p.codigo_producto,
            p.descripcion_completa,
            p.talla,
            l.nombre_linea,
            l.codigo_linea,
            tp.nombre_tipo
        FROM inventario_intermedio i
        INNER JOIN productos_tejidos p ON i.id_producto = p.id_producto
        INNER JOIN lineas_producto l ON p.id_linea = l.id_linea
        INNER JOIN tipos_producto tp ON p.id_tipo_producto = tp.id_tipo_producto
        WHERE i.tipo_inventario = ?
        AND p.activo = 1
        ORDER BY l.nombre_linea, p.descripcion_completa
    ");
    
    $stmt->execute([$tipo]);
    $inventarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'tipo' => $tipo,
        'inventarios' => $inventarios,
        'total' => count($inventarios)
    ]);
}

/**
 * Obtener stock de un producto específico en todos los inventarios
 */
function obtenerStockProducto($db) {
    $id_producto = $_GET['id_producto'] ?? null;
    
    if (!$id_producto) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'ID de producto requerido']);
        return;
    }
    
    $stmt = $db->prepare("
        SELECT 
            tipo_inventario,
            docenas,
            unidades,
            total_unidades_calculado
        FROM inventario_intermedio
        WHERE id_producto = ?
    ");
    
    $stmt->execute([$id_producto]);
    $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener info del producto
    $stmt = $db->prepare("
        SELECT 
            p.*,
            l.nombre_linea,
            tp.nombre_tipo
        FROM productos_tejidos p
        INNER JOIN lineas_producto l ON p.id_linea = l.id_linea
        INNER JOIN tipos_producto tp ON p.id_tipo_producto = tp.id_tipo_producto
        WHERE p.id_producto = ?
    ");
    
    $stmt->execute([$id_producto]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'producto' => $producto,
        'stocks' => $stocks
    ]);
}


/**
 * ============================================
 * FUNCIONES POST
 * ============================================
 */

function handlePost($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        return;
    }
    
    $action = $data['action'] ?? 'registrar_movimiento';
    
    switch ($action) {
        case 'registrar_movimiento':
            registrarMovimiento($db, $data);
            break;
            
        case 'ajuste_inventario':
            ajusteInventario($db, $data);
            break;
            
        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
}

/**
 * Registrar un movimiento de inventario
 */
function registrarMovimiento($db, $data) {
    // Validar datos requeridos
    $id_producto = $data['id_producto'] ?? null;
    $tipo_movimiento = $data['tipo_movimiento'] ?? null;
    $tipo_inventario = $data['tipo_inventario'] ?? null;
    $docenas = intval($data['docenas'] ?? 0);
    $unidades = intval($data['unidades'] ?? 0);
    $origen = $data['origen'] ?? '';
    $destino = $data['destino'] ?? '';
    $observaciones = $data['observaciones'] ?? '';
    $id_produccion = $data['id_produccion'] ?? null;
    
    $id_usuario = $_SESSION['user_id'];
    
    // Validaciones
    if (!$id_producto || !$tipo_movimiento || !$tipo_inventario) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
        return;
    }
    
    if ($unidades < 0 || $unidades > 11) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Unidades deben estar entre 0 y 11']);
        return;
    }
    
    if ($docenas == 0 && $unidades == 0) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Debe ingresar al menos una cantidad']);
        return;
    }
    
    // Usar procedimiento almacenado
    try {
        $stmt = $db->prepare("CALL sp_registrar_movimiento(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @resultado, @id_movimiento)");
        $stmt->execute([
            $id_producto,
            $tipo_movimiento,
            $tipo_inventario,
            $docenas,
            $unidades,
            $origen,
            $destino,
            $id_usuario,
            $id_produccion,
            $observaciones
        ]);
        
        // Obtener resultado
        $result = $db->query("SELECT @resultado as resultado, @id_movimiento as id_movimiento")->fetch(PDO::FETCH_ASSOC);
        
        if ($result['resultado'] == 'EXITO') {
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Movimiento registrado exitosamente',
                'id_movimiento' => $result['id_movimiento']
            ]);
        } else {
            ob_clean();
            echo json_encode([
                'success' => false,
                'message' => $result['resultado']
            ]);
        }
        
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Error al registrar movimiento: ' . $e->getMessage()
        ]);
    }
}

/**
 * Ajuste manual de inventario (para correcciones)
 */
function ajusteInventario($db, $data) {
    $id_producto = $data['id_producto'] ?? null;
    $tipo_inventario = $data['tipo_inventario'] ?? null;
    $docenas = intval($data['docenas'] ?? 0);
    $unidades = intval($data['unidades'] ?? 0);
    $observaciones = $data['observaciones'] ?? '';
    
    $id_usuario = $_SESSION['user_id'];
    
    // Validaciones
    if (!$id_producto || !$tipo_inventario) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
        return;
    }
    
    if ($unidades < 0 || $unidades > 11) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Unidades deben estar entre 0 y 11']);
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // Obtener stock actual
        $stmt = $db->prepare("
            SELECT docenas, unidades, total_unidades_calculado
            FROM inventario_intermedio
            WHERE id_producto = ? AND tipo_inventario = ?
        ");
        $stmt->execute([$id_producto, $tipo_inventario]);
        $stock_actual = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_anterior = $stock_actual['total_unidades_calculado'] ?? 0;
        $total_nuevo = ($docenas * 12) + $unidades;
        $diferencia = $total_nuevo - $total_anterior;
        
        // Actualizar inventario
        $stmt = $db->prepare("
            INSERT INTO inventario_intermedio (id_producto, tipo_inventario, docenas, unidades)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                docenas = ?,
                unidades = ?
        ");
        $stmt->execute([$id_producto, $tipo_inventario, $docenas, $unidades, $docenas, $unidades]);
        
        // Registrar el ajuste como movimiento
        $tipo_mov = $diferencia >= 0 ? 'entrada' : 'salida';
        $diff_docenas = abs(intdiv($diferencia, 12));
        $diff_unidades = abs($diferencia % 12);
        
        if ($diferencia != 0) {
            $stmt = $db->prepare("
                INSERT INTO movimientos_inventario (
                    id_producto, tipo_movimiento, tipo_inventario,
                    docenas, unidades, total_unidades_calculado,
                    origen, destino, id_usuario, observaciones
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $id_producto,
                $tipo_mov,
                $tipo_inventario,
                $diff_docenas,
                $diff_unidades,
                abs($diferencia),
                'Ajuste Manual',
                $tipo_inventario,
                $id_usuario,
                "Ajuste manual: $observaciones"
            ]);
        }
        
        $db->commit();
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Inventario ajustado exitosamente',
            'diferencia' => $diferencia
        ]);
        
    } catch (PDOException $e) {
        $db->rollBack();
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Error al ajustar inventario: ' . $e->getMessage()
        ]);
    }
}


/**
 * ============================================
 * FUNCIONES DELETE
 * ============================================
 */

function handleDelete($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id_movimiento = $data['id_movimiento'] ?? null;
    
    if (!$id_movimiento) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'ID de movimiento requerido']);
        return;
    }
    
    try {
        // Nota: Eliminar movimientos puede descuadrar el inventario
        // Solo permitir a administradores o bajo ciertas condiciones
        
        if (!hasRole(['admin', 'coordinador'])) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar movimientos']);
            return;
        }
        
        $stmt = $db->prepare("DELETE FROM movimientos_inventario WHERE id_movimiento = ?");
        $stmt->execute([$id_movimiento]);
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Movimiento eliminado. ADVERTENCIA: Recalcule el inventario manualmente.'
        ]);
        
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar movimiento: ' . $e->getMessage()
        ]);
    }
}
?>