<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Adjust path as needed
require_once 'config/database.php';

try {
    $db = getDB();

    echo "=== TIPOS DE INVENTARIO ===\n";
    $stmt = $db->query("SELECT * FROM tipos_inventario");
    $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($tipos);

    echo "\n=== MOVIMIENTOS RECIENTES (LIMIT 5) ===\n";
    $stmt = $db->query("
        SELECT 
            m.id_movimiento, 
            m.documento_numero,
            m.tipo_movimiento,
            i.codigo as item_code, 
            i.id_tipo_inventario, 
            t.codigo as tipo_code
        FROM movimientos_inventario m
        JOIN inventarios i ON m.id_inventario = i.id_inventario
        JOIN tipos_inventario t ON i.id_tipo_inventario = t.id_tipo_inventario
        ORDER BY m.fecha_movimiento DESC
        LIMIT 5
    ");
    $movs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($movs);

    echo "\n=== MOVIMIENTOS FILTRADOS POR EMP (asumiendo codigo='EMP') ===\n";
    // Find EMP id
    $empId = null;
    foreach ($tipos as $t) {
        if ($t['codigo'] === 'EMP') {
            $empId = $t['id_tipo_inventario'];
            break;
        }
    }

    if ($empId) {
        echo "ID para EMP es: $empId\n";
        $stmt = $db->prepare("
            SELECT count(*) as total
            FROM movimientos_inventario m
            JOIN inventarios i ON m.id_inventario = i.id_inventario
            WHERE i.id_tipo_inventario = ?
        ");
        $stmt->execute([$empId]);
        print_r($stmt->fetch(PDO::FETCH_ASSOC));
    } else {
        echo "No se encontro tipo EMP\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
