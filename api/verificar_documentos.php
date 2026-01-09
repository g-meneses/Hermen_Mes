<?php
/**
 * Script de Verificación y Corrección de Documentos
 * Sistema MES Hermen Ltda.
 */

require_once '../config/database.php';

if (!isLoggedIn()) {
    die('No autorizado');
}

$db = getDB();
$idInventario = $_GET['id'] ?? 1;

echo "<!DOCTYPE html>
<html>
<head>
    <title>Verificación de Documentos - Producto ID: $idInventario</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #2196F3; color: white; }
        .error { background: #ffcdd2; }
        .success { background: #c8e6c9; }
        .warning { background: #fff9c4; }
        .info { background: #e1f5fe; padding: 10px; margin: 10px 0; border-radius: 5px; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 4px; }
        button:hover { background: #45a049; }
    </style>
</head>
<body>
<h1>Verificación de Documentos de Ingreso</h1>";

// 1. Verificar documentos de ingreso
echo "<div class='info'><h2>1. Documentos de Ingreso del Producto</h2>";

$stmt = $db->prepare("
    SELECT 
        d.id_documento,
        d.numero_documento,
        d.fecha_documento,
        d.subtotal as doc_subtotal,
        d.iva as doc_iva,
        d.total as doc_total,
        d.con_factura,
        dd.id_detalle,
        dd.id_inventario,
        dd.cantidad,
        dd.costo_unitario,
        dd.costo_con_iva,
        dd.subtotal as linea_subtotal
    FROM documentos_inventario d
    LEFT JOIN documentos_inventario_detalle dd ON d.id_documento = dd.id_documento 
        AND dd.id_inventario = ?
    WHERE d.tipo_documento = 'INGRESO'
        AND d.numero_documento IN (
            SELECT DISTINCT documento_referencia 
            FROM kardex_inventario 
            WHERE id_inventario = ? 
            AND tipo_movimiento = 'ENTRADA'
        )
    ORDER BY d.fecha_documento DESC
");

$stmt->execute([$idInventario, $idInventario]);
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Total de documentos encontrados: " . count($documentos) . "</p>";

echo "<table>";
echo "<tr>
    <th>ID Doc</th>
    <th>Número</th>
    <th>Fecha</th>
    <th>Con Fact</th>
    <th>Doc Total</th>
    <th>ID Detalle</th>
    <th>Cantidad</th>
    <th>Costo Unit</th>
    <th>Costo c/IVA</th>
    <th>Subtotal</th>
    <th>Estado</th>
</tr>";

$documentosSinDetalle = [];
foreach ($documentos as $doc) {
    $tieneDetalle = !empty($doc['id_detalle']);
    $clase = $tieneDetalle ? 'success' : 'error';

    if (!$tieneDetalle) {
        $documentosSinDetalle[] = $doc;
    }

    echo "<tr class='$clase'>";
    echo "<td>{$doc['id_documento']}</td>";
    echo "<td>{$doc['numero_documento']}</td>";
    echo "<td>" . substr($doc['fecha_documento'], 0, 10) . "</td>";
    echo "<td>" . ($doc['con_factura'] ? 'Sí' : 'No') . "</td>";
    echo "<td>" . number_format($doc['doc_total'], 2) . "</td>";
    echo "<td>" . ($doc['id_detalle'] ?: '<span style="color:red;">FALTA</span>') . "</td>";
    echo "<td>" . number_format($doc['cantidad'], 2) . "</td>";
    echo "<td>" . number_format($doc['costo_unitario'], 2) . "</td>";
    echo "<td>" . number_format($doc['costo_con_iva'], 2) . "</td>";
    echo "<td>" . number_format($doc['linea_subtotal'], 2) . "</td>";
    echo "<td>" . ($tieneDetalle ? '✓ OK' : '❌ Sin detalle') . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// 2. Si hay documentos sin detalle, ofrecer inserción manual
if (count($documentosSinDetalle) > 0) {
    echo "<div class='info'><h2>2. Documentos Sin Detalle (Problema Detectado)</h2>";
    echo "<p class='error'>Se encontraron " . count($documentosSinDetalle) . " documentos sin líneas de detalle.</p>";

    // Obtener información del producto
    $stmtProd = $db->prepare("SELECT * FROM inventarios WHERE id_inventario = ?");
    $stmtProd->execute([$idInventario]);
    $producto = $stmtProd->fetch(PDO::FETCH_ASSOC);

    echo "<h3>Datos del Producto:</h3>";
    echo "<ul>";
    echo "<li>Código: {$producto['codigo']}</li>";
    echo "<li>Nombre: {$producto['nombre']}</li>";
    echo "<li>Costo Promedio Actual: " . number_format($producto['costo_promedio'], 4) . "</li>";
    echo "</ul>";

    echo "<h3>Opciones de Corrección:</h3>";
    echo "<form method='POST'>";
    echo "<table>";
    echo "<tr><th>Documento</th><th>Total Doc</th><th>Cantidad en Kardex</th><th>Costo Unit Sugerido</th><th>Costo Unit Manual</th></tr>";

    // Buscar cantidades del kardex
    foreach ($documentosSinDetalle as $doc) {
        $stmtKardex = $db->prepare("
            SELECT cantidad FROM kardex_inventario 
            WHERE documento_referencia = ? AND id_inventario = ?
        ");
        $stmtKardex->execute([$doc['numero_documento'], $idInventario]);
        $kardex = $stmtKardex->fetch(PDO::FETCH_ASSOC);
        $cantidad = $kardex['cantidad'] ?? 0;

        // Calcular costo sugerido
        $costoSugerido = 0;
        if ($cantidad > 0 && $doc['doc_total'] > 0) {
            if ($doc['con_factura']) {
                // Si tiene factura, el total incluye IVA, hay que quitarlo
                $costoSugerido = ($doc['doc_total'] / 1.13) / $cantidad;
            } else {
                $costoSugerido = $doc['doc_total'] / $cantidad;
            }
        }

        echo "<tr>";
        echo "<td>{$doc['numero_documento']}</td>";
        echo "<td>" . number_format($doc['doc_total'], 2) . "</td>";
        echo "<td>" . number_format($cantidad, 2) . "</td>";
        echo "<td class='warning'>" . number_format($costoSugerido, 2) . "</td>";
        echo "<td><input type='number' name='costo[{$doc['id_documento']}]' value='" . number_format($costoSugerido, 2, '.', '') . "' step='0.01' style='width:100px;'></td>";
        echo "<input type='hidden' name='cantidad[{$doc['id_documento']}]' value='{$cantidad}'>";
        echo "<input type='hidden' name='con_factura[{$doc['id_documento']}]' value='{$doc['con_factura']}'>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<button type='submit' name='insertar_detalles' value='1'>📝 Insertar Detalles Faltantes</button>";
    echo "</form>";
    echo "</div>";
}

// 3. Procesar inserción de detalles
if (isset($_POST['insertar_detalles'])) {
    echo "<div class='info'><h2>3. Insertando Detalles...</h2>";

    $stmtInsert = $db->prepare("
        INSERT INTO documentos_inventario_detalle 
        (id_documento, id_inventario, cantidad, costo_unitario, costo_con_iva, subtotal)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $insertados = 0;
    foreach ($_POST['costo'] as $idDoc => $costoUnit) {
        $cantidad = $_POST['cantidad'][$idDoc];
        $conFactura = $_POST['con_factura'][$idDoc];

        $costoUnit = floatval($costoUnit);
        $cantidad = floatval($cantidad);

        // Calcular valores
        if ($conFactura) {
            // El costo unitario es sin IVA, el costo_con_iva incluye IVA
            $costoConIva = $costoUnit * 1.13;
        } else {
            $costoConIva = $costoUnit;
        }

        $subtotal = $cantidad * $costoConIva;

        try {
            $stmtInsert->execute([
                $idDoc,
                $idInventario,
                $cantidad,
                $costoUnit,
                $costoConIva,
                $subtotal
            ]);
            $insertados++;

            echo "<p class='success'>✓ Insertado detalle para documento ID: $idDoc (Cant: $cantidad, Costo: $costoUnit, Subtotal: $subtotal)</p>";
        } catch (Exception $e) {
            echo "<p class='error'>Error al insertar ID $idDoc: " . $e->getMessage() . "</p>";
        }
    }

    echo "<p><strong>Total insertados: $insertados detalles</strong></p>";

    // Ahora actualizar el kardex con los nuevos valores
    echo "<h3>Actualizando Kardex...</h3>";

    $stmt = $db->prepare("
        UPDATE kardex_inventario k
        JOIN documentos_inventario d ON k.documento_referencia = d.numero_documento
        JOIN documentos_inventario_detalle dd ON d.id_documento = dd.id_documento 
            AND dd.id_inventario = k.id_inventario
        SET 
            k.costo_unitario = dd.costo_unitario,
            k.costo_total = dd.subtotal
        WHERE k.id_inventario = ? 
            AND k.tipo_movimiento = 'ENTRADA'
            AND (k.costo_total = 0 OR k.costo_total IS NULL)
    ");
    $stmt->execute([$idInventario]);

    $actualizados = $stmt->rowCount();
    echo "<p class='success'>✓ Kardex actualizado: $actualizados registros</p>";

    // Recalcular CPP
    echo "<h3>Recalculando CPP...</h3>";
    echo "<p>Ejecutando procedimiento almacenado...</p>";

    try {
        $stmt = $db->prepare("CALL recalcular_kardex_producto(?)");
        $stmt->execute([$idInventario]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        echo "<p class='success'><strong>✅ Recálculo completado:</strong></p>";
        echo "<ul>";
        echo "<li>Stock Final: " . number_format($resultado['stock_final'], 2) . "</li>";
        echo "<li>Valor Final: Bs. " . number_format($resultado['valor_final'], 2) . "</li>";
        echo "<li>CPP Final: Bs. " . number_format($resultado['cpp_final'], 4) . "</li>";
        echo "</ul>";
    } catch (Exception $e) {
        echo "<p class='warning'>No se pudo ejecutar el procedimiento. Puede que necesite crearlo primero.</p>";
        echo "<p>Error: " . $e->getMessage() . "</p>";
    }

    echo "</div>";
}

// 4. Mostrar kardex actualizado
echo "<div class='info'><h2>4. Kardex Actual (últimos 10)</h2>";
$stmt = $db->prepare("
    SELECT * FROM kardex_inventario 
    WHERE id_inventario = ? 
    ORDER BY fecha_movimiento DESC 
    LIMIT 10
");
$stmt->execute([$idInventario]);
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table style='font-size:12px;'>";
echo "<tr>
    <th>Fecha</th>
    <th>Documento</th>
    <th>Tipo</th>
    <th>Cantidad</th>
    <th>Costo Unit</th>
    <th>Costo Total</th>
    <th>Stock Post</th>
    <th>CPP Post</th>
</tr>";

foreach ($movimientos as $mov) {
    $clase = $mov['costo_total'] > 0 ? 'success' : 'error';
    echo "<tr class='$clase'>";
    echo "<td>" . substr($mov['fecha_movimiento'], 0, 16) . "</td>";
    echo "<td>{$mov['documento_referencia']}</td>";
    echo "<td>{$mov['tipo_movimiento']}</td>";
    echo "<td align='right'>" . number_format($mov['cantidad'], 2) . "</td>";
    echo "<td align='right'>" . number_format($mov['costo_unitario'], 2) . "</td>";
    echo "<td align='right'>" . number_format($mov['costo_total'], 2) . "</td>";
    echo "<td align='right'>" . number_format($mov['stock_posterior'], 2) . "</td>";
    echo "<td align='right'>" . number_format($mov['costo_promedio_posterior'], 4) . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

echo "</body></html>";
?>