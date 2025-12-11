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

// ========== MODAL INGRESO ==========
function abrirModalIngreso() {
    document.getElementById('ingresoDocumento').value = generarNumeroDoc('ING-MP');
    document.getElementById('ingresoFecha').value = new Date().toISOString().split('T')[0];
    
    // Poblar select de proveedores agrupados por tipo
    const selectProv = document.getElementById('ingresoProveedor');
    
    // Separar por tipo
    const locales = proveedores.filter(p => p.tipo === 'LOCAL');
    const importacion = proveedores.filter(p => p.tipo === 'IMPORTACION');
    
    let optionsHtml = '<option value="">Seleccione proveedor...</option>';
    
    if (locales.length > 0) {
        optionsHtml += '<optgroup label="🇧🇴 Proveedores Locales">';
        locales.forEach(p => {
            const nombre = p.nombre_comercial || p.razon_social;
            optionsHtml += `<option value="${p.id_proveedor}" data-moneda="${p.moneda}">${p.codigo} - ${nombre}</option>`;
        });
        optionsHtml += '</optgroup>';
    }
    
    if (importacion.length > 0) {
        optionsHtml += '<optgroup label="🌎 Proveedores Importación">';
        importacion.forEach(p => {
            const nombre = p.nombre_comercial || p.razon_social;
            optionsHtml += `<option value="${p.id_proveedor}" data-moneda="${p.moneda}">${p.codigo} - ${nombre} (${p.pais})</option>`;
        });
        optionsHtml += '</optgroup>';
    }
    
    selectProv.innerHTML = optionsHtml;
    
    // Reset otros campos
    document.getElementById('ingresoConFactura').checked = false;
    document.getElementById('ingresoReferencia').value = '';
    document.getElementById('ingresoObservaciones').value = '';
    
    lineasIngreso = [];
    renderLineasIngreso();
    actualizarMonedaIngreso();
    document.getElementById('modalIngreso').classList.add('show');
}

// Actualizar indicador de moneda cuando cambia el proveedor
function actualizarMonedaIngreso() {
    const select = document.getElementById('ingresoProveedor');
    const selectedOption = select.options[select.selectedIndex];
    const moneda = selectedOption?.dataset?.moneda || 'BOB';
    
    // Actualizar etiquetas de moneda en el modal
    document.querySelectorAll('.moneda-label').forEach(el => {
        el.textContent = moneda === 'USD' ? 'USD' : 'Bs.';
    });
}

function agregarLineaIngreso() {
    lineasIngreso.push({ id_inventario: '', cantidad: 0, costo_unitario: 0 });
    renderLineasIngreso();
}

function renderLineasIngreso() {
    const tbody = document.getElementById('ingresoLineasBody');
    const conFactura = document.getElementById('ingresoConFactura').checked;
    
    tbody.innerHTML = lineasIngreso.map((l, i) => {
        const costoNeto = conFactura ? l.costo_unitario / 1.13 : l.costo_unitario;
        const subtotal = l.cantidad * costoNeto;
        return `<tr>
            <td>
                <select onchange="actualizarLineaIngreso(${i}, 'id_inventario', this.value)">
                    <option value="">Seleccione...</option>
                    ${productosCompletos.map(p => `<option value="${p.id_inventario}" ${p.id_inventario == l.id_inventario ? 'selected' : ''}>${p.codigo} - ${p.nombre}</option>`).join('')}
                </select>
            </td>
            <td><input type="number" step="0.01" value="${l.cantidad}" onchange="actualizarLineaIngreso(${i}, 'cantidad', this.value)"></td>
            <td><input type="number" step="0.01" value="${l.costo_unitario}" onchange="actualizarLineaIngreso(${i}, 'costo_unitario', this.value)"></td>
            <td style="text-align:right;">Bs. ${formatNum(costoNeto)}</td>
            <td style="text-align:right;">Bs. ${formatNum(subtotal)}</td>
            <td><button class="btn-icon" style="background:#dc3545;color:white;" onclick="eliminarLineaIngreso(${i})"><i class="fas fa-trash"></i></button></td>
        </tr>`;
    }).join('');
    
    recalcularIngreso();
}

function actualizarLineaIngreso(index, campo, valor) {
    lineasIngreso[index][campo] = campo === 'id_inventario' ? valor : toNum(valor);
    renderLineasIngreso();
}

function eliminarLineaIngreso(index) {
    lineasIngreso.splice(index, 1);
    renderLineasIngreso();
}

function recalcularIngreso() {
    const conFactura = document.getElementById('ingresoConFactura').checked;
    let totalNeto = 0, totalBruto = 0;
    
    lineasIngreso.forEach(l => {
        const costoNeto = conFactura ? l.costo_unitario / 1.13 : l.costo_unitario;
        totalNeto += l.cantidad * costoNeto;
        totalBruto += l.cantidad * l.costo_unitario;
    });
    
    const iva = conFactura ? totalBruto - totalNeto : 0;
    
    document.getElementById('ingresoTotalNeto').textContent = 'Bs. ' + formatNum(totalNeto);
    document.getElementById('ingresoIVA').textContent = 'Bs. ' + formatNum(iva);
    document.getElementById('ingresoTotal').textContent = 'Bs. ' + formatNum(totalBruto);
}

async function guardarIngreso() {
    if (lineasIngreso.length === 0) { alert('Agregue al menos una línea'); return; }
    if (lineasIngreso.some(l => !l.id_inventario || l.cantidad <= 0)) { alert('Complete todos los campos'); return; }
    
    const conFactura = document.getElementById('ingresoConFactura').checked;
    
    const data = {
        action: 'movimiento',
        tipo_movimiento: 'ENTRADA_COMPRA',
        documento_tipo: 'INGRESO',
        documento_numero: document.getElementById('ingresoDocumento').value,
        fecha: document.getElementById('ingresoFecha').value,
        id_proveedor: document.getElementById('ingresoProveedor').value || null,
        referencia: document.getElementById('ingresoReferencia').value || null,
        con_factura: conFactura,
        observaciones: document.getElementById('ingresoObservaciones').value,
        lineas: lineasIngreso.map(l => ({
            id_inventario: l.id_inventario,
            cantidad: l.cantidad,
            costo_unitario: conFactura ? l.costo_unitario / 1.13 : l.costo_unitario,
            costo_con_iva: l.costo_unitario
        }))
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
            cerrarModal('modalIngreso');
            cargarDatos();
        } else {
            alert('❌ ' + d.message);
        }
    } catch (e) {
        console.error('Error:', e);
        alert('Error al guardar');
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

console.log('✅ Módulo Materias Primas v1.7 cargado');
console.log('   - Proveedores integrados al modal Ingreso');
console.log('   - Select agrupado por tipo (Local/Import)');
console.log('   - Modal Editar funcionando');