<?php
/**
 * API de Ingresos de Materias Primas
 * Sistema MES Hermen Ltda.
 * Versión: 1.1 - Sin conflictos Git
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
    $TIPO_INVENTARIO_MP = 1; // ID de Materias Primas
    
    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? 'list';
            
            switch ($action) {
                case 'list':
                    // Listar documentos de ingreso
                    $desde = $_GET['desde'] ?? date('Y-m-01');
                    $hasta = $_GET['hasta'] ?? date('Y-m-d');
                    $proveedor = $_GET['proveedor'] ?? null;
                    $estado = $_GET['estado'] ?? 'todos';
                    
                    // Consulta directa sin depender de la vista
                    $sql = "SELECT 
                                d.id_documento,
                                d.numero_documento,
                                d.fecha_documento,
                                d.tipo_documento,
                                d.id_tipo_inventario,
                                d.id_proveedor,
                                p.codigo AS proveedor_codigo,
                                p.razon_social AS proveedor_nombre,
                                p.nombre_comercial AS proveedor_comercial,
                                p.tipo AS proveedor_tipo,
                                d.referencia_externa,
                                d.con_factura,
                                d.moneda,
                                d.subtotal,
                                d.iva,
                                d.total,
                                d.estado,
                                d.observaciones,
                                d.fecha_creacion
                            FROM documentos_inventario d
                            LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor
                            WHERE d.tipo_documento = 'INGRESO' 
                            AND d.fecha_documento BETWEEN ? AND ?";
                    $params = [$desde, $hasta];
                    
                    if ($proveedor) {
                        $sql .= " AND d.id_proveedor = ?";
                        $params[] = $proveedor;
                    }
                    
                    if ($estado !== 'todos') {
                        $sql .= " AND d.estado = ?";
                        $params[] = $estado;
                    }
                    
                    $sql .= " ORDER BY d.fecha_documento DESC, d.id_documento DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'documentos' => $documentos,
                        'total' => count($documentos)
                    ]);
                    break;
                    
                case 'get':
                    // Obtener un documento con su detalle
                    $id = $_GET['id'] ?? null;
                    if (!$id) {
                        echo json_encode(['success' => false, 'message' => 'ID requerido']);
                        exit();
                    }
                    
                    // Documento principal
                    $stmt = $db->prepare("
                        SELECT 
                            d.*,
                            p.codigo AS proveedor_codigo,
                            p.razon_social AS proveedor_nombre,
                            p.nombre_comercial AS proveedor_comercial
                        FROM documentos_inventario d
                        LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor
                        WHERE d.id_documento = ?
                    ");
                    $stmt->execute([$id]);
                    $documento = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$documento) {
                        echo json_encode(['success' => false, 'message' => 'Documento no encontrado']);
                        exit();
                    }
                    
                    // Detalle del documento
                    $stmtDet = $db->prepare("
                        SELECT 
                            dd.*,
                            i.codigo AS producto_codigo,
                            i.nombre AS producto_nombre,
                            um.abreviatura AS unidad
                        FROM documentos_inventario_detalle dd
                        JOIN inventarios i ON dd.id_inventario = i.id_inventario
                        LEFT JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                        WHERE dd.id_documento = ?
                    ");
                    $stmtDet->execute([$id]);
                    $detalle = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
                    
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'documento' => $documento,
                        'detalle' => $detalle
                    ]);
                    break;
                    
                case 'siguiente_numero':
                    // Obtener siguiente número de documento
                    $numero = generarNumeroDocumento($db, 'INGRESO', 'ING-MP');
                    ob_clean();
                    echo json_encode(['success' => true, 'numero' => $numero]);
                    break;
                    
                case 'resumen':
                    // KPIs del mes actual
                    $mes = $_GET['mes'] ?? date('m');
                    $anio = $_GET['anio'] ?? date('Y');
                    
                    $stmt = $db->prepare("
                        SELECT 
                            COUNT(*) as total_documentos,
                            SUM(CASE WHEN estado = 'CONFIRMADO' THEN total ELSE 0 END) as valor_total,
                            SUM(CASE WHEN estado = 'CONFIRMADO' THEN 1 ELSE 0 END) as confirmados,
                            SUM(CASE WHEN estado = 'ANULADO' THEN 1 ELSE 0 END) as anulados,
                            SUM(CASE WHEN con_factura = 1 AND estado = 'CONFIRMADO' THEN total ELSE 0 END) as total_con_factura,
                            SUM(CASE WHEN con_factura = 0 AND estado = 'CONFIRMADO' THEN total ELSE 0 END) as total_sin_factura
                        FROM documentos_inventario 
                        WHERE tipo_documento = 'INGRESO' 
                        AND id_tipo_inventario = ?
                        AND MONTH(fecha_documento) = ? 
                        AND YEAR(fecha_documento) = ?
                    ");
                    $stmt->execute([$TIPO_INVENTARIO_MP, $mes, $anio]);
                    $resumen = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Últimos 5 ingresos
                    $stmtUlt = $db->prepare("
                        SELECT 
                            d.id_documento, 
                            d.numero_documento, 
                            d.fecha_documento, 
                            p.nombre_comercial AS proveedor_comercial, 
                            p.razon_social AS proveedor_nombre, 
                            d.total, 
                            d.estado
                        FROM documentos_inventario d
                        LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor
                        WHERE d.tipo_documento = 'INGRESO'
                        ORDER BY d.fecha_documento DESC, d.id_documento DESC 
                        LIMIT 5
                    ");
                    $stmtUlt->execute();
                    $ultimos = $stmtUlt->fetchAll(PDO::FETCH_ASSOC);
                    
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'resumen' => $resumen,
                        'ultimos' => $ultimos
                    ]);
                    break;
                    
                case 'productos':
                    // Listar productos de materias primas para el select
                    $categoria = $_GET['categoria'] ?? null;
                    $subcategoria = $_GET['subcategoria'] ?? null;
                    
                    $sql = "
                        SELECT 
                            i.id_inventario,
                            i.codigo,
                            i.nombre,
                            i.id_categoria,
                            i.id_subcategoria,
                            i.stock_actual,
                            i.costo_promedio,
                            um.abreviatura as unidad
                        FROM inventarios i
                        LEFT JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                        WHERE i.id_tipo_inventario = ? AND i.activo = 1
                    ";
                    $params = [$TIPO_INVENTARIO_MP];
                    
                    if ($categoria) {
                        $sql .= " AND i.id_categoria = ?";
                        $params[] = $categoria;
                    }
                    
                    if ($subcategoria) {
                        $sql .= " AND i.id_subcategoria = ?";
                        $params[] = $subcategoria;
                    }
                    
                    $sql .= " ORDER BY i.codigo";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    ob_clean();
                    echo json_encode(['success' => true, 'productos' => $productos]);
                    break;
                    
                case 'categorias':
                    // Categorías de materias primas
                    $stmt = $db->prepare("
                        SELECT id_categoria, codigo, nombre 
                        FROM categorias_inventario 
                        WHERE id_tipo_inventario = ? AND activo = 1
                        ORDER BY orden, nombre
                    ");
                    $stmt->execute([$TIPO_INVENTARIO_MP]);
                    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    ob_clean();
                    echo json_encode(['success' => true, 'categorias' => $categorias]);
                    break;
                    
                case 'subcategorias':
                    $catId = $_GET['categoria_id'] ?? null;
                    if (!$catId) {
                        echo json_encode(['success' => true, 'subcategorias' => []]);
                        exit();
                    }
                    
                    $stmt = $db->prepare("
                        SELECT id_subcategoria, codigo, nombre 
                        FROM subcategorias_inventario 
                        WHERE id_categoria = ? AND activo = 1
                        ORDER BY orden, nombre
                    ");
                    $stmt->execute([$catId]);
                    $subcategorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    ob_clean();
                    echo json_encode(['success' => true, 'subcategorias' => $subcategorias]);
                    break;
                    
                case 'proveedores':
                    $tipo = $_GET['tipo'] ?? null;
                    
                    $sql = "SELECT id_proveedor, codigo, razon_social, nombre_comercial, tipo, moneda, condicion_pago, pais 
                            FROM proveedores WHERE activo = 1";
                    $params = [];
                    
                    if ($tipo && $tipo !== 'TODOS') {
                        $sql .= " AND tipo = ?";
                        $params[] = $tipo;
                    }
                    
                    $sql .= " ORDER BY tipo, razon_social";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    ob_clean();
                    echo json_encode(['success' => true, 'proveedores' => $proveedores]);
                    break;
                    
                default:
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? 'crear';
            
            switch ($action) {
                case 'crear':
                    // Validaciones
                    if (empty($data['id_proveedor'])) {
                        echo json_encode(['success' => false, 'message' => 'Seleccione un proveedor']);
                        exit();
                    }
                    
                    if (empty($data['lineas']) || count($data['lineas']) === 0) {
                        echo json_encode(['success' => false, 'message' => 'Agregue al menos una línea']);
                        exit();
                    }
                    
                    $db->beginTransaction();
                    
                    try {
                        // Generar número de documento
                        $numeroDoc = generarNumeroDocumento($db, 'INGRESO', 'ING-MP');
                        
                        // Calcular totales desde las líneas
                        $totalDocumento = 0;
                        $totalNeto = 0;
                        $iva = 0;
                        $conFactura = $data['con_factura'] ?? false;
                        
                        foreach ($data['lineas'] as $linea) {
                            $subtotalLinea = floatval($linea['subtotal'] ?? 0);
                            $totalDocumento += $subtotalLinea;
                            
                            if ($conFactura) {
                                // Neto = Subtotal * 0.87
                                $netoLinea = $subtotalLinea * 0.87;
                                $totalNeto += $netoLinea;
                                $iva += $subtotalLinea * 0.13;
                            } else {
                                $totalNeto += $subtotalLinea;
                            }
                        }
                        
                        // Insertar documento
                        $stmt = $db->prepare("
                            INSERT INTO documentos_inventario (
                                tipo_documento, numero_documento, fecha_documento,
                                id_tipo_inventario, id_proveedor, referencia_externa,
                                con_factura, moneda, subtotal, iva, total,
                                observaciones, estado, creado_por
                            ) VALUES (
                                'INGRESO', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'CONFIRMADO', ?
                            )
                        ");
                        
                        $stmt->execute([
                            $numeroDoc,
                            $data['fecha'] ?? date('Y-m-d'),
                            $TIPO_INVENTARIO_MP,
                            $data['id_proveedor'],
                            $data['referencia'] ?? null,
                            $conFactura ? 1 : 0,
                            $data['moneda'] ?? 'BOB',
                            $totalNeto,        // Subtotal neto (sin IVA)
                            $iva,              // IVA
                            $totalDocumento,   // Total documento
                            $data['observaciones'] ?? null,
                            $_SESSION['user_id'] ?? null
                        ]);
                        
                        $idDocumento = $db->lastInsertId();
                        
                        // Insertar líneas y actualizar stock
                        $stmtLinea = $db->prepare("
                            INSERT INTO documentos_inventario_detalle (
                                id_documento, id_inventario, cantidad, costo_unitario, subtotal
                            ) VALUES (?, ?, ?, ?, ?)
                        ");
                        
                        $stmtStock = $db->prepare("
                            UPDATE inventarios 
                            SET stock_actual = stock_actual + ?,
                                costo_promedio = CASE 
                                    WHEN stock_actual = 0 THEN ?
                                    ELSE ((stock_actual * costo_promedio) + (? * ?)) / (stock_actual + ?)
                                END,
                                costo_unitario = ?
                            WHERE id_inventario = ?
                        ");
                        
                        $stmtKardex = $db->prepare("
                            INSERT INTO kardex_inventario (
                                id_inventario, fecha_movimiento, tipo_movimiento, id_documento,
                                documento_referencia, cantidad, costo_unitario, costo_total,
                                stock_anterior, stock_posterior, creado_por
                            ) VALUES (?, NOW(), 'ENTRADA', ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        foreach ($data['lineas'] as $linea) {
                            $cantidad = floatval($linea['cantidad']);
                            $costoUnit = floatval($linea['costo_unitario']);
                            $subtotalLinea = floatval($linea['subtotal']);
                            
                            // Insertar línea
                            $stmtLinea->execute([
                                $idDocumento,
                                $linea['id_inventario'],
                                $cantidad,
                                $costoUnit,       // Costo sin IVA (para inventario)
                                $subtotalLinea    // Subtotal del documento
                            ]);
                            
                            // Obtener stock anterior
                            $stmtStockAnt = $db->prepare("SELECT stock_actual FROM inventarios WHERE id_inventario = ?");
                            $stmtStockAnt->execute([$linea['id_inventario']]);
                            $stockAnterior = floatval($stmtStockAnt->fetchColumn());
                            
                            // Actualizar stock e inventario (usar costo sin IVA para el inventario)
                            $stmtStock->execute([
                                $cantidad,           // Sumar al stock
                                $costoUnit,          // Costo si stock era 0
                                $cantidad,           // Para promedio
                                $costoUnit,          // Para promedio
                                $cantidad,           // Para promedio
                                $costoUnit,          // Actualizar costo unitario
                                $linea['id_inventario']
                            ]);
                            
                            // Registrar en Kardex (con costo sin IVA)
                            $costoTotalNeto = $cantidad * $costoUnit;
                            $stmtKardex->execute([
                                $linea['id_inventario'],
                                $idDocumento,
                                $numeroDoc,
                                $cantidad,
                                $costoUnit,
                                $costoTotalNeto,
                                $stockAnterior,
                                $stockAnterior + $cantidad,
                                $_SESSION['user_id'] ?? null
                            ]);
                        }
                        
                        $db->commit();
                        
                        ob_clean();
                        echo json_encode([
                            'success' => true,
                            'message' => "Ingreso $numeroDoc registrado exitosamente",
                            'id_documento' => $idDocumento,
                            'numero_documento' => $numeroDoc
                        ]);
                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;
                    
                case 'anular':
                    $id = $data['id_documento'] ?? null;
                    $motivo = $data['motivo'] ?? 'Sin especificar';
                    
                    if (!$id) {
                        echo json_encode(['success' => false, 'message' => 'ID requerido']);
                        exit();
                    }
                    
                    $db->beginTransaction();
                    
                    try {
                        // Verificar que el documento existe y está confirmado
                        $stmt = $db->prepare("SELECT * FROM documentos_inventario WHERE id_documento = ? AND estado = 'CONFIRMADO'");
                        $stmt->execute([$id]);
                        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$doc) {
                            echo json_encode(['success' => false, 'message' => 'Documento no encontrado o ya anulado']);
                            exit();
                        }
                        
                        // Obtener detalle para revertir stock
                        $stmtDet = $db->prepare("SELECT * FROM documentos_inventario_detalle WHERE id_documento = ?");
                        $stmtDet->execute([$id]);
                        $lineas = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Revertir stock
                        $stmtRevert = $db->prepare("UPDATE inventarios SET stock_actual = stock_actual - ? WHERE id_inventario = ?");
                        
                        foreach ($lineas as $linea) {
                            $stmtRevert->execute([$linea['cantidad'], $linea['id_inventario']]);
                            
                            // Registrar en Kardex la reversión
                            $stmtStockAct = $db->prepare("SELECT stock_actual FROM inventarios WHERE id_inventario = ?");
                            $stmtStockAct->execute([$linea['id_inventario']]);
                            $stockActual = floatval($stmtStockAct->fetchColumn());
                            
                            $stmtKardex = $db->prepare("
                                INSERT INTO kardex_inventario (
                                    id_inventario, fecha_movimiento, tipo_movimiento, id_documento,
                                    documento_referencia, cantidad, costo_unitario, costo_total,
                                    stock_anterior, stock_posterior, observaciones, creado_por
                                ) VALUES (?, NOW(), 'SALIDA', ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmtKardex->execute([
                                $linea['id_inventario'],
                                $id,
                                $doc['numero_documento'] . ' (ANULADO)',
                                $linea['cantidad'],
                                $linea['costo_unitario'],
                                $linea['subtotal'],
                                $stockActual + $linea['cantidad'],
                                $stockActual,
                                'Anulación: ' . $motivo,
                                $_SESSION['user_id'] ?? null
                            ]);
                        }
                        
                        // Marcar documento como anulado
                        $stmtAnular = $db->prepare("
                            UPDATE documentos_inventario 
                            SET estado = 'ANULADO', fecha_anulacion = NOW(), motivo_anulacion = ?, actualizado_por = ?
                            WHERE id_documento = ?
                        ");
                        $stmtAnular->execute([$motivo, $_SESSION['user_id'] ?? null, $id]);
                        
                        $db->commit();
                        
                        ob_clean();
                        echo json_encode([
                            'success' => true,
                            'message' => 'Documento anulado exitosamente'
                        ]);
                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;
                    
                default:
                    ob_clean();
                    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            }
            break;
            
        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
    
} catch(PDOException $e) {
    error_log("Error en ingresos_mp.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("Error en ingresos_mp.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

/**
 * Genera el siguiente número de documento
 */
function generarNumeroDocumento($db, $tipo, $prefijo) {
    $anio = date('Y');
    $mes = date('m');
    
    // Buscar o crear secuencia
    $stmt = $db->prepare("
        SELECT ultimo_numero FROM secuencias_documento 
        WHERE tipo_documento = ? AND prefijo = ? AND anio = ? AND mes = ?
        FOR UPDATE
    ");
    $stmt->execute([$tipo, $prefijo, $anio, $mes]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $siguiente = $row['ultimo_numero'] + 1;
        $stmtUp = $db->prepare("
            UPDATE secuencias_documento SET ultimo_numero = ?
            WHERE tipo_documento = ? AND prefijo = ? AND anio = ? AND mes = ?
        ");
        $stmtUp->execute([$siguiente, $tipo, $prefijo, $anio, $mes]);
    } else {
        $siguiente = 1;
        $stmtIn = $db->prepare("
            INSERT INTO secuencias_documento (tipo_documento, prefijo, anio, mes, ultimo_numero)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmtIn->execute([$tipo, $prefijo, $anio, $mes]);
    }
    
    return $prefijo . '-' . $anio . $mes . '-' . str_pad($siguiente, 4, '0', STR_PAD_LEFT);
}

ob_end_flush();
exit();