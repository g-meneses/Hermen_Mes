<?php
/**
 * Vista: Inventario Intermedio
 * Sistema MES Hermen Ltda.
 * Fecha: 16 de Noviembre de 2025
 * Versión: 1.0
 */

require_once '../../config/database.php';



if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Inventario Intermedio';
$currentPage = 'inventario_intermedio';
require_once '../../includes/header.php';
?>

<!-- Contenedor Principal -->
<div class="container-fluid">
    
    <!-- Header con Título -->
    <div class="page-header">
        <h1>📦 Inventario Intermedio</h1>
        <p class="text-muted">Control de stock en procesos de producción</p>
    </div>
    
    <!-- Cards de Estadísticas -->
    <div class="stats-grid">
        <!-- Card 1: Inventario Tejido -->
        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="stat-icon">🧵</div>
            <div class="stat-content">
                <div class="stat-value" id="statTejido">0|0</div>
                <div class="stat-label">Inventario Tejido</div>
                <div class="stat-sublabel" id="statTejidoProductos">0 productos</div>
            </div>
        </div>
        
        <!-- Card 2: Inventario Vaporizado -->
        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="stat-icon">💨</div>
            <div class="stat-content">
                <div class="stat-value" id="statVaporizado">0|0</div>
                <div class="stat-label">Inventario Vaporizado</div>
                <div class="stat-sublabel" id="statVaporizadoProductos">0 productos</div>
            </div>
        </div>
        
        <!-- Card 3: Inventario Pre-Teñido -->
        <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <div class="stat-icon">✂️</div>
            <div class="stat-content">
                <div class="stat-value" id="statPretenido">0|0</div>
                <div class="stat-label">Inventario Pre-Teñido</div>
                <div class="stat-sublabel" id="statPretenidoProductos">0 productos</div>
            </div>
        </div>
        
        <!-- Card 4: Inventario Teñido -->
        <div class="stat-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
            <div class="stat-icon">🎨</div>
            <div class="stat-content">
                <div class="stat-value" id="statTenido">0|0</div>
                <div class="stat-label">Inventario Teñido</div>
                <div class="stat-sublabel" id="statTenidoProductos">0 productos</div>
            </div>
        </div>
    </div>
    
    <!-- Tabs de Navegación -->
    <div class="tabs-container">
        <div class="tabs-nav">
            <button class="tab-btn active" data-tab="tejido">
                <span class="tab-icon">🧵</span> Tejido
            </button>
            <button class="tab-btn" data-tab="vaporizado">
                <span class="tab-icon">💨</span> Vaporizado
            </button>
            <button class="tab-btn" data-tab="preteñido">
                <span class="tab-icon">✂️</span> Pre-Teñido
            </button>
            <button class="tab-btn" data-tab="teñido">
                <span class="tab-icon">🎨</span> Teñido
            </button>
            <button class="tab-btn" data-tab="movimientos">
                <span class="tab-icon">📜</span> Historial
            </button>
        </div>
    </div>
    
    <!-- Panel de Inventario -->
    <div class="card" id="panelInventario">
        <div class="card-header">
            <div class="header-left">
                <h3 id="tituloInventario">Inventario en Tejido</h3>
            </div>
            <div class="header-right">
                <button class="btn btn-primary" onclick="abrirModalMovimiento()">
                    <i class="fas fa-plus"></i> Registrar Movimiento
                </button>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="card-filters">
            <div class="filter-group">
                <label>Línea:</label>
                <select id="filtroLinea" onchange="filtrarInventario()">
                    <option value="">Todas las líneas</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Buscar:</label>
                <input type="text" id="filtroBusqueda" placeholder="Código o descripción..." onkeyup="filtrarInventario()">
            </div>
            
            <div class="filter-group">
                <button class="btn btn-secondary" onclick="limpiarFiltros()">
                    <i class="fas fa-eraser"></i> Limpiar
                </button>
            </div>
        </div>
        
        <!-- Tabla de Inventario -->
        <div class="table-container">
            <table class="table" id="tablaInventario">
                <thead>
                    <tr>
                        <th>Línea</th>
                        <th>Producto</th>
                        <th>Talla</th>
                        <th>Docenas</th>
                        <th>Unidades</th>
                        <th>Total Unid.</th>
                        <th>Última Act.</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="bodyInventario">
                    <tr>
                        <td colspan="8" class="text-center">Cargando...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Panel de Historial de Movimientos -->
    <div class="card" id="panelMovimientos" style="display: none;">
        <div class="card-header">
            <div class="header-left">
                <h3>📜 Historial de Movimientos</h3>
            </div>
        </div>
        
        <!-- Filtros de Movimientos -->
        <div class="card-filters">
            <div class="filter-group">
                <label>Tipo Mov.:</label>
                <select id="filtroTipoMov" onchange="filtrarMovimientos()">
                    <option value="">Todos</option>
                    <option value="entrada">Entradas</option>
                    <option value="salida">Salidas</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Inventario:</label>
                <select id="filtroTipoInv" onchange="filtrarMovimientos()">
                    <option value="">Todos</option>
                    <option value="tejido">Tejido</option>
                    <option value="vaporizado">Vaporizado</option>
                    <option value="preteñido">Pre-Teñido</option>
                    <option value="teñido">Teñido</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Fecha Desde:</label>
                <input type="date" id="filtroFechaDesde" onchange="filtrarMovimientos()">
            </div>
            
            <div class="filter-group">
                <label>Fecha Hasta:</label>
                <input type="date" id="filtroFechaHasta" onchange="filtrarMovimientos()">
            </div>
        </div>
        
        <!-- Tabla de Movimientos -->
        <div class="table-container">
            <table class="table" id="tablaMovimientos">
                <thead>
                    <tr>
                        <th>Fecha/Hora</th>
                        <th>Tipo</th>
                        <th>Inventario</th>
                        <th>Producto</th>
                        <th>Doc|Uni</th>
                        <th>Origen</th>
                        <th>Destino</th>
                        <th>Responsable</th>
                    </tr>
                </thead>
                <tbody id="bodyMovimientos">
                    <tr>
                        <td colspan="8" class="text-center">Cargando...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
</div>

<!-- Modal: Registrar Movimiento -->
<div id="modalMovimiento" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>📦 Registrar Movimiento de Inventario</h3>
            <button class="modal-close" onclick="cerrarModalMovimiento()">&times;</button>
        </div>
        
        <div class="modal-body">
            <!-- Tipo de Movimiento -->
            <div class="form-row">
                <div class="form-group" style="width: 100%;">
                    <label>Tipo de Movimiento: *</label>
                    <div style="display: flex; gap: 20px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="radio" name="tipoMovimiento" value="entrada" checked onchange="actualizarCamposMovimiento()">
                            <span style="margin-left: 8px;">➕ Entrada</span>
                        </label>
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="radio" name="tipoMovimiento" value="salida" onchange="actualizarCamposMovimiento()">
                            <span style="margin-left: 8px;">➖ Salida</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Inventario y Producto -->
            <div class="form-row">
                <div class="form-group" style="width: 50%;">
                    <label>Inventario: *</label>
                    <select id="modalTipoInventario" required>
                        <option value="">Seleccione...</option>
                        <option value="tejido">🧵 Tejido</option>
                        <option value="vaporizado">💨 Vaporizado</option>
                        <option value="preteñido">✂️ Pre-Teñido</option>
                        <option value="teñido">🎨 Teñido</option>
                    </select>
                </div>
                
                <div class="form-group" style="width: 50%;">
                    <label>Producto: *</label>
                    <select id="modalProducto" required>
                        <option value="">Seleccione...</option>
                    </select>
                </div>
            </div>
            
            <!-- Cantidades -->
            <div class="form-row">
                <div class="form-group" style="width: 50%;">
                    <label>Docenas: *</label>
                    <input type="number" id="modalDocenas" min="0" value="0" required>
                </div>
                
                <div class="form-group" style="width: 50%;">
                    <label>Unidades (0-11): *</label>
                    <input type="number" id="modalUnidades" min="0" max="11" value="0" required>
                </div>
            </div>
            
            <!-- Origen y Destino -->
            <div class="form-row">
                <div class="form-group" style="width: 50%;">
                    <label id="labelOrigen">Origen:</label>
                    <input type="text" id="modalOrigen" placeholder="Ej: Producción">
                </div>
                
                <div class="form-group" style="width: 50%;">
                    <label id="labelDestino">Destino:</label>
                    <input type="text" id="modalDestino" placeholder="Ej: Vaporizado">
                </div>
            </div>
            
            <!-- Observaciones -->
            <div class="form-row">
                <div class="form-group" style="width: 100%;">
                    <label>Observaciones:</label>
                    <textarea id="modalObservaciones" rows="2" placeholder="Observaciones adicionales..."></textarea>
                </div>
            </div>
            
            <!-- Info de Stock (solo para salidas) -->
            <div id="infoStock" style="display: none; padding: 10px; background: #fff3cd; border-radius: 4px; margin-top: 10px;">
                <strong>Stock Disponible:</strong> <span id="stockDisponible">-</span>
            </div>
        </div>
        
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModalMovimiento()">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarMovimiento()">
                <i class="fas fa-save"></i> Guardar Movimiento
            </button>
        </div>
    </div>
</div>

<style>
/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

/* Tabs */
.tabs-container {
    margin-bottom: 20px;
}

.tabs-nav {
    display: flex;
    gap: 5px;
    border-bottom: 2px solid #e0e0e0;
}

.tab-btn {
    padding: 12px 24px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #666;
    border-bottom: 3px solid transparent;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.tab-btn:hover {
    color: #333;
    background: #f5f5f5;
}

.tab-btn.active {
    color: #3498db;
    border-bottom-color: #3498db;
    background: #f0f8ff;
}

.tab-icon {
    font-size: 16px;
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

.badge-entrada {
    background: #d4edda;
    color: #155724;
}

.badge-salida {
    background: #f8d7da;
    color: #721c24;
}

.badge-tejido {
    background: #e7e3fc;
    color: #5f3dc4;
}

.badge-vaporizado {
    background: #ffe3e3;
    color: #c92a2a;
}

.badge-preteñido {
    background: #d3f9f9;
    color: #0c8599;
}

.badge-teñido {
    background: #d8f5d8;
    color: #2f9e44;
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
.form-group select,
.form-group textarea {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .tabs-nav {
        overflow-x: auto;
    }
    
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
let inventarios = [];
let movimientos = [];
let lineas = [];
let productos = [];
let tipoInventarioActual = 'tejido';

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    cargarLineas();
    cargarProductos();
    cargarEstadisticas();
    cargarInventario('tejido');
    
    // Configurar tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.dataset.tab;
            cambiarTab(tab);
        });
    });
});

/**
 * Cambiar entre tabs
 */
function cambiarTab(tab) {
    // Actualizar botones
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
    
    // Mostrar panel correspondiente
    if (tab === 'movimientos') {
        document.getElementById('panelInventario').style.display = 'none';
        document.getElementById('panelMovimientos').style.display = 'block';
        cargarMovimientos();
    } else {
        document.getElementById('panelInventario').style.display = 'block';
        document.getElementById('panelMovimientos').style.display = 'none';
        tipoInventarioActual = tab;
        actualizarTituloInventario(tab);
        cargarInventario(tab);
    }
}

/**
 * Actualizar título del inventario
 */
function actualizarTituloInventario(tipo) {
    const titulos = {
        'tejido': '🧵 Inventario en Tejido',
        'vaporizado': '💨 Inventario Vaporizado',
        'preteñido': '✂️ Inventario Pre-Teñido',
        'teñido': '🎨 Inventario Teñido'
    };
    document.getElementById('tituloInventario').textContent = titulos[tipo] || 'Inventario';
}

/**
 * Cargar estadísticas generales
 */
async function cargarEstadisticas() {
    try {
        const response = await fetch(`${baseUrl}/api/inventario_intermedio.php?action=estadisticas`);
        const data = await response.json();
        
        if (data.success) {
            const stats = data.estadisticas.por_tipo;
            
            // Actualizar cards
            actualizarCard('tejido', stats.tejido);
            actualizarCard('vaporizado', stats.vaporizado);
            actualizarCard('preteñido', stats.preteñido);
            actualizarCard('teñido', stats.teñido);
        }
    } catch (error) {
        console.error('Error al cargar estadísticas:', error);
    }
}

/**
 * Actualizar un card de estadísticas
 */
function actualizarCard(tipo, data) {
    if (!data) {
        document.getElementById(`stat${capitalizar(tipo)}`).textContent = '0|0';
        document.getElementById(`stat${capitalizar(tipo)}Productos`).textContent = '0 productos';
        return;
    }
    
    document.getElementById(`stat${capitalizar(tipo)}`).textContent = 
        `${data.docenas}|${data.unidades}`;
    document.getElementById(`stat${capitalizar(tipo)}Productos`).textContent = 
        `${data.productos} producto${data.productos !== 1 ? 's' : ''}`;
}

/**
 * Capitalizar primera letra (para IDs)
 */
function capitalizar(str) {
    if (str === 'preteñido') return 'Pretenido';
    if (str === 'teñido') return 'Tenido';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

/**
 * Cargar líneas de productos
 */
async function cargarLineas() {
    try {
        const response = await fetch(`${baseUrl}/api/catalogos.php?tipo=lineas`);
        const data = await response.json();
        
        if (data.success) {
            lineas = data.lineas;
            
            const select = document.getElementById('filtroLinea');
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
 * Cargar productos
 */
async function cargarProductos() {
    try {
        const response = await fetch(`${baseUrl}/api/productos.php`);
        const data = await response.json();
        
        if (data.success) {
            productos = data.productos;
            
            const select = document.getElementById('modalProducto');
            productos.forEach(prod => {
                const option = document.createElement('option');
                option.value = prod.id_producto;
                option.textContent = `${prod.descripcion_completa} - ${prod.talla}`;
                option.dataset.codigo = prod.codigo_producto;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error al cargar productos:', error);
    }
}

/**
 * Cargar inventario por tipo
 */
async function cargarInventario(tipo) {
    try {
        const response = await fetch(`${baseUrl}/api/inventario_intermedio.php?action=por_tipo&tipo=${tipo}`);
        const data = await response.json();
        
        if (data.success) {
            inventarios = data.inventarios;
            renderInventario(inventarios);
        }
    } catch (error) {
        console.error('Error al cargar inventario:', error);
        document.getElementById('bodyInventario').innerHTML = 
            '<tr><td colspan="8" class="text-center text-danger">Error al cargar datos</td></tr>';
    }
}

/**
 * Renderizar tabla de inventario
 */
function renderInventario(items) {
    const tbody = document.getElementById('bodyInventario');
    
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No hay productos en este inventario</td></tr>';
        return;
    }
    
    tbody.innerHTML = items.map(item => `
        <tr>
            <td>
                <span class="badge badge-${item.codigo_linea.toLowerCase()}">${item.codigo_linea}</span>
            </td>
            <td>
                <div style="font-weight: 500;">${item.descripcion_completa}</div>
                <small style="color: #666;">${item.codigo_producto}</small>
            </td>
            <td>${item.talla}</td>
            <td style="text-align: center; font-weight: 600;">${item.docenas}</td>
            <td style="text-align: center; font-weight: 600;">${item.unidades}</td>
            <td style="text-align: center; color: #666;">${item.total_unidades_calculado}</td>
            <td style="font-size: 12px;">${formatearFecha(item.fecha_actualizacion)}</td>
            <td>
                <button class="btn-icon btn-primary" onclick="verDetalleProducto(${item.id_producto})" title="Ver detalle">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Filtrar inventario
 */
function filtrarInventario() {
    const idLinea = document.getElementById('filtroLinea').value;
    const busqueda = document.getElementById('filtroBusqueda').value.toLowerCase();
    
    let filtered = inventarios;
    
    if (idLinea) {
        filtered = filtered.filter(item => item.id_linea == idLinea);
    }
    
    if (busqueda) {
        filtered = filtered.filter(item => 
            item.codigo_producto.toLowerCase().includes(busqueda) ||
            item.descripcion_completa.toLowerCase().includes(busqueda)
        );
    }
    
    renderInventario(filtered);
}

/**
 * Limpiar filtros
 */
function limpiarFiltros() {
    document.getElementById('filtroLinea').value = '';
    document.getElementById('filtroBusqueda').value = '';
    renderInventario(inventarios);
}

/**
 * Cargar movimientos
 */
async function cargarMovimientos() {
    try {
        const params = new URLSearchParams({
            action: 'movimientos',
            limit: 100
        });
        
        const tipoMov = document.getElementById('filtroTipoMov').value;
        const tipoInv = document.getElementById('filtroTipoInv').value;
        const fechaDesde = document.getElementById('filtroFechaDesde').value;
        const fechaHasta = document.getElementById('filtroFechaHasta').value;
        
        if (tipoMov) params.append('tipo_movimiento', tipoMov);
        if (tipoInv) params.append('tipo_inventario', tipoInv);
        if (fechaDesde) params.append('fecha_desde', fechaDesde);
        if (fechaHasta) params.append('fecha_hasta', fechaHasta);
        
        const response = await fetch(`${baseUrl}/api/inventario_intermedio.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            movimientos = data.movimientos;
            renderMovimientos(movimientos);
        }
    } catch (error) {
        console.error('Error al cargar movimientos:', error);
    }
}

/**
 * Renderizar tabla de movimientos
 */
function renderMovimientos(items) {
    const tbody = document.getElementById('bodyMovimientos');
    
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No hay movimientos registrados</td></tr>';
        return;
    }
    
    tbody.innerHTML = items.map(mov => `
        <tr>
            <td style="font-size: 12px;">${formatearFechaHora(mov.fecha_movimiento)}</td>
            <td>
                <span class="badge badge-${mov.tipo_movimiento}">${mov.tipo_movimiento.toUpperCase()}</span>
            </td>
            <td>
                <span class="badge badge-${mov.tipo_inventario}">${mov.tipo_inventario}</span>
            </td>
            <td>
                <div style="font-size: 12px; font-weight: 500;">${mov.descripcion_completa}</div>
                <small style="color: #666;">${mov.nombre_linea}</small>
            </td>
            <td style="text-align: center; font-weight: 600;">${mov.docenas}|${mov.unidades}</td>
            <td style="font-size: 11px;">${mov.origen || '-'}</td>
            <td style="font-size: 11px;">${mov.destino || '-'}</td>
            <td style="font-size: 12px;">${mov.responsable}</td>
        </tr>
    `).join('');
}

/**
 * Filtrar movimientos
 */
function filtrarMovimientos() {
    cargarMovimientos();
}

/**
 * Abrir modal de movimiento
 */
function abrirModalMovimiento() {
    document.getElementById('modalMovimiento').classList.add('show');
    document.getElementById('modalTipoInventario').value = tipoInventarioActual;
    actualizarCamposMovimiento();
}

/**
 * Cerrar modal de movimiento
 */
function cerrarModalMovimiento() {
    document.getElementById('modalMovimiento').classList.remove('show');
    limpiarFormularioMovimiento();
}

/**
 * Actualizar campos según tipo de movimiento
 */
function actualizarCamposMovimiento() {
    const tipoMov = document.querySelector('input[name="tipoMovimiento"]:checked').value;
    
    if (tipoMov === 'entrada') {
        document.getElementById('labelOrigen').textContent = 'Origen:';
        document.getElementById('labelDestino').textContent = 'Destino:';
        document.getElementById('infoStock').style.display = 'none';
    } else {
        document.getElementById('labelOrigen').textContent = 'Origen:';
        document.getElementById('labelDestino').textContent = 'Destino:';
        document.getElementById('infoStock').style.display = 'block';
        verificarStock();
    }
}

/**
 * Verificar stock disponible (para salidas)
 */
async function verificarStock() {
    const idProducto = document.getElementById('modalProducto').value;
    const tipoInv = document.getElementById('modalTipoInventario').value;
    
    if (!idProducto || !tipoInv) return;
    
    try {
        const response = await fetch(`${baseUrl}/api/inventario_intermedio.php?action=stock_producto&id_producto=${idProducto}`);
        const data = await response.json();
        
        if (data.success) {
            const stock = data.stocks.find(s => s.tipo_inventario === tipoInv);
            if (stock) {
                document.getElementById('stockDisponible').textContent = 
                    `${stock.docenas} docenas | ${stock.unidades} unidades`;
            } else {
                document.getElementById('stockDisponible').textContent = '0 docenas | 0 unidades';
            }
        }
    } catch (error) {
        console.error('Error al verificar stock:', error);
    }
}

/**
 * Guardar movimiento
 */
async function guardarMovimiento() {
    const tipoMov = document.querySelector('input[name="tipoMovimiento"]:checked').value;
    const tipoInv = document.getElementById('modalTipoInventario').value;
    const idProducto = document.getElementById('modalProducto').value;
    let docenas = parseInt(document.getElementById('modalDocenas').value) || 0;
    let unidades = parseInt(document.getElementById('modalUnidades').value) || 0;
    const origen = document.getElementById('modalOrigen').value;
    const destino = document.getElementById('modalDestino').value;
    const observaciones = document.getElementById('modalObservaciones').value;
    
    // Validaciones
    if (!tipoInv || !idProducto) {
        alert('Por favor complete todos los campos requeridos');
        return;
    }
    
    // Validar y ajustar unidades
    if (unidades > 11) {
        const docenasExtra = Math.floor(unidades / 12);
        unidades = unidades % 12;
        docenas += docenasExtra;
        document.getElementById('modalDocenas').value = docenas;
        document.getElementById('modalUnidades').value = unidades;
    }
    
    if (docenas === 0 && unidades === 0) {
        alert('Debe ingresar al menos una cantidad');
        return;
    }
    
    try {
        const response = await fetch(`${baseUrl}/api/inventario_intermedio.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'registrar_movimiento',
                tipo_movimiento: tipoMov,
                tipo_inventario: tipoInv,
                id_producto: idProducto,
                docenas: docenas,
                unidades: unidades,
                origen: origen,
                destino: destino,
                observaciones: observaciones
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Movimiento registrado exitosamente');
            cerrarModalMovimiento();
            cargarEstadisticas();
            cargarInventario(tipoInventarioActual);
        } else {
            alert('❌ Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error al guardar movimiento:', error);
        alert('Error al guardar el movimiento');
    }
}

/**
 * Limpiar formulario de movimiento
 */
function limpiarFormularioMovimiento() {
    document.querySelector('input[name="tipoMovimiento"][value="entrada"]').checked = true;
    document.getElementById('modalTipoInventario').value = '';
    document.getElementById('modalProducto').value = '';
    document.getElementById('modalDocenas').value = '0';
    document.getElementById('modalUnidades').value = '0';
    document.getElementById('modalOrigen').value = '';
    document.getElementById('modalDestino').value = '';
    document.getElementById('modalObservaciones').value = '';
}

/**
 * Ver detalle de un producto
 */
async function verDetalleProducto(idProducto) {
    try {
        const response = await fetch(`${baseUrl}/api/inventario_intermedio.php?action=stock_producto&id_producto=${idProducto}`);
        const data = await response.json();
        
        if (data.success) {
            const prod = data.producto;
            const stocks = data.stocks;
            
            let mensaje = `PRODUCTO: ${prod.descripcion_completa}\n\n`;
            mensaje += `Stock en inventarios:\n\n`;
            
            if (stocks.length === 0) {
                mensaje += 'No hay stock en ningún inventario';
            } else {
                stocks.forEach(stock => {
                    mensaje += `${stock.tipo_inventario.toUpperCase()}: ${stock.docenas}|${stock.unidades}\n`;
                });
            }
            
            alert(mensaje);
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

/**
 * Formatear fecha
 */
function formatearFecha(fecha) {
    if (!fecha) return '-';
    const d = new Date(fecha);
    return d.toLocaleDateString('es-BO');
}

/**
 * Formatear fecha y hora
 */
function formatearFechaHora(fecha) {
    if (!fecha) return '-';
    const d = new Date(fecha);
    return d.toLocaleDateString('es-BO') + ' ' + d.toLocaleTimeString('es-BO', {hour: '2-digit', minute: '2-digit'});
}
</script>

<?php require_once '../../includes/footer.php'; ?>