<?php
require_once 'config/database.php';
session_start();
$_SESSION['user_id'] = 1;

try {
    $db = getDB();
    
    echo "Iniciando prueba de inserción de lote con id_documento_consumo = NULL...\n";
    
    // Datos de prueba mínimos
    $datos = [
        'id_maquina' => 1,
        'fecha_produccion' => date('Y-m-d'),
        'id_producto' => 1,
        'id_turno' => 1,
        'cantidad_docenas' => 10,
        'cantidad_unidades' => 0,
        'cantidad_base_unidades' => 120,
        'id_area_actual' => 1,
        'costo_mp_acumulado' => 100.50,
        'costo_unitario_promedio' => 0.8375,
        'id_documento_salida' => 0 // Esto provocará que se envíe NULL al INSERT
    ];
    
    // No podemos llamar directamente a insertarLoteProduccionTejido si no incluimos wip.php
    // Pero wip.php tiene header('Content-Type: application/json') y ob_start(), mejor lo incluimos
    // con cuidado o simplemente replicamos el INSERT aquí para validar la base de datos.
    
    $codigoLote = "TEST-FIFO-" . time();
    $stmt = $db->prepare("
        INSERT INTO lote_wip (
            codigo_lote, id_producto, id_maquina, id_turno, id_linea_produccion,
            cantidad_docenas, cantidad_unidades, cantidad_base_unidades,
            id_area_actual, estado_lote, costo_mp_acumulado, costo_unitario_promedio,
            id_documento_consumo, id_documento_salida, referencia_externa, fecha_inicio, creado_por
        ) VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, 'ACTIVO', ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $idDocumento = null;
    $res = $stmt->execute([
        $codigoLote,
        $datos['id_producto'],
        $datos['id_maquina'],
        $datos['id_turno'],
        $datos['cantidad_docenas'],
        $datos['cantidad_unidades'],
        $datos['cantidad_base_unidades'],
        $datos['id_area_actual'],
        $datos['costo_mp_acumulado'],
        $datos['costo_unitario_promedio'],
        $idDocumento,
        $idDocumento,
        $codigoLote,
        $datos['fecha_produccion'] . ' 00:00:00',
        $_SESSION['user_id']
    ]);
    
    if ($res) {
        $idNuevo = $db->lastInsertId();
        echo "ÉXITO: Lote insertado correctamente con ID: $idNuevo\n";
        
        // Limpieza de prueba
        $db->exec("DELETE FROM lote_wip WHERE id_lote_wip = $idNuevo");
        echo "Lote de prueba eliminado.\n";
    } else {
        echo "FALLO: No se pudo insertar el lote.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
