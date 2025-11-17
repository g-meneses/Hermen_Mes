<?php
require_once '../config/database.php';

session_destroy();
$_SESSION = [];

jsonResponse(['success' => true, 'message' => 'Sesión cerrada exitosamente']);
?>
