<?php
require_once 'config/database.php';
$db = getDB();

$usuario = 'admin'; // Use lowercase 'admin' as seen in the DB
$password = 'Admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Updating password for user: $usuario\n";

$stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE usuario = ?");
if ($stmt->execute([$hash, $usuario])) {
    echo "Password updated successfully!\n";
} else {
    echo "Error updating password.\n";
}

// Also check if 'Admin' exists as a separate user (case-sensitive check)
$stmt = $db->prepare("SELECT id_usuario FROM usuarios WHERE usuario = 'Admin' COLLATE utf8mb4_bin");
$stmt->execute();
if ($stmt->fetch()) {
    echo "User 'Admin' (capital A) also exists. Updating it too.\n";
    $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE usuario = 'Admin' COLLATE utf8mb4_bin");
    $stmt->execute([$hash]);
}
?>