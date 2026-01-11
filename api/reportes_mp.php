<?php
/**
 * API para Reportes de Materia Prima
 * ERP Hermen Ltda.
 */
ob_start();
ob_clean();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit;
}

$db = getDB();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'stock_valorizado':
            $catId = $_GET['id_categoria'] ?? null;

            $sql = "
                SELECT 
                    p.id_inventario,
                    p.codigo,
                    p.nombre,
                    um.abreviatura as unidad,
                    c.nombre as categoria,
                    p.stock_actual,
                    p.costo_promedio as cpp,
                    (p.stock_actual * p.costo_promedio) as valor_total
                FROM inventarios p
                JOIN categorias_inventario c ON p.id_categoria = c.id_categoria
                JOIN unidades_medida um ON p.id_unidad = um.id_unidad
                WHERE p.activo = 1
            ";

            if ($catId) {
                $sql .= " AND p.id_categoria = :catId";
            }

            $sql .= " ORDER BY c.nombre, p.nombre";

            $stmt = $db->prepare($sql);
            if ($catId)
                $stmt->bindValue(':catId', $catId);
            $stmt->execute();
            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $datos]);
            break;

        case 'movimientos':
            $desde = $_GET['desde'] ?? date('Y-m-01');
            $hasta = $_GET['hasta'] ?? date('Y-m-d');
            $tipo = $_GET['tipo'] ?? null; // ENTRADA o SALIDA
            $idInv = $_GET['id_inventario'] ?? null;

            $sql = "
                SELECT 
                    m.id_movimiento,
                    m.fecha_movimiento as fecha,
                    m.tipo_movimiento,
                    m.codigo_movimiento,
                    m.documento_numero,
                    p.nombre as producto,
                    m.cantidad,
                    m.costo_unitario,
                    m.costo_total,
                    m.stock_posterior as saldo_cantidad
                FROM movimientos_inventario m
                JOIN inventarios p ON m.id_inventario = p.id_inventario
                WHERE DATE(m.fecha_movimiento) BETWEEN :desde AND :hasta
                AND m.id_tipo_inventario = 1 -- Solo Materia Prima
            ";

            if ($tipo) {
                $sql .= " AND m.tipo_movimiento LIKE :tipo";
            }
            if ($idInv) {
                $sql .= " AND m.id_inventario = :idInv";
            }

            $sql .= " ORDER BY m.fecha_movimiento DESC, m.id_movimiento DESC";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':desde', $desde);
            $stmt->bindValue(':hasta', $hasta);
            if ($tipo)
                $stmt->bindValue(':tipo', $tipo . '%');
            if ($idInv)
                $stmt->bindValue(':idInv', $idInv);
            $stmt->execute();
            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $datos]);
            break;

        case 'analisis':
            // Resumen por categoría
            $sqlCat = "
                SELECT 
                    c.nombre as categoria,
                    COUNT(p.id_inventario) as items,
                    SUM(p.stock_actual * p.costo_promedio) as valor_total
                FROM inventarios p
                JOIN categorias_inventario c ON p.id_categoria = c.id_categoria
                WHERE p.activo = 1 AND p.id_tipo_inventario = 1
                GROUP BY c.id_categoria
            ";
            $resCat = $db->query($sqlCat)->fetchAll(PDO::FETCH_ASSOC);

            // Top 10 productos por valor
            $sqlTop = "
                SELECT nombre, (stock_actual * costo_promedio) as valor
                FROM inventarios
                WHERE activo = 1 AND id_tipo_inventario = 1
                ORDER BY valor DESC
                LIMIT 10
            ";
            $resTop = $db->query($sqlTop)->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'categorias' => $resCat,
                'top_productos' => $resTop
            ]);
            break;

        case 'consolidado':
            // Reporte consolidado de todos los tipos de inventario
            $sql = "
                SELECT 
                    ti.id_tipo_inventario,
                    ti.codigo,
                    ti.nombre,
                    ti.icono,
                    ti.color,
                    COUNT(i.id_inventario) AS total_items,
                    SUM(CASE WHEN i.stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
                    SUM(CASE WHEN i.stock_actual > 0 AND i.stock_actual <= i.stock_minimo THEN 1 ELSE 0 END) AS stock_critico,
                    COALESCE(SUM(i.stock_actual * i.costo_promedio), 0) AS valor_total
                FROM tipos_inventario ti
                LEFT JOIN inventarios i ON ti.id_tipo_inventario = i.id_tipo_inventario AND i.activo = 1
                WHERE ti.activo = 1
                GROUP BY ti.id_tipo_inventario, ti.codigo, ti.nombre, ti.icono, ti.color
                ORDER BY ti.orden
            ";
            $stmt = $db->query($sql);
            $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular totales generales
            $totalItems = 0;
            $totalValor = 0;
            $totalAlertas = 0;
            foreach ($tipos as &$tipo) {
                $tipo['alertas'] = intval($tipo['sin_stock']) + intval($tipo['stock_critico']);
                $totalItems += intval($tipo['total_items']);
                $totalValor += floatval($tipo['valor_total']);
                $totalAlertas += $tipo['alertas'];
            }

            echo json_encode([
                'success' => true,
                'tipos' => $tipos,
                'totales' => [
                    'items' => $totalItems,
                    'valor' => $totalValor,
                    'alertas' => $totalAlertas
                ]
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>