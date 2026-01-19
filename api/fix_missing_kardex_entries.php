<?php
/**
 * Script para corregir productos con stock inicial pero sin registro en kardex
 * Este script identifica los productos que tienen stock > 0 y no tienen movimientos en kardex_inventario
 * y les genera un movimiento de SALDO_INICIAL.
 */

// Incluir configuración de base de datos
require_once '../config/database.php';

// Aumentar tiempo de ejecución para procesos largos
set_time_limit(300);

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // 1. Ver cuántos productos tienen stock pero no kardex (para reporte)
    $stmtCount = $db->query("
        SELECT COUNT(*) as productos_sin_kardex
        FROM inventarios i
        LEFT JOIN kardex_inventario k ON i.id_inventario = k.id_inventario
        WHERE i.stock_actual > 0 
          AND i.id_tipo_inventario = 2
          AND k.id_kardex IS NULL
    ");
    $countBefore = $stmtCount->fetch(PDO::FETCH_ASSOC)['productos_sin_kardex'];
    
    if ($countBefore > 0) {
        // 2. Insertar los saldos iniciales faltantes
        // Usamos una consulta INSERT ... SELECT para hacerlo masivamente
        $stmtInsert = $db->query("
            INSERT INTO kardex_inventario (
                id_inventario,
                fecha_movimiento,
                tipo_movimiento,
                documento_referencia,
                cantidad,
                costo_unitario,
                costo_total,
                stock_anterior,
                stock_posterior,
                observaciones,
                creado_por
            )
            SELECT 
                i.id_inventario,
                COALESCE(i.fecha_creacion, NOW()),
                'SALDO_INICIAL',
                'SALDO-INICIAL',
                i.stock_actual,
                i.costo_unitario,
                i.stock_actual * i.costo_unitario,
                0,
                i.stock_actual,
                'Saldo inicial - Corrección masiva',
                1
            FROM inventarios i
            LEFT JOIN kardex_inventario k ON i.id_inventario = k.id_inventario
            WHERE i.stock_actual > 0 
              AND i.id_tipo_inventario = 2
              AND k.id_kardex IS NULL
        ");
        
        $filasInsertadas = $stmtInsert->rowCount();
        
        // 3. Confirmar cambios
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Corrección completada exitosamente',
            'productos_encontrados' => $countBefore,
            'registros_generados' => $filasInsertadas
        ]);
        
    } else {
        $db->commit();
        echo json_encode([
            'success' => true,
            'message' => 'No se encontraron productos pendientes de corrección',
            'productos_encontrados' => 0
        ]);
    }

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error al ejecutar la corrección: ' . $e->getMessage()
    ]);
}
