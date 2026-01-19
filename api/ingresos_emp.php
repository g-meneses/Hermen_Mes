<?php
/**
 * API para Gestión de Ingresos - Material de Empaque (EMP)
 * ID de Inventario: 3
 */

require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$TIPO_INVENTARIO_EMP = 3; // ID fijo para Material de Empaque

try {
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $action = $_GET['action'] ?? 'list';

            switch ($action) {
                case 'list':
                    $desde = $_GET['desde'] ?? date('Y-m-01');
                    $hasta = $_GET['hasta'] ?? date('Y-m-t');

                    $stmt = $db->prepare("SELECT 
                                d.id_documento,
                                d.fecha_documento,
                                d.numero_documento,
                                d.tipo_documento,
                                d.estado,
                                d.total_documento,
                                d.creado_por,
                                u.nombre_usuario as usuario,
                                p.nombre_proveedor
                            FROM documentos_inventario d
                            LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor
                            LEFT JOIN usuarios u ON d.creado_por = u.id_usuario
                            WHERE d.tipo_documento = 'INGRESO' 
                            AND d.id_tipo_inventario = ?
                            AND d.fecha_documento BETWEEN ? AND ?
                            ORDER BY d.fecha_documento DESC, d.created_at DESC");
                    $stmt->execute([$TIPO_INVENTARIO_EMP, $desde, $hasta]);
                    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode(['success' => true, 'data' => $documentos]);
                    break;

                case 'siguiente_numero':
                    // REDIRIGIR a API centralizada con modo preview usando include
                    $tipo = $_GET['tipo'] ?? 'COMPRA';

                    // Configurar parámetros para la API centralizada
                    $_GET['tipo_inventario'] = '3';
                    $_GET['operacion'] = 'INGRESO';
                    $_GET['tipo_movimiento'] = $tipo;
                    $_GET['modo'] = 'preview';

                    include 'obtener_siguiente_numero.php';
                    exit();
                    break;

                case 'get':
                    $id = $_GET['id'] ?? null;
                    if (!$id)
                        throw new Exception("ID de documento requerido");

                    // Obtener cabecera
                    $stmt = $db->prepare("SELECT d.*, p.nombre_proveedor, u.nombre_usuario 
                                        FROM documentos_inventario d
                                        LEFT JOIN proveedores p ON d.id_proveedor = p.id_proveedor
                                        LEFT JOIN usuarios u ON d.creado_por = u.id_usuario
                                        WHERE d.id_documento = ? AND d.id_tipo_inventario = ?");
                    $stmt->execute([$id, $TIPO_INVENTARIO_EMP]);
                    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$doc)
                        throw new Exception("Documento no encontrado o no corresponde a EMP");

                    // Obtener líneas usando movimientos_inventario
                    $stmtLines = $db->prepare("SELECT 
                                            m.id_movimiento,
                                            m.id_inventario,
                                            m.cantidad,
                                            m.costo_unitario,
                                            m.costo_total,
                                            i.codigo,
                                            i.nombre,
                                            u.abreviatura as unidad
                                        FROM movimientos_inventario m
                                        JOIN inventario i ON m.id_inventario = i.id_inventario
                                        LEFT JOIN unidades_medida u ON i.id_unidad_medida = u.id_unidad_medida
                                        WHERE m.documento_id = ? AND m.documento_tipo = 'INGRESO' AND m.documento_numero = ?");
                    $stmtLines->execute([$id, $doc['numero_documento']]);
                    $lineas = $stmtLines->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode([
                        'success' => true,
                        'documento' => $doc,
                        'lineas' => $lineas
                    ]);
                    break;

                default:
                    throw new Exception("Acción no válida");
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? 'crear';

            switch ($action) {
                case 'crear':
                    $tipoIngreso = $input['id_tipo_ingreso'] ?? null; // ID del tipo de ingreso (config)
                    // Nota: El frontend manda 'id_tipo_ingreso' como INT, pero usamos códigos para prefijo?
                    // Asumiremos que el frontend manda el CÓDIGO o que tenemos un mapa.
                    // Si el frontend manda ID, necesitamos convertirlo a CÓDIGO (COMPRA, INICIAL, etc)
                    // Para simplificar y dado el request usuario, asumiremos que identificamos el tipo.
                    // En materias_primas.js se usa tiposIngresoConfig que tiene el 'codigo'.
                    // Ajuste: Vamos a requerir el 'codigo_tipo' o deducirlo. 
                    // Si el input trae 'tipo_ingreso' (string) como 'COMPRA', usaremos eso.

                    // Mapeo temporal si viene ID (Esto debería venir del front idealmente, pero...)
                    $tipoCodigo = 'COMPRA'; // Default
                    // Buscar código en base de datos si es necesario, o confiar en un param extra
                    // Por ahora usaremos 'COMPRA' si no se especifica, pero debe ser dinámico.
                    // En ingresos_mp.php vimos $tipoIngreso usado directamente en el switch de prefijos.
                    // Asumiendo que $input['id_tipo_ingreso'] es el CÓDIGO (string) o Texto.

                    // REVISIÓN: En ingresos_mp.php $tipoIngreso venía de ?? null.
                    // Si miramos el JS: datosIngreso.id_tipo_ingreso = tipoId.

                    // ERROR POTENCIAL: id_tipo_ingreso es un INT.
                    // Necesitamos obtener el código ('COMPRA', etc) de la tabla tipos_ingreso_config o similar?
                    // O el frontend debería mandarlo.
                    // Para no romper nada, haremos una consulta rápida del código si es numérico.

                    if (is_numeric($tipoIngreso)) {
                        $stmtTipo = $db->prepare("SELECT codigo FROM tipos_ingreso_config WHERE id_tipo_ingreso = ?");
                        $stmtTipo->execute([$tipoIngreso]);
                        $tipoData = $stmtTipo->fetch(PDO::FETCH_ASSOC);
                        $tipoCodigoString = $tipoData['codigo'] ?? 'OTRO';
                    } else {
                        $tipoCodigoString = $tipoIngreso ?? 'OTRO';
                    }

                    $fecha = $input['fecha'] ?? date('Y-m-d');
                    $observaciones = $input['observaciones'] ?? '';
                    $lineas = $input['lineas'] ?? [];

                    if (empty($lineas))
                        throw new Exception("No hay líneas en el ingreso");

                    $db->beginTransaction();

                    try {
                        // Smart Prefix Logic
                        $codigosTipo = [
                            'COMPRA' => 'C',
                            'INICIAL' => 'I',
                            'DEVOLUCION_PROD' => 'D',
                            'AJUSTE_POS' => 'A'
                        ];
                        $codigoLetra = $codigosTipo[$tipoCodigoString] ?? 'X';
                        $prefijo = "IN-EMP-$codigoLetra";

                        $numeroDoc = generarNumeroDocumento($db, 'INGRESO', $prefijo);

                        // Calcular totales
                        $totalDoc = 0;
                        foreach ($lineas as $l) {
                            $totalDoc += ($l['cantidad'] * $l['costo_unitario']);
                        }

                        // Insertar Documento
                        $stmtDoc = $db->prepare("INSERT INTO documentos_inventario (
                            id_tipo_inventario, tipo_documento, numero_documento, fecha_documento, 
                            observaciones, estado, total_documento, creado_por, created_at,
                            id_proveedor, referencia, con_factura
                        ) VALUES (?, 'INGRESO', ?, ?, ?, 'CONFIRMADO', ?, ?, NOW(), ?, ?, ?)");

                        $idProveedor = $input['id_proveedor'] ?? null;
                        $referencia = $input['referencia'] ?? null;
                        $conFactura = $input['con_factura'] ?? 0;

                        $stmtDoc->execute([
                            $TIPO_INVENTARIO_EMP,
                            $numeroDoc,
                            $fecha,
                            $observaciones,
                            $totalDoc,
                            $_SESSION['user_id'] ?? 1,
                            $idProveedor,
                            $referencia,
                            $conFactura
                        ]);

                        $idDocumento = $db->lastInsertId();

                        // Insertar Movimientos
                        $stmtMov = $db->prepare("INSERT INTO movimientos_inventario (
                            id_inventario, id_tipo_inventario, fecha_movimiento, tipo_movimiento,
                            codigo_movimiento, documento_tipo, documento_numero, documento_id,
                            cantidad, costo_unitario, costo_total,
                            stock_anterior, stock_posterior,
                            costo_promedio_anterior, costo_promedio_posterior,
                            estado, creado_por
                        ) VALUES (?, ?, NOW(), 'ENTRADA_COMPRA', ?, 'INGRESO', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?)");

                        foreach ($lineas as $l) {
                            // Obtener datos actuales del producto (CPP, Stock)
                            $stmtProd = $db->prepare("SELECT stock_actual, costo_promedio FROM inventario WHERE id_inventario = ?");
                            $stmtProd->execute([$l['id_inventario']]);
                            $prod = $stmtProd->fetch(PDO::FETCH_ASSOC);

                            $stockAnt = $prod['stock_actual'] ?? 0;
                            $cppAnt = $prod['costo_promedio'] ?? 0;

                            $cantidad = $l['cantidad'];
                            $costoUnit = $l['costo_unitario'];
                            $costoTotal = $cantidad * $costoUnit;

                            $stockNuevo = $stockAnt + $cantidad;
                            // Nuevo CPP Ponderado
                            $nuevoCpp = (($stockAnt * $cppAnt) + $costoTotal) / $stockNuevo;

                            $codMov = generarCodigoMovimiento($db);

                            $stmtMov->execute([
                                $l['id_inventario'],
                                $TIPO_INVENTARIO_EMP,
                                $codMov,
                                $numeroDoc,
                                $idDocumento,
                                $cantidad,
                                $costoUnit,
                                $costoTotal,
                                $stockAnt,
                                $stockNuevo,
                                $cppAnt,
                                $nuevoCpp,
                                $_SESSION['user_id'] ?? 1
                            ]);

                            // Actualizar Inventario Maestro
                            $stmtUpd = $db->prepare("UPDATE inventario SET stock_actual = ?, costo_promedio = ? WHERE id_inventario = ?");
                            $stmtUpd->execute([$stockNuevo, $nuevoCpp, $l['id_inventario']]);
                        }

                        $db->commit();
                        echo json_encode(['success' => true, 'message' => "Ingreso EMP $numeroDoc registrado"]);

                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;
            }
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Funciones auxiliares copiadas/adaptadas
function generarNumeroDocumento($db, $tipo, $prefijo)
{
    // Lógica idéntica a MP
    $anio = date('Y');
    $mes = date('m');
    $stmt = $db->prepare("SELECT ultimo_numero FROM secuencias_documento WHERE tipo_documento = ? AND prefijo = ? AND anio = ? AND mes = ? FOR UPDATE");
    $stmt->execute([$tipo, $prefijo, $anio, $mes]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $sig = $row['ultimo_numero'] + 1;
        $db->prepare("UPDATE secuencias_documento SET ultimo_numero = ? WHERE tipo_documento = ? AND prefijo = ? AND anio = ? AND mes = ?")->execute([$sig, $tipo, $prefijo, $anio, $mes]);
    } else {
        $sig = 1;
        $db->prepare("INSERT INTO secuencias_documento (tipo_documento, prefijo, anio, mes, ultimo_numero) VALUES (?, ?, ?, ?, 1)")->execute([$tipo, $prefijo, $anio, $mes]);
    }
    return $prefijo . '-' . $anio . $mes . '-' . str_pad($sig, 4, '0', STR_PAD_LEFT);
}

function generarCodigoMovimiento($db)
{
    $fecha = date('Ymd');
    // ... misma lógica ...
    // Simplificado para brevedad, idealmente esto debería estar en un helper compartido, pero por reglas de "sin dependencias ocultas" lo incluyo.
    $stmt = $db->prepare("SELECT ultimo_numero FROM secuencias_documento WHERE tipo_documento = 'MOVIMIENTO' AND prefijo = 'MOV' AND anio = ? AND mes = ? FOR UPDATE");
    $stmt->execute([date('Y'), date('m')]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $sig = $row['ultimo_numero'] + 1;
        $db->prepare("UPDATE secuencias_documento SET ultimo_numero = ? WHERE tipo_documento = 'MOVIMIENTO' AND prefijo = 'MOV' AND anio = ? AND mes = ?")->execute([$sig, 'MOV', date('Y'), date('m')]);
    } else {
        $sig = 1;
        $db->prepare("INSERT INTO secuencias_documento (tipo_documento, prefijo, anio, mes, ultimo_numero) VALUES ('MOVIMIENTO', 'MOV', ?, ?, 1)")->execute([date('Y'), date('m')]);
    }
    return 'MOV-' . $fecha . '-' . str_pad($sig, 4, '0', STR_PAD_LEFT);
}
?>