<?php
require_once '../config/database.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'No autorizado'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    
    switch ($method) {
        case 'GET':
            // Listar todas las máquinas
            $stmt = $db->query("
                SELECT * FROM maquinas 
                ORDER BY numero_maquina ASC
            ");
            $maquinas = $stmt->fetchAll();
            
            jsonResponse([
                'success' => true,
                'maquinas' => $maquinas
            ]);
            break;
            
        case 'POST':
            // Crear o actualizar máquina
            $data = json_decode(file_get_contents('php://input'), true);
            
            $id_maquina = $data['id_maquina'] ?? null;
            $numero_maquina = sanitize($data['numero_maquina']);
            $descripcion = sanitize($data['descripcion'] ?? '');
            $diametro_pulgadas = $data['diametro_pulgadas'] ?? 4.0;
            $numero_agujas = $data['numero_agujas'] ?? 400;
            $estado = sanitize($data['estado']);
            $ubicacion = sanitize($data['ubicacion'] ?? '');
            $fecha_instalacion = $data['fecha_instalacion'] ?? null;
            $observaciones = sanitize($data['observaciones'] ?? '');
            
            if (empty($numero_maquina) || empty($estado)) {
                jsonResponse(['success' => false, 'message' => 'Faltan datos requeridos'], 400);
            }
            
            if ($id_maquina) {
                // Actualizar
                $stmt = $db->prepare("
                    UPDATE maquinas SET
                        numero_maquina = ?,
                        descripcion = ?,
                        diametro_pulgadas = ?,
                        numero_agujas = ?,
                        estado = ?,
                        ubicacion = ?,
                        fecha_instalacion = ?,
                        observaciones = ?
                    WHERE id_maquina = ?
                ");
                
                $stmt->execute([
                    $numero_maquina,
                    $descripcion,
                    $diametro_pulgadas,
                    $numero_agujas,
                    $estado,
                    $ubicacion,
                    $fecha_instalacion ?: null,
                    $observaciones,
                    $id_maquina
                ]);
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Máquina actualizada exitosamente'
                ]);
            } else {
                // Crear
                $stmt = $db->prepare("
                    INSERT INTO maquinas (
                        numero_maquina, descripcion, diametro_pulgadas, numero_agujas,
                        estado, ubicacion, fecha_instalacion, observaciones
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $numero_maquina,
                    $descripcion,
                    $diametro_pulgadas,
                    $numero_agujas,
                    $estado,
                    $ubicacion,
                    $fecha_instalacion ?: null,
                    $observaciones
                ]);
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Máquina creada exitosamente'
                ]);
            }
            break;
            
        case 'DELETE':
            // Eliminar máquina
            $data = json_decode(file_get_contents('php://input'), true);
            $id_maquina = $data['id_maquina'] ?? null;
            
            if (!$id_maquina) {
                jsonResponse(['success' => false, 'message' => 'ID de máquina requerido'], 400);
            }
            
            $stmt = $db->prepare("DELETE FROM maquinas WHERE id_maquina = ?");
            $stmt->execute([$id_maquina]);
            
            jsonResponse([
                'success' => true,
                'message' => 'Máquina eliminada exitosamente'
            ]);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Método no permitido'], 405);
    }
    
} catch(PDOException $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ], 500);
}
?>
