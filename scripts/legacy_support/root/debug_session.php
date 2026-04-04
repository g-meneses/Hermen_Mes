<?php
/**
 * Diagnóstico de Sesión
 * Ejecutar: http://localhost/mes_hermen/debug_session.php
 */
require_once 'config/database.php';

echo "<h1>Diagnóstico de Sesión</h1>";
echo "<pre>";

echo "=== ESTADO DE SESIÓN ===\n";
echo "session_status(): " . session_status() . " (1=disabled, 2=active)\n";
echo "session_id(): " . session_id() . "\n\n";

echo "=== VARIABLES DE SESIÓN ===\n";
print_r($_SESSION);

echo "\n=== USUARIO LOGUEADO ===\n";
if (isLoggedIn()) {
    echo "✓ Usuario está logueado\n";
    echo "  user_id: " . ($_SESSION['user_id'] ?? 'NO DEFINIDO') . "\n";
    echo "  user_name: " . ($_SESSION['user_name'] ?? 'NO DEFINIDO') . "\n";
    echo "  user_role: " . ($_SESSION['user_role'] ?? 'NO DEFINIDO') . "\n";
    echo "  permisos_inventario: " . (isset($_SESSION['permisos_inventario']) ? 'DEFINIDO' : 'NO DEFINIDO') . "\n";
} else {
    echo "✗ Usuario NO está logueado\n";
    echo "  Razón: \$_SESSION['user_id'] = " . var_export($_SESSION['user_id'] ?? null, true) . "\n";
}

echo "\n=== COOKIES ===\n";
print_r($_COOKIE);

echo "\n=== CONFIGURACIÓN PHP ===\n";
echo "session.save_path: " . session_save_path() . "\n";
echo "session.cookie_path: " . ini_get('session.cookie_path') . "\n";
echo "session.cookie_lifetime: " . ini_get('session.cookie_lifetime') . "\n";

echo "</pre>";

// Enlace de prueba
echo "<h2>Prueba de Navegación</h2>";
echo "<p><a href='modules/inventarios/materias_primas.php'>Ir a Materias Primas</a></p>";
echo "<p><a href='modules/inventarios/index.php'>Ir a Centro de Inventarios</a></p>";
?>