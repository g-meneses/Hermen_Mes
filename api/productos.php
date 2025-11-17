<?php
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
            // Listar productos
            $stmt = $db->query("
                SELECT 
                    pt.id_producto,
                    pt.codigo_producto,
                    pt.id_linea,
                    pt.id_tipo_producto,
                    pt.id_diseno,
                    pt.talla,
                    pt.descripcion_completa,
                    pt.peso_promedio_docena,
                    pt.tiempo_estimado_docena,
                    l.nombre_linea,
                    l.codigo_linea,
                    tp.nombre_tipo,
                    tp.categoria,
                    d.nombre_diseno
                FROM productos_tejidos pt
                JOIN lineas_producto l ON pt.id_linea = l.id_linea
                JOIN tipos_producto tp ON pt.id_tipo_producto = tp.id_tipo_producto
                JOIN disenos d ON pt.id_diseno = d.id_diseno
                WHERE pt.activo = 1
                ORDER BY l.nombre_linea, tp.nombre_tipo, pt.talla
            ");
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'productos' => $productos
            ]);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $id_producto = $data['id_producto'] ?? null;
            $codigo_producto = trim($data['codigo_producto'] ?? '');
            $id_linea = (int)($data['id_linea'] ?? 0);
            $id_tipo_producto = (int)($data['id_tipo_producto'] ?? 0);
            $id_diseno = (int)($data['id_diseno'] ?? 0);
            $talla = trim($data['talla'] ?? '');
            $descripcion_completa = trim($data['descripcion_completa'] ?? '');
            $peso_promedio_docena = $data['peso_promedio_docena'] ? floatval($data['peso_promedio_docena']) : null;
            $tiempo_estimado_docena = $data['tiempo_estimado_docena'] ? intval($data['tiempo_estimado_docena']) : null;
            
            if (empty($codigo_producto) || !$id_linea || !$id_tipo_producto || !$id_diseno || empty($talla)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
                exit();
            }
            
            if ($id_producto) {
                // Actualizar
                $stmt = $db->prepare("
                    UPDATE productos_tejidos SET
                        codigo_producto = ?,
                        id_linea = ?,
                        id_tipo_producto = ?,
                        id_diseno = ?,
                        talla = ?,
                        descripcion_completa = ?,
                        peso_promedio_docena = ?,
                        tiempo_estimado_docena = ?
                    WHERE id_producto = ?
                ");
                $stmt->execute([
                    $codigo_producto, $id_linea, $id_tipo_producto, $id_diseno, $talla,
                    $descripcion_completa, $peso_promedio_docena, $tiempo_estimado_docena, $id_producto
                ]);
                
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Producto actualizado exitosamente']);
            } else {
                // Crear
                $stmt = $db->prepare("
                    INSERT INTO productos_tejidos (
                        codigo_producto, id_linea, id_tipo_producto, id_diseno, talla,
                        descripcion_completa, peso_promedio_docena, tiempo_estimado_docena
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $codigo_producto, $id_linea, $id_tipo_producto, $id_diseno, $talla,
                    $descripcion_completa, $peso_promedio_docena, $tiempo_estimado_docena
                ]);
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Producto creado exitosamente',
                    'id_producto' => $db->lastInsertId()
                ]);
            }
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id_producto = $data['id_producto'] ?? null;
            
            if (!$id_producto) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'ID de producto requerido']);
                exit();
            }
            
            $stmt = $db->prepare("UPDATE productos_tejidos SET activo = 0 WHERE id_producto = ?");
            $stmt->execute([$id_producto]);
            
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Producto eliminado exitosamente']);
            break;
            
        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
    
} catch(PDOException $e) {
    error_log("Error en productos.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos',
        'error' => $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("Error en productos.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();
exit();
