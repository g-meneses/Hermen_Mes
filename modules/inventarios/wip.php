<?php
/**
 * Módulo de Inventarios - Productos en Proceso (WIP)
 * Sistema MES Hermen Ltda. v1.0
 */
require_once '../../config/database.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Productos en Proceso (WIP)';
$currentPage = 'inventarios_wip';

require_once '../../includes/header.php';
require_once '../../includes/permisos_inventario.php';

// Base Url path for API calls
$baseUrl = ''; // Derived in frontend or set via JS
?>

<!-- Import Premium Font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root {
        --tipo-color: #17a2b8; /* Cyan para WIP */
    }

    body {
        font-family: 'Inter', sans-serif;
        background-color: #f4f6f9;
        margin: 0;
    }

    .mp-module {
        padding: 20px;
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
        font-weight: 700;
    }

    .mp-title p {
        font-size: 0.85rem;
        color: #6c757d;
        margin: 0;
    }

    /* KPIs */
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
        transition: transform 0.2s;
    }
    
    .kpi-card:hover {
        transform: translateY(-5px);
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

    .kpi-icon.items { background: linear-gradient(135deg, #17a2b8, #30cce0); }
    .kpi-icon.valor { background: linear-gradient(135deg, #11998e, #38ef7d); }
    .kpi-icon.docenas { background: linear-gradient(135deg, #f2994a, #f2c94c); }
    .kpi-icon.unidades { background: linear-gradient(135deg, #e67e22, #f39c12); }
    .kpi-icon.alertas { background: linear-gradient(135deg, #eb3349, #f45c43); }

    .kpi-label {
        font-size: 0.8rem;
        color: #6c757d;
        text-transform: uppercase;
        font-weight: 600;
    }

    .kpi-value {
        font-size: 1.4rem;
        font-weight: 700;
        color: #1a1a2e;
        margin-top: 5px;
    }

    .kpi-value.danger { color: #dc3545; }

    /* Áreas Flow Grid */
    .categorias-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .categoria-card {
        background: white;
        border-radius: 12px;
        padding: 18px;
        transition: all 0.2s;
        border: 2px solid transparent;
        border-top: 3px solid var(--tipo-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    .categoria-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    }

    .categoria-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 12px;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }

    .categoria-nombre {
        font-weight: 700;
        color: #1a1a2e;
        font-size: 1.1rem;
    }

    .categoria-badge {
        background: var(--tipo-color);
        color: white;
        font-size: 0.8rem;
        padding: 3px 10px;
        border-radius: 12px;
        font-weight: bold;
    }

    .categoria-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        text-align: center;
    }

    .cat-stat-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: #333;
    }

    .cat-stat-value.alerta { color: #dc3545; }

    .cat-stat-label {
        font-size: 0.7rem;
        color: #6c757d;
        text-transform: uppercase;
    }

    /* Tablas */
    .mp-productos {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }

    .productos-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid #e9ecef;
        background: #fafbfe;
    }

    .productos-header h3 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: #1a1a2e;
    }

    .table-responsive {
        overflow-x: auto;
    }

    .productos-table {
        width: 100%;
        border-collapse: collapse;
    }

    .productos-table th {
        background: #2c3e50;
        color: white;
        padding: 12px 15px;
        text-align: left;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        position: sticky;
        top: 0;
    }

    .productos-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #f1f1f1;
        font-size: 0.9rem;
        color: #495057;
        vertical-align: middle;
    }

    .productos-table tr:hover { background: #f8f9fa; }

    /* Badges */
    .badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge.estado-activo { background: #d4edda; color: #155724; }
    .badge.estado-pausado { background: #fff3cd; color: #856404; }
    .badge.estado-alerta { background: #f8d7da; color: #721c24; }
    .badge.estado-inactivo { background: #e2e3e5; color: #383d41; }
    
    .badge.area {
        background: #e7f3ff;
        color: #0d6efd;
        border: 1px solid #b6d4fe;
    }

    /* Movimientos */
    .movimientos-container {
        margin-top: 30px;
    }

    .movimiento-item {
        padding: 12px 15px;
        border-left: 3px solid var(--tipo-color);
        background: white;
        margin-bottom: 10px;
        border-radius: 0 8px 8px 0;
        box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .movimiento-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .mov-lote { font-weight: bold; color: #1a1a2e; }
    .mov-tipo { font-size: 0.8rem; color: #6c757d; }
    .mov-fecha { font-size: 0.75rem; color: #adb5bd; }
    .mov-cants { font-weight: 600; color: #198754; }

    .loading-spinner {
        padding: 40px;
        text-align: center;
        color: #6c757d;
    }
</style>

<div class="mp-module">
    <!-- Encabezado Principal -->
    <div class="mp-header">
        <div class="mp-header-left">
            <a href="index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver a Inventarios
            </a>
            <div class="mp-title">
                <div class="mp-title-icon">
                    <i class="fas fa-cogs"></i>
                </div>
                <div>
                    <h1>Productos en Proceso (WIP)</h1>
                    <p>Monitoreo de Lotes, Trazabilidad y Estado Operativo</p>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs -->
    <div class="mp-kpis">
        <div class="kpi-card">
            <div class="kpi-icon items"><i class="fas fa-layer-group"></i></div>
            <div>
                <div class="kpi-label">Lotes Activos</div>
                <div class="kpi-value" id="kpiActivos">0</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon valor"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <div class="kpi-label">Costo MP Acumulado</div>
                <div class="kpi-value" id="kpiValor">Bs. 0</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon docenas"><i class="fas fa-th"></i></div>
            <div>
                <div class="kpi-label">Docenas en Proceso</div>
                <div class="kpi-value" id="kpiDocenas">0</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon unidades"><i class="fas fa-tshirt"></i></div>
            <div>
                <div class="kpi-label">Unidades en Proceso</div>
                <div class="kpi-value" id="kpiUnidades">0</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon alertas"><i class="fas fa-exclamation-triangle"></i></div>
            <div>
                <div class="kpi-label">Alertas WIP</div>
                <div class="kpi-value danger" id="kpiAlertas">0</div>
            </div>
        </div>
    </div>

    <!-- Flujo de Áreas -->
    <h4 class="mb-3 font-weight-bold" style="color: #1a1a2e;"><i class="fas fa-project-diagram mr-2"></i> Lotes por Etapa productiva</h4>
    <div class="categorias-grid" id="areasGrid">
        <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Cargando áreas...</div>
    </div>

    <!-- Tabla Detallada -->
    <div class="mp-productos mb-4">
        <div class="productos-header">
            <h3><i class="fas fa-list mr-2"></i> Listado de Lotes Activos</h3>
            <div class="input-group" style="width: 250px;">
                <input type="text" id="filtroTabla" class="form-control form-control-sm" placeholder="Buscar lote o producto..." oninput="filtrarLotes()">
            </div>
        </div>
        <div class="table-responsive">
            <table class="productos-table" id="tablaLotes">
                <thead>
                    <tr>
                        <th>Código Lote</th>
                        <th>Producto</th>
                        <th>Área Actual</th>
                        <th>Docenas</th>
                        <th>Unidades</th>
                        <th>Costo Acumulado</th>
                        <th>Días Activo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="lotesBody">
                    <tr><td colspan="8" class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Cargando lotes...</td></tr>
                </tbody>
            </table>
        </div>
    </div>


</div>

<script>
    const sysBaseUrl = window.location.origin + '/mes_hermen';
    let lotesData = [];

    document.addEventListener('DOMContentLoaded', () => {
        cargarKpis();
        cargarAreas();
        cargarLotes();
    });

    async function cargarKpis() {
        try {
            const res = await fetch(`${sysBaseUrl}/api/wip_inventario.php?action=resumen`);
            const data = await res.json();
            if (data.success) {
                document.getElementById('kpiActivos').textContent = data.totales.activos;
                document.getElementById('kpiValor').textContent = 'Bs. ' + parseFloat(data.totales.valor).toLocaleString('es-BO', { minimumFractionDigits: 2 });
                document.getElementById('kpiDocenas').textContent = data.totales.docenas.toLocaleString();
                document.getElementById('kpiUnidades').textContent = data.totales.unidades.toLocaleString();
                document.getElementById('kpiAlertas').textContent = data.totales.alertas;
            }
        } catch (e) {
            console.error('Error cargando KPIs:', e);
        }
    }

    async function cargarAreas() {
        try {
            const res = await fetch(`${sysBaseUrl}/api/wip_inventario.php?action=areas`);
            const data = await res.json();
            if (data.success) {
                const grid = document.getElementById('areasGrid');
                if(data.areas.length === 0){
                    grid.innerHTML = '<div class="alert alert-info">No hay lotes en ninguna etapa.</div>';
                    return;
                }
                
                grid.innerHTML = data.areas.map(a => `
                    <div class="categoria-card">
                        <div class="categoria-header">
                            <div class="categoria-nombre">${a.nombre}</div>
                            <span class="categoria-badge">${a.lotes} lotes</span>
                        </div>
                        <div class="categoria-stats" style="grid-template-columns: repeat(4, 1fr);">
                            <div>
                                <div class="cat-stat-value">${a.docenas}</div>
                                <div class="cat-stat-label">Doc</div>
                            </div>
                            <div>
                                <div class="cat-stat-value">${a.unidades}</div>
                                <div class="cat-stat-label">Uni</div>
                            </div>
                            <div>
                                <div class="cat-stat-value">Bs. ${Math.round(a.valor).toLocaleString('es-BO')}</div>
                                <div class="cat-stat-label">Valor</div>
                            </div>
                            <div>
                                <div class="cat-stat-value ${a.alertas > 0 ? 'alerta' : ''}">${a.alertas}</div>
                                <div class="cat-stat-label">Alertas</div>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
        } catch(e) {
            console.error('Error cargando areas:', e);
        }
    }

    async function cargarLotes() {
        try {
            const res = await fetch(`${sysBaseUrl}/api/wip_inventario.php?action=lotes`);
            const data = await res.json();
            if (data.success) {
                lotesData = data.lotes;
                renderLotes(lotesData);
            }
        } catch(e) {
            console.error('Error cargando lotes:', e);
        }
    }

    function renderLotes(lista) {
        const tbody = document.getElementById('lotesBody');
        if (lista.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No hay lotes en WIP.</td></tr>';
            return;
        }

        tbody.innerHTML = lista.map(l => {
            // Badges
            let badgeClass = 'estado-activo';
            if (l.estado_alerta === 'PAUSADO') badgeClass = 'estado-pausado';
            else if (l.estado_alerta === 'OBSERVADO' || l.estado_alerta === 'PARCIAL') badgeClass = 'estado-alerta';
            else if (l.estado_alerta === 'INACTIVO') badgeClass = 'estado-inactivo';

            return `
            <tr>
                <td class="font-weight-bold">${l.codigo_lote}</td>
                <td>${l.producto || l.referencia_externa || 'N/A'}</td>
                <td><span class="badge area">${l.area || 'Sin Área'}</span></td>
                <td>${l.docenas}</td>
                <td>${l.unidades}</td>
                <td class="text-right">Bs. ${parseFloat(l.costo).toLocaleString('es-BO', {minimumFractionDigits:2})}</td>
                <td>${l.dias_proceso} días</td>
                <td><span class="badge ${badgeClass}">${l.estado_alerta}</span></td>
            </tr>
            `;
        }).join('');
    }

    function filtrarLotes() {
        const q = document.getElementById('filtroTabla').value.toLowerCase();
        const filtrados = lotesData.filter(l => 
            l.codigo_lote.toLowerCase().includes(q) || 
            (l.producto && l.producto.toLowerCase().includes(q)) ||
            (l.area && l.area.toLowerCase().includes(q))
        );
        renderLotes(filtrados);
    }


</script>

<?php require_once '../../includes/footer.php'; ?>
