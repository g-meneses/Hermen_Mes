<?php
require_once '../../config/database.php';

$pageTitle = 'Hilos e Insumos';
$currentPage = 'insumos';

require_once '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-spool"></i> Gestión de Hilos e Insumos</h3>
        <button class="btn btn-primary" onclick="openModal()">
            <i class="fas fa-plus"></i> Nuevo Insumo
        </button>
    </div>
    
    <div class="filters-section">
        <div class="form-group">
            <input type="text" id="searchInsumo" class="form-control" placeholder="Buscar por código o nombre...">
        </div>
        <div class="form-group">
            <select id="filterTipo" class="form-control">
                <option value="">Todos los tipos</option>
                <option value="HILO_POLIAMIDA">Hilos de Poliamida</option>
                <option value="LYCRA">Lycra/Spandex</option>
                <option value="AUXILIAR_QUIMICO">Auxiliares Químicos</option>
                <option value="OTRO">Otros</option>
            </select>
        </div>
        <div class="form-group">
            <select id="filterStock" class="form-control">
                <option value="">Todos los stocks</option>
                <option value="BAJO">Stock Bajo</option>
                <option value="MEDIO">Stock Medio</option>
                <option value="OK">Stock OK</option>
            </select>
        </div>
    </div>
    
    <div class="stats-row">
        <div class="stat-item">
            <i class="fas fa-boxes stat-icon"></i>
            <div>
                <span class="stat-label">Total Insumos:</span>
                <span class="stat-value" id="totalInsumos">0</span>
            </div>
        </div>
        <div class="stat-item">
            <i class="fas fa-filter stat-icon"></i>
            <div>
                <span class="stat-label">Filtrados:</span>
                <span class="stat-value" id="insumosFiltrados">0</span>
            </div>
        </div>
        <div class="stat-item alert-stock">
            <i class="fas fa-exclamation-triangle stat-icon"></i>
            <div>
                <span class="stat-label">Stock Bajo:</span>
                <span class="stat-value" id="stockBajo">0</span>
            </div>
        </div>
        <div class="stat-item">
            <i class="fas fa-dollar-sign stat-icon"></i>
            <div>
                <span class="stat-label">Valor Stock:</span>
                <span class="stat-value" id="valorStock">Bs. 0.00</span>
            </div>
        </div>
    </div>
    
    <div class="table-container">
        <table id="tablaInsumos">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Costo (Bs/KG)</th>
                    <th>Stock Actual</th>
                    <th>Stock Mínimo</th>
                    <th>Estado</th>
                    <th>Valor</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="insumosBody">
                <tr>
                    <td colspan="9" class="text-center">Cargando...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal para Agregar/Editar Insumo -->
<div id="insumoModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3 id="modalTitle">Nuevo Insumo</h3>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <form id="insumoForm">
            <input type="hidden" id="id_insumo">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="codigo_insumo">Código del Insumo *</label>
                    <input type="text" id="codigo_insumo" class="form-control" required placeholder="DTY-22-10F-S">
                </div>
                
                <div class="form-group">
                    <label for="tipo_insumo">Tipo de Insumo *</label>
                    <select id="tipo_insumo" class="form-control" required>
                        <option value="">Seleccione...</option>
                        <option value="HILO_POLIAMIDA">Hilo de Poliamida</option>
                        <option value="LYCRA">Lycra/Spandex</option>
                        <option value="AUXILIAR_QUIMICO">Auxiliar Químico</option>
                        <option value="OTRO">Otro</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="nombre_insumo">Nombre del Insumo *</label>
                <input type="text" id="nombre_insumo" class="form-control" required placeholder="DTY 22 DTEX / 10 F, TORSION S">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="unidad_medida">Unidad de Medida</label>
                    <select id="unidad_medida" class="form-control">
                        <option value="kilogramos">Kilogramos</option>
                        <option value="gramos">Gramos</option>
                        <option value="metros">Metros</option>
                        <option value="unidades">Unidades</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="costo_unitario">Costo Unitario (Bs.)</label>
                    <input type="number" step="0.01" id="costo_unitario" class="form-control" placeholder="81.97">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="stock_actual">Stock Actual</label>
                    <input type="number" step="0.01" id="stock_actual" class="form-control" placeholder="50.00">
                </div>
                
                <div class="form-group">
                    <label for="stock_minimo">Stock Mínimo</label>
                    <input type="number" step="0.01" id="stock_minimo" class="form-control" placeholder="10.00">
                </div>
            </div>
            
            <div class="form-group">
                <label for="proveedor">Proveedor</label>
                <input type="text" id="proveedor" class="form-control" placeholder="Nombre del proveedor">
            </div>
            
            <div class="form-group">
                <label for="observaciones">Observaciones</label>
                <textarea id="observaciones" class="form-control" rows="3" placeholder="Notas adicionales sobre el insumo"></textarea>
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
    grid-template-columns: 2fr 1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px;
    background: #f7fafc;
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.stat-item.alert-stock {
    border-left-color: #f56565;
    background: #fff5f5;
}

.stat-icon {
    font-size: 32px;
    color: #667eea;
}

.alert-stock .stat-icon {
    color: #f56565;
}

.stat-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #4a5568;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value {
    display: block;
    font-size: 24px;
    font-weight: 700;
    color: #2d3748;
    margin-top: 4px;
}

.badge-stock {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-stock.bajo {
    background: #fee;
    color: #c53030;
}

.badge-stock.medio {
    background: #fef5e7;
    color: #d69e2e;
}

.badge-stock.ok {
    background: #f0fff4;
    color: #38a169;
}

.tipo-badge {
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.tipo-hilo {
    background: #e6f3ff;
    color: #1e5a8e;
}

.tipo-lycra {
    background: #ffe6f0;
    color: #a02560;
}

.tipo-auxiliar {
    background: #f0e6ff;
    color: #6b21a8;
}

.tipo-otro {
    background: #e6e6e6;
    color: #4a5568;
}

/* Modal */
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
    max-width: 700px;
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
}

.close-btn {
    background: none;
    border: none;
    font-size: 28px;
    font-weight: bold;
    color: #a0aec0;
    cursor: pointer;
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

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
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
    
    .stats-row {
        grid-template-columns: 1fr 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
let insumos = [];
const baseUrl = window.location.origin + '/mes_hermen';

document.addEventListener('DOMContentLoaded', function() {
    loadInsumos();
    
    document.getElementById('searchInsumo').addEventListener('input', filterInsumos);
    document.getElementById('filterTipo').addEventListener('change', filterInsumos);
    document.getElementById('filterStock').addEventListener('change', filterInsumos);
    document.getElementById('insumoForm').addEventListener('submit', saveInsumo);
});

async function loadInsumos() {
    try {
        const response = await fetch(baseUrl + '/api/insumos.php');
        const data = await response.json();
        
        if (data.success) {
            insumos = data.insumos;
            renderInsumos(insumos);
            updateStats();
        } else {
            showNotification(data.message || 'Error al cargar insumos', 'danger');
        }
    } catch (error) {
        console.error('Error al cargar insumos:', error);
        showNotification('Error al cargar los insumos', 'danger');
    }
}

function renderInsumos(insumosToRender) {
    const tbody = document.getElementById('insumosBody');
    
    if (insumosToRender.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">No se encontraron insumos</td></tr>';
        return;
    }
    
    tbody.innerHTML = insumosToRender.map(i => {
        const valor = (i.stock_actual * i.costo_unitario).toFixed(2);
        const estadoClass = i.estado_stock.toLowerCase();
        const tipoClass = getTipoClass(i.tipo_insumo);
        const tipoLabel = getTipoLabel(i.tipo_insumo);
        
        return `
            <tr>
                <td><strong>${i.codigo_insumo}</strong></td>
                <td>${i.nombre_insumo}</td>
                <td><span class="tipo-badge tipo-${tipoClass}">${tipoLabel}</span></td>
                <td class="text-right">Bs. ${parseFloat(i.costo_unitario).toFixed(2)}</td>
                <td class="text-right">${parseFloat(i.stock_actual).toFixed(2)} ${i.unidad_medida}</td>
                <td class="text-right">${parseFloat(i.stock_minimo).toFixed(2)} ${i.unidad_medida}</td>
                <td><span class="badge-stock ${estadoClass}">${i.estado_stock}</span></td>
                <td class="text-right"><strong>Bs. ${valor}</strong></td>
                <td class="text-center">
                    <button class="btn-icon" onclick="editInsumo(${i.id_insumo})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon" onclick="deleteInsumo(${i.id_insumo}, '${i.codigo_insumo}')" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
    
    document.getElementById('insumosFiltrados').textContent = insumosToRender.length;
}

function getTipoClass(tipo) {
    const map = {
        'HILO_POLIAMIDA': 'hilo',
        'LYCRA': 'lycra',
        'AUXILIAR_QUIMICO': 'auxiliar',
        'OTRO': 'otro'
    };
    return map[tipo] || 'otro';
}

function getTipoLabel(tipo) {
    const map = {
        'HILO_POLIAMIDA': 'Hilo Poliamida',
        'LYCRA': 'Lycra',
        'AUXILIAR_QUIMICO': 'Auxiliar',
        'OTRO': 'Otro'
    };
    return map[tipo] || tipo;
}

function updateStats() {
    document.getElementById('totalInsumos').textContent = insumos.length;
    document.getElementById('insumosFiltrados').textContent = insumos.length;
    
    const stockBajo = insumos.filter(i => i.estado_stock === 'BAJO').length;
    document.getElementById('stockBajo').textContent = stockBajo;
    
    const valorTotal = insumos.reduce((sum, i) => sum + (i.stock_actual * i.costo_unitario), 0);
    document.getElementById('valorStock').textContent = 'Bs. ' + valorTotal.toFixed(2);
}

function filterInsumos() {
    const searchText = document.getElementById('searchInsumo').value.toLowerCase();
    const tipoFilter = document.getElementById('filterTipo').value;
    const stockFilter = document.getElementById('filterStock').value;
    
    const filtered = insumos.filter(i => {
        const matchSearch = i.codigo_insumo.toLowerCase().includes(searchText) ||
                          i.nombre_insumo.toLowerCase().includes(searchText);
        const matchTipo = !tipoFilter || i.tipo_insumo === tipoFilter;
        const matchStock = !stockFilter || i.estado_stock === stockFilter;
        
        return matchSearch && matchTipo && matchStock;
    });
    
    renderInsumos(filtered);
    
    // Actualizar estadística de filtrados
    document.getElementById('insumosFiltrados').textContent = filtered.length;
}

function openModal(id = null) {
    const modal = document.getElementById('insumoModal');
    const form = document.getElementById('insumoForm');
    
    form.reset();
    document.getElementById('id_insumo').value = '';
    document.getElementById('modalTitle').textContent = 'Nuevo Insumo';
    
    if (id) {
        const insumo = insumos.find(i => i.id_insumo == id);
        if (insumo) {
            document.getElementById('id_insumo').value = insumo.id_insumo;
            document.getElementById('codigo_insumo').value = insumo.codigo_insumo;
            document.getElementById('nombre_insumo').value = insumo.nombre_insumo;
            document.getElementById('tipo_insumo').value = insumo.tipo_insumo;
            document.getElementById('unidad_medida').value = insumo.unidad_medida;
            document.getElementById('costo_unitario').value = insumo.costo_unitario;
            document.getElementById('stock_actual').value = insumo.stock_actual;
            document.getElementById('stock_minimo').value = insumo.stock_minimo;
            document.getElementById('proveedor').value = insumo.proveedor || '';
            document.getElementById('observaciones').value = insumo.observaciones || '';
            document.getElementById('modalTitle').textContent = 'Editar Insumo';
        }
    }
    
    modal.classList.add('show');
}

function closeModal() {
    document.getElementById('insumoModal').classList.remove('show');
}

function editInsumo(id) {
    openModal(id);
}

async function saveInsumo(e) {
    e.preventDefault();
    
    const formData = {
        id_insumo: document.getElementById('id_insumo').value,
        codigo_insumo: document.getElementById('codigo_insumo').value,
        nombre_insumo: document.getElementById('nombre_insumo').value,
        tipo_insumo: document.getElementById('tipo_insumo').value,
        unidad_medida: document.getElementById('unidad_medida').value,
        costo_unitario: document.getElementById('costo_unitario').value,
        stock_actual: document.getElementById('stock_actual').value,
        stock_minimo: document.getElementById('stock_minimo').value,
        proveedor: document.getElementById('proveedor').value,
        observaciones: document.getElementById('observaciones').value
    };
    
    try {
        const response = await fetch(baseUrl + '/api/insumos.php', {
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
            loadInsumos();
        } else {
            showNotification(data.message, 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al guardar el insumo', 'danger');
    }
}

async function deleteInsumo(id, codigo) {
    if (confirmAction(`¿Está seguro de eliminar el insumo ${codigo}?`)) {
        try {
            const response = await fetch(baseUrl + '/api/insumos.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id_insumo: id })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showNotification(data.message, 'success');
                loadInsumos();
            } else {
                showNotification(data.message, 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Error al eliminar el insumo', 'danger');
        }
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>