<?php
require_once '../../config/database.php';
$pageTitle = 'Registro de Producción';
$currentPage = 'produccion';
require_once '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-chart-line"></i> Registro de Producción por Turno</h3>
        <div class="card-header-actions">
            <input type="date" id="filtroFechaDesde" class="filter-input">
            <input type="date" id="filtroFechaHasta" class="filter-input">
            <select id="filtroTurno" class="filter-select">
                <option value="">Todos los turnos</option>
            </select>
            <button onclick="filtrarProducciones()" class="btn-secondary">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <button onclick="abrirModalRegistro()" class="btn-primary">
                <i class="fas fa-plus"></i> Registrar Producción
            </button>
        </div>
    </div>

    <!-- Resumen del día y mes -->
    <div class="stats-container" style="margin-bottom: 20px;" id="resumenDia">
        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="stat-value" id="totalRegistros">0</div>
            <div class="stat-label">Producción Anual</div>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="stat-value" id="totalDocenas">0</div>
            <div class="stat-label">Docenas Hoy</div>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <div class="stat-value" id="totalMesActual">0</div>
            <div class="stat-label">Total Mes Actual</div>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
            <div class="stat-value" id="maquinasActivas">0</div>
            <div class="stat-label">Máquinas Activas</div>
        </div>
    </div>

    <div class="table-container">
        <table id="tablaProducciones">
            <thead>
                <tr>
                    <th>Código Lote</th>
                    <th>Fecha</th>
                    <th>Turno</th>
                    <th>Tejedor</th>
                    <th>Máquinas</th>
                    <th>Docenas</th>
                    <th>Unidades</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="bodyProducciones">
                <tr>
                    <td colspan="9" class="text-center">Cargando registros...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal para registrar producción -->
<div id="modalRegistro" class="modal">
    <div class="modal-content" style="max-width: 98vw; max-height: 100vh; overflow-y: hidden; display: flex; flex-direction: column; border-radius: 0; margin: 0;">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Registrar Producción del Turno</h3>
            <button class="close-modal" onclick="cerrarModalRegistro()">&times;</button>
        </div>
        <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column;">
            <!-- Datos del turno -->
            <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 12px;">
                <div class="form-group">
                    <label for="fecha_produccion">Fecha de Producción: <span class="required">*</span></label>
                    <input type="date" id="fecha_produccion" required>
                </div>
                
                <div class="form-group">
                    <label for="id_turno">Turno: <span class="required">*</span></label>
                    <select id="id_turno" required>
                        <option value="">Seleccione turno</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="id_tejedor">Tejedor: <span class="required">*</span></label>
                    <select id="id_tejedor" required>
                        <option value="">Seleccione tejedor</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="id_tecnico">Técnico:</label>
                    <select id="id_tecnico">
                        <option value="">Seleccione técnico (opcional)</option>
                    </select>
                </div>
            </div>
            
           <div class="form-group" style="margin-bottom: 12px;">
                <label for="observaciones_turno">Observaciones del Turno:</label>
                <textarea id="observaciones_turno" rows="2" placeholder="Ej: Apagón de luz a las 10:30 am"></textarea>
            </div>

            <!-- Botones de acciones rápidas -->
            <div style="margin-bottom: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
                <button onclick="cargarPlanGenerico()" class="btn-secondary" style="font-size: 14px;">
                    <i class="fas fa-download"></i> Cargar Plan Genérico
                </button>
               <button onclick="importarUltimoRegistro()" class="btn-secondary" style="margin-right: 10px;">
                    <i class="fas fa-history"></i> Importar último registro
                </button>
                <button onclick="limpiarTablaProduccion()" class="btn-secondary" style="font-size: 14px;">
                    <i class="fas fa-eraser"></i> Limpiar Todo
                </button>
            </div>

            <h4 style="margin-bottom: 8px; font-size: 16px;">Producción por Máquina</h4>
            
            <!-- Tabla editable de producción -->
            <div class="table-container" style="flex: 1; overflow-y: auto; min-height: 400px; max-height: none; border: 1px solid #dee2e6; border-radius: 4px;">
                <table id="tablaProduccion" class="tabla-editable">
                <thead style="position: sticky; top: 0; background: white; z-index: 10;">
                    <tr>
                        <th>Máquina</th>
                        <th style="min-width: 250px;">Producto</th>
                        <th>Docenas</th>
                        <th>Unidades</th>
                        <th style="min-width: 150px;">Observaciones</th>
                    </tr>
                </thead>
                <tbody id="bodyProduccion">
                    <!-- Se llenará con JavaScript -->
                </tbody>
                <tfoot style="background: #f8f9fa; font-weight: bold; position: sticky; bottom: 0;">
                    <tr>
                        <td colspan="2">TOTALES:</td>
                        <td colspan="2" style="text-align: center; font-size: 16px; color: #2c3e50;">
                            <span id="totalDocenasFooter">0</span> | <span id="totalUnidadesFooter">0</span>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>
        <div class="modal-footer" style="background: white; border-top: 1px solid #dee2e6; padding: 15px 20px; position: sticky; bottom: 0; z-index: 100;">
            <button onclick="cerrarModalRegistro()" class="btn-secondary">Cancelar</button>
            <button onclick="guardarProduccion()" class="btn-primary">
                <i class="fas fa-save"></i> Guardar Producción
            </button>
        </div>
    </div>
</div>

<!-- Modal de Detalle -->
<div id="modalDetalle" class="modal">
    <div class="modal-content" style="max-width: 1000px;">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Detalle de Producción</h3>
            <button class="close-modal" onclick="cerrarModalDetalle()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Información del turno -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <div>
                    <strong>Código de Lote:</strong><br>
                    <span id="detalle_codigo_lote"></span>
                </div>
                <div>
                    <strong>Fecha:</strong><br>
                    <span id="detalle_fecha"></span>
                </div>
                <div>
                    <strong>Turno:</strong><br>
                    <span id="detalle_turno"></span>
                </div>
                <div>
                    <strong>Tejedor:</strong><br>
                    <span id="detalle_tejedor"></span>
                </div>
            </div>
            <div style="margin-top: 10px;" id="detalle_observaciones_container">
                <strong>Observaciones:</strong><br>
                <span id="detalle_observaciones"></span>
            </div>

            <!-- Tabla de detalle -->
            <div class="table-container">
                <table id="tablaDetalle">
                    <thead>
                        <tr>
                            <th>Máquina</th>
                            <th>Producto</th>
                            <th>Línea</th>
                            <th>Docenas</th>
                            <th>Unidades</th>
                            <th>Total Unid.</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody id="bodyDetalle">
                    </tbody>
                    <tfoot style="background: #f8f9fa; font-weight: bold;">
                        <tr>
                            <td colspan="3">TOTALES:</td>
                            <td id="detalle_total_docenas">0</td>
                            <td id="detalle_total_unidades">0</td>
                            <td id="detalle_total_unidades_calc">0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="cerrarModalDetalle()" class="btn-secondary">Cerrar</button>
        </div>
    </div>
</div>

<style>
/* Estilos para los cards de estadísticas */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    padding: 0 20px;
}

.stat-card {
    text-align: center;
    padding: 20px;
    border-radius: 12px;
    color: white;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.stat-value {
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    opacity: 0.9;
}

/* Estilos para tabla editable */
.tabla-editable input[type="number"],
.tabla-editable select {
    width: 100%;
    padding: 5px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.tabla-editable input[type="text"] {
    width: 100%;
    padding: 5px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
}

.tabla-editable tbody tr:hover {
    background-color: #f8f9fa;
}

/* Badges de estado */
.badge-estado {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge-completado {
    background-color: #d4edda;
    color: #155724;
}

.badge-en-proceso {
    background-color: #fff3cd;
    color: #856404;
}

/* Utilidades */
.text-center {
    text-align: center;
}

/* Ajuste para el textarea de observaciones del turno */
#observaciones_turno {
    width: 100%; /* Ocupa todo el ancho del contenedor padre */
    resize: vertical; /* Permite redimensionar solo verticalmente */
}
/* Estilos existentes que ya tienes */

/* ✅ AGREGA AQUÍ EL NUEVO CSS: */

/* Estilos para campos numericos en tabla editable */
.input-docenas {
    width: 80px !important; /* Ancho para 3 digitos (0-999) */
    min-width: 80px;
}

.input-unidades {
    width: 60px !important; /* Ancho para 2 digitos (0-11) */
    min-width: 60px;
}

/* Asegurar que el total se vea bien */
.total-unidades {
    min-width: 70px;
    text-align: center;
    font-weight: 600;
    background-color: #f8f9fa;
}

/* Opcional: Centrar inputs numericos */
.input-docenas, .input-unidades {
    text-align: center;
}

/* Agrega al final de la sección <style> */
#totalDocenasFooter, #totalUnidadesFooter {
    font-weight: bold;
    color: #2c3e50;
}

#totalDocenasFooter::after {
    content: " docenas";
    font-size: 12px;
    color: #7f8c8d;
    margin-left: 3px;
}

#totalUnidadesFooter::before {
    content: "";
    margin: 0 8px;
    border-left: 1px solid #bdc3c7;
}

#totalUnidadesFooter::after {
    content: " unidades";
    font-size: 12px;
    color: #7f8c8d;
    margin-left: 3px;
}

/* Para el modal más grande */
.modal-content {
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

/* Para la tabla más compacta */
.tabla-editable tbody tr {
    height: 42px; /* Reduce un poco la altura de cada fila */
}

.tabla-editable input[type="number"],
.tabla-editable select,
.tabla-editable input[type="text"] {
    padding: 4px 6px; /* Reduce padding para más espacio */
    font-size: 13px;
}

/* Para el header sticky de la tabla */
.tabla-editable thead th {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 10;
    padding: 8px 10px;
    font-size: 14px;
}

/* Hacer todo más compacto */
.modal-header {
    padding: 12px 20px !important;
    flex-shrink: 0;
}

.modal-header h3 {
    font-size: 18px !important;
}

.close-modal {
    font-size: 24px !important;
}

.tabla-editable thead th {
    padding: 6px 8px !important;
    font-size: 13px !important;
}

.tabla-editable tbody td {
    padding: 3px 8px !important;
}

.tabla-editable tbody tr {
    height: 36px !important;
}

.tabla-editable input, .tabla-editable select {
    padding: 2px 5px !important;
    font-size: 12px !important;
}

/* Footer fijo en la parte inferior */
.modal-footer {
    flex-shrink: 0;
    background: white;
    border-top: 1px solid #dee2e6;
    padding: 12px 20px;
    position: sticky;
    bottom: 0;
    z-index: 100;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
}
</style>

<script>
const baseUrl = window.location.origin + '/mes_hermen';

let producciones = [];
let maquinas = [];
let productos = [];
let turnos = [];
let usuarios = [];

document.addEventListener('DOMContentLoaded', async function() {
    console.log('🚀 Iniciando carga del módulo...');
    
    inicializarFiltrosFecha();
    
    try {
        await cargarTurnos();
        await cargarUsuarios();
        await cargarMaquinas();
        await cargarProductos();
        
        console.log('✅ Todos los datos iniciales cargados');
        console.log('Turnos:', turnos.length, 'Usuarios:', usuarios.length, 'Máquinas:', maquinas.length);
        
        // Llamar a la función PRINCIPAL
        await cargarProducciones();
        
    } catch (error) {
        console.error('❌ Error en carga inicial:', error);
        showNotification('Error crítico al cargar datos', 'error');
    }
});

// ============================================
// CARGA DE DATOS PRINCIPAL
// ============================================

async function cargarProducciones() {
    try {
        const fechaDesde = document.getElementById('filtroFechaDesde')?.value || '';
        const fechaHasta = document.getElementById('filtroFechaHasta')?.value || '';
        const idTurno = document.getElementById('filtroTurno')?.value || '';
        
        let url = baseUrl + '/api/produccion.php?1=1';
        if (fechaDesde) url += '&fecha_desde=' + fechaDesde;
        if (fechaHasta) url += '&fecha_hasta=' + fechaHasta;
        if (idTurno) url += '&id_turno=' + idTurno;
        
        console.log('📡 Llamando API producción:', url);
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            producciones = data.producciones || [];
            console.log('✅ Producciones recibidas:', producciones.length);
            renderProducciones();
            
            // Actualizar cards
            const stats = data.estadisticas || {};
            document.getElementById('totalRegistros').textContent = `${stats.docenas_anuales || 0}|${stats.unidades_anuales || 0}`;
            document.getElementById('totalDocenas').textContent = `${stats.docenas_dia || 0}|${stats.unidades_dia || 0}`;
            document.getElementById('totalMesActual').textContent = `${stats.docenas_mes_actual || 0}|${stats.unidades_mes_actual || 0}`;
            document.getElementById('maquinasActivas').textContent = formatNumber(stats.maquinas_activas || 0);
            
        } else {
            throw new Error(data.message || 'Error en API');
        }
    } catch (error) {
        console.error('❌ Error al cargar producciones:', error);
        mostrarErrorTabla('Error al cargar: ' + error.message);
    }
}

function renderProducciones() {
    const tbody = document.getElementById('bodyProducciones');
    if (!tbody) {
        console.error('❌ tbody bodyProducciones no encontrado');
        return;
    }
    
    if (!Array.isArray(producciones) || producciones.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">No se encontraron registros de producción</td></tr>';
        return;
    }
    
    tbody.innerHTML = producciones.map(prod => {
        const fecha = new Date(prod.fecha_produccion + 'T00:00:00').toLocaleDateString('es-BO');
        const badge = prod.estado === 'completado' 
            ? '<span class="badge-estado badge-completado">Completado</span>'
            : '<span class="badge-estado badge-en-proceso">En Proceso</span>';
        
        return `
            <tr>
                <td><strong>${prod.codigo_lote_turno || 'N/A'}</strong></td>
                <td>${fecha}</td>
                <td>${prod.nombre_turno || 'N/A'}</td>
                <td>${prod.nombre_tejedor || 'N/A'}</td>
                <td class="text-center">${prod.num_maquinas || 0}</td>
                <td class="text-center">${prod.total_docenas || 0}</td>
                <td class="text-center">${prod.total_unidades_calc || 0}</td>
                <td>${badge}</td>
                <td>
                    <button onclick="verDetalle(${prod.id_produccion})" class="btn-icon btn-primary" title="Ver detalle">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="eliminarProduccion(${prod.id_produccion})" class="btn-icon btn-danger" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function mostrarErrorTabla(mensaje) {
    const tbody = document.getElementById('bodyProducciones');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center" style="color: red;">${mensaje}</td></tr>`;
    }
}

// ============================================
// CARGA DE DATOS AUXILIAR
// ============================================

async function cargarTurnos() {
    try {
        const response = await fetch(baseUrl + '/api/catalogos.php?tipo=turnos');
        const data = await response.json();
        
        if (data.success && Array.isArray(data.turnos)) {
            turnos = data.turnos;
            poblarSelectTurnos();
            console.log(`✅ Turnos cargados: ${turnos.length}`);
        } else {
            turnos = [];
        }
    } catch (error) {
        console.error('❌ Error al cargar turnos:', error);
        turnos = [];
    }
}

async function cargarUsuarios() {
    try {
        const response = await fetch(baseUrl + '/api/catalogos.php?tipo=usuarios');
        const data = await response.json();
        
        if (data.success && Array.isArray(data.usuarios)) {
            usuarios = data.usuarios;
            poblarSelectUsuarios();
            console.log(`✅ Usuarios cargados: ${usuarios.length}`);
        } else {
            usuarios = [];
        }
    } catch (error) {
        console.error('❌ Error al cargar usuarios:', error);
        usuarios = [];
    }
}

async function cargarMaquinas() {
    try {
        const response = await fetch(baseUrl + '/api/maquinas.php');
        const data = await response.json();
        
        if (data.success && Array.isArray(data.maquinas)) {
            maquinas = data.maquinas;
            console.log(`✅ Máquinas cargadas: ${maquinas.length}`);
        } else {
            maquinas = [];
        }
    } catch (error) {
        console.error('❌ Error al cargar máquinas:', error);
        maquinas = [];
    }
}

async function cargarProductos() {
    try {
        const response = await fetch(baseUrl + '/api/productos.php');
        const data = await response.json();
        
        if (data.success && Array.isArray(data.productos)) {
            productos = data.productos;
            console.log(`✅ Productos cargados: ${productos.length}`);
        } else {
            productos = [];
        }
    } catch (error) {
        console.error('❌ Error al cargar productos:', error);
        productos = [];
    }
}

// ============================================
// POBLAR SELECTS
// ============================================

function poblarSelectTurnos() {
    const selectModal = document.getElementById('id_turno');
    const selectFiltro = document.getElementById('filtroTurno');
    
    if (!selectModal || !selectFiltro) return;
    
    selectModal.innerHTML = '<option value="">Seleccione turno</option>';
    selectFiltro.innerHTML = '<option value="">Todos los turnos</option>';
    
    turnos.forEach(turno => {
        if (!turno.id_turno || !turno.nombre_turno) return;
        
        const option = document.createElement('option');
        option.value = turno.id_turno;
        option.textContent = `${turno.nombre_turno} (${turno.hora_inicio || 'N/A'} - ${turno.hora_fin || 'N/A'})`;
        
        selectModal.appendChild(option.cloneNode(true));
        selectFiltro.appendChild(option.cloneNode(true));
    });
}

function poblarSelectUsuarios() {
    const selectTejedor = document.getElementById('id_tejedor');
    const selectTecnico = document.getElementById('id_tecnico');
    
    if (!selectTejedor || !selectTecnico) return;
    
    selectTejedor.innerHTML = '<option value="">Seleccione tejedor</option>';
    selectTecnico.innerHTML = '<option value="">Sin técnico</option>';
    
    const tejedores = usuarios.filter(u => u.rol === 'tejedor');
    tejedores.forEach(usuario => {
        if (!usuario.id_usuario || !usuario.nombre_completo) return;
        
        const option = document.createElement('option');
        option.value = usuario.id_usuario;
        option.textContent = usuario.nombre_completo;
        selectTejedor.appendChild(option);
    });

    const tecnicos = usuarios.filter(u => ['tejedor', 'coordinador', 'admin', 'supervisor'].includes(u.rol));
    tecnicos.forEach(usuario => {
        if (!usuario.id_usuario || !usuario.nombre_completo) return;
        
        const option = document.createElement('option');
        option.value = usuario.id_usuario;
        option.textContent = usuario.nombre_completo;
        selectTecnico.appendChild(option);
    });
}

// ============================================
// MODAL Y FORMULARIO
// ============================================

function inicializarFiltrosFecha() {
    const hoy = new Date();
    const hace30Dias = new Date();
    hace30Dias.setDate(hoy.getDate() - 30);
    
    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');
    
    if (filtroFechaDesde) filtroFechaDesde.value = hace30Dias.toISOString().split('T')[0];
    if (filtroFechaHasta) filtroFechaHasta.value = hoy.toISOString().split('T')[0];
}

function abrirModalRegistro() {
    document.getElementById('fecha_produccion').value = new Date().toISOString().split('T')[0];
    document.getElementById('id_turno').value = '';
    document.getElementById('id_tejedor').value = '';
    document.getElementById('id_tecnico').value = '';
    document.getElementById('observaciones_turno').value = '';
    
    cargarTablaProduccion();
    document.getElementById('modalRegistro').classList.add('show');
}

function cerrarModalRegistro() {
    document.getElementById('modalRegistro').classList.remove('show');
}

function cargarTablaProduccion() {
    const tbody = document.getElementById('bodyProduccion');
    if (!tbody) return;
    
    if (!Array.isArray(maquinas) || maquinas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No hay máquinas cargadas</td></tr>';
        return;
    }
    
    // Inputs con clases de ancho específicas
    tbody.innerHTML = maquinas.map(m => `
        <tr data-id-maquina="${m.id_maquina || ''}">
                        <td><strong>${m.numero_maquina || 'N/A'}</strong></td>
            <td>
                <select class="producto-select" onchange="calcularTotales()">
                    <option value="">Sin producción</option>
                    ${productos.map(p => `<option value="${p.id_producto}">${p.descripcion_completa || 'N/A'}</option>`).join('')}
                </select>
            </td>
            <td><input type="number" class="docenas-input input-docenas" min="0" value="0" onchange="calcularTotales()"></td>
            <td><input type="number" class="unidades-input input-unidades" min="0" max="11" value="0" onchange="calcularTotales()"></td>
            <td><input type="text" class="observaciones-input" placeholder="Opcional"></td>
        </tr>
    `).join('');
    
    calcularTotales();
}
function calcularTotales() {
    const rows = document.querySelectorAll('#bodyProduccion tr');
    let totalUnidadesAbsolutas = 0;
    
    rows.forEach(row => {
        const docenas = parseInt(row.querySelector('.docenas-input').value) || 0;
        let unidades = parseInt(row.querySelector('.unidades-input').value) || 0;
        
        // Validar y ajustar unidades en tiempo real (0-11)
        if (unidades > 11) {
            // Calcular cuántas docenas extra son y añadirlas
            const docenasExtra = Math.floor(unidades / 12);
            unidades = unidades % 12;
            
            // Actualizar los campos visualmente
            row.querySelector('.unidades-input').value = unidades;
            row.querySelector('.docenas-input').value = docenas + docenasExtra;
        } else if (unidades < 0) {
            row.querySelector('.unidades-input').value = 0;
            unidades = 0;
        }
        
        // Sumar al total absoluto
        totalUnidadesAbsolutas += (docenas * 12) + unidades;
    });
    
    // Convertir el total absoluto a formato docenas|unidades
    const totalDocenas = Math.floor(totalUnidadesAbsolutas / 12);
    const totalUnidades = totalUnidadesAbsolutas % 12;
    
    // Actualizar el footer con formato docenas | unidades
    document.getElementById('totalDocenasFooter').textContent = totalDocenas;
    document.getElementById('totalUnidadesFooter').textContent = totalUnidades;
}
function limpiarTablaProduccion() {
    if (!confirm('¿Limpiar todos los datos?')) return;
    
    document.querySelectorAll('#bodyProduccion tr').forEach(row => {
        row.querySelector('.producto-select').value = '';
        row.querySelector('.docenas-input').value = 0;
        row.querySelector('.unidades-input').value = 0;
        row.querySelector('.observaciones-input').value = '';
    });
    calcularTotales();
}

// ============================================
// CARGAR PLAN Y ÚLTIMO REGISTRO
// ============================================

async function cargarPlanGenerico() {
    try {
        showNotification('Cargando plan genérico vigente...', 'info');
        
        const response = await fetch(baseUrl + '/api/plan_generico.php?vigente=1');
        const data = await response.json();
        
        if (!data.success || !data.detalle) {
            showNotification('No hay plan genérico vigente', 'warning');
            return;
        }
        
        const tbody = document.getElementById('bodyProduccion');
        const rows = tbody.querySelectorAll('tr');
        let cargados = 0;
        
        rows.forEach(row => {
            const idMaquina = parseInt(row.dataset.idMaquina);
            const asignacion = data.detalle.find(d => d.id_maquina === idMaquina);
            
            if (asignacion && asignacion.id_producto) {
                row.querySelector('.producto-select').value = asignacion.id_producto;
                cargados++;
            }
        });
        
        calcularTotales();
        showNotification(`Plan genérico cargado: ${cargados} máquinas asignadas`, 'success');
        
    } catch (error) {
        console.error('Error al cargar plan genérico:', error);
        showNotification('Error al cargar plan genérico', 'error');
    }
}

async function importarUltimoRegistro() {
    try {
        showNotification('🎯 Buscando último registro...', 'info');
        
        const response = await fetch(`${baseUrl}/api/produccion.php?action=ultimo_registro`);
        const data = await response.json();
        
        if (data.success && data.detalle && data.detalle.length > 0) {
            const tbody = document.getElementById('bodyProduccion');
            const rows = tbody.querySelectorAll('tr');
            let importados = 0;
            
            data.detalle.forEach(item => {
                rows.forEach(row => {
                    if (parseInt(row.dataset.idMaquina) === parseInt(item.id_maquina)) {
                        row.querySelector('.producto-select').value = item.id_producto;
                        row.querySelector('.docenas-input').value = 0;
                        row.querySelector('.unidades-input').value = 0;
                        importados++;
                    }
                });
            });
            
            showNotification(`Último registro importado: ${importados} máquinas`, 'success');
            calcularTotales();
        } else {
            showNotification(data.message || 'No se encontró detalle del último registro', 'warning');
        }
    } catch (error) {
        console.error('Error al importar último registro:', error);
        showNotification('Error al importar último registro', 'error');
    }
}

// ============================================
// GUARDAR, VER DETALLE Y ELIMINAR
// ============================================

async function guardarProduccion() {
    const fecha = document.getElementById('fecha_produccion').value;
    const idTurno = document.getElementById('id_turno').value;
    const idTejedor = document.getElementById('id_tejedor').value;
    const idTecnico = document.getElementById('id_tecnico').value;
    const observaciones = document.getElementById('observaciones_turno').value;
    
    if (!fecha || !idTurno || !idTejedor) {
        showNotification('Complete los campos requeridos', 'warning');
        return;
    }
    
    const rows = document.querySelectorAll('#bodyProduccion tr');
    const detalle = [];
    
    rows.forEach(row => {
    const idMaquina = parseInt(row.dataset.idMaquina);
    const idProducto = parseInt(row.querySelector('.producto-select').value) || 0;
    let docenas = parseInt(row.querySelector('.docenas-input').value) || 0;
    let unidades = parseInt(row.querySelector('.unidades-input').value) || 0;
    const obs = row.querySelector('.observaciones-input').value;
    
    // Normalizar: convertir unidades > 11 a docenas
    if (unidades > 11) {
        const docenasExtra = Math.floor(unidades / 12);
        unidades = unidades % 12;
        docenas += docenasExtra;
        
        // Actualizar visualmente por si acaso
        row.querySelector('.docenas-input').value = docenas;
        row.querySelector('.unidades-input').value = unidades;
    }
    
    if (idProducto > 0 && (docenas > 0 || unidades > 0)) {
        detalle.push({
            id_maquina: idMaquina,
            id_producto: idProducto,
            docenas_producidas: docenas,
            unidades_producidas: unidades,
            calidad: 'primera',
            observaciones: obs
        });
    }
});
    
    if (detalle.length === 0) {
        showNotification('Debe registrar producción de al menos una máquina', 'warning');
        return;
    }
    
    const datos = {
        fecha_produccion: fecha,
        id_turno: parseInt(idTurno),
        id_tejedor: parseInt(idTejedor),
        id_tecnico: idTecnico ? parseInt(idTecnico) : null,
        observaciones: observaciones,
        detalle: detalle
    };
    
    try {
        const response = await fetch(baseUrl + '/api/produccion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(`Producción registrada: ${data.codigo_lote} (${data.registros} máquinas)`, 'success');
            cerrarModalRegistro();
            cargarProducciones();
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Error al guardar producción:', error);
        showNotification('Error al guardar la producción', 'error');
    }
}

async function verDetalle(idProduccion) {
    try {
        const response = await fetch(baseUrl + '/api/produccion.php?id_produccion=' + idProduccion);
        const data = await response.json();
        
        if (data.success) {
            const p = data.produccion;
            const detalle = data.detalle;
            
            document.getElementById('detalle_codigo_lote').textContent = p.codigo_lote_turno;
            document.getElementById('detalle_fecha').textContent = formatDate(p.fecha_produccion);
            document.getElementById('detalle_turno').textContent = p.nombre_turno + ' (' + p.hora_inicio + ' - ' + p.hora_fin + ')';
            document.getElementById('detalle_tejedor').textContent = p.nombre_tejedor;
            
            if (p.observaciones) {
                document.getElementById('detalle_observaciones').textContent = p.observaciones;
                document.getElementById('detalle_observaciones_container').style.display = 'block';
            } else {
                document.getElementById('detalle_observaciones_container').style.display = 'none';
            }
            
            const tbody = document.getElementById('bodyDetalle');
            tbody.innerHTML = detalle.map(d => `
                <tr>
                    <td><strong>${d.numero_maquina}</strong></td>
                    <td>${d.descripcion_completa}</td>
                    <td><span class="badge badge-${d.codigo_linea}">${d.nombre_linea}</span></td>
                    <td>${d.docenas_producidas}</td>
                    <td>${d.unidades_producidas}</td>
                    <td>${d.total_unidades_calculado || 0}</td>
                    <td>${d.observaciones || '-'}</td>
                </tr>
            `).join('');
            
            let totalDocenas = 0;
            let totalUnidades = 0;
            let totalUnidadesCalc = 0;
            
            detalle.forEach(item => {
                totalDocenas += parseInt(item.docenas_producidas);
                totalUnidades += parseInt(item.unidades_producidas);
                totalUnidadesCalc += parseInt(item.total_unidades_calculado);
            });
            
            document.getElementById('detalle_total_docenas').textContent = totalDocenas;
            document.getElementById('detalle_total_unidades').textContent = totalUnidades;
            document.getElementById('detalle_total_unidades_calc').textContent = totalUnidadesCalc;
            
            document.getElementById('modalDetalle').classList.add('show');
        }
    } catch (error) {
        console.error('Error al ver detalle:', error);
        showNotification('Error al cargar el detalle', 'error');
    }
}

function cerrarModalDetalle() {
    document.getElementById('modalDetalle').classList.remove('show');
}

async function eliminarProduccion(idProduccion) {
    if (!confirm('¿Está seguro de eliminar este registro de producción?')) {
        return;
    }
    
    try {
        const response = await fetch(baseUrl + '/api/produccion.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_produccion: idProduccion })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Registro eliminado exitosamente', 'success');
            cargarProducciones();
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Error al eliminar:', error);
        showNotification('Error al eliminar el registro', 'error');
    }
}

// ============================================
// UTILIDADES
// ============================================

function formatDate(date) {
    if (!date) return '';
    const d = new Date(date + 'T00:00:00');
    return d.toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatNumber(number) {
    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function showNotification(message, type = 'info') {
    const colors = { success: '#28a745', error: '#dc3545', warning: '#ffc107', info: '#17a2b8' };
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 8px;
        color: white; z-index: 9999; font-weight: 500; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        background-color: ${colors[type] || colors.info}; transition: transform 0.3s ease;
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => notification.style.transform = 'translateX(0)', 10);
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function filtrarProducciones() {
    cargarProducciones();
}

</script>
<?php require_once '../../includes/footer.php'; ?>