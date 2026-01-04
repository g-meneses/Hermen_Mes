<?php
/**
 * API de Kardex de Materias Primas
 * Sistema MES Hermen Ltda.
 * Versión: 2.1 - Corrección de valores monetarios
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
                        WHERE i.id_inventario = ?
                    ");
                    $stmtProd->execute([$idInventario]);
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
                                COALESCE(SUM(CASE 
                                    WHEN k.tipo_movimiento IN ('ENTRADA', 'AJUSTE_POSITIVO', 'INGRESO') THEN k.cantidad
                                    WHEN k.tipo_movimiento IN ('SALIDA', 'AJUSTE_NEGATIVO') THEN -k.cantidad
                                    ELSE 0
                                END), 0) as cantidad,
                                COALESCE(SUM(CASE 
                                    WHEN k.tipo_movimiento IN ('ENTRADA', 'AJUSTE_POSITIVO', 'INGRESO') THEN 
                                        COALESCE(k.costo_total, k.cantidad * k.costo_unitario)
                                    WHEN k.tipo_movimiento IN ('SALIDA', 'AJUSTE_NEGATIVO') THEN 
                                        -COALESCE(k.costo_total, k.cantidad * k.costo_unitario)
                                    ELSE 0
                                END), 0) as valor_total
                            FROM kardex_inventario k
                            WHERE k.id_inventario = ? 
                              AND DATE(k.fecha_movimiento) < ?
                        ");
                        $stmtSaldo->execute([$idInventario, $desde]);
                        $saldoInicial = $stmtSaldo->fetch(PDO::FETCH_ASSOC);

                        if ($saldoInicial) {
                            $saldoInicial['cantidad'] = floatval($saldoInicial['cantidad']);
                            $saldoInicial['valor_total'] = floatval($saldoInicial['valor_total']);
                            $saldoInicial['cpp'] = $saldoInicial['cantidad'] > 0 ?
                                $saldoInicial['valor_total'] / $saldoInicial['cantidad'] : 0;
                        } else {
                            $saldoInicial = [
                                'cantidad' => 0,
                                'valor_total' => 0,
                                'cpp' => 0
                            ];
                        }
                    }

                    // Construir query de movimientos con JOIN para obtener más información
                    $sql = "
                        SELECT 
                            k.id_kardex,
                            k.fecha_movimiento,
                            k.tipo_movimiento,
                            k.documento_referencia,
                            k.cantidad,
                            k.costo_unitario,
                            k.costo_total,
                            k.stock_anterior,
                            k.stock_posterior,
                            k.costo_promedio_anterior,
                            k.costo_promedio_posterior,
                            k.observaciones,
                            d.numero_documento,
                            d.tipo_documento,
                            d.subtotal as doc_subtotal,
                            d.total as doc_total,
                            dd.costo_unitario as detalle_costo_unitario,
                            dd.subtotal as detalle_subtotal
                        FROM kardex_inventario k
                        LEFT JOIN documentos_inventario d ON k.id_documento = d.id_documento
                        LEFT JOIN documentos_inventario_detalle dd ON 
                            d.id_documento = dd.id_documento AND dd.id_inventario = k.id_inventario
                        WHERE k.id_inventario = ?
                    ";
                    $params = [$idInventario];

                    if ($desde) {
                        $sql .= " AND k.fecha_movimiento >= ?";
                        $params[] = $desde;
                    }

                    if ($hasta) {
                        $sql .= " AND k.fecha_movimiento <= ?";
                        $params[] = $hasta . ' 23:59:59';
                    }

                    $sql .= " ORDER BY k.fecha_movimiento ASC, k.id_kardex ASC";

                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Inicializar saldos acumulados
                    $saldoCantidad = $saldoInicial ? $saldoInicial['cantidad'] : 0;
                    $saldoValor = $saldoInicial ? $saldoInicial['valor_total'] : 0;
                    $cpp = $saldoInicial ? $saldoInicial['cpp'] : 0;

                    // Formatear movimientos para el frontend
                    $movimientosFormateados = [];
                    foreach ($movimientos as $mov) {
                        $esEntrada = in_array($mov['tipo_movimiento'], ['ENTRADA', 'AJUSTE_POSITIVO', 'INGRESO']);

                        // Calcular el valor del movimiento
                        $valorMovimiento = 0;
                        $costoUnitarioMov = 0;

                        if ($esEntrada) {
                            // Para entradas, usar el costo del documento o calcular
                            if ($mov['costo_total'] > 0) {
                                $valorMovimiento = floatval($mov['costo_total']);
                            } elseif ($mov['detalle_subtotal'] > 0) {
                                $valorMovimiento = floatval($mov['detalle_subtotal']);
                            } elseif ($mov['costo_unitario'] > 0) {
                                $valorMovimiento = floatval($mov['cantidad']) * floatval($mov['costo_unitario']);
                            } elseif ($mov['detalle_costo_unitario'] > 0) {
                                $valorMovimiento = floatval($mov['cantidad']) * floatval($mov['detalle_costo_unitario']);
                            }

                            $costoUnitarioMov = $mov['cantidad'] > 0 ? $valorMovimiento / $mov['cantidad'] : 0;

                            // Actualizar saldos
                            $saldoCantidad += floatval($mov['cantidad']);
                            $saldoValor += $valorMovimiento;

                        } else {
                            // Para salidas, usar el CPP actual
                            $costoUnitarioMov = $cpp;
                            $valorMovimiento = floatval($mov['cantidad']) * $costoUnitarioMov;

                            // Actualizar saldos
                            $saldoCantidad -= floatval($mov['cantidad']);
                            $saldoValor -= $valorMovimiento;
                        }

                        // Recalcular CPP después del movimiento
                        if ($saldoCantidad > 0) {
                            $cpp = $saldoValor / $saldoCantidad;
                        } elseif ($saldoCantidad == 0) {
                            $cpp = 0;
                            $saldoValor = 0; // Ajustar a 0 si no hay stock
                        }

                        // Verificar si el CPP del kardex está guardado y es diferente
                        if ($mov['costo_promedio_posterior'] > 0 && abs($mov['costo_promedio_posterior'] - $cpp) > 0.01) {
                            // Si hay una diferencia significativa, usar el valor guardado
                            $cpp = floatval($mov['costo_promedio_posterior']);
                            $saldoValor = $saldoCantidad * $cpp;
                        }

                        $movimientosFormateados[] = [
                            'fecha' => $mov['fecha_movimiento'],
                            'documento' => $mov['numero_documento'] ?: $mov['documento_referencia'],
                            'tipo' => $mov['tipo_movimiento'],
                            'observaciones' => $mov['observaciones'],

                            // Físico
                            'cantidad_entrada' => $esEntrada ? floatval($mov['cantidad']) : 0,
                            'cantidad_salida' => !$esEntrada ? floatval($mov['cantidad']) : 0,
                            'saldo_cantidad' => $saldoCantidad,

                            // Valorado
                            'valor_entrada' => $esEntrada ? $valorMovimiento : 0,
                            'valor_salida' => !$esEntrada ? $valorMovimiento : 0,
                            'cpp' => $cpp,
                            'saldo_valor' => $saldoValor,

                            // Costo unitario para referencia
                            'costo_unitario' => $costoUnitarioMov
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
                            'saldo_final_cantidad' => $saldoCantidad,
                            'saldo_final_valor' => $saldoValor,
                            'cpp_final' => $cpp
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

                    // Obtener todos los movimientos ordenados
                    $stmt = $db->prepare("
                        SELECT 
                            k.*,
                            dd.costo_unitario as detalle_costo,
                            dd.subtotal as detalle_subtotal
                        FROM kardex_inventario k
                        LEFT JOIN documentos_inventario_detalle dd ON 
                            k.id_documento = dd.id_documento AND dd.id_inventario = k.id_inventario
                        WHERE k.id_inventario = ?
                        ORDER BY k.fecha_movimiento, k.id_kardex
                    ");
                    $stmt->execute([$idInventario]);
                    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $saldoCantidad = 0;
                    $saldoValor = 0;
                    $cpp = 0;
                    $actualizaciones = 0;

                    $stmtUpdate = $db->prepare("
                        UPDATE kardex_inventario 
                        SET costo_total = ?, 
                            costo_promedio_posterior = ?,
                            stock_posterior = ?
                        WHERE id_kardex = ?
                    ");

                    foreach ($movimientos as $mov) {
                        $esEntrada = in_array($mov['tipo_movimiento'], ['ENTRADA', 'AJUSTE_POSITIVO', 'INGRESO']);

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

                            $saldoCantidad += floatval($mov['cantidad']);
                            $saldoValor += $valorMov;
                        } else {
                            // Para salidas usar CPP
                            $valorMov = floatval($mov['cantidad']) * $cpp;
                            $saldoCantidad -= floatval($mov['cantidad']);
                            $saldoValor -= $valorMov;
                        }

                        // Recalcular CPP
                        if ($saldoCantidad > 0) {
                            $cpp = $saldoValor / $saldoCantidad;
                        } else {
                            $cpp = 0;
                            $saldoValor = 0;
                        }

                        // Actualizar en BD
                        $stmtUpdate->execute([$valorMov, $cpp, $saldoCantidad, $mov['id_kardex']]);
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
    error_log("Error en kardex_mp.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error en kardex_mp.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

ob_end_flush();
exit();
?>