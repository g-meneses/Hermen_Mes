<?php
/**
 * Módulo de Inventarios - Materias Primas
 * Sistema MES Hermen Ltda. v1.0
 * Página independiente para gestión completa de Materias Primas
 */
require_once '../../config/database.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Materias Primas - Inventarios';
$currentPage = 'materias_primas';

$db = getDB();
$stmt = $db->prepare("SELECT * FROM tipos_inventario WHERE codigo = 'MP' AND activo = 1");
$stmt->execute();
$tipoInventario = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$tipoInventario) {
    die('Error: Tipo de inventario "Materias Primas" no encontrado');
}

$tipoId = $tipoInventario['id_tipo_inventario'];
$tipoColor = $tipoInventario['color'] ?? '#007bff';
$tipoIcono = $tipoInventario['icono'] ?? 'fa-box';

require_once '../../includes/header.php';
?>

<!--<link rel="stylesheet" href="css/inventario_tipo.css"> -->

<style>
    :root {
        --tipo-color:
            <?php echo $tipoColor; ?>
        ;
    }

    .mp-module {
        padding: 20px;
        background: #f4f6f9;
        min-height: calc(100vh - 60px);
    }

    .mp-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        background: white;
        padding: 20px 25px;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        flex-wrap: wrap;
        gap: 15px;
    }

    .mp-header-left {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .btn-volver {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: #6c757d;
        color: white;
        border: none;
        border-radius: 8px;
        text-decoration: none;
    }

    .btn-volver:hover {
        background: #5a6268;
        color: white;
    }

    .mp-title {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .mp-title-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        background: var(--tipo-color);
    }

    .mp-title h1 {
        font-size: 1.6rem;
        color: #1a1a2e;
        margin: 0;
    }

    .mp-title p {
        font-size: 0.85rem;
        color: #6c757d;
        margin: 0;
    }

    .mp-header-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn-action {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .btn-ingreso {
        background: #28a745;
        color: white;
    }

    .btn-salida {
        background: #dc3545;
        color: white;
    }

    .btn-historial {
        background: #17a2b8;
        color: white;
    }

    .btn-nuevo {
        background: var(--tipo-color);
        color: white;
    }

    /* ========================================
       ESTILOS PARA KARDEX VALORADO
       ======================================== */
    .kardex-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        margin-top: 10px;
    }



    .kardex-table td {
        padding: 8px;
        border: 1px solid #e9ecef;
        text-align: right;
        font-size: 0.85rem;
    }

    .kardex-table td.documento {
        text-align: left;
        font-weight: 600;
    }

    .kardex-table tr.entrada {
        background: #d4edda;
    }

    .kardex-table tr.salida {
        background: #f8d7da;
    }

    .kardex-table tr.saldo-inicial {
        background: #fff3cd;
        font-weight: 700;
    }

    .kardex-table .cpp-column {
        background: #e7f3ff;
        font-weight: 600;
    }

    .kardex-table tbody tr:hover {
        opacity: 0.9;
    }

    .mp-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .kpi-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .kpi-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: white;
    }

    .kpi-icon.items {
        background: linear-gradient(135deg, #1a237e, #4fc3f7);
    }

    .kpi-icon.valor {
        background: linear-gradient(135deg, #11998e, #38ef7d);
    }

    .kpi-icon.alertas {
        background: linear-gradient(135deg, #eb3349, #f45c43);
    }

    .kpi-icon.categorias {
        background: linear-gradient(135deg, #4facfe, #00f2fe);
    }

    .kpi-label {
        font-size: 0.8rem;
        color: #6c757d;
    }

    .kpi-value {
        font-size: 1.4rem;
        font-weight: 700;
        color: #1a1a2e;
    }

    .kpi-value.danger {
        color: #dc3545;
    }

    .categorias-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .categoria-card {
        background: white;
        border-radius: 12px;
        padding: 18px;
        cursor: pointer;
        transition: all 0.2s;
        border: 2px solid transparent;
        border-top: 3px solid var(--tipo-color);
    }

    .categoria-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    }

    .categoria-card.active {
        border-color: var(--tipo-color);
    }

    .categoria-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    .categoria-nombre {
        font-weight: 600;
        color: #1a1a2e;
    }

    .categoria-badge {
        background: var(--tipo-color);
        color: white;
        font-size: 0.75rem;
        padding: 2px 8px;
        border-radius: 10px;
    }

    .categoria-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        text-align: center;
    }

    .cat-stat-value {
        font-size: 1rem;
        font-weight: 700;
    }

    .cat-stat-value.alerta {
        color: #dc3545;
    }

    .cat-stat-label {
        font-size: 0.65rem;
        color: #6c757d;
        text-transform: uppercase;
    }

    .subcategorias-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
        padding: 15px;
        background: #e9ecef;
        border-radius: 8px;
    }

    .subcategoria-chip {
        padding: 8px 16px;
        background: white;
        border: 2px solid #dee2e6;
        border-radius: 20px;
        cursor: pointer;
    }

    .subcategoria-chip:hover {
        border-color: var(--tipo-color);
    }

    .subcategoria-chip.active {
        background: var(--tipo-color);
        color: white;
        border-color: var(--tipo-color);
    }

    .mp-productos {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .productos-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid #e9ecef;
        flex-wrap: wrap;
        gap: 10px;
    }

    .productos-table {
        width: 100%;
        border-collapse: collapse;
    }



    .productos-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #f1f1f1;
    }

    .productos-table tr:hover {
        background: #f8f9fa;
    }

    .stock-badge {
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .stock-badge.ok {
        background: #d4edda;
        color: #155724;
    }

    .stock-badge.bajo {
        background: #fff3cd;
        color: #856404;
    }

    .stock-badge.critico {
        background: #f8d7da;
        color: #721c24;
    }

    .stock-badge.sin-stock {
        background: #e9ecef;
        color: #6c757d;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 0 2px;
    }

    .btn-icon.kardex {
        background: #1a237e;
        color: white;
    }

    .btn-icon.editar {
        background: #ffc107;
        color: #212529;
    }

    /* MODALES */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal.show {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 16px;
        width: 95%;
        max-width: 900px;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .modal-content.large {
        max-width: 1100px;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 25px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        background: linear-gradient(135deg, #1a237e, #4fc3f7);
        color: white;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.2rem;
        color: white;
        font-weight: 700;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .modal-close {
        width: 35px;
        height: 35px;
        border: none;
        background: #e9ecef;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.2rem;
    }

    .modal-close:hover {
        background: #dc3545;
        color: white;
    }

    .modal-body {
        padding: 25px;
        overflow-y: auto;
        flex: 1;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 15px 25px;
        border-top: 1px solid #e9ecef;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 15px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .form-group label {
        font-size: 0.85rem;
        font-weight: 500;
        color: #495057;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 10px 14px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        font-size: 0.9rem;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--tipo-color);
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
    }

    .btn-primary {
        background: var(--tipo-color);
        color: white;
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-success {
        background: #28a745;
        color: white;
    }

    .btn-danger {
        background: #dc3545;
        color: white;
    }

    .tabla-lineas {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
    }



    .tabla-lineas td {
        padding: 8px;
        border-bottom: 1px solid #e9ecef;
    }

    .tabla-lineas select,
    .tabla-lineas input {
        width: 100%;
        padding: 8px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
    }

    .totales-box {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        text-align: right;
    }

    .totales-box .total-final {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--tipo-color);
    }

    .checkbox-iva {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        background: #e7f3ff;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    .checkbox-iva input {
        width: 18px;
        height: 18px;
    }

    .badge-activo {
        background: #28a745;
        color: white;
        padding: 3px 10px;
        border-radius: 10px;
        font-size: 0.75rem;
    }

    .badge-anulado {
        background: #dc3545;
        color: white;
        padding: 3px 10px;
        border-radius: 10px;
        font-size: 0.75rem;
    }

    .kardex-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }



    .kardex-table td {
        padding: 10px;
        border-bottom: 1px solid #e9ecef;
    }

    .kardex-table tr.entrada td {
        background: #d4edda;
    }

    .kardex-table tr.salida td {
        background: #f8d7da;
    }

    @media (max-width: 768px) {
        .mp-header {
            flex-direction: column;
        }

        .form-row {
            grid-template-columns: 1fr;
        }
    }

    /* Modal extra grande */
    .modal-content.xlarge {
        max-width: 1100px;
    }

    /* Info Proveedor Box */
    .info-proveedor-box {
        display: flex;
        gap: 15px;
        align-items: center;
        padding: 10px 15px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 15px;
        font-size: 0.85rem;
    }

    /* Filtros productos */
    .filtros-productos {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    /* Tabla de líneas mejorada */
    .tabla-ingreso-container {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #e9ecef;
        border-radius: 8px;
    }

    .tabla-lineas {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .tabla-lineas th,
    .kardex-table th,
    .tabla-detalle th,
    .productos-table th {
        background: #2c3e50 !important;
        color: white !important;
        padding: 12px 10px !important;
        text-align: center;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        position: sticky;
        top: 0;
        z-index: 10;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
    }

    .productos-table th {
        text-align: left;
    }



    .tabla-lineas td {
        padding: 8px;
        border-bottom: 1px solid #e9ecef;
        vertical-align: middle;
    }

    .tabla-lineas input,
    .tabla-lineas select {
        width: 100%;
        padding: 6px 8px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        font-size: 0.85rem;
    }

    .tabla-lineas input[type="number"] {
        text-align: right;
    }

    .tabla-lineas .col-producto {
        min-width: 250px;
    }

    .tabla-lineas .col-unidad {
        width: 60px;
        text-align: center;
    }

    .tabla-lineas .col-cantidad {
        width: 90px;
    }

    .tabla-lineas .col-costo {
        width: 100px;
    }

    .tabla-lineas .col-iva {
        width: 80px;
        background: #fff3cd;
    }

    .tabla-lineas .col-total {
        width: 100px;
    }

    .tabla-lineas .col-acciones {
        width: 50px;
    }

    .tabla-lineas .valor-calculado {
        background: #e9ecef;
        text-align: right;
        padding-right: 10px;
        font-weight: 500;
    }

    .tabla-lineas .valor-iva {
        background: #fff3cd;
        text-align: right;
        padding-right: 10px;
    }

    /* Totales mejorados */
    .totales-grid {
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-width: 350px;
        margin-left: auto;
    }

    .total-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 15px;
        background: #f8f9fa;
        border-radius: 6px;
    }

    .total-item.total-final {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        font-size: 1.1rem;
    }

    .total-label {
        font-weight: 500;
    }

    .total-value {
        font-weight: 700;
    }

    .total-value.iva {
        color: #856404;
    }

    /* Badges */
    .badge-tipo {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-tipo.local {
        background: #d4edda;
        color: #155724;
    }

    .badge-tipo.import {
        background: #cce5ff;
        color: #004085;
    }

    .badge-moneda {
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .badge-moneda.bob {
        background: #fff3cd;
        color: #856404;
    }

    .badge-moneda.usd {
        background: #d1ecf1;
        color: #0c5460;
    }

    /* ========================================
   ESTILOS PARA MODAL DE DEVOLUCIÓN
   ======================================== */

    .ingreso-card {
        background: white;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 12px;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .ingreso-card:hover {
        border-color: #007bff;
        box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
        transform: translateY(-2px);
    }

    .ingreso-card.selected {
        border-color: #28a745;
        background: #f0f9f4;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
    }

    .ingreso-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
    }

    .ingreso-numero {
        font-weight: 700;
        font-size: 1rem;
        color: #1a1a2e;
    }

    .ingreso-fecha {
        font-size: 0.85rem;
        color: #6c757d;
        margin-top: 4px;
    }

    .badge-factura {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .badge-factura.con {
        background: #d4edda;
        color: #155724;
    }

    .badge-factura.sin {
        background: #fff3cd;
        color: #856404;
    }

    .ingreso-proveedor {
        font-size: 0.9rem;
        color: #495057;
        margin-bottom: 8px;
    }

    .ingreso-proveedor i {
        color: #6c757d;
        margin-right: 6px;
    }

    .ingreso-total {
        font-size: 0.95rem;
        color: #6c757d;
        padding-top: 8px;
        border-top: 1px solid #e9ecef;
    }

    .ingreso-total strong {
        color: #1a1a2e;
    }

    .ingresos-container {
        max-height: 400px;
        overflow-y: auto;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    /* Scrollbar personalizado */
    .ingresos-container::-webkit-scrollbar {
        width: 8px;
    }

    .ingresos-container::-webkit-scrollbar-track {
        background: #e9ecef;
        border-radius: 4px;
    }

    .ingresos-container::-webkit-scrollbar-thumb {
        background: #6c757d;
        border-radius: 4px;
    }

    .ingresos-container::-webkit-scrollbar-thumb:hover {
        background: #495057;
    }

    #seccionLineasDevolucion {
        margin-top: 25px;
    }

    /* Encabezados de tabla - texto negro en fondos de colores */
    .tabla-lineas thead th {
        color: #ffffff !important;
    }

    .tabla-lineas thead th[style*="background:#fff3cd"],
    .tabla-lineas thead th[style*="background:#d4edda"],
    .tabla-lineas thead th[style*="background:#fff9e6"] {
        color: #212529 !important;
        font-weight: 700 !important;
    }

    /* ========================================
   ESTILOS PARA MODAL DE HISTORIAL
   ======================================== */

    /* Badges de tipo de movimiento */
    /* Badges de movimiento (Ingreso/Salida) */
    .badge-tipo-mov {
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        min-width: 85px;
        justify-content: center;
    }

    .badge-tipo-mov.ingreso {
        background: #e8f5e9;
        color: #2e7d32;
        border: 1px solid #c8e6c9;
    }

    .badge-tipo-mov.salida {
        background: #fff3e0;
        color: #e65100;
        border: 1px solid #ffe0b2;
    }

    /* Badges de subtipos específicos */
    .badge-subtipo {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
        white-space: nowrap;
    }

    .badge-sub-compra {
        background: #e3f2fd;
        color: #1565c0;
        border: 1px solid #bbdefb;
    }

    .badge-sub-devolucion {
        background: #fff3e0;
        color: #e65100;
        border: 1px solid #ffe0b2;
    }

    .badge-sub-ajuste {
        background: #f3e5f5;
        color: #7b1fa2;
        border: 1px solid #e1bee7;
    }

    .badge-sub-inicial {
        background: #e8eaf6;
        color: #283593;
        border: 1px solid #c5cae9;
    }

    .badge-sub-produccion {
        background: #e8f5e9;
        color: #2e7d32;
        border: 1px solid #c8e6c9;
    }

    .badge-sub-venta {
        background: #f1f8e9;
        color: #33691e;
        border: 1px solid #dcedc8;
    }

    .badge-sub-muestras {
        background: #e0f2f1;
        color: #00695c;
        border: 1px solid #b2dfdb;
    }

    /* Badges de estado */
    .badge-estado {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .badge-estado.confirmado {
        background: #d4edda;
        color: #155724;
    }

    .badge-estado.anulado {
        background: #f8d7da;
        color: #721c24;
    }

    .badge-estado.pendiente {
        background: #fff3cd;
        color: #856404;
    }

    /* Botones de acciones */
    .btn-icon.ver {
        background: #17a2b8;
        color: white;
    }

    .btn-icon.ver:hover {
        background: #138496;
    }

    .btn-icon.anular {
        background: #dc3545;
        color: white;
    }

    .btn-icon.anular:hover {
        background: #c82333;
    }

    /* Tabla de detalle */
    .tabla-detalle {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        font-size: 0.9rem;
    }

    .tabla-detalle thead th {
        background: #343a40;
        color: white;
        padding: 12px 10px;
        font-size: 0.8rem;
        text-transform: uppercase;
    }

    .tabla-detalle tbody td {
        padding: 12px 10px;
        border-bottom: 1px solid #e9ecef;
    }

    .tabla-detalle tbody tr:hover {
        background: #f8f9fa;
    }

    .tabla-detalle tfoot td {
        padding: 15px 10px;
        font-size: 1rem;
    }

    .alert-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-info i {
        font-size: 1.2rem;
    }

    /* Clase para ocultar elementos */
    .d-none {
        display: none !important;
    }

    /* Mejoras visuales para las secciones dinámicas */
    #seccionArea,
    #seccionMotivo,
    #seccionAutorizacion,
    #seccionUbicacion {
        animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .kardex-tabs {
        display: flex;
        gap: 5px;
        margin-bottom: 20px;
        border-bottom: 2px solid #dee2e6;
    }

    .kardex-tab {
        padding: 10px 20px;
        background: #e9ecef;
        color: #495057;
        border: none;
        border-radius: 8px 8px 0 0;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .kardex-tab:hover {
        background: #dee2e6;
    }

    .kardex-tab.active {
        background: #1a237e;
        color: white;
    }

    /* Estilos para el kardex */
    .kardex-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .kardex-table th {
        background: #343a40;
        color: white;
        padding: 10px 8px;
        text-align: center;
        font-size: 0.75rem;
        text-transform: uppercase;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .kardex-table td {
        padding: 8px;
        border-bottom: 1px solid #e9ecef;
        text-align: right;
    }

    .kardex-table tr.entrada {
        background: #e8f5e9;
    }

    .kardex-table tr.salida {
        background: #ffebee;
    }

    .kardex-table tr.saldo-inicial {
        background: #f5f5f5;
        font-weight: bold;
    }
</style>

<div class="mp-module">
    <!-- HEADER -->
    <div class="mp-header">
        <div class="mp-header-left">
            <a href="index.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver</a>
            <div class="mp-title">
                <div class="mp-title-icon"><i class="fas <?php echo $tipoIcono; ?>"></i></div>
                <div>
                    <h1>Materias Primas</h1>
                    <p>Gestión de inventario de materias primas</p>
                </div>
            </div>
        </div>
        <div class="mp-header-actions">
            <button class="btn-action btn-ingreso" onclick="abrirModalIngreso()"><i class="fas fa-arrow-down"></i>
                Ingreso</button>
            <button class="btn-action btn-salida" onclick="abrirModalSalida()"><i class="fas fa-arrow-up"></i>
                Salida</button>
            <button class="btn-action btn-historial" onclick="abrirModalHistorial()"><i class="fas fa-history"></i>
                Historial</button>
            <button class="btn-action btn-nuevo" onclick="abrirModalNuevoItem()"><i class="fas fa-plus"></i> Nuevo
                Item</button>
        </div>
    </div>

    <!-- KPIs -->
    <div class="mp-kpis">
        <div class="kpi-card">
            <div class="kpi-icon items"><i class="fas fa-boxes"></i></div>
            <div class="kpi-info">
                <div class="kpi-label">Total Items</div>
                <div class="kpi-value" id="kpiItems">0</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon valor"><i class="fas fa-dollar-sign"></i></div>
            <div class="kpi-info">
                <div class="kpi-label">Valor Total</div>
                <div class="kpi-value" id="kpiValor">Bs. 0</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon alertas"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="kpi-info">
                <div class="kpi-label">Alertas Stock</div>
                <div class="kpi-value danger" id="kpiAlertas">0</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon categorias"><i class="fas fa-folder"></i></div>
            <div class="kpi-info">
                <div class="kpi-label">Categorías</div>
                <div class="kpi-value" id="kpiCategorias">0</div>
            </div>
        </div>
    </div>

    <!-- CATEGORÍAS -->
    <h3 style="margin-bottom: 15px;"><i class="fas fa-folder-open"></i> Categorías</h3>
    <div class="categorias-grid" id="categoriasGrid">
        <p style="padding: 20px; text-align: center;"><i class="fas fa-spinner fa-spin"></i> Cargando...</p>
    </div>

    <!-- SUBCATEGORÍAS -->
    <div id="subcategoriasSection" style="display: none;">
        <h4 style="margin-bottom: 10px;"><i class="fas fa-folder"></i> Subcategorías de <span
                id="subcategoriaTitulo"></span></h4>
        <div class="categorias-grid" id="subcategoriasGrid"></div>
    </div>

    <!-- PRODUCTOS -->
    <div class="mp-productos" id="productosSection" style="display: none;">
        <div class="productos-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <h3 id="productosTitulo">Productos</h3>
                <span class="categoria-badge" id="productosCount">0 items</span>
            </div>
            <input type="text" id="buscarProducto" placeholder="Buscar..." onkeyup="filtrarProductos()"
                style="padding: 8px 15px; border: 1px solid #dee2e6; border-radius: 8px; width: 200px;">
        </div>
        <table class="productos-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Stock</th>
                    <th>Unidad</th>
                    <th>Estado</th>
                    <th>Costo</th>
                    <th>Valor</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="productosBody"></tbody>
        </table>
    </div>
</div>

<!-- MODALES -->
<!-- Modal Nuevo/Editar Item -->
<div class="modal" id="modalItem">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-box"></i> <span id="modalItemTitulo">Nuevo Item</span></h3>
            <button class="modal-close" onclick="cerrarModal('modalItem')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formItem">
                <input type="hidden" id="itemId">
                <div class="form-row">
                    <div class="form-group"><label>Código *</label><input type="text" id="itemCodigo" required></div>
                    <div class="form-group"><label>Nombre *</label><input type="text" id="itemNombre" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Categoría *</label><select id="itemCategoria" required
                            onchange="cargarSubcategoriasItem()"></select></div>
                    <div class="form-group"><label>Subcategoría</label><select id="itemSubcategoria">
                            <option value="">Sin subcategoría</option>
                        </select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Stock Actual</label><input type="number" id="itemStockActual"
                            step="0.01" value="0"></div>
                    <div class="form-group"><label>Stock Mínimo</label><input type="number" id="itemStockMinimo"
                            step="0.01" value="0"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Unidad *</label><select id="itemUnidad" required></select></div>
                    <div class="form-group"><label>Costo Unitario (Bs.)</label><input type="number" id="itemCosto"
                            step="0.01" value="0"></div>
                </div>
                <div class="form-group"><label>Descripción</label><textarea id="itemDescripcion"></textarea></div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModal('modalItem')">Cancelar</button>
            <button class="btn btn-success" onclick="guardarItem()"><i class="fas fa-save"></i> Guardar</button>
        </div>
    </div>
</div>

<!-- Modal Ingreso -->
<div class="modal" id="modalIngreso">
    <div class="modal-content xlarge">
        <div class="modal-header">
            <h3><i class="fas fa-arrow-down"></i> Ingreso de Materias Primas</h3>
            <button class="modal-close" onclick="cerrarModal('modalIngreso')"
                style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body">

            <!-- ⭐ SELECTOR DE TIPO DE INGRESO -->
            <div class="form-row"
                style="background: linear-gradient(135deg, #1a237e, #4fc3f7); padding: 15px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(26, 35, 126, 0.3);">
                <div class="form-group" style="flex: 1; margin: 0;">
                    <label style="color: white; font-weight: 600; font-size: 0.95rem;">
                        <i class="fas fa-clipboard-list"></i> Tipo de Ingreso
                        <span style="color: #ffe066;">*</span>
                    </label>
                    <select id="ingresoTipoIngreso" required class="form-control" onchange="cambiarTipoIngreso()"
                        style="font-size: 1.05rem; font-weight: 500; padding: 12px;">
                        <option value="">Seleccione tipo de ingreso...</option>
                    </select>
                </div>
            </div>

            <!-- Fila 1: Documento y Fecha -->
            <div class="form-row">
                <div class="form-group">
                    <label>Documento Nº</label>
                    <input type="text" id="ingresoDocumento" readonly style="background:#e9ecef; font-weight:bold;">
                </div>
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" id="ingresoFecha">
                </div>
            </div>

            <!-- ⭐ SECCIÓN PROVEEDOR (se muestra/oculta dinámicamente) -->
            <div id="seccionProveedor" class="d-none">
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo Proveedor</label>
                        <select id="ingresoTipoProveedor" onchange="filtrarProveedoresIngreso()">
                            <option value="TODOS">📋 Todos los proveedores</option>
                            <option value="LOCAL">🇧🇴 Proveedores Locales</option>
                            <option value="IMPORTACION">🌎 Proveedores Importación</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Proveedor</label>
                        <select id="ingresoProveedor" onchange="actualizarInfoProveedor()">
                            <option value="">Seleccione proveedor...</option>
                        </select>
                    </div>
                </div>

                <!-- Info Proveedor (se muestra al seleccionar) -->
                <div id="infoProveedorBox" class="info-proveedor-box" style="display:none;">
                    <span id="infoProveedorTipo" class="badge-tipo"></span>
                    <span id="infoProveedorMoneda" class="badge-moneda"></span>
                    <span id="infoProveedorPago"></span>
                </div>
            </div>

            <!-- ⭐ SECCIÓN FACTURA (se muestra/oculta) -->
            <div id="seccionFactura" class="d-none">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nº Factura / Documento</label>
                        <input type="text" id="ingresoReferencia" placeholder="Ej: FAC-001234">
                    </div>
                    <div class="form-group" style="display:flex; align-items:flex-end;">
                        <div class="checkbox-iva" style="margin:0; flex:1;">
                            <input type="checkbox" id="ingresoConFactura" onchange="toggleModoFactura()">
                            <label for="ingresoConFactura"><strong>Con Factura</strong> - Incluir IVA 13%</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ⭐ SECCIÓN ÁREA DE PRODUCCIÓN (para devoluciones) -->
            <div id="seccionArea" class="d-none"
                style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label>🏭 Área que Devuelve <span class="text-danger">*</span></label>
                        <select id="ingresoArea" class="form-control">
                            <option value="">Seleccione área...</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>👤 Responsable Entrega</label>
                        <input type="text" id="ingresoResponsableEntrega" class="form-control"
                            placeholder="Nombre del operario">
                    </div>
                </div>
            </div>

            <!-- ⭐ SECCIÓN MOTIVO (para devoluciones y ajustes) -->
            <div id="seccionMotivo" class="d-none"
                style="background: #e7f3ff; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                <div class="form-group" style="margin: 0;">
                    <label>📋 Motivo <span class="text-danger">*</span></label>
                    <select id="ingresoMotivo" class="form-control">
                        <option value="">Seleccione motivo...</option>
                    </select>
                </div>
            </div>

            <!-- ⭐ SECCIÓN AUTORIZACIÓN (para ajustes) -->
            <div id="seccionAutorizacion" class="d-none"
                style="background: #f8d7da; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                <div class="form-group" style="margin: 0;">
                    <label>✅ Autorizado Por <span class="text-danger">*</span></label>
                    <select id="ingresoAutorizadoPor" class="form-control">
                        <option value="">Seleccione usuario...</option>
                        <!-- Se llenará con usuarios con permisos de autorización -->
                    </select>
                </div>
            </div>

            <!-- ⭐ SECCIÓN UBICACIÓN (para inventario inicial) -->
            <div id="seccionUbicacion" class="d-none"
                style="background: #d1ecf1; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label>📍 Ubicación / Almacén</label>
                        <input type="text" id="ingresoUbicacion" class="form-control"
                            placeholder="Ej: Almacén Principal">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>👤 Responsable Conteo</label>
                        <input type="text" id="ingresoResponsableConteo" class="form-control" placeholder="Nombre">
                    </div>
                </div>
            </div>

            <!-- Separador -->
            <hr style="margin: 20px 0; border-color: #e9ecef;">

            <!-- Filtro de Productos por Categoría/Subcategoría -->
            <div class="filtros-productos">
                <h4 style="margin-bottom:10px;"><i class="fas fa-filter"></i> Filtrar Productos</h4>
                <div class="form-row" style="margin-bottom:15px;">
                    <div class="form-group">
                        <label>Categoría</label>
                        <select id="ingresoFiltroCat" onchange="filtrarProductosIngreso()">
                            <option value="">Todas las categorías</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Subcategoría</label>
                        <select id="ingresoFiltroSubcat" onchange="filtrarProductosIngreso()">
                            <option value="">Todas las subcategorías</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tabla de Líneas -->
            <h4><i class="fas fa-list"></i> Líneas de Ingreso</h4>
            <div class="tabla-ingreso-container">
                <table class="tabla-lineas" id="tablaLineasIngreso">
                    <thead id="theadIngreso">
                        <!-- Se genera dinámicamente según tipo de ingreso -->
                    </thead>
                    <tbody id="ingresoLineasBody"></tbody>
                </table>
            </div>

            <button class="btn btn-primary" onclick="agregarLineaIngreso()" style="margin-top:10px;">
                <i class="fas fa-plus"></i> Agregar Línea
            </button>

            <!-- Totales -->
            <div class="totales-box" style="margin-top: 20px;">
                <div class="totales-grid">
                    <div class="total-item">
                        <span class="total-label">Total Neto:</span>
                        <span class="total-value" id="ingresoTotalNeto">Bs. 0.00</span>
                    </div>
                    <div class="total-item" id="rowIVA" style="display:none;">
                        <span class="total-label">IVA 13%:</span>
                        <span class="total-value iva" id="ingresoIVA">Bs. 0.00</span>
                    </div>
                    <div class="total-item total-final">
                        <span class="total-label">TOTAL DOCUMENTO:</span>
                        <span class="total-value" id="ingresoTotal">Bs. 0.00</span>
                    </div>
                </div>
            </div>

            <!-- Observaciones -->
            <div class="form-group" style="margin-top: 15px;">
                <label>Observaciones</label>
                <textarea id="ingresoObservaciones" placeholder="Notas adicionales sobre este ingreso..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModal('modalIngreso')">Cancelar</button>
            <button class="btn btn-success" onclick="guardarIngreso()"><i class="fas fa-check"></i> Registrar
                Ingreso</button>
        </div>
    </div>
</div>

<!-- Modal Salida - ACTUALIZADO -->
<div class="modal" id="modalSalida">
    <div class="modal-content large">
        <div class="modal-header">
            <h3><i class="fas fa-arrow-up"></i> Salida de Materias Primas</h3>
            <button class="modal-close" onclick="cerrarModal('modalSalida')"
                style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Fila 1: Documento y Fecha -->
            <div class="form-row">
                <div class="form-group">
                    <label>Documento Nº</label>
                    <input type="text" id="salidaDocumento" readonly style="background:#e9ecef; font-weight:bold;">
                </div>
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" id="salidaFecha">
                </div>
            </div>

            <!-- Fila 2: Tipo de Salida y Referencia -->
            <div class="form-row">
                <div class="form-group">
                    <label>Tipo de Salida</label>
                    <select id="salidaTipo" onchange="actualizarNumeroSalida()">
                        <option value="" selected disabled>Seleccione tipo de salida</option>
                        <option value="PRODUCCION">📦 Producción (entrega a tejido)</option>
                        <option value="VENTA">💰 Venta de Materias Primas</option>
                        <option value="MUESTRAS">🔬 Desarrollo de Muestras/Prototipos</option>
                        <option value="AJUSTE">⚙️ Ajuste de Inventario</option>
                        <option value="DEVOLUCION">↩️ Devolución a Proveedor</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Referencia</label>
                    <input type="text" id="salidaReferencia" placeholder="Ej: OF-123, Cliente XYZ">
                </div>
            </div>

            <!-- Separador -->
            <hr style="margin: 20px 0; border-color: #e9ecef;">

            <!-- Filtro de Productos por Categoría/Subcategoría -->
            <div class="filtros-productos">
                <h4 style="margin-bottom:10px;"><i class="fas fa-filter"></i> Filtrar Productos</h4>
                <div class="form-row" style="margin-bottom:15px;">
                    <div class="form-group">
                        <label>Categoría</label>
                        <select id="salidaFiltroCat" onchange="filtrarProductosSalida()">
                            <option value="">Todas las categorías</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Subcategoría</label>
                        <select id="salidaFiltroSubcat" onchange="filtrarProductosSalida()">
                            <option value="">Todas las subcategorías</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tabla de Líneas -->
            <h4><i class="fas fa-list"></i> Líneas de Salida</h4>
            <div class="tabla-ingreso-container">
                <table class="tabla-lineas" id="tablaLineasSalida">
                    <thead>
                        <tr>
                            <th style="min-width:250px;">PRODUCTO</th>
                            <th style="width:120px; text-align:center;">STOCK DISPONIBLE</th>
                            <th style="width:60px; text-align:center;">UNID.</th>
                            <th style="width:100px; background:#fff3cd; text-align:center;">CANTIDAD</th>
                            <th style="width:130px; text-align:center;">CPP</th>
                            <th style="width:120px; text-align:center;">SUBTOTAL</th>
                            <th style="width:50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="salidaLineasBody"></tbody>
                </table>
            </div>

            <button class="btn btn-primary" onclick="agregarLineaSalida()" style="margin-top:10px;">
                <i class="fas fa-plus"></i> Agregar Línea
            </button>

            <!-- Totales -->
            <div class="totales-box" style="margin-top: 20px;">
                <div class="totales-grid">
                    <div class="total-item total-final">
                        <span class="total-label">TOTAL SALIDA:</span>
                        <span class="total-value" id="salidaTotal">Bs. 0.00</span>
                    </div>
                </div>
            </div>

            <!-- Observaciones -->
            <div class="form-group" style="margin-top: 15px;">
                <label>Observaciones / Motivo <span id="motivoObligatorio"
                        style="color:#dc3545; display:none;">*</span></label>
                <textarea id="salidaObservaciones" placeholder="Notas adicionales sobre esta salida..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModal('modalSalida')">Cancelar</button>
            <button class="btn btn-danger" onclick="guardarSalida()"><i class="fas fa-check"></i> Registrar
                Salida</button>
        </div>
    </div>
</div>
<!-- Modal Historial -->
<div class="modal" id="modalHistorial">
    <div class="modal-content large">
        <div class="modal-header">
            <h3><i class="fas fa-history"></i> Historial de Movimientos</h3>
            <button class="modal-close" onclick="cerrarModal('modalHistorial')"
                style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Filtros -->
            <div
                style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <div class="form-group" style="flex:1; min-width: 150px;">
                    <label style="font-size:0.85rem; font-weight:600;">Desde</label>
                    <input type="date" id="historialDesde"
                        style="padding:8px; border:1px solid #dee2e6; border-radius:6px; width:100%;">
                </div>
                <div class="form-group" style="flex:1; min-width: 150px;">
                    <label style="font-size:0.85rem; font-weight:600;">Hasta</label>
                    <input type="date" id="historialHasta"
                        style="padding:8px; border:1px solid #dee2e6; border-radius:6px; width:100%;">
                </div>
                <div class="form-group" style="flex:1; min-width: 150px;">
                    <label style="font-size:0.85rem; font-weight:600;">Tipo</label>
                    <select id="historialTipo"
                        style="padding:8px; border:1px solid #dee2e6; border-radius:6px; width:100%;">
                        <option value="">📋 Todos los movimientos</option>

                        <optgroup label="➕ INGRESOS">
                            <option value="INGRESO">📥 Todos los ingresos</option>
                            <option value="COMPRA">🛒 Compras</option>
                            <option value="DEVOLUCION_PRODUCCION">↩️ Devolución Producción</option>
                            <option value="AJUSTE_POSITIVO">⚙️ Ajuste Positivo</option>
                            <option value="INGRESO_INICIAL">📦 Ingreso Inicial</option>
                        </optgroup>

                        <optgroup label="➖ SALIDAS">
                            <option value="SALIDA">📤 Todas las salidas</option>
                            <option value="PRODUCCION">🏭 Producción</option>
                            <option value="VENTA">💰 Venta</option>
                            <option value="DEVOLUCION">↩️ Devolución</option>
                            <option value="MUESTRAS">🎁 Muestras</option>
                            <option value="AJUSTE">⚙️ Ajuste</option>
                        </optgroup>
                    </select>
                </div>
                <div class="form-group" style="flex:1; min-width: 150px;">
                    <label style="font-size:0.85rem; font-weight:600;">Estado</label>
                    <select id="historialEstado"
                        style="padding:8px; border:1px solid #dee2e6; border-radius:6px; width:100%;">
                        <option value="todos">Todos</option>
                        <option value="CONFIRMADO">Confirmados</option>
                        <option value="ANULADO">Anulados</option>
                    </select>
                </div>
                <div class="form-group" style="align-self: flex-end;">
                    <button class="btn btn-primary" onclick="buscarHistorial()" style="padding:9px 20px;">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </div>

            <!-- Tabla -->
            <div style="overflow-x: auto;">
                <table class="productos-table">
                    <thead>
                        <tr>
                            <th style="width:90px;">Fecha</th>
                            <th style="width:150px;">Documento</th>
                            <th style="width:110px;">Movimiento</th>
                            <th style="width:140px;">Tipo</th>
                            <th style="width:110px; text-align:right;">Total</th>
                            <th style="width:110px; text-align:center;">Estado</th>
                            <th>Observaciones</th>
                            <th style="width:90px; text-align:center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="historialBody">
                        <tr>
                            <td colspan="8" style="text-align:center; padding:30px; color:#6c757d;">
                                <i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i><br>
                                Cargando...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModal('modalHistorial')">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal Detalle de Documento -->
<div class="modal" id="modalDetalle">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="detalleTitulo"><i class="fas fa-file-alt"></i> Detalle de Documento</h3>
            <button class="modal-close" onclick="cerrarModal('modalDetalle')"
                style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body" id="detalleContenido">
            <!-- Se carga dinámicamente -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-danger" id="btnAnularDetalle" style="display:none;">
                <i class="fas fa-ban"></i> Anular Documento
            </button>
            <button class="btn btn-secondary" onclick="cerrarModal('modalDetalle')">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal Kardex -->
<div class="modal" id="modalKardex">
    <div class="modal-content large">
        <div class="modal-header">
            <h3><i class="fas fa-book"></i> Kardex - <span id="kardexProducto"></span></h3>
            <button class="modal-close" onclick="cerrarModal('modalKardex')"
                style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Información del producto -->
            <div id="kardexProductoInfo"
                style="background: #f8f9fa; padding: 12px 15px; border-radius: 8px; margin-bottom: 15px; font-size: 0.9rem;">
            </div>

            <!-- Pestañas para Físico y Valorado -->
            <div class="kardex-tabs"
                style="display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 2px solid #dee2e6;">
                <button class="kardex-tab active" data-tipo="valorado" onclick="cambiarTabKardex('valorado')"
                    style="padding: 10px 20px; background: #1a237e; color: white; border: none; border-radius: 8px 8px 0 0; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-dollar-sign"></i> Kardex Valorado
                </button>
                <button class="kardex-tab" data-tipo="fisico" onclick="cambiarTabKardex('fisico')"
                    style="padding: 10px 20px; background: #e9ecef; color: #495057; border: none; border-radius: 8px 8px 0 0; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-boxes"></i> Kardex Físico
                </button>
            </div>

            <!-- Filtros de fecha -->
            <div
                style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; align-items: flex-end;">
                <div class="form-group" style="flex:1; min-width: 150px; margin: 0;">
                    <label style="font-size:0.85rem; font-weight:600;">Desde</label>
                    <input type="date" id="kardexDesde"
                        style="padding:8px; border:1px solid #dee2e6; border-radius:6px; width:100%;">
                </div>
                <div class="form-group" style="flex:1; min-width: 150px; margin: 0;">
                    <label style="font-size:0.85rem; font-weight:600;">Hasta</label>
                    <input type="date" id="kardexHasta"
                        style="padding:8px; border:1px solid #dee2e6; border-radius:6px; width:100%;">
                </div>
                <div class="form-group" style="margin: 0;">
                    <button class="btn btn-primary" onclick="buscarKardex()" style="padding:9px 20px;">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
                <div class="form-group" style="margin: 0;">
                    <button class="btn btn-info" onclick="exportarKardex()" style="padding:9px 20px;">
                        <i class="fas fa-download"></i> Exportar
                    </button>
                </div>
                <div class="form-group" style="margin: 0;">
                    <button class="btn btn-secondary" onclick="imprimirKardex()" style="padding:9px 20px;">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>
            </div>

            <!-- Tabla de kardex (se actualiza dinámicamente según la pestaña) -->
            <div style="overflow-x: auto; max-height: 400px;">
                <table class="kardex-table">
                    <thead id="kardexTableHead">
                        <!-- El encabezado se genera dinámicamente según el tipo de kardex -->
                        <tr>
                            <th width="120">Fecha</th>
                            <th>Documento</th>
                            <th width="90">Entrada</th>
                            <th width="90">Salida</th>
                            <th width="90">Saldo</th>
                            <th width="110">Valor Entrada</th>
                            <th width="110">Valor Salida</th>
                            <th width="110">Saldo Valor</th>
                            <th width="100">CPP</th>
                        </tr>
                    </thead>
                    <tbody id="kardexBody">
                        <tr>
                            <td colspan="9" style="text-align:center; padding:30px; color:#6c757d;">
                                <i class="fas fa-search" style="font-size:2rem;"></i><br>
                                Seleccione un rango de fechas y presione Buscar
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: space-between; width: 100%;">
            <button class="btn btn-warning" onclick="recalcularKardex(kardexActual.idInventario)"
                style="background: #ffc107; color: #212529; border: none;">
                <i class="fas fa-sync-alt"></i> Recalcular Costos
            </button>
            <button class="btn btn-secondary" onclick="cerrarModal('modalKardex')">Cerrar</button>
        </div>
    </div>
</div>
<!-- Modal Devolución a Proveedor -->
<div class="modal" id="modalDevolucion">
    <div class="modal-content xlarge">
        <div class="modal-header">
            <h3><i class="fas fa-undo"></i> Devolución a Proveedor</h3>
            <button class="modal-close" onclick="cerrarModal('modalDevolucion')"
                style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Información del Documento -->
            <div class="form-row">
                <div class="form-group">
                    <label>Documento Nº</label>
                    <input type="text" id="devolucionDocumento" readonly style="background:#e9ecef; font-weight:bold;">
                </div>
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" id="devolucionFecha">
                </div>
            </div>

            <div class="form-group">
                <label>Referencia</label>
                <input type="text" id="devolucionReferencia" placeholder="Ej: Hilo defectuoso, Error en entrega">
            </div>

            <!-- Separador -->
            <hr style="margin: 20px 0; border-color: #e9ecef;">

            <!-- Paso 1: Seleccionar Ingreso -->
            <h4><i class="fas fa-search"></i> Paso 1: Seleccione el ingreso a devolver</h4>
            <p style="color:#6c757d; font-size:0.9rem; margin-bottom:15px;">
                Haga clic en el ingreso del cual desea devolver productos
            </p>

            <!-- 🔍 BUSCADOR DE INGRESOS -->
            <div class="form-group" style="margin-bottom: 20px;">
                <div class="input-with-icon" style="position: relative;">
                    <i class="fas fa-search"
                        style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #6c757d;"></i>
                    <input type="text" id="buscarIngresoDevolucion"
                        placeholder="Buscar por Nº de Documento o Proveedor..." oninput="filtrarIngresosDevolucion()"
                        style="width: 100%; padding: 12px 12px 12px 40px; border: 2px solid #e9ecef; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s;">
                </div>
            </div>

            <div class="ingresos-container" id="ingresosDisponibles">
                <p style="padding:20px; text-align:center;"><i class="fas fa-spinner fa-spin"></i> Cargando ingresos...
                </p>
            </div>

            <!-- Separador -->
            <hr style="margin: 25px 0; border-color: #e9ecef;">

            <!-- Paso 2: Líneas del Ingreso -->
            <div id="seccionLineasDevolucion" style="display:none;">
                <h4><i class="fas fa-list"></i> Paso 2: Indique las cantidades a devolver</h4>
                <p style="color:#6c757d; font-size:0.9rem; margin-bottom:15px;">
                    Las cantidades se valoran al <strong>costo de adquisición original</strong>
                    ${document.getElementById('devolucionFecha') ? '' : '(con IVA si corresponde)'}
                </p>

                <div class="tabla-ingreso-container">
                    <table class="tabla-lineas">
                        <thead id="theadDevolucion">
                            <tr>
                                <th style="min-width:250px;">PRODUCTO</th>
                                <th style="width:100px; text-align:center;">CANT.<br>ORIGINAL</th>
                                <th style="width:100px; text-align:center;">DEVUELTO<br>ANTES</th>
                                <th style="width:100px; text-align:center;">DISPONIBLE</th>
                                <th style="width:60px; text-align:center;">UNID.</th>
                                <th style="width:100px; text-align:center;">CANTIDAD<br>A DEVOLVER</th>
                                <th style="width:120px; text-align:center;">COSTO ADQ.<br>(NETO)</th>
                                <th style="width:100px; text-align:center;" id="colIVADev">IVA 13%</th>
                                <th style="width:120px; text-align:center;">SUBTOTAL</th>
                            </tr>
                        </thead>
                        <tbody id="devolucionLineasBody"></tbody>
                    </table>
                </div>

                <!-- Totales -->
                <div class="totales-box" style="margin-top: 20px;">
                    <div class="totales-grid">
                        <div class="total-item">
                            <span class="total-label">Total Neto:</span>
                            <span class="total-value" id="devolucionTotalNeto">Bs. 0.00</span>
                        </div>
                        <div class="total-item" id="rowDevIVA" style="display:none;">
                            <span class="total-label">IVA 13%:</span>
                            <span class="total-value iva" id="devolucionIVA">Bs. 0.00</span>
                        </div>
                        <div class="total-item total-final">
                            <span class="total-label">TOTAL A RECLAMAR:</span>
                            <span class="total-value" id="devolucionTotal">Bs. 0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="form-group" style="margin-top: 15px;">
                    <label>Observaciones / Motivo de Devolución</label>
                    <textarea id="devolucionObservaciones"
                        placeholder="Describa el motivo de la devolución..."></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModal('modalDevolucion')">Cancelar</button>
            <button class="btn btn-success" onclick="guardarDevolucion()">
                <i class="fas fa-check"></i> Registrar Devolución
            </button>
        </div>
    </div>
</div>
<!-- Modal Detalle de Documento -->
<div class="modal" id="modalDetalle">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="detalleTitulo"><i class="fas fa-file-alt"></i> Detalle de Documento</h3>
            <button class="modal-close" onclick="cerrarModal('modalDetalle')">&times;</button>
        </div>
        <div class="modal-body" id="detalleContenido">
            <!-- Se carga dinámicamente -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-danger" id="btnAnularDetalle" style="display:none;">
                <i class="fas fa-ban"></i> Anular Documento
            </button>
            <button class="btn btn-secondary" onclick="cerrarModal('modalDetalle')">Cerrar</button>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="js/materias_primas.js"></script>
<script src="js/materias_primas_dinamico.js"></script>
<script src="js/devolucion_proveedor.js"></script>
<script src="js/historial_movimientos.js"></script>
<script src="js/kardex_mp.js"></script>
<!-- Inyectar fecha del servidor como variable global -->
<script>
    window.FECHA_SERVIDOR = '<?php echo date("Y-m-d"); ?>';
    console.log('📅 Fecha del servidor inyectada:', window.FECHA_SERVIDOR);
</script>
<!-- Control de Fechas - INLINE para evitar problemas de caché -->
<script>
(function() {
    'use strict';
    
    const FECHA_SERVIDOR = window.FECHA_SERVIDOR || '<?php echo date("Y-m-d"); ?>';
    console.log('🚀 Control de fechas iniciado con fecha:', FECHA_SERVIDOR);
    
    function configurarInputFecha(input) {
        if (!input.value || input.value === '' || input.value > FECHA_SERVIDOR) {
            input.value = FECHA_SERVIDOR;
        }
        input.setAttribute('max', FECHA_SERVIDOR);
        input.setAttribute('readonly', 'readonly');
        input.style.backgroundColor = '#e9ecef';
        input.style.cursor = 'not-allowed';
        input.setAttribute('title', 'Fecha automática del servidor: ' + FECHA_SERVIDOR);
        console.log('✅ Campo configurado:', input.id || input.name, '=', FECHA_SERVIDOR);
    }
    
    function configurarTodos() {
        const inputs = document.querySelectorAll('input[type="date"]');
        console.log('🔍 Encontrados', inputs.length, 'campos de fecha');
        inputs.forEach(configurarInputFecha);
    }
    
    // Observador para campos dinámicos
    const observer = new MutationObserver(() => {
        document.querySelectorAll('input[type="date"]').forEach(input => {
            if (!input.hasAttribute('data-fecha-configurada')) {
                input.setAttribute('data-fecha-configurada', 'true');
                configurarInputFecha(input);
            }
        });
    });
    
    function init() {
        configurarTodos();
        observer.observe(document.body, { childList: true, subtree: true });
        setInterval(configurarTodos, 3000);
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
<?php require_once '../../includes/footer.php'; ?>