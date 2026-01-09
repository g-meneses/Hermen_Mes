<?php
/**
 * API de Inventarios Centralizado
 * Sistema ERP Hermen Ltda.
 * Versión: 1.0
 */

ob_start();
ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
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
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Determinar qué tipo de consulta
            $action = $_GET['action'] ?? 'list';

            switch ($action) {
                case 'resumen':
                    // Resumen por tipo de inventario (para dashboard)
                    $stmt = $db->query("
                        SELECT 
                            ti.id_tipo_inventario,
                            ti.codigo,
                            ti.nombre,
                            ti.icono,
                            ti.color,
                            ti.orden,
                            COUNT(i.id_inventario) AS total_items,
                            SUM(CASE WHEN i.stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
                            SUM(CASE WHEN i.stock_actual > 0 AND i.stock_actual <= i.stock_minimo THEN 1 ELSE 0 END) AS stock_critico,
                            SUM(CASE WHEN i.stock_actual > i.stock_minimo THEN 1 ELSE 0 END) AS stock_ok,
                            COALESCE(SUM(i.stock_actual * i.costo_unitario), 0) AS valor_total
                        FROM tipos_inventario ti
                        LEFT JOIN inventarios i ON ti.id_tipo_inventario = i.id_tipo_inventario AND i.activo = 1
                        WHERE ti.activo = 1
                        GROUP BY ti.id_tipo_inventario, ti.codigo, ti.nombre, ti.icono, ti.color, ti.orden
                        ORDER BY ti.orden
                    ");
                    $resumen = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Calcular totales generales
                    $totalItems = 0;
                    $totalValor = 0;
                    $totalCritico = 0;
                    foreach ($resumen as $tipo) {
                        $totalItems += $tipo['total_items'];
                        $totalValor += $tipo['valor_total'];
                        $totalCritico += $tipo['stock_critico'] + $tipo['sin_stock'];
                    }

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'resumen' => $resumen,
                        'totales' => [
                            'items' => $totalItems,
                            'valor' => $totalValor,
                            'alertas' => $totalCritico
                        ]
                    ]);
                    break;

                case 'tipos':
                    // Lista de tipos de inventario
                    $stmt = $db->query("
                        SELECT id_tipo_inventario, codigo, nombre, icono, color 
                        FROM tipos_inventario 
                        WHERE activo = 1 
                        ORDER BY orden
                    ");
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'tipos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                    ]);
                    break;

                case 'categorias':
                    // Lista de categorías (opcionalmente filtrado por tipo)
                    $tipoId = $_GET['tipo_id'] ?? null;

                    $sql = "
                        SELECT c.id_categoria, c.codigo, c.nombre, c.id_tipo_inventario,
                               t.nombre AS tipo_nombre
                        FROM categorias_inventario c
                        JOIN tipos_inventario t ON c.id_tipo_inventario = t.id_tipo_inventario
                        WHERE c.activo = 1
                    ";

                    if ($tipoId) {
                        $sql .= " AND c.id_tipo_inventario = " . intval($tipoId);
                    }
                    $sql .= " ORDER BY t.orden, c.orden";

                    $stmt = $db->query($sql);
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'categorias' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                    ]);
                    break;

                case 'categorias_resumen':
                    // Lista de categorías con totales (items, valor, alertas)
                    $tipoId = $_GET['tipo_id'] ?? null;

                    $sql = "
                        SELECT 
                            c.id_categoria, 
                            c.codigo, 
                            c.nombre, 
                            c.id_tipo_inventario,
                            COUNT(i.id_inventario) AS total_items,
                            COALESCE(SUM(i.stock_actual * i.costo_unitario), 0) AS valor_total,
                            SUM(CASE WHEN i.stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
                            SUM(CASE WHEN i.stock_actual > 0 AND i.stock_actual <= i.stock_minimo THEN 1 ELSE 0 END) AS stock_critico
                        FROM categorias_inventario c
                        LEFT JOIN inventarios i ON c.id_categoria = i.id_categoria AND i.activo = 1
                        WHERE c.activo = 1
                    ";

                    if ($tipoId) {
                        $sql .= " AND c.id_tipo_inventario = " . intval($tipoId);
                    }

                    $sql .= " GROUP BY c.id_categoria, c.codigo, c.nombre, c.id_tipo_inventario
                              ORDER BY c.orden, c.nombre";

                    $stmt = $db->query($sql);
                    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Calcular alertas totales
                    foreach ($categorias as &$cat) {
                        $cat['alertas'] = intval($cat['sin_stock']) + intval($cat['stock_critico']);
                    }

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'categorias' => $categorias
                    ]);
                    break;

                case 'subcategorias':
                    // Lista de subcategorías (opcionalmente filtrado por categoría)
                    $categoriaId = $_GET['categoria_id'] ?? null;

                    $sql = "
                        SELECT s.id_subcategoria, s.codigo, s.nombre, s.id_categoria, s.orden,
                               c.nombre AS categoria_nombre, c.codigo AS categoria_codigo,
                               t.id_tipo_inventario, t.nombre AS tipo_nombre
                        FROM subcategorias_inventario s
                        JOIN categorias_inventario c ON s.id_categoria = c.id_categoria
                        JOIN tipos_inventario t ON c.id_tipo_inventario = t.id_tipo_inventario
                        WHERE s.activo = 1
                    ";

                    if ($categoriaId) {
                        $sql .= " AND s.id_categoria = " . intval($categoriaId);
                    }
                    $sql .= " ORDER BY c.orden, s.orden, s.nombre";

                    $stmt = $db->query($sql);
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'subcategorias' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                    ]);
                    break;

                case 'subcategorias_resumen':
                    // Subcategorías con totales (items, valor, alertas)
                    $categoriaId = $_GET['categoria_id'] ?? null;

                    $sql = "
                        SELECT 
                            s.id_subcategoria, 
                            s.codigo, 
                            s.nombre, 
                            s.id_categoria,
                            c.nombre AS categoria_nombre,
                            COUNT(i.id_inventario) AS total_items,
                            COALESCE(SUM(i.stock_actual * i.costo_unitario), 0) AS valor_total,
                            SUM(CASE WHEN i.stock_actual <= 0 THEN 1 ELSE 0 END) AS sin_stock,
                            SUM(CASE WHEN i.stock_actual > 0 AND i.stock_actual <= i.stock_minimo THEN 1 ELSE 0 END) AS stock_critico
                        FROM subcategorias_inventario s
                        JOIN categorias_inventario c ON s.id_categoria = c.id_categoria
                        LEFT JOIN inventarios i ON s.id_subcategoria = i.id_subcategoria AND i.activo = 1
                        WHERE s.activo = 1
                    ";

                    if ($categoriaId) {
                        $sql .= " AND s.id_categoria = " . intval($categoriaId);
                    }

                    $sql .= " GROUP BY s.id_subcategoria, s.codigo, s.nombre, s.id_categoria, c.nombre
                              ORDER BY s.orden, s.nombre";

                    $stmt = $db->query($sql);
                    $subcategorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Calcular alertas totales
                    foreach ($subcategorias as &$sub) {
                        $sub['alertas'] = intval($sub['sin_stock']) + intval($sub['stock_critico']);
                    }

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'subcategorias' => $subcategorias
                    ]);
                    break;

                case 'unidades':
                    // Lista de unidades de medida
                    $stmt = $db->query("
                        SELECT id_unidad, codigo, nombre, abreviatura, tipo 
                        FROM unidades_medida 
                        WHERE activo = 1 
                        ORDER BY tipo, nombre
                    ");
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'unidades' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                    ]);
                    break;

                case 'ubicaciones':
                    // Lista de ubicaciones
                    $stmt = $db->query("
                        SELECT id_ubicacion, codigo, nombre, tipo 
                        FROM ubicaciones_almacen 
                        WHERE activo = 1 
                        ORDER BY tipo, nombre
                    ");
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'ubicaciones' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                    ]);
                    break;

                case 'lineas':
                    // Líneas de producción
                    $stmt = $db->query("
                        SELECT id_linea_produccion, codigo, nombre 
                        FROM lineas_produccion_erp 
                        WHERE activo = 1 
                        ORDER BY id_linea_produccion
                    ");
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'lineas' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                    ]);
                    break;

                case 'kardex':
                    // Kardex completo de un producto (datos + movimientos)
                    $inventarioId = $_GET['id'] ?? null;
                    if (!$inventarioId) {
                        ob_clean();
                        echo json_encode(['success' => false, 'message' => 'ID de inventario requerido']);
                        exit();
                    }

                    // Obtener datos del producto
                    $stmt = $db->prepare("
                        SELECT 
                            i.id_inventario, i.codigo, i.nombre, i.descripcion,
                            i.stock_actual, i.stock_minimo, i.costo_unitario, i.costo_promedio,
                            um.abreviatura AS unidad,
                            ti.nombre AS tipo_nombre,
                            ci.nombre AS categoria_nombre
                        FROM inventarios i
                        JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                        JOIN tipos_inventario ti ON i.id_tipo_inventario = ti.id_tipo_inventario
                        JOIN categorias_inventario ci ON i.id_categoria = ci.id_categoria
                        WHERE i.id_inventario = ?
                    ");
                    $stmt->execute([$inventarioId]);
                    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$producto) {
                        ob_clean();
                        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
                        exit();
                    }

                    // Obtener movimientos
                    $stmt = $db->prepare("
                        SELECT 
                            m.id_movimiento,
                            m.fecha_movimiento,
                            m.tipo_movimiento,
                            m.cantidad,
                            m.stock_anterior,
                            m.stock_nuevo,
                            m.costo_unitario,
                            m.costo_total,
                            m.costo_promedio_resultado,
                            m.documento_tipo,
                            m.documento_numero,
                            m.observaciones,
                            m.estado,
                            u.nombre_completo AS usuario
                        FROM movimientos_inventario_erp m
                        LEFT JOIN usuarios u ON m.id_usuario = u.id_usuario
                        WHERE m.id_inventario = ?
                        ORDER BY m.fecha_movimiento DESC
                        LIMIT 100
                    ");
                    $stmt->execute([$inventarioId]);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'producto' => $producto,
                        'movimientos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                    ]);
                    break;

                case 'historial':
                case 'documentos':
                    // Lista de documentos agrupados (para histórico)
                    $tipoMov = $_GET['tipo_movimiento'] ?? $_GET['tipo_mov'] ?? null;
                    $fechaDesde = $_GET['fecha_desde'] ?? null;
                    $fechaHasta = $_GET['fecha_hasta'] ?? null;
                    $buscar = $_GET['buscar'] ?? null;
                    $tipoInventarioId = $_GET['tipo_id'] ?? null;

                    $sql = "
                        SELECT 
                            m.documento_numero,
                            m.documento_tipo,
                            m.tipo_movimiento,
                            MIN(m.fecha_movimiento) AS fecha,
                            COUNT(*) AS total_lineas,
                            SUM(m.cantidad) AS total_cantidad,
                            SUM(m.costo_total) AS total_documento,
                            MAX(m.observaciones) AS observaciones,
                            u.nombre_completo AS usuario,
                            CASE 
                                WHEN m.tipo_movimiento LIKE 'ENTRADA%' THEN 'ENTRADA'
                                WHEN m.tipo_movimiento LIKE 'DEVOLUCION%' THEN 'DEVOLUCION'
                                ELSE 'SALIDA'
                            END AS categoria,
                            MAX(m.estado) AS estado
                        FROM movimientos_inventario_erp m
                        LEFT JOIN usuarios u ON m.id_usuario = u.id_usuario
                    ";

                    // Si hay filtro por tipo de inventario, hacer join
                    if ($tipoInventarioId) {
                        $sql .= " JOIN inventarios i ON m.id_inventario = i.id_inventario ";
                    }

                    $sql .= " WHERE 1=1 ";
                    $params = [];

                    if ($tipoMov) {
                        if ($tipoMov === 'ENTRADA') {
                            $sql .= " AND m.tipo_movimiento LIKE 'ENTRADA%'";
                        } elseif ($tipoMov === 'SALIDA') {
                            $sql .= " AND m.tipo_movimiento LIKE 'SALIDA%'";
                        } elseif ($tipoMov === 'DEVOLUCION') {
                            $sql .= " AND m.tipo_movimiento LIKE 'DEVOLUCION%'";
                        } else {
                            $sql .= " AND m.tipo_movimiento = ?";
                            $params[] = $tipoMov;
                        }
                    }

                    if ($tipoInventarioId) {
                        $sql .= " AND i.id_tipo_inventario = ?";
                        $params[] = $tipoInventarioId;
                    }

                    if ($fechaDesde) {
                        $sql .= " AND DATE(m.fecha_movimiento) >= ?";
                        $params[] = $fechaDesde;
                    }

                    if ($fechaHasta) {
                        $sql .= " AND DATE(m.fecha_movimiento) <= ?";
                        $params[] = $fechaHasta;
                    }

                    if ($buscar) {
                        $sql .= " AND (m.documento_numero COLLATE utf8mb4_unicode_ci LIKE ? OR m.observaciones COLLATE utf8mb4_unicode_ci LIKE ?)";
                        $params[] = "%$buscar%";
                        $params[] = "%$buscar%";
                    }

                    $sql .= " GROUP BY m.documento_numero, m.documento_tipo, m.tipo_movimiento, u.nombre_completo
                              ORDER BY fecha DESC
                              LIMIT 100";

                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'documentos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                    ]);
                    break;

                case 'documento_detalle':
                    // Detalle de un documento específico
                    $docNumero = $_GET['documento'] ?? null;

                    if (!$docNumero) {
                        ob_clean();
                        echo json_encode(['success' => false, 'message' => 'Número de documento requerido']);
                        exit();
                    }

                    // Obtener cabecera (datos generales del documento)
                    $stmt = $db->prepare("
                        SELECT 
                            documento_numero,
                            documento_tipo,
                            tipo_movimiento,
                            MIN(fecha_movimiento) AS fecha,
                            MAX(observaciones) AS observaciones,
                            MAX(m.estado) AS estado,
                            u.nombre_completo AS usuario,
                            SUM(costo_total) AS total_documento
                        FROM movimientos_inventario_erp m
                        LEFT JOIN usuarios u ON m.id_usuario = u.id_usuario
                        WHERE documento_numero = ?
                        GROUP BY documento_numero, documento_tipo, tipo_movimiento, u.nombre_completo
                    ");
                    $stmt->execute([$docNumero]);
                    $cabecera = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$cabecera) {
                        ob_clean();
                        echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
                        exit();
                    }

                    // Obtener detalle (líneas del documento)
                    $stmt = $db->prepare("
                        SELECT 
                            m.id_movimiento,
                            m.id_inventario,
                            i.codigo AS producto_codigo,
                            i.nombre AS producto_nombre,
                            um.abreviatura AS unidad,
                            m.cantidad,
                            m.costo_unitario,
                            m.costo_total,
                            m.stock_anterior,
                            m.stock_nuevo,
                            m.costo_promedio_resultado AS cpp_resultante,
                            m.estado
                        FROM movimientos_inventario_erp m
                        JOIN inventarios i ON m.id_inventario = i.id_inventario
                        JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                        WHERE m.documento_numero = ?
                        ORDER BY m.id_movimiento
                    ");
                    $stmt->execute([$docNumero]);
                    $lineas = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'cabecera' => $cabecera,
                        'lineas' => $lineas
                    ]);
                    break;

                case 'detalle':
                    // Detalle de un producto específico
                    $inventarioId = $_GET['id'] ?? null;
                    if (!$inventarioId) {
                        ob_clean();
                        echo json_encode(['success' => false, 'message' => 'ID de inventario requerido']);
                        exit();
                    }

                    $stmt = $db->prepare("
                        SELECT 
                            i.*,
                            ti.codigo AS tipo_codigo,
                            ti.nombre AS tipo_nombre,
                            ti.color AS tipo_color,
                            ci.codigo AS categoria_codigo,
                            ci.nombre AS categoria_nombre,
                            um.codigo AS unidad_codigo,
                            um.abreviatura AS unidad,
                            ua.nombre AS ubicacion_nombre,
                            lp.nombre AS linea_nombre
                        FROM inventarios i
                        JOIN tipos_inventario ti ON i.id_tipo_inventario = ti.id_tipo_inventario
                        JOIN categorias_inventario ci ON i.id_categoria = ci.id_categoria
                        JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                        LEFT JOIN ubicaciones_almacen ua ON i.id_ubicacion = ua.id_ubicacion
                        LEFT JOIN lineas_produccion_erp lp ON i.id_linea_produccion = lp.id_linea_produccion
                        WHERE i.id_inventario = ?
                    ");
                    $stmt->execute([$inventarioId]);
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'item' => $item
                    ]);
                    break;

                default:
                    // Listar inventarios con filtros
                    $tipoId = $_GET['tipo_id'] ?? null;
                    $categoriaId = $_GET['categoria_id'] ?? null;
                    $subcategoriaId = $_GET['subcategoria_id'] ?? null;
                    $buscar = $_GET['buscar'] ?? null;
                    $estadoStock = $_GET['estado_stock'] ?? null;

                    $sql = "
                        SELECT 
                            i.id_inventario,
                            i.codigo,
                            i.nombre,
                            i.descripcion,
                            ti.id_tipo_inventario,
                            ti.codigo AS tipo_codigo,
                            ti.nombre AS tipo_nombre,
                            ti.color AS tipo_color,
                            ci.id_categoria,
                            ci.codigo AS categoria_codigo,
                            ci.nombre AS categoria_nombre,
                            i.id_subcategoria,
                            um.abreviatura AS unidad,
                            i.stock_actual,
                            i.stock_minimo,
                            i.costo_unitario,
                            i.costo_promedio,
                            (i.stock_actual * COALESCE(i.costo_promedio, i.costo_unitario, 0)) AS valor_total,
                            ua.nombre AS ubicacion,
                            i.proveedor_principal,
                            CASE 
                                WHEN i.stock_actual <= 0 THEN 'SIN_STOCK'
                                WHEN i.stock_actual <= i.stock_minimo THEN 'CRITICO'
                                WHEN i.stock_actual <= (i.stock_minimo * 1.5) THEN 'BAJO'
                                ELSE 'OK'
                            END AS estado_stock
                        FROM inventarios i
                        JOIN tipos_inventario ti ON i.id_tipo_inventario = ti.id_tipo_inventario
                        JOIN categorias_inventario ci ON i.id_categoria = ci.id_categoria
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

                    if ($buscar) {
                        $sql .= " AND (i.codigo COLLATE utf8mb4_unicode_ci LIKE ? OR i.nombre COLLATE utf8mb4_unicode_ci LIKE ?)";
                        $params[] = "%$buscar%";
                        $params[] = "%$buscar%";
                    }

                    $sql .= " ORDER BY ti.orden, ci.orden, i.nombre";

                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $inventarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Filtrar por estado de stock si se especificó
                    if ($estadoStock) {
                        $inventarios = array_filter($inventarios, function ($item) use ($estadoStock) {
                            return $item['estado_stock'] === $estadoStock;
                        });
                        $inventarios = array_values($inventarios);
                    }

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'inventarios' => $inventarios,
                        'total' => count($inventarios)
                    ]);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            $action = $data['action'] ?? 'save';

            // ========== GUARDAR TIPO DE INVENTARIO ==========
            if ($action === 'guardar_tipo') {
                $idTipo = $data['id_tipo_inventario'] ?? null;
                $codigo = strtoupper(trim($data['codigo'] ?? ''));
                $nombre = trim($data['nombre'] ?? '');
                $icono = trim($data['icono'] ?? 'fa-box');
                $color = trim($data['color'] ?? '#007bff');

                if (empty($codigo) || empty($nombre)) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Código y Nombre son requeridos']);
                    exit();
                }

                try {
                    if ($idTipo) {
                        // Actualizar
                        $stmt = $db->prepare("
                            UPDATE tipos_inventario 
                            SET codigo = ?, nombre = ?, icono = ?, color = ?
                            WHERE id_tipo_inventario = ?
                        ");
                        $stmt->execute([$codigo, $nombre, $icono, $color, $idTipo]);
                        $mensaje = 'Tipo de inventario actualizado';
                    } else {
                        // Obtener el siguiente orden
                        $stmt = $db->query("SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente FROM tipos_inventario");
                        $orden = $stmt->fetch()['siguiente'];

                        // Crear nuevo
                        $stmt = $db->prepare("
                            INSERT INTO tipos_inventario (codigo, nombre, icono, color, orden, activo) 
                            VALUES (?, ?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([$codigo, $nombre, $icono, $color, $orden]);
                        $mensaje = 'Tipo de inventario creado';
                    }

                    ob_clean();
                    echo json_encode(['success' => true, 'message' => $mensaje]);

                } catch (PDOException $e) {
                    ob_clean();
                    if (strpos($e->getMessage(), 'Duplicate') !== false) {
                        echo json_encode(['success' => false, 'message' => 'El código ya existe']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
                    }
                }
                exit();
            }

            // ========== GUARDAR CATEGORÍA ==========
            if ($action === 'guardar_categoria') {
                $idCategoria = $data['id_categoria'] ?? null;
                $idTipoInventario = intval($data['id_tipo_inventario'] ?? 0);
                $codigo = strtoupper(trim($data['codigo'] ?? ''));
                $nombre = trim($data['nombre'] ?? '');
                $orden = intval($data['orden'] ?? 1);

                if (!$idTipoInventario || empty($codigo) || empty($nombre)) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Tipo, Código y Nombre son requeridos']);
                    exit();
                }

                try {
                    if ($idCategoria) {
                        // Actualizar
                        $stmt = $db->prepare("
                            UPDATE categorias_inventario 
                            SET id_tipo_inventario = ?, codigo = ?, nombre = ?, orden = ?
                            WHERE id_categoria = ?
                        ");
                        $stmt->execute([$idTipoInventario, $codigo, $nombre, $orden, $idCategoria]);
                        $mensaje = 'Categoría actualizada';
                    } else {
                        // Crear nueva
                        $stmt = $db->prepare("
                            INSERT INTO categorias_inventario (id_tipo_inventario, codigo, nombre, orden, activo) 
                            VALUES (?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([$idTipoInventario, $codigo, $nombre, $orden]);
                        $mensaje = 'Categoría creada';
                    }

                    ob_clean();
                    echo json_encode(['success' => true, 'message' => $mensaje]);

                } catch (PDOException $e) {
                    ob_clean();
                    if (strpos($e->getMessage(), 'Duplicate') !== false) {
                        echo json_encode(['success' => false, 'message' => 'El código ya existe']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
                    }
                }
                exit();
            }

            // ========== GUARDAR SUBCATEGORÍA ==========
            if ($action === 'guardar_subcategoria') {
                $idSubcategoria = $data['id_subcategoria'] ?? null;
                $idCategoria = intval($data['id_categoria'] ?? 0);
                $codigo = strtoupper(trim($data['codigo'] ?? ''));
                $nombre = trim($data['nombre'] ?? '');
                $orden = intval($data['orden'] ?? 1);

                if (!$idCategoria || empty($codigo) || empty($nombre)) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Categoría, Código y Nombre son requeridos']);
                    exit();
                }

                try {
                    if ($idSubcategoria) {
                        // Actualizar
                        $stmt = $db->prepare("
                            UPDATE subcategorias_inventario 
                            SET id_categoria = ?, codigo = ?, nombre = ?, orden = ?
                            WHERE id_subcategoria = ?
                        ");
                        $stmt->execute([$idCategoria, $codigo, $nombre, $orden, $idSubcategoria]);
                        $mensaje = 'Subcategoría actualizada';
                    } else {
                        // Crear nueva
                        $stmt = $db->prepare("
                            INSERT INTO subcategorias_inventario (id_categoria, codigo, nombre, orden, activo) 
                            VALUES (?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([$idCategoria, $codigo, $nombre, $orden]);
                        $mensaje = 'Subcategoría creada';
                    }

                    ob_clean();
                    echo json_encode(['success' => true, 'message' => $mensaje]);

                } catch (PDOException $e) {
                    ob_clean();
                    if (strpos($e->getMessage(), 'Duplicate') !== false) {
                        echo json_encode(['success' => false, 'message' => 'El código ya existe']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
                    }
                }
                exit();
            }

            if ($action === 'movimiento') {
                // Registrar movimiento de inventario con COSTO PROMEDIO PONDERADO
                $idInventario = $data['id_inventario'] ?? null;
                $tipoMovimiento = $data['tipo_movimiento'] ?? null;
                $cantidad = floatval($data['cantidad'] ?? 0);
                $costoUnitarioEntrada = floatval($data['costo_unitario'] ?? 0);
                $documentoTipo = $data['documento_tipo'] ?? null;
                $documentoNumero = $data['documento_numero'] ?? null;
                $observaciones = $data['observaciones'] ?? null;

                if (!$idInventario || !$tipoMovimiento || $cantidad <= 0) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Datos incompletos para el movimiento']);
                    exit();
                }

                // Obtener stock actual y costo promedio
                $stmt = $db->prepare("
                    SELECT stock_actual, costo_unitario, costo_promedio, 
                           (stock_actual * costo_promedio) AS valor_inventario
                    FROM inventarios 
                    WHERE id_inventario = ?
                ");
                $stmt->execute([$idInventario]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$item) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
                    exit();
                }

                $stockAnterior = floatval($item['stock_actual']);
                $costoPromedioAnterior = floatval($item['costo_promedio']) ?: floatval($item['costo_unitario']);
                $valorInventarioAnterior = floatval($item['valor_inventario']) ?: ($stockAnterior * $costoPromedioAnterior);

                $esEntrada = strpos($tipoMovimiento, 'ENTRADA') !== false || $tipoMovimiento === 'TRANSFERENCIA_ENTRADA';

                // Variables para el movimiento
                $stockNuevo = 0;
                $costoUnitarioMovimiento = 0;
                $costoPromedioNuevo = $costoPromedioAnterior;
                $valorTotalMovimiento = 0;
                $valorInventarioNuevo = 0;

                if ($esEntrada) {
                    // ========== ENTRADA: Calcular Costo Promedio Ponderado ==========
                    $stockNuevo = $stockAnterior + $cantidad;

                    // Si no se proporciona costo, usar el costo promedio actual
                    if ($costoUnitarioEntrada <= 0) {
                        $costoUnitarioEntrada = $costoPromedioAnterior;
                    }

                    $costoUnitarioMovimiento = $costoUnitarioEntrada;
                    $valorTotalMovimiento = $cantidad * $costoUnitarioEntrada;

                    // Calcular nuevo Costo Promedio Ponderado
                    // CPP = (Valor Inventario Anterior + Valor Entrada) / (Stock Anterior + Cantidad Entrada)
                    if ($stockNuevo > 0) {
                        $costoPromedioNuevo = ($valorInventarioAnterior + $valorTotalMovimiento) / $stockNuevo;
                    } else {
                        $costoPromedioNuevo = $costoUnitarioEntrada;
                    }

                    $valorInventarioNuevo = $stockNuevo * $costoPromedioNuevo;

                } else {
                    // ========== SALIDA: Usar Costo Promedio Actual ==========
                    if ($stockAnterior < $cantidad) {
                        ob_clean();
                        echo json_encode(['success' => false, 'message' => 'Stock insuficiente. Disponible: ' . number_format($stockAnterior, 2)]);
                        exit();
                    }

                    $stockNuevo = $stockAnterior - $cantidad;

                    // Las salidas SIEMPRE se valoran al Costo Promedio actual
                    $costoUnitarioMovimiento = $costoPromedioAnterior;
                    $valorTotalMovimiento = $cantidad * $costoPromedioAnterior;

                    // El costo promedio NO cambia en las salidas
                    $costoPromedioNuevo = $costoPromedioAnterior;
                    $valorInventarioNuevo = $stockNuevo * $costoPromedioNuevo;
                }

                $db->beginTransaction();

                try {
                    // Insertar movimiento con datos completos para Kardex Físico-Valorado
                    $stmt = $db->prepare("
                        INSERT INTO movimientos_inventario_erp (
                            id_inventario, tipo_movimiento, cantidad,
                            stock_anterior, stock_nuevo, 
                            costo_unitario, costo_total, costo_promedio_resultado,
                            documento_tipo, documento_numero,
                            id_usuario, observaciones
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $idInventario,
                        $tipoMovimiento,
                        $cantidad,
                        $stockAnterior,
                        $stockNuevo,
                        $costoUnitarioMovimiento,
                        $valorTotalMovimiento,
                        $costoPromedioNuevo,  // CPP después del movimiento
                        $documentoTipo,
                        $documentoNumero,
                        $_SESSION['user_id'],
                        $observaciones
                    ]);

                    // Actualizar inventario con nuevo stock y costo promedio
                    $stmt = $db->prepare("
                        UPDATE inventarios 
                        SET stock_actual = ?,
                            costo_promedio = ?,
                            costo_unitario = ?
                        WHERE id_inventario = ?
                    ");
                    $stmt->execute([
                        $stockNuevo,
                        $costoPromedioNuevo,
                        $costoPromedioNuevo, // Mantener sincronizado
                        $idInventario
                    ]);

                    $db->commit();

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Movimiento registrado exitosamente',
                        'datos' => [
                            'stock_anterior' => $stockAnterior,
                            'stock_nuevo' => $stockNuevo,
                            'costo_unitario' => $costoUnitarioMovimiento,
                            'valor_movimiento' => $valorTotalMovimiento,
                            'costo_promedio_anterior' => $costoPromedioAnterior,
                            'costo_promedio_nuevo' => $costoPromedioNuevo,
                            'valor_inventario' => $valorInventarioNuevo
                        ]
                    ]);
                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }

            } elseif ($action === 'multiproducto') {
                // ========== MOVIMIENTO MULTIPRODUCTO v2.0 ==========
                // Con descuento de IVA 13% en el costo para compras con factura

                $tipoMovimiento = $data['tipo_movimiento'] ?? null;
                $documentoTipo = $data['documento_tipo'] ?? 'NOTA';
                $documentoNumero = $data['documento_numero'] ?? null;
                $proveedor = $data['proveedor'] ?? '';
                $fecha = $data['fecha'] ?? date('Y-m-d');
                $observaciones = $data['observaciones'] ?? '';
                $conFactura = $data['con_factura'] ?? false;
                $lineas = $data['lineas'] ?? [];

                // Validaciones
                if (!$tipoMovimiento || !$documentoNumero || empty($lineas)) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Datos incompletos. Verifique tipo, documento y líneas.']);
                    exit();
                }

                $esEntrada = strpos($tipoMovimiento, 'ENTRADA') !== false;

                $db->beginTransaction();

                try {
                    $movimientosRegistrados = 0;
                    $errores = [];
                    $totalValorNeto = 0;
                    $totalIVA = 0;

                    foreach ($lineas as $linea) {
                        $idInventario = intval($linea['id_inventario']);
                        $cantidad = floatval($linea['cantidad']);

                        // El costo_unitario que llega ya viene con el descuento de IVA aplicado desde el frontend
                        $costoUnitarioNeto = floatval($linea['costo_unitario']);
                        $costoBruto = floatval($linea['costo_bruto'] ?? $costoUnitarioNeto);
                        $valorTotalBruto = floatval($linea['valor_total_bruto'] ?? ($cantidad * $costoBruto));

                        if ($idInventario <= 0 || $cantidad <= 0) {
                            continue;
                        }

                        // Obtener datos actuales del producto
                        $stmt = $db->prepare("
                            SELECT id_inventario, codigo, nombre, stock_actual, costo_unitario, costo_promedio,
                                   (stock_actual * COALESCE(costo_promedio, costo_unitario)) AS valor_inventario
                            FROM inventarios 
                            WHERE id_inventario = ? AND activo = 1
                        ");
                        $stmt->execute([$idInventario]);
                        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$producto) {
                            $errores[] = "Producto ID {$idInventario} no encontrado";
                            continue;
                        }

                        $stockAnterior = floatval($producto['stock_actual']);
                        $costoPromedioAnterior = floatval($producto['costo_promedio']) ?: floatval($producto['costo_unitario']);
                        $valorInventarioAnterior = $stockAnterior * $costoPromedioAnterior;

                        // Variables para el movimiento
                        $stockNuevo = 0;
                        $costoUnitarioMovimiento = $costoUnitarioNeto; // Usar costo NETO
                        $costoPromedioNuevo = $costoPromedioAnterior;
                        $valorTotalMovimiento = $cantidad * $costoUnitarioNeto;

                        if ($esEntrada) {
                            // ========== ENTRADA ==========
                            $stockNuevo = $stockAnterior + $cantidad;

                            // Si no viene costo, usar el promedio actual
                            if ($costoUnitarioNeto <= 0) {
                                $costoUnitarioNeto = $costoPromedioAnterior;
                                $costoUnitarioMovimiento = $costoUnitarioNeto;
                                $valorTotalMovimiento = $cantidad * $costoUnitarioNeto;
                            }

                            // Calcular nuevo CPP con el COSTO NETO (sin IVA)
                            if ($stockNuevo > 0) {
                                $costoPromedioNuevo = ($valorInventarioAnterior + $valorTotalMovimiento) / $stockNuevo;
                            } else {
                                $costoPromedioNuevo = $costoUnitarioNeto;
                            }

                            // Calcular IVA para el registro
                            if ($conFactura) {
                                $ivaLinea = $valorTotalBruto - $valorTotalMovimiento;
                                $totalIVA += $ivaLinea;
                            }
                            $totalValorNeto += $valorTotalMovimiento;

                        } else {
                            // ========== SALIDA ==========
                            if ($stockAnterior < $cantidad) {
                                $errores[] = "Stock insuficiente para {$producto['codigo']}. Disponible: " . number_format($stockAnterior, 2);
                                continue;
                            }

                            $stockNuevo = $stockAnterior - $cantidad;
                            $costoUnitarioMovimiento = $costoPromedioAnterior; // Salidas al CPP
                            $valorTotalMovimiento = $cantidad * $costoPromedioAnterior;
                            $costoPromedioNuevo = $costoPromedioAnterior; // No cambia
                        }

                        // Construir observaciones
                        $obsCompleta = trim($observaciones);
                        if ($proveedor) {
                            $obsCompleta = ($esEntrada ? "Prov: " : "Dest: ") . $proveedor . ($obsCompleta ? " | {$obsCompleta}" : "");
                        }
                        if ($conFactura && $esEntrada) {
                            $obsCompleta .= " [FACTURA - IVA 13% descontado del costo]";
                        }

                        // Insertar movimiento
                        $stmt = $db->prepare("
                            INSERT INTO movimientos_inventario_erp (
                                id_inventario, tipo_movimiento, cantidad,
                                stock_anterior, stock_nuevo, 
                                costo_unitario, costo_total, costo_promedio_resultado,
                                documento_tipo, documento_numero,
                                id_usuario, observaciones, fecha_movimiento
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");

                        $fechaMovimiento = $fecha . ' ' . date('H:i:s');

                        $stmt->execute([
                            $idInventario,
                            $tipoMovimiento,
                            $cantidad,
                            $stockAnterior,
                            $stockNuevo,
                            $costoUnitarioMovimiento,
                            $valorTotalMovimiento,
                            $costoPromedioNuevo,
                            $documentoTipo,
                            $documentoNumero,
                            $_SESSION['user_id'],
                            $obsCompleta,
                            $fechaMovimiento
                        ]);

                        // Actualizar inventario con el nuevo CPP
                        $stmt = $db->prepare("
                            UPDATE inventarios 
                            SET stock_actual = ?,
                                costo_promedio = ?,
                                costo_unitario = ?
                            WHERE id_inventario = ?
                        ");
                        $stmt->execute([
                            $stockNuevo,
                            $costoPromedioNuevo,
                            $costoPromedioNuevo,
                            $idInventario
                        ]);

                        $movimientosRegistrados++;
                    }

                    if ($movimientosRegistrados === 0) {
                        $db->rollBack();
                        ob_clean();
                        echo json_encode([
                            'success' => false,
                            'message' => 'No se registró ningún movimiento',
                            'errores' => $errores
                        ]);
                        exit();
                    }

                    $db->commit();

                    // Construir mensaje de respuesta
                    $tipoTexto = $esEntrada ? 'ingreso(s)' : 'salida(s)';
                    $mensaje = "Se registraron {$movimientosRegistrados} {$tipoTexto} exitosamente";

                    if ($conFactura && $esEntrada && $totalIVA > 0) {
                        $mensaje .= ". Crédito Fiscal IVA: Bs. " . number_format($totalIVA, 2);
                    }

                    if (count($errores) > 0) {
                        $mensaje .= ". Advertencias: " . implode("; ", $errores);
                    }

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => $mensaje,
                        'movimientos_registrados' => $movimientosRegistrados,
                        'documento' => $documentoNumero,
                        'valor_neto' => $totalValorNeto,
                        'iva_credito' => $totalIVA,
                        'errores' => $errores
                    ]);

                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }

            } elseif ($action === 'anular_documento') {
                // ========== ANULAR DOCUMENTO ==========
                // Crea movimientos inversos para revertir el documento original
                $documentoNumero = $data['documento_numero'] ?? null;
                $motivo = trim($data['motivo'] ?? 'Anulación de documento');

                if (!$documentoNumero) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Número de documento requerido']);
                    exit();
                }

                // Verificar que el documento existe y no está ya anulado
                $stmt = $db->prepare("
                    SELECT documento_numero, tipo_movimiento, estado
                    FROM movimientos_inventario_erp 
                    WHERE documento_numero = ?
                    LIMIT 1
                ");
                $stmt->execute([$documentoNumero]);
                $docOriginal = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$docOriginal) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
                    exit();
                }

                if ($docOriginal['estado'] === 'ANULADO') {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Este documento ya fue anulado']);
                    exit();
                }

                // Obtener todos los movimientos del documento original
                $stmt = $db->prepare("
                    SELECT m.*, i.stock_actual, i.costo_promedio
                    FROM movimientos_inventario_erp m
                    JOIN inventarios i ON m.id_inventario = i.id_inventario
                    WHERE m.documento_numero = ? AND m.estado = 'ACTIVO'
                ");
                $stmt->execute([$documentoNumero]);
                $movimientosOriginales = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($movimientosOriginales) === 0) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'No hay movimientos activos para anular']);
                    exit();
                }

                try {
                    $db->beginTransaction();

                    $docAnulacion = 'ANUL-' . $documentoNumero;
                    $fechaAnulacion = date('Y-m-d H:i:s');
                    $movimientosAnulados = 0;

                    foreach ($movimientosOriginales as $movOrig) {
                        $idInventario = $movOrig['id_inventario'];
                        $cantidadOriginal = floatval($movOrig['cantidad']);
                        $costoOriginal = floatval($movOrig['costo_unitario']);
                        $stockActual = floatval($movOrig['stock_actual']);
                        $cppActual = floatval($movOrig['costo_promedio']);

                        $tipoOriginal = $movOrig['tipo_movimiento'];
                        $esEntradaOriginal = strpos($tipoOriginal, 'ENTRADA') !== false;

                        // Calcular movimiento inverso
                        if ($esEntradaOriginal) {
                            // Original fue ENTRADA, anulación es SALIDA
                            $tipoAnulacion = 'SALIDA_ANULACION';
                            $stockNuevo = $stockActual - $cantidadOriginal;

                            // Para salidas, el CPP no cambia
                            $cppNuevo = $cppActual;
                            $costoTotalAnulacion = $cantidadOriginal * $cppActual;
                        } else {
                            // Original fue SALIDA, anulación es ENTRADA
                            $tipoAnulacion = 'ENTRADA_ANULACION';
                            $stockNuevo = $stockActual + $cantidadOriginal;

                            // Recalcular CPP con la entrada de anulación
                            $valorActual = $stockActual * $cppActual;
                            $valorAnulacion = $cantidadOriginal * $costoOriginal;
                            $cppNuevo = ($valorActual + $valorAnulacion) / $stockNuevo;
                            $costoTotalAnulacion = $cantidadOriginal * $costoOriginal;
                        }

                        // Validar que no quede stock negativo
                        if ($stockNuevo < 0) {
                            throw new Exception("No se puede anular: el producto {$movOrig['id_inventario']} quedaría con stock negativo");
                        }

                        // Insertar movimiento de anulación
                        $stmt = $db->prepare("
                            INSERT INTO movimientos_inventario_erp (
                                id_inventario, tipo_movimiento, cantidad,
                                stock_anterior, stock_nuevo, 
                                costo_unitario, costo_total, costo_promedio_resultado,
                                documento_tipo, documento_numero,
                                id_usuario, observaciones, fecha_movimiento, estado
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ANULACION', ?, ?, ?, ?, 'ACTIVO')
                        ");

                        $obsAnulacion = "ANULACIÓN: {$motivo} (Doc. original: {$documentoNumero})";

                        $stmt->execute([
                            $idInventario,
                            $tipoAnulacion,
                            $cantidadOriginal,
                            $stockActual,
                            $stockNuevo,
                            $costoOriginal,
                            $costoTotalAnulacion,
                            $cppNuevo,
                            $docAnulacion,
                            $_SESSION['user_id'],
                            $obsAnulacion,
                            $fechaAnulacion
                        ]);

                        // Actualizar inventario
                        $stmt = $db->prepare("
                            UPDATE inventarios 
                            SET stock_actual = ?,
                                costo_promedio = ?,
                                costo_unitario = ?
                            WHERE id_inventario = ?
                        ");
                        $stmt->execute([
                            $stockNuevo,
                            $cppNuevo,
                            $cppNuevo,
                            $idInventario
                        ]);

                        // Marcar movimiento original como anulado
                        $stmt = $db->prepare("
                            UPDATE movimientos_inventario_erp 
                            SET estado = 'ANULADO'
                            WHERE id_movimiento = ?
                        ");
                        $stmt->execute([$movOrig['id_movimiento']]);

                        $movimientosAnulados++;
                    }

                    $db->commit();

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => "Documento {$documentoNumero} anulado exitosamente. Se revirtieron {$movimientosAnulados} movimiento(s).",
                        'documento_anulacion' => $docAnulacion,
                        'movimientos_anulados' => $movimientosAnulados
                    ]);

                } catch (Exception $e) {
                    $db->rollBack();
                    ob_clean();
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al anular: ' . $e->getMessage()
                    ]);
                    exit();
                }

            } else {
                // Crear o actualizar inventario
                $idInventario = $data['id_inventario'] ?? null;
                $codigo = trim($data['codigo'] ?? '');
                $nombre = trim($data['nombre'] ?? '');
                $descripcion = trim($data['descripcion'] ?? '');
                $idTipoInventario = intval($data['id_tipo_inventario'] ?? 0);
                $idCategoria = intval($data['id_categoria'] ?? 0);
                // Capturar id_subcategoria (puede ser null si no se asigna)
                $idSubcategoria = isset($data['id_subcategoria']) && $data['id_subcategoria'] !== null && $data['id_subcategoria'] !== ''
                    ? intval($data['id_subcategoria'])
                    : null;
                $idUnidad = intval($data['id_unidad'] ?? 0);
                $stockActual = floatval($data['stock_actual'] ?? 0);
                $stockMinimo = floatval($data['stock_minimo'] ?? 0);
                $costoUnitario = floatval($data['costo_unitario'] ?? 0);
                $idUbicacion = $data['id_ubicacion'] ? intval($data['id_ubicacion']) : null;
                $idLineaProduccion = $data['id_linea_produccion'] ? intval($data['id_linea_produccion']) : null;
                $proveedorPrincipal = trim($data['proveedor_principal'] ?? '');

                // Validaciones
                if (empty($codigo) || empty($nombre) || !$idTipoInventario || !$idCategoria || !$idUnidad) {
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
                    exit();
                }

                if ($idInventario) {
                    // Actualizar
                    $stmt = $db->prepare("
                        UPDATE inventarios SET
                            codigo = ?,
                            nombre = ?,
                            descripcion = ?,
                            id_tipo_inventario = ?,
                            id_categoria = ?,
                            id_subcategoria = ?,
                            id_unidad = ?,
                            stock_actual = ?,
                            stock_minimo = ?,
                            costo_unitario = ?,
                            id_ubicacion = ?,
                            id_linea_produccion = ?,
                            proveedor_principal = ?
                        WHERE id_inventario = ?
                    ");
                    $stmt->execute([
                        $codigo,
                        $nombre,
                        $descripcion,
                        $idTipoInventario,
                        $idCategoria,
                        $idSubcategoria,
                        $idUnidad,
                        $stockActual,
                        $stockMinimo,
                        $costoUnitario,
                        $idUbicacion,
                        $idLineaProduccion,
                        $proveedorPrincipal,
                        $idInventario
                    ]);

                    ob_clean();
                    echo json_encode(['success' => true, 'message' => 'Inventario actualizado exitosamente']);
                } else {
                    // Crear
                    $stmt = $db->prepare("
                        INSERT INTO inventarios (
                            codigo, nombre, descripcion,
                            id_tipo_inventario, id_categoria, id_subcategoria, id_unidad,
                            stock_actual, stock_minimo, costo_unitario,
                            id_ubicacion, id_linea_produccion, proveedor_principal
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $codigo,
                        $nombre,
                        $descripcion,
                        $idTipoInventario,
                        $idCategoria,
                        $idSubcategoria,
                        $idUnidad,
                        $stockActual,
                        $stockMinimo,
                        $costoUnitario,
                        $idUbicacion,
                        $idLineaProduccion,
                        $proveedorPrincipal
                    ]);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Inventario creado exitosamente',
                        'id_inventario' => $db->lastInsertId()
                    ]);
                }
            }
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $idInventario = $data['id_inventario'] ?? null;

            if (!$idInventario) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'ID de inventario requerido']);
                exit();
            }

            // Soft delete
            $stmt = $db->prepare("UPDATE inventarios SET activo = 0 WHERE id_inventario = ?");
            $stmt->execute([$idInventario]);

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Inventario eliminado exitosamente']);
            break;

        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }

} catch (PDOException $e) {
    error_log("Error en inventarios.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error en inventarios.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();
exit();