<?php
/**
 * API de Unidades de Medida
 * Sistema ERP Hermen Ltda.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

try {
    $db = getDB();
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            $stmt = $db->prepare("
                SELECT 
                    id_unidad,
                    codigo,
                    nombre,
                    abreviatura,
                    tipo,
                    activo
                FROM unidades_medida
                WHERE activo = 1
                ORDER BY nombre
            ");
            
            $stmt->execute();
            $unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'unidades' => $unidades,
                'total' => count($unidades)
            ]);
            break;
            
        case 'get':
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID requerido']);
                exit;
            }
            
            $stmt = $db->prepare("
                SELECT * FROM unidades_medida WHERE id_unidad = ?
            ");
            $stmt->execute([$id]);
            $unidad = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($unidad) {
                echo json_encode([
                    'success' => true,
                    'unidad' => $unidad
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Unidad no encontrada'
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Acción no válida'
            ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>