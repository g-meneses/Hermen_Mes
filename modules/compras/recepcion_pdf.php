<?php
/**
 * Generador de PDF para Recepciones de Compra
 * Sistema MES Hermen Ltda.
 */

require_once '../../config/database.php';

if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die('ID de recepción no especificado');
}

$db = getDB();

// Obtener datos de la recepción
$stmt = $db->prepare("
    SELECT r.*, 
           p.razon_social as proveedor_nombre, p.nit as proveedor_nit, 
           p.direccion as proveedor_direccion, p.telefono as proveedor_telefono,
           u.nombre_completo as usuario_nombre,
           oc.numero_orden as orden_numero
    FROM recepciones_compra r
    JOIN proveedores p ON r.id_proveedor = p.id_proveedor
    JOIN usuarios u ON r.creado_por = u.id_usuario
    JOIN ordenes_compra oc ON r.id_orden_compra = oc.id_orden_compra
    WHERE r.id_recepcion = ?
");
$stmt->execute([$id]);
$recepcion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recepcion) {
    die('Recepción no encontrada');
}

// Obtener detalles
$stmtDet = $db->prepare("SELECT * FROM recepciones_compra_detalle WHERE id_recepcion = ?");
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
    <title>Recepción de Compra -
        <?= htmlspecialchars($recepcion['numero_recepcion']) ?>
    </title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
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
        }

        td {
            padding: 10px 8px;
            border-bottom: 1px solid #e5e7eb;
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

        .badge {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
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
                <h2>RECEPCIÓN DE COMPRA</h2>
                <div class="numero">
                    <?= htmlspecialchars($recepcion['numero_recepcion']) ?>
                </div>
                <div class="fecha">Fecha:
                    <?= date('d/m/Y H:i', strtotime($recepcion['fecha_recepcion'])) ?>
                </div>
                <div class="fecha">Ref:
                    <?= htmlspecialchars($recepcion['orden_numero']) ?>
                </div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <h3>📦 PROVEEDOR</h3>
                <p><strong>
                        <?= htmlspecialchars($recepcion['proveedor_nombre']) ?>
                    </strong></p>
                <p>NIT:
                    <?= htmlspecialchars($recepcion['proveedor_nit']) ?>
                </p>
                <p>Factura/Guía:
                    <?= htmlspecialchars($recepcion['numero_factura'] ?: ($recepcion['numero_guia_remision'] ?: 'N/A')) ?>
                </p>
            </div>
            <div class="info-box">
                <h3>📋 CONTROL DE RECEPCIÓN</h3>
                <p><strong>Recibido por:</strong>
                    <?= htmlspecialchars($recepcion['usuario_nombre']) ?>
                </p>
                <p><strong>Tipo:</strong>
                    <?= htmlspecialchars($recepcion['tipo_recepcion']) ?>
                </p>
                <p><strong>Estado:</strong>
                    <?= htmlspecialchars($recepcion['estado']) ?>
                </p>
            </div>
        </div>

        <div class="section">
            <div class="section-title">DETALLE DE MERCANCÍA RECIBIDA</div>
            <table>
                <thead>
                    <tr>
                        <th width="5%" class="text-center">#</th>
                        <th width="12%">Código</th>
                        <th>Descripción del Producto</th>
                        <th width="10%" class="text-center">Cantidad</th>
                        <th width="15%">Lote</th>
                        <th width="12%">Vencimiento</th>
                        <th width="10%" class="text-center">Calidad</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $i => $det): ?>
                        <tr>
                            <td class="text-center">
                                <?= $i + 1 ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($det['codigo_producto']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($det['descripcion_producto']) ?>
                            </td>
                            <td class="text-center"><strong>
                                    <?= number_format($det['cantidad_recibida'], 2) ?>
                                </strong></td>
                            <td>
                                <?= htmlspecialchars($det['numero_lote'] ?: '-') ?>
                            </td>
                            <td>
                                <?= $det['fecha_vencimiento'] ? date('d/m/Y', strtotime($det['fecha_vencimiento'])) : '-' ?>
                            </td>
                            <td class="text-center">
                                <span
                                    class="badge <?= $det['estado_calidad'] === 'APROBADO' ? 'badge-success' : ($det['estado_calidad'] === 'OBSERVADO' ? 'badge-warning' : 'badge-danger') ?>">
                                    <?= htmlspecialchars($det['estado_calidad']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($recepcion['observaciones'])): ?>
            <div
                style="margin-top: 20px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <p
                    style="font-weight: bold; color: #064e3b; margin-bottom: 5px; font-size: 10px; text-transform: uppercase;">
                    Observaciones:</p>
                <p>
                    <?= nl2br(htmlspecialchars($recepcion['observaciones'])) ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="firmas">
            <div class="firma-box">
                <div class="firma-linea">
                    <strong>Recibido Conforme (Almacén)</strong><br>
                    <?= htmlspecialchars($recepcion['usuario_nombre']) ?>
                </div>
            </div>
            <div class="firma-box">
                <div class="firma-linea">
                    <strong>Entrega (Transporte/Proveedor)</strong><br>
                    Nombre y Firma
                </div>
            </div>
        </div>
    </div>
</body>

</html>