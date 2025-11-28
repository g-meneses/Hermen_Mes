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
                    // Movimientos de un producto específico
                    $inventarioId = $_GET['id'] ?? null;
                    if (!$inventarioId) {
                        ob_clean();
                        echo json_encode(['success' => false, 'message' => 'ID de inventario requerido']);
                        exit();
                    }
                    
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
                            m.documento_tipo,
                            m.documento_numero,
                            m.numero_lote,
                            m.observaciones,
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
                        'movimientos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
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
                            um.abreviatura AS unidad,
                            i.stock_actual,
                            i.stock_minimo,
                            i.costo_unitario,
                            (i.stock_actual * i.costo_unitario) AS valor_total,
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
                    
                    if ($buscar) {
                        $sql .= " AND (i.codigo LIKE ? OR i.nombre LIKE ?)";
                        $params[] = "%$buscar%";
                        $params[] = "%$buscar%";
                    }
                    
                    $sql .= " ORDER BY ti.orden, ci.orden, i.nombre";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $inventarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Filtrar por estado de stock si se especificó
                    if ($estadoStock) {
                        $inventarios = array_filter($inventarios, function($item) use ($estadoStock) {
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
                            costo_unitario, costo_total,
                            documento_tipo, documento_numero,
                            id_usuario, observaciones
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $idInventario,
                        $tipoMovimiento,
                        $cantidad,
                        $stockAnterior,
                        $stockNuevo,
                        $costoUnitarioMovimiento,
                        $valorTotalMovimiento,
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
                
            } else {
                // Crear o actualizar inventario
                $idInventario = $data['id_inventario'] ?? null;
                $codigo = trim($data['codigo'] ?? '');
                $nombre = trim($data['nombre'] ?? '');
                $descripcion = trim($data['descripcion'] ?? '');
                $idTipoInventario = intval($data['id_tipo_inventario'] ?? 0);
                $idCategoria = intval($data['id_categoria'] ?? 0);
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
                        $codigo, $nombre, $descripcion,
                        $idTipoInventario, $idCategoria, $idUnidad,
                        $stockActual, $stockMinimo, $costoUnitario,
                        $idUbicacion, $idLineaProduccion, $proveedorPrincipal,
                        $idInventario
                    ]);
                    
                    ob_clean();
                    echo json_encode(['success' => true, 'message' => 'Inventario actualizado exitosamente']);
                } else {
                    // Crear
                    $stmt = $db->prepare("
                        INSERT INTO inventarios (
                            codigo, nombre, descripcion,
                            id_tipo_inventario, id_categoria, id_unidad,
                            stock_actual, stock_minimo, costo_unitario,
                            id_ubicacion, id_linea_produccion, proveedor_principal
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $codigo, $nombre, $descripcion,
                        $idTipoInventario, $idCategoria, $idUnidad,
                        $stockActual, $stockMinimo, $costoUnitario,
                        $idUbicacion, $idLineaProduccion, $proveedorPrincipal
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
    
} catch(PDOException $e) {
    error_log("Error en inventarios.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos',
        'error' => $e->getMessage()
    ]);
} catch(Exception $e) {
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