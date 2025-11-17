<?php
// Iniciar buffer de salida y limpiarlo
ob_start();
ob_clean();

// Configurar headers ANTES de cualquier output
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Desactivar visualización de errores (pero seguir logueándolos)
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // Incluir configuración
    require_once '../config/database.php';
    
    // Verificar sesión
    if (!isLoggedIn()) {
        echo json_encode([
            'success' => false,
            'message' => 'No autorizado'
        ]);
        exit();
    }
    
    // Obtener conexión
    $db = getDB();
    
    // Verificar si se solicita un tipo específico de catálogo
    $tipo = $_GET['tipo'] ?? 'all';
    
    switch ($tipo) {
        case 'lineas':
            // Solo líneas de producto
            $stmt = $db->query("SELECT id_linea, codigo_linea, nombre_linea, descripcion FROM lineas_producto WHERE activo = 1 ORDER BY nombre_linea");
            $lineas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'lineas' => $lineas
            ]);
            break;
            
        case 'tipos':
            // Solo tipos de producto
            $stmt = $db->query("SELECT id_tipo_producto, nombre_tipo, categoria, descripcion FROM tipos_producto WHERE activo = 1 ORDER BY nombre_tipo");
            $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'tipos' => $tipos
            ]);
            break;
            
        case 'disenos':
            // Solo diseños
            $stmt = $db->query("SELECT id_diseno, nombre_diseno, descripcion FROM disenos WHERE activo = 1 ORDER BY nombre_diseno");
            $disenos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'disenos' => $disenos
            ]);
            break;
            
        case 'turnos':
            // Turnos de trabajo
            $stmt = $db->query("SELECT id_turno, nombre_turno, hora_inicio, hora_fin, activo FROM turnos WHERE activo = 1 ORDER BY hora_inicio");
            $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'turnos' => $turnos
            ]);
            break;
            
        case 'usuarios':
            // Usuarios activos
            $stmt = $db->query("SELECT id_usuario, codigo_usuario, nombre_completo, rol, area FROM usuarios WHERE estado = 'activo' ORDER BY nombre_completo");
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'usuarios' => $usuarios
            ]);
            break;
            
        case 'all':
        default:
            // Todos los catálogos (comportamiento original)
            // Obtener líneas de producto
            $stmtLineas = $db->query("SELECT id_linea, codigo_linea, nombre_linea, descripcion FROM lineas_producto WHERE activo = 1 ORDER BY nombre_linea");
            $lineas = $stmtLineas->fetchAll(PDO::FETCH_ASSOC);
            
            // Obtener tipos de producto
            $stmtTipos = $db->query("SELECT id_tipo_producto, nombre_tipo, categoria, descripcion FROM tipos_producto WHERE activo = 1 ORDER BY nombre_tipo");
            $tipos = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);
            
            // Obtener diseños
            $stmtDisenos = $db->query("SELECT id_diseno, nombre_diseno, descripcion FROM disenos WHERE activo = 1 ORDER BY nombre_diseno");
            $disenos = $stmtDisenos->fetchAll(PDO::FETCH_ASSOC);
            
            // Preparar respuesta
            $response = [
                'success' => true,
                'lineas' => $lineas,
                'tipos' => $tipos,
                'disenos' => $disenos
            ];
            
            // Limpiar cualquier output previo
            ob_clean();
            
            // Enviar JSON
            echo json_encode($response);
            break;
    }
    
} catch(PDOException $e) {
    // Log del error
    error_log("Error en catalogos.php (PDO): " . $e->getMessage());
    
    // Limpiar buffer
    ob_clean();
    
    // Responder con error
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos',
        'error' => $e->getMessage()
    ]);
    
} catch(Exception $e) {
    // Log del error
    error_log("Error en catalogos.php (General): " . $e->getMessage());
    
    // Limpiar buffer
    ob_clean();
    
    // Responder con error
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'error' => $e->getMessage()
    ]);
}

// Terminar y limpiar buffer
ob_end_flush();
exit();