<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'No autorizado'], 401);
}

try {
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $idProducto = (int) ($_GET['id_producto'] ?? 0);

            if ($idProducto > 0) {
                $stmt = $db->prepare("
                    SELECT
                        b.id_bom,
                        b.id_producto,
                        b.codigo_bom,
                        b.version_bom,
                        b.estado,
                        b.merma_pct,
                        b.observaciones,
                        b.fecha_vigencia_desde,
                        b.fecha_vigencia_hasta,
                        p.codigo_producto,
                        p.descripcion_completa
                    FROM bom_productos b
                    INNER JOIN productos_tejidos p ON p.id_producto = b.id_producto
                    WHERE b.id_producto = ?
                      AND b.estado = 'ACTIVO'
                    ORDER BY b.fecha_vigencia_desde DESC, b.id_bom DESC
                    LIMIT 1
                ");
                $stmt->execute([$idProducto]);
                $bom = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$bom) {
                    jsonResponse([
                        'success' => true,
                        'bom' => null,
                        'detalles' => []
                    ]);
                }

                $stmtDet = $db->prepare("
                    SELECT
                        d.id_bom_detalle,
                        d.id_inventario,
                        d.gramos_por_docena,
                        d.porcentaje_componente,
                        d.merma_pct,
                        d.es_principal,
                        d.orden_visual,
                        d.observaciones,
                        i.codigo,
                        i.nombre,
                        i.id_tipo_inventario,
                        i.stock_actual,
                        um.abreviatura AS unidad_abreviatura,
                        um.nombre AS unidad_nombre
                    FROM bom_productos_detalle d
                    INNER JOIN inventarios i ON i.id_inventario = d.id_inventario
                    LEFT JOIN unidades_medida um ON um.id_unidad = i.id_unidad
                    WHERE d.id_bom = ?
                    ORDER BY d.orden_visual, d.es_principal DESC, i.nombre
                ");
                $stmtDet->execute([$bom['id_bom']]);
                $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

                jsonResponse([
                    'success' => true,
                    'bom' => $bom,
                    'detalles' => $detalles
                ]);
            }

            $stmt = $db->query("
                SELECT
                    p.id_producto,
                    p.codigo_producto,
                    p.descripcion_completa,
                    p.id_linea,
                    l.codigo_linea,
                    l.nombre_linea,
                    p.talla,
                    b.id_bom,
                    b.codigo_bom,
                    b.estado,
                    b.fecha_vigencia_desde,
                    COUNT(d.id_bom_detalle) AS total_componentes,
                    COALESCE(SUM(d.gramos_por_docena), 0) AS gramos_totales_docena
                FROM productos_tejidos p
                LEFT JOIN lineas_producto l ON l.id_linea = p.id_linea
                LEFT JOIN bom_productos b
                    ON b.id_producto = p.id_producto
                   AND b.estado = 'ACTIVO'
                LEFT JOIN bom_productos_detalle d ON d.id_bom = b.id_bom
                WHERE p.activo = 1
                GROUP BY
                    p.id_producto,
                    p.codigo_producto,
                    p.descripcion_completa,
                    p.id_linea,
                    l.codigo_linea,
                    l.nombre_linea,
                    p.talla,
                    b.id_bom,
                    b.codigo_bom,
                    b.estado,
                    b.fecha_vigencia_desde
                ORDER BY p.codigo_producto
            ");

            jsonResponse([
                'success' => true,
                'productos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? 'save';

            if ($action !== 'save') {
                jsonResponse(['success' => false, 'message' => 'Acción no válida'], 400);
            }

            $idBom = (int) ($data['id_bom'] ?? 0);
            $idProducto = (int) ($data['id_producto'] ?? 0);
            $mermaPct = round((float) ($data['merma_pct'] ?? 0), 3);
            $observaciones = trim($data['observaciones'] ?? '');
            $detalles = $data['detalles'] ?? [];

            if ($idProducto <= 0) {
                jsonResponse(['success' => false, 'message' => 'Producto requerido'], 400);
            }

            if (empty($detalles) || !is_array($detalles)) {
                jsonResponse(['success' => false, 'message' => 'Debe registrar al menos un componente'], 400);
            }

            $db->beginTransaction();

            try {
                $stmtProducto = $db->prepare("SELECT id_producto, codigo_producto FROM productos_tejidos WHERE id_producto = ? AND activo = 1");
                $stmtProducto->execute([$idProducto]);
                $producto = $stmtProducto->fetch(PDO::FETCH_ASSOC);

                if (!$producto) {
                    throw new Exception('Producto no encontrado o inactivo');
                }

                if ($idBom > 0) {
                    $stmtBom = $db->prepare("SELECT id_bom FROM bom_productos WHERE id_bom = ? AND id_producto = ?");
                    $stmtBom->execute([$idBom, $idProducto]);
                    if (!$stmtBom->fetchColumn()) {
                        throw new Exception('BOM no encontrado para el producto indicado');
                    }

                    $stmtUpd = $db->prepare("
                        UPDATE bom_productos
                        SET merma_pct = ?, observaciones = ?, estado = 'ACTIVO'
                        WHERE id_bom = ?
                    ");
                    $stmtUpd->execute([$mermaPct, $observaciones ?: null, $idBom]);
                } else {
                    $stmtExistente = $db->prepare("
                        SELECT id_bom
                        FROM bom_productos
                        WHERE id_producto = ? AND estado = 'ACTIVO'
                        ORDER BY fecha_vigencia_desde DESC, id_bom DESC
                        LIMIT 1
                    ");
                    $stmtExistente->execute([$idProducto]);
                    $idBom = (int) ($stmtExistente->fetchColumn() ?: 0);

                    if ($idBom > 0) {
                        $stmtUpd = $db->prepare("
                            UPDATE bom_productos
                            SET merma_pct = ?, observaciones = ?, estado = 'ACTIVO'
                            WHERE id_bom = ?
                        ");
                        $stmtUpd->execute([$mermaPct, $observaciones ?: null, $idBom]);
                    } else {
                        $codigoBom = generarCodigoBom($db, $producto['codigo_producto']);
                        $stmtIns = $db->prepare("
                            INSERT INTO bom_productos (
                                id_producto, codigo_bom, version_bom, estado,
                                merma_pct, observaciones, creado_por
                            ) VALUES (?, ?, 1, 'ACTIVO', ?, ?, ?)
                        ");
                        $stmtIns->execute([
                            $idProducto,
                            $codigoBom,
                            $mermaPct,
                            $observaciones ?: null,
                            $_SESSION['user_id'] ?? null
                        ]);
                        $idBom = (int) $db->lastInsertId();
                    }
                }

                $db->prepare("DELETE FROM bom_productos_detalle WHERE id_bom = ?")->execute([$idBom]);

                $stmtInsDet = $db->prepare("
                    INSERT INTO bom_productos_detalle (
                        id_bom, id_inventario, gramos_por_docena, porcentaje_componente,
                        merma_pct, es_principal, orden_visual, observaciones
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $orden = 1;
                foreach ($detalles as $detalle) {
                    $idInventario = (int) ($detalle['id_inventario'] ?? 0);
                    $gramosPorDocena = round((float) ($detalle['gramos_por_docena'] ?? 0), 4);
                    $porcentaje = isset($detalle['porcentaje_componente']) && $detalle['porcentaje_componente'] !== ''
                        ? round((float) $detalle['porcentaje_componente'], 3)
                        : null;
                    $mermaDetalle = round((float) ($detalle['merma_pct'] ?? 0), 3);
                    $esPrincipal = !empty($detalle['es_principal']) ? 1 : 0;
                    $obsDetalle = trim($detalle['observaciones'] ?? '');

                    if ($idInventario <= 0 || $gramosPorDocena <= 0) {
                        throw new Exception('Cada componente requiere inventario y gramos por docena mayores a cero');
                    }

                    $stmtInsDet->execute([
                        $idBom,
                        $idInventario,
                        $gramosPorDocena,
                        $porcentaje,
                        $mermaDetalle,
                        $esPrincipal,
                        $orden++,
                        $obsDetalle ?: null
                    ]);
                }

                $db->commit();

                jsonResponse([
                    'success' => true,
                    'message' => 'BOM WIP guardado correctamente',
                    'id_bom' => $idBom
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Método no permitido'], 405);
    }
} catch (Exception $e) {
    error_log('Error en bom_wip.php: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function generarCodigoBom($db, $codigoProducto)
{
    $base = 'BOM-' . preg_replace('/[^A-Z0-9]/', '', strtoupper($codigoProducto));
    $stmt = $db->prepare("SELECT COUNT(*) FROM bom_productos WHERE codigo_bom LIKE ?");
    $stmt->execute([$base . '%']);
    $correlativo = (int) $stmt->fetchColumn() + 1;
    return $base . '-' . str_pad((string) $correlativo, 3, '0', STR_PAD_LEFT);
}
