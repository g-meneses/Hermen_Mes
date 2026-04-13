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

    <div class="incidencias-grid" id="gridIncidencias">
        <!-- Se carga dinámicamente -->
        <div class="loading-state">
            <div class="spinner"></div>
            <p>Analizando incidencias en planta...</p>
        </div>
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

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f8fafc;
    font-size: 0.9rem;
}

.detail-row:last-child { border-bottom: none; }

.detail-row .label { color: #64748b; }
.detail-row .value { font-weight: 600; color: #334155; }
.detail-row .value.alert { color: #dc2626; }

.loading-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px;
    color: #64748b;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f1f5f9;
    border-top: 4px solid #2563eb;
    border-radius: 50%;
    margin: 0 auto 16px;
    animation: spin 1s linear infinite;
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
                        <span class="value" style="color:#f59e0b">${i.estado}</span>
                    </div>
                </div>
            </div>
        `;
    }).join('');
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
