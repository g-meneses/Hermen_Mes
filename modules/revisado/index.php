<?php
/**
 * Vista: Revisado Crudo
 * Sistema MES Hermen Ltda.
 * Fecha: 16 de Noviembre de 2025
 * Versión: 1.0
 */

require_once '../../config/database.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Revisado Crudo';
$currentPage = 'revisado';
require_once '../../includes/header.php';
?>

<!-- Contenedor Principal -->
<div class="container-fluid">
    
    <!-- Header con Título -->
    <div class="page-header">
        <h1>✅ Revisado Crudo</h1>
        <p class="text-muted">Control de calidad de producción tejida</p>
    </div>
    
    <!-- Cards de Estadísticas -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        
        <!-- Stock en Tejido -->
        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="stat-icon">🧵</div>
            <div class="stat-content">
                <div class="stat-value" id="statStockTejido">0|0</div>
                <div class="stat-label">Stock en Tejido</div>
                <div class="stat-sublabel" id="statProductosTejido">0 productos</div>
            </div>
        </div>
        
        <!-- Stock Revisado -->
        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="stat-icon">✅</div>
            <div class="stat-content">
                <div class="stat-value" id="statStockRevisado">0|0</div>
                <div class="stat-label">Stock Revisado</div>
                <div class="stat-sublabel" id="statProductosRevisado">0 productos</div>
            </div>
        </div>
        
        <!-- Batches del Mes -->
        <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <div class="stat-icon">📦</div>
            <div class="stat-content">
                <div class="stat-value" id="statBatches">0</div>
                <div class="stat-label">Batches del Mes</div>
                <div class="stat-sublabel">registros</div>
            </div>
        </div>
        
    </div>
    
    <!-- Panel Principal -->
    <div class="card">
        <div class="card-header">
            <div class="header-left">
                <h3>📋 Registros de Revisado Crudo</h3>
            </div>
            <div class="header-right">
                <button class="btn btn-primary" onclick="abrirModalRevisado()">
                    <i class="fas fa-plus"></i> Registrar Revisado
                </button>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="card-filters">
            <div class="filter-group">
                <label>Fecha Desde:</label>
                <input type="date" id="filtroFechaDesde" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" onchange="cargarRevisados()">
            </div>
            
            <div class="filter-group">
                <label>Fecha Hasta:</label>
                <input type="date" id="filtroFechaHasta" value="<?php echo date('Y-m-d'); ?>" onchange="cargarRevisados()">
            </div>
            
            <div class="filter-group">
                <label>Turno:</label>
                <select id="filtroTurno" onchange="cargarRevisados()">
                    <option value="">Todos</option>
                </select>
            </div>
        </div>
        
        <!-- Tabla de Registros -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Código Lote</th>
                        <th>Fecha</th>
                        <th>Turno</th>
                        <th>Total</th>
                        <th>Primera</th>
                        <th>Segunda</th>
                        <th>Observada</th>
                        <th>Desperdicio</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="bodyRevisados">
                    <tr>
                        <td colspan="9" class="text-center">Cargando...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
</div>

<!-- Modal: Registrar Revisado -->
<div id="modalRevisado" class="modal">
    <div class="modal-content" style="max-width: 1000px;">
        <div class="modal-header">
            <h3>✅ Registrar Revisado Crudo</h3>
            <button class="modal-close" onclick="cerrarModalRevisado()">&times;</button>
        </div>
        
        <div class="modal-body">
            <!-- Datos Generales -->
            <div class="form-row">
                <div class="form-group" style="width: 33%;">
                    <label>Fecha: *</label>
                    <input type="date" id="modalFecha" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group" style="width: 33%;">
                    <label>Turno: *</label>
                    <select id="modalTurno" required>
                        <option value="">Seleccione...</option>
                    </select>
                </div>
                
                <div class="form-group" style="width: 34%;">
                    <label>Observaciones Generales:</label>
                    <input type="text" id="modalObservaciones" placeholder="Opcional">
                </div>
            </div>
            
            <hr>
            
            <!-- Stock Disponible en Tejido -->
            <div style="margin-bottom: 15px;">
                <h4>📦 Stock Disponible en Tejido</h4>
                <div class="form-row">
                    <div class="form-group" style="width: 50%;">
                        <label>Filtrar por Línea:</label>
                        <select id="modalFiltroLinea" onchange="cargarStockTejido()">
                            <option value="">Todas las líneas</option>
                        </select>
                    </div>
                    <div class="form-group" style="width: 50%;">
                        <label>Buscar:</label>
                        <input type="text" id="modalBusqueda" placeholder="Código o descripción..." onkeyup="filtrarTablaStock()">
                    </div>
                </div>
            </div>
            
            <!-- Tabla de Stock Tejido -->
            <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                <table class="table" id="tablaStockTejido">
                    <thead>
                        <tr>
                            <th style="width: 40px;">Sel</th>
                            <th>Línea</th>
                            <th>Producto</th>
                            <th>Talla</th>
                            <th>Stock Tejido</th>
                            <th>Revisadora</th>
                            <th>Revisar</th>
                            <th>Calidad</th>
                            <th style="width: 150px;">Obs.</th>
                        </tr>
                    </thead>
                    <tbody id="bodyStockTejido">
                        <tr>
                            <td colspan="9" class="text-center">Cargando stock...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 10px; padding: 10px; background: #e3f2fd; border-radius: 4px;">
                <strong>💡 Ayuda:</strong> 
                Selecciona los productos que se revisaron. Ingresa cuánto se revisó de cada uno, 
                quién lo revisó y clasifica la calidad.
            </div>
            
        </div>
        
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModalRevisado()">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarRevisado()">
                <i class="fas fa-save"></i> Guardar Revisado
            </button>
        </div>
    </div>
</div>

<!-- Modal: Ver Detalle -->
<div id="modalDetalle" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3>📄 Detalle de Revisado</h3>
            <button class="modal-close" onclick="cerrarModalDetalle()">&times;</button>
        </div>
        
        <div class="modal-body">
            <div id="detalleContenido">
                <!-- Se llenará dinámicamente -->
            </div>
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
    margin-bottom: 3px;
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
    text-transform: uppercase;
}

.badge-primera { background: #d4edda; color: #155724; }
.badge-segunda { background: #fff3cd; color: #856404; }
.badge-observada { background: #ffe5b4; color: #d97706; }
.badge-desperdicio { background: #f8d7da; color: #721c24; }

/* Checkbox personalizado */
input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

/* Input pequeño para cantidades */
.input-cantidad {
    width: 60px;
    text-align: center;
    padding: 4px 8px !important;
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
let stockTejido = [];
let stockTejidoFiltrado = [];
let revisados = [];
let turnos = [];
let lineas = [];
let usuarios = [];

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    cargarTurnos();
    cargarLineas();
    cargarUsuarios();
    cargarEstadisticas();
    cargarRevisados();
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
            
            // Llenar selects
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
        console.error('Error al cargar turnos:', error);
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
        console.error('Error al cargar líneas:', error);
    }
}

/**
 * Cargar usuarios (revisadoras)
 */
async function cargarUsuarios() {
    try {
        const response = await fetch(`${baseUrl}/api/catalogos.php?tipo=usuarios`);
        const data = await response.json();
        
        if (data.success) {
            usuarios = data.usuarios;
        }
    } catch (error) {
        console.error('Error al cargar usuarios:', error);
    }
}

/**
 * Cargar estadísticas
 */
async function cargarEstadisticas() {
    try {
        // Stock en Tejido
        const respTejido = await fetch(`${baseUrl}/api/inventario.php?action=estadisticas`);
        const dataTejido = await respTejido.json();
        
        if (dataTejido.success && dataTejido.estadisticas.por_tipo.tejido) {
            const tejido = dataTejido.estadisticas.por_tipo.tejido;
            document.getElementById('statStockTejido').textContent = 
                `${tejido.docenas}|${tejido.unidades}`;
            document.getElementById('statProductosTejido').textContent = 
                `${tejido.productos} productos`;
        }
        
        // Stock en Revisado
        const respRevisado = await fetch(`${baseUrl}/api/revisado.php?action=estadisticas`);
        const dataRevisado = await respRevisado.json();
        
        if (dataRevisado.success) {
            const rev = dataRevisado.estadisticas.inventario_revisado;
            document.getElementById('statStockRevisado').textContent = 
                `${rev.docenas}|${rev.unidades}`;
            document.getElementById('statProductosRevisado').textContent = 
                `${rev.productos} productos`;
        }
        
    } catch (error) {
        console.error('Error al cargar estadísticas:', error);
    }
}

/**
 * Cargar revisados
 */
async function cargarRevisados() {
    try {
        const fechaDesde = document.getElementById('filtroFechaDesde').value;
        const fechaHasta = document.getElementById('filtroFechaHasta').value;
        const idTurno = document.getElementById('filtroTurno').value;
        
        let url = `${baseUrl}/api/revisado.php?action=listar&fecha_desde=${fechaDesde}&fecha_hasta=${fechaHasta}`;
        if (idTurno) url += `&id_turno=${idTurno}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            revisados = data.revisados;
            renderRevisados();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

/**
 * Renderizar tabla de revisados
 */
function renderRevisados() {
    const tbody = document.getElementById('bodyRevisados');
    
    if (revisados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">No hay registros</td></tr>';
        return;
    }
    
    tbody.innerHTML = revisados.map(rev => `
        <tr>
            <td><strong>${rev.codigo_lote_revisado}</strong></td>
            <td>${formatearFecha(rev.fecha_revisado)}</td>
            <td>${rev.nombre_turno}</td>
            <td style="text-align: center; font-weight: 600;">${rev.docenas}|${rev.unidades}</td>
            <td style="text-align: center;">${formatearUnidades(rev.primera)}</td>
            <td style="text-align: center;">${formatearUnidades(rev.segunda)}</td>
            <td style="text-align: center;">${formatearUnidades(rev.observada)}</td>
            <td style="text-align: center;">${formatearUnidades(rev.desperdicio)}</td>
            <td>
                <button class="btn-icon btn-primary" onclick="verDetalle(${rev.id_revisado})" title="Ver detalle">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Abrir modal de revisado
 */
function abrirModalRevisado() {
    document.getElementById('modalRevisado').classList.add('show');
    cargarStockTejido();
}

/**
 * Cerrar modal de revisado
 */
function cerrarModalRevisado() {
    document.getElementById('modalRevisado').classList.remove('show');
    limpiarFormulario();
}

/**
 * Cargar stock de tejido
 */
async function cargarStockTejido() {
    try {
        const idLinea = document.getElementById('modalFiltroLinea').value;
        
        let url = `${baseUrl}/api/revisado.php?action=stock_tejido`;
        if (idLinea) url += `&id_linea=${idLinea}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            stockTejido = data.stock;
            stockTejidoFiltrado = stockTejido;
            renderStockTejido();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

/**
 * Renderizar tabla de stock
 */
function renderStockTejido() {
    const tbody = document.getElementById('bodyStockTejido');
    
    if (stockTejidoFiltrado.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">No hay stock disponible</td></tr>';
        return;
    }
    
    tbody.innerHTML = stockTejidoFiltrado.map((item, index) => `
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
            <td style="text-align: center; font-weight: 600;">${item.docenas}|${item.unidades}</td>
            <td>
                <select class="revisor-select" data-producto="${item.id_producto}" disabled style="font-size: 12px;">
                    <option value="">Seleccione...</option>
                    ${usuarios.map(u => `<option value="${u.id_usuario}">${u.nombre_completo}</option>`).join('')}
                </select>
            </td>
            <td>
                <input type="number" class="input-cantidad docenas-input" data-producto="${item.id_producto}" 
                       min="0" max="${item.docenas}" value="0" disabled> |
                <input type="number" class="input-cantidad unidades-input" data-producto="${item.id_producto}" 
                       min="0" max="11" value="0" disabled>
            </td>
            <td>
                <select class="calidad-select" data-producto="${item.id_producto}" disabled style="font-size: 12px;">
                    <option value="primera">Primera</option>
                    <option value="segunda">Segunda</option>
                    <option value="observada">Observada</option>
                    <option value="desperdicio">Desperdicio</option>
                </select>
            </td>
            <td>
                <input type="text" class="obs-input" data-producto="${item.id_producto}" 
                       placeholder="Obs..." disabled style="font-size: 11px; padding: 4px;">
            </td>
        </tr>
    `).join('');
}

/**
 * Toggle producto seleccionado
 */
function toggleProducto(index) {
    const item = stockTejidoFiltrado[index];
    const isChecked = document.querySelector(`input[data-index="${index}"]`).checked;
    
    // Habilitar/deshabilitar inputs
    const row = document.getElementById(`row_${item.id_producto}`);
    row.querySelectorAll('select, input').forEach(input => {
        if (!input.classList.contains('chk-producto')) {
            input.disabled = !isChecked;
        }
    });
    
    if (isChecked) {
        // Sugerir cantidad completa
        row.querySelector('.docenas-input').value = item.docenas;
        row.querySelector('.unidades-input').value = item.unidades;
    }
}

/**
 * Filtrar tabla de stock
 */
function filtrarTablaStock() {
    const busqueda = document.getElementById('modalBusqueda').value.toLowerCase();
    
    if (!busqueda) {
        stockTejidoFiltrado = stockTejido;
    } else {
        stockTejidoFiltrado = stockTejido.filter(item =>
            item.codigo_producto.toLowerCase().includes(busqueda) ||
            item.descripcion_completa.toLowerCase().includes(busqueda)
        );
    }
    
    renderStockTejido();
}

/**
 * Guardar revisado
 */
async function guardarRevisado() {
    const fecha = document.getElementById('modalFecha').value;
    const idTurno = document.getElementById('modalTurno').value;
    const observaciones = document.getElementById('modalObservaciones').value;
    
    if (!fecha || !idTurno) {
        alert('Por favor complete todos los campos requeridos');
        return;
    }
    
    // Obtener productos seleccionados
    const detalle = [];
    document.querySelectorAll('.chk-producto:checked').forEach(chk => {
        const index = chk.dataset.index;
        const item = stockTejidoFiltrado[index];
        const idProducto = item.id_producto;
        
        const idRevisadora = document.querySelector(`.revisor-select[data-producto="${idProducto}"]`).value;
        const docenas = parseInt(document.querySelector(`.docenas-input[data-producto="${idProducto}"]`).value) || 0;
        const unidades = parseInt(document.querySelector(`.unidades-input[data-producto="${idProducto}"]`).value) || 0;
        const calidad = document.querySelector(`.calidad-select[data-producto="${idProducto}"]`).value;
        const obs = document.querySelector(`.obs-input[data-producto="${idProducto}"]`).value;
        
        if (!idRevisadora) {
            alert(`Debe seleccionar una revisadora para ${item.descripcion_completa}`);
            throw new Error('Revisadora requerida');
        }
        
        if (docenas > 0 || unidades > 0) {
            detalle.push({
                id_producto: idProducto,
                id_revisadora: idRevisadora,
                docenas: docenas,
                unidades: unidades,
                calidad: calidad,
                observaciones: obs
            });
        }
    });
    
    if (detalle.length === 0) {
        alert('Debe seleccionar al menos un producto con cantidad');
        return;
    }
    
    try {
        const response = await fetch(`${baseUrl}/api/revisado.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                fecha_revisado: fecha,
                id_turno: idTurno,
                observaciones: observaciones,
                detalle: detalle
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Revisado registrado exitosamente');
            cerrarModalRevisado();
            cargarRevisados();
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
 * Ver detalle de revisado
 */
async function verDetalle(idRevisado) {
    try {
        const response = await fetch(`${baseUrl}/api/revisado.php?action=detalle&id_revisado=${idRevisado}`);
        const data = await response.json();
        
        if (data.success) {
            const rev = data.revisado;
            const detalle = data.detalle;
            
            let html = `
                <div style="margin-bottom: 20px;">
                    <h4>${rev.codigo_lote_revisado}</h4>
                    <p><strong>Fecha:</strong> ${formatearFecha(rev.fecha_revisado)} - ${rev.nombre_turno}</p>
                    ${rev.observaciones ? `<p><strong>Observaciones:</strong> ${rev.observaciones}</p>` : ''}
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Revisadora</th>
                            <th>Cantidad</th>
                            <th>Calidad</th>
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
                        <td style="text-align: center; font-weight: 600;">${item.docenas_revisadas}|${item.unidades_revisadas}</td>
                        <td><span class="badge badge-${item.calidad}">${item.calidad.toUpperCase()}</span></td>
                        <td style="font-size: 11px;">${item.observaciones || '-'}</td>
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
 * Cerrar modal de detalle
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
    document.getElementById('modalObservaciones').value = '';
    document.getElementById('modalFiltroLinea').value = '';
    document.getElementById('modalBusqueda').value = '';
}

/**
 * Formatear fecha
 */
function formatearFecha(fecha) {
    if (!fecha) return '-';
    const d = new Date(fecha + 'T00:00:00');
    return d.toLocaleDateString('es-BO');
}

/**
 * Formatear unidades
 */
function formatearUnidades(total) {
    if (!total || total == 0) return '-';
    const doc = Math.floor(total / 12);
    const uni = total % 12;
    return `${doc}|${uni}`;
}
</script>

<?php require_once '../../includes/footer.php'; ?>