<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método no permitido'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$usuario = sanitize($data['usuario'] ?? '');
$password = $data['password'] ?? '';

if (empty($usuario) || empty($password)) {
    jsonResponse(['success' => false, 'message' => 'Usuario y contraseña son requeridos'], 400);
}

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id_usuario, codigo_usuario, nombre_completo, usuario, password, rol, area, estado 
        FROM usuarios 
        WHERE usuario = ? AND estado = 'activo'
    ");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'Usuario o contraseña incorrectos'], 401);
    }
    
    // Verificar password
    if (!password_verify($password, $user['password'])) {
        jsonResponse(['success' => false, 'message' => 'Usuario o contraseña incorrectos'], 401);
    }
    
    // Actualizar último acceso
    $updateStmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?");
    $updateStmt->execute([$user['id_usuario']]);
    
    // Crear sesión
    $_SESSION['user_id'] = $user['id_usuario'];
    $_SESSION['user_code'] = $user['codigo_usuario'];
    $_SESSION['user_name'] = $user['nombre_completo'];
    $_SESSION['user_username'] = $user['usuario'];
    $_SESSION['user_role'] = $user['rol'];
    $_SESSION['user_area'] = $user['area'];
    
    jsonResponse([
        'success' => true,
        'message' => 'Inicio de sesión exitoso',
        'user' => [
            'nombre' => $user['nombre_completo'],
            'rol' => $user['rol'],
            'area' => $user['area']
        ]
    ]);
    
} catch(Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Error en el servidor'], 500);
}
?>
