<?php
/**
 * API de Aprobaciones
 * Sistema MES Hermen Ltda.
 * Versión: 1.0
 */

ob_start();
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST');

ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once '../../config/database.php';

    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit();
    }

    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'pedientes';
        $userId = $_SESSION['user_id'] ?? 1; // Testing fallback

        if ($action === 'pendientes') {
            // Buscar solicitudes u órdenes que requieran aprobación DE ESTE USUARIO
            // Lógica simplificada: Si el usuario tiene rol 'APROBADOR' o está en nvl aprobación

            // Query Mockup V1 - Traer todas las pendientes
            $sql = "
                SELECT 'SOLICITUD' as tipo, s.id_solicitud as id, s.numero_solicitud as numero,
                       s.fecha_solicitud as fecha, s.monto_estimado as monto,
                       u.nombre_completo as solicitante, s.prioridad
                FROM solicitudes_compra s
                JOIN usuarios u ON s.id_usuario_solicitante = u.id_usuario
                WHERE s.estado = 'EN_APROBACION'
                -- AND (Logic to check if user is approver)
            ";

            $stmt = $db->query($sql);
            $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            echo json_encode(['success' => true, 'pendientes' => $pendientes]);

        } elseif ($action === 'historial') {
            // Historial de aprobaciones realizadas por el usuario
            $stmt = $db->prepare("
                SELECT a.*, 
                       CASE WHEN a.tipo_documento = 'SOLICITUD_COMPRA' THEN s.numero_solicitud 
                            ELSE oc.numero_orden END as documento_numero
                FROM aprobaciones a
                LEFT JOIN solicitudes_compra s ON a.id_documento = s.id_solicitud AND a.tipo_documento = 'SOLICITUD_COMPRA'
                LEFT JOIN ordenes_compra oc ON a.id_documento = oc.id_orden_compra AND a.tipo_documento = 'ORDEN_COMPRA'
                WHERE a.id_usuario_aprobador = ?
                ORDER BY a.fecha_aprobacion DESC
             ");
            $stmt->execute([$userId]);
            $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            echo json_encode(['success' => true, 'historial' => $historial]);
        }

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? 'aprobar';

        if ($action === 'procesar') {
            $id = $data['id_documento'];
            $tipo = $data['tipo_documento']; // SOLICITUD_COMPRA o ORDEN_COMPRA
            $decision = $data['decision']; // APROBADO, RECHAZADO, OBSERVADO
            $comentario = $data['comentarios'] ?? '';

            $db->beginTransaction();
            try {
                // Registrar en historial aprobaciones
                $stmt = $db->prepare("
                    INSERT INTO aprobaciones (
                        tipo_documento, id_documento, id_usuario_aprobador,
                        fecha_aprobacion, accion, comentarios
                    ) VALUES (?, ?, ?, NOW(), ?, ?)
                 ");
                $stmt->execute([
                    $tipo,
                    $id,
                    $_SESSION['user_id'] ?? 1,
                    $decision,
                    $comentario
                ]);

                // Actualizar estado del documento
                $nuevoEstado = $decision; // Mapeo directo por ahora
                if ($decision === 'APROBADO')
                    $nuevoEstado = 'APROBADA'; // Solicitud usa APROBADA fem.

                // TODO: Manejar niveles múltiples. Por ahora aprobación directa.

                if ($tipo === 'SOLICITUD_COMPRA') {
                    $stmtUpd = $db->prepare("UPDATE solicitudes_compra SET estado = ? WHERE id_solicitud = ?");
                    $stmtUpd->execute([$nuevoEstado, $id]);
                } elseif ($tipo === 'ORDEN_COMPRA') {
                    $stmtUpd = $db->prepare("UPDATE ordenes_compra SET estado = ? WHERE id_orden_compra = ?");
                    $stmtUpd->execute([$nuevoEstado === 'APROBADA' ? 'CONFIRMADA' : 'RECHAZADA', $id]);
                }

                $db->commit();
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Documento procesado']);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        }
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error aprobaciones.php: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
