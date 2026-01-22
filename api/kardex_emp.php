<?php
/**
 * API de Kardex de Material de Empaque (EMP)
 * Sistema MES Hermen Ltda.
 * Adaptado del módulo de Colorantes y Auxiliares Químicos
 */

ob_start();
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
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
    $TIPO_INVENTARIO_EMP = 3; // ID fijo para Material de Empaque

    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? 'get';

            switch ($action) {
                case 'get':
                    // Obtener kardex de un producto específico
                    $idInventario = $_GET['id_inventario'] ?? null;
                    $desde = $_GET['desde'] ?? null;
                    $hasta = $_GET['hasta'] ?? null;

                    if (!$idInventario) {
                        echo json_encode(['success' => false, 'message' => 'ID de inventario requerido']);
                        exit();
                    }

                    // Obtener información del producto
                    $stmtProd = $db->prepare("
                        SELECT 
                            i.codigo,
                            i.nombre,
                            i.stock_actual,
                            i.costo_promedio,
                            i.costo_unitario,
                            um.abreviatura as unidad
                        FROM inventarios i
                        LEFT JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                        WHERE i.id_inventario = ? AND i.id_tipo_inventario = ?
                    ");
                    $stmtProd->execute([$idInventario, $TIPO_INVENTARIO_EMP]);
                    $producto = $stmtProd->fetch(PDO::FETCH_ASSOC);

                    if (!$producto) {
                        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
                        exit();
                    }

                    // Calcular saldo inicial si hay filtro de fecha
                    $saldoInicial = null;
                    if ($desde) {
                        // Buscar el último movimiento antes de la fecha inicial
                        $stmtSaldo = $db->prepare("
                            SELECT 
                                stock_posterior as cantidad,
                                stock_posterior * costo_promedio_posterior as valor_total,
                                costo_promedio_posterior as cpp
                            FROM movimientos_inventario
                            WHERE id_inventario = ? 
                              AND id_tipo_inventario = ?
                              AND DATE(fecha_movimiento) < ?
                              AND estado = 'ACTIVO'
                            ORDER BY fecha_movimiento DESC, id_movimiento DESC
                            LIMIT 1
                        ");
                        $stmtSaldo->execute([$idInventario, $TIPO_INVENTARIO_EMP, $desde]);
                        $saldoInicial = $stmtSaldo->fetch(PDO::FETCH_ASSOC);

                        if ($saldoInicial) {
                            $saldoInicial['cantidad'] = floatval($saldoInicial['cantidad']);
                            $saldoInicial['valor_total'] = floatval($saldoInicial['valor_total']);
                            $saldoInicial['cpp'] = floatval($saldoInicial['cpp']);
                        } else {
                            $saldoInicial = [
                                'cantidad' => 0,
                                'valor_total' => 0,
                                'cpp' => 0
                            ];
                        }
                    }

                    // Construir query de movimientos con ORDEN CRONOLÓGICO PERFECTO
                    $sql = "
                        SELECT 
                            m.id_movimiento,
                            m.fecha_movimiento,
                            m.tipo_movimiento,
                            m.codigo_movimiento,
                            m.documento_numero,
                            m.documento_tipo,
                            m.cantidad,
                            m.costo_unitario,
                            m.costo_total,
                            m.stock_anterior,
                            m.stock_posterior,
                            m.costo_promedio_anterior,
                            m.costo_promedio_posterior,
                            m.observaciones,
                            m.estado
                        FROM movimientos_inventario m
                        WHERE m.id_inventario = ?
                          AND m.id_tipo_inventario = ?
                          AND m.estado = 'ACTIVO'
                    ";
                    $params = [$idInventario, $TIPO_INVENTARIO_EMP];

                    if ($desde) {
                        $sql .= " AND DATE(m.fecha_movimiento) >= ?";
                        $params[] = $desde;
                    }

                    if ($hasta) {
                        $sql .= " AND DATE(m.fecha_movimiento) <= ?";
                        $params[] = $hasta;
                    }

                    // ✅ ORDEN CRONOLÓGICO PERFECTO
                    $sql .= " ORDER BY m.fecha_movimiento ASC, m.id_movimiento ASC";

                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Formatear movimientos para el frontend
                    $movimientosFormateados = [];
                    foreach ($movimientos as $mov) {
                        $esEntrada = in_array($mov['tipo_movimiento'], [
                            'ENTRADA_COMPRA',
                            'ENTRADA_DEVOLUCION',
                            'ENTRADA_AJUSTE'
                        ]);

                        $cantidad = floatval($mov['cantidad']);
                        $costoUnit = floatval($mov['costo_unitario']);
                        $costoTotal = floatval($mov['costo_total']);
                        $stockAnt = floatval($mov['stock_anterior']);
                        $stockPost = floatval($mov['stock_posterior']);
                        $cppAnt = floatval($mov['costo_promedio_anterior']);
                        $cppPost = floatval($mov['costo_promedio_posterior']);

                        // Calcular valores
                        $valorAnt = $stockAnt * $cppAnt;
                        $valorPost = $stockPost * $cppPost;

                        $movimientosFormateados[] = [
                            'fecha' => $mov['fecha_movimiento'],
                            'documento' => $mov['documento_numero'] ?: $mov['codigo_movimiento'],
                            'tipo' => $mov['tipo_movimiento'],
                            'observaciones' => $mov['observaciones'],

                            // Físico
                            'cantidad_entrada' => $esEntrada ? $cantidad : 0,
                            'cantidad_salida' => !$esEntrada ? $cantidad : 0,
                            'saldo_cantidad' => $stockPost,

                            // Valorado
                            'valor_entrada' => $esEntrada ? $costoTotal : 0,
                            'valor_salida' => !$esEntrada ? $costoTotal : 0,
                            'cpp' => $cppPost,
                            'saldo_valor' => $valorPost,

                            // Costo unitario para referencia
                            'costo_unitario' => $costoUnit,

                            // Saldo anterior
                            'stock_anterior' => $stockAnt,
                            'cpp_anterior' => $cppAnt,
                            'valor_anterior' => $valorAnt
                        ];
                    }

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'producto' => $producto,
                        'saldo_inicial' => $saldoInicial,
                        'movimientos' => $movimientosFormateados,
                        'total_movimientos' => count($movimientosFormateados),
                        'debug' => [
                            'params' => $params,
                            'query' => $sql
                        ]
                    ]);
                    break;

                case 'recalcular':
                    // Función para recalcular y actualizar los valores del kardex
                    $idInventario = $_GET['id_inventario'] ?? null;

                    if (!$idInventario) {
                        echo json_encode(['success' => false, 'message' => 'ID de inventario requerido']);
                        exit();
                    }

                    // Obtener todos los movimientos ordenados cronológicamente
                    $stmt = $db->prepare("
                        SELECT 
                            m.*,
                            dd.costo_unitario as detalle_costo,
                            dd.subtotal as detalle_subtotal
                        FROM movimientos_inventario m
                        LEFT JOIN documentos_inventario_detalle dd ON 
                            m.documento_id = dd.id_documento AND dd.id_inventario = m.id_inventario
                        WHERE m.id_inventario = ?
                          AND m.id_tipo_inventario = ?
                          AND m.estado = 'ACTIVO'
                        ORDER BY m.fecha_movimiento ASC, m.id_movimiento ASC
                    ");
                    $stmt->execute([$idInventario, $TIPO_INVENTARIO_EMP]);
                    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $saldoCantidad = 0;
                    $saldoValor = 0;
                    $cpp = 0;
                    $actualizaciones = 0;

                    $stmtUpdate = $db->prepare("
                        UPDATE movimientos_inventario 
                        SET costo_unitario = ?,
                            costo_total = ?, 
                            stock_anterior = ?,
                            stock_posterior = ?,
                            costo_promedio_anterior = ?,
                            costo_promedio_posterior = ?
                        WHERE id_movimiento = ?
                    ");

                    foreach ($movimientos as $mov) {
                        $esEntrada = in_array($mov['tipo_movimiento'], [
                            'ENTRADA_COMPRA',
                            'ENTRADA_DEVOLUCION',
                            'ENTRADA_AJUSTE'
                        ]);

                        $stockAnt = $saldoCantidad;
                        $cppAnt = $cpp;

                        if ($esEntrada) {
                            // Calcular valor de entrada
                            $valorMov = 0;
                            if ($mov['detalle_subtotal'] > 0) {
                                $valorMov = floatval($mov['detalle_subtotal']);
                            } elseif ($mov['detalle_costo'] > 0) {
                                $valorMov = floatval($mov['cantidad']) * floatval($mov['detalle_costo']);
                            } elseif ($mov['costo_unitario'] > 0) {
                                $valorMov = floatval($mov['cantidad']) * floatval($mov['costo_unitario']);
                            }

                            $costoUnitMov = floatval($mov['cantidad']) > 0 ? $valorMov / floatval($mov['cantidad']) : 0;
                            $saldoCantidad += floatval($mov['cantidad']);
                            $saldoValor += $valorMov;
                        } else {
                            // Si es devolución a proveedor, usar costo registrado. Otros tipos usan CPP.
                            $costoUnitMov = ($mov['tipo_movimiento'] === 'SALIDA_DEVOLUCION')
                                ? floatval($mov['costo_unitario'])
                                : $cpp;

                            $valorMov = floatval($mov['cantidad']) * $costoUnitMov;
                            $saldoCantidad -= floatval($mov['cantidad']);
                            $saldoValor -= $valorMov;
                        }

                        // Recalcular CPP con 4 decimales de precisión
                        if ($saldoCantidad > 0) {
                            $cpp = round($saldoValor / $saldoCantidad, 4);
                        } else {
                            $cpp = 0;
                            $saldoValor = 0;
                        }

                        // Actualizar en BD (todos los campos para mayor consistencia)
                        $stmtUpdate->execute([
                            $costoUnitMov,
                            $valorMov,
                            $stockAnt,
                            $saldoCantidad,
                            $cppAnt,
                            $cpp,
                            $mov['id_movimiento']
                        ]);
                        $actualizaciones++;
                    }

                    // Actualizar el inventario con el CPP final
                    $stmtInv = $db->prepare("
                        UPDATE inventarios 
                        SET costo_promedio = ?, stock_actual = ?
                        WHERE id_inventario = ?
                    ");
                    $stmtInv->execute([$cpp, $saldoCantidad, $idInventario]);

                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => "Kardex recalculado: $actualizaciones registros actualizados",
                        'saldo_final' => $saldoCantidad,
                        'valor_final' => $saldoValor,
                        'cpp_final' => $cpp
                    ]);
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

} catch (PDOException $e) {
    error_log("Error en kardex_emp.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error en kardex_emp.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

ob_end_flush();
exit();
?>