<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

echo "========================================================\n";
echo "✔️ VERIFICACIÓN DE LIMPIEZA DE DATA DE PRUEBA\n";
echo "========================================================\n\n";

$tablasWIP = [
    'lote_wip', 
    'movimientos_wip', 
    'planillas_tejido', 
    'consumos_wip_detalle', 
    'consumos_wip_pendientes',
    'auditorias_wip_tejido',
    'auditorias_wip_tejido_detalle'
];

echo "📋 Resumen de Tablas Limpias:\n";
foreach ($tablasWIP as $tabla) {
    $count = $db->query("SELECT COUNT(*) FROM $tabla")->fetchColumn();
    if ($count == 0) {
        echo "✅ $tabla: vacía (0 registros).\n";
    } else {
        echo "❌ $tabla: ERROR - contiene $count registros.\n";
    }
}

echo "\n📋 Verificación de Documentos SAL-TEJ:\n";
$salTejCount = $db->query("SELECT COUNT(*) FROM documentos_inventario WHERE numero_documento LIKE 'SAL-TEJ%'")->fetchColumn();
echo "🔹 Documentos SAL-TEJ conservados: $salTejCount\n";

$saldoError = $db->query("
    SELECT COUNT(*) 
    FROM documentos_inventario_detalle dd
    JOIN documentos_inventario d ON d.id_documento = dd.id_documento
    WHERE d.numero_documento LIKE 'SAL-TEJ%' AND dd.saldo_disponible != dd.cantidad
")->fetchColumn();

if ($saldoError == 0) {
    echo "✅ saldos_disponibles: restaurados al 100% de la cantidad original.\n";
} else {
    echo "❌ saldos_disponibles: ERROR - se encontraron $saldoError discrepancias.\n";
}

echo "\n========================================================\n";
