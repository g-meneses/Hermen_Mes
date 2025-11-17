<?php
/**
 * TEST DE APIs
 * Archivo temporal para probar las APIs directamente
 * ELIMINA este archivo después de usarlo
 */

require_once '../config/database.php';

echo "<h1>Test de APIs - MES Hermen</h1>";
echo "<hr>";

// Test 1: Verificar sesión
echo "<h2>1. Test de Sesión</h2>";
if (isLoggedIn()) {
    echo "✅ Usuario logueado: " . $_SESSION['user_name'] . "<br>";
    echo "Rol: " . $_SESSION['user_role'] . "<br>";
} else {
    echo "❌ No hay sesión activa<br>";
    echo "<a href='../index.php'>Ir a Login</a><br>";
    exit();
}

// Test 2: Conexión a base de datos
echo "<hr>";
echo "<h2>2. Test de Conexión a BD</h2>";
try {
    $db = getDB();
    echo "✅ Conexión exitosa<br>";
} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    exit();
}

// Test 3: Líneas de producto
echo "<hr>";
echo "<h2>3. Test de Líneas de Producto</h2>";
try {
    $stmt = $db->query("SELECT * FROM lineas_producto WHERE activo = TRUE");
    $lineas = $stmt->fetchAll();
    echo "✅ Líneas encontradas: " . count($lineas) . "<br>";
    echo "<ul>";
    foreach($lineas as $linea) {
        echo "<li>{$linea['codigo_linea']} - {$linea['nombre_linea']}</li>";
    }
    echo "</ul>";
} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 4: Tipos de producto
echo "<hr>";
echo "<h2>4. Test de Tipos de Producto</h2>";
try {
    $stmt = $db->query("SELECT * FROM tipos_producto WHERE activo = TRUE");
    $tipos = $stmt->fetchAll();
    echo "✅ Tipos encontrados: " . count($tipos) . "<br>";
    echo "<ul>";
    foreach($tipos as $tipo) {
        echo "<li>{$tipo['nombre_tipo']} ({$tipo['categoria']})</li>";
    }
    echo "</ul>";
} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 5: Diseños
echo "<hr>";
echo "<h2>5. Test de Diseños</h2>";
try {
    $stmt = $db->query("SELECT * FROM disenos WHERE activo = TRUE");
    $disenos = $stmt->fetchAll();
    echo "✅ Diseños encontrados: " . count($disenos) . "<br>";
    echo "<ul>";
    foreach($disenos as $diseno) {
        echo "<li>{$diseno['nombre_diseno']}</li>";
    }
    echo "</ul>";
} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 6: Productos
echo "<hr>";
echo "<h2>6. Test de Productos</h2>";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM productos_tejidos WHERE activo = TRUE");
    $result = $stmt->fetch();
    echo "✅ Productos encontrados: " . $result['total'] . "<br>";
    
    if ($result['total'] == 0) {
        echo "<strong style='color: red;'>⚠️ NO HAY PRODUCTOS EN LA BASE DE DATOS</strong><br>";
        echo "Necesitas ejecutar: productos_tejidos_insert.sql<br>";
    } else {
        // Mostrar algunos productos
        $stmt = $db->query("SELECT * FROM productos_tejidos WHERE activo = TRUE LIMIT 5");
        $productos = $stmt->fetchAll();
        echo "<ul>";
        foreach($productos as $p) {
            echo "<li>{$p['codigo_producto']} - {$p['descripcion_completa']}</li>";
        }
        echo "</ul>";
    }
} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 7: API de Catálogos
echo "<hr>";
echo "<h2>7. Test de API Catálogos</h2>";
try {
    $catalogosData = [
        'success' => true,
        'lineas' => $lineas,
        'tipos' => $tipos,
        'disenos' => $disenos
    ];
    echo "✅ JSON válido: " . (json_encode($catalogosData) !== false ? 'SÍ' : 'NO') . "<br>";
    echo "<details>";
    echo "<summary>Ver JSON</summary>";
    echo "<pre>" . json_encode($catalogosData, JSON_PRETTY_PRINT) . "</pre>";
    echo "</details>";
} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h2>✅ RESUMEN</h2>";
echo "<p>Si todos los tests pasaron, las APIs deberían funcionar correctamente.</p>";
echo "<p><strong>ELIMINA este archivo después de usar: api/test.php</strong></p>";
?>
