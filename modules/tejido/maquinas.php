<?php
require_once '../../config/database.php';

$pageTitle = 'Máquinas de Tejido';
$currentPage = 'maquinas';

require_once '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-cog"></i> Gestión de Máquinas</h3>
        <button class="btn btn-primary" onclick="openModal()">
            <i class="fas fa-plus"></i> Nueva Máquina
        </button>
    </div>
    
    <div class="filters-section">
        <div class="form-group">
            <input type="text" id="searchMaquina" class="form-control" placeholder="Buscar máquina...">
        </div>
        <div class="form-group">
            <select id="filterEstado" class="form-control">
                <option value="">Todos los estados</option>
                <option value="operativa">Operativa</option>
                <option value="mantenimiento">Mantenimiento</option>
                <option value="inactiva">Inactiva</option>
                <option value="sin_asignacion">Sin Asignación</option>
                <option value="sin_repuestos">Sin Repuestos</option>
            </select>
        </div>
    </div>
    
    <div class="table-container">
        <table id="tablaMaquinas">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Descripción</th>
                    <th>Diámetro</th>
                    <th>Agujas</th>
                    <th>Estado</th>
                    <th>Ubicación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="maquinasBody">
                <tr>
                    <td colspan="7" class="text-center">Cargando...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal para Agregar/Editar Máquina -->
<div id="maquinaModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Nueva Máquina</h3>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <form id="maquinaForm">
            <input type="hidden" id="id_maquina">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="numero_maquina">Número de Máquina *</label>
                    <input type="text" id="numero_maquina" class="form-control" required placeholder="M-01">
                </div>
                
                <div class="form-group">
                    <label for="estado">Estado *</label>
                    <select id="estado" class="form-control" required>
                        <option value="operativa">Operativa</option>
                        <option value="mantenimiento">Mantenimiento</option>
                        <option value="inactiva">Inactiva</option>
                        <option value="sin_asignacion">Sin Asignación</option>
                        <option value="sin_repuestos">Sin Repuestos</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="descripcion">Descripción</label>
                <input type="text" id="descripcion" class="form-control" placeholder="Máquina Circular 4 pulgadas 400 agujas">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="diametro_pulgadas">Diámetro (pulgadas)</label>
                    <input type="number" step="0.1" id="diametro_pulgadas" class="form-control" value="4.0">
                </div>
                
                <div class="form-group">
                    <label for="numero_agujas">Número de Agujas</label>
                    <input type="number" id="numero_agujas" class="form-control" value="400">
                </div>
                
                <div class="form-group">
                    <label for="ubicacion">Ubicación</label>
                    <input type="text" id="ubicacion" class="form-control" placeholder="ZONA A">
                </div>
            </div>
            
            <div class="form-group">
                <label for="fecha_instalacion">Fecha de Instalación</label>
                <input type="date" id="fecha_instalacion" class="form-control">
            </div>
            
            <div class="form-group">
                <label for="observaciones">Observaciones</label>
                <textarea id="observaciones" class="form-control" rows="3"></textarea>
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
    grid-template-columns: 1fr 200px;
    gap: 15px;
    margin-bottom: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 2000;
    align-items: center;
    justify-content: center;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 10px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 2px solid #e2e8f0;
}

.modal-header h3 {
    margin: 0;
}

.close-btn {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #718096;
}

.modal form {
    padding: 20px;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.text-center {
    text-align: center;
    padding: 20px;
    color: #a0aec0;
}

@media (max-width: 768px) {
    .filters-section {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
let maquinas = [];

document.addEventListener('DOMContentLoaded', function() {
    loadMaquinas();
    
    document.getElementById('searchMaquina').addEventListener('input', filterMaquinas);
    document.getElementById('filterEstado').addEventListener('change', filterMaquinas);
    document.getElementById('maquinaForm').addEventListener('submit', saveMaquina);
});

async function loadMaquinas() {
    try {
        const response = await fetch('../../api/maquinas.php');
        const data = await response.json();
        
        if (data.success) {
            maquinas = data.maquinas;
            renderMaquinas(maquinas);
        }
    } catch (error) {
        console.error('Error al cargar máquinas:', error);
        showNotification('Error al cargar las máquinas', 'danger');
    }
}

function renderMaquinas(maquinasToRender) {
    const tbody = document.getElementById('maquinasBody');
    
    if (maquinasToRender.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No se encontraron máquinas</td></tr>';
        return;
    }
    
    tbody.innerHTML = maquinasToRender.map(m => `
        <tr>
            <td><strong>${m.numero_maquina}</strong></td>
            <td>${m.descripcion || '-'}</td>
            <td>${m.diametro_pulgadas}"</td>
            <td>${m.numero_agujas}</td>
            <td>${getEstadoBadge(m.estado)}</td>
            <td>${m.ubicacion || '-'}</td>
            <td>
                <button class="btn-icon" onclick="editMaquina(${m.id_maquina})" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon" onclick="deleteMaquina(${m.id_maquina}, '${m.numero_maquina}')" title="Eliminar">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function getEstadoBadge(estado) {
    const badges = {
        'operativa': '<span class="badge badge-success">Operativa</span>',
        'mantenimiento': '<span class="badge badge-warning">Mantenimiento</span>',
        'inactiva': '<span class="badge badge-danger">Inactiva</span>',
        'sin_asignacion': '<span class="badge badge-info">Sin Asignación</span>',
        'sin_repuestos': '<span class="badge badge-danger">Sin Repuestos</span>'
    };
    return badges[estado] || estado;
}

function filterMaquinas() {
    const searchText = document.getElementById('searchMaquina').value.toLowerCase();
    const estadoFilter = document.getElementById('filterEstado').value;
    
    const filtered = maquinas.filter(m => {
        const matchSearch = m.numero_maquina.toLowerCase().includes(searchText) ||
                          (m.descripcion && m.descripcion.toLowerCase().includes(searchText)) ||
                          (m.ubicacion && m.ubicacion.toLowerCase().includes(searchText));
        
        const matchEstado = !estadoFilter || m.estado === estadoFilter;
        
        return matchSearch && matchEstado;
    });
    
    renderMaquinas(filtered);
}

function openModal(id = null) {
    const modal = document.getElementById('maquinaModal');
    const form = document.getElementById('maquinaForm');
    
    form.reset();
    document.getElementById('id_maquina').value = '';
    document.getElementById('modalTitle').textContent = 'Nueva Máquina';
    
    if (id) {
        const maquina = maquinas.find(m => m.id_maquina == id);
        if (maquina) {
            document.getElementById('id_maquina').value = maquina.id_maquina;
            document.getElementById('numero_maquina').value = maquina.numero_maquina;
            document.getElementById('descripcion').value = maquina.descripcion || '';
            document.getElementById('diametro_pulgadas').value = maquina.diametro_pulgadas;
            document.getElementById('numero_agujas').value = maquina.numero_agujas;
            document.getElementById('estado').value = maquina.estado;
            document.getElementById('ubicacion').value = maquina.ubicacion || '';
            document.getElementById('fecha_instalacion').value = maquina.fecha_instalacion || '';
            document.getElementById('observaciones').value = maquina.observaciones || '';
            document.getElementById('modalTitle').textContent = 'Editar Máquina';
        }
    }
    
    modal.classList.add('show');
}

function closeModal() {
    document.getElementById('maquinaModal').classList.remove('show');
}

function editMaquina(id) {
    openModal(id);
}

async function saveMaquina(e) {
    e.preventDefault();
    
    const formData = {
        id_maquina: document.getElementById('id_maquina').value,
        numero_maquina: document.getElementById('numero_maquina').value,
        descripcion: document.getElementById('descripcion').value,
        diametro_pulgadas: document.getElementById('diametro_pulgadas').value,
        numero_agujas: document.getElementById('numero_agujas').value,
        estado: document.getElementById('estado').value,
        ubicacion: document.getElementById('ubicacion').value,
        fecha_instalacion: document.getElementById('fecha_instalacion').value,
        observaciones: document.getElementById('observaciones').value
    };
    
    try {
        const response = await fetch('../../api/maquinas.php', {
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
            loadMaquinas();
        } else {
            showNotification(data.message, 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al guardar la máquina', 'danger');
    }
}

async function deleteMaquina(id, numero) {
    if (confirmAction(`¿Está seguro de eliminar la máquina ${numero}?`)) {
        try {
            const response = await fetch('../../api/maquinas.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id_maquina: id })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showNotification(data.message, 'success');
                loadMaquinas();
            } else {
                showNotification(data.message, 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Error al eliminar la máquina', 'danger');
        }
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
