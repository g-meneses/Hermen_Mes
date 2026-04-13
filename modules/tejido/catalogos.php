<?php
require_once '../../config/database.php';

if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}

$pageTitle = 'Gestión de Catálogos - Tejido';
$currentPage = 'catalogos_tejido';

require_once '../../includes/header.php';
?>

<div class="catalog-container animate-fade-in">
    <div class="catalog-header">
        <div class="header-info">
            <h2 class="text-3xl font-bold text-slate-900 tracking-tight">Catálogos de Producción</h2>
            <p class="text-slate-500 mt-1">Administración de maestros de tejeduría: Tipos, Diseños y Tallas.</p>
        </div>
        <div class="header-actions">
            <!-- Selector para mostrar/ocultar inactivos -->
            <label class="toggle-container" title="Mostrar u ocultar registros desactivados">
                <input type="checkbox" id="showInactive" onchange="applyFilters()">
                <span class="toggle-label text-slate-500 text-sm">Mostrar Inactivos</span>
                <span class="toggle-switch"></span>
            </label>
            
            <?php if (hasRole(['admin'])): ?>
            <button class="btn-create" onclick="openModal()">
                <i class="fas fa-plus"></i> Nuevo Registro
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pestañas -->
    <div class="tabs-wrapper">
        <ul class="catalog-tabs">
            <li class="tab-item active" onclick="switchTab('tipos_producto', this)">
                <i class="fas fa-tags"></i> Tipos de Producto
            </li>
            <li class="tab-item" onclick="switchTab('disenos', this)">
                <i class="fas fa-palette"></i> Diseños
            </li>
            <li class="tab-item" onclick="switchTab('tallas_tejido', this)">
                <i class="fas fa-ruler-combined"></i> Tallas
            </li>
        </ul>
    </div>

    <!-- Contenido de Tablas -->
    <div class="catalog-card box-shadow-premium">
        <div id="tableLoader" class="loader-overlay" style="display:none;">
            <div class="spinner"></div>
        </div>
        <div class="table-responsive">
            <table class="catalog-table" id="catalogTable">
                <thead>
                    <tr id="tableHeader">
                        <!-- Se llena dinámicamente -->
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <!-- Se llena dinámicamente -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal CRUD -->
<div id="catalogModal" class="modal-premium">
    <div class="modal-dialog">
        <div class="modal-content glassmorphism">
            <div class="modal-header">
                <h3 id="modalTitle">Nuevo Registro</h3>
                <button class="btn-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="catalogForm">
                <input type="hidden" id="item_id">
                <div id="formFields">
                    <!-- Campos dinámicos según la entidad -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-save" id="btnSubmit">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Estilos Premium para Catálogos */
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --card-bg: rgba(255, 255, 255, 0.9);
    --border-radius: 16px;
}

.catalog-container {
    max-width: 1200px;
    margin: 0 auto;
}

.catalog-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.btn-create {
    background: var(--primary-gradient);
    color: white;
    padding: 0.8rem 1.5rem;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 15px rgba(118, 75, 162, 0.3);
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-create:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(118, 75, 162, 0.4);
}

/* Tabs Animation */
.tabs-wrapper {
    margin-bottom: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
}

.catalog-tabs {
    display: flex;
    list-style: none;
    gap: 2rem;
    padding: 0 10px;
}

.tab-item {
    padding: 1rem 0.5rem;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    position: relative;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.tab-item i {
    font-size: 1.1rem;
}

.tab-item:hover {
    color: #4f46e5;
}

.tab-item.active {
    color: #4f46e5;
}

.tab-item.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 100%;
    height: 3px;
    background: var(--primary-gradient);
    border-radius: 3px 3px 0 0;
}

/* Table Design */
.catalog-card {
    background: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    position: relative;
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.catalog-table {
    width: 100%;
    border-collapse: collapse;
}

.catalog-table th {
    background: #f8fafc;
    padding: 1.2rem 1.5rem;
    text-align: left;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
}

.catalog-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    font-size: 0.95rem;
}

.catalog-table tr:hover td {
    background: #f9fafb;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
}

.status-active { background: #dcfce7; color: #166534; }
.status-inactive { background: #fee2e2; color: #991b1b; }

.action-btns {
    display: flex;
    gap: 8px;
}

.btn-action {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    background: #f1f5f9;
    color: #64748b;
}

.btn-action.edit:hover { background: #e0e7ff; color: #4338ca; }
.btn-action.delete:hover { background: #fee2e2; color: #dc2626; }
.btn-action.restore:hover { background: #dcfce7; color: #16a34a; }
.btn-action.permanent-delete:hover { background: #7f1d1d; color: #ffffff; }

/* Toggle Estilizado */
.toggle-container {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-right: 20px;
    cursor: pointer;
}

.toggle-switch {
    width: 40px;
    height: 20px;
    background: #cbd5e1;
    border-radius: 20px;
    position: relative;
    transition: all 0.3s;
}

.toggle-switch::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    background: white;
    border-radius: 50%;
    top: 2px;
    left: 2px;
    transition: all 0.3s;
}

#showInactive:checked ~ .toggle-switch {
    background: #4f46e5;
}

#showInactive:checked ~ .toggle-switch::after {
    transform: translateX(20px);
}

#showInactive { display: none; }

.modal-premium {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(4px);
    z-index: 2000;
}

.modal-dialog {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 1rem;
}

.modal-content {
    width: 100%;
    max-width: 500px;
    background: white;
    border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow: hidden;
}

.modal-header {
    padding: 1.5rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 1.5rem;
}

.form-field {
    margin-bottom: 1.25rem;
    padding: 0 1.5rem;
    margin-top: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: #f8fafc;
}

.form-label {
    display: block;
    font-size: 0.8rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}

.form-input {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.btn-save {
    background: #4f46e5;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
}

.btn-cancel {
    background: #f1f5f9;
    color: #64748b;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
}

.animate-fade-in { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const userRole = '<?php echo $_SESSION['user_role'] ?? 'invitado'; ?>';
let entidadActual = 'tipos_producto';
let catalogoData = [];

document.addEventListener('DOMContentLoaded', () => {
    cargarEntidad(entidadActual);
});

function switchTab(entidad, el) {
    entidadActual = entidad;
    document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    cargarEntidad(entidad);
}

function applyFilters() {
    renderTable(entidadActual, catalogoData);
}

async function cargarEntidad(entidad) {
    const loader = document.getElementById('tableLoader');
    loader.style.display = 'flex';
    
    try {
        const res = await fetch(`../../api/catalogos_produccion.php?entidad=${entidad}`);
        const result = await res.json();
        
        if (result.success) {
            catalogoData = result.data;
            renderTable(entidad, result.data);
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'No se pudieron cargar los datos', 'error');
    } finally {
        loader.style.display = 'none';
    }
}

function renderTable(entidad, data) {
    const header = document.getElementById('tableHeader');
    const body = document.getElementById('tableBody');
    const showInactive = document.getElementById('showInactive').checked;
    
    let headerHtml = '<th>Nombre</th>';
    if (entidad === 'tipos_producto') headerHtml += '<th>Categoría</th>';
    headerHtml += '<th>Descripción</th><th>Estado</th><th style="text-align:right">Acciones</th>';
    header.innerHTML = headerHtml;
    
    // Filtrar inactivos si el toggle esta apagado
    const filteredData = showInactive ? data : data.filter(item => item.activo == 1);
    
    if (filteredData.length === 0) {
        body.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:40px; color:#94a3b8">No hay registros activos en este catálogo</td></tr>`;
        return;
    }
    
    body.innerHTML = filteredData.map(item => `
        <tr class="${item.activo == 0 ? 'opacity-50 grayscale' : ''}">
            <td><strong>${item.nombre}</strong></td>
            ${entidad === 'tipos_producto' ? `<td><span class="status-badge" style="background:#f1f5f9; color:#475569">${item.categoria.toUpperCase()}</span></td>` : ''}
            <td><span class="text-xs text-slate-500">${item.descripcion || '-'}</span></td>
            <td><span class="status-badge ${item.activo == 1 ? 'status-active' : 'status-inactive'}">${item.activo == 1 ? 'Activo' : 'Inactivo'}</span></td>
            <td class="action-btns" style="justify-content:flex-end">
                ${userRole === 'admin' ? `
                    <button class="btn-action edit" onclick="editItem(${item.id})" title="Editar"><i class="fas fa-edit"></i></button>
                    <button class="btn-action ${item.activo == 1 ? 'delete' : 'restore'}" onclick="toggleStatus(${item.id}, ${item.activo})" title="${item.activo == 1 ? 'Desactivar' : 'Restaurar'}">
                        <i class="fas ${item.activo == 1 ? 'fa-eye-slash' : 'fa-eye'}"></i>
                    </button>
                    <button class="btn-action permanent-delete" onclick="deletePermanent(${item.id}, '${item.nombre}')" title="Eliminar Permanentemente">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                ` : '<span class="text-xs text-slate-400">Ver solamente</span>'}
            </td>
        </tr>
    `).join('');
}

function openModal(id = null) {
    const modal = document.getElementById('catalogModal');
    const form = document.getElementById('catalogForm');
    const title = document.getElementById('modalTitle');
    const fields = document.getElementById('formFields');
    
    form.reset();
    document.getElementById('item_id').value = id || '';
    title.innerText = id ? 'Editar Registro' : 'Nuevo Registro';
    
    let fieldsHtml = `
        <div class="form-field">
            <label class="form-label">Nombre</label>
            <input type="text" id="nombre" class="form-input" required placeholder="Ej: Manga Larga, Diseño Lujo, etc.">
        </div>
    `;
    
    if (entidadActual === 'tipos_producto') {
        fieldsHtml += `
            <div class="form-field">
                <label class="form-label">Categoría</label>
                <select id="categoria" class="form-input">
                    <option value="directo">Directo</option>
                    <option value="ensamblaje">Ensamblaje</option>
                </select>
            </div>
        `;
    }
    
    fieldsHtml += `
        <div class="form-field">
            <label class="form-label">Descripción</label>
            <textarea id="descripcion" class="form-input" rows="3" placeholder="Opcional..."></textarea>
        </div>
    `;
    
    fields.innerHTML = fieldsHtml;
    
    if (id) {
        const item = catalogoData.find(i => i.id == id);
        document.getElementById('nombre').value = item.nombre;
        document.getElementById('descripcion').value = item.descripcion || '';
        if (entidadActual === 'tipos_producto') document.getElementById('categoria').value = item.categoria;
    }
    
    modal.style.display = 'block';
}

function closeModal() {
    document.getElementById('catalogModal').style.display = 'none';
}

document.getElementById('catalogForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('item_id').value;
    const body = {
        accion: id ? 'editar' : 'crear',
        entidad: entidadActual,
        id: id,
        datos: {
            descripcion: document.getElementById('descripcion').value
        }
    };
    
    if (entidadActual === 'tipos_producto') {
        body.datos.nombre_tipo = document.getElementById('nombre').value;
        body.datos.categoria = document.getElementById('categoria').value;
    } else if (entidadActual === 'disenos') {
        body.datos.nombre_diseno = document.getElementById('nombre').value;
    } else if (entidadActual === 'tallas_tejido') {
        body.datos.nombre_talla = document.getElementById('nombre').value;
    }
    
    try {
        const res = await fetch('../../api/catalogos_produccion.php', {
            method: 'POST',
            body: JSON.stringify(body),
            headers: {'Content-Type': 'application/json'}
        });
        const result = await res.json();
        if (result.success) {
            Swal.fire('Éxito', result.message, 'success');
            closeModal();
            cargarEntidad(entidadActual);
        } else {
            Swal.fire('Error', result.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
    }
});

async function toggleStatus(id, estadoActual) {
    const word = estadoActual ? 'desactivar' : 'activar';
    const result = await Swal.fire({
        title: `¿Confirmas ${word} este registro?`,
        text: estadoActual ? "Seguirá existiendo pero no aparecerá en nuevos registros." : "Volverá a estar disponible en los selectores.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        confirmButtonText: 'Sí, confirmar'
    });
    
    if (result.isConfirmed) {
        try {
            const res = await fetch('../../api/catalogos_produccion.php', {
                method: 'POST',
                body: JSON.stringify({
                    accion: 'desactivar',
                    entidad: entidadActual,
                    id: id,
                    activo: estadoActual ? 0 : 1
                }),
                headers: {'Content-Type': 'application/json'}
            });
            const data = await res.json();
            if (data.success) {
                cargarEntidad(entidadActual);
            } else {
                Swal.fire('Bloqueado', data.message, 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo actualizar el estado', 'error');
        }
    }
}

async function deletePermanent(id, nombre) {
    const result = await Swal.fire({
        title: '¿Eliminar permanentemente?',
        text: `Esta acción borrará a "${nombre}" de la base de datos. Solo es posible si NO tiene historial de uso.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, borrar para siempre',
        cancelButtonText: 'Cancelar'
    });
    
    if (result.isConfirmed) {
        try {
            const res = await fetch('../../api/catalogos_produccion.php', {
                method: 'POST',
                body: JSON.stringify({
                    accion: 'eliminar_fisico',
                    entidad: entidadActual,
                    id: id
                }),
                headers: {'Content-Type': 'application/json'}
            });
            const data = await res.json();
            if (data.success) {
                Swal.fire('Eliminado', data.message, 'success');
                cargarEntidad(entidadActual);
            } else {
                Swal.fire('No es posible eliminar', data.message, 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
        }
    }
}

function editItem(id) {
    openModal(id);
}

// Cerrar modal al clickear afuera
window.onclick = (e) => {
    if (e.target.classList.contains('modal-premium')) closeModal();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
