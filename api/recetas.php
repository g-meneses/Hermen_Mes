<?php
/**
 * API REST para Gestión de Recetas de Productos (BOM)
 * Sistema MES Hermen Ltda.
 * Maneja la tabla producto_insumos
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
            // Verificar si se solicita la receta de un producto específico
            if (isset($_GET['id_producto'])) {
                $id_producto = (int)$_GET['id_producto'];
                
                // Obtener receta completa del producto
                $stmt = $db->prepare("
                    SELECT 
                        pi.id_producto_insumo,
                        pi.id_producto,
                        pi.id_insumo,
                        pi.cantidad_por_docena,
                        pi.es_principal,
                        pi.observaciones,
                        i.codigo_insumo,
                        i.nombre_insumo,
                        i.costo_unitario,
                        i.unidad_medida,
                        (pi.cantidad_por_docena * i.costo_unitario / 1000) as costo_total
                    FROM producto_insumos pi
                    JOIN insumos i ON pi.id_insumo = i.id_insumo
                    WHERE pi.id_producto = ?
                    ORDER BY pi.es_principal DESC, i.nombre_insumo
                ");
                $stmt->execute([$id_producto]);
                $receta = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Calcular costo total de la receta
                $costo_total_receta = 0;
                foreach ($receta as $item) {
                    $costo_total_receta += $item['costo_total'];
                }
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'receta' => $receta,
                    'costo_total_receta' => round($costo_total_receta, 2),
                    'tiene_receta' => count($receta) > 0
                ]);
                
            } else {
                // Listar todos los productos con indicador de si tienen receta
                $stmt = $db->query("
                    SELECT 
                        p.id_producto,
                        p.codigo_producto,
                        p.descripcion_completa,
                        p.id_linea,
                        l.nombre_linea,
                        l.codigo_linea,
                        tp.nombre_tipo,
                        p.talla,
                        COUNT(pi.id_producto_insumo) as num_insumos,
                        SUM(pi.cantidad_por_docena * i.costo_unitario / 1000) as costo_receta
                    FROM productos_tejidos p
                    JOIN lineas_producto l ON p.id_linea = l.id_linea
                    JOIN tipos_producto tp ON p.id_tipo_producto = tp.id_tipo_producto
                    LEFT JOIN producto_insumos pi ON p.id_producto = pi.id_producto
                    LEFT JOIN insumos i ON pi.id_insumo = i.id_insumo
                    WHERE p.activo = 1
                    GROUP BY p.id_producto
                    ORDER BY l.nombre_linea, tp.nombre_tipo, p.talla
                ");
                $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'productos' => $productos
                ]);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $id_producto_insumo = $data['id_producto_insumo'] ?? null;
            $id_producto = (int)($data['id_producto'] ?? 0);
            $id_insumo = (int)($data['id_insumo'] ?? 0);
            $cantidad_por_docena = floatval($data['cantidad_por_docena'] ?? 0);
            $es_principal = isset($data['es_principal']) ? (int)$data['es_principal'] : 0;
            $observaciones = trim($data['observaciones'] ?? '');
            
            if (!$id_producto || !$id_insumo || $cantidad_por_docena <= 0) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos']);
                exit();
            }
            
            if ($id_producto_insumo) {
                // Actualizar insumo existente en la receta
                $stmt = $db->prepare("
                    UPDATE producto_insumos SET
                        id_insumo = ?,
                        cantidad_por_docena = ?,
                        es_principal = ?,
                        observaciones = ?
                    WHERE id_producto_insumo = ?
                ");
                $stmt->execute([
                    $id_insumo, 
                    $cantidad_por_docena, 
                    $es_principal, 
                    $observaciones, 
                    $id_producto_insumo
                ]);
                
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Insumo actualizado en la receta']);
            } else {
                // Verificar si ya existe este insumo en el producto
                $stmt = $db->prepare("
                    SELECT COUNT(*) as existe 
                    FROM producto_insumos 
                    WHERE id_producto = ? AND id_insumo = ?
                ");
                $stmt->execute([$id_producto, $id_insumo]);
                $existe = $stmt->fetch(PDO::FETCH_ASSOC)['existe'];
                
                if ($existe > 0) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Este insumo ya está en la receta del producto']);
                    exit();
                }
                
                // Si se marca como principal, desmarcar otros
                if ($es_principal == 1) {
                    $stmt = $db->prepare("
                        UPDATE producto_insumos 
                        SET es_principal = 0 
                        WHERE id_producto = ?
                    ");
                    $stmt->execute([$id_producto]);
                }
                
                // Agregar nuevo insumo a la receta
                $stmt = $db->prepare("
                    INSERT INTO producto_insumos (
                        id_producto, id_insumo, cantidad_por_docena, 
                        es_principal, observaciones
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id_producto, 
                    $id_insumo, 
                    $cantidad_por_docena, 
                    $es_principal, 
                    $observaciones
                ]);
                
                ob_clean();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Insumo agregado a la receta',
                    'id_producto_insumo' => $db->lastInsertId()
                ]);
            }
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id_producto_insumo = $data['id_producto_insumo'] ?? null;
            
            if (!$id_producto_insumo) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'ID requerido']);
                exit();
            }
            
            $stmt = $db->prepare("DELETE FROM producto_insumos WHERE id_producto_insumo = ?");
            $stmt->execute([$id_producto_insumo]);
            
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Insumo eliminado de la receta']);
            break;
            
        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
    
} catch(PDOException $e) {
    error_log("Error en recetas.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos',
        'error' => $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("Error en recetas.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();
exit();