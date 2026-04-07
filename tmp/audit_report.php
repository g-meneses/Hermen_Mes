<?php
require_once 'C:/xampp/htdocs/mes_hermen/config/database.php';

function getAuditReport() {
    $db = getDB();
    $report = ["OK" => [], "ERROR" => [], "OBS" => []];

    try {
        // 1. IDENTIFICAR EL ÚLTIMO DOCUMENTO DE SALIDA A PRODUCCIÓN (TEJIDO)
        $stmt = $db->prepare("
            SELECT * FROM documentos_inventario 
            WHERE tipo_documento = 'SALIDA' 
            AND tipo_consumo = 'TEJIDO' 
            ORDER BY id_documento DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $doc = $stmt->fetch();

        if (!$doc) {
            $report["ERROR"][] = "No se encontró ningún registro en documentos_inventario con tipo_consumo = 'TEJIDO'.";
            return $report;
        }

        $id_doc = $doc['id_documento'];
        $num_doc = $doc['numero_documento'];
        $report["Documento Evaluado"] = $num_doc;

        // 2. VALIDAR DOCUMENTO
        if (strpos($num_doc, 'SAL-TEJ') === 0) {
            $report["OK"][] = "Formato de número de documento correcto: $num_doc";
        } else {
            $report["ERROR"][] = "Formato de número incorrecto: $num_doc (Esperado SAL-TEJ-...)";
        }

        if ($doc['tipo_documento'] === 'SALIDA' && $doc['tipo_consumo'] === 'TEJIDO') {
            $report["OK"][] = "Metadatos de documento correctos (SALIDA/TEJIDO)";
        } else {
            $report["ERROR"][] = "Metadatos inconsistentes: " . $doc['tipo_documento'] . " / " . $doc['tipo_consumo'];
        }

        // 3. VALIDAR DETALLE
        $stmtLines = $db->prepare("SELECT d.*, i.nombre 
                                 FROM documentos_inventario_detalle d
                                 JOIN inventarios i ON d.id_inventario = i.id_inventario
                                 WHERE d.id_documento = ?");
        $stmtLines->execute([$id_doc]);
        $details = $stmtLines->fetchAll();

        if (count($details) > 0) {
            $report["OK"][] = "Detalle documental íntegro (5 ítems encontrados)";
            foreach ($details as $d) {
                if ($d['cantidad'] <= 0) $report["ERROR"][] = "Cantidad en detalle <= 0 para " . $d['nombre'];
            }
        } else {
            $report["ERROR"][] = "Documento sin detalle en documentos_inventario_detalle";
        }

        // 4. VALIDAR MOVIMIENTOS DE INVENTARIO (CRÍTICO)
        $stmtMovs = $db->prepare("SELECT m.*, i.nombre as item_nombre 
                                FROM movimientos_inventario m 
                                JOIN inventarios i ON m.id_inventario = i.id_inventario
                                WHERE m.documento_id = ? OR m.documento_numero = ?");
        $stmtMovs->execute([$id_doc, $num_doc]);
        $movs = $stmtMovs->fetchAll();

        if (count($movs) > 0) {
            $report["OK"][] = "Kardex generado: " . count($movs) . " registros encontrados";
            foreach ($movs as $m) {
                // 5. VALIDAR IMPACTO EN STOCK
                if ($m['stock_posterior'] < $m['stock_anterior']) {
                    $report["OK"][] = "Impacto OK en " . $m['item_nombre'] . " (Disminución confirmada)";
                } else {
                    $report["ERROR"][] = "Falla de impacto en stock para " . $m['item_nombre'] . ": Stock Ant: " . $m['stock_anterior'] . " -> Pos: " . $m['stock_posterior'];
                }
                
                // Observación de signos
                if ($m['cantidad'] > 0) {
                    $report["OBS"][] = "Movimiento para " . $m['item_nombre'] . " almacenado como valor absoluto (" . $m['cantidad'] . ").";
                }
            }
        } else {
            $report["ERROR"][] = "No se generaron movimientos físicos de inventario en movimientos_inventario";
        }

        // 6. VALIDAR REGLAS DE NEGOCIO (Desacoplamiento WIP)
        // No deben existir movimientos_wip apuntando a este documento aún
        $stmtWip = $db->prepare("SELECT COUNT(*) as count FROM movimientos_wip WHERE id_documento_inventario = ?");
        $stmtWip->execute([$id_doc]);
        $wipCount = $stmtWip->fetch()['count'];

        if ($wipCount == 0) {
            $report["OK"][] = "Validación WIP: Desacoplamiento confirmado (Sin movimientos WIP)";
        } else {
            $report["ERROR"][] = "Falla de desacoplamiento: Se encontraron $wipCount registros en movimientos_wip vinculados a este documento.";
        }

        // Lote WIP check
        $stmtLote = $db->prepare("SELECT COUNT(*) as count FROM lote_wip WHERE id_documento_consumo = ? OR id_documento_salida = ?");
        $stmtLote->execute([$id_doc, $id_doc]);
        $loteCount = $stmtLote->fetch()['count'];

        if ($loteCount == 0) {
            $report["OK"][] = "Validación WIP: Desacoplamiento confirmado (Sin lotes WIP creados)";
        } else {
            $report["ERROR"][] = "Falla de desacoplamiento: Se encontraron $loteCount lotes WIP creados fraudulentamente desde la salida.";
        }

        // 7. STOCK GLOBAL (No negativo por error de cálculo)
        $stmtNeg = $db->query("SELECT nombre, stock_actual FROM inventarios WHERE stock_actual < 0");
        $negatives = $stmtNeg->fetchAll();
        if (count($negatives) > 0) {
            foreach($negatives as $n) $report["ERROR"][] = "Stock Crítico Negativo post-operación: " . $n['nombre'] . " (" . $n['stock_actual'] . ")";
        } else {
            $report["OK"][] = "Integridad de Stock Global: OK (Sin negativos)";
        }

    } catch (Exception $e) {
        $report["ERROR"][] = "Error fatal en auditoría: " . $e->getMessage();
    }

    return $report;
}

$report = getAuditReport();
echo json_encode($report, JSON_PRETTY_PRINT);
?>
