<?php
/**
 * Generador de PDF para Órdenes de Compra
 * Sistema MES Hermen Ltda.
 */

require_once '../../config/database.php';

if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die('ID de orden no especificado');
}

$db = getDB();

// Obtener datos de la orden
$stmt = $db->prepare("
    SELECT oc.*, 
           p.razon_social as proveedor_nombre, p.nit as proveedor_nit, 
           p.direccion as proveedor_direccion, p.telefono as proveedor_telefono,
           p.email as proveedor_email,
           u.nombre_completo as comprador_nombre
    FROM ordenes_compra oc
    JOIN proveedores p ON oc.id_proveedor = p.id_proveedor
    JOIN usuarios u ON oc.id_comprador = u.id_usuario
    WHERE oc.id_orden_compra = ?
");
$stmt->execute([$id]);
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orden) {
    die('Orden no encontrada');
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
    'email' => 'compras@hermen.com.bo'
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Orden de Compra -
        <?= htmlspecialchars($orden['numero_orden']) ?>
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

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .logo-section {
            flex: 1;
        }

        .logo-section h1 {
            color: #2563eb;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .logo-section p {
            color: #666;
            font-size: 10px;
        }

        .doc-info {
            text-align: right;
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
        }

        .doc-info h2 {
            color: #2563eb;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .doc-info .numero {
            font-size: 16px;
            font-weight: bold;
            color: #1e40af;
        }

        .doc-info .fecha {
            color: #666;
            margin-top: 5px;
        }

        /* Secciones */
        .section {
            margin-bottom: 20px;
        }

        .section-title {
            background: #2563eb;
            color: white;
            padding: 8px 15px;
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 10px;
            border-radius: 4px;
        }

        /* Info boxes */
        .info-grid {
            display: flex;
            gap: 20px;
        }

        .info-box {
            flex: 1;
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #2563eb;
        }

        .info-box h3 {
            color: #2563eb;
            margin-bottom: 10px;
            font-size: 12px;
        }

        .info-box p {
            margin-bottom: 5px;
        }

        .info-box strong {
            color: #1e40af;
        }

        /* Tabla de productos */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background: #1e40af;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-size: 11px;
        }

        th:first-child {
            border-radius: 4px 0 0 0;
        }

        th:last-child {
            border-radius: 0 4px 0 0;
        }

        td {
            padding: 10px 8px;
            border-bottom: 1px solid #e5e7eb;
        }

        tr:nth-child(even) {
            background: #f8fafc;
        }

        tr:hover {
            background: #e0f2fe;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* Totales */
        .totales {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
        }

        .totales-box {
            background: #f8fafc;
            padding: 15px 25px;
            border-radius: 8px;
            min-width: 250px;
        }

        .totales-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }

        .totales-row.total {
            border-top: 2px solid #2563eb;
            margin-top: 10px;
            padding-top: 10px;
            font-size: 14px;
            font-weight: bold;
            color: #1e40af;
        }

        /* Observaciones */
        .observaciones {
            background: #fef3c7;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #f59e0b;
            margin-top: 20px;
        }

        .observaciones h4 {
            color: #b45309;
            margin-bottom: 8px;
        }

        /* Firmas */
        .firmas {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            padding-top: 20px;
        }

        .firma-box {
            text-align: center;
            width: 200px;
        }

        .firma-linea {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 10px;
        }

        /* Botones de acción */
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
            background: #2563eb;
            color: white;
        }

        .btn-close {
            background: #6b7280;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }
    </style>
</head>

<body>
    <!-- Botones de acción (no se imprimen) -->
    <div class="actions no-print">
        <button class="btn btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimir / PDF
        </button>
        <button class="btn btn-close" onclick="window.close()">
            <i class="fas fa-times"></i> Cerrar
        </button>
    </div>

    <div class="container">
        <!-- Cabecera -->
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
                <h2>ORDEN DE COMPRA</h2>
                <div class="numero">
                    <?= htmlspecialchars($orden['numero_orden']) ?>
                </div>
                <div class="fecha">Fecha:
                    <?= date('d/m/Y', strtotime($orden['fecha_orden'])) ?>
                </div>
                <?php if ($orden['fecha_entrega_estimada']): ?>
                    <div class="fecha">Entrega Est.:
                        <?= date('d/m/Y', strtotime($orden['fecha_entrega_estimada'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Información de Proveedor y Comprador -->
        <div class="info-grid">
            <div class="info-box">
                <h3>📦 PROVEEDOR</h3>
                <p><strong>
                        <?= htmlspecialchars($orden['proveedor_nombre']) ?>
                    </strong></p>
                <p>NIT:
                    <?= htmlspecialchars($orden['proveedor_nit']) ?>
                </p>
                <?php if ($orden['proveedor_direccion']): ?>
                    <p>Dirección:
                        <?= htmlspecialchars($orden['proveedor_direccion']) ?>
                    </p>
                <?php endif; ?>
                <?php if ($orden['proveedor_telefono']): ?>
                    <p>Tel:
                        <?= htmlspecialchars($orden['proveedor_telefono']) ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="info-box">
                <h3>📋 DATOS DE LA ORDEN</h3>
                <p><strong>Condición de Pago:</strong>
                    <?= htmlspecialchars($orden['condicion_pago'] ?? 'CONTADO') ?>
                </p>
                <p><strong>Moneda:</strong>
                    <?= htmlspecialchars($orden['moneda'] ?? 'BOB') ?>
                </p>
                <p><strong>Emitido por:</strong>
                    <?= htmlspecialchars($orden['comprador_nombre']) ?>
                </p>
                <?php if ($orden['numero_solicitud']): ?>
                    <p><strong>Ref. Solicitud:</strong>
                        <?= htmlspecialchars($orden['numero_solicitud']) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tabla de productos -->
        <div class="section">
            <div class="section-title">DETALLE DE PRODUCTOS</div>
            <table>
                <thead>
                    <tr>
                        <th width="5%" class="text-center">#</th>
                        <th width="10%">Código</th>
                        <th>Descripción</th>
                        <th width="10%" class="text-center">Cantidad</th>
                        <th width="10%" class="text-center">Unidad</th>
                        <th width="12%" class="text-right">P. Unit.</th>
                        <th width="12%" class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $i => $det): ?>
                        <tr>
                            <td class="text-center">
                                <?= $i + 1 ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($det['codigo_producto'] ?? '-') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($det['descripcion_producto']) ?>
                            </td>
                            <td class="text-center">
                                <?= number_format($det['cantidad_ordenada'], 2) ?>
                            </td>
                            <td class="text-center">
                                <?= htmlspecialchars($det['unidad_medida']) ?>
                            </td>
                            <td class="text-right">
                                <?= number_format($det['precio_unitario'], 2) ?>
                            </td>
                            <td class="text-right">
                                <?= number_format($det['total_linea'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totales -->
        <div class="totales">
            <div class="totales-box">
                <div class="totales-row">
                    <span>Subtotal:</span>
                    <span>BOB
                        <?= number_format($orden['subtotal'] ?? $orden['total'], 2) ?>
                    </span>
                </div>
                <?php if (($orden['descuento_general'] ?? 0) > 0): ?>
                    <div class="totales-row">
                        <span>Descuento:</span>
                        <span>-BOB
                            <?= number_format($orden['descuento_general'], 2) ?>
                        </span>
                    </div>
                <?php endif; ?>
                <div class="totales-row total">
                    <span>TOTAL:</span>
                    <span>BOB
                        <?= number_format($orden['total'], 2) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Observaciones -->
        <?php if (!empty($orden['observaciones'])): ?>
            <div class="observaciones">
                <h4>📝 Observaciones / Instrucciones</h4>
                <p>
                    <?= nl2br(htmlspecialchars($orden['observaciones'])) ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Firmas -->
        <div class="firmas">
            <div class="firma-box">
                <div class="firma-linea">
                    <strong>Elaborado por</strong><br>
                    <?= htmlspecialchars($orden['comprador_nombre']) ?>
                </div>
            </div>
            <div class="firma-box">
                <div class="firma-linea">
                    <strong>Autorizado por</strong><br>
                    Gerencia
                </div>
            </div>
            <div class="firma-box">
                <div class="firma-linea">
                    <strong>Recibido por</strong><br>
                    Proveedor
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</body>

</html>