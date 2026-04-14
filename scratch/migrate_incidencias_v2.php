<?php
require_once 'config/database.php';

try {
    $db = getDB();
    echo "Iniciando migración aditiva para gestión de incidencias...\n";

    // 1. Alterar tabla consumos_wip_pendientes
    // Nota: Usamos MODIFY para el ENUM y ADD para las nuevas columnas
    $db->exec("
        ALTER TABLE consumos_wip_pendientes
        MODIFY COLUMN estado ENUM('PENDIENTE', 'EN_REVISION', 'RESUELTA', 'JUSTIFICADA', 'ANULADA', 'REGULARIZADO') DEFAULT 'PENDIENTE',
        ADD COLUMN id_planilla INT(11) NULL AFTER id_lote_wip,
        ADD COLUMN accion_resolucion VARCHAR(50) NULL AFTER estado,
        ADD COLUMN observacion_resolucion TEXT NULL AFTER accion_resolucion,
        ADD COLUMN id_usuario_resolucion INT(11) NULL AFTER observacion_resolucion,
        ADD COLUMN fecha_resolucion DATETIME NULL AFTER id_usuario_resolucion,
        ADD COLUMN fecha_revision DATETIME NULL AFTER fecha_resolucion
    ");

    echo "Tabla 'consumos_wip_pendientes' actualizada exitosamente.\n";

    // 2. Agregar índices para rendimiento
    $db->exec("CREATE INDEX idx_incidencias_planilla ON consumos_wip_pendientes(id_planilla)");
    echo "Índice de trazabilidad creado.\n";

    echo "Migración completada con éxito.\n";

} catch (Exception $e) {
    echo "ERROR EN MIGRACIÓN: " . $e->getMessage() . "\n";
}
