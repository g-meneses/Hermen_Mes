<?php
/**
 * Generador de PDF/Impresión para Hoja de Liquidación de Importación (Landed Cost)
 * Sistema MES Hermen Ltda.
 */

require_once '../../config/database.php';

if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die('ID de orden de compra no especificado');
}

$db = getDB();

// Obtener datos de la Orden
$stmt = $db->prepare("
    SELECT oc.*, 
           p.razon_social as proveedor_nombre, p.nit as proveedor_nit, 
           p.direccion as proveedor_direccion, p.telefono as proveedor_telefono
    FROM ordenes_compra oc
    JOIN proveedores p ON oc.id_proveedor = p.id_proveedor
    WHERE oc.id_orden_compra = ?
");
$stmt->execute([$id]);
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orden) {
    die('Orden de compra no encontrada');
}

// Obtener detalles
$stmtDet = $db->prepare("SELECT * FROM ordenes_compra_detalle WHERE id_orden_compra = ?");
$stmtDet->execute([$id]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

// Gastos Registrados
$stmtGastos = $db->prepare("SELECT * FROM ordenes_compra_gastos WHERE id_orden_compra = ? ORDER BY fecha_gasto ASC");
$stmtGastos->execute([$id]);
$gastos = $stmtGastos->fetchAll(PDO::FETCH_ASSOC);

// Datos de la empresa
$empresa = [
    'nombre' => 'Hermen Ltda.',
    'nit' => '123456789',
    'direccion' => 'Zona Industrial, La Paz - Bolivia',
    'telefono' => '+591 2 1234567',
    'email' => 'compras@hermen.com.bo'
];

$tcInternacion = $orden['moneda'] === 'USD' ? (float) ($orden['tipo_cambio'] ?: 6.96) : 1;

$fobTotalBob = 0;
foreach ($detalles as $det) {
    $cant = floatval($det['cantidad_embarcada'] ?? $det['cantidad_ordenada']);
    $fobTotalBob += ($cant * floatval($det['precio_unitario'])) * $tcInternacion;
}

$totalGastos = 0;
foreach ($gastos as $g) {
    $montoBob = (!empty($g['monto_bob']) && floatval($g['monto_bob']) > 0) ? floatval($g['monto_bob']) : floatval($g['monto']);
    $totalGastos += $montoBob;
}

$totalInternado = $fobTotalBob + $totalGastos;
$factor = $fobTotalBob > 0 ? ($totalInternado / $fobTotalBob) : 1;

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Hoja de Liquidación -
        <?= htmlspecialchars($orden['numero_orden']) ?>
    </title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
        }

        .container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 10mm;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #1e40af;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .logo-section h1 {
            color: #1e40af;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .logo-section p {
            color: #666;
            font-size: 10px;
        }

        .doc-info {
            text-align: right;
            background: #eff6ff;
            padding: 15px;
            border-radius: 8px;
        }

        .doc-info h2 {
            color: #1e40af;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .doc-info .numero {
            font-size: 16px;
            font-weight: bold;
            color: #1e3a8a;
        }

        .doc-info .fecha {
            color: #666;
            margin-top: 5px;
            font-size: 10px;
        }

        .section-title {
            background: #1e40af;
            color: white;
            padding: 6px 15px;
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 10px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .info-grid {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-box {
            flex: 1;
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid #1e40af;
        }

        .info-box h3 {
            color: #1e40af;
            margin-bottom: 8px;
            font-size: 11px;
            text-transform: uppercase;
        }

        .info-box p {
            margin-bottom: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th {
            background: #1e3a8a;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 10px;
            border: 1px solid #1e3a8a;
        }

        td {
            padding: 8px;
            border: 1px solid #cbd5e1;
            font-size: 10px;
        }

        tr:nth-child(even) {
            background: #f8fafc;
        }

        tfoot th,
        tfoot td {
            background: #eff6ff;
            font-weight: bold;
            color: #1e3a8a;
            border: 1px solid #cbd5e1;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .firmas {
            display: flex;
            justify-content: space-around;
            margin-top: 50px;
            page-break-inside: avoid;
        }

        .firma-box {
            text-align: center;
            width: 200px;
        }

        .firma-linea {
            border-top: 1px solid #333;
            margin-top: 40px;
            padding-top: 5px;
        }

        .actions {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-print {
            background: #1e40af;
            color: white;
        }

        .btn-close {
            background: #6b7280;
            color: white;
        }

        .highlight-cell {
            background-color: #dbeafe !important;
            font-weight: bold;
            color: #1e3a8a;
        }

        .math-summary {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-around;
            align-items: center;
        }

        .math-item {
            text-align: center;
        }

        .math-item span {
            display: block;
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .math-item strong {
            font-size: 16px;
            color: #0f172a;
        }

        .math-operator {
            font-size: 20px;
            color: #94a3b8;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="actions no-print">
        <button class="btn btn-print" onclick="window.print()">Imprimir / Guardar PDF</button>
        <button class="btn btn-close" onclick="window.close()">Cerrar</button>
    </div>

    <div class="container">
        <div class="header">
            <div class="logo-section">
                <h1>
                    <?= htmlspecialchars($empresa['nombre']) ?>
                </h1>
                <p>NIT:
                    <?= htmlspecialchars($empresa['nit']) ?>
                </p>
                <p>
                    <?= htmlspecialchars($empresa['direccion']) ?>
                </p>
                <p>Tel:
                    <?= htmlspecialchars($empresa['telefono']) ?> |
                    <?= htmlspecialchars($empresa['email']) ?>
                </p>
            </div>
            <div class="doc-info">
                <h2>LIQUIDACIÓN DE IMPORTACIÓN</h2>
                <div class="numero">Orden:
                    <?= htmlspecialchars($orden['numero_orden']) ?>
                </div>
                <div class="fecha">Fecha Impresión:
                    <?= date('d/m/Y H:i') ?>
                </div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <h3>📦 DATOS DEL PROVEEDOR</h3>
                <p><strong>
                        <?= htmlspecialchars($orden['proveedor_nombre']) ?>
                    </strong></p>
                <p>Origen / Destino: Internacional</p>
                <p>Factura Comerc.:
                    <?= htmlspecialchars($orden['factura_comercial'] ?? 'S/N') ?>
                </p>
            </div>
            <div class="info-box">
                <h3>📊 RESUMEN MONETARIO (BOB)</h3>
                <p>Moneda Origen OC:
                    <?= htmlspecialchars($orden['moneda']) ?>
                </p>
                <p>T.C. Extranjero:
                    <?= number_format($tcInternacion, 4) ?>
                </p>
                <p>Factor Prorrateo: <strong>x
                        <?= number_format($factor, 4) ?>
                    </strong></p>
            </div>
        </div>

        <div class="math-summary">
            <div class="math-item">
                <span>Valor FOB Total (BOB)</span>
                <strong>
                    <?= number_format($fobTotalBob, 2) ?>
                </strong>
            </div>
            <div class="math-operator">+</div>
            <div class="math-item">
                <span>Gastos Adicionales (BOB)</span>
                <strong>
                    <?= number_format($totalGastos, 2) ?>
                </strong>
            </div>
            <div class="math-operator">=</div>
            <div class="math-item">
                <span style="color: #1e40af;">Costo Total Internado (BOB)</span>
                <strong style="color: #1e40af; font-size: 18px;">
                    <?= number_format($totalInternado, 2) ?>
                </strong>
            </div>
        </div>

        <?php if (count($gastos) > 0): ?>
            <div class="section">
                <div class="section-title">DETALLE DE GASTOS ADICIONALES</div>
                <table>
                    <thead>
                        <tr>
                            <th width="5%" class="text-center">#</th>
                            <th width="25%">Concepto / Tipo de Gasto</th>
                            <th width="40%">Descripción / Proveedor Secundario</th>
                            <th width="15%" class="text-center">Nº Factura / Doc</th>
                            <th width="15%" class="text-right">Monto Líquido (BOB)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $counter = 1;
                        foreach ($gastos as $gasto):
                            $montoBobLinea = (!empty($gasto['monto_bob']) && floatval($gasto['monto_bob']) > 0) ? floatval($gasto['monto_bob']) : floatval($gasto['monto']);
                            ?>
                            <tr>
                                <td class="text-center">
                                    <?= $counter++ ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($gasto['tipo_gasto']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($gasto['descripcion']) ?>
                                </td>
                                <td class="text-center">
                                    <?= htmlspecialchars($gasto['numero_factura_gasto'] ?: 'S/D') ?>
                                </td>
                                <td class="text-right">
                                    <?= number_format($montoBobLinea, 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-right">SUMATORIA GASTOS ADICIONALES:</th>
                            <td class="text-right highlight-cell">
                                <?= number_format($totalGastos, 2) ?> BOB
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>

        <div class="section">
            <div class="section-title">CUADRO DE LIQUIDACIÓN Y PRORRATEO DE ÍTEMS</div>
            <table>
                <thead>
                    <tr>
                        <th width="4%" class="text-center">#</th>
                        <th width="12%">Código</th>
                        <th width="32%">Descripción del Producto</th>
                        <th width="10%" class="text-center">Cant.</th>
                        <th width="14%" class="text-right">FOB Unit (BOB)</th>
                        <th width="14%" class="text-right">FOB Total (BOB)</th>
                        <th width="14%" class="text-right">Costo Internado Unit.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $counter = 1;
                    foreach ($detalles as $det):
                        $cant = floatval($det['cantidad_embarcada'] ?? $det['cantidad_ordenada']);
                        $fobUnitBob = floatval($det['precio_unitario']) * $tcInternacion;
                        $fobSubtotal = $cant * $fobUnitBob;

                        $costoInternado = floatval($det['precio_unitario_internacion']);
                        if ($costoInternado <= 0) {
                            $costoInternado = $fobUnitBob * $factor; // Fallback
                        }
                        ?>
                        <tr>
                            <td class="text-center">
                                <?= $counter++ ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($det['codigo_producto']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($det['descripcion_producto']) ?>
                            </td>
                            <td class="text-center font-bold">
                                <?= number_format($cant, 2) ?> <span style="font-size:8px; font-weight:normal;">
                                    <?= htmlspecialchars($det['unidad_medida'] ?: 'Und') ?>
                                </span>
                            </td>
                            <td class="text-right text-slate-500">
                                <?= number_format($fobUnitBob, 4) ?>
                            </td>
                            <td class="text-right text-slate-500">
                                <?= number_format($fobSubtotal, 2) ?>
                            </td>
                            <td class="text-right highlight-cell">
                                <?= number_format($costoInternado, 4) ?> BOB
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="font-size: 8px; color: #64748b; font-style: italic; text-align: right;">* Costo Internado Unitario
                consolidado que será promediado e insertado en Kardex.</p>
        </div>

        <div class="firmas">
            <div class="firma-box">
                <div class="firma-linea">
                    <strong>Firma Encargado de Compras</strong><br>
                    Elaborado por
                </div>
            </div>
            <div class="firma-box">
                <div class="firma-linea">
                    <strong>VºBº Gerencia / Contabilidad</strong><br>
                    Aprobado por
                </div>
            </div>
            <div class="firma-box">
                <div class="firma-linea">
                    <strong>Firma Almacenes</strong><br>
                    Revisado por
                </div>
            </div>
        </div>
    </div>
</body>

</html>