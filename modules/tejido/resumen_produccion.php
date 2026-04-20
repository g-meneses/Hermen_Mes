<?php
require_once '../../config/database.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Resumen de Producción de Tejido';
$currentPage = 'resumen_produccion_tejido';
require_once '../../includes/header.php';
?>

<div class="resumen-container">

    <!-- ══════════════ HEADER ══════════════ -->
    <header class="page-header">
        <div class="header-content">
            <h1><i class="fas fa-chart-bar"></i> Resumen de Producción &mdash; Tejido</h1>
            <p>Vista gerencial multi-dimensión: por producto, por día y por turno.</p>
        </div>
        <div class="header-actions">
            <button class="btn-export" id="btnExport" onclick="exportarVista()" style="display:none;">
                <i class="fas fa-file-csv"></i> Exportar CSV
            </button>
        </div>
    </header>

    <!-- ══════════════ FILTROS ══════════════ -->
    <div class="filter-bar glass">
        <div class="filter-group">
            <label>Rango de fechas <span class="req">*</span></label>
            <div class="date-row">
                <input type="date" id="filtroFechaInicio" class="fc">
                <span class="date-sep">→</span>
                <input type="date" id="filtroFechaFin" class="fc">
            </div>
        </div>
        <div class="filter-group">
            <label>Familia</label>
            <select id="filtroFamilia" class="fc" onchange="filtrarProductosPorFamilia()"><option value="">Todas</option></select>
        </div>
        <div class="filter-group">
            <label>Producto</label>
            <select id="filtroProducto" class="fc"><option value="">Todos</option></select>
        </div>
        <div class="filter-group">
            <label>Turno</label>
            <select id="filtroTurno" class="fc"><option value="">Todos</option></select>
        </div>
        <div class="filter-actions">
            <button class="btn-primary" id="btnConsultar" onclick="consultarResumen()">
                <i class="fas fa-search"></i> Consultar
            </button>
            <button class="btn-ghost" onclick="limpiarFiltros()">
                <i class="fas fa-times"></i> Limpiar
            </button>
        </div>
    </div>

    <!-- ══════════════ KPIs ══════════════ -->
    <div id="secKpis" class="kpi-wrapper" style="display:none;">

        <!-- Fila 1: métricas globales -->
        <div class="kpi-row main-kpis">
            <div class="kpi blue">
                <div class="kpi-ico"><i class="fas fa-boxes"></i></div>
                <div>
                    <div class="kpi-val" id="kpiProductos">0</div>
                    <div class="kpi-lbl">Productos distintos</div>
                </div>
            </div>
            <div class="kpi green">
                <div class="kpi-ico"><i class="fas fa-layer-group"></i></div>
                <div>
                    <div class="kpi-val" id="kpiDocenas">0</div>
                    <div class="kpi-lbl">Total Docenas</div>
                </div>
            </div>
            <div class="kpi purple">
                <div class="kpi-ico"><i class="fas fa-cubes"></i></div>
                <div>
                    <div class="kpi-val" id="kpiUnidades">0</div>
                    <div class="kpi-lbl">Total Unidades</div>
                </div>
            </div>
            <div class="kpi orange">
                <div class="kpi-ico"><i class="fas fa-cog"></i></div>
                <div>
                    <div class="kpi-val" id="kpiMaquinas">0</div>
                    <div class="kpi-lbl">Máquinas</div>
                </div>
            </div>
            <div class="kpi teal">
                <div class="kpi-ico"><i class="fas fa-clipboard-list"></i></div>
                <div>
                    <div class="kpi-val" id="kpiRegistros">0</div>
                    <div class="kpi-lbl">Registros</div>
                </div>
            </div>
            <div class="kpi slate">
                <div class="kpi-ico"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <div class="kpi-val" id="kpiDias">0</div>
                    <div class="kpi-lbl">Días con producción</div>
                </div>
            </div>
        </div>

        <!-- Fila 2: métricas de análisis -->
        <div class="kpi-row insight-kpis">
            <div class="insight-card">
                <div class="insight-label"><i class="fas fa-chart-line"></i> Promedio diario</div>
                <div class="insight-val" id="insPromDoc">—</div>
                <div class="insight-sub" id="insPromBase"></div>
            </div>
            <div class="insight-card highlight">
                <div class="insight-label"><i class="fas fa-trophy"></i> Mejor día</div>
                <div class="insight-val date-font" id="insMejorDia">—</div>
                <div class="insight-sub" id="insMejorDiaBase"></div>
            </div>
            <div class="insight-card highlight-purple">
                <div class="insight-label"><i class="fas fa-moon"></i> Turno top</div>
                <div class="insight-val" id="insTurnoTop">—</div>
                <div class="insight-sub" id="insTurnoTopBase"></div>
            </div>
        </div>
    </div>

    <!-- ══════════════ TABS ══════════════ -->
    <div id="secTabs" style="display:none;">
        <div class="tab-bar">
            <button class="tab-btn active" id="tab-producto" onclick="cambiarTab('producto')">
                <i class="fas fa-boxes"></i> Por Producto
                <span class="tab-count" id="cntProducto">0</span>
            </button>
            <button class="tab-btn" id="tab-dia" onclick="cambiarTab('dia')">
                <i class="fas fa-calendar-day"></i> Por Día
                <span class="tab-count" id="cntDia">0</span>
            </button>
            <button class="tab-btn" id="tab-turno" onclick="cambiarTab('turno')">
                <i class="fas fa-clock"></i> Por Turno
                <span class="tab-count" id="cntTurno">0</span>
            </button>
        </div>

        <!-- ── Panel: Por Producto ── -->
        <div class="tab-panel" id="panel-producto">
            <div class="panel-header">
                <div class="panel-title"><i class="fas fa-boxes"></i> Producción agrupada por producto <span class="rango-pill" id="labelRangoProd"></span></div>
                <div class="panel-info" id="infoProd"></div>
            </div>
            <div class="table-responsive">
                <table class="itab" id="tablaProducto">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Código</th>
                            <th>Producto</th>
                            <th class="tr">Docenas</th>
                            <th class="tr">Unidades</th>
                            <th class="tr dim">Base uni.</th>
                            <th class="tc">Registros</th>
                            <th class="tc">Máquinas</th>
                            <th class="tc">Turnos</th>
                            <th>Última fecha</th>
                        </tr>
                    </thead>
                    <tbody id="tbProducto"></tbody>
                    <tfoot id="tfProducto" style="display:none;"></tfoot>
                </table>
            </div>
        </div>

        <!-- ── Panel: Por Día ── -->
        <div class="tab-panel" id="panel-dia" style="display:none;">
            <div class="panel-header">
                <div class="panel-title"><i class="fas fa-calendar-day"></i> Producción agrupada por día</div>
                <div class="panel-info" id="infoDia"></div>
            </div>
            <div class="chart-container" id="chartDia">
                <!-- Mini barras SVG -->
            </div>
            <div class="table-responsive">
                <table class="itab" id="tablaDia">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th class="tr">Docenas</th>
                            <th class="tr">Unidades</th>
                            <th class="tr dim">Base uni.</th>
                            <th class="tc">Registros</th>
                            <th class="tc">Productos</th>
                            <th class="tc">Máquinas</th>
                            <th class="tc">Turnos</th>
                            <th class="tc">Participación</th>
                        </tr>
                    </thead>
                    <tbody id="tbDia"></tbody>
                    <tfoot id="tfDia" style="display:none;"></tfoot>
                </table>
            </div>
        </div>

        <!-- ── Panel: Por Turno ── -->
        <div class="tab-panel" id="panel-turno" style="display:none;">
            <div class="panel-header">
                <div class="panel-title"><i class="fas fa-clock"></i> Producción agrupada por turno</div>
                <div class="panel-info" id="infoTurno"></div>
            </div>
            <div class="table-responsive">
                <table class="itab" id="tablaTurno">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Turno</th>
                            <th class="tr">Docenas</th>
                            <th class="tr">Unidades</th>
                            <th class="tr dim">Base uni.</th>
                            <th class="tc">Registros</th>
                            <th class="tc">Productos</th>
                            <th class="tc">Máquinas</th>
                            <th>Primera fecha</th>
                            <th>Última fecha</th>
                            <th class="tc">Participación</th>
                        </tr>
                    </thead>
                    <tbody id="tbTurno"></tbody>
                    <tfoot id="tfTurno" style="display:none;"></tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- ══════════════ ESTADOS ══════════════ -->
    <div id="estadoInicial" class="estado">
        <div class="estado-ico"><i class="fas fa-chart-bar"></i></div>
        <h3>Selecciona un rango de fechas para iniciar</h3>
        <p>Elige fecha inicio y fecha fin, luego presiona <strong>Consultar</strong>.</p>
    </div>
    <div id="estadoVacio" class="estado" style="display:none;">
        <div class="estado-ico empty"><i class="fas fa-inbox"></i></div>
        <h3>Sin resultados para el período seleccionado</h3>
        <p>No se encontraron lotes de producción activos en ese rango de fechas.</p>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     ESTILOS
══════════════════════════════════════════════════════════ -->
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

/* ─── Variables ─── */
:root{
    --c-blue:#4f46e5; --c-blue-lt:#ede9fe;
    --c-green:#059669; --c-green-lt:#d1fae5;
    --c-purple:#7c3aed; --c-purple-lt:#f5f3ff;
    --c-orange:#d97706; --c-orange-lt:#fef3c7;
    --c-teal:#0891b2; --c-teal-lt:#e0f2fe;
    --c-slate:#475569; --c-slate-lt:#f1f5f9;
    --radius:14px; --radius-sm:8px;
    --shadow:0 2px 8px rgba(0,0,0,.07);
    --shadow-lg:0 8px 32px rgba(0,0,0,.11);
    --border:#e9eef4;
}

/* ─── Layout ─── */
.resumen-container{padding:24px;max-width:1600px;margin:0 auto;font-family:'Inter','Segoe UI',sans-serif;}

/* ─── Page header ─── */
.page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:22px;}
.page-header h1{font-size:24px;font-weight:800;color:#0f172a;margin:0;display:flex;align-items:center;gap:10px;}
.page-header h1 i{color:var(--c-blue);}
.page-header p{margin:5px 0 0;color:#64748b;font-size:13px;}

/* ─── Filter bar ─── */
.filter-bar{padding:18px 22px;border-radius:var(--radius);display:flex;gap:18px;align-items:flex-end;flex-wrap:wrap;
    background:rgba(255,255,255,.92);backdrop-filter:blur(14px);
    box-shadow:var(--shadow);border:1px solid rgba(255,255,255,.8);
    margin-bottom:22px;}
.filter-group{display:flex;flex-direction:column;gap:5px;min-width:170px;}
.filter-group label{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;}
.req{color:#ef4444;}
.date-row{display:flex;align-items:center;gap:6px;}
.date-sep{color:#94a3b8;font-weight:600;font-size:13px;}
.fc{padding:9px 13px;border-radius:var(--radius-sm);border:1.5px solid var(--border);
    font-size:13px;font-family:inherit;color:#1e293b;background:#f8fafc;
    transition:.18s;outline:none;min-width:130px;}
.fc:focus{border-color:var(--c-blue);background:#fff;box-shadow:0 0 0 3px rgba(79,70,229,.11);}
.filter-actions{display:flex;gap:9px;align-items:flex-end;}

/* Buttons */
.btn-primary{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none;
    padding:9px 20px;border-radius:var(--radius-sm);font-weight:700;font-size:13px;
    cursor:pointer;display:flex;align-items:center;gap:7px;
    transition:.2s;box-shadow:0 4px 12px rgba(79,70,229,.3);}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(79,70,229,.4);}
.btn-primary:disabled{opacity:.6;cursor:not-allowed;transform:none;}
.btn-ghost{background:transparent;color:#64748b;border:1.5px solid var(--border);
    padding:9px 16px;border-radius:var(--radius-sm);font-weight:600;font-size:13px;
    cursor:pointer;transition:.18s;}
.btn-ghost:hover{background:#f1f5f9;}
.btn-export{background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;
    padding:9px 18px;border-radius:var(--radius-sm);font-weight:700;font-size:13px;
    cursor:pointer;display:flex;align-items:center;gap:7px;
    transition:.2s;box-shadow:0 4px 12px rgba(16,185,129,.3);}
.btn-export:hover{transform:translateY(-1px);}

/* ─── KPIs ─── */
.kpi-wrapper{margin-bottom:22px;}
.kpi-row{display:grid;gap:14px;margin-bottom:14px;}
.main-kpis{grid-template-columns:repeat(6,1fr);}
.insight-kpis{grid-template-columns:repeat(3,1fr);}

.kpi{background:#fff;border-radius:var(--radius);padding:18px;display:flex;
    align-items:center;gap:14px;box-shadow:var(--shadow);border:1px solid var(--border);
    position:relative;overflow:hidden;transition:.2s;}
.kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
.kpi:hover{transform:translateY(-2px);box-shadow:var(--shadow-lg);}

.kpi.blue::before{background:linear-gradient(90deg,#4f46e5,#818cf8);}
.kpi.green::before{background:linear-gradient(90deg,#059669,#34d399);}
.kpi.purple::before{background:linear-gradient(90deg,#7c3aed,#a78bfa);}
.kpi.orange::before{background:linear-gradient(90deg,#d97706,#fbbf24);}
.kpi.teal::before{background:linear-gradient(90deg,#0891b2,#22d3ee);}
.kpi.slate::before{background:linear-gradient(90deg,#475569,#94a3b8);}

.kpi-ico{width:44px;height:44px;border-radius:11px;display:flex;
    align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.kpi.blue .kpi-ico{background:var(--c-blue-lt);color:var(--c-blue);}
.kpi.green .kpi-ico{background:var(--c-green-lt);color:var(--c-green);}
.kpi.purple .kpi-ico{background:var(--c-purple-lt);color:var(--c-purple);}
.kpi.orange .kpi-ico{background:var(--c-orange-lt);color:var(--c-orange);}
.kpi.teal .kpi-ico{background:var(--c-teal-lt);color:var(--c-teal);}
.kpi.slate .kpi-ico{background:var(--c-slate-lt);color:var(--c-slate);}

.kpi-val{font-size:26px;font-weight:800;color:#0f172a;letter-spacing:-.02em;line-height:1;}
.kpi-lbl{font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;
    letter-spacing:.05em;margin-top:3px;}

/* Insight cards */
.insight-card{background:#fff;border-radius:var(--radius);padding:18px 22px;
    box-shadow:var(--shadow);border:1px solid var(--border);
    display:flex;flex-direction:column;gap:6px;position:relative;overflow:hidden;}
.insight-card.highlight{border-color:#fbbf24;background:linear-gradient(135deg,#fffbeb,#fff);}
.insight-card.highlight::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;
    background:linear-gradient(90deg,#f59e0b,#fbbf24);}
.insight-card.highlight-purple{border-color:#a78bfa;background:linear-gradient(135deg,#f5f3ff,#fff);}
.insight-card.highlight-purple::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;
    background:linear-gradient(90deg,#7c3aed,#a78bfa);}
.insight-label{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;
    letter-spacing:.06em;display:flex;align-items:center;gap:6px;}
.insight-val{font-size:22px;font-weight:800;color:#0f172a;letter-spacing:-.02em;}
.insight-val.date-font{font-family:monospace;font-size:19px;}
.insight-sub{font-size:12px;color:#94a3b8;font-weight:500;}

/* ─── Tabs ─── */
.tab-bar{display:flex;gap:4px;background:#f1f5f9;border-radius:12px 12px 0 0;
    padding:6px 6px 0;border:1px solid var(--border);border-bottom:none;}
.tab-btn{padding:10px 18px;border:none;background:transparent;cursor:pointer;
    font-family:inherit;font-size:13px;font-weight:600;color:#64748b;
    border-radius:9px 9px 0 0;display:flex;align-items:center;gap:8px;
    transition:.18s;position:relative;top:0;}
.tab-btn:hover{background:rgba(255,255,255,.7);color:#334155;}
.tab-btn.active{background:#fff;color:var(--c-blue);
    box-shadow:0 -2px 8px rgba(0,0,0,.06);}
.tab-count{background:#e0e7ff;color:#4f46e5;font-size:11px;font-weight:700;
    padding:1px 7px;border-radius:999px;}
.tab-btn.active .tab-count{background:#4f46e5;color:#fff;}

/* ─── Tab panels ─── */
.tab-panel{background:#fff;border-radius:0 0 var(--radius) var(--radius);
    border:1px solid var(--border);border-top:none;overflow:hidden;}
.panel-header{display:flex;justify-content:space-between;align-items:center;
    padding:16px 22px;border-bottom:1px solid #f1f5f9;}
.panel-title{font-weight:700;font-size:14px;color:#1e293b;display:flex;align-items:center;gap:8px;}
.panel-title i{color:var(--c-blue);}
.rango-pill{background:#ede9fe;color:#4f46e5;font-size:11px;font-weight:700;
    padding:2px 9px;border-radius:999px;font-family:monospace;}
.panel-info{font-size:12px;color:#64748b;font-weight:500;}

/* ─── Tabla industrial ─── */
.table-responsive{overflow-x:auto;}
.itab{width:100%;border-collapse:collapse;}
.itab thead th{background:#f8fafc;padding:11px 14px;font-size:11px;font-weight:700;
    color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;
    border-bottom:2px solid #e2e8f0;}
.itab tbody td{padding:13px 14px;font-size:13px;border-bottom:1px solid #f1f5f9;
    color:#334155;transition:background .12s;}
.itab tbody tr:hover td{background:#fafbff;}
.itab tbody tr:last-child td{border-bottom:none;}
.itab tfoot td{padding:13px 14px;font-size:13px;background:#f0f9ff;
    font-weight:700;color:#0f172a;border-top:2px solid #bfdbfe;}

.tr{text-align:right;}
.tc{text-align:center;}
.dim{color:#94a3b8;}

/* Pills */
.cod{background:#f1f5f9;color:#475569;font-family:monospace;font-size:11px;
    font-weight:700;padding:2px 7px;border-radius:5px;display:inline-block;}
.num{font-size:15px;font-weight:700;color:#1e293b;}
.sub{font-size:11px;color:#94a3b8;}
.pill-blue{background:#e0e7ff;color:#4f46e5;font-size:11px;font-weight:700;
    padding:2px 9px;border-radius:999px;display:inline-block;}
.pill-green{background:#dcfce7;color:#15803d;font-size:11px;font-weight:700;
    padding:2px 9px;border-radius:999px;display:inline-block;}
.pill-orange{background:#fef3c7;color:#92400e;font-size:11px;font-weight:700;
    padding:2px 9px;border-radius:999px;display:inline-block;}
.pill-purple{background:#f5f3ff;color:#6d28d9;font-size:11px;font-weight:700;
    padding:2px 9px;border-radius:999px;display:inline-block;}
.pill-gold{background:#fef9c3;color:#854d0e;font-size:11px;font-weight:700;
    padding:2px 9px;border-radius:999px;display:inline-block;}
.dt{font-family:monospace;font-size:12px;color:#64748b;}

/* Mini bar chart */
.chart-container{padding:16px 22px 4px;display:flex;align-items:flex-end;gap:5px;
    min-height:80px;overflow-x:auto;}
.bar-wrap{display:flex;flex-direction:column;align-items:center;gap:4px;min-width:40px;max-width:80px;flex:1;}
.bar{width:100%;border-radius:4px 4px 0 0;background:linear-gradient(180deg,#4f46e5,#818cf8);
    min-height:4px;transition:height .4s ease;cursor:default;position:relative;}
.bar:hover::after{content:attr(data-tip);position:absolute;top:-28px;left:50%;transform:translateX(-50%);
    background:#1e293b;color:#fff;font-size:10px;padding:3px 7px;border-radius:5px;white-space:nowrap;
    font-family:'Inter',sans-serif;pointer-events:none;}
.bar-lbl{font-size:9px;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    max-width:100%;text-align:center;}
.bar-cnt{font-size:10px;font-weight:700;color:#475569;}

/* Barra de participación */
.part-bar{width:100%;height:7px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin-top:3px;}
.part-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#4f46e5,#818cf8);}

/* Estado vacío / inicial */
.estado{text-align:center;padding:70px 20px;color:#94a3b8;}
.estado-ico{font-size:56px;margin-bottom:16px;opacity:.35;}
.estado-ico.empty{color:#94a3b8;}
.estado h3{font-size:18px;font-weight:700;color:#475569;margin:0 0 8px;}
.estado p{font-size:13px;margin:0;color:#94a3b8;}

/* Spinner */
.spinner-row{display:flex;flex-direction:column;align-items:center;justify-content:center;
    padding:50px;gap:14px;color:#64748b;font-size:13px;}
.spin{width:36px;height:36px;border:4px solid #e2e8f0;border-top-color:#4f46e5;
    border-radius:50%;animation:rot .7s linear infinite;}
@keyframes rot{to{transform:rotate(360deg);}}

/* Toast */
.toast{position:fixed;top:20px;right:24px;z-index:9999;
    padding:13px 20px;border-radius:11px;font-size:13px;font-weight:600;
    box-shadow:0 8px 24px rgba(0,0,0,.12);display:flex;align-items:center;gap:9px;
    max-width:380px;animation:fadeInRight .25s ease;}
@keyframes fadeInRight{from{opacity:0;transform:translateX(36px);}to{opacity:1;transform:translateX(0);}}

/* Responsive */
@media(max-width:1200px){.main-kpis{grid-template-columns:repeat(3,1fr);}}
@media(max-width:900px){
    .main-kpis{grid-template-columns:repeat(2,1fr);}
    .insight-kpis{grid-template-columns:1fr;}
    .filter-bar{flex-direction:column;}
}
</style>

<!-- ══════════════════════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════════════════════ -->
<script>
const API = window.location.origin + '/mes_hermen/api/wip.php';
let dataGlobal   = {};
let tabActiva    = 'producto';
window.allProductos = [];

// ─── Init ───────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    setFechasDefecto();
    cargarCatalogos();
});

function setFechasDefecto() {
    const hoy = new Date();
    const pri = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    document.getElementById('filtroFechaInicio').value = fmtDate(pri);
    document.getElementById('filtroFechaFin').value    = fmtDate(hoy);
}

function fmtDate(d) { return d.toISOString().split('T')[0]; }
function fmtNum(n)  { return Number(n || 0).toLocaleString('es-CL'); }
function esc(s)     { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ─── Catálogos ───────────────────────────────────────────────────────────────
async function cargarCatalogos() {
    const base = window.location.origin + '/mes_hermen/api/catalogos.php';
    try {
        const rt = await fetch(`${base}?tipo=turnos`);
        const dt = await rt.json();
        if (dt.success) populateSelect('filtroTurno', dt.turnos, 'id_turno', 'nombre');
    } catch(e) {}

    try {
        const lf = await fetch(`${base}?tipo=lineas`);
        const df = await lf.json();
        if (df.success) populateSelect('filtroFamilia', df.lineas, 'id_linea', 'nombre_linea');
    } catch(e) {}

    try {
        const rp = await fetch(window.location.origin + '/mes_hermen/api/productos.php');
        const dp = await rp.json();
        window.allProductos = dp.success ? (dp.productos || dp.data || []) : [];
        if (window.allProductos.length) {
            popularComboProductos(window.allProductos);
        }
    } catch(e) {}
}

function popularComboProductos(arr) {
    const sel = document.getElementById('filtroProducto');
    sel.innerHTML = '<option value="">Todos</option>';
    arr.forEach(item => {
        const o = document.createElement('option');
        o.value = item.id_producto;
        o.textContent = `${item.codigo_producto} — ${item.descripcion_completa}`;
        sel.appendChild(o);
    });
}

function filtrarProductosPorFamilia() {
    const idFam = document.getElementById('filtroFamilia').value;
    if (!idFam) {
        popularComboProductos(window.allProductos);
    } else {
        const filtrados = window.allProductos.filter(p => String(p.id_linea) === String(idFam));
        popularComboProductos(filtrados);
    }
}

function populateSelect(id, arr, valKey, lblKey) {
    const sel = document.getElementById(id);
    arr.forEach(item => {
        const o = document.createElement('option');
        o.value = item[valKey];
        o.textContent = typeof lblKey === 'function' ? lblKey(item) : item[lblKey];
        sel.appendChild(o);
    });
}

// ─── Consultar ────────────────────────────────────────────────────────────────
async function consultarResumen() {
    const fi = document.getElementById('filtroFechaInicio').value;
    const ff = document.getElementById('filtroFechaFin').value;

    if (!fi || !ff)  { toast('Selecciona fecha inicio y fecha fin.', 'warn'); return; }
    if (fi > ff)     { toast('La fecha inicio no puede ser mayor a la fecha fin.', 'warn'); return; }

    const turno = document.getElementById('filtroTurno').value;
    const prod  = document.getElementById('filtroProducto').value;
    const familia = document.getElementById('filtroFamilia').value;
    const btn   = document.getElementById('btnConsultar');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando…';

    // Ocultar todo
    document.getElementById('estadoInicial').style.display = 'none';
    document.getElementById('estadoVacio').style.display   = 'none';
    document.getElementById('secKpis').style.display       = 'none';
    document.getElementById('secTabs').style.display       = 'none';
    document.getElementById('btnExport').style.display     = 'none';

    try {
        const params = new URLSearchParams({
            action: 'get_resumen_produccion_tejido',
            fecha_inicio: fi,
            fecha_fin: ff,
        });
        if (turno) params.append('id_turno', turno);
        if (prod)  params.append('id_producto', prod);
        if (familia) params.append('id_familia', familia);

        const res  = await fetch(`${API}?${params}`);
        const data = await res.json();

        if (!data.success) throw new Error(data.message || 'Error desconocido');

        dataGlobal = data;

        const sinDatos = (!data.por_producto?.length && !data.por_dia?.length);
        if (sinDatos) {
            document.getElementById('estadoVacio').style.display = 'block';
            return;
        }

        renderKpis(data.totales, data.metricas);
        renderTabProducto(data.por_producto, data.totales, fi, ff);
        renderTabDia(data.por_dia, data.totales);
        renderTabTurno(data.por_turno, data.totales);

        // Counts en tabs
        document.getElementById('cntProducto').textContent = data.por_producto?.length || 0;
        document.getElementById('cntDia').textContent      = data.por_dia?.length || 0;
        document.getElementById('cntTurno').textContent    = data.por_turno?.length || 0;
        document.getElementById('labelRangoProd').textContent = `${fi} → ${ff}`;

        document.getElementById('secKpis').style.display   = 'block';
        document.getElementById('secTabs').style.display   = 'block';
        document.getElementById('btnExport').style.display = 'flex';

        cambiarTab(tabActiva);

    } catch(e) {
        toast('Error: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-search"></i> Consultar';
    }
}

// ─── KPIs ────────────────────────────────────────────────────────────────────
function renderKpis(tot, met) {
    ctr('kpiProductos', tot.productos_distintos);
    ctr('kpiDocenas',   tot.docenas);
    ctr('kpiUnidades',  tot.unidades);
    ctr('kpiMaquinas',  tot.maquinas);
    ctr('kpiRegistros', tot.registros);
    ctr('kpiDias',      tot.dias_con_produccion);

    // Insights
    document.getElementById('insPromDoc').textContent   =
        `${fmtNum(met.promedio_diario_docenas)} doc`;
    document.getElementById('insPromBase').textContent  =
        `≈ ${fmtNum(met.promedio_diario_base)} unid. base / día`;

    document.getElementById('insMejorDia').textContent    = met.mejor_dia || '—';
    document.getElementById('insMejorDiaBase').textContent =
        met.mejor_dia ? `${fmtNum(met.mejor_dia_docenas)} doc ese día` : '';

    document.getElementById('insTurnoTop').textContent    = met.turno_top || '—';
    document.getElementById('insTurnoTopBase').textContent =
        met.turno_top ? `${fmtNum(met.turno_top_docenas)} doc en total` : '';
}

function ctr(id, valorFin) {
    const el = document.getElementById(id);
    let v = 0;
    const step = Math.ceil(valorFin / 25);
    const iv = setInterval(() => {
        v = Math.min(v + step, valorFin);
        el.textContent = fmtNum(v);
        if (v >= valorFin) clearInterval(iv);
    }, 25);
}

// ─── Tab: Por Producto ────────────────────────────────────────────────────────
function renderTabProducto(filas, totales, fi, ff) {
    const tb = document.getElementById('tbProducto');
    const tf = document.getElementById('tfProducto');

    if (!filas.length) {
        tb.innerHTML = '<tr><td colspan="10" class="tc" style="padding:30px;color:#94a3b8;">Sin datos</td></tr>';
        tf.style.display = 'none';
        document.getElementById('infoProd').textContent = '';
        return;
    }

    tb.innerHTML = filas.map((r, i) => `
        <tr>
            <td><span class="sub">${i+1}</span></td>
            <td><span class="cod">${esc(r.codigo)}</span></td>
            <td><strong style="color:#1e293b;">${esc(r.producto)}</strong></td>
            <td class="tr"><span class="num">${fmtNum(r.docenas)}</span></td>
            <td class="tr"><span class="num">${fmtNum(r.unidades)}</span></td>
            <td class="tr dim">${fmtNum(r.total_base_unidades)}</td>
            <td class="tc"><span class="pill-green">${r.total_registros}</span></td>
            <td class="tc"><span class="pill-blue">${r.total_maquinas}</span></td>
            <td class="tc"><span class="pill-purple">${r.total_turnos}</span></td>
            <td><span class="dt">${r.ultima_fecha || '—'}</span></td>
        </tr>
    `).join('');

    tf.style.display = 'table-footer-group';
    tf.innerHTML = `<tr>
        <td colspan="3"><strong>TOTALES GLOBALES</strong></td>
        <td class="tr"><strong>${fmtNum(totales.docenas)}</strong></td>
        <td class="tr"><strong>${fmtNum(totales.unidades)}</strong></td>
        <td class="tr dim"><strong>${fmtNum(totales.base_unidades)}</strong></td>
        <td class="tc"><strong>${fmtNum(totales.registros)}</strong></td>
        <td class="tc"><strong>${fmtNum(totales.maquinas)}</strong></td>
        <td class="tc">—</td><td>—</td>
    </tr>`;

    document.getElementById('infoProd').textContent = `${filas.length} producto${filas.length!==1?'s':''} · ${fi} al ${ff}`;
}

// ─── Tab: Por Día ────────────────────────────────────────────────────────────
function renderTabDia(filas, totales) {
    const tb  = document.getElementById('tbDia');
    const tf  = document.getElementById('tfDia');
    const inf = document.getElementById('infoDia');

    if (!filas.length) {
        tb.innerHTML = '<tr><td colspan="10" class="tc" style="padding:30px;color:#94a3b8;">Sin datos</td></tr>';
        tf.style.display = 'none';
        inf.textContent = '';
        drawChart([]);
        return;
    }

    const maxBase = Math.max(...filas.map(r => r.total_base_unidades));

    tb.innerHTML = filas.map((r, i) => {
        const pct = totales.base_unidades > 0 ? (r.total_base_unidades / totales.base_unidades * 100).toFixed(1) : 0;
        const esMejor = r.fecha === dataGlobal.metricas?.mejor_dia;
        return `<tr${esMejor ? ' style="background:#fffbeb;"' : ''}>
            <td><span class="sub">${i+1}</span></td>
            <td><span class="dt" style="font-size:13px;font-weight:${esMejor?'700':'400'};color:${esMejor?'#92400e':'#475569'};">
                ${r.fecha}${esMejor?' <span class="pill-gold" style="font-size:9px;">🏆 Mejor</span>':''}
            </span></td>
            <td class="tr"><span class="num">${fmtNum(r.docenas)}</span></td>
            <td class="tr"><span class="num">${fmtNum(r.unidades)}</span></td>
            <td class="tr dim">${fmtNum(r.total_base_unidades)}</td>
            <td class="tc"><span class="pill-green">${r.total_registros}</span></td>
            <td class="tc"><span class="pill-purple">${r.productos_distintos}</span></td>
            <td class="tc"><span class="pill-blue">${r.total_maquinas}</span></td>
            <td class="tc">${r.total_turnos}</td>
            <td class="tc">
                <div style="display:flex;align-items:center;gap:6px;min-width:80px;">
                    <div class="part-bar" style="flex:1;"><div class="part-fill" style="width:${pct}%;"></div></div>
                    <span style="font-size:11px;color:#64748b;font-weight:600;min-width:32px;">${pct}%</span>
                </div>
            </td>
        </tr>`;
    }).join('');

    tf.style.display = 'table-footer-group';
    tf.innerHTML = `<tr>
        <td colspan="2"><strong>TOTALES</strong></td>
        <td class="tr"><strong>${fmtNum(totales.docenas)}</strong></td>
        <td class="tr"><strong>${fmtNum(totales.unidades)}</strong></td>
        <td class="tr dim"><strong>${fmtNum(totales.base_unidades)}</strong></td>
        <td class="tc"><strong>${fmtNum(totales.registros)}</strong></td>
        <td class="tc">—</td><td class="tc">—</td><td class="tc">—</td><td class="tc">100%</td>
    </tr>`;

    inf.textContent = `${filas.length} día${filas.length!==1?'s':''} con producción`;
    drawChart(filas);
}

// Mini bar chart
function drawChart(filas) {
    const cont = document.getElementById('chartDia');
    if (!filas.length) { cont.innerHTML = ''; return; }
    const maxB = Math.max(...filas.map(r => r.total_base_unidades));
    const H = 60;
    cont.innerHTML = filas.map(r => {
        const h = maxB > 0 ? Math.max(4, Math.round((r.total_base_unidades / maxB) * H)) : 4;
        const esMejor = r.fecha === dataGlobal.metricas?.mejor_dia;
        return `<div class="bar-wrap">
            <span class="bar-cnt">${r.docenas}</span>
            <div class="bar" style="height:${h}px;${esMejor?'background:linear-gradient(180deg,#f59e0b,#fbbf24);':''}
                " data-tip="${r.fecha}: ${fmtNum(r.total_base_unidades)} uni."></div>
            <span class="bar-lbl">${r.fecha.slice(5)}</span>
        </div>`;
    }).join('');
}

// ─── Tab: Por Turno ────────────────────────────────────────────────────────────
function renderTabTurno(filas, totales) {
    const tb  = document.getElementById('tbTurno');
    const tf  = document.getElementById('tfTurno');
    const inf = document.getElementById('infoTurno');

    if (!filas.length) {
        tb.innerHTML = '<tr><td colspan="11" class="tc" style="padding:30px;color:#94a3b8;">Sin datos</td></tr>';
        tf.style.display = 'none';
        inf.textContent = '';
        return;
    }

    tb.innerHTML = filas.map((r, i) => {
        const pct = totales.base_unidades > 0 ? (r.total_base_unidades / totales.base_unidades * 100).toFixed(1) : 0;
        const esTop = r.turno_nombre === dataGlobal.metricas?.turno_top;
        return `<tr${esTop ? ' style="background:#f5f3ff;"' : ''}>
            <td><span class="sub">${i+1}</span></td>
            <td><strong style="color:${esTop?'#6d28d9':'#1e293b'};">${esc(r.turno_nombre)}</strong>
                ${esTop ? '<span class="pill-purple" style="font-size:9px;margin-left:4px;">⭐ Top</span>' : ''}
            </td>
            <td class="tr"><span class="num">${fmtNum(r.docenas)}</span></td>
            <td class="tr"><span class="num">${fmtNum(r.unidades)}</span></td>
            <td class="tr dim">${fmtNum(r.total_base_unidades)}</td>
            <td class="tc"><span class="pill-green">${r.total_registros}</span></td>
            <td class="tc"><span class="pill-purple">${r.productos_distintos}</span></td>
            <td class="tc"><span class="pill-blue">${r.total_maquinas}</span></td>
            <td><span class="dt">${r.primera_fecha || '—'}</span></td>
            <td><span class="dt">${r.ultima_fecha || '—'}</span></td>
            <td class="tc">
                <div style="display:flex;align-items:center;gap:6px;min-width:80px;">
                    <div class="part-bar" style="flex:1;"><div class="part-fill" style="width:${pct}%;background:linear-gradient(90deg,#7c3aed,#a78bfa);"></div></div>
                    <span style="font-size:11px;color:#64748b;font-weight:600;min-width:32px;">${pct}%</span>
                </div>
            </td>
        </tr>`;
    }).join('');

    tf.style.display = 'table-footer-group';
    tf.innerHTML = `<tr>
        <td colspan="2"><strong>TOTALES</strong></td>
        <td class="tr"><strong>${fmtNum(totales.docenas)}</strong></td>
        <td class="tr"><strong>${fmtNum(totales.unidades)}</strong></td>
        <td class="tr dim"><strong>${fmtNum(totales.base_unidades)}</strong></td>
        <td class="tc"><strong>${fmtNum(totales.registros)}</strong></td>
        <td class="tc">—</td>
        <td class="tc"><strong>${fmtNum(totales.maquinas)}</strong></td>
        <td colspan="2">—</td><td class="tc">100%</td>
    </tr>`;

    inf.textContent = `${filas.length} turno${filas.length!==1?'s':''} activos`;
}

// ─── Tabs switch ─────────────────────────────────────────────────────────────
function cambiarTab(tab) {
    tabActiva = tab;
    ['producto','dia','turno'].forEach(t => {
        document.getElementById('tab-' + t).classList.toggle('active', t === tab);
        document.getElementById('panel-' + t).style.display = t === tab ? 'block' : 'none';
    });
    if (tab === 'dia') drawChart(dataGlobal.por_dia || []);
}

// ─── Limpiar ─────────────────────────────────────────────────────────────────
function limpiarFiltros() {
    setFechasDefecto();
    document.getElementById('filtroTurno').value   = '';
    document.getElementById('filtroFamilia').value = '';
    document.getElementById('filtroProducto').value = '';
    filtrarProductosPorFamilia();
    document.getElementById('estadoInicial').style.display = 'block';
    document.getElementById('estadoVacio').style.display   = 'none';
    document.getElementById('secKpis').style.display       = 'none';
    document.getElementById('secTabs').style.display       = 'none';
    document.getElementById('btnExport').style.display     = 'none';
    dataGlobal = {};
}

// ─── Export CSV ──────────────────────────────────────────────────────────────
function exportarVista() {
    const fi = document.getElementById('filtroFechaInicio').value;
    const ff = document.getElementById('filtroFechaFin').value;
    let csv = '';

    // Hoja 1: por producto
    csv += 'RESUMEN POR PRODUCTO\r\n';
    csv += '"Codigo","Producto","Docenas","Unidades","Base Uni","Registros","Maquinas","Turnos","Ultima fecha"\r\n';
    (dataGlobal.por_producto || []).forEach(r => {
        csv += [r.codigo, r.producto, r.docenas, r.unidades, r.total_base_unidades,
            r.total_registros, r.total_maquinas, r.total_turnos, r.ultima_fecha]
            .map(c => `"${String(c||'').replace(/"/g,'""')}"`).join(',') + '\r\n';
    });

    csv += '\r\nRESUMEN POR DÍA\r\n';
    csv += '"Fecha","Docenas","Unidades","Base Uni","Registros","Productos","Maquinas","Turnos"\r\n';
    (dataGlobal.por_dia || []).forEach(r => {
        csv += [r.fecha, r.docenas, r.unidades, r.total_base_unidades,
            r.total_registros, r.productos_distintos, r.total_maquinas, r.total_turnos]
            .map(c => `"${String(c||'').replace(/"/g,'""')}"`).join(',') + '\r\n';
    });

    csv += '\r\nRESUMEN POR TURNO\r\n';
    csv += '"Turno","Docenas","Unidades","Base Uni","Registros","Productos","Maquinas","Primera fecha","Ultima fecha"\r\n';
    (dataGlobal.por_turno || []).forEach(r => {
        csv += [r.turno_nombre, r.docenas, r.unidades, r.total_base_unidades,
            r.total_registros, r.productos_distintos, r.total_maquinas, r.primera_fecha, r.ultima_fecha]
            .map(c => `"${String(c||'').replace(/"/g,'""')}"`).join(',') + '\r\n';
    });

    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `resumen_produccion_tejido_${fi}_${ff}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

// ─── Toast ───────────────────────────────────────────────────────────────────
function toast(msg, tipo) {
    const paleta = {
        warn:  { bg:'#fffbeb', bc:'#f59e0b', tc:'#78350f', ico:'fa-exclamation-triangle' },
        error: { bg:'#fef2f2', bc:'#ef4444', tc:'#7f1d1d', ico:'fa-times-circle' },
        ok:    { bg:'#f0fdf4', bc:'#22c55e', tc:'#14532d', ico:'fa-check-circle' },
    };
    const p = paleta[tipo] || paleta.error;
    const d = document.createElement('div');
    d.className = 'toast';
    d.style.cssText = `background:${p.bg};border:1.5px solid ${p.bc};color:${p.tc};`;
    d.innerHTML = `<i class="fas ${p.ico}"></i> ${msg}`;
    document.body.appendChild(d);
    setTimeout(() => d.remove(), 4000);
}
</script>

<?php require_once '../../includes/footer.php'; ?>
