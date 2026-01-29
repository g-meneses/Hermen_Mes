<?php
/**
 * Test de diagnóstico para la API
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DIAGNÓSTICO DE API ===\n\n";

// Test 1: Verificar que el archivo database.php existe
echo "1. Verificando database.php... ";
if (file_exists('../config/database.php')) {
    echo "✓ Existe\n";
    require_once '../config/database.php';
} else {
    echo "✗ NO EXISTE\n";
    die();
}

// Test 2: Verificar conexión a BD
echo "2. Probando conexión a BD... ";
try {
    $db = getDB();
    echo "✓ Conectado\n";
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    die();
}

// Test 3: Verificar tabla tipos_inventario
echo "3. Verificando tabla tipos_inventario... ";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM tipos_inventario");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ Existe (" . $result['total'] . " registros)\n";
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}

// Test 4: Verificar tabla inventarios
echo "4. Verificando tabla inventarios... ";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM inventarios");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ Existe (" . $result['total'] . " registros)\n";
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}

// Test 5: Ejecutar query del resumen
echo "5. Ejecutando query de resumen... ";
try {
    $stmt = $db->query("
        SELECT 
            ti.id_tipo_inventario,
            ti.codigo,
            ti.nombre,
            ti.icono,
            ti.color,
            ti.orden,
            COUNT(i.id_inventario) AS total_items,
            SUM(CASE WHEN i.stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
            SUM(CASE WHEN i.stock_actual > 0 AND i.stock_actual <= i.stock_minimo THEN 1 ELSE 0 END) AS stock_critico,
            SUM(CASE WHEN i.stock_actual > i.stock_minimo THEN 1 ELSE 0 END) AS stock_ok,
            COALESCE(SUM(i.stock_actual * i.costo_unitario), 0) AS valor_total
        FROM tipos_inventario ti
        LEFT JOIN inventarios i ON ti.id_tipo_inventario = i.id_tipo_inventario AND i.activo = 1
        WHERE ti.activo = 1
        GROUP BY ti.id_tipo_inventario, ti.codigo, ti.nombre, ti.icono, ti.color, ti.orden
        ORDER BY ti.orden
    ");
    $resumen = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✓ OK (" . count($resumen) . " tipos encontrados)\n";

    echo "\n=== RESULTADO JSON ===\n";
    echo json_encode([
        'success' => true,
        'resumen' => $resumen,
        'totales' => [
            'items' => array_sum(array_column($resumen, 'total_items')),
            'valor' => array_sum(array_column($resumen, 'valor_total')),
            'alertas' => array_sum(array_map(function ($t) {
                return $t['stock_critico'] + $t['sin_stock'];
            }, $resumen))
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}

echo "\n\n=== FIN DEL DIAGNÓSTICO ===\n";
