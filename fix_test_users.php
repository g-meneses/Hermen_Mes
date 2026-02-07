<?php
require_once 'config/database.php';
$db = getDB();

echo "=== Corrigiendo IDs de usuarios de prueba ===\n\n";

// Eliminar registros con ID 0
echo "1. Eliminando registros con ID 0...\n";
$db->exec("DELETE FROM permisos_inventario WHERE id_usuario = 0");
$db->exec("DELETE FROM usuarios WHERE id_usuario = 0");
echo "   ✓ Eliminados\n\n";

// Obtener siguiente ID disponible
$max = $db->query("SELECT MAX(id_usuario) as max_id FROM usuarios")->fetch();
$nextId = ($max['max_id'] ?? 0) + 1;
echo "2. Siguiente ID disponible: $nextId\n\n";

$password_hash = password_hash('test123', PASSWORD_DEFAULT);

// Crear test_salidas con ID específico
echo "3. Creando usuarios con IDs explícitos...\n";

$id_salidas = $nextId;
$db->prepare("INSERT INTO usuarios (id_usuario, codigo_usuario, nombre_completo, usuario, password, rol, area, estado)
    VALUES (?, 'TEST-SAL', 'Usuario Prueba Salidas', 'test_salidas', ?, 'operador_inv', 'Almacén', 'activo')")
    ->execute([$id_salidas, $password_hash]);
echo "   ✓ test_salidas creado con ID = $id_salidas\n";

$id_ingresos = $nextId + 1;
$db->prepare("INSERT INTO usuarios (id_usuario, codigo_usuario, nombre_completo, usuario, password, rol, area, estado)
    VALUES (?, 'TEST-ING', 'Usuario Prueba Ingresos', 'test_ingresos', ?, 'operador_inv', 'Almacén', 'activo')")
    ->execute([$id_ingresos, $password_hash]);
echo "   ✓ test_ingresos creado con ID = $id_ingresos\n\n";

// Crear permisos
echo "4. Configurando permisos...\n";

// test_salidas: Solo salidas
$db->prepare("INSERT INTO permisos_inventario 
    (id_usuario, puede_salidas, puede_ingresos, puede_ajustes, puede_crear_items, 
     ver_costos, ver_valores_totales, ver_kardex, ver_reportes, activo)
    VALUES (?, 1, 0, 0, 0, 0, 0, 0, 0, 1)")->execute([$id_salidas]);
echo "   ✓ test_salidas: Puede hacer salidas (NO ingresos)\n";

// test_ingresos: Solo ingresos
$db->prepare("INSERT INTO permisos_inventario 
    (id_usuario, puede_ingresos, puede_salidas, puede_ajustes, puede_crear_items, 
     ver_costos, ver_valores_totales, ver_kardex, ver_reportes, activo)
    VALUES (?, 1, 0, 0, 0, 0, 0, 0, 0, 1)")->execute([$id_ingresos]);
echo "   ✓ test_ingresos: Puede hacer ingresos (NO salidas)\n";

echo "\n=== ✓ Completado ===\n";
echo "\nUsuarios creados:\n";
echo "  test_salidas (ID=$id_salidas) - contraseña: test123 - Permisos: Solo SALIDAS\n";
echo "  test_ingresos (ID=$id_ingresos) - contraseña: test123 - Permisos: Solo INGRESOS\n";
echo "\nCierra sesión y vuelve a iniciar sesión para probar.\n";
?>