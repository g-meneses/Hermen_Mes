<?php
/**
 * API de Autenticación Móvil
 * Sistema ERP Hermen Ltda.
 * 
 * Autenticación por PIN de 4 dígitos para app móvil
 */

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';

try {
    $db = getDB();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $pin = trim($data['pin'] ?? '');

    if (empty($pin) || strlen($pin) !== 4 || !ctype_digit($pin)) {
        throw new Exception('PIN inválido. Debe ser de 4 dígitos.', 400);
    }

    // Buscar usuario por PIN
    $stmt = $db->prepare("
        SELECT 
            id_usuario,
            codigo_usuario,
            nombre_completo,
            rol,
            area,
            pin
        FROM usuarios 
        WHERE pin = ? AND estado = 'activo'
    ");
    $stmt->execute([$pin]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('PIN incorrecto o usuario inactivo', 401);
    }

    // Actualizar último acceso
    $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?")
        ->execute([$user['id_usuario']]);

    // Respuesta exitosa
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Autenticación exitosa',
        'user' => [
            'id' => (int) $user['id_usuario'],
            'codigo' => $user['codigo_usuario'],
            'nombre' => $user['nombre_completo'],
            'rol' => $user['rol'],
            'area' => $user['area']
        ]
    ]);

} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>