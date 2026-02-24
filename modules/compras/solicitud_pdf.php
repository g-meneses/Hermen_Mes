<?php
/**
 * Generador de PDF para Solicitudes de Compra
 * Sistema MES Hermen Ltda.
 */

require_once '../../config/database.php';

if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die('ID de solicitud no especificado');
}

$db = getDB();

// Obtener datos de la solicitud
$stmt = $db->prepare("
    SELECT s.*, 
           u.nombre_completo as solicitante_nombre,
           ti.nombre as tipo_inventario_nombre
    FROM solicitudes_compra s
    LEFT JOIN usuarios u ON s.id_usuario_solicitante = u.id_usuario
    LEFT JOIN tipos_inventario ti ON s.id_tipo_inventario = ti.id_tipo_inventario
    WHERE s.id_solicitud = ?
");
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    die('Solicitud no encontrada');
}

// Configuración de Idioma
$lang = $_GET['lang'] ?? 'es';
$l = [
    'es' => [
        'titulo' => 'SOLICITUD DE COMPRA',
        'numero' => 'Número:',
        'fecha' => 'Fecha:',
        'solicitante' => 'SOLICITANTE',
        'nombre' => 'Nombre:',
        'centro_costo' => 'Centro de Costo:',
        'tipo_inventario' => 'Tipo Inventario:',
        'detalles_req' => 'DETALLES DEL REQUERIMIENTO',
        'prioridad' => 'Prioridad:',
        'tipo_compra' => 'Tipo Compra:',
        'estado' => 'Estado:',
        'tabla_productos' => 'DETALLE DE PRODUCTOS REQUERIDOS',
        'col_item' => '#',
        'col_codigo' => 'Código',
        'col_descripcion' => 'Descripción del Producto',
        'col_cantidad' => 'Cantidad',
        'col_unidad' => 'Unidad',
        'justificacion' => 'Justificación / Motivo del Pedido',
        'observaciones' => 'Observaciones de Control',
        'firma_solicitante' => 'SOLICITANTE',
        'firma_jefe' => 'Jefe de Área',
        'firma_autorizacion' => 'Autorización',
        'firma_adquisiciones' => 'Adquisiciones',
        'firma_recepcion' => 'Recepción',
        'btn_imprimir' => 'Imprimir Solicitud',
        'btn_cerrar' => 'Cerrar'
    ],
    'en' => [
        'titulo' => 'REQUEST FOR QUOTATION (RFQ)',
        'numero' => 'Number:',
        'fecha' => 'Date:',
        'solicitante' => 'REQUESTER',
        'nombre' => 'Name:',
        'centro_costo' => 'Cost Center:',
        'tipo_inventario' => 'Inventory Type:',
        'detalles_req' => 'REQUIREMENT DETAILS',
        'prioridad' => 'Priority:',
        'tipo_compra' => 'Purchase Type:',
        'estado' => 'Status:',
        'tabla_productos' => 'REQUIRED PRODUCTS DETAIL',
        'col_item' => '#',
        'col_codigo' => 'Code',
        'col_descripcion' => 'Product Description',
        'col_cantidad' => 'Quantity',
        'col_unidad' => 'Unit',
        'justificacion' => 'Justification / Purpose of Order',
        'observaciones' => 'Control Observations',
        'firma_solicitante' => 'REQUESTER',
        'firma_jefe' => 'Department Head',
        'firma_autorizacion' => 'Authorization',
        'firma_adquisiciones' => 'Procurement',
        'firma_recepcion' => 'Reception',
        'btn_imprimir' => 'Print RFQ',
        'btn_cerrar' => 'Close'
    ]
];

$txt = $l[$lang] ?? $l['es'];

// Obtener detalles con stock actual del producto
$stmtDet = $db->prepare("
    SELECT d.*, i.stock_actual 
    FROM solicitudes_compra_detalle d
    LEFT JOIN inventarios i ON d.id_producto = i.id_inventario
    WHERE d.id_solicitud = ?
");
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
    <title><?= $txt['titulo'] ?> -
        <?= htmlspecialchars($solicitud['numero_solicitud']) ?>
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
            border-bottom: 3px solid #6366f1;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .logo-section {
            flex: 1;
        }

        .logo-section h1 {
            color: #4f46e5;
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
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .doc-info h2 {
            color: #4f46e5;
            font-size: 18px;
            margin-bottom: 10px;
            letter-spacing: 0.05em;
        }

        .doc-info .numero {
            font-size: 16px;
            font-weight: bold;
            color: #1e1b4b;
        }

        .doc-info .fecha {
            color: #64748b;
            margin-top: 5px;
            font-weight: 500;
        }

        /* Secciones */
        .section-title {
            background: #4f46e5;
            color: white;
            padding: 8px 15px;
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 10px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        /* Info boxes */
        .info-grid {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-box {
            flex: 1;
            background: #ffffff;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            position: relative;
        }

        .info-box h3 {
            color: #64748b;
            margin-bottom: 10px;
            font-size: 9px;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.05em;
        }

        .info-box p {
            margin-bottom: 6px;
            font-size: 11px;
        }

        .info-box strong {
            color: #1e293b;
            font-weight: 600;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-normal {
            background: #e0e7ff;
            color: #4338ca;
        }

        .badge-alta {
            background: #ffedd5;
            color: #9a3412;
        }

        .badge-urgente {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Tabla de productos */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }

        th {
            background: #f8fafc;
            color: #64748b;
            padding: 12px 10px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 11px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        /* Motivo / Justificación */
        .motivo-box {
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-top: 20px;
        }

        .motivo-box h4 {
            color: #4f46e5;
            margin-bottom: 8px;
            font-size: 10px;
            text-transform: uppercase;
        }

        .motivo-text {
            font-style: italic;
            color: #475569;
        }

        /* Firmas */
        .firmas {
            display: flex;
            justify-content: space-around;
            margin-top: 60px;
        }

        .firma-box {
            text-align: center;
            width: 180px;
        }

        .firma-linea {
            border-top: 1px solid #94a3b8;
            margin-top: 40px;
            padding-top: 10px;
        }

        .firma-box strong {
            display: block;
            font-size: 10px;
            color: #1e293b;
        }

        .firma-box span {
            font-size: 9px;
            color: #64748b;
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
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-print {
            background: #4f46e5;
            color: white;
        }

        .btn-close {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <div class="actions no-print">
        <button class="btn btn-print" onclick="window.print()">
            <?= $txt['btn_imprimir'] ?>
        </button>
        <button class="btn btn-close" onclick="window.close()">
            <?= $txt['btn_cerrar'] ?>
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
                <h2><?= $txt['titulo'] ?></h2>
                <div class="numero">
                    <?= htmlspecialchars($solicitud['numero_solicitud']) ?>
                </div>
                <div class="fecha"><?= $txt['fecha'] ?>
                    <?= date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])) ?>
                </div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <h3>👤 <?= $txt['solicitante'] ?></h3>
                <p><strong><?= $txt['nombre'] ?></strong>
                    <?= htmlspecialchars($solicitud['solicitante_nombre'] ?? 'Sistema') ?>
                </p>
                <p><strong><?= $txt['centro_costo'] ?></strong>
                    <?= htmlspecialchars($solicitud['centro_costo'] ?? 'General') ?>
                </p>
                <p><strong><?= $txt['tipo_inventario'] ?></strong>
                    <?= htmlspecialchars($solicitud['tipo_inventario_nombre'] ?? 'N/A') ?>
                </p>
            </div>
            <div class="info-box">
                <h3>📋 <?= $txt['detalles_req'] ?></h3>
                <p><strong><?= $txt['prioridad'] ?></strong>
                    <span class="badge badge-<?= strtolower($solicitud['prioridad']) ?>">
                        <?= htmlspecialchars($solicitud['prioridad']) ?>
                    </span>
                </p>
                <p><strong><?= $txt['tipo_compra'] ?></strong>
                    <?= htmlspecialchars($solicitud['tipo_compra'] ?? 'REPOSICIÓN') ?>
                </p>
                <p><strong><?= $txt['estado'] ?></strong>
                    <?= htmlspecialchars($solicitud['estado']) ?>
                </p>
            </div>
        </div>

        <div class="section">
            <div class="section-title"><?= $txt['tabla_productos'] ?></div>
            <table>
                <thead>
                    <tr>
                        <th width="5%" class="text-center"><?= $txt['col_item'] ?></th>
                        <th width="15%"><?= $txt['col_codigo'] ?></th>
                        <th><?= $txt['col_descripcion'] ?></th>
                        <th width="15%" class="text-center"><?= $txt['col_cantidad'] ?></th>
                        <th width="15%" class="text-center"><?= $txt['col_unidad'] ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $i => $det): ?>
                        <tr>
                            <td class="text-center">
                                <?= $i + 1 ?>
                            </td>
                            <td style="font-family: monospace; font-weight: 600;">
                                <?= htmlspecialchars($det['codigo_producto'] ?? '-') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($det['descripcion_producto']) ?>
                            </td>
                            <td class="text-center" style="font-weight: bold;">
                                <?= number_format($det['cantidad_solicitada'], 2) ?>
                            </td>
                            <td class="text-center">
                                <?= htmlspecialchars($det['unidad_medida']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($solicitud['motivo'])): ?>
            <div class="motivo-box">
                <h4>📝 <?= $txt['justificacion'] ?></h4>
                <p class="motivo-text">
                    <?= nl2br(htmlspecialchars($solicitud['motivo'])) ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (!empty($solicitud['observaciones'])): ?>
            <div class="motivo-box" style="margin-top: 10px; border-left: 4px solid #f59e0b;">
                <h4 style="color: #b45309;"><?= $txt['observaciones'] ?></h4>
                <p>
                    <?= nl2br(htmlspecialchars($solicitud['observaciones'])) ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="firmas">
            <div class="firma-box">
                <div class="firma-linea">
                    <strong><?= $txt['firma_solicitante'] ?></strong>
                    <span>
                        <?= htmlspecialchars($solicitud['solicitante_nombre'] ?? 'Firma') ?>
                    </span>
                </div>
            </div>
            <div class="firma-box">
                <div class="firma-linea">
                    <strong><?= $txt['firma_jefe'] ?></strong>
                    <span><?= $txt['firma_autorizacion'] ?></span>
                </div>
            </div>
            <div class="firma-box">
                <div class="firma-linea">
                    <strong><?= $txt['firma_adquisiciones'] ?></strong>
                    <span><?= $txt['firma_recepcion'] ?></span>
                </div>
            </div>
        </div>
    </div>
</body>

</html>