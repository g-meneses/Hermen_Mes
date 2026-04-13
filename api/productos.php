<?php
/**
 * API para Gestión de Productos de Tejido
 * ERP Hermen Ltda. - Versión Refinada (id_talla source of truth)
 */

// Iniciar buffer de salida y limpiarlo
ob_start();
ob_clean();

// Configurar headers ANTES de cualquier output
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Desactivar visualización de errores directos
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once '../config/database.php';
    
    if (!isLoggedIn()) {
        throw new Exception("No autorizado");
    }
    
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // Listar productos con Join a tallas y auditoría básica
            $stmt = $db->query("
                SELECT 
                    pt.id_producto,
                    pt.codigo_producto,
                    pt.id_linea,
                    pt.id_tipo_producto,
                    pt.id_diseno,
                    pt.id_talla,
                    COALESCE(tt.nombre_talla, pt.talla) AS talla,
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
                LEFT JOIN tallas_tejido tt ON pt.id_talla = tt.id_talla
                WHERE pt.activo = 1
                ORDER BY l.nombre_linea, tp.nombre_tipo, talla
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
            
            if (!$data) throw new Exception("No se recibieron datos válidos");

            $id_producto = $data['id_producto'] ?? null;
            $codigo_producto = trim($data['codigo_producto'] ?? '');
            $id_linea = (int)($data['id_linea'] ?? 0);
            $id_tipo_producto = (int)($data['id_tipo_producto'] ?? 0);
            $id_diseno = (int)($data['id_diseno'] ?? 0);
            $id_talla = (int)($data['id_talla'] ?? 0);
            $descripcion_completa = trim($data['descripcion_completa'] ?? '');
            $peso_promedio_docena = isset($data['peso_promedio_docena']) && $data['peso_promedio_docena'] !== '' ? floatval($data['peso_promedio_docena']) : null;
            $tiempo_estimado_docena = isset($data['tiempo_estimado_docena']) && $data['tiempo_estimado_docena'] !== '' ? intval($data['tiempo_estimado_docena']) : null;
            
            if (empty($codigo_producto) || !$id_linea || !$id_tipo_producto || !$id_diseno || !$id_talla) {
                throw new Exception("Faltan datos requeridos (Código, Línea, Tipo, Diseño y Talla son obligatorios)");
            }

            // ESCURA DUAL: Obtener el nombre de la talla para la columna legacy
            $stmtTalla = $db->prepare("SELECT nombre_talla FROM tallas_tejido WHERE id_talla = ?");
            $stmtTalla->execute([$id_talla]);
            $nombreTalla = $stmtTalla->fetchColumn();
            
            if (!$nombreTalla) {
                throw new Exception("La talla seleccionada no es válida o ha sido eliminada.");
            }
            
            if ($id_producto) {
                // Actualizar
                $stmt = $db->prepare("
                    UPDATE productos_tejidos SET
                        codigo_producto = ?,
                        id_linea = ?,
                        id_tipo_producto = ?,
                        id_diseno = ?,
                        id_talla = ?,
                        talla = ?,
                        descripcion_completa = ?,
                        peso_promedio_docena = ?,
                        tiempo_estimado_docena = ?
                    WHERE id_producto = ?
                ");
                $stmt->execute([
                    $codigo_producto, $id_linea, $id_tipo_producto, $id_diseno, 
                    $id_talla, $nombreTalla,
                    $descripcion_completa, $peso_promedio_docena, $tiempo_estimado_docena, $id_producto
                ]);
                $message = "Producto actualizado exitosamente";
            } else {
                // Crear
                $stmt = $db->prepare("
                    INSERT INTO productos_tejidos (
                        codigo_producto, id_linea, id_tipo_producto, id_diseno, 
                        id_talla, talla,
                        descripcion_completa, peso_promedio_docena, tiempo_estimado_docena
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $codigo_producto, $id_linea, $id_tipo_producto, $id_diseno, 
                    $id_talla, $nombreTalla,
                    $descripcion_completa, $peso_promedio_docena, $tiempo_estimado_docena
                ]);
                $id_producto = $db->lastInsertId();
                $message = "Producto creado exitosamente";
            }
            
            ob_clean();
            echo json_encode([
                'success' => true, 
                'message' => $message,
                'id_producto' => $id_producto
            ]);
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id_producto = $data['id_producto'] ?? null;
            
            if (!$id_producto) throw new Exception("ID de producto requerido");
            
            // Verificación de seguridad básica (podría extenderse a WIP)
            $stmt = $db->prepare("UPDATE productos_tejidos SET activo = 0 WHERE id_producto = ?");
            $stmt->execute([$id_producto]);
            
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Producto eliminado exitosamente']);
            break;
            
        default:
            throw new Exception("Método no permitido");
    }
    
} catch(Exception $e) {
    error_log("Error en api/productos.php: " . $e->getMessage());
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
exit();
