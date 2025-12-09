/**
 * JavaScript para módulo Materias Primas
 * Sistema MES Hermen Ltda. v1.0
 */

const baseUrl = window.location.origin + '/mes_hermen';
const TIPO_ID = document.querySelector('.mp-title-icon')?.dataset?.tipoId || 1;

let categorias = [], subcategorias = [], productos = [], productosCompletos = [];
let unidades = [], proveedores = [];
let categoriaSeleccionada = null, subcategoriaSeleccionada = null;
let lineasIngreso = [], lineasSalida = [];
let documentoActual = null;

// ========== INICIALIZACIÓN ==========
document.addEventListener('DOMContentLoaded', cargarDatos);

async function cargarDatos() {
    await Promise.all([cargarKPIs(), cargarCategorias(), cargarUnidades(), cargarProveedores(), cargarTodosProductos()]);
}

// ========== KPIs ==========
async function cargarKPIs() {
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=resumen&tipo_id=${TIPO_ID}`);
        const d = await r.json();
        console.log('Respuesta KPIs:', d);
        if (d.success) {
            // La API devuelve: { success, resumen: Array, totales: {items, valor, alertas} }
            const totales = d.totales || {};
            const numCategorias = d.resumen ? d.resumen.length : 0;
            
            // Redondear valor para evitar errores de precisión flotante
            const valorRedondeado = Math.round(parseFloat(totales.valor || 0) * 100) / 100;
            
            document.getElementById('kpiItems').textContent = totales.items || 0;
            document.getElementById('kpiValor').textContent = 'Bs. ' + formatNumber(valorRedondeado, 2);
            document.getElementById('kpiAlertas').textContent = totales.alertas || 0;
            document.getElementById('kpiCategorias').textContent = numCategorias;
        }
    } catch (e) { console.error('Error KPIs:', e); }
}

// ========== CATEGORÍAS ==========
async function cargarCategorias() {
    try {
        // Primero obtener las categorías del tipo
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=categorias&tipo_id=${TIPO_ID}`);
        const d = await r.json();
        console.log('Respuesta Categorías:', d);
        
        if (d.success && d.categorias) {
            // Guardar categorías base
            categorias = d.categorias.map(cat => ({
                id_categoria: cat.id_categoria,
                nombre: cat.nombre,
                codigo: cat.codigo || '',
                total_items: 0,
                alertas: 0,
                valor_total: 0
            }));
            
            // Ahora cargar productos para calcular totales por categoría
            const rProd = await fetch(`${baseUrl}/api/inventarios.php?action=list&tipo_id=${TIPO_ID}`);
            const dProd = await rProd.json();
            
            if (dProd.success && dProd.inventarios) {
                // Calcular totales por categoría
                dProd.inventarios.forEach(prod => {
                    const cat = categorias.find(c => c.id_categoria == prod.id_categoria);
                    if (cat) {
                        cat.total_items++;
                        const stock = parseFloat(prod.stock_actual) || 0;
                        const stockMin = parseFloat(prod.stock_minimo) || 0;
                        const costo = parseFloat(prod.costo_promedio || prod.costo_unitario) || 0;
                        cat.valor_total += stock * costo;
                        if (stock > 0 && stock <= stockMin) {
                            cat.alertas++;
                        }
                    }
                });
                
                // Redondear valores para evitar errores de precisión flotante
                categorias.forEach(cat => {
                    cat.valor_total = Math.round(cat.valor_total * 100) / 100;
                });
            }
            
            renderCategorias();
        }
    } catch (e) { console.error('Error categorías:', e); }
}

function renderCategorias() {
    const grid = document.getElementById('categoriasGrid');
    if (categorias.length === 0) { grid.innerHTML = '<p style="padding:20px;text-align:center;">No hay categorías</p>'; return; }
    
    grid.innerHTML = categorias.map(c => `
        <div class="categoria-card ${categoriaSeleccionada?.id_categoria == c.id_categoria ? 'active' : ''}" onclick="seleccionarCategoria(${c.id_categoria})">
            <div class="categoria-header">
                <div class="categoria-nombre">${c.nombre}</div>
                <span class="categoria-badge">${c.total_items || 0}</span>
            </div>
            <div class="categoria-stats">
                <div><div class="cat-stat-value">${c.total_items || 0}</div><div class="cat-stat-label">Items</div></div>
                <div><div class="cat-stat-value alerta">${c.alertas || 0}</div><div class="cat-stat-label">Alertas</div></div>
                <div><div class="cat-stat-value">Bs.${formatNumber(c.valor_total || 0, 2)}</div><div class="cat-stat-label">Valor</div></div>
            </div>
        </div>
    `).join('');
}

async function seleccionarCategoria(idCategoria) {
    categoriaSeleccionada = categorias.find(c => c.id_categoria == idCategoria);
    subcategoriaSeleccionada = null;
    renderCategorias();
    
    console.log('Categoría seleccionada:', categoriaSeleccionada);
    
    try {
        // Intentar cargar subcategorías
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=subcategorias&categoria_id=${idCategoria}`);
        const d = await r.json();
        console.log('Respuesta subcategorías:', d);
        
        if (d.success && d.subcategorias && d.subcategorias.length > 0) {
            subcategorias = d.subcategorias.map(s => ({
                ...s,
                total_items: 0,
                valor_total: 0
            }));
            
            // Calcular totales por subcategoría usando productosCompletos
            productosCompletos.forEach(prod => {
                if (prod.id_categoria == idCategoria && prod.id_subcategoria) {
                    const sub = subcategorias.find(s => s.id_subcategoria == prod.id_subcategoria);
                    if (sub) {
                        sub.total_items++;
                        const stock = parseFloat(prod.stock_actual) || 0;
                        const costo = parseFloat(prod.costo_promedio || prod.costo_unitario) || 0;
                        sub.valor_total += stock * costo;
                    }
                }
            });
            
            // Redondear valores
            subcategorias.forEach(s => {
                s.valor_total = Math.round(s.valor_total * 100) / 100;
            });
            
            mostrarSubcategorias();
        } else {
            // No hay subcategorías, cargar productos directamente
            document.getElementById('subcategoriasSection').style.display = 'none';
            cargarProductosCategoria(idCategoria);
        }
    } catch (e) { 
        console.error('Error subcategorías:', e);
        // En caso de error, cargar productos directamente
        document.getElementById('subcategoriasSection').style.display = 'none';
        cargarProductosCategoria(idCategoria);
    }
}

function mostrarSubcategorias() {
    document.getElementById('subcategoriaTitulo').textContent = categoriaSeleccionada.nombre;
    document.getElementById('subcategoriasGrid').innerHTML = subcategorias.map(s => `
        <div class="categoria-card ${subcategoriaSeleccionada?.id_subcategoria == s.id_subcategoria ? 'active' : ''}" onclick="seleccionarSubcategoria(${s.id_subcategoria})">
            <div class="categoria-header">
                <div class="categoria-nombre">${s.nombre}</div>
                <span class="categoria-badge">${s.total_items || 0}</span>
            </div>
            <div class="categoria-stats">
                <div><div class="cat-stat-value">${s.total_items || 0}</div><div class="cat-stat-label">Items</div></div>
                <div><div class="cat-stat-value alerta">0</div><div class="cat-stat-label">Alertas</div></div>
                <div><div class="cat-stat-value">Bs.${formatNumber(s.valor_total || 0, 2)}</div><div class="cat-stat-label">Valor</div></div>
            </div>
        </div>
    `).join('');
    document.getElementById('subcategoriasSection').style.display = 'block';
    document.getElementById('productosSection').style.display = 'none';
}

async function seleccionarSubcategoria(idSubcategoria) {
    subcategoriaSeleccionada = subcategorias.find(s => s.id_subcategoria == idSubcategoria);
    mostrarSubcategorias();
    cargarProductosSubcategoria(idSubcategoria);
}

// ========== PRODUCTOS ==========
async function cargarProductosCategoria(idCategoria) {
    mostrarProductosSection(categoriaSeleccionada.nombre);
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=list&tipo_id=${TIPO_ID}&categoria_id=${idCategoria}`);
        const d = await r.json();
        if (d.success) { productos = d.inventarios || []; renderProductos(); }
    } catch (e) { console.error('Error:', e); }
}

async function cargarProductosSubcategoria(idSubcategoria) {
    mostrarProductosSection(subcategoriaSeleccionada.nombre);
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=list&subcategoria_id=${idSubcategoria}`);
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
    
    if (productos.length === 0) { tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;">Sin productos</td></tr>'; return; }
    
    tbody.innerHTML = productos.map(p => {
        const stock = parseFloat(p.stock_actual) || 0;
        const stockMin = parseFloat(p.stock_minimo) || 0;
        const costo = parseFloat(p.costo_promedio || p.costo_unitario) || 0;
        const valor = Math.round(stock * costo * 100) / 100; // Redondear para evitar errores de precisión
        let estado = 'ok', estadoTxt = 'OK';
        if (stock <= 0) { estado = 'sin-stock'; estadoTxt = 'Sin Stock'; }
        else if (stock <= stockMin) { estado = 'critico'; estadoTxt = 'Crítico'; }
        else if (stock <= stockMin * 1.5) { estado = 'bajo'; estadoTxt = 'Bajo'; }
        
        return `<tr>
            <td><strong>${p.codigo}</strong></td>
            <td>${p.nombre}</td>
            <td style="text-align:right;">${formatNumber(stock, 2)}</td>
            <td>${p.unidad_abrev || '-'}</td>
            <td><span class="stock-badge ${estado}">${estadoTxt}</span></td>
            <td style="text-align:right;">Bs. ${formatNumber(costo, 2)}</td>
            <td style="text-align:right;">Bs. ${formatNumber(valor, 2)}</td>
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
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=unidades`);
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
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=list&tipo_id=${TIPO_ID}`);
        const d = await r.json();
        if (d.success) productosCompletos = d.inventarios || [];
    } catch (e) { console.error('Error:', e); }
}

// ========== MODAL NUEVO/EDITAR ITEM ==========
function abrirModalNuevoItem() {
    document.getElementById('modalItemTitulo').textContent = 'Nuevo Item';
    document.getElementById('formItem').reset();
    document.getElementById('itemId').value = '';
    cargarSelectCategorias();
    cargarSelectUnidades();
    document.getElementById('modalItem').classList.add('show');
}

function cargarSelectCategorias() {
    document.getElementById('itemCategoria').innerHTML = '<option value="">Seleccione...</option>' + categorias.map(c => `<option value="${c.id_categoria}">${c.nombre}</option>`).join('');
}

async function cargarSubcategoriasItem() {
    const catId = document.getElementById('itemCategoria').value;
    const select = document.getElementById('itemSubcategoria');
    if (!catId) { select.innerHTML = '<option value="">Sin subcategoría</option>'; return; }
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=subcategorias&categoria_id=${catId}`);
        const d = await r.json();
        select.innerHTML = '<option value="">Sin subcategoría</option>' + (d.subcategorias || []).map(s => `<option value="${s.id_subcategoria}">${s.nombre}</option>`).join('');
    } catch (e) { console.error('Error:', e); }
}

function cargarSelectUnidades() {
    document.getElementById('itemUnidad').innerHTML = '<option value="">Seleccione...</option>' + unidades.map(u => `<option value="${u.id_unidad}">${u.nombre} (${u.abreviatura})</option>`).join('');
}

async function editarItem(id) {
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=detalle&id=${id}`);
        const d = await r.json();
        if (d.success && d.item) {
            const item = d.item;
            document.getElementById('modalItemTitulo').textContent = 'Editar Item';
            document.getElementById('itemId').value = item.id_inventario;
            document.getElementById('itemCodigo').value = item.codigo;
            document.getElementById('itemNombre').value = item.nombre;
            document.getElementById('itemStockActual').value = item.stock_actual;
            document.getElementById('itemStockMinimo').value = item.stock_minimo;
            document.getElementById('itemCosto').value = item.costo_unitario || 0;
            document.getElementById('itemDescripcion').value = item.descripcion || '';
            cargarSelectCategorias();
            cargarSelectUnidades();
            setTimeout(() => {
                document.getElementById('itemCategoria').value = item.id_categoria;
                document.getElementById('itemUnidad').value = item.id_unidad;
                cargarSubcategoriasItem().then(() => {
                    if (item.id_subcategoria) document.getElementById('itemSubcategoria').value = item.id_subcategoria;
                });
            }, 100);
            document.getElementById('modalItem').classList.add('show');
        }
    } catch (e) { alert('Error al cargar'); }
}

async function guardarItem() {
    const payload = {
        id_inventario: document.getElementById('itemId').value || null,
        codigo: document.getElementById('itemCodigo').value.trim(),
        nombre: document.getElementById('itemNombre').value.trim(),
        id_tipo_inventario: TIPO_ID,
        id_categoria: document.getElementById('itemCategoria').value,
        id_subcategoria: document.getElementById('itemSubcategoria').value || null,
        id_unidad: document.getElementById('itemUnidad').value,
        stock_actual: parseFloat(document.getElementById('itemStockActual').value) || 0,
        stock_minimo: parseFloat(document.getElementById('itemStockMinimo').value) || 0,
        costo_unitario: parseFloat(document.getElementById('itemCosto').value) || 0,
        descripcion: document.getElementById('itemDescripcion').value.trim()
    };
    
    if (!payload.codigo || !payload.nombre || !payload.id_categoria || !payload.id_unidad) {
        alert('Complete los campos requeridos'); return;
    }
    
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
        });
        const d = await r.json();
        if (d.success) {
            alert('✅ ' + d.message);
            cerrarModal('modalItem');
            cargarDatos();
            if (categoriaSeleccionada) seleccionarCategoria(categoriaSeleccionada.id_categoria);
        } else alert('❌ ' + d.message);
    } catch (e) { alert('Error de conexión'); }
}

// ========== MODAL INGRESO ==========
function abrirModalIngreso() {
    document.getElementById('ingresoDocumento').value = generarNumeroDoc('ING');
    document.getElementById('ingresoFecha').value = new Date().toISOString().split('T')[0];
    document.getElementById('ingresoConFactura').checked = false;
    document.getElementById('ingresoObservaciones').value = '';
    document.getElementById('ingresoReferencia').value = '';
    document.getElementById('ingresoProveedor').innerHTML = '<option value="">Seleccione...</option>' + proveedores.map(p => `<option value="${p.id_proveedor}">${p.razon_social}</option>`).join('');
    lineasIngreso = [];
    agregarLineaIngreso();
    document.getElementById('modalIngreso').classList.add('show');
}

function agregarLineaIngreso() {
    lineasIngreso.push({ id: Date.now(), id_inventario: '', cantidad: 0, costo_bruto: 0, costo_neto: 0 });
    renderLineasIngreso();
}

function renderLineasIngreso() {
    const conFactura = document.getElementById('ingresoConFactura').checked;
    document.getElementById('ingresoLineasBody').innerHTML = lineasIngreso.map(l => `
        <tr>
            <td><select onchange="actualizarLineaIngreso(${l.id},'id_inventario',this.value)">
                <option value="">Seleccione...</option>
                ${productosCompletos.map(p => `<option value="${p.id_inventario}" ${l.id_inventario==p.id_inventario?'selected':''}>${p.codigo} - ${p.nombre}</option>`).join('')}
            </select></td>
            <td><input type="number" step="0.01" value="${l.cantidad}" onchange="actualizarLineaIngreso(${l.id},'cantidad',this.value)"></td>
            <td><input type="number" step="0.01" value="${l.costo_bruto}" onchange="actualizarLineaIngreso(${l.id},'costo_bruto',this.value)"></td>
            <td style="text-align:right;">${conFactura ? 'Bs.'+formatNumber(l.costo_neto,2) : '-'}</td>
            <td style="text-align:right;">Bs.${formatNumber(l.cantidad*(conFactura?l.costo_neto:l.costo_bruto),2)}</td>
            <td><button style="background:#dc3545;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;" onclick="quitarLineaIngreso(${l.id})">×</button></td>
        </tr>
    `).join('');
    calcularTotalesIngreso();
}

function actualizarLineaIngreso(id, campo, valor) {
    const l = lineasIngreso.find(x => x.id === id);
    if (!l) return;
    if (campo === 'cantidad' || campo === 'costo_bruto') {
        l[campo] = parseFloat(valor) || 0;
        l.costo_neto = document.getElementById('ingresoConFactura').checked ? l.costo_bruto / 1.13 : l.costo_bruto;
    } else l[campo] = valor;
    renderLineasIngreso();
}

function quitarLineaIngreso(id) {
    if (lineasIngreso.length <= 1) { alert('Debe haber al menos una línea'); return; }
    lineasIngreso = lineasIngreso.filter(l => l.id !== id);
    renderLineasIngreso();
}

function recalcularIngreso() {
    const conFactura = document.getElementById('ingresoConFactura').checked;
    lineasIngreso.forEach(l => { l.costo_neto = conFactura ? l.costo_bruto / 1.13 : l.costo_bruto; });
    renderLineasIngreso();
}

function calcularTotalesIngreso() {
    const conFactura = document.getElementById('ingresoConFactura').checked;
    let totalNeto = lineasIngreso.reduce((sum, l) => sum + l.cantidad * (conFactura ? l.costo_neto : l.costo_bruto), 0);
    const iva = conFactura ? totalNeto * 0.13 : 0;
    document.getElementById('ingresoTotalNeto').textContent = 'Bs. ' + formatNumber(totalNeto, 2);
    document.getElementById('ingresoIVA').textContent = 'Bs. ' + formatNumber(iva, 2);
    document.getElementById('ingresoTotal').textContent = 'Bs. ' + formatNumber(totalNeto + iva, 2);
}

async function guardarIngreso() {
    const lineasValidas = lineasIngreso.filter(l => l.id_inventario && l.cantidad > 0);
    if (lineasValidas.length === 0) { alert('Agregue al menos una línea'); return; }
    
    const conFactura = document.getElementById('ingresoConFactura').checked;
    const payload = {
        action: 'multiproducto',
        documento_tipo: 'INGRESO',
        documento_numero: document.getElementById('ingresoDocumento').value,
        tipo_movimiento: 'ENTRADA_COMPRA',
        fecha: document.getElementById('ingresoFecha').value,
        id_proveedor: document.getElementById('ingresoProveedor').value || null,
        referencia: document.getElementById('ingresoReferencia').value,
        observaciones: document.getElementById('ingresoObservaciones').value,
        lineas: lineasValidas.map(l => ({ id_inventario: l.id_inventario, cantidad: l.cantidad, costo_unitario: conFactura ? l.costo_neto : l.costo_bruto }))
    };
    
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const d = await r.json();
        if (d.success) { alert('✅ ' + d.message); cerrarModal('modalIngreso'); cargarDatos(); if (categoriaSeleccionada) seleccionarCategoria(categoriaSeleccionada.id_categoria); }
        else alert('❌ ' + d.message);
    } catch (e) { alert('Error de conexión'); }
}

// ========== MODAL SALIDA ==========
function abrirModalSalida() {
    document.getElementById('salidaDocumento').value = generarNumeroDoc('SAL');
    document.getElementById('salidaFecha').value = new Date().toISOString().split('T')[0];
    document.getElementById('salidaObservaciones').value = '';
    document.getElementById('salidaReferencia').value = '';
    lineasSalida = [];
    agregarLineaSalida();
    document.getElementById('modalSalida').classList.add('show');
}

function agregarLineaSalida() {
    lineasSalida.push({ id: Date.now(), id_inventario: '', cantidad: 0, stock_disponible: 0, costo_cpp: 0 });
    renderLineasSalida();
}

function renderLineasSalida() {
    document.getElementById('salidaLineasBody').innerHTML = lineasSalida.map(l => `
        <tr>
            <td><select onchange="actualizarLineaSalida(${l.id},this)">
                <option value="">Seleccione...</option>
                ${productosCompletos.map(p => `<option value="${p.id_inventario}" data-stock="${p.stock_actual}" data-costo="${p.costo_promedio||p.costo_unitario||0}" ${l.id_inventario==p.id_inventario?'selected':''}>${p.codigo} - ${p.nombre}</option>`).join('')}
            </select></td>
            <td style="text-align:center;">${formatNumber(l.stock_disponible,2)}</td>
            <td><input type="number" step="0.01" max="${l.stock_disponible}" value="${l.cantidad}" onchange="actualizarCantidadSalida(${l.id},this.value)"></td>
            <td style="text-align:right;">Bs.${formatNumber(l.costo_cpp,2)}</td>
            <td style="text-align:right;">Bs.${formatNumber(l.cantidad*l.costo_cpp,2)}</td>
            <td><button style="background:#dc3545;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;" onclick="quitarLineaSalida(${l.id})">×</button></td>
        </tr>
    `).join('');
    calcularTotalesSalida();
}

function actualizarLineaSalida(id, select) {
    const l = lineasSalida.find(x => x.id === id);
    if (!l) return;
    const opt = select.options[select.selectedIndex];
    l.id_inventario = select.value;
    l.stock_disponible = parseFloat(opt.dataset.stock) || 0;
    l.costo_cpp = parseFloat(opt.dataset.costo) || 0;
    l.cantidad = 0;
    renderLineasSalida();
}

function actualizarCantidadSalida(id, valor) {
    const l = lineasSalida.find(x => x.id === id);
    if (!l) return;
    const cant = parseFloat(valor) || 0;
    if (cant > l.stock_disponible) { alert('Stock insuficiente'); l.cantidad = l.stock_disponible; }
    else l.cantidad = cant;
    renderLineasSalida();
}

function quitarLineaSalida(id) {
    if (lineasSalida.length <= 1) { alert('Debe haber al menos una línea'); return; }
    lineasSalida = lineasSalida.filter(l => l.id !== id);
    renderLineasSalida();
}

function calcularTotalesSalida() {
    const total = lineasSalida.reduce((sum, l) => sum + l.cantidad * l.costo_cpp, 0);
    document.getElementById('salidaTotal').textContent = 'Bs. ' + formatNumber(total, 2);
}

async function guardarSalida() {
    const lineasValidas = lineasSalida.filter(l => l.id_inventario && l.cantidad > 0);
    if (lineasValidas.length === 0) { alert('Agregue al menos una línea'); return; }
    for (const l of lineasValidas) { if (l.cantidad > l.stock_disponible) { alert('Stock insuficiente'); return; } }
    
    const payload = {
        action: 'multiproducto',
        documento_tipo: 'SALIDA',
        documento_numero: document.getElementById('salidaDocumento').value,
        tipo_movimiento: document.getElementById('salidaTipo').value,
        fecha: document.getElementById('salidaFecha').value,
        referencia: document.getElementById('salidaReferencia').value,
        observaciones: document.getElementById('salidaObservaciones').value,
        lineas: lineasValidas.map(l => ({ id_inventario: l.id_inventario, cantidad: l.cantidad, costo_unitario: l.costo_cpp }))
    };
    
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const d = await r.json();
        if (d.success) { alert('✅ ' + d.message); cerrarModal('modalSalida'); cargarDatos(); if (categoriaSeleccionada) seleccionarCategoria(categoriaSeleccionada.id_categoria); }
        else alert('❌ ' + d.message);
    } catch (e) { alert('Error de conexión'); }
}

// ========== MODAL HISTORIAL ==========
function abrirModalHistorial() {
    const hoy = new Date(), hace30 = new Date(hoy); hace30.setDate(hace30.getDate() - 30);
    document.getElementById('historialDesde').value = hace30.toISOString().split('T')[0];
    document.getElementById('historialHasta').value = hoy.toISOString().split('T')[0];
    document.getElementById('historialTipo').value = '';
    document.getElementById('modalHistorial').classList.add('show');
    buscarHistorial();
}

async function buscarHistorial() {
    const desde = document.getElementById('historialDesde').value;
    const hasta = document.getElementById('historialHasta').value;
    const tipo = document.getElementById('historialTipo').value;
    let url = `${baseUrl}/api/inventarios.php?action=historial&tipo_id=${TIPO_ID}`;
    if (desde) url += `&fecha_desde=${desde}`;
    if (hasta) url += `&fecha_hasta=${hasta}`;
    if (tipo) url += `&tipo_movimiento=${tipo}`;
    
    document.getElementById('historialBody').innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    
    try {
        const r = await fetch(url);
        const d = await r.json();
        if (d.success) renderHistorial(d.documentos || []);
    } catch (e) { console.error('Error:', e); }
}

function renderHistorial(docs) {
    const tbody = document.getElementById('historialBody');
    if (docs.length === 0) { tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;">Sin documentos</td></tr>'; return; }
    
    tbody.innerHTML = docs.map(doc => {
        const esEntrada = doc.tipo_movimiento?.includes('ENTRADA');
        const esAnulado = doc.estado === 'ANULADO';
        return `<tr style="${esAnulado?'opacity:0.6;':''}">
            <td>${doc.fecha ? new Date(doc.fecha).toLocaleDateString('es-BO') : '-'}</td>
            <td><strong>${doc.documento_numero}</strong></td>
            <td style="color:${esEntrada?'#28a745':'#dc3545'};">${esEntrada?'↓ Entrada':'↑ Salida'}</td>
            <td style="text-align:right;">Bs.${formatNumber(doc.total_documento||0,2)}</td>
            <td><span class="${esAnulado?'badge-anulado':'badge-activo'}">${doc.estado||'ACTIVO'}</span></td>
            <td><button class="btn-icon" style="background:#17a2b8;color:white;" onclick="verDetalleDocumento('${doc.documento_numero}')"><i class="fas fa-eye"></i></button></td>
        </tr>`;
    }).join('');
}

// ========== MODAL DETALLE ==========
async function verDetalleDocumento(docNumero) {
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=documento_detalle&documento=${encodeURIComponent(docNumero)}`);
        const d = await r.json();
        if (d.success) { documentoActual = d; renderDetalleDocumento(d); document.getElementById('modalDetalle').classList.add('show'); }
        else alert('❌ ' + d.message);
    } catch (e) { alert('Error'); }
}

function renderDetalleDocumento(data) {
    const cab = data.cabecera, lineas = data.lineas || [];
    const esAnulado = cab.estado === 'ANULADO';
    document.getElementById('detalleTitulo').textContent = 'Documento: ' + cab.documento_numero;
    document.getElementById('btnAnular').style.display = esAnulado ? 'none' : 'inline-block';
    
    document.getElementById('detalleContenido').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;background:#f8f9fa;padding:15px;border-radius:8px;">
            <div><p><strong>Tipo:</strong> ${cab.documento_tipo||'-'}</p><p><strong>Fecha:</strong> ${cab.fecha?new Date(cab.fecha).toLocaleDateString('es-BO'):'-'}</p><p><strong>Movimiento:</strong> ${cab.tipo_movimiento||'-'}</p></div>
            <div><p><strong>Usuario:</strong> ${cab.usuario||'N/A'}</p><p><strong>Estado:</strong> <span class="${esAnulado?'badge-anulado':'badge-activo'}">${cab.estado||'ACTIVO'}</span></p></div>
        </div>
        <table class="productos-table">
            <thead><tr><th>Código</th><th>Producto</th><th style="text-align:right;">Cantidad</th><th style="text-align:right;">Costo</th><th style="text-align:right;">Total</th></tr></thead>
            <tbody>${lineas.map(l => `<tr><td>${l.producto_codigo||'-'}</td><td>${l.producto_nombre||'-'}</td><td style="text-align:right;">${formatNumber(l.cantidad,2)}</td><td style="text-align:right;">Bs.${formatNumber(l.costo_unitario,2)}</td><td style="text-align:right;">Bs.${formatNumber(l.cantidad*l.costo_unitario,2)}</td></tr>`).join('')}</tbody>
            <tfoot><tr style="background:#e9ecef;font-weight:bold;"><td colspan="4" style="text-align:right;">TOTAL:</td><td style="text-align:right;">Bs.${formatNumber(cab.total_documento||0,2)}</td></tr></tfoot>
        </table>
    `;
}

async function anularDocumento() {
    if (!documentoActual) return;
    if (!confirm('¿Anular este documento?')) return;
    const motivo = prompt('Motivo:');
    if (!motivo) { alert('Ingrese motivo'); return; }
    
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'anular_documento', documento_numero: documentoActual.cabecera.documento_numero, motivo })
        });
        const d = await r.json();
        if (d.success) { alert('✅ ' + d.message); cerrarModal('modalDetalle'); buscarHistorial(); cargarDatos(); }
        else alert('❌ ' + d.message);
    } catch (e) { alert('Error'); }
}

function imprimirDocumento() {
    if (!documentoActual) return;
    const contenido = document.getElementById('detalleContenido').innerHTML;
    const v = window.open('', '_blank');
    v.document.write(`<html><head><title>${documentoActual.cabecera.documento_numero}</title><style>body{font-family:Arial;padding:20px;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ddd;padding:8px;}</style></head><body><h1>HERMEN LTDA.</h1><h2>${documentoActual.cabecera.documento_numero}</h2>${contenido}<scr`+`ipt>window.print();</scr`+`ipt></body></html>`);
    v.document.close();
}

// ========== MODAL KARDEX ==========
async function verKardex(id) {
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=kardex&id=${id}`);
        const d = await r.json();
        if (d.success) { renderKardex(d); document.getElementById('modalKardex').classList.add('show'); }
        else alert('❌ ' + d.message);
    } catch (e) { alert('Error'); }
}

function renderKardex(data) {
    const p = data.producto, movs = data.movimientos || [];
    document.getElementById('kardexTitulo').textContent = 'Kardex: ' + p.codigo;
    document.getElementById('kardexHeader').innerHTML = `<h4>${p.nombre}</h4><p><strong>Stock:</strong> ${formatNumber(p.stock_actual,2)} | <strong>CPP:</strong> Bs.${formatNumber(p.costo_promedio||0,2)}</p>`;
    
    if (movs.length === 0) { document.getElementById('kardexBody').innerHTML = '<tr><td colspan="8" style="text-align:center;">Sin movimientos</td></tr>'; return; }
    
    document.getElementById('kardexBody').innerHTML = movs.map(m => {
        const esEntrada = m.tipo_movimiento?.includes('ENTRADA');
        return `<tr class="${esEntrada?'entrada':'salida'}">
            <td>${m.fecha?new Date(m.fecha).toLocaleDateString('es-BO'):'-'}</td>
            <td>${m.documento_numero||'-'}</td>
            <td>${esEntrada?'Entrada':'Salida'}</td>
            <td style="text-align:right;">${esEntrada?formatNumber(m.cantidad,2):''}</td>
            <td style="text-align:right;">${!esEntrada?formatNumber(m.cantidad,2):''}</td>
            <td style="text-align:right;">${formatNumber(m.stock_despues||0,2)}</td>
            <td style="text-align:right;">Bs.${formatNumber(m.costo_unitario||0,2)}</td>
            <td style="text-align:right;">Bs.${formatNumber(m.cpp_despues||0,2)}</td>
        </tr>`;
    }).join('');
}

// ========== UTILIDADES ==========
function cerrarModal(id) { document.getElementById(id).classList.remove('show'); }

function generarNumeroDoc(prefijo) {
    const f = new Date();
    return `${prefijo}-${f.getFullYear().toString().substr(-2)}${String(f.getMonth()+1).padStart(2,'0')}${String(f.getDate()).padStart(2,'0')}-${Math.floor(Math.random()*1000).toString().padStart(3,'0')}`;
}

function formatNumber(n, d = 2) {
    if (n === null || n === undefined || isNaN(n)) return '0.00';
    // Primero redondear para evitar errores de precisión flotante
    const num = Math.round(parseFloat(n) * 100) / 100;
    // Separar parte entera y decimal
    const parts = num.toFixed(d).split('.');
    // Agregar separador de miles (coma) a la parte entera
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    // Unir con punto decimal
    return parts.join('.');
}

console.log('✅ Módulo Materias Primas v1.0 cargado');