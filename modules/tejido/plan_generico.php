<?php
/**
 * Plan Genérico de Tejido
 * Sistema MES Hermen Ltda.
 */

require_once '../../config/database.php';

// Verificar sesión
if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Plan Genérico de Tejido';
$currentPage = 'plan_generico';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Header con título y botones -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="mb-1"><i class="fas fa-clipboard-list"></i> Plan Genérico de Tejido</h3>
                <p class="text-muted mb-0">Define qué producto teje cada máquina de manera permanente</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="showNuevoPlanModal()">
                    <i class="fas fa-plus"></i> Nuevo Plan
                </button>
                <button class="btn btn-success" onclick="imprimirPlan()" id="btnImprimir" style="display: none;">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <button class="btn btn-outline-secondary" onclick="showHistorialModal()">
                    <i class="fas fa-history"></i> Historial
                </button>
            </div>
        </div>
    </div>

    <!-- Información del Plan Actual -->
    <div id="planActualCard" class="card mb-4" style="display: none;">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Código:</strong> <span id="planCodigo">-</span>
                </div>
                <div class="col-md-3">
                    <strong>Vigencia desde:</strong> <span id="planFechaInicio">-</span>
                </div>
                <div class="col-md-3">
                    <strong>Creado por:</strong> <span id="planCreador">-</span>
                </div>
                <div class="col-md-3">
                    <strong>Estado:</strong> <span id="planEstado" class="badge badge-success">VIGENTE</span>
                </div>
            </div>
            <div class="row mt-2" id="planObservacionesRow" style="display: none;">
                <div class="col-md-12">
                    <strong>Observaciones:</strong> <span id="planObservaciones">-</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Sin Plan Activo -->
    <div id="sinPlanCard" class="card text-center" style="display: none;">
        <div class="card-body py-5">
            <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
            <h4>No hay un plan genérico activo</h4>
            <p class="text-muted">Crea un nuevo plan para comenzar a asignar productos a las máquinas</p>
            <button class="btn btn-primary btn-lg" onclick="showNuevoPlanModal()">
                <i class="fas fa-plus"></i> Crear Primer Plan
            </button>
        </div>
    </div>

    <!-- Tabla de Asignaciones -->
    <div id="tablaMaquinasCard" class="card" style="display: none;">
        <div class="card-header">
            <h5 class="mb-0">Asignaciones por Máquina</h5>
        </div>
        <div class="card-body">
            <!-- Filtros -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <input type="text" id="searchMaquina" class="form-control" placeholder="Buscar por número de máquina...">
                </div>
                <div class="col-md-3">
                    <select id="filterAccion" class="form-control">
                        <option value="">Todas las acciones</option>
                        <option value="mantener">MANTENER</option>
                        <option value="cambiar">CAMBIAR</option>
                        <option value="parar">PARAR</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="filterLinea" class="form-control">
                        <option value="">Todas las líneas</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-success btn-block" onclick="showAsignarModal()">
                        <i class="fas fa-plus"></i> Asignar Máquina
                    </button>
                </div>
            </div>

            <!-- Tabla -->
            <div class="table-container">
                <table class="table table-bordered table-hover" id="tablaMaquinas">
                    <thead class="thead-light">
                        <tr>
                            <th width="8%">Máquina</th>
                            <th width="12%">Línea</th>
                            <th width="15%">Producto Actual</th>
                            <th width="8%">Talla</th>
                            <th width="10%">Acción</th>
                            <th width="15%">Cambiar a</th>
                            <th width="8%">Objetivo</th>
                            <th width="14%">Observaciones</th>
                            <th width="10%">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="bodyMaquinas">
                        <tr><td colspan="9" class="text-center">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Estadísticas -->
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="stat-card bg-success">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-info">
                            <div class="stat-value" id="statMantener">0</div>
                            <div class="stat-label">Mantener</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-warning">
                        <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
                        <div class="stat-info">
                            <div class="stat-value" id="statCambiar">0</div>
                            <div class="stat-label">Cambiar</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-danger">
                        <div class="stat-icon"><i class="fas fa-stop-circle"></i></div>
                        <div class="stat-info">
                            <div class="stat-value" id="statParar">0</div>
                            <div class="stat-label">Parar</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-info">
                        <div class="stat-icon"><i class="fas fa-cog"></i></div>
                        <div class="stat-info">
                            <div class="stat-value" id="statTotal">0</div>
                            <div class="stat-label">Total Asignadas</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Nuevo Plan -->
<div id="modalNuevoPlan" class="modal">
    <div class="modal-content modal-xl">
        <div class="modal-header">
            <h5 class="modal-title">Crear Nuevo Plan Genérico de Tejido</h5>
            <span class="close" onclick="closeModal('modalNuevoPlan')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                <strong>Nuevo Plan:</strong> Se copiarán las asignaciones del plan actual. 
                Edita las máquinas que necesites cambiar y guarda todo de una vez.
            </div>
            
            <!-- Datos del Plan -->
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="mb-3"><i class="fas fa-clipboard-list"></i> Datos del Plan</h6>
                    <form id="formNuevoPlan">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Código del Plan <span class="text-danger">*</span></label>
                                    <input type="text" id="codigoPlanNuevo" class="form-control" 
                                           placeholder="Ej: PLAN-2025-01" required>
                                    <small class="form-text text-muted">Formato: PLAN-AÑO-NUMERO</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Fecha de Vigencia <span class="text-danger">*</span></label>
                                    <input type="date" id="fechaInicioPlan" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Total Máquinas Asignadas</label>
                                    <input type="text" id="totalMaquinasNuevoPlan" class="form-control" 
                                           value="0" readonly style="font-weight: bold; background: #f7fafc;">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Observaciones del Plan</label>
                            <textarea id="observacionesPlan" class="form-control" rows="2" 
                                      placeholder="Motivo del cambio de plan, lineamientos generales, etc."></textarea>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla de Asignaciones -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="fas fa-cogs"></i> Asignaciones de Máquinas</h6>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="marcarTodasMantener()">
                                <i class="fas fa-check-double"></i> Todas MANTENER
                            </button>
                        </div>
                    </div>
                    
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-bordered" id="tablaNuevoPlan">
                            <thead style="position: sticky; top: 0; background: white; z-index: 10;">
                                <tr>
                                    <th width="10%">Máquina</th>
                                    <th width="25%">Producto Actual</th>
                                    <th width="15%">Acción</th>
                                    <th width="25%">Cambiar a</th>
                                    <th width="10%">Cantidad (doc)</th>
                                    <th width="15%">Observaciones</th>
                                </tr>
                            </thead>
                            <tbody id="bodyNuevoPlan">
                                <tr><td colspan="6" class="text-center">Cargando asignaciones...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modalNuevoPlan')">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="button" class="btn btn-primary" onclick="guardarNuevoPlanCompleto()">
                <i class="fas fa-save"></i> Guardar Plan Completo
            </button>
        </div>
    </div>
</div>

<!-- Modal: Asignar Máquina -->
<div id="modalAsignar" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h5 class="modal-title" id="modalAsignarTitle">Asignar Producto a Máquina</h5>
            <span class="close" onclick="closeModal('modalAsignar')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formAsignar">
                <input type="hidden" id="idDetalleGenerico">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Máquina <span class="text-danger">*</span></label>
                            <select id="idMaquina" class="form-control" required>
                                <option value="">Seleccione una máquina</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Producto Actual <span class="text-danger">*</span></label>
                            <select id="idProducto" class="form-control" required>
                                <option value="">Seleccione un producto</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Acción <span class="text-danger">*</span></label>
                            <select id="accion" class="form-control" onchange="toggleCambioProducto()" required>
                                <option value="mantener">MANTENER - Continuar con el mismo producto</option>
                                <option value="cambiar">CAMBIAR - Cambiar a otro producto temporalmente</option>
                                <option value="parar">PARAR - Detener producción</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group" id="groupCantidadObjetivo" style="display: none;">
                            <label>Cantidad Objetivo (docenas)</label>
                            <input type="number" id="cantidadObjetivo" class="form-control" min="0"
                                   placeholder="Ej: 100">
                            <small class="form-text text-muted">Solo para acción CAMBIAR</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group" id="groupProductoNuevo" style="display: none;">
                            <label>Producto Nuevo</label>
                            <select id="productoNuevo" class="form-control">
                                <option value="">Seleccione el producto de cambio</option>
                            </select>
                            <small class="form-text text-muted">Producto al que se cambiará temporalmente</small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea id="observacionesAsignacion" class="form-control" rows="3"
                              placeholder="Motivo del cambio, tiempo estimado, instrucciones especiales, etc."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modalAsignar')">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="guardarAsignacion()">
                <i class="fas fa-save"></i> Guardar Asignación
            </button>
        </div>
    </div>
</div>

<!-- Modal: Historial -->
<div id="modalHistorial" class="modal">
    <div class="modal-content modal-xl">
        <div class="modal-header">
            <h5 class="modal-title">Historial de Planes Genéricos</h5>
            <span class="close" onclick="closeModal('modalHistorial')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="table-container">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Vigencia Inicio</th>
                            <th>Vigencia Fin</th>
                            <th>Estado</th>
                            <th>Asignaciones</th>
                            <th>Creado Por</th>
                            <th width="120">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="bodyHistorial">
                        <tr><td colspan="7" class="text-center">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Detalle Plan Histórico -->
<div id="modalDetallePlanHistorico" class="modal">
    <div class="modal-content modal-xl">
        <div class="modal-header">
            <h5 class="modal-title" id="tituloDetallePlanHistorico">Detalle del Plan</h5>
            <span class="close" onclick="closeModal('modalDetallePlanHistorico')">&times;</span>
        </div>
        <div class="modal-body">
            <!-- Información del Plan -->
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Código:</strong> <span id="detalleCodigo">-</span>
                        </div>
                        <div class="col-md-3">
                            <strong>Vigencia:</strong> <span id="detalleVigencia">-</span>
                        </div>
                        <div class="col-md-3">
                            <strong>Estado:</strong> <span id="detalleEstado">-</span>
                        </div>
                        <div class="col-md-3">
                            <strong>Creado por:</strong> <span id="detalleCreador">-</span>
                        </div>
                    </div>
                    <div class="row mt-2" id="detalleObservacionesRow" style="display: none;">
                        <div class="col-md-12">
                            <strong>Observaciones:</strong> <span id="detalleObservaciones">-</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Asignaciones -->
            <div class="table-container">
                <table class="table table-bordered table-sm">
                    <thead class="thead-light">
                        <tr>
                            <th>Máquina</th>
                            <th>Línea</th>
                            <th>Producto Actual</th>
                            <th>Talla</th>
                            <th>Acción</th>
                            <th>Cambiar a</th>
                            <th>Objetivo</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody id="bodyDetallePlanHistorico">
                        <tr><td colspan="8" class="text-center">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-success" onclick="imprimirPlanActualDetalle()">
                <i class="fas fa-print"></i> Imprimir
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeModal('modalDetallePlanHistorico')">
                Cerrar
            </button>
        </div>
    </div>
</div>

<style>
/* Estilos específicos del módulo Plan Genérico */
.stat-card {
    padding: 20px;
    border-radius: 8px;
    color: white;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-card .stat-icon {
    font-size: 2.5rem;
    margin-right: 15px;
    opacity: 0.8;
}

.stat-card .stat-info {
    flex: 1;
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: bold;
    line-height: 1;
}

.stat-card .stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-top: 5px;
}

.badge-accion {
    font-size: 0.85rem;
    padding: 0.4em 0.6em;
    font-weight: 600;
}

.badge-mantener {
    background-color: #28a745;
    color: white;
}

.badge-cambiar {
    background-color: #ffc107;
    color: #212529;
}

.badge-parar {
    background-color: #dc3545;
    color: white;
}

/* Modal XL para nuevo plan */
.modal-xl .modal-content {
    max-width: 1400px;
}

/* Tabla en modal nuevo plan */
#tablaNuevoPlan {
    font-size: 0.875rem;
}

#tablaNuevoPlan th {
    font-size: 0.8rem;
    font-weight: 600;
    background: #f7fafc;
}

#tablaNuevoPlan td {
    vertical-align: middle;
    padding: 8px;
}

#tablaNuevoPlan select,
#tablaNuevoPlan input {
    font-size: 0.8rem;
}

.d-flex {
    display: flex;
}

.justify-content-between {
    justify-content: space-between;
}

.align-items-center {
    align-items: center;
}

.mb-3 {
    margin-bottom: 1rem;
}

.mb-0 {
    margin-bottom: 0;
}

.alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-info {
    background: #e6f7ff;
    border-left: 4px solid #4299e1;
    color: #2c5282;
}
</style>

<script>
const baseUrl = window.location.origin + '/mes_hermen';
let planActual = null;
let productos = [];
let maquinas = [];
let lineas = [];

document.addEventListener('DOMContentLoaded', function() {
    // Establecer fecha por defecto en el formulario
    document.getElementById('fechaInicioPlan').valueAsDate = new Date();
    
    // Cargar plan actual
    loadPlanActual();
    
    // Cargar productos y máquinas después de un pequeño delay
    // para asegurar que todos los elementos del DOM estén listos
    setTimeout(() => {
        loadProductos();
        loadMaquinas();
    }, 500);
    
    // Event listeners para filtros
    document.getElementById('searchMaquina').addEventListener('input', filtrarTabla);
    document.getElementById('filterAccion').addEventListener('change', filtrarTabla);
    document.getElementById('filterLinea').addEventListener('change', filtrarTabla);
});

async function loadPlanActual() {
    try {
        const response = await fetch(`${baseUrl}/api/plan_generico.php?action=plan_actual`);
        const data = await response.json();
        
        if (data.success && data.plan) {
            planActual = data.plan;
            mostrarPlanActual();
            renderAsignaciones(data.plan.detalles);
            calcularEstadisticas(data.plan.detalles);
        } else {
            mostrarSinPlan();
        }
    } catch (error) {
        console.error('Error al cargar plan:', error);
        showNotification('Error al cargar el plan actual', 'error');
    }
}

function mostrarPlanActual() {
    document.getElementById('planActualCard').style.display = 'block';
    document.getElementById('sinPlanCard').style.display = 'none';
    document.getElementById('tablaMaquinasCard').style.display = 'block';
    document.getElementById('btnImprimir').style.display = 'inline-block';
    
    document.getElementById('planCodigo').textContent = planActual.codigo_plan_generico;
    document.getElementById('planFechaInicio').textContent = formatDate(planActual.fecha_vigencia_inicio);
    document.getElementById('planCreador').textContent = planActual.creado_por || 'Sistema';
    
    if (planActual.observaciones_plan) {
        document.getElementById('planObservacionesRow').style.display = 'block';
        document.getElementById('planObservaciones').textContent = planActual.observaciones_plan;
    }
}

function mostrarSinPlan() {
    document.getElementById('planActualCard').style.display = 'none';
    document.getElementById('sinPlanCard').style.display = 'block';
    document.getElementById('tablaMaquinasCard').style.display = 'none';
}

function renderAsignaciones(detalles) {
    const tbody = document.getElementById('bodyMaquinas');
    
    if (!detalles || detalles.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No hay asignaciones. Use el botón "Asignar Máquina" para comenzar.</td></tr>';
        return;
    }
    
    tbody.innerHTML = detalles.map(detalle => {
        const badgeAccion = detalle.accion === 'mantener' ? 'badge-mantener' :
                           detalle.accion === 'cambiar' ? 'badge-cambiar' : 'badge-parar';
        
        const lineaBadge = getLineaBadge(detalle.codigo_linea);
        
        return `
            <tr>
                <td><strong>${detalle.numero_maquina}</strong></td>
                <td>${lineaBadge}</td>
                <td><small>${detalle.descripcion_completa}</small></td>
                <td><span class="badge badge-secondary">${detalle.talla}</span></td>
                <td><span class="badge badge-accion ${badgeAccion}">${detalle.accion.toUpperCase()}</span></td>
                <td><small>${detalle.descripcion_producto_nuevo || '-'}</small></td>
                <td>${detalle.cantidad_objetivo_docenas ? detalle.cantidad_objetivo_docenas + ' doc' : '-'}</td>
                <td><small>${detalle.observaciones || '-'}</small></td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick='editarAsignacion(${JSON.stringify(detalle)})' title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="eliminarAsignacion(${detalle.id_detalle_generico})" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function calcularEstadisticas(detalles) {
    if (!detalles) {
        document.getElementById('statMantener').textContent = '0';
        document.getElementById('statCambiar').textContent = '0';
        document.getElementById('statParar').textContent = '0';
        document.getElementById('statTotal').textContent = '0';
        return;
    }
    
    const stats = {
        mantener: detalles.filter(d => d.accion === 'mantener').length,
        cambiar: detalles.filter(d => d.accion === 'cambiar').length,
        parar: detalles.filter(d => d.accion === 'parar').length
    };
    
    document.getElementById('statMantener').textContent = stats.mantener;
    document.getElementById('statCambiar').textContent = stats.cambiar;
    document.getElementById('statParar').textContent = stats.parar;
    document.getElementById('statTotal').textContent = detalles.length;
}

async function loadProductos() {
    try {
        const response = await fetch(`${baseUrl}/api/productos.php`);
        const data = await response.json();
        
        if (data.success) {
            productos = data.productos;
            
            // Poblar select de productos
            const selectProducto = document.getElementById('idProducto');
            const selectProductoNuevo = document.getElementById('productoNuevo');
            
            // Limpiar selects
            selectProducto.innerHTML = '<option value="">Seleccione un producto</option>';
            selectProductoNuevo.innerHTML = '<option value="">Seleccione el producto de cambio</option>';
            
            // Extraer líneas únicas para el filtro
            lineas = [...new Set(productos.map(p => ({ 
                id: p.id_linea, 
                nombre: p.nombre_linea 
            }))).values()].filter((v, i, a) => a.findIndex(t => t.id === v.id) === i);
            
            const filterLinea = document.getElementById('filterLinea');
            filterLinea.innerHTML = '<option value="">Todas las líneas</option>';
            lineas.forEach(linea => {
                filterLinea.innerHTML += `<option value="${linea.id}">${linea.nombre}</option>`;
            });
            
            // Agrupar productos por línea
            const productosPorLinea = productos.reduce((acc, p) => {
                if (!acc[p.nombre_linea]) acc[p.nombre_linea] = [];
                acc[p.nombre_linea].push(p);
                return acc;
            }, {});
            
            // Crear optgroups
            Object.keys(productosPorLinea).forEach(linea => {
                const optgroup = document.createElement('optgroup');
                optgroup.label = linea;
                
                const optgroup2 = document.createElement('optgroup');
                optgroup2.label = linea;
                
                productosPorLinea[linea].forEach(producto => {
                    const option = new Option(
                        `${producto.codigo_producto} - ${producto.nombre_tipo} ${producto.talla}`,
                        producto.id_producto
                    );
                    const option2 = option.cloneNode(true);
                    
                    optgroup.appendChild(option);
                    optgroup2.appendChild(option2);
                });
                
                selectProducto.appendChild(optgroup);
                selectProductoNuevo.appendChild(optgroup2);
            });
        }
    } catch (error) {
        console.error('Error al cargar productos:', error);
        showNotification('Error al cargar productos', 'error');
    }
}

async function loadMaquinas() {
    console.log('🔧 Iniciando carga de máquinas...');
    try {
        const url = `${baseUrl}/api/plan_generico.php`;
        console.log('📡 URL de API:', url);
        
        const response = await fetch(url);
        console.log('📨 Response status:', response.status);
        
        const data = await response.json();
        console.log('📦 Datos recibidos:', data);
        
        if (data.success) {
            maquinas = data.maquinas;
            console.log('✅ Máquinas asignadas al array:', maquinas.length);
            
            const select = document.getElementById('idMaquina');
            console.log('🎯 Select encontrado:', select ? 'SÍ' : 'NO');
            
            if (select) {
                // Limpiar opciones existentes excepto la primera
                select.innerHTML = '<option value="">Seleccione una máquina</option>';
                
                maquinas.forEach(maquina => {
                    const option = new Option(
                        `${maquina.numero_maquina} - ${maquina.estado}`,
                        maquina.id_maquina
                    );
                    select.appendChild(option);
                });
                console.log('✅ Select poblado con', select.options.length, 'opciones');
            } else {
                console.error('❌ No se encontró el select con id "idMaquina"');
            }
        } else {
            console.error('❌ API devolvió success=false:', data.message);
        }
    } catch (error) {
        console.error('💥 Error al cargar máquinas:', error);
        showNotification('Error al cargar máquinas', 'error');
    }
}

let asignacionesNuevoPlan = [];

function showNuevoPlanModal() {
    document.getElementById('formNuevoPlan').reset();
    document.getElementById('fechaInicioPlan').valueAsDate = new Date();
    
    // Generar código sugerido
    const hoy = new Date();
    const codigoSugerido = `PLAN-${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}${String(hoy.getDate()).padStart(2, '0')}`;
    document.getElementById('codigoPlanNuevo').value = codigoSugerido;
    
    showModal('modalNuevoPlan');
    cargarAsignacionesParaNuevoPlan();
}

async function cargarAsignacionesParaNuevoPlan() {
    const tbody = document.getElementById('bodyNuevoPlan');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando asignaciones...</td></tr>';
    
    try {
        // Si hay plan actual, copiar sus asignaciones
        if (planActual && planActual.detalles && planActual.detalles.length > 0) {
            asignacionesNuevoPlan = planActual.detalles.map(det => ({
                id_maquina: det.id_maquina,
                numero_maquina: det.numero_maquina,
                id_producto: det.id_producto,
                nombre_producto: det.descripcion_completa,
                talla: det.talla,
                accion: 'mantener', // Por defecto mantener
                producto_nuevo: null,
                cantidad_objetivo_docenas: null,
                observaciones: ''
            }));
        } else {
            // Si no hay plan, cargar todas las máquinas operativas sin asignación
            const response = await fetch(`${baseUrl}/api/plan_generico.php`);
            const data = await response.json();
            
            if (data.success && data.maquinas) {
                asignacionesNuevoPlan = data.maquinas
                    .filter(m => m.estado === 'operativa')
                    .map(m => ({
                        id_maquina: m.id_maquina,
                        numero_maquina: m.numero_maquina,
                        id_producto: null,
                        nombre_producto: '',
                        talla: '',
                        accion: 'mantener',
                        producto_nuevo: null,
                        cantidad_objetivo_docenas: null,
                        observaciones: ''
                    }));
            }
        }
        
        renderAsignacionesNuevoPlan();
        actualizarContadorMaquinas();
        
    } catch (error) {
        console.error('Error al cargar asignaciones:', error);
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error al cargar asignaciones</td></tr>';
    }
}

function renderAsignacionesNuevoPlan() {
    const tbody = document.getElementById('bodyNuevoPlan');
    
    if (asignacionesNuevoPlan.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No hay máquinas para asignar</td></tr>';
        return;
    }
    
    tbody.innerHTML = asignacionesNuevoPlan.map((asig, index) => `
        <tr>
            <td><strong>${asig.numero_maquina}</strong></td>
            <td>
                <select class="form-control form-control-sm" 
                        onchange="actualizarProductoAsignacion(${index}, this.value)">
                    <option value="">Sin asignar</option>
                    ${generarOpcionesProductos(asig.id_producto)}
                </select>
            </td>
            <td>
                <select class="form-control form-control-sm" 
                        onchange="actualizarAccionAsignacion(${index}, this.value)">
                    <option value="mantener" ${asig.accion === 'mantener' ? 'selected' : ''}>MANTENER</option>
                    <option value="cambiar" ${asig.accion === 'cambiar' ? 'selected' : ''}>CAMBIAR</option>
                    <option value="parar" ${asig.accion === 'parar' ? 'selected' : ''}>PARAR</option>
                </select>
            </td>
            <td>
                <select class="form-control form-control-sm" 
                        id="productoNuevo_${index}"
                        onchange="actualizarProductoNuevo(${index}, this.value)"
                        ${asig.accion !== 'cambiar' ? 'disabled' : ''}>
                    <option value="">-</option>
                    ${generarOpcionesProductos(asig.producto_nuevo)}
                </select>
            </td>
            <td>
                <input type="number" class="form-control form-control-sm" 
                       id="cantidad_${index}"
                       value="${asig.cantidad_objetivo_docenas || ''}"
                       onchange="actualizarCantidad(${index}, this.value)"
                       ${asig.accion !== 'cambiar' ? 'disabled' : ''}
                       min="0" placeholder="0">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm" 
                       value="${asig.observaciones || ''}"
                       onchange="actualizarObservaciones(${index}, this.value)"
                       placeholder="Observaciones">
            </td>
        </tr>
    `).join('');
}

function generarOpcionesProductos(idProductoSeleccionado) {
    if (productos.length === 0) return '';
    
    const productosPorLinea = productos.reduce((acc, p) => {
        if (!acc[p.nombre_linea]) acc[p.nombre_linea] = [];
        acc[p.nombre_linea].push(p);
        return acc;
    }, {});
    
    let html = '';
    Object.keys(productosPorLinea).forEach(linea => {
        html += `<optgroup label="${linea}">`;
        productosPorLinea[linea].forEach(producto => {
            const selected = producto.id_producto == idProductoSeleccionado ? 'selected' : '';
            html += `<option value="${producto.id_producto}" ${selected}>
                ${producto.codigo_producto} - ${producto.nombre_tipo} ${producto.talla}
            </option>`;
        });
        html += '</optgroup>';
    });
    
    return html;
}

function actualizarProductoAsignacion(index, idProducto) {
    asignacionesNuevoPlan[index].id_producto = idProducto || null;
    actualizarContadorMaquinas();
}

function actualizarAccionAsignacion(index, accion) {
    asignacionesNuevoPlan[index].accion = accion;
    
    // Habilitar/deshabilitar campos según la acción
    const productoNuevo = document.getElementById(`productoNuevo_${index}`);
    const cantidad = document.getElementById(`cantidad_${index}`);
    
    if (accion === 'cambiar') {
        productoNuevo.disabled = false;
        cantidad.disabled = false;
    } else {
        productoNuevo.disabled = true;
        cantidad.disabled = true;
        asignacionesNuevoPlan[index].producto_nuevo = null;
        asignacionesNuevoPlan[index].cantidad_objetivo_docenas = null;
    }
}

function actualizarProductoNuevo(index, idProducto) {
    asignacionesNuevoPlan[index].producto_nuevo = idProducto || null;
}

function actualizarCantidad(index, cantidad) {
    asignacionesNuevoPlan[index].cantidad_objetivo_docenas = cantidad ? parseInt(cantidad) : null;
}

function actualizarObservaciones(index, observaciones) {
    asignacionesNuevoPlan[index].observaciones = observaciones;
}

function actualizarContadorMaquinas() {
    const totalAsignadas = asignacionesNuevoPlan.filter(a => a.id_producto).length;
    document.getElementById('totalMaquinasNuevoPlan').value = `${totalAsignadas} de ${asignacionesNuevoPlan.length}`;
}

function marcarTodasMantener() {
    asignacionesNuevoPlan.forEach((asig, index) => {
        asig.accion = 'mantener';
        asig.producto_nuevo = null;
        asig.cantidad_objetivo_docenas = null;
    });
    renderAsignacionesNuevoPlan();
}

async function guardarNuevoPlanCompleto() {
    const codigo = document.getElementById('codigoPlanNuevo').value.trim();
    const fecha = document.getElementById('fechaInicioPlan').value;
    const observaciones = document.getElementById('observacionesPlan').value.trim();
    
    if (!codigo || !fecha) {
        showNotification('Complete el código y fecha del plan', 'warning');
        return;
    }
    
    // Validar que al menos haya una asignación
    const asignacionesValidas = asignacionesNuevoPlan.filter(a => a.id_producto);
    if (asignacionesValidas.length === 0) {
        showNotification('Debe asignar al menos un producto a una máquina', 'warning');
        return;
    }
    
    // Validar que las acciones CAMBIAR tengan producto nuevo
    const cambiosSinProducto = asignacionesNuevoPlan.filter(a => 
        a.accion === 'cambiar' && a.id_producto && !a.producto_nuevo
    );
    if (cambiosSinProducto.length > 0) {
        showNotification('Las máquinas con acción CAMBIAR deben tener un producto nuevo asignado', 'warning');
        return;
    }
    
    if (!confirm(`¿Crear nuevo plan con ${asignacionesValidas.length} máquinas asignadas?`)) {
        return;
    }
    
    try {
        // 1. Crear el plan
        const responsePlan = await fetch(`${baseUrl}/api/plan_generico.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'crear_plan',
                codigo_plan_generico: codigo,
                fecha_vigencia_inicio: fecha,
                observaciones: observaciones
            })
        });
        
        const dataPlan = await responsePlan.json();
        
        if (!dataPlan.success) {
            showNotification(dataPlan.message || 'Error al crear el plan', 'error');
            return;
        }
        
        const idPlanNuevo = dataPlan.id_plan_generico;
        
        // 2. Guardar todas las asignaciones
        let asignacionesGuardadas = 0;
        for (const asig of asignacionesValidas) {
            const responseAsig = await fetch(`${baseUrl}/api/plan_generico.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'asignar_maquina',
                    id_plan_generico: idPlanNuevo,
                    id_maquina: asig.id_maquina,
                    id_producto: asig.id_producto,
                    accion: asig.accion,
                    producto_nuevo: asig.producto_nuevo,
                    cantidad_objetivo_docenas: asig.cantidad_objetivo_docenas,
                    observaciones: asig.observaciones
                })
            });
            
            const dataAsig = await responseAsig.json();
            if (dataAsig.success) {
                asignacionesGuardadas++;
            }
        }
        
        showNotification(`Plan creado con ${asignacionesGuardadas} máquinas asignadas`, 'success');
        closeModal('modalNuevoPlan');
        loadPlanActual();
        
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al guardar el plan', 'error');
    }
}

function showAsignarModal() {
    if (!planActual) {
        showNotification('Primero debe crear un plan genérico', 'warning');
        return;
    }
    
    document.getElementById('formAsignar').reset();
    document.getElementById('idDetalleGenerico').value = '';
    document.getElementById('modalAsignarTitle').textContent = 'Asignar Producto a Máquina';
    toggleCambioProducto();
    showModal('modalAsignar');
}

function editarAsignacion(detalle) {
    document.getElementById('idDetalleGenerico').value = detalle.id_detalle_generico;
    document.getElementById('idMaquina').value = detalle.id_maquina;
    document.getElementById('idProducto').value = detalle.id_producto;
    document.getElementById('accion').value = detalle.accion;
    document.getElementById('productoNuevo').value = detalle.producto_nuevo || '';
    document.getElementById('cantidadObjetivo').value = detalle.cantidad_objetivo_docenas || '';
    document.getElementById('observacionesAsignacion').value = detalle.observaciones || '';
    
    document.getElementById('modalAsignarTitle').textContent = 'Editar Asignación - ' + detalle.numero_maquina;
    toggleCambioProducto();
    showModal('modalAsignar');
}

function toggleCambioProducto() {
    const accion = document.getElementById('accion').value;
    const groupProductoNuevo = document.getElementById('groupProductoNuevo');
    const groupCantidadObjetivo = document.getElementById('groupCantidadObjetivo');
    
    if (accion === 'cambiar') {
        groupProductoNuevo.style.display = 'block';
        groupCantidadObjetivo.style.display = 'block';
    } else {
        groupProductoNuevo.style.display = 'none';
        groupCantidadObjetivo.style.display = 'none';
        document.getElementById('productoNuevo').value = '';
        document.getElementById('cantidadObjetivo').value = '';
    }
}

async function guardarAsignacion() {
    const idMaquina = document.getElementById('idMaquina').value;
    const idProducto = document.getElementById('idProducto').value;
    const accion = document.getElementById('accion').value;
    const productoNuevo = document.getElementById('productoNuevo').value;
    const cantidadObjetivo = document.getElementById('cantidadObjetivo').value;
    const observaciones = document.getElementById('observacionesAsignacion').value;
    
    if (!idMaquina || !idProducto || !accion) {
        showNotification('Complete los campos requeridos', 'warning');
        return;
    }
    
    if (accion === 'cambiar' && !productoNuevo) {
        showNotification('Debe seleccionar el producto de cambio', 'warning');
        return;
    }
    
    try {
        const response = await fetch(`${baseUrl}/api/plan_generico.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'asignar_maquina',
                id_plan_generico: planActual.id_plan_generico,
                id_maquina: idMaquina,
                id_producto: idProducto,
                accion: accion,
                producto_nuevo: productoNuevo || null,
                cantidad_objetivo_docenas: cantidadObjetivo || null,
                observaciones: observaciones
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            closeModal('modalAsignar');
            loadPlanActual();
        } else {
            showNotification(data.message || 'Error al guardar', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al guardar la asignación', 'error');
    }
}

async function eliminarAsignacion(idDetalle) {
    if (!confirm('¿Está seguro de eliminar esta asignación?')) return;
    
    try {
        const response = await fetch(`${baseUrl}/api/plan_generico.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_detalle_generico: idDetalle
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Asignación eliminada', 'success');
            loadPlanActual();
        } else {
            showNotification(data.message || 'Error al eliminar', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al eliminar', 'error');
    }
}

async function showHistorialModal() {
    showModal('modalHistorial');
    
    try {
        const response = await fetch(`${baseUrl}/api/plan_generico.php?action=historial`);
        const data = await response.json();
        
        const tbody = document.getElementById('bodyHistorial');
        
        if (data.success && data.planes.length > 0) {
            tbody.innerHTML = data.planes.map(plan => `
                <tr style="cursor: pointer;" onclick="verDetallePlanHistorico(${plan.id_plan_generico})">
                    <td><strong>${plan.codigo_plan_generico}</strong></td>
                    <td>${formatDate(plan.fecha_vigencia_inicio)}</td>
                    <td>${plan.fecha_vigencia_fin ? formatDate(plan.fecha_vigencia_fin) : '-'}</td>
                    <td>
                        <span class="badge badge-${plan.estado === 'vigente' ? 'success' : 'secondary'}">
                            ${plan.estado.toUpperCase()}
                        </span>
                    </td>
                    <td>${plan.total_asignaciones}</td>
                    <td><small>${plan.creado_por || 'Sistema'}</small></td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="event.stopPropagation(); verDetallePlanHistorico(${plan.id_plan_generico})" title="Ver Detalle">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-success" onclick="event.stopPropagation(); imprimirPlanHistorico(${plan.id_plan_generico})" title="Imprimir">
                            <i class="fas fa-print"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No hay planes en el historial</td></tr>';
        }
    } catch (error) {
        console.error('Error:', error);
        document.getElementById('bodyHistorial').innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error al cargar historial</td></tr>';
    }
}

function filtrarTabla() {
    const searchText = document.getElementById('searchMaquina').value.toLowerCase();
    const filterAccion = document.getElementById('filterAccion').value;
    const filterLineaId = document.getElementById('filterLinea').value;
    
    if (!planActual || !planActual.detalles) return;
    
    const detallesFiltrados = planActual.detalles.filter(detalle => {
        const matchSearch = !searchText || 
                           detalle.numero_maquina.toLowerCase().includes(searchText) ||
                           detalle.descripcion_completa.toLowerCase().includes(searchText);
        const matchAccion = !filterAccion || detalle.accion === filterAccion;
        const matchLinea = !filterLineaId || detalle.id_linea == filterLineaId;
        
        return matchSearch && matchAccion && matchLinea;
    });
    
    renderAsignaciones(detallesFiltrados);
}

function getLineaBadge(codigoLinea) {
    const colors = {
        'LUJO': 'primary',
        'STRETCH': 'success',
        'LYCRA20': 'info',
        'LYCRA40': 'warning',
        'CAMISETAS': 'danger'
    };
    const color = colors[codigoLinea] || 'secondary';
    return `<span class="badge badge-${color}">${codigoLinea}</span>`;
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString + 'T00:00:00');
    return date.toLocaleDateString('es-BO', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
}

function showModal(modalId) {
    document.getElementById(modalId).classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

async function verDetallePlanHistorico(idPlan) {
    try {
        const response = await fetch(`${baseUrl}/api/plan_generico.php?action=detalle_plan&id_plan=${idPlan}`);
        const data = await response.json();
        
        if (data.success && data.plan) {
            const plan = data.plan;
            
            // Llenar información del plan
            document.getElementById('tituloDetallePlanHistorico').textContent = `Plan: ${plan.codigo_plan_generico}`;
            document.getElementById('detalleCodigo').textContent = plan.codigo_plan_generico;
            document.getElementById('detalleVigencia').textContent = `${formatDate(plan.fecha_vigencia_inicio)} - ${plan.fecha_vigencia_fin ? formatDate(plan.fecha_vigencia_fin) : 'Actual'}`;
            document.getElementById('detalleEstado').innerHTML = `<span class="badge badge-${plan.estado === 'vigente' ? 'success' : 'secondary'}">${plan.estado.toUpperCase()}</span>`;
            document.getElementById('detalleCreador').textContent = plan.creado_por || 'Sistema';
            
            if (plan.observaciones_plan) {
                document.getElementById('detalleObservacionesRow').style.display = 'block';
                document.getElementById('detalleObservaciones').textContent = plan.observaciones_plan;
            } else {
                document.getElementById('detalleObservacionesRow').style.display = 'none';
            }
            
            // Llenar tabla de asignaciones
            const tbody = document.getElementById('bodyDetallePlanHistorico');
            if (plan.detalles && plan.detalles.length > 0) {
                tbody.innerHTML = plan.detalles.map(det => {
                    const badgeAccion = det.accion === 'mantener' ? 'badge-mantener' :
                                       det.accion === 'cambiar' ? 'badge-cambiar' : 'badge-parar';
                    const lineaBadge = getLineaBadge(det.codigo_linea);
                    
                    return `
                        <tr>
                            <td><strong>${det.numero_maquina}</strong></td>
                            <td>${lineaBadge}</td>
                            <td><small>${det.descripcion_completa}</small></td>
                            <td><span class="badge badge-secondary">${det.talla}</span></td>
                            <td><span class="badge badge-accion ${badgeAccion}">${det.accion.toUpperCase()}</span></td>
                            <td><small>${det.descripcion_producto_nuevo || '-'}</small></td>
                            <td>${det.cantidad_objetivo_docenas ? det.cantidad_objetivo_docenas + ' doc' : '-'}</td>
                            <td><small>${det.observaciones || '-'}</small></td>
                        </tr>
                    `;
                }).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No hay asignaciones en este plan</td></tr>';
            }
            
            // Cerrar modal de historial y abrir modal de detalle
            closeModal('modalHistorial');
            showModal('modalDetallePlanHistorico');
            
            // Guardar el plan actual en detalle para imprimir
            window.planEnDetalle = plan;
        } else {
            showNotification('No se pudo cargar el detalle del plan', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al cargar el detalle', 'error');
    }
}

function imprimirPlan() {
    if (!planActual) {
        showNotification('No hay plan para imprimir', 'warning');
        return;
    }
    
    imprimirPlanGenerico(planActual);
}

function imprimirPlanHistorico(idPlan) {
    // Cargar el plan y luego imprimir
    fetch(`${baseUrl}/api/plan_generico.php?action=detalle_plan&id_plan=${idPlan}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.plan) {
                imprimirPlanGenerico(data.plan);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al cargar el plan para imprimir', 'error');
        });
}

function imprimirPlanActualDetalle() {
    if (window.planEnDetalle) {
        imprimirPlanGenerico(window.planEnDetalle);
    }
}

function imprimirPlanGenerico(plan) {
    const ventana = window.open('', '_blank');
    
    const estadisticas = {
        mantener: plan.detalles ? plan.detalles.filter(d => d.accion === 'mantener').length : 0,
        cambiar: plan.detalles ? plan.detalles.filter(d => d.accion === 'cambiar').length : 0,
        parar: plan.detalles ? plan.detalles.filter(d => d.accion === 'parar').length : 0
    };
    
    // Ruta del logo - AJUSTA ESTA RUTA según donde tengas tu logo
    const logoUrl = '${baseUrl}/assets/images/logo.png'; // Si tienes logo
    
    const html = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Plan Genérico de Tejido - ${plan.codigo_plan_generico}</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
            <style>
                @page { 
                    margin: 1cm; 
                    size: landscape;
                }
                body {
                    font-family: Arial, sans-serif;
                    font-size: 9px;
                    line-height: 1.3;
                    margin: 0;
                    padding: 0;
                }
                .header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    margin-bottom: 10px;
                    padding-bottom: 8px;
                    border-bottom: 3px solid #667eea;
                }
                .header-left {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }
                .logo {
                    width: 60px;
                    height: 60px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .logo img {
                    max-width: 100%;
                    max-height: 100%;
                }
                .logo-icon {
                    font-size: 48px;
                    color: #667eea;
                }
                .header-info h1 {
                    margin: 0 0 2px 0;
                    font-size: 18px;
                    color: #2d3748;
                }
                .header-info h2 {
                    margin: 0;
                    font-size: 13px;
                    color: #666;
                    font-weight: normal;
                }
                .header-right {
                    text-align: right;
                    font-size: 8px;
                    color: #666;
                }
                .info-plan {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 8px;
                    margin-bottom: 10px;
                    background: #f8f9fa;
                    padding: 8px;
                    border-radius: 4px;
                }
                .info-item {
                    padding: 4px 8px;
                    background: white;
                    border-radius: 3px;
                }
                .info-item strong {
                    color: #667eea;
                    font-size: 8px;
                    display: block;
                    margin-bottom: 2px;
                }
                .info-item span {
                    font-size: 10px;
                    font-weight: bold;
                    color: #2d3748;
                }
                .observaciones-plan {
                    background: #fff3cd;
                    padding: 6px 8px;
                    border-left: 3px solid #ffc107;
                    margin-bottom: 10px;
                    font-size: 8px;
                }
                .observaciones-plan strong {
                    color: #856404;
                }
                .estadisticas {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 8px;
                    margin-bottom: 10px;
                }
                .stat-box {
                    text-align: center;
                    padding: 8px;
                    border-radius: 4px;
                }
                .stat-box.mantener { background: #d4edda; border-left: 3px solid #28a745; }
                .stat-box.cambiar { background: #fff3cd; border-left: 3px solid #ffc107; }
                .stat-box.parar { background: #f8d7da; border-left: 3px solid #dc3545; }
                .stat-box.total { background: #e7f3ff; border-left: 3px solid #4299e1; }
                .stat-box .numero {
                    font-size: 20px;
                    font-weight: bold;
                    color: #2d3748;
                    line-height: 1;
                }
                .stat-box .label {
                    font-size: 9px;
                    color: #666;
                    margin-top: 2px;
                    font-weight: 600;
                }
                table.asignaciones {
                    width: 100%;
                    border-collapse: collapse;
                }
                table.asignaciones th,
                table.asignaciones td {
                    border: 1px solid #dee2e6;
                    padding: 4px 6px;
                    text-align: left;
                    font-size: 8px;
                }
                table.asignaciones th {
                    background: #667eea;
                    color: white;
                    font-weight: bold;
                    font-size: 9px;
                }
                table.asignaciones tr:nth-child(even) {
                    background: #f8f9fa;
                }
                table.asignaciones tr:hover {
                    background: #e7f3ff;
                }
                .badge {
                    display: inline-block;
                    padding: 2px 6px;
                    border-radius: 3px;
                    font-size: 8px;
                    font-weight: bold;
                    white-space: nowrap;
                }
                .badge-mantener { background: #28a745; color: white; }
                .badge-cambiar { background: #ffc107; color: #333; }
                .badge-parar { background: #dc3545; color: white; }
                .badge-linea { 
                    padding: 2px 5px;
                    font-size: 7px;
                    border-radius: 2px;
                    font-weight: bold;
                }
                .badge-LUJO { background: #667eea; color: white; }
                .badge-STRETCH { background: #48bb78; color: white; }
                .badge-LYCRA20 { background: #4299e1; color: white; }
                .badge-LYCRA40 { background: #ed8936; color: white; }
                .badge-CAMISETAS { background: #f56565; color: white; }
                .footer {
                    margin-top: 15px;
                    padding-top: 8px;
                    border-top: 2px solid #dee2e6;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .footer-left {
                    font-size: 7px;
                    color: #666;
                }
                .footer-right {
                    text-align: right;
                }
                .firma-box {
                    border: 1px solid #333;
                    padding: 8px 20px;
                    min-width: 250px;
                    text-align: center;
                }
                .firma-box .label {
                    font-size: 8px;
                    color: #666;
                    margin-bottom: 20px;
                }
                .firma-box .linea {
                    border-top: 1px solid #333;
                    margin-top: 20px;
                    padding-top: 4px;
                    font-size: 8px;
                    font-weight: bold;
                }
                @media print {
                    .no-print { display: none !important; }
                    body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="header-left">
                    <div class="logo">
                        <!-- Opción 1: Si tienes logo en PNG/JPG -->
                        <!-- <img src="${logoUrl}" alt="Logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"> -->
                        
                        <!-- Opción 2: Ícono como logo (se muestra por defecto) -->
                        <i class="fas fa-industry logo-icon"></i>
                    </div>
                    <div class="header-info">
                        <h1>HERMEN LTDA.</h1>
                        <h2>Plan Genérico de Producción - Área de Tejido</h2>
                    </div>
                </div>
                <div class="header-right">
                    <div><strong>Fecha de Impresión:</strong></div>
                    <div>${new Date().toLocaleDateString('es-BO', { 
                        weekday: 'long', 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })}</div>
                </div>
            </div>
            
            <div class="info-plan">
                <div class="info-item">
                    <strong>CÓDIGO DEL PLAN</strong>
                    <span>${plan.codigo_plan_generico}</span>
                </div>
                <div class="info-item">
                    <strong>FECHA DE VIGENCIA</strong>
                    <span>${formatDate(plan.fecha_vigencia_inicio)}</span>
                </div>
                <div class="info-item">
                    <strong>ESTADO</strong>
                    <span style="color: ${plan.estado === 'vigente' ? '#28a745' : '#666'};">${plan.estado.toUpperCase()}</span>
                </div>
                <div class="info-item">
                    <strong>CREADO POR</strong>
                    <span>${plan.creado_por || 'Sistema'}</span>
                </div>
                <div class="info-item">
                    <strong>FECHA DE CREACIÓN</strong>
                    <span>${formatDate(plan.fecha_creacion)}</span>
                </div>
                <div class="info-item">
                    <strong>TOTAL ASIGNACIONES</strong>
                    <span>${plan.detalles ? plan.detalles.length : 0} máquinas</span>
                </div>
            </div>
            
            ${plan.observaciones_plan ? `
            <div class="observaciones-plan">
                <strong><i class="fas fa-info-circle"></i> Observaciones del Plan:</strong> ${plan.observaciones_plan}
            </div>
            ` : ''}
            
            <div class="estadisticas">
                <div class="stat-box mantener">
                    <div class="numero">${estadisticas.mantener}</div>
                    <div class="label">MANTENER</div>
                </div>
                <div class="stat-box cambiar">
                    <div class="numero">${estadisticas.cambiar}</div>
                    <div class="label">CAMBIAR</div>
                </div>
                <div class="stat-box parar">
                    <div class="numero">${estadisticas.parar}</div>
                    <div class="label">PARAR</div>
                </div>
                <div class="stat-box total">
                    <div class="numero">${plan.detalles ? plan.detalles.length : 0}</div>
                    <div class="label">TOTAL</div>
                </div>
            </div>
            
            <table class="asignaciones">
                <thead>
                    <tr>
                        <th width="7%">MÁQUINA</th>
                        <th width="8%">LÍNEA</th>
                        <th width="25%">PRODUCTO ACTUAL</th>
                        <th width="6%">TALLA</th>
                        <th width="9%">ACCIÓN</th>
                        <th width="23%">CAMBIAR A</th>
                        <th width="7%">OBJETIVO</th>
                        <th width="15%">OBSERVACIONES</th>
                    </tr>
                </thead>
                <tbody>
                    ${plan.detalles && plan.detalles.length > 0 ? plan.detalles.map(det => `
                        <tr>
                            <td><strong>${det.numero_maquina}</strong></td>
                            <td><span class="badge-linea badge-${det.codigo_linea}">${det.codigo_linea}</span></td>
                            <td>${det.descripcion_completa}</td>
                            <td style="text-align: center;">${det.talla}</td>
                            <td><span class="badge badge-${det.accion}">${det.accion.toUpperCase()}</span></td>
                            <td>${det.descripcion_producto_nuevo || '-'}</td>
                            <td style="text-align: center;">${det.cantidad_objetivo_docenas ? det.cantidad_objetivo_docenas + ' doc' : '-'}</td>
                            <td>${det.observaciones || '-'}</td>
                        </tr>
                    `).join('') : '<tr><td colspan="8" style="text-align: center; padding: 20px;">No hay asignaciones registradas</td></tr>'}
                </tbody>
            </table>
            
            <div class="footer">
                <div class="footer-left">
                    <div><strong>Sistema MES Hermen Ltda.</strong> - Módulo de Planificación de Tejido</div>
                    <div>La Paz, Bolivia - Tel: [Tu teléfono] - Email: [Tu email]</div>
                </div>
                <div class="footer-right">
                    <div class="firma-box">
                        <div class="label">APROBADO POR:</div>
                        <div class="linea">FIRMA Y SELLO DE GERENCIA</div>
                    </div>
                </div>
            </div>
            
            <button onclick="window.print()" class="no-print" style="position: fixed; top: 20px; right: 20px; padding: 12px 24px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; box-shadow: 0 4px 6px rgba(0,0,0,0.2); font-weight: bold;">
                <i class="fas fa-print"></i> IMPRIMIR
            </button>
        </body>
        </html>
    `;
    
    ventana.document.write(html);
    ventana.document.close();
}

function showNotification(message, type = 'info') {
    // Crear el contenedor de notificaciones si no existe
    let container = document.getElementById('notificationContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notificationContainer';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
        `;
        document.body.appendChild(container);
    }
    
    // Crear la notificación
    const notification = document.createElement('div');
    const colors = {
        success: '#48bb78',
        error: '#f56565',
        warning: '#ed8936',
        info: '#4299e1'
    };
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-times-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    notification.style.cssText = `
        background: white;
        border-left: 4px solid ${colors[type] || colors.info};
        border-radius: 8px;
        padding: 15px 20px;
        margin-bottom: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideInRight 0.3s ease;
    `;
    
    notification.innerHTML = `
        <i class="fas ${icons[type] || icons.info}" style="color: ${colors[type] || colors.info}; font-size: 20px;"></i>
        <span style="flex: 1; color: #2d3748;">${message}</span>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; color: #a0aec0; cursor: pointer; font-size: 20px; padding: 0; line-height: 1;">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(notification);
    
    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Agregar animaciones CSS
if (!document.getElementById('notificationStyles')) {
    const style = document.createElement('style');
    style.id = 'notificationStyles';
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}
</script>

<?php require_once '../../includes/footer.php'; ?>