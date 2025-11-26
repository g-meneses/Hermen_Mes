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

<!-- Modal Principal -->
<div id="modalProduccion" class="modal">
    <div class="modal-content" style="max-width: 950px; max-height: 95vh;">
        <div class="modal-header" style="padding: 10px 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-bottom: none;">
            <h3 id="modalTitle" style="margin: 0; font-size: 1.1rem; font-weight: 600;">
                <i class="fas fa-plus-circle"></i> Nueva Producción
            </h3>
            <button type="button" class="close" onclick="closeModal()" style="color: white; opacity: 1; font-size: 1.5rem; text-shadow: none;">&times;</button>
        </div>
        
        <div class="modal-body" style="padding: 15px; background: #f8f9fa;">
            <form id="formProduccion" onsubmit="guardarProduccion(event)">
                <input type="hidden" id="id_produccion">
                
                <!-- Datos Generales -->
                <div style="background: white; padding: 12px; border-radius: 8px; margin-bottom: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.08);">
                    <h6 style="margin: 0 0 10px 0; font-size: 0.9rem; color: #495057; border-bottom: 2px solid #e9ecef; padding-bottom: 5px;">
                        <i class="fas fa-info-circle"></i> Información General
                    </h6>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                        <div>
                            <label style="font-size: 0.75rem; font-weight: 600; color: #495057; display: block; margin-bottom: 3px;">
                                Código Lote <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="codigo_lote" required 
                                   style="width: 100%; height: 32px; padding: 4px 8px; font-size: 0.85rem; border: 1px solid #ced4da; border-radius: 4px;"
                                   placeholder="Ej: 241125-1">
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; font-weight: 600; color: #495057; display: block; margin-bottom: 3px;">
                                Fecha Producción <span class="text-danger">*</span>
                            </label>
                            <input type="date" id="fecha_produccion" required 
                                   style="width: 100%; height: 32px; padding: 4px 8px; font-size: 0.85rem; border: 1px solid #ced4da; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; font-weight: 600; color: #495057; display: block; margin-bottom: 3px;">
                                Turno <span class="text-danger">*</span>
                            </label>
                            <select id="id_turno" required 
                                    style="width: 100%; height: 32px; padding: 4px 8px; font-size: 0.85rem; border: 1px solid #ced4da; border-radius: 4px;">
                                <option value="">Seleccione turno...</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; font-weight: 600; color: #495057; display: block; margin-bottom: 3px;">
                                Tejedor
                            </label>
                            <select id="id_tejedor" 
                                    style="width: 100%; height: 32px; padding: 4px 8px; font-size: 0.85rem; border: 1px solid #ced4da; border-radius: 4px;">
                                <option value="">Sin asignar</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="margin-top: 10px;">
                        <label style="font-size: 0.75rem; font-weight: 600; color: #495057; display: block; margin-bottom: 3px;">
                            Observaciones
                        </label>
                        <textarea id="observaciones" rows="2" 
                                  style="width: 100%; padding: 6px 8px; font-size: 0.85rem; border: 1px solid #ced4da; border-radius: 4px; resize: vertical;"
                                  placeholder="Ej: Apagón de luz a las 10:30 am"></textarea>
                    </div>
                </div>

                <!-- Sección de Producción por Máquina -->
                <div style="background: white; padding: 12px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.08);">
                    <!-- Header con botones -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 2px solid #e9ecef; padding-bottom: 8px;">
                        <h6 style="margin: 0; font-size: 0.9rem; color: #495057;">
                            <i class="fas fa-cogs"></i> Producción por Máquina
                            <span class="badge badge-primary" id="contadorMaquinas" style="margin-left: 8px; font-size: 0.75rem;">0 máqs</span>
                        </h6>
                        <div style="display: flex; gap: 6px;">
                            <button type="button" class="btn btn-success btn-sm" onclick="agregarMaquina()" 
                                    style="padding: 4px 10px; font-size: 0.8rem; display: flex; align-items: center; gap: 4px;">
                                <i class="fas fa-plus"></i> Agregar
                            </button>
                            <!-- desabilitamos el boton de importar plan generico por el momento 
                             <button type="button" class="btn btn-info btn-sm" onclick="importarPlanGenerico()" 
                                    style="padding: 4px 10px; font-size: 0.8rem; display: flex; align-items: center; gap: 4px;">
                                <i class="fas fa-file-import"></i> Importar Plan
                            </button>
                            -->
                            <button type="button" class="btn btn-secondary btn-sm" onclick="importarUltimoRegistro()" 
                                    style="padding: 4px 10px; font-size: 0.8rem; display: flex; align-items: center; gap: 4px;">
                                <i class="fas fa-history"></i> Último Registro
                            </button>
                            <button type="button" class="btn btn-warning btn-sm" onclick="limpiarDetalles()" 
                                    style="padding: 4px 10px; font-size: 0.8rem; display: flex; align-items: center; gap: 4px;">
                                <i class="fas fa-trash"></i> Limpiar
                            </button>
                        </div>
                    </div>

                    <!-- Tabla de detalles con scroll -->
                    <div style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px;">
                        <table class="table table-sm table-hover" style="margin: 0; font-size: 0.85rem;">
                            <thead style="background: #495057; color: white; position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th style="width: 130px; padding: 8px; font-size: 0.8rem; border: none;">Máquina</th>
                                    <th style="padding: 8px; font-size: 0.8rem; border: none;">Producto</th>
                                    <th style="width: 100px; padding: 8px; font-size: 0.8rem; text-align: center; border: none;">Docenas</th>
                                    <th style="width: 100px; padding: 8px; font-size: 0.8rem; text-align: center; border: none;">Unidades</th>
                                    <th style="width: 100px; padding: 8px; font-size: 0.8rem; text-align: center; border: none;">Acción</th>
                                    
                                </tr>
                            </thead>
                            <tbody id="bodyDetalles">
                                <tr>
                                    <td colspan="6" class="text-center text-muted" style="padding: 20px; font-size: 0.85rem;">
                                        <i class="fas fa-inbox fa-2x mb-2" style="opacity: 0.3;"></i><br>
                                        No hay máquinas agregadas. Click en "Agregar" o "Importar Plan" para comenzar.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Nota informativa -->
                    <div style="background: #e7f3ff; border-left: 3px solid #0056b3; padding: 8px 12px; margin-top: 10px; border-radius: 4px;">
                        <small style="font-size: 0.75rem; color: #004085;">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Formato:</strong> Unidades deben estar entre 0-11. Total se calcula automáticamente.
                        </small>
                    </div>

                    <!-- Total general -->
                    <div style="display: flex; justify-content: flex-end; align-items: center; margin-top: 12px; padding: 10px; background: #f8f9fa; border-radius: 4px; padding-right: 180px;">
                        <span style="font-size: 1.4rem; font-weight: 600; color: #495057;">
                            Total General: 
                        </span>
                        <span class="badge badge-success" id="totalGeneral" style="font-size: 1.4rem !important; padding: 10px 20px !important; margin-left: 10px; font-weight: bold;">0|0</span>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="modal-footer" style="padding: 10px 15px; background: #f8f9fa; border-top: 1px solid #dee2e6;">
            <button type="button" class="btn btn-secondary" onclick="closeModal()" style="padding: 6px 16px; font-size: 0.9rem;">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="submit" form="formProduccion" class="btn btn-primary" style="padding: 6px 16px; font-size: 0.9rem;">
                <i class="fas fa-save"></i> Guardar Producción
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
let maquinasOperativas = [];
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
        console.log('✓ Todos los datos cargados');
        console.log(`  - Máquinas totales: ${maquinas.length}`);
        console.log(`  - Máquinas operativas: ${maquinasOperativas.length}`);
        console.log(`  - Productos: ${productos.length}`);
        console.log(`  - Turnos: ${turnos.length}`);
        console.log(`  - Tejedores: ${tejedores.length}`);
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
            // Filtrar solo máquinas operativas (minúsculas como está en tu BD)
            maquinasOperativas = maquinas.filter(m => m.estado === 'operativa');
            console.log(`✓ ${maquinasOperativas.length} máquinas operativas de ${maquinas.length} totales`);
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
        
        console.log('Respuesta tejedores:', data);

        if (data.success) {
            tejedores = data.usuarios || [];
            console.log(`✓ ${tejedores.length} tejedores cargados`);
            
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

// =============================================
// FUNCIÓN renderDetalles() CORREGIDA
// =============================================
function renderDetalles() {
    const tbody = document.getElementById('bodyDetalles');
    
    if (detalles.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-muted" style="padding: 20px; font-size: 0.85rem;">
                    <i class="fas fa-inbox fa-2x mb-2" style="opacity: 0.3;"></i><br>
                    No hay máquinas agregadas. Click en "Agregar" o "Importar Plan" para comenzar.
                </td>
            </tr>
        `;
        actualizarTotales();
        return;
    }
    
    tbody.innerHTML = detalles.map((detalle, index) => {
        // Generar opciones de máquinas
        const opcionesMaquinas = maquinasOperativas.map(m => 
            `<option value="${m.id_maquina}" ${m.id_maquina == detalle.id_maquina ? 'selected' : ''}>${m.numero_maquina}</option>`
        ).join('');
        
        // Generar opciones de productos
        const opcionesProductos = productos.map(p => 
            `<option value="${p.id_producto}" ${p.id_producto == detalle.id_producto ? 'selected' : ''}>${p.codigo_producto} - ${p.talla}</option>`
        ).join('');
        
        return `
            <tr style="background: ${index % 2 === 0 ? '#ffffff' : '#f8f9fa'};">
                <td style="padding: 6px;">
                    <select onchange="cambiarMaquina(${index}, this.value)" required 
                            style="width: 100%; height: 30px; padding: 4px 8px; font-size: 0.8rem; border: 1px solid #ced4da; border-radius: 4px;">
                        <option value="">Seleccione...</option>
                        ${opcionesMaquinas}
                    </select>
                </td>
                <td style="padding: 6px;">
                    <select onchange="cambiarProducto(${index}, this.value)" required
                            style="width: 100%; height: 30px; padding: 4px 8px; font-size: 0.8rem; border: 1px solid #ced4da; border-radius: 4px;">
                        <option value="">Seleccione producto...</option>
                        ${opcionesProductos}
                    </select>
                </td>
                <td style="padding: 6px;">
                    <input type="number" min="0" 
                           value="${detalle.docenas}" 
                           onchange="cambiarDocenas(${index}, this.value)" 
                           required
                           style="width: 100%; height: 30px; padding: 4px 8px; font-size: 0.85rem; border: 1px solid #ced4da; border-radius: 4px; text-align: center;">
                </td>
                <td style="padding: 6px;">
                    <input type="number" min="0" max="11" 
                           value="${detalle.unidades}" 
                           onchange="cambiarUnidades(${index}, this.value)" 
                           required
                           style="width: 100%; height: 30px; padding: 4px 8px; font-size: 0.85rem; border: 1px solid #ced4da; border-radius: 4px; text-align: center;">
                </td>
                
                <td style="padding: 6px; text-align: center; vertical-align: middle;">
                    <button type="button" class="btn btn-danger btn-sm" onclick="eliminarDetalle(${index})" 
                            title="Eliminar" style="padding: 4px 8px; font-size: 0.75rem;">
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
    document.getElementById('contadorMaquinas').textContent = `${detalles.length} máqs`;
}

async function openModal() {
    if (maquinasOperativas.length === 0 || productos.length === 0 || turnos.length === 0) {
        showNotification('Cargando datos...', 'info');
        await Promise.all([
            loadMaquinas(),
            loadProductos(),
            loadTurnos(),
            loadTejedores()
        ]);
    }
    
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Nueva Producción';
    document.getElementById('formProduccion').reset();
    document.getElementById('id_produccion').value = '';
    
    const hoy = new Date().toISOString().split('T')[0];
    document.getElementById('fecha_produccion').value = hoy;
    
    // Generar código de lote automático
    const fecha = new Date();
    const codigoLote = `${fecha.getDate().toString().padStart(2,'0')}${(fecha.getMonth()+1).toString().padStart(2,'0')}${fecha.getFullYear().toString().slice(-2)}-1`;
    document.getElementById('codigo_lote').value = codigoLote;
    
    // Pre-cargar TODAS las máquinas operativas
    detalles = maquinasOperativas.map(m => ({
        id_maquina: m.id_maquina,
        id_producto: '',
        docenas: 0,
        unidades: 0,
        total_unidades: 0
    }));
    
    renderDetalles();
    showNotification(`✓ ${maquinasOperativas.length} máquinas operativas cargadas`, 'success');
    
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
    
    // Filtrar solo detalles con máquina y producto asignados
    const detallesValidos = detalles.filter(d => d.id_maquina && d.id_producto);
    
    if (detallesValidos.length === 0) {
        showNotification('Debe asignar producto a al menos una máquina', 'warning');
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
        detalles: detallesValidos.map(d => ({
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
    if (!confirm('¿Eliminar esta producción? Esta acción también revertirá el inventario.')) {
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
        showNotification('Unidades máximo 11 (se convierte a docenas)', 'warning');
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


// =============================================
// IMPORTAR ÚLTIMO REGISTRO
// =============================================
async function importarUltimoRegistro() {
    try {
        showNotification('Buscando último registro...', 'info');
        
        const response = await fetch(baseUrl + '/api/produccion.php?ultimo=true');
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Respuesta último registro:', data);
        
        if (data.success && data.produccion && data.detalles && data.detalles.length > 0) {
            // Importar turno y tejedor
            document.getElementById('id_turno').value = data.produccion.id_turno || '';
            document.getElementById('id_tejedor').value = data.produccion.id_tejedor || '';
            
            // Importar detalles de máquinas (con cantidad en 0 para nuevo registro)
            detalles = data.detalles
                .filter(d => {
                    const maquina = maquinasOperativas.find(m => m.id_maquina == d.id_maquina);
                    return maquina !== undefined;
                })
                .map(d => ({
                    id_maquina: d.id_maquina,
                    id_producto: d.id_producto,
                    docenas: 0,
                    unidades: 0,
                    total_unidades: 0
                }));
            
            renderDetalles();
            showNotification(`✓ Importadas ${detalles.length} máquinas del registro ${data.produccion.codigo_lote}`, 'success');
        } else {
            showNotification('No hay registros previos para importar', 'warning');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al importar: ' + error.message, 'error');
    }
}

async function verDetalle(id) {
    document.getElementById('modalDetalle').classList.add('show');
    document.getElementById('detalleContenido').innerHTML = `
        <div class="text-center" style="padding: 40px;">
            <i class="fas fa-spinner fa-spin fa-3x" style="color: #667eea;"></i>
            <p style="margin-top: 15px; color: #666;">Cargando detalle...</p>
        </div>
    `;
    
    try {
        const response = await fetch(baseUrl + `/api/produccion.php?id_produccion=${id}`);
        const data = await response.json();
        
        if (data.success) {
            const prod = data.produccion;
            const det = data.detalles;
            
            // Calcular totales
            let totalUnidades = 0;
            det.forEach(d => {
                totalUnidades += (d.docenas * 12) + d.unidades;
            });
            const totalDocenas = Math.floor(totalUnidades / 12);
            const totalUnidadesResto = totalUnidades % 12;
            
            let html = `
                <div id="contenidoImprimible">
                    <!-- Header para impresión -->
                    <div class="solo-impresion" style="display: none; text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 15px;">
                        <h2 style="margin: 0; font-size: 18px;">HERMEN LTDA.</h2>
                        <p style="margin: 5px 0; font-size: 12px;">Sistema MES de Producción</p>
                        <h3 style="margin: 10px 0 0 0; font-size: 16px;">REGISTRO DE PRODUCCIÓN DE TEJEDURÍA</h3>
                    </div>
                    
                    <!-- Card Principal con 2 columnas -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                        
                        <!-- Columna Izquierda: Información General -->
                        <div style="background: #f8f9fa; border-radius: 8px; padding: 15px; border: 1px solid #e0e0e0;">
                            <h6 style="margin: 0 0 12px 0; font-size: 0.9rem; color: #495057; border-bottom: 2px solid #667eea; padding-bottom: 8px;">
                                <i class="fas fa-info-circle" style="color: #667eea;"></i> Información General
                            </h6>
                            <table style="width: 100%; font-size: 0.85rem;">
                                <tr>
                                    <td style="padding: 6px 0; font-weight: 600; color: #666; width: 100px;">Código:</td>
                                    <td style="padding: 6px 0;"><strong style="font-size: 1rem; color: #333;">${prod.codigo_lote}</strong></td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-weight: 600; color: #666;">Fecha:</td>
                                    <td style="padding: 6px 0;">${formatDate(prod.fecha_produccion)}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-weight: 600; color: #666;">Turno:</td>
                                    <td style="padding: 6px 0;">
                                        <span class="badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 4px 12px; font-size: 0.8rem;">
                                            ${prod.nombre_turno}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-weight: 600; color: #666;">Tejedor:</td>
                                    <td style="padding: 6px 0;">${prod.nombre_tejedor || '<em style="color: #999;">Sin asignar</em>'}</td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Columna Derecha: Resumen -->
                        <div style="background: linear-gradient(135deg, #585958ff 0%, #929393ff 100%); border-radius: 8px; padding: 15px; color: white; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                            <h6 style="margin: 0 0 10px 0; font-size: 0.85rem; opacity: 0.9;">
                                <i class="fas fa-chart-bar"></i> PRODUCCIÓN TOTAL
                            </h6>
                            <div style="font-size: 2.5rem; font-weight: bold; line-height: 1;">
                                ${totalDocenas}|${totalUnidadesResto}
                            </div>
                            <div style="font-size: 0.9rem; opacity: 0.9; margin-top: 5px;">
                                ${totalUnidades} unidades
                            </div>
                            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.3); width: 100%; text-align: center;">
                                <i class="fas fa-cogs"></i> ${det.length} máquinas registradas
                            </div>
                        </div>
                    </div>
                    
                    <!-- Observaciones (debajo del card) -->
                    <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 10px 15px; margin-bottom: 15px; ${!prod.observaciones ? 'display: none;' : ''}">
                        <strong style="color: #856404;"><i class="fas fa-sticky-note"></i> Observaciones:</strong>
                        <span style="color: #856404; margin-left: 8px;">${prod.observaciones || ''}</span>
                    </div>
                    
                    <!-- Tabla de Detalle -->
                    <div style="border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden;">
                        <div style="background: #495057; color: white; padding: 10px 15px; font-size: 0.9rem; font-weight: 600;">
                            <i class="fas fa-list"></i> Detalle por Máquina
                        </div>
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                            <thead>
                                <tr style="background: #f1f3f4;">
                                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6; width: 70px;">Máq.</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Producto</th>
                                    <th style="padding: 10px; text-align: center; border-bottom: 2px solid #dee2e6; width: 90px;">Línea</th>
                                    <th style="padding: 10px; text-align: center; border-bottom: 2px solid #dee2e6; width: 70px;">Doc.</th>
                                    <th style="padding: 10px; text-align: center; border-bottom: 2px solid #dee2e6; width: 70px;">Unids.</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${det.map((d, index) => `
                                    <tr style="background: ${index % 2 === 0 ? '#ffffff' : '#f8f9fa'}; border-bottom: 1px solid #eee;">
                                        <td style="padding: 8px 10px; font-weight: bold; color: #333;">${d.numero_maquina}</td>
                                        <td style="padding: 8px 10px;">
                                            <span style="color: #666; font-size: 0.8rem;">${d.codigo_producto}</span><br>
                                            <span style="color: #333;">${d.descripcion_completa}</span>
                                        </td>
                                        <td style="padding: 8px 10px; text-align: center;">
                                            <span class="badge" style="background: ${getColorLinea(d.codigo_linea)}; color: white; padding: 3px 8px; font-size: 0.75rem;">
                                                ${d.nombre_linea}
                                            </span>
                                        </td>
                                        <td style="padding: 8px 10px; text-align: center; font-weight: 600; font-size: 0.95rem;">${d.docenas}</td>
                                        <td style="padding: 8px 10px; text-align: center; font-weight: 600; font-size: 0.95rem;">${d.unidades}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                            <tfoot>
                                <tr style="background: #e9ecef; font-weight: bold;">
                                    <td colspan="3" style="padding: 10px; text-align: right;">TOTAL:</td>
                                    <td style="padding: 10px; text-align: center; font-size: 1rem; color: #28a745;">${totalDocenas}</td>
                                    <td style="padding: 10px; text-align: center; font-size: 1rem; color: #28a745;">${totalUnidadesResto}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <!-- Footer para impresión -->
                    <div class="solo-impresion" style="display: none; margin-top: 30px; padding-top: 15px; border-top: 1px solid #ccc;">
                        <div style="display: flex; justify-content: space-between; font-size: 11px; color: #666;">
                            <div>Impreso: ${new Date().toLocaleString('es-BO')}</div>
                            <div>Sistema MES Hermen v1.5.1</div>
                        </div>
                        <div style="margin-top: 40px; display: flex; justify-content: space-around;">
                            <div style="text-align: center;">
                                <div style="border-top: 1px solid #333; width: 150px; margin: 0 auto;"></div>
                                <div style="font-size: 11px; margin-top: 5px;">Tejedor</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="border-top: 1px solid #333; width: 150px; margin: 0 auto;"></div>
                                <div style="font-size: 11px; margin-top: 5px;">Supervisor</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('detalleContenido').innerHTML = html;
        } else {
            document.getElementById('detalleContenido').innerHTML = `
                <div class="alert alert-danger" style="margin: 20px;">
                    <i class="fas fa-exclamation-circle"></i> Error al cargar el detalle
                </div>
            `;
        }
    } catch (error) {
        console.error('Error:', error);
        document.getElementById('detalleContenido').innerHTML = `
            <div class="alert alert-danger" style="margin: 20px;">
                <i class="fas fa-exclamation-circle"></i> Error de conexión
            </div>
        `;
    }
}

// Función auxiliar para colores de línea
function getColorLinea(codigoLinea) {
    const colores = {
        'LUJO': '#6f42c1',
        'LY20': '#007bff',
        'LY40': '#17a2b8',
        'STRETCH': '#28a745',
        'CAM': '#fd7e14'
    };
    return colores[codigoLinea] || '#6c757d';
}

// =============================================
// REEMPLAZAR LA FUNCIÓN imprimirDetalle()
// =============================================

function imprimirDetalle() {
    const contenido = document.getElementById('contenidoImprimible');
    if (!contenido) {
        showNotification('No hay contenido para imprimir', 'error');
        return;
    }
    
    // Crear ventana de impresión
    const ventanaImpresion = window.open('', '_blank', 'width=800,height=600');
    
    ventanaImpresion.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Registro de Producción - Hermen Ltda.</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    line-height: 1.4;
                    color: #333;
                    padding: 15px;
                }
                
                .header-empresa {
                    text-align: center;
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #333;
                }
                
                .header-empresa h1 {
                    font-size: 20px;
                    margin-bottom: 5px;
                }
                
                .header-empresa p {
                    font-size: 11px;
                    color: #666;
                }
                
                .header-empresa h2 {
                    font-size: 16px;
                    margin-top: 10px;
                    color: #444;
                }
                
                .info-grid {
                    display: flex;
                    gap: 20px;
                    margin-bottom: 15px;
                }
                
                .info-general {
                    flex: 1;
                    border: 1px solid #ccc;
                    border-radius: 5px;
                    padding: 12px;
                }
                
                .info-general h3 {
                    font-size: 12px;
                    border-bottom: 1px solid #ccc;
                    padding-bottom: 5px;
                    margin-bottom: 10px;
                }
                
                .info-general table {
                    width: 100%;
                }
                
                .info-general td {
                    padding: 4px 0;
                }
                
                .info-general td:first-child {
                    font-weight: bold;
                    width: 80px;
                    color: #666;
                }
                
                .resumen {
                    flex: 1;
                    border: 2px solid #28a745;
                    border-radius: 5px;
                    padding: 12px;
                    text-align: center;
                    background: #f8fff8;
                }
                
                .resumen h3 {
                    font-size: 11px;
                    color: #28a745;
                    margin-bottom: 8px;
                }
                
                .resumen .total-grande {
                    font-size: 32px;
                    font-weight: bold;
                    color: #28a745;
                }
                
                .resumen .total-unidades {
                    font-size: 12px;
                    color: #666;
                }
                
                .resumen .maquinas {
                    font-size: 11px;
                    margin-top: 8px;
                    padding-top: 8px;
                    border-top: 1px solid #ccc;
                }
                
                .observaciones {
                    background: #fff9e6;
                    border: 1px solid #ffc107;
                    border-radius: 5px;
                    padding: 10px;
                    margin-bottom: 15px;
                    font-size: 11px;
                }
                
                .tabla-detalle {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                
                .tabla-detalle th {
                    background: #495057;
                    color: white;
                    padding: 8px;
                    text-align: left;
                    font-size: 11px;
                }
                
                .tabla-detalle th:nth-child(4),
                .tabla-detalle th:nth-child(5) {
                    text-align: center;
                }
                
                .tabla-detalle td {
                    padding: 6px 8px;
                    border-bottom: 1px solid #ddd;
                }
                
                .tabla-detalle td:nth-child(4),
                .tabla-detalle td:nth-child(5) {
                    text-align: center;
                    font-weight: bold;
                }
                
                .tabla-detalle tr:nth-child(even) {
                    background: #f9f9f9;
                }
                
                .tabla-detalle tfoot td {
                    background: #e9ecef;
                    font-weight: bold;
                    padding: 10px 8px;
                }
                
                .badge-linea {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 3px;
                    font-size: 10px;
                    color: white;
                    background: #6c757d;
                }
                
                .footer-impresion {
                    margin-top: 30px;
                    padding-top: 15px;
                    border-top: 1px solid #ccc;
                }
                
                .footer-info {
                    display: flex;
                    justify-content: space-between;
                    font-size: 10px;
                    color: #666;
                    margin-bottom: 30px;
                }
                
                .firmas {
                    display: flex;
                    justify-content: space-around;
                    margin-top: 40px;
                }
                
                .firma {
                    text-align: center;
                }
                
                .firma .linea {
                    border-top: 1px solid #333;
                    width: 150px;
                    margin: 0 auto;
                }
                
                .firma .cargo {
                    font-size: 10px;
                    margin-top: 5px;
                }
                
                @media print {
                    body {
                        padding: 0;
                    }
                }
            </style>
        </head>
        <body>
            ${generarContenidoImpresion()}
        </body>
        </html>
    `);
    
    ventanaImpresion.document.close();
    
    // Esperar a que cargue y luego imprimir
    setTimeout(() => {
        ventanaImpresion.print();
    }, 250);
}

// Función auxiliar para generar contenido de impresión
function generarContenidoImpresion() {
    const contenido = document.getElementById('contenidoImprimible');
    if (!contenido) return '';
    
    // Obtener datos del DOM actual
    const codigo = contenido.querySelector('strong[style*="font-size: 1rem"]')?.textContent || '';
    const filas = contenido.querySelectorAll('tbody tr');
    const totalText = contenido.querySelector('div[style*="font-size: 2.5rem"]')?.textContent || '0|0';
    const unidadesText = contenido.querySelector('div[style*="font-size: 0.9rem"]')?.textContent || '0 unidades';
    const maquinasText = contenido.querySelector('div[style*="border-top: 1px solid rgba"]')?.textContent || '0 máquinas';
    
    // Obtener info general
    const infoTable = contenido.querySelector('table[style*="width: 100%"]');
    const infoRows = infoTable?.querySelectorAll('tr') || [];
    
    let fecha = '', turno = '', tejedor = '';
    infoRows.forEach(row => {
        const label = row.querySelector('td:first-child')?.textContent || '';
        const value = row.querySelector('td:last-child')?.textContent || '';
        if (label.includes('Fecha')) fecha = value;
        if (label.includes('Turno')) turno = value.trim();
        if (label.includes('Tejedor')) tejedor = value;
    });
    
    // Obtener observaciones
    const obsDiv = contenido.querySelector('div[style*="background: #fff3cd"]');
    const observaciones = obsDiv ? obsDiv.querySelector('span')?.textContent || '' : '';
    
    // Construir filas de tabla
    let filasHTML = '';
    filas.forEach(fila => {
        const celdas = fila.querySelectorAll('td');
        if (celdas.length >= 5) {
            const maquina = celdas[0]?.textContent || '';
            const producto = celdas[1]?.textContent?.trim().replace(/\s+/g, ' ') || '';
            const linea = celdas[2]?.textContent?.trim() || '';
            const docenas = celdas[3]?.textContent || '0';
            const unidades = celdas[4]?.textContent || '0';
            
            filasHTML += `
                <tr>
                    <td>${maquina}</td>
                    <td>${producto}</td>
                    <td><span class="badge-linea">${linea}</span></td>
                    <td>${docenas}</td>
                    <td>${unidades}</td>
                </tr>
            `;
        }
    });
    
    // Obtener totales del footer
    const footerCeldas = contenido.querySelectorAll('tfoot td');
    const totalDoc = footerCeldas[1]?.textContent || '0';
    const totalUni = footerCeldas[2]?.textContent || '0';
    
    return `
        <div class="header-empresa">
            <h1>HERMEN LTDA.</h1>
            <p>Textiles de Poliamida - La Paz, Bolivia</p>
            <h2>REGISTRO DE PRODUCCIÓN DE TEJEDURÍA</h2>
        </div>
        
        <div class="info-grid">
            <div class="info-general">
                <h3>📋 Información General</h3>
                <table>
                    <tr><td>Código:</td><td><strong>${codigo}</strong></td></tr>
                    <tr><td>Fecha:</td><td>${fecha}</td></tr>
                    <tr><td>Turno:</td><td>${turno}</td></tr>
                    <tr><td>Tejedor:</td><td>${tejedor}</td></tr>
                </table>
            </div>
            <div class="resumen">
                <h3>📊 PRODUCCIÓN TOTAL</h3>
                <div class="total-grande">${totalText}</div>
                <div class="total-unidades">${unidadesText}</div>
                <div class="maquinas">${maquinasText}</div>
            </div>
        </div>
        
        ${observaciones ? `<div class="observaciones"><strong>📝 Observaciones:</strong> ${observaciones}</div>` : ''}
        
        <table class="tabla-detalle">
            <thead>
                <tr>
                    <th style="width: 60px;">Máq.</th>
                    <th>Producto</th>
                    <th style="width: 80px; text-align: center;">Línea</th>
                    <th style="width: 60px;">Doc.</th>
                    <th style="width: 60px;">Unids.</th>
                </tr>
            </thead>
            <tbody>
                ${filasHTML}
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align: right;">TOTAL:</td>
                    <td style="text-align: center;">${totalDoc}</td>
                    <td style="text-align: center;">${totalUni}</td>
                </tr>
            </tfoot>
        </table>
        
        <div class="footer-impresion">
            <div class="footer-info">
                <div>Impreso: ${new Date().toLocaleString('es-BO')}</div>
                <div>Sistema MES Hermen v1.5.1</div>
            </div>
            <div class="firmas">
                <div class="firma">
                    <div class="linea"></div>
                    <div class="cargo">Tejedor</div>
                </div>
                <div class="firma">
                    <div class="linea"></div>
                    <div class="cargo">Supervisor</div>
                </div>
            </div>
        </div>
    `;
}

// Función auxiliar para colores de línea
function getColorLinea(codigoLinea) {
    const colores = {
        'LUJO': '#6f42c1',
        'LY20': '#007bff',
        'LY40': '#17a2b8',
        'STRETCH': '#28a745',
        'CAM': '#fd7e14'
    };
    return colores[codigoLinea] || '#6c757d';
}

function closeModalDetalle() {
    document.getElementById('modalDetalle').classList.remove('show');
}

function imprimirDetalle() {
    const contenido = document.getElementById('contenidoImprimible');
    if (!contenido) {
        showNotification('No hay contenido para imprimir', 'error');
        return;
    }
    
    // Crear ventana de impresión
    const ventanaImpresion = window.open('', '_blank', 'width=800,height=600');
    
    ventanaImpresion.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Registro de Producción - Hermen Ltda.</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    line-height: 1.4;
                    color: #333;
                    padding: 15px;
                }
                
                .header-empresa {
                    text-align: center;
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #333;
                }
                
                .header-empresa h1 {
                    font-size: 20px;
                    margin-bottom: 5px;
                }
                
                .header-empresa p {
                    font-size: 11px;
                    color: #666;
                }
                
                .header-empresa h2 {
                    font-size: 16px;
                    margin-top: 10px;
                    color: #444;
                }
                
                .info-grid {
                    display: flex;
                    gap: 20px;
                    margin-bottom: 15px;
                }
                
                .info-general {
                    flex: 1;
                    border: 1px solid #ccc;
                    border-radius: 5px;
                    padding: 12px;
                }
                
                .info-general h3 {
                    font-size: 12px;
                    border-bottom: 1px solid #ccc;
                    padding-bottom: 5px;
                    margin-bottom: 10px;
                }
                
                .info-general table {
                    width: 100%;
                }
                
                .info-general td {
                    padding: 4px 0;
                }
                
                .info-general td:first-child {
                    font-weight: bold;
                    width: 80px;
                    color: #666;
                }
                
                .resumen {
                    flex: 1;
                    border: 2px solid #28a745;
                    border-radius: 5px;
                    padding: 12px;
                    text-align: center;
                    background: #f8fff8;
                }
                
                .resumen h3 {
                    font-size: 11px;
                    color: #28a745;
                    margin-bottom: 8px;
                }
                
                .resumen .total-grande {
                    font-size: 32px;
                    font-weight: bold;
                    color: #28a745;
                }
                
                .resumen .total-unidades {
                    font-size: 12px;
                    color: #666;
                }
                
                .resumen .maquinas {
                    font-size: 11px;
                    margin-top: 8px;
                    padding-top: 8px;
                    border-top: 1px solid #ccc;
                }
                
                .observaciones {
                    background: #fff9e6;
                    border: 1px solid #ffc107;
                    border-radius: 5px;
                    padding: 10px;
                    margin-bottom: 15px;
                    font-size: 11px;
                }
                
                .tabla-detalle {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                
                .tabla-detalle th {
                    background: #495057;
                    color: white;
                    padding: 8px;
                    text-align: left;
                    font-size: 11px;
                }
                
                .tabla-detalle th:nth-child(4),
                .tabla-detalle th:nth-child(5) {
                    text-align: center;
                }
                
                .tabla-detalle td {
                    padding: 6px 8px;
                    border-bottom: 1px solid #ddd;
                }
                
                .tabla-detalle td:nth-child(4),
                .tabla-detalle td:nth-child(5) {
                    text-align: center;
                    font-weight: bold;
                }
                
                .tabla-detalle tr:nth-child(even) {
                    background: #f9f9f9;
                }
                
                .tabla-detalle tfoot td {
                    background: #e9ecef;
                    font-weight: bold;
                    padding: 10px 8px;
                }
                
                .badge-linea {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 3px;
                    font-size: 10px;
                    color: white;
                    background: #6c757d;
                }
                
                .footer-impresion {
                    margin-top: 30px;
                    padding-top: 15px;
                    border-top: 1px solid #ccc;
                }
                
                .footer-info {
                    display: flex;
                    justify-content: space-between;
                    font-size: 10px;
                    color: #666;
                    margin-bottom: 30px;
                }
                
                .firmas {
                    display: flex;
                    justify-content: space-around;
                    margin-top: 40px;
                }
                
                .firma {
                    text-align: center;
                }
                
                .firma .linea {
                    border-top: 1px solid #333;
                    width: 150px;
                    margin: 0 auto;
                }
                
                .firma .cargo {
                    font-size: 10px;
                    margin-top: 5px;
                }
                
                @media print {
                    body {
                        padding: 0;
                    }
                }
            </style>
        </head>
        <body>
            ${generarContenidoImpresion()}
        </body>
        </html>
    `);
    
    ventanaImpresion.document.close();
    
    // Esperar a que cargue y luego imprimir
    setTimeout(() => {
        ventanaImpresion.print();
    }, 250);
}

function calcularTotal(detalles) {
    let totalUnidades = 0;
    detalles.forEach(d => {
        totalUnidades += (d.docenas * 12) + d.unidades;
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