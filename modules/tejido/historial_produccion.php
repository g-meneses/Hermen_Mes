<?php
require_once '../../config/database.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Historial de Producción de Tejido';
$currentPage = 'historial_produccion_tejido';
require_once '../../includes/header.php';
?>

<div class="historial-container">
    <header class="page-header">
        <div class="header-content">
            <h1><i class="fas fa-book"></i> Historial de Producción de Tejido</h1>
            <p>Bitácora industrial y cuaderno de control de registros de tejeduría.</p>
        </div>
        <div class="header-actions">
            <button class="btn-refresh" onclick="cargarHistorial()"><i class="fas fa-sync-alt"></i> Actualizar</button>
        </div>
    </header>

    <div class="filter-bar glassmorphism">
        <div class="filter-group">
            <label>Rango de Fechas</label>
            <div class="date-inputs">
                <input type="date" id="filtroFechaDesde" class="form-control">
                <span>al</span>
                <input type="date" id="filtroFechaHasta" class="form-control">
            </div>
        </div>
        <div class="filter-group">
            <label>Turno</label>
            <select id="filtroTurno" class="form-control">
                <option value="">Todos los turnos</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Responsable</label>
            <select id="filtroResponsable" class="form-control">
                <option value="">Cualquier responsable</option>
            </select>
        </div>
        <div class="filter-group grow">
            <label>Búsqueda</label>
            <div class="search-input">
                <i class="fas fa-search"></i>
                <input type="text" id="filtroBusqueda" placeholder="Planilla u observaciones..." onkeyup="if(event.key==='Enter') cargarHistorial()">
            </div>
        </div>
        <div class="filter-actions">
            <button class="btn-primary" onclick="cargarHistorial()">Aplicar Filtros</button>
            <button class="btn-secondary" onclick="limpiarFiltros()">Limpiar</button>
        </div>
    </div>

    <div class="grid-card">
        <div class="table-responsive">
            <table class="industrial-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Turno</th>
                        <th>Responsable</th>
                        <th># Planilla</th>
                        <th>Máquinas</th>
                        <th>Total Doz</th>
                        <th>Total Und</th>
                        <th>Incidencias</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbodyHistorial">
                    <tr><td colspan="10" class="text-center">Cargando bitácora...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="pagination-container" id="pagination">
            <!-- Paginación dinámica -->
        </div>
    </div>
</div>

<!-- Modal Detalle de Planilla -->
<div id="modalDetalle" class="modal-mes">
    <div class="modal-content-mes logbook-style">
        <header class="modal-header">
            <div class="header-title">
                <h2><i class="fas fa-clipboard-list"></i> Detalle de Planilla de Producción</h2>
                <span id="labelNumeroPlanilla" class="planilla-badge"># ---</span>
            </div>
            <button class="close-btn" onclick="cerrarModalDetail()">&times;</button>
        </header>
        
        <div class="modal-body">
            <!-- Bloque A: Cabecera -->
            <section class="detail-section head-section">
                <div class="section-title">Información de Cabecera</div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Fecha</span>
                        <span class="value" id="detFecha">---</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Turno</span>
                        <span class="value" id="detTurno">---</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Responsable</span>
                        <span class="value" id="detResponsable">---</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Estado</span>
                        <span class="value" id="detEstado">---</span>
                    </div>
                    <div class="info-item full">
                        <span class="label">Observaciones</span>
                        <span class="value" id="detObservaciones">---</span>
                    </div>
                    <div class="info-item half">
                        <span class="label">Registrado por</span>
                        <span class="value" id="detUsuario">---</span>
                    </div>
                    <div class="info-item half">
                        <span class="label">Fecha/Hora Registro</span>
                        <span class="value" id="detFechaRegistro">---</span>
                    </div>
                </div>
            </section>

            <!-- Bloque B: Resumen visual -->
            <section class="detail-section summary-section">
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-value" id="kpiDoz">0</div>
                        <div class="kpi-label">Docenas Totales</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value" id="kpiUnd">0</div>
                        <div class="kpi-label">Unidades Totales</div>
                    </div>
                    <div class="kpi-card success">
                        <div class="kpi-value" id="kpiMaq">0</div>
                        <div class="kpi-label">Máquinas Activas</div>
                    </div>
                    <div class="kpi-card danger">
                        <div class="kpi-value" id="kpiInc">0</div>
                        <div class="kpi-label">Incidencias</div>
                    </div>
                    <div class="kpi-card warning">
                        <div class="kpi-value" id="kpiLot">0</div>
                        <div class="kpi-label">Lotes WIP</div>
                    </div>
                </div>
            </section>

            <!-- Bloque C: Producción por máquina -->
            <section class="detail-section">
                <div class="section-title">Producción Detallada por Máquina</div>
                <div class="table-container small">
                    <table class="compact-table">
                        <thead>
                            <tr>
                                <th>Máquina</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Observaciones</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyMaquinas">
                            <!-- Dinámico -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Bloque D: Incidencias -->
            <section class="detail-section" id="secIncidencias">
                <div class="section-title">Incidencias de Materia Prima y Stock (FIFO)</div>
                <div class="table-container small">
                    <table class="compact-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Requerido</th>
                                <th>Faltante</th>
                                <th>Estado</th>
                                <th>Resolución</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyIncidencias">
                            <!-- Dinámico -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Bloque G: Análisis de Consumo (NUEVO) -->
            <section class="detail-section" id="secAnalisisConsumo">
                <div class="section-title">Análisis Comparativo de Consumo (BOM vs Real)</div>
                <div class="table-container small">
                    <table class="compact-table">
                        <thead>
                            <tr>
                                <th>Componente</th>
                                <th>Real (Kg)</th>
                                <th>Teórico (Kg)</th>
                                <th>Pendiente (Kg)</th>
                                <th>Diferencia</th>
                                <th>%</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyAnalisisConsumo">
                            <!-- Dinámico -->
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Bloque E: Lotes WIP -->
            <section class="detail-section">
                <div class="section-title">Lotes WIP Generados</div>
                <div class="lotes-grid" id="containerLotes">
                    <!-- Tarjetas de lotes -->
                </div>
            </section>

            <!-- Bloque F: Trazabilidad posterior -->
            <section class="detail-section">
                <div class="section-title">Recorrido y Trazabilidad Posterior</div>
                <div class="timeline" id="timelineMovimientos">
                    <!-- Timeline dinámico -->
                </div>
            </section>
        </div>
    </div>
</div>

<!-- Modal Historial del Lote (Reutilizado de wip_fase0) -->
<div id="modalHistorialLote" class="modal-mes">
    <div class="modal-content-mes">
        <header class="modal-header">
            <h2>Trazabilidad Detallada del Lote</h2>
            <button class="close-btn" onclick="cerrarModalLote()">&times;</button>
        </header>
        <div class="modal-body" id="bodyHistorialLote">
            <!-- Se carga contenido dinámico de trazabilidad -->
        </div>
    </div>
</div>

<style>
/* Estilos Cuaderno Industrial */
.historial-container { padding: 20px; max-width: 1400px; margin: 0 auto; }
.page-header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
.page-header h1 { font-size: 24px; margin: 0; color: #1e293b; display:flex; align-items:center; gap:12px; }
.page-header h1 i { color: #2563eb; }
.page-header p { margin: 4px 0 0; color: #64748b; }

.filter-bar { padding: 18px 22px; margin-bottom: 24px; border-radius: 16px; display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap; }
.filter-group { display: flex; flex-direction: column; gap: 8px; }
.filter-group label { font-size: 13px; font-weight: 600; color: #475569; }
.filter-group.grow { flex: 1; min-width: 250px; }
.date-inputs { display: flex; align-items: center; gap: 10px; }
.form-control { padding: 10px 14px; border-radius: 10px; border: 1px solid #e2e8f0; font-size: 14px; }
.search-input { position: relative; }
.search-input i { position: absolute; left: 14px; top: 12px; color: #94a3b8; }
.search-input input { width: 100%; padding: 10px 10px 10px 40px; border-radius: 10px; border: 1px solid #e2e8f0; }

.grid-card { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; }
.industrial-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.industrial-table th { background: #f8fafc; padding: 14px 16px; font-size: 13px; font-weight: 700; color: #64748b; border-bottom: 2px solid #e2e8f0; text-align: left; }
.industrial-table td { padding: 14px 16px; font-size: 14px; border-bottom: 1px solid #f1f5f9; color: #334155; }
.industrial-table tr:hover { background: #f8fafc; }

.text-center { text-align: center; }
.badge { padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.badge-primary { background: #e0f2fe; color: #0369a1; }
.badge-warning { background: #ffedd5; color: #9a3412; }
.badge-danger { background: #fee2e2; color: #991b1b; }
.badge-success { background: #dcfce7; color: #166534; }

.btn-primary { background: #2563eb; color: #fff; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: 0.2s; }
.btn-primary:hover { background: #1d4ed8; }
.btn-secondary { background: #f1f5f9; color: #475569; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; }

/* Modales */
.modal-mes { display: none; position: fixed; top: 0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
.modal-mes.show { display: flex; }
.modal-content-mes { background: #fff; width: 95%; max-width: 1100px; max-height: 90vh; border-radius: 20px; overflow: hidden; display: flex; flex-direction: column; }
.modal-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
.modal-header h2 { margin: 0; font-size: 18px; display: flex; align-items: center; gap: 10px; }
.planilla-badge { background: #1e293b; color:#fff; padding: 4px 12px; border-radius: 8px; font-family: monospace; font-size: 16px; margin-left: 10px; }
.modal-body { padding: 24px; overflow-y: auto; }

.detail-section { margin-bottom: 30px; }
.section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9; }

.info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
.info-item { display: flex; flex-direction: column; }
.info-item .label { font-size: 12px; color: #94a3b8; }
.info-item .value { font-weight: 600; color: #1e293b; }
.info-item.full { grid-column: 1 / -1; }
.info-item.half { grid-column: span 2; }

.kpi-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; }
.kpi-card { background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid #f1f5f9; text-align: center; }
.kpi-card .kpi-value { font-size: 24px; font-weight: 700; color: #2563eb; }
.kpi-card .kpi-label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; margin-top: 4px; }
.kpi-card.success .kpi-value { color: #10b981; }
.kpi-card.danger .kpi-value { color: #ef4444; }
.kpi-card.warning .kpi-value { color: #f59e0b; }

.compact-table { width: 100%; border-collapse: collapse; }
.compact-table th { background: #f1f5f9; padding: 10px; font-size: 12px; text-align: left; }
.compact-table td { padding: 10px; font-size: 13px; border-bottom: 1px solid #f1f5f9; }

.lotes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
.lote-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 8px; }
.lote-card .lote-header { display: flex; justify-content: space-between; align-items: flex-start; }
.lote-card .lote-id { font-weight: 700; color: #2563eb; font-family: monospace; }
.lote-card .lote-prod { font-size: 12px; color: #64748b; }
.btn-trace { margin-top: 8px; background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; padding: 6px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; text-align: center; }

.timeline { padding: 10px 0; }
.timeline-item { position: relative; padding-left: 30px; margin-bottom: 20px; border-left: 2px solid #e2e8f0; }
.timeline-item::before { content: ""; position: absolute; left: -7px; top: 0; width: 12px; height: 12px; border-radius: 50%; background: #2563eb; border: 2px solid #fff; }
.timeline-item .tm-date { font-size: 11px; color: #94a3b8; }
.timeline-item .tm-action { font-weight: 700; font-size: 13px; }
.timeline-item .tm-node { font-size: 12px; color: #64748b; }

.pagination-container { margin-top: 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 16px; }
.pagination-info { font-size: 13px; color: #64748b; }
.pagination-btns { display: flex; gap: 8px; }

@media (max-width: 1024px) {
    .kpi-grid { grid-template-columns: repeat(3, 1fr); }
    .info-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<script>
const baseUrl = window.location.origin + '/mes_hermen/api/wip.php';
let currentPage = 1;
let totalPages = 1;

document.addEventListener('DOMContentLoaded', () => {
    cargarFiltros();
    cargarHistorial();
});

async function cargarFiltros() {
    // Cargar turnos
    const respT = await fetch(window.location.origin + '/mes_hermen/api/catalogos.php?tipo=turnos');
    const dataT = await respT.json();
    if (dataT.success) {
        const select = document.getElementById('filtroTurno');
        dataT.turnos.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id_turno;
            opt.textContent = t.nombre;
            select.appendChild(opt);
        });
    }

    // Cargar responsables (usuarios)
    const respU = await fetch(window.location.origin + '/mes_hermen/api/catalogos.php?tipo=usuarios');
    const dataU = await respU.json();
    if (dataU.success) {
        const select = document.getElementById('filtroResponsable');
        dataU.usuarios.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.id_usuario;
            opt.textContent = u.nombre_completo;
            select.appendChild(opt);
        });
    }
}

async function cargarHistorial(page = 1) {
    currentPage = page;
    const tbody = document.getElementById('tbodyHistorial');
    tbody.innerHTML = '<tr><td colspan="10" class="text-center"><i class="fas fa-spinner fa-spin"></i> Consultando bitácora...</td></tr>';

    const desde = document.getElementById('filtroFechaDesde').value;
    const hasta = document.getElementById('filtroFechaHasta').value;
    const turno = document.getElementById('filtroTurno').value;
    const resp = document.getElementById('filtroResponsable').value;
    const search = document.getElementById('filtroBusqueda').value;

    const params = new URLSearchParams({
        action: 'get_historial_produccion_tejido',
        page: currentPage,
        fecha_desde: desde,
        fecha_hasta: hasta,
        id_turno: turno,
        id_responsable: resp,
        search: search
    });

    try {
        const response = await fetch(`${baseUrl}?${params.toString()}`);
        const data = await response.json();

        if (!data.success) throw new Error(data.message);

        if (data.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center">No se encontraron registros histórico.</td></tr>';
            renderPagination(0);
            return;
        }

        tbody.innerHTML = data.data.map(item => `
            <tr>
                <td><strong>${item.fecha}</strong></td>
                <td><span class="badge badge-primary">${item.turno_nombre || 'S/D'}</span></td>
                <td>${item.responsable_nombre}</td>
                <td><span style="font-family:monospace; font-weight:bold;">#${item.id_planilla}</span></td>
                <td>${item.maquinas_activas}</td>
                <td style="font-weight:700;">${item.total_docenas}</td>
                <td style="font-weight:700;">${item.total_unidades}</td>
                <td><span class="badge ${item.cantidad_incidencias > 0 ? 'badge-danger' : 'badge-success'}">${item.cantidad_incidencias}</span></td>
                <td><span class="badge badge-success">${item.estado}</span></td>
                <td>
                    <button class="btn-primary btn-sm" onclick="verDetalle(${item.id_planilla})">
                        <i class="fas fa-search"></i> Ver Cuaderno
                    </button>
                </td>
            </tr>
        `).join('');

        totalPages = data.pagination.total_pages;
        renderPagination(data.pagination.total);

    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center" style="color:red">Error: ${e.message}</td></tr>`;
    }
}

function renderPagination(total) {
    const container = document.getElementById('pagination');
    if (total === 0) {
        container.innerHTML = '';
        return;
    }

    container.innerHTML = `
        <div class="pagination-info">Mostrando página ${currentPage} de ${totalPages} (${total} registros totales)</div>
        <div class="pagination-btns">
            <button class="btn-secondary" onclick="cargarHistorial(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>Anterior</button>
            <button class="btn-secondary" onclick="cargarHistorial(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>Siguiente</button>
        </div>
    `;
}

function limpiarFiltros() {
    document.getElementById('filtroFechaDesde').value = '';
    document.getElementById('filtroFechaHasta').value = '';
    document.getElementById('filtroTurno').value = '';
    document.getElementById('filtroResponsable').value = '';
    document.getElementById('filtroBusqueda').value = '';
    cargarHistorial(1);
}

async function verDetalle(id) {
    const modal = document.getElementById('modalDetalle');
    modal.classList.add('show');
    
    // Resetear UI
    document.getElementById('labelNumeroPlanilla').textContent = `# ${id}`;
    
    try {
        const response = await fetch(`${baseUrl}?action=get_detalle_historial_produccion_tejido&id_planilla=${id}`);
        const res = await response.json();
        
        if (!res.success) throw new Error(res.message);
        
        const { cabecera, resumen, maquinas, incidencias, lotes_wip, movimientos } = res.data;
        
        // Cabecera
        document.getElementById('detFecha').textContent = cabecera.fecha;
        document.getElementById('detTurno').textContent = cabecera.turno;
        document.getElementById('detResponsable').textContent = cabecera.responsable_nombre;
        document.getElementById('detEstado').innerHTML = `<span class="badge badge-success">${cabecera.estado}</span>`;
        document.getElementById('detObservaciones').textContent = cabecera.observaciones || 'Sin observaciones';
        document.getElementById('detUsuario').textContent = cabecera.responsable_nombre;
        document.getElementById('detFechaRegistro').textContent = cabecera.fecha_registro;
        
        // Resumen
        document.getElementById('kpiDoz').textContent = resumen.total_docenas;
        document.getElementById('kpiUnd').textContent = resumen.total_unidades;
        document.getElementById('kpiMaq').textContent = resumen.maquinas_activas;
        document.getElementById('kpiInc').textContent = resumen.total_incidencias;
        document.getElementById('kpiLot').textContent = resumen.total_lotes;
        
        // Máquinas
        const tbodyM = document.getElementById('tbodyMaquinas');
        tbodyM.innerHTML = maquinas.map(m => `
            <tr>
                <td><strong>${m.maquina_nombre}</strong></td>
                <td>${m.producto_codigo} - ${m.producto_nombre}</td>
                <td>${m.cantidad_docenas} doz / ${m.cantidad_unidades} und</td>
                <td>${m.observaciones || '-'}</td>
                <td><span class="badge badge-success">OK</span></td>
            </tr>
        `).join('');
        
        // Incidencias
        document.getElementById('secIncidencias').style.display = incidencias.length > 0 ? 'block' : 'none';
        const tbodyI = document.getElementById('tbodyIncidencias');
        tbodyI.innerHTML = incidencias.map(i => `
            <tr>
                <td><strong>${i.item_nombre}</strong><br><small>${i.item_codigo}</small></td>
                <td>${Number(i.cantidad_requerida).toFixed(3)} Kg</td>
                <td class="text-danger">${Number(i.cantidad_pendiente).toFixed(3)} Kg</td>
                <td><span class="badge ${i.estado === 'RESUELTA' ? 'badge-success' : 'badge-warning'}">${i.estado}</span></td>
                <td>${i.accion_resolucion || 'Pendiente'}<br><small>${i.usuario_nombre || ''}</small></td>
            </tr>
        `).join('');
        
        // Análisis de Consumo (NUEVO)
        const tbodyA = document.getElementById('tbodyAnalisisConsumo');
        const analisis = res.data.analisis_consumo || [];
        if (analisis.length > 0) {
            document.getElementById('secAnalisisConsumo').style.display = 'block';
            tbodyA.innerHTML = analisis.map(a => {
                const lowPct = a.porcentaje < 90;
                return `
                    <tr>
                        <td><strong>${a.componente}</strong></td>
                        <td style="color:${a.real_kg > 0 ? '#10b981' : '#ef4444'}">${Number(a.real_kg).toFixed(4)}</td>
                        <td>${Number(a.teorico_kg).toFixed(4)}</td>
                        <td style="color:${a.pendiente_kg > 0 ? '#ef4444' : ''}">${Number(a.pendiente_kg).toFixed(4)}</td>
                        <td style="color:${a.diferencia_kg < 0 ? '#ef4444' : ''}">${Number(a.diferencia_kg).toFixed(4)}</td>
                        <td style="font-weight:700; color:${lowPct ? '#ef4444' : '#10b981'}">${a.porcentaje}%</td>
                    </tr>
                `;
            }).join('');
        } else {
            document.getElementById('secAnalisisConsumo').style.display = 'none';
        }
        
        // Lotes
        const containerL = document.getElementById('containerLotes');
        containerL.innerHTML = lotes_wip.map(l => `
            <div class="lote-card">
                <div class="lote-header">
                    <span class="lote-id">${l.codigo_lote}</span>
                    <span class="badge badge-primary">${l.estado_lote}</span>
                </div>
                <div class="lote-prod">${l.codigo_producto} - ${l.descripcion_completa}</div>
                <div class="lote-info" style="font-size:12px; margin-top:4px;">
                    <strong>Cantidad:</strong> ${l.cantidad_docenas} doz | ${l.cantidad_unidades} und<br>
                    <strong>Área Actual:</strong> ${l.area_nombre || 'Finalizado'}
                </div>
                <button class="btn-trace" onclick="abrirHistorialLote(${l.id_lote_wip})">
                    <i class="fas fa-route"></i> Ver recorrido
                </button>
            </div>
        `).join('');
        
        // Trazabilidad posterior
        const timeline = document.getElementById('timelineMovimientos');
        if (movimientos.length === 0) {
            timeline.innerHTML = '<p style="color:#94a3b8; font-style:italic">No se registran movimientos posteriores para estos lotes.</p>';
        } else {
            timeline.innerHTML = movimientos.map(m => `
                <div class="timeline-item">
                    <div class="tm-date">${m.fecha}</div>
                    <div class="tm-action">${m.tipo_movimiento}</div>
                    <div class="tm-node">${m.area_origen_nombre || 'INICIO'} <i class="fas fa-arrow-right"></i> ${m.area_destino_nombre || 'FIN'}</div>
                    <div class="tm-obs" style="font-size:11px; margin-top:4px; opacity:0.8;">${m.observaciones || ''}</div>
                </div>
            `).join('');
        }
        
    } catch (e) {
        alert("Error al cargar detalle: " + e.message);
    }
}

function cerrarModalDetail() {
    document.getElementById('modalDetalle').classList.remove('show');
}

// Lógica de trazabilidad heredada y adaptada de wip_fase0
async function abrirHistorialLote(idLoteWip) {
    const modal = document.getElementById('modalHistorialLote');
    const body = document.getElementById('bodyHistorialLote');
    body.innerHTML = '<div class="text-center p-4"><i class="fas fa-sync fa-spin"></i> Cargando trazabilidad...</div>';
    modal.classList.add('show');

    try {
        const response = await fetch(`${baseUrl}?action=historial&id_lote_wip=${idLoteWip}`);
        const data = await response.json();
        
        if (!data.success) throw new Error(data.message);

        const { lote, movimientos, resumen } = data;
        
        body.innerHTML = `
            <div style="background:#f8fafc; padding:15px; border-radius:10px; margin-bottom:20px; border-left:4px solid #2563eb;">
                <h4 style="margin:0;">Lote: ${lote.codigo_lote}</h4>
                <p style="margin:5px 0 0; color:#64748b;">${lote.codigo_producto} - ${lote.descripcion_completa}</p>
            </div>
            
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; margin-bottom:20px;">
                <div class="kpi-card"><div class="kpi-value">${lote.cantidad_docenas}|${String(lote.cantidad_unidades).padStart(2,'0')}</div><div class="kpi-label">Cantidad Original</div></div>
                <div class="kpi-card success"><div class="kpi-value">${resumen.area_actual}</div><div class="kpi-label">Ubicación Actual</div></div>
                <div class="kpi-card info"><div class="kpi-value">${resumen.numero_transferencias}</div><div class="kpi-label">Pasos Recorridos</div></div>
            </div>

            <table class="compact-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Movimiento</th>
                        <th>Origen</th>
                        <th>Destino</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    ${movimientos.map(m => `
                        <tr>
                            <td>${m.fecha}</td>
                            <td><span class="badge badge-primary">${m.tipo_movimiento}</span></td>
                            <td>${m.area_origen_nombre || '-'}</td>
                            <td>${m.area_destino_nombre || '-'}</td>
                            <td>${m.usuario_nombre || 'SISTEMA'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } catch (e) {
        body.innerHTML = `<div class="alert alert-danger">${e.message}</div>`;
    }
}

function cerrarModalLote() {
    document.getElementById('modalHistorialLote').classList.remove('show');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
