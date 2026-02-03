<?php
// Test script for API reports
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';

define('SITE_URL', 'http://localhost/mes_hermen');

require_once 'config/database.php';

echo "Database loaded.\n";

$db = getDB();
echo "Connection established.\n";

// Test Consolidado Logic
$sql = "
    SELECT 
        ti.id_tipo_inventario,
        ti.codigo,
        ti.nombre,
        ti.icono,
        ti.color,
        COUNT(i.id_inventario) AS total_items,
        SUM(CASE WHEN i.stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
        SUM(CASE WHEN i.stock_actual > 0 AND i.stock_actual <= i.stock_minimo THEN 1 ELSE 0 END) AS stock_critico,
        COALESCE(SUM(i.stock_actual * i.costo_promedio), 0) AS valor_total
    FROM tipos_inventario ti
    LEFT JOIN inventarios i ON ti.id_tipo_inventario = i.id_tipo_inventario AND i.activo = 1
    WHERE ti.activo = 1
    GROUP BY ti.id_tipo_inventario, ti.codigo, ti.nombre, ti.icono, ti.color
    ORDER BY ti.orden
";

try {
    $stmt = $db->query($sql);
    $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Query executed. Rows: " . count($tipos) . "\n";
    print_r($tipos[0] ?? "No rows");

    // Test JSON Encoding
    $json = json_encode(['success' => true, 'tipos' => $tipos]);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON Error: " . json_last_error_msg() . "\n";
    } else {
        echo "JSON Encode successful.\n";
    }

} catch (PDOException $e) {
    echo "SQL Error: " . $e->getMessage() . "\n";
}
?>