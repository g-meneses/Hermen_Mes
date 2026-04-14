<?php
require_once '../../config/database.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Incidencias de Consumo WIP';
$currentPage = 'incidencias_consumo';
require_once '../../includes/header.php';
?>

<div class="incidencias-container">
    <header class="incidencias-header">
        <div class="header-info">
            <h1><i class="fas fa-exclamation-triangle"></i> Incidencias de Consumo</h1>
            <p>Seguimiento de quiebres de stock teórico detectados por el motor FIFO en Tejeduría.</p>
        </div>
        <div class="header-stats">
            <div class="mini-stat">
                <span class="label">Total Pendientes</span>
                <span class="value" id="countPendientes">0</span>
            </div>
            <button class="btn-refresh" onclick="cargarIncidencias()"><i class="fas fa-sync-alt"></i> Actualizar</button>
        </div>
    </header>

    <div class="search-bar">
        <div class="search-input-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" id="filtroIncidencia" placeholder="Buscar por lote, producto o hilo..." oninput="filtrarIncidencias()">
        </div>
        <div class="filters">
            <select id="filtroEstado" onchange="filtrarIncidencias()">
                <option value="PENDIENTE">Sólo Pendientes</option>
                <option value="TODOS">Todos los Estados</option>
            </select>
        </div>
    </div>

        </div>
    </div>
</div>

<!-- Modal Detalle de Incidencia -->
<div id="modalDetalleIncidencia" class="modal-wip">
    <div class="modal-content-wip glassmorphism">
        <header class="modal-header">
            <h2><i class="fas fa-search-plus"></i> Detalles de Incidencia</h2>
            <button class="close-btn" onclick="cerrarModal('modalDetalleIncidencia')">&times;</button>
        </header>
        <div class="modal-body" id="bodyDetalleIncidencia">
             <!-- Se carga dinámicamente -->
        </div>
        <footer class="modal-footer">
            <button class="btn-secondary" onclick="cerrarModal('modalDetalleIncidencia')">Cerrar</button>
            <button class="btn-primary" id="btnIrAResolucion">Resolver Incidencia</button>
        </footer>
    </div>
</div>

<!-- Modal Resolución de Incidencia -->
<div id="modalResolucionIncidencia" class="modal-wip">
    <div class="modal-content-wip glassmorphism">
        <header class="modal-header">
            <h2><i class="fas fa-check-circle"></i> Resolución de Incidencia</h2>
            <button class="close-btn" onclick="cerrarModal('modalResolucionIncidencia')">&times;</button>
        </header>
        <div class="modal-body">
            <form id="formResolucion">
                <input type="hidden" id="res_id_incidencia">
                
                <div class="form-group">
                    <label>Tipo de Resolución</label>
                    <select id="res_tipo" required>
                        <option value="">Seleccione una acción...</option>
                        <option value="REPROCESAR">REPROCESAR (Reintentar FIFO)</option>
                        <option value="JUSTIFICAR">JUSTIFICAR (Cierre operativo)</option>
                        <option value="AJUSTE_MANUAL">AJUSTE MANUAL</option>
                        <option value="ANULAR">ANULAR</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Estado de Destino</label>
                    <select id="res_estado" required>
                        <option value="RESUELTA">RESUELTA</option>
                        <option value="JUSTIFICADA">JUSTIFICADA</option>
                        <option value="EN_REVISION">EN REVISIÓN</option>
                        <option value="ANULADA">ANULADA</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Observación (Obligatoria)</label>
                    <textarea id="res_observacion" rows="4" placeholder="Describa el motivo de la resolución..." required></textarea>
                </div>
            </form>
        </div>
        <footer class="modal-footer">
            <button class="btn-secondary" onclick="cerrarModal('modalResolucionIncidencia')">Cancelar</button>
            <button class="btn-success" onclick="guardarResolucion()">Guardar Resolución</button>
        </footer>
    </div>
</div>

<style>
.incidencias-container {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
}

.incidencias-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.incidencias-header h1 {
    margin: 0;
    font-size: 1.8rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 12px;
}

.incidencias-header h1 i {
    color: #f59e0b;
}

.incidencias-header p {
    margin: 4px 0 0;
    color: #64748b;
}

.header-stats {
    display: flex;
    gap: 20px;
    align-items: center;
}

.mini-stat {
    background: #fff;
    padding: 10px 20px;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
}

.mini-stat .label {
    font-size: 0.75rem;
    color: #64748b;
    text-transform: uppercase;
    font-weight: 600;
}

.mini-stat .value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #2563eb;
}

.btn-refresh {
    background: #fff;
    border: 1px solid #e2e8f0;
    padding: 10px 18px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-refresh:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.search-bar {
    display: flex;
    gap: 16px;
    margin-bottom: 24px;
}

.search-input-wrapper {
    flex: 1;
    position: relative;
}

.search-input-wrapper i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
}

.search-input-wrapper input {
    width: 100%;
    padding: 12px 12px 12px 42px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    font-size: 0.95rem;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.filters select {
    padding: 12px 16px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #fff;
    font-weight: 600;
    color: #475569;
}

.incidencias-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.incidencia-card {
    background: #fff;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative;
    overflow: hidden;
}

.incidencia-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 20px -8px rgba(0,0,0,0.1);
}

.incidencia-card.pending {
    border-left: 5px solid #f59e0b;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.lote-tag {
    font-family: monospace;
    font-weight: 700;
    color: #2563eb;
    background: #eff6ff;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.9rem;
}

.fecha-tag {
    font-size: 0.8rem;
    color: #94a3b8;
}

.hilo-info h3 {
    margin: 0;
    font-size: 1.1rem;
    color: #1e293b;
}

.hilo-info p {
    margin: 4px 0 0;
    font-size: 0.85rem;
    color: #64748b;
}

.progress-section {
    margin: 20px 0;
}

.progress-stats {
    display: flex;
    justify-content: space-between;
    font-size: 0.85rem;
    margin-bottom: 8px;
}

.progress-bar {
    height: 10px;
    background: #f1f5f9;
    border-radius: 5px;
    overflow: hidden;
    display: flex;
}

.bar-consumed { background: #10b981; }
.bar-pending { background: #f59e0b; }

/* Estilos Premium Modales */
.modal-wip {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-wip.show { display: flex; }

.modal-content-wip {
    background: #fff;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    border-radius: 20px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.glassmorphism {
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(to right, #f8fafc, #fff);
}

.modal-header h2 { margin: 0; font-size: 1.25rem; color: #1e293b; display: flex; align-items: center; gap: 10px; }
.modal-header h2 i { color: #2563eb; }

.close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8; }

.modal-body { padding: 24px; overflow-y: auto; }

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #f1f5f9;
    background: #f8fafc;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

/* Secciones del detalle */
.detalle-sec { margin-bottom: 24px; }
.detalle-sec h4 { font-size: 0.85rem; text-transform: uppercase; color: #64748b; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px; }

.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

.info-item { display: flex; flex-direction: column; }
.info-item .label { font-size: 0.75rem; color: #94a3b8; }
.info-item .value { font-weight: 600; color: #1e293b; }

.fifo-badge { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
.fifo-badge.parcial { background: #fef3c7; color: #92400e; }
.fifo-badge.sin_stock { background: #fee2e2; color: #991b1b; }

/* Botones */
.btn-primary { background: #2563eb; color: #fff; border: none; padding: 10px 18px; border-radius: 10px; font-weight: 600; cursor: pointer; }
.btn-success { background: #10b981; color: #fff; border: none; padding: 10px 18px; border-radius: 10px; font-weight: 600; cursor: pointer; }
.btn-secondary { background: #f1f5f9; color: #475569; border: none; padding: 10px 18px; border-radius: 10px; font-weight: 600; cursor: pointer; }

.card-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 20px;
}

.btn-card {
    padding: 8px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid #e2e8f0;
    background: #fff;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-card:hover { background: #f8fafc; border-color: #cbd5e1; }
.btn-card.primary { color: #2563eb; border-color: #dbeafe; background: #eff6ff; }
.btn-card.primary:hover { background: #dbeafe; }

.btn-card.warning { color: #d97706; border-color: #fef3c7; background: #fffbeb; }
.btn-card.warning:hover { background: #fef3c7; }

/* Formulario */
.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 8px; }
.form-group select, .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    font-size: 0.95rem;
}

.res-historial {
    background: #f8fafc;
    border-radius: 12px;
    padding: 16px;
    border: 1px dashed #cbd5e1;
}

@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

</style>

<script>
let incidenciasRaw = [];

document.addEventListener('DOMContentLoaded', () => {
    cargarIncidencias();
});

async function cargarIncidencias() {
    const grid = document.getElementById('gridIncidencias');
    try {
        const response = await fetch(`${baseUrl}/api/wip.php?action=get_incidencias_consumo`);
        const data = await response.json();
        
        if (!data.success) throw new Error(data.message);
        
        incidenciasRaw = data.incidencias || [];
        renderIncidencias();
        actualizarContadores();
        
    } catch (e) {
        grid.innerHTML = `<div class="loading-state"><p style="color:red">Error: ${e.message}</p></div>`;
    }
}

function renderIncidencias() {
    const grid = document.getElementById('gridIncidencias');
    const query = document.getElementById('filtroIncidencia').value.toLowerCase();
    const estado = document.getElementById('filtroEstado').value;

    const filtradas = incidenciasRaw.filter(i => {
        const matchSearch = i.codigo_lote.toLowerCase().includes(query) || 
                          i.item_nombre.toLowerCase().includes(query) ||
                          i.producto_nombre.toLowerCase().includes(query);
        const matchEstado = estado === 'TODOS' || i.estado === estado;
        return matchSearch && matchEstado;
    });

    if (filtradas.length === 0) {
        grid.innerHTML = '<div class="loading-state"><p>No se encontraron incidencias activas.</p></div>';
        return;
    }

    grid.innerHTML = filtradas.map(i => {
        const pct = Math.round((i.cantidad_consumida / i.cantidad_requerida) * 100);
        return `
            <div class="incidencia-card ${i.estado.toLowerCase()}">
                <div class="card-header">
                    <span class="lote-tag">${i.codigo_lote}</span>
                    <span class="fecha-tag">${i.fecha_registro}</span>
                </div>
                <div class="hilo-info">
                    <h3>${i.item_nombre}</h3>
                    <p>${i.producto_nombre}</p>
                </div>
                
                <div class="progress-section">
                    <div class="progress-stats">
                        <span>Consumido: ${pct}%</span>
                        <span>Requerido: ${Number(i.cantidad_requerida).toFixed(3)} Kg</span>
                    </div>
                    <div class="progress-bar">
                        <div class="bar-consumed" style="width: ${pct}%"></div>
                        <div class="bar-pending" style="width: ${100-pct}%"></div>
                    </div>
                </div>

                <div class="details">
                    <div class="detail-row">
                        <span class="label">Cantidad Consumida</span>
                        <span class="value">${Number(i.cantidad_consumida).toFixed(3)} Kg</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Cantidad Pendiente</span>
                        <span class="value alert">${Number(i.cantidad_pendiente).toFixed(3)} Kg</span>
                    </div>
                    <div class="detail-row" style="margin-top:10px; border-top: 1px dashed #e2e8f0; padding-top:10px;">
                        <span class="label">Estado de Flujo</span>
                        <span class="value" style="color:#f59e0b">${i.estado} ${i.accion_resolucion ? ' - ' + i.accion_resolucion : ''}</span>
                    </div>
                </div>

                <div class="card-actions">
                    <button class="btn-card primary" onclick="verDetalle(${i.id_incidencia})">
                        <i class="fas fa-search"></i> Detalle
                    </button>
                    ${i.estado === 'PENDIENTE' || i.estado === 'EN_REVISION' ? `
                        <button class="btn-card warning" onclick="abrirModalResolucion(${i.id_incidencia})">
                            <i class="fas fa-check"></i> Resolver
                        </button>
                    ` : `
                        <button class="btn-card" disabled>
                            <i class="fas fa-lock"></i> Cerrada
                        </button>
                    `}
                </div>
            </div>
        `;
    }).join('');
}

async function verDetalle(id) {
    const modal = document.getElementById('modalDetalleIncidencia');
    const body = document.getElementById('bodyDetalleIncidencia');
    const btnRes = document.getElementById('btnIrAResolucion');
    
    body.innerHTML = '<div class="loading-state"><div class="spinner"></div><p>Cargando contexto...</p></div>';
    modal.classList.add('show');

    try {
        const response = await fetch(`${baseUrl}/api/wip.php?action=get_incidencia_detalle&id_incidencia=${id}`);
        const data = await response.json();
        if (!data.success) throw new Error(data.message);

        const inc = data.incidencia;
        const plan = data.planilla;
        const res = data.resolucion;

        body.innerHTML = `
            <div class="detalle-sec">
                <h4><i class="fas fa-info-circle"></i> Información de Incidencia</h4>
                <div class="grid-2">
                    <div class="info-item"><span class="label">Código Lote</span><span class="value">${inc.codigo_lote}</span></div>
                    <div class="info-item"><span class="label">Estado Actual</span><span class="value" style="color:#f59e0b">${inc.estado}</span></div>
                    <div class="info-item"><span class="label">Hilo Faltante</span><span class="value">${inc.item_nombre}</span></div>
                    <div class="info-item"><span class="label">Código Hilo</span><span class="value">${inc.item_codigo}</span></div>
                </div>
            </div>

            <div class="detalle-sec">
                <h4><i class="fas fa-industry"></i> Contexto de Producción</h4>
                <div class="grid-2">
                    <div class="info-item"><span class="label">Producto Fabricado</span><span class="value">${inc.producto_nombre}</span></div>
                    <div class="info-item"><span class="label">Máquina</span><span class="value">${inc.numero_maquina || 'No especificada'}</span></div>
                    <div class="info-item"><span class="label">Turno</span><span class="value">${inc.turno_nombre || 'No especificado'}</span></div>
                    <div class="info-item"><span class="label">Responsable Registro</span><span class="value">${inc.responsable_nombre || 'SISTEMA'}</span></div>
                    <div class="info-item"><span class="label">Fecha Producción</span><span class="value">${inc.fecha_registro}</span></div>
                    <div class="info-item"><span class="label">Planilla MES</span><span class="value">${plan ? plan.id_planilla : 'No vinculada'}</span></div>
                </div>
            </div>

            <div class="detalle-sec">
                <h4><i class="fas fa-calculator"></i> Resultado Motor FIFO</h4>
                <div class="grid-2" style="margin-bottom:15px">
                    <div class="info-item"><span class="label">Análisis Stock</span><span class="value"><span class="fifo-badge ${data.fifo.tipo.toLowerCase()}">${data.fifo.tipo}</span></span></div>
                    <div class="info-item"><span class="label">Déficit Teórico</span><span class="value" style="color:#dc2626">${Number(inc.cantidad_pendiente).toFixed(3)} ${inc.item_unidad}</span></div>
                </div>
                <h5>Documentos Vinculados (Consumo Parcial):</h5>
                ${data.fifo.detalle.length > 0 ? `
                    <table style="width:100%; font-size:0.8rem; border-collapse:collapse; margin-top:8px;">
                        <tr style="background:#f8fafc; text-align:left">
                            <th style="padding:6px">Doc. Origen</th>
                            <th style="padding:6px">Fecha Doc.</th>
                            <th style="padding:6px">Consumo</th>
                        </tr>
                        ${data.fifo.detalle.map(d => `
                            <tr>
                                <td style="padding:6px">${d.numero_documento}</td>
                                <td style="padding:6px">${d.fecha_documento}</td>
                                <td style="padding:6px; font-weight:bold">${Number(d.cantidad_consumida).toFixed(3)} ${inc.item_unidad}</td>
                            </tr>
                        `).join('')}
                    </table>
                ` : '<p style="font-size:0.8rem; color:#94a3b8">No se encontró stock en ningún documento SAL-TEJ disponible.</p>'}
            </div>

            ${res ? `
                <div class="detalle-sec">
                    <h4><i class="fas fa-history"></i> Historial de Resolución</h4>
                    <div class="res-historial">
                        <div class="grid-2">
                            <div class="info-item"><span class="label">Acción Tomada</span><span class="value">${res.accion}</span></div>
                            <div class="info-item"><span class="label">Resuelto por</span><span class="value">${res.usuario_nombre}</span></div>
                            <div class="info-item"><span class="label">Fecha Resolución</span><span class="value">${res.fecha}</span></div>
                        </div>
                        <div class="info-item" style="margin-top:10px">
                            <span class="label">Observaciones</span>
                            <span class="value" style="font-weight:normal; font-style:italic">"${res.observacion}"</span>
                        </div>
                    </div>
                </div>
            ` : ''}
        `;

        if (inc.estado === 'PENDIENTE' || inc.estado === 'EN_REVISION') {
            btnRes.style.display = 'block';
            btnRes.onclick = () => { cerrarModal('modalDetalleIncidencia'); abrirModalResolucion(id); };
        } else {
            btnRes.style.display = 'none';
        }

    } catch (e) {
        body.innerHTML = `<div class="loading-state"><p style="color:red">Error: ${e.message}</p></div>`;
    }
}

function abrirModalResolucion(id) {
    document.getElementById('res_id_incidencia').value = id;
    document.getElementById('formResolucion').reset();
    document.getElementById('modalResolucionIncidencia').classList.add('show');
}

function cerrarModal(id) {
    document.getElementById(id).classList.remove('show');
}

async function guardarResolucion() {
    const id = document.getElementById('res_id_incidencia').value;
    const accion = document.getElementById('res_tipo').value;
    const estado = document.getElementById('res_estado').value;
    const observacion = document.getElementById('res_observacion').value;

    if (!accion || !observacion) {
        Swal.fire('Error', 'Debe seleccionar una acción y escribir una observación', 'error');
        return;
    }

    try {
        const response = await fetch(`${baseUrl}/api/wip.php`, {
            method: 'POST',
            body: JSON.stringify({
                action: 'resolver_incidencia',
                id_incidencia: id,
                accion,
                estado,
                observacion
            })
        });

        const data = await response.json();
        if (!data.success) throw new Error(data.message);

        Swal.fire('Éxito', 'Incidencia resuelta correctamente', 'success');
        cerrarModal('modalResolucionIncidencia');
        cargarIncidencias();

    } catch (e) {
        Swal.fire('Error', e.message, 'error');
    }
}

function filtrarIncidencias() {
    renderIncidencias();
}

function actualizarContadores() {
    const pendientes = incidenciasRaw.filter(i => i.estado === 'PENDIENTE').length;
    document.getElementById('countPendientes').textContent = pendientes;
}

</script>

<?php require_once '../../includes/footer.php'; ?>
