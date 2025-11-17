<?php
/**
 * Vista: Vaporizado
 * Sistema MES Hermen Ltda.
 * Fecha: 16 de Noviembre de 2025
 * Versión: 1.0
 */

require_once '../../config/database.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Vaporizado';
$currentPage = 'vaporizado';
require_once '../../includes/header.php';
?>

<!-- Contenedor Principal -->
<div class="container-fluid">
    
    <!-- Header -->
    <div class="page-header">
        <h1>💨 Vaporizado</h1>
        <p class="text-muted">Control de batches de vaporizado (40-90 docenas ideales)</p>
    </div>
    
    <!-- Cards de Estadísticas -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        
        <!-- Stock Revisado -->
        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="stat-icon">✅</div>
            <div class="stat-content">
                <div class="stat-value" id="statStockRevisado">0|0</div>
                <div class="stat-label">Stock Revisado</div>
                <div class="stat-sublabel" id="statProductosRevisado">0 productos</div>
            </div>
        </div>
        
        <!-- Stock Vaporizado -->
        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="stat-icon">💨</div>
            <div class="stat-content">
                <div class="stat-value" id="statStockVaporizado">0|0</div>
                <div class="stat-label">Stock Vaporizado</div>
                <div class="stat-sublabel" id="statProductosVaporizado">0 productos</div>
            </div>
        </div>
        
        <!-- Batches del Mes -->
        <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <div class="stat-icon">📦</div>
            <div class="stat-content">
                <div class="stat-value" id="statBatches">0</div>
                <div class="stat-label">Batches del Mes</div>
                <div class="stat-sublabel">vaporizados</div>
            </div>
        </div>
        
    </div>
    
    <!-- Panel Principal -->
    <div class="card">
        <div class="card-header">
            <div class="header-left">
                <h3>📋 Batches de Vaporizado</h3>
            </div>
            <div class="header-right">
                <button class="btn btn-primary" onclick="abrirModalVaporizado()">
                    <i class="fas fa-plus"></i> Registrar Batch
                </button>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="card-filters">
            <div class="filter-group">
                <label>Fecha Desde:</label>
                <input type="date" id="filtroFechaDesde" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" onchange="cargarVaporizados()">
            </div>
            
            <div class="filter-group">
                <label>Fecha Hasta:</label>
                <input type="date" id="filtroFechaHasta" value="<?php echo date('Y-m-d'); ?>" onchange="cargarVaporizados()">
            </div>
            
            <div class="filter-group">
                <label>Turno:</label>
                <select id="filtroTurno" onchange="cargarVaporizados()">
                    <option value="">Todos</option>
                </select>
            </div>
        </div>
        
        <!-- Tabla de Batches -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Código Lote</th>
                        <th>Fecha</th>
                        <th>Turno</th>
                        <th>Operario</th>
                        <th>Total</th>
                        <th>Tiempo</th>
                        <th>Rango</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="bodyVaporizados">
                    <tr>
                        <td colspan="8" class="text-center">Cargando...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
</div>

<!-- Modal: Registrar Vaporizado -->
<div id="modalVaporizado" class="modal">
    <div class="modal-content" style="max-width: 1100px;">
        <div class="modal-header">
            <h3>💨 Registrar Batch de Vaporizado</h3>
            <button class="modal-close" onclick="cerrarModalVaporizado()">&times;</button>
        </div>
        
        <div class="modal-body">
            <!-- Datos del Batch -->
            <div class="form-row">
                <div class="form-group" style="width: 25%;">
                    <label>Fecha: *</label>
                    <input type="date" id="modalFecha" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group" style="width: 25%;">
                    <label>Turno: *</label>
                    <select id="modalTurno" required>
                        <option value="">Seleccione...</option>
                    </select>
                </div>
                
                <div class="form-group" style="width: 25%;">
                    <label>Operario: *</label>
                    <select id="modalOperario" required>
                        <option value="">Seleccione...</option>
                    </select>
                </div>
                
                <div class="form-group" style="width: 25%;">
                    <label>Tiempo Vapor (min): *</label>
                    <input type="number" id="modalTiempo" value="35" min="1" max="120" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group" style="width: 100%;">
                    <label>Observaciones Generales:</label>
                    <input type="text" id="modalObservaciones" placeholder="Opcional">
                </div>
            </div>
            
            <hr>
            
            <!-- Stock Revisado Disponible -->
            <div style="margin-bottom: 15px;">
                <h4>📦 Productos Revisados Disponibles para Vaporizar</h4>
                <div class="form-row">
                    <div class="form-group" style="width: 50%;">
                        <label>Filtrar por Línea:</label>
                        <select id="modalFiltroLinea" onchange="cargarStockRevisado()">
                            <option value="">Todas las líneas</option>
                        </select>
                    </div>
                    <div class="form-group" style="width: 50%;">
                        <label>Buscar:</label>
                        <input type="text" id="modalBusqueda" placeholder="Código o descripción..." onkeyup="filtrarTablaStock()">
                    </div>
                </div>
            </div>
            
            <!-- Tabla de Stock Revisado -->
            <div class="table-container" style="max-height: 350px; overflow-y: auto; border: 2px solid #e0e0e0; border-radius: 8px;">
                <table class="table" id="tablaStockRevisado" style="margin-bottom: 0;">
                    <thead style="position: sticky; top: 0; background: white; z-index: 10;">
                        <tr>
                            <th style="width: 40px;">Sel</th>
                            <th>Línea</th>
                            <th>Producto</th>
                            <th>Talla</th>
                            <th>Stock Revisado</th>
                            <th>Vaporizar Ahora</th>
                            <th>Obs.</th>
                        </tr>
                    </thead>
                    <tbody id="bodyStockRevisado">
                        <tr>
                            <td colspan="7" class="text-center">Cargando stock...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Resumen del Batch -->
            <div id="resumenBatch" style="margin-top: 15px; padding: 15px; background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%); border-radius: 8px; border: 2px solid #3498db;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h4 style="margin: 0 0 10px 0;">📊 RESUMEN DEL BATCH</h4>
                        <div style="display: flex; gap: 30px;">
                            <div>
                                <strong>Total a vaporizar:</strong> <span id="totalDocenas" style="font-size: 20px; color: #3498db;">0</span> doc | 
                                <span id="totalUnidades" style="font-size: 20px; color: #3498db;">0</span> uni
                            </div>
                            <div>
                                <strong>Productos:</strong> <span id="totalProductos" style="font-size: 18px;">0</span>
                            </div>
                        </div>
                    </div>
                    <div id="indicadorRango" style="padding: 10px 20px; border-radius: 8px; font-weight: bold; font-size: 14px;">
                        <!-- Se llenará dinámicamente -->
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border-radius: 4px;">
                <strong>💡 Rango Ideal:</strong> 40-90 docenas por batch. 
                El sistema te avisará si estás fuera del rango recomendado.
            </div>
            
        </div>
        
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModalVaporizado()">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarVaporizado()">
                <i class="fas fa-save"></i> 💨 Guardar Batch
            </button>
        </div>
    </div>
</div>

<!-- Modal: Ver Detalle -->
<div id="modalDetalle" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3>📄 Detalle del Batch</h3>
            <button class="modal-close" onclick="cerrarModalDetalle()">&times;</button>
        </div>
        
        <div class="modal-body">
            <div id="detalleContenido"></div>
        </div>
        
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModalDetalle()">Cerrar</button>
        </div>
    </div>
</div>

<style>
/* Stats Grid */
.stats-grid {
    display: grid;
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    padding: 20px;
    border-radius: 12px;
    color: white;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    font-size: 36px;
    opacity: 0.9;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    opacity: 0.9;
}

.stat-sublabel {
    font-size: 12px;
    opacity: 0.8;
}

/* Form Rows */
.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 5px;
    font-weight: 500;
    font-size: 13px;
    color: #333;
}

.form-group input,
.form-group select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

/* Badges */
.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.badge-ideal { background: #d4edda; color: #155724; }
.badge-bajo { background: #fff3cd; color: #856404; }
.badge-alto { background: #ffe5b4; color: #d97706; }

/* Input pequeño */
.input-cantidad {
    width: 60px;
    text-align: center;
    padding: 4px 8px !important;
}

input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

/* Responsive */
@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
    }
    
    .form-group {
        width: 100% !important;
    }
}
</style>

<script>
const baseUrl = window.location.origin + '/mes_hermen';
let stockRevisado = [];
let stockRevisadoFiltrado = [];
let vaporizados = [];
let turnos = [];
let lineas = [];
let usuarios = [];

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    cargarTurnos();
    cargarLineas();
    cargarUsuarios();
    cargarEstadisticas();
    cargarVaporizados();
});

/**
 * Cargar turnos
 */
async function cargarTurnos() {
    try {
        const response = await fetch(`${baseUrl}/api/catalogos.php?tipo=turnos`);
        const data = await response.json();
        
        if (data.success) {
            turnos = data.turnos;
            
            const selects = ['filtroTurno', 'modalTurno'];
            selects.forEach(selectId => {
                const select = document.getElementById(selectId);
                turnos.forEach(turno => {
                    const option = document.createElement('option');
                    option.value = turno.id_turno;
                    option.textContent = turno.nombre_turno;
                    select.appendChild(option);
                });
            });
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

/**
 * Cargar líneas
 */
async function cargarLineas() {
    try {
        const response = await fetch(`${baseUrl}/api/catalogos.php?tipo=lineas`);
        const data = await response.json();
        
        if (data.success) {
            lineas = data.lineas;
            
            const select = document.getElementById('modalFiltroLinea');
            lineas.forEach(linea => {
                const option = document.createElement('option');
                option.value = linea.id_linea;
                option.textContent = linea.nombre_linea;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

/**
 * Cargar usuarios
 */
async function cargarUsuarios() {
    try {
        const response = await fetch(`${baseUrl}/api/catalogos.php?tipo=usuarios`);
        const data = await response.json();
        
        if (data.success) {
            usuarios = data.usuarios;
            
            const select = document.getElementById('modalOperario');
            usuarios.forEach(u => {
                const option = document.createElement('option');
                option.value = u.id_usuario;
                option.textContent = u.nombre_completo;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

/**
 * Cargar estadísticas
 */
async function cargarEstadisticas() {
    try {
        const response = await fetch(`${baseUrl}/api/inventario.php?action=estadisticas`);
        const data = await response.json();
        
        if (data.success && data.estadisticas.por_tipo) {
            // Stock Revisado
            if (data.estadisticas.por_tipo.revisado) {
                const rev = data.estadisticas.por_tipo.revisado;
                document.getElementById('statStockRevisado').textContent = 
                    `${rev.docenas}|${rev.unidades}`;
                document.getElementById('statProductosRevisado').textContent = 
                    `${rev.productos} productos`;
            }
            
            // Stock Vaporizado
            if (data.estadisticas.por_tipo.vaporizado) {
                const vap = data.estadisticas.por_tipo.vaporizado;
                document.getElementById('statStockVaporizado').textContent = 
                    `${vap.docenas}|${vap.unidades}`;
                document.getElementById('statProductosVaporizado').textContent = 
                    `${vap.productos} productos`;
            }
        }
        
        // Batches del mes
        const respBatches = await fetch(`${baseUrl}/api/vaporizado.php?action=estadisticas`);
        const dataBatches = await respBatches.json();
        
        if (dataBatches.success) {
            document.getElementById('statBatches').textContent = 
                dataBatches.estadisticas.batches_mes || 0;
        }
        
    } catch (error) {
        console.error('Error:', error);
    }
}

/**
 * Cargar vaporizados
 */
async function cargarVaporizados() {
    try {
        const fechaDesde = document.getElementById('filtroFechaDesde').value;
        const fechaHasta = document.getElementById('filtroFechaHasta').value;
        const idTurno = document.getElementById('filtroTurno').value;
        
        let url = `${baseUrl}/api/vaporizado.php?action=listar&fecha_desde=${fechaDesde}&fecha_hasta=${fechaHasta}`;
        if (idTurno) url += `&id_turno=${idTurno}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            vaporizados = data.vaporizados;
            renderVaporizados();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

/**
 * Renderizar vaporizados
 */
function renderVaporizados() {
    const tbody = document.getElementById('bodyVaporizados');
    
    if (vaporizados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No hay registros</td></tr>';
        return;
    }
    
    tbody.innerHTML = vaporizados.map(vap => {
        let badgeRango = '';
        if (vap.rango_estado === 'ideal') {
            badgeRango = '<span class="badge badge-ideal">✅ IDEAL</span>';
        } else if (vap.rango_estado === 'bajo') {
            badgeRango = '<span class="badge badge-bajo">⚠️ BAJO</span>';
        } else {
            badgeRango = '<span class="badge badge-alto">⚠️ ALTO</span>';
        }
        
        return `
            <tr>
                <td><strong>${vap.codigo_lote_vaporizado}</strong></td>
                <td>${formatearFecha(vap.fecha_vaporizado)}</td>
                <td>${vap.nombre_turno}</td>
                <td>${vap.operario}</td>
                <td style="text-align: center; font-weight: 600;">${vap.docenas}|${vap.unidades}</td>
                <td style="text-align: center;">${vap.tiempo_vapor} min</td>
                <td style="text-align: center;">${badgeRango}</td>
                <td>
                    <button class="btn-icon btn-primary" onclick="verDetalle(${vap.id_vaporizado})" title="Ver detalle">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * Abrir modal
 */
function abrirModalVaporizado() {
    document.getElementById('modalVaporizado').classList.add('show');
    cargarStockRevisado();
}

/**
 * Cerrar modal
 */
function cerrarModalVaporizado() {
    document.getElementById('modalVaporizado').classList.remove('show');
    limpiarFormulario();
}

/**
 * Cargar stock revisado
 */
async function cargarStockRevisado() {
    try {
        const idLinea = document.getElementById('modalFiltroLinea').value;
        
        let url = `${baseUrl}/api/vaporizado.php?action=stock_revisado`;
        if (idLinea) url += `&id_linea=${idLinea}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            stockRevisado = data.stock;
            stockRevisadoFiltrado = stockRevisado;
            renderStockRevisado();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

/**
 * Renderizar stock
 */
function renderStockRevisado() {
    const tbody = document.getElementById('bodyStockRevisado');
    
    if (stockRevisadoFiltrado.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No hay stock revisado disponible</td></tr>';
        return;
    }
    
    tbody.innerHTML = stockRevisadoFiltrado.map((item, index) => `
        <tr id="row_${item.id_producto}">
            <td style="text-align: center;">
                <input type="checkbox" class="chk-producto" data-index="${index}" onchange="toggleProducto(${index})">
            </td>
            <td>
                <span class="badge badge-${item.codigo_linea.toLowerCase()}">${item.codigo_linea}</span>
            </td>
            <td>
                <div style="font-size: 12px; font-weight: 500;">${item.descripcion_completa}</div>
                <small style="color: #666;">${item.codigo_producto}</small>
            </td>
            <td>${item.talla}</td>
            <td style="text-align: center; font-weight: 600; background: #f0f8ff;">${item.docenas}|${item.unidades}</td>
            <td style="background: #fff9e6;">
                <input type="number" class="input-cantidad docenas-input" data-producto="${item.id_producto}" 
                       min="0" max="${item.docenas}" value="0" disabled onchange="calcularTotales()"> |
                <input type="number" class="input-cantidad unidades-input" data-producto="${item.id_producto}" 
                       min="0" max="11" value="0" disabled onchange="calcularTotales()">
            </td>
            <td>
                <input type="checkbox" class="obs-check" data-producto="${item.id_producto}" disabled>
                <small>Con obs.</small>
            </td>
        </tr>
    `).join('');
}

/**
 * Toggle producto
 */
function toggleProducto(index) {
    const item = stockRevisadoFiltrado[index];
    const isChecked = document.querySelector(`input[data-index="${index}"]`).checked;
    
    const row = document.getElementById(`row_${item.id_producto}`);
    row.querySelectorAll('input').forEach(input => {
        if (!input.classList.contains('chk-producto')) {
            input.disabled = !isChecked;
        }
    });
    
    if (isChecked) {
        row.querySelector('.docenas-input').value = item.docenas;
        row.querySelector('.unidades-input').value = item.unidades;
    } else {
        row.querySelector('.docenas-input').value = 0;
        row.querySelector('.unidades-input').value = 0;
    }
    
    calcularTotales();
}

/**
 * Calcular totales del batch
 */
function calcularTotales() {
    let totalUnidades = 0;
    let productosSeleccionados = 0;
    
    document.querySelectorAll('.chk-producto:checked').forEach(chk => {
        const index = chk.dataset.index;
        const item = stockRevisadoFiltrado[index];
        
        const docenas = parseInt(document.querySelector(`.docenas-input[data-producto="${item.id_producto}"]`).value) || 0;
        const unidades = parseInt(document.querySelector(`.unidades-input[data-producto="${item.id_producto}"]`).value) || 0;
        
        totalUnidades += (docenas * 12) + unidades;
        productosSeleccionados++;
    });
    
    const totalDocenas = Math.floor(totalUnidades / 12);
    const totalUni = totalUnidades % 12;
    
    document.getElementById('totalDocenas').textContent = totalDocenas;
    document.getElementById('totalUnidades').textContent = totalUni;
    document.getElementById('totalProductos').textContent = productosSeleccionados;
    
    // Indicador de rango
    const indicador = document.getElementById('indicadorRango');
    if (totalDocenas >= 40 && totalDocenas <= 90) {
        indicador.innerHTML = '✅ RANGO IDEAL';
        indicador.style.background = '#d4edda';
        indicador.style.color = '#155724';
    } else if (totalDocenas < 40) {
        indicador.innerHTML = '⚠️ BATCH PEQUEÑO';
        indicador.style.background = '#fff3cd';
        indicador.style.color = '#856404';
    } else {
        indicador.innerHTML = '⚠️ BATCH GRANDE';
        indicador.style.background = '#ffe5b4';
        indicador.style.color = '#d97706';
    }
}

/**
 * Filtrar tabla
 */
function filtrarTablaStock() {
    const busqueda = document.getElementById('modalBusqueda').value.toLowerCase();
    
    if (!busqueda) {
        stockRevisadoFiltrado = stockRevisado;
    } else {
        stockRevisadoFiltrado = stockRevisado.filter(item =>
            item.codigo_producto.toLowerCase().includes(busqueda) ||
            item.descripcion_completa.toLowerCase().includes(busqueda)
        );
    }
    
    renderStockRevisado();
}

/**
 * Guardar vaporizado
 */
async function guardarVaporizado() {
    const fecha = document.getElementById('modalFecha').value;
    const idTurno = document.getElementById('modalTurno').value;
    const idOperario = document.getElementById('modalOperario').value;
    const tiempoVapor = parseInt(document.getElementById('modalTiempo').value);
    const observaciones = document.getElementById('modalObservaciones').value;
    
    if (!fecha || !idTurno || !idOperario) {
        alert('Por favor complete todos los campos requeridos');
        return;
    }
    
    // Obtener detalle
    const detalle = [];
    document.querySelectorAll('.chk-producto:checked').forEach(chk => {
        const index = chk.dataset.index;
        const item = stockRevisadoFiltrado[index];
        
        const docenas = parseInt(document.querySelector(`.docenas-input[data-producto="${item.id_producto}"]`).value) || 0;
        const unidades = parseInt(document.querySelector(`.unidades-input[data-producto="${item.id_producto}"]`).value) || 0;
        const tieneObs = document.querySelector(`.obs-check[data-producto="${item.id_producto}"]`).checked ? 1 : 0;
        
        if (docenas > 0 || unidades > 0) {
            detalle.push({
                id_producto: item.id_producto,
                id_revisadora: <?php echo $_SESSION['user_id']; ?>,
                docenas: docenas,
                unidades: unidades,
                tiene_observaciones: tieneObs,
                observaciones: ''
            });
        }
    });
    
    if (detalle.length === 0) {
        alert('Debe seleccionar al menos un producto');
        return;
    }
    
    try {
        const response = await fetch(`${baseUrl}/api/vaporizado.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                fecha_vaporizado: fecha,
                id_turno: idTurno,
                id_operario: idOperario,
                tiempo_vapor: tiempoVapor,
                observaciones: observaciones,
                detalle: detalle
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            let mensaje = '✅ Batch vaporizado registrado exitosamente\n\n';
            mensaje += `Total: ${data.total_docenas} docenas\n`;
            mensaje += `Estado: ${data.rango_estado === 'ideal' ? 'RANGO IDEAL ✅' : 'Fuera de rango ⚠️'}`;
            
            alert(mensaje);
            cerrarModalVaporizado();
            cargarVaporizados();
            cargarEstadisticas();
        } else {
            alert('❌ Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al guardar');
    }
}

/**
 * Ver detalle
 */
async function verDetalle(idVaporizado) {
    try {
        const response = await fetch(`${baseUrl}/api/vaporizado.php?action=detalle&id_vaporizado=${idVaporizado}`);
        const data = await response.json();
        
        if (data.success) {
            const vap = data.vaporizado;
            const detalle = data.detalle;
            
            let html = `
                <div style="margin-bottom: 20px;">
                    <h4>${vap.codigo_lote_vaporizado}</h4>
                    <p><strong>Fecha:</strong> ${formatearFecha(vap.fecha_vaporizado)} - ${vap.nombre_turno}</p>
                    <p><strong>Operario:</strong> ${vap.operario}</p>
                    <p><strong>Tiempo de Vapor:</strong> ${vap.tiempo_vapor} minutos</p>
                    ${vap.observaciones ? `<p><strong>Observaciones:</strong> ${vap.observaciones}</p>` : ''}
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Revisadora</th>
                            <th>Cantidad</th>
                            <th>Obs.</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            detalle.forEach(item => {
                html += `
                    <tr>
                        <td>
                            <span class="badge badge-${item.codigo_linea.toLowerCase()}">${item.codigo_linea}</span>
                            ${item.descripcion_completa} - ${item.talla}
                        </td>
                        <td>${item.revisadora}</td>
                        <td style="text-align: center; font-weight: 600;">${item.docenas_vaporizadas}|${item.unidades_vaporizadas}</td>
                        <td>${item.tiene_observaciones ? '⚠️ Con obs.' : '-'}</td>
                    </tr>
                `;
            });
            
            html += '</tbody></table>';
            
            document.getElementById('detalleContenido').innerHTML = html;
            document.getElementById('modalDetalle').classList.add('show');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

/**
 * Cerrar modal detalle
 */
function cerrarModalDetalle() {
    document.getElementById('modalDetalle').classList.remove('show');
}

/**
 * Limpiar formulario
 */
function limpiarFormulario() {
    document.getElementById('modalFecha').value = '<?php echo date("Y-m-d"); ?>';
    document.getElementById('modalTurno').value = '';
    document.getElementById('modalOperario').value = '';
    document.getElementById('modalTiempo').value = '35';
    document.getElementById('modalObservaciones').value = '';
    document.getElementById('modalFiltroLinea').value = '';
    document.getElementById('modalBusqueda').value = '';
    
    document.getElementById('totalDocenas').textContent = '0';
    document.getElementById('totalUnidades').textContent = '0';
    document.getElementById('totalProductos').textContent = '0';
}

/**
 * Formatear fecha
 */
function formatearFecha(fecha) {
    if (!fecha) return '-';
    const d = new Date(fecha + 'T00:00:00');
    return d.toLocaleDateString('es-BO');
}
</script>

<?php require_once '../../includes/footer.php'; ?>