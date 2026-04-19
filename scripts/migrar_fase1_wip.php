<?php
/**
 * Script de migración: Fase 1 — Stock Físico WIP
 * Sistema MES Hermen Ltda.
 * 
 * PROPÓSITO:
 *   Poblar wip_stock_mp con el estado actual del sistema (saldo_disponible
 *   de documentos SAL-TEJ confirmados que aún NO han sido integrados al
 *   nuevo modelo).
 *
 * SEGURIDAD:
 *   - Es idempotente: puede ejecutarse múltiples veces sin duplicar datos
 *   - No modifica datos históricos existentes
 *   - No descuenta stock dos veces (filtra integrado_wip_nuevo_modelo = 0)
 *   - Solo crea/actualiza wip_stock_mp, no toca consumos ni lotes
 *
 * USO:
 *   Ejecutar desde el navegador: http://localhost/mes_hermen/scripts/migrar_fase1_wip.php
 *   O desde CLI: php scripts/migrar_fase1_wip.php
 *
 * REQUISITO PREVIO:
 *   Ejecutar scripts/fase1_wip_stock_mp.sql para crear las tablas.
 */

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

// Solo admin puede ejecutar esto
if (php_sapi_name() !== 'cli') {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        die('<h2 style="color:red">Error: Debe estar autenticado para ejecutar este script.</h2>');
    }
    // En producción, agregar verificación de rol admin aquí
}

$db = getDB();

echo '<pre>';
echo "=== MIGRACIÓN FASE 1: Stock Físico WIP ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// PASO 1: Verificar que las tablas existen
// ─────────────────────────────────────────────────────────────────────────────
echo "PASO 1: Verificando existencia de tablas requeridas...\n";

$tablas = ['wip_stock_mp', 'wip_transferencias_mp'];
foreach ($tablas as $tabla) {
    $stmt = $db->query("SHOW TABLES LIKE '$tabla'");
    if (!$stmt->fetchColumn()) {
        die("ERROR: La tabla '$tabla' no existe. Ejecute primero scripts/fase1_wip_stock_mp.sql\n</pre>");
    }
    echo "  ✓ $tabla existe\n";
}

// Verificar columna flag
$stmt = $db->query("
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'documentos_inventario'
      AND COLUMN_NAME  = 'integrado_wip_nuevo_modelo'
");
if (!(int)$stmt->fetchColumn()) {
    die("ERROR: La columna 'integrado_wip_nuevo_modelo' no existe en documentos_inventario. Ejecute primero el SQL de DDL.\n</pre>");
}
echo "  ✓ columna integrado_wip_nuevo_modelo existe\n\n";

// ─────────────────────────────────────────────────────────────────────────────
// PASO 2: Calcular stock actual desde saldo_disponible
// ─────────────────────────────────────────────────────────────────────────────
echo "PASO 2: Calculando saldo_disponible actual de documentos SAL-TEJ legado...\n";

$stmt = $db->query("
    SELECT
        dd.id_inventario,
        i.codigo,
        i.nombre,
        ROUND(SUM(dd.saldo_disponible), 4) AS stock_disponible,
        COUNT(DISTINCT d.id_documento)     AS documentos_origen
    FROM documentos_inventario_detalle dd
    JOIN documentos_inventario d ON d.id_documento = dd.id_documento
    JOIN inventarios i ON i.id_inventario = dd.id_inventario
    WHERE d.tipo_documento = 'SALIDA'
      AND (
          d.tipo_consumo = 'TEJIDO'
          OR d.numero_documento LIKE 'SAL-TEJ%'
      )
      AND d.estado = 'CONFIRMADO'
      AND COALESCE(d.integrado_wip_nuevo_modelo, 0) = 0
      AND dd.saldo_disponible > 0
    GROUP BY dd.id_inventario, i.codigo, i.nombre
    HAVING ROUND(SUM(dd.saldo_disponible), 4) > 0
    ORDER BY i.nombre
");

$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($filas)) {
    echo "  INFO: No se encontraron saldos disponibles en documentos SAL-TEJ legado.\n";
    echo "        Puede que ya estén todos integrados al nuevo modelo, o que no haya documentos SAL-TEJ.\n\n";
} else {
    echo sprintf("  Encontrados %d componentes con saldo:\n", count($filas));
    printf("  %-12s %-40s %12s %10s\n", 'Código', 'Nombre', 'Stock (Kg)', 'Docs');
    echo "  " . str_repeat('-', 80) . "\n";
    $totalKg = 0;
    foreach ($filas as $f) {
        printf("  %-12s %-40s %12.4f %10d\n",
            $f['codigo'], substr($f['nombre'], 0, 40), $f['stock_disponible'], $f['documentos_origen']);
        $totalKg += $f['stock_disponible'];
    }
    echo "  " . str_repeat('-', 80) . "\n";
    printf("  TOTAL: %67.4f Kg\n\n", $totalKg);
}

// ─────────────────────────────────────────────────────────────────────────────
// PASO 3: Confirmar antes de ejecutar
// ─────────────────────────────────────────────────────────────────────────────
if (php_sapi_name() !== 'cli' && !isset($_GET['confirmar'])) {
    echo "PASO 3: Confirmación requerida.\n";
    echo "  Para ejecutar la migración, acceda a:\n";
    echo "  <a href='?confirmar=1'>?confirmar=1</a>\n\n";
    echo "  NOTA: Este paso es idempotente. Puede ejecutarse múltiples veces.\n";
    echo "        Sobreescribe wip_stock_mp con el estado actual (no acumula).\n";
    echo '</pre>';
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// PASO 4: Ejecutar la migración dentro de una transacción
// ─────────────────────────────────────────────────────────────────────────────
echo "PASO 3: Ejecutando migración...\n";

$db->beginTransaction();

try {
    // A. Insertar/actualizar wip_stock_mp para cada componente con saldo legado
    $stmtUpsert = $db->prepare("
        INSERT INTO wip_stock_mp (id_inventario, stock_disponible, stock_reservado)
        VALUES (?, ?, 0.0000)
        ON DUPLICATE KEY UPDATE
            stock_disponible    = VALUES(stock_disponible),
            stock_reservado     = 0.0000,
            fecha_actualizacion = CURRENT_TIMESTAMP
    ");

    $migrados = 0;
    foreach ($filas as $f) {
        $stmtUpsert->execute([$f['id_inventario'], $f['stock_disponible']]);
        echo "  → Migrado: {$f['nombre']} → {$f['stock_disponible']} Kg\n";
        $migrados++;
    }

    // B. Si no había filas, eliminar registros obsoletos con stock 0
    //    (para componentes que ya no tienen saldo en documentos legado)
    $stmtClean = $db->prepare("
        DELETE FROM wip_stock_mp
        WHERE id_inventario NOT IN (
            SELECT dd.id_inventario
            FROM documentos_inventario_detalle dd
            JOIN documentos_inventario d ON d.id_documento = dd.id_documento
            WHERE d.tipo_documento = 'SALIDA'
              AND (d.tipo_consumo = 'TEJIDO' OR d.numero_documento LIKE 'SAL-TEJ%')
              AND d.estado = 'CONFIRMADO'
              AND COALESCE(d.integrado_wip_nuevo_modelo, 0) = 0
              AND dd.saldo_disponible > 0
        )
        AND stock_reservado = 0
        -- Solo limpiar si fue cargado por migración (sin trazabilidad en wip_transferencias_mp)
        AND id_inventario NOT IN (SELECT DISTINCT id_inventario FROM wip_transferencias_mp)
    ");
    $stmtClean->execute();
    $eliminados = $stmtClean->rowCount();

    $db->commit();

    echo "\n  ✓ Migración completada:\n";
    echo "    - Componentes procesados: $migrados\n";
    echo "    - Registros obsoletos eliminados: $eliminados\n\n";

} catch (Throwable $e) {
    $db->rollBack();
    echo "\n  ERROR en migración: " . $e->getMessage() . "\n";
    echo "  Transacción revertida. No se modificaron datos.\n";
    echo '</pre>';
    exit(1);
}

// ─────────────────────────────────────────────────────────────────────────────
// PASO 5: Reporte final de verificación
// ─────────────────────────────────────────────────────────────────────────────
echo "PASO 4: Verificación post-migración:\n\n";

$stmt = $db->query("
    SELECT
        ws.id_inventario,
        i.codigo,
        i.nombre,
        u.abreviatura                         AS unidad,
        ROUND(ws.stock_disponible, 4)         AS stock_disponible_kg,
        ROUND(ws.stock_reservado, 4)          AS stock_reservado_kg,
        ws.fecha_actualizacion
    FROM wip_stock_mp ws
    JOIN inventarios i ON i.id_inventario = ws.id_inventario
    JOIN unidades_medida u ON u.id_unidad = i.id_unidad
    ORDER BY i.nombre
");

$resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($resultado)) {
    echo "  INFO: wip_stock_mp está vacío. No hay stock de planta registrado.\n";
} else {
    printf("  %-12s %-40s %12s %12s %5s\n", 'Código', 'Nombre', 'Disponible', 'Reservado', 'Und');
    echo "  " . str_repeat('-', 88) . "\n";
    $totalDisp = 0;
    foreach ($resultado as $r) {
        printf("  %-12s %-40s %12.4f %12.4f %5s\n",
            $r['codigo'], substr($r['nombre'], 0, 40),
            $r['stock_disponible_kg'], $r['stock_reservado_kg'], $r['unidad']);
        $totalDisp += $r['stock_disponible_kg'];
    }
    echo "  " . str_repeat('-', 88) . "\n";
    printf("  TOTAL STOCK WIP PLANTA: %.4f Kg\n", $totalDisp);
}

echo "\n=== FIN DE MIGRACIÓN ===\n";
echo '</pre>';
