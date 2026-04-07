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

<div class="container-fluid dashboard-wip">
    <!-- Header & Filters -->
    <div class="d-flex justify-content-between align-items-center mb-4 pt-4 border-bottom pb-4 header-container">
        <div class="header-titles">
            <h1 class="h3 mb-1 font-weight-bold text-dark d-flex align-items-center">
                <span class="icon-box bg-primary-gradient mr-3">
                    <i class="fas fa-microchip text-white"></i>
                </span>
                WIP Tissue Intelligence
            </h1>
            <p class="text-muted mb-0 pl-1"><i class="fas fa-satellite-dish mr-1"></i> Control operativo y trazabilidad de hilos en tiempo real</p>
        </div>
        <div class="filters-panel p-3 bg-white rounded shadow-sm border d-flex align-items-end gap-3">
            <div class="filter-group">
                <label class="small font-weight-bold">Desde</label>
                <input type="date" id="fechaDesde" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="filter-group">
                <label class="small font-weight-bold">Hasta</label>
                <input type="date" id="fechaHasta" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="filter-group">
                <label class="small font-weight-bold">Turno</label>
                <select id="filtroTurno" class="form-control form-control-sm">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="small font-weight-bold">Maquina</label>
                <select id="filtroMaquina" class="form-control form-control-sm">
                    <option value="">Todas</option>
                </select>
            </div>
            <div class="filter-group">
                <button class="btn btn-primary btn-sm px-4" onclick="loadDashboard()">
                    <i class="fas fa-sync-alt mr-1"></i> Actualizar
                </button>
            </div>
        </div>
    </div>

    <!-- Alerts Section -->
    <div id="alertsContainer" class="mb-4"></div>

    <!-- KPI Row -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">MP Transmitida (Kg)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiMP">-</div>
                            <div class="text-muted small mt-1">Salida Real Almacén</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-weight-hanging fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Producción Hoy</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiProd">-</div>
                            <div class="text-muted small mt-1">Total Doc|Und</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-industry fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Saldo WIP Actual</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiWip">-</div>
                            <div class="text-muted small mt-1">En Piso Tejeduría</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-boxes fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Maquinas Activas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiMach">-</div>
                            <div class="text-muted small mt-1" id="kpiLots">- lotes activos</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-cogs fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hilos KPI Row (New) -->
    <div class="row mb-4 hilos-section">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Total Hilo Recibido</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><span id="kpiHiloRec">-</span> <small>Kg</small></div>
                            <div class="text-muted small mt-1">Saldo del Período</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-truck-loading fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Consumo Teórico (BOM)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><span id="kpiHiloCons">-</span> <small>Kg</small></div>
                            <div class="text-muted small mt-1">Estimado por Producción</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-invoice fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-dark shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Saldo en Proceso (Total)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><span id="kpiHiloSaldo">-</span> <small>Kg</small></div>
                            <div class="text-muted small mt-1">Máquinas + Sala</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-layer-group fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-indigo shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-indigo text-uppercase mb-1">Distribución de Saldo</div>
                            <div class="d-flex justify-content-between pr-3 mt-1">
                                <span class="small">Máquinas:</span>
                                <span class="small font-weight-bold" id="kpiHiloMaq">-</span>
                            </div>
                            <div class="d-flex justify-content-between pr-3">
                                <span class="small">Sala/Piso:</span>
                                <span class="small font-weight-bold" id="kpiHiloSala">-</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-warehouse fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Tabs/Cards -->
    <div class="row">
        <!-- Production by Machine -->
        <div class="col-lg-7 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Operativo: Producción por Máquina</h6>
                    <button class="btn btn-sm btn-outline-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i>Reporte</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Máquina</th>
                                    <th>Turno</th>
                                    <th>Producto</th>
                                    <th class="text-center">Cant (Doc|Und)</th>
                                    <th class="text-center"># Lotes</th>
                                </tr>
                            </thead>
                            <tbody id="bodyProductionMachine"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- WIP Inventory (Product Summary) -->
        <div class="col-lg-5 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Inventario WIP (Resumen Producto)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center">Saldo Actual</th>
                                    <th class="text-center">Lotes</th>
                                </tr>
                            </thead>
                            <tbody id="bodyWipProduct"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div>

    <!-- Hilos Detail: Control de Consumo (New) -->
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-gray-100">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-braille mr-2"></i>Detalle de Hilos: Consumo Teórico (BOM) vs Saldo</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 text-sm">
                            <thead class="bg-light">
                                <tr>
                                    <th>Hilo / Fibra</th>
                                    <th class="text-right">Recibido (Kg)</th>
                                    <th class="text-right">Consumo BOM (Kg)</th>
                                    <th class="text-right">En Máquina</th>
                                    <th class="text-right">En Sala</th>
                                    <th class="text-right">Total</th>
                                    <th style="width: 150px;">Uso</th>
                                </tr>
                            </thead>
                            <tbody id="bodyHilosDetalle"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Yarn Kardex (Review by Period) -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow mb-4 border-bottom-primary">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Análisis por Período (Kardex)</h6>
                </div>
                <div class="card-body p-0 text-sm">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Hilo</th>
                                    <th class="text-right">Ant.</th>
                                    <th class="text-right">Rec.</th>
                                    <th class="text-right">Cons.</th>
                                    <th class="text-right font-weight-bold">Final</th>
                                </tr>
                            </thead>
                            <tbody id="bodyHilosKardex"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed WIP by Lot (Restored & Upgraded) -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow mb-4 border-left-primary">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list-ul mr-2"></i>Vista Detallada por Lote (WIP Activo)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 text-sm">
                            <thead>
                                <tr>
                                    <th>Lote</th>
                                    <th>Producto</th>
                                    <th>Área Actual</th>
                                    <th>Estado</th>
                                    <th class="text-right">Docenas | Unidades</th>
                                    <th>Últ. Actualización</th>
                                    <th>Ref SAL-TEJ</th>
                                </tr>
                            </thead>
                            <tbody id="bodyWipLot"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Traceability SAL-TEJ -> Lote -> Production -->
    <div class="row" id="traceabilitySection">
        <div class="col-12 mb-4">
            <div class="card shadow mb-4 border-left-info">
                <div class="card-header py-3 bg-light">
                    <h6 class="m-0 font-weight-bold text-info"><i class="fas fa-project-diagram mr-2"></i>Trazabilidad Completa: Origen → Lote → Resultado</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 text-sm">
                            <thead>
                                <tr>
                                    <th>SAL-TEJ (Origen)</th>
                                    <th>Fecha MP</th>
                                    <th>Lote WIP</th>
                                    <th>Producto</th>
                                    <th>Resultado</th>
                                    <th>Máquina | Turno</th>
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
    --primary-dark: #2e59d9;
    --success: #1cc88a;
    --info: #36b9cc;
    --warning: #f6c23e;
    --danger: #e74a3b;
    --indigo: #6610f2;
    --dark: #1a202c;
    --gray-100: #f8f9fc;
    --gray-200: #eaecf4;
    --card-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background-color: #f7fafc; }

.dashboard-wip { padding: 0 25px; max-width: 1600px; margin: 0 auto; }

/* Header & Filters */
.header-container { background: transparent; }
.icon-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 1.25rem; }
.bg-primary-gradient { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); }

.filters-panel { 
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 16px; 
    padding: 20px !important;
    transition: var(--transition);
}
.filters-panel:hover { box-shadow: 0 10px 25px rgba(0,0,0,0.05) !important; }
.filter-group label { font-size: 0.75rem; color: #718096; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
.form-control-sm { border-radius: 8px; border: 1px solid var(--gray-200); height: 38px; }
.form-control-sm:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.1); }

/* Premium Cards */
.card { 
    border: none; 
    border-radius: 20px; 
    transition: var(--transition); 
    overflow: visible;
    background: #ffffff;
}
.card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.08) !important; }

.card-body { padding: 1.5rem !important; }

.border-left-primary { border-left: 5px solid var(--primary) !important; }
.border-left-success { border-left: 5px solid var(--success) !important; }
.border-left-info { border-left: 5px solid var(--info) !important; }
.border-left-warning { border-left: 5px solid var(--warning) !important; }
.border-left-secondary { border-left: 5px solid #6c757d !important; }
.border-left-danger { border-left: 5px solid var(--danger) !important; }
.border-left-dark { border-left: 5px solid var(--dark) !important; }
.border-left-indigo { border-left: 5px solid var(--indigo) !important; }

.text-xs { font-size: .75rem; letter-spacing: 0.05em; margin-bottom: 0.5rem !important; }
.h5 { font-size: 1.5rem; letter-spacing: -0.02em; }
.text-gray-300 { color: #edf2f7 !important; }

/* Tables & Sections */
.card-header { 
    background-color: transparent !important; 
    border-bottom: 1px solid var(--gray-200) !important; 
    padding: 1.25rem 1.5rem !important;
}
.card-header h6 { font-size: 1rem; letter-spacing: -0.01em; }

.table { border-collapse: separate; border-spacing: 0 8px; padding: 0 1.5rem; }
.table thead th { background: transparent; border: none; font-size: 0.7rem; text-transform: uppercase; color: #a0aec0; padding: 10px 15px; }
.table tbody tr { background: #fff; transition: var(--transition); border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
.table tbody tr:hover { background: #f8fafc; z-index: 10; transform: scale(1.005); }
.table td { border: none !important; vertical-align: middle !important; padding: 15px !important; }
.table td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
.table td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }

/* Badges & UI Elements */
.badge { padding: 6px 12px; border-radius: 8px; font-weight: 600; text-transform: uppercase; font-size: 0.65rem; }
.badge-success { background-color: #c6f6d5; color: #22543d; }
.badge-warning { background-color: #feebc8; color: #744210; }
.badge-danger { background-color: #fed7d7; color: #822727; }
.badge-info { background-color: #bee3f8; color: #2a4365; }
.badge-secondary { background-color: #edf2f7; color: #4a5568; }

/* Progress Bars */
.progress-small { height: 10px; border-radius: 10px; background-color: #edf2f7; overflow: hidden; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); }
.progress-bar { transition: width 1s ease; }

/* Alertas */
.alert { border: none; border-radius: 16px; padding: 1rem 1.5rem; }

/* Animations */
@keyframes pulse-soft {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.8; }
    100% { transform: scale(1); opacity: 1; }
}
.pulse-active { animation: pulse-soft 2s infinite ease-in-out; }

@media print {
    .filters-panel, .includes-header, .sidebar, .topbar { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .card { shadow: none !important; border: 1px solid #ddd !important; }
}
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
    document.getElementById('kpiMP').innerHTML = `${kpis.mp_transferred_today.toFixed(2)} <small class="text-muted">Kg</small>`;
    document.getElementById('kpiProd').textContent = kpis.production_today_fmt;
    document.getElementById('kpiWip').textContent = kpis.wip_balance_current_fmt;
    document.getElementById('kpiMach').innerHTML = `${kpis.active_machines} <span class="badge badge-success pulse-active ml-2">En Línea</span>`;
    document.getElementById('kpiLots').textContent = kpis.active_lots + ' lotes activos en planta';

    // Hilo KPIs
    document.getElementById('kpiHiloRec').textContent = kpis.hilo_recibido.toFixed(2);
    document.getElementById('kpiHiloCons').textContent = kpis.hilo_consumo_teorico.toFixed(2);
    document.getElementById('kpiHiloSaldo').textContent = kpis.hilo_saldo_proceso.toFixed(2);
    document.getElementById('kpiHiloMaq').textContent = kpis.hilo_saldo_maquinas.toFixed(2) + ' Kg';
    document.getElementById('kpiHiloSala').textContent = kpis.hilo_saldo_sala.toFixed(2) + ' Kg';
}

function renderYarnControl(detalle, kardex) {
    const bodyDet = document.getElementById('bodyHilosDetalle');
    const bodyKar = document.getElementById('bodyHilosKardex');

    // Detalle Table
    if (!detalle.length) {
        bodyDet.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Sin movimientos de hilos detectados</td></tr>';
    } else {
        bodyDet.innerHTML = detalle.map(h => `
            <tr>
                <td><strong>${h.codigo_hilo}</strong><br><small class="text-muted">${h.nombre}</small></td>
                <td class="text-right">${h.recibido.toFixed(2)}</td>
                <td class="text-right text-danger">${h.consumo_teorico.toFixed(2)}</td>
                <td class="text-right">${h.saldo_maquina.toFixed(2)}</td>
                <td class="text-right">${h.saldo_sala.toFixed(2)}</td>
                <td class="text-right font-weight-bold">${h.saldo_total.toFixed(2)}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="progress flex-grow-1 progress-small mr-2 bg-gray-200">
                            <div class="progress-bar bg-success" role="progressbar" style="width: ${h.porcentajes.consumido}%"></div>
                            <div class="progress-bar bg-amber" role="progressbar" style="width: ${h.porcentajes.saldo}%; background-color: #f6c23e;"></div>
                        </div>
                        <small>${h.porcentajes.consumido}%</small>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // Kardex Table
    if (!kardex.length) {
        bodyKar.innerHTML = '<tr><td colspan="5" class="text-center text-muted">N/A</td></tr>';
    } else {
        bodyKar.innerHTML = kardex.map(k => `
            <tr>
                <td><small><strong>${k.codigo}</strong></small></td>
                <td class="text-right">${k.saldo_anterior.toFixed(1)}</td>
                <td class="text-right">${k.recibido.toFixed(1)}</td>
                <td class="text-right text-danger">${k.consumo_teorico.toFixed(1)}</td>
                <td class="text-right font-weight-bold ${k.saldo_final < 0 ? 'text-danger' : 'text-primary'}">${k.saldo_final.toFixed(1)}</td>
            </tr>
        `).join('');
    }
}

function renderProductionByMachine(data) {
    const tbody = document.getElementById('bodyProductionMachine');
    if (!data.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No hay producción en este periodo</td></tr>';
        return;
    }
    tbody.innerHTML = data.map(row => `
        <tr>
            <td><strong>M${row.numero_maquina}</strong></td>
            <td>${row.turno_nombre}</td>
            <td><small>${row.codigo_producto}</small><br>${row.descripcion_completa}</td>
            <td class="text-center"><strong>${row.docenas}|${strPad(row.unidades)}</strong></td>
            <td class="text-center"><span class="badge badge-light border">${row.num_lotes}</span></td>
        </tr>
    `).join('');
}

function renderWipByLot(data) {
    const tbody = document.getElementById('bodyWipLot');
    if (!data.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No hay saldo WIP detectable</td></tr>';
        return;
    }
    tbody.innerHTML = data.map(row => `
        <tr>
            <td><strong>${row.codigo_lote}</strong></td>
            <td><small>${row.codigo_producto}</small><br>${row.descripcion_completa}</td>
            <td>${row.area_nombre}</td>
            <td><span class="badge badge-${getStatusColor(row.estado_lote)}">${row.estado_lote}</span></td>
            <td class="text-center"><strong>${row.cantidad_docenas}|${strPad(row.cantidad_unidades)}</strong></td>
            <td><small>${row.fecha_actualizacion}</small></td>
            <td>${row.sal_tej_ref || '<span class="text-danger small"><i class="fas fa-exclamation-triangle mr-1"></i>Sin Referencia</span>'}</td>
        </tr>
    `).join('');
}

function renderWipByProduct(data) {
    const tbody = document.getElementById('bodyWipProduct');
    if (!data.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Sin saldo</td></tr>';
        return;
    }
    tbody.innerHTML = data.map(row => `
        <tr>
            <td><strong>${row.codigo_producto}</strong><br><small>${row.descripcion_completa}</small></td>
            <td class="text-center font-weight-bold">${row.docenas}|${strPad(row.unidades)}</td>
            <td class="text-center"><span class="badge badge-info">${row.num_lotes}</span></td>
        </tr>
    `).join('');
}

function renderTraceability(data) {
    const tbody = document.getElementById('bodyTraceability');
    if (!data.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No hay datos de trazabilidad directa</td></tr>';
        return;
    }
    tbody.innerHTML = data.map(row => `
        <tr>
            <td><strong>${row.sal_tej}</strong></td>
            <td><small>${row.fecha_documento}</small></td>
            <td><strong>${row.codigo_lote}</strong></td>
            <td><small>${row.codigo_producto}</small></td>
            <td><strong>${row.cantidad_docenas}|${strPad(row.cantidad_unidades)}</strong></td>
            <td>M${row.numero_maquina} | ${row.turno_nombre}</td>
        </tr>
    `).join('');
}

function renderAlerts(alerts) {
    const container = document.getElementById('alertsContainer');
    if (!alerts.length) {
        container.innerHTML = '';
        return;
    }
    container.innerHTML = alerts.map(a => `
        <div class="alert alert-${a.type} alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-${a.type === 'danger' ? 'circle' : 'triangle'} alert-icon mr-2"></i>
            <strong>Alerta Operativa [${a.code}]:</strong> ${a.msg}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `).join('');
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
