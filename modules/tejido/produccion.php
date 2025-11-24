<?php
require_once '../../config/database.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Registro de Producción de Tejeduría';
$currentPage = 'produccion';
require_once '../../includes/header.php';
?>

<style>
/* ESTILOS ULTRA COMPACTOS */
.page-header {
    margin-bottom: 8px !important;
    padding-bottom: 5px !important;
}

.page-header h2 {
    font-size: 1.3rem !important;
    margin-bottom: 2px !important;
}

.page-header p {
    font-size: 0.8rem !important;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    margin-bottom: 8px !important;
}

.stat-card {
    padding: 8px !important;
}

.stat-icon {
    width: 40px !important;
    height: 40px !important;
    font-size: 16px !important;
}

.stat-label {
    font-size: 10px !important;
}

.stat-value {
    font-size: 16px !important;
}

/* FILTROS EN UNA LÍNEA HORIZONTAL */
.card {
    margin-top: 8px !important;
}

.card-header {
    padding: 5px 10px !important;
    background: #f5f5f5 !important;
}

.card-header h5 {
    font-size: 0.85rem !important;
    margin: 0 !important;
}

.card-body {
    padding: 8px !important;
}

.filtros-row {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    flex-wrap: wrap !important;
}

.filtro-item {
    display: flex !important;
    align-items: center !important;
    gap: 5px !important;
}

.filtro-item label {
    font-size: 0.75rem !important;
    margin: 0 !important;
    white-space: nowrap !important;
    font-weight: 500 !important;
}

.filtro-item input,
.filtro-item select {
    height: 28px !important;
    padding: 2px 6px !important;
    font-size: 0.8rem !important;
    min-width: 140px !important;
}

.btn-sm {
    padding: 3px 8px !important;
    font-size: 0.8rem !important;
    height: 28px !important;
}

/* Tabla compacta */
.table-sm th, .table-sm td {
    padding: 5px 6px !important;
    font-size: 0.8rem !important;
}

.table thead th {
    font-size: 0.75rem !important;
    padding: 6px !important;
    background: #343a40 !important;
    color: white !important;
}

/* Modal */
.modal-content {
    max-height: 92vh !important;
}

.modal-header {
    padding: 8px 12px !important;
}

.modal-header h3 {
    font-size: 1rem !important;
}

.modal-body {
    padding: 10px !important;
}

.form-group {
    margin-bottom: 6px !important;
}

.form-control {
    height: 28px !important;
    padding: 3px 6px !important;
    font-size: 0.8rem !important;
}

textarea.form-control {
    height: auto !important;
}

.badge {
    font-size: 0.7rem !important;
    padding: 2px 5px !important;
}
</style>

<div class="container-fluid" style="padding: 8px;">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e0e0e0; margin-bottom: 8px; padding-bottom: 5px;">
        <div>
            <h2 style="font-size: 1.3rem; margin-bottom: 2px;"><i class="fas fa-industry"></i> Registro de Producción de Tejeduría</h2>
            <p class="text-muted" style="font-size: 0.8rem; margin: 0;">Registre la producción de cada máquina por turno</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal()">
                <i class="fas fa-plus"></i> Nueva Producción
            </button>
        </div>
    </div>

    <!-- Cards de Estadísticas -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 8px;">
        <div style="background: white; padding: 8px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 8px; border-left: 4px solid #007bff;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: #007bff; display: flex; align-items: center; justify-content: center; color: white; font-size: 16px;">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-size: 10px; color: #666;">Producción Hoy</div>
                <div style="font-size: 16px; font-weight: bold; color: #333;" id="statHoy">0|0</div>
            </div>
        </div>

        <div style="background: white; padding: 8px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 8px; border-left: 4px solid #28a745;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: #28a745; display: flex; align-items: center; justify-content: center; color: white; font-size: 16px;">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-size: 10px; color: #666;">Esta Semana</div>
                <div style="font-size: 16px; font-weight: bold; color: #333;" id="statSemana">0|0</div>
            </div>
        </div>

        <div style="background: white; padding: 8px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 8px; border-left: 4px solid #17a2b8;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: #17a2b8; display: flex; align-items: center; justify-content: center; color: white; font-size: 16px;">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-size: 10px; color: #666;">Este Mes</div>
                <div style="font-size: 16px; font-weight: bold; color: #333;" id="statMes">0|0</div>
            </div>
        </div>

        <div style="background: white; padding: 8px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 8px; border-left: 4px solid #ffc107;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: #ffc107; display: flex; align-items: center; justify-content: center; color: white; font-size: 16px;">
                <i class="fas fa-calendar"></i>
            </div>
            <div style="flex: 1;">
                <div style="font-size: 10px; color: #666;">Este Año</div>
                <div style="font-size: 16px; font-weight: bold; color: #333;" id="statAnual">0|0</div>
            </div>
        </div>
    </div>

    <!-- Filtros EN UNA SOLA LÍNEA HORIZONTAL -->
    <div class="card" style="margin-top: 8px;">
        <div class="card-header" style="padding: 5px 10px; background: #f5f5f5;">
            <h5 style="margin: 0; font-size: 0.85rem;"><i class="fas fa-filter"></i> Filtros de Búsqueda</h5>
        </div>
        <div class="card-body" style="padding: 8px;">
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 5px;">
                    <label style="font-size: 0.75rem; margin: 0; white-space: nowrap; font-weight: 500;">Desde:</label>
                    <input type="date" id="filtroFechaDesde" style="height: 28px; padding: 2px 6px; font-size: 0.8rem; width: 140px; border: 1px solid #ced4da; border-radius: 3px;">
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <label style="font-size: 0.75rem; margin: 0; white-space: nowrap; font-weight: 500;">Hasta:</label>
                    <input type="date" id="filtroFechaHasta" style="height: 28px; padding: 2px 6px; font-size: 0.8rem; width: 140px; border: 1px solid #ced4da; border-radius: 3px;">
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <label style="font-size: 0.75rem; margin: 0; white-space: nowrap; font-weight: 500;">Turno:</label>
                    <select id="filtroTurno" style="height: 28px; padding: 2px 6px; font-size: 0.8rem; width: 180px; border: 1px solid #ced4da; border-radius: 3px;">
                        <option value="">Todos los turnos</option>
                    </select>
                </div>
                <div style="display: flex; gap: 5px;">
                    <button class="btn btn-sm btn-primary" onclick="loadProducciones()" style="padding: 3px 10px; font-size: 0.8rem; height: 28px;">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="limpiarFiltros()" style="padding: 3px 10px; font-size: 0.8rem; height: 28px;">
                        <i class="fas fa-eraser"></i> Limpiar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Producciones -->
    <div class="card" style="margin-top: 8px;">
        <div class="card-header" style="padding: 5px 10px; background: #f5f5f5;">
            <h5 style="margin: 0; font-size: 0.85rem;"><i class="fas fa-list"></i> Historial de Producciones</h5>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table table-hover table-sm" style="margin: 0;">
                    <thead style="background: #343a40; color: white;">
                        <tr>
                            <th style="width: 110px; padding: 6px; font-size: 0.75rem;">Código Lote</th>
                            <th style="width: 100px; padding: 6px; font-size: 0.75rem;">Fecha</th>
                            <th style="width: 90px; padding: 6px; font-size: 0.75rem;">Turno</th>
                            <th style="width: 140px; padding: 6px; font-size: 0.75rem;">Tejedor</th>
                            <th style="width: 70px; text-align: center; padding: 6px; font-size: 0.75rem;">Máqs.</th>
                            <th style="width: 130px; padding: 6px; font-size: 0.75rem;">Total Producido</th>
                            <th style="width: 130px; text-align: center; padding: 6px; font-size: 0.75rem;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="bodyProducciones">
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 15px;">Cargando...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="modalProduccion" class="modal">
    <div class="modal-content" style="max-width: 1200px; max-height: 92vh; overflow-y: auto;">
        <div class="modal-header" style="padding: 8px 12px; background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
            <h3 id="modalTitle" style="margin: 0; font-size: 1rem;"><i class="fas fa-plus-circle"></i> Nueva Producción</h3>
            <button type="button" class="close" onclick="closeModal()" style="font-size: 1.3rem;">&times;</button>
        </div>
        <div class="modal-body" style="padding: 10px;">
            <form id="formProduccion" onsubmit="guardarProduccion(event)">
                <input type="hidden" id="id_produccion">
                
                <!-- Datos Generales en una fila -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 6px;">
                    <div>
                        <label style="font-size: 0.75rem; margin-bottom: 2px; font-weight: 600; display: block;">Código Lote: <span class="text-danger">*</span></label>
                        <input type="text" id="codigo_lote" required style="width: 100%; height: 28px; padding: 3px 6px; font-size: 0.8rem; border: 1px solid #ced4da; border-radius: 3px;" placeholder="Ej: 231125-1">
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; margin-bottom: 2px; font-weight: 600; display: block;">Fecha: <span class="text-danger">*</span></label>
                        <input type="date" id="fecha_produccion" required style="width: 100%; height: 28px; padding: 3px 6px; font-size: 0.8rem; border: 1px solid #ced4da; border-radius: 3px;">
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; margin-bottom: 2px; font-weight: 600; display: block;">Turno: <span class="text-danger">*</span></label>
                        <select id="id_turno" required style="width: 100%; height: 28px; padding: 3px 6px; font-size: 0.8rem; border: 1px solid #ced4da; border-radius: 3px;">
                            <option value="">Seleccione...</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; margin-bottom: 2px; font-weight: 600; display: block;">Tejedor:</label>
                        <select id="id_tejedor" style="width: 100%; height: 28px; padding: 3px 6px; font-size: 0.8rem; border: 1px solid #ced4da; border-radius: 3px;">
                            <option value="">Sin asignar</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 6px;">
                    <label style="font-size: 0.75rem; margin-bottom: 2px; font-weight: 600; display: block;">Observaciones:</label>
                    <textarea id="observaciones" rows="2" style="width: 100%; padding: 4px 6px; font-size: 0.8rem; border: 1px solid #ced4da; border-radius: 3px;" placeholder="Ej: Apagón de luz a las 10:30 am"></textarea>
                </div>

                <hr style="margin: 6px 0; border-color: #ddd;">

                <!-- Botones -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <h6 style="margin: 0; font-size: 0.9rem;">
                        <i class="fas fa-cogs"></i> Producción por Máquina
                        <span class="badge badge-info" id="contadorMaquinas" style="margin-left: 6px; font-size: 0.7rem; padding: 2px 5px;">0 máqs</span>
                    </h6>
                    <div style="display: flex; gap: 4px;">
                        <button type="button" class="btn btn-sm btn-success" onclick="agregarMaquina()" style="padding: 3px 8px; font-size: 0.75rem;">
                            <i class="fas fa-plus"></i> Agregar
                        </button>
                        <button type="button" class="btn btn-sm btn-info" onclick="importarPlanGenerico()" style="padding: 3px 8px; font-size: 0.75rem;">
                            <i class="fas fa-file-import"></i> Importar
                        </button>
                        <button type="button" class="btn btn-sm btn-warning" onclick="limpiarDetalles()" style="padding: 3px 8px; font-size: 0.75rem;">
                            <i class="fas fa-trash"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- Tabla -->
                <div style="max-height: 360px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 6px;">
                    <table class="table table-bordered table-sm" style="margin: 0; font-size: 0.8rem;">
                        <thead style="background: #343a40; color: white; position: sticky; top: 0; z-index: 10;">
                            <tr>
                                <th style="width: 120px; padding: 5px; font-size: 0.75rem;">Máquina</th>
                                <th style="padding: 5px; font-size: 0.75rem;">Producto</th>
                                <th style="width: 70px; padding: 5px; font-size: 0.75rem;">Docenas</th>
                                <th style="width: 70px; padding: 5px; font-size: 0.75rem;">Unids.</th>
                                <th style="width: 80px; text-align: center; padding: 5px; font-size: 0.75rem;">Total</th>
                                <th style="width: 50px; text-align: center; padding: 5px; font-size: 0.75rem;">Acc.</th>
                            </tr>
                        </thead>
                        <tbody id="bodyDetalles">
                            <tr>
                                <td colspan="6" class="text-center text-muted" style="padding: 12px; font-size: 0.8rem;">
                                    No hay máquinas. Click "Agregar" para comenzar.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Nota -->
                <div class="alert alert-info" style="padding: 5px 8px; margin-bottom: 6px; font-size: 0.7rem;">
                    <i class="fas fa-info-circle"></i> <strong>Nota:</strong> Unidades entre 0-11. Formato: docenas|unidades (Ej: 20|11 = 251 unids)
                </div>

                <!-- Totales -->
                <div style="text-align: right;">
                    <span style="font-size: 0.9rem;">
                        Total: 
                        <span class="badge badge-success" id="totalGeneral" style="font-size: 0.85rem; padding: 4px 7px;">0|0</span>
                        <small class="text-muted" style="font-size: 0.75rem;">(<span id="totalUnidadesGeneral">0</span> unids)</small>
                    </span>
                </div>
            </form>
        </div>
        <div class="modal-footer" style="padding: 6px 12px; background: #f8f9fa; border-top: 1px solid #dee2e6;">
            <button type="button" class="btn btn-sm btn-secondary" onclick="closeModal()" style="padding: 4px 10px; font-size: 0.8rem;">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="submit" form="formProduccion" class="btn btn-sm btn-primary" style="padding: 4px 10px; font-size: 0.8rem;">
                <i class="fas fa-save"></i> Guardar
            </button>
        </div>
    </div>
</div>

<!-- Modal Ver Detalle -->
<div id="modalDetalle" class="modal">
    <div class="modal-content" style="max-width: 1000px;">
        <div class="modal-header">
            <h3><i class="fas fa-eye"></i> Detalle de Producción</h3>
            <button type="button" class="close" onclick="closeModalDetalle()">&times;</button>
        </div>
        <div class="modal-body" id="detalleContenido">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin fa-3x"></i>
                <p>Cargando detalle...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModalDetalle()">
                <i class="fas fa-times"></i> Cerrar
            </button>
            <button type="button" class="btn btn-primary" onclick="imprimirDetalle()">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </div>
</div>

<script>
const baseUrl = window.location.origin + '/mes_hermen';
let producciones = [];
let maquinas = [];
let productos = [];
let turnos = [];
let tejedores = [];
let detalles = [];

document.addEventListener('DOMContentLoaded', function() {
    const hoy = new Date().toISOString().split('T')[0];
    document.getElementById('fecha_produccion').value = hoy;
    
    Promise.all([
        loadMaquinas(),
        loadProductos(),
        loadTurnos(),
        loadTejedores()
    ]).then(() => {
        loadProducciones();
    });
});

async function loadProducciones() {
    try {
        const fechaDesde = document.getElementById('filtroFechaDesde').value;
        const fechaHasta = document.getElementById('filtroFechaHasta').value;
        const idTurno = document.getElementById('filtroTurno').value;
        
        let url = baseUrl + '/api/produccion.php?';
        if (fechaDesde) url += `fecha_desde=${fechaDesde}&`;
        if (fechaHasta) url += `fecha_hasta=${fechaHasta}&`;
        if (idTurno) url += `id_turno=${idTurno}&`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            producciones = data.producciones || [];
            renderProducciones(producciones);
            
            if (data.estadisticas) {
                const stats = data.estadisticas;
                document.getElementById('statHoy').textContent = `${stats.docenas_hoy}|${stats.unidades_hoy}`;
                document.getElementById('statSemana').textContent = `${stats.docenas_semana}|${stats.unidades_semana}`;
                document.getElementById('statMes').textContent = `${stats.docenas_mes}|${stats.unidades_mes}`;
                document.getElementById('statAnual').textContent = `${stats.docenas_anuales}|${stats.unidades_anuales}`;
            }
        } else {
            showNotification('Error al cargar producciones: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al cargar producciones', 'error');
    }
}

async function loadMaquinas() {
    try {
        const response = await fetch(baseUrl + '/api/maquinas.php');
        const data = await response.json();
        if (data.success) {
            maquinas = data.maquinas || [];
        }
    } catch (error) {
        console.error('Error cargando máquinas:', error);
    }
}

async function loadProductos() {
    try {
        const response = await fetch(baseUrl + '/api/productos.php');
        const data = await response.json();
        if (data.success) {
            productos = data.productos || [];
        }
    } catch (error) {
        console.error('Error cargando productos:', error);
    }
}

async function loadTurnos() {
    try {
        const response = await fetch(baseUrl + '/api/catalogos.php?tipo=turnos');
        const data = await response.json();
        if (data.success) {
            turnos = data.turnos || [];
            
            const selectTurno = document.getElementById('id_turno');
            selectTurno.innerHTML = '<option value="">Seleccione...</option>';
            turnos.forEach(turno => {
                selectTurno.innerHTML += `<option value="${turno.id_turno}">
                    ${turno.nombre_turno} (${turno.hora_inicio.substring(0,5)}-${turno.hora_fin.substring(0,5)})
                </option>`;
            });
            
            const filtroTurno = document.getElementById('filtroTurno');
            filtroTurno.innerHTML = '<option value="">Todos los turnos</option>';
            turnos.forEach(turno => {
                filtroTurno.innerHTML += `<option value="${turno.id_turno}">
                    ${turno.nombre_turno}
                </option>`;
            });
        }
    } catch (error) {
        console.error('Error cargando turnos:', error);
    }
}

async function loadTejedores() {
    try {
        const response = await fetch(baseUrl + '/api/catalogos.php?tipo=tejedores');
        const data = await response.json();
        if (data.success) {
            tejedores = data.usuarios || [];
            
            const selectTejedor = document.getElementById('id_tejedor');
            selectTejedor.innerHTML = '<option value="">Sin asignar</option>';
            tejedores.forEach(tejedor => {
                selectTejedor.innerHTML += `<option value="${tejedor.id_usuario}">
                    ${tejedor.nombre_completo}
                </option>`;
            });
        }
    } catch (error) {
        console.error('Error cargando tejedores:', error);
    }
}

function renderProducciones(data) {
    const tbody = document.getElementById('bodyProducciones');
    
    if (!data || data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted" style="padding: 18px;">
                    <i class="fas fa-inbox"></i> No hay producciones registradas
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = data.map(prod => `
        <tr>
            <td style="padding: 5px 6px; font-size: 0.8rem;"><strong>${prod.codigo_lote}</strong></td>
            <td style="padding: 5px 6px; font-size: 0.8rem;"><small>${formatDate(prod.fecha_produccion)}</small></td>
            <td style="padding: 5px 6px; font-size: 0.8rem;">
                <span class="badge badge-info" style="font-size: 0.7rem; padding: 2px 5px;">
                    ${prod.nombre_turno}
                </span>
            </td>
            <td style="padding: 5px 6px; font-size: 0.8rem;"><small>${prod.nombre_tejedor || '<em class="text-muted">Sin asignar</em>'}</small></td>
            <td class="text-center" style="padding: 5px 6px; font-size: 0.8rem;">
                <span class="badge badge-secondary" style="font-size: 0.7rem; padding: 2px 5px;">${prod.num_maquinas || 0}</span>
            </td>
            <td style="padding: 5px 6px; font-size: 0.8rem;">
                <strong>${prod.total_docenas}|${prod.total_unidades}</strong>
                <small class="text-muted">(${prod.total_unidades_calc})</small>
            </td>
            <td class="text-center" style="padding: 5px 6px;">
                <button class="btn btn-info btn-sm" onclick="verDetalle(${prod.id_produccion})" 
                        title="Ver" style="padding: 2px 5px; font-size: 0.7rem;">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-warning btn-sm" onclick="editarProduccion(${prod.id_produccion})" 
                        title="Editar" style="padding: 2px 5px; font-size: 0.7rem;">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-danger btn-sm" onclick="eliminarProduccion(${prod.id_produccion})" 
                        title="Eliminar" style="padding: 2px 5px; font-size: 0.7rem;">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function renderDetalles() {
    const tbody = document.getElementById('bodyDetalles');
    
    if (detalles.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-muted" style="padding: 12px; font-size: 0.8rem;">
                    No hay máquinas. Click "Agregar" para comenzar.
                </td>
            </tr>
        `;
        actualizarTotales();
        return;
    }
    
    tbody.innerHTML = detalles.map((detalle, index) => {
        return `
            <tr>
                <td style="padding: 3px;">
                    <select onchange="cambiarMaquina(${index}, this.value)" required 
                            style="width: 100%; height: 26px; padding: 2px 4px; font-size: 0.75rem; border: 1px solid #ced4da;">
                        <option value="">Sel...</option>
                        ${maquinas.map(m => `
                            <option value="${m.id_maquina}" ${m.id_maquina == detalle.id_maquina ? 'selected' : ''}>
                                ${m.numero_maquina}
                            </option>
                        `).join('')}
                    </select>
                </td>
                <td style="padding: 3px;">
                    <select onchange="cambiarProducto(${index}, this.value)" required
                            style="width: 100%; height: 26px; padding: 2px 4px; font-size: 0.75rem; border: 1px solid #ced4da;">
                        <option value="">Seleccione...</option>
                        ${productos.map(p => `
                            <option value="${p.id_producto}" ${p.id_producto == detalle.id_producto ? 'selected' : ''}>
                                ${p.codigo_producto} - ${p.talla}
                            </option>
                        `).join('')}
                    </select>
                </td>
                <td style="padding: 3px;">
                    <input type="number" min="0" 
                           value="${detalle.docenas}" 
                           onchange="cambiarDocenas(${index}, this.value)" required
                           style="width: 100%; height: 26px; padding: 2px 4px; font-size: 0.75rem; border: 1px solid #ced4da;">
                </td>
                <td style="padding: 3px;">
                    <input type="number" min="0" max="11" 
                           value="${detalle.unidades}" 
                           onchange="cambiarUnidades(${index}, this.value)" required
                           style="width: 100%; height: 26px; padding: 2px 4px; font-size: 0.75rem; border: 1px solid #ced4da;">
                </td>
                <td class="text-center" style="padding: 3px;">
                    <small><strong>${detalle.total_unidades || 0}</strong></small>
                </td>
                <td class="text-center" style="padding: 3px;">
                    <button type="button" class="btn btn-danger btn-sm" onclick="eliminarDetalle(${index})" 
                            title="X" style="padding: 2px 4px; font-size: 0.7rem;">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
    
    actualizarTotales();
}

function actualizarTotales() {
    let totalUnidades = 0;
    detalles.forEach(detalle => {
        totalUnidades += (parseInt(detalle.docenas) || 0) * 12 + (parseInt(detalle.unidades) || 0);
    });
    
    const docenas = Math.floor(totalUnidades / 12);
    const unidades = totalUnidades % 12;
    
    document.getElementById('totalGeneral').textContent = `${docenas}|${unidades}`;
    document.getElementById('totalUnidadesGeneral').textContent = totalUnidades;
    document.getElementById('contadorMaquinas').textContent = `${detalles.length} máqs`;
}

function openModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Nueva Producción';
    document.getElementById('formProduccion').reset();
    document.getElementById('id_produccion').value = '';
    
    const hoy = new Date().toISOString().split('T')[0];
    document.getElementById('fecha_produccion').value = hoy;
    
    detalles = [];
    renderDetalles();
    
    document.getElementById('modalProduccion').classList.add('show');
}

function closeModal() {
    document.getElementById('modalProduccion').classList.remove('show');
}

async function editarProduccion(id) {
    try {
        const response = await fetch(baseUrl + `/api/produccion.php?id_produccion=${id}`);
        const data = await response.json();
        
        if (data.success) {
            const prod = data.produccion;
            
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Editar Producción';
            document.getElementById('id_produccion').value = prod.id_produccion;
            document.getElementById('codigo_lote').value = prod.codigo_lote;
            document.getElementById('fecha_produccion').value = prod.fecha_produccion;
            document.getElementById('id_turno').value = prod.id_turno;
            document.getElementById('id_tejedor').value = prod.id_tejedor || '';
            document.getElementById('observaciones').value = prod.observaciones || '';
            
            detalles = data.detalles.map(d => ({
                id_maquina: d.id_maquina,
                id_producto: d.id_producto,
                docenas: d.docenas,
                unidades: d.unidades,
                total_unidades: (d.docenas * 12) + d.unidades
            }));
            
            renderDetalles();
            document.getElementById('modalProduccion').classList.add('show');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al cargar producción', 'error');
    }
}

async function guardarProduccion(event) {
    event.preventDefault();
    
    if (detalles.length === 0) {
        showNotification('Debe agregar al menos una máquina', 'warning');
        return;
    }
    
    const id_produccion = document.getElementById('id_produccion').value;
    const produccionData = {
        id_produccion: id_produccion || null,
        codigo_lote: document.getElementById('codigo_lote').value,
        fecha_produccion: document.getElementById('fecha_produccion').value,
        id_turno: parseInt(document.getElementById('id_turno').value),
        id_tejedor: document.getElementById('id_tejedor').value || null,
        observaciones: document.getElementById('observaciones').value,
        detalles: detalles.map(d => ({
            id_maquina: parseInt(d.id_maquina),
            id_producto: parseInt(d.id_producto),
            docenas: parseInt(d.docenas) || 0,
            unidades: parseInt(d.unidades) || 0
        }))
    };
    
    try {
        const response = await fetch(baseUrl + '/api/produccion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(produccionData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            closeModal();
            loadProducciones();
        } else {
            showNotification('Error: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al guardar', 'error');
    }
}

async function eliminarProduccion(id) {
    if (!confirm('¿Eliminar esta producción?')) {
        return;
    }
    
    try {
        const response = await fetch(baseUrl + '/api/produccion.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_produccion: id })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            loadProducciones();
        } else {
            showNotification('Error: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al eliminar', 'error');
    }
}

function agregarMaquina() {
    detalles.push({
        id_maquina: '',
        id_producto: '',
        docenas: 0,
        unidades: 0,
        total_unidades: 0
    });
    renderDetalles();
}

function eliminarDetalle(index) {
    detalles.splice(index, 1);
    renderDetalles();
}

function cambiarMaquina(index, idMaquina) {
    detalles[index].id_maquina = idMaquina;
}

function cambiarProducto(index, idProducto) {
    detalles[index].id_producto = idProducto;
}

function cambiarDocenas(index, valor) {
    detalles[index].docenas = parseInt(valor) || 0;
    detalles[index].total_unidades = (detalles[index].docenas * 12) + (parseInt(detalles[index].unidades) || 0);
    renderDetalles();
}

function cambiarUnidades(index, valor) {
    let unidades = parseInt(valor) || 0;
    
    if (unidades > 11) {
        showNotification('Unidades máximo 11', 'warning');
        unidades = 11;
    }
    
    detalles[index].unidades = unidades;
    detalles[index].total_unidades = (parseInt(detalles[index].docenas) || 0) * 12 + unidades;
    renderDetalles();
}

function limpiarDetalles() {
    if (detalles.length > 0 && !confirm('¿Limpiar todos los detalles?')) {
        return;
    }
    detalles = [];
    renderDetalles();
}

async function importarPlanGenerico() {
    try {
        const response = await fetch(baseUrl + '/api/plan_generico.php?vigente=true');
        const data = await response.json();
        
        if (data.success && data.plan && data.detalles) {
            const detallesImportados = data.detalles
                .filter(d => d.accion === 'MANTENER')
                .map(d => ({
                    id_maquina: d.id_maquina,
                    id_producto: d.id_producto_actual,
                    docenas: 0,
                    unidades: 0,
                    total_unidades: 0
                }));
            
            if (detallesImportados.length > 0) {
                detalles = detallesImportados;
                renderDetalles();
                showNotification(`Importadas ${detallesImportados.length} máquinas`, 'success');
            } else {
                showNotification('No hay máquinas MANTENER', 'info');
            }
        } else {
            showNotification('No hay plan vigente', 'warning');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al importar', 'error');
    }
}

async function verDetalle(id) {
    document.getElementById('modalDetalle').classList.add('show');
    document.getElementById('detalleContenido').innerHTML = `
        <div class="text-center">
            <i class="fas fa-spinner fa-spin fa-3x"></i>
            <p>Cargando...</p>
        </div>
    `;
    
    try {
        const response = await fetch(baseUrl + `/api/produccion.php?id_produccion=${id}`);
        const data = await response.json();
        
        if (data.success) {
            const prod = data.produccion;
            const det = data.detalles;
            
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <h5>Información General</h5>
                        <table class="table table-bordered table-sm">
                            <tr>
                                <th width="120">Código:</th>
                                <td><strong>${prod.codigo_lote}</strong></td>
                            </tr>
                            <tr>
                                <th>Fecha:</th>
                                <td>${formatDate(prod.fecha_produccion)}</td>
                            </tr>
                            <tr>
                                <th>Turno:</th>
                                <td><span class="badge badge-info">${prod.nombre_turno}</span></td>
                            </tr>
                            <tr>
                                <th>Tejedor:</th>
                                <td>${prod.nombre_tejedor || '<em>Sin asignar</em>'}</td>
                            </tr>
                            <tr>
                                <th>Observ.:</th>
                                <td>${prod.observaciones || '<em>Ninguna</em>'}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>Resumen</h5>
                        <div class="alert alert-success">
                            <h4>Total: ${calcularTotal(det)}</h4>
                            <p class="mb-0">Máquinas: ${det.length}</p>
                        </div>
                    </div>
                </div>
                
                <h5 class="mt-3">Detalle por Máquina</h5>
                <table class="table table-bordered table-striped table-sm">
                    <thead class="thead-dark">
                        <tr>
                            <th>Máq.</th>
                            <th>Producto</th>
                            <th>Línea</th>
                            <th>Doc.</th>
                            <th>Unids.</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${det.map(d => `
                            <tr>
                                <td><strong>${d.numero_maquina}</strong></td>
                                <td><small>${d.codigo_producto} - ${d.descripcion_completa}</small></td>
                                <td><span class="badge badge-info">${d.nombre_linea}</span></td>
                                <td class="text-center">${d.docenas}</td>
                                <td class="text-center">${d.unidades}</td>
                                <td class="text-center"><strong>${d.total_unidades}</strong></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
            
            document.getElementById('detalleContenido').innerHTML = html;
        }
    } catch (error) {
        console.error('Error:', error);
        document.getElementById('detalleContenido').innerHTML = `
            <div class="alert alert-danger">
                Error al cargar detalle
            </div>
        `;
    }
}

function closeModalDetalle() {
    document.getElementById('modalDetalle').classList.remove('show');
}

function imprimirDetalle() {
    window.print();
}

function calcularTotal(detalles) {
    let totalUnidades = 0;
    detalles.forEach(d => {
        totalUnidades += d.total_unidades;
    });
    const docenas = Math.floor(totalUnidades / 12);
    const unidades = totalUnidades % 12;
    return `${docenas}|${unidades} (${totalUnidades} unids)`;
}

function limpiarFiltros() {
    document.getElementById('filtroFechaDesde').value = '';
    document.getElementById('filtroFechaHasta').value = '';
    document.getElementById('filtroTurno').value = '';
    loadProducciones();
}

function formatDate(dateString) {
    const date = new Date(dateString + 'T00:00:00');
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('es-ES', options);
}

function showNotification(message, type = 'info') {
    const alertClass = {
        'success': 'alert-success',
        'error': 'alert-danger',
        'warning': 'alert-warning',
        'info': 'alert-info'
    }[type] || 'alert-info';
    
    const notification = document.createElement('div');
    notification.className = `alert ${alertClass} alert-dismissible fade show`;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '280px';
    notification.innerHTML = `
        ${message}
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 4000);
}
</script>

<?php require_once '../../includes/footer.php'; ?>