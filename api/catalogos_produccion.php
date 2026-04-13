<?php
/**
 * API para Gestión Administrativa de Catálogos de Producción
 * ERP Hermen Ltda. - Versión Refinada (Admin Only & Physical Delete)
 */

// Iniciar buffer de salida
ob_start();
ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Desactivar visualización de errores directa
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once '../config/database.php';
    
    // Verificar sesión básica
    if (!isLoggedIn()) {
        throw new Exception("Sesión no iniciada o no autorizada");
    }
    
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['user_role'] ?? '';
    
    // Función auxiliar para validar entidad
    function validarEntidad($entidad) {
        $permitidas = ['tipos_producto', 'disenos', 'tallas_tejido'];
        if (!in_array($entidad, $permitidas)) {
            throw new Exception("Entidad no permitida: " . $entidad);
        }
        return $entidad;
    }

    switch ($method) {
        case 'GET':
            // Todos los usuarios pueden Ver los catálogos
            $entidad = validarEntidad($_GET['entidad'] ?? '');
            
            $sql = "";
            if ($entidad === 'tipos_producto') {
                $sql = "SELECT id_tipo_producto as id, nombre_tipo as nombre, categoria, descripcion, activo FROM tipos_producto ORDER BY nombre_tipo ASC";
            } elseif ($entidad === 'disenos') {
                $sql = "SELECT id_diseno as id, nombre_diseno as nombre, descripcion, activo FROM disenos ORDER BY nombre_diseno ASC";
            } elseif ($entidad === 'tallas_tejido') {
                $sql = "SELECT id_talla as id, nombre_talla as nombre, descripcion, activo FROM tallas_tejido ORDER BY nombre_talla ASC";
            }
            
            $stmt = $db->query($sql);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            echo json_encode(['success' => true, 'data' => $items]);
            break;
            
        case 'POST':
            // SOLO EL ADMINISTRADOR puede ejecutar acciones de escritura/modificación/borrado
            if ($userRole !== 'admin') {
                throw new Exception("No tienes permisos suficientes (Rol Admin Requerido) para realizar esta acción.");
            }

            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            
            if (!$data) throw new Exception("No se recibieron datos válidos");
            
            $accion = $data['accion'] ?? '';
            $entidad = validarEntidad($data['entidad'] ?? '');
            $id = $data['id'] ?? null;
            $pk = ($entidad === 'tipos_producto') ? 'id_tipo_producto' : (($entidad === 'disenos') ? 'id_diseno' : 'id_talla');

            if ($accion === 'crear' || $accion === 'editar') {
                $payload = $data['datos'] ?? [];
                
                // Mapeo de campos según entidad
                $config = [
                    'tipos_producto' => ['id' => 'id_tipo_producto', 'nombre' => 'nombre_tipo'],
                    'disenos' => ['id' => 'id_diseno', 'nombre' => 'nombre_diseno'],
                    'tallas_tejido' => ['id' => 'id_talla', 'nombre' => 'nombre_talla']
                ];
                
                $conf = $config[$entidad];
                $nombreVal = mb_strtoupper(trim($payload[$conf['nombre']] ?? ''), 'UTF-8');
                
                if (empty($nombreVal)) throw new Exception("El nombre es obligatorio");
                
                // 1. Validar unicidad (UPPER TRIM)
                $sqlCheck = "SELECT COUNT(*) FROM $entidad WHERE UPPER(TRIM({$conf['nombre']})) = ?";
                $paramsCheck = [$nombreVal];
                if ($id) {
                    $sqlCheck .= " AND {$conf['id']} != ?";
                    $paramsCheck[] = $id;
                }
                $stmtCheck = $db->prepare($sqlCheck);
                $stmtCheck->execute($paramsCheck);
                if ($stmtCheck->fetchColumn() > 0) {
                    throw new Exception("Ya existe un registro con el nombre '$nombreVal'");
                }
                
                if ($accion === 'crear') {
                    if ($entidad === 'tipos_producto') {
                        $stmt = $db->prepare("INSERT INTO tipos_producto (nombre_tipo, categoria, descripcion, created_by, activo) VALUES (?, ?, ?, ?, 1)");
                        $stmt->execute([$nombreVal, $payload['categoria'], $payload['descripcion'] ?? '', $userId]);
                    } else {
                        $stmt = $db->prepare("INSERT INTO $entidad ({$conf['nombre']}, descripcion, created_by, activo) VALUES (?, ?, ?, 1)");
                        $stmt->execute([$nombreVal, $payload['descripcion'] ?? '', $userId]);
                    }
                } else {
                    if ($entidad === 'tipos_producto') {
                        $stmt = $db->prepare("UPDATE tipos_producto SET nombre_tipo = ?, categoria = ?, descripcion = ?, updated_by = ? WHERE id_tipo_producto = ?");
                        $stmt->execute([$nombreVal, $payload['categoria'], $payload['descripcion'] ?? '', $userId, $id]);
                    } else {
                        $stmt = $db->prepare("UPDATE $entidad SET {$conf['nombre']} = ?, descripcion = ?, updated_by = ? WHERE {$conf['id']} = ?");
                        $stmt->execute([$nombreVal, $payload['descripcion'] ?? '', $userId, $id]);
                    }
                }
                
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Operación completada con éxito']);
                
            } elseif ($accion === 'desactivar') {
                $estado = isset($data['activo']) ? (int)$data['activo'] : 0;
                if (!$id) throw new Exception("ID no proporcionado");
                
                // REGLA: Bloqueo total si está en uso por productos ACTIVOS
                if ($estado === 0) {
                    $stmtCheck = $db->prepare("SELECT COUNT(*) FROM productos_tejidos WHERE $pk = ? AND activo = 1");
                    $stmtCheck->execute([$id]);
                    $uso = $stmtCheck->fetchColumn();
                    if ($uso > 0) {
                        throw new Exception("No se puede desactivar este registro porque está asignado a $uso productos activos.");
                    }
                }
                
                $stmt = $db->prepare("UPDATE $entidad SET activo = ?, updated_by = ? WHERE $pk = ?");
                $stmt->execute([$estado, $userId, $id]);
                
                ob_clean();
                echo json_encode(['success' => true, 'message' => ($estado ? 'Activado' : 'Desactivado') . ' correctamente']);

            } elseif ($accion === 'eliminar_fisico') {
                if (!$id) throw new Exception("ID no proporcionado");
                
                // REGLA CRÍTICA: Bloqueo si fue usado alguna vez (histórico)
                $stmtCheck = $db->prepare("SELECT COUNT(*) FROM productos_tejidos WHERE $pk = ?");
                $stmtCheck->execute([$id]);
                $uso = $stmtCheck->fetchColumn();
                if ($uso > 0) {
                    throw new Exception("No se puede eliminar permanentemente porque tiene historial en el catálogo de productos ($uso registros). Se sugiere desactivarlo en su lugar.");
                }
                
                $stmt = $db->prepare("DELETE FROM $entidad WHERE $pk = ?");
                $stmt->execute([$id]);
                
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Registro eliminado permanentemente del sistema']);

            } else {
                throw new Exception("Acción no reconocida");
            }
            break;
            
        default:
            throw new Exception("Método HTTP no permitido");
    }
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
exit();
