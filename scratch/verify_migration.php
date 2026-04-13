<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

echo "========================================================\n";
echo "✔️ VALIDACIÓN POST-MIGRACIÓN: SISTEMA WIP INTEGRAL\n";
echo "========================================================\n\n";

// 1. Validar Tablas
$tablasNuevas = [
    'consumos_wip_detalle', 
    'consumos_wip_pendientes', 
    'auditorias_wip_tejido', 
    'auditorias_wip_tejido_detalle'
];

echo "📋 Verificando Tablas Nuevas:\n";
foreach ($tablasNuevas as $tabla) {
    $stmt = $db->query("SHOW TABLES LIKE '$tabla'");
    if ($stmt->fetch()) {
        echo "✅ Tabla '$tabla' creada correctamente.\n";
    } else {
        echo "❌ ERROR: Tabla '$tabla' NO encontrada.\n";
    }
}

// 2. Validar Campo saldo_disponible
echo "\n📋 Verificando Campo 'saldo_disponible':\n";
$stmt = $db->query("SHOW COLUMNS FROM documentos_inventario_detalle LIKE 'saldo_disponible'");
if ($stmt->fetch()) {
    echo "✅ Campo 'saldo_disponible' existe.\n";
} else {
    echo "❌ ERROR: Campo 'saldo_disponible' NO encontrado.\n";
}

// 3. Validar Inicialización de Saldos
echo "\n📋 Estadísticas de Inicialización (SAL-TEJ):\n";
$queryStats = "
    SELECT 
        COUNT(*) as total_registros,
        SUM(CASE WHEN saldo_disponible IS NOT NULL THEN 1 ELSE 0 END) as registros_inicializados,
        SUM(CASE WHEN saldo_disponible IS NULL THEN 1 ELSE 0 END) as registros_sin_inicializar,
        SUM(CASE WHEN saldo_disponible < 0 THEN 1 ELSE 0 END) as saldos_negativos
    FROM documentos_inventario_detalle
";
$stats = $db->query($queryStats)->fetch();
print_r($stats);

$querySalTej = "
    SELECT COUNT(*) as total_sal_tej
    FROM documentos_inventario_detalle dd
    JOIN documentos_inventario d ON d.id_documento = dd.id_documento
    WHERE d.tipo_documento = 'SALIDA' 
      AND (d.tipo_consumo = 'TEJIDO' OR d.numero_documento LIKE 'SAL-TEJ%')
";
$salTejCount = $db->query($querySalTej)->fetch();
echo "🔹 Registros SAL-TEJ esperados: " . $salTejCount['total_sal_tej'] . "\n";

// 4. Buscar Anomalías
echo "\n📋 Detección de Anomalías:\n";
$queryAnomalias = "
    SELECT d.numero_documento, dd.id_detalle, dd.cantidad, dd.saldo_disponible
    FROM documentos_inventario_detalle dd
    JOIN documentos_inventario d ON d.id_documento = dd.id_documento
    WHERE d.tipo_documento = 'SALIDA' 
      AND (d.tipo_consumo = 'TEJIDO' OR d.numero_documento LIKE 'SAL-TEJ%')
      AND dd.saldo_disponible != dd.cantidad
";
$anomalias = $db->query($queryAnomalias)->fetchAll();
if (empty($anomalias)) {
    echo "✅ No se detectaron discrepancias entre cantidad y saldo inicial.\n";
} else {
    echo "⚠️ ADVERTENCIA: Se encontraron " . count($anomalias) . " discrepancias.\n";
    print_r(array_slice($anomalias, 0, 5));
}
echo "\n========================================================\n";
