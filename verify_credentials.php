<?php
require_once 'config/database.php';
$db = getDB();

$usuario = 'Admin';
$password = 'Admin123';

echo "Checking user: $usuario\n";

$stmt = $db->prepare("SELECT id_usuario, usuario, password, rol, estado FROM usuarios WHERE usuario = ?");
$stmt->execute([$usuario]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "User found: " . print_r($user, true) . "\n";
    if (password_verify($password, $user['password'])) {
        echo "Password matches!\n";
    } else {
        echo "Password DOES NOT match.\n";
        // Check if the hashed password looks like 'Admin123' (plain text check just in case)
        if ($user['password'] === $password) {
            echo "Password matched as plain text! (This is insecure and login.php uses password_verify)\n";
        }
    }
} else {
    echo "User '$usuario' not found.\n";
    // Check for lowercase 'admin'
    $stmt = $db->prepare("SELECT id_usuario, usuario, password, rol, estado FROM usuarios WHERE usuario = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        echo "Found 'admin' (lowercase): " . print_r($admin, true) . "\n";
        if (password_verify($password, $admin['password'])) {
            echo "Password matches for 'admin' (lowercase)!\n";
        } else {
            echo "Password DOES NOT match for 'admin' (lowercase).\n";
        }
    }
}
?>