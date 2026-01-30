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
            $tipoId = $_GET['id_tipo'] ?? null;
            $catId = $_GET['id_categoria'] ?? null;
            $subcatId = $_GET['id_subcategoria'] ?? null;

            $sql = "
                SELECT 
                    p.id_inventario,
                    p.codigo,
                    p.nombre,
                    um.abreviatura as unidad,
                    c.nombre as categoria,
                    COALESCE(sc.nombre, '-') as subcategoria,
                    p.stock_actual,
                    p.costo_promedio as cpp,
                    (p.stock_actual * p.costo_promedio) as valor_total
                FROM inventarios p
                JOIN categorias_inventario c ON p.id_categoria = c.id_categoria
                LEFT JOIN subcategorias_inventario sc ON p.id_subcategoria = sc.id_subcategoria
                JOIN unidades_medida um ON p.id_unidad = um.id_unidad
                WHERE p.activo = 1
            ";

            if ($tipoId) {
                $sql .= " AND p.id_tipo_inventario = :tipoId";
            }
            if ($catId) {
                $sql .= " AND p.id_categoria = :catId";
            }
            if ($subcatId) {
                $sql .= " AND p.id_subcategoria = :subcatId";
            }

            $sql .= " ORDER BY c.nombre, p.nombre";

            $stmt = $db->prepare($sql);
            if ($tipoId)
                $stmt->bindValue(':tipoId', $tipoId);
            if ($catId)
                $stmt->bindValue(':catId', $catId);
            if ($subcatId)
                $stmt->bindValue(':subcatId', $subcatId);
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

        case 'tipos_categorias':
            // Reporte de tipos de inventario con sus categorías y subtotales
            $sql = "
                SELECT 
                    ti.id_tipo_inventario,
                    ti.codigo as tipo_codigo,
                    ti.nombre as tipo_nombre,
                    ti.icono as tipo_icono,
                    ti.color as tipo_color,
                    c.id_categoria,
                    c.nombre as categoria_nombre,
                    COUNT(i.id_inventario) as total_items,
                    COALESCE(SUM(i.stock_actual), 0) as total_stock,
                    COALESCE(SUM(i.stock_actual * i.costo_promedio), 0) as valor_total
                FROM tipos_inventario ti
                LEFT JOIN categorias_inventario c ON c.id_tipo_inventario = ti.id_tipo_inventario AND c.activo = 1
                LEFT JOIN inventarios i ON i.id_categoria = c.id_categoria AND i.activo = 1
                WHERE ti.activo = 1
                GROUP BY ti.id_tipo_inventario, ti.codigo, ti.nombre, ti.icono, ti.color, c.id_categoria, c.nombre
                ORDER BY ti.orden, c.nombre
            ";
            $stmt = $db->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Agrupar por tipo de inventario
            $tipos = [];
            $totalGeneral = ['items' => 0, 'valor' => 0];

            foreach ($rows as $row) {
                $tipoId = $row['id_tipo_inventario'];

                if (!isset($tipos[$tipoId])) {
                    $tipos[$tipoId] = [
                        'id' => $tipoId,
                        'codigo' => $row['tipo_codigo'],
                        'nombre' => $row['tipo_nombre'],
                        'icono' => $row['tipo_icono'],
                        'color' => $row['tipo_color'],
                        'categorias' => [],
                        'subtotal_items' => 0,
                        'subtotal_valor' => 0
                    ];
                }

                if ($row['id_categoria']) {
                    $tipos[$tipoId]['categorias'][] = [
                        'id' => $row['id_categoria'],
                        'nombre' => $row['categoria_nombre'],
                        'items' => intval($row['total_items']),
                        'valor' => floatval($row['valor_total'])
                    ];
                    $tipos[$tipoId]['subtotal_items'] += intval($row['total_items']);
                    $tipos[$tipoId]['subtotal_valor'] += floatval($row['valor_total']);
                }

                $totalGeneral['items'] += intval($row['total_items']);
                $totalGeneral['valor'] += floatval($row['valor_total']);
            }

            echo json_encode([
                'success' => true,
                'tipos' => array_values($tipos),
                'total_general' => $totalGeneral
            ]);
            break;

        case 'rotacion':
            // Reporte de Rotación de Inventario
            $desde = $_GET['desde'] ?? date('Y-m-01');
            $hasta = $_GET['hasta'] ?? date('Y-m-d');
            $tipoId = $_GET['id_tipo'] ?? null;
            $catId = $_GET['id_categoria'] ?? null;

            // Calcular días del período
            $fechaDesde = new DateTime($desde);
            $fechaHasta = new DateTime($hasta);
            $diasPeriodo = $fechaHasta->diff($fechaDesde)->days + 1;

            $sql = "
                SELECT 
                    i.id_inventario,
                    i.codigo,
                    i.nombre,
                    um.abreviatura as unidad,
                    c.nombre as categoria,
                    i.stock_actual,
                    i.costo_promedio,
                    
                    -- Stock inicial (al inicio del período)
                    COALESCE((
                        SELECT stock_posterior 
                        FROM movimientos_inventario 
                        WHERE id_inventario = i.id_inventario 
                        AND fecha_movimiento < :desde
                        ORDER BY fecha_movimiento DESC, id_movimiento DESC
                        LIMIT 1
                    ), 0) as stock_inicial,
                    
                    -- Total de entradas en el período
                    COALESCE((
                        SELECT SUM(cantidad)
                        FROM movimientos_inventario
                        WHERE id_inventario = i.id_inventario
                        AND DATE(fecha_movimiento) BETWEEN :desde2 AND :hasta
                        AND tipo_movimiento LIKE 'ENTRADA%'
                    ), 0) as total_entradas,
                    
                    -- Total de salidas en el período (consumo)
                    COALESCE((
                        SELECT SUM(cantidad)
                        FROM movimientos_inventario
                        WHERE id_inventario = i.id_inventario
                        AND DATE(fecha_movimiento) BETWEEN :desde3 AND :hasta2
                        AND tipo_movimiento LIKE 'SALIDA%'
                    ), 0) as total_salidas,
                    
                    -- Valor total del inventario actual
                    (i.stock_actual * i.costo_promedio) as valor_actual
                    
                FROM inventarios i
                JOIN categorias_inventario c ON i.id_categoria = c.id_categoria
                JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                WHERE i.activo = 1
            ";

            if ($tipoId) {
                $sql .= " AND i.id_tipo_inventario = :tipoId";
            }
            if ($catId) {
                $sql .= " AND i.id_categoria = :catId";
            }

            $sql .= " ORDER BY c.nombre, i.nombre";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':desde', $desde);
            $stmt->bindValue(':desde2', $desde);
            $stmt->bindValue(':desde3', $desde);
            $stmt->bindValue(':hasta', $hasta);
            $stmt->bindValue(':hasta2', $hasta);
            
            if ($tipoId) {
                $stmt->bindValue(':tipoId', $tipoId);
            }
            if ($catId) {
                $stmt->bindValue(':catId', $catId);
            }

            $stmt->execute();
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular métricas de rotación para cada producto
            $datos = [];
            foreach ($productos as $prod) {
                $stockInicial = floatval($prod['stock_inicial']);
                $stockFinal = floatval($prod['stock_actual']);
                $entradas = floatval($prod['total_entradas']);
                $salidas = floatval($prod['total_salidas']);
                
                // Inventario Promedio = (Stock Inicial + Stock Final) / 2
                $inventarioPromedio = ($stockInicial + $stockFinal) / 2;
                
                // Índice de Rotación = Consumo / Inventario Promedio
                $rotacion = $inventarioPromedio > 0 ? $salidas / $inventarioPromedio : 0;
                
                // Días de Stock = Días del Período / Rotación
                $diasStock = $rotacion > 0 ? $diasPeriodo / $rotacion : 999;
                
                // Clasificación de rotación
                $clasificacion = '';
                if ($rotacion >= 2) {
                    $clasificacion = 'ALTA';
                } elseif ($rotacion >= 0.5) {
                    $clasificacion = 'MEDIA';
                } elseif ($rotacion > 0) {
                    $clasificacion = 'BAJA';
                } else {
                    $clasificacion = 'SIN_MOVIMIENTO';
                }
                
                $datos[] = [
                    'id_inventario' => $prod['id_inventario'],
                    'codigo' => $prod['codigo'],
                    'nombre' => $prod['nombre'],
                    'unidad' => $prod['unidad'],
                    'categoria' => $prod['categoria'],
                    'stock_inicial' => $stockInicial,
                    'stock_final' => $stockFinal,
                    'inventario_promedio' => round($inventarioPromedio, 2),
                    'entradas' => $entradas,
                    'salidas' => $salidas,
                    'rotacion' => round($rotacion, 2),
                    'dias_stock' => round($diasStock, 0),
                    'clasificacion' => $clasificacion,
                    'valor_actual' => floatval($prod['valor_actual']),
                    'costo_promedio' => floatval($prod['costo_promedio'])
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => $datos,
                'periodo' => [
                    'desde' => $desde,
                    'hasta' => $hasta,
                    'dias' => $diasPeriodo
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