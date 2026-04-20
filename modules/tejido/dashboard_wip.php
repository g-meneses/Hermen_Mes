<?php
require_once '../../config/database.php';

if (!isLoggedIn() || !hasRole(['admin', 'gerencia', 'coordinador'])) {
    redirect('index.php');
}

$pageTitle = 'WIP Tissue: Intelligence Dashboard';
$currentPage = 'dashboard_wip';
require_once '../../includes/header.php';
?>

<!-- Import Premium Font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<div class="container-fluid dashboard-wip pt-3 pb-5">
    
    <!-- 1) Franja Superior Compacta: Encabezado y Filtros -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div class="header-titles mb-2 mb-md-0">
            <h1 class="h4 mb-0 font-weight-bold text-dark d-flex align-items-center">
                <span class="icon-box bg-primary-gradient mr-2 shadow-sm">
                    <i class="fas fa-microchip text-white" style="font-size:1.1rem;"></i>
                </span>
                WIP Tissue Dashboard
            </h1>
        </div>
        
        <div class="filters-panel p-2 bg-white rounded shadow-sm border border-light flex-grow-1 ml-md-3">
            <div class="form-row align-items-end m-0">
                <div class="col-6 col-md-2 mb-2 mb-md-0 px-1">
                    <label class="small font-weight-bold text-muted mb-1 text-uppercase">Desde</label>
                    <input type="date" id="fechaDesde" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-6 col-md-2 mb-2 mb-md-0 px-1">
                    <label class="small font-weight-bold text-muted mb-1 text-uppercase">Hasta</label>
                    <input type="date" id="fechaHasta" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-6 col-md-2 mb-2 mb-md-0 px-1">
                    <label class="small font-weight-bold text-muted mb-1 text-uppercase">Turno</label>
                    <select id="filtroTurno" class="form-control form-control-sm">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="col-6 col-md-2 mb-2 mb-md-0 px-1">
                    <label class="small font-weight-bold text-muted mb-1 text-uppercase">Maquina</label>
                    <select id="filtroMaquina" class="form-control form-control-sm">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="col-12 col-md-4 px-1 d-flex gap-2">
                    <button class="btn btn-primary btn-sm flex-fill shadow-sm" onclick="loadDashboard()">
                        <i class="fas fa-sync-alt mr-1"></i> Actualizar
                    </button>
                    <button class="btn btn-light btn-sm flex-fill border text-dark shadow-sm" onclick="resetFilters()">
                        <i class="fas fa-eraser mr-1"></i> Limpiar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 2) Banda de Alertas al Inicio -->
    <div id="alertsContainer" class="d-flex flex-wrap gap-2 mb-3" style="display:none;"></div>

    <!-- 3) KPIs Principales en Grid Real -->
    <div class="row align-items-stretch mb-2">
        <!-- Fila 1 -->
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card kpi-card shadow-sm border-left-success h-100">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-xs font-weight-bold text-success text-uppercase mb-1">Producción (Período)</p>
                            <h4 class="font-weight-bold text-gray-800 mb-0" id="kpiProd">-</h4>
                            <p class="text-muted small mb-0 mt-1">Lotes / Unidades reportadas</p>
                        </div>
                        <div class="kpi-icon bg-success-light rounded p-2">
                            <i class="fas fa-industry text-success fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card kpi-card shadow-sm border-left-info h-100">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-xs font-weight-bold text-info text-uppercase mb-1">Saldo WIP Actual</p>
                            <h4 class="font-weight-bold text-gray-800 mb-0" id="kpiWip">-</h4>
                            <p class="text-muted small mb-0 mt-1">En Piso Tejeduría</p>
                        </div>
                        <div class="kpi-icon bg-info-light rounded p-2">
                            <i class="fas fa-boxes text-info fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card kpi-card shadow-sm border-left-primary h-100">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-xs font-weight-bold text-primary text-uppercase mb-1">MP Disponible (Tejeduría)</p>
                            <h4 class="font-weight-bold text-gray-800 mb-0"><span id="kpiMpDisponible">0.00</span> <small class="text-xs text-muted">Kg</small></h4>
                            <p class="text-muted small mb-0 mt-1">Saldo Real FIFO</p>
                        </div>
                        <div class="kpi-icon bg-primary-light rounded p-2">
                            <i class="fas fa-warehouse text-primary fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card kpi-card shadow-sm border-left-warning h-100">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-xs font-weight-bold text-warning text-uppercase mb-1">Máquinas Activas</p>
                            <h4 class="font-weight-bold text-gray-800 mb-0" id="kpiMach">-</h4>
                            <p class="text-muted small mb-0 mt-1" id="kpiLots">Sin actividad reciente</p>
                        </div>
                        <div class="kpi-icon bg-warning-light rounded p-2">
                            <i class="fas fa-cogs text-warning fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fila 2 -->
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card kpi-card border-0 shadow-sm border-left-danger h-100" style="background:#fffcfc;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-xs font-weight-bold text-danger text-uppercase mb-1">Consumo Teórico BOM</p>
                            <h5 class="font-weight-bold text-gray-800 mb-0"><span id="kpiHiloCons">0.00</span> <small class="text-xs">Kg</small></h5>
                        </div>
                        <i class="fas fa-file-invoice text-danger" style="opacity:0.3;font-size:1.5rem"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card kpi-card border-0 shadow-sm border-left-secondary h-100" style="background:#f8f9fa;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Consumo Real Acumulado</p>
                            <h5 class="font-weight-bold text-gray-800 mb-0"><span id="kpiConsumoReal">0.00</span> <small class="text-xs">Kg</small></h5>
                        </div>
                        <i class="fas fa-compress-arrows-alt text-secondary" style="opacity:0.3;font-size:1.5rem"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card kpi-card border-0 shadow-sm border-left-dark h-100">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-xs font-weight-bold text-dark text-uppercase mb-1">Saldo en Máquinas</p>
                            <h5 class="font-weight-bold text-gray-800 mb-0"><span id="kpiHiloMaq">0.00</span> <small class="text-xs">Kg</small></h5>
                        </div>
                        <i class="fas fa-layer-group text-dark" style="opacity:0.3;font-size:1.5rem"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card kpi-card border-0 shadow-sm border-left-indigo h-100" style="background:#fbfaff;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-xs font-weight-bold text-indigo text-uppercase mb-1">Saldo en Sala / Piso</p>
                            <h5 class="font-weight-bold text-gray-800 mb-0"><span id="kpiHiloSala">0.00</span> <small class="text-xs">Kg</small></h5>
                        </div>
                        <i class="fas fa-pallet text-indigo" style="opacity:0.3;font-size:1.5rem"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4) Núcleo Operativo en Dos Paneles -->
    <div class="row mb-4">
        <!-- Panel Izquierdo: Producción por máquina -->
        <div class="col-md-6 col-lg-6 mb-4 mb-md-0">
            <div class="card shadow-sm h-100 border-0" style="border-radius: 16px;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center" style="border-radius: 16px 16px 0 0; border-bottom: 2px solid #f1f5f9;">
                    <h6 class="m-0 font-weight-bold text-dark">
                        <i class="fas fa-chart-bar text-primary mr-2"></i>Producción (Top Máquinas)
                    </h6>
                    <button class="btn btn-sm btn-light border shadow-sm px-3" onclick="window.print()" title="Imprimir Reporte"><i class="fas fa-print"></i></button>
                </div>
                <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 text-sm align-middle">
                            <thead class="bg-light" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th class="border-top-0 border-bottom-0 pl-3">Máquina</th>
                                    <th class="border-top-0 border-bottom-0">Producto</th>
                                    <th class="border-top-0 border-bottom-0 text-center">Producción</th>
                                    <th class="border-top-0 border-bottom-0 text-center">Lotes</th>
                                </tr>
                            </thead>
                            <tbody id="bodyProductionMachine">
                                <tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Derecho: MP Disponible en Tejeduría -->
        <div class="col-md-6 col-lg-6">
            <div class="card shadow-sm h-100 border-0" style="border-radius: 16px;">
                <div class="card-header py-3 d-flex justify-content-between align-items-center" style="background:#f0fdf4; border-radius: 16px 16px 0 0; border-bottom:1px solid #bbf7d0;">
                    <h6 class="m-0 font-weight-bold" style="color:#166534;">
                        <i class="fas fa-warehouse mr-2"></i>MP Disponible FIFO (Planta)
                    </h6>
                    <span class="badge" style="background:#dcfce7;color:#166534;" title="Componentes críticos">Vigilancia Real</span>
                </div>
                <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 text-sm align-middle">
                            <thead class="bg-light" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th class="border-top-0 border-bottom-0 pl-3">Componente</th>
                                    <th class="text-right border-top-0 border-bottom-0"><span style="color:#15803d;">Disponible</span></th>
                                    <th class="text-center border-top-0 border-bottom-0" style="width: 140px;">Consumo %</th>
                                    <th class="text-center border-top-0 border-bottom-0" title="SAL-TEJ">Docs</th>
                                </tr>
                            </thead>
                            <tbody id="bodySaldoMp">
                                <tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 5) Todo el detalle inferior en TABS -->
    <div class="card shadow-sm border-0" style="border-radius: 16px; overflow: hidden; margin-bottom: 50px;">
        <div class="card-header bg-white p-0 border-bottom">
            <ul class="nav nav-tabs border-0 px-3 pt-2 d-flex flex-row flex-nowrap" style="overflow-x: auto; white-space: nowrap;" id="detailTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active font-weight-bold text-dark px-4 py-3" id="tab-resumen" data-toggle="tab" href="#pane-resumen" role="tab" style="border:none; border-bottom: 3px solid transparent;">
                        <i class="fas fa-boxes mr-2 text-primary"></i>Resumen WIP
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link font-weight-bold text-dark px-4 py-3" id="tab-lotes" data-toggle="tab" href="#pane-lotes" role="tab" style="border:none; border-bottom: 3px solid transparent;">
                        <i class="fas fa-list-ul mr-2 text-info"></i>Lotes Activos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link font-weight-bold text-dark px-4 py-3" id="tab-hilos" data-toggle="tab" href="#pane-hilos" role="tab" style="border:none; border-bottom: 3px solid transparent;">
                        <i class="fas fa-braille mr-2 text-warning"></i>Hilos / BOM
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link font-weight-bold text-dark px-4 py-3" id="tab-kardex" data-toggle="tab" href="#pane-kardex" role="tab" style="border:none; border-bottom: 3px solid transparent;">
                        <i class="fas fa-calendar-alt mr-2 text-secondary"></i>Kardex Período
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link font-weight-bold text-dark px-4 py-3" id="tab-trazabilidad" data-toggle="tab" href="#pane-trazabilidad" role="tab" style="border:none; border-bottom: 3px solid transparent;">
                        <i class="fas fa-project-diagram mr-2 text-success"></i>Trazabilidad
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body p-0">
            <div class="tab-content" id="detailTabsContent">
                
                <!-- Tab: Resumen WIP -->
                <div class="tab-pane fade show active" id="pane-resumen" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 text-sm align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="pl-4">Producto</th>
                                    <th class="text-center">Saldo Actual</th>
                                    <th class="text-center">Lotes Asociados</th>
                                </tr>
                            </thead>
                            <tbody id="bodyWipProduct">
                                <tr><td colspan="3" class="text-center text-muted py-4">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Lotes Activos -->
                <div class="tab-pane fade" id="pane-lotes" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 text-sm align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="pl-4">Lote</th>
                                    <th>Producto</th>
                                    <th>Área Actual</th>
                                    <th>Estado</th>
                                    <th class="text-center">Cantidad</th>
                                    <th>Últ. Actualización</th>
                                    <th>Referencia Origen</th>
                                </tr>
                            </thead>
                            <tbody id="bodyWipLot"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Hilos / BOM -->
                <div class="tab-pane fade" id="pane-hilos" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 text-sm align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="pl-4">Hilo / Fibra (BOM)</th>
                                    <th class="text-right">Recibido (Kg)</th>
                                    <th class="text-right">Consumo BOM (Kg)</th>
                                    <th class="text-right">En Máquina / Proceso</th>
                                    <th class="text-right">En Sala (Libre)</th>
                                    <th class="text-right font-weight-bold">Total Asignado</th>
                                </tr>
                            </thead>
                            <tbody id="bodyHilosDetalle"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Kardex del Período -->
                <div class="tab-pane fade" id="pane-kardex" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 text-sm align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="pl-4">Componente / Código</th>
                                    <th class="text-right">Saldo Inicial</th>
                                    <th class="text-right">Abonado (Rec)</th>
                                    <th class="text-right">Deducido (Cons)</th>
                                    <th class="text-right font-weight-bold">Saldo Final</th>
                                </tr>
                            </thead>
                            <tbody id="bodyHilosKardex"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Trazabilidad -->
                <div class="tab-pane fade" id="pane-trazabilidad" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 text-sm align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="pl-4">SAL-TEJ (Origen)</th>
                                    <th>Fecha Despacho MP</th>
                                    <th>Código Lote WIP</th>
                                    <th>Producto Convertido</th>
                                    <th>Resultado Producción</th>
                                    <th>Reportado Por</th>
                                </tr>
                            </thead>
                            <tbody id="bodyTraceability"></tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<style>
:root {
    --primary: #4e73df;
    --success: #1cc88a;
    --info: #36b9cc;
    --warning: #f6c23e;
    --danger: #e74a3b;
    --indigo: #6610f2;
    --dark: #1a202c;
    --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

/* --- BOOTSTRAP 4 SHIM (El ERP no carga Bootstrap Grid en sus dependencias) --- */
.row { display: flex; flex-wrap: wrap; margin-right: -10px; margin-left: -10px; }
.form-row { display: flex; flex-wrap: wrap; margin-right: -5px; margin-left: -5px; }
.form-row > [class*="col-"] { padding-right: 5px; padding-left: 5px; }
.col-6, .col-12, .col-md-2, .col-md-4, .col-md-6, .col-lg-3, .col-lg-6 { padding-right: 10px; padding-left: 10px; position: relative; width: 100%; }
.col-6 { flex: 0 0 50%; max-width: 50%; }
.col-12 { flex: 0 0 100%; max-width: 100%; }
@media (min-width: 768px) {
    .col-md-2 { flex: 0 0 16.666667%; max-width: 16.666667%; }
    .col-md-4 { flex: 0 0 33.333333%; max-width: 33.333333%; }
    .col-md-6 { flex: 0 0 50%; max-width: 50%; }
    .ml-md-3 { margin-left: 1rem !important; }
    .mb-md-0 { margin-bottom: 0 !important; }
}
@media (min-width: 992px) {
    .col-lg-3 { flex: 0 0 25%; max-width: 25%; }
    .col-lg-6 { flex: 0 0 50%; max-width: 50%; }
    .mb-lg-0 { margin-bottom: 0 !important; }
}
/* Flexbox Utilities */
.d-flex { display: flex !important; }
.flex-column { flex-direction: column !important; }
.flex-row { flex-direction: row !important; }
.flex-wrap { flex-wrap: wrap !important; }
.flex-nowrap { flex-wrap: nowrap !important; }
.justify-content-between { justify-content: space-between !important; }
.justify-content-center { justify-content: center !important; }
.align-items-center { align-items: center !important; }
.align-items-start { align-items: flex-start !important; }
.align-items-end { align-items: flex-end !important; }
.align-items-stretch { align-items: stretch !important; }
.flex-grow-1 { flex-grow: 1 !important; }
.flex-fill { flex: 1 1 auto !important; }
.gap-2 { gap: 0.5rem !important; }
/* Typography & Align */
.text-center { text-align: center !important; }
.text-right { text-align: right !important; }
.text-muted { color: #6c757d !important; }
.text-primary { color: var(--primary) !important; }
.text-success { color: var(--success) !important; }
.text-info { color: var(--info) !important; }
.text-warning { color: var(--warning) !important; }
.text-danger { color: var(--danger) !important; }
.text-dark { color: var(--dark) !important; }
.text-secondary { color: #6c757d !important; }
.text-indigo { color: var(--indigo) !important; }
.text-xs { font-size: 0.75rem !important; }
.text-sm { font-size: 0.875rem !important; }
.font-weight-bold { font-weight: 700 !important; }
.font-weight-500 { font-weight: 500 !important; }
.font-weight-normal { font-weight: 400 !important; }
.text-uppercase { text-transform: uppercase !important; }
.text-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
/* Spacing Helpers */
.m-0 { margin: 0 !important; } .mb-0 { margin-bottom: 0 !important; }
.mb-1 { margin-bottom: 0.25rem !important; } .mb-2 { margin-bottom: 0.5rem !important; }
.mb-3 { margin-bottom: 1rem !important; } .mb-4 { margin-bottom: 1.5rem !important; }
.mt-1 { margin-top: 0.25rem !important; } .mr-1 { margin-right: 0.25rem !important; }
.mr-2 { margin-right: 0.5rem !important; } .ml-1 { margin-left: 0.25rem !important; }
.ml-2 { margin-left: 0.5rem !important; }
.p-0 { padding: 0 !important; } .p-2 { padding: 0.5rem !important; } .p-3 { padding: 1rem !important; }
.px-1 { padding-left: 0.25rem !important; padding-right: 0.25rem !important; }
.px-2 { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
.px-3 { padding-left: 1rem !important; padding-right: 1rem !important; }
.px-4 { padding-left: 1.5rem !important; padding-right: 1.5rem !important; }
.py-2 { padding-top: 0.5rem !important; padding-bottom: 0.5rem !important; }
.py-3 { padding-top: 1rem !important; padding-bottom: 1rem !important; }
.py-4 { padding-top: 1.5rem !important; padding-bottom: 1.5rem !important; }
.pt-2 { padding-top: 0.5rem !important; } .pt-3 { padding-top: 1rem !important; }
.pb-5 { padding-bottom: 3rem !important; }
.pl-3 { padding-left: 1rem !important; } .pl-4 { padding-left: 1.5rem !important; }
/* Design & Sizes */
.w-100 { width: 100% !important; } .h-100 { height: 100% !important; }
.bg-white { background-color: #fff !important; } .bg-light { background-color: #f8f9fa !important; }
.shadow-sm { box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075) !important; }
.border { border: 1px solid #dee2e6 !important; } .border-0 { border: 0 !important; }
.border-top-0 { border-top: 0 !important; } .border-bottom-0 { border-bottom: 0 !important; }
.border-bottom { border-bottom: 1px solid #dee2e6 !important; }
.rounded { border-radius: 0.25rem !important; } .rounded-pill { border-radius: 50rem !important; }
.d-block { display: block !important; } .opacity-25 { opacity: 0.25 !important; } .opacity-50 { opacity: 0.5 !important; }
/* Tabs Runtime (BS4 Tabs Logic) */
.tab-content > .tab-pane { display: none; }
.tab-content > .active { display: block; }


body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background-color: #f8fafc; }
.dashboard-wip { max-width: 1650px; margin: 0 auto; }

/* Custom Overrides de Grid & Cards */
.icon-box { border-radius: 10px; width: 40px; height: 40px; justify-content: center; }
.bg-primary-gradient { background: linear-gradient(135deg, #4e73df 0%, #2e59d9 100%); }

.filters-panel { background: rgba(255,255,255,0.95); backdrop-filter: blur(5px); }

/* KPI Cards */
.kpi-card { border-radius: 14px; transition: var(--transition); border: none; }
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.06) !important; }
.border-left-success { border-left: 4px solid var(--success) !important; }
.border-left-info { border-left: 4px solid var(--info) !important; }
.border-left-primary { border-left: 4px solid var(--primary) !important; }
.border-left-warning { border-left: 4px solid var(--warning) !important; }
.border-left-danger { border-left: 4px solid var(--danger) !important; }
.border-left-secondary { border-left: 4px solid #6c757d !important; }
.border-left-dark { border-left: 4px solid var(--dark) !important; }
.border-left-indigo { border-left: 4px solid var(--indigo) !important; }

.kpi-icon { width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; }
.bg-success-light { background-color: #e6f9f0; }
.bg-info-light { background-color: #ebf8fa; }
.bg-primary-light { background-color: #edf1fc; }
.bg-warning-light { background-color: #fef5d8; }

/* Tables compact */
.table td, .table th { padding: 12px 16px !important; white-space: nowrap; }
.table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
.table tbody tr:hover { background-color: #f1f5f9; }

/* Badges Semánticos */
.badge { font-weight: 600; padding: 6px 10px; border-radius: 8px; letter-spacing: 0.02em; }
.badge-success { background-color: #d1fae5; color: #065f46; }
.badge-warning { background-color: #fef3c7; color: #92400e; }
.badge-danger { background-color: #fee2e2; color: #991b1b; }
.badge-secondary { background-color: #f1f5f9; color: #475569; }

/* Alertas destacadas */
#alertsContainer { gap: 10px; }
.alert-card {
    border-radius: 12px; border: none; border-left: 4px solid; padding: 12px 18px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: #fff;
    display: flex; align-items: flex-start; position: relative;
    flex: 1 1 300px;
}
.alert-card.alert-danger { border-left-color: #ef4444; background-color: #fef2f2; }
.alert-card.alert-warning { border-left-color: #f59e0b; background-color: #fffbeb; }
.alert-card.alert-info { border-left-color: #3b82f6; background-color: #eff6ff; }
.alert-card .alert-icon { font-size: 1.5rem; margin-right: 14px; margin-top:2px; }
.alert-danger .alert-icon { color: #ef4444; }
.alert-warning .alert-icon { color: #f59e0b; }
.alert-info .alert-icon { color: #3b82f6; }
.alert-card .close { position: absolute; right: 10px; top: 10px; opacity: 0.6; }

/* Tabs overriding Bootstrap */
.nav-tabs .nav-link.active {
    color: var(--primary) !important;
    background-color: transparent;
    border-color: transparent !important;
    border-bottom: 3px solid var(--primary) !important;
}
.nav-tabs .nav-link:hover:not(.active) {
    border-color: transparent;
    background-color: rgba(0,0,0,0.02);
    border-bottom: 3px solid #e2e8f0 !important;
}

/* Animations */
@keyframes pulse-dot { 0% { opacity: 0.5; } 50% { opacity: 1; transform:scale(1.1); } 100% { opacity: 0.5; } }
.pulse-circle { display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: var(--success); animation: pulse-dot 2s infinite ease-in-out; }
</style>

<script>
const baseUrl = window.location.origin + '/mes_hermen';

document.addEventListener('DOMContentLoaded', async () => {
    await Promise.all([loadCatalogs(), loadDashboard()]);
});

async function loadCatalogs() {
    try {
        const [respTurnos, respMaquinas] = await Promise.all([
            fetch(baseUrl + '/api/catalogos.php?tipo=turnos'),
            fetch(baseUrl + '/api/maquinas.php')
        ]);
        
        const turnos = await respTurnos.json();
        const maquinas = await respMaquinas.json();

        if (turnos.success) {
            const select = document.getElementById('filtroTurno');
            turnos.turnos.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id_turno;
                opt.textContent = t.nombre_turno;
                select.appendChild(opt);
            });
        }

        if (maquinas.success) {
            const select = document.getElementById('filtroMaquina');
            maquinas.maquinas.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id_maquina;
                opt.textContent = 'M' + m.numero_maquina;
                select.appendChild(opt);
            });
        }
    } catch (e) { console.error('Error cargando catálogos', e); }
}

function resetFilters() {
    document.getElementById('filtroTurno').value = '';
    document.getElementById('filtroMaquina').value = '';
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('fechaDesde').value = today;
    document.getElementById('fechaHasta').value = today;
    loadDashboard();
}

async function loadDashboard() {
    const fDesde = document.getElementById('fechaDesde').value;
    const fHasta = document.getElementById('fechaHasta').value;
    const turn = document.getElementById('filtroTurno').value;
    const mach = document.getElementById('filtroMaquina').value;

    let url = baseUrl + `/api/wip_dashboard.php?fecha_desde=${fDesde}&fecha_hasta=${fHasta}`;
    if (turn) url += `&id_turno=${turn}`;
    if (mach) url += `&id_maquina=${mach}`;

    try {
        const resp = await fetch(url);
        const json = await resp.json();
        if (json.success) {
            renderKPIs(json.data.kpis);
            renderSaldoDisponibleMp(json.data.saldo_disponible_mp || []);
            renderProductionByMachine(json.data.production_by_machine);
            renderWipByLot(json.data.wip_by_lot);
            renderWipByProduct(json.data.wip_by_product);
            renderTraceability(json.data.traceability);
            renderYarnControl(json.data.hilos_detalle, json.data.hilos_kardex);
            renderAlerts(json.data.alerts);
        }
    } catch (e) { console.error('Error cargando dashboard', e); }
}

function renderKPIs(kpis) {
    document.getElementById('kpiProd').textContent = kpis.production_today_fmt || '0|00';
    document.getElementById('kpiWip').textContent = kpis.wip_balance_current_fmt || '0|00';
    document.getElementById('kpiMach').innerHTML = `
        <div class="d-flex align-items-center">
            ${kpis.active_machines || 0} <span class="pulse-circle ml-2 mr-1"></span><small class="text-success text-xs" style="font-size:0.7rem;">En línea</small>
        </div>
    `;
    document.getElementById('kpiLots').textContent = `${kpis.active_lots || 0} lotes en piso`;

    // Fila 2
    document.getElementById('kpiHiloCons').textContent = (kpis.hilo_consumo_teorico || 0).toFixed(2);
    document.getElementById('kpiHiloMaq').textContent = (kpis.hilo_saldo_maquinas || 0).toFixed(2);
    document.getElementById('kpiHiloSala').textContent = (kpis.hilo_saldo_sala || 0).toFixed(2);
}

function renderSaldoDisponibleMp(data) {
    const tbody = document.getElementById('bodySaldoMp');
    let totalConsumoAcumulado = 0;
    let totalMpDisponible = 0;

    if (!data || data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-4 pl-3">
            <i class="fas fa-inbox fa-2x mb-2 d-block opacity-25"></i>
            Sin MP registrada disponible.
            <div class="mt-1" style="font-size:0.75rem;">Requiere emisión de documentos SAL-TEJ.</div>
        </td></tr>`;
        document.getElementById('kpiConsumoReal').textContent = "0.00";
        document.getElementById('kpiMpDisponible').textContent = "0.00";
        return;
    }

    data.forEach(r => {
        totalConsumoAcumulado += parseFloat(r.consumido_kg || 0);
        totalMpDisponible += parseFloat(r.saldo_disponible_kg || 0);
    });

    document.getElementById('kpiConsumoReal').textContent = totalConsumoAcumulado.toFixed(2);
    document.getElementById('kpiMpDisponible').textContent = totalMpDisponible.toFixed(2);

    // Ordenar por riesgo (menor saldo disponible = más prioritario)
    data.sort((a,b) => parseFloat(a.saldo_disponible_kg) - parseFloat(b.saldo_disponible_kg));

    tbody.innerHTML = data.map(r => {
        const saldo = parseFloat(r.saldo_disponible_kg);
        const cons  = parseFloat(r.consumido_kg);
        const pct   = parseFloat(r.pct_consumido);
        const isCritical = saldo <= 0;
        const colorClass = isCritical ? '#ef4444' : (saldo < 15 ? '#f59e0b' : '#10b981');
        const bgProg = pct >= 90 ? '#ef4444' : pct >= 70 ? '#f59e0b' : '#10b981';

        return `
        <tr style="${isCritical ? 'background-color:#fff5f5;' : ''}">
            <td class="pl-3">
                <strong style="color:#1e293b; font-size:0.85rem;" class="d-block text-truncate" style="max-width:200px;" title="${r.nombre}">${r.nombre}</strong>
                <small class="text-muted"><i class="fas fa-hashtag mr-1" style="font-size:0.6rem;"></i>${r.codigo}</small>
            </td>
            <td class="text-right">
                <span style="font-weight:700; color:${colorClass}; font-size:1.05rem;">${saldo.toFixed(2)}</span>
                ${isCritical ? '<div style="color:#ef4444;font-size:0.65rem;font-weight:bold;margin-top:-2px;"><i class="fas fa-exclamation-triangle"></i> AGOTADO</div>' : ''}
            </td>
            <td class="text-center">
                <div class="d-flex flex-column" style="gap:4px;">
                    <span style="font-size:0.75rem;font-weight:600;color:${bgProg}; line-height:1;">${pct}%</span>
                    <div style="width:100%;height:5px;border-radius:3px;background:#e2e8f0;overflow:hidden;">
                        <div style="height:100%;border-radius:3px;background:${bgProg};width:${Math.min(pct,100)}%;"></div>
                    </div>
                    <span class="text-muted" style="font-size:0.65rem;">${cons.toFixed(1)} Kg us.</span>
                </div>
            </td>
            <td class="text-center">
                <span class="badge badge-secondary border font-weight-normal" title="Último doc: ${r.ultimo_sal_tej || 'N/A'}">${r.num_documentos} docs</span>
            </td>
        </tr>`;
    }).join('');
}

function renderProductionByMachine(data) {
    const tbody = document.getElementById('bodyProductionMachine');
    if (!data || !data.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-industry fa-2x mb-2 d-block opacity-25"></i>Sin producción para estos filtros</td></tr>';
        return;
    }
    
    // Sort logic JS fallback (por unidades equivalentes)
    data.forEach(r => { r.total_pcs = (parseInt(r.docenas||0)*12) + parseInt(r.unidades||0); });
    data.sort((a,b) => b.total_pcs - a.total_pcs);
    const topData = data.slice(0, 10);

    tbody.innerHTML = topData.map(row => {
        return `
        <tr>
            <td class="pl-3">
                <strong class="text-primary" style="font-size:1.1rem;">M${row.numero_maquina}</strong>
                <br><small class="text-muted"><i class="far fa-clock mr-1"></i>${row.turno_nombre}</small>
            </td>
            <td>
                <span class="text-dark font-weight-500 d-block" style="font-size:0.85rem;">${row.codigo_producto}</span>
                <span class="text-muted text-truncate d-block" style="max-width: 200px; font-size:0.75rem;" title="${row.descripcion_completa}">${row.descripcion_completa}</span>
            </td>
            <td class="text-center">
                <span class="font-weight-bold p-1 px-2 border rounded bg-white shadow-sm" style="font-size:1rem; color:#334155;">
                    ${row.docenas}<span class="text-muted mx-1">|</span>${strPad(row.unidades)}
                </span>
            </td>
            <td class="text-center">
                <span class="badge badge-info shadow-sm" style="font-size:0.8rem;">${row.num_lotes}</span>
            </td>
        </tr>
    `}).join('');
}

function renderWipByLot(data) {
    const tbody = document.getElementById('bodyWipLot');
    if (!data || !data.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-5"><i class="fas fa-clipboard-check fa-3x mb-3 text-light"></i><br>No hay lotes activos detectados en planta.</td></tr>';
        return;
    }
    
    const today = new Date();
    
    tbody.innerHTML = data.map(row => {
        const dUpdate = new Date(row.fecha_actualizacion);
        const diffDays = Math.floor(Math.abs(today - dUpdate) / (86400000));
        let lotWarning = '';
        if (diffDays > 4) {
            lotWarning = `<span class="badge badge-warning ml-1" title="Lote inactivo / sin actualizar hace ${diffDays} días"><i class="far fa-clock"></i> ${diffDays}d</span>`;
        }

        const refBadge = row.sal_tej_ref ? 
            `<span class="badge badge-secondary border font-weight-normal"><i class="fas fa-file-alt text-muted mr-1"></i>${row.sal_tej_ref}</span>` : 
            `<span class="badge badge-danger"><i class="fas fa-exclamation-triangle mr-1"></i>SIN REF</span>`;

        return `
        <tr>
            <td class="pl-4">
                <strong class="text-dark">${row.codigo_lote}</strong>
                ${lotWarning}
            </td>
            <td>
                <span class="d-block font-weight-500" style="font-size:0.85rem;">${row.codigo_producto}</span>
                <span class="text-muted text-truncate d-block" style="max-width:250px; font-size:0.75rem;">${row.descripcion_completa}</span>
            </td>
            <td><span class="badge" style="background:#e2e8f0; color:#475569;">${row.area_nombre}</span></td>
            <td><span class="badge badge-${getStatusColor(row.estado_lote)}">${row.estado_lote}</span></td>
            <td class="text-center font-weight-bold text-dark">${row.cantidad_docenas}<span class="text-muted mx-1">|</span>${strPad(row.cantidad_unidades)}</td>
            <td><small class="text-muted"><i class="far fa-calendar-alt mr-1"></i>${row.fecha_actualizacion}</small></td>
            <td>${refBadge}</td>
        </tr>
    `}).join('');
}

function renderWipByProduct(data) {
    const tbody = document.getElementById('bodyWipProduct');
    if (!data || !data.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-5"><i class="fas fa-box-open fa-3x mb-3 text-light"></i><br>Sin inventario WIP.</td></tr>';
        return;
    }
    tbody.innerHTML = data.map(row => `
        <tr>
            <td class="pl-4">
                <span class="font-weight-bold text-primary">${row.codigo_producto}</span>
                <br><span class="text-muted small">${row.descripcion_completa}</span>
            </td>
            <td class="text-center font-weight-bold text-dark" style="font-size:1.1rem;">
                ${row.docenas}<span class="text-muted mx-1">|</span>${strPad(row.unidades)}
            </td>
            <td class="text-center"><span class="badge badge-info px-3 py-2 rounded-pill shadow-sm">${row.num_lotes} lotes</span></td>
        </tr>
    `).join('');
}

function renderYarnControl(detalle, kardex) {
    const bodyDet = document.getElementById('bodyHilosDetalle');
    const bodyKar = document.getElementById('bodyHilosKardex');

    if (!detalle || !detalle.length) {
        bodyDet.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5"><i class="fas fa-braille fa-3x mb-3 text-light opacity-50"></i><br>Sin asignaciones BOM para este período.</td></tr>';
    } else {
        bodyDet.innerHTML = detalle.map(h => `
            <tr>
                <td class="pl-4">
                    <strong class="text-dark d-block">${h.codigo_hilo}</strong>
                    <span class="text-muted" style="font-size:0.75rem;">${h.nombre}</span>
                </td>
                <td class="text-right font-weight-500">${h.recibido.toFixed(2)}</td>
                <td class="text-right text-danger font-weight-bold">${h.consumo_teorico.toFixed(2)}</td>
                <td class="text-right">${h.saldo_maquina.toFixed(2)}</td>
                <td class="text-right text-muted">${h.saldo_sala.toFixed(2)}</td>
                <td class="text-right font-weight-bold" style="color:#0f172a;">${h.saldo_total.toFixed(2)}</td>
            </tr>
        `).join('');
    }

    if (!kardex || !kardex.length) {
        bodyKar.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-calendar-times opacity-25 fa-2x mb-2 d-block"></i>Información de bitácora no disponible.</td></tr>';
    } else {
        bodyKar.innerHTML = kardex.map(k => `
            <tr>
                <td class="pl-4"><span class="font-weight-500" style="font-size:0.85rem;">${k.codigo}</span></td>
                <td class="text-right text-muted">${k.saldo_anterior.toFixed(2)}</td>
                <td class="text-right text-success">+${k.recibido.toFixed(2)}</td>
                <td class="text-right text-danger">-${k.consumo_teorico.toFixed(2)}</td>
                <td class="text-right font-weight-bold ${k.saldo_final < 0 ? 'text-danger' : 'text-primary'}">${k.saldo_final.toFixed(2)}</td>
            </tr>
        `).join('');
    }
}

function renderTraceability(data) {
    const tbody = document.getElementById('bodyTraceability');
    if (!data || !data.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5"><i class="fas fa-link fa-3x mb-3 text-light opacity-50"></i><br>Sin trazabilidad directa vinculada.<br><small class="opacity-50">Producciones sin SAL-TEJ asociado no construyen red de trazabilidad.</small></td></tr>';
        return;
    }
    tbody.innerHTML = data.map(row => `
        <tr>
            <td class="pl-4">
                <span class="badge badge-light border text-dark font-weight-bold shadow-sm p-2"><i class="fas fa-truck-loading text-primary mr-1"></i>${row.sal_tej}</span>
            </td>
            <td><small class="text-muted">${row.fecha_documento}</small></td>
            <td><strong class="text-info">${row.codigo_lote}</strong></td>
            <td><span style="font-size:0.85rem;" class="font-weight-500">${row.codigo_producto}</span></td>
            <td>
                <span class="font-weight-bold border rounded px-2 py-1 bg-white shadow-sm" style="font-size:0.9rem;">
                    ${row.cantidad_docenas}<span class="text-muted mx-1">|</span>${strPad(row.cantidad_unidades)}
                </span>
            </td>
            <td>
                <span class="d-block" style="font-size:0.85rem;"><i class="fas fa-cogs mr-1 text-muted"></i>M${row.numero_maquina}</span>
                <small class="text-muted"><i class="far fa-clock mr-1"></i>${row.turno_nombre}</small>
            </td>
        </tr>
    `).join('');
}

function renderAlerts(alerts) {
    const container = document.getElementById('alertsContainer');
    if (!alerts || !alerts.length) {
        container.innerHTML = '';
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'flex';
    
    const iconMap = {
        'danger': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle',
        'success': 'check-circle'
    };

    container.innerHTML = alerts.map(a => {
        const type = a.type || 'info';
        const icon = iconMap[type] || 'bell';
        
        return `
        <div class="alert-card alert-${type} alert-dismissible fade show" role="alert">
            <i class="fas fa-${icon} alert-icon"></i>
            <div class="flex-grow-1 pr-4">
                <strong class="d-block mb-1 text-dark" style="font-size:0.8rem; letter-spacing:0.02em; text-transform:uppercase;">[${a.code || 'Alerta'}]</strong>
                <span style="font-size:0.85rem; color:#475569; line-height:1.2;">${a.msg}</span>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="background:none; border:none; padding:10px;">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        `;
    }).join('');
}

function getStatusColor(status) {
    switch(status) {
        case 'ACTIVO': return 'success';
        case 'PAUSADO': return 'warning';
        case 'CERRADO': return 'secondary';
        case 'ANULADO': return 'danger';
        default: return 'light';
    }
}

function strPad(n) { return String(n).padStart(2, '0'); }

</script>

<?php require_once '../../includes/footer.php'; ?>
