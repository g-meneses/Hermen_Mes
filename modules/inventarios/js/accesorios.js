/**
 * JavaScript para módulo Materias Primas  
 * Sistema MES Hermen Ltda. v1.9
 * VERSIÓN CORREGIDA CON TODAS LAS FUNCIONES
 */

const BASE_URL_API = window.location.origin + '/mes_hermen/api';
const baseUrl = window.location.origin + '/mes_hermen';
const iconTitle = document.querySelector('.mp-title-icon');
const TIPO_ID = (iconTitle && iconTitle.dataset && iconTitle.dataset.tipoId) || 4;

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

// ========== VARIABLES PARA GENERADOR DE CÓDIGOS ==========
let modoManual = false; // Para controlar modo de edición de código
const codigoTipo = 'ACC'; // Prefijo fijo para Accesorios

// ========== BLINDAJE DE UNICIDAD - Variables y Funciones ==========
let codigoValidado = true; // Estado de validación del código

async function verificarCodigoDuplicado(codigo, excludeId = null) {
    if (!codigo || codigo.length < 3) {
        codigoValidado = false;
        actualizarEstadoBotonGuardar();
        return false;
    }

    actualizarBannerCodigo('loading', '🔍 Verificando disponibilidad...');

    try {
        let url = `${baseUrl}/api/centro_inventarios.php?action=verificar_codigo&codigo=${encodeURIComponent(codigo)}`;
        if (excludeId) url += `&exclude_id=${excludeId}`;

        const response = await fetch(url);
        const data = await response.json();

        if (data.existe) {
            codigoValidado = false;
            actualizarBannerCodigo('error', `❌ Código duplicado: ya existe en "${data.nombre}"`);
        } else {
            codigoValidado = true;
            actualizarBannerCodigo('ok', '✅ Código disponible');
        }
    } catch (error) {
        console.error('Error verificando código:', error);
        codigoValidado = true;
        actualizarBannerCodigo('ok', '✅ Código verificado');
    }

    actualizarEstadoBotonGuardar();
    return codigoValidado;
}

function actualizarBannerCodigo(tipo, mensaje) {
    const banner = document.getElementById('codigoStatusBanner');
    const icon = document.getElementById('codigoStatusIcon');
    const msg = document.getElementById('codigoStatusMessage');

    if (!banner) return;

    banner.style.display = 'flex';
    banner.className = '';

    switch (tipo) {
        case 'ok':
            banner.classList.add('codigo-status-ok');
            icon.className = 'fas fa-check-circle';
            break;
        case 'error':
            banner.classList.add('codigo-status-error');
            icon.className = 'fas fa-exclamation-circle';
            break;
        case 'loading':
            banner.classList.add('codigo-status-loading');
            icon.className = 'fas fa-spinner fa-spin';
            break;
    }

    msg.textContent = ' ' + mensaje;
}

function actualizarEstadoBotonGuardar() {
    const btnGuardar = document.querySelector('#modalItem .btn-success');
    if (btnGuardar) {
        btnGuardar.disabled = !codigoValidado;
        btnGuardar.style.opacity = codigoValidado ? '1' : '0.5';
        btnGuardar.style.cursor = codigoValidado ? 'pointer' : 'not-allowed';
    }
}

function ocultarBannerCodigo() {
    const banner = document.getElementById('codigoStatusBanner');
    if (banner) banner.style.display = 'none';
}

// ========== FUNCIONES DE GENERADOR DE CÓDIGOS ==========
function toggleModoManual() {
    modoManual = !modoManual;

    const autoView = document.getElementById('codigoAutomaticoView');
    const manualView = document.getElementById('codigoManualView');
    const previewSection = document.getElementById('codigoPreviewSection');
    const sufijoRow = document.getElementById('sufijoPersonalizadoRow');

    if (modoManual) {
        autoView.style.display = 'none';
        manualView.style.display = 'block';
        previewSection.style.display = 'none';
        sufijoRow.style.display = 'none';

        const codigoActual = document.getElementById('itemCodigo').value;
        document.getElementById('itemCodigoManual').value = codigoActual;
    } else {
        autoView.style.display = 'block';
        manualView.style.display = 'none';
        previewSection.style.display = 'block';
        sufijoRow.style.display = 'flex';

        actualizarCodigoSugerido();
    }
}

async function actualizarCodigoSugerido() {
    const catSelect = document.getElementById('itemCategoria');
    const subcatSelect = document.getElementById('itemSubcategoria');

    if (!catSelect || !catSelect.value) {
        document.getElementById('codigoPreviewSection').style.display = 'none';
        document.getElementById('sufijoPersonalizadoRow').style.display = 'none';
        document.getElementById('itemCodigo').value = '';
        actualizarBannerCodigo('loading', 'Seleccione categoría...');
        codigoValidado = false;
        actualizarEstadoBotonGuardar();
        return;
    }

    document.getElementById('codigoPreviewSection').style.display = 'block';
    document.getElementById('sufijoPersonalizadoRow').style.display = 'flex';

    const catId = catSelect.value;
    const subcatId = subcatSelect ? subcatSelect.value : '';

    const cat = categorias.find(c => c.id_categoria == catId);

    // Generar código de categoría
    let catCodigo = cat?.codigo || '';
    if (!catCodigo && cat?.nombre) {
        catCodigo = cat.nombre.replace(/[^a-zA-Z]/g, '').substring(0, 3).toUpperCase() || 'CAT';
    }
    if (!catCodigo) catCodigo = 'CAT';

    let subcatCodigo = 'GEN';
    if (subcatId && subcatId !== '0' && subcatId !== '') {
        try {
            const r = await fetch(`${baseUrl}/api/centro_inventarios.php?action=subcategorias&categoria_id=${catId}`);
            const d = await r.json();
            if (d.success && d.subcategorias) {
                const subcat = d.subcategorias.find(s => s.id_subcategoria == subcatId);
                if (subcat) {
                    subcatCodigo = subcat.codigo || '';
                    if (!subcatCodigo && subcat.nombre) {
                        subcatCodigo = subcat.nombre.replace(/[^a-zA-Z]/g, '').substring(0, 3).toUpperCase() || 'SUB';
                    }
                    if (!subcatCodigo) subcatCodigo = 'SUB';
                }
            }
        } catch (e) { console.error('Error:', e); }
    }

    // Actualizar badges
    document.getElementById('labelTipo').textContent = codigoTipo;
    document.getElementById('labelCat').textContent = catCodigo;
    document.getElementById('labelSubcat').textContent = subcatCodigo;

    // Obtener siguiente correlativo
    const prefijo = `${codigoTipo}-${catCodigo}-${subcatCodigo}-`;
    try {
        const url = `${baseUrl}/api/centro_inventarios.php?action=siguiente_codigo&tipo_id=${TIPO_ID}&prefijo=${encodeURIComponent(prefijo)}`;
        const r = await fetch(url);
        const d = await r.json();

        if (d.success && d.siguiente) {
            document.getElementById('itemSufijo').value = d.siguiente;
            document.getElementById('labelNum').textContent = String(d.siguiente).padStart(3, '0');
        } else {
            document.getElementById('itemSufijo').value = '001';
            document.getElementById('labelNum').textContent = '001';
        }
    } catch (e) {
        console.error('Error obteniendo correlativo:', e);
        document.getElementById('itemSufijo').value = '001';
        document.getElementById('labelNum').textContent = '001';
    }

    actualizarCodigoFinal();
}

async function actualizarCodigoFinal() {
    const tipo = document.getElementById('labelTipo').textContent || codigoTipo;
    const cat = document.getElementById('labelCat').textContent || 'CAT';
    const subcat = document.getElementById('labelSubcat').textContent || 'GEN';
    const sufijo = document.getElementById('itemSufijo').value || '001';

    const sufijoFormateado = String(sufijo).padStart(3, '0');
    const codigoFinal = `${tipo}-${cat}-${subcat}-${sufijoFormateado}`;

    document.getElementById('itemCodigo').value = codigoFinal;
    document.getElementById('previewPrefijo').textContent = `${tipo}-${cat}-${subcat}-`;
    document.getElementById('previewSufijo').textContent = sufijoFormateado;
    document.getElementById('labelNum').textContent = sufijoFormateado;

    const formatoEl = document.getElementById('formatoSugerido');
    if (formatoEl) formatoEl.textContent = `Formato: ${tipo}-${cat}-${subcat}-XXX`;

    // Verificar disponibilidad
    const itemId = document.getElementById('itemId').value;
    await verificarCodigoDuplicado(codigoFinal, itemId);
}

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
    lastLoadedCategoryId = null;
    document.getElementById('formItem').reset();
    document.getElementById('itemId').value = '';
    document.getElementById('modalItemTitulo').textContent = 'Nuevo Item de Accesorio';

    // Resetear estado del generador de códigos
    modoManual = false;
    document.getElementById('codigoPreviewSection').style.display = 'none';
    document.getElementById('sufijoPersonalizadoRow').style.display = 'none';
    document.getElementById('codigoAutomaticoView').style.display = 'block';
    document.getElementById('codigoManualView').style.display = 'none';
    document.getElementById('itemCodigo').value = '';

    // Blindaje: Resetear estado de validación
    codigoValidado = false;
    ocultarBannerCodigo();
    actualizarEstadoBotonGuardar();

    // Listener para código manual
    const codigoManual = document.getElementById('itemCodigoManual');
    if (codigoManual) {
        codigoManual.addEventListener('blur', function () {
            verificarCodigoDuplicado(this.value.trim().toUpperCase(), document.getElementById('itemId').value);
        });
    }

    poblarSelects();
    document.getElementById('modalItem').classList.add('show');
}

async function editarItem(id) {
    const item = productosCompletos.find(p => p.id_inventario == id);
    if (!item) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se encontró el producto',
            confirmButtonColor: '#d33'
        });
        return;
    }

    poblarSelects();

    // Llenar campos del formulario
    document.getElementById('itemId').value = item.id_inventario;
    document.getElementById('itemNombre').value = item.nombre || '';
    document.getElementById('itemCategoria').value = item.id_categoria || '';

    // Cargar subcategorías y seleccionar
    await cargarSubcategoriasItem(item.id_subcategoria);
    // document.getElementById('itemSubcategoria').value = item.id_subcategoria || '0';

    document.getElementById('itemUnidad').value = item.id_unidad || '';
    // document.getElementById('itemStockActual').value = item.stock_actual || 0; // Eliminado del form
    document.getElementById('itemStockMinimo').value = item.stock_minimo || 0;
    document.getElementById('itemCosto').value = item.costo_unitario || item.costo_promedio || 0;
    document.getElementById('itemDescripcion').value = item.descripcion || '';

    // Manejar el código en modo manual con código existente
    modoManual = true;
    document.getElementById('codigoPreviewSection').style.display = 'none';
    document.getElementById('codigoAutomaticoView').style.display = 'none';
    document.getElementById('codigoManualView').style.display = 'block';
    document.getElementById('sufijoPersonalizadoRow').style.display = 'none';
    document.getElementById('itemCodigoManual').value = item.codigo || '';
    document.getElementById('itemCodigo').value = item.codigo || '';

    // Blindaje: código existente = validado
    codigoValidado = true;
    actualizarBannerCodigo('ok', '✅ Editando item existente: ' + item.codigo);
    actualizarEstadoBotonGuardar();

    // Actualizar título del modal
    document.getElementById('modalItemTitulo').textContent = 'Editar Item: ' + item.codigo;
    document.getElementById('modalItem').classList.add('show');
}

// Variable global para controlar recarga de subcategorías
let lastLoadedCategoryId = null;

async function cargarSubcategoriasItem(selectedSubcatId = null) {
    const catSelect = document.getElementById('itemCategoria');
    const subSelect = document.getElementById('itemSubcategoria');

    if (!catSelect || !subSelect) return;

    const catId = catSelect.value;

    // Si la categoría es la misma y ya hay opciones, no recargar (salvo que se fuerce selección en edición)
    if (catId === lastLoadedCategoryId && subSelect.options.length > 1 && selectedSubcatId === null) {
        return;
    }

    lastLoadedCategoryId = catId;
    subSelect.innerHTML = '<option value="0">Sin subcategoría</option>'; // Resetear y añadir opción por defecto
    if (!catId) return;

    try {
        const r = await fetch(`${baseUrl}/api/centro_inventarios.php?action=subcategorias&categoria_id=${catId}`);
        const d = await r.json();
        if (d.success && d.subcategorias) {
            d.subcategorias.forEach(s => {
                const option = document.createElement('option');
                option.value = s.id_subcategoria;
                option.textContent = s.nombre;
                subSelect.appendChild(option);
            });
            // Seleccionar la subcategoría si se proporcionó un ID
            if (selectedSubcatId !== null) {
                subSelect.value = selectedSubcatId;
            }
        }
    } catch (e) { console.error('Error cargando subcategorías:', e); }

    // Actualizar código sugerido cuando cambia categoría
    if (!modoManual) {
        actualizarCodigoSugerido();
    }
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

    // Obtener código según el modo
    let codigoFinal;
    if (modoManual) {
        const inputManual = document.getElementById('itemCodigoManual');
        codigoFinal = inputManual ? inputManual.value.trim().toUpperCase() : '';
    } else {
        codigoFinal = document.getElementById('itemCodigo').value.trim().toUpperCase();
    }

    // Validaciones básicas
    if (!codigoFinal) {
        alert('⚠️ Debe generar o ingresar un código para el producto');
        return;
    }

    const nombre = document.getElementById('itemNombre').value.trim();
    if (!nombre) {
        alert('⚠️ Debe ingresar un nombre para el producto');
        return;
    }

    // Validar duplicados (solo al crear, no al editar)
    if (!id) {
        // Verificar código duplicado
        const codigoDuplicado = productosCompletos.find(p =>
            p.codigo && p.codigo.toUpperCase() === codigoFinal
        );

        if (codigoDuplicado) {
            alert(
                `❌ ERROR: Código duplicado\n\n` +
                `El código "${codigoFinal}" ya existe:\n` +
                `Producto: ${codigoDuplicado.nombre}\n\n` +
                `Por favor, use un código diferente o edite el producto existente.`
            );
            return;
        }

        // Verificar nombre duplicado
        const nombreDuplicado = productosCompletos.find(p =>
            p.nombre && p.nombre.trim().toUpperCase() === nombre.toUpperCase()
        );

        if (nombreDuplicado) {
            const confirmar = confirm(
                `⚠️ ADVERTENCIA: Nombre similar detectado\n\n` +
                `Ya existe un producto con nombre similar:\n` +
                `Código: ${nombreDuplicado.codigo}\n` +
                `Nombre: ${nombreDuplicado.nombre}\n\n` +
                `¿Desea continuar de todas formas?`
            );

            if (!confirmar) return;
        }
    }

    const catSelect = document.getElementById('itemCategoria');
    const subcatSelect = document.getElementById('itemSubcategoria');

    const data = {
        action: id ? 'update' : 'create',
        id_inventario: id || null,
        id_tipo_inventario: TIPO_ID,
        codigo: codigoFinal,
        nombre: nombre,
        id_categoria: catSelect ? catSelect.value : null,
        id_subcategoria: (subcatSelect && subcatSelect.value !== '0') ? subcatSelect.value : null,
        id_categoria: catSelect ? catSelect.value : null,
        id_subcategoria: (subcatSelect && subcatSelect.value !== '0') ? subcatSelect.value : null,
        id_unidad: document.getElementById('itemUnidad').value,
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
        alert('❌ Error al guardar el producto');
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
        const response = await fetch(`${BASE_URL_API}/ingresos_acc.php`, {
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

        const r = await fetch(`${BASE_URL_API}/salidas_acc.php?action=siguiente_numero&tipo=${tipo}`);
        const d = await r.json();

        if (d.success) {
            window.numerosSalidaCache[tipo] = d.numero;
            document.getElementById('salidaDocumento').value = d.numero;
            document.getElementById('salidaDocumento').disabled = false;
        }
    } catch (e) {
        console.error('Error al obtener número de salida:', e);
        const prefijos = {
            'PRODUCCION': 'OUT-ACC-P',
            'VENTA': 'OUT-ACC-V',
            'MUESTRAS': 'OUT-ACC-M',
            'AJUSTE': 'OUT-ACC-A',
            'DEVOLUCION': 'OUT-ACC-R'
        };
        const prefijo = prefijos[tipo] || 'OUT-ACC-X';
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
        Swal.fire('Atención', 'Agregue al menos una línea', 'warning');
        return;
    }

    // Validar que todas las líneas tengan producto y cantidad
    for (let i = 0; i < lineasSalida.length; i++) {
        if (!lineasSalida[i].id_inventario) {
            Swal.fire('Atención', `Seleccione un producto en la línea ${i + 1}`, 'warning');
            return;
        }
        if (lineasSalida[i].cantidad <= 0) {
            Swal.fire('Atención', `Ingrese cantidad mayor a 0 en la línea ${i + 1}`, 'warning');
            return;
        }

        // Validar stock
        const prod = productosCompletos.find(p => p.id_inventario == lineasSalida[i].id_inventario);
        const stockDisp = prod ? toNum(prod.stock_actual) : 0;
        if (lineasSalida[i].cantidad > stockDisp) {
            Swal.fire({
                icon: 'error',
                title: 'Stock Insuficiente',
                html: `Producto: <strong>${prod.codigo} - ${prod.nombre}</strong><br>` +
                    `Solicitado: <strong>${formatNum(lineasSalida[i].cantidad)}</strong><br>` +
                    `Disponible: <strong style="color:red">${formatNum(stockDisp)}</strong>`
            });
            return;
        }
    }

    // Validar motivo para ajustes
    if (tipo === 'AJUSTE' && !document.getElementById('salidaObservaciones').value.trim()) {
        Swal.fire('Atención', 'El motivo es obligatorio para ajustes de inventario', 'warning');
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
        const r = await fetch(`${BASE_URL_API}/salidas_acc.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const d = await r.json();
        console.log('Respuesta:', d);

        if (d.success) {
            await Swal.fire({
                icon: 'success',
                title: '¡Salida Registrada!',
                text: d.message,
                timer: 2000,
                showConfirmButton: false
            });
            cerrarModal('modalSalida');
            cargarDatos();
        } else {
            Swal.fire('Error', d.message, 'error');
        }
    } catch (e) {
        console.error('Error:', e);
        Swal.fire('Error', 'Error al guardar la salida', 'error');
    }
}

// ========== MODAL HISTORIAL ==========
async function abrirModalHistorial() {
    // Fechas por defecto: Últimos 30 días
    const hoy = new Date();
    const hace30dias = new Date();
    hace30dias.setDate(hoy.getDate() - 30);

    document.getElementById('historialFechaDesde').value = hace30dias.toISOString().split('T')[0];
    document.getElementById('historialFechaHasta').value = hoy.toISOString().split('T')[0];
    document.getElementById('historialTipoMov').value = '';
    document.getElementById('historialBuscar').value = '';

    await cargarHistorial();
    document.getElementById('modalHistorial').classList.add('show');
}

async function cargarHistorial() {
    const desde = document.getElementById('historialFechaDesde').value;
    const hasta = document.getElementById('historialFechaHasta').value;
    const tipo = document.getElementById('historialTipoMov').value;
    const buscar = document.getElementById('historialBuscar').value;
    const body = document.getElementById('historialBody');

    body.innerHTML = '<tr><td colspan="7" style="text-align:center;"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>';

    try {
        let url = `${baseUrl}/api/centro_inventarios.php?action=historial&tipo_id=${TIPO_ID}`;
        if (desde) url += `&fecha_desde=${desde}`;
        if (hasta) url += `&fecha_hasta=${hasta}`;
        if (tipo) url += `&tipo_mov=${tipo}`;
        if (buscar) url += `&buscar=${encodeURIComponent(buscar)}`;

        const r = await fetch(url);
        const d = await r.json();

        if (d.success && d.documentos) {
            renderHistorial(d.documentos);
        } else {
            body.innerHTML = '<tr><td colspan="7" style="text-align:center;">No se encontraron movimientos</td></tr>';
        }
    } catch (e) {
        console.error('Error historial:', e);
        body.innerHTML = '<tr><td colspan="7" style="text-align:center;color:red;">Error al cargar historial</td></tr>';
    }
}

function renderHistorial(docs) {
    const body = document.getElementById('historialBody');
    if (docs.length === 0) {
        body.innerHTML = '<tr><td colspan="7" style="text-align:center;">No hay movimientos en este rango</td></tr>';
        return;
    }

    body.innerHTML = docs.map(d => {
        let badgeClass = 'secondary';
        if (d.categoria === 'ENTRADA') badgeClass = 'success';
        else if (d.categoria === 'SALIDA') badgeClass = 'danger';
        else if (d.categoria === 'DEVOLUCION') badgeClass = 'warning';

        const estadoBadge = d.estado === 'ANULADO'
            ? '<span class="badge badge-danger">ANULADO</span>'
            : '<span class="badge badge-success">ACTIVO</span>';

        return `
            <tr>
                <td>${d.fecha}</td>
                <td><span class="badge badge-${badgeClass}">${d.tipo_movimiento}</span></td>
                <td><strong>${d.documento_numero}</strong><br><small>${d.documento_tipo}</small></td>
                <td>${d.usuario || '-'}</td>
                <td style="text-align:center;">${d.total_lineas}</td>
                <td style="text-align:right;">Bs. ${formatNum(d.total_documento)}</td>
                <td>${estadoBadge}</td>
                <td style="text-align:center;">
                    <button class="btn-icon" onclick="verDetalleDocumento('${d.documento_numero}')" title="Ver Detalle">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${d.estado !== 'ANULADO' ?
                `<button class="btn-icon" style="background:#dc3545;color:white;" onclick="anularDocumento('${d.documento_numero}')" title="Anular">
                            <i class="fas fa-ban"></i>
                        </button>` : ''}
                </td>
            </tr>`;
    }).join('');
}

// ========== DETALLE DE DOCUMENTO ==========
async function verDetalleDocumento(docNumero) {
    try {
        const r = await fetch(`${baseUrl}/api/centro_inventarios.php?action=documento_detalle&documento=${encodeURIComponent(docNumero)}`);
        const d = await r.json();

        if (d.success) {
            mostrarDetalleDocumento(d.cabecera, d.lineas);
        } else {
            Swal.fire('Error', d.message || 'No se pudo cargar el detalle', 'error');
        }
    } catch (e) {
        console.error('Error cargando detalle:', e);
        Swal.fire('Error', 'Error de conexión al cargar detalle', 'error');
    }
}

function mostrarDetalleDocumento(doc, detalle) {
    const contenedor = document.getElementById('detalleContenido');

    // Color según tipo
    let color = '#6c757d';
    if (doc.tipo_movimiento.includes('ENTRADA')) color = '#28a745';
    else if (doc.tipo_movimiento.includes('SALIDA')) color = '#dc3545';
    else if (doc.tipo_movimiento.includes('DEVOLUCION')) color = '#ffc107';

    let html = `
        <div style="background:#f8f9fa; padding:15px; border-radius:8px; margin-bottom:20px; border-left:5px solid ${color}">
            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:10px;">
                <div>
                    <strong>Documento:</strong> <span style="font-size:1.1em; color:${color}">${doc.documento_numero}</span><br>
                    <strong>Tipo:</strong> ${doc.documento_tipo} (${doc.tipo_movimiento})<br>
                    <strong>Fecha:</strong> ${doc.fecha}
                </div>
                <div style="text-align:right;">
                    <strong>Total:</strong> <span style="font-size:1.2em; font-weight:bold;">Bs. ${formatNum(doc.total_documento)}</span><br>
                    <strong>Estado:</strong> ${doc.estado === 'ANULADO' ? '<span style="color:red;font-weight:bold">ANULADO</span>' : '<span style="color:green;font-weight:bold">ACTIVO</span>'}<br>
                    <strong>Usuario:</strong> ${doc.usuario || '-'}
                </div>
            </div>
            ${doc.observaciones ? `<div style="margin-top:10px; border-top:1px solid #ddd; padding-top:5px;"><strong>Obs:</strong> ${doc.observaciones}</div>` : ''}
        </div>

        <h5><i class="fas fa-list"></i> Detalle de Items</h5>
        <div style="overflow-x:auto">
            <table class="table-custom" style="font-size:0.9rem;">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th style="text-align:right">Cant.</th>
                        <th style="text-align:right">Costo U.</th>
                        <th style="text-align:right">Total</th>
                    </tr>
                </thead>
                <tbody>
    `;

    html += detalle.map(item => `
        <tr>
            <td>${item.producto_codigo}</td>
            <td>${item.producto_nombre}</td>
            <td style="text-align:right">${formatNum(item.cantidad)} ${item.unidad || ''}</td>
            <td style="text-align:right">Bs. ${formatNum(item.costo_unitario)}</td>
            <td style="text-align:right; font-weight:bold;">Bs. ${formatNum(item.costo_total)}</td>
        </tr>
    `).join('');

    html += `
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align:right; font-weight:bold;">TOTAL</td>
                        <td style="text-align:right; font-weight:bold; font-size:1.1em;">Bs. ${formatNum(doc.total_documento)}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    `;

    contenedor.innerHTML = html;

    // Configurar botón de anular en el modal
    const btnAnular = document.getElementById('btnAnularDetalle');
    if (doc.estado === 'ACTIVO' || doc.estado === 'CONFIRMADO') {
        btnAnular.style.display = 'inline-block';
        btnAnular.onclick = () => anularDocumento(doc.documento_numero);
    } else {
        btnAnular.style.display = 'none';
    }

    document.getElementById('modalDetalle').classList.add('show');
}

async function anularDocumento(docNumero) {
    const { value: motivo } = await Swal.fire({
        title: '¿Anular Documento?',
        text: "Esta acción revertirá los movimientos de inventario. Ingrese el motivo:",
        input: 'text',
        inputPlaceholder: 'Motivo de anulación...',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, anular',
        inputValidator: (value) => {
            if (!value) return 'El motivo es obligatorio';
        }
    });

    if (motivo) {
        try {
            const r = await fetch(`${baseUrl}/api/centro_inventarios.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'anular_documento',
                    documento: docNumero,
                    motivo: motivo
                })
            });
            const d = await r.json();

            if (d.success) {
                Swal.fire('¡Anulado!', d.message, 'success');
                cerrarModal('modalDetalle');
                cargarHistorial(); // Refrescar lista
                cargarDatos(); // Refrescar KPIs
            } else {
                Swal.fire('Error', d.message, 'error');
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'Error al procesar la anulación', 'error');
        }
    }
}

// ========== KARDEX ==========

let kardexActual = {
    idInventario: null,
    nombreProducto: null,
    desde: null,
    hasta: null,
    tipoVista: 'valorado' // 'fisico' o 'valorado'
};

function verKardex(id) {
    const prod = productosCompletos.find(p => p.id_inventario == id);
    const nombre = prod ? prod.nombre : 'Producto ' + id;
    abrirKardex(id, nombre);
}

/**
 * Abrir modal de kardex para un producto específico
 */
function abrirKardex(idInventario, nombreProducto) {
    kardexActual.idInventario = idInventario;
    kardexActual.nombreProducto = nombreProducto;
    kardexActual.tipoVista = 'valorado'; // Por defecto mostrar valorado

    // Establecer rango de fechas por defecto (últimos 3 meses)
    const hoy = new Date();
    const hace3Meses = new Date(hoy.getFullYear(), hoy.getMonth() - 3, 1);

    document.getElementById('kardexDesde').value = hace3Meses.toISOString().split('T')[0];
    document.getElementById('kardexHasta').value = hoy.toISOString().split('T')[0];

    // Actualizar título del modal
    const titleElem = document.getElementById('kardexProducto');
    if (titleElem) {
        titleElem.textContent = nombreProducto;
    }

    // Activar pestaña valorado por defecto
    cambiarTabKardex('valorado');

    // Cargar datos
    buscarKardex();

    // Mostrar modal
    document.getElementById('modalKardex').classList.add('show');
}

/**
 * Cambiar entre pestañas de kardex
 */
function cambiarTabKardex(tipo) {
    kardexActual.tipoVista = tipo;

    // Actualizar pestañas activas
    document.querySelectorAll('.kardex-tab').forEach(tab => {
        tab.classList.remove('active');
        tab.style.background = '#e9ecef';
        tab.style.color = '#495057';
    });

    const activeTab = document.querySelector(`.kardex-tab[data-tipo="${tipo}"]`);
    if (activeTab) {
        activeTab.classList.add('active');
        activeTab.style.background = '#1a237e';
        activeTab.style.color = 'white';
    }

    // Mostrar/ocultar columnas según el tipo
    const thead = document.getElementById('kardexTableHead');
    if (tipo === 'fisico') {
        // Kardex Físico - ocultar columnas de valores
        thead.innerHTML = `
            <tr>
                <th width="120">Fecha</th>
                <th>Documento</th>
                <th width="100">Entrada</th>
                <th width="100">Salida</th>
                <th width="100">Saldo</th>
                <th>Observaciones</th>
            </tr>
        `;
    } else {
        // Kardex Valorado - mostrar todas las columnas
        thead.innerHTML = `
            <tr>
                <th width="110">Fecha</th>
                <th>Documento</th>
                <th width="90">Entrada</th>
                <th width="90">Salida</th>
                <th width="90">Saldo</th>
                <th width="100">Costo Aplic.</th>
                <th width="110">Val. Entrada</th>
                <th width="110">Val. Salida</th>
                <th width="110">Saldo Valor</th>
                <th width="90">CPP</th>
            </tr>
        `;
    }

    // Re-renderizar con los datos actuales
    if (window.ultimosKardexDatos) {
        renderKardex(window.ultimosKardexDatos);
    }
}

/**
 * Buscar kardex con filtros aplicados
 */
async function buscarKardex() {
    const desde = document.getElementById('kardexDesde').value;
    const hasta = document.getElementById('kardexHasta').value;

    if (!desde || !hasta) {
        Swal.fire('Atención', 'Seleccione rango de fechas', 'warning');
        return;
    }

    if (new Date(desde) > new Date(hasta)) {
        Swal.fire('Atención', 'La fecha inicial no puede ser mayor a la fecha final', 'warning');
        return;
    }

    kardexActual.desde = desde;
    kardexActual.hasta = hasta;

    try {
        // Mostrar loading
        document.getElementById('kardexBody').innerHTML = `
            <tr>
                <td colspan="9" style="text-align:center; padding:20px;">
                    <i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i>
                    <p>Cargando kardex...</p>
                </td>
            </tr>
        `;

        const params = new URLSearchParams({
            action: 'get',
            id_inventario: kardexActual.idInventario,
            desde: desde,
            hasta: hasta
        });

        // Usar la API genérica de kardex (compatible con ID global)
        const response = await fetch(`${baseUrl}/api/kardex_mp.php?${params}`);
        const data = await response.json();

        if (data.success) {
            window.ultimosKardexDatos = data; // Guardar para cambio de pestañas
            renderKardex(data);
        } else {
            Swal.fire('Error', data.message, 'error');
            document.getElementById('kardexBody').innerHTML = `<tr><td colspan="9" style="text-align:center;color:red;">${data.message}</td></tr>`;
        }
    } catch (e) {
        console.error('Error al cargar kardex:', e);
        Swal.fire('Error', 'Error al cargar el kardex', 'error');
    }
}

/**
 * Renderizar tabla de kardex según el tipo de vista
 */
function renderKardex(data) {
    const tbody = document.getElementById('kardexBody');
    const { producto, saldo_inicial, movimientos } = data;

    let html = '';

    // Renderizar según el tipo de vista
    if (kardexActual.tipoVista === 'fisico') {
        // KARDEX FÍSICO
        if (saldo_inicial) {
            html += `
                <tr class="saldo-inicial">
                    <td colspan="2" style="text-align:center; font-weight:700; background:#f5f5f5;">
                        <i class="fas fa-flag"></i> SALDO INICIAL
                    </td>
                    <td style="text-align:right;">-</td>
                    <td style="text-align:right;">-</td>
                    <td style="text-align:right; font-weight:700; background:#e8f5e9;">
                        ${formatNum(saldo_inicial.cantidad, 2)}
                    </td>
                    <td></td>
                </tr>
            `;
        }

        if (movimientos.length === 0) {
            html += `
                <tr>
                    <td colspan="6" style="text-align:center; padding:30px; color:#6c757d;">
                        <i class="fas fa-inbox" style="font-size:2rem; margin-bottom:10px; display:block;"></i>
                        No hay movimientos en este período
                    </td>
                </tr>
            `;
        } else {
            movimientos.forEach(mov => {
                const esEntrada = mov.cantidad_entrada > 0;
                const rowClass = esEntrada ? 'entrada' : 'salida';
                const fecha = new Date(mov.fecha).toLocaleDateString('es-BO', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit'
                });

                html += `
                    <tr class="${rowClass}">
                        <td style="font-size:0.85rem;">${fecha}</td>
                        <td style="font-weight:600;">${mov.documento || '-'}</td>
                        <td style="text-align:right; color:#2e7d32; font-weight:600;">
                            ${formatNum(mov.cantidad_entrada, 2)}
                        </td>
                        <td style="text-align:right; color:#c62828; font-weight:600;">
                            ${formatNum(mov.cantidad_salida, 2)}
                        </td>
                        <td style="text-align:right; font-weight:700; background:#e8f5e9;">
                            ${formatNum(mov.saldo_cantidad, 2)}
                        </td>
                        <td style="font-size:0.8rem; color:#666;">
                            ${mov.observaciones || ''}
                        </td>
                    </tr>
                `;
            });
        }

        // Resumen final para kardex físico
        if (movimientos.length > 0) {
            const totalEntradas = movimientos.reduce((sum, m) => sum + m.cantidad_entrada, 0);
            const totalSalidas = movimientos.reduce((sum, m) => sum + m.cantidad_salida, 0);
            const ultimoMov = movimientos[movimientos.length - 1];

            html += `
                <tr style="background:#263238; color:white; font-weight:700;">
                    <td colspan="2">TOTALES DEL PERÍODO</td>
                    <td style="text-align:right;">${formatNum(totalEntradas, 2)}</td>
                    <td style="text-align:right;">${formatNum(totalSalidas, 2)}</td>
                    <td style="text-align:right; background:#1b5e20;">
                        ${formatNum(ultimoMov.saldo_cantidad, 2)}
                    </td>
                    <td></td>
                </tr>
            `;
        }

    } else {
        // KARDEX VALORADO
        if (saldo_inicial) {
            html += `
                <tr class="saldo-inicial">
                    <td colspan="2" style="text-align:center; font-weight:700; background:#f5f5f5;">
                        <i class="fas fa-flag"></i> SALDO INICIAL
                    </td>
                    <td style="text-align:right;">-</td>
                    <td style="text-align:right;">-</td>
                    <td style="text-align:right; font-weight:700; background:#e8f5e9;">
                        ${formatNum(saldo_inicial.cantidad, 2)}
                    </td>
                    <td style="text-align:right;">-</td>
                    <td style="text-align:right;">-</td>
                    <td style="text-align:right;">-</td>
                    <td style="text-align:right; font-weight:700; background:#fff3e0;">
                        Bs. ${formatNum(saldo_inicial.valor_total, 2)}
                    </td>
                    <td style="text-align:right; font-weight:700; background:#e3f2fd;">
                        Bs. ${formatNum(saldo_inicial.cpp, 4)}
                    </td>
                </tr>
            `;
        }

        if (movimientos.length === 0) {
            html += `
                <tr>
                    <td colspan="9" style="text-align:center; padding:30px; color:#6c757d;">
                        <i class="fas fa-inbox" style="font-size:2rem; margin-bottom:10px; display:block;"></i>
                        No hay movimientos en este período
                    </td>
                </tr>
            `;
        } else {
            movimientos.forEach(mov => {
                const esEntrada = mov.cantidad_entrada > 0;
                const rowClass = esEntrada ? 'entrada' : 'salida';
                const fecha = new Date(mov.fecha).toLocaleDateString('es-BO', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit'
                });

                html += `
                    <tr class="${rowClass}">
                        <td style="font-size:0.85rem;">${fecha}</td>
                        <td style="font-weight:600;">${mov.documento || '-'}</td>
                        <td style="text-align:right;">
                            ${formatNum(mov.cantidad_entrada, 2)}
                        </td>
                        <td style="text-align:right;">
                            ${formatNum(mov.cantidad_salida, 2)}
                        </td>
                        <td style="text-align:right; font-weight:600; background:#e8f5e9;">
                            ${formatNum(mov.saldo_cantidad, 2)}
                        </td>
                        <td style="text-align:right; font-size:0.85rem; background:#f5f5f5;">
                            Bs. ${formatNum(mov.costo_unitario, 4)}
                        </td>
                        <td style="text-align:right; color:#2e7d32;">
                            ${mov.valor_entrada > 0 ? 'Bs. ' + formatNum(mov.valor_entrada, 2) : '-'}
                        </td>
                        <td style="text-align:right; color:#c62828;">
                            ${mov.valor_salida > 0 ? 'Bs. ' + formatNum(mov.valor_salida, 2) : '-'}
                        </td>
                        <td style="text-align:right; font-weight:600; background:#fff3e0;">
                            Bs. ${formatNum(mov.saldo_valor, 2)}
                        </td>
                        <td style="text-align:right; font-weight:600; background:#e3f2fd;">
                            Bs. ${formatNum(mov.cpp, 4)}
                        </td>
                    </tr>
                `;
            });
        }

        // Resumen final para kardex valorado
        if (movimientos.length > 0) {
            const totalEntradas = movimientos.reduce((sum, m) => sum + m.cantidad_entrada, 0);
            const totalSalidas = movimientos.reduce((sum, m) => sum + m.cantidad_salida, 0);
            const totalValorEntradas = movimientos.reduce((sum, m) => sum + m.valor_entrada, 0);
            const totalValorSalidas = movimientos.reduce((sum, m) => sum + m.valor_salida, 0);
            const ultimoMov = movimientos[movimientos.length - 1];

            html += `
                <tr style="background:#263238; color:white; font-weight:700;">
                    <td colspan="2">TOTALES DEL PERÍODO</td>
                    <td style="text-align:right;">${formatNum(totalEntradas, 2)}</td>
                    <td style="text-align:right;">${formatNum(totalSalidas, 2)}</td>
                    <td style="text-align:right; background:#1b5e20;">
                        ${formatNum(ultimoMov.saldo_cantidad, 2)}
                    </td>
                    <td style="text-align:right; background:#37474f;">-</td>
                    <td style="text-align:right;">Bs. ${formatNum(totalValorEntradas, 2)}</td>
                    <td style="text-align:right;">Bs. ${formatNum(totalValorSalidas, 2)}</td>
                    <td style="text-align:right; background:#e65100;">
                        Bs. ${formatNum(ultimoMov.saldo_valor, 2)}
                    </td>
                    <td style="text-align:right; background:#0d47a1;">
                        Bs. ${formatNum(ultimoMov.cpp, 4)}
                    </td>
                </tr>
            `;
        }
    }

    tbody.innerHTML = html;
}

/**
 * Imprimir kardex
 */
function imprimirKardex() {
    const contenido = document.getElementById('modalKardex').cloneNode(true);
    const nombreProd = kardexActual.nombreProducto || 'Kardex';

    // Crear ventana de impresión
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Kardex - ${nombreProd}</title>
            <style>
                body { font-family: Arial, sans-serif; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background: #f2f2f2; }
                .entrada { background: #e8f5e9; }
                .salida { background: #ffebee; }
                @media print {
                    button { display: none; }
                }
            </style>
        </head>
        <body>
            <h2>Kardex ${kardexActual.tipoVista === 'fisico' ? 'Físico' : 'Valorado'}</h2>
            <h3>${nombreProd}</h3>
            <p>Período: ${kardexActual.desde} al ${kardexActual.hasta}</p>
            ${contenido.querySelector('.kardex-table').outerHTML}
        </body>
        </html>
    `);

    ventana.document.close();
    ventana.print();
}

/**
 * Exportar kardex a Excel
 */
function exportarKardex() {
    Swal.fire('Información', 'Función de exportación en desarrollo', 'info');
}

/**
 * Función para recalcular el kardex (corregir valores)
 */
async function recalcularKardex(idInventario) {
    const result = await Swal.fire({
        title: '¿Recalcular Kardex?',
        text: "Esto actualizará los costos y CPP de todos los movimientos. Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, recalcular'
    });

    if (!result.isConfirmed) return;

    try {
        // Usar la API genérica de kardex
        const response = await fetch(`${baseUrl}/api/kardex_mp.php?action=recalcular&id_inventario=${idInventario}`);
        const data = await response.json();

        if (data.success) {
            Swal.fire('Éxito', `${data.message}\nCPP Final: Bs. ${formatNum(data.cpp_final, 4)}`, 'success');
            // Recargar el kardex
            if (kardexActual.idInventario) {
                buscarKardex();
            }
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (e) {
        console.error('Error al recalcular:', e);
        Swal.fire('Error', 'Error al recalcular el kardex', 'error');
    }
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

// ========== FUNCIONES DE GENERACIÓN DE CÓDIGO ==========

/**
 * Actualiza el código sugerido cuando cambia categoría o subcategoría
 */
async function actualizarCodigoSugerido() {
    const catId = document.getElementById('itemCategoria').value;

    // Cargar subcategorías si cambió la categoría
    await cargarSubcategoriasItem();

    const subcatId = document.getElementById('itemSubcategoria').value;

    if (!catId) {
        document.getElementById('codigoPreviewSection').style.display = 'none';
        document.getElementById('sufijoPersonalizadoRow').style.display = 'none';
        return;
    }

    // Mostrar sección de preview
    document.getElementById('codigoPreviewSection').style.display = 'block';
    document.getElementById('sufijoPersonalizadoRow').style.display = 'flex';

    // Obtener códigos de categoría y subcategoría
    const categoria = categorias.find(c => c.id_categoria == catId);
    const codigoCat = categoria ? categoria.codigo : 'XXX';

    let codigoSubcat = '';
    if (subcatId && subcatId !== '0' && subcatId !== '') {
        // La subcategoría ya debería estar cargada en el select, pero necesitamos su código.
        // Podemos buscarla en el array global `subcategorias` si se cargaron todas, 
        // pero `cargarSubcategoriasItem` hace un fetch local. 
        // Mejor hacer fetch de nuevo o optimizar. 
        // Como `cargarSubcategoriasItem` no guarda en variable global accesible facilmente (solo llena select),
        // haremos fetch rápido para obtener código. OJO: Esto podría optimizarse.

        try {
            const rSub = await fetch(`${baseUrl}/api/centro_inventarios.php?action=subcategorias&categoria_id=${catId}`);
            const dSub = await rSub.json();
            if (dSub.success && dSub.subcategorias) {
                const sub = dSub.subcategorias.find(s => s.id_subcategoria == subcatId);
                codigoSubcat = sub ? sub.codigo : '';
                // Si el código ya incluye guiones, tomar la última parte
                if (codigoSubcat.includes('-')) {
                    const partes = codigoSubcat.split('-');
                    codigoSubcat = partes[partes.length - 1];
                }
            }
        } catch (e) { console.error(e); }
    }

    // Construir prefijo
    const prefijo = (subcatId && subcatId !== '0' && subcatId !== '' && codigoSubcat)
        ? `${codigoTipo}-${codigoCat}-${codigoSubcat}-`
        : `${codigoTipo}-${codigoCat}-`;

    // Obtener siguiente correlativo
    const siguienteNum = await obtenerSiguienteCorrelativo(prefijo);

    // Actualizar preview en UI
    document.getElementById('previewPrefijo').textContent = prefijo;
    document.getElementById('previewSufijo').textContent = siguienteNum;
    document.getElementById('labelTipo').textContent = codigoTipo;
    document.getElementById('labelCat').textContent = codigoCat;
    document.getElementById('labelSubcat').textContent = codigoSubcat || '-';
    document.getElementById('labelNum').textContent = siguienteNum;
    document.getElementById('formatoSugerido').textContent = prefijo + 'XXX';

    // Establecer sufijo por defecto
    document.getElementById('itemSufijo').value = siguienteNum;

    // Actualizar código final
    if (!modoManual) {
        actualizarCodigoFinal();
    }
}

/**
 * Obtiene el siguiente número correlativo disponible para un prefijo
 */
async function obtenerSiguienteCorrelativo(prefijo) {
    try {
        const response = await fetch(
            `${baseUrl}/api/centro_inventarios.php?action=siguiente_codigo&tipo_id=${TIPO_ID}&prefijo=${encodeURIComponent(prefijo)}`
        );
        const data = await response.json();
        return (data.success && data.siguiente) ? data.siguiente : '001';
    } catch (error) {
        console.error('Error obteniendo correlativo:', error);
        return '001';
    }
}

/**
 * Actualiza el código final combinando prefijo + sufijo
 */
function actualizarCodigoFinal() {
    const prefijo = document.getElementById('previewPrefijo').textContent;
    const sufijo = document.getElementById('itemSufijo').value || '001';
    const codigoFinal = prefijo + sufijo;

    document.getElementById('itemCodigo').value = codigoFinal.toUpperCase();
    document.getElementById('previewSufijo').textContent = sufijo;
    document.getElementById('labelNum').textContent = sufijo;
}

/**
 * Alternar entre modo automático y manual
 */
function toggleModoManual() {
    modoManual = !modoManual;
    const autoView = document.getElementById('codigoAutomaticoView');
    const manualView = document.getElementById('codigoManualView');
    const sufijoRow = document.getElementById('sufijoPersonalizadoRow');
    const btnTexto = document.getElementById('btnToggleTexto');
    const inputManual = document.getElementById('itemCodigoManual');

    if (modoManual) {
        autoView.style.display = 'none';
        manualView.style.display = 'block';
        sufijoRow.style.display = 'none';
        btnTexto.textContent = 'Usar automático';

        inputManual.value = document.getElementById('itemCodigo').value;
        inputManual.focus();

        // Actualizar el código oculto cuando se edita manualmente
        inputManual.addEventListener('input', function () {
            document.getElementById('itemCodigo').value = this.value.toUpperCase();
        });
    } else {
        autoView.style.display = 'block';
        manualView.style.display = 'none';
        sufijoRow.style.display = 'flex';
        btnTexto.textContent = 'Editar manualmente';
        actualizarCodigoFinal();
    }
}

console.log('✅ Módulo Accesorios de Confección v1.9 cargado');
console.log('   - Modal Ingreso mejorado v2.0');
console.log('   - Filtros por tipo proveedor y categoría');
console.log('   - Cálculo IVA con columnas dinámicas');
console.log('   - Costos con 4 decimales');