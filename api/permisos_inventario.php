<?php
/**
 * API de Permisos de Inventario
 * Gestiona los permisos granulares para el módulo de inventarios
 * 
 * Acciones:
 * - GET ?action=verificar&permiso=NOMBRE - Verifica si el usuario actual tiene un permiso
 * - GET ?action=obtener - Obtiene todos los permisos del usuario actual
 * - GET ?action=obtener&id_usuario=X - Obtiene permisos de un usuario específico (solo admin)
 */

require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? 'obtener';

try {
    $db = getDB();

    switch ($action) {
        case 'verificar':
            verificarPermiso($db);
            break;

        case 'obtener':
            obtenerPermisos($db);
            break;

        case 'actualizar':
            actualizarPermisos($db);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Acción no válida'], 400);
    }

} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}

/**
 * Verifica si el usuario tiene un permiso específico
 */
function verificarPermiso($db)
{
    $permiso = $_GET['permiso'] ?? '';
    $id_usuario = $_SESSION['user_id'] ?? null;

    if (!$id_usuario) {
        jsonResponse(['success' => false, 'tiene_permiso' => false, 'message' => 'No autenticado']);
        return;
    }

    $tiene_permiso = tienePermisoInventario($db, $id_usuario, $permiso);

    jsonResponse([
        'success' => true,
        'tiene_permiso' => $tiene_permiso,
        'permiso' => $permiso
    ]);
}

/**
 * Obtiene todos los permisos del usuario
 */
function obtenerPermisos($db)
{
    $id_usuario = $_GET['id_usuario'] ?? ($_SESSION['user_id'] ?? null);

    if (!$id_usuario) {
        jsonResponse(['success' => false, 'message' => 'Usuario no especificado']);
        return;
    }

    // Solo admin puede ver permisos de otros usuarios
    if ($id_usuario != ($_SESSION['user_id'] ?? null) && ($_SESSION['user_role'] ?? '') !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Sin autorización']);
        return;
    }

    $permisos = obtenerPermisosUsuario($db, $id_usuario);

    jsonResponse([
        'success' => true,
        'permisos' => $permisos
    ]);
}

/**
 * Actualiza los permisos de un usuario (solo admin)
 */
function actualizarPermisos($db)
{
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Solo administradores pueden modificar permisos']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $id_usuario = $data['id_usuario'] ?? null;

    if (!$id_usuario) {
        jsonResponse(['success' => false, 'message' => 'Usuario no especificado']);
        return;
    }

    $campos = [
        'puede_ingresos',
        'puede_salidas',
        'puede_ajustes',
        'puede_transferencias',
        'puede_crear_items',
        'puede_editar_items',
        'puede_eliminar_items',
        'ver_costos',
        'ver_valores_totales',
        'ver_kardex',
        'ver_reportes',
        'tipos_inventario_permitidos',
        'activo'
    ];

    $updates = [];
    $params = [];

    foreach ($campos as $campo) {
        if (isset($data[$campo])) {
            $updates[] = "$campo = ?";
            $params[] = $data[$campo];
        }
    }

    if (empty($updates)) {
        jsonResponse(['success' => false, 'message' => 'No hay cambios para aplicar']);
        return;
    }

    $params[] = $id_usuario;

    $sql = "UPDATE permisos_inventario SET " . implode(', ', $updates) . " WHERE id_usuario = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    jsonResponse(['success' => true, 'message' => 'Permisos actualizados']);
}

// ============ FUNCIONES UTILITARIAS (Reutilizables) ============

/**
 * Verifica si un usuario tiene un permiso específico de inventario
 * 
 * @param PDO $db Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @param string $permiso Nombre del permiso a verificar
 * @return bool True si tiene el permiso, False si no
 */
function tienePermisoInventario($db, $id_usuario, $permiso)
{
    // Roles con acceso completo
    $rolesCompletos = ['admin', 'gerencia', 'coordinador'];

    // Verificar rol del usuario
    $stmt = $db->prepare("SELECT rol FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && in_array($user['rol'], $rolesCompletos)) {
        return true; // Acceso completo para estos roles
    }

    // Verificar permisos específicos
    $stmt = $db->prepare("SELECT * FROM permisos_inventario WHERE id_usuario = ? AND activo = 1");
    $stmt->execute([$id_usuario]);
    $permisos = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$permisos) {
        return false; // Sin permisos configurados
    }

    // Mapeo de permisos
    $mapeo = [
        'ingresos' => 'puede_ingresos',
        'salidas' => 'puede_salidas',
        'ajustes' => 'puede_ajustes',
        'transferencias' => 'puede_transferencias',
        'crear_items' => 'puede_crear_items',
        'editar_items' => 'puede_editar_items',
        'eliminar_items' => 'puede_eliminar_items',
        'ver_costos' => 'ver_costos',
        'ver_valores' => 'ver_valores_totales',
        'ver_kardex' => 'ver_kardex',
        'ver_reportes' => 'ver_reportes'
    ];

    $campo = $mapeo[$permiso] ?? $permiso;

    return isset($permisos[$campo]) && $permisos[$campo] == 1;
}

/**
 * Obtiene todos los permisos de un usuario
 * 
 * @param PDO $db Conexión a la base de datos
 * @param int $id_usuario ID del usuario
 * @return array Permisos del usuario
 */
function obtenerPermisosUsuario($db, $id_usuario)
{
    // Verificar rol del usuario primero
    $stmt = $db->prepare("SELECT rol FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $rolesCompletos = ['admin', 'gerencia', 'coordinador'];

    if ($user && in_array($user['rol'], $rolesCompletos)) {
        // Retornar permisos completos
        return [
            'tiene_restricciones' => false,
            'rol' => $user['rol'],
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
        // Sin permisos = sin acceso
        return [
            'tiene_restricciones' => true,
            'rol' => $user['rol'] ?? 'unknown',
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

    $permisos['tiene_restricciones'] = true;
    $permisos['rol'] = $user['rol'] ?? 'operador_inv';

    // Convertir valores a booleanos
    $boolFields = [
        'puede_ingresos',
        'puede_salidas',
        'puede_ajustes',
        'puede_transferencias',
        'puede_crear_items',
        'puede_editar_items',
        'puede_eliminar_items',
        'ver_costos',
        'ver_valores_totales',
        'ver_kardex',
        'ver_reportes',
        'activo'
    ];

    foreach ($boolFields as $field) {
        if (isset($permisos[$field])) {
            $permisos[$field] = (bool) $permisos[$field];
        }
    }

    return $permisos;
}
?>