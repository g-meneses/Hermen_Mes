/**
 * JavaScript para módulo Materias Primas
 * Sistema MES Hermen Ltda. v1.3
 * CORRECCIÓN DEFINITIVA: Formato de números sin multiplicaciones
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

// ========== FUNCIONES DE FORMATO (AL INICIO PARA ESTAR DISPONIBLES) ==========

/**
 * Convierte cualquier valor a número flotante limpio
 * NO multiplica ni divide - solo limpia y parsea
 */
function toNum(value) {
    if (value === null || value === undefined || value === '') return 0;
    if (typeof value === 'number') return value;
    
    // Convertir a string y limpiar
    let str = String(value).trim();
    
    // Si el string tiene formato con comas como separador de miles (ej: "1,400.50")
    // Detectar: si tiene coma Y punto, la coma es separador de miles
    if (str.includes(',') && str.includes('.')) {
        // Formato americano: 1,234.56 - remover comas
        str = str.replace(/,/g, '');
    } else if (str.includes(',')) {
        // Solo tiene comas - podría ser miles (1,400) o decimal europeo (1,50)
        // Si la coma está seguida de exactamente 2 dígitos al final, es decimal
        if (/,\d{2}$/.test(str)) {
            str = str.replace(',', '.');
        } else {
            // Es separador de miles
            str = str.replace(/,/g, '');
        }
    }
    
    const num = parseFloat(str);
    return isNaN(num) ? 0 : num;
}

/**
 * Formatea número para mostrar en pantalla
 * @param {any} value - Valor a formatear
 * @param {number} decimals - Número de decimales (default 2)
 * @returns {string} Número formateado con separador de miles
 */
function formatNum(value, decimals = 2) {
    const num = toNum(value);
    
    // Usar toLocaleString para formato correcto
    return num.toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

// ========== KPIs ==========
async function cargarKPIs() {
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=resumen&tipo_id=${TIPO_ID}`);
        const d = await r.json();
        console.log('Respuesta KPIs:', d);
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
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=categorias&tipo_id=${TIPO_ID}`);
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
            
            const rProd = await fetch(`${baseUrl}/api/inventarios.php?action=list&tipo_id=${TIPO_ID}`);
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
    
    console.log('Categoría seleccionada:', categoriaSeleccionada);
    
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=subcategorias&categoria_id=${idCategoria}`);
        const d = await r.json();
        console.log('Respuesta subcategorías:', d);
        
        if (d.success && d.subcategorias && d.subcategorias.length > 0) {
            // Contar productos SIN subcategoría asignada
            let sinSubcategoria = { total_items: 0, valor_total: 0, alertas: 0 };
            
            subcategorias = d.subcategorias.map(s => ({
                ...s,
                total_items: 0,
                valor_total: 0,
                alertas: 0
            }));
            
            // Recorrer productos de esta categoría
            productosCompletos.forEach(prod => {
                if (prod.id_categoria == idCategoria) {
                    const stock = toNum(prod.stock_actual);
                    const stockMin = toNum(prod.stock_minimo);
                    const costo = toNum(prod.costo_promedio) || toNum(prod.costo_unitario);
                    const esAlerta = stock > 0 && stock <= stockMin;
                    
                    if (prod.id_subcategoria) {
                        // Producto CON subcategoría
                        const sub = subcategorias.find(s => s.id_subcategoria == prod.id_subcategoria);
                        if (sub) {
                            sub.total_items++;
                            sub.valor_total += stock * costo;
                            if (esAlerta) sub.alertas++;
                        }
                    } else {
                        // Producto SIN subcategoría
                        sinSubcategoria.total_items++;
                        sinSubcategoria.valor_total += stock * costo;
                        if (esAlerta) sinSubcategoria.alertas++;
                    }
                }
            });
            
            // Si hay productos sin subcategoría, agregar card especial
            if (sinSubcategoria.total_items > 0) {
                subcategorias.unshift({
                    id_subcategoria: 0, // ID especial para "sin clasificar"
                    nombre: '📦 Sin Clasificar',
                    total_items: sinSubcategoria.total_items,
                    valor_total: sinSubcategoria.valor_total,
                    alertas: sinSubcategoria.alertas
                });
            }
            
            // Siempre agregar card "Ver Todos" al inicio
            const catData = categorias.find(c => c.id_categoria == idCategoria);
            subcategorias.unshift({
                id_subcategoria: -1, // ID especial para "ver todos"
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
        // Determinar si es card especial
        const esVerTodos = s.id_subcategoria === -1;
        const esSinClasificar = s.id_subcategoria === 0;
        const esEspecial = esVerTodos || esSinClasificar;
        
        // Estilos especiales para cards especiales
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
        // "Ver Todos" - cargar todos los productos de la categoría
        cargarProductosCategoria(categoriaSeleccionada.id_categoria);
    } else if (idSubcategoria === 0) {
        // "Sin Clasificar" - cargar productos sin subcategoría
        cargarProductosSinSubcategoria(categoriaSeleccionada.id_categoria);
    } else {
        // Subcategoría normal
        cargarProductosSubcategoria(idSubcategoria);
    }
}

// Nueva función para cargar productos sin subcategoría
async function cargarProductosSinSubcategoria(idCategoria) {
    mostrarProductosSection('Sin Clasificar');
    try {
        // Filtrar de productosCompletos los que no tienen subcategoría
        productos = productosCompletos.filter(p => 
            p.id_categoria == idCategoria && 
            (!p.id_subcategoria || p.id_subcategoria === null || p.id_subcategoria === 0)
        );
        console.log('Productos sin subcategoría:', productos.length);
        renderProductos();
    } catch (e) { 
        console.error('Error:', e); 
    }
}

// ========== PRODUCTOS ==========
async function cargarProductosCategoria(idCategoria) {
    mostrarProductosSection(categoriaSeleccionada.nombre);
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=list&tipo_id=${TIPO_ID}&categoria_id=${idCategoria}`);
        const d = await r.json();
        console.log('=== DEBUG Productos ===');
        console.log('Primer producto:', d.inventarios?.[0]);
        if (d.success) { productos = d.inventarios || []; renderProductos(); }
    } catch (e) { console.error('Error:', e); }
}

async function cargarProductosSubcategoria(idSubcategoria) {
    mostrarProductosSection(subcategoriaSeleccionada.nombre);
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=list&subcategoria_id=${idSubcategoria}`);
        const d = await r.json();
        console.log('=== DEBUG Productos Subcategoría ===');
        console.log('Primer producto:', d.inventarios?.[0]);
        if (d.success) { productos = d.inventarios || []; renderProductos(); }
    } catch (e) { console.error('Error:', e); }
}

function mostrarProductosSection(titulo) {
    document.getElementById('productosTitulo').textContent = titulo;
    document.getElementById('productosBody').innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>';
    document.getElementById('productosSection').style.display = 'block';
}

/**
 * Renderiza la tabla de productos
 * Usa formatNum() para mostrar valores correctamente
 */
function renderProductos() {
    const tbody = document.getElementById('productosBody');
    document.getElementById('productosCount').textContent = productos.length + ' items';
    
    if (productos.length === 0) { 
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;">Sin productos</td></tr>'; 
        return; 
    }
    
    // Debug del primer producto
    const p0 = productos[0];
    console.log('=== DEBUG renderProductos ===');
    console.log('stock_actual:', p0.stock_actual, '→ toNum:', toNum(p0.stock_actual));
    console.log('costo_promedio:', p0.costo_promedio, '→ toNum:', toNum(p0.costo_promedio));
    console.log('costo_unitario:', p0.costo_unitario, '→ toNum:', toNum(p0.costo_unitario));
    console.log('unidad campos:', {
        unidad_abrev: p0.unidad_abrev,
        abreviatura: p0.abreviatura, 
        unidad: p0.unidad,
        unidad_medida: p0.unidad_medida
    });
    
    tbody.innerHTML = productos.map(p => {
        const stock = toNum(p.stock_actual);
        const stockMin = toNum(p.stock_minimo);
        const costo = toNum(p.costo_promedio) || toNum(p.costo_unitario);
        const valor = stock * costo;
        
        // Buscar unidad en varios campos posibles
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
    document.getElementById('formItem').reset();
    document.getElementById('itemId').value = '';
    document.getElementById('modalItemTitulo').textContent = 'Nuevo Item de Materia Prima';
    poblarSelects();
    document.getElementById('modalItem').classList.add('show');
}

async function editarItem(id) {
    console.log('=== Editando item ID:', id);
    
    // Buscar el item en productosCompletos (ya cargados)
    const item = productosCompletos.find(p => p.id_inventario == id);
    
    if (!item) {
        alert('❌ No se encontró el item');
        return;
    }
    
    console.log('Item encontrado:', item);
    
    // Poblar selects primero
    poblarSelects();
    
    // Llenar el formulario
    document.getElementById('itemId').value = item.id_inventario;
    document.getElementById('itemCodigo').value = item.codigo || '';
    document.getElementById('itemNombre').value = item.nombre || '';
    document.getElementById('itemCategoria').value = item.id_categoria || '';
    
    // Cargar subcategorías de esta categoría
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
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=subcategorias&categoria_id=${catId}`);
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
    const currentCat = catSelect.value; // Guardar valor actual
    catSelect.innerHTML = '<option value="">Seleccione...</option>' + 
        categorias.map(c => `<option value="${c.id_categoria}">${c.nombre}</option>`).join('');
    if (currentCat) catSelect.value = currentCat; // Restaurar valor
    
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
    
    console.log('Guardando item:', data);
    
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const d = await r.json();
        console.log('Respuesta guardar:', d);
        
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

// ========== MODAL INGRESO MEJORADO v2.0 ==========
let productosFiltrados = []; // Productos filtrados por categoría/subcategoría
let modoConFactura = false;
let contadorDocIngreso = 0;

function abrirModalIngreso() {
    // Generar número de documento automático
    generarNumeroDocumentoIngreso();
    
    // Fecha actual
    document.getElementById('ingresoFecha').value = new Date().toISOString().split('T')[0];
    
    // Reset filtro tipo proveedor
    document.getElementById('ingresoTipoProveedor').value = 'TODOS';
    filtrarProveedoresIngreso();
    
    // Poblar filtros de categorías
    poblarFiltrosCategorias();
    
    // Reset checkbox factura
    document.getElementById('ingresoConFactura').checked = false;
    modoConFactura = false;
    
    // Reset campos
    document.getElementById('ingresoReferencia').value = '';
    document.getElementById('ingresoObservaciones').value = '';
    document.getElementById('infoProveedorBox').style.display = 'none';
    
    // Reset líneas
    lineasIngreso = [];
    productosFiltrados = [...productosCompletos];
    
    // Renderizar
    toggleModoFactura();
    renderLineasIngreso();
    
    document.getElementById('modalIngreso').classList.add('show');
}

async function generarNumeroDocumentoIngreso() {
    // Formato: ING-MP-YYYYMMDD-XXX
    const hoy = new Date();
    const fecha = hoy.toISOString().split('T')[0].replace(/-/g, '');
    
    // Obtener último número del día (simulado, idealmente vendría del servidor)
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
    
    // Agrupar por tipo
    const locales = provFiltrados.filter(p => p.tipo === 'LOCAL');
    const importacion = provFiltrados.filter(p => p.tipo === 'IMPORTACION');
    
    let html = '<option value="">Seleccione proveedor...</option>';
    
    if (locales.length > 0) {
        html += '<optgroup label="🇧🇴 Proveedores Locales">';
        locales.forEach(p => {
            const nombre = p.nombre_comercial || p.razon_social;
            html += `<option value="${p.id_proveedor}" data-tipo="${p.tipo}" data-moneda="${p.moneda}" data-pago="${p.condicion_pago}">${p.codigo} - ${nombre}</option>`;
        });
        html += '</optgroup>';
    }
    
    if (importacion.length > 0) {
        html += '<optgroup label="🌎 Proveedores Importación">';
        importacion.forEach(p => {
            const nombre = p.nombre_comercial || p.razon_social;
            html += `<option value="${p.id_proveedor}" data-tipo="${p.tipo}" data-moneda="${p.moneda}" data-pago="${p.condicion_pago}">${p.codigo} - ${nombre} (${p.pais})</option>`;
        });
        html += '</optgroup>';
    }
    
    select.innerHTML = html;
    document.getElementById('infoProveedorBox').style.display = 'none';
}

function actualizarInfoProveedor() {
    const select = document.getElementById('ingresoProveedor');
    const opt = select.options[select.selectedIndex];
    
    if (!opt || !opt.value) {
        document.getElementById('infoProveedorBox').style.display = 'none';
        return;
    }
    
    const tipo = opt.dataset.tipo;
    const moneda = opt.dataset.moneda;
    const pago = opt.dataset.pago;
    
    document.getElementById('infoProveedorTipo').textContent = tipo === 'LOCAL' ? '🇧🇴 Local' : '🌎 Importación';
    document.getElementById('infoProveedorTipo').className = `badge-tipo ${tipo === 'LOCAL' ? 'local' : 'import'}`;
    
    document.getElementById('infoProveedorMoneda').textContent = moneda;
    document.getElementById('infoProveedorMoneda').className = `badge-moneda ${moneda.toLowerCase()}`;
    
    document.getElementById('infoProveedorPago').textContent = `Condición: ${pago || 'Contado'}`;
    
    document.getElementById('infoProveedorBox').style.display = 'flex';
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
    
    // Actualizar subcategorías si hay categoría seleccionada
    if (catId) {
        try {
            const r = await fetch(`${baseUrl}/api/inventarios.php?action=subcategorias&categoria_id=${catId}`);
            const d = await r.json();
            const selectSubcat = document.getElementById('ingresoFiltroSubcat');
            selectSubcat.innerHTML = '<option value="">Todas las subcategorías</option>';
            if (d.success && d.subcategorias) {
                d.subcategorias.forEach(s => {
                    selectSubcat.innerHTML += `<option value="${s.id_subcategoria}">${s.nombre}</option>`;
                });
            }
        } catch (e) { console.error(e); }
    } else {
        document.getElementById('ingresoFiltroSubcat').innerHTML = '<option value="">Todas las subcategorías</option>';
    }
    
    // Filtrar productos
    productosFiltrados = productosCompletos.filter(p => {
        if (catId && p.id_categoria != catId) return false;
        if (subcatId && p.id_subcategoria != subcatId) return false;
        return true;
    });
    
    // Re-renderizar líneas con productos filtrados
    renderLineasIngreso();
}

function toggleModoFactura() {
    modoConFactura = document.getElementById('ingresoConFactura').checked;
    
    // Mostrar/ocultar fila de IVA en totales
    document.getElementById('rowIVA').style.display = modoConFactura ? 'flex' : 'none';
    
    // Actualizar encabezado de tabla
    const thead = document.getElementById('theadIngreso');
    
    if (modoConFactura) {
        thead.innerHTML = `
            <tr>
                <th class="col-producto">Producto</th>
                <th class="col-unidad">Unid.</th>
                <th class="col-cantidad">Cantidad</th>
                <th class="col-costo">Costo Neto</th>
                <th class="col-costo">Costo Unit.</th>
                <th class="col-iva">IVA 13%</th>
                <th class="col-total">Costo Doc.</th>
                <th class="col-total">Subtotal</th>
                <th class="col-acciones"></th>
            </tr>`;
    } else {
        thead.innerHTML = `
            <tr>
                <th class="col-producto">Producto</th>
                <th class="col-unidad">Unid.</th>
                <th class="col-cantidad">Cantidad</th>
                <th class="col-costo">Costo Neto</th>
                <th class="col-costo">Costo Unit.</th>
                <th class="col-total">Subtotal</th>
                <th class="col-acciones"></th>
            </tr>`;
    }
    
    renderLineasIngreso();
}

function agregarLineaIngreso() {
    lineasIngreso.push({ 
        id_inventario: '', 
        cantidad: 0, 
        costo_neto: 0,      // Costo sin IVA (se ingresa)
        costo_unitario: 0,  // Costo calculado con 4 decimales
        unidad: ''
    });
    renderLineasIngreso();
}

function renderLineasIngreso() {
    const tbody = document.getElementById('ingresoLineasBody');
    
    if (lineasIngreso.length === 0) {
        const cols = modoConFactura ? 9 : 7;
        tbody.innerHTML = `<tr><td colspan="${cols}" style="text-align:center;padding:30px;color:#6c757d;">
            <i class="fas fa-inbox" style="font-size:2rem;margin-bottom:10px;display:block;opacity:0.3;"></i>
            Haga clic en "Agregar Línea" para comenzar
        </td></tr>`;
        recalcularIngreso();
        return;
    }
    
    tbody.innerHTML = lineasIngreso.map((l, i) => {
        // Buscar producto para obtener unidad
        const prod = productosCompletos.find(p => p.id_inventario == l.id_inventario);
        const unidad = prod ? (prod.unidad_abrev || prod.abreviatura || prod.unidad || 'Kg') : '-';
        
        // Cálculos
        const costoNeto = toNum(l.costo_neto);
        const cantidad = toNum(l.cantidad);
        
        let costoUnitario, iva, costoDoc, subtotal;
        
        if (modoConFactura) {
            // Con factura: Costo Neto + 13% = Costo Documento
            iva = costoNeto * 0.13;
            costoDoc = costoNeto + iva;
            costoUnitario = costoNeto; // El costo unitario real es el neto
            subtotal = cantidad * costoDoc;
        } else {
            // Sin factura: Costo Neto = Costo Unitario
            costoUnitario = costoNeto;
            subtotal = cantidad * costoNeto;
        }
        
        // Guardar costo unitario calculado
        lineasIngreso[i].costo_unitario = costoUnitario;
        
        if (modoConFactura) {
            return `<tr>
                <td class="col-producto">
                    <select onchange="actualizarLineaIngreso(${i}, 'id_inventario', this.value)">
                        <option value="">Seleccione producto...</option>
                        ${productosFiltrados.map(p => `<option value="${p.id_inventario}" ${p.id_inventario == l.id_inventario ? 'selected' : ''}>${p.codigo} - ${p.nombre}</option>`).join('')}
                    </select>
                </td>
                <td class="col-unidad" style="text-align:center;font-weight:500;">${unidad}</td>
                <td class="col-cantidad">
                    <input type="number" step="0.01" min="0" value="${cantidad || ''}" 
                           onchange="actualizarLineaIngreso(${i}, 'cantidad', this.value)" 
                           placeholder="0.00">
                </td>
                <td class="col-costo">
                    <input type="number" step="0.0001" min="0" value="${costoNeto || ''}" 
                           onchange="actualizarLineaIngreso(${i}, 'costo_neto', this.value)" 
                           placeholder="0.0000">
                </td>
                <td class="valor-calculado">${formatNum(costoUnitario, 4)}</td>
                <td class="valor-iva">${formatNum(iva, 4)}</td>
                <td class="valor-calculado">${formatNum(costoDoc, 4)}</td>
                <td class="valor-calculado" style="font-weight:700;">${formatNum(subtotal)}</td>
                <td class="col-acciones">
                    <button class="btn-icon" style="background:#dc3545;color:white;" onclick="eliminarLineaIngreso(${i})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        } else {
            return `<tr>
                <td class="col-producto">
                    <select onchange="actualizarLineaIngreso(${i}, 'id_inventario', this.value)">
                        <option value="">Seleccione producto...</option>
                        ${productosFiltrados.map(p => `<option value="${p.id_inventario}" ${p.id_inventario == l.id_inventario ? 'selected' : ''}>${p.codigo} - ${p.nombre}</option>`).join('')}
                    </select>
                </td>
                <td class="col-unidad" style="text-align:center;font-weight:500;">${unidad}</td>
                <td class="col-cantidad">
                    <input type="number" step="0.01" min="0" value="${cantidad || ''}" 
                           onchange="actualizarLineaIngreso(${i}, 'cantidad', this.value)" 
                           placeholder="0.00">
                </td>
                <td class="col-costo">
                    <input type="number" step="0.0001" min="0" value="${costoNeto || ''}" 
                           onchange="actualizarLineaIngreso(${i}, 'costo_neto', this.value)" 
                           placeholder="0.0000">
                </td>
                <td class="valor-calculado">${formatNum(costoUnitario, 4)}</td>
                <td class="valor-calculado" style="font-weight:700;">${formatNum(subtotal)}</td>
                <td class="col-acciones">
                    <button class="btn-icon" style="background:#dc3545;color:white;" onclick="eliminarLineaIngreso(${i})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        }
    }).join('');
    
    recalcularIngreso();
}

function actualizarLineaIngreso(index, campo, valor) {
    if (campo === 'id_inventario') {
        lineasIngreso[index].id_inventario = valor;
        // Obtener unidad del producto
        const prod = productosCompletos.find(p => p.id_inventario == valor);
        lineasIngreso[index].unidad = prod ? (prod.unidad_abrev || prod.abreviatura || 'Kg') : '';
    } else {
        lineasIngreso[index][campo] = toNum(valor);
    }
    renderLineasIngreso();
}

function eliminarLineaIngreso(index) {
    lineasIngreso.splice(index, 1);
    renderLineasIngreso();
}

function recalcularIngreso() {
    let totalNeto = 0;
    let totalIVA = 0;
    let totalDoc = 0;
    
    lineasIngreso.forEach(l => {
        const cantidad = toNum(l.cantidad);
        const costoNeto = toNum(l.costo_neto);
        
        if (modoConFactura) {
            const iva = costoNeto * 0.13;
            const costoDoc = costoNeto + iva;
            totalNeto += cantidad * costoNeto;
            totalIVA += cantidad * iva;
            totalDoc += cantidad * costoDoc;
        } else {
            totalNeto += cantidad * costoNeto;
            totalDoc = totalNeto;
        }
    });
    
    // Obtener moneda del proveedor seleccionado
    const selectProv = document.getElementById('ingresoProveedor');
    const opt = selectProv.options[selectProv.selectedIndex];
    const moneda = opt?.dataset?.moneda === 'USD' ? 'USD' : 'Bs.';
    
    document.getElementById('ingresoTotalNeto').textContent = `${moneda} ${formatNum(totalNeto)}`;
    document.getElementById('ingresoIVA').textContent = `${moneda} ${formatNum(totalIVA)}`;
    document.getElementById('ingresoTotal').textContent = `${moneda} ${formatNum(totalDoc)}`;
}

async function guardarIngreso() {
    // Validaciones
    if (lineasIngreso.length === 0) { 
        alert('❌ Agregue al menos una línea de ingreso'); 
        return; 
    }
    
    if (!document.getElementById('ingresoProveedor').value) {
        alert('❌ Seleccione un proveedor');
        return;
    }
    
    const lineasIncompletas = lineasIngreso.some(l => !l.id_inventario || l.cantidad <= 0 || l.costo_neto <= 0);
    if (lineasIncompletas) { 
        alert('❌ Complete todos los campos de las líneas (Producto, Cantidad y Costo)'); 
        return; 
    }
    
    const data = {
        action: 'movimiento',
        tipo_movimiento: 'ENTRADA_COMPRA',
        documento_tipo: 'INGRESO',
        documento_numero: document.getElementById('ingresoDocumento').value,
        fecha: document.getElementById('ingresoFecha').value,
        id_proveedor: document.getElementById('ingresoProveedor').value,
        referencia: document.getElementById('ingresoReferencia').value || null,
        con_factura: modoConFactura,
        observaciones: document.getElementById('ingresoObservaciones').value,
        lineas: lineasIngreso.map(l => {
            const costoNeto = toNum(l.costo_neto);
            return {
                id_inventario: l.id_inventario,
                cantidad: toNum(l.cantidad),
                costo_unitario: costoNeto, // Costo neto (sin IVA)
                costo_con_iva: modoConFactura ? costoNeto * 1.13 : costoNeto
            };
        })
    };
    
    console.log('Guardando ingreso:', data);
    
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const d = await r.json();
        
        if (d.success) {
            alert('✅ ' + d.message);
            cerrarModal('modalIngreso');
            cargarDatos();
        } else {
            alert('❌ ' + d.message);
        }
    } catch (e) {
        console.error('Error:', e);
        alert('❌ Error al guardar el ingreso');
    }
}


// ========== MODAL SALIDA ==========
function abrirModalSalida() {
    document.getElementById('salidaDocumento').value = generarNumeroDoc('SAL-MP');
    document.getElementById('salidaFecha').value = new Date().toISOString().split('T')[0];
    lineasSalida = [];
    renderLineasSalida();
    document.getElementById('modalSalida').classList.add('show');
}

function agregarLineaSalida() {
    lineasSalida.push({ id_inventario: '', cantidad: 0 });
    renderLineasSalida();
}

function renderLineasSalida() {
    const tbody = document.getElementById('salidaLineasBody');
    
    tbody.innerHTML = lineasSalida.map((l, i) => {
        const prod = productosCompletos.find(p => p.id_inventario == l.id_inventario);
        const stockDisp = prod ? toNum(prod.stock_actual) : 0;
        const cpp = prod ? (toNum(prod.costo_promedio) || toNum(prod.costo_unitario)) : 0;
        const subtotal = l.cantidad * cpp;
        
        return `<tr>
            <td>
                <select onchange="actualizarLineaSalida(${i}, 'id_inventario', this.value)">
                    <option value="">Seleccione...</option>
                    ${productosCompletos.filter(p => toNum(p.stock_actual) > 0).map(p => 
                        `<option value="${p.id_inventario}" ${p.id_inventario == l.id_inventario ? 'selected' : ''}>${p.codigo} - ${p.nombre}</option>`
                    ).join('')}
                </select>
            </td>
            <td style="text-align:right;">${formatNum(stockDisp)}</td>
            <td><input type="number" step="0.01" max="${stockDisp}" value="${l.cantidad}" onchange="actualizarLineaSalida(${i}, 'cantidad', this.value)"></td>
            <td style="text-align:right;">Bs. ${formatNum(cpp)}</td>
            <td style="text-align:right;">Bs. ${formatNum(subtotal)}</td>
            <td><button class="btn-icon" style="background:#dc3545;color:white;" onclick="eliminarLineaSalida(${i})"><i class="fas fa-trash"></i></button></td>
        </tr>`;
    }).join('');
    
    recalcularSalida();
}

function actualizarLineaSalida(index, campo, valor) {
    lineasSalida[index][campo] = campo === 'id_inventario' ? valor : toNum(valor);
    renderLineasSalida();
}

function eliminarLineaSalida(index) {
    lineasSalida.splice(index, 1);
    renderLineasSalida();
}

function recalcularSalida() {
    let total = 0;
    lineasSalida.forEach(l => {
        const prod = productosCompletos.find(p => p.id_inventario == l.id_inventario);
        const cpp = prod ? (toNum(prod.costo_promedio) || toNum(prod.costo_unitario)) : 0;
        total += l.cantidad * cpp;
    });
    document.getElementById('salidaTotal').textContent = 'Bs. ' + formatNum(total);
}

async function guardarSalida() {
    if (lineasSalida.length === 0) { alert('Agregue al menos una línea'); return; }
    if (lineasSalida.some(l => !l.id_inventario || l.cantidad <= 0)) { alert('Complete todos los campos'); return; }
    
    for (const l of lineasSalida) {
        const prod = productosCompletos.find(p => p.id_inventario == l.id_inventario);
        if (prod && l.cantidad > toNum(prod.stock_actual)) {
            alert(`Stock insuficiente para ${prod.nombre}`);
            return;
        }
    }
    
    const data = {
        action: 'movimiento',
        tipo_movimiento: document.getElementById('salidaTipo').value,
        documento_tipo: 'SALIDA',
        documento_numero: document.getElementById('salidaDocumento').value,
        fecha: document.getElementById('salidaFecha').value,
        referencia: document.getElementById('salidaReferencia').value || null,
        observaciones: document.getElementById('salidaObservaciones').value,
        lineas: lineasSalida.map(l => {
            const prod = productosCompletos.find(p => p.id_inventario == l.id_inventario);
            return {
                id_inventario: l.id_inventario,
                cantidad: l.cantidad,
                costo_unitario: prod ? (toNum(prod.costo_promedio) || toNum(prod.costo_unitario)) : 0
            };
        })
    };
    
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const d = await r.json();
        if (d.success) {
            alert('✅ ' + d.message);
            cerrarModal('modalSalida');
            cargarDatos();
        } else {
            alert('❌ ' + d.message);
        }
    } catch (e) {
        console.error('Error:', e);
        alert('Error al guardar');
    }
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
    if (docs.length === 0) { 
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;">Sin documentos</td></tr>'; 
        return; 
    }
    
    tbody.innerHTML = docs.map(doc => {
        const esEntrada = doc.tipo_movimiento?.includes('ENTRADA');
        const esAnulado = doc.estado === 'ANULADO';
        return `<tr style="${esAnulado?'opacity:0.6;':''}">
            <td>${doc.fecha ? new Date(doc.fecha).toLocaleDateString('es-BO') : '-'}</td>
            <td><strong>${doc.documento_numero}</strong></td>
            <td style="color:${esEntrada?'#28a745':'#dc3545'};">${esEntrada?'↓ Entrada':'↑ Salida'}</td>
            <td style="text-align:right;">Bs. ${formatNum(doc.total_documento)}</td>
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
        if (d.success) { 
            documentoActual = d; 
            renderDetalleDocumento(d); 
            document.getElementById('modalDetalle').classList.add('show'); 
        } else {
            alert('❌ ' + d.message);
        }
    } catch (e) { alert('Error'); }
}

function renderDetalleDocumento(data) {
    const cab = data.cabecera, lineas = data.lineas || [];
    const esAnulado = cab.estado === 'ANULADO';
    document.getElementById('detalleTitulo').textContent = 'Documento: ' + cab.documento_numero;
    document.getElementById('btnAnular').style.display = esAnulado ? 'none' : 'inline-block';
    
    document.getElementById('detalleContenido').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;background:#f8f9fa;padding:15px;border-radius:8px;">
            <div>
                <p><strong>Tipo:</strong> ${cab.documento_tipo||'-'}</p>
                <p><strong>Fecha:</strong> ${cab.fecha?new Date(cab.fecha).toLocaleDateString('es-BO'):'-'}</p>
                <p><strong>Movimiento:</strong> ${cab.tipo_movimiento||'-'}</p>
            </div>
            <div>
                <p><strong>Usuario:</strong> ${cab.usuario||'N/A'}</p>
                <p><strong>Estado:</strong> <span class="${esAnulado?'badge-anulado':'badge-activo'}">${cab.estado||'ACTIVO'}</span></p>
            </div>
        </div>
        <table class="productos-table">
            <thead><tr><th>Código</th><th>Producto</th><th style="text-align:right;">Cantidad</th><th style="text-align:right;">Costo</th><th style="text-align:right;">Total</th></tr></thead>
            <tbody>${lineas.map(l => `<tr>
                <td>${l.producto_codigo||'-'}</td>
                <td>${l.producto_nombre||'-'}</td>
                <td style="text-align:right;">${formatNum(l.cantidad)}</td>
                <td style="text-align:right;">Bs. ${formatNum(l.costo_unitario)}</td>
                <td style="text-align:right;">Bs. ${formatNum(toNum(l.cantidad) * toNum(l.costo_unitario))}</td>
            </tr>`).join('')}</tbody>
            <tfoot><tr style="background:#e9ecef;font-weight:bold;">
                <td colspan="4" style="text-align:right;">TOTAL:</td>
                <td style="text-align:right;">Bs. ${formatNum(cab.total_documento)}</td>
            </tr></tfoot>
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
            method: 'POST', 
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'anular_documento', 
                documento_numero: documentoActual.cabecera.documento_numero, 
                motivo 
            })
        });
        const d = await r.json();
        if (d.success) { 
            alert('✅ ' + d.message); 
            cerrarModal('modalDetalle'); 
            buscarHistorial(); 
            cargarDatos(); 
        } else {
            alert('❌ ' + d.message);
        }
    } catch (e) { alert('Error'); }
}

function imprimirDocumento() {
    if (!documentoActual) return;
    const contenido = document.getElementById('detalleContenido').innerHTML;
    const v = window.open('', '_blank');
    v.document.write(`
        <html>
        <head>
            <title>${documentoActual.cabecera.documento_numero}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; }
                th { background: #f8f9fa; }
            </style>
        </head>
        <body>
            <h1>HERMEN LTDA.</h1>
            <h2>${documentoActual.cabecera.documento_numero}</h2>
            ${contenido}
            <script>window.print();<\/script>
        </body>
        </html>
    `);
    v.document.close();
}

// ========== MODAL KARDEX ==========
async function verKardex(id) {
    try {
        const r = await fetch(`${baseUrl}/api/inventarios.php?action=kardex&id=${id}`);
        const d = await r.json();
        if (d.success) { 
            renderKardex(d); 
            document.getElementById('modalKardex').classList.add('show'); 
        } else {
            alert('❌ ' + d.message);
        }
    } catch (e) { alert('Error'); }
}

function renderKardex(data) {
    const p = data.producto, movs = data.movimientos || [];
    document.getElementById('kardexTitulo').textContent = 'Kardex: ' + p.codigo;
    document.getElementById('kardexHeader').innerHTML = `
        <h4>${p.nombre}</h4>
        <p><strong>Stock:</strong> ${formatNum(p.stock_actual)} | <strong>CPP:</strong> Bs. ${formatNum(p.costo_promedio)}</p>
    `;
    
    if (movs.length === 0) { 
        document.getElementById('kardexBody').innerHTML = '<tr><td colspan="8" style="text-align:center;">Sin movimientos</td></tr>'; 
        return; 
    }
    
    document.getElementById('kardexBody').innerHTML = movs.map(m => {
        const esEntrada = m.tipo_movimiento?.includes('ENTRADA');
        return `<tr class="${esEntrada?'entrada':'salida'}">
            <td>${m.fecha?new Date(m.fecha).toLocaleDateString('es-BO'):'-'}</td>
            <td>${m.documento_numero||'-'}</td>
            <td>${esEntrada?'Entrada':'Salida'}</td>
            <td style="text-align:right;">${esEntrada?formatNum(m.cantidad):''}</td>
            <td style="text-align:right;">${!esEntrada?formatNum(m.cantidad):''}</td>
            <td style="text-align:right;">${formatNum(m.stock_despues)}</td>
            <td style="text-align:right;">Bs. ${formatNum(m.costo_unitario)}</td>
            <td style="text-align:right;">Bs. ${formatNum(m.cpp_despues)}</td>
        </tr>`;
    }).join('');
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

console.log('✅ Módulo Materias Primas v1.8 cargado');
console.log('   - Modal Ingreso mejorado v2.0');
console.log('   - Filtros por tipo proveedor y categoría');
console.log('   - Cálculo IVA con columnas dinámicas');
console.log('   - Costos con 4 decimales');