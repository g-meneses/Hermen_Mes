<?php
/**
 * Módulo de Inventarios - Dashboard Profesional
 * Sistema MES Hermen Ltda.
 * Versión: 2.0
 * 
 * Características:
 * - Dashboard con KPIs por tipo de inventario
 * - Vista por categorías con valores
 * - Control de acceso por tipo (preparado)
 * - Movimientos contextuales
 */

require_once '../../config/database.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Centro de Inventarios';
$currentPage = 'centro_inventarios';
require_once '../../includes/header.php';
?>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<style>
    /* ========== VARIABLES CSS ========== */
    :root {
        --color-mp: #007bff;
        /* Materias Primas - Azul */
        --color-caq: #3f51b5;
        /* Colorantes - Índigo Profundo */
        --color-emp: #fd7e14;
        /* Empaque - Naranja */
        --color-acc: #0097a7;
        /* Accesorios - Cian Oscuro */
        --color-wip: #17a2b8;
        /* En Proceso - Cyan */
        --color-pt: #28a745;
        /* Terminados - Verde */
        --color-rep: #6c757d;
        /* Repuestos - Gris */
    }

    /* ========== LAYOUT PRINCIPAL ========== */
    .inv-module {
        padding: 20px;
        background: #f4f6f9;
        min-height: calc(100vh - 60px);
    }

    .inv-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .inv-header h1 {
        font-size: 1.8rem;
        color: #1a1a2e;
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 0;
    }

    .inv-header h1 i {
        color: #007bff;
    }

    /* ========== RESUMEN GENERAL (KPIs) ========== */
    .inv-kpis {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 25px;
    }

    .kpi-card {
        background: white;
        border-radius: 16px;
        padding: 20px 25px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    }

    .kpi-icon {
        width: 60px;
        height: 60px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }

    .kpi-icon.items {
        background: linear-gradient(135deg, #1a237e 0%, #4fc3f7 100%);
    }

    .kpi-icon.valor {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    }

    .kpi-icon.alertas {
        background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
    }

    .kpi-icon.tipos {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .kpi-info {
        flex: 1;
    }

    .kpi-label {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 4px;
    }

    .kpi-value {
        font-size: 1.6rem;
        font-weight: 700;
        color: #1a1a2e;
    }

    .kpi-value.success {
        color: #28a745;
    }

    .kpi-value.warning {
        color: #ffc107;
    }

    .kpi-value.danger {
        color: #dc3545;
    }

    /* ========== TIPOS DE INVENTARIO (Cards principales) ========== */
    .inv-tipos-section {
        margin-bottom: 30px;
    }

    .section-title {
        font-size: 1.1rem;
        color: #495057;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-title i {
        color: #007bff;
    }

    .inv-tipos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
    }

    .tipo-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 3px solid transparent;
        position: relative;
        overflow: hidden;
    }

    .tipo-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--tipo-color);
    }

    .tipo-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
    }

    .tipo-card.active {
        border-color: var(--tipo-color);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .tipo-card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 15px;
    }

    .tipo-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: white;
        background: var(--tipo-color);
    }

    .tipo-nombre {
        font-size: 0.9rem;
        font-weight: 600;
        color: #1a1a2e;
        line-height: 1.2;
    }

    .tipo-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .tipo-stat {
        text-align: center;
        padding: 8px;
        background: #f8f9fa;
        border-radius: 8px;
    }

    .tipo-stat-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1a1a2e;
    }

    .tipo-stat-label {
        font-size: 0.7rem;
        color: #6c757d;
        text-transform: uppercase;
    }

    .tipo-valor-total {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #e9ecef;
        text-align: center;
    }

    .tipo-valor-total .valor {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--tipo-color);
    }

    .tipo-valor-total .label {
        font-size: 0.7rem;
        color: #6c757d;
    }

    /* ========== ÁREA DE TRABAJO (Tipo Seleccionado) ========== */
    .inv-workspace {
        background: white;
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        display: none;
    }

    .inv-workspace.active {
        display: block;
    }

    .workspace-header {
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #e9ecef;
    }

    .workspace-title {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .workspace-title .icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        color: white;
    }

    .workspace-title h2 {
        margin: 0;
        font-size: 1.3rem;
        color: #1a1a2e;
    }

    .workspace-title .subtitle {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .workspace-actions {
        display: flex;
        gap: 10px;
    }

    .workspace-actions .btn {
        padding: 10px 18px;
        border-radius: 10px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }

    .workspace-actions .btn-ingreso {
        background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        color: white;
    }

    .workspace-actions .btn-salida {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: white;
    }

    .workspace-actions .btn-historial {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        color: white;
    }

    .workspace-actions .btn-nuevo {
        background: linear-gradient(135deg, #1a237e 0%, #4fc3f7 100%);
        color: white;
    }

    .workspace-actions .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    /* ========== CATEGORÍAS DEL TIPO ========== */
    .workspace-body {
        padding: 25px;
    }

    .categorias-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .categoria-card {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 16px;
        padding: 20px;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .categoria-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        border-color: var(--tipo-color);
    }

    .categoria-card.active {
        background: white;
        border-color: var(--tipo-color);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    .categoria-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .categoria-info h4 {
        margin: 0 0 4px 0;
        font-size: 1rem;
        color: #1a1a2e;
    }

    .categoria-info .codigo {
        font-size: 0.75rem;
        color: #6c757d;
        font-family: 'Consolas', monospace;
    }

    .categoria-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        background: var(--tipo-color);
        color: white;
    }

    .categoria-stats {
        display: flex;
        justify-content: space-between;
        padding-top: 15px;
        border-top: 1px solid #dee2e6;
    }

    .categoria-stat {
        text-align: center;
    }

    .categoria-stat .value {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1a1a2e;
    }

    .categoria-stat .label {
        font-size: 0.7rem;
        color: #6c757d;
        text-transform: uppercase;
    }

    .categoria-stat .value.money {
        color: #28a745;
    }

    /* ========== TABLA DE PRODUCTOS ========== */
    .productos-section {
        margin-top: 25px;
        display: none;
    }

    .productos-section.active {
        display: block;
    }

    .productos-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e9ecef;
    }

    .productos-header h3 {
        margin: 0;
        font-size: 1.1rem;
        color: #495057;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .productos-search {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .productos-search input {
        padding: 8px 15px;
        border: 1px solid #ced4da;
        border-radius: 8px;
        width: 250px;
    }

    .productos-tabla {
        width: 100%;
        border-collapse: collapse;
    }

    .productos-tabla th {
        background: #1a1a2e;
        color: white;
        padding: 14px 12px;
        text-align: left;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .productos-tabla td {
        padding: 12px;
        border-bottom: 1px solid #e9ecef;
        font-size: 0.9rem;
    }

    .productos-tabla tr:hover {
        background: #f8f9fa;
    }

    .productos-tabla .codigo {
        font-family: 'Consolas', monospace;
        font-size: 0.85rem;
        color: #495057;
    }

    .productos-tabla .nombre {
        font-weight: 500;
        color: #1a1a2e;
    }

    .productos-tabla .stock {
        font-weight: 600;
    }

    .productos-tabla .stock.ok {
        color: #28a745;
    }

    .productos-tabla .stock.bajo {
        color: #ffc107;
    }

    .productos-tabla .stock.critico {
        color: #dc3545;
    }

    .productos-tabla .valor {
        text-align: right;
        color: #28a745;
        font-weight: 500;
    }

    .productos-tabla .acciones {
        display: flex;
        gap: 8px;
        justify-content: center;
    }

    .productos-tabla .btn-accion {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .btn-accion.ver {
        background: #e3f2fd;
        color: #1976d2;
    }

    .btn-accion.editar {
        background: #fff3e0;
        color: #f57c00;
    }

    .btn-accion.movimiento {
        background: #e8f5e9;
        color: #388e3c;
    }

    .btn-accion.kardex {
        background: #f3e5f5;
        color: #7b1fa2;
    }

    .btn-accion:hover {
        transform: scale(1.1);
    }

    /* Estado badges */
    .estado-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .estado-badge.ok {
        background: #d4edda;
        color: #155724;
    }

    .estado-badge.bajo {
        background: #fff3cd;
        color: #856404;
    }

    .estado-badge.critico {
        background: #f8d7da;
        color: #721c24;
    }

    .estado-badge.sin-stock {
        background: #6c757d;
        color: white;
    }

    /* ========== MENSAJE INICIAL ========== */
    .workspace-placeholder {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
    }

    .workspace-placeholder i {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
    }

    .workspace-placeholder h3 {
        margin: 0 0 10px 0;
        color: #495057;
    }

    .workspace-placeholder p {
        margin: 0;
        font-size: 0.95rem;
    }

    /* ========== RESPONSIVE ========== */
    @media (max-width: 1200px) {
        .inv-kpis {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .inv-kpis {
            grid-template-columns: 1fr;
        }

        .inv-tipos-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .workspace-header {
            flex-direction: column;
            gap: 15px;
        }

        .workspace-actions {
            flex-wrap: wrap;
            justify-content: center;
        }
    }

    /* ========== ANIMACIONES ========== */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .fade-in {
        animation: fadeIn 0.3s ease;
    }

    /* ========== LOADING ========== */
    .loading-spinner {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px;
        color: #6c757d;
    }

    .loading-spinner i {
        font-size: 2rem;
        margin-bottom: 10px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    /* ========== BOTÓN VOLVER ========== */
    .btn-volver {
        background: #f8f9fa;
        border: 1px solid #ced4da;
        color: #495057;
        padding: 8px 15px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
        transition: all 0.2s;
    }

    .btn-volver:hover {
        background: #e9ecef;
    }

    /* Botón Configuración */
    .btn-config {
        background: linear-gradient(135deg, #495057 0%, #343a40 100%);
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        transition: all 0.2s;
    }

    .btn-config:hover {
        background: linear-gradient(135deg, #343a40 0%, #212529 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    /* Breadcrumb de navegación */
    .breadcrumb-nav {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        background: #e9ecef;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .breadcrumb-item {
        color: #007bff;
        cursor: pointer;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .breadcrumb-item:hover {
        text-decoration: underline;
    }

    .breadcrumb-current {
        color: #495057;
        font-weight: 600;
        font-size: 0.9rem;
    }

    /* Sección de subcategorías */
    .subcategorias-section {
        margin-bottom: 25px;
    }

    .subcategoria-card {
        background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
        border-radius: 14px;
        padding: 18px;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid #e9ecef;
        position: relative;
    }

    .subcategoria-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--tipo-color, #007bff);
        border-radius: 14px 14px 0 0;
    }

    .subcategoria-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        border-color: var(--tipo-color, #007bff);
    }

    .subcategoria-card.active {
        border-color: var(--tipo-color, #007bff);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    /* Estilos para modal de configuración */
    .config-tabs {
        display: flex;
        border-bottom: 2px solid #e9ecef;
        margin-bottom: 20px;
    }

    .config-tab {
        padding: 12px 24px;
        background: none;
        border: none;
        cursor: pointer;
        font-size: 0.95rem;
        color: #6c757d;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        transition: all 0.2s;
    }

    .config-tab:hover {
        color: #007bff;
    }

    .config-tab.active {
        color: #007bff;
        border-bottom-color: #007bff;
        font-weight: 600;
    }

    .config-panel {
        display: none;
    }

    .config-panel.active {
        display: block;
    }

    .config-lista {
        max-height: 400px;
        overflow-y: auto;
    }

    .config-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 15px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 10px;
    }

    .config-item:hover {
        background: #e9ecef;
    }

    .config-item-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .config-item-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .config-item-details h4 {
        margin: 0 0 4px 0;
        font-size: 0.95rem;
    }

    .config-item-details span {
        font-size: 0.8rem;
        color: #6c757d;
    }

    .config-item-actions {
        display: flex;
        gap: 8px;
    }

    .config-item-actions button {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-editar-config {
        background: #fff3e0;
        color: #f57c00;
    }

    .btn-eliminar-config {
        background: #ffebee;
        color: #c62828;
    }

    .config-add-btn {
        width: 100%;
        padding: 12px;
        background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 15px;
    }

    .config-add-btn:hover {
        background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
    }

    /* Formulario de categoría */
    .form-config {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 12px;
        margin-top: 15px;
    }

    .form-config .form-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin-bottom: 15px;
    }

    .form-config .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-config label {
        font-size: 0.85rem;
        font-weight: 500;
        color: #495057;
    }

    .form-config input,
    .form-config select {
        padding: 10px 14px;
        border: 1px solid #ced4da;
        border-radius: 8px;
        font-size: 0.9rem;
    }

    .form-config-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 15px;
    }
</style>

<!-- ========== HTML PRINCIPAL ========== -->
<div class="inv-module">
    <!-- Header -->
    <div class="inv-header">
        <h1><i class="fas fa-warehouse"></i> Centro de Inventarios</h1>
        <button class="btn btn-config" onclick="openModalConfig()">
            <i class="fas fa-cog"></i> Configuración
        </button>
    </div>

    <!-- KPIs Generales -->
    <div class="inv-kpis">
        <div class="kpi-card">
            <div class="kpi-icon items">
                <i class="fas fa-boxes"></i>
            </div>
            <div class="kpi-info">
                <div class="kpi-label">Total de Items</div>
                <div class="kpi-value" id="kpiTotalItems">--</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon valor">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="kpi-info">
                <div class="kpi-label">Valor Total Inventario</div>
                <div class="kpi-value success" id="kpiValorTotal">--</div>
            </div>
        </div>
        <div class="kpi-card" onclick="abrirModalAlertas()" style="cursor: pointer;">
            <div class="kpi-icon alertas">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="kpi-info">
                <div class="kpi-label">Alertas de Stock</div>
                <div class="kpi-value" id="kpiAlertas">--</div>
                <div style="font-size: 0.8rem; color: #dc3545; font-weight: 600; margin-top: 5px;">
                    <i class="fas fa-eye"></i> Ver
                </div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon tipos">
                <i class="fas fa-layer-group"></i>
            </div>
            <div class="kpi-info">
                <div class="kpi-label">Tipos de Inventario</div>
                <div class="kpi-value" id="kpiTipos">--</div>
            </div>
        </div>
    </div>

    <!-- Global Search -->
    <style>
        .global-search-section {
            margin-bottom: 30px;
            position: relative;
        }

        .search-container {
            position: relative;
            max-width: 800px;
            margin: 0 auto;
        }

        .search-input-wrapper {
            position: relative;
            background: white;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            padding: 5px 25px;
            border: 2px solid transparent;
            transition: all 0.3s;
        }

        .search-input-wrapper:focus-within {
            border-color: #007bff;
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.15);
            transform: translateY(-2px);
        }

        .search-input-wrapper i {
            font-size: 1.2rem;
            color: #6c757d;
            margin-right: 15px;
        }

        .global-search-input {
            border: none;
            background: transparent;
            width: 100%;
            padding: 15px 0;
            font-size: 1.1rem;
            color: #1a1a2e;
            outline: none;
        }

        .search-results-container {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 16px;
            margin-top: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            max-height: 500px;
            overflow-y: auto;
            display: none;
            border: 1px solid #e9ecef;
        }

        .search-results-container.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .search-result-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f3f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item:hover {
            background: #f8f9fa;
        }

        .result-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .result-type-badge {
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            color: white;
            background: #6c757d;
            min-width: 45px;
            text-align: center;
        }

        .result-details h4 {
            margin: 0;
            font-size: 0.95rem;
            color: #1a1a2e;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .result-details .result-code {
            font-family: 'Consolas', monospace;
            color: #007bff;
            background: #e3f2fd;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.85rem;
        }

        .result-details p {
            margin: 5px 0 0 0;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .result-stock {
            font-weight: 600;
            color: #28a745;
        }

        .result-actions .btn-kardex {
            padding: 8px 15px;
            background: #fff3e0;
            color: #f57c00;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .result-actions .btn-kardex:hover {
            background: #ffe0b2;
            transform: scale(1.05);
        }

        /* ========== NUEVOS ESTILOS ALERTAS DE STOCK ========== */
        .modal-alertas-header {
            background: linear-gradient(135deg, #eb3349, #f45c43);
            color: white;
        }

        .modal-alertas-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            padding: 5px;
            border-bottom: 1px solid #eee;
        }

        .tab-alert {
            padding: 8px 16px;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-alert:hover {
            border-color: #dc3545;
            color: #dc3545;
        }

        .tab-alert.active {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }

        .tab-alert .count {
            background: rgba(0, 0, 0, 0.1);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
        }

        .tab-alert.active .count {
            background: rgba(255, 255, 255, 0.2);
        }

        .resumen-alertas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .resumen-card-alert {
            padding: 15px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .resumen-card-alert.critico {
            background: linear-gradient(135deg, #fff5f5, #ffe0e0);
            border-left: 4px solid #dc3545;
        }

        .resumen-card-alert.bajo {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border-left: 4px solid #f59e0b;
        }

        .resumen-card-alert.sin-stock {
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
            border-left: 4px solid #6b7280;
        }

        .resumen-card-alert i {
            font-size: 1.5rem;
        }

        .resumen-card-alert.critico i {
            color: #dc3545;
        }

        .resumen-card-alert.bajo i {
            color: #f59e0b;
        }

        .resumen-card-alert.sin-stock i {
            color: #6b7280;
        }

        .resumen-info-alert .value {
            font-size: 1.5rem;
            font-weight: 700;
            display: block;
        }

        .resumen-info-alert .label {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .filtros-alertas {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .filtro-alert-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1;
            min-width: 150px;
        }

        .filtro-alert-group label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
        }

        .filtro-alert-group select,
        .filtro-alert-group input {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 0.85rem;
        }

        .stock-visual-alert {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stock-bar-alert {
            flex: 1;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            min-width: 80px;
        }

        .stock-bar-fill-alert {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s;
        }

        .stock-bar-fill-alert.critico {
            background: #dc3545;
        }

        .stock-bar-fill-alert.bajo {
            background: #f59e0b;
        }

        .stock-bar-fill-alert.sin-stock {
            background: #6b7280;
        }

        .stock-percent-alert {
            font-size: 0.75rem;
            color: #6c757d;
            min-width: 35px;
        }

        .badge-tipo-alert {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .btn-accion-alert {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            margin: 0 2px;
        }

        .btn-accion-alert.comprar {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .btn-accion-alert.kardex {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .btn-accion-alert:hover {
            transform: scale(1.1);
        }

        .footer-alert-info {
            font-size: 0.85rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ========== ESTILOS GRÁFICO TENDENCIA ========== */
        .trend-section {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .trend-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .trend-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.1rem;
            color: #1a1a2e;
            font-weight: 600;
        }

        .trend-title i {
            color: #667eea;
        }

        .trend-chart-container {
            height: 300px;
            position: relative;
        }
    </style>

    <!-- Gráfico de Tendencia -->
    <div class="trend-section">
        <div class="trend-header">
            <div class="trend-title">
                <i class="fas fa-chart-line"></i>
                Tendencia del Valor de Inventario (Últimos 6 Meses)
            </div>
            <div id="valorActualTrend" style="font-weight: 700; color: #28a745; font-size: 1.1rem;">
                Bs. 0.00
            </div>
        </div>
        <div class="trend-chart-container">
            <canvas id="chartTendenciaValor"></canvas>
        </div>
    </div>

    <div class="global-search-section">
        <div class="search-container">
            <div class="search-input-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" class="global-search-input" id="globalSearchInput"
                    placeholder="Buscar producto en todo el inventario (Código o Nombre)..."
                    onkeyup="filtrarGlobalDebounce(this.value)">
                <div id="searchSpinner" style="display: none; color: #007bff;">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
            </div>

            <div class="search-results-container" id="globalSearchResults">
                <!-- Results populated by JS -->
            </div>
        </div>
    </div>

    <!-- Tipos de Inventario -->
    <div class="inv-tipos-section">
        <div class="section-title">
            <i class="fas fa-th-large"></i>
            <span>Seleccione un tipo de inventario para gestionar</span>
        </div>
        <div class="inv-tipos-grid" id="tiposGrid">
            <div class="loading-spinner">
                <i class="fas fa-spinner"></i>
                <span>Cargando tipos...</span>
            </div>
        </div>
    </div>

    <!-- Latest Movements Widget -->
    <style>
        .ultimos-movimientos-section {
            margin-bottom: 30px;
            margin-top: 30px;
        }

        .widget-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid #e9ecef;
        }

        .widget-header {
            padding: 15px 25px;
            border-bottom: 1px solid #f0f0f0;
            background: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .widget-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .widget-body {
            background: #fff;
        }


        #listaUltimosMovimientos {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 5px;
            /* Prevent content overlap with scrollbar */

            /* Firefox */
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 #f8f9fa;
        }

        /* Webkit (Chrome, Safari, Edge) */
        #listaUltimosMovimientos::-webkit-scrollbar {
            width: 6px;
        }

        #listaUltimosMovimientos::-webkit-scrollbar-track {
            background: #f8f9fa;
        }

        #listaUltimosMovimientos::-webkit-scrollbar-thumb {
            background-color: #cbd5e0;
            border-radius: 3px;
        }

        #listaUltimosMovimientos::-webkit-scrollbar-thumb:hover {
            background-color: #a0aec0;
        }

        .movimiento-item {
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            align-items: center;
            padding: 15px 25px;
            border-bottom: 1px solid #f4f6f9;
            transition: background 0.2s;
            gap: 15px;
        }

        .movimiento-item:last-child {
            border-bottom: none;
        }

        .movimiento-item:hover {
            background: #f8f9fa;
        }

        .mov-main {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .mov-icon-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .mov-desc h5 {
            margin: 0 0 2px 0;
            font-size: 0.95rem;
            color: #1a1a2e;
        }

        .mov-desc span {
            font-size: 0.8rem;
            color: #6c757d;
            font-family: 'Consolas', monospace;
        }

        .mov-monto {
            font-weight: 700;
            color: #1a1a2e;
            font-size: 0.95rem;
        }

        .mov-fecha {
            color: #6c757d;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .mov-tipo {
            font-size: 0.85rem;
            padding: 4px 10px;
            border-radius: 12px;
            background: #e9ecef;
            color: #495057;
            display: inline-block;
            text-align: center;
        }

        @media (max-width: 900px) {
            .movimiento-item {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .mov-tipo {
                display: inline-block;
                width: auto;
            }
        }
    </style>

    <div class="ultimos-movimientos-section">
        <div class="widget-card">
            <div class="widget-header">
                <h3><i class="fas fa-clipboard-list"></i> Últimos 10 Movimientos</h3>
            </div>
            <div class="widget-body">
                <div id="listaUltimosMovimientos">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner"></i>
                        <span>Cargando movimientos...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Área de Trabajo Eliminada (Ahora cada tipo tiene su propia página) -->
</div>

<!-- ========== MODALES ========== -->

<!-- Modal Crear/Editar Item -->
<div class="modal-inventario" id="modalInventario">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitulo"><i class="fas fa-box"></i> Nuevo Item de Inventario</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formInventario">
                <input type="hidden" id="idInventario" name="id_inventario">
                <input type="hidden" id="tipoInventarioHidden" name="tipo_inventario_hidden">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Código <span class="required">*</span></label>
                        <input type="text" id="codigo" name="codigo" required maxlength="30">
                    </div>
                    <div class="form-group">
                        <label>Nombre <span class="required">*</span></label>
                        <input type="text" id="nombre" name="nombre" required maxlength="150">
                    </div>
                    <div class="form-group">
                        <label>Tipo de Inventario</label>
                        <input type="text" id="tipoInventarioDisplay" readonly class="readonly-input">
                    </div>
                    <div class="form-group">
                        <label>Categoría <span class="required">*</span></label>
                        <select id="idCategoria" name="id_categoria" required onchange="cargarSubcategoriasParaItem()">
                            <option value="">Seleccione...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Subcategoría</label>
                        <select id="idSubcategoria" name="id_subcategoria">
                            <option value="">Sin subcategoría</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Unidad de Medida <span class="required">*</span></label>
                        <select id="idUnidad" name="id_unidad" required>
                            <option value="">Seleccione...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ubicación</label>
                        <select id="idUbicacion" name="id_ubicacion">
                            <option value="">Sin ubicación específica</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Stock Mínimo</label>
                        <input type="number" id="stockMinimo" name="stock_minimo" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label>Stock Actual</label>
                        <input type="number" id="stockActual" name="stock_actual" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label>Costo Unitario (Bs.)</label>
                        <input type="number" id="costoUnitario" name="costo_unitario" step="0.0001" value="0">
                    </div>
                    <div class="form-group">
                        <label>Proveedor Principal</label>
                        <input type="text" id="proveedorPrincipal" name="proveedor_principal" maxlength="100">
                    </div>
                    <div class="form-group full-width">
                        <label>Descripción</label>
                        <textarea id="descripcion" name="descripcion" rows="2"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="guardarInventario()">
                <i class="fas fa-save"></i> Guardar
            </button>
        </div>
    </div>
</div>

<!-- Modal Kardex -->
<div class="modal-inventario" id="modalKardex">
    <div class="modal-content" style="max-width: 1100px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #1a237e 0%, #4fc3f7 100%); color: white;">
            <h3><i class="fas fa-book"></i> <span id="kardexTitulo">Kardex del Producto</span></h3>
            <button class="modal-close" onclick="closeModalKardex()"
                style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
            <div id="kardexContent">
                <div class="loading-spinner">
                    <i class="fas fa-spinner"></i>
                    <span>Cargando kardex...</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModalKardex()">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal Configuración de Catálogos -->
<div class="modal-inventario" id="modalConfig">
    <div class="modal-content" style="max-width: 900px; max-height: 85vh;">
        <div class="modal-header" style="background: linear-gradient(135deg, #495057 0%, #343a40 100%); color: white;">
            <h3><i class="fas fa-cog"></i> Configuración de Inventarios</h3>
            <button class="modal-close" onclick="closeModalConfig()"
                style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body" style="overflow-y: auto; max-height: calc(85vh - 140px);">
            <!-- Tabs -->
            <div class="config-tabs">
                <button class="config-tab active" onclick="cambiarTabConfig('tipos')">
                    <i class="fas fa-layer-group"></i> Tipos
                </button>
                <button class="config-tab" onclick="cambiarTabConfig('categorias')">
                    <i class="fas fa-folder"></i> Categorías
                </button>
                <button class="config-tab" onclick="cambiarTabConfig('subcategorias')">
                    <i class="fas fa-folder-open"></i> Subcategorías
                </button>
            </div>

            <!-- Panel Tipos de Inventario -->
            <div class="config-panel active" id="panelTipos">
                <div class="config-lista" id="listaTipos">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner"></i>
                        <span>Cargando tipos...</span>
                    </div>
                </div>
                <button class="config-add-btn" onclick="mostrarFormTipo()">
                    <i class="fas fa-plus"></i> Agregar Tipo de Inventario
                </button>

                <!-- Formulario Tipo -->
                <div class="form-config" id="formTipoContainer" style="display: none;">
                    <h4 id="formTipoTitulo"><i class="fas fa-plus"></i> Nuevo Tipo de Inventario</h4>
                    <input type="hidden" id="tipoId">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Código *</label>
                            <input type="text" id="tipoCodigo" maxlength="10" placeholder="Ej: MP, CAQ, PT">
                        </div>
                        <div class="form-group">
                            <label>Nombre *</label>
                            <input type="text" id="tipoNombre" maxlength="100" placeholder="Ej: Materias Primas">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Ícono (FontAwesome)</label>
                            <input type="text" id="tipoIcono" placeholder="fa-box, fa-flask, etc.">
                        </div>
                        <div class="form-group">
                            <label>Color</label>
                            <input type="color" id="tipoColor" value="#007bff">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Orden de Visualización</label>
                            <input type="number" id="tipoOrden" value="1" min="1" placeholder="Orden numérico">
                        </div>
                    </div>
                    <div class="form-config-actions">
                        <button class="btn btn-secondary" onclick="cancelarFormTipo()">Cancelar</button>
                        <button class="btn btn-primary" onclick="guardarTipo()">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Panel Categorías -->
            <div class="config-panel" id="panelCategorias">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Filtrar por Tipo de Inventario:</label>
                    <select id="filtroTipoCategoria" onchange="cargarCategoriasPorTipo()">
                        <option value="">-- Todos los tipos --</option>
                    </select>
                </div>

                <div class="config-lista" id="listaCategorias">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner"></i>
                        <span>Cargando categorías...</span>
                    </div>
                </div>
                <button class="config-add-btn" onclick="mostrarFormCategoria()">
                    <i class="fas fa-plus"></i> Agregar Categoría
                </button>

                <!-- Formulario Categoría -->
                <div class="form-config" id="formCategoriaContainer" style="display: none;">
                    <h4 id="formCategoriaTitulo"><i class="fas fa-plus"></i> Nueva Categoría</h4>
                    <input type="hidden" id="categoriaId">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipo de Inventario *</label>
                            <select id="categoriaTipo" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Código *</label>
                            <input type="text" id="categoriaCodigo" maxlength="20" placeholder="Ej: MP-HILO-POLI">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre *</label>
                            <input type="text" id="categoriaNombre" maxlength="100"
                                placeholder="Ej: Hilos de Poliamida">
                        </div>
                        <div class="form-group">
                            <label>Orden</label>
                            <input type="number" id="categoriaOrden" value="1" min="1">
                        </div>
                    </div>
                    <div class="form-config-actions">
                        <button class="btn btn-secondary" onclick="cancelarFormCategoria()">Cancelar</button>
                        <button class="btn btn-primary" onclick="guardarCategoria()">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Panel Subcategorías -->
            <div class="config-panel" id="panelSubcategorias">
                <div class="form-row" style="margin-bottom: 15px;">
                    <div class="form-group">
                        <label>Filtrar por Categoría:</label>
                        <select id="filtroCategoriaSub" onchange="cargarSubcategoriasPorCategoria()">
                            <option value="">-- Todas las categorías --</option>
                        </select>
                    </div>
                </div>

                <div class="config-lista" id="listaSubcategorias">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner"></i>
                        <span>Cargando subcategorías...</span>
                    </div>
                </div>
                <button class="config-add-btn" onclick="mostrarFormSubcategoria()">
                    <i class="fas fa-plus"></i> Agregar Subcategoría
                </button>

                <!-- Formulario Subcategoría -->
                <div class="form-config" id="formSubcategoriaContainer" style="display: none;">
                    <h4 id="formSubcategoriaTitulo"><i class="fas fa-plus"></i> Nueva Subcategoría</h4>
                    <input type="hidden" id="subcategoriaId">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Categoría Padre *</label>
                            <select id="subcategoriaCategoria" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Código *</label>
                            <input type="text" id="subcategoriaCodigo" maxlength="30" placeholder="Ej: MP-HILO-DTY">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre *</label>
                            <input type="text" id="subcategoriaNombre" maxlength="100" placeholder="Ej: Hilos DTY">
                        </div>
                        <div class="form-group">
                            <label>Orden</label>
                            <input type="number" id="subcategoriaOrden" value="1" min="1">
                        </div>
                    </div>
                    <div class="form-config-actions">
                        <button class="btn btn-secondary" onclick="cancelarFormSubcategoria()">Cancelar</button>
                        <button class="btn btn-primary" onclick="guardarSubcategoria()">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModalConfig()">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal Alertas Stock -->
<!-- Modal Alertas Stock Rediseñado -->
<div class="modal-inventario" id="modalAlertas">
    <div class="modal-content" style="max-width: 1100px;">
        <div class="modal-header modal-alertas-header">
            <h3>
                <i class="fas fa-exclamation-triangle"></i>
                Alertas de Stock
                <span class="badge" id="totalAlertasBadge"
                    style="background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; margin-left: 10px;">0
                    productos</span>
            </h3>
            <button class="modal-close" onclick="closeModalAlertas()"
                style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>

        <div class="modal-body">
            <!-- Tabs por Tipo de Inventario -->
            <div class="modal-alertas-tabs" id="tabsAlertas">
                <button class="tab-alert active" onclick="filtrarAlertasPorTipo('todos', this)">
                    <i class="fas fa-boxes"></i> Todos
                    <span class="count" id="countAlertTodos">0</span>
                </button>
                <!-- Otros tabs se cargarán dinámicamente -->
            </div>

            <!-- Resumen por Severidad -->
            <div class="resumen-alertas">
                <div class="resumen-card-alert sin-stock">
                    <i class="fas fa-times-circle"></i>
                    <div class="resumen-info-alert">
                        <span class="value" id="countSinStock">0</span>
                        <span class="label">Sin Stock (0%)</span>
                    </div>
                </div>
                <div class="resumen-card-alert critico">
                    <i class="fas fa-exclamation-circle"></i>
                    <div class="resumen-info-alert">
                        <span class="value" id="countCritico">0</span>
                        <span class="label">Crítico (1-25%)</span>
                    </div>
                </div>
                <div class="resumen-card-alert bajo">
                    <i class="fas fa-info-circle"></i>
                    <div class="resumen-info-alert">
                        <span class="value" id="countBajo">0</span>
                        <span class="label">Bajo (26-50%)</span>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filtros-alertas">
                <div class="filtro-alert-group">
                    <label>Categoría</label>
                    <select id="filtroAlertCategoria" onchange="aplicarFiltrosAlertas()">
                        <option value="">Todas las categorías</option>
                    </select>
                </div>
                <div class="filtro-alert-group">
                    <label>Severidad</label>
                    <select id="filtroAlertSeveridad" onchange="aplicarFiltrosAlertas()">
                        <option value="">Todas</option>
                        <option value="SIN_STOCK">🔴 Sin Stock</option>
                        <option value="CRITICO">🟠 Crítico</option>
                        <option value="BAJO">🟡 Bajo</option>
                    </select>
                </div>
                <div class="filtro-alert-group">
                    <label>Buscar</label>
                    <input type="text" id="filtroAlertBusqueda" placeholder="Código o nombre..."
                        onkeyup="aplicarFiltrosAlertas()">
                </div>
                <button class="btn btn-primary" style="margin-top: 18px;" onclick="aplicarFiltrosAlertas()">
                    <i class="fas fa-search"></i> Filtrar
                </button>
            </div>

            <!-- Tabla de Alertas -->
            <div id="loadingAlertas" class="loading-spinner">
                <i class="fas fa-spinner"></i>
                <span>Cargando alertas...</span>
            </div>

            <div id="contentAlertas" style="display:none; overflow-x: auto;">
                <table class="productos-tabla">
                    <thead>
                        <tr style="position: sticky; top: 0; background: white; z-index: 10;">
                            <th>Tipo</th>
                            <th>Código</th>
                            <th>Producto</th>
                            <th style="width: 150px;">Nivel Stock</th>
                            <th style="text-align: right;">Stock</th>
                            <th style="text-align: right;">Mínimo</th>
                            <th style="text-align: right;">Faltante</th>
                            <th>Estado</th>
                            <th style="text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaAlertasBody">
                        <!-- Filled by JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
            <div class="footer-alert-info">
                <i class="fas fa-info-circle"></i>
                <span id="footerAlertStats">Mostrando 0 de 0 alertas</span>
            </div>
            <div style="display: flex; gap: 10px;">
                <button class="btn"
                    style="background: #28a745; color: white; display: flex; align-items: center; gap: 8px;"
                    onclick="exportarAlertasExcel()">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </button>
                <button class="btn"
                    style="background: #17a2b8; color: white; display: flex; align-items: center; gap: 8px;"
                    onclick="imprimirAlertas()">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <button class="btn btn-secondary" onclick="closeModalAlertas()">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================
     MODALES ADICIONALES PARA INVENTARIOS v1.6.5
     Sistema MES Hermen Ltda.
     
     Este archivo contiene:
     - Modal de Ingreso (con Proveedor y IVA 13%)
     - Modal de Salida
     - Modal de Historial de Documentos
     - Modal de Kardex
     - Modal de Devoluciones
     - Modal de Reportes
     - Modal de Proveedores
     
     INSTRUCCIÓN: Agregar este código ANTES del cierre </body>
     en modules/inventarios/index.php
===================================================== -->

<!-- ========== MODAL INGRESO MULTIPRODUCTO CON PROVEEDOR ========== -->
<div class="modal-inventario" id="modalIngreso">
    <div class="modal-content" style="max-width: 1000px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
            <h3><i class="fas fa-arrow-down"></i> Ingreso de Inventario</h3>
            <button class="modal-close" onclick="closeModalIngreso()"
                style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formIngreso">
                <!-- Cabecera del documento -->
                <div class="form-row"
                    style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Tipo Documento *</label>
                        <select id="ingresoDocTipo" required>
                            <option value="FACTURA">Factura</option>
                            <option value="NOTA">Nota de Ingreso</option>
                            <option value="REMISION">Remisión</option>
                            <option value="AJUSTE">Ajuste</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nº Documento *</label>
                        <input type="text" id="ingresoDocNumero" required placeholder="Ej: FAC-001">
                    </div>
                    <div class="form-group">
                        <label>Fecha *</label>
                        <input type="date" id="ingresoFecha" required>
                    </div>
                    <div class="form-group">
                        <label>Proveedor</label>
                        <select id="ingresoProveedor">
                            <option value="">-- Seleccione --</option>
                        </select>
                    </div>
                </div>

                <!-- Checkbox IVA -->
                <div class="form-row" style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" id="ingresoConFactura" style="width: 20px; height: 20px;">
                        <span>📄 Compra con Factura (Descontar IVA 13% del costo)</span>
                    </label>
                </div>

                <!-- Observaciones -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Observaciones</label>
                    <input type="text" id="ingresoObservaciones" placeholder="Notas adicionales...">
                </div>

                <!-- Líneas de productos -->
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 style="margin: 0;"><i class="fas fa-list"></i> Productos a Ingresar</h4>
                        <button type="button" class="btn btn-sm" onclick="agregarLineaIngreso()"
                            style="background: #28a745; color: white; padding: 8px 15px; border-radius: 6px;">
                            <i class="fas fa-plus"></i> Agregar Línea
                        </button>
                    </div>

                    <table class="tabla-lineas" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #e9ecef;">
                                <th style="padding: 10px; text-align: left; width: 35%;">Producto</th>
                                <th style="padding: 10px; text-align: right; width: 15%;">Cantidad</th>
                                <th style="padding: 10px; text-align: right; width: 15%;">Costo Bruto</th>
                                <th style="padding: 10px; text-align: right; width: 15%;">Costo Neto</th>
                                <th style="padding: 10px; text-align: right; width: 15%;">Subtotal</th>
                                <th style="padding: 10px; width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="lineasIngresoBody">
                            <!-- Se llena dinámicamente -->
                        </tbody>
                        <tfoot>
                            <tr style="background: #d4edda; font-weight: bold;">
                                <td colspan="4" style="padding: 12px; text-align: right;">TOTAL NETO:</td>
                                <td style="padding: 12px; text-align: right;" id="ingresoTotalNeto">Bs. 0.00</td>
                                <td></td>
                            </tr>
                            <tr id="filaIVA" style="background: #fff3cd; display: none;">
                                <td colspan="4" style="padding: 10px; text-align: right;">IVA 13% (Crédito Fiscal):</td>
                                <td style="padding: 10px; text-align: right;" id="ingresoTotalIVA">Bs. 0.00</td>
                                <td></td>
                            </tr>
                            <tr id="filaBruto" style="background: #e2e3e5; display: none;">
                                <td colspan="4" style="padding: 10px; text-align: right;">Total Bruto (con IVA):</td>
                                <td style="padding: 10px; text-align: right;" id="ingresoTotalBruto">Bs. 0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModalIngreso()">Cancelar</button>
            <button type="button" class="btn btn-success" onclick="guardarIngreso()">
                <i class="fas fa-save"></i> Guardar Ingreso
            </button>
        </div>
    </div>
</div>

<!-- ========== MODAL SALIDA MULTIPRODUCTO ========== -->
<div class="modal-inventario" id="modalSalida">
    <div class="modal-content" style="max-width: 1000px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;">
            <h3><i class="fas fa-arrow-up"></i> Salida de Inventario</h3>
            <button class="modal-close" onclick="closeModalSalida()"
                style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formSalida">
                <!-- Cabecera -->
                <div class="form-row"
                    style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Tipo Movimiento *</label>
                        <select id="salidaTipoMov" required>
                            <option value="SALIDA_CONSUMO">Consumo Producción</option>
                            <option value="SALIDA_PRODUCCION">Entrega a Producción</option>
                            <option value="SALIDA_AJUSTE">Ajuste de Inventario</option>
                            <option value="SALIDA_MERMA">Merma/Desperdicio</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nº Documento *</label>
                        <input type="text" id="salidaDocNumero" required placeholder="Ej: SAL-001">
                    </div>
                    <div class="form-group">
                        <label>Fecha *</label>
                        <input type="date" id="salidaFecha" required>
                    </div>
                    <div class="form-group">
                        <label>Destino</label>
                        <input type="text" id="salidaDestino" placeholder="Área o responsable">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Observaciones</label>
                    <input type="text" id="salidaObservaciones" placeholder="Notas adicionales...">
                </div>

                <!-- Líneas -->
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 style="margin: 0;"><i class="fas fa-list"></i> Productos a Salir</h4>
                        <button type="button" class="btn btn-sm" onclick="agregarLineaSalida()"
                            style="background: #dc3545; color: white; padding: 8px 15px; border-radius: 6px;">
                            <i class="fas fa-plus"></i> Agregar Línea
                        </button>
                    </div>

                    <table class="tabla-lineas" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #e9ecef;">
                                <th style="padding: 10px; text-align: left; width: 40%;">Producto</th>
                                <th style="padding: 10px; text-align: right; width: 15%;">Stock Disp.</th>
                                <th style="padding: 10px; text-align: right; width: 15%;">Cantidad</th>
                                <th style="padding: 10px; text-align: right; width: 15%;">Costo Unit.</th>
                                <th style="padding: 10px; text-align: right; width: 10%;">Subtotal</th>
                                <th style="padding: 10px; width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="lineasSalidaBody">
                        </tbody>
                        <tfoot>
                            <tr style="background: #f8d7da; font-weight: bold;">
                                <td colspan="4" style="padding: 12px; text-align: right;">TOTAL SALIDA:</td>
                                <td style="padding: 12px; text-align: right;" id="salidaTotal">Bs. 0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModalSalida()">Cancelar</button>
            <button type="button" class="btn btn-danger" onclick="guardarSalida()">
                <i class="fas fa-save"></i> Registrar Salida
            </button>
        </div>
    </div>
</div>

<!-- ========== MODAL HISTORIAL DE DOCUMENTOS ========== -->
<div class="modal-inventario" id="modalHistorial">
    <div class="modal-content" style="max-width: 1100px; max-height: 90vh;">
        <div class="modal-header" style="background: linear-gradient(135deg, #1a237e 0%, #4fc3f7 100%); color: white;">
            <h3><i class="fas fa-history"></i> Historial de Documentos</h3>
            <button class="modal-close" onclick="closeModalHistorial()"
                style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
            <!-- Filtros -->
            <div
                style="display: grid; grid-template-columns: repeat(4, 1fr) auto; gap: 15px; margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 8px;">
                <div>
                    <label style="font-size: 0.85rem; color: #666;">Desde</label>
                    <input type="date" id="historialFechaDesde" class="form-control">
                </div>
                <div>
                    <label style="font-size: 0.85rem; color: #666;">Hasta</label>
                    <input type="date" id="historialFechaHasta" class="form-control">
                </div>
                <div>
                    <label style="font-size: 0.85rem; color: #666;">Tipo</label>
                    <select id="historialTipoMov" class="form-control">
                        <option value="">Todos</option>
                        <option value="ENTRADA">Entradas</option>
                        <option value="SALIDA">Salidas</option>
                        <option value="DEVOLUCION">Devoluciones</option>
                    </select>
                </div>
                <div>
                    <label style="font-size: 0.85rem; color: #666;">Buscar</label>
                    <input type="text" id="historialBuscar" class="form-control" placeholder="Nº Doc...">
                </div>
                <div style="display: flex; align-items: flex-end;">
                    <button type="button" class="btn btn-primary" onclick="buscarHistorial()"
                        style="padding: 10px 20px;">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </div>

            <!-- Tabla de documentos -->
            <table class="tabla-historial" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #343a40; color: white;">
                        <th style="padding: 12px;">Fecha</th>
                        <th style="padding: 12px;">Documento</th>
                        <th style="padding: 12px;">Tipo</th>
                        <th style="padding: 12px;">Proveedor/Destino</th>
                        <th style="padding: 12px; text-align: right;">Total</th>
                        <th style="padding: 12px;">Estado</th>
                        <th style="padding: 12px; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="historialBody">
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 30px;">Use los filtros para buscar
                            documentos</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModalHistorial()">Cerrar</button>
        </div>
    </div>
</div>

<!-- ========== MODAL DETALLE DE DOCUMENTO ========== -->
<div class="modal-inventario" id="modalDetalleDoc">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white;">
            <h3 id="detalleDocTitulo"><i class="fas fa-file-alt"></i> Detalle de Documento</h3>
            <button class="modal-close" onclick="closeModalDetalleDoc()"
                style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body">
            <div id="detalleDocContenido">
                <!-- Se llena dinámicamente -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModalDetalleDoc()">Cerrar</button>
            <button type="button" class="btn btn-warning" onclick="anularDocumento()" id="btnAnularDoc">
                <i class="fas fa-ban"></i> Anular Documento
            </button>
            <button type="button" class="btn btn-info" onclick="imprimirDocumento()">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </div>
</div>

<!-- ========== MODAL DEVOLUCIONES ========== -->
<div class="modal-inventario" id="modalDevolucion">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #fd7e14 0%, #e55c00 100%); color: white;">
            <h3><i class="fas fa-undo-alt"></i> Devolución a Proveedor</h3>
            <button class="modal-close" onclick="closeModalDevolucion()"
                style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Paso 1: Buscar documento original -->
            <div id="devolucionPaso1">
                <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <p style="margin: 0;"><i class="fas fa-info-circle"></i> Ingrese el número de documento de compra
                        original para iniciar la devolución.</p>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr auto; gap: 15px;">
                    <div class="form-group">
                        <label>Nº Documento de Compra Original *</label>
                        <input type="text" id="devolucionDocOriginal" placeholder="Ej: FAC-001">
                    </div>
                    <div style="display: flex; align-items: flex-end;">
                        <button type="button" class="btn btn-primary" onclick="buscarDocumentoDevolucion()">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </div>

                <div id="devolucionDocInfo" style="display: none; margin-top: 20px;">
                    <!-- Info del documento encontrado -->
                </div>
            </div>

            <!-- Paso 2: Seleccionar items a devolver -->
            <div id="devolucionPaso2" style="display: none;">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Nº Documento de Devolución *</label>
                    <input type="text" id="devolucionDocNumero" placeholder="Ej: DEV-001">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Motivo de Devolución *</label>
                    <textarea id="devolucionMotivo" rows="2"
                        placeholder="Describa el motivo de la devolución..."></textarea>
                </div>

                <h4 style="margin: 20px 0 10px;"><i class="fas fa-list"></i> Items a Devolver</h4>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #e9ecef;">
                            <th style="padding: 10px;"><input type="checkbox" id="devSeleccionarTodos"
                                    onchange="toggleTodosDevolucion()"></th>
                            <th style="padding: 10px; text-align: left;">Producto</th>
                            <th style="padding: 10px; text-align: right;">Cant. Original</th>
                            <th style="padding: 10px; text-align: right;">Cant. Devolver</th>
                            <th style="padding: 10px; text-align: right;">Costo Unit.</th>
                            <th style="padding: 10px; text-align: right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="devolucionLineasBody">
                    </tbody>
                    <tfoot>
                        <tr style="background: #fff3cd; font-weight: bold;">
                            <td colspan="5" style="padding: 12px; text-align: right;">TOTAL DEVOLUCIÓN:</td>
                            <td style="padding: 12px; text-align: right;" id="devolucionTotal">Bs. 0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModalDevolucion()">Cancelar</button>
            <button type="button" class="btn btn-warning" onclick="procesarDevolucion()" id="btnProcesarDev"
                style="display: none;">
                <i class="fas fa-undo-alt"></i> Procesar Devolución
            </button>
        </div>
    </div>
</div>

<!-- ========== MODAL REPORTES ========== -->
<div class="modal-inventario" id="modalReportes">
    <div class="modal-content" style="max-width: 1200px; max-height: 90vh;">
        <div class="modal-header" style="background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%); color: white;">
            <h3><i class="fas fa-chart-bar"></i> Reportes de Inventario</h3>
            <button class="modal-close" onclick="closeModalReportes()"
                style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
            <!-- Selector de tipo de reporte -->
            <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                <button class="btn-reporte active" onclick="seleccionarReporte('total')" id="btnRepTotal">
                    <i class="fas fa-globe"></i> Total General
                </button>
                <button class="btn-reporte" onclick="seleccionarReporte('por_tipo')" id="btnRepTipo">
                    <i class="fas fa-layer-group"></i> Por Tipo
                </button>
                <button class="btn-reporte" onclick="seleccionarReporte('por_categoria')" id="btnRepCategoria">
                    <i class="fas fa-folder"></i> Por Categoría
                </button>
                <button class="btn-reporte" onclick="seleccionarReporte('por_subcategoria')" id="btnRepSubcategoria">
                    <i class="fas fa-folder-open"></i> Por Subcategoría
                </button>
                <button class="btn-reporte" onclick="seleccionarReporte('detallado')" id="btnRepDetallado">
                    <i class="fas fa-list-alt"></i> Detallado
                </button>
                <button class="btn-reporte" onclick="seleccionarReporte('compras_proveedor')" id="btnRepProveedores">
                    <i class="fas fa-truck"></i> Compras x Proveedor
                </button>
            </div>

            <!-- Filtros -->
            <div id="filtrosReporte"
                style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: none;">
                <div style="display: grid; grid-template-columns: repeat(4, 1fr) auto; gap: 15px;">
                    <div id="filtroTipoReporte">
                        <label>Tipo Inventario</label>
                        <select id="reporteTipoId" class="form-control">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div id="filtroCategoriaReporte" style="display: none;">
                        <label>Categoría</label>
                        <select id="reporteCategoriaId" class="form-control">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div id="filtroFechasReporte" style="display: none;">
                        <label>Desde</label>
                        <input type="date" id="reporteFechaDesde" class="form-control">
                    </div>
                    <div id="filtroFechasReporte2" style="display: none;">
                        <label>Hasta</label>
                        <input type="date" id="reporteFechaHasta" class="form-control">
                    </div>
                    <div style="display: flex; align-items: flex-end;">
                        <button type="button" class="btn btn-primary" onclick="generarReporte()">
                            <i class="fas fa-sync"></i> Generar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Contenido del reporte -->
            <div id="reporteContenido">
                <div style="text-align: center; padding: 50px; color: #666;">
                    <i class="fas fa-chart-pie" style="font-size: 3rem; margin-bottom: 15px;"></i>
                    <p>Seleccione un tipo de reporte para comenzar</p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModalReportes()">Cerrar</button>
            <button type="button" class="btn btn-success" onclick="exportarReporte('excel')" id="btnExportarExcel"
                style="display: none;">
                <i class="fas fa-file-excel"></i> Exportar Excel
            </button>
            <button type="button" class="btn btn-info" onclick="imprimirReporte()" id="btnImprimirReporte"
                style="display: none;">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </div>
</div>

<!-- ========== MODAL GESTIÓN DE PROVEEDORES ========== -->
<div class="modal-inventario" id="modalProveedores">
    <div class="modal-content" style="max-width: 1000px; max-height: 90vh;">
        <div class="modal-header" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white;">
            <h3><i class="fas fa-truck"></i> Gestión de Proveedores</h3>
            <button class="modal-close" onclick="closeModalProveedores()"
                style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
            <!-- Barra de acciones -->
            <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="buscarProveedor" placeholder="Buscar proveedor..."
                        style="padding: 10px; border-radius: 6px; border: 1px solid #ddd; width: 300px;">
                    <button type="button" class="btn btn-outline" onclick="buscarProveedores()">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <button type="button" class="btn btn-success" onclick="nuevoProveedor()">
                    <i class="fas fa-plus"></i> Nuevo Proveedor
                </button>
            </div>

            <!-- Lista de proveedores -->
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #343a40; color: white;">
                        <th style="padding: 12px;">Código</th>
                        <th style="padding: 12px;">Razón Social</th>
                        <th style="padding: 12px;">NIT</th>
                        <th style="padding: 12px;">Contacto</th>
                        <th style="padding: 12px;">Teléfono</th>
                        <th style="padding: 12px; text-align: right;">Total Compras</th>
                        <th style="padding: 12px; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="proveedoresBody">
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 30px;">Cargando...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModalProveedores()">Cerrar</button>
        </div>
    </div>
</div>

<!-- ========== MODAL FORM PROVEEDOR ========== -->
<div class="modal-inventario" id="modalFormProveedor">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 id="formProveedorTitulo"><i class="fas fa-truck"></i> Nuevo Proveedor</h3>
            <button class="modal-close" onclick="closeModalFormProveedor()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formProveedor">
                <input type="hidden" id="proveedorId">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Código *</label>
                        <input type="text" id="proveedorCodigo" required placeholder="Ej: PROV-001">
                    </div>
                    <div class="form-group">
                        <label>NIT</label>
                        <input type="text" id="proveedorNIT" placeholder="Número de NIT">
                    </div>
                </div>
                <div class="form-group">
                    <label>Razón Social *</label>
                    <input type="text" id="proveedorRazonSocial" required placeholder="Nombre de la empresa">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Nombre Contacto</label>
                        <input type="text" id="proveedorContacto" placeholder="Persona de contacto">
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" id="proveedorTelefono" placeholder="+591...">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="proveedorEmail" placeholder="correo@empresa.com">
                    </div>
                    <div class="form-group">
                        <label>Ciudad</label>
                        <input type="text" id="proveedorCiudad" placeholder="La Paz, Cochabamba...">
                    </div>
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <textarea id="proveedorDireccion" rows="2" placeholder="Dirección completa..."></textarea>
                </div>
                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea id="proveedorObservaciones" rows="2" placeholder="Notas adicionales..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModalFormProveedor()">Cancelar</button>
            <button type="button" class="btn btn-success" onclick="guardarProveedor()">
                <i class="fas fa-save"></i> Guardar
            </button>
        </div>
    </div>
</div>

<style>
    /* Estilos adicionales para modales y formularios */
    .modal-inventario {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal-inventario.show {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 16px;
        width: 90%;
        max-width: 700px;
        max-height: 90vh;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    .modal-header {
        padding: 20px 25px;
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-close {
        background: rgba(255, 255, 255, 0.1);
        border: none;
        color: white;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        font-size: 1.5rem;
        cursor: pointer;
        transition: background 0.2s;
    }

    .modal-close:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .modal-body {
        padding: 25px;
        overflow-y: auto;
    }

    .modal-footer {
        padding: 15px 25px;
        background: #f8f9fa;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        border-top: 1px solid #e9ecef;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-group.full-width {
        grid-column: span 2;
    }

    .form-group label {
        font-size: 0.85rem;
        font-weight: 500;
        color: #495057;
    }

    .form-group .required {
        color: #dc3545;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 10px 14px;
        border: 1px solid #ced4da;
        border-radius: 8px;
        font-size: 0.95rem;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
    }

    .readonly-input {
        background: #e9ecef;
        cursor: not-allowed;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 0.9rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }

    .btn-primary {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    /* Kardex styles */
    .kardex-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .kardex-table th {
        background: #343a40;
        color: white;
        padding: 10px 8px;
        text-align: left;
    }

    .kardex-table td {
        padding: 8px;
        border-bottom: 1px solid #e9ecef;
    }

    .kardex-table tr:nth-child(even) {
        background: #f8f9fa;
    }

    .kardex-entrada {
        color: #28a745;
    }

    .kardex-salida {
        color: #dc3545;
    }


    /* Estilos para botones de reporte */
    .btn-reporte {
        padding: 12px 20px;
        border: 2px solid #dee2e6;
        background: white;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.9rem;
    }

    .btn-reporte:hover {
        border-color: #007bff;
        color: #007bff;
    }

    .btn-reporte.active {
        background: #007bff;
        color: white;
        border-color: #007bff;
    }

    .btn-reporte i {
        margin-right: 8px;
    }

    /* Tablas */
    .tabla-lineas input,
    .tabla-lineas select {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        width: 100%;
    }

    .tabla-lineas input[type="number"] {
        text-align: right;
    }

    .tabla-lineas td {
        padding: 8px;
        border-bottom: 1px solid #eee;
    }

    /* Badges de estado */
    .badge-activo {
        background: #28a745;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
    }

    .badge-anulado {
        background: #dc3545;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
    }

    /* Form controls */
    .form-control {
        padding: 10px 14px;
        border: 1px solid #ced4da;
        border-radius: 8px;
        width: 100%;
        font-size: 0.9rem;
    }

    /* Tabla historial */
    .tabla-historial td {
        padding: 12px;
        border-bottom: 1px solid #dee2e6;
    }

    .tabla-historial tr:hover {
        background: #f8f9fa;
    }

    /* Botón eliminar línea */
    .btn-eliminar-linea {
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 6px 10px;
        cursor: pointer;
    }

    .btn-eliminar-linea:hover {
        background: #c82333;
    }

    /* Reporte tabla */
    .reporte-tabla {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    .reporte-tabla th {
        background: #343a40;
        color: white;
        padding: 12px;
        text-align: left;
    }

    .reporte-tabla td {
        padding: 10px 12px;
        border-bottom: 1px solid #dee2e6;
    }

    .reporte-tabla tr:hover {
        background: #f8f9fa;
    }

    .reporte-tabla .subtotal {
        background: #e9ecef;
        font-weight: bold;
    }

    .reporte-tabla .total {
        background: #343a40;
        color: white;
        font-weight: bold;
    }

    /* Cards KPI reporte */
    .reporte-kpis {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        margin-bottom: 20px;
    }

    .reporte-kpi {
        background: white;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .reporte-kpi .valor {
        font-size: 1.8rem;
        font-weight: bold;
        color: #333;
    }

    .reporte-kpi .label {
        font-size: 0.85rem;
        color: #666;
        margin-top: 5px;
    }
</style>


<script>
    // ========== VARIABLES GLOBALES ==========
    // const baseUrl = window.location.origin + '/mes_hermen'; // Ya definido en header.php

    let tiposInventario = [];
    let categoriasInventario = [];
    let productosInventario = [];
    let unidadesMedida = [];
    let ubicaciones = [];

    let tipoSeleccionado = null;
    let categoriaSeleccionada = null;

    // ========== INICIALIZACIÓN ==========
    document.addEventListener('DOMContentLoaded', function () {
        // Cargar dashboard principal (crítico)
        cargarDashboard();

        // Cargar componentes secundarios con timeouts para evitar bloqueos
        setTimeout(() => cargarUltimosMovimientos().catch(e => console.error('Error movimientos:', e)), 100);
        setTimeout(() => cargarCatalogos().catch(e => console.error('Error catálogos:', e)), 200);
        setTimeout(() => cargarTendencia().catch(e => console.error('Error tendencia:', e)), 300);
    });

    // ========== CARGA DE DATOS ==========
    async function cargarDashboard() {
        try {
            const response = await fetch(`${baseUrl}/api/centro_inventarios.php?action=resumen`);
            const data = await response.json();

            if (data.success) {
                // KPIs generales
                document.getElementById('kpiTotalItems').textContent = data.totales.items;
                document.getElementById('kpiValorTotal').textContent = 'Bs. ' + parseFloat(data.totales.valor).toLocaleString('es-BO', { minimumFractionDigits: 2 });
                document.getElementById('kpiAlertas').textContent = data.totales.alertas;
                document.getElementById('kpiAlertas').className = 'kpi-value ' + (data.totales.alertas > 0 ? 'danger' : 'success');
                document.getElementById('kpiTipos').textContent = data.resumen.length;

                // Guardar tipos y renderizar
                tiposInventario = data.resumen;
                renderTiposInventario(tiposInventario);
            } else {
                console.error('Error backend:', data.message);
                // Si hay un error de conexión SQL, mostrarlo
                if (data.error) console.error('Detalle error:', data.error);

                // Mostrar estado de error en los KPIs
                document.getElementById('kpiTipos').innerHTML = '<i class="fas fa-exclamation-triangle text-danger"></i>';
                alert('No se pudo cargar el dashboard: ' + (data.message || 'Error desconocido'));
            }
        } catch (error) {
            console.error('Error cargando dashboard:', error);
        }
    }

    async function cargarCatalogos() {
        try {
            // Cargar unidades de medida
            const resUnidades = await fetch(`${baseUrl}/api/centro_inventarios.php?action=unidades`);
            const dataUnidades = await resUnidades.json();
            if (dataUnidades.success) {
                unidadesMedida = dataUnidades.unidades;
            }

            // Cargar ubicaciones
            const resUbicaciones = await fetch(`${baseUrl}/api/centro_inventarios.php?action=ubicaciones`);
            const dataUbicaciones = await resUbicaciones.json();
            if (dataUbicaciones.success) {
                ubicaciones = dataUbicaciones.ubicaciones;
            }
        } catch (error) {
            console.error('Error cargando catálogos:', error);
        }
    }

    // ========== RENDERIZADO DE TIPOS ==========
    function renderTiposInventario(tipos) {
        const container = document.getElementById('tiposGrid');

        if (tipos.length === 0) {
            container.innerHTML = '<p class="empty">No hay tipos de inventario configurados</p>';
            return;
        }

        container.innerHTML = tipos.map(tipo => {
            const color = tipo.color || '#007bff';
            const tieneItems = parseInt(tipo.total_items) > 0;
            const alertas = parseInt(tipo.sin_stock) + parseInt(tipo.stock_critico);

            return `
            <div class="tipo-card fade-in" 
                 style="--tipo-color: ${color}"
                 onclick="seleccionarTipo(${tipo.id_tipo_inventario})"
                 id="tipoCard_${tipo.id_tipo_inventario}">
                <div class="tipo-card-header">
                    <div class="tipo-icon" style="background: ${color}">
                        <i class="fas ${tipo.icono || 'fa-box'}"></i>
                    </div>
                    <div class="tipo-nombre">${tipo.nombre}</div>
                </div>
                <div class="tipo-stats">
                    <div class="tipo-stat">
                        <div class="tipo-stat-value">${tipo.total_items}</div>
                        <div class="tipo-stat-label">Items</div>
                    </div>
                    <div class="tipo-stat">
                        <div class="tipo-stat-value ${alertas > 0 ? 'text-danger' : ''}">${alertas}</div>
                        <div class="tipo-stat-label">Alertas</div>
                    </div>
                </div>
                <div class="tipo-valor-total">
                    <div class="valor">Bs. ${parseFloat(tipo.valor_total).toLocaleString('es-BO', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}</div>
                    <div class="label">Valor Total</div>
                </div>
            </div>
        `;
        }).join('');
    }

    // ========== SELECCIÓN DE TIPO ==========
    async function seleccionarTipo(idTipo) {
        // Mapeo de redireccionamiento por tipo de inventario
        const rutas = {
            1: 'materias_primas.php',
            2: 'colorantes_quimicos.php',
            3: 'empaque.php',
            4: 'accesorios.php',
            6: 'productos_terminados.php',
            7: 'repuestos.php'
        };

        const destino = rutas[idTipo];

        if (destino) {
            // Animación de salida opcional
            document.getElementById(`tipoCard_${idTipo}`).classList.add('active');

            // Redirigir a la página correspondiente
            window.location.href = destino;
        } else {
            // Manejo de módulos no implementados (Próximamente)
            const tipo = tiposInventario.find(t => t.id_tipo_inventario == idTipo);
            const nombre = tipo ? tipo.nombre : 'este módulo';

            alert(`ℹ️ El módulo de "${nombre}" se encuentra actualmente en desarrollo y estará disponible próximamente.`);

            // Si el workspace estaba abierto por alguna razón previa, ocultarlo
            const workspace = document.getElementById('workspace');
            if (workspace) workspace.classList.remove('active');
        }
    }

    async function cargarCategoriasDelTipo(idTipo) {
        const container = document.getElementById('categoriasGrid');
        container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner"></i><span>Cargando categorías...</span></div>';

        try {
            // Obtener categorías con sus valores
            const response = await fetch(`${baseUrl}/api/centro_inventarios.php?action=categorias_resumen&tipo_id=${idTipo}`);
            const data = await response.json();

            if (data.success && data.categorias) {
                categoriasInventario = data.categorias;
                renderCategorias(data.categorias);
            } else {
                // Si no hay endpoint especial, cargar categorías básicas
                const resCat = await fetch(`${baseUrl}/api/centro_inventarios.php?action=categorias&tipo_id=${idTipo}`);
                const dataCat = await resCat.json();

                if (dataCat.success) {
                    // Cargar productos para calcular totales por categoría
                    const resProd = await fetch(`${baseUrl}/api/centro_inventarios.php?action=list&tipo_id=${idTipo}`);
                    const dataProd = await resProd.json();

                    if (dataProd.success) {
                        productosInventario = dataProd.inventarios;

                        // Calcular totales por categoría
                        const categoriasConTotales = dataCat.categorias.map(function (cat) {
                            const productosCat = productosInventario.filter(function (p) { return p.id_categoria == cat.id_categoria; });
                            return Object.assign({}, cat, {
                                total_items: productosCat.length,
                                valor_total: productosCat.reduce(function (sum, p) { return sum + parseFloat(p.valor_total || 0); }, 0),
                                alertas: productosCat.filter(function (p) {
                                    const stock = parseFloat(p.stock_actual);
                                    const minimo = parseFloat(p.stock_minimo);
                                    return stock <= 0 || stock <= minimo;
                                }).length
                            });
                        });

                        categoriasInventario = categoriasConTotales;
                        renderCategorias(categoriasConTotales);
                    }
                }
            }
        } catch (error) {
            console.error('Error cargando categorías:', error);
            container.innerHTML = '<p class="error">Error al cargar categorías</p>';
        }
    }

    function renderCategorias(categorias) {
        const container = document.getElementById('categoriasGrid');
        const color = (tipoSeleccionado && tipoSeleccionado.color) ? tipoSeleccionado.color : '#007bff';

        if (categorias.length === 0) {
            container.innerHTML = `
            <div class="workspace-placeholder">
                <i class="fas fa-folder-open"></i>
                <h3>Sin categorías</h3>
                <p>Este tipo de inventario no tiene categorías configuradas</p>
            </div>
        `;
            return;
        }

        container.innerHTML = categorias.map(cat => `
        <div class="categoria-card fade-in" 
             style="--tipo-color: ${color}"
             onclick="seleccionarCategoria(${cat.id_categoria})"
             id="catCard_${cat.id_categoria}">
            <div class="categoria-header">
                <div class="categoria-info">
                    <h4>${cat.nombre}</h4>
                    <div class="codigo">${cat.codigo}</div>
                </div>
                <span class="categoria-badge" style="background: ${color}">${cat.total_items || 0}</span>
            </div>
            <div class="categoria-stats">
                <div class="categoria-stat">
                    <div class="value">${cat.total_items || 0}</div>
                    <div class="label">Items</div>
                </div>
                <div class="categoria-stat">
                    <div class="value ${(cat.alertas || 0) > 0 ? 'text-danger' : ''}">${cat.alertas || 0}</div>
                    <div class="label">Alertas</div>
                </div>
                <div class="categoria-stat">
                    <div class="value money">Bs. ${parseFloat(cat.valor_total || 0).toLocaleString('es-BO', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}</div>
                    <div class="label">Valor</div>
                </div>
            </div>
        </div>
    `).join('');
    }

    // ========== SELECCIÓN DE CATEGORÍA ==========
    let subcategoriasInventario = [];
    let subcategoriaSeleccionada = null;

    async function seleccionarCategoria(idCategoria) {
        // Marcar card como activo
        document.querySelectorAll('.categoria-card').forEach(function (card) { card.classList.remove('active'); });
        var cardElement = document.getElementById('catCard_' + idCategoria);
        if (cardElement) cardElement.classList.add('active');

        // Guardar categoría seleccionada
        categoriaSeleccionada = categoriasInventario.find(function (c) { return c.id_categoria == idCategoria; });

        if (!categoriaSeleccionada) return;

        // Ocultar subcategorías y productos anteriores
        document.getElementById('subcategoriasSection').style.display = 'none';
        document.getElementById('productosSection').classList.remove('active');
        document.getElementById('breadcrumbNav').style.display = 'none';
        subcategoriaSeleccionada = null;

        // Verificar si la categoría tiene subcategorías
        try {
            const response = await fetch(baseUrl + '/api/centro_inventarios.php?action=subcategorias_resumen&categoria_id=' + idCategoria);
            const data = await response.json();

            if (data.success && data.subcategorias && data.subcategorias.length > 0) {
                // Tiene subcategorías - mostrar subcategorías Y productos de la categoría
                subcategoriasInventario = data.subcategorias;
                mostrarSubcategorias(data.subcategorias);

                // TAMBIÉN mostrar productos de la categoría (para asignar subcategorías)
                cargarProductosCategoria(idCategoria);
            } else {
                // No tiene subcategorías - mostrar productos directamente
                cargarProductosCategoria(idCategoria);
            }
        } catch (error) {
            console.error('Error verificando subcategorías:', error);
            // En caso de error, intentar cargar productos directamente
            cargarProductosCategoria(idCategoria);
        }
    }

    function mostrarSubcategorias(subcategorias) {
        var container = document.getElementById('subcategoriasGrid');
        var color = (tipoSeleccionado && tipoSeleccionado.color) ? tipoSeleccionado.color : '#007bff';

        document.getElementById('subcategoriasTitulo').textContent = categoriaSeleccionada.nombre;
        document.getElementById('subcategoriasSection').style.display = 'block';

        container.innerHTML = subcategorias.map(function (sub) {
            return '<div class="categoria-card subcategoria-card fade-in" ' +
                'style="--tipo-color: ' + color + '" ' +
                'onclick="seleccionarSubcategoria(' + sub.id_subcategoria + ')" ' +
                'id="subCard_' + sub.id_subcategoria + '">' +
                '<div class="categoria-header">' +
                '<div class="categoria-info">' +
                '<h4>' + sub.nombre + '</h4>' +
                '<div class="codigo">' + sub.codigo + '</div>' +
                '</div>' +
                '<span class="categoria-badge" style="background: ' + color + '">' + (sub.total_items || 0) + '</span>' +
                '</div>' +
                '<div class="categoria-stats">' +
                '<div class="categoria-stat">' +
                '<div class="value">' + (sub.total_items || 0) + '</div>' +
                '<div class="label">Items</div>' +
                '</div>' +
                '<div class="categoria-stat">' +
                '<div class="value ' + ((sub.alertas || 0) > 0 ? 'text-danger' : '') + '">' + (sub.alertas || 0) + '</div>' +
                '<div class="label">Alertas</div>' +
                '</div>' +
                '<div class="categoria-stat">' +
                '<div class="value money">Bs. ' + parseFloat(sub.valor_total || 0).toLocaleString('es-BO', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + '</div>' +
                '<div class="label">Valor</div>' +
                '</div>' +
                '</div>' +
                '</div>';
        }).join('');

        // Scroll a subcategorías
        document.getElementById('subcategoriasSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function seleccionarSubcategoria(idSubcategoria) {
        // Marcar card como activo
        document.querySelectorAll('.subcategoria-card').forEach(function (card) { card.classList.remove('active'); });
        var cardElement = document.getElementById('subCard_' + idSubcategoria);
        if (cardElement) cardElement.classList.add('active');

        // Guardar subcategoría seleccionada
        subcategoriaSeleccionada = subcategoriasInventario.find(function (s) { return s.id_subcategoria == idSubcategoria; });

        if (!subcategoriaSeleccionada) return;

        // Mostrar breadcrumb
        document.getElementById('breadcrumbCategoria').textContent = categoriaSeleccionada.nombre;
        document.getElementById('breadcrumbSubcategoria').textContent = subcategoriaSeleccionada.nombre;
        document.getElementById('breadcrumbNav').style.display = 'flex';

        // Actualizar título de productos
        document.getElementById('productosTitulo').textContent = subcategoriaSeleccionada.nombre;
        document.getElementById('productosCategoria').textContent = subcategoriaSeleccionada.codigo;
        var color = (tipoSeleccionado && tipoSeleccionado.color) ? tipoSeleccionado.color : '#007bff';
        document.getElementById('productosCategoria').style.background = color;

        // Mostrar sección de productos con loading
        document.getElementById('productosSection').classList.add('active');
        document.getElementById('productosBody').innerHTML = '<tr><td colspan="8" class="text-center" style="padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Cargando productos...</td></tr>';

        // Cargar productos de la subcategoría desde la API
        try {
            var response = await fetch(baseUrl + '/api/centro_inventarios.php?action=list&subcategoria_id=' + idSubcategoria);
            var data = await response.json();

            if (data.success) {
                var productos = data.inventarios || [];
                renderProductos(productos);
            } else {
                document.getElementById('productosBody').innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error al cargar productos</td></tr>';
            }
        } catch (error) {
            console.error('Error:', error);
            document.getElementById('productosBody').innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error de conexión</td></tr>';
        }

        // Scroll a la tabla
        document.getElementById('productosSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function cargarProductosCategoria(idCategoria) {
        // Actualizar título de productos
        document.getElementById('productosTitulo').textContent = categoriaSeleccionada.nombre;
        document.getElementById('productosCategoria').textContent = categoriaSeleccionada.codigo;
        var color = (tipoSeleccionado && tipoSeleccionado.color) ? tipoSeleccionado.color : '#007bff';
        document.getElementById('productosCategoria').style.background = color;

        // Mostrar sección de productos con loading
        document.getElementById('productosSection').classList.add('active');
        document.getElementById('productosBody').innerHTML = '<tr><td colspan="8" class="text-center" style="padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Cargando productos...</td></tr>';

        // Cargar productos de la categoría desde la API
        try {
            var response = await fetch(baseUrl + '/api/centro_inventarios.php?action=list&tipo_id=' + tipoSeleccionado.id_tipo_inventario + '&categoria_id=' + idCategoria);
            var data = await response.json();

            if (data.success) {
                var productos = data.inventarios || [];
                renderProductos(productos);
            } else {
                document.getElementById('productosBody').innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error al cargar productos</td></tr>';
            }
        } catch (error) {
            console.error('Error:', error);
            document.getElementById('productosBody').innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error de conexión</td></tr>';
        }

        // Scroll a la tabla
        document.getElementById('productosSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function volverACategorias() {
        // Ocultar subcategorías, breadcrumb y productos
        document.getElementById('subcategoriasSection').style.display = 'none';
        document.getElementById('productosSection').classList.remove('active');
        document.getElementById('breadcrumbNav').style.display = 'none';
        subcategoriaSeleccionada = null;

        // Quitar selección de categoría
        document.querySelectorAll('.categoria-card').forEach(function (card) { card.classList.remove('active'); });
        categoriaSeleccionada = null;
    }

    // Variable para almacenar los productos de la categoría actual
    let productosCategoriaCargados = [];

    function renderProductos(productos) {
        const tbody = document.getElementById('productosBody');
        document.getElementById('totalProductos').textContent = `${productos.length} items`;

        // Guardar productos cargados para el filtro
        productosCategoriaCargados = productos;

        if (productos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center" style="padding: 40px; color: #6c757d;">No hay productos en esta categoría</td></tr>';
            return;
        }

        tbody.innerHTML = productos.map(prod => {
            const stock = parseFloat(prod.stock_actual);
            const minimo = parseFloat(prod.stock_minimo);
            let estadoClass = 'ok';
            let estadoText = 'OK';

            if (stock <= 0) {
                estadoClass = 'sin-stock';
                estadoText = 'Sin Stock';
            } else if (stock <= minimo) {
                estadoClass = 'critico';
                estadoText = 'Crítico';
            } else if (stock <= minimo * 1.5) {
                estadoClass = 'bajo';
                estadoText = 'Bajo';
            }

            const valor = parseFloat(prod.valor_total || 0);
            const costo = parseFloat(prod.costo_unitario || 0);

            return `
            <tr>
                <td class="codigo">${prod.codigo}</td>
                <td class="nombre">${prod.nombre}</td>
                <td class="text-right stock ${estadoClass}">${stock.toFixed(2)}</td>
                <td>${prod.unidad || '-'}</td>
                <td><span class="estado-badge ${estadoClass}">${estadoText}</span></td>
                <td class="text-right">Bs. ${costo.toFixed(4)}</td>
                <td class="text-right valor">Bs. ${valor.toLocaleString('es-BO', { minimumFractionDigits: 2 })}</td>
                <td class="acciones">
                    <button class="btn-accion kardex" onclick="verKardex(${prod.id_inventario})" title="Ver Kardex">
                        <i class="fas fa-book"></i>
                    </button>
                    <button class="btn-accion editar" onclick="editarItem(${prod.id_inventario})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                </td>
            </tr>
        `;
        }).join('');
    }

    function filtrarProductos() {
        const buscar = document.getElementById('buscarProducto').value.toLowerCase().trim();

        if (!buscar) {
            // Si no hay búsqueda, mostrar todos
            renderProductosFiltrados(productosCategoriaCargados);
            return;
        }

        // Filtrar de los productos cargados
        const productosFiltrados = productosCategoriaCargados.filter(p =>
            p.codigo.toLowerCase().includes(buscar) ||
            p.nombre.toLowerCase().includes(buscar)
        );

        renderProductosFiltrados(productosFiltrados);
    }

    function renderProductosFiltrados(productos) {
        const tbody = document.getElementById('productosBody');
        document.getElementById('totalProductos').textContent = `${productos.length} items`;

        if (productos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center" style="padding: 40px; color: #6c757d;">No se encontraron productos</td></tr>';
            return;
        }

        tbody.innerHTML = productos.map(prod => {
            const stock = parseFloat(prod.stock_actual);
            const minimo = parseFloat(prod.stock_minimo);
            let estadoClass = 'ok';
            let estadoText = 'OK';

            if (stock <= 0) {
                estadoClass = 'sin-stock';
                estadoText = 'Sin Stock';
            } else if (stock <= minimo) {
                estadoClass = 'critico';
                estadoText = 'Crítico';
            } else if (stock <= minimo * 1.5) {
                estadoClass = 'bajo';
                estadoText = 'Bajo';
            }

            const valor = parseFloat(prod.valor_total || 0);
            const costo = parseFloat(prod.costo_unitario || 0);

            return `
            <tr>
                <td class="codigo">${prod.codigo}</td>
                <td class="nombre">${prod.nombre}</td>
                <td class="text-right stock ${estadoClass}">${stock.toFixed(2)}</td>
                <td>${prod.unidad || '-'}</td>
                <td><span class="estado-badge ${estadoClass}">${estadoText}</span></td>
                <td class="text-right">Bs. ${costo.toFixed(4)}</td>
                <td class="text-right valor">Bs. ${valor.toLocaleString('es-BO', { minimumFractionDigits: 2 })}</td>
                <td class="acciones">
                    <button class="btn-accion kardex" onclick="verKardex(${prod.id_inventario})" title="Ver Kardex">
                        <i class="fas fa-book"></i>
                    </button>
                    <button class="btn-accion editar" onclick="editarItem(${prod.id_inventario})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                </td>
            </tr>
        `;
        }).join('');
    }

    // ========== NAVEGACIÓN ==========
    function volverATipos() {
        document.getElementById('workspace').classList.remove('active');
        document.querySelectorAll('.tipo-card').forEach(card => card.classList.remove('active'));
        tipoSeleccionado = null;
        categoriaSeleccionada = null;
    }

    // ========== ACCIONES CONTEXTUALES ==========
    function abrirIngreso() {
        if (!tipoSeleccionado) {
            alert('Seleccione un tipo de inventario primero');
            return;
        }
        // Abrir modal de ingreso filtrado por tipo
        openModalMultiContextual('ENTRADA');
    }

    function abrirSalida() {
        if (!tipoSeleccionado) {
            alert('Seleccione un tipo de inventario primero');
            return;
        }
        // Abrir modal de salida filtrado por tipo
        openModalSalidaContextual();
    }

    function abrirHistorial() {
        if (!tipoSeleccionado) {
            alert('Seleccione un tipo de inventario primero');
            return;
        }
        // Abrir historial filtrado por tipo
        openModalHistoricoContextual();
    }

    function abrirNuevoItem() {
        if (!tipoSeleccionado) {
            alert('Seleccione un tipo de inventario primero');
            return;
        }
        openModalNuevoItem();
    }

    // ========== MODAL NUEVO ITEM ==========
    async function openModalNuevoItem() {
        const modal = document.getElementById('modalInventario');

        // Limpiar formulario
        document.getElementById('formInventario').reset();
        document.getElementById('idInventario').value = '';
        document.getElementById('tipoInventarioHidden').value = tipoSeleccionado.id_tipo_inventario;
        document.getElementById('tipoInventarioDisplay').value = tipoSeleccionado.nombre;
        document.getElementById('modalTitulo').innerHTML = `<i class="fas fa-plus"></i> Nuevo Item - ${tipoSeleccionado.nombre}`;

        // Cargar categorías del tipo seleccionado
        await cargarSelectCategorias();
        cargarSelectUnidades();
        cargarSelectUbicaciones();

        modal.classList.add('show');
    }

    async function cargarSelectCategorias() {
        const select = document.getElementById('idCategoria');
        select.innerHTML = '<option value="">Cargando...</option>';

        // Limpiar subcategorías
        document.getElementById('idSubcategoria').innerHTML = '<option value="">Sin subcategoría</option>';

        try {
            const response = await fetch(`${baseUrl}/api/centro_inventarios.php?action=categorias&tipo_id=${tipoSeleccionado.id_tipo_inventario}`);
            const data = await response.json();

            if (data.success) {
                select.innerHTML = '<option value="">Seleccione...</option>' +
                    data.categorias.map(cat => `<option value="${cat.id_categoria}">${cat.codigo} - ${cat.nombre}</option>`).join('');
            }
        } catch (error) {
            console.error('Error:', error);
            select.innerHTML = '<option value="">Error al cargar</option>';
        }
    }

    // Cargar subcategorías cuando cambia la categoría
    async function cargarSubcategoriasParaItem() {
        var categoriaId = document.getElementById('idCategoria').value;
        var select = document.getElementById('idSubcategoria');

        if (!categoriaId) {
            select.innerHTML = '<option value="">Sin subcategoría</option>';
            return;
        }

        select.innerHTML = '<option value="">Cargando...</option>';

        try {
            var response = await fetch(baseUrl + '/api/centro_inventarios.php?action=subcategorias&categoria_id=' + categoriaId);
            var data = await response.json();

            if (data.success && data.subcategorias.length > 0) {
                select.innerHTML = '<option value="">Sin subcategoría</option>' +
                    data.subcategorias.map(function (sub) {
                        return '<option value="' + sub.id_subcategoria + '">' + sub.codigo + ' - ' + sub.nombre + '</option>';
                    }).join('');
            } else {
                select.innerHTML = '<option value="">No hay subcategorías</option>';
            }
        } catch (error) {
            console.error('Error:', error);
            select.innerHTML = '<option value="">Error al cargar</option>';
        }
    }

    function cargarSelectUnidades() {
        const select = document.getElementById('idUnidad');
        select.innerHTML = '<option value="">Seleccione...</option>' +
            unidadesMedida.map(u => `<option value="${u.id_unidad}">${u.abreviatura} - ${u.nombre}</option>`).join('');
    }

    function cargarSelectUbicaciones() {
        const select = document.getElementById('idUbicacion');
        select.innerHTML = '<option value="">Sin ubicación específica</option>' +
            ubicaciones.map(u => `<option value="${u.id_ubicacion}">${u.codigo} - ${u.nombre}</option>`).join('');
    }

    function closeModal() {
        document.getElementById('modalInventario').classList.remove('show');
    }

    async function guardarInventario() {
        const form = document.getElementById('formInventario');

        // Obtener valor de subcategoría correctamente
        var subcategoriaValue = document.getElementById('idSubcategoria').value;
        var idSubcategoriaFinal = (subcategoriaValue && subcategoriaValue !== '' && subcategoriaValue !== '0') ? parseInt(subcategoriaValue) : null;

        const payload = {
            id_inventario: document.getElementById('idInventario').value || null,
            codigo: document.getElementById('codigo').value.trim(),
            nombre: document.getElementById('nombre').value.trim(),
            descripcion: document.getElementById('descripcion').value.trim(),
            id_tipo_inventario: document.getElementById('tipoInventarioHidden').value,
            id_categoria: document.getElementById('idCategoria').value,
            id_subcategoria: idSubcategoriaFinal,
            id_unidad: document.getElementById('idUnidad').value,
            stock_actual: parseFloat(document.getElementById('stockActual').value) || 0,
            stock_minimo: parseFloat(document.getElementById('stockMinimo').value) || 0,
            costo_unitario: parseFloat(document.getElementById('costoUnitario').value) || 0,
            id_ubicacion: document.getElementById('idUbicacion').value || null,
            proveedor_principal: document.getElementById('proveedorPrincipal').value.trim()
        };

        // Debug - ver qué se está enviando
        console.log('Guardando inventario:', payload);
        console.log('Subcategoría seleccionada:', subcategoriaValue, '→', idSubcategoriaFinal);

        if (!payload.codigo || !payload.nombre || !payload.id_categoria || !payload.id_unidad) {
            alert('⚠️ Complete los campos requeridos');
            return;
        }

        try {
            const response = await fetch(`${baseUrl}/api/centro_inventarios.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            console.log('Respuesta servidor:', data);

            if (data.success) {
                alert('✅ ' + data.message);
                closeModal();
                cargarDashboard();
                if (tipoSeleccionado) {
                    seleccionarTipo(tipoSeleccionado.id_tipo_inventario);
                }
            } else {
                alert('❌ ' + (data.message || 'Error al guardar'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('❌ Error de conexión');
        }
    }

    async function editarItem(idInventario) {
        try {
            const response = await fetch(`${baseUrl}/api/centro_inventarios.php?action=detalle&id=${idInventario}`);
            const data = await response.json();

            if (data.success && data.item) {
                const item = data.item;

                document.getElementById('idInventario').value = item.id_inventario;
                document.getElementById('codigo').value = item.codigo;
                document.getElementById('nombre').value = item.nombre;
                document.getElementById('descripcion').value = item.descripcion || '';
                document.getElementById('tipoInventarioHidden').value = item.id_tipo_inventario;
                document.getElementById('tipoInventarioDisplay').value = item.tipo_nombre;
                document.getElementById('stockActual').value = item.stock_actual;
                document.getElementById('stockMinimo').value = item.stock_minimo;
                document.getElementById('costoUnitario').value = item.costo_unitario;
                document.getElementById('proveedorPrincipal').value = item.proveedor_principal || '';

                await cargarSelectCategorias();
                cargarSelectUnidades();
                cargarSelectUbicaciones();

                // Esperar un momento para que se carguen los selects
                setTimeout(async function () {
                    document.getElementById('idCategoria').value = item.id_categoria;
                    document.getElementById('idUnidad').value = item.id_unidad;
                    document.getElementById('idUbicacion').value = item.id_ubicacion || '';

                    // Cargar subcategorías de la categoría seleccionada
                    if (item.id_categoria) {
                        await cargarSubcategoriasParaItem();
                        // Esperar a que se carguen las subcategorías
                        setTimeout(function () {
                            if (item.id_subcategoria) {
                                document.getElementById('idSubcategoria').value = item.id_subcategoria;
                            }
                        }, 200);
                    }
                }, 300);

                document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-edit"></i> Editar: ' + item.nombre;
                document.getElementById('modalInventario').classList.add('show');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('❌ Error al cargar datos del item');
        }
    }

    // ========== KARDEX ==========
    async function verKardex(idInventario) {
        const modal = document.getElementById('modalKardex');
        const content = document.getElementById('kardexContent');

        content.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner"></i><span>Cargando kardex...</span></div>';
        modal.classList.add('show');

        try {
            const response = await fetch(`${baseUrl}/api/centro_inventarios.php?action=kardex&id=${idInventario}`);
            const data = await response.json();

            if (data.success) {
                const producto = productosInventario.find(p => p.id_inventario == idInventario);
                document.getElementById('kardexTitulo').textContent = 'Kardex: ' + (producto && producto.nombre ? producto.nombre : 'Producto');

                if (data.movimientos.length === 0) {
                    content.innerHTML = '<p class="text-center" style="padding: 40px; color: #6c757d;">No hay movimientos registrados</p>';
                    return;
                }

                content.innerHTML = `
                <table class="kardex-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Documento</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">Costo Unit.</th>
                            <th class="text-right">Stock</th>
                            <th class="text-right">CPP</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.movimientos.map(mov => {
                    const esEntrada = mov.tipo_movimiento.includes('ENTRADA');
                    const fecha = new Date(mov.fecha_movimiento);
                    return `
                                <tr>
                                    <td>${fecha.toLocaleDateString('es-BO')}</td>
                                    <td class="${esEntrada ? 'kardex-entrada' : 'kardex-salida'}">
                                        ${esEntrada ? '↓' : '↑'} ${mov.tipo_movimiento.replace('ENTRADA_', '').replace('SALIDA_', '')}
                                    </td>
                                    <td>${mov.documento_numero || '-'}</td>
                                    <td class="text-right ${esEntrada ? 'kardex-entrada' : 'kardex-salida'}">
                                        ${esEntrada ? '+' : '-'}${parseFloat(mov.cantidad).toFixed(2)}
                                    </td>
                                    <td class="text-right">Bs. ${parseFloat(mov.costo_unitario).toFixed(4)}</td>
                                    <td class="text-right">${parseFloat(mov.stock_nuevo).toFixed(2)}</td>
                                    <td class="text-right">Bs. ${parseFloat(mov.costo_promedio_resultado).toFixed(4)}</td>
                                </tr>
                            `;
                }).join('')}
                    </tbody>
                </table>
            `;
            }
        } catch (error) {
            console.error('Error:', error);
            content.innerHTML = '<p class="text-center text-danger">Error al cargar kardex</p>';
        }
    }

    function closeModalKardex() {
        document.getElementById('modalKardex').classList.remove('show');
    }

    // ========== FUNCIONES PLACEHOLDER PARA MODALES CONTEXTUALES ==========
    // Estas funciones conectarán con los modales existentes pero filtrados por tipo



    // ========== MODAL CONFIGURACIÓN ==========

    let tiposConfig = [];
    let categoriasConfig = [];

    function openModalConfig() {
        document.getElementById('modalConfig').classList.add('show');
        cambiarTabConfig('tipos');
    }

    function closeModalConfig() {
        document.getElementById('modalConfig').classList.remove('show');
        // Recargar dashboard para reflejar cambios
        cargarDashboard();
    }

    async function cambiarTabConfig(tab, tabButton = null) {
        // Cambiar tabs activos
        document.querySelectorAll('.config-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.config-panel').forEach(p => p.classList.remove('active'));

        // Si se pasa un botón específico, usarlo; si no, buscar el botón correcto
        if (tabButton) {
            tabButton.classList.add('active');
        } else {
            // Buscar el botón de tab correcto por su onclick o data attribute
            const tabs = document.querySelectorAll('.config-tab');
            tabs.forEach(t => {
                if (t.textContent.toLowerCase().includes(tab)) {
                    t.classList.add('active');
                }
            });
        }

        document.getElementById('panel' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('active');

        // Cargar datos y esperar a que terminen
        if (tab === 'tipos') {
            await cargarTiposConfig();
        } else if (tab === 'categorias') {
            await cargarCategoriasConfig();
        } else if (tab === 'subcategorias') {
            await cargarSubcategoriasConfig();
        }
    }

    // ========== TIPOS DE INVENTARIO ==========

    async function cargarTiposConfig() {
        const lista = document.getElementById('listaTipos');
        lista.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><span>Cargando...</span></div>';

        try {
            const response = await fetch(`${baseUrl}/api/centro_inventarios.php?action=tipos`);
            const data = await response.json();

            if (data.success) {
                tiposConfig = data.tipos;
                renderTiposConfig(data.tipos);

                // También cargar en el select de categorías
                const selectTipo = document.getElementById('filtroTipoCategoria');
                const selectCategoriaTipo = document.getElementById('categoriaTipo');

                const opciones = '<option value="">-- Todos --</option>' +
                    data.tipos.map(t => `<option value="${t.id_tipo_inventario}">${t.nombre}</option>`).join('');

                selectTipo.innerHTML = opciones;
                selectCategoriaTipo.innerHTML = '<option value="">Seleccione...</option>' +
                    data.tipos.map(t => `<option value="${t.id_tipo_inventario}">${t.nombre}</option>`).join('');
            }
        } catch (error) {
            console.error('Error:', error);
            lista.innerHTML = '<p class="text-danger">Error al cargar tipos</p>';
        }
    }

    function renderTiposConfig(tipos) {
        const lista = document.getElementById('listaTipos');

        if (tipos.length === 0) {
            lista.innerHTML = '<p class="text-center" style="padding: 20px; color: #6c757d;">No hay tipos de inventario</p>';
            return;
        }

        lista.innerHTML = tipos.map(tipo => `
        <div class="config-item" style="cursor: pointer;" onclick="verCategoriasDeTipo(${tipo.id_tipo_inventario})">
            <div class="config-item-info">
                <div class="config-item-icon" style="background: ${tipo.color || '#007bff'}">
                    <i class="fas ${tipo.icono || 'fa-box'}"></i>
                </div>
                <div class="config-item-details">
                    <h4>${tipo.nombre}</h4>
                    <span>${tipo.codigo} | Orden: ${tipo.orden || 1}</span>
                </div>
            </div>
            <div class="config-item-actions">
                <button class="btn-editar-config" onclick="event.stopPropagation(); editarTipo(${tipo.id_tipo_inventario})" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
        </div>
    `).join('');
    }

    function mostrarFormTipo(tipo = null) {
        document.getElementById('formTipoContainer').style.display = 'block';

        if (tipo) {
            document.getElementById('formTipoTitulo').innerHTML = '<i class="fas fa-edit"></i> Editar Tipo';
            document.getElementById('tipoId').value = tipo.id_tipo_inventario;
            document.getElementById('tipoCodigo').value = tipo.codigo;
            document.getElementById('tipoNombre').value = tipo.nombre;
            document.getElementById('tipoIcono').value = tipo.icono || '';
            document.getElementById('tipoColor').value = tipo.color || '#007bff';
            document.getElementById('tipoOrden').value = tipo.orden || 1;
        } else {
            document.getElementById('formTipoTitulo').innerHTML = '<i class="fas fa-plus"></i> Nuevo Tipo';
            document.getElementById('tipoId').value = '';
            document.getElementById('tipoCodigo').value = '';
            document.getElementById('tipoNombre').value = '';
            document.getElementById('tipoIcono').value = 'fa-box';
            document.getElementById('tipoColor').value = '#007bff';
            document.getElementById('tipoOrden').value = 1;
        }
    }

    function cancelarFormTipo() {
        document.getElementById('formTipoContainer').style.display = 'none';
    }

    function editarTipo(id) {
        const tipo = tiposConfig.find(t => t.id_tipo_inventario == id);
        if (tipo) {
            mostrarFormTipo(tipo);
        }
    }

    async function guardarTipo() {
        const payload = {
            action: 'guardar_tipo',
            id_tipo_inventario: document.getElementById('tipoId').value || null,
            codigo: document.getElementById('tipoCodigo').value.trim(),
            nombre: document.getElementById('tipoNombre').value.trim(),
            icono: document.getElementById('tipoIcono').value.trim(),
            color: document.getElementById('tipoColor').value,
            orden: parseInt(document.getElementById('tipoOrden').value) || 1
        };

        if (!payload.codigo || !payload.nombre) {
            alert('⚠️ Código y Nombre son requeridos');
            return;
        }

        try {
            const response = await fetch(`${baseUrl}/api/centro_inventarios.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (data.success) {
                alert('✅ ' + data.message);
                cancelarFormTipo();
                cargarTiposConfig();
            } else {
                alert('❌ ' + (data.message || 'Error al guardar'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('❌ Error de conexión');
        }
    }

    // ========== CATEGORÍAS ==========

    async function cargarCategoriasConfig() {
        const lista = document.getElementById('listaCategorias');
        lista.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><span>Cargando...</span></div>';

        // Primero cargar tipos si no están cargados
        if (tiposConfig.length === 0) {
            await cargarTiposConfig();
        }

        cargarCategoriasPorTipo();
    }

    async function cargarCategoriasPorTipo() {
        const tipoId = document.getElementById('filtroTipoCategoria').value;
        const lista = document.getElementById('listaCategorias');
        lista.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><span>Cargando...</span></div>';

        try {
            let url = `${baseUrl}/api/centro_inventarios.php?action=categorias`;
            if (tipoId) {
                url += `&tipo_id=${tipoId}`;
            }

            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                categoriasConfig = data.categorias;
                renderCategoriasConfig(data.categorias);
            }
        } catch (error) {
            console.error('Error:', error);
            lista.innerHTML = '<p class="text-danger">Error al cargar categorías</p>';
        }
    }

    function renderCategoriasConfig(categorias) {
        const lista = document.getElementById('listaCategorias');

        if (categorias.length === 0) {
            lista.innerHTML = '<p class="text-center" style="padding: 20px; color: #6c757d;">No hay categorías para mostrar</p>';
            return;
        }

        // Agrupar por tipo
        const porTipo = {};
        categorias.forEach(cat => {
            const tipoNombre = cat.tipo_nombre || 'Sin tipo';
            if (!porTipo[tipoNombre]) {
                porTipo[tipoNombre] = [];
            }
            porTipo[tipoNombre].push(cat);
        });

        let html = '';
        for (const [tipoNombre, cats] of Object.entries(porTipo)) {
            html += `<h5 style="margin: 15px 0 10px; color: #495057; border-bottom: 1px solid #e9ecef; padding-bottom: 5px;">${tipoNombre}</h5>`;
            cats.forEach(cat => {
                const tipo = tiposConfig.find(t => t.id_tipo_inventario == cat.id_tipo_inventario);
                const color = (tipo && tipo.color) ? tipo.color : '#6c757d';

                html += `
                <div class="config-item" style="cursor: pointer;" onclick="verSubcategoriasDeCategoria(${cat.id_categoria})">
                    <div class="config-item-info">
                        <div class="config-item-icon" style="background: ${color}">
                            <i class="fas fa-folder"></i>
                        </div>
                        <div class="config-item-details">
                            <h4>${cat.nombre}</h4>
                            <span>${cat.codigo} | Orden: ${cat.orden || 1}</span>
                        </div>
                    </div>
                    <div class="config-item-actions">
                        <button class="btn-editar-config" onclick="event.stopPropagation(); editarCategoria(${cat.id_categoria})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
            `;
            });
        }

        lista.innerHTML = html;
    }

    function mostrarFormCategoria(categoria = null) {
        document.getElementById('formCategoriaContainer').style.display = 'block';

        if (categoria) {
            document.getElementById('formCategoriaTitulo').innerHTML = '<i class="fas fa-edit"></i> Editar Categoría';
            document.getElementById('categoriaId').value = categoria.id_categoria;
            document.getElementById('categoriaTipo').value = categoria.id_tipo_inventario;
            document.getElementById('categoriaCodigo').value = categoria.codigo;
            document.getElementById('categoriaNombre').value = categoria.nombre;
            document.getElementById('categoriaOrden').value = categoria.orden || 1;
        } else {
            document.getElementById('formCategoriaTitulo').innerHTML = '<i class="fas fa-plus"></i> Nueva Categoría';
            document.getElementById('categoriaId').value = '';
            document.getElementById('categoriaTipo').value = document.getElementById('filtroTipoCategoria').value || '';
            document.getElementById('categoriaCodigo').value = '';
            document.getElementById('categoriaNombre').value = '';
            document.getElementById('categoriaOrden').value = 1;
        }
    }

    function cancelarFormCategoria() {
        document.getElementById('formCategoriaContainer').style.display = 'none';
    }

    function editarCategoria(id) {
        const cat = categoriasConfig.find(c => c.id_categoria == id);
        if (cat) {
            mostrarFormCategoria(cat);
        }
    }

    async function guardarCategoria() {
        const payload = {
            action: 'guardar_categoria',
            id_categoria: document.getElementById('categoriaId').value || null,
            id_tipo_inventario: document.getElementById('categoriaTipo').value,
            codigo: document.getElementById('categoriaCodigo').value.trim(),
            nombre: document.getElementById('categoriaNombre').value.trim(),
            orden: parseInt(document.getElementById('categoriaOrden').value) || 1
        };

        if (!payload.id_tipo_inventario || !payload.codigo || !payload.nombre) {
            alert('⚠️ Tipo, Código y Nombre son requeridos');
            return;
        }

        try {
            const response = await fetch(`${baseUrl}/api/centro_inventarios.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (data.success) {
                alert('✅ ' + data.message);
                cancelarFormCategoria();
                cargarCategoriasPorTipo();
            } else {
                alert('❌ ' + (data.message || 'Error al guardar'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('❌ Error de conexión');
        }
    }

    // ========== SUBCATEGORÍAS ==========
    let subcategoriasConfig = [];

    async function cargarSubcategoriasConfig() {
        var lista = document.getElementById('listaSubcategorias');
        lista.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><span>Cargando...</span></div>';

        // Cargar tipos y categorías para los selects
        if (tiposConfig.length === 0) {
            await cargarTiposConfig();
        }

        // Cargar todas las categorías para el filtro
        try {
            var response = await fetch(baseUrl + '/api/centro_inventarios.php?action=categorias');
            var data = await response.json();

            if (data.success) {
                var select = document.getElementById('filtroCategoriaSub');
                var selectForm = document.getElementById('subcategoriaCategoria');

                var opciones = data.categorias.map(function (c) {
                    return '<option value="' + c.id_categoria + '">' + c.nombre + ' (' + (c.tipo_nombre || '') + ')</option>';
                }).join('');

                select.innerHTML = '<option value="">-- Todas --</option>' + opciones;
                selectForm.innerHTML = '<option value="">Seleccione...</option>' + opciones;
            }
        } catch (error) {
            console.error('Error:', error);
        }

        cargarSubcategoriasPorCategoria();
    }

    async function cargarSubcategoriasPorCategoria() {
        var categoriaId = document.getElementById('filtroCategoriaSub').value;
        var lista = document.getElementById('listaSubcategorias');
        lista.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><span>Cargando...</span></div>';

        try {
            var url = baseUrl + '/api/centro_inventarios.php?action=subcategorias';
            if (categoriaId) {
                url += '&categoria_id=' + categoriaId;
            }

            var response = await fetch(url);
            var data = await response.json();

            if (data.success) {
                subcategoriasConfig = data.subcategorias;
                renderSubcategoriasConfig(data.subcategorias);
            }
        } catch (error) {
            console.error('Error:', error);
            lista.innerHTML = '<p class="text-danger">Error al cargar subcategorías</p>';
        }
    }

    function renderSubcategoriasConfig(subcategorias) {
        var lista = document.getElementById('listaSubcategorias');

        if (subcategorias.length === 0) {
            lista.innerHTML = '<p class="text-center" style="padding: 20px; color: #6c757d;">No hay subcategorías. ¡Crea la primera!</p>';
            return;
        }

        // Agrupar por categoría
        var porCategoria = {};
        subcategorias.forEach(function (sub) {
            var catNombre = sub.categoria_nombre || 'Sin categoría';
            if (!porCategoria[catNombre]) {
                porCategoria[catNombre] = [];
            }
            porCategoria[catNombre].push(sub);
        });

        var html = '';
        for (var catNombre in porCategoria) {
            html += '<h5 style="margin: 15px 0 10px; color: #495057; border-bottom: 1px solid #e9ecef; padding-bottom: 5px;">' + catNombre + '</h5>';
            porCategoria[catNombre].forEach(function (sub) {
                html += '<div class="config-item">' +
                    '<div class="config-item-info">' +
                    '<div class="config-item-icon" style="background: #17a2b8">' +
                    '<i class="fas fa-folder-open"></i>' +
                    '</div>' +
                    '<div class="config-item-details">' +
                    '<h4>' + sub.nombre + '</h4>' +
                    '<span>' + sub.codigo + ' | Orden: ' + (sub.orden || 1) + '</span>' +
                    '</div>' +
                    '</div>' +
                    '<div class="config-item-actions">' +
                    '<button class="btn-editar-config" onclick="editarSubcategoria(' + sub.id_subcategoria + ')" title="Editar">' +
                    '<i class="fas fa-edit"></i>' +
                    '</button>' +
                    '</div>' +
                    '</div>';
            });
        }

        lista.innerHTML = html;
    }

    function mostrarFormSubcategoria(subcategoria) {
        document.getElementById('formSubcategoriaContainer').style.display = 'block';

        if (subcategoria) {
            document.getElementById('formSubcategoriaTitulo').innerHTML = '<i class="fas fa-edit"></i> Editar Subcategoría';
            document.getElementById('subcategoriaId').value = subcategoria.id_subcategoria;
            document.getElementById('subcategoriaCategoria').value = subcategoria.id_categoria;
            document.getElementById('subcategoriaCodigo').value = subcategoria.codigo;
            document.getElementById('subcategoriaNombre').value = subcategoria.nombre;
            document.getElementById('subcategoriaOrden').value = subcategoria.orden || 1;
        } else {
            document.getElementById('formSubcategoriaTitulo').innerHTML = '<i class="fas fa-plus"></i> Nueva Subcategoría';
            document.getElementById('subcategoriaId').value = '';
            document.getElementById('subcategoriaCategoria').value = document.getElementById('filtroCategoriaSub').value || '';
            document.getElementById('subcategoriaCodigo').value = '';
            document.getElementById('subcategoriaNombre').value = '';
            document.getElementById('subcategoriaOrden').value = 1;
        }
    }

    function cancelarFormSubcategoria() {
        document.getElementById('formSubcategoriaContainer').style.display = 'none';
    }

    function editarSubcategoria(id) {
        var sub = subcategoriasConfig.find(function (s) { return s.id_subcategoria == id; });
        if (sub) {
            mostrarFormSubcategoria(sub);
        }
    }

    async function guardarSubcategoria() {
        var payload = {
            action: 'guardar_subcategoria',
            id_subcategoria: document.getElementById('subcategoriaId').value || null,
            id_categoria: document.getElementById('subcategoriaCategoria').value,
            codigo: document.getElementById('subcategoriaCodigo').value.trim(),
            nombre: document.getElementById('subcategoriaNombre').value.trim(),
            orden: parseInt(document.getElementById('subcategoriaOrden').value) || 1
        };

        if (!payload.id_categoria || !payload.codigo || !payload.nombre) {
            alert('⚠️ Categoría, Código y Nombre son requeridos');
            return;
        }

        try {
            var response = await fetch(baseUrl + '/api/centro_inventarios.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            var data = await response.json();

            if (data.success) {
                alert('✅ ' + data.message);
                cancelarFormSubcategoria();
                cargarSubcategoriasPorCategoria();
            } else {
                alert('❌ ' + (data.message || 'Error al guardar'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('❌ Error de conexión');
        }
    }

    // ========== NAVEGACIÓN EN CASCADA ==========
    async function verCategoriasDeTipo(idTipo) {
        // Seleccionar tab categorías y esperar a que cargue
        await cambiarTabConfig('categorias');

        // Establecer filtro
        const select = document.getElementById('filtroTipoCategoria');
        select.value = idTipo;

        // Disparar evento change y cargar
        cargarCategoriasPorTipo();
    }

    async function verSubcategoriasDeCategoria(idCategoria) {
        // Seleccionar tab subcategorías y esperar a que cargue
        await cambiarTabConfig('subcategorias');

        // Ahora que los datos están cargados, establecer filtro
        const select = document.getElementById('filtroCategoriaSub');
        select.value = idCategoria;

        // Cargar subcategorías con el filtro aplicado
        cargarSubcategoriasPorCategoria();
    }

    // ========== VARIABLES GLOBALES ADICIONALES ==========
    let productosDisponibles = [];
    let proveedoresLista = [];
    // let lineasIngreso = [];
    let lineasSalida = [];
    let documentoActual = null;
    let reporteActual = null;

    // ========== REEMPLAZAR FUNCIONES PLACEHOLDER ==========

    // Reemplazar openModalMultiContextual
    function openModalMultiContextual(tipo) {
        if (tipo === 'ENTRADA') {
            abrirModalIngreso();
        } else {
            abrirModalSalida();
        }
    }

    // Reemplazar openModalSalidaContextual  
    function openModalSalidaContextual() {
        abrirModalSalida();
    }

    // Reemplazar openModalHistoricoContextual
    function openModalHistoricoContextual() {
        abrirModalHistorial();
    }

    // ========== CARGAR DATOS PARA MODALES ==========
    async function cargarProductosParaMovimientos() {
        try {
            let url = `${baseUrl}/api/centro_inventarios.php?action=list`;
            if (tipoSeleccionado) {
                url += `&tipo_id=${tipoSeleccionado.id_tipo_inventario}`;
            }
            const response = await fetch(url);
            const data = await response.json();
            if (data.success) {
                productosDisponibles = data.inventarios || [];
            }
        } catch (error) {
            console.error('Error cargando productos:', error);
        }
    }

    async function cargarProveedoresSelect() {
        try {
            const response = await fetch(`${baseUrl}/api/proveedores.php?action=list`);
            const data = await response.json();
            if (data.success) {
                proveedoresLista = data.proveedores || [];
                const select = document.getElementById('ingresoProveedor');
                if (select) {
                    select.innerHTML = '<option value="">-- Seleccione Proveedor --</option>';
                    proveedoresLista.forEach(p => {
                        select.innerHTML += `<option value="${p.id_proveedor}">${p.codigo} - ${p.razon_social}</option>`;
                    });
                }
            }
        } catch (error) {
            console.error('Error cargando proveedores:', error);
        }
    }

    // ========== MODAL INGRESO ==========
    async function abrirModalIngreso() {
        await cargarProductosParaMovimientos();
        await cargarProveedoresSelect();

        // Resetear formulario
        document.getElementById('formIngreso').reset();
        document.getElementById('ingresoFecha').value = new Date().toISOString().split('T')[0];
        document.getElementById('ingresoDocNumero').value = generarNumeroDocumento('ING');

        // Configurar checkbox IVA
        document.getElementById('ingresoConFactura').checked = false;
        document.getElementById('ingresoConFactura').onchange = function () {
            document.getElementById('filaIVA').style.display = this.checked ? 'table-row' : 'none';
            document.getElementById('filaBruto').style.display = this.checked ? 'table-row' : 'none';
            calcularTotalesIngreso();
        };

        document.getElementById('filaIVA').style.display = 'none';
        document.getElementById('filaBruto').style.display = 'none';

        lineasIngreso = [];
        renderLineasIngreso();
        agregarLineaIngreso();

        document.getElementById('modalIngreso').classList.add('show');
    }

    function closeModalIngreso() {
        document.getElementById('modalIngreso').classList.remove('show');
    }

    function agregarLineaIngreso() {
        const linea = {
            id: Date.now(),
            id_inventario: '',
            cantidad: 0,
            costo_bruto: 0,
            costo_neto: 0,
            subtotal: 0
        };
        lineasIngreso.push(linea);
        renderLineasIngreso();
    }

    function renderLineasIngreso() {
        const tbody = document.getElementById('lineasIngresoBody');
        tbody.innerHTML = '';

        lineasIngreso.forEach((linea, index) => {
            const optionsProductos = productosDisponibles.map(p =>
                `<option value="${p.id_inventario}" ${p.id_inventario == linea.id_inventario ? 'selected' : ''}>
                ${p.codigo} - ${p.nombre} (${p.unidad || 'UN'})
            </option>`
            ).join('');

            tbody.innerHTML += `
            <tr data-id="${linea.id}">
                <td style="padding: 8px;">
                    <select style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" 
                            onchange="actualizarLineaIngreso(${linea.id}, 'id_inventario', this.value)">
                        <option value="">-- Seleccione --</option>
                        ${optionsProductos}
                    </select>
                </td>
                <td style="padding: 8px;">
                    <input type="number" step="0.01" min="0" value="${linea.cantidad}" 
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; text-align: right;"
                           onchange="actualizarLineaIngreso(${linea.id}, 'cantidad', this.value)">
                </td>
                <td style="padding: 8px;">
                    <input type="number" step="0.01" min="0" value="${linea.costo_bruto}" 
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; text-align: right;"
                           onchange="actualizarLineaIngreso(${linea.id}, 'costo_bruto', this.value)" placeholder="Con IVA">
                </td>
                <td style="padding: 8px; text-align: right; font-weight: bold;">Bs. ${linea.costo_neto.toFixed(2)}</td>
                <td style="padding: 8px; text-align: right;">Bs. ${linea.subtotal.toFixed(2)}</td>
                <td style="padding: 8px; text-align: center;">
                    <button type="button" onclick="eliminarLineaIngreso(${linea.id})" 
                            style="background: #dc3545; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        });
    }

    function actualizarLineaIngreso(id, campo, valor) {
        const linea = lineasIngreso.find(l => l.id === id);
        if (!linea) return;

        linea[campo] = campo === 'id_inventario' ? valor : parseFloat(valor) || 0;

        const conFactura = document.getElementById('ingresoConFactura').checked;
        if (campo === 'costo_bruto' || campo === 'cantidad') {
            if (conFactura) {
                linea.costo_neto = linea.costo_bruto / 1.13;
            } else {
                linea.costo_neto = linea.costo_bruto;
            }
            linea.subtotal = linea.cantidad * linea.costo_neto;
        }

        calcularTotalesIngreso();
        renderLineasIngreso();
    }

    function eliminarLineaIngreso(id) {
        lineasIngreso = lineasIngreso.filter(l => l.id !== id);
        if (lineasIngreso.length === 0) agregarLineaIngreso();
        renderLineasIngreso();
        calcularTotalesIngreso();
    }

    function calcularTotalesIngreso() {
        const conFactura = document.getElementById('ingresoConFactura').checked;
        let totalNeto = 0;
        let totalBruto = 0;

        lineasIngreso.forEach(linea => {
            if (conFactura) {
                linea.costo_neto = linea.costo_bruto / 1.13;
            } else {
                linea.costo_neto = linea.costo_bruto;
            }
            linea.subtotal = linea.cantidad * linea.costo_neto;
            totalNeto += linea.subtotal;
            totalBruto += linea.cantidad * linea.costo_bruto;
        });

        const totalIVA = totalBruto - totalNeto;

        document.getElementById('ingresoTotalNeto').textContent = `Bs. ${totalNeto.toFixed(2)}`;
        document.getElementById('ingresoTotalIVA').textContent = `Bs. ${totalIVA.toFixed(2)}`;
        document.getElementById('ingresoTotalBruto').textContent = `Bs. ${totalBruto.toFixed(2)}`;
    }

    async function guardarIngreso() {
        const lineasValidas = lineasIngreso.filter(l => l.id_inventario && l.cantidad > 0);

        if (lineasValidas.length === 0) {
            alert('⚠️ Agregue al menos un producto con cantidad válida');
            return;
        }

        const docNumero = document.getElementById('ingresoDocNumero').value.trim();
        if (!docNumero) {
            alert('⚠️ Ingrese el número de documento');
            return;
        }

        const conFactura = document.getElementById('ingresoConFactura').checked;
        const proveedorSelect = document.getElementById('ingresoProveedor');

        const payload = {
            action: 'multiproducto',
            tipo_movimiento: 'ENTRADA_COMPRA',
            documento_tipo: document.getElementById('ingresoDocTipo').value,
            documento_numero: docNumero,
            proveedor: proveedorSelect.selectedOptions[0]?.text || '',
            id_proveedor: proveedorSelect.value || null,
            fecha: document.getElementById('ingresoFecha').value,
            observaciones: document.getElementById('ingresoObservaciones').value,
            con_factura: conFactura,
            lineas: lineasValidas.map(l => ({
                id_inventario: l.id_inventario,
                cantidad: l.cantidad,
                costo_unitario: l.costo_neto,
                costo_bruto: l.costo_bruto,
                valor_total_bruto: l.cantidad * l.costo_bruto
            }))
        };

        try {
            const response = await fetch(`${baseUrl}/api/centro_inventarios.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (data.success) {
                alert(`✅ ${data.message}\n\nDocumento: ${docNumero}\nProductos: ${data.movimientos_registrados || lineasValidas.length}`);
                closeModalIngreso();
                cargarDashboard();
                if (tipoSeleccionado) {
                    seleccionarTipo(tipoSeleccionado.id_tipo_inventario);
                }
            } else {
                alert('❌ ' + (data.message || 'Error al guardar'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('❌ Error de conexión');
        }
    }

    // ========== MODAL SALIDA ==========
    async function abrirModalSalida() {
        await cargarProductosParaMovimientos();

        document.getElementById('formSalida').reset();
        document.getElementById('salidaFecha').value = new Date().toISOString().split('T')[0];
        document.getElementById('salidaDocNumero').value = generarNumeroDocumento('SAL');

        lineasSalida = [];
        renderLineasSalida();
        agregarLineaSalida();

        document.getElementById('modalSalida').classList.add('show');
    }

    function closeModalSalida() {
        document.getElementById('modalSalida').classList.remove('show');
    }

    function agregarLineaSalida() {
        const linea = {
            id: Date.now(),
            id_inventario: '',
            stock_disponible: 0,
            cantidad: 0,
            costo_unitario: 0,
            subtotal: 0
        };
        lineasSalida.push(linea);
        renderLineasSalida();
    }

    function renderLineasSalida() {
        const tbody = document.getElementById('lineasSalidaBody');
        tbody.innerHTML = '';

        lineasSalida.forEach((linea, index) => {
            const optionsProductos = productosDisponibles.map(p =>
                `<option value="${p.id_inventario}" 
                data-stock="${p.stock_actual}" 
                data-costo="${p.costo_unitario}"
                ${p.id_inventario == linea.id_inventario ? 'selected' : ''}>
                ${p.codigo} - ${p.nombre}
            </option>`
            ).join('');

            tbody.innerHTML += `
            <tr data-id="${linea.id}">
                <td style="padding: 8px;">
                    <select style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" 
                            onchange="actualizarLineaSalida(${linea.id}, 'id_inventario', this)">
                        <option value="">-- Seleccione --</option>
                        ${optionsProductos}
                    </select>
                </td>
                <td style="padding: 8px; text-align: right; color: ${linea.stock_disponible < linea.cantidad ? 'red' : 'green'}; font-weight: bold;">
                    ${linea.stock_disponible.toFixed(2)}
                </td>
                <td style="padding: 8px;">
                    <input type="number" step="0.01" min="0" max="${linea.stock_disponible}" value="${linea.cantidad}" 
                           style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; text-align: right;"
                           onchange="actualizarLineaSalidaCantidad(${linea.id}, this.value)">
                </td>
                <td style="padding: 8px; text-align: right;">Bs. ${linea.costo_unitario.toFixed(2)}</td>
                <td style="padding: 8px; text-align: right;">Bs. ${linea.subtotal.toFixed(2)}</td>
                <td style="padding: 8px; text-align: center;">
                    <button type="button" onclick="eliminarLineaSalida(${linea.id})" 
                            style="background: #dc3545; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        });
    }

    function actualizarLineaSalida(id, campo, select) {
        const linea = lineasSalida.find(l => l.id === id);
        if (!linea) return;

        const option = select.selectedOptions[0];
        linea.id_inventario = select.value;
        linea.stock_disponible = parseFloat(option?.dataset.stock || 0);
        linea.costo_unitario = parseFloat(option?.dataset.costo || 0);
        linea.subtotal = linea.cantidad * linea.costo_unitario;

        calcularTotalesSalida();
        renderLineasSalida();
    }

    function actualizarLineaSalidaCantidad(id, valor) {
        const linea = lineasSalida.find(l => l.id === id);
        if (!linea) return;

        linea.cantidad = parseFloat(valor) || 0;
        linea.subtotal = linea.cantidad * linea.costo_unitario;

        calcularTotalesSalida();
        renderLineasSalida();
    }

    function eliminarLineaSalida(id) {
        lineasSalida = lineasSalida.filter(l => l.id !== id);
        if (lineasSalida.length === 0) agregarLineaSalida();
        renderLineasSalida();
        calcularTotalesSalida();
    }

    function calcularTotalesSalida() {
        let total = 0;
        lineasSalida.forEach(linea => {
            total += linea.subtotal;
        });
        document.getElementById('salidaTotal').textContent = `Bs. ${total.toFixed(2)}`;
    }

    async function guardarSalida() {
        const lineasValidas = lineasSalida.filter(l => l.id_inventario && l.cantidad > 0);

        if (lineasValidas.length === 0) {
            alert('⚠️ Agregue al menos un producto con cantidad válida');
            return;
        }

        // Validar stock
        for (const linea of lineasValidas) {
            if (linea.cantidad > linea.stock_disponible) {
                alert(`⚠️ Stock insuficiente. Disponible: ${linea.stock_disponible}`);
                return;
            }
        }

        const docNumero = document.getElementById('salidaDocNumero').value.trim();
        if (!docNumero) {
            alert('⚠️ Ingrese el número de documento');
            return;
        }

        const payload = {
            action: 'multiproducto',
            tipo_movimiento: document.getElementById('salidaTipoMov').value,
            documento_tipo: 'SALIDA',
            documento_numero: docNumero,
            proveedor: document.getElementById('salidaDestino').value,
            fecha: document.getElementById('salidaFecha').value,
            observaciones: document.getElementById('salidaObservaciones').value,
            lineas: lineasValidas.map(l => ({
                id_inventario: l.id_inventario,
                cantidad: l.cantidad,
                costo_unitario: l.costo_unitario
            }))
        };

        try {
            const response = await fetch(`${baseUrl}/api/centro_inventarios.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (data.success) {
                alert(`✅ ${data.message}\n\nDocumento: ${docNumero}`);
                closeModalSalida();
                cargarDashboard();
                if (tipoSeleccionado) {
                    seleccionarTipo(tipoSeleccionado.id_tipo_inventario);
                }
            } else {
                alert('❌ ' + (data.message || 'Error al guardar'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('❌ Error de conexión');
        }
    }

    // ========== MODAL HISTORIAL ==========
    function abrirModalHistorial() {
        const hoy = new Date();
        const hace30dias = new Date(hoy.getTime() - 30 * 24 * 60 * 60 * 1000);

        document.getElementById('historialFechaHasta').value = hoy.toISOString().split('T')[0];
        document.getElementById('historialFechaDesde').value = hace30dias.toISOString().split('T')[0];
        document.getElementById('historialTipoMov').value = '';
        document.getElementById('historialBuscar').value = '';

        document.getElementById('historialBody').innerHTML =
            '<tr><td colspan="7" style="text-align: center; padding: 30px;">Use los filtros y presione Buscar</td></tr>';

        document.getElementById('modalHistorial').classList.add('show');

        // Cargar automáticamente
        buscarHistorial();
    }

    function closeModalHistorial() {
        document.getElementById('modalHistorial').classList.remove('show');
    }

    async function buscarHistorial() {
        const fechaDesde = document.getElementById('historialFechaDesde').value;
        const fechaHasta = document.getElementById('historialFechaHasta').value;
        const tipoMov = document.getElementById('historialTipoMov').value;
        const buscar = document.getElementById('historialBuscar').value;

        let url = `${baseUrl}/api/centro_inventarios.php?action=historial`;
        if (fechaDesde) url += `&fecha_desde=${fechaDesde}`;
        if (fechaHasta) url += `&fecha_hasta=${fechaHasta}`;
        if (tipoMov) url += `&tipo_movimiento=${tipoMov}`;
        if (buscar) url += `&buscar=${encodeURIComponent(buscar)}`;
        if (tipoSeleccionado) url += `&tipo_id=${tipoSeleccionado.id_tipo_inventario}`;

        document.getElementById('historialBody').innerHTML =
            '<tr><td colspan="7" style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Buscando...</td></tr>';

        try {
            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                renderHistorial(data.documentos || []);
            } else {
                document.getElementById('historialBody').innerHTML =
                    `<tr><td colspan="7" style="text-align: center; padding: 30px; color: red;">${data.message}</td></tr>`;
            }
        } catch (error) {
            console.error('Error:', error);
            document.getElementById('historialBody').innerHTML =
                '<tr><td colspan="7" style="text-align: center; padding: 30px; color: red;">Error de conexión</td></tr>';
        }
    }

    function renderHistorial(documentos) {
        const tbody = document.getElementById('historialBody');

        if (documentos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 30px;">No se encontraron documentos</td></tr>';
            return;
        }

        tbody.innerHTML = documentos.map(doc => {
            const esEntrada = doc.tipo_movimiento && doc.tipo_movimiento.includes('ENTRADA');
            const esAnulado = doc.estado === 'ANULADO';
            const fecha = doc.fecha ? new Date(doc.fecha).toLocaleDateString('es-BO') : '-';

            return `
            <tr style="${esAnulado ? 'opacity: 0.6; text-decoration: line-through;' : ''}">
                <td style="padding: 12px;">${fecha}</td>
                <td style="padding: 12px;"><strong>${doc.documento_numero}</strong><br><small>${doc.documento_tipo || ''}</small></td>
                <td style="padding: 12px;">
                    <span style="color: ${esEntrada ? '#28a745' : '#dc3545'};">
                        ${esEntrada ? '↓' : '↑'} ${(doc.tipo_movimiento || '').replace('ENTRADA_', '').replace('SALIDA_', '')}
                    </span>
                </td>
                <td style="padding: 12px;">${doc.observaciones ? doc.observaciones.substring(0, 30) : '-'}</td>
                <td style="padding: 12px; text-align: right;">Bs. ${parseFloat(doc.total_documento || 0).toFixed(2)}</td>
                <td style="padding: 12px;"><span class="${esAnulado ? 'badge-anulado' : 'badge-activo'}">${doc.estado || 'ACTIVO'}</span></td>
                <td style="padding: 12px; text-align: center;">
                    <button onclick="verDetalleDocumento('${doc.documento_numero}')" 
                            style="background: #17a2b8; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;"
                            title="Ver detalle">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
        }).join('');
    }

    // ========== MODAL DETALLE DOCUMENTO ==========
    async function verDetalleDocumento(docNumero) {
        try {
            const response = await fetch(`${baseUrl}/api/centro_inventarios.php?action=documento_detalle&documento=${encodeURIComponent(docNumero)}`);
            const data = await response.json();

            if (data.success) {
                documentoActual = data;
                renderDetalleDocumento(data);
                document.getElementById('modalDetalleDoc').classList.add('show');
            } else {
                alert('❌ ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('❌ Error al cargar documento');
        }
    }

    function renderDetalleDocumento(data) {
        const cabecera = data.cabecera;
        const lineas = data.lineas || [];
        const esAnulado = cabecera.estado === 'ANULADO';

        document.getElementById('detalleDocTitulo').innerHTML =
            `<i class="fas fa-file-alt"></i> Documento: ${cabecera.documento_numero}`;

        document.getElementById('btnAnularDoc').style.display = esAnulado ? 'none' : 'inline-block';

        const fecha = cabecera.fecha ? new Date(cabecera.fecha).toLocaleDateString('es-BO') : '-';

        let html = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 8px;">
            <div>
                <p><strong>Tipo:</strong> ${cabecera.documento_tipo || '-'}</p>
                <p><strong>Fecha:</strong> ${fecha}</p>
                <p><strong>Movimiento:</strong> ${cabecera.tipo_movimiento || '-'}</p>
            </div>
            <div>
                <p><strong>Usuario:</strong> ${cabecera.usuario || 'N/A'}</p>
                <p><strong>Estado:</strong> <span class="${esAnulado ? 'badge-anulado' : 'badge-activo'}">${cabecera.estado || 'ACTIVO'}</span></p>
                <p><strong>Obs:</strong> ${cabecera.observaciones || '-'}</p>
            </div>
        </div>
        
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #343a40; color: white;">
                    <th style="padding: 10px;">Código</th>
                    <th style="padding: 10px;">Producto</th>
                    <th style="padding: 10px; text-align: right;">Cantidad</th>
                    <th style="padding: 10px; text-align: right;">Costo Unit.</th>
                    <th style="padding: 10px; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                ${lineas.map(l => `
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 10px;">${l.producto_codigo || l.codigo || '-'}</td>
                        <td style="padding: 10px;">${l.producto_nombre || l.nombre || '-'}</td>
                        <td style="padding: 10px; text-align: right;">${parseFloat(l.cantidad).toFixed(2)}</td>
                        <td style="padding: 10px; text-align: right;">Bs. ${parseFloat(l.costo_unitario).toFixed(2)}</td>
                        <td style="padding: 10px; text-align: right;">Bs. ${parseFloat(l.costo_total || l.cantidad * l.costo_unitario).toFixed(2)}</td>
                    </tr>
                `).join('')}
            </tbody>
            <tfoot>
                <tr style="background: #e9ecef; font-weight: bold;">
                    <td colspan="4" style="padding: 12px; text-align: right;">TOTAL:</td>
                    <td style="padding: 12px; text-align: right;">Bs. ${parseFloat(cabecera.total_documento || 0).toFixed(2)}</td>
                </tr>
            </tfoot>
        </table>
    `;

        document.getElementById('detalleDocContenido').innerHTML = html;
    }

    function closeModalDetalleDoc() {
        document.getElementById('modalDetalleDoc').classList.remove('show');
    }

    async function anularDocumento() {
        if (!documentoActual) return;

        const confirmacion = confirm(`⚠️ ¿Está seguro de ANULAR el documento ${documentoActual.cabecera.documento_numero}?\n\nEsta acción revertirá todos los movimientos de stock asociados.`);

        if (!confirmacion) return;

        const motivo = prompt('Ingrese el motivo de la anulación:');
        if (!motivo) {
            alert('Debe ingresar un motivo para anular');
            return;
        }

        try {
            const response = await fetch(`${baseUrl}/api/centro_inventarios.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'anular_documento',
                    documento_numero: documentoActual.cabecera.documento_numero,
                    motivo: motivo
                })
            });

            const data = await response.json();

            if (data.success) {
                alert(`✅ ${data.message}`);
                closeModalDetalleDoc();
                buscarHistorial();
                cargarDashboard();
            } else {
                alert('❌ ' + data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('❌ Error al anular documento');
        }
    }

    function imprimirDocumento() {
        if (!documentoActual) return;

        const contenido = document.getElementById('detalleDocContenido').innerHTML;
        const logoImg = document.querySelector('.sidebar-header img');
        const logoSrc = logoImg ? logoImg.src : '';

        const ventana = window.open('', '_blank');
        ventana.document.write(`
        <html>
        <head>
            <title>Documento ${documentoActual.cabecera.documento_numero}</title>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; padding: 40px; color: #333; }
                .print-header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #1a237e; padding-bottom: 20px; margin-bottom: 30px; }
                .company-info img { max-width: 180px; height: auto; }
                .doc-info { text-align: right; }
                .doc-info h2 { margin: 0; color: #1a237e; font-size: 1.5rem; }
                .doc-info p { margin: 5px 0 0 0; color: #666; font-weight: 600; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                th { background: #f8f9fa; color: #1a237e; font-weight: bold; }
                .badge-activo { background: #e8f5e9; color: #2e7d32; padding: 4px 12px; border-radius: 4px; font-weight: 600; font-size: 0.8rem; }
                .badge-anulado { background: #ffebee; color: #c62828; padding: 4px 12px; border-radius: 4px; font-weight: 600; font-size: 0.8rem; }
                .print-footer { margin-top: 50px; display: flex; justify-content: space-between; }
                .firma { border-top: 1px solid #333; width: 200px; text-align: center; padding-top: 10px; font-size: 0.9rem; }
            </style>
        </head>
        <body>
            <div class="print-header">
                <div class="company-info">
                    ${logoSrc ? `<img src="${logoSrc}" alt="Logo">` : '<h1>HERMEN LTDA.</h1>'}
                </div>
                <div class="doc-info">
                    <h2>NOTA DE ${documentoActual.cabecera.tipo_movimiento.toUpperCase()}</h2>
                    <p>Nº: ${documentoActual.cabecera.documento_numero}</p>
                    <p>Fecha: ${new Date(documentoActual.cabecera.fecha).toLocaleDateString()}</p>
                </div>
            </div>

            ${contenido}

            <div class="print-footer">
                <div class="firma">Entrega Conforme</div>
                <div class="firma">Recibe Conforme</div>
            </div>

            <script>
                window.onload = function() {
                    setTimeout(() => {
                        window.print();
                    }, 500);
                };
            <\/script>
        </body>
        </html>
    `);
        ventana.document.close();
    }

    // ========== UTILIDADES ==========
    function generarNumeroDocumento(prefijo) {
        const fecha = new Date();
        const año = fecha.getFullYear().toString().substr(-2);
        const mes = String(fecha.getMonth() + 1).padStart(2, '0');
        const dia = String(fecha.getDate()).padStart(2, '0');
        const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
        return `${prefijo}-${año}${mes}${dia}-${random}`;
    }

    // ========== FUNCIONES PLACEHOLDER PARA OTROS MODALES ==========
    // Estas se implementarán después

    function closeModalDevolucion() {
        document.getElementById('modalDevolucion').classList.remove('show');
    }

    function closeModalReportes() {
        document.getElementById('modalReportes').classList.remove('show');
    }

    function closeModalProveedores() {
        document.getElementById('modalProveedores').classList.remove('show');
    }

    function closeModalFormProveedor() {
        document.getElementById('modalFormProveedor').classList.remove('show');
    }

    console.log('✅ Funciones de modales v1.6.5 cargadas correctamente');



    // ========== MODAL ALERTAS DE STOCK REDISEÑADO ==========
    let alertasData = [];
    let inventoryTypes = [];
    let tipoAlertaActual = 'todos';
    let categoriasAlertas = [];

    async function abrirModalAlertas() {
        document.getElementById('modalAlertas').classList.add('show');

        // Cargar tipos de inventario para los tabs si no están cargados
        if (inventoryTypes.length === 0) {
            await cargarFiltrosInicialesAlertas();
        }

        cargarAlertas();
    }

    async function cargarFiltrosInicialesAlertas() {
        try {
            // Cargar tipos
            const respTipos = await fetch(`${baseUrl}/api/centro_inventarios.php?action=tipos`);
            const dataTipos = await respTipos.json();
            if (dataTipos.success) inventoryTypes = dataTipos.tipos;

            // Cargar todas las categorías para el filtro
            const respCats = await fetch(`${baseUrl}/api/centro_inventarios.php?action=categorias`);
            const dataCats = await respCats.json();
            if (dataCats.success) {
                categoriasAlertas = dataCats.categorias;

                const select = document.getElementById('filtroAlertCategoria');
                if (select) {
                    select.innerHTML = '<option value="">Todas las categorías</option>' +
                        categoriasAlertas.map(c => `<option value="${c.id_categoria}">${c.nombre} (${c.tipo_nombre})</option>`).join('');
                }
            }

            renderTabsAlertas();
        } catch (e) {
            console.error('Error al cargar filtros de alertas:', e);
        }
    }

    function renderTabsAlertas() {
        const container = document.getElementById('tabsAlertas');
        if (!container) return;

        let html = `
            <button class="tab-alert ${tipoAlertaActual === 'todos' ? 'active' : ''}" onclick="filtrarAlertasPorTipo('todos', this)">
                <i class="fas fa-boxes"></i> Todos
                <span class="count" id="countAlertTodos">0</span>
            </button>
        `;

        inventoryTypes.forEach(t => {
            html += `
                <button class="tab-alert ${tipoAlertaActual == t.id_tipo_inventario ? 'active' : ''}" onclick="filtrarAlertasPorTipo(${t.id_tipo_inventario}, this)">
                    <i class="fas ${t.icono || 'fa-box'}"></i> ${t.nombre}
                    <span class="count" id="countAlert${t.id_tipo_inventario}">0</span>
                </button>
            `;
        });

        container.innerHTML = html;
    }

    function closeModalAlertas() {
        document.getElementById('modalAlertas').classList.remove('show');
    }

    async function cargarAlertas() {
        if (document.getElementById('loadingAlertas')) document.getElementById('loadingAlertas').style.display = 'flex';
        if (document.getElementById('contentAlertas')) document.getElementById('contentAlertas').style.display = 'none';

        try {
            const response = await fetch(`${baseUrl}/api/centro_inventarios.php?action=alertas`);
            const data = await response.json();

            if (data.success) {
                alertasData = data.inventarios.map(item => {
                    const stock = parseFloat(item.stock_actual) || 0;
                    const min = parseFloat(item.stock_minimo) || 0;
                    const pct = (stock / min) * 100;

                    let sev = 'OK';
                    if (stock <= 0) sev = 'SIN_STOCK';
                    else if (pct <= 25) sev = 'CRITICO';
                    else if (pct <= 50) sev = 'BAJO';

                    return { ...item, severidad: sev, porcentaje: pct };
                }).filter(item => item.severidad !== 'OK');

                aplicarFiltrosAlertas();
                actualizarContadoresAlertas();
            } else {
                console.error('Error al cargar alertas:', data.message);
            }
        } catch (e) {
            console.error('Error de conexión al cargar alertas:', e);
        } finally {
            if (document.getElementById('loadingAlertas')) document.getElementById('loadingAlertas').style.display = 'none';
            if (document.getElementById('contentAlertas')) document.getElementById('contentAlertas').style.display = 'block';
        }
    }

    function actualizarContadoresAlertas() {
        // Contador total badge
        const badgeEle = document.getElementById('totalAlertasBadge');
        if (badgeEle) badgeEle.textContent = `${alertasData.length} productos`;

        const countTodosEle = document.getElementById('countAlertTodos');
        if (countTodosEle) countTodosEle.textContent = alertasData.length;

        // Contadores por tipo
        inventoryTypes.forEach(t => {
            const count = alertasData.filter(a => a.id_tipo_inventario == t.id_tipo_inventario).length;
            const el = document.getElementById(`countAlert${t.id_tipo_inventario}`);
            if (el) el.textContent = count;
        });

        // Contadores por severidad (del tab activo)
        const d = tipoAlertaActual === 'todos' ? alertasData : alertasData.filter(a => a.id_tipo_inventario == tipoAlertaActual);

        const countSinStockEle = document.getElementById('countSinStock');
        if (countSinStockEle) countSinStockEle.textContent = d.filter(a => a.severidad === 'SIN_STOCK').length;

        const countCriticoEle = document.getElementById('countCritico');
        if (countCriticoEle) countCriticoEle.textContent = d.filter(a => a.severidad === 'CRITICO').length;

        const countBajoEle = document.getElementById('countBajo');
        if (countBajoEle) countBajoEle.textContent = d.filter(a => a.severidad === 'BAJO').length;
    }

    function filtrarAlertasPorTipo(tipo, btn) {
        tipoAlertaActual = tipo;

        // UI tabs
        document.querySelectorAll('.tab-alert').forEach(t => t.classList.remove('active'));
        if (btn) btn.classList.add('active');

        aplicarFiltrosAlertas();
        actualizarContadoresAlertas();
    }

    function aplicarFiltrosAlertas() {
        const catId = document.getElementById('filtroAlertCategoria') ? document.getElementById('filtroAlertCategoria').value : '';
        const sevId = document.getElementById('filtroAlertSeveridad') ? document.getElementById('filtroAlertSeveridad').value : '';
        const buscar = document.getElementById('filtroAlertBusqueda') ? document.getElementById('filtroAlertBusqueda').value.toLowerCase() : '';

        let filtrados = alertasData;

        if (tipoAlertaActual !== 'todos') {
            filtrados = filtrados.filter(a => a.id_tipo_inventario == tipoAlertaActual);
        }

        if (catId) {
            filtrados = filtrados.filter(a => a.id_categoria == catId);
        }

        if (sevId) {
            filtrados = filtrados.filter(a => a.severidad === sevId);
        }

        if (buscar) {
            filtrados = filtrados.filter(a =>
                (a.codigo || '').toLowerCase().includes(buscar) ||
                (a.nombre || '').toLowerCase().includes(buscar)
            );
        }

        renderAlertasTabla(filtrados);
        const statsEle = document.getElementById('footerAlertStats');
        if (statsEle) statsEle.textContent = `Mostrando ${filtrados.length} de ${alertasData.length} alertas`;
    }

    function renderAlertasTabla(documentos) {
        const tbody = document.getElementById('tablaAlertasBody');
        if (!tbody) return;

        if (documentos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:30px;">No se encontraron alertas con los filtros aplicados</td></tr>';
            return;
        }

        tbody.innerHTML = documentos.map(item => {
            const stock = parseFloat(item.stock_actual) || 0;
            const min = parseFloat(item.stock_minimo) || 0;
            const faltante = min - stock;

            let badgeSev = '';
            if (item.severidad === 'SIN_STOCK') badgeSev = '<span class="badge-severidad-alert" style="background:#4b5563; color:white; padding:4px 10px; border-radius:12px; font-size:0.75rem;">Sin Stock</span>';
            else if (item.severidad === 'CRITICO') badgeSev = '<span class="badge-severidad-alert" style="background:#fee2e2; color:#dc2626; padding:4px 10px; border-radius:12px; font-size:0.75rem;">Crítico</span>';
            else if (item.severidad === 'BAJO') badgeSev = '<span class="badge-severidad-alert" style="background:#fef3c7; color:#d97706; padding:4px 10px; border-radius:12px; font-size:0.75rem;">Bajo</span>';

            return `
                <tr>
                    <td><span class="badge-tipo-alert" style="background:${item.tipo_color}20; color:${item.tipo_color}; border:1px solid ${item.tipo_color}">${item.tipo_codigo}</span></td>
                    <td><strong>${item.codigo}</strong></td>
                    <td>${item.nombre}</td>
                    <td>
                        <div class="stock-visual-alert">
                            <div class="stock-bar-alert">
                                <div class="stock-bar-fill-alert ${item.severidad.toLowerCase().replace('_', '-')}" style="width: ${Math.min(item.porcentaje, 100)}%"></div>
                            </div>
                            <span class="stock-percent-alert">${Math.round(item.porcentaje)}%</span>
                        </div>
                    </td>
                    <td style="text-align: right;">${stock.toFixed(2)} ${item.unidad || ''}</td>
                    <td style="text-align: right;">${min.toFixed(2)} ${item.unidad || ''}</td>
                    <td style="text-align: right; color:#dc3545; font-weight:bold;">${faltante > 0 ? faltante.toFixed(2) : '0.00'}</td>
                    <td>${badgeSev}</td>
                    <td style="text-align: center;">
                        <button class="btn-accion-alert comprar" title="Comprar" onclick="Swal.fire('🛍️ Compra', 'Funcionalidad de compra próximamente disponible en este módulo', 'info')">
                            <i class="fas fa-shopping-cart"></i>
                        </button>
                        <button class="btn-accion-alert kardex" title="Ver Kardex" onclick="verKardexGlobal(${item.id_inventario})">
                            <i class="fas fa-book"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function exportarAlertasExcel() {
        const d = tipoAlertaActual === 'todos' ? alertasData : alertasData.filter(a => a.id_tipo_inventario == tipoAlertaActual);
        if (d.length === 0) { alert('No hay datos para exportar'); return; }

        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Tipo,Código,Producto,Stock,Mínimo,Faltante,Estado\r\n";

        d.forEach(item => {
            const row = [
                item.tipo_codigo,
                item.codigo,
                `"${(item.nombre || '').replace(/"/g, '""')}"`,
                item.stock_actual,
                item.stock_minimo,
                (item.stock_minimo - item.stock_actual).toFixed(2),
                item.severidad
            ].join(",");
            csvContent += row + "\r\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `alertas_stock_${new Date().toISOString().split('T')[0]}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function imprimirAlertas() {
        const d = tipoAlertaActual === 'todos' ? alertasData : alertasData.filter(a => a.id_tipo_inventario == tipoAlertaActual);
        if (d.length === 0) { alert('No hay datos para imprimir'); return; }

        const logoImg = document.querySelector('.sidebar-header img');
        const logoSrc = logoImg ? logoImg.src : '';
        const fechaHoy = new Date().toLocaleDateString('es-BO');

        const filas = d.map(item => `
            <tr>
                <td>${item.tipo_codigo}</td>
                <td>${item.codigo}</td>
                <td>${item.nombre}</td>
                <td style="text-align: right;">${parseFloat(item.stock_actual).toFixed(2)}</td>
                <td style="text-align: right;">${parseFloat(item.stock_minimo).toFixed(2)}</td>
                <td style="text-align: right; color:red; font-weight:bold;">${(item.stock_minimo - item.stock_actual).toFixed(2)}</td>
                <td>${item.severidad}</td>
            </tr>
        `).join('');

        const ventana = window.open('', '_blank');
        ventana.document.write(`
            <html>
            <head>
                <title>Reporte de Alertas de Stock</title>
                <style>
                    body { font-family: 'Segoe UI', sans-serif; padding: 30px; }
                    .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #dc3545; padding-bottom: 10px; margin-bottom: 20px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
                    th { background: #f4f4f4; color: #dc3545; }
                </style>
            </head>
            <body>
                <div class="header">
                    <div>${logoSrc ? `<img src="${logoSrc}" height="50">` : '<h2>HERMEN LTDA</h2>'}</div>
                    <div style="text-align:right">
                        <h3>REPORTES DE ALERTAS DE STOCK</h3>
                        <p>Fecha: ${fechaHoy}</p>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Código</th>
                            <th>Producto</th>
                            <th style="text-align:right">Stock Actual</th>
                            <th style="text-align:right">Stock Mínimo</th>
                            <th style="text-align:right">Faltante</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>${filas}</tbody>
                </table>
                <script>window.onload = () => { setTimeout(() => window.print(), 500); }<\/script>
            </body>
            </html>
        `);
        ventana.document.close();
    }

    // ========== BÚSQUEDA GLOBAL ==========
    let searchTimeout;

    function filtrarGlobalDebounce(query) {
        clearTimeout(searchTimeout);
        if (query.length < 3) {
            document.getElementById('globalSearchResults').classList.remove('active');
            return;
        }

        document.getElementById('searchSpinner').style.display = 'block';
        searchTimeout = setTimeout(() => {
            buscarGlobal(query);
        }, 500);
    }

    async function buscarGlobal(query) {
        try {
            const response = await fetch(`${baseUrl}/api/centro_inventarios.php?action=list&buscar=${encodeURIComponent(query)}`);
            const data = await response.json();

            if (data.success) {
                renderResultadosGlobal(data.inventarios || []);
            } else {
                renderResultadosGlobal([]);
            }
        } catch (e) {
            console.error(e);
            alert('Error al buscar');
        } finally {
            document.getElementById('searchSpinner').style.display = 'none';
        }
    }

    function renderResultadosGlobal(resultados) {
        const container = document.getElementById('globalSearchResults');
        container.classList.add('active');

        if (resultados.length === 0) {
            container.innerHTML = `<div style="padding: 20px; text-align: center; color: #6c757d;">No se encontraron resultados</div>`;
            return;
        }

        container.innerHTML = resultados.map(item => {
            const tipoCodigo = item.tipo_codigo || '???';
            const tipoColor = item.tipo_color || '#6c757d';

            return `
            <div class="search-result-item">
                <div class="result-info">
                    <div class="result-type-badge" style="background: ${tipoColor}">${tipoCodigo}</div>
                    <div class="result-details">
                        <h4>
                            <span class="result-code">${item.codigo}</span> 
                            ${item.nombre}
                        </h4>
                        <p>Stock: <span class="result-stock">${parseFloat(item.stock_actual).toFixed(2)} ${item.unidad || ''}</span></p>
                    </div>
                </div>
                <div class="result-actions">
                    <button class="btn-kardex" onclick="verKardexGlobal(${item.id_inventario})">
                        <i class="fas fa-book-open"></i> Kardex
                    </button>
                </div>
            </div>
            `;
        }).join('');

        // Cierra al hacer click fuera
        document.addEventListener('click', function (e) {
            if (!container.contains(e.target) && e.target.id !== 'globalSearchInput') {
                container.classList.remove('active');
            }
        }, { once: true });
    }

    async function verKardexGlobal(id) {
        document.getElementById('modalKardex').classList.add('show');
        document.getElementById('kardexContent').innerHTML = `
            <div class="loading-spinner">
                <i class="fas fa-spinner"></i>
                <span>Cargando historial de movimientos...</span>
            </div>
        `;

        try {
            const response = await fetch(`${baseUrl}/api/centro_inventarios.php?action=kardex&id=${id}`);
            const data = await response.json();

            if (data.success) {
                const p = data.producto;
                const movs = data.movimientos;

                let html = `
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 10px 0; color: #007bff;">${p.nombre}</h4>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; font-size: 0.9rem;">
                        <div><strong>Código:</strong> ${p.codigo}</div>
                        <div><strong>Stock Actual:</strong> <span style="font-weight: bold; color: #28a745;">${parseFloat(p.stock_actual).toFixed(2)} ${p.unidad}</span></div>
                        <div><strong>Costo Unitario:</strong> Bs. ${parseFloat(p.costo_unitario).toFixed(2)}</div>
                        <div><strong>Ubicación:</strong> ${p.ubicacion || 'N/A'}</div>
                    </div>
                </div>
                
                <h5 style="margin-bottom: 15px; border-bottom: 2px solid #e9ecef; padding-bottom: 10px;">
                    <i class="fas fa-history"></i> Historial de Movimientos
                </h5>
                
                <table class="tabla-lineas" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #343a40; color: white;">
                            <th style="padding: 10px;">Fecha</th>
                            <th style="padding: 10px;">Documento</th>
                            <th style="padding: 10px;">Detalle</th>
                            <th style="padding: 10px; text-align: right;">Entrada</th>
                            <th style="padding: 10px; text-align: right;">Salida</th>
                            <th style="padding: 10px; text-align: right;">Saldo</th>
                            <th style="padding: 10px;">Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                `;

                if (movs.length === 0) {
                    html += `<tr><td colspan="7" style="text-align: center; padding: 20px;">No hay movimientos registrados</td></tr>`;
                } else {
                    movs.forEach(m => {
                        const esEntrada = m.tipo_movimiento.includes('ENTRADA') || m.tipo_movimiento.includes('DEVOLUCION_CLIENTE') || m.tipo_movimiento.includes('INICIAL');
                        const entrada = esEntrada ? m.cantidad : 0;
                        const salida = !esEntrada ? m.cantidad : 0;

                        html += `
                        <tr style="border-bottom: 1px solid #dee2e6;">
                            <td style="padding: 10px;">${new Date(m.fecha_movimiento).toLocaleString()}</td>
                            <td style="padding: 10px;">
                                <strong>${m.documento_numero}</strong><br>
                                <small>${m.documento_tipo}</small>
                            </td>
                            <td style="padding: 10px;">
                                <div style="font-size: 0.85rem; color: #666;">${m.tipo_movimiento}</div>
                                <div style="font-size: 0.8rem; color: #888; font-style: italic;">${m.observaciones || ''}</div>
                            </td>
                            <td style="padding: 10px; text-align: right; color: #28a745; background: #e8f5e9;">
                                ${entrada > 0 ? parseFloat(entrada).toFixed(2) : '-'}
                            </td>
                            <td style="padding: 10px; text-align: right; color: #dc3545; background: #ffebee;">
                                ${salida > 0 ? parseFloat(salida).toFixed(2) : '-'}
                            </td>
                            <td style="padding: 10px; text-align: right; font-weight: bold;">
                                ${parseFloat(m.stock_nuevo).toFixed(2)}
                            </td>
                            <td style="padding: 10px; font-size: 0.85rem;">${m.usuario || 'Sistema'}</td>
                        </tr>
                        `;
                    });
                }

                html += `</tbody></table>`;

                document.getElementById('kardexContent').innerHTML = html;
                document.getElementById('kardexTitulo').textContent = `Kardex: ${p.codigo}`;

            } else {
                document.getElementById('kardexContent').innerHTML = `<div style="text-align: center; color: red; padding: 20px;">${data.message}</div>`;
            }
        } catch (error) {
            console.error(error);
            document.getElementById('kardexContent').innerHTML = '<div style="text-align: center; color: red; padding: 20px;">Error de conexión</div>';
        }
    }

    function closeModalKardex() {
        document.getElementById('modalKardex').classList.remove('show');
    }

    async function cargarUltimosMovimientos() {
        const container = document.getElementById('listaUltimosMovimientos');

        try {
            const response = await fetch(`${baseUrl}/api/centro_inventarios.php?action=ultimos_movimientos`);
            const data = await response.json();

            if (data.success && data.movimientos.length > 0) {
                const hoy = new Date().setHours(0, 0, 0, 0);
                const ayer = new Date(hoy - 86400000).setHours(0, 0, 0, 0);

                container.innerHTML = data.movimientos.map(m => {
                    const esEntrada = m.categoria === 'ENTRADA' || m.categoria === 'DEVOLUCION';
                    // Nota: DEVOLUCION DE CLIENTE es entrada, DEVOLUCION A PROVEEDOR es salida. 
                    // El API devuelve 'DEVOLUCION' genérico, asumiremos salida si es proveedor? 
                    // Revisando API: 
                    // WHEN m.tipo_movimiento LIKE 'ENTRADA%' THEN 'ENTRADA'
                    // WHEN m.tipo_movimiento LIKE 'DEVOLUCION%' THEN 'DEVOLUCION'
                    // Pero visualmente queremos verde/rojo. 
                    // Si es devolución a proveedor (salida de stock) debería ser rojo.
                    // Si es devolución de producción (entrada a stock) debería ser verde.

                    let colorClass = 'salida';
                    let iconClass = 'fa-arrow-up';
                    let label = 'Salida';

                    if (m.tipo_movimiento.includes('ENTRADA') || m.tipo_movimiento.includes('INICIAL') || m.tipo_movimiento.includes('DEVOLUCION_PRODUCCION')) {
                        colorClass = 'entrada'; // verde
                        iconClass = 'fa-arrow-down';
                        label = 'Ingreso';
                    } else if (m.tipo_movimiento.includes('SALIDA') || m.tipo_movimiento.includes('CONSUMO') || m.tipo_movimiento.includes('MERMA')) {
                        colorClass = 'salida'; // rojo
                        iconClass = 'fa-arrow-up';
                        label = 'Salida';
                    } else if (m.tipo_movimiento.includes('DEVOLUCION_PROVEEDOR')) {
                        colorClass = 'salida';
                        iconClass = 'fa-undo';
                        label = 'Devolución';
                    }

                    // Ajuste visual
                    const colorBg = colorClass === 'entrada' ? '#e8f5e9' : '#ffebee';
                    const colorFg = colorClass === 'entrada' ? '#2e7d32' : '#c62828';

                    const fechaMov = new Date(m.fecha + 'T00:00:00'); // Asegurar parsing correcto de fecha YYYY-MM-DD
                    const fechaMovTime = fechaMov.setHours(0, 0, 0, 0);

                    let fechaTexto = '';
                    if (fechaMovTime === hoy) fechaTexto = 'Hoy';
                    else if (fechaMovTime === ayer) fechaTexto = 'Ayer';
                    else fechaTexto = new Date(m.fecha).toLocaleDateString('es-BO');

                    return `
                    <div class="movimiento-item">
                        <div class="mov-main">
                            <div class="mov-icon-circle" style="background: ${colorBg}; color: ${colorFg};">
                                <i class="fas ${iconClass}"></i>
                            </div>
                            <div class="mov-desc">
                                <h5>${label}</h5>
                                <span>${m.documento_numero}</span>
                            </div>
                        </div>
                        <div class="mov-monto">
                            Bs. ${parseFloat(m.total_documento || 0).toFixed(2)}
                        </div>
                        <div class="mov-fecha">
                            <i class="far fa-clock"></i> ${fechaTexto}
                        </div>
                        <div style="text-align: center;">
                            <span class="mov-tipo" style="color: white; background: ${m.tipo_inventario_color || '#6c757d'};">
                                ${m.tipo_inventario_nombre || 'Inventario'}
                            </span>
                        </div>
                        <div style="text-align: right;">
                            <button onclick="verDetalleDocumento('${m.documento_numero}')" 
                                    style="border: none; background: transparent; color: #007bff; cursor: pointer; font-size: 1.1rem;"
                                    title="Ver detalle del documento">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    `;
                }).join('');
            } else {
                container.innerHTML = '<div style="padding: 30px; text-align: center; color: #6c757d;">No hay movimientos recientes</div>';
            }
        } catch (e) {
            console.error(e);
            container.innerHTML = '<div style="padding: 30px; text-align: center; color: red;">Error al cargar movimientos</div>';
        }
    }

    // ========== GRÁFICO DE TENDENCIA ==========
    let chartTendencia = null;

    async function cargarTendencia() {
        try {
            // Timeout de 10 segundos para evitar bloqueos
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000);

            const response = await fetch(`${baseUrl}/api/centro_inventarios.php?action=tendencia_valor`, {
                signal: controller.signal
            });
            clearTimeout(timeoutId);

            const data = await response.json();

            if (data.success && data.tendencia) {
                initChartTendencia(data.tendencia);

                // Actualizar el valor actual en el trend header (último dato)
                const ultimoDato = data.tendencia[data.tendencia.length - 1];
                if (ultimoDato) {
                    document.getElementById('valorActualTrend').textContent = 'Bs. ' + ultimoDato.valor.toLocaleString('es-BO', { minimumFractionDigits: 2 });
                }
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                console.warn('Carga de tendencia cancelada por timeout');
            } else {
                console.error('Error al cargar tendencia:', error);
            }
            // No mostrar error al usuario, solo en consola
        }
    }

    function initChartTendencia(datos) {
        const ctx = document.getElementById('chartTendenciaValor').getContext('2d');

        if (chartTendencia) chartTendencia.destroy();

        const labels = datos.map(d => d.mes);
        const valores = datos.map(d => d.valor);

        chartTendencia = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Valor Total (Bs.)',
                    data: valores,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#667eea',
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function (context) {
                                let val = context.parsed.y;
                                return 'Valor: Bs. ' + val.toLocaleString('es-BO', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: {
                            drawBorder: false,
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function (value) {
                                if (value >= 1000000) return (value / 1000000).toFixed(1) + 'M';
                                if (value >= 1000) return (value / 1000).toFixed(0) + 'K';
                                return value;
                            }
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                }
            }
        });
    }

</script>

<?php require_once '../../includes/footer.php'; ?>