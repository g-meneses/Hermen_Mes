/**
 * JavaScript para mÃ³dulo Colorantes y Auxiliares QuÃ­micos  
 * Sistema MES Hermen Ltda. v1.0
 * Adaptado del mÃ³dulo Materias Primas
 */

const BASE_URL_API = window.location.origin + '/mes_hermen/api';
const baseUrl = window.location.origin + '/mes_hermen';
const iconTitle = document.querySelector('.mp-title-icon');
const TIPO_ID = (iconTitle && iconTitle.dataset && iconTitle.dataset.tipoId) || 2;

let categorias = [], subcategorias = [], productos = [], productosCompletos = [];
let unidades = [], proveedores = [];
let categoriaSeleccionada = null, subcategoriaSeleccionada = null;
let lineasIngreso = [];
let lineasSalida = [];
let documentoActual = null;
let productosFiltrados = [];
let modoConFactura = false;
let contadorDocIngreso = 0;

// ========== BLINDAJE DE UNICIDAD - Variables ==========
let codigoValidado = true; // Estado de validaciÃ³n del cÃ³digo
// modoManual se declara mÃ¡s abajo en el archivo original

// ========== BLINDAJE DE UNICIDAD - Funciones ==========
/**
 * Verificar si un cÃ³digo ya existe en la base de datos
 */
async function verificarCodigoDuplicado(codigo, excludeId = null) {
    if (!codigo || codigo.trim() === '') {
        actualizarBannerCodigo('loading', 'Seleccione categorÃ­a y subcategorÃ­a...');
        codigoValidado = false;
        actualizarEstadoBotonGuardar();
        return false;
    }

    actualizarBannerCodigo('loading', 'Verificando disponibilidad...');

    try {
        let url = `${baseUrl}/api/centro_inventarios.php?action=verificar_codigo&codigo=${encodeURIComponent(codigo)}`;
        if (excludeId && excludeId !== '') {
            url += `&exclude_id=${excludeId}`;
        }

        const r = await fetch(url);
        const d = await r.json();

        if (d.existe) {
            actualizarBannerCodigo('error', `âŒ ERROR: CÃ³digo duplicado detectado. Ya existe en: "${d.producto_existente}". Por favor, ajuste el sufijo.`);
            codigoValidado = false;
            actualizarEstadoBotonGuardar();
            return false;
        } else {
            actualizarBannerCodigo('ok', 'âœ… CÃ³digo disponible');
            codigoValidado = true;
            actualizarEstadoBotonGuardar();
            return true;
        }
    } catch (e) {
        console.error('Error verificando cÃ³digo:', e);
        actualizarBannerCodigo('error', 'âš ï¸ Error al verificar cÃ³digo');
        codigoValidado = false;
        actualizarEstadoBotonGuardar();
        return false;
    }
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

// ========== VARIABLES PARA GENERADOR DE CÃ“DIGOS ==========
let modoManual = false;
const codigoTipo = 'CAQ'; // Prefijo fijo para Colorantes y Aux. QuÃ­micos

// ========== FUNCIONES DE GENERADOR DE CÃ“DIGOS ==========
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

        // Copiar cÃ³digo actual al input manual
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
    const catSelect = document.getElementById('itemCategoria_modal');
    const subcatSelect = document.getElementById('itemSubcategoria_modal');

    if (!catSelect || !catSelect.value) {
        document.getElementById('codigoPreviewSection').style.display = 'none';
        document.getElementById('sufijoPersonalizadoRow').style.display = 'none';
        document.getElementById('itemCodigo').value = '';
        actualizarBannerCodigo('loading', 'Seleccione categorÃ­a...');
        codigoValidado = false;
        actualizarEstadoBotonGuardar();
        return;
    }

    // Mostrar preview y correlativo
    document.getElementById('codigoPreviewSection').style.display = 'block';
    document.getElementById('sufijoPersonalizadoRow').style.display = 'block';

    // Obtener cÃ³digos de categorÃ­a y subcategorÃ­a
    const catId = catSelect.value;
    const subcatId = subcatSelect ? subcatSelect.value : '';

    const cat = categorias.find(c => c.id_categoria == catId);

    // Generar cÃ³digo de categorÃ­a: usar codigo si existe, o generar abreviatura de 3 letras
    let catCodigo = cat?.codigo || '';
    if (!catCodigo && cat?.nombre) {
        catCodigo = cat.nombre.replace(/[^a-zA-Z]/g, '').substring(0, 3).toUpperCase() || 'CAT';
    }
    if (!catCodigo) catCodigo = 'CAT';

    let subcatCodigo = 'GEN';
    if (subcatId && subcatId !== '0') {
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

    // Actualizar labels
    document.getElementById('labelTipo').textContent = codigoTipo;
    document.getElementById('labelCat').textContent = catCodigo;
    document.getElementById('labelSubcat').textContent = subcatCodigo;

    // Construir prefijo para buscar siguiente correlativo
    const prefijo = `${codigoTipo}-${catCodigo}-${subcatCodigo}-`;

    // Obtener prÃ³ximo nÃºmero correlativo
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

    // Verificar disponibilidad usando el banner principal
    const itemId = document.getElementById('itemId').value;
    await verificarCodigoDuplicado(codigoFinal, itemId);
}

// Cache global para nÃºmeros de documento (accesible desde HTML onchange)
window.numerosSalidaCache = {};
window.numerosIngresoCache = {};

// ========== INICIALIZACIÃ“N ==========
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

// ========== CATEGORÃAS ==========
async function cargarCategorias() {
    try {
        const r = await fetch(`${baseUrl}/api/centro_inventarios.php?action=categorias&tipo_id=${TIPO_ID}`);
        const d = await r.json();
        console.log('Respuesta CategorÃ­as:', d);

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
    } catch (e) { console.error('Error categorÃ­as:', e); }
}

function renderCategorias() {
    const grid = document.getElementById('categoriasGrid');
    if (categorias.length === 0) {
        grid.innerHTML = '<p style="padding:20px;text-align:center;">No hay categorÃ­as</p>';
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
                    nombre: 'ðŸ“¦ Sin Clasificar',
                    total_items: sinSubcategoria.total_items,
                    valor_total: sinSubcategoria.valor_total,
                    alertas: sinSubcategoria.alertas
                });
            }

            const catData = categorias.find(c => c.id_categoria == idCategoria);
            subcategorias.unshift({
                id_subcategoria: -1,
                nombre: 'ðŸ‘ï¸ Ver Todos',
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
        console.error('Error subcategorÃ­as:', e);
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
        else if (stock <= stockMin) { estado = 'critico'; estadoTxt = 'CrÃ­tico'; }
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
                <button class="btn-icon eliminar" onclick="eliminarItem(${p.id_inventario}, '${p.nombre.replace(/'/g, "\\'")}')" title="Eliminar"><i class="fas fa-trash"></i></button>
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
    poblarSelects();
    document.getElementById('formItem').reset();
    document.getElementById('itemId').value = '';
    document.getElementById('modalItemTitulo').textContent = 'Nuevo Item de Colorante/QuÃ­mico';

    // Resetear generador de cÃ³digos
    modoManual = false;
    document.getElementById('codigoPreviewSection').style.display = 'none';
    document.getElementById('sufijoPersonalizadoRow').style.display = 'none';
    document.getElementById('codigoAutomaticoView').style.display = 'block';
    document.getElementById('codigoManualView').style.display = 'none';
    document.getElementById('itemCodigo').value = '';

    // ========== BLINDAJE: Resetear estado de validaciÃ³n ==========
    codigoValidado = false;
    ocultarBannerCodigo();
    actualizarEstadoBotonGuardar();

    // Listener para cÃ³digo manual
    const codigoManual = document.getElementById('itemCodigoManual');
    if (codigoManual) {
        codigoManual.addEventListener('blur', function () {
            verificarCodigoDuplicado(this.value.trim().toUpperCase(), document.getElementById('itemId').value);
        });
    }

    document.getElementById('modalItem').classList.add('show');
}

async function editarItem(id) {
    const item = productosCompletos.find(p => p.id_inventario == id);
    if (!item) { alert('âŒ No se encontrÃ³ el item'); return; }

    poblarSelects();
    document.getElementById('itemId').value = item.id_inventario;
    document.getElementById('itemCodigo').value = item.codigo || '';
    document.getElementById('itemNombre').value = item.nombre || '';
    document.getElementById('itemCategoria_modal').value = item.id_categoria || '';
    await cargarSubcategoriasItem();
    document.getElementById('itemSubcategoria_modal').value = item.id_subcategoria || '';
    document.getElementById('itemUnidad').value = item.id_unidad || '';
    document.getElementById('itemStockMinimo').value = item.stock_minimo || 0;
    document.getElementById('itemCosto').value = item.costo_unitario || item.costo_promedio || 0;
    document.getElementById('itemDescripcion').value = item.descripcion || '';
    document.getElementById('modalItemTitulo').textContent = 'Editar Item: ' + item.codigo;

    // En ediciÃ³n, mostrar en modo manual con cÃ³digo existente
    modoManual = true;
    document.getElementById('codigoPreviewSection').style.display = 'none';
    document.getElementById('codigoAutomaticoView').style.display = 'none';
    document.getElementById('codigoManualView').style.display = 'block';
    document.getElementById('itemCodigoManual').value = item.codigo || '';
    document.getElementById('sufijoPersonalizadoRow').style.display = 'none';

    // ========== BLINDAJE: En ediciÃ³n, cÃ³digo ya existe - permitir guardar ==========
    codigoValidado = true;
    actualizarBannerCodigo('ok', 'âœ… Editando item existente: ' + item.codigo);
    actualizarEstadoBotonGuardar();

    document.getElementById('modalItem').classList.add('show');
}

async function cargarSubcategoriasItem() {
    const catId = document.getElementById('itemCategoria_modal').value;
    const subSelect = document.getElementById('itemSubcategoria_modal');

    // ðŸ” DEBUG: Ver quÃ© categorÃ­a se seleccionÃ³
    console.log('ðŸ” Cargando subcategorÃ­as para categorÃ­a ID:', catId);

    const valorActual = subSelect.value;
    subSelect.innerHTML = '<option value="0">Sin subcategorÃ­a</option>';

    if (!catId) {
        console.log('âš ï¸ No hay categorÃ­a seleccionada');
        return;
    }

    try {
        const url = `${baseUrl}/api/centro_inventarios.php?action=subcategorias&categoria_id=${catId}`;
        console.log('ðŸ” URL llamada:', url);

        const r = await fetch(url);
        const d = await r.json();

        console.log('ðŸ” Respuesta completa del API:', d);

        if (d.success && d.subcategorias && d.subcategorias.length > 0) {
            console.log('ðŸ” Total subcategorÃ­as encontradas:', d.subcategorias.length);

            // Construir opciones con LOG detallado
            d.subcategorias.forEach((s, index) => {
                console.log(`  ðŸ“¦ SubcategorÃ­a ${index + 1}:`);
                console.log(`     - ID: ${s.id_subcategoria} (tipo: ${typeof s.id_subcategoria})`);
                console.log(`     - CÃ³digo: ${s.codigo || 'N/A'}`);
                console.log(`     - Nombre: ${s.nombre}`);

                const optionHTML = `<option value="${s.id_subcategoria}">${s.nombre}</option>`;
                console.log(`     - HTML generado: ${optionHTML}`);

                subSelect.innerHTML += optionHTML;
            });

            console.log('ðŸ” Total opciones en select despuÃ©s de agregar:', subSelect.options.length);

            // Mostrar todas las opciones finales
            console.log('ðŸ” VerificaciÃ³n final de opciones:');
            for (let i = 0; i < subSelect.options.length; i++) {
                console.log(`     OpciÃ³n ${i}: value="${subSelect.options[i].value}" text="${subSelect.options[i].text}"`);
            }

            // Restaurar valor si existe
            if (valorActual && valorActual !== '0') {
                subSelect.value = valorActual;
                console.log('ðŸ” Intentando restaurar valor:', valorActual);
                console.log('ðŸ” Valor actual despuÃ©s de restaurar:', subSelect.value);
            }
        } else {
            console.log('âš ï¸ No se encontraron subcategorÃ­as');
            console.log('   - success:', d.success);
            console.log('   - subcategorias existe:', !!d.subcategorias);
            console.log('   - subcategorias.length:', d.subcategorias ? d.subcategorias.length : 'N/A');
        }
    } catch (e) {
        console.error('âŒ Error cargando subcategorÃ­as:', e);
    }

    // Actualizar cÃ³digo sugerido cuando cambia categorÃ­a
    if (!modoManual) {
        actualizarCodigoSugerido();
    }
}

function poblarSelects() {
    const catSelect = document.getElementById('itemCategoria_modal');
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

    // ========== BLINDAJE: Verificar validaciÃ³n de cÃ³digo ==========
    if (!codigoValidado && !id) {
        Swal.fire('Error', 'âŒ El cÃ³digo no ha sido validado o estÃ¡ duplicado. Verifique antes de guardar.', 'error');
        return;
    }

    const data = {
        action: id ? 'update' : 'create',
        id_inventario: id || null,
        id_tipo_inventario: TIPO_ID,
        codigo: document.getElementById('itemCodigo').value,
        nombre: document.getElementById('itemNombre').value,
        id_categoria: document.getElementById('itemCategoria_modal').value,
        id_subcategoria: document.getElementById('itemSubcategoria_modal').value || null,
        id_unidad: document.getElementById('itemUnidad').value,
        stock_actual: 0,
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
            Swal.fire({
                icon: 'success',
                title: 'Â¡Ã‰xito!',
                text: d.message,
                confirmButtonColor: '#28a745'
            }).then(() => {
                cerrarModal('modalItem');
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'âš ï¸ Advertencia',
                text: d.message,
                confirmButtonColor: '#dc3545'
            });
        }
    } catch (e) {
        console.error('Error:', e);
        Swal.fire({
            icon: 'error',
            title: 'âš ï¸ Advertencia',
            text: 'Error al guardar',
            confirmButtonColor: '#dc3545'
        });
    }
}

async function eliminarItem(id, nombre) {
    const result = await Swal.fire({
        title: 'âš ï¸ Advertencia',
        text: `Â¿EstÃ¡ seguro de eliminar "${nombre}"? Esta acciÃ³n no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'SÃ­, eliminar',
        cancelButtonText: 'Cancelar'
    });

    if (!result.isConfirmed) return;

    try {
        const r = await fetch(`${baseUrl}/api/centro_inventarios.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_inventario: id })
        });
        const d = await r.json();
        if (d.success) {
            Swal.fire({
                icon: 'success',
                title: 'Â¡Eliminado!',
                text: d.message,
                confirmButtonColor: '#28a745'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'âš ï¸ Advertencia',
                text: d.message,
                confirmButtonColor: '#dc3545'
            });
        }
    } catch (e) {
        console.error('Error:', e);
        Swal.fire({
            icon: 'error',
            title: 'âš ï¸ Advertencia',
            text: 'Error al eliminar',
            confirmButtonColor: '#dc3545'
        });
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
    document.getElementById('ingresoDocumento').value = `ING-CAQ-${fecha}-${numero}`;
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
    document.getElementById('infoProveedorTipo').textContent = tipo === 'LOCAL' ? 'ðŸ‡§ðŸ‡´ LOCAL' : 'ðŸŒŽ IMPORTACIÃ“N';

    document.getElementById('infoProveedorMoneda').className = `badge-moneda ${moneda === 'USD' ? 'usd' : 'bob'}`;
    document.getElementById('infoProveedorMoneda').textContent = moneda || 'BOB';

    document.getElementById('infoProveedorPago').textContent = `Pago: ${pago || 'N/A'}`;
    box.style.display = 'flex';
}

function poblarFiltrosCategorias() {
    const selectCat = document.getElementById('ingresoFiltroCat');
    selectCat.innerHTML = '<option value="">Todas las categorÃ­as</option>' +
        categorias.map(c => `<option value="${c.id_categoria}">${c.nombre}</option>`).join('');
    document.getElementById('ingresoFiltroSubcat').innerHTML = '<option value="">Todas las subcategorÃ­as</option>';
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
                    '<option value="">Todas las subcategorÃ­as</option>' +
                    d.subcategorias.map(s => `<option value="${s.id_subcategoria}">${s.nombre}</option>`).join('');

                // CORRECCIÃ“N: Restaurar el valor seleccionado despuÃ©s de recargar opciones
                if (subcatId) {
                    selectSubcat.value = subcatId;
                }
            }
        } catch (e) { console.error(e); }
    } else {
        document.getElementById('ingresoFiltroSubcat').innerHTML = '<option value="">Todas las subcategorÃ­as</option>';
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
        console.warn('âš ï¸ No hay tipo de ingreso seleccionado');
        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; padding:20px;">Seleccione un tipo de ingreso</td></tr>';
        return;
    }

    // Obtener configuraciÃ³n de columnas
    const configColumnas = obtenerConfiguracionColumnas();
    if (!configColumnas) {
        console.error('âŒ No se pudo obtener configuraciÃ³n de columnas');
        return;
    }

    const columnas = configColumnas.columnas;
    console.log('ðŸ“‹ Renderizando con', columnas.length, 'columnas para tipo:', tipoIngresoActual.codigo);

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

    // 2. Si no hay lÃ­neas, agregar una
    if (lineasIngreso.length === 0) {
        agregarLineaIngreso();
        return;
    }

    // 3. RENDERIZAR FILAS (tbody) segÃºn el tipo de ingreso
    tbody.innerHTML = lineasIngreso.map((l, i) => {
        const prod = productosCompletos.find(p => p.id_inventario == l.id_inventario);
        const unidad = prod ? (prod.unidad_abrev || prod.abreviatura || prod.unidad || 'kg') : '-';
        const cantidad = toNum(l.cantidad);
        const valorTotal = toNum(l.valor_total_item);

        // Estilo comÃºn para inputs
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
        // TIPO: DEVOLUCIÃ“N DE PRODUCCIÃ“N
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
// NUEVAS FUNCIONES DE CÃLCULO POR TIPO
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
 * Calcular para DEVOLUCIÃ“N DE PRODUCCIÃ“N
 * Usuario ingresa: Cantidad
 * Sistema usa: Costo Promedio del producto (readonly)
 * Sistema calcula: Valor Total
 */
function calcularLineaIngresoDevolucion(index) {
    const cantidad = toNum(document.getElementById(`ingCant_${index}`).value);

    console.log('ðŸ”¢ Cantidad ingresada:', cantidad);

    lineasIngreso[index].cantidad = cantidad;

    // Obtener costo promedio del producto seleccionado
    const idProd = lineasIngreso[index].id_inventario;

    if (!idProd) {
        console.warn('âš ï¸ Seleccione un producto primero');
        return;
    }

    const prod = productosCompletos.find(p => p.id_inventario == idProd);
    const costoPromedio = prod ? (toNum(prod.costo_promedio) || toNum(prod.costo_unitario)) : 0;

    console.log(`ðŸ“Š Producto: ${prod?.nombre || 'N/A'}`);
    console.log(`ðŸ’° CPP usado: ${costoPromedio}`);

    lineasIngreso[index].costo_unitario = costoPromedio;
    lineasIngreso[index].valor_total_item = cantidad * costoPromedio;

    console.log(`ðŸ’µ Valor Total = ${cantidad} Ã— ${costoPromedio} = ${cantidad * costoPromedio}`);

    // Actualizar celdas en la tabla
    const elCPP = document.getElementById(`res_cpp_${index}`);
    if (elCPP) {
        elCPP.textContent = formatNum(costoPromedio, 4);
        console.log('âœ… CPP actualizado en tabla');
    }

    const elTotal = document.getElementById(`res_total_${index}`);
    if (elTotal) {
        elTotal.textContent = formatNum(cantidad * costoPromedio, 2);
        console.log('âœ… Total actualizado en tabla');
    }

    recalcularIngreso();
}


/**
 * Calcular para AJUSTE POSITIVO
 * Similar a Inventario Inicial
 */
function calcularLineaIngresoAjuste(index) {
    const cantidad = toNum(document.getElementById(`ingCant_${index}`).value);

    console.log('ðŸ”¢ Cantidad ingresada (Ajuste):', cantidad);

    lineasIngreso[index].cantidad = cantidad;

    const idProd = lineasIngreso[index].id_inventario;

    if (!idProd) {
        console.warn('âš ï¸ Seleccione un producto primero');
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

    console.log(`ðŸ“Š Producto (Ajuste): ${prod?.nombre || 'N/A'}`);
    console.log(`ðŸ’° CPP usado (Ajuste): ${costoPromedio}`);

    lineasIngreso[index].costo_unitario = costoPromedio;
    lineasIngreso[index].valor_total_item = cantidad * costoPromedio;

    console.log(`ðŸ’µ Valor Total = ${cantidad} Ã— ${costoPromedio} = ${cantidad * costoPromedio}`);

    // Actualizar celdas
    const elCPP = document.getElementById(`res_cpp_${index}`);
    if (elCPP) {
        elCPP.textContent = formatNum(costoPromedio, 4);
        console.log('âœ… CPP actualizado en tabla');
    }

    const elTotal = document.getElementById(`res_total_${index}`);
    if (elTotal) {
        elTotal.textContent = formatNum(cantidad * costoPromedio, 2);
        console.log('âœ… Total actualizado en tabla');
    }

    recalcularIngreso();
}


// ========================================
// NUEVA FUNCIÃ“N: NavegaciÃ³n con TAB
// ========================================

function handleTabNavigation(event, index, field) {
    // Solo actuar si es la tecla TAB
    if (event.key !== 'Tab' && event.keyCode !== 9) return;

    // Detener el salto automÃ¡tico del navegador
    event.preventDefault();

    setTimeout(() => {
        if (field === 'cantidad') {
            // Ir al siguiente campo segÃºn el tipo
            let nextField = null;

            if (tipoIngresoActual.codigo === 'COMPRA') {
                // COMPRA: Cantidad â†’ Valor
                nextField = document.getElementById(`ingValor_${index}`);
            } else if (tipoIngresoActual.codigo === 'INICIAL') {
                // INVENTARIO INICIAL: Cantidad â†’ Costo
                nextField = document.getElementById(`ingCosto_${index}`);
            } else if (tipoIngresoActual.codigo === 'AJUSTE_POS') {
                // AJUSTE POSITIVO: Cantidad â†’ Siguiente lÃ­nea (como devoluciÃ³n)
                nextField = document.getElementById(`ingCant_${index + 1}`);
                if (!nextField) {
                    nextField = document.getElementById(`ingCant_0`);
                }
            } else if (tipoIngresoActual.codigo === 'DEVOLUCION_PROD') {
                // DEVOLUCIÃ“N: Cantidad â†’ Siguiente lÃ­nea (no hay mÃ¡s campos)
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
            // Desde Valor o Costo â†’ ir a Cantidad de la siguiente fila
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
    }, 50); // 50ms como en la versiÃ³n original que funcionaba
}


// NUEVA FUNCIÃ“N: Formatear input al perder foco
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

// NUEVA FUNCIÃ“N: Limpiar formato al enfocar (para editar)
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

    console.log('ðŸ“¦ Producto seleccionado ID:', idInventario);

    // Para DevoluciÃ³n y Ajuste Positivo, cargar CPP automÃ¡ticamente
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
            console.log('ðŸ’° CPP cargado:', cpp);

            const cantidad = toNum(lineasIngreso[index].cantidad);
            if (cantidad > 0) {
                lineasIngreso[index].valor_total_item = cantidad * cpp;
                console.log('ðŸ’µ Valor calculado:', cantidad * cpp);
            }
        }
    }

    renderLineasIngreso();
}

function eliminarLineaIngreso(index) {
    if (lineasIngreso.length === 1) {
        alert('Debe haber al menos una lÃ­nea');
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

        // 2. Obtener configuraciÃ³n del tipo
        const config = tiposIngresoConfig[tipoId];
        if (!config) {
            mostrarAlerta('ConfiguraciÃ³n del tipo de ingreso no encontrada', 'error');
            return;
        }

        console.log('ðŸ’¾ Guardando ingreso tipo:', config.nombre);

        // 3. VALIDACIONES DINÃMICAS segÃºn tipo

        // 3.1 Validar PROVEEDOR (Solo para COMPRAS)
        if (config.codigo === 'COMPRA') {
            const proveedorId = document.getElementById('ingresoProveedor').value;
            if (!proveedorId) {
                mostrarAlerta('Seleccione un proveedor para completar el registro de compra', 'error');
                return;
            }
        }

        // 3.2 Validar ÃREA DE PRODUCCIÃ“N (solo si es requerido)
        if (config.requiere_area_produccion) {
            const areaId = document.getElementById('ingresoArea').value;
            if (!areaId) {
                mostrarAlerta('Seleccione el Ã¡rea que devuelve', 'error');
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

        // 3.4 Validar AUTORIZACIÃ“N (solo si es requerido)
        if (config.requiere_autorizacion && config.codigo !== 'AJUSTE_POS') {
            const autorizadoPor = document.getElementById('ingresoAutorizadoPor').value;
            if (!autorizadoPor) {
                mostrarAlerta('Seleccione quiÃ©n autoriza', 'error');
                return;
            }
        }

        // 3.5 Validar OBSERVACIONES (solo si son obligatorias)
        if (config.observaciones_obligatorias) {
            const obs = document.getElementById('ingresoObservaciones').value.trim();
            if (!obs || obs.length < config.minimo_caracteres_obs) {
                mostrarAlerta(`Las observaciones son obligatorias (mÃ­nimo ${config.minimo_caracteres_obs} caracteres)`, 'error');
                return;
            }
        }

        // 4. Validar que haya lÃ­neas
        if (!lineasIngreso || lineasIngreso.length === 0) {
            mostrarAlerta('Debe agregar al menos una lÃ­nea de productos', 'error');
            return;
        }

        // 5. Construir objeto de datos segÃºn tipo
        const datosIngreso = {
            action: 'crear',
            id_tipo_ingreso: tipoId,
            fecha: document.getElementById('ingresoFecha').value,
            observaciones: document.getElementById('ingresoObservaciones').value || null,
            lineas: lineasIngreso
        };
        //datosIngreso.tipo_ingreso = config.codigo;
        // 6. Agregar campos especÃ­ficos segÃºn tipo

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

        // 6.3 DEVOLUCIÃ“N DE PRODUCCIÃ“N
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

        console.log('ðŸ“¦ Datos a enviar:', datosIngreso);

        // 7. Enviar al servidor
        const response = await fetch(`${BASE_URL_API}/ingresos_caq.php`, {
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
                title: 'Â¡Registrado!',
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
        console.error('âŒ Error en guardarIngreso:', error);
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
// (AquÃ­ irÃ­an las funciones de los otros modales - las omito por espacio pero estÃ¡n en el original)

// ========== MODAL SALIDA ==========

let productosFiltradosSalida = [];

function abrirModalSalida() {
    // Resetear cachÃ© para asegurar nÃºmero fresco
    window.numerosSalidaCache = {};

    // Tipo por defecto vacÃ­o
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

    // Poblar filtros de categorÃ­as
    poblarFiltrosCategoriasSalida();

    // Reset lÃ­neas
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
                // Cerrar modal normal y abrir modal de devoluciÃ³n
                cerrarModal('modalSalida');
                setTimeout(() => abrirModalDevolucion(), 300);
            }
        });
    }
});

function poblarFiltrosCategoriasSalida() {
    const selectCat = document.getElementById('salidaFiltroCat');
    selectCat.innerHTML = '<option value="">Todas las categorÃ­as</option>' +
        categorias.map(c => `<option value="${c.id_categoria}">${c.nombre}</option>`).join('');

    document.getElementById('salidaFiltroSubcat').innerHTML = '<option value="">Todas las subcategorÃ­as</option>';
}

async function filtrarProductosSalida() {
    const catId = document.getElementById('salidaFiltroCat').value;
    const subcatId = document.getElementById('salidaFiltroSubcat').value;

    // Si cambia categorÃ­a, actualizar subcategorÃ­as
    if (catId) {
        try {
            const r = await fetch(`${baseUrl}/api/centro_inventarios.php?action=subcategorias&categoria_id=${catId}`);
            const d = await r.json();
            if (d.success && d.subcategorias) {
                const selectSubcat = document.getElementById('salidaFiltroSubcat');
                selectSubcat.innerHTML =
                    '<option value="">Todas las subcategorÃ­as</option>' +
                    d.subcategorias.map(s => `<option value="${s.id_subcategoria}">${s.nombre}</option>`).join('');

                // Restaurar valor si existe
                if (subcatId) {
                    selectSubcat.value = subcatId;
                }
            }
        } catch (e) { console.error(e); }
    } else {
        document.getElementById('salidaFiltroSubcat').innerHTML = '<option value="">Todas las subcategorÃ­as</option>';
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

function cambioTipoSalida() {
    const tipo = document.getElementById('salidaTipo').value;
    const seccionDestino = document.getElementById('seccionDestinoProduccion');
    const selectDestino = document.getElementById('salidaDestino');

    if (tipo === 'PRODUCCION') {
        if (seccionDestino) seccionDestino.style.display = 'block';
    } else {
        if (seccionDestino) seccionDestino.style.display = 'none';
        if (selectDestino) selectDestino.value = '';
        actualizarNumeroSalida();
    }
}

async function actualizarNumeroSalida() {
    const tipo = document.getElementById('salidaTipo').value;
    const destino = document.getElementById('salidaDestino') ? document.getElementById('salidaDestino').value : '';
    const docInput = document.getElementById('salidaDocumento');
    const motivoObligatorio = document.getElementById('motivoObligatorio');

    if (!tipo) {
        if (docInput) {
            docInput.value = '';
            docInput.placeholder = 'Seleccione tipo de salida...';
            docInput.disabled = true;
        }
        return;
    }

    // Si es PRODUCCION pero no hay destino, pedir destino
    if (tipo === 'PRODUCCION' && !destino) {
        if (docInput) {
            docInput.value = '';
            docInput.placeholder = 'Seleccione destino...';
            docInput.disabled = true;
        }
        return;
    }

    const cacheKey = destino ? `${tipo}_${destino}` : tipo;

    if (window.numerosSalidaCache[cacheKey]) {
        if (docInput) {
            docInput.value = window.numerosSalidaCache[cacheKey];
            docInput.disabled = false;
        }
        if (motivoObligatorio) {
            motivoObligatorio.style.display = tipo === 'AJUSTE' ? 'inline' : 'none';
        }
        return;
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 8000);

    try {
        if (docInput) {
            docInput.value = 'â³ Generando...';
            docInput.disabled = true;
        }

        const url = `${BASE_URL_API}/salidas_caq.php?action=siguiente_numero&tipo=${tipo}&destino=${destino}`;
        const r = await fetch(url, { signal: controller.signal });
        clearTimeout(timeoutId);

        if (r.status === 401) throw new Error('SESIÃ“N_EXPIRADA');

        const d = await r.json();

        if (d.success) {
            window.numerosSalidaCache[cacheKey] = d.numero;
            if (docInput) {
                docInput.value = d.numero;
                docInput.disabled = false;
            }
        } else {
            if (d.message && (d.message.includes('autorizado') || d.message.includes('No autorizado'))) throw new Error('SESIÃ“N_EXPIRADA');
            throw new Error(d.message || 'Error servidor');
        }
    } catch (e) {
        clearTimeout(timeoutId);
        if (e.name === 'AbortError') {
            ejecutarFallbackLocalSalida(tipo, destino, 'Timeout');
        } else if (e.message === 'SESIÃ“N_EXPIRADA') {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ title: 'SesiÃ³n Expirada', text: 'Por favor reingrese.', icon: 'warning' }).then(() => { window.location.href = 'index.php'; });
            }
            if (docInput) { docInput.value = ''; docInput.disabled = true; }
        } else {
            ejecutarFallbackLocalSalida(tipo, destino, e.message);
        }
    } finally {
        if (docInput && docInput.value === 'â³ Generando...') { docInput.value = ''; docInput.disabled = false; }
        if (motivoObligatorio) motivoObligatorio.style.display = tipo === 'AJUSTE' ? 'inline' : 'none';
    }
}

function ejecutarFallbackLocalSalida(tipo, destino, motivo) {
    const prefijosBase = { 'PRODUCCION': 'OUT-CAQ-P', 'VENTA': 'OUT-CAQ-V', 'MUESTRAS': 'OUT-CAQ-M', 'AJUSTE': 'OUT-CAQ-A', 'DEVOLUCION': 'OUT-CAQ-R' };
    const prefijosDestino = { 'TEJIDO': 'SAL-TEJ', 'COSTURA': 'SAL-COS', 'TENIDO': 'SAL-TEN' };

    let prefijo = prefijosBase[tipo] || 'OUT-CAQ-X';
    if (tipo === 'PRODUCCION' && destino && prefijosDestino[destino]) {
        prefijo = prefijosDestino[destino];
    }

    const cacheKey = destino ? `${tipo}_${destino}` : tipo;
    const docInput = document.getElementById('salidaDocumento');
    if (docInput) {
        const numero = generarNumeroDoc(prefijo);
        window.numerosSalidaCache[cacheKey] = numero;
        docInput.value = numero;
        docInput.disabled = false;
        console.warn('Fallback CAQ:', numero);
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
        alert(`âš ï¸ Stock insuficiente. Disponible: ${formatNum(stockDisp)}`);
        document.getElementById(`salCant_${index}`).value = stockDisp;
        lineasSalida[index].cantidad = stockDisp;
    } else {
        lineasSalida[index].cantidad = cantidad;
    }

    renderLineasSalida();
}

function eliminarLineaSalida(index) {
    if (lineasSalida.length === 1) {
        alert('Debe haber al menos una lÃ­nea');
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
        alert('âš ï¸ Agregue al menos una lÃ­nea');
        return;
    }

    // Validar que todas las lÃ­neas tengan producto y cantidad
    for (let i = 0; i < lineasSalida.length; i++) {
        if (!lineasSalida[i].id_inventario) {
            alert(`âš ï¸ Seleccione un producto en la lÃ­nea ${i + 1}`);
            return;
        }
        if (lineasSalida[i].cantidad <= 0) {
            alert(`âš ï¸ Ingrese cantidad en la lÃ­nea ${i + 1}`);
            return;
        }

        // Validar stock
        const prod = productosCompletos.find(p => p.id_inventario == lineasSalida[i].id_inventario);
        const stockDisp = prod ? toNum(prod.stock_actual) : 0;
        if (lineasSalida[i].cantidad > stockDisp) {
            alert(`âš ï¸ Stock insuficiente para ${prod.nombre}. Disponible: ${formatNum(stockDisp)}`);
            return;
        }
    }

    // Validar motivo para ajustes
    if (tipo === 'AJUSTE' && !document.getElementById('salidaObservaciones').value.trim()) {
        alert('âš ï¸ El motivo es obligatorio para ajustes de inventario');
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
        const r = await fetch(`${baseUrl}/api/salidas_caq.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const d = await r.json();
        console.log('Respuesta:', d);

        if (d.success) {
            alert('âœ… ' + d.message);
            cerrarModal('modalSalida');
            cargarDatos();
        } else {
            alert('âŒ ' + d.message);
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

console.log('âœ… MÃ³dulo Materias Primas v1.9 cargado');
console.log('   - Modal Ingreso mejorado v2.0');
console.log('   - Filtros por tipo proveedor y categorÃ­a');
console.log('   - CÃ¡lculo IVA con columnas dinÃ¡micas');
console.log('   - Costos con 4 decimales');