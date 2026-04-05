<?php
require_once '../../config/database.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'BOM y Lotes WIP';
$currentPage = 'wip_fase0';
require_once '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-flask"></i> BOM WIP + Registro de Lote</h3>
        <div class="card-header-actions">
            <input type="text" id="buscarProducto" placeholder="Buscar producto..." class="search-input">
            <select id="filtroLinea" class="filter-select">
                <option value="">Todas las líneas</option>
            </select>
            <select id="filtroEstado" class="filter-select">
                <option value="">Todos</option>
                <option value="con">Con BOM</option>
                <option value="sin">Sin BOM</option>
            </select>
        </div>
    </div>

    <div class="stats-container">
        <div class="stat-card stat-primary">
            <div class="stat-value" id="totalProductos">0</div>
            <div class="stat-label">Productos</div>
        </div>
        <div class="stat-card stat-success">
            <div class="stat-value" id="productosConBom">0</div>
            <div class="stat-label">Con BOM</div>
        </div>
        <div class="stat-card stat-warning">
            <div class="stat-value" id="productosSinBom">0</div>
            <div class="stat-label">Sin BOM</div>
        </div>
        <div class="stat-card stat-info">
            <div class="stat-value" id="coberturaBom">0%</div>
            <div class="stat-label">Cobertura</div>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Línea</th>
                    <th>Talla</th>
                    <th>Componentes</th>
                    <th>Gr/doc</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="bodyProductos">
                <tr><td colspan="8" class="text-center">Cargando productos...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-industry"></i> Lotes WIP Recientes</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Lote</th>
                    <th>Producto</th>
                    <th>Área</th>
                    <th>Cantidad</th>
                    <th>Costo MP</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody id="bodyLotes">
                <tr><td colspan="7" class="text-center">Cargando lotes...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div id="modalBom" class="modal">
    <div class="modal-content" style="max-width: 1050px;">
        <div class="modal-header">
            <h3><i class="fas fa-layer-group"></i> BOM WIP del Producto</h3>
            <button class="close-modal" onclick="cerrarModalBom()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="info-producto">
                <h4 id="bomProductoCodigo"></h4>
                <p id="bomProductoDetalle"></p>
            </div>

            <div class="toolbar-row">
                <div>
                    <label>Merma global (%)</label>
                    <input type="number" id="bomMermaGlobal" step="0.001" min="0" value="0">
                </div>
                <div class="grow">
                    <label>Observaciones BOM</label>
                    <input type="text" id="bomObservaciones" placeholder="Observaciones del BOM">
                </div>
                <div class="toolbar-actions">
                    <button class="btn-secondary" onclick="abrirModalComponente()">Agregar componente</button>
                    <button class="btn-primary" onclick="guardarBom()">Guardar BOM</button>
                </div>
            </div>

            <div class="table-container" style="margin-top: 16px;">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Componente</th>
                            <th>Stock</th>
                            <th>Unidad</th>
                            <th>Gr/doc</th>
                            <th>% Comp.</th>
                            <th>% Merma</th>
                            <th>Principal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="bodyBom">
                        <tr><td colspan="9" class="text-center">No hay componentes cargados</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="toolbar-actions" style="justify-content: flex-end; margin-top: 16px;">
                <button class="btn-success" id="btnAbrirLoteDesdeBom" onclick="abrirModalLote()">Registrar lote WIP</button>
            </div>
        </div>
    </div>
</div>

<div id="modalComponente" class="modal">
    <div class="modal-content" style="max-width: 560px;">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Agregar Componente MP</h3>
            <button class="close-modal" onclick="cerrarModalComponente()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label for="componenteInventario">Componente MP</label>
                    <select id="componenteInventario"></select>
                </div>
                <div class="form-group">
                    <label for="componenteGramos">Gramos por docena</label>
                    <input type="number" id="componenteGramos" min="0.0001" step="0.0001">
                </div>
                <div class="form-group">
                    <label for="componentePorcentaje">% componente</label>
                    <input type="number" id="componentePorcentaje" min="0" step="0.001">
                </div>
                <div class="form-group">
                    <label for="componenteMerma">% merma</label>
                    <input type="number" id="componenteMerma" min="0" step="0.001" value="0">
                </div>
                <div class="form-group">
                    <label for="componentePrincipal">Principal</label>
                    <select id="componentePrincipal">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="cerrarModalComponente()">Cancelar</button>
            <button class="btn-primary" onclick="agregarComponente()">Agregar</button>
        </div>
    </div>
</div>

<div id="modalLote" class="modal">
    <div class="modal-content" style="max-width: 560px;">
        <div class="modal-header">
            <h3><i class="fas fa-industry"></i> Registrar Lote WIP FASE 0</h3>
            <button class="close-modal" onclick="cerrarModalLote()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="info-producto compact">
                <h4 id="loteProductoCodigo"></h4>
                <p id="loteProductoDetalle"></p>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="loteDocenas">Docenas</label>
                    <input type="number" id="loteDocenas" min="0" step="1" value="0">
                </div>
                <div class="form-group">
                    <label for="loteUnidades">Unidades</label>
                    <input type="number" id="loteUnidades" min="0" max="11" step="1" value="0">
                </div>
                <div class="form-group full">
                    <label for="loteReferencia">Referencia externa</label>
                    <input type="text" id="loteReferencia" placeholder="Opcional, si se deja vacío se genera OP-TEJ">
                </div>
                <div class="form-group full">
                    <label for="loteObservaciones">Observaciones</label>
                    <textarea id="loteObservaciones" rows="3" placeholder="Notas del lote"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="cerrarModalLote()">Cancelar</button>
            <button class="btn-success" onclick="registrarLoteWip()">Registrar lote</button>
        </div>
    </div>
</div>

<style>
.stats-container { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; padding:20px; }
.stat-card { border-radius:14px; color:#fff; padding:18px; box-shadow:0 10px 24px rgba(0,0,0,.08); }
.stat-primary { background:linear-gradient(135deg,#2563eb,#4f46e5); }
.stat-success { background:linear-gradient(135deg,#059669,#10b981); }
.stat-warning { background:linear-gradient(135deg,#d97706,#f59e0b); }
.stat-info { background:linear-gradient(135deg,#0891b2,#06b6d4); }
.stat-value { font-size:28px; font-weight:700; }
.stat-label { font-size:13px; opacity:.92; }
.info-producto { background:#f8fafc; border-left:4px solid #2563eb; padding:14px 16px; border-radius:10px; margin-bottom:16px; }
.info-producto.compact { margin-bottom:12px; }
.info-producto h4 { margin:0 0 4px; color:#1e293b; }
.info-producto p { margin:0; color:#64748b; }
.toolbar-row { display:flex; gap:12px; align-items:end; flex-wrap:wrap; }
.toolbar-row .grow { flex:1; min-width:220px; }
.toolbar-row label, .form-group label { display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#334155; }
.toolbar-row input, .form-group input, .form-group select, .form-group textarea { width:100%; padding:10px 12px; border:1px solid #dbe3ea; border-radius:10px; font:inherit; }
.toolbar-actions { display:flex; gap:10px; align-items:center; }
.form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
.form-group.full { grid-column:1 / -1; }
.text-center { text-align:center; }
.badge-ok, .badge-empty { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; }
.badge-ok { background:#dcfce7; color:#166534; }
.badge-empty { background:#fef3c7; color:#92400e; }
.btn-primary, .btn-secondary, .btn-success { border:none; border-radius:10px; padding:10px 14px; cursor:pointer; font-weight:600; }
.btn-primary { background:#2563eb; color:#fff; }
.btn-secondary { background:#e2e8f0; color:#1e293b; }
.btn-success { background:#059669; color:#fff; }
.btn-icon { border:none; border-radius:8px; padding:8px 10px; cursor:pointer; margin-right:6px; }
.btn-icon.btn-primary { background:#dbeafe; color:#1d4ed8; }
.btn-icon.btn-success { background:#dcfce7; color:#15803d; }
.btn-icon.btn-danger { background:#fee2e2; color:#b91c1c; }
.notification { position:fixed; top:82px; right:24px; min-width:280px; max-width:420px; padding:14px 16px; border-radius:12px; color:#fff; box-shadow:0 14px 34px rgba(15,23,42,.22); transform:translateY(-12px); opacity:0; transition:all .25s ease; z-index:11050; }
.notification.show { transform:translateY(0); opacity:1; }
.notification-success { background:#059669; }
.notification-error { background:#dc2626; }
.notification-warning { background:#d97706; }
@media (max-width: 768px) { .form-grid { grid-template-columns:1fr; } }
</style>

<script>
const baseUrl = window.location.origin + '/mes_hermen';
let productos = [];
let productosFiltrados = [];
let componentesMp = [];
let lotesRecientes = [];
let productoActual = null;
let bomActual = null;
let detallesBomActual = [];

document.addEventListener('DOMContentLoaded', async () => {
    await Promise.all([cargarLineas(), cargarComponentesMp(), cargarProductos(), cargarLotes()]);
    document.getElementById('buscarProducto').addEventListener('input', filtrarProductos);
    document.getElementById('filtroLinea').addEventListener('change', filtrarProductos);
    document.getElementById('filtroEstado').addEventListener('change', filtrarProductos);
});

async function cargarLineas() {
    const response = await fetch(baseUrl + '/api/catalogos.php?tipo=lineas');
    const data = await response.json();
    if (!data.success) return;
    const select = document.getElementById('filtroLinea');
    data.lineas.forEach(linea => {
        const option = document.createElement('option');
        option.value = linea.id_linea;
        option.textContent = linea.nombre_linea;
        select.appendChild(option);
    });
}

async function cargarComponentesMp() {
    const response = await fetch(baseUrl + '/api/ingresos_mp.php?action=productos');
    const data = await response.json();
    if (data.success) {
        componentesMp = data.productos || [];
    }
}

async function cargarProductos() {
    const response = await fetch(baseUrl + '/api/bom_wip.php');
    const data = await response.json();
    if (!data.success) {
        showNotification(data.message || 'No se pudo cargar BOM WIP', 'error');
        return;
    }
    productos = data.productos || [];
    productosFiltrados = [...productos];
    actualizarEstadisticas();
    renderProductos(productosFiltrados);
}

async function cargarLotes() {
    const response = await fetch(baseUrl + '/api/wip.php');
    const data = await response.json();
    if (!data.success) {
        document.getElementById('bodyLotes').innerHTML = '<tr><td colspan="7" class="text-center">No se pudieron cargar los lotes</td></tr>';
        return;
    }

    lotesRecientes = data.lotes || [];
    renderLotes();
}

function actualizarEstadisticas() {
    const total = productos.length;
    const conBom = productos.filter(p => parseInt(p.total_componentes || 0, 10) > 0).length;
    const sinBom = total - conBom;
    const cobertura = total > 0 ? Math.round((conBom / total) * 100) : 0;
    document.getElementById('totalProductos').textContent = total;
    document.getElementById('productosConBom').textContent = conBom;
    document.getElementById('productosSinBom').textContent = sinBom;
    document.getElementById('coberturaBom').textContent = cobertura + '%';
}

function filtrarProductos() {
    const texto = document.getElementById('buscarProducto').value.toLowerCase();
    const linea = document.getElementById('filtroLinea').value;
    const estado = document.getElementById('filtroEstado').value;

    productosFiltrados = productos.filter(p => {
        const matchTexto = !texto || p.codigo_producto.toLowerCase().includes(texto) || (p.descripcion_completa || '').toLowerCase().includes(texto);
        const matchLinea = !linea || String(p.id_linea) === String(linea);
        const totalComp = parseInt(p.total_componentes || 0, 10);
        const matchEstado = !estado || (estado === 'con' ? totalComp > 0 : totalComp === 0);
        return matchTexto && matchLinea && matchEstado;
    });

    renderProductos(productosFiltrados);
}

function renderProductos(lista) {
    const tbody = document.getElementById('bodyProductos');
    if (!lista.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No se encontraron productos</td></tr>';
        return;
    }

    tbody.innerHTML = lista.map(producto => {
        const totalComp = parseInt(producto.total_componentes || 0, 10);
        const tieneBom = totalComp > 0;
        return `
            <tr>
                <td><strong>${producto.codigo_producto}</strong></td>
                <td>${producto.descripcion_completa || ''}</td>
                <td>${producto.nombre_linea || '-'}</td>
                <td>${producto.talla || '-'}</td>
                <td>${tieneBom ? totalComp + ' comp.' : '-'}</td>
                <td>${tieneBom ? Number(producto.gramos_totales_docena || 0).toFixed(2) + ' g' : '-'}</td>
                <td>${tieneBom ? '<span class="badge-ok">Con BOM</span>' : '<span class="badge-empty">Sin BOM</span>'}</td>
                <td>
                    <button class="btn-icon btn-primary" title="Editar BOM" onclick="abrirBom(${producto.id_producto})"><i class="fas fa-flask"></i></button>
                    ${tieneBom ? `<button class="btn-icon btn-success" title="Registrar lote WIP" onclick="abrirLoteDesdeTabla(${producto.id_producto})"><i class="fas fa-industry"></i></button>` : ''}
                </td>
            </tr>
        `;
    }).join('');
}

function renderLotes() {
    const tbody = document.getElementById('bodyLotes');
    if (!lotesRecientes.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">Todavía no hay lotes WIP registrados</td></tr>';
        return;
    }

    tbody.innerHTML = lotesRecientes.map(lote => `
        <tr>
            <td><strong>${lote.codigo_lote}</strong><br><small>${lote.referencia_externa || ''}</small></td>
            <td>${lote.codigo_producto} - ${lote.descripcion_completa || ''}</td>
            <td>${lote.area_actual_nombre || '-'}</td>
            <td>${Number(lote.cantidad_docenas || 0)} doc / ${Number(lote.cantidad_unidades || 0)} und</td>
            <td>Bs. ${Number(lote.costo_mp_acumulado || 0).toFixed(2)}</td>
            <td><span class="badge-ok">${lote.estado_lote || 'ACTIVO'}</span></td>
            <td>${lote.fecha_inicio || '-'}</td>
        </tr>
    `).join('');
}

async function abrirBom(idProducto) {
    productoActual = productos.find(p => String(p.id_producto) === String(idProducto));
    if (!productoActual) return;

    document.getElementById('bomProductoCodigo').textContent = productoActual.codigo_producto;
    document.getElementById('bomProductoDetalle').textContent = productoActual.descripcion_completa || '';
    document.getElementById('bomMermaGlobal').value = 0;
    document.getElementById('bomObservaciones').value = '';

    const response = await fetch(baseUrl + '/api/bom_wip.php?id_producto=' + idProducto);
    const data = await response.json();

    if (data.success) {
        bomActual = data.bom;
        detallesBomActual = data.detalles || [];
        document.getElementById('bomMermaGlobal').value = bomActual.merma_pct || 0;
        document.getElementById('bomObservaciones').value = bomActual.observaciones || '';
    } else {
        bomActual = { id_bom: null, id_producto: idProducto };
        detallesBomActual = [];
    }

    renderBom();
    document.getElementById('btnAbrirLoteDesdeBom').style.display = detallesBomActual.length ? 'inline-flex' : 'none';
    document.getElementById('modalBom').classList.add('show');
}

function renderBom() {
    const tbody = document.getElementById('bodyBom');
    if (!detallesBomActual.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">No hay componentes cargados</td></tr>';
        return;
    }

    tbody.innerHTML = detallesBomActual.map((item, idx) => `
        <tr>
            <td>${item.codigo}</td>
            <td>${item.nombre}</td>
            <td>${Number(item.stock_actual || 0).toFixed(2)}</td>
            <td>${item.unidad_abreviatura || item.unidad_nombre || '-'}</td>
            <td>${Number(item.gramos_por_docena || 0).toFixed(4)}</td>
            <td>${item.porcentaje_componente !== null && item.porcentaje_componente !== undefined ? Number(item.porcentaje_componente).toFixed(3) : '-'}</td>
            <td>${Number(item.merma_pct || 0).toFixed(3)}</td>
            <td>${Number(item.es_principal) === 1 ? 'Sí' : 'No'}</td>
            <td><button class="btn-icon btn-danger" onclick="quitarComponente(${idx})"><i class="fas fa-trash"></i></button></td>
        </tr>
    `).join('');
}

function abrirModalComponente() {
    const select = document.getElementById('componenteInventario');
    select.innerHTML = '<option value="">Seleccione un componente MP</option>' + componentesMp.map(item =>
        `<option value="${item.id_inventario}">${item.codigo} - ${item.nombre} (${Number(item.stock_actual || 0).toFixed(2)} ${item.unidad || ''})</option>`
    ).join('');
    document.getElementById('componenteGramos').value = '';
    document.getElementById('componentePorcentaje').value = '';
    document.getElementById('componenteMerma').value = '0';
    document.getElementById('componentePrincipal').value = '0';
    document.getElementById('modalComponente').classList.add('show');
}

function agregarComponente() {
    const idInventario = parseInt(document.getElementById('componenteInventario').value, 10);
    const gramos = parseFloat(document.getElementById('componenteGramos').value);
    const porcentaje = document.getElementById('componentePorcentaje').value;
    const merma = parseFloat(document.getElementById('componenteMerma').value || '0');
    const esPrincipal = parseInt(document.getElementById('componentePrincipal').value, 10);

    if (!idInventario || !gramos || gramos <= 0) {
        showNotification('Seleccione un componente y registre gramos por docena válidos', 'warning');
        return;
    }

    const componente = componentesMp.find(item => Number(item.id_inventario) === idInventario);
    if (!componente) {
        showNotification('No se encontró el componente seleccionado', 'error');
        return;
    }

    const existente = detallesBomActual.find(item => Number(item.id_inventario) === idInventario);
    if (existente) {
        existente.gramos_por_docena = gramos;
        existente.porcentaje_componente = porcentaje === '' ? null : parseFloat(porcentaje);
        existente.merma_pct = merma;
        existente.es_principal = esPrincipal;
    } else {
        detallesBomActual.push({
            id_inventario: idInventario,
            codigo: componente.codigo,
            nombre: componente.nombre,
            stock_actual: componente.stock_actual,
            unidad_abreviatura: componente.unidad,
            gramos_por_docena: gramos,
            porcentaje_componente: porcentaje === '' ? null : parseFloat(porcentaje),
            merma_pct: merma,
            es_principal: esPrincipal
        });
    }

    if (esPrincipal === 1) {
        detallesBomActual = detallesBomActual.map(item => ({
            ...item,
            es_principal: Number(item.id_inventario) === idInventario ? 1 : 0
        }));
    }

    renderBom();
    document.getElementById('btnAbrirLoteDesdeBom').style.display = detallesBomActual.length ? 'inline-flex' : 'none';
    cerrarModalComponente();
}

function quitarComponente(index) {
    detallesBomActual.splice(index, 1);
    renderBom();
    document.getElementById('btnAbrirLoteDesdeBom').style.display = detallesBomActual.length ? 'inline-flex' : 'none';
}

async function guardarBom() {
    if (!productoActual) return;
    if (!detallesBomActual.length) {
        showNotification('Debe agregar al menos un componente', 'warning');
        return;
    }

    const payload = {
        action: 'save',
        id_bom: bomActual?.id_bom || null,
        id_producto: productoActual.id_producto,
        merma_pct: parseFloat(document.getElementById('bomMermaGlobal').value || '0'),
        observaciones: document.getElementById('bomObservaciones').value,
        detalles: detallesBomActual.map((item, idx) => ({
            id_inventario: item.id_inventario,
            gramos_por_docena: item.gramos_por_docena,
            porcentaje_componente: item.porcentaje_componente,
            merma_pct: item.merma_pct || 0,
            es_principal: item.es_principal || 0,
            orden_visual: idx + 1
        }))
    };

    const response = await fetch(baseUrl + '/api/bom_wip.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const data = await response.json();

    if (!data.success) {
        showNotification(data.message || 'No se pudo guardar el BOM', 'error');
        return;
    }

    showNotification(data.message, 'success');
    await cargarProductos();
    await abrirBom(productoActual.id_producto);
}

function abrirLoteDesdeTabla(idProducto) {
    abrirBom(idProducto).then(() => abrirModalLote());
}

function abrirModalLote() {
    if (!productoActual || !detallesBomActual.length) {
        showNotification('El producto debe tener BOM activo antes de registrar un lote', 'warning');
        return;
    }

    document.getElementById('loteProductoCodigo').textContent = productoActual.codigo_producto;
    document.getElementById('loteProductoDetalle').textContent = productoActual.descripcion_completa || '';
    document.getElementById('loteDocenas').value = '0';
    document.getElementById('loteUnidades').value = '0';
    document.getElementById('loteReferencia').value = '';
    document.getElementById('loteObservaciones').value = '';
    document.getElementById('modalLote').classList.add('show');
}

async function registrarLoteWip() {
    if (!productoActual) return;

    const payload = {
        action: 'crear_lote',
        id_producto: productoActual.id_producto,
        cantidad_docenas: parseInt(document.getElementById('loteDocenas').value || '0', 10),
        cantidad_unidades: parseInt(document.getElementById('loteUnidades').value || '0', 10),
        referencia_externa: document.getElementById('loteReferencia').value.trim(),
        observaciones: document.getElementById('loteObservaciones').value.trim()
    };

    const response = await fetch(baseUrl + '/api/wip.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const data = await response.json();

    if (!data.success) {
        mostrarErrorVisible(data.message || 'No se pudo registrar el lote WIP');
        return;
    }

    cerrarModalLote();
    mostrarExitoVisible(`${data.message}: ${data.codigo_lote} / ${data.numero_documento}`);
    await cargarLotes();
}

function cerrarModalBom() { document.getElementById('modalBom').classList.remove('show'); }
function cerrarModalComponente() { document.getElementById('modalComponente').classList.remove('show'); }
function cerrarModalLote() { document.getElementById('modalLote').classList.remove('show'); }

window.onclick = function (event) {
    ['modalBom', 'modalComponente', 'modalLote'].forEach(id => {
        const modal = document.getElementById(id);
        if (event.target === modal) {
            modal.classList.remove('show');
        }
    });
};

function showNotification(message, type) {
    const el = document.createElement('div');
    el.className = `notification notification-${type}`;
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(() => el.classList.add('show'), 10);
    setTimeout(() => {
        el.classList.remove('show');
        setTimeout(() => el.remove(), 250);
    }, 3200);
}

function mostrarErrorVisible(message) {
    showNotification(message, 'error');
    window.alert(message);
}

function mostrarExitoVisible(message) {
    showNotification(message, 'success');
    window.alert(message);
}
</script>

<?php require_once '../../includes/footer.php'; ?>
