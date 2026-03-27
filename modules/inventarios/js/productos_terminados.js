/**
 * JavaScript para módulo Materias Primas  
 * Sistema MES Hermen Ltda. v1.9
 * VERSIÓN CORREGIDA CON TODAS LAS FUNCIONES
 */

const BASE_URL_API = window.location.origin + '/mes_hermen/api';
const baseUrl = window.location.origin + '/mes_hermen';
const iconTitle = document.querySelector('.mp-title-icon');
const TIPO_ID = (iconTitle && iconTitle.dataset && iconTitle.dataset.tipoId) || 6;

let categorias = [], subcategorias = [], productos = [], productosCompletos = [];
let unidades = [], proveedores = [];
let categoriaSeleccionada = null, subcategoriaSeleccionada = null;
let lineasIngreso = [];
let lineasSalida = [];
let documentoActual = null;

// ⭐ CACHE DE NÚMEROS DE SALIDA POR SESIÓN (accesible desde HTML onchange)
window.numerosSalidaCache = {};
window.numerosIngresoCache = {};

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

            document.getElementById('kpiItems').textContent = totales.items || 0;
            document.getElementById('kpiValor').textContent = 'Bs. ' + formatNum(totales.valor);
            document.getElementById('kpiAlertas').textContent = totales.alertas || 0;
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
                        if (stock <= 0 || stock <= stockMin) {
                            cat.alertas++;
                        }
                    }
                });
            }

            renderCategorias();
            document.getElementById('kpiCategorias').textContent = categorias.length;
        } else {
            document.getElementById('kpiCategorias').textContent = 0;
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
        <div class="categoria-card ${(categoriaSeleccionada && categoriaSeleccionada.id_categoria == c.id_categoria) ? 'active' : ''}" onclick="seleccionarCategoria(${c.id_categoria})">
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
            estiloExtra = 'background: linear-gradient(135deg, #1a237e, #4fc3f7); color: white;';
        } else if (esSinClasificar) {
            estiloExtra = 'background: #fff3cd; border-color: #ffc107;';
        }

        return `
        <div class="categoria-card ${subcategoriaSeleccionada?.id_subcategoria == s.id_subcategoria ? 'active' : ''}" 
             onclick="seleccionarSubcategoria(${s.id_subcategoria})"
             style="${estiloExtra}">
            <div class="categoria-header">
                <div class="categoria-nombre" ${esVerTodos ? 'style="color:white;"' : ''}>${s.nombre}</div>
                <span class="categoria-badge" ${esVerTodos ? 'style="background:white;color:#1a237e;"' : ''}>${s.total_items || 0}</span>
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
                <button class="btn-icon kardex" onclick="abrirKardexSafe(${p.id_inventario})" title="Kardex"><i class="fas fa-book"></i></button>
                <button class="btn-icon editar" onclick="editarItem(${p.id_inventario})" title="Editar"><i class="fas fa-edit"></i></button>
            </td>
        </tr>`;
    }).join('');
}

function abrirKardexSafe(id) {
    const item = productosCompletos.find(p => p.id_inventario == id);
    if (item) {
        abrirKardex(id, item.nombre);
    } else {
        console.error('Item no encontrado para abrir kardex');
    }
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

    // Simplemente llamamos a render para que ella ajuste el encabezado y las filas
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
    const thead = document.getElementById('theadIngreso');

    // Validar que hay un tipo de ingreso seleccionado
    if (!tipoIngresoActual) {
        console.warn('⚠️ No hay tipo de ingreso seleccionado');
        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; padding:20px;">Seleccione un tipo de ingreso</td></tr>';
        return;
    }

    // Obtener configuración de columnas
    const configColumnas = obtenerConfiguracionColumnas();
    if (!configColumnas) {
        console.error('❌ No se pudo obtener configuración de columnas');
        return;
    }

    const columnas = configColumnas.columnas;
    console.log('📋 Renderizando con', columnas.length, 'columnas para tipo:', tipoIngresoActual.codigo);

    // 1. ACTUALIZAR ENCABEZADO (thead)
    thead.innerHTML = `
        <tr>
            ${columnas.map(col => {
        let style = `width:${col.width};`;
        if (col.align) style += ` text-align:${col.align};`;
        // if (col.bg) style += ` background:${col.bg};`;
        if (col.calculado) style += ' font-size:0.75rem;';

        return `<th style="${style}">${col.label}</th>`;
    }).join('')}
        </tr>
    `;

    // 2. Si no hay líneas, agregar una
    if (lineasIngreso.length === 0) {
        agregarLineaIngreso();
        return;
    }

    // 3. RENDERIZAR FILAS (tbody) según el tipo de ingreso
    tbody.innerHTML = lineasIngreso.map((l, i) => {
        const prod = productosCompletos.find(p => p.id_inventario == l.id_inventario);
        const unidad = prod ? (prod.unidad_abrev || prod.abreviatura || prod.unidad || 'kg') : '-';
        const cantidad = toNum(l.cantidad);
        const valorTotal = toNum(l.valor_total_item);

        // Estilo común para inputs
        const inputStyle = "width:100%; padding:6px; font-weight:600; text-align:right; border:1px solid #ddd; border-radius:4px;";

        // =======================================
        // TIPO: COMPRA A PROVEEDOR
        // =======================================
        if (tipoIngresoActual.codigo === 'COMPRA') {
            if (modoConFactura) {
                // CON FACTURA (9 columnas)
                const costoUnitDoc = cantidad > 0 ? valorTotal / cantidad : 0;
                const iva = valorTotal * 0.13;
                const costoItem = valorTotal - iva;
                const costoUnitNeto = cantidad > 0 ? costoItem / cantidad : 0;

                return `
                    <tr>
                        <td style="text-align:center; font-weight:600; background:#f8f9fa;">${i + 1}</td>
                        <td>
                            <select id="ingProd_${i}" onchange="seleccionarProductoIngreso(${i})" style="width:100%; padding:6px; font-size:0.85rem;">
                                <option value="">Seleccione producto...</option>
                                ${productosFiltrados.map(p => `<option value="${p.id_inventario}" ${p.id_inventario == l.id_inventario ? 'selected' : ''}>${p.nombre}</option>`).join('')}
                            </select>
                        </td>
                        <td style="text-align:center; font-weight:600;">${unidad}</td>
                        <td><input type="text" id="ingCant_${i}" value="${formatNum(cantidad, 2)}" style="${inputStyle} background:#fff3cd;" onchange="calcularLineaIngreso(${i})" onkeydown="handleTabNavigation(event, ${i}, 'cantidad')" onfocus="limpiarFormatoInput('ingCant_${i}')" onblur="formatearInputNumerico('ingCant_${i}')"></td>
                        <td><input type="text" id="ingValor_${i}" value="${formatNum(valorTotal, 2)}" style="${inputStyle} background:#fff3cd;" onchange="calcularLineaIngreso(${i})" onkeydown="handleTabNavigation(event, ${i}, 'valor')" onfocus="limpiarFormatoInput('ingValor_${i}')" onblur="formatearInputNumerico('ingValor_${i}')"></td>
                        <td id="res_cudoc_${i}" style="background:#f8f9fa; text-align:right; padding-right:10px; font-weight:500;">${formatNum(costoUnitDoc, 4)}</td>
                        <td id="res_iva_${i}" style="background:#fff9e6; text-align:right; padding-right:10px; font-weight:500;">${formatNum(iva, 2)}</td>
                        <td id="res_citem_${i}" style="background:#f8f9fa; text-align:right; padding-right:10px; font-weight:500;">${formatNum(costoItem, 2)}</td>
                        <td id="res_cuneto_${i}" style="background:#d4edda; text-align:right; padding-right:10px; font-weight:700; color:#155724;">${formatNum(costoUnitNeto, 4)}</td>
                        <td style="text-align:center;"><button type="button" onclick="eliminarLineaIngreso(${i})" style="background:#dc3545; color:white; border:none; padding:6px 10px; border-radius:4px;"><i class="fas fa-trash"></i></button></td>
                    </tr>`;
            } else {
                // SIN FACTURA (7 columnas)
                const costoUnitario = cantidad > 0 ? valorTotal / cantidad : 0;

                return `
                    <tr>
                        <td style="text-align:center; font-weight:600; background:#f8f9fa;">${i + 1}</td>
                        <td>
                            <select id="ingProd_${i}" onchange="seleccionarProductoIngreso(${i})" style="width:100%; padding:6px; font-size:0.85rem;">
                                <option value="">Seleccione producto...</option>
                                ${productosFiltrados.map(p => `<option value="${p.id_inventario}" ${p.id_inventario == l.id_inventario ? 'selected' : ''}>${p.nombre}</option>`).join('')}
                            </select>
                        </td>
                        <td style="text-align:center; font-weight:600;">${unidad}</td>
                        <td><input type="text" id="ingCant_${i}" value="${formatNum(cantidad, 2)}" style="${inputStyle} background:#fff3cd;" onchange="calcularLineaIngreso(${i})" onkeydown="handleTabNavigation(event, ${i}, 'cantidad')" onfocus="limpiarFormatoInput('ingCant_${i}')" onblur="formatearInputNumerico('ingCant_${i}')"></td>
                        <td><input type="text" id="ingValor_${i}" value="${formatNum(valorTotal, 2)}" style="${inputStyle} background:#fff3cd;" onchange="calcularLineaIngreso(${i})" onkeydown="handleTabNavigation(event, ${i}, 'valor')" onfocus="limpiarFormatoInput('ingValor_${i}')" onblur="formatearInputNumerico('ingValor_${i}')"></td>
                        <td id="res_cu_${i}" style="background:#d4edda; text-align:right; padding-right:10px; font-weight:700; color:#155724;">${formatNum(costoUnitario, 4)}</td>
                        <td style="text-align:center;"><button type="button" onclick="eliminarLineaIngreso(${i})" style="background:#dc3545; color:white; border:none; padding:6px 10px; border-radius:4px;"><i class="fas fa-trash"></i></button></td>
                    </tr>`;
            }
        }

        // =======================================
        // TIPO: INVENTARIO INICIAL
        // =======================================
        else if (tipoIngresoActual.codigo === 'INICIAL') {
            const costoUnit = toNum(l.costo_unitario);
            const valorCalculado = cantidad * costoUnit;

            return `
                <tr>
                    <td style="text-align:center; font-weight:600; background:#f8f9fa;">${i + 1}</td>
                    <td>
                        <select id="ingProd_${i}" onchange="seleccionarProductoIngreso(${i})" style="width:100%; padding:6px; font-size:0.85rem;">
                            <option value="">Seleccione producto...</option>
                            ${productosFiltrados.map(p => `<option value="${p.id_inventario}" ${p.id_inventario == l.id_inventario ? 'selected' : ''}>${p.nombre}</option>`).join('')}
                        </select>
                    </td>
                    <td style="text-align:center; font-weight:600;">${unidad}</td>
                    <td><input type="text" id="ingCant_${i}" value="${formatNum(cantidad, 2)}" style="${inputStyle} background:#e3f2fd;" onchange="calcularLineaIngresoInicial(${i})" onkeydown="handleTabNavigation(event, ${i}, 'cantidad')" onfocus="limpiarFormatoInput('ingCant_${i}')" onblur="formatearInputNumerico('ingCant_${i}')"></td>
                    <td><input type="text" id="ingCosto_${i}" value="${formatNum(costoUnit, 2)}" style="${inputStyle} background:#e3f2fd;" onchange="calcularLineaIngresoInicial(${i})" onkeydown="handleTabNavigation(event, ${i}, 'costo')" onfocus="limpiarFormatoInput('ingCosto_${i}')" onblur="formatearInputNumerico('ingCosto_${i}')"></td>
                    <td id="res_total_${i}" style="background:#f1f8e9; text-align:right; padding-right:10px; font-weight:700; color:#2e7d32;">${formatNum(valorCalculado, 2)}</td>
                    <td style="text-align:center;"><button type="button" onclick="eliminarLineaIngreso(${i})" style="background:#dc3545; color:white; border:none; padding:6px 10px; border-radius:4px;"><i class="fas fa-trash"></i></button></td>
                </tr>`;
        }

        // =======================================
        // TIPO: DEVOLUCIÓN DE PRODUCCIÓN
        // =======================================
        else if (tipoIngresoActual.codigo === 'DEVOLUCION_PROD') {
            const costoPromedio = prod ? (toNum(prod.costo_promedio) || toNum(prod.costo_unitario)) : 0;
            const valorCalculado = cantidad * costoPromedio;

            return `
                <tr>
                    <td style="text-align:center; font-weight:600; background:#f8f9fa;">${i + 1}</td>
                    <td>
                        <select id="ingProd_${i}" onchange="seleccionarProductoIngreso(${i})" style="width:100%; padding:6px; font-size:0.85rem;">
                            <option value="">Seleccione producto...</option>
                            ${productosFiltrados.map(p => `<option value="${p.id_inventario}" ${p.id_inventario == l.id_inventario ? 'selected' : ''}>${p.nombre}</option>`).join('')}
                        </select>
                    </td>
                    <td style="text-align:center; font-weight:600;">${unidad}</td>
                    <td><input type="text" id="ingCant_${i}" value="${formatNum(cantidad, 2)}" style="${inputStyle} background:#fff3e0;" onchange="calcularLineaIngresoDevolucion(${i})" onkeydown="handleTabNavigation(event, ${i}, 'cantidad')" onfocus="limpiarFormatoInput('ingCant_${i}')" onblur="formatearInputNumerico('ingCant_${i}')"></td>
                    <td id="res_cpp_${i}" style="background:#f5f5f5; text-align:right; padding-right:10px; font-weight:500; color:#666;">${formatNum(costoPromedio, 4)}</td>
                    <td id="res_total_${i}" style="background:#f1f8e9; text-align:right; padding-right:10px; font-weight:700; color:#2e7d32;">${formatNum(valorCalculado, 2)}</td>
                    <td style="text-align:center;"><button type="button" onclick="eliminarLineaIngreso(${i})" style="background:#dc3545; color:white; border:none; padding:6px 10px; border-radius:4px;"><i class="fas fa-trash"></i></button></td>
                </tr>`;
        }

        // =======================================
        // TIPO: AJUSTE POSITIVO
        // =======================================
        else if (tipoIngresoActual.codigo === 'AJUSTE_POS') {
            const costoPromedio = prod ? (toNum(prod.costo_promedio) || toNum(prod.costo_unitario)) : 0;
            const valorCalculado = cantidad * costoPromedio;

            return `
        <tr>
            <td style="text-align:center; font-weight:600; background:#f8f9fa;">${i + 1}</td>
            <td>
                <select id="ingProd_${i}" onchange="seleccionarProductoIngreso(${i})" style="width:100%; padding:6px; font-size:0.85rem;">
                    <option value="">Seleccione producto...</option>
                    ${productosFiltrados.map(p => `<option value="${p.id_inventario}" ${p.id_inventario == l.id_inventario ? 'selected' : ''}>${p.nombre}</option>`).join('')}
                </select>
            </td>
            <td style="text-align:center; font-weight:600;">${unidad}</td>
            <td><input type="text" id="ingCant_${i}" value="${formatNum(cantidad, 2)}" style="${inputStyle} background:#e8f5e9;" onchange="calcularLineaIngresoAjuste(${i})" onkeydown="handleTabNavigation(event, ${i}, 'cantidad')" onfocus="limpiarFormatoInput('ingCant_${i}')" onblur="formatearInputNumerico('ingCant_${i}')"></td>
            <td id="res_cpp_${i}" style="background:#f5f5f5; text-align:right; padding-right:10px; font-weight:500; color:#666;">${formatNum(costoPromedio, 4)}</td>
            <td id="res_total_${i}" style="background:#f1f8e9; text-align:right; padding-right:10px; font-weight:700; color:#2e7d32;">${formatNum(valorCalculado, 2)}</td>
            <td style="text-align:center;"><button type="button" onclick="eliminarLineaIngreso(${i})" style="background:#dc3545; color:white; border:none; padding:6px 10px; border-radius:4px;"><i class="fas fa-trash"></i></button></td>
        </tr>`;
        }


        // =======================================
        // TIPO NO RECONOCIDO
        // =======================================
        else {
            return `<tr><td colspan="${columnas.length}" style="text-align:center; padding:20px; color:red;">Tipo de ingreso no soportado: ${tipoIngresoActual.codigo}</td></tr>`;
        }

    }).join('');

    // 4. RECALCULAR TOTALES
    recalcularIngreso();
}

// ========================================
// NUEVAS FUNCIONES DE CÁLCULO POR TIPO
// ========================================

/**
 * Calcular para INVENTARIO INICIAL
 * Usuario ingresa: Cantidad y Costo Unitario
 * Sistema calcula: Valor Total
 */
function calcularLineaIngresoInicial(index) {
    const cantidad = toNum(document.getElementById(`ingCant_${index}`).value);
    const costoUnit = toNum(document.getElementById(`ingCosto_${index}`).value);

    lineasIngreso[index].cantidad = cantidad;
    lineasIngreso[index].costo_unitario = costoUnit;
    lineasIngreso[index].valor_total_item = cantidad * costoUnit;

    // Actualizar valor calculado
    const elTotal = document.getElementById(`res_total_${index}`);
    if (elTotal) {
        elTotal.textContent = formatNum(cantidad * costoUnit, 2);
    }

    recalcularIngreso();
}

/**
 * Calcular para DEVOLUCIÓN DE PRODUCCIÓN
 * Usuario ingresa: Cantidad
 * Sistema usa: Costo Promedio del producto (readonly)
 * Sistema calcula: Valor Total
 */
function calcularLineaIngresoDevolucion(index) {
    const cantidad = toNum(document.getElementById(`ingCant_${index}`).value);

    console.log('🔢 Cantidad ingresada:', cantidad);

    lineasIngreso[index].cantidad = cantidad;

    // Obtener costo promedio del producto seleccionado
    const idProd = lineasIngreso[index].id_inventario;

    if (!idProd) {
        console.warn('⚠️ Seleccione un producto primero');
        return;
    }

    const prod = productosCompletos.find(p => p.id_inventario == idProd);
    const costoPromedio = prod ? (toNum(prod.costo_promedio) || toNum(prod.costo_unitario)) : 0;

    console.log(`📊 Producto: ${prod?.nombre || 'N/A'}`);
    console.log(`💰 CPP usado: ${costoPromedio}`);

    lineasIngreso[index].costo_unitario = costoPromedio;
    lineasIngreso[index].valor_total_item = cantidad * costoPromedio;

    console.log(`💵 Valor Total = ${cantidad} × ${costoPromedio} = ${cantidad * costoPromedio}`);

    // Actualizar celdas en la tabla
    const elCPP = document.getElementById(`res_cpp_${index}`);
    if (elCPP) {
        elCPP.textContent = formatNum(costoPromedio, 4);
        console.log('✅ CPP actualizado en tabla');
    }

    const elTotal = document.getElementById(`res_total_${index}`);
    if (elTotal) {
        elTotal.textContent = formatNum(cantidad * costoPromedio, 2);
        console.log('✅ Total actualizado en tabla');
    }

    recalcularIngreso();
}


/**
 * Calcular para AJUSTE POSITIVO
 * Similar a Inventario Inicial
 */
function calcularLineaIngresoAjuste(index) {
    const cantidad = toNum(document.getElementById(`ingCant_${index}`).value);

    console.log('🔢 Cantidad ingresada (Ajuste):', cantidad);

    lineasIngreso[index].cantidad = cantidad;

    const idProd = lineasIngreso[index].id_inventario;

    if (!idProd) {
        console.warn('⚠️ Seleccione un producto primero');
        return;
    }

    const prod = productosCompletos.find(p => p.id_inventario == idProd);

    let costoPromedio = 0;

    if (prod) {
        if (prod.costo_promedio !== undefined && prod.costo_promedio !== null) {
            costoPromedio = toNum(prod.costo_promedio);
        }

        if (costoPromedio === 0 && prod.costo_unitario !== undefined) {
            costoPromedio = toNum(prod.costo_unitario);
        }
    }

    console.log(`📊 Producto (Ajuste): ${prod?.nombre || 'N/A'}`);
    console.log(`💰 CPP usado (Ajuste): ${costoPromedio}`);

    lineasIngreso[index].costo_unitario = costoPromedio;
    lineasIngreso[index].valor_total_item = cantidad * costoPromedio;

    console.log(`💵 Valor Total = ${cantidad} × ${costoPromedio} = ${cantidad * costoPromedio}`);

    // Actualizar celdas
    const elCPP = document.getElementById(`res_cpp_${index}`);
    if (elCPP) {
        elCPP.textContent = formatNum(costoPromedio, 4);
        console.log('✅ CPP actualizado en tabla');
    }

    const elTotal = document.getElementById(`res_total_${index}`);
    if (elTotal) {
        elTotal.textContent = formatNum(cantidad * costoPromedio, 2);
        console.log('✅ Total actualizado en tabla');
    }

    recalcularIngreso();
}


// ========================================
// NUEVA FUNCIÓN: Navegación con TAB
// ========================================

function handleTabNavigation(event, index, field) {
    // Solo actuar si es la tecla TAB
    if (event.key !== 'Tab' && event.keyCode !== 9) return;

    // Detener el salto automático del navegador
    event.preventDefault();

    setTimeout(() => {
        if (field === 'cantidad') {
            // Ir al siguiente campo según el tipo
            let nextField = null;

            if (tipoIngresoActual.codigo === 'COMPRA') {
                // COMPRA: Cantidad → Valor
                nextField = document.getElementById(`ingValor_${index}`);
            } else if (tipoIngresoActual.codigo === 'INICIAL') {
                // INVENTARIO INICIAL: Cantidad → Costo
                nextField = document.getElementById(`ingCosto_${index}`);
            } else if (tipoIngresoActual.codigo === 'AJUSTE_POS') {
                // AJUSTE POSITIVO: Cantidad → Siguiente línea (como devolución)
                nextField = document.getElementById(`ingCant_${index + 1}`);
                if (!nextField) {
                    nextField = document.getElementById(`ingCant_0`);
                }
            } else if (tipoIngresoActual.codigo === 'DEVOLUCION_PROD') {
                // DEVOLUCIÓN: Cantidad → Siguiente línea (no hay más campos)
                nextField = document.getElementById(`ingCant_${index + 1}`);
                if (!nextField) {
                    // Si no hay siguiente, volver a la primera
                    nextField = document.getElementById(`ingCant_0`);
                }
            }

            if (nextField) {
                nextField.focus();
                nextField.select();
            }

        } else if (field === 'valor' || field === 'costo') {
            // Desde Valor o Costo → ir a Cantidad de la siguiente fila
            const nextCantField = document.getElementById(`ingCant_${index + 1}`);

            if (nextCantField) {
                // Ya existe la siguiente fila
                nextCantField.focus();
                nextCantField.select();
            } else {
                // No existe, volver a la primera fila (TAB CIRCULAR)
                const firstCantField = document.getElementById(`ingCant_0`);
                if (firstCantField) {
                    firstCantField.focus();
                    firstCantField.select();

                    // Opcional: Desplazar el scroll hacia arriba
                    firstCantField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        }
    }, 50); // 50ms como en la versión original que funcionaba
}


// NUEVA FUNCIÓN: Formatear input al perder foco
function formatearInputNumerico(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;

    const valor = toNum(input.value);
    if (valor === 0) {
        input.value = '';
        return;
    }

    // Formatear con comas
    input.value = formatNum(valor, 2);
}

// NUEVA FUNCIÓN: Limpiar formato al enfocar (para editar)
function limpiarFormatoInput(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;

    // Remover comas para editar
    const valorSinComas = input.value.replace(/,/g, '');
    input.value = valorSinComas;
    input.select(); // Seleccionar todo el texto
}

function calcularLineaIngreso(index) {
    const cantEl = document.getElementById(`ingCant_${index}`);
    const valorEl = document.getElementById(`ingValor_${index}`);

    const cantidad = toNum(cantEl.value);
    const valorTotal = toNum(valorEl.value);

    lineasIngreso[index].cantidad = cantidad;
    lineasIngreso[index].valor_total_item = valorTotal;

    if (modoConFactura) {
        const iva = valorTotal * 0.13;
        const costoItem = valorTotal - iva;
        const cuNeto = cantidad > 0 ? costoItem / cantidad : 0;
        const cuDoc = cantidad > 0 ? valorTotal / cantidad : 0;

        lineasIngreso[index].costo_unitario = cuNeto;

        // Actualizar solo las celdas de texto de la fila actual
        const updateText = (id, val, dec) => {
            let el = document.getElementById(id);
            if (el) el.textContent = formatNum(val, dec);
        };

        updateText(`res_cudoc_${index}`, cuDoc, 4);
        updateText(`res_iva_${index}`, iva, 2);
        updateText(`res_citem_${index}`, costoItem, 2);
        updateText(`res_cuneto_${index}`, cuNeto, 4);

    } else {
        const cu = cantidad > 0 ? valorTotal / cantidad : 0;
        lineasIngreso[index].costo_unitario = cu;

        let elCu = document.getElementById(`res_cu_${index}`);
        if (elCu) elCu.textContent = formatNum(cu, 4);
    }

    recalcularIngreso(); // Totales de abajo
}

function seleccionarProductoIngreso(index) {
    const select = document.getElementById(`ingProd_${index}`);
    const idInventario = select.value;

    lineasIngreso[index].id_inventario = idInventario;

    console.log('📦 Producto seleccionado ID:', idInventario);

    // Para Devolución y Ajuste Positivo, cargar CPP automáticamente
    if (tipoIngresoActual && (tipoIngresoActual.codigo === 'DEVOLUCION_PROD' || tipoIngresoActual.codigo === 'AJUSTE_POS') && idInventario) {
        const prod = productosCompletos.find(p => p.id_inventario == idInventario);

        if (prod) {
            let cpp = 0;

            if (prod.costo_promedio !== undefined && prod.costo_promedio !== null) {
                cpp = toNum(prod.costo_promedio);
            }

            if (cpp === 0 && prod.costo_unitario !== undefined) {
                cpp = toNum(prod.costo_unitario);
            }

            lineasIngreso[index].costo_unitario = cpp;
            console.log('💰 CPP cargado:', cpp);

            const cantidad = toNum(lineasIngreso[index].cantidad);
            if (cantidad > 0) {
                lineasIngreso[index].valor_total_item = cantidad * cpp;
                console.log('💵 Valor calculado:', cantidad * cpp);
            }
        }
    }

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
    try {
        // 1. Obtener tipo de ingreso seleccionado
        const tipoId = parseInt(document.getElementById('ingresoTipoIngreso').value);

        if (!tipoId) {
            mostrarAlerta('Debe seleccionar un tipo de ingreso', 'error');
            return;
        }

        // 2. Obtener configuración del tipo
        const config = tiposIngresoConfig[tipoId];
        if (!config) {
            mostrarAlerta('Configuración del tipo de ingreso no encontrada', 'error');
            return;
        }

        console.log('💾 Guardando ingreso tipo:', config.nombre);

        // 3. VALIDACIONES DINÁMICAS según tipo

        // 3.1 Validar PROVEEDOR (Solo para COMPRAS)
        if (config.codigo === 'COMPRA') {
            const proveedorId = document.getElementById('ingresoProveedor').value;
            if (!proveedorId) {
                mostrarAlerta('Seleccione un proveedor para completar el registro de compra', 'error');
                return;
            }
        }

        // 3.2 Validar ÁREA DE PRODUCCIÓN (solo si es requerido)
        if (config.requiere_area_produccion) {
            const areaId = document.getElementById('ingresoArea').value;
            if (!areaId) {
                mostrarAlerta('Seleccione el área que devuelve', 'error');
                return;
            }
        }

        // 3.3 Validar MOTIVO (solo si es requerido)
        if (config.requiere_motivo) {
            const motivoId = document.getElementById('ingresoMotivo').value;
            if (!motivoId) {
                mostrarAlerta('Seleccione el motivo', 'error');
                return;
            }
        }

        // 3.4 Validar AUTORIZACIÓN (solo si es requerido)
        if (config.requiere_autorizacion && config.codigo !== 'AJUSTE_POS') {
            const autorizadoPor = document.getElementById('ingresoAutorizadoPor').value;
            if (!autorizadoPor) {
                mostrarAlerta('Seleccione quién autoriza', 'error');
                return;
            }
        }

        // 3.5 Validar OBSERVACIONES (solo si son obligatorias)
        if (config.observaciones_obligatorias) {
            const obs = document.getElementById('ingresoObservaciones').value.trim();
            if (!obs || obs.length < config.minimo_caracteres_obs) {
                mostrarAlerta(`Las observaciones son obligatorias (mínimo ${config.minimo_caracteres_obs} caracteres)`, 'error');
                return;
            }
        }

        // 4. Validar que haya líneas
        if (!lineasIngreso || lineasIngreso.length === 0) {
            mostrarAlerta('Debe agregar al menos una línea de productos', 'error');
            return;
        }

        // 5. Construir objeto de datos según tipo
        const datosIngreso = {
            action: 'crear',
            id_tipo_ingreso: tipoId,
            fecha: document.getElementById('ingresoFecha').value,
            observaciones: document.getElementById('ingresoObservaciones').value || null,
            lineas: lineasIngreso
        };
        //datosIngreso.tipo_ingreso = config.codigo;
        // 6. Agregar campos específicos según tipo

        // 6.1 COMPRA A PROVEEDOR
        if (config.codigo === 'COMPRA') {
            datosIngreso.id_proveedor = parseInt(document.getElementById('ingresoProveedor').value);
            datosIngreso.referencia = document.getElementById('ingresoReferencia').value || null;
            datosIngreso.con_factura = document.getElementById('ingresoConFactura').checked;
            datosIngreso.moneda = 'BOB'; // O desde un campo si lo tienes
        }

        // 6.2 INVENTARIO INICIAL
        else if (config.codigo === 'INICIAL') {
            datosIngreso.ubicacion_almacen = document.getElementById('ingresoUbicacion').value || null;
            datosIngreso.responsable_conteo = document.getElementById('ingresoResponsableConteo').value || null;
        }

        // 6.3 DEVOLUCIÓN DE PRODUCCIÓN
        else if (config.codigo === 'DEVOLUCION_PROD') {
            datosIngreso.id_area_produccion = parseInt(document.getElementById('ingresoArea').value);
            datosIngreso.motivo_ingreso = document.getElementById('ingresoMotivo').value;
            datosIngreso.responsable_entrega = document.getElementById('ingresoResponsableEntrega').value || null;
        }

        // 6.4 AJUSTE POSITIVO
        else if (config.codigo === 'AJUSTE_POS') {
            datosIngreso.motivo_ingreso = document.getElementById('ingresoMotivo').value;
            // No enviar autorizado_por
        }

        console.log('📦 Datos a enviar:', datosIngreso);

        // 7. Enviar al servidor
        const response = await fetch(`${BASE_URL_API}/ingresos_pt.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(datosIngreso)
        });

        const resultado = await response.json();

        if (resultado.success) {
            cerrarModal('modalIngreso');
            Swal.fire({
                title: '¡Registrado!',
                text: `Ingreso ${resultado.numero_documento || ''} registrado exitosamente.`,
                icon: 'success',
                confirmButtonText: 'Continuar'
            }).then(() => {
                window.location.href = `${window.baseUrl || baseUrl}/modules/inventarios/index.php`;
            });
        } else {
            mostrarAlerta(resultado.message || 'Error al registrar el ingreso', 'error');
        }

    } catch (error) {
        console.error('❌ Error en guardarIngreso:', error);
        mostrarAlerta('Error al procesar el ingreso: ' + error.message, 'error');
    }
}

/**
 * Cerrar modal
 */
function cerrarModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
    }
}

// ========== MODALES SALIDA, HISTORIAL, DETALLE, KARDEX ==========
// (Aquí irían las funciones de los otros modales - las omito por espacio pero están en el original)

// ========== MODAL SALIDA ==========

let productosFiltradosSalida = [];

function abrirModalSalida() {
    // Tipo por defecto vacío
    document.getElementById('salidaTipo').value = '';

    // Limpiar documento
    const docInput = document.getElementById('salidaDocumento');
    if (docInput) {
        docInput.value = '';
        docInput.disabled = true;
        docInput.placeholder = 'Seleccione tipo de salida...';
    }

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
document.addEventListener('DOMContentLoaded', function () {
    const selectTipo = document.getElementById('salidaTipo');
    if (selectTipo) {
        selectTipo.addEventListener('change', function () {
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

async function actualizarNumeroSalida() {
    const tipo = document.getElementById('salidaTipo').value;

    if (!tipo) {
        const docInput = document.getElementById('salidaDocumento');
        if (docInput) {
            docInput.value = '';
            docInput.placeholder = 'Seleccione tipo de salida...';
            docInput.disabled = true;
        }
        return;
    }

    if (window.numerosSalidaCache[tipo]) {
        const docInput = document.getElementById('salidaDocumento');
        if (docInput) {
            docInput.value = window.numerosSalidaCache[tipo];
            docInput.disabled = false;
        }

        const motivoObligatorio = document.getElementById('motivoObligatorio');
        if (motivoObligatorio) {
            motivoObligatorio.style.display = tipo === 'AJUSTE' ? 'inline' : 'none';
        }
        return;
    }

    try {
        const docInput = document.getElementById('salidaDocumento');
        if (docInput) {
            docInput.value = '⏳ Generando...';
            docInput.disabled = true;
        }

        const r = await fetch(`${BASE_URL_API}/salidas_pt.php?action=siguiente_numero&tipo=${tipo}`);
        const d = await r.json();

        if (d.success) {
            window.numerosSalidaCache[tipo] = d.numero;
            document.getElementById('salidaDocumento').value = d.numero;
            document.getElementById('salidaDocumento').disabled = false;
        }
    } catch (e) {
        console.error('Error al obtener número de salida:', e);
        const prefijos = {
            'PRODUCCION': 'OUT-PT-P',
            'VENTA': 'OUT-PT-V',
            'MUESTRAS': 'OUT-PT-M',
            'AJUSTE': 'OUT-PT-A',
            'DEVOLUCION': 'OUT-PT-R'
        };
        const prefijo = prefijos[tipo] || 'OUT-PT-X';
        const numero = generarNumeroDoc(prefijo);
        window.numerosSalidaCache[tipo] = numero;
        document.getElementById('salidaDocumento').value = numero;
        document.getElementById('salidaDocumento').disabled = false;
    }

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
        const r = await fetch(`${BASE_URL_API}/salidas_pt.php`, {
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

console.log('✅ Módulo Productos Terminados v1.9 cargado');
console.log('   - Modal Ingreso mejorado v2.0');
console.log('   - Filtros por tipo proveedor y categoría');
console.log('   - Cálculo IVA con columnas dinámicas');
console.log('   - Costos con 4 decimales');