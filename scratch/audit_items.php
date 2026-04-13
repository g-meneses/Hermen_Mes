<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

echo "--- EVALUACIÓN DE SALDOS DETALLADA (Kg) ---\n";
echo str_pad("Doc #", 25) . " | " . str_pad("Item", 30) . " | " . str_pad("Entregado", 12) . " | " . str_pad("Consumido", 12) . " | Saldo\n";
echo str_repeat("-", 95) . "\n";

// Obtener todos los documentos SAL-TEJ relevantes
$query = "
    SELECT d.id_documento, d.numero_documento, d.fecha_documento
    FROM documentos_inventario d
    WHERE d.tipo_documento = 'SALIDA' AND d.tipo_consumo = 'TEJIDO'
    ORDER BY d.fecha_documento DESC
    LIMIT 20
";
$docs = $db->query($query)->fetchAll();

foreach ($docs as $doc) {
    // Items entregados en este documento
    $queryItems = "
        SELECT i.id_inventario, i.nombre, dd.cantidad, um.abreviatura
        FROM documentos_inventario_detalle dd
        JOIN inventarios i ON dd.id_inventario = i.id_inventario
        JOIN unidades_medida um ON i.id_unidad = um.id_unidad
        WHERE dd.id_documento = ?
    ";
    $stmtItems = $db->prepare($queryItems);
    $stmtItems->execute([$doc['id_documento']]);
    $entregados = $stmtItems->fetchAll();
    
    // Lotes vinculados
    $queryLotes = "SELECT id_lote_wip, id_producto, cantidad_base_unidades FROM lote_wip WHERE id_documento_salida = ?";
    $stmtLotes = $db->prepare($queryLotes);
    $stmtLotes->execute([$doc['id_documento']]);
    $lotes = $stmtLotes->fetchAll();
    
    foreach ($entregados as $e) {
        $entKg = ($e['abreviatura'] == 'g' ? $e['cantidad'] / 1000 : $e['cantidad']);
        $consTotalKg = 0;
        
        foreach ($lotes as $l) {
            // Ver si este item está en el BOM de este producto
            $queryBOM = "
                SELECT bd.gramos_por_docena, b.merma_pct as m_c, bd.merma_pct as m_d
                FROM bom_productos b
                JOIN bom_productos_detalle bd ON b.id_bom = bd.id_bom
                WHERE b.id_producto = ? AND bd.id_inventario = ? AND b.estado = 'ACTIVO'
            ";
            $stmtBOM = $db->prepare($queryBOM);
            $stmtBOM->execute([$l['id_producto'], $e['id_inventario']]);
            $bomInfo = $stmtBOM->fetch();
            
            if ($bomInfo) {
                $factor = $l['cantidad_base_unidades'] / 12;
                $merma = 1 + (($bomInfo['m_c'] + $bomInfo['m_d']) / 100);
                $consTotalKg += ($bomInfo['gramos_por_docena'] * $factor * $merma) / 1000;
            }
        }
        
        $saldo = $entKg - $consTotalKg;
        echo str_pad($doc['numero_documento'], 25) . " | " . 
             str_pad(substr($e['nombre'], 0, 30), 30) . " | " . 
             str_pad(number_format($entKg, 2), 12, " ", STR_PAD_LEFT) . " | " . 
             str_pad(number_format($consTotalKg, 2), 12, " ", STR_PAD_LEFT) . " | " . 
             number_format($saldo, 2) . "\n";
    }
}
