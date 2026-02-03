// ========== CONFIGURACIÓN GLOBAL DE RESPALDO ==========
if (typeof baseUrl === 'undefined') {
    window.baseUrl = window.location.origin + '/mes_hermen';
}

if (typeof formatNum === 'undefined') {
    window.toNum = function (value) {
        if (value === null || value === undefined || value === '') return 0;
        if (typeof value === 'number') return value;
        let str = String(value).trim();
        if (str.includes(',') && str.includes('.')) str = str.replace(/,/g, '');
        else if (str.includes(',')) {
            if (/,\d{2}$/.test(str)) str = str.replace(',', '.');
            else str = str.replace(/,/g, '');
        }
        const num = parseFloat(str);
        return isNaN(num) ? 0 : num;
    };

    window.formatNum = function (value, decimals = 2) {
        const num = toNum(value);
        return num.toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    };
}

if (typeof window.reporteActual === 'undefined') {
    window.reporteActual = {
        tipo: null,
        filtros: {}
    };
}

console.log('📊 reportes_mp.js cargado correctamente');

/**
 * Abre el modal de reporte con la configuración inicial
 */
window.abrirReporte = function (tipo) {
    console.log('📊 abrirReporte llamado con tipo:', tipo);

    // Verificar que los elementos necesarios existen
    const modalReporte = document.getElementById('modalReporte');
    const reporteTitulo = document.getElementById('reporteTitulo');
    const reporteFiltros = document.getElementById('reporteFiltros');
    const reporteContenido = document.getElementById('reporteContenido');

    if (!modalReporte || !reporteTitulo || !reporteFiltros || !reporteContenido) {
        console.error('❌ Error: Elementos del modal de reportes no encontrados:', {
            modalReporte: !!modalReporte,
            reporteTitulo: !!reporteTitulo,
            reporteFiltros: !!reporteFiltros,
            reporteContenido: !!reporteContenido
        });
        alert('Error: No se pudo abrir el modal de reportes. Por favor recargue la página.');
        return;
    }

    window.reporteActual.tipo = tipo;
    const titulos = {
        'consolidado': 'Reporte Consolidado de Inventarios',
        'stock_valorizado': 'Reporte de Stock Valorizado',
        'movimientos': 'Reporte de Movimientos de Inventario',
        'analisis': 'Análisis Estadístico de Inventario',
        'tipos_categorias': 'Reporte de Tipos y Categorías',
        'rotacion': 'Reporte de Rotación de Inventario'
    };

    reporteTitulo.innerHTML = `<i class="fas fa-chart-bar"></i> ${titulos[tipo] || 'Reporte'}`;

    // Configurar filtros según el tipo
    renderFiltrosReporte(tipo);

    // Cargar reporte inicial
    cargarReporte(tipo);

    // Mostrar modal
    modalReporte.classList.add('show');
    console.log('✅ Modal de reporte abierto correctamente');
};

/**
 * Renderiza los campos de filtro necesarios para cada reporte
 */
function renderFiltrosReporte(tipo) {
    const container = document.getElementById('reporteFiltros');
    let html = '';

    const hoy = new Date().toISOString().split('T')[0];
    const primeroMes = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];

    if (tipo === 'consolidado') {
        html = `<p style="margin:0; color:#666;"><i class="fas fa-info-circle"></i> Resumen de todos los tipos de inventario del sistema.</p>`;
    } else if (tipo === 'stock_valorizado') {
        html = `
            <div class="form-group" style="flex:1; min-width:150px;">
                <label>Tipo Inventario</label>
                <select id="repFiltroTipo" onchange="actualizarCategoriasReporte()">
                    <option value="">Todos los tipos</option>
                </select>
            </div>
            <div class="form-group" style="flex:1; min-width:150px;">
                <label>Categoría</label>
                <select id="repFiltroCat" onchange="actualizarSubcategoriasReporte()">
                    <option value="">Todas las categorías</option>
                </select>
            </div>
            <div class="form-group" style="flex:1; min-width:150px;">
                <label>Subcategoría</label>
                <select id="repFiltroSubcat">
                    <option value="">Todas las subcategorías</option>
                </select>
            </div>
            <div class="form-group" style="display:flex; align-items:flex-end;">
                <button class="btn btn-primary" onclick="cargarReporte('stock_valorizado')"><i class="fas fa-search"></i> Filtrar</button>
            </div>
        `;

        // Cargar tipos de inventario
        setTimeout(cargarTiposParaReporte, 100);
    } else if (tipo === 'movimientos') {
        html = `
            <div class="form-group">
                <label>Desde</label>
                <input type="date" id="repDesde" value="${primeroMes}">
            </div>
            <div class="form-group">
                <label>Hasta</label>
                <input type="date" id="repHasta" value="${hoy}">
            </div>
            <div class="form-group">
                <label>Tipo</label>
                <select id="repTipoMov">
                    <option value="">Todos</option>
                    <option value="ENTRADA">Entradas</option>
                    <option value="SALIDA">Salidas</option>
                </select>
            </div>
            <div class="form-group" style="display:flex; align-items:flex-end;">
                <button class="btn btn-primary" onclick="cargarReporte('movimientos')"><i class="fas fa-search"></i> Buscar</button>
            </div>
        `;
    } else if (tipo === 'analisis') {
        html = `<p style="margin:0; color:#666;"><i class="fas fa-info-circle"></i> Resumen ejecutivo del estado actual del inventario.</p>`;
    } else if (tipo === 'tipos_categorias') {
        html = `<p style="margin:0; color:#666;"><i class="fas fa-info-circle"></i> Tipos de inventario con sus categorías y valores.</p>`;
    } else if (tipo === 'rotacion') {
        html = `
            <div class="form-group">
                <label>Desde</label>
                <input type="date" id="repDesde" value="${primeroMes}">
            </div>
            <div class="form-group">
                <label>Hasta</label>
                <input type="date" id="repHasta" value="${hoy}">
            </div>
            <div class="form-group" style="flex:1; min-width:150px;">
                <label>Tipo Inventario</label>
                <select id="repFiltroTipo" onchange="actualizarCategoriasReporte()">
                    <option value="">Todos los tipos</option>
                </select>
            </div>
            <div class="form-group" style="flex:1; min-width:150px;">
                <label>Categoría</label>
                <select id="repFiltroCat">
                    <option value="">Todas las categorías</option>
                </select>
            </div>
            <div class="form-group" style="display:flex; align-items:flex-end;">
                <button class="btn btn-primary" onclick="cargarReporte('rotacion')"><i class="fas fa-search"></i> Generar</button>
            </div>
        `;

        // Cargar tipos de inventario
        setTimeout(cargarTiposParaReporte, 100);
    }

    container.innerHTML = html;
}

/**
 * Carga los datos desde la API
 */
async function cargarReporte(tipo) {
    console.log('📊 cargarReporte llamado con tipo:', tipo);

    const contenido = document.getElementById('reporteContenido');
    if (!contenido) {
        console.error('❌ Error: Elemento reporteContenido no encontrado');
        return;
    }

    contenido.innerHTML = `<p style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Cargando datos...</p>`;

    try {
        let url = `${baseUrl}/api/reportes_mp.php?action=${tipo}`;

        if (tipo === 'stock_valorizado') {
            const tipoId = document.getElementById('repFiltroTipo')?.value;
            const catId = document.getElementById('repFiltroCat')?.value;
            const subcatId = document.getElementById('repFiltroSubcat')?.value;
            if (tipoId) url += `&id_tipo=${tipoId}`;
            if (catId) url += `&id_categoria=${catId}`;
            if (subcatId) url += `&id_subcategoria=${subcatId}`;
        } else if (tipo === 'movimientos') {
            const desde = document.getElementById('repDesde').value;
            const hasta = document.getElementById('repHasta').value;
            const tipoMov = document.getElementById('repTipoMov').value;
            url += `&desde=${desde}&hasta=${hasta}&tipo=${tipoMov}`;
        } else if (tipo === 'rotacion') {
            const desde = document.getElementById('repDesde').value;
            const hasta = document.getElementById('repHasta').value;
            const tipoId = document.getElementById('repFiltroTipo')?.value;
            const catId = document.getElementById('repFiltroCat')?.value;
            url += `&desde=${desde}&hasta=${hasta}`;
            if (tipoId) url += `&id_tipo=${tipoId}`;
            if (catId) url += `&id_categoria=${catId}`;
        }

        console.log('📊 Haciendo fetch a:', url);
        const response = await fetch(url);
        const data = await response.json();
        console.log('📊 Respuesta recibida:', data);

        if (data.success) {
            renderDataReporte(tipo, data);
            console.log('✅ Reporte renderizado correctamente');
        } else {
            console.warn('⚠️ API retornó error:', data.message);
            contenido.innerHTML = `<p style="color:red; text-align:center; padding:20px;">${data.message}</p>`;
        }
    } catch (e) {
        console.error('❌ Error en cargarReporte:', e);
        contenido.innerHTML = `<p style="color:red; text-align:center; padding:20px;">Error de conexión con el servidor: ${e.message}</p>`;
    }
}

/**
 * Renderiza la tabla de datos según el reporte
 */
function renderDataReporte(tipo, data) {
    console.log('📊 renderDataReporte iniciado para tipo:', tipo);

    const contenido = document.getElementById('reporteContenido');
    if (!contenido) {
        console.error('❌ Error: reporteContenido no encontrado en renderDataReporte');
        return;
    }

    let html = '';

    try {
    if (tipo === 'consolidado') {
        html = `
            <table class="tabla-reporte" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#1a237e; color:white;">
                        <th style="padding:10px; text-align:left; color:white;">Tipo de Inventario</th>
                        <th style="padding:10px; text-align:right; color:white;">Total Items</th>
                        <th style="padding:10px; text-align:right; color:white;">Alertas</th>
                        <th style="padding:10px; text-align:right; color:white;">Valor Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.tipos.map(tipo => `
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:10px;">
                                <i class="${tipo.icono}" style="color:${tipo.color}; margin-right:8px;"></i>
                                <span style="font-weight:600;">${tipo.nombre}</span>
                            </td>
                            <td style="padding:10px; text-align:right;">${formatNum(tipo.total_items, 0)}</td>
                            <td style="padding:10px; text-align:right;">
                                ${tipo.alertas > 0 ? `<span style="background:#ffebee; color:#c62828; padding:4px 8px; border-radius:4px; font-weight:600;">${tipo.alertas}</span>` : '-'}
                            </td>
                            <td style="padding:10px; text-align:right; font-weight:700; color:#2e7d32;">Bs. ${formatNum(tipo.valor_total, 2)}</td>
                        </tr>
                    `).join('')}
                </tbody>
                <tfoot>
                    <tr style="background:#f5f5f5; font-weight:700; font-size:1.1rem;">
                        <td style="padding:12px;">TOTALES GENERALES</td>
                        <td style="padding:12px; text-align:right;">${formatNum(data.totales.items, 0)}</td>
                        <td style="padding:12px; text-align:right;">${data.totales.alertas}</td>
                        <td style="padding:12px; text-align:right; color:#1b5e20; font-size:1.2rem;">Bs. ${formatNum(data.totales.valor, 2)}</td>
                    </tr>
                </tfoot>
            </table>
        `;
    } else if (tipo === 'stock_valorizado') {
        html = `
            <table class="tabla-reporte" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#1a237e;">
                        <th style="padding:10px; text-align:left; color:white;">Cod.</th>
                        <th style="padding:10px; text-align:left; color:white;">Producto</th>
                        <th style="padding:10px; text-align:left; color:white;">Categoría</th>
                        <th style="padding:10px; text-align:right; color:white;">Stock</th>
                        <th style="padding:10px; text-align:center; color:white;">Unid.</th>
                        <th style="padding:10px; text-align:right; color:white;">CPP</th>
                        <th style="padding:10px; text-align:right; color:white;">Valor Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.data.map(row => `
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:8px;">${row.codigo}</td>
                            <td style="padding:8px; font-weight:600;">${row.nombre}</td>
                            <td style="padding:8px; font-size:0.85rem;">${row.categoria}</td>
                            <td style="padding:8px; text-align:right; font-weight:700;">${formatNum(row.stock_actual, 2)}</td>
                            <td style="padding:8px; text-align:center;">${row.unidad}</td>
                            <td style="padding:8px; text-align:right;">Bs. ${formatNum(row.cpp, 4)}</td>
                            <td style="padding:8px; text-align:right; font-weight:700; color:#2e7d32; background:#f1f8e9;">Bs. ${formatNum(row.valor_total, 2)}</td>
                        </tr>
                    `).join('')}
                </tbody>
                <tfoot>
                    <tr style="background:#f5f5f5; font-weight:700;">
                        <td colspan="6" style="padding:10px; text-align:right;">TOTAL VALORIZADO:</td>
                        <td style="padding:10px; text-align:right; color:#1b5e20;">Bs. ${formatNum(data.data.reduce((s, r) => s + parseFloat(r.valor_total), 0), 2)}</td>
                    </tr>
                </tfoot>
            </table>
        `;
    } else if (tipo === 'movimientos') {
        html = `
            <table class="tabla-reporte" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#37474f; color:white;">
                        <th style="padding:10px; text-align:left;">Fecha</th>
                        <th style="padding:10px; text-align:left;">Documento</th>
                        <th style="padding:10px; text-align:left;">Producto</th>
                        <th style="padding:10px; text-align:left;">Tipo Mov.</th>
                        <th style="padding:10px; text-align:right;">Cant.</th>
                        <th style="padding:10px; text-align:right;">Costo Unit.</th>
                        <th style="padding:10px; text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.data.map(row => `
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:8px; font-size:0.8rem;">${new Date(row.fecha).toLocaleDateString()}</td>
                            <td style="padding:8px; font-weight:600;">${row.documento_numero}</td>
                            <td style="padding:8px;">${row.producto}</td>
                            <td style="padding:8px;"><span class="badge" style="background:${row.tipo_movimiento.includes('ENTRADA') ? '#e8f5e9' : '#ffebee'}; color:${row.tipo_movimiento.includes('ENTRADA') ? '#2e7d32' : '#c62828'}; font-size:0.75rem; padding:2px 6px; border-radius:4px;">${row.tipo_movimiento}</span></td>
                            <td style="padding:8px; text-align:right;">${formatNum(row.cantidad, 2)}</td>
                            <td style="padding:8px; text-align:right;">${formatNum(row.costo_unitario, 4)}</td>
                            <td style="padding:8px; text-align:right; font-weight:600;">Bs. ${formatNum(row.costo_total, 2)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } else if (tipo === 'analisis') {
        html = `
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div style="background:white; padding:15px; border-radius:8px; border:1px solid #ddd;">
                    <h4 style="margin-bottom:15px; color:#1a237e;"><i class="fas fa-layer-group"></i> Valor por Categoría</h4>
                    <table style="width:100%;">
                        ${data.categorias.map(c => `
                            <tr>
                                <td style="padding:5px;">${c.categoria}</td>
                                <td style="text-align:right; font-weight:600;">Bs. ${formatNum(c.valor_total, 2)}</td>
                            </tr>
                        `).join('')}
                    </table>
                </div>
                <div style="background:white; padding:15px; border-radius:8px; border:1px solid #ddd;">
                    <h4 style="margin-bottom:15px; color:#c62828;"><i class="fas fa-crown"></i> Top 10 Productos (Valor)</h4>
                    <table style="width:100%;">
                        ${data.top_productos.map(p => `
                            <tr>
                                <td style="padding:5px; font-size:0.85rem;">${p.nombre}</td>
                                <td style="text-align:right; font-weight:600;">Bs. ${formatNum(p.valor, 2)}</td>
                            </tr>
                        `).join('')}
                    </table>
                </div>
            </div>
        `;
    } else if (tipo === 'tipos_categorias') {
        html = `
            <table class="tabla-reporte" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#1a237e;">
                        <th style="padding:12px; text-align:left; color:white;">Tipo / Categoría</th>
                        <th style="padding:12px; text-align:right; color:white;">Items</th>
                        <th style="padding:12px; text-align:right; color:white;">Valor Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.tipos.map(tipo => `
                        <tr style="background:#e3f2fd; font-weight:700;">
                            <td style="padding:12px;">
                                <i class="${tipo.icono}" style="color:${tipo.color}; margin-right:8px;"></i>
                                ${tipo.nombre}
                            </td>
                            <td style="padding:12px; text-align:right;">${formatNum(tipo.subtotal_items, 0)}</td>
                            <td style="padding:12px; text-align:right; color:#1565c0;">Bs. ${formatNum(tipo.subtotal_valor, 2)}</td>
                        </tr>
                        ${tipo.categorias.length > 0 ? tipo.categorias.map(cat => `
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:10px 10px 10px 40px; color:#555;">
                                    <i class="fas fa-folder-open" style="color:#90a4ae; margin-right:8px;"></i>
                                    ${cat.nombre}
                                </td>
                                <td style="padding:10px; text-align:right;">${formatNum(cat.items, 0)}</td>
                                <td style="padding:10px; text-align:right;">Bs. ${formatNum(cat.valor, 2)}</td>
                            </tr>
                        `).join('') : `
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:10px 10px 10px 40px; color:#999; font-style:italic;">
                                    <i class="fas fa-folder" style="color:#ccc; margin-right:8px;"></i>
                                    Sin categorías registradas
                                </td>
                                <td style="padding:10px; text-align:right; color:#999;">-</td>
                                <td style="padding:10px; text-align:right; color:#999;">-</td>
                            </tr>
                        `}
                    `).join('')}
                </tbody>
                <tfoot>
                    <tr style="background:#1a237e; color:white; font-weight:700; font-size:1.1rem;">
                        <td style="padding:14px;">TOTAL GENERAL</td>
                        <td style="padding:14px; text-align:right;">${formatNum(data.total_general.items, 0)}</td>
                        <td style="padding:14px; text-align:right;">Bs. ${formatNum(data.total_general.valor, 2)}</td>
                    </tr>
                </tfoot>
            </table>
        `;
    } else if (tipo === 'rotacion') {
        // Helper para obtener color según clasificación
        const getColorClasificacion = (clasificacion) => {
            switch (clasificacion) {
                case 'ALTA': return '#4caf50';
                case 'MEDIA': return '#ff9800';
                case 'BAJA': return '#f44336';
                case 'SIN_MOVIMIENTO': return '#9e9e9e';
                default: return '#666';
            }
        };

        const getBadgeClasificacion = (clasificacion) => {
            const color = getColorClasificacion(clasificacion);
            const texto = clasificacion.replace('_', ' ');
            return `<span style="background:${color}; color:white; padding:3px 8px; border-radius:4px; font-size:0.75rem; font-weight:600;">${texto}</span>`;
        };

        html = `
            <div style="background:#e3f2fd; padding:12px; border-radius:8px; margin-bottom:15px;">
                <p style="margin:0; color:#1565c0; font-weight:600;">
                    <i class="fas fa-calendar-alt"></i> Período: ${new Date(data.periodo.desde).toLocaleDateString('es-BO')} - ${new Date(data.periodo.hasta).toLocaleDateString('es-BO')} (${data.periodo.dias} días)
                </p>
            </div>
            <table class="tabla-reporte" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#1a237e;">
                        <th style="padding:10px; text-align:left; color:white;">Producto</th>
                        <th style="padding:10px; text-align:right; color:white;">Inv. Promedio</th>
                        <th style="padding:10px; text-align:right; color:white;">Consumo</th>
                        <th style="padding:10px; text-align:right; color:white;">Rotación</th>
                        <th style="padding:10px; text-align:center; color:white;">Días Stock</th>
                        <th style="padding:10px; text-align:center; color:white;">Clasificación</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.data.map(row => {
            const alertaDias = row.dias_stock > 180 ? ' ⚠️' : '';
            const alertaRotacion = row.rotacion < 0.1 && row.salidas > 0 ? ' 🔴' : '';

            return `
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:8px;">
                                    <div style="font-weight:600;">${row.nombre}</div>
                                    <div style="font-size:0.8rem; color:#666;">${row.codigo} | ${row.categoria}</div>
                                </td>
                                <td style="padding:8px; text-align:right; font-weight:600;">
                                    ${formatNum(row.inventario_promedio, 2)} ${row.unidad}
                                </td>
                                <td style="padding:8px; text-align:right; ${row.salidas > 0 ? 'color:#2e7d32; font-weight:600;' : 'color:#999;'}">
                                    ${formatNum(row.salidas, 2)} ${row.unidad}
                                </td>
                                <td style="padding:8px; text-align:right; font-weight:700; color:${getColorClasificacion(row.clasificacion)};">
                                    ${formatNum(row.rotacion, 2)}${alertaRotacion}
                                </td>
                                <td style="padding:8px; text-align:center; font-weight:600; ${row.dias_stock > 180 ? 'background:#fff3e0; color:#e65100;' : ''}">
                                    ${row.dias_stock >= 999 ? '∞' : row.dias_stock + ' días'}${alertaDias}
                                </td>
                                <td style="padding:8px; text-align:center;">
                                    ${getBadgeClasificacion(row.clasificacion)}
                                </td>
                            </tr>
                        `;
        }).join('')}
                </tbody>
                <tfoot>
                    <tr style="background:#f5f5f5; font-weight:700;">
                        <td colspan="6" style="padding:12px; text-align:center; color:#666;">
                            <i class="fas fa-info-circle"></i> 
                            Rotación Alta: ≥2 | Media: 0.5-2 | Baja: <0.5 | Sin Movimiento: 0
                        </td>
                    </tr>
                </tfoot>
            </table>
        `;
    }

    contenido.innerHTML = html;
    console.log('✅ HTML renderizado en reporteContenido, longitud:', html.length);

    } catch (renderError) {
        console.error('❌ Error en renderDataReporte:', renderError);
        contenido.innerHTML = `<p style="color:red; text-align:center; padding:20px;">Error al renderizar el reporte: ${renderError.message}</p>`;
    }
}

/**
 * Función provisional para descargar Excel (CSV compatible)
 */
function descargarExcelReporte() {
    const table = document.querySelector('#reporteContenido table');
    if (!table) {
        alert('No hay datos para exportar');
        return;
    }

    let csv = [];
    const rows = table.querySelectorAll('tr');

    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        for (let j = 0; j < cols.length; j++) {
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
            data = data.replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        csv.push(row.join(','));
    }

    const csvContent = "data:text/csv;charset=utf-8," + csv.join("\n");
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `reporte_${reporteActual.tipo}_${new Date().getTime()}.csv`);
    document.body.appendChild(link);
    link.click();
}

/**
 * Exportar reporte a PDF usando jsPDF
 */
function descargarPDFReporte() {
    const { jsPDF } = window.jspdf;
    if (!jsPDF) {
        alert('Error: Librería jsPDF no cargada');
        return;
    }

    const doc = new jsPDF();
    const titulo = document.getElementById('reporteTitulo').innerText;
    const fecha = new Date().toLocaleDateString('es-BO', { year: 'numeric', month: 'long', day: 'numeric' });

    // Intentar añadir logo si existe en el header
    const logoImg = document.querySelector('.sidebar-header img');
    if (logoImg) {
        try {
            doc.addImage(logoImg, 'PNG', 14, 10, 40, 0);
        } catch (e) {
            console.error('Error al añadir logo al PDF:', e);
        }
    }

    // Encabezado
    doc.setFontSize(16);
    doc.setTextColor(26, 35, 126);
    doc.text(titulo, logoImg ? 60 : 14, 18);

    doc.setFontSize(10);
    doc.setTextColor(100);
    doc.text(`Generado: ${fecha}`, logoImg ? 60 : 14, 25);

    // Obtener datos de la tabla
    const table = document.querySelector('#reporteContenido table');
    if (!table) {
        alert('No hay datos para exportar');
        return;
    }

    // Extraer datos de la tabla
    const headers = [];
    const rows = [];

    // Encabezados
    const headerCells = table.querySelectorAll('thead th');
    headerCells.forEach(cell => headers.push(cell.innerText));

    // Filas del cuerpo
    const bodyRows = table.querySelectorAll('tbody tr');
    bodyRows.forEach(row => {
        const rowData = [];
        row.querySelectorAll('td').forEach(cell => {
            rowData.push(cell.innerText.replace(/\s+/g, ' ').trim());
        });
        rows.push(rowData);
    });

    // Fila de totales si existe
    const footerRow = table.querySelector('tfoot tr');
    if (footerRow) {
        const footerData = [];
        footerRow.querySelectorAll('td').forEach(cell => {
            footerData.push(cell.innerText.replace(/\s+/g, ' ').trim());
        });
        rows.push(footerData);
    }

    // Generar tabla con autoTable
    doc.autoTable({
        head: [headers],
        body: rows,
        startY: 28,
        theme: 'grid',
        headStyles: {
            fillColor: [26, 35, 126],
            textColor: 255,
            fontStyle: 'bold',
            halign: 'center'
        },
        styles: {
            fontSize: 9,
            cellPadding: 3
        },
        columnStyles: {
            0: { halign: 'left' },
            1: { halign: 'right' },
            2: { halign: 'right' },
            3: { halign: 'right' }
        },
        didParseCell: function (data) {
            // Resaltar fila de totales
            if (data.row.index === rows.length - 1 && footerRow) {
                data.cell.styles.fillColor = [245, 245, 245];
                data.cell.styles.fontStyle = 'bold';
                data.cell.styles.fontSize = 10;
            }
        }
    });

    // Pie de página
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setTextColor(150);
        doc.text(
            `Página ${i} de ${pageCount}`,
            doc.internal.pageSize.getWidth() / 2,
            doc.internal.pageSize.getHeight() - 10,
            { align: 'center' }
        );
    }

    // Descargar
    doc.save(`reporte_${reporteActual.tipo}_${new Date().getTime()}.pdf`);
}

/**
 * Imprimir reporte
 */
function imprimirReporte() {
    const tit = document.getElementById('reporteTitulo').innerText;
    const cont = document.getElementById('reporteContenido').innerHTML;

    const win = window.open('', '', 'height=700,width=900');
    win.document.write('<html><head><title>Imprimir Reporte</title>');
    win.document.write('<style>body{font-family:Arial;padding:20px;} .print-header{display:flex;align-items:center;gap:20px;margin-bottom:20px;} .print-logo{max-width:150px;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f5f5f5;}</style>');
    win.document.write('</head><body>');

    win.document.write('<div class="print-header">');
    const logoImg = document.querySelector('.sidebar-header img');
    if (logoImg) {
        win.document.write(`<img src="${logoImg.src}" class="print-logo">`);
    }
    win.document.write('<div>');
    win.document.write(`<h2 style="margin:0;">${tit}</h2>`);
    win.document.write(`<p style="margin:5px 0 0 0; color:#666;">Fecha: ${new Date().toLocaleString()}</p>`);
    win.document.write('</div></div>');

    win.document.write(cont);
    win.document.write('</body></html>');
    win.document.close();
    win.print();
}

/**
 * Carga categorías si el reporte las necesita y no están disponibles
 */
async function fetchCategoriasParaReporte() {
    try {
        const response = await fetch(`${baseUrl}/api/centro_inventarios.php?action=categorias&tipo_id=1`);
        const data = await response.json();
        if (data.success && data.categorias) {
            window.categorias = data.categorias;
            // Re-renderizar filtros si el reporte sigue abierto y es de stock
            if (reporteActual.tipo === 'stock_valorizado') {
                renderFiltrosReporte('stock_valorizado');
            }
        }
    } catch (e) {
        console.error('Error cargando categorías para reporte:', e);
    }
}

// ========== FUNCIONES PARA FILTROS EN CASCADA ==========

// Cargar tipos de inventario en el dropdown
async function cargarTiposParaReporte() {
    try {
        const response = await fetch(`${baseUrl}/api/categorias.php?action=get_tipos`);
        const data = await response.json();
        if (data.success) {
            const select = document.getElementById('repFiltroTipo');
            if (select) {
                select.innerHTML = '<option value="">Todos los tipos</option>' +
                    data.tipos.map(t => `<option value="${t.id_tipo_inventario}">${t.nombre}</option>`).join('');
            }
        }
    } catch (e) {
        console.error('Error cargando tipos:', e);
    }
}

// Actualizar categorías según el tipo seleccionado
window.actualizarCategoriasReporte = async function () {
    const tipoId = document.getElementById('repFiltroTipo')?.value;
    const selectCat = document.getElementById('repFiltroCat');
    const selectSubcat = document.getElementById('repFiltroSubcat');

    if (selectCat) {
        selectCat.innerHTML = '<option value="">Cargando...</option>';
    }
    if (selectSubcat) {
        selectSubcat.innerHTML = '<option value="">Todas las subcategorías</option>';
    }

    try {
        let url = `${baseUrl}/api/categorias.php?action=get_categorias`;
        if (tipoId) url += `&id_tipo=${tipoId}`;

        const response = await fetch(url);
        const data = await response.json();
        if (data.success && selectCat) {
            selectCat.innerHTML = '<option value="">Todas las categorías</option>' +
                data.categorias.map(c => `<option value="${c.id_categoria}">${c.nombre}</option>`).join('');
        }
    } catch (e) {
        console.error('Error cargando categorías:', e);
        if (selectCat) selectCat.innerHTML = '<option value="">Error al cargar</option>';
    }
};

// Actualizar subcategorías según la categoría seleccionada
window.actualizarSubcategoriasReporte = async function () {
    const catId = document.getElementById('repFiltroCat')?.value;
    const selectSubcat = document.getElementById('repFiltroSubcat');

    if (!selectSubcat) return;

    if (!catId) {
        selectSubcat.innerHTML = '<option value="">Todas las subcategorías</option>';
        return;
    }

    selectSubcat.innerHTML = '<option value="">Cargando...</option>';

    try {
        const response = await fetch(`${baseUrl}/api/categorias.php?action=get_subcategorias&id_categoria=${catId}`);
        const data = await response.json();
        if (data.success) {
            selectSubcat.innerHTML = '<option value="">Todas las subcategorías</option>' +
                data.subcategorias.map(s => `<option value="${s.id_subcategoria}">${s.nombre}</option>`).join('');
        }
    } catch (e) {
        console.error('Error cargando subcategorías:', e);
        selectSubcat.innerHTML = '<option value="">Error al cargar</option>';
    }
};

// ========== ESTILOS ADICIONALES PARA REPORTES ==========
if (typeof window.estilosReportesMP === 'undefined') {
    window.estilosReportesMP = `
    <style>
    .modal-content.xlarge {
        width: 95% !important;
        max-width: 1200px !important;
    }
    .tabla-reporte th {
        position: sticky;
        top: 0;
        z-index: 10;
    }
    </style>
    `;
    document.head.insertAdjacentHTML('beforeend', window.estilosReportesMP);
}
