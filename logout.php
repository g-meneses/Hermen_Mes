<?php
/**
 * Logout - Cierra la sesión del usuario
 * Sistema MES Hermen Ltda.
 */
require_once 'config/database.php';

// Destruir todas las variables de sesión
$_SESSION = [];

// Destruir la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Redirigir al login
header('Location: ' . SITE_URL . '/index.php');
exit;
?>