<?php
/**
 * API de Proveedores
 * Sistema MES Hermen Ltda.
 * Versión: 1.0
 */

ob_start();
ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

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
            $action = $_GET['action'] ?? 'list';
            
            switch ($action) {
                case 'list':
                    // Listar todos los proveedores
                    $buscar = $_GET['buscar'] ?? null;
                    
                    $sql = "
                        SELECT 
                            p.*,
                            (SELECT COUNT(*) FROM movimientos_inventario_erp m 
                             WHERE m.id_proveedor = p.id_proveedor AND m.estado = 'ACTIVO') AS total_movimientos,
                            (SELECT SUM(m.costo_total) FROM movimientos_inventario_erp m 
                             WHERE m.id_proveedor = p.id_proveedor 
                             AND m.tipo_movimiento LIKE 'ENTRADA_%' 
                             AND m.estado = 'ACTIVO') AS total_compras
                        FROM proveedores p
                        WHERE p.activo = 1
                    ";
                    
                    $params = [];
                    if ($buscar) {
                        $sql .= " AND (p.codigo LIKE ? OR p.razon_social LIKE ? OR p.nit LIKE ?)";
                        $buscarParam = "%{$buscar}%";
                        $params = [$buscarParam, $buscarParam, $buscarParam];
                    }
                    
                    $sql .= " ORDER BY p.razon_social";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'proveedores' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                    ]);
                    break;
                    
                case 'detalle':
                    // Detalle de un proveedor con historial de compras
                    $idProveedor = $_GET['id'] ?? null;
                    
                    if (!$idProveedor) {
                        ob_clean();
                        echo json_encode(['success' => false, 'message' => 'ID de proveedor requerido']);
                        exit();
                    }
                    
                    // Datos del proveedor
                    $stmt = $db->prepare("SELECT * FROM proveedores WHERE id_proveedor = ?");
                    $stmt->execute([$idProveedor]);
                    $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$proveedor) {
                        ob_clean();
                        echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
                        exit();
                    }
                    
                    // Resumen de compras
                    $stmt = $db->prepare("
                        SELECT 
                            COUNT(DISTINCT documento_numero) AS total_documentos,
                            COUNT(*) AS total_lineas,
                            SUM(cantidad) AS total_cantidad,
                            SUM(costo_total) AS total_compras,
                            MIN(fecha_movimiento) AS primera_compra,
                            MAX(fecha_movimiento) AS ultima_compra
                        FROM movimientos_inventario_erp
                        WHERE id_proveedor = ? 
                        AND tipo_movimiento LIKE 'ENTRADA_%'
                        AND estado = 'ACTIVO'
                    ");
                    $stmt->execute([$idProveedor]);
                    $resumenCompras = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Últimas compras
                    $stmt = $db->prepare("
                        SELECT 
                            m.documento_numero,
                            m.fecha_movimiento,
                            i.codigo AS producto_codigo,
                            i.nombre AS producto_nombre,
                            m.cantidad,
                            um.abreviatura AS unidad,
                            m.costo_unitario,
                            m.costo_total
                        FROM movimientos_inventario_erp m
                        JOIN inventarios i ON m.id_inventario = i.id_inventario
                        JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                        WHERE m.id_proveedor = ?
                        AND m.tipo_movimiento LIKE 'ENTRADA_%'
                        AND m.estado = 'ACTIVO'
                        ORDER BY m.fecha_movimiento DESC
                        LIMIT 50
                    ");
                    $stmt->execute([$idProveedor]);
                    $ultimasCompras = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'proveedor' => $proveedor,
                        'resumen_compras' => $resumenCompras,
                        'ultimas_compras' => $ultimasCompras
                    ]);
                    break;
                    
                case 'historial_precios':
                    // Historial de precios de un producto por proveedor
                    $idInventario = $_GET['id_inventario'] ?? null;
                    $idProveedor = $_GET['id_proveedor'] ?? null;
                    
                    $sql = "
                        SELECT 
                            m.fecha_movimiento,
                            m.documento_numero,
                            p.razon_social AS proveedor,
                            m.cantidad,
                            m.costo_unitario,
                            m.costo_total
                        FROM movimientos_inventario_erp m
                        LEFT JOIN proveedores p ON m.id_proveedor = p.id_proveedor
                        WHERE m.tipo_movimiento LIKE 'ENTRADA_%'
                        AND m.estado = 'ACTIVO'
                    ";
                    
                    $params = [];
                    if ($idInventario) {
                        $sql .= " AND m.id_inventario = ?";
                        $params[] = $idInventario;
                    }
                    if ($idProveedor) {
                        $sql .= " AND m.id_proveedor = ?";
                        $params[] = $idProveedor;
                    }
                    
                    $sql .= " ORDER BY m.fecha_movimiento DESC LIMIT 100";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'historial' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                    ]);
                    break;
                    
                case 'select':
                    // Lista simple para select
                    $stmt = $db->query("
                        SELECT id_proveedor, codigo, razon_social, nit
                        FROM proveedores 
                        WHERE activo = 1 
                        ORDER BY razon_social
                    ");
                    
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'proveedores' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                    ]);
                    break;
                    
                default:
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $idProveedor = $data['id_proveedor'] ?? null;
            $codigo = trim($data['codigo'] ?? '');
            $razonSocial = trim($data['razon_social'] ?? '');
            $nit = trim($data['nit'] ?? '');
            $nombreContacto = trim($data['nombre_contacto'] ?? '');
            $telefono = trim($data['telefono'] ?? '');
            $email = trim($data['email'] ?? '');
            $direccion = trim($data['direccion'] ?? '');
            $ciudad = trim($data['ciudad'] ?? '');
            $observaciones = trim($data['observaciones'] ?? '');
            
            // Validaciones
            if (empty($codigo) || empty($razonSocial)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Código y Razón Social son requeridos']);
                exit();
            }
            
            try {
                if ($idProveedor) {
                    // Actualizar
                    $stmt = $db->prepare("
                        UPDATE proveedores SET
                            codigo = ?,
                            razon_social = ?,
                            nit = ?,
                            nombre_contacto = ?,
                            telefono = ?,
                            email = ?,
                            direccion = ?,
                            ciudad = ?,
                            observaciones = ?
                        WHERE id_proveedor = ?
                    ");
                    $stmt->execute([
                        $codigo, $razonSocial, $nit, $nombreContacto,
                        $telefono, $email, $direccion, $ciudad, $observaciones,
                        $idProveedor
                    ]);
                    
                    ob_clean();
                    echo json_encode(['success' => true, 'message' => 'Proveedor actualizado exitosamente']);
                } else {
                    // Crear
                    $stmt = $db->prepare("
                        INSERT INTO proveedores (
                            codigo, razon_social, nit, nombre_contacto,
                            telefono, email, direccion, ciudad, observaciones
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $codigo, $razonSocial, $nit, $nombreContacto,
                        $telefono, $email, $direccion, $ciudad, $observaciones
                    ]);
                    
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Proveedor creado exitosamente',
                        'id_proveedor' => $db->lastInsertId()
                    ]);
                }
            } catch (PDOException $e) {
                ob_clean();
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    echo json_encode(['success' => false, 'message' => 'El código de proveedor ya existe']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
            }
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $idProveedor = $data['id_proveedor'] ?? null;
            
            if (!$idProveedor) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'ID de proveedor requerido']);
                exit();
            }
            
            // Soft delete
            $stmt = $db->prepare("UPDATE proveedores SET activo = 0 WHERE id_proveedor = ?");
            $stmt->execute([$idProveedor]);
            
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Proveedor eliminado exitosamente']);
            break;
            
        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
    
} catch(PDOException $e) {
    error_log("Error en proveedores.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos',
        'error' => $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("Error en proveedores.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();
exit();