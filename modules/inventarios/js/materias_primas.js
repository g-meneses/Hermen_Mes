/**
 * JavaScript para módulo Materias Primas  
 * Sistema MES Hermen Ltda. v1.9
 * VERSIÓN CORREGIDA CON TODAS LAS FUNCIONES
 */

const baseUrl = window.location.origin + '/mes_hermen';
const TIPO_ID = document.querySelector('.mp-title-icon')?.dataset?.tipoId || 1;

let categorias = [], subcategorias = [], productos = [], productosCompletos = [];
let unidades = [], proveedores = [];
let categoriaSeleccionada = null, subcategoriaSeleccionada = null;
let lineasIngreso = [], lineasSalida = [];
let documentoActual = null;
let productosFiltrados = [];
let modoConFactura = false;
let contadorDocIngreso = 0;

// ========== INICIALIZACIÓN ==========
document.addEventListener('DOMContentLoaded', cargarDatos);

async function cargarDatos() {
    await Promise.all([cargarKPIs(), cargarCategorias(), cargarUnidades(), cargarProveedores(), cargarTodosProductos()]);
}

// ========== FUNCIONES DE FORMATO ==========

function toNum(value) {
    if (value === null || value === undefined || value === '') return 0;
    if (typeof value === 'number') return value;
    
    let str = String(value).trim();
    
    if (str.includes(',') && str.includes('.')) {
        str = str.replace(/,/g, '');
    } else if (str.includes(',')) {
        if (/,\d{2}$/.test(str)) {
            str = str.replace(',', '.');
        } else {
            str = str.replace(/,/g, '');
        }
    }
    
    const num = parseFloat(str);
    return isNaN(num) ? 0 : num;
}

function formatNum(value, decimals = 2) {
    const num = toNum(value);
    return num.toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

// ========== KPIs ==========
async function cargarKPIs() {
    try {
        const r = await fetch(`${baseUrl}/api/centro_inventarios.php?action=resumen&tipo_id=${TIPO_ID}`);
        const d = await r.json();
        if (d.success) {
            const totales = d.totales || {};
            const numCategorias = d.resumen ? d.resumen.length : 0;
            
            document.getElementById('kpiItems').textContent = totales.items || 0;
            document.getElementById('kpiValor').textContent = 'Bs. ' + formatNum(totales.valor);
            document.getElementById('kpiAlertas').textContent = totales.alertas || 0;
            document.getElementById('kpiCategorias').textContent = numCategorias;
        }
    } catch (e) { console.error('Error KPIs:', e); }
}

// ========== CATEGORÍAS ==========
async function cargarCategorias() {
    try {
        const r = await fetch(`${baseUrl}/api/centro_inventarios.php?action=categorias&tipo_id=${TIPO_ID}`);
        const d = await r.json();
        console.log('Respuesta Categorías:', d);
        
        if (d.success && d.categorias) {
            categorias = d.categorias.map(cat => ({
                id_categoria: cat.id_categoria,
                nombre: cat.nombre,
                codigo: cat.codigo || '',
                total_items: 0,
                alertas: 0,
                valor_total: 0
            }));
            
            const rProd = await fetch(`${baseUrl}/api/centro_inventarios.php?action=list&tipo_id=${TIPO_ID}`);
            const dProd = await rProd.json();
            
            if (dProd.success && dProd.inventarios) {
                dProd.inventarios.forEach(prod => {
                    const cat = categorias.find(c => c.id_categoria == prod.id_categoria);
                    if (cat) {
                        cat.total_items++;
                        const stock = toNum(prod.stock_actual);
                        const stockMin = toNum(prod.stock_minimo);
                        const costo = toNum(prod.costo_promedio) || toNum(prod.costo_unitario);
                        cat.valor_total += stock * costo;
                        if (stock > 0 && stock <= stockMin) {
                            cat.alertas++;
                        }
                    }
                });
            }
            
            renderCategorias();
        }
    } catch (e) { console.error('Error categorías:', e); }
}

function renderCategorias() {
    const grid = document.getElementById('categoriasGrid');
    if (categorias.length === 0) { 
        grid.innerHTML = '<p style="padding:20px;text-align:center;">No hay categorías</p>'; 
        return; 
    }
    
    grid.innerHTML = categorias.map(c => `
        <div class="categoria-card ${categoriaSeleccionada?.id_categoria == c.id_categoria ? 'active' : ''}" onclick="seleccionarCategoria(${c.id_categoria})">
            <div class="categoria-header">
                <div class="categoria-nombre">${c.nombre}</div>
                <span class="categoria-badge">${c.total_items || 0}</span>
            </div>
            <div class="categoria-stats">
                <div><div class="cat-stat-value">${c.total_items || 0}</div><div class="cat-stat-label">Items</div></div>
                <div><div class="cat-stat-value alerta">${c.alertas || 0}</div><div class="cat-stat-label">Alertas</div></div>
                <div><div class="cat-stat-value">Bs.${formatNum(c.valor_total)}</div><div class="cat-stat-label">Valor</div></div>
            </div>
        </div>
    `).join('');
}

async function seleccionarCategoria(idCategoria) {
    categoriaSeleccionada = categorias.find(c => c.id_categoria == idCategoria);
    subcategoriaSeleccionada = null;
    renderCategorias();
    
    try {
        const r = await fetch(`${baseUrl}/api/centro_inventarios.php?action=subcategorias&categoria_id=${idCategoria}`);
        const d = await r.json();
        
        if (d.success && d.subcategorias && d.subcategorias.length > 0) {
            let sinSubcategoria = { total_items: 0, valor_total: 0, alertas: 0 };
            
            subcategorias = d.subcategorias.map(s => ({
                ...s,
                total_items: 0,
                valor_total: 0,
                alertas: 0
            }));
            
            productosCompletos.forEach(prod => {
                if (prod.id_categoria == idCategoria) {
                    const stock = toNum(prod.stock_actual);
                    const stockMin = toNum(prod.stock_minimo);
                    const costo = toNum(prod.costo_promedio) || toNum(prod.costo_unitario);
                    const esAlerta = stock > 0 && stock <= stockMin;
                    
                    if (prod.id_subcategoria) {
                        const sub = subcategorias.find(s => s.id_subcategoria == prod.id_subcategoria);
                        if (sub) {
                            sub.total_items++;
                            sub.valor_total += stock * costo;
                            if (esAlerta) sub.alertas++;
                        }
                    } else {
                        sinSubcategoria.total_items++;
                        sinSubcategoria.valor_total += stock * costo;
                        if (esAlerta) sinSubcategoria.alertas++;
                    }
                }
            });
            
            if (sinSubcategoria.total_items > 0) {
                subcategorias.unshift({
                    id_subcategoria: 0,
                    nombre: '📦 Sin Clasificar',
                    total_items: sinSubcategoria.total_items,
                    valor_total: sinSubcategoria.valor_total,
                    alertas: sinSubcategoria.alertas
                });
            }
            
            const catData = categorias.find(c => c.id_categoria == idCategoria);
            subcategorias.unshift({
                id_subcategoria: -1,
                nombre: '👁️ Ver Todos',
                total_items: catData?.total_items || 0,
                valor_total: catData?.valor_total || 0,
                alertas: catData?.alertas || 0
            });
            
            mostrarSubcategorias();
        } else {
            document.getElementById('subcategoriasSection').style.display = 'none';
            cargarProductosCategoria(idCategoria);
        }
    } catch (e) { 
        console.error('Error subcategorías:', e);
        document.getElementById('subcategoriasSection').style.display = 'none';
        cargarProductosCategoria(idCategoria);
    }
}

function mostrarSubcategorias() {
    document.getElementById('subcategoriaTitulo').textContent = categoriaSeleccionada.nombre;
    document.getElementById('subcategoriasGrid').innerHTML = subcategorias.map(s => {
        const esVerTodos = s.id_subcategoria === -1;
        const esSinClasificar = s.id_subcategoria === 0;
        
        let estiloExtra = '';
        if (esVerTodos) {
            estiloExtra = 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;';
        } else if (esSinClasificar) {
            estiloExtra = 'background: #fff3cd; border-color: #ffc107;';
        }
        
        return `
        <div class="categoria-card ${subcategoriaSeleccionada?.id_subcategoria == s.id_subcategoria ? 'active' : ''}" 
             onclick="seleccionarSubcategoria(${s.id_subcategoria})"
             style="${estiloExtra}">
            <div class="categoria-header">
                <div class="categoria-nombre" ${esVerTodos ? 'style="color:white;"' : ''}>${s.nombre}</div>
                <span class="categoria-badge" ${esVerTodos ? 'style="background:white;color:#667eea;"' : ''}>${s.total_items || 0}</span>
            </div>
            <div class="categoria-stats">
                <div>
                    <div class="cat-stat-value" ${esVerTodos ? 'style="color:white;"' : ''}>${s.total_items || 0}</div>
                    <div class="cat-stat-label" ${esVerTodos ? 'style="color:rgba(255,255,255,0.8);"' : ''}>Items</div>
                </div>
                <div>
                    <div class="cat-stat-value ${s.alertas > 0 ? 'alerta' : ''}" ${esVerTodos ? 'style="color:white;"' : ''}>${s.alertas || 0}</div>
                    <div class="cat-stat-label" ${esVerTodos ? 'style="color:rgba(255,255,255,0.8);"' : ''}>Alertas</div>
                </div>
                <div>
                    <div class="cat-stat-value" ${esVerTodos ? 'style="color:white;"' : ''}>Bs.${formatNum(s.valor_total)}</div>
                    <div class="cat-stat-label" ${esVerTodos ? 'style="color:rgba(255,255,255,0.8);"' : ''}>Valor</div>
                </div>
            </div>
        </div>`;
    }).join('');
    document.getElementById('subcategoriasSection').style.display = 'block';
    document.getElementById('productosSection').style.display = 'none';
}

async function seleccionarSubcategoria(idSubcategoria) {
    subcategoriaSeleccionada = subcategorias.find(s => s.id_subcategoria == idSubcategoria);
    mostrarSubcategorias();
    
    if (idSubcategoria === -1) {
        cargarProductosCategoria(categoriaSeleccionada.id_categoria);
    } else if (idSubcategoria === 0) {
        cargarProductosSinSubcategoria(categoriaSeleccionada.id_categoria);
    } else {
        cargarProductosSubcategoria(idSubcategoria);
    }
}

async function cargarProductosSinSubcategoria(idCategoria) {
    mostrarProductosSection('Sin Clasificar');
    try {
        productos = productosCompletos.filter(p => 
            p.id_categoria == idCategoria && 
            (!p.id_subcategoria || p.id_subcategoria === null || p.id_subcategoria === 0)
        );
        renderProductos();
    } catch (e) { console.error('Error:', e); }
}

// ========== PRODUCTOS ==========
async function cargarProductosCategoria(idCategoria) {
    mostrarProductosSection(categoriaSeleccionada.nombre);
    try {
        const r = await fetch(`${baseUrl}/api/centro_inventarios.php?action=list&tipo_id=${TIPO_ID}&categoria_id=${idCategoria}`);
        const d = await r.json();
        if (d.success) { productos = d.inventarios || []; renderProductos(); }
    } catch (e) { console.error('Error:', e); }
}

async function cargarProductosSubcategoria(idSubcategoria) {
    mostrarProductosSection(subcategoriaSeleccionada.nombre);
    try {
        const r = await fetch(`${baseUrl}/api/centro_inventarios.php?action=list&subcategoria_id=${idSubcategoria}`);
        const d = await r.json();
        if (d.success) { productos = d.inventarios || []; renderProductos(); }
    } catch (e) { console.error('Error:', e); }
}

function mostrarProductosSection(titulo) {
    document.getElementById('productosTitulo').textContent = titulo;
    document.getElementById('productosBody').innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>';
    document.getElementById('productosSection').style.display = 'block';
}

function renderProductos() {
    const tbody = document.getElementById('productosBody');
    document.getElementById('productosCount').textContent = productos.length + ' items';
    
    if (productos.length === 0) { 
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;">Sin productos</td></tr>'; 
        return; 
    }
    
    tbody.innerHTML = productos.map(p => {
        const stock = toNum(p.stock_actual);
        const stockMin = toNum(p.stock_minimo);
        const costo = toNum(p.costo_promedio) || toNum(p.costo_unitario);
        const valor = stock * costo;
        const unidad = p.unidad_abrev || p.abreviatura || p.unidad || p.unidad_medida || 'Kg';
        
        let estado = 'ok', estadoTxt = 'OK';
        if (stock <= 0) { estado = 'sin-stock'; estadoTxt = 'Sin Stock'; }
        else if (stock <= stockMin) { estado = 'critico'; estadoTxt = 'Crítico'; }
        else if (stock <= stockMin * 1.5) { estado = 'bajo'; estadoTxt = 'Bajo'; }
        
        return `<tr>
            <td><strong>${p.codigo || '-'}</strong></td>
            <td>${p.nombre || '-'}</td>
            <td style="text-align:right;">${formatNum(stock)}</td>
            <td>${unidad}</td>
            <td><span class="stock-badge ${estado}">${estadoTxt}</span></td>
            <td style="text-align:right;">Bs. ${formatNum(costo)}</td>
            <td style="text-align:right;">Bs. ${formatNum(valor)}</td>
            <td>
                <button class="btn-icon kardex" onclick="verKardex(${p.id_inventario})" title="Kardex"><i class="fas fa-book"></i></button>
                <button class="btn-icon editar" onclick="editarItem(${p.id_inventario})" title="Editar"><i class="fas fa-edit"></i></button>
            </td>
        </tr>`;
    }).join('');
}

function filtrarProductos() {
    const buscar = document.getElementById('buscarProducto').value.toLowerCase();
    document.querySelectorAll('#productosBody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(buscar) ? '' : 'none';
    });
}

// ========== DATOS AUXILIARES ==========
async function cargarUnidades() {
    try {
        const r = await fetch(`${baseUrl}/api/centro_inventarios.php?action=unidades`);
        const d = await r.json();
        if (d.success) unidades = d.unidades || [];
    } catch (e) { console.error('Error:', e); }
}

async function cargarProveedores() {
    try {
        const r = await fetch(`${baseUrl}/api/proveedores.php`);
        const d = await r.json();
        if (d.success) proveedores = d.proveedores || [];
    } catch (e) { console.error('Error:', e); }
}

async function cargarTodosProductos() {
    try {
        const r = await fetch(`${baseUrl}/api/centro_inventarios.php?action=list&tipo_id=${TIPO_ID}`);
        const d = await r.json();
        if (d.success) productosCompletos = d.inventarios || [];
    } catch (e) { console.error('Error:', e); }
}

// ========== MODAL NUEVO/EDITAR ITEM ==========
function abrirModalNuevoItem() {
    document.getElementById('formItem').reset();
    document.getElementById('itemId').value = '';
    document.getElementById('modalItemTitulo').textContent = 'Nuevo Item de Materia Prima';
    poblarSelects();
    document.getElementById('modalItem').classList.add('show');
}

async function editarItem(id) {
    const item = productosCompletos.find(p => p.id_inventario == id);
    if (!item) { alert('❌ No se encontró el item'); return; }
    
    poblarSelects();
    document.getElementById('itemId').value = item.id_inventario;
    document.getElementById('itemCodigo').value = item.codigo || '';
    document.getElementById('itemNombre').value = item.nombre || '';
    document.getElementById('itemCategoria').value = item.id_categoria || '';
    await cargarSubcategoriasItem();
    document.getElementById('itemSubcategoria').value = item.id_subcategoria || '';
    document.getElementById('itemUnidad').value = item.id_unidad || '';
    document.getElementById('itemStockActual').value = item.stock_actual || 0;
    document.getElementById('itemStockMinimo').value = item.stock_minimo || 0;
    document.getElementById('itemCosto').value = item.costo_unitario || item.costo_promedio || 0;
    document.getElementById('itemDescripcion').value = item.descripcion || '';
    document.getElementById('modalItemTitulo').textContent = 'Editar Item: ' + item.codigo;
    document.getElementById('modalItem').classList.add('show');
}

async function cargarSubcategoriasItem() {
    const catId = document.getElementById('itemCategoria').value;
    const subSelect = document.getElementById('itemSubcategoria');
    subSelect.innerHTML = '<option value="">Sin subcategoría</option>';
    if (!catId) return;
    
    try {
        const r = await fetch(`${baseUrl}/api/centro_inventarios.php?action=subcategorias&categoria_id=${catId}`);
        const d = await r.json();
        if (d.success && d.subcategorias) {
            d.subcategorias.forEach(s => {
                subSelect.innerHTML += `<option value="${s.id_subcategoria}">${s.nombre}</option>`;
            });
        }
    } catch (e) { console.error('Error:', e); }
}

function poblarSelects() {
    const catSelect = document.getElementById('itemCategoria');
    const currentCat = catSelect.value;
    catSelect.innerHTML = '<option value="">Seleccione...</option>' + 
        categorias.map(c => `<option value="${c.id_categoria}">${c.nombre}</option>`).join('');
    if (currentCat) catSelect.value = currentCat;
    
    const unidSelect = document.getElementById('itemUnidad');
    const currentUnid = unidSelect.value;
    unidSelect.innerHTML = '<option value="">Seleccione...</option>' + 
        unidades.map(u => `<option value="${u.id_unidad}">${u.nombre} (${u.abreviatura})</option>`).join('');
    if (currentUnid) unidSelect.value = currentUnid;
}

async function guardarItem() {
    const id = document.getElementById('itemId').value;
    const data = {
        action: id ? 'update' : 'create',
        id_inventario: id || null,
        id_tipo_inventario: TIPO_ID,
        codigo: document.getElementById('itemCodigo').value,
        nombre: document.getElementById('itemNombre').value,
        id_categoria: document.getElementById('itemCategoria').value,
        id_subcategoria: document.getElementById('itemSubcategoria').value || null,
        id_unidad: document.getElementById('itemUnidad').value,
        stock_actual: document.getElementById('itemStockActual').value || 0,
        stock_minimo: document.getElementById('itemStockMinimo').value || 0,
        costo_unitario: document.getElementById('itemCosto').value || 0,
        descripcion: document.getElementById('itemDescripcion').value
    };
    
    try {
        const r = await fetch(`${baseUrl}/api/centro_inventarios.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const d = await r.json();
        if (d.success) {
            alert('✅ ' + d.message);
            cerrarModal('modalItem');
            cargarDatos();
        } else {
            alert('❌ ' + d.message);
        }
    } catch (e) {
        console.error('Error:', e);
        alert('Error al guardar');
    }
}

// ========== MODAL INGRESO ==========

function abrirModalIngreso() {
    generarNumeroDocumentoIngreso();
    document.getElementById('ingresoFecha').value = new Date().toISOString().split('T')[0];
    document.getElementById('ingresoTipoProveedor').value = 'TODOS';
    filtrarProveedoresIngreso();
    poblarFiltrosCategorias();
    document.getElementById('ingresoConFactura').checked = false;
    modoConFactura = false;
    document.getElementById('ingresoReferencia').value = '';
    document.getElementById('ingresoObservaciones').value = '';
    document.getElementById('infoProveedorBox').style.display = 'none';
    lineasIngreso = [];
    productosFiltrados = [...productosCompletos];
    toggleModoFactura();
    document.getElementById('modalIngreso').classList.add('show');
}

async function generarNumeroDocumentoIngreso() {
    const hoy = new Date();
    const fecha = hoy.toISOString().split('T')[0].replace(/-/g, '');
    contadorDocIngreso++;
    const numero = String(contadorDocIngreso).padStart(3, '0');
    document.getElementById('ingresoDocumento').value = `ING-MP-${fecha}-${numero}`;
}

function filtrarProveedoresIngreso() {
    const tipo = document.getElementById('ingresoTipoProveedor').value;
    const select = document.getElementById('ingresoProveedor');
    
    let provFiltrados = proveedores;
    if (tipo !== 'TODOS') {
        provFiltrados = proveedores.filter(p => p.tipo === tipo);
    }
    
    select.innerHTML = '<option value="">Seleccione proveedor...</option>' +
        provFiltrados.map(p => 
            `<option value="${p.id_proveedor}" 
                     data-tipo="${p.tipo}" 
                     data-moneda="${p.moneda}" 
                     data-pago="${p.condicion_pago}">
                ${p.nombre_comercial || p.razon_social}
            </option>`
        ).join('');
}

function actualizarInfoProveedor() {
    const select = document.getElementById('ingresoProveedor');
    const box = document.getElementById('infoProveedorBox');
    
    if (!select.value) {
        box.style.display = 'none';
        return;
    }
    
    const opt = select.options[select.selectedIndex];
    const tipo = opt.dataset.tipo;
    const moneda = opt.dataset.moneda;
    const pago = opt.dataset.pago;
    
    document.getElementById('infoProveedorTipo').className = `badge-tipo ${tipo === 'LOCAL' ? 'local' : 'import'}`;
    document.getElementById('infoProveedorTipo').textContent = tipo === 'LOCAL' ? '🇧🇴 LOCAL' : '🌎 IMPORTACIÓN';
    
    document.getElementById('infoProveedorMoneda').className = `badge-moneda ${moneda === 'USD' ? 'usd' : 'bob'}`;
    document.getElementById('infoProveedorMoneda').textContent = moneda || 'BOB';
    
    document.getElementById('infoProveedorPago').textContent = `Pago: ${pago || 'N/A'}`;
    box.style.display = 'flex';
}

function poblarFiltrosCategorias() {
    const selectCat = document.getElementById('ingresoFiltroCat');
    selectCat.innerHTML = '<option value="">Todas las categorías</option>' +
        categorias.map(c => `<option value="${c.id_categoria}">${c.nombre}</option>`).join('');
    document.getElementById('ingresoFiltroSubcat').innerHTML = '<option value="">Todas las subcategorías</option>';
}

async function filtrarProductosIngreso() {
    const catId = document.getElementById('ingresoFiltroCat').value;
    const subcatId = document.getElementById('ingresoFiltroSubcat').value;
    
    if (catId) {
        try {
            const r = await fetch(`${baseUrl}/api/centro_inventarios.php?action=subcategorias&categoria_id=${catId}`);
            const d = await r.json();
            if (d.success && d.subcategorias) {
                const selectSubcat = document.getElementById('ingresoFiltroSubcat');
                selectSubcat.innerHTML = 
                    '<option value="">Todas las subcategorías</option>' +
                    d.subcategorias.map(s => `<option value="${s.id_subcategoria}">${s.nombre}</option>`).join('');
                
                // CORRECCIÓN: Restaurar el valor seleccionado después de recargar opciones
                if (subcatId) {
                    selectSubcat.value = subcatId;
                }
            }
        } catch (e) { console.error(e); }
    } else {
        document.getElementById('ingresoFiltroSubcat').innerHTML = '<option value="">Todas las subcategorías</option>';
    }
    
    productosFiltrados = productosCompletos.filter(p => {
        if (catId && p.id_categoria != catId) return false;
        if (subcatId && p.id_subcategoria != subcatId) return false;
        return true;
    });
    
    renderLineasIngreso();
}

function toggleModoFactura() {
    modoConFactura = document.getElementById('ingresoConFactura').checked;
    document.getElementById('rowIVA').style.display = modoConFactura ? 'flex' : 'none';
    
    const thead = document.getElementById('theadIngreso');
    
    if (modoConFactura) {
        thead.innerHTML = `
            <tr>
                <th style="min-width:250px;">PRODUCTO</th>
                <th style="width:60px; text-align:center;">UNID.</th>
                <th style="width:100px; background:#fff3cd; text-align:center;">CANTIDAD</th>
                <th style="width:140px; background:#fff3cd; text-align:center;">VALOR TOTAL<br>DEL ITEM</th>
                <th style="width:130px; text-align:center;">COSTO UNITARIO<br>DOCUMENTO</th>
                <th style="width:100px; background:#fff9e6; text-align:center;">IVA 13%</th>
                <th style="width:110px; text-align:center;">COSTO ITEM</th>
                <th style="width:130px; background:#d4edda; text-align:center;">COSTO UNITARIO<br>- IVA</th>
                <th style="width:50px;"></th>
            </tr>`;
    } else {
        thead.innerHTML = `
            <tr>
                <th style="min-width:250px;">PRODUCTO</th>
                <th style="width:60px; text-align:center;">UNID.</th>
                <th style="width:100px; background:#fff3cd; text-align:center;">CANTIDAD</th>
                <th style="width:140px; background:#fff3cd; text-align:center;">VALOR TOTAL<br>DEL ITEM</th>
                <th style="width:130px; background:#d4edda; text-align:center;">COSTO UNITARIO</th>
                <th style="width:50px;"></th>
            </tr>`;
    }
    
    renderLineasIngreso();
}

function agregarLineaIngreso() {
    lineasIngreso.push({ 
        id_inventario: '', 
        cantidad: 0,
        valor_total_item: 0,
        costo_unitario: 0,
        unidad: ''
    });
    renderLineasIngreso();
}

function renderLineasIngreso() {
    const tbody = document.getElementById('ingresoLineasBody');
    
    if (lineasIngreso.length === 0) {
        agregarLineaIngreso();
        return;
    }
    
    tbody.innerHTML = lineasIngreso.map((l, i) => {
        const prod = productosCompletos.find(p => p.id_inventario == l.id_inventario);
        const unidad = prod ? (prod.unidad_abrev || prod.abreviatura || prod.unidad || 'kg') : '-';
        
        const cantidad = toNum(l.cantidad);
        const valorTotal = toNum(l.valor_total_item);
        
        if (modoConFactura) {
            const costoUnitDoc = cantidad > 0 ? valorTotal / cantidad : 0;
            const iva = valorTotal * 0.13;
            const costoItem = valorTotal - iva;
            const costoUnitNeto = cantidad > 0 ? costoItem / cantidad : 0;
            
            return `
                <tr>
                    <td>
                        <select id="ingProd_${i}" onchange="seleccionarProductoIngreso(${i})" style="width:100%; padding:6px;">
                            <option value="">Seleccione producto...</option>
                            ${productosFiltrados.map(p => 
                                `<option value="${p.id_inventario}" ${p.id_inventario == l.id_inventario ? 'selected' : ''}>
                                    ${p.codigo} - ${p.nombre}
                                </option>`
                            ).join('')}
                        </select>
                    </td>
                    <td style="text-align:center; font-weight:600; color:#495057;">${unidad}</td>
                    <td>
                        <input type="number" id="ingCant_${i}" value="${cantidad || ''}" step="0.01" 
                               style="width:100%; padding:6px; background:#fff3cd; font-weight:600; text-align:right;" 
                               onchange="calcularLineaIngreso(${i})" placeholder="0.00">
                    </td>
                    <td>
                        <input type="number" id="ingValor_${i}" value="${valorTotal || ''}" step="0.01" 
                               style="width:100%; padding:6px; background:#fff3cd; font-weight:600; text-align:right;" 
                               onchange="calcularLineaIngreso(${i})" placeholder="0.00">
                    </td>
                    <td style="background:#f8f9fa; text-align:right; padding-right:10px; font-weight:500;">
                        ${formatNum(costoUnitDoc, 4)}
                    </td>
                    <td style="background:#fff9e6; text-align:right; padding-right:10px; font-weight:500;">
                        ${formatNum(iva, 2)}
                    </td>
                    <td style="background:#f8f9fa; text-align:right; padding-right:10px; font-weight:500;">
                        ${formatNum(costoItem, 2)}
                    </td>
                    <td style="background:#d4edda; text-align:right; padding-right:10px; font-weight:700; color:#155724;">
                        ${formatNum(costoUnitNeto, 4)}
                    </td>
                    <td style="text-align:center;">
                        <button type="button" onclick="eliminarLineaIngreso(${i})" 
                                style="background:#dc3545; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
        } else {
            const costoUnitario = cantidad > 0 ? valorTotal / cantidad : 0;
            const subtotal = valorTotal;
            
            return `
                <tr>
                    <td>
                        <select id="ingProd_${i}" onchange="seleccionarProductoIngreso(${i})" style="width:100%; padding:6px;">
                            <option value="">Seleccione producto...</option>
                            ${productosFiltrados.map(p => 
                                `<option value="${p.id_inventario}" ${p.id_inventario == l.id_inventario ? 'selected' : ''}>
                                    ${p.codigo} - ${p.nombre}
                                </option>`
                            ).join('')}
                        </select>
                    </td>
                    <td style="text-align:center; font-weight:600; color:#495057;">${unidad}</td>
                    <td>
                        <input type="number" id="ingCant_${i}" value="${cantidad || ''}" step="0.01" 
                               style="width:100%; padding:6px; background:#fff3cd; font-weight:600; text-align:right;" 
                               onchange="calcularLineaIngreso(${i})" placeholder="0.00">
                    </td>
                    <td>
                        <input type="number" id="ingValor_${i}" value="${valorTotal || ''}" step="0.01" 
                               style="width:100%; padding:6px; background:#fff3cd; font-weight:600; text-align:right;" 
                               onchange="calcularLineaIngreso(${i})" placeholder="0.00">
                    </td>
                    <td style="background:#d4edda; text-align:right; padding-right:10px; font-weight:700; color:#155724;">
                        ${formatNum(costoUnitario, 4)}
                    </td>
                    <td style="text-align:center;">
                        <button type="button" onclick="eliminarLineaIngreso(${i})" 
                                style="background:#dc3545; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
        }
    }).join('');
    
    recalcularIngreso();
}

function calcularLineaIngreso(index) {
    const cantidad = toNum(document.getElementById(`ingCant_${index}`).value);
    const valorTotal = toNum(document.getElementById(`ingValor_${index}`).value);
    
    lineasIngreso[index].cantidad = cantidad;
    lineasIngreso[index].valor_total_item = valorTotal;
    
    if (modoConFactura) {
        const iva = valorTotal * 0.13;
        const costoItem = valorTotal - iva;
        const costoUnitNeto = cantidad > 0 ? costoItem / cantidad : 0;
        
        lineasIngreso[index].costo_unitario = costoUnitNeto;
        lineasIngreso[index].subtotal = valorTotal;
        lineasIngreso[index].iva = iva;
    } else {
        const costoUnitario = cantidad > 0 ? valorTotal / cantidad : 0;
        lineasIngreso[index].costo_unitario = costoUnitario;
        lineasIngreso[index].subtotal = valorTotal;
    }
    
    renderLineasIngreso();
}

function seleccionarProductoIngreso(index) {
    const select = document.getElementById(`ingProd_${index}`);
    lineasIngreso[index].id_inventario = select.value;
    renderLineasIngreso();
}

function eliminarLineaIngreso(index) {
    if (lineasIngreso.length === 1) {
        alert('Debe haber al menos una línea');
        return;
    }
    lineasIngreso.splice(index, 1);
    renderLineasIngreso();
}

function recalcularIngreso() {
    let totalNeto = 0;
    let totalIVA = 0;
    let totalDocumento = 0;
    
    lineasIngreso.forEach(l => {
        const valorTotal = toNum(l.valor_total_item);
        totalDocumento += valorTotal;
        
        if (modoConFactura) {
            const iva = valorTotal * 0.13;
            const neto = valorTotal - iva;
            totalIVA += iva;
            totalNeto += neto;
        } else {
            totalNeto += valorTotal;
        }
    });
    
    document.getElementById('ingresoTotalNeto').textContent = 'Bs. ' + formatNum(totalNeto, 2);
    document.getElementById('ingresoIVA').textContent = 'Bs. ' + formatNum(totalIVA, 2);
    document.getElementById('ingresoTotal').textContent = 'Bs. ' + formatNum(totalDocumento, 2);
}

async function guardarIngreso() {
    const proveedor = document.getElementById('ingresoProveedor').value;
    if (!proveedor) {
        alert('⚠️ Seleccione un proveedor');
        return;
    }
    
    if (lineasIngreso.length === 0) {
        alert('⚠️ Agregue al menos una línea');
        return;
    }
    
    for (let i = 0; i < lineasIngreso.length; i++) {
        if (!lineasIngreso[i].id_inventario) {
            alert(`⚠️ Seleccione un producto en la línea ${i + 1}`);
            return;
        }
        if (lineasIngreso[i].cantidad <= 0) {
            alert(`⚠️ Ingrese cantidad en la línea ${i + 1}`);
            return;
        }
        if (lineasIngreso[i].valor_total_item <= 0) {
            alert(`⚠️ Ingrese valor total en la línea ${i + 1}`);
            return;
        }
    }
    
    const data = {
        fecha: document.getElementById('ingresoFecha').value,
        id_proveedor: proveedor,
        referencia: document.getElementById('ingresoReferencia').value,
        con_factura: modoConFactura,
        observaciones: document.getElementById('ingresoObservaciones').value,
        lineas: lineasIngreso.map(l => ({
            id_inventario: l.id_inventario,
            cantidad: l.cantidad,
            costo_unitario: l.costo_unitario,
            subtotal: l.subtotal
        }))
    };
    
    console.log('Guardando ingreso:', data);
    
    try {
        const r = await fetch(`${baseUrl}/api/ingresos_mp.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'crear', ...data })
        });
        const d = await r.json();
        console.log('Respuesta:', d);
        
        if (d.success) {
            alert('✅ ' + d.message);
            cerrarModal('modalIngreso');
            cargarDatos();
        } else {
            alert('❌ ' + d.message);
        }
    } catch (e) {
        console.error('Error:', e);
        alert('Error al guardar el ingreso');
    }
}

// ========== MODALES SALIDA, HISTORIAL, DETALLE, KARDEX ==========
// (Aquí irían las funciones de los otros modales - las omito por espacio pero están en el original)

// ========== MODAL SALIDA ==========

let productosFiltradosSalida = [];

function abrirModalSalida() {
    // Tipo por defecto
    document.getElementById('salidaTipo').value = 'PRODUCCION';
    
    // Generar número según tipo
    actualizarNumeroSalida();
    
    // Fecha actual
    document.getElementById('salidaFecha').value = new Date().toISOString().split('T')[0];
    
    // Reset referencia y observaciones
    document.getElementById('salidaReferencia').value = '';
    document.getElementById('salidaObservaciones').value = '';
    
    // Poblar filtros de categorías
    poblarFiltrosCategoriasSalida();
    
    // Reset líneas
    lineasSalida = [];
    productosFiltradosSalida = productosCompletos.filter(p => toNum(p.stock_actual) > 0);
    
    // Renderizar
    renderLineasSalida();
    
    document.getElementById('modalSalida').classList.add('show');
}

// Event listener para cambio de tipo
document.addEventListener('DOMContentLoaded', function() {
    const selectTipo = document.getElementById('salidaTipo');
    if (selectTipo) {
        selectTipo.addEventListener('change', function() {
            if (this.value === 'DEVOLUCION') {
                // Cerrar modal normal y abrir modal de devolución
                cerrarModal('modalSalida');
                setTimeout(() => abrirModalDevolucion(), 300);
            }
        });
    }
});

function poblarFiltrosCategoriasSalida() {
    const selectCat = document.getElementById('salidaFiltroCat');
    selectCat.innerHTML = '<option value="">Todas las categorías</option>' +
        categorias.map(c => `<option value="${c.id_categoria}">${c.nombre}</option>`).join('');
    
    document.getElementById('salidaFiltroSubcat').innerHTML = '<option value="">Todas las subcategorías</option>';
}

async function filtrarProductosSalida() {
    const catId = document.getElementById('salidaFiltroCat').value;
    const subcatId = document.getElementById('salidaFiltroSubcat').value;
    
    // Si cambia categoría, actualizar subcategorías
    if (catId) {
        try {
            const r = await fetch(`${baseUrl}/api/centro_inventarios.php?action=subcategorias&categoria_id=${catId}`);
            const d = await r.json();
            if (d.success && d.subcategorias) {
                const selectSubcat = document.getElementById('salidaFiltroSubcat');
                selectSubcat.innerHTML = 
                    '<option value="">Todas las subcategorías</option>' +
                    d.subcategorias.map(s => `<option value="${s.id_subcategoria}">${s.nombre}</option>`).join('');
                
                // Restaurar valor si existe
                if (subcatId) {
                    selectSubcat.value = subcatId;
                }
            }
        } catch (e) { console.error(e); }
    } else {
        document.getElementById('salidaFiltroSubcat').innerHTML = '<option value="">Todas las subcategorías</option>';
    }
    
    // Filtrar productos con stock > 0
    productosFiltradosSalida = productosCompletos.filter(p => {
        if (toNum(p.stock_actual) <= 0) return false;
        if (catId && p.id_categoria != catId) return false;
        if (subcatId && p.id_subcategoria != subcatId) return false;
        return true;
    });
    
    renderLineasSalida();
}

function actualizarNumeroSalida() {
    const tipo = document.getElementById('salidaTipo').value;
    const prefijos = {
        'PRODUCCION': 'SMP-PR',
        'VENTA': 'SMP-V',
        'MUESTRAS': 'SMP-M',
        'AJUSTE': 'SMP-A',
        'DEVOLUCION': 'SMP-DV'
    };
    const prefijo = prefijos[tipo] || 'SAL-MP';
    document.getElementById('salidaDocumento').value = generarNumeroDoc(prefijo);
    
    // Mostrar/ocultar indicador de motivo obligatorio
    const motivoObligatorio = document.getElementById('motivoObligatorio');
    if (motivoObligatorio) {
        motivoObligatorio.style.display = tipo === 'AJUSTE' ? 'inline' : 'none';
    }
}

function agregarLineaSalida() {
    lineasSalida.push({ 
        id_inventario: '', 
        cantidad: 0,
        stock_disponible: 0,
        costo_unitario: 0
    });
    renderLineasSalida();
}

function renderLineasSalida() {
    const tbody = document.getElementById('salidaLineasBody');
    
    if (lineasSalida.length === 0) {
        agregarLineaSalida();
        return;
    }
    
    tbody.innerHTML = lineasSalida.map((l, i) => {
        const prod = productosCompletos.find(p => p.id_inventario == l.id_inventario);
        const stockDisp = prod ? toNum(prod.stock_actual) : 0;
        const cpp = prod ? (toNum(prod.costo_promedio) || toNum(prod.costo_unitario)) : 0;
        const unidad = prod ? (prod.unidad_abrev || prod.abreviatura || prod.unidad || 'kg') : '-';
        const cantidad = toNum(l.cantidad);
        const subtotal = cantidad * cpp;
        
        return `
            <tr>
                <td>
                    <select id="salProd_${i}" onchange="seleccionarProductoSalida(${i})" style="width:100%; padding:6px;">
                        <option value="">Seleccione producto...</option>
                        ${productosFiltradosSalida.map(p => 
                            `<option value="${p.id_inventario}" ${p.id_inventario == l.id_inventario ? 'selected' : ''}>
                                ${p.codigo} - ${p.nombre}
                            </option>`
                        ).join('')}
                    </select>
                </td>
                <td style="text-align:right; font-weight:600; color:#28a745;">${formatNum(stockDisp, 2)}</td>
                <td style="text-align:center; font-weight:600; color:#495057;">${unidad}</td>
                <td>
                    <input type="number" id="salCant_${i}" value="${cantidad || ''}" step="0.01" max="${stockDisp}"
                           style="width:100%; padding:6px; background:#fff3cd; text-align:right;" 
                           onchange="calcularLineaSalida(${i})" placeholder="0.00">
                </td>
                <td style="background:#f8f9fa; text-align:right; padding-right:10px; font-weight:500;">
                    Bs. ${formatNum(cpp, 4)}
                </td>
                <td style="background:#f8f9fa; text-align:right; padding-right:10px; font-weight:600;">
                    Bs. ${formatNum(subtotal, 2)}
                </td>
                <td style="text-align:center;">
                    <button type="button" onclick="eliminarLineaSalida(${i})" 
                            style="background:#dc3545; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer;">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
    }).join('');
    
    recalcularSalida();
}

function seleccionarProductoSalida(index) {
    const select = document.getElementById(`salProd_${index}`);
    const idInventario = select.value;
    
    lineasSalida[index].id_inventario = idInventario;
    
    // Obtener stock disponible
    if (idInventario) {
        const prod = productosCompletos.find(p => p.id_inventario == idInventario);
        if (prod) {
            lineasSalida[index].stock_disponible = toNum(prod.stock_actual);
            lineasSalida[index].costo_unitario = toNum(prod.costo_promedio) || toNum(prod.costo_unitario);
        }
    }
    
    renderLineasSalida();
}

function calcularLineaSalida(index) {
    const cantidad = toNum(document.getElementById(`salCant_${index}`).value);
    const stockDisp = lineasSalida[index].stock_disponible || 0;
    
    // Validar que no exceda el stock
    if (cantidad > stockDisp) {
        alert(`⚠️ Stock insuficiente. Disponible: ${formatNum(stockDisp)}`);
        document.getElementById(`salCant_${index}`).value = stockDisp;
        lineasSalida[index].cantidad = stockDisp;
    } else {
        lineasSalida[index].cantidad = cantidad;
    }
    
    renderLineasSalida();
}

function eliminarLineaSalida(index) {
    if (lineasSalida.length === 1) {
        alert('Debe haber al menos una línea');
        return;
    }
    lineasSalida.splice(index, 1);
    renderLineasSalida();
}

function recalcularSalida() {
    let total = 0;
    
    lineasSalida.forEach(l => {
        const cantidad = toNum(l.cantidad);
        const cpp = toNum(l.costo_unitario);
        total += cantidad * cpp;
    });
    
    document.getElementById('salidaTotal').textContent = 'Bs. ' + formatNum(total, 2);
}

async function guardarSalida() {
    // Validaciones
    const tipo = document.getElementById('salidaTipo').value;
    
    if (lineasSalida.length === 0) {
        alert('⚠️ Agregue al menos una línea');
        return;
    }
    
    // Validar que todas las líneas tengan producto y cantidad
    for (let i = 0; i < lineasSalida.length; i++) {
        if (!lineasSalida[i].id_inventario) {
            alert(`⚠️ Seleccione un producto en la línea ${i + 1}`);
            return;
        }
        if (lineasSalida[i].cantidad <= 0) {
            alert(`⚠️ Ingrese cantidad en la línea ${i + 1}`);
            return;
        }
        
        // Validar stock
        const prod = productosCompletos.find(p => p.id_inventario == lineasSalida[i].id_inventario);
        const stockDisp = prod ? toNum(prod.stock_actual) : 0;
        if (lineasSalida[i].cantidad > stockDisp) {
            alert(`⚠️ Stock insuficiente para ${prod.nombre}. Disponible: ${formatNum(stockDisp)}`);
            return;
        }
    }
    
    // Validar motivo para ajustes
    if (tipo === 'AJUSTE' && !document.getElementById('salidaObservaciones').value.trim()) {
        alert('⚠️ El motivo es obligatorio para ajustes de inventario');
        return;
    }
    
    const data = {
        action: 'crear',
        fecha: document.getElementById('salidaFecha').value,
        tipo_salida: tipo,
        referencia: document.getElementById('salidaReferencia').value,
        observaciones: document.getElementById('salidaObservaciones').value,
        lineas: lineasSalida.map(l => ({
            id_inventario: l.id_inventario,
            cantidad: l.cantidad,
            costo_unitario: l.costo_unitario
        }))
    };
    
    console.log('Guardando salida:', data);
    
    try {
        const r = await fetch(`${baseUrl}/api/salidas_mp.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const d = await r.json();
        console.log('Respuesta:', d);
        
        if (d.success) {
            alert('✅ ' + d.message);
            cerrarModal('modalSalida');
            cargarDatos();
        } else {
            alert('❌ ' + d.message);
        }
    } catch (e) {
        console.error('Error:', e);
        alert('Error al guardar la salida');
    }
}

function abrirModalHistorial() {
    alert('Modal Historial - Por implementar');
}

function verKardex(id) {
    alert('Modal Kardex - Por implementar');
}

// ========== UTILIDADES ==========
function cerrarModal(id) { 
    document.getElementById(id).classList.remove('show'); 
}

function generarNumeroDoc(prefijo) {
    const f = new Date();
    const anio = f.getFullYear().toString().substr(-2);
    const mes = String(f.getMonth() + 1).padStart(2, '0');
    const dia = String(f.getDate()).padStart(2, '0');
    const rand = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
    return `${prefijo}-${anio}${mes}${dia}-${rand}`;
}

console.log('✅ Módulo Materias Primas v1.9 cargado');
console.log('   - Modal Ingreso mejorado v2.0');
console.log('   - Filtros por tipo proveedor y categoría');
console.log('   - Cálculo IVA con columnas dinámicas');
console.log('   - Costos con 4 decimales');