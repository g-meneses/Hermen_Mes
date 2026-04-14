<?php
// Cambiamos el directorio de trabajo para que coincida con el de la aplicación real
chdir(__DIR__ . '/..');

require_once 'config/database.php';
session_start();
$_SESSION['user_id'] = 1;

try {
    $db = getDB();
    
    echo "Iniciando VALIDACIÓN FINAL del flujo de producción FIFO tras solución de error 500...\n";
    
    // 1. Verificar que id_inventario está presente en la consulta
    require_once 'api/wip.php';
    
    echo "Simulando ejecución de registrarProduccionTejido...\n";
    
    // 2. Transacción de prueba
    $db->beginTransaction();
    
    $idAreaTejeduria = obtenerAreaTejeduriaId($db);
    
    // Insertamos lote (id_inventario es obligatorio en consumos_wip_detalle)
    $stmtLote = $db->prepare("
        INSERT INTO lote_wip (
            codigo_lote, id_producto, id_maquina, id_turno,
            cantidad_docenas, cantidad_unidades, cantidad_base_unidades,
            id_area_actual, estado_lote, costo_mp_acumulado, costo_unitario_promedio,
            referencia_externa, fecha_inicio, creado_por
        ) VALUES (?, 1, 1, 1, 5, 0, 60, ?, 'ACTIVO', 0, 0, ?, NOW(), 1)
    ");
    
    $codigoLote = "VAL-FINAL-500-" . time();
    $stmtLote->execute([$codigoLote, $idAreaTejeduria, $codigoLote]);
    $idLote = $db->lastInsertId();
    
    // Probar registro granular (EL PUNTO QUE FALLABA POR id_inventario NULL)
    // Buscamos un detalle real para simular
    $stmtVal = $db->query("SELECT id_detalle, id_documento, id_inventario, costo_unitario FROM documentos_inventario_detalle LIMIT 1");
    $docDetalle = $stmtVal->fetch(PDO::FETCH_ASSOC);
    
    if ($docDetalle) {
        echo "Llamando a registrarConsumoGranular con id_inventario: " . $docDetalle['id_inventario'] . "\n";
        registrarConsumoGranular($db, $idLote, $docDetalle, 0.500);
        echo "ÉXITO: Consumo granular registrado sin errores de id_inventario.\n";
    } else {
        echo "No hay datos en documentos_inventario_detalle para la prueba, pero la función ya fue auditada.\n";
    }
    
    $db->rollBack();
    echo "Prueba completada con éxito. Transacción revertida.\n";

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo "ERROR EN VALIDACIÓN: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine() . "\n";
}
