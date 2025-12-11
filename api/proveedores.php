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
                    // Listar proveedores con filtros opcionales
                    $tipo = $_GET['tipo'] ?? null;
                    $activo = $_GET['activo'] ?? '1';
                    $buscar = $_GET['buscar'] ?? null;
                    
                    $sql = "SELECT * FROM proveedores WHERE 1=1";
                    $params = [];
                    
                    if ($activo !== 'todos') {
                        $sql .= " AND activo = ?";
                        $params[] = $activo;
                    }
                    
                    if ($tipo && $tipo !== 'todos') {
                        $sql .= " AND tipo = ?";
                        $params[] = $tipo;
                    }
                    
                    if ($buscar) {
                        $sql .= " AND (codigo LIKE ? OR razon_social LIKE ? OR nombre_comercial LIKE ? OR nit LIKE ?)";
                        $buscarLike = "%$buscar%";
                        $params[] = $buscarLike;
                        $params[] = $buscarLike;
                        $params[] = $buscarLike;
                        $params[] = $buscarLike;
                    }
                    
                    $sql .= " ORDER BY tipo, razon_social";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Contar por tipo
                    $stmtCount = $db->query("SELECT tipo, COUNT(*) as total FROM proveedores WHERE activo = 1 GROUP BY tipo");
                    $conteos = $stmtCount->fetchAll(PDO::FETCH_ASSOC);
                    
                    $totales = ['LOCAL' => 0, 'IMPORTACION' => 0];
                    foreach ($conteos as $c) {
                        $totales[$c['tipo']] = (int)$c['total'];
                    }
                    
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'proveedores' => $proveedores,
                        'total' => count($proveedores),
                        'totales' => $totales
                    ]);
                    break;
                    
                case 'get':
                    // Obtener un proveedor específico
                    $id = $_GET['id'] ?? null;
                    if (!$id) {
                        echo json_encode(['success' => false, 'message' => 'ID requerido']);
                        exit();
                    }
                    
                    $stmt = $db->prepare("SELECT * FROM proveedores WHERE id_proveedor = ?");
                    $stmt->execute([$id]);
                    $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($proveedor) {
                        ob_clean();
                        echo json_encode(['success' => true, 'proveedor' => $proveedor]);
                    } else {
                        ob_clean();
                        echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
                    }
                    break;
                    
                case 'paises':
                    // Lista de países para select
                    $paises = [
                        'Bolivia', 'Perú', 'Brasil', 'Argentina', 'Chile', 'Colombia',
                        'Estados Unidos', 'China', 'Alemania', 'Italia', 'España',
                        'Corea del Sur', 'Taiwán', 'India', 'Turquía', 'México'
                    ];
                    ob_clean();
                    echo json_encode(['success' => true, 'paises' => $paises]);
                    break;
                    
                default:
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? 'create';
            
            switch ($action) {
                case 'create':
                case 'update':
                    // Validar campos requeridos
                    $codigo = trim($data['codigo'] ?? '');
                    $razon_social = trim($data['razon_social'] ?? '');
                    $tipo = $data['tipo'] ?? 'LOCAL';
                    
                    if (empty($codigo) || empty($razon_social)) {
                        ob_clean();
                        echo json_encode(['success' => false, 'message' => 'Código y Razón Social son requeridos']);
                        exit();
                    }
                    
                    $id = $data['id_proveedor'] ?? null;
                    
                    // Verificar código único
                    $sqlCheck = "SELECT id_proveedor FROM proveedores WHERE codigo = ?";
                    $paramsCheck = [$codigo];
                    if ($id) {
                        $sqlCheck .= " AND id_proveedor != ?";
                        $paramsCheck[] = $id;
                    }
                    $stmtCheck = $db->prepare($sqlCheck);
                    $stmtCheck->execute($paramsCheck);
                    if ($stmtCheck->fetch()) {
                        ob_clean();
                        echo json_encode(['success' => false, 'message' => 'El código ya existe']);
                        exit();
                    }
                    
                    if ($id) {
                        // Actualizar
                        $stmt = $db->prepare("
                            UPDATE proveedores SET
                                codigo = ?,
                                razon_social = ?,
                                nombre_comercial = ?,
                                tipo = ?,
                                nit = ?,
                                nombre_contacto = ?,
                                contacto_telefono = ?,
                                telefono = ?,
                                email = ?,
                                direccion = ?,
                                ciudad = ?,
                                pais = ?,
                                moneda = ?,
                                condicion_pago = ?,
                                dias_credito = ?,
                                observaciones = ?,
                                activo = ?
                            WHERE id_proveedor = ?
                        ");
                        $stmt->execute([
                            $codigo,
                            $razon_social,
                            $data['nombre_comercial'] ?? null,
                            $tipo,
                            $data['nit'] ?? null,
                            $data['nombre_contacto'] ?? null,
                            $data['contacto_telefono'] ?? null,
                            $data['telefono'] ?? null,
                            $data['email'] ?? null,
                            $data['direccion'] ?? null,
                            $data['ciudad'] ?? null,
                            $data['pais'] ?? 'Bolivia',
                            $data['moneda'] ?? 'BOB',
                            $data['condicion_pago'] ?? 'Contado',
                            $data['dias_credito'] ?? 0,
                            $data['observaciones'] ?? null,
                            $data['activo'] ?? 1,
                            $id
                        ]);
                        
                        ob_clean();
                        echo json_encode(['success' => true, 'message' => 'Proveedor actualizado exitosamente']);
                    } else {
                        // Crear nuevo
                        $stmt = $db->prepare("
                            INSERT INTO proveedores (
                                codigo, razon_social, nombre_comercial, tipo, nit,
                                nombre_contacto, contacto_telefono, telefono, email,
                                direccion, ciudad, pais, moneda, condicion_pago,
                                dias_credito, observaciones, activo
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([
                            $codigo,
                            $razon_social,
                            $data['nombre_comercial'] ?? null,
                            $tipo,
                            $data['nit'] ?? null,
                            $data['nombre_contacto'] ?? null,
                            $data['contacto_telefono'] ?? null,
                            $data['telefono'] ?? null,
                            $data['email'] ?? null,
                            $data['direccion'] ?? null,
                            $data['ciudad'] ?? null,
                            $data['pais'] ?? 'Bolivia',
                            $data['moneda'] ?? 'BOB',
                            $data['condicion_pago'] ?? 'Contado',
                            $data['dias_credito'] ?? 0,
                            $data['observaciones'] ?? null
                        ]);
                        
                        ob_clean();
                        echo json_encode([
                            'success' => true,
                            'message' => 'Proveedor creado exitosamente',
                            'id_proveedor' => $db->lastInsertId()
                        ]);
                    }
                    break;
                    
                default:
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            }
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id_proveedor'] ?? null;
            
            if (!$id) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'ID requerido']);
                exit();
            }
            
            // Soft delete
            $stmt = $db->prepare("UPDATE proveedores SET activo = 0 WHERE id_proveedor = ?");
            $stmt->execute([$id]);
            
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
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("Error en proveedores.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

ob_end_flush();
exit();