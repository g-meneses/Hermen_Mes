<?php
/**
 * API de Tipos de Ingreso
 * Sistema ERP Hermen v2.1.0
 * Endpoint para obtener configuración de tipos de ingreso
 */

ob_start();
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
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
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            // Listar todos los tipos de ingreso activos
            $stmt = $db->prepare("
                SELECT 
                    id_tipo_ingreso,
                    codigo,
                    nombre,
                    descripcion,
                    requiere_proveedor,
                    requiere_factura,
                    requiere_area_produccion,
                    requiere_motivo,
                    requiere_autorizacion,
                    permite_iva,
                    permite_moneda_extranjera,
                    observaciones_obligatorias,
                    minimo_caracteres_obs,
                    afecta_cpp,
                    tipo_kardex,
                    icono,
                    color,
                    orden
                FROM tipos_ingreso
                WHERE activo = TRUE
                ORDER BY orden
            ");
            $stmt->execute();
            $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convertir valores booleanos
            foreach ($tipos as &$tipo) {
                $tipo['requiere_proveedor'] = (bool)$tipo['requiere_proveedor'];
                $tipo['requiere_factura'] = (bool)$tipo['requiere_factura'];
                $tipo['requiere_area_produccion'] = (bool)$tipo['requiere_area_produccion'];
                $tipo['requiere_motivo'] = (bool)$tipo['requiere_motivo'];
                $tipo['requiere_autorizacion'] = (bool)$tipo['requiere_autorizacion'];
                $tipo['permite_iva'] = (bool)$tipo['permite_iva'];
                $tipo['permite_moneda_extranjera'] = (bool)$tipo['permite_moneda_extranjera'];
                $tipo['observaciones_obligatorias'] = (bool)$tipo['observaciones_obligatorias'];
                $tipo['afecta_cpp'] = (bool)$tipo['afecta_cpp'];
            }
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'tipos' => $tipos,
                'total' => count($tipos)
            ]);
            break;
            
        case 'get':
            // Obtener un tipo específico
            $id = $_GET['id'] ?? null;
            $codigo = $_GET['codigo'] ?? null;
            
            if (!$id && !$codigo) {
                echo json_encode(['success' => false, 'message' => 'ID o código requerido']);
                exit();
            }
            
            if ($id) {
                $stmt = $db->prepare("SELECT * FROM tipos_ingreso WHERE id_tipo_ingreso = ? AND activo = TRUE");
                $stmt->execute([$id]);
            } else {
                $stmt = $db->prepare("SELECT * FROM tipos_ingreso WHERE codigo = ? AND activo = TRUE");
                $stmt->execute([$codigo]);
            }
            
            $tipo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tipo) {
                echo json_encode(['success' => false, 'message' => 'Tipo de ingreso no encontrado']);
                exit();
            }
            
            // Convertir booleanos
            $tipo['requiere_proveedor'] = (bool)$tipo['requiere_proveedor'];
            $tipo['requiere_factura'] = (bool)$tipo['requiere_factura'];
            $tipo['requiere_area_produccion'] = (bool)$tipo['requiere_area_produccion'];
            $tipo['requiere_motivo'] = (bool)$tipo['requiere_motivo'];
            $tipo['requiere_autorizacion'] = (bool)$tipo['requiere_autorizacion'];
            $tipo['permite_iva'] = (bool)$tipo['permite_iva'];
            $tipo['permite_moneda_extranjera'] = (bool)$tipo['permite_moneda_extranjera'];
            $tipo['observaciones_obligatorias'] = (bool)$tipo['observaciones_obligatorias'];
            $tipo['afecta_cpp'] = (bool)$tipo['afecta_cpp'];
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'tipo' => $tipo
            ]);
            break;
            
        case 'motivos':
            // Obtener motivos para un tipo de ingreso
            $tipoId = $_GET['tipo_id'] ?? null;
            
            if (!$tipoId) {
                echo json_encode(['success' => false, 'message' => 'Tipo de ingreso requerido']);
                exit();
            }
            
            $stmt = $db->prepare("
                SELECT 
                    id_motivo,
                    codigo,
                    descripcion,
                    requiere_detalle
                FROM motivos_ingreso
                WHERE id_tipo_ingreso = ? AND activo = TRUE
                ORDER BY orden
            ");
            $stmt->execute([$tipoId]);
            $motivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'motivos' => $motivos
            ]);
            break;
            
        case 'areas':
            // Obtener áreas de producción activas
            $stmt = $db->prepare("
                SELECT 
                    id_area,
                    codigo,
                    nombre,
                    descripcion,
                    responsable
                FROM areas_produccion
                WHERE activo = TRUE
                ORDER BY nombre
            ");
            $stmt->execute();
            $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'areas' => $areas
            ]);
            break;
            
        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
    
} catch(PDOException $e) {
    error_log("Error en tipos_ingreso.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("Error en tipos_ingreso.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

ob_end_flush();
exit();