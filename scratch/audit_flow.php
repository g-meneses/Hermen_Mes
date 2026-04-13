<?php
/**
 * SCRIPT DE AUDITORÍA: FLUJO MP -> TEJIDO -> WIP
 * MES HERMEN - Diagnóstico de Coherencia y Readiness para FIFO
 */

require_once __DIR__ . '/../config/database.php';

// Simular sesión para bypass de seguridad si es necesario
$_SESSION['user_id'] = 1; 

try {
    $db = getDB();
    
    echo "========================================================\n";
    echo "🔍 AUDITORÍA TÉCNICA: FLUJO MP -> TEJIDO -> WIP\n";
    echo "========================================================\n\n";

    // 1. OBTENER ENTREGAS (SAL-TEJ) - Últimos 90 días o 500 registros
    $fechaLimite = date('Y-m-d', strtotime('-90 days'));
    
    $queryDocs = "
        SELECT 
            d.id_documento,
            d.numero_documento,
            d.fecha_documento,
            d.estado,
            d.tipo_consumo,
            d.modo_asignacion,
            COUNT(dd.id_detalle) as varieda_items,
            SUM(CASE 
                WHEN um.abreviatura = 'kg' THEN dd.cantidad 
                WHEN um.abreviatura = 'g' THEN dd.cantidad / 1000
                ELSE dd.cantidad 
            END) as total_kg_entregado
        FROM documentos_inventario d
        LEFT JOIN documentos_inventario_detalle dd ON d.id_documento = dd.id_documento
        LEFT JOIN unidades_medida um ON dd.id_unidad = um.id_unidad
        WHERE (d.tipo_documento = 'SALIDA' AND d.tipo_consumo = 'TEJIDO')
           OR d.numero_documento LIKE 'SAL-TEJ%'
        GROUP BY d.id_documento
        ORDER BY d.fecha_documento DESC, d.id_documento DESC
        LIMIT 500
    ";
    
    $docs = $db->query($queryDocs)->fetchAll();
    echo "📦 Documentos de Salida (MP a Tejido) detectados: " . count($docs) . "\n";

    // 2. OBTENER LOTES WIP Y CONSUMO TEÓRICO
    $queryLotes = "
        SELECT 
            l.id_lote_wip,
            l.codigo_lote,
            l.id_producto,
            pt.codigo_producto,
            l.cantidad_base_unidades,
            l.id_documento_salida,
            l.estado_lote,
            l.fecha_inicio,
            l.costo_mp_acumulado,
            DATEDIFF(NOW(), l.fecha_inicio) as antiguedad_dias
        FROM lote_wip l
        JOIN productos_tejidos pt ON l.id_producto = pt.id_producto
        ORDER BY l.fecha_inicio DESC
        LIMIT 1000
    ";
    
    $lotes = $db->query($queryLotes)->fetchAll();
    echo "🧵 Lotes WIP analizados: " . count($lotes) . "\n\n";

    // 3. ANÁLISIS DE COHERENCIA (ENTREGA vs CONSUMO)
    $reporteConsumo = [];
    $lotesSinDoc = 0;
    
    foreach ($lotes as $lote) {
        if (!$lote['id_documento_salida']) {
            $lotesSinDoc++;
            continue;
        }
        
        $idDoc = $lote['id_documento_salida'];
        if (!isset($reporteConsumo[$idDoc])) {
            $reporteConsumo[$idDoc] = [
                'entregado' => 0,
                'consumido_teorico' => 0,
                'lotes' => []
            ];
            // Buscar info del doc
            foreach ($docs as $d) {
                if ($d['id_documento'] == $idDoc) {
                    $reporteConsumo[$idDoc]['entregado'] = $d['total_kg_entregado'];
                    $reporteConsumo[$idDoc]['numero'] = $d['numero_documento'];
                    break;
                }
            }
        }
        
        // Calcular consumo teórico del lote vía BOM
        $queryBOM = "
            SELECT 
                bd.gramos_por_docena,
                b.merma_pct as merma_cabecera,
                bd.merma_pct as merma_detalle
            FROM bom_productos b
            JOIN bom_productos_detalle bd ON b.id_bom = bd.id_bom
            WHERE b.id_producto = ? AND b.estado = 'ACTIVO'
        ";
        $detallesBOM = $db->prepare($queryBOM);
        $detallesBOM->execute([$lote['id_producto']]);
        $itemsBOM = $detallesBOM->fetchAll();
        
        $consumoLoteKg = 0;
        foreach ($itemsBOM as $item) {
            $factorDocena = $lote['cantidad_base_unidades'] / 12;
            $mermaTotal = 1 + (($item['merma_cabecera'] + $item['merma_detalle']) / 100);
            $consumoLoteKg += ($item['gramos_por_docena'] * $factorDocena * $mermaTotal) / 1000;
        }
        
        $reporteConsumo[$idDoc]['consumido_teorico'] += $consumoLoteKg;
        $reporteConsumo[$idDoc]['lotes'][] = $lote['codigo_lote'];
    }

    echo "📊 ALERTAS INICIALES:\n";
    echo "--------------------------------------------------------\n";
    echo "⚠️ Lotes sin documento de origen (Error crítico): $lotesSinDoc\n";
    
    $negativos = 0;
    foreach ($reporteConsumo as $id => $data) {
        $saldo = $data['entregado'] - $data['consumido_teorico'];
        if ($saldo < -0.01) { // Margen de error decimal
            $negativos++;
        }
    }
    echo "🔴 Documentos con Saldo Negativo (Consumo > Entrega): $negativos\n\n";

    // 4. ANÁLISIS DE ACUMULACIÓN DE LOTES ABIERTOS
    $lotesAbiertos = array_filter($lotes, function($l) { return $l['estado_lote'] == 'ACTIVO'; });
    $totalAbiertos = count($lotesAbiertos);
    $edades = array_column($lotesAbiertos, 'antiguedad_dias');
    $promedioEdad = $totalAbiertos > 0 ? array_sum($edades) / $totalAbiertos : 0;
    
    echo "🏢 ESTADO DE LOTES ABIERTOS EN TEJIDO:\n";
    echo "--------------------------------------------------------\n";
    echo "🔹 Lotes Abiertos simultáneamente: $totalAbiertos\n";
    echo "🔹 Antigüedad promedio: " . round($promedioEdad, 1) . " días\n";
    
    // Detectar si hay entregas nuevas antes de cerrar lotes viejos
    // (Simplificación: si hay un lote abierto viejo y un lote reciente con distinto doc)
    $docsAbiertos = [];
    foreach($lotesAbiertos as $la) {
        if($la['id_documento_salida']) $docsAbiertos[] = $la['id_documento_salida'];
    }
    $docsUnicosAbiertos = array_unique($docsAbiertos);
    echo "🔹 Documentos MP involucrados en lotes abiertos: " . count($docsUnicosAbiertos) . "\n\n";

    // 5. EVALUACIÓN DE SUPERPOSICIÓN / 1-A-1
    echo "🔄 ANÁLISIS DE MODELO 1-A-1:\n";
    echo "--------------------------------------------------------\n";
    $multipleLotesPerDoc = 0;
    foreach($reporteConsumo as $data) {
        if (count($data['lotes']) > 1) $multipleLotesPerDoc++;
    }
    echo "🔸 Documentos que alimentan a MÚLTIPLES lotes: $multipleLotesPerDoc\n";
    echo "🔸 (Si este número es alto, el sistema ya soporta 1-N, pero falta N-1 o N-N)\n\n";

    // 6. MUESTRA PARA REPORTE (TOP 10 Documentos con sus lotes y saldos)
    echo "📝 DETALLE DE COHERENCIA (Muestra Top 10):\n";
    echo "-------------------------------------------------------------------------------------------------\n";
    echo "| Documento    | Entregado (Kg) | Consumido (Kg) | Saldo (Kg) | Lotes vinculados                |\n";
    echo "-------------------------------------------------------------------------------------------------\n";
    
    $count = 0;
    foreach ($reporteConsumo as $id => $data) {
        if ($count++ >= 10) break;
        $num = str_pad($data['numero'] ?? "ID:$id", 12);
        $ent = str_pad(number_format($data['entregado'], 2), 14);
        $con = str_pad(number_format($data['consumido_teorico'], 2), 14);
        $sal = str_pad(number_format($data['entregado'] - $data['consumido_teorico'], 2), 10);
        $lts = implode(", ", array_slice($data['lotes'], 0, 2));
        if (count($data['lotes']) > 2) $lts .= "...";
        
        echo "| $num | $ent | $con | $sal | $lts\n";
    }
    echo "-------------------------------------------------------------------------------------------------\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
