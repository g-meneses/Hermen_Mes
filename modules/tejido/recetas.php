<?php
require_once '../../config/database.php';
$pageTitle = 'Recetas de Productos (BOM)';
$currentPage = 'recetas';
require_once '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-flask"></i> Recetas de Productos (BOM - Bill of Materials)</h3>
        <div class="card-header-actions">
            <input type="text" id="buscarProducto" placeholder="Buscar producto..." class="search-input">
            <select id="filtroLinea" class="filter-select">
                <option value="">Todas las líneas</option>
            </select>
            <select id="filtroReceta" class="filter-select">
                <option value="">Todos los productos</option>
                <option value="con">Solo con receta</option>
                <option value="sin">Solo sin receta</option>
            </select>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="stats-container" style="margin-bottom: 20px;">
        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="stat-value" id="totalProductos">0</div>
            <div class="stat-label">Total Productos</div>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="stat-value" id="productosConReceta">0</div>
            <div class="stat-label">Con Receta</div>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
            <div class="stat-value" id="productosSinReceta">0</div>
            <div class="stat-label">Sin Receta</div>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
            <div class="stat-value" id="porcentajeCobertura">0%</div>
            <div class="stat-label">Cobertura</div>
        </div>
    </div>

    <div class="table-container">
        <table id="tablaProductos">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Línea</th>
                    <th>Tipo</th>
                    <th>Talla</th>
                    <th>Insumos</th>
                    <th>Costo Receta</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="bodyProductos">
                <tr>
                    <td colspan="9" class="text-center">Cargando productos...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal para gestionar receta -->
<div id="modalReceta" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3><i class="fas fa-flask"></i> Gestionar Receta del Producto</h3>
            <button class="close-modal" onclick="cerrarModal()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Información del producto -->
            <div class="info-producto" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h4 id="nombreProducto" style="margin: 0 0 5px 0; color: #2c3e50;"></h4>
                <p id="detalleProducto" style="margin: 0; color: #7f8c8d; font-size: 14px;"></p>
            </div>

            <!-- Botón para agregar insumo -->
            <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                <h4 style="margin: 0;">Insumos de la Receta</h4>
                <button onclick="abrirModalInsumo()" class="btn-primary">
                    <i class="fas fa-plus"></i> Agregar Insumo
                </button>
            </div>

            <!-- Tabla de insumos de la receta -->
            <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                <table id="tablaReceta">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Insumo</th>
                            <th>Cantidad (g)</th>
                            <th>Costo Unit. (Bs/kg)</th>
                            <th>Costo Total (Bs)</th>
                            <th>Principal</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="bodyReceta">
                        <tr>
                            <td colspan="7" class="text-center">No hay insumos en esta receta</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f8f9fa; font-weight: bold;">
                            <td colspan="4" class="text-right">COSTO TOTAL POR DOCENA:</td>
                            <td id="costoTotalReceta">Bs. 0.00</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="cerrarModal()" class="btn-secondary">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal para agregar/editar insumo -->
<div id="modalInsumo" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="tituloModalInsumo"><i class="fas fa-plus"></i> Agregar Insumo a la Receta</h3>
            <button class="close-modal" onclick="cerrarModalInsumo()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formInsumo">
                <input type="hidden" id="id_producto_insumo" name="id_producto_insumo">
                <input type="hidden" id="id_producto" name="id_producto">
                
                <div class="form-group">
                    <label for="id_insumo">Insumo: <span class="required">*</span></label>
                    <select id="id_insumo" name="id_insumo" required>
                        <option value="">Seleccione un insumo</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="cantidad_por_docena">Cantidad por Docena (gramos): <span class="required">*</span></label>
                    <input type="number" id="cantidad_por_docena" name="cantidad_por_docena" 
                           step="0.001" min="0.001" required placeholder="Ej: 125.5">
                    <small>Especifique la cantidad en gramos que se consume por cada docena producida</small>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="es_principal" name="es_principal" value="1">
                        Marcar como insumo principal
                    </label>
                    <small>El insumo principal es el hilo o material más importante del producto</small>
                </div>

                <div class="form-group">
                    <label for="observaciones">Observaciones:</label>
                    <textarea id="observaciones" name="observaciones" rows="3" 
                              placeholder="Comentarios adicionales sobre este insumo"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="cerrarModalInsumo()" class="btn-secondary">Cancelar</button>
            <button onclick="guardarInsumo()" class="btn-primary">
                <i class="fas fa-save"></i> Guardar Insumo
            </button>
        </div>
    </div>
</div>

<style>
.info-producto {
    border-left: 4px solid #3498db;
}

.badge-receta {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge-con-receta {
    background-color: #d4edda;
    color: #155724;
}

.badge-sin-receta {
    background-color: #fff3cd;
    color: #856404;
}

.badge-principal {
    background-color: #ffd700;
    color: #000;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
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

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    padding: 0 20px;
}

#tablaReceta tfoot tr {
    border-top: 2px solid #dee2e6;
}

.text-right {
    text-align: right;
}
</style>

<script>
const baseUrl = window.location.origin + '/mes_hermen';
let productos = [];
let productoActual = null;
let recetaActual = [];
let insumos = [];

document.addEventListener('DOMContentLoaded', function() {
    cargarLineas();
    cargarInsumos();
    cargarProductos();
    
    // Event listeners para filtros
    document.getElementById('buscarProducto').addEventListener('input', filtrarProductos);
    document.getElementById('filtroLinea').addEventListener('change', filtrarProductos);
    document.getElementById('filtroReceta').addEventListener('change', filtrarProductos);
});

async function cargarLineas() {
    try {
        const response = await fetch(baseUrl + '/api/catalogos.php?tipo=lineas');
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('filtroLinea');
            data.lineas.forEach(linea => {
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

async function cargarInsumos() {
    try {
        const response = await fetch(baseUrl + '/api/insumos.php');
        const data = await response.json();
        
        if (data.success) {
            insumos = data.insumos;
        }
    } catch (error) {
        console.error('Error al cargar insumos:', error);
    }
}

async function cargarProductos() {
    try {
        const response = await fetch(baseUrl + '/api/recetas.php');
        const data = await response.json();
        
        if (data.success) {
            productos = data.productos;
            actualizarEstadisticas();
            renderProductos(productos);
        }
    } catch (error) {
        console.error('Error al cargar productos:', error);
        showNotification('Error al cargar productos', 'error');
    }
}

function actualizarEstadisticas() {
    const total = productos.length;
    const conReceta = productos.filter(p => p.num_insumos > 0).length;
    const sinReceta = total - conReceta;
    const porcentaje = total > 0 ? Math.round((conReceta / total) * 100) : 0;
    
    document.getElementById('totalProductos').textContent = total;
    document.getElementById('productosConReceta').textContent = conReceta;
    document.getElementById('productosSinReceta').textContent = sinReceta;
    document.getElementById('porcentajeCobertura').textContent = porcentaje + '%';
}

function renderProductos(lista) {
    const tbody = document.getElementById('bodyProductos');
    
    if (lista.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">No se encontraron productos</td></tr>';
        return;
    }
    
    tbody.innerHTML = lista.map(producto => {
        const tieneReceta = producto.num_insumos > 0;
        const badgeReceta = tieneReceta 
            ? '<span class="badge-receta badge-con-receta">Con receta</span>' 
            : '<span class="badge-receta badge-sin-receta">Sin receta</span>';
        
        const costoReceta = tieneReceta && producto.costo_receta 
            ? 'Bs. ' + parseFloat(producto.costo_receta).toFixed(2)
            : '-';
        
        const numInsumos = tieneReceta ? producto.num_insumos + ' insumos' : '-';
        
        return `
            <tr>
                <td><strong>${producto.codigo_producto}</strong></td>
                <td>${producto.descripcion_completa}</td>
                <td><span class="badge-linea badge-${producto.codigo_linea}">${producto.nombre_linea}</span></td>
                <td>${producto.nombre_tipo}</td>
                <td>${producto.talla}</td>
                <td>${numInsumos}</td>
                <td>${costoReceta}</td>
                <td>${badgeReceta}</td>
                <td>
                    <button onclick="abrirReceta(${producto.id_producto})" class="btn-icon btn-primary" 
                            title="Gestionar receta">
                        <i class="fas fa-flask"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function filtrarProductos() {
    const busqueda = document.getElementById('buscarProducto').value.toLowerCase();
    const lineaId = document.getElementById('filtroLinea').value;
    const filtroReceta = document.getElementById('filtroReceta').value;
    
    let productosFiltrados = [...productos]; // Crear copia del array
    
    console.log('Filtrando productos:', {
        total: productos.length,
        lineaId: lineaId,
        filtroReceta: filtroReceta,
        busqueda: busqueda
    });
    
    // Filtrar por búsqueda
    if (busqueda) {
        productosFiltrados = productosFiltrados.filter(p => 
            p.codigo_producto.toLowerCase().includes(busqueda) ||
            p.descripcion_completa.toLowerCase().includes(busqueda)
        );
    }
    
    // Filtrar por línea
    if (lineaId) {
        const lineaIdNum = parseInt(lineaId);
        productosFiltrados = productosFiltrados.filter(p => {
            const productoLineaId = parseInt(p.id_linea);
            console.log('Comparando:', productoLineaId, '==', lineaIdNum, '?', productoLineaId === lineaIdNum);
            return productoLineaId === lineaIdNum;
        });
    }
    
    // Filtrar por estado de receta
    if (filtroReceta === 'con') {
        productosFiltrados = productosFiltrados.filter(p => parseInt(p.num_insumos) > 0);
    } else if (filtroReceta === 'sin') {
        productosFiltrados = productosFiltrados.filter(p => parseInt(p.num_insumos) == 0);
    }
    
    console.log('Productos filtrados:', productosFiltrados.length);
    renderProductos(productosFiltrados);
}

async function abrirReceta(idProducto) {
    try {
        // Buscar el producto
        productoActual = productos.find(p => p.id_producto == idProducto);
        
        if (!productoActual) {
            showNotification('Producto no encontrado', 'error');
            return;
        }
        
        // Actualizar información del producto
        document.getElementById('nombreProducto').textContent = productoActual.codigo_producto;
        document.getElementById('detalleProducto').textContent = productoActual.descripcion_completa;
        document.getElementById('id_producto').value = idProducto;
        
        // Cargar receta del producto
        await cargarReceta(idProducto);
        
        // Mostrar modal
        document.getElementById('modalReceta').classList.add('show');
    } catch (error) {
        console.error('Error al abrir receta:', error);
        showNotification('Error al cargar la receta', 'error');
    }
}

async function cargarReceta(idProducto) {
    try {
        const response = await fetch(baseUrl + '/api/recetas.php?id_producto=' + idProducto);
        const data = await response.json();
        
        if (data.success) {
            recetaActual = data.receta;
            renderReceta();
            
            // Actualizar costo total
            const costoTotal = data.costo_total_receta || 0;
            document.getElementById('costoTotalReceta').textContent = 'Bs. ' + costoTotal.toFixed(2);
        }
    } catch (error) {
        console.error('Error al cargar receta:', error);
        showNotification('Error al cargar receta del producto', 'error');
    }
}

function renderReceta() {
    const tbody = document.getElementById('bodyReceta');
    
    if (recetaActual.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No hay insumos en esta receta</td></tr>';
        return;
    }
    
    tbody.innerHTML = recetaActual.map(item => {
        const principal = item.es_principal == 1 
            ? '<span class="badge-principal">★ PRINCIPAL</span>' 
            : '';
        
        return `
            <tr>
                <td>${item.codigo_insumo}</td>
                <td>${item.nombre_insumo}</td>
                <td>${parseFloat(item.cantidad_por_docena).toFixed(3)} g</td>
                <td>Bs. ${parseFloat(item.costo_unitario).toFixed(2)}</td>
                <td><strong>Bs. ${parseFloat(item.costo_total).toFixed(4)}</strong></td>
                <td>${principal}</td>
                <td>
                    <button onclick="eliminarInsumo(${item.id_producto_insumo})" 
                            class="btn-icon btn-danger" title="Eliminar insumo">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function abrirModalInsumo() {
    // Limpiar formulario
    document.getElementById('formInsumo').reset();
    document.getElementById('id_producto_insumo').value = '';
    document.getElementById('tituloModalInsumo').innerHTML = '<i class="fas fa-plus"></i> Agregar Insumo a la Receta';
    
    // Poblar select de insumos
    const select = document.getElementById('id_insumo');
    select.innerHTML = '<option value="">Seleccione un insumo</option>';
    insumos.forEach(insumo => {
        const option = document.createElement('option');
        option.value = insumo.id_insumo;
        option.textContent = `${insumo.codigo_insumo} - ${insumo.nombre_insumo}`;
        select.appendChild(option);
    });
    
    // Mostrar modal
    document.getElementById('modalInsumo').classList.add('show');
}

async function guardarInsumo() {
    const formData = {
        id_producto_insumo: document.getElementById('id_producto_insumo').value,
        id_producto: document.getElementById('id_producto').value,
        id_insumo: document.getElementById('id_insumo').value,
        cantidad_por_docena: document.getElementById('cantidad_por_docena').value,
        es_principal: document.getElementById('es_principal').checked ? 1 : 0,
        observaciones: document.getElementById('observaciones').value
    };
    
    if (!formData.id_insumo || !formData.cantidad_por_docena) {
        showNotification('Complete todos los campos requeridos', 'warning');
        return;
    }
    
    try {
        const response = await fetch(baseUrl + '/api/recetas.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            cerrarModalInsumo();
            await cargarReceta(formData.id_producto);
            await cargarProductos(); // Actualizar lista principal
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Error al guardar insumo:', error);
        showNotification('Error al guardar el insumo', 'error');
    }
}

async function eliminarInsumo(idProductoInsumo) {
    if (!confirm('¿Está seguro de eliminar este insumo de la receta?')) {
        return;
    }
    
    try {
        const response = await fetch(baseUrl + '/api/recetas.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id_producto_insumo: idProductoInsumo })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            await cargarReceta(document.getElementById('id_producto').value);
            await cargarProductos(); // Actualizar lista principal
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Error al eliminar insumo:', error);
        showNotification('Error al eliminar el insumo', 'error');
    }
}

function cerrarModal() {
    document.getElementById('modalReceta').classList.remove('show');
    productoActual = null;
    recetaActual = [];
}

function cerrarModalInsumo() {
    document.getElementById('modalInsumo').classList.remove('show');
}

// Cerrar modales al hacer clic fuera
window.onclick = function(event) {
    const modalReceta = document.getElementById('modalReceta');
    const modalInsumo = document.getElementById('modalInsumo');
    
    if (event.target == modalReceta) {
        cerrarModal();
    }
    if (event.target == modalInsumo) {
        cerrarModalInsumo();
    }
}

function showNotification(message, type) {
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    
    // Agregar al body
    document.body.appendChild(notification);
    
    // Mostrar con animación
    setTimeout(() => notification.classList.add('show'), 10);
    
    // Remover después de 3 segundos
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
</script>

<?php require_once '../../includes/footer.php'; ?>