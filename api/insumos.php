<?php
// Iniciar buffer de salida y limpiarlo
ob_start();
ob_clean();

// Configurar headers
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
            // Listar insumos
            $stmt = $db->query("
                SELECT 
                    id_insumo,
                    codigo_insumo,
                    nombre_insumo,
                    tipo_insumo,
                    unidad_medida,
                    costo_unitario,
                    stock_actual,
                    stock_minimo,
                    proveedor,
                    descripcion,
                    id_tipo_insumo,
                    CASE 
                        WHEN stock_actual <= stock_minimo THEN 'BAJO'
                        WHEN stock_actual <= (stock_minimo * 1.5) THEN 'MEDIO'
                        ELSE 'OK'
                    END as estado_stock
                FROM insumos
                WHERE activo = 1 OR activo IS NULL
                ORDER BY tipo_insumo, nombre_insumo
            ");
            $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'insumos' => $insumos
            ]);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $id_insumo = $data['id_insumo'] ?? null;
            $codigo_insumo = trim($data['codigo_insumo'] ?? '');
            $nombre_insumo = trim($data['nombre_insumo'] ?? '');
            $tipo_insumo = trim($data['tipo_insumo'] ?? '');
            $id_tipo_insumo = (int)($data['id_tipo_insumo'] ?? 1);
            $unidad_medida = trim($data['unidad_medida'] ?? 'kilogramos');
            $costo_unitario = $data['costo_unitario'] ? floatval($data['costo_unitario']) : 0.00;
            $stock_actual = $data['stock_actual'] ? floatval($data['stock_actual']) : 0.00;
            $stock_minimo = $data['stock_minimo'] ? floatval($data['stock_minimo']) : 10.00;
            $proveedor = trim($data['proveedor'] ?? '');
            $descripcion = trim($data['observaciones'] ?? ''); // Frontend usa 'observaciones'
            
            if (empty($codigo_insumo) || empty($nombre_insumo) || empty($tipo_insumo)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
                exit();
            }
            
            if ($id_insumo) {
                // Actualizar
                $stmt = $db->prepare("
                    UPDATE insumos SET
                        codigo_insumo = ?,
                        nombre_insumo = ?,
                        tipo_insumo = ?,
                        id_tipo_insumo = ?,
                        unidad_medida = ?,
                        costo_unitario = ?,
                        stock_actual = ?,
                        stock_minimo = ?,
                        proveedor = ?,
                        descripcion = ?
                    WHERE id_insumo = ?
                ");
                $stmt->execute([
                    $codigo_insumo, $nombre_insumo, $tipo_insumo, $id_tipo_insumo, $unidad_medida,
                    $costo_unitario, $stock_actual, $stock_minimo, $proveedor,
                    $descripcion, $id_insumo
                ]);
                
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Insumo actualizado exitosamente']);
            } else {
                // Crear
                $stmt = $db->prepare("
                    INSERT INTO insumos (
                        codigo_insumo, nombre_insumo, tipo_insumo, id_tipo_insumo, unidad_medida,
                        costo_unitario, stock_actual, stock_minimo, proveedor, descripcion
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $codigo_insumo, $nombre_insumo, $tipo_insumo, $id_tipo_insumo, $unidad_medida,
                    $costo_unitario, $stock_actual, $stock_minimo, $proveedor, $descripcion
                ]);
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Insumo creado exitosamente',
                    'id_insumo' => $db->lastInsertId()
                ]);
            }
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id_insumo = $data['id_insumo'] ?? null;
            
            if (!$id_insumo) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'ID de insumo requerido']);
                exit();
            }
            
            $stmt = $db->prepare("UPDATE insumos SET activo = 0 WHERE id_insumo = ?");
            $stmt->execute([$id_insumo]);
            
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Insumo eliminado exitosamente']);
            break;
            
        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
    
} catch(PDOException $e) {
    error_log("Error en insumos.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos',
        'error' => $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("Error en insumos.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();
exit();