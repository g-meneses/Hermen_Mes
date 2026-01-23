<?php
require_once 'config/database.php';

try {
    $db = getDB();
    $tipoInventarioId = 3; // EMP

    $sql = "
        SELECT 
            m.documento_numero,
            m.documento_tipo,
            m.tipo_movimiento,
            i.id_tipo_inventario,
            t.codigo as tipo_codigo
        FROM movimientos_inventario m
        LEFT JOIN usuarios u ON m.creado_por = u.id_usuario
    ";

    // Replicating logic
    if ($tipoInventarioId) {
        $sql .= " JOIN inventarios i ON m.id_inventario = i.id_inventario ";
        // Also joining tipos_inventario to verify
        $sql .= " JOIN tipos_inventario t ON i.id_tipo_inventario = t.id_tipo_inventario ";
    }

    $sql .= " WHERE 1=1 ";
    $params = [];

    if ($tipoInventarioId) {
        $sql .= " AND i.id_tipo_inventario = ?";
        $params[] = $tipoInventarioId;
    }

    $sql .= " GROUP BY m.documento_numero, m.documento_tipo, m.tipo_movimiento, u.nombre_completo, i.id_tipo_inventario, t.codigo
              ORDER BY MIN(m.fecha_movimiento) DESC
              LIMIT 20";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "=== RESULTADOS SIMULACION (TIPO_ID = 3) ===\n";
    foreach ($rows as $r) {
        echo "Doc: {$r['documento_numero']} | Tipo: {$r['tipo_codigo']} (id: {$r['id_tipo_inventario']})\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
