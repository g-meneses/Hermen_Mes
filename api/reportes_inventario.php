<?php
/**
 * API de Reportes de Inventario
 * Sistema MES Hermen Ltda.
 * Versión: 1.0
 * 
 * Reportes disponibles:
 * - Total General: Resumen global del inventario
 * - Por Tipo: Totales agrupados por tipo de inventario
 * - Por Categoría: Totales agrupados por categoría
 * - Por Subcategoría: Totales agrupados por subcategoría
 * - Detallado: Lista completa de todos los items
 */

ob_start();
ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once '../config/database.php';

    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit();
    }

    $db = getDB();
    $action = $_GET['action'] ?? 'total';
    $formato = $_GET['formato'] ?? 'json'; // json, csv, pdf (futuro)

    // Filtros opcionales
    $tipoId = $_GET['tipo_id'] ?? null;
    $categoriaId = $_GET['categoria_id'] ?? null;
    $subcategoriaId = $_GET['subcategoria_id'] ?? null;
    $fechaDesde = $_GET['fecha_desde'] ?? null;
    $fechaHasta = $_GET['fecha_hasta'] ?? null;

    switch ($action) {
        // ========== REPORTE 1: TOTAL GENERAL ==========
        case 'total':
            $stmt = $db->query("
                SELECT 
                    COUNT(*) AS total_items,
                    SUM(stock_actual) AS total_unidades,
                    SUM(stock_actual * costo_unitario) AS valor_total,
                    SUM(CASE WHEN stock_actual <= 0 THEN 1 ELSE 0 END) AS items_sin_stock,
                    SUM(CASE WHEN stock_actual > 0 AND stock_actual <= stock_minimo THEN 1 ELSE 0 END) AS items_stock_critico,
                    SUM(CASE WHEN stock_actual > stock_minimo THEN 1 ELSE 0 END) AS items_stock_ok,
                    AVG(costo_unitario) AS costo_promedio_global,
                    MIN(costo_unitario) AS costo_minimo,
                    MAX(costo_unitario) AS costo_maximo
                FROM inventarios
                WHERE activo = 1
            ");
            $totales = $stmt->fetch(PDO::FETCH_ASSOC);

            // Totales por tipo
            $stmt = $db->query("
                SELECT 
                    ti.codigo AS tipo_codigo,
                    ti.nombre AS tipo_nombre,
                    COUNT(i.id_inventario) AS items,
                    SUM(i.stock_actual * i.costo_unitario) AS valor
                FROM tipos_inventario ti
                LEFT JOIN inventarios i ON ti.id_tipo_inventario = i.id_tipo_inventario AND i.activo = 1
                WHERE ti.activo = 1
                GROUP BY ti.id_tipo_inventario, ti.codigo, ti.nombre
                ORDER BY ti.orden
            ");
            $porTipo = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            echo json_encode([
                'success' => true,
                'reporte' => 'TOTAL_GENERAL',
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'totales' => $totales,
                'resumen_por_tipo' => $porTipo
            ]);
            break;

        // ========== REPORTE 2: POR TIPO DE INVENTARIO ==========
        case 'por_tipo':
            $sql = "
                SELECT 
                    ti.id_tipo_inventario,
                    ti.codigo AS tipo_codigo,
                    ti.nombre AS tipo_nombre,
                    ti.color,
                    COUNT(i.id_inventario) AS total_items,
                    SUM(i.stock_actual) AS total_unidades,
                    SUM(i.stock_actual * i.costo_unitario) AS valor_total,
                    SUM(CASE WHEN i.stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
                    SUM(CASE WHEN i.stock_actual > 0 AND i.stock_actual <= i.stock_minimo THEN 1 ELSE 0 END) AS stock_critico,
                    SUM(CASE WHEN i.stock_actual > i.stock_minimo THEN 1 ELSE 0 END) AS stock_ok
                FROM tipos_inventario ti
                LEFT JOIN inventarios i ON ti.id_tipo_inventario = i.id_tipo_inventario AND i.activo = 1
                WHERE ti.activo = 1
            ";

            $params = [];
            if ($tipoId) {
                $sql .= " AND ti.id_tipo_inventario = ?";
                $params[] = $tipoId;
            }

            $sql .= " GROUP BY ti.id_tipo_inventario, ti.codigo, ti.nombre, ti.color ORDER BY ti.orden";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular totales
            $totalItems = 0;
            $totalValor = 0;
            foreach ($datos as $row) {
                $totalItems += $row['total_items'];
                $totalValor += $row['valor_total'];
            }

            ob_clean();
            echo json_encode([
                'success' => true,
                'reporte' => 'POR_TIPO',
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'datos' => $datos,
                'totales' => [
                    'items' => $totalItems,
                    'valor' => $totalValor
                ]
            ]);
            break;

        // ========== REPORTE 3: POR CATEGORÍA ==========
        case 'por_categoria':
            $sql = "
                SELECT 
                    ti.id_tipo_inventario,
                    ti.codigo AS tipo_codigo,
                    ti.nombre AS tipo_nombre,
                    ti.color AS tipo_color,
                    ci.id_categoria,
                    ci.codigo AS categoria_codigo,
                    ci.nombre AS categoria_nombre,
                    COUNT(i.id_inventario) AS total_items,
                    SUM(i.stock_actual) AS total_unidades,
                    SUM(i.stock_actual * i.costo_unitario) AS valor_total,
                    SUM(CASE WHEN i.stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
                    SUM(CASE WHEN i.stock_actual > 0 AND i.stock_actual <= i.stock_minimo THEN 1 ELSE 0 END) AS stock_critico
                FROM tipos_inventario ti
                JOIN categorias_inventario ci ON ti.id_tipo_inventario = ci.id_tipo_inventario
                LEFT JOIN inventarios i ON ci.id_categoria = i.id_categoria AND i.activo = 1
                WHERE ti.activo = 1 AND ci.activo = 1
            ";

            $params = [];
            if ($tipoId) {
                $sql .= " AND ti.id_tipo_inventario = ?";
                $params[] = $tipoId;
            }
            if ($categoriaId) {
                $sql .= " AND ci.id_categoria = ?";
                $params[] = $categoriaId;
            }

            $sql .= " GROUP BY ti.id_tipo_inventario, ti.codigo, ti.nombre, ti.color, 
                               ci.id_categoria, ci.codigo, ci.nombre
                      ORDER BY ti.orden, ci.orden";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Agrupar por tipo para mejor visualización
            $agrupado = [];
            $totalItems = 0;
            $totalValor = 0;

            foreach ($datos as $row) {
                $tipoKey = $row['tipo_codigo'];
                if (!isset($agrupado[$tipoKey])) {
                    $agrupado[$tipoKey] = [
                        'tipo_codigo' => $row['tipo_codigo'],
                        'tipo_nombre' => $row['tipo_nombre'],
                        'tipo_color' => $row['tipo_color'],
                        'subtotal_items' => 0,
                        'subtotal_valor' => 0,
                        'categorias' => []
                    ];
                }
                $agrupado[$tipoKey]['categorias'][] = [
                    'categoria_codigo' => $row['categoria_codigo'],
                    'categoria_nombre' => $row['categoria_nombre'],
                    'total_items' => $row['total_items'],
                    'total_unidades' => $row['total_unidades'],
                    'valor_total' => $row['valor_total'],
                    'sin_stock' => $row['sin_stock'],
                    'stock_critico' => $row['stock_critico']
                ];
                $agrupado[$tipoKey]['subtotal_items'] += $row['total_items'];
                $agrupado[$tipoKey]['subtotal_valor'] += $row['valor_total'];
                $totalItems += $row['total_items'];
                $totalValor += $row['valor_total'];
            }

            ob_clean();
            echo json_encode([
                'success' => true,
                'reporte' => 'POR_CATEGORIA',
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'datos' => array_values($agrupado),
                'datos_plano' => $datos,
                'totales' => [
                    'items' => $totalItems,
                    'valor' => $totalValor
                ]
            ]);
            break;

        // ========== REPORTE 4: POR SUBCATEGORÍA ==========
        case 'por_subcategoria':
            $sql = "
                SELECT 
                    ti.codigo AS tipo_codigo,
                    ti.nombre AS tipo_nombre,
                    ci.codigo AS categoria_codigo,
                    ci.nombre AS categoria_nombre,
                    COALESCE(si.codigo, 'SIN-SUB') AS subcategoria_codigo,
                    COALESCE(si.nombre, 'Sin Subcategoría') AS subcategoria_nombre,
                    COUNT(i.id_inventario) AS total_items,
                    SUM(i.stock_actual) AS total_unidades,
                    SUM(i.stock_actual * i.costo_unitario) AS valor_total,
                    SUM(CASE WHEN i.stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
                    SUM(CASE WHEN i.stock_actual > 0 AND i.stock_actual <= i.stock_minimo THEN 1 ELSE 0 END) AS stock_critico
                FROM inventarios i
                JOIN tipos_inventario ti ON i.id_tipo_inventario = ti.id_tipo_inventario
                JOIN categorias_inventario ci ON i.id_categoria = ci.id_categoria
                LEFT JOIN subcategorias_inventario si ON i.id_subcategoria = si.id_subcategoria
                WHERE i.activo = 1
            ";

            $params = [];
            if ($tipoId) {
                $sql .= " AND ti.id_tipo_inventario = ?";
                $params[] = $tipoId;
            }
            if ($categoriaId) {
                $sql .= " AND ci.id_categoria = ?";
                $params[] = $categoriaId;
            }
            if ($subcategoriaId) {
                $sql .= " AND i.id_subcategoria = ?";
                $params[] = $subcategoriaId;
            }

            $sql .= " GROUP BY ti.codigo, ti.nombre, ci.codigo, ci.nombre, 
                               si.id_subcategoria, si.codigo, si.nombre
                      ORDER BY ti.codigo, ci.codigo, si.codigo";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalItems = 0;
            $totalValor = 0;
            foreach ($datos as $row) {
                $totalItems += $row['total_items'];
                $totalValor += $row['valor_total'];
            }

            ob_clean();
            echo json_encode([
                'success' => true,
                'reporte' => 'POR_SUBCATEGORIA',
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'datos' => $datos,
                'totales' => [
                    'items' => $totalItems,
                    'valor' => $totalValor
                ]
            ]);
            break;

        // ========== REPORTE 5: DETALLADO (TODOS LOS ITEMS) ==========
        case 'detallado':
            $sql = "
                SELECT 
                    ti.codigo AS tipo_codigo,
                    ti.nombre AS tipo_nombre,
                    ci.codigo AS categoria_codigo,
                    ci.nombre AS categoria_nombre,
                    COALESCE(si.codigo, '') AS subcategoria_codigo,
                    COALESCE(si.nombre, '') AS subcategoria_nombre,
                    i.codigo AS item_codigo,
                    i.nombre AS item_nombre,
                    i.descripcion,
                    um.abreviatura AS unidad,
                    i.stock_actual,
                    i.stock_minimo,
                    i.costo_unitario,
                    (i.stock_actual * i.costo_unitario) AS valor_total,
                    CASE 
                        WHEN i.stock_actual <= 0 THEN 'SIN_STOCK'
                        WHEN i.stock_actual <= i.stock_minimo THEN 'CRITICO'
                        ELSE 'OK'
                    END AS estado_stock,
                    i.proveedor_principal,
                    COALESCE(ua.nombre, '') AS ubicacion
                FROM inventarios i
                JOIN tipos_inventario ti ON i.id_tipo_inventario = ti.id_tipo_inventario
                JOIN categorias_inventario ci ON i.id_categoria = ci.id_categoria
                LEFT JOIN subcategorias_inventario si ON i.id_subcategoria = si.id_subcategoria
                JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                LEFT JOIN ubicaciones_almacen ua ON i.id_ubicacion = ua.id_ubicacion
                WHERE i.activo = 1
            ";

            $params = [];
            if ($tipoId) {
                $sql .= " AND ti.id_tipo_inventario = ?";
                $params[] = $tipoId;
            }
            if ($categoriaId) {
                $sql .= " AND ci.id_categoria = ?";
                $params[] = $categoriaId;
            }
            if ($subcategoriaId) {
                $sql .= " AND i.id_subcategoria = ?";
                $params[] = $subcategoriaId;
            }

            $sql .= " ORDER BY ti.codigo, ci.codigo, si.codigo, i.codigo";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular totales
            $totalItems = count($datos);
            $totalUnidades = 0;
            $totalValor = 0;
            foreach ($datos as $row) {
                $totalUnidades += $row['stock_actual'];
                $totalValor += $row['valor_total'];
            }

            ob_clean();
            echo json_encode([
                'success' => true,
                'reporte' => 'DETALLADO',
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'datos' => $datos,
                'totales' => [
                    'items' => $totalItems,
                    'unidades' => $totalUnidades,
                    'valor' => $totalValor
                ]
            ]);
            break;

        // ========== REPORTE 6: COMPRAS POR PROVEEDOR ==========
        case 'compras_proveedor':
            $sql = "
                SELECT 
                    COALESCE(p.codigo, 'SIN-PROV') AS proveedor_codigo,
                    COALESCE(p.razon_social, 'Sin Proveedor Asignado') AS proveedor_nombre,
                    COALESCE(p.nit, '') AS proveedor_nit,
                    COUNT(DISTINCT m.documento_numero) AS total_documentos,
                    COUNT(m.id_movimiento) AS total_lineas,
                    SUM(m.cantidad) AS total_cantidad,
                    SUM(m.costo_total) AS total_compras,
                    MIN(m.fecha_movimiento) AS primera_compra,
                    MAX(m.fecha_movimiento) AS ultima_compra
                FROM movimientos_inventario m
                JOIN documentos_inventario d ON m.documento_id = d.id_documento
                LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor
                WHERE m.tipo_movimiento COLLATE utf8mb4_unicode_ci LIKE 'ENTRADA_%'
                AND m.estado = 'ACTIVO'
            ";

            $params = [];
            if ($fechaDesde) {
                $sql .= " AND DATE(m.fecha_movimiento) >= ?";
                $params[] = $fechaDesde;
            }
            if ($fechaHasta) {
                $sql .= " AND DATE(m.fecha_movimiento) <= ?";
                $params[] = $fechaHasta;
            }

            $sql .= " GROUP BY p.id_proveedor, p.codigo, p.razon_social, p.nit
                      ORDER BY total_compras DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalCompras = 0;
            foreach ($datos as $row) {
                $totalCompras += $row['total_compras'];
            }

            ob_clean();
            echo json_encode([
                'success' => true,
                'reporte' => 'COMPRAS_POR_PROVEEDOR',
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'filtros' => [
                    'fecha_desde' => $fechaDesde,
                    'fecha_hasta' => $fechaHasta
                ],
                'datos' => $datos,
                'totales' => [
                    'proveedores' => count($datos),
                    'compras' => $totalCompras
                ]
            ]);
            break;

        // ========== REPORTE 7: MOVIMIENTOS DE INVENTARIO ==========
        case 'movimientos':
            $sql = "
                SELECT 
                    m.fecha_movimiento,
                    m.documento_tipo,
                    m.documento_numero,
                    m.tipo_movimiento,
                    ti.nombre AS tipo_inventario,
                    i.codigo AS item_codigo,
                    i.nombre AS item_nombre,
                    m.cantidad,
                    um.abreviatura AS unidad,
                    m.costo_unitario,
                    m.costo_total,
                    m.stock_anterior,
                    m.stock_posterior AS stock_nuevo,
                    COALESCE(p.razon_social, '') AS proveedor,
                    u.nombre_completo AS usuario,
                    m.estado
                FROM movimientos_inventario m
                JOIN inventarios i ON m.id_inventario = i.id_inventario
                JOIN tipos_inventario ti ON i.id_tipo_inventario = ti.id_tipo_inventario
                JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                LEFT JOIN documentos_inventario d ON m.documento_id = d.id_documento
                LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor
                LEFT JOIN usuarios u ON m.creado_por = u.id_usuario
                WHERE 1=1
            ";

            $params = [];
            if ($tipoId) {
                $sql .= " AND ti.id_tipo_inventario = ?";
                $params[] = $tipoId;
            }
            if ($fechaDesde) {
                $sql .= " AND DATE(m.fecha_movimiento) >= ?";
                $params[] = $fechaDesde;
            }
            if ($fechaHasta) {
                $sql .= " AND DATE(m.fecha_movimiento) <= ?";
                $params[] = $fechaHasta;
            }

            $sql .= " ORDER BY m.fecha_movimiento DESC LIMIT 500";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            echo json_encode([
                'success' => true,
                'reporte' => 'MOVIMIENTOS',
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'datos' => $datos,
                'total_registros' => count($datos)
            ]);
            break;

        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Tipo de reporte no válido']);
    }

} catch (PDOException $e) {
    error_log("Error en reportes_inventario.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error en reportes_inventario.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();
exit();