<?php
/**
 * API para Gestión de Aprobaciones de Ajustes de Inventario
 * Sistema MES Hermen Ltda.
 */
require_once '../config/database.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'list':
                // Listar solicitudes de ajuste
                $estado = $_GET['estado'] ?? 'PENDIENTE';
                
                $sql = "
                    SELECT 
                        a.id_ajuste,
                        a.codigo_ajuste,
                        a.tipo_ajuste,
                        a.estado,
                        a.motivo,
                        a.fecha_solicitud,
                        a.fecha_aprobacion,
                        u1.nombre_completo as solicitante_nombre,
                        u2.nombre_completo as aprobador_nombre
                    FROM ajustes_inventario a
                    LEFT JOIN usuarios u1 ON a.id_solicitante = u1.id_usuario
                    LEFT JOIN usuarios u2 ON a.id_aprobador = u2.id_usuario
                    WHERE 1=1
                ";
                
                $params = [];
                if ($estado !== 'TODOS') {
                    $sql .= " AND a.estado = ?";
                    $params[] = $estado;
                }
                
                $sql .= " ORDER BY a.fecha_solicitud DESC";
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $ajustes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'ajustes' => $ajustes
                ]);
                break;
                
            case 'count_pendientes':
                $stmt = $db->prepare("SELECT COUNT(*) FROM ajustes_inventario WHERE estado = 'PENDIENTE'");
                $stmt->execute();
                $total = $stmt->fetchColumn();
                echo json_encode(['success' => true, 'total' => (int)$total]);
                break;
                
            case 'get':
                // Obtener detalle de una solicitud
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    echo json_encode(['success' => false, 'message' => 'ID requerido']);
                    exit();
                }
                
                // Cabecera
                $stmt = $db->prepare("
                    SELECT 
                        a.*,
                        u1.nombre_completo as solicitante_nombre
                    FROM ajustes_inventario a
                    LEFT JOIN usuarios u1 ON a.id_solicitante = u1.id_usuario
                    WHERE a.id_ajuste = ?
                ");
                $stmt->execute([$id]);
                $ajuste = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$ajuste) {
                    echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
                    exit();
                }
                
                // Detalle
                $stmtDet = $db->prepare("
                    SELECT 
                        d.*,
                        i.codigo as producto_codigo,
                        i.nombre as producto_nombre,
                        um.abreviatura as unidad,
                        ti.nombre as tipo_inventario_nombre
                    FROM ajustes_inventario_detalle d
                    JOIN inventarios i ON d.id_inventario = i.id_inventario
                    LEFT JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                    LEFT JOIN tipos_inventario ti ON i.id_tipo_inventario = ti.id_tipo_inventario
                    WHERE d.id_ajuste = ?
                ");
                $stmtDet->execute([$id]);
                $detalle = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'ajuste' => $ajuste,
                    'detalle' => $detalle
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';
        
        if ($action === 'procesar') {
            $id = $data['id_ajuste'] ?? null;
            $decision = $data['decision'] ?? null; // 'APROBAR' o 'RECHAZAR'
            $observaciones = $data['observaciones'] ?? '';
            
            $autorizadoPorId = $data['autorizado_por'] ?? null;
            $costosDetalle = $data['detalles_costo'] ?? [];

            if (!$id || !in_array($decision, ['APROBAR', 'RECHAZAR'])) {
                echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos']);
                exit();
            }
            
            error_log("Procesando ajuste ID $id - Decisión: $decision");
            
            $db->beginTransaction();
            
            try {
                // Verificar estado actual
                $stmtCheck = $db->prepare("SELECT * FROM ajustes_inventario WHERE id_ajuste = ? FOR UPDATE");
                $stmtCheck->execute([$id]);
                $ajuste = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                
                if (!$ajuste || $ajuste['estado'] !== 'PENDIENTE') {
                    throw new Exception('La solicitud ya fue procesada o no existe.');
                }
                
                $idUsuario = $_SESSION['user_id'] ?? null;
                $estadoFinal = ($decision === 'APROBAR') ? 'APROBADO' : 'RECHAZADO';

                if ($autorizadoPorId) {
                    $stmtUsu = $db->prepare("SELECT nombre_completo FROM usuarios WHERE id_usuario = ?");
                    $stmtUsu->execute([$autorizadoPorId]);
                    $usu = $stmtUsu->fetch(PDO::FETCH_ASSOC);
                    if ($usu) {
                        $nombreAutorizador = trim($usu['nombre_completo']);
                        $observaciones = "(Autorizado por: " . $nombreAutorizador . ")\n" . $observaciones;
                    }
                }
                
                // Actualizar cabecera
                $stmtUpdate = $db->prepare("
                    UPDATE ajustes_inventario 
                    SET estado = ?, observaciones_aprobador = ?, id_aprobador = ?, fecha_aprobacion = NOW()
                    WHERE id_ajuste = ?
                ");
                $stmtUpdate->execute([$estadoFinal, $observaciones, $idUsuario, $id]);
                
                if ($decision === 'APROBAR') {
                    // Si se aprueba, EJECUTAR LOS MOVIMIENTOS EN INVENTARIO
                    
                    // Obtener líneas
                    $stmtLineas = $db->prepare("SELECT * FROM ajustes_inventario_detalle WHERE id_ajuste = ?");
                    $stmtLineas->execute([$id]);
                    $lineas = $stmtLineas->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($lineas)) {
                        throw new Exception('La solicitud no tiene productos en el detalle.');
                    }
                    
                    // Determinar parámetros base según si es Entada (Positivo) o Salida (Negativo)
                    $tipoDocMain = ($ajuste['tipo_ajuste'] === 'ENTRADA') ? 'INGRESO' : 'SALIDA';
                    $tipoOperacion = ($ajuste['tipo_ajuste'] === 'ENTRADA') ? 'AJUSTE_POS' : 'AJUSTE';
                    $prefijox = ($ajuste['tipo_ajuste'] === 'ENTRADA') ? 'AJ-POS' : 'AJ-NEG'; // Prefix
                    $tipoDocTexto = ($ajuste['tipo_ajuste'] === 'ENTRADA') ? 'AJUSTE_POS' : 'AJUSTE_NEG';

                    // Variables para totalizar
                    $totalGeneral = 0;
                    
                    // Obtener primer ID de tipo_inventario de la tabla inventarios (para simplificar, se asume que todos los items de este ajuste van al mismo almacén, usualmente es así)
                    $stmtTipoInv = $db->prepare("SELECT id_tipo_inventario FROM inventarios WHERE id_inventario = ?");
                    $stmtTipoInv->execute([$lineas[0]['id_inventario']]);
                    $idTipoInvAprox = $stmtTipoInv->fetchColumn();
                    $idTipoInvMain = $idTipoInvAprox ?: 1; // Default to MP
                    
                    $codigosInventarioSuffix = [
                        1 => 'MP',
                        2 => 'CAQ',
                        3 => 'EMP',
                        4 => 'ACC'
                    ];
                    $suffixInvText = $codigosInventarioSuffix[$idTipoInvMain] ?? 'GEN';
                    $prefixMainDoc = ($ajuste['tipo_ajuste'] === 'ENTRADA' ? 'IN' : 'OUT') . "-$suffixInvText-A";
                    
                    // 1. Crear Documento Principal en documentos_inventario
                    $numeroDocumentoReal = $ajuste['codigo_ajuste']; // Reutilizamos el código del ajuste
                    
                    $stmtDocMain = $db->prepare("
                        INSERT INTO documentos_inventario (
                            tipo_documento, 
                            " . ($ajuste['tipo_ajuste'] === 'ENTRADA' ? 'tipo_ingreso' : 'tipo_salida') . ",
                            numero_documento, fecha_documento, id_tipo_inventario, 
                            referencia_externa, con_factura, subtotal, total, observaciones, estado, creado_por
                        ) VALUES (?, ?, ?, NOW(), ?, ?, 0, ?, ?, ?, 'CONFIRMADO', ?)
                    ");
                    
                    // Se requieren los subtotales para el documento = cantidad * costo actual
                    // Pero para Salidas, necesitamos el costo promedio actual just-in-time
                    
                    foreach ($lineas as $idx => $linea) {
                        $cantidad = floatval($linea['cantidad_solicitada']);
                        
                        // Obtener stock y costo del item AHORA MISMO
                        $stmtStk = $db->prepare("SELECT stock_actual, costo_promedio, id_tipo_inventario FROM inventarios WHERE id_inventario = ? FOR UPDATE");
                        $stmtStk->execute([$linea['id_inventario']]);
                        $stkInfo = $stmtStk->fetch(PDO::FETCH_ASSOC);
                        
                        $stockActualItem = floatval($stkInfo['stock_actual']);
                        $cppActualItem = floatval($stkInfo['costo_promedio']);
                        $idTipoInvent = $stkInfo['id_tipo_inventario'];
                        
                        // Validar stock si es salida
                        if ($ajuste['tipo_ajuste'] === 'SALIDA' && $stockActualItem < $cantidad) {
                            throw new Exception("No hay stock suficiente para aprobar la salida del producto ID {$linea['id_inventario']}. Disponible: $stockActualItem");
                        }
                        
                        if ($ajuste['tipo_ajuste'] === 'ENTRADA') {
                            $costoUnitarioUsar = floatval($linea['costo_unitario_guardado']);
                            // Buscar si hay un costo actualizado en el payload
                            foreach ($costosDetalle as $c) {
                                if ($c['id_detalle'] == $linea['id_detalle']) {
                                    $costoUnitarioUsar = floatval($c['costo_unitario']);
                                    // Actualizar el detalle con el nuevo costo
                                    $stmtUpdDet = $db->prepare("UPDATE ajustes_inventario_detalle SET costo_unitario_guardado = ? WHERE id_detalle = ?");
                                    $stmtUpdDet->execute([$costoUnitarioUsar, $linea['id_detalle']]);
                                    break;
                                }
                            }
                        } else {
                            $costoUnitarioUsar = $cppActualItem;
                        }

                        $subtotLinea = $cantidad * $costoUnitarioUsar;
                        $totalGeneral += $subtotLinea;
                        
                        // Guardar para el paso de líneas (evitamos segunda consulta for update)
                        $lineas[$idx]['_stock_actual_lock'] = $stockActualItem;
                        $lineas[$idx]['_cpp_actual_lock'] = $cppActualItem;
                        $lineas[$idx]['_costo_unitario_usar'] = $costoUnitarioUsar;
                        $lineas[$idx]['_subtotal_linea'] = $subtotLinea;
                        $lineas[$idx]['_id_tipo_inventario'] = $idTipoInvent;
                    }
                    
                    // Ahora sí, ejecutamos el INsert del master Doc
                    $refFinal = 'Cod Aj: ' . $ajuste['codigo_ajuste'] . ' | Apr por: ' . $_SESSION['nombre_completo'];
                    $obsFinal = $ajuste['motivo'] . ' | ' . $observaciones;
                    
                    $stmtDocMain->execute([
                        $tipoDocMain,
                        $tipoOperacion,
                        $numeroDocumentoReal,
                        $idTipoInvMain,
                        $refFinal,
                        $totalGeneral,
                        $totalGeneral,
                        $obsFinal,
                        $ajuste['id_solicitante']
                    ]);
                    
                    $idDocumentoReal = $db->lastInsertId();
                    
                    // Preparamos consultas compartidas
                    $stmtLineaDoc = $db->prepare("
                        INSERT INTO documentos_inventario_detalle (
                            id_documento, id_inventario, cantidad, costo_unitario, subtotal
                        ) VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    $stmtUpdateStock = $db->prepare("
                        UPDATE inventarios 
                        SET stock_actual = ?, costo_promedio = ?, costo_unitario = ?
                        WHERE id_inventario = ?
                    ");
                    
                    $stmtMovimientoReal = $db->prepare("
                        INSERT INTO movimientos_inventario (
                            id_inventario, id_tipo_inventario, fecha_movimiento, tipo_movimiento,
                            codigo_movimiento, documento_tipo, documento_numero, documento_id,
                            cantidad, costo_unitario, costo_total,
                            stock_anterior, stock_posterior,
                            costo_promedio_anterior, costo_promedio_posterior,
                            estado, creado_por
                        ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?)
                    ");
                    
                    // Generar secuencia MOV
                    $fechaBase = date('Ymd');
                    $stmtSecMov = $db->prepare("SELECT ultimo_numero FROM secuencias_documento WHERE tipo_documento = 'MOVIMIENTO' AND prefijo = 'MOV' AND anio = ? AND mes = ? FOR UPDATE");
                    $stmtSecMov->execute([date('Y'), date('m')]);
                    $rowSec = $stmtSecMov->fetch(PDO::FETCH_ASSOC);
                    
                    $secuenciaGlobalMov = $rowSec ? $rowSec['ultimo_numero'] : 0;
                    
                    if ($rowSec) {
                        $stmtUpSec = $db->prepare("UPDATE secuencias_documento SET ultimo_numero = ultimo_numero + ? WHERE tipo_documento = 'MOVIMIENTO' AND prefijo = 'MOV' AND anio = ? AND mes = ?");
                        $stmtUpSec->execute([count($lineas), date('Y'), date('m')]);
                    } else {
                        $secuenciaGlobalMov = 0;
                        $stmtInSec = $db->prepare("INSERT INTO secuencias_documento (tipo_documento, prefijo, anio, mes, ultimo_numero) VALUES ('MOVIMIENTO', 'MOV', ?, ?, ?)");
                        $stmtInSec->execute([date('Y'), date('m'), count($lineas)]);
                    }
                    
                    // Insertar detalle, kardex y movimientos
                    foreach ($lineas as $idx => $linea) {
                        $cantidad = floatval($linea['cantidad_solicitada']);
                        $stockAnt = floatval($linea['_stock_actual_lock']);
                        $cppAnt = floatval($linea['_cpp_actual_lock']);
                        $costoUnit = floatval($linea['_costo_unitario_usar']);
                        $subtotLn = floatval($linea['_subtotal_linea']);
                        $idTipoInvLinea = $linea['_id_tipo_inventario'];
                        
                        // Nuevo Stock y CPP
                        if ($ajuste['tipo_ajuste'] === 'ENTRADA') {
                            $stockNuevo = $stockAnt + $cantidad;
                            if ($stockAnt == 0) {
                                $cppNuevo = $costoUnit;
                            } else {
                                $cppNuevo = (($stockAnt * $cppAnt) + ($cantidad * $costoUnit)) / $stockNuevo;
                            }
                            $cppNuevo = round($cppNuevo, 4);
                            $tipoMovInv = 'ENTRADA_AJUSTE';
                        } else {
                            $stockNuevo = $stockAnt - $cantidad;
                            $cppNuevo = $cppAnt; // El CPP de salida no cambia el promedio general
                            $tipoMovInv = 'SALIDA_AJUSTE';
                        }
                        
                        // 1. Modificar Stock en Tabla Inventarios
                        $stmtUpdateStock->execute([
                            $stockNuevo,
                            $cppNuevo,
                            $costoUnit, // Se asume costo último = unitario
                            $linea['id_inventario']
                        ]);
                        
                        // 2. Insertar Detalle Documento
                        $stmtLineaDoc->execute([
                            $idDocumentoReal,
                            $linea['id_inventario'],
                            $cantidad,
                            $costoUnit,
                            $subtotLn
                        ]);
                        
                        // 3. Generar Código MOV
                        $secuenciaGlobalMov++;
                        $codigoMovLn = 'MOV-' . $fechaBase . '-' . str_pad($secuenciaGlobalMov, 4, '0', STR_PAD_LEFT);
                        
                        // 4. Escribir Movimiento en Kardex de Auditoría
                        $docTextToKardex = ($ajuste['tipo_ajuste'] === 'ENTRADA') ? 'AJUSTE POSITIVO' : 'AJUSTE NEGATIVO';
                        $stmtMovimientoReal->execute([
                            $linea['id_inventario'],
                            $idTipoInvLinea,
                            $tipoMovInv,
                            $codigoMovLn,
                            $docTextToKardex,
                            $numeroDocumentoReal,
                            $idDocumentoReal,
                            $cantidad,
                            $costoUnit,
                            $subtotLn,
                            $stockAnt,
                            $stockNuevo,
                            $cppAnt,
                            $cppNuevo,
                            $ajuste['id_solicitante'] // Quien lo creó originalmente (o el aprobador: $_SESSION['user_id'])
                        ]);
                    }
                    
                } // Fin de la Lógica de Aprobación
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => ($decision === 'APROBAR') 
                        ? 'Solicitud Aprobada y Kardex Actualizado Correctamente.' 
                        : 'Solicitud Rechazada. Los stocks no han sido afectados.'
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error Procesando Ajuste: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
             echo json_encode(['success' => false, 'message' => 'Acción POST no válida']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Método HTTP no soportado']);
}
?>
