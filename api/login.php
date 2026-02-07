<?php
require_once '../config/database.php';

/**
 * Carga los permisos de inventario de un usuario
 * @param PDO $db Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @param string $rol Rol del usuario
 * @return array Permisos del usuario
 */
function cargarPermisosInventario($db, $id_usuario, $rol)
{
    // Roles con acceso completo
    $rolesCompletos = ['admin', 'gerencia', 'coordinador'];

    if (in_array($rol, $rolesCompletos)) {
        return [
            'tiene_restricciones' => false,
            'puede_ingresos' => true,
            'puede_salidas' => true,
            'puede_ajustes' => true,
            'puede_transferencias' => true,
            'puede_crear_items' => true,
            'puede_editar_items' => true,
            'puede_eliminar_items' => true,
            'ver_costos' => true,
            'ver_valores_totales' => true,
            'ver_kardex' => true,
            'ver_reportes' => true,
            'tipos_inventario_permitidos' => null
        ];
    }

    // Buscar permisos específicos
    $stmt = $db->prepare("SELECT * FROM permisos_inventario WHERE id_usuario = ? AND activo = 1");
    $stmt->execute([$id_usuario]);
    $permisos = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$permisos) {
        // Sin permisos específicos = permisos básicos (ver pero no modificar)
        return [
            'tiene_restricciones' => true,
            'puede_ingresos' => false,
            'puede_salidas' => false,
            'puede_ajustes' => false,
            'puede_transferencias' => false,
            'puede_crear_items' => false,
            'puede_editar_items' => false,
            'puede_eliminar_items' => false,
            'ver_costos' => false,
            'ver_valores_totales' => false,
            'ver_kardex' => false,
            'ver_reportes' => false,
            'tipos_inventario_permitidos' => null
        ];
    }

    return [
        'tiene_restricciones' => true,
        'puede_ingresos' => (bool) $permisos['puede_ingresos'],
        'puede_salidas' => (bool) $permisos['puede_salidas'],
        'puede_ajustes' => (bool) $permisos['puede_ajustes'],
        'puede_transferencias' => (bool) $permisos['puede_transferencias'],
        'puede_crear_items' => (bool) $permisos['puede_crear_items'],
        'puede_editar_items' => (bool) $permisos['puede_editar_items'],
        'puede_eliminar_items' => (bool) $permisos['puede_eliminar_items'],
        'ver_costos' => (bool) $permisos['ver_costos'],
        'ver_valores_totales' => (bool) $permisos['ver_valores_totales'],
        'ver_kardex' => (bool) $permisos['ver_kardex'],
        'ver_reportes' => (bool) $permisos['ver_reportes'],
        'tipos_inventario_permitidos' => $permisos['tipos_inventario_permitidos']
    ];
}

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
    $_SESSION['nombre_completo'] = $user['nombre_completo']; // Alias para header.php
    $_SESSION['user_username'] = $user['usuario'];
    $_SESSION['user_role'] = $user['rol'];
    $_SESSION['user_area'] = $user['area'];

    // Cargar permisos de inventario
    $permisos_inv = cargarPermisosInventario($db, $user['id_usuario'], $user['rol']);
    $_SESSION['permisos_inventario'] = $permisos_inv;

    jsonResponse([
        'success' => true,
        'message' => 'Inicio de sesión exitoso',
        'user' => [
            'nombre' => $user['nombre_completo'],
            'rol' => $user['rol'],
            'area' => $user['area'],
            'permisos_inventario' => $permisos_inv
        ]
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Error en el servidor'], 500);
}
?>