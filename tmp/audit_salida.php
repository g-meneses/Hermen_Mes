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
            return ["ERROR" => "No se encontró ningún documento 'SAL-TEJ' en documentos_inventario."];
        }

        $id_doc = $doc['id_documento'];
        $num_doc = $doc['numero_documento'];
        $report["Documento Evaluado"] = $num_doc;

        // 2. VALIDAR DOCUMENTO (Cabecera)
        if (strpos($num_doc, 'SAL-TEJ') === 0) {
            $report["OK"][] = "Prefijo correcto (SAL-TEJ)";
        } else {
            $report["ERROR"][] = "Prefijo incorrecto: $num_doc";
        }

        if ($doc['tipo_documento'] === 'SALIDA') {
            $report["OK"][] = "Tipo de documento correcto (SALIDA)";
        } else {
            $report["ERROR"][] = "Tipo de documento incorrecto: " . $doc['tipo_documento'];
        }

        // 3. VALIDAR DETALLE
        $stmtLines = $db->prepare("SELECT * FROM documentos_inventario_detalle WHERE id_documento = ?");
        $stmtLines->execute([$id_doc]);
        $details = $stmtLines->fetchAll();

        if (count($details) > 0) {
            $report["OK"][] = "Detalle contiene " . count($details) . " ítems";
            foreach ($details as $d) {
                if ($d['cantidad'] <= 0) {
                    $report["ERROR"][] = "Cantidad no válida en detalle (ID: " . $d['id_detalle'] . ")";
                }
            }
        } else {
            // Nota: Algunos módulos guardan el detalle solo en movimientos_inventario.
            // Vamos a verificar movimientos_inventario también.
            $report["OBS"][] = "No se encontraron registros en documentos_inventario_detalle. Verificando movimientos_inventario...";
        }

        // 4. VALIDAR MOVIMIENTOS DE INVENTARIO (CRÍTICO)
        $stmtMovs = $db->prepare("SELECT m.*, i.nombre as item_nombre, i.stock_actual 
                                FROM movimientos_inventario m 
                                JOIN inventarios i ON m.id_inventario = i.id_inventario
                                WHERE m.documento_id = ? OR m.documento_numero = ?");
        $stmtMovs->execute([$id_doc, $num_doc]);
        $movs = $stmtMovs->fetchAll();

        if (count($movs) > 0) {
            $report["OK"][] = "Se encontraron " . count($movs) . " movimientos de inventario";
            foreach ($movs as $m) {
                if ($m['cantidad'] < 0) {
                    $report["OK"][] = "Movimiento OK: " . $m['item_nombre'] . " (" . $m['cantidad'] . ")";
                } else {
                    $report["ERROR"][] = "El movimiento para " . $m['item_nombre'] . " debería ser negativo (" . $m['cantidad'] . ")";
                }
                
                // 5. VALIDAR IMPACTO EN STOCK
                // En este sistema, stock_posterior en el movimiento debería coincidir con el stock_actual si fue el último mov.
                // O al menos confirmar que stock_posterior < stock_anterior
                if ($m['stock_posterior'] < $m['stock_anterior']) {
                    $report["OK"][] = "Impacto en stock OK para " . $m['item_nombre'] . " (Pos: " . $m['stock_posterior'] . " < Ant: " . $m['stock_anterior'] . ")";
                } else {
                    $report["ERROR"][] = "El stock no disminuyó para " . $m['item_nombre'];
                }
            }
        } else {
            $report["ERROR"][] = "No se encontraron movimientos de inventario asociados al documento $num_doc";
        }

        // 6. VALIDAR REGLAS DE NEGOCIO (WIP decoupling)
        // No deben existir movimientos_wip apuntando a este documento aún
        $stmtWip = $db->prepare("SELECT COUNT(*) as count FROM movimientos_wip WHERE documento_referencia = ? OR observaciones LIKE ?");
        $stmtWip->execute([$num_doc, "%$num_doc%"]);
        $wipCount = $stmtWip->fetch()['count'];

        if ($wipCount == 0) {
            $report["OK"][] = "Desacoplamiento WIP OK (No hay movimientos WIP asociados)";
        } else {
            $report["ERROR"][] = "Se encontraron $wipCount movimientos WIP vinculados prematuramente";
        }

        // lote_wip check
        $stmtLote = $db->prepare("SELECT COUNT(*) as count FROM lote_wip WHERE referencia = ? OR observaciones LIKE ?");
        $stmtLote->execute([$num_doc, "%$num_doc%"]);
        $loteCount = $stmtLote->fetch()['count'];

        if ($loteCount == 0) {
            $report["OK"][] = "Desacoplamiento Lote WIP OK (No hay lotes WIP creados)";
        } else {
            $report["ERROR"][] = "Se encontraron $loteCount lotes WIP vinculados";
        }

    } catch (Exception $e) {
        $report["ERROR"][] = "Excepción durante la auditoría: " . $e->getMessage();
    }

    return $report;
}

$report = getAuditReport();
echo json_encode($report, JSON_PRETTY_PRINT);
?>
