<?php
require_once '../../config/database.php';

$pageTitle = 'Productos Tejidos';
$currentPage = 'productos';

require_once '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-box"></i> Catálogo de Productos Tejidos</h3>
        <button class="btn btn-primary" onclick="openModal()">
            <i class="fas fa-plus"></i> Nuevo Producto
        </button>
    </div>
    
    <div class="filters-section">
        <div class="form-group">
            <input type="text" id="searchProducto" class="form-control" placeholder="Buscar por código o descripción...">
        </div>
        <div class="form-group">
            <select id="filterLinea" class="form-control">
                <option value="">Todas las líneas</option>
            </select>
        </div>
        <div class="form-group">
            <select id="filterTipo" class="form-control">
                <option value="">Todos los tipos</option>
            </select>
        </div>
        <div class="form-group">
            <select id="filterDiseno" class="form-control">
                <option value="">Todos los diseños</option>
            </select>
        </div>
    </div>
    
    <div class="stats-row">
        <div class="stat-item">
            <span class="stat-label">Total Productos:</span>
            <span class="stat-value" id="totalProductos">0</span>
        </div>
        <div class="stat-item">
            <span class="stat-label">Filtrados:</span>
            <span class="stat-value" id="productosFiltrados">0</span>
        </div>
    </div>
    
    <div class="table-container">
        <table id="tablaProductos">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Línea</th>
                    <th>Tipo</th>
                    <th>Diseño</th>
                    <th>Talla</th>
                    <th>Descripción</th>
                    <th>Peso/Doc (g)</th>
                    <th>Tiempo (min)</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="productosBody">
                <tr>
                    <td colspan="9" class="text-center">Cargando...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal para Agregar/Editar Producto -->
<div id="productoModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3 id="modalTitle">Nuevo Producto</h3>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <form id="productoForm">
            <input type="hidden" id="id_producto">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="codigo_producto">Código del Producto *</label>
                    <input type="text" id="codigo_producto" class="form-control" required placeholder="LUJO-PH-PR-S">
                </div>
                
                <div class="form-group">
                    <label for="talla">Talla *</label>
                    <input type="text" id="talla" class="form-control" required placeholder="S, M, L, XL, TU, etc.">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="id_linea">Línea de Producto *</label>
                    <select id="id_linea" class="form-control" required>
                        <option value="">Seleccione...</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="id_tipo_producto">Tipo de Producto *</label>
                    <select id="id_tipo_producto" class="form-control" required>
                        <option value="">Seleccione...</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="id_diseno">Diseño *</label>
                    <select id="id_diseno" class="form-control" required>
                        <option value="">Seleccione...</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="descripcion_completa">Descripción Completa</label>
                <input type="text" id="descripcion_completa" class="form-control" placeholder="Pantyhose Lujo Puntera Reforzada Talla S">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="peso_promedio_docena">Peso Promedio por Docena (gramos)</label>
                    <input type="number" step="0.01" id="peso_promedio_docena" class="form-control" placeholder="850">
                </div>
                
                <div class="form-group">
                    <label for="tiempo_estimado_docena">Tiempo Estimado por Docena (minutos)</label>
                    <input type="number" id="tiempo_estimado_docena" class="form-control" placeholder="45">
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<style>
.filters-section {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.stats-row {
    display: flex;
    gap: 30px;
    padding: 15px;
    background: #f7fafc;
    border-radius: 8px;
    margin-bottom: 20px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.stat-label {
    font-weight: 600;
    color: #4a5568;
}

.stat-value {
    font-size: 20px;
    font-weight: 700;
    color: #667eea;
}

.modal-large {
    max-width: 800px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

/* ===== ESTILOS DEL MODAL ===== */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    overflow: auto;
    animation: fadeIn 0.3s;
}

.modal.show {
    display: flex !important;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: #fff;
    margin: auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    animation: slideDown 0.3s;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
}

.modal-header h3 {
    margin: 0;
    color: #2d3748;
    font-size: 20px;
}

.close-btn {
    background: none;
    border: none;
    font-size: 28px;
    font-weight: bold;
    color: #a0aec0;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.3s;
}

.close-btn:hover {
    color: #4a5568;
}

.modal form {
    padding: 20px;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
    margin-top: 20px;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideDown {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@media (max-width: 768px) {
    .filters-section {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 95%;
        margin: 10px;
    }
}
</style>

<script>
let productos = [];
let catalogos = {
    lineas: [],
    tipos: [],
    disenos: []
};

// Obtener la URL base del sitio
const baseUrl = window.location.origin + '/mes_hermen';

document.addEventListener('DOMContentLoaded', function() {
    console.log('Iniciando carga de datos...');
    loadCatalogos();
    loadProductos();
    
    document.getElementById('searchProducto').addEventListener('input', filterProductos);
    document.getElementById('filterLinea').addEventListener('change', filterProductos);
    document.getElementById('filterTipo').addEventListener('change', filterProductos);
    document.getElementById('filterDiseno').addEventListener('change', filterProductos);
    document.getElementById('productoForm').addEventListener('submit', saveProducto);
});

async function loadCatalogos() {
    try {
        console.log('Cargando catálogos...');
        const response = await fetch(baseUrl + '/api/catalogos.php');
        const data = await response.json();
        
        console.log('Catálogos recibidos:', data);
        
        if (data.success) {
            catalogos = data;
            
            // Llenar select de líneas
            const selectLinea = document.getElementById('id_linea');
            const filterLinea = document.getElementById('filterLinea');
            data.lineas.forEach(linea => {
                selectLinea.innerHTML += `<option value="${linea.id_linea}">${linea.nombre_linea}</option>`;
                filterLinea.innerHTML += `<option value="${linea.id_linea}">${linea.nombre_linea}</option>`;
            });
            
            // Llenar select de tipos
            const selectTipo = document.getElementById('id_tipo_producto');
            const filterTipo = document.getElementById('filterTipo');
            data.tipos.forEach(tipo => {
                selectTipo.innerHTML += `<option value="${tipo.id_tipo_producto}">${tipo.nombre_tipo}</option>`;
                filterTipo.innerHTML += `<option value="${tipo.id_tipo_producto}">${tipo.nombre_tipo}</option>`;
            });
            
            // Llenar select de diseños
            const selectDiseno = document.getElementById('id_diseno');
            const filterDiseno = document.getElementById('filterDiseno');
            data.disenos.forEach(diseno => {
                selectDiseno.innerHTML += `<option value="${diseno.id_diseno}">${diseno.nombre_diseno}</option>`;
                filterDiseno.innerHTML += `<option value="${diseno.id_diseno}">${diseno.nombre_diseno}</option>`;
            });
            
            console.log('Catálogos cargados correctamente');
        }
    } catch (error) {
        console.error('Error al cargar catálogos:', error);
        showNotification('Error al cargar catálogos', 'danger');
    }
}

async function loadProductos() {
    try {
        console.log('Cargando productos...');
        const response = await fetch(baseUrl + '/api/productos.php');
        const data = await response.json();
        
        console.log('Productos recibidos:', data);
        
        if (data.success) {
            productos = data.productos;
            renderProductos(productos);
            updateStats();
            console.log('Productos cargados:', productos.length);
        } else {
            console.error('Error en respuesta:', data.message);
            showNotification(data.message || 'Error al cargar productos', 'danger');
        }
    } catch (error) {
        console.error('Error al cargar productos:', error);
        showNotification('Error al cargar los productos', 'danger');
    }
}

function renderProductos(productosToRender) {
    const tbody = document.getElementById('productosBody');
    
    if (productosToRender.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">No se encontraron productos</td></tr>';
        return;
    }
    
    tbody.innerHTML = productosToRender.map(p => `
        <tr>
            <td><strong>${p.codigo_producto}</strong></td>
            <td><span class="badge badge-info">${p.nombre_linea}</span></td>
            <td>${p.nombre_tipo}</td>
            <td>${p.nombre_diseno}</td>
            <td><strong>${p.talla}</strong></td>
            <td>${p.descripcion_completa || '-'}</td>
            <td>${p.peso_promedio_docena || '-'}</td>
            <td>${p.tiempo_estimado_docena || '-'}</td>
            <td>
                <button class="btn-icon" onclick="editProducto(${p.id_producto})" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon" onclick="deleteProducto(${p.id_producto}, '${p.codigo_producto}')" title="Eliminar">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
    
    document.getElementById('productosFiltrados').textContent = productosToRender.length;
}

function updateStats() {
    document.getElementById('totalProductos').textContent = productos.length;
    document.getElementById('productosFiltrados').textContent = productos.length;
}

function filterProductos() {
    const searchText = document.getElementById('searchProducto').value.toLowerCase();
    const lineaFilter = document.getElementById('filterLinea').value;
    const tipoFilter = document.getElementById('filterTipo').value;
    const disenoFilter = document.getElementById('filterDiseno').value;
    
    const filtered = productos.filter(p => {
        const matchSearch = p.codigo_producto.toLowerCase().includes(searchText) ||
                          (p.descripcion_completa && p.descripcion_completa.toLowerCase().includes(searchText));
        
        const matchLinea = !lineaFilter || p.id_linea == lineaFilter;
        const matchTipo = !tipoFilter || p.id_tipo_producto == tipoFilter;
        const matchDiseno = !disenoFilter || p.id_diseno == disenoFilter;
        
        return matchSearch && matchLinea && matchTipo && matchDiseno;
    });
    
    renderProductos(filtered);
}

function openModal(id = null) {
    const modal = document.getElementById('productoModal');
    const form = document.getElementById('productoForm');
    
    form.reset();
    document.getElementById('id_producto').value = '';
    document.getElementById('modalTitle').textContent = 'Nuevo Producto';
    
    if (id) {
        const producto = productos.find(p => p.id_producto == id);
        if (producto) {
            document.getElementById('id_producto').value = producto.id_producto;
            document.getElementById('codigo_producto').value = producto.codigo_producto;
            document.getElementById('id_linea').value = producto.id_linea;
            document.getElementById('id_tipo_producto').value = producto.id_tipo_producto;
            document.getElementById('id_diseno').value = producto.id_diseno;
            document.getElementById('talla').value = producto.talla;
            document.getElementById('descripcion_completa').value = producto.descripcion_completa || '';
            document.getElementById('peso_promedio_docena').value = producto.peso_promedio_docena || '';
            document.getElementById('tiempo_estimado_docena').value = producto.tiempo_estimado_docena || '';
            document.getElementById('modalTitle').textContent = 'Editar Producto';
        }
    }
    
    modal.classList.add('show');
}

function closeModal() {
    document.getElementById('productoModal').classList.remove('show');
}

function editProducto(id) {
    openModal(id);
}

async function saveProducto(e) {
    e.preventDefault();
    
    const formData = {
        id_producto: document.getElementById('id_producto').value,
        codigo_producto: document.getElementById('codigo_producto').value,
        id_linea: document.getElementById('id_linea').value,
        id_tipo_producto: document.getElementById('id_tipo_producto').value,
        id_diseno: document.getElementById('id_diseno').value,
        talla: document.getElementById('talla').value,
        descripcion_completa: document.getElementById('descripcion_completa').value,
        peso_promedio_docena: document.getElementById('peso_promedio_docena').value,
        tiempo_estimado_docena: document.getElementById('tiempo_estimado_docena').value
    };
    
    try {
        const response = await fetch(baseUrl + '/api/productos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            closeModal();
            loadProductos();
        } else {
            showNotification(data.message, 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al guardar el producto', 'danger');
    }
}

async function deleteProducto(id, codigo) {
    if (confirmAction(`¿Está seguro de eliminar el producto ${codigo}?`)) {
        try {
            const response = await fetch(baseUrl + '/api/productos.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id_producto: id })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showNotification(data.message, 'success');
                loadProductos();
            } else {
                showNotification(data.message, 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Error al eliminar el producto', 'danger');
        }
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
