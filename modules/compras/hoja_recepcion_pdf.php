<?php
/**
 * Generador de PDF/Impresión para Hoja de Recepción (Picking List)
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

// Datos de la empresa
$empresa = [
    'nombre' => 'Hermen Ltda.',
    'nit' => '123456789',
    'direccion' => 'Zona Industrial, La Paz - Bolivia',
    'telefono' => '+591 2 1234567',
    'email' => 'almacen@hermen.com.bo'
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Hoja de Recepción - <?= htmlspecialchars($orden['numero_orden']) ?></title>
    <style>
        @page {
            size: A4 landscape;
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
            max-width: 280mm;
            margin: 0 auto;
            padding: 10mm;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #059669;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .logo-section h1 {
            color: #059669;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .logo-section p {
            color: #666;
            font-size: 10px;
        }

        .doc-info {
            text-align: right;
            background: #f0fdf4;
            padding: 15px;
            border-radius: 8px;
        }

        .doc-info h2 {
            color: #059669;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .doc-info .numero {
            font-size: 16px;
            font-weight: bold;
            color: #064e3b;
        }

        .doc-info .fecha {
            color: #666;
            margin-top: 5px;
        }

        .section-title {
            background: #059669;
            color: white;
            padding: 8px 15px;
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 10px;
            border-radius: 4px;
        }

        .info-grid {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-box {
            flex: 1;
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #059669;
        }

        .info-box h3 {
            color: #059669;
            margin-bottom: 10px;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background: #064e3b;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-size: 11px;
            border: 1px solid #064e3b;
        }

        td {
            padding: 12px 8px;
            border: 1px solid #9ca3af;
        }

        tr:nth-child(even) {
            background: #f8fafc;
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
            margin-top: 60px;
        }

        .firma-box {
            text-align: center;
            width: 250px;
        }

        .firma-linea {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 10px;
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
            background: #059669;
            color: white;
        }

        .btn-close {
            background: #6b7280;
            color: white;
        }

        .empty-cell {
            background-color: #ffffff;
        }
    </style>
</head>

<body>
    <div class="actions no-print">
        <button class="btn btn-print" onclick="window.print()">Imprimir / PDF</button>
        <button class="btn btn-close" onclick="window.close()">Cerrar</button>
    </div>

    <div class="container">
        <div class="header">
            <div class="logo-section">
                <h1><?= htmlspecialchars($empresa['nombre']) ?></h1>
                <p>NIT: <?= htmlspecialchars($empresa['nit']) ?></p>
                <p><?= htmlspecialchars($empresa['direccion']) ?></p>
                <p>Tel: <?= htmlspecialchars($empresa['telefono']) ?> | <?= htmlspecialchars($empresa['email']) ?></p>
            </div>
            <div class="doc-info">
                <h2>HOJA DE RECEPCIÓN (FÍSICO)</h2>
                <div class="numero">Orden Ref: <?= htmlspecialchars($orden['numero_orden']) ?></div>
                <div class="fecha">Fecha de Impresión: <?= date('d/m/Y H:i') ?></div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <h3>📦 PROVEEDOR</h3>
                <p><strong><?= htmlspecialchars($orden['proveedor_nombre']) ?></strong></p>
                <p>NIT: <?= htmlspecialchars($orden['proveedor_nit'] ?: 'N/A') ?></p>
                <p>Teléfono: <?= htmlspecialchars($orden['proveedor_telefono'] ?: 'N/A') ?></p>
            </div>
            <div class="info-box">
                <h3>📝 DOCUMENTOS DE RESPALDO</h3>
                <p>Factura Comerc. / Nota: ________________________</p>
                <p>Remisión / Guía: ________________________</p>
            </div>
        </div>

        <div class="section">
            <div class="section-title">DETALLE PARA CONTROL DE ALMACÉN</div>
            <table>
                <thead>
                    <tr>
                        <th width="4%" class="text-center">#</th>
                        <th width="10%">Código</th>
                        <th width="20%">Descripción del Producto</th>
                        <th width="8%" class="text-center">Cant. Esperada</th>
                        <th width="6%" class="text-center">Ud.</th>
                        <th width="10%" class="text-center">Físico Recibido</th>
                        <th width="10%" class="text-center">Rechazado</th>
                        <th width="12%">Nro. Lote</th>
                        <th width="20%">Venc. / Observación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $counter = 1;
                    foreach ($detalles as $det):
                        // Priorizar cantidad_embarcada si existe
                        $cantEsperadaRaw = $det['cantidad_embarcada'] ?? $det['cantidad_ordenada'];
                        $cantRecibida = $det['cantidad_recibida'] ?? 0;
                        $cantPendiente = floatval($cantEsperadaRaw) - floatval($cantRecibida);

                        if ($cantPendiente <= 0)
                            continue; // Solo mostrar los pendientes
                        ?>
                        <tr>
                            <td class="text-center"><?= $counter++ ?></td>
                            <td><?= htmlspecialchars($det['codigo_producto']) ?></td>
                            <td><?= htmlspecialchars($det['descripcion_producto']) ?></td>
                            <td class="text-center text-emerald-700 font-bold" style="background-color: #f0fdf4;">
                                <?= number_format($cantPendiente, 2) ?>
                            </td>
                            <td class="text-center text-slate-500 text-xs">
                                <?= htmlspecialchars($det['unidad_medida'] ?: 'Und') ?></td>
                            <td class="empty-cell"></td>
                            <td class="empty-cell"></td>
                            <td class="empty-cell"></td>
                            <td class="empty-cell"></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($counter === 1): ?>
                        <tr>
                            <td colspan="9" class="text-center" style="padding: 20px;">No hay items pendientes por recibir
                                en esta orden.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="firmas">
            <div class="firma-box">
                <div class="firma-linea">
                    <strong>Firma Encargado de Almacén</strong><br>
                    Nombre: _______________________
                </div>
            </div>
            <div class="firma-box">
                <div class="firma-linea">
                    <strong>Conformidad Proveedor / Transporte</strong><br>
                    Nombre: _______________________
                </div>
            </div>
        </div>
    </div>
</body>

</html>