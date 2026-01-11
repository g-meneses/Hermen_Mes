// ========== KARDEX FÍSICO Y VALORADO DE MATERIAS PRIMAS ==========

let kardexActual = {
    idInventario: null,
    nombreProducto: null,
    desde: null,
    hasta: null,
    tipoVista: 'valorado' // 'fisico' o 'valorado'
};

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
    document.getElementById('kardexProducto').textContent = nombreProducto;

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
    activeTab.classList.add('active');
    activeTab.style.background = '#1a237e';
    activeTab.style.color = 'white';

    // Mostrar/ocultar columnas según el tipo
    if (tipo === 'fisico') {
        // Kardex Físico - ocultar columnas de valores
        document.getElementById('kardexTableHead').innerHTML = `
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
        document.getElementById('kardexTableHead').innerHTML = `
            <tr>
                <th width="120">Fecha</th>
                <th>Documento</th>
                <th width="90">Entrada</th>
                <th width="90">Salida</th>
                <th width="90">Saldo</th>
                <th width="110">Valor Entrada</th>
                <th width="110">Valor Salida</th>
                <th width="110">Saldo Valor</th>
                <th width="100">CPP</th>
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
        alert('⚠️ Seleccione rango de fechas');
        return;
    }

    if (new Date(desde) > new Date(hasta)) {
        alert('⚠️ La fecha inicial no puede ser mayor a la fecha final');
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

        const response = await fetch(`${baseUrl}/api/kardex_mp.php?${params}`);
        const data = await response.json();

        if (data.success) {
            window.ultimosKardexDatos = data; // Guardar para cambio de pestañas
            renderKardex(data);
        } else {
            alert('❌ ' + data.message);
        }
    } catch (e) {
        console.error('Error al cargar kardex:', e);
        alert('Error al cargar el kardex');
    }
}

/**
 * Renderizar tabla de kardex según el tipo de vista
 */
function renderKardex(data) {
    const tbody = document.getElementById('kardexBody');
    const { producto, saldo_inicial, movimientos } = data;

    // Actualizar información del producto en el header
    document.getElementById('kardexProductoInfo').innerHTML = `
        <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
            <div>
                <strong>Código:</strong> ${producto.codigo} | 
                <strong>Unidad:</strong> ${producto.unidad} | 
                <strong>Stock Actual:</strong> <span style="color:#2e7d32; font-weight:700;">${formatNum(producto.stock_actual, 2)} ${producto.unidad}</span>
            </div>
            <div>
                <strong>CPP Actual:</strong> <span style="color:#1976d2; font-weight:700;">Bs. ${formatNum(producto.costo_promedio, 4)}</span>
            </div>
        </div>
    `;

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
                            ${mov.cantidad_entrada > 0 ? formatNum(mov.cantidad_entrada, 2) : '-'}
                        </td>
                        <td style="text-align:right; color:#c62828; font-weight:600;">
                            ${mov.cantidad_salida > 0 ? formatNum(mov.cantidad_salida, 2) : '-'}
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
                            ${mov.cantidad_entrada > 0 ? formatNum(mov.cantidad_entrada, 2) : '-'}
                        </td>
                        <td style="text-align:right;">
                            ${mov.cantidad_salida > 0 ? formatNum(mov.cantidad_salida, 2) : '-'}
                        </td>
                        <td style="text-align:right; font-weight:600; background:#e8f5e9;">
                            ${formatNum(mov.saldo_cantidad, 2)}
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

    // Crear ventana de impresión
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Kardex - ${kardexActual.nombreProducto}</title>
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
            <h3>${kardexActual.nombreProducto}</h3>
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
    // Preparar datos para exportación
    const params = new URLSearchParams({
        action: 'exportar',
        id_inventario: kardexActual.idInventario,
        desde: kardexActual.desde,
        hasta: kardexActual.hasta,
        tipo: kardexActual.tipoVista,
        formato: 'excel'
    });

    // Por ahora mostrar mensaje de desarrollo
    alert('🚧 Función de exportación en desarrollo\n\nPróximamente podrás exportar el kardex a Excel');

    // TODO: Implementar exportación real
    // window.open(`${baseUrl}/api/kardex_mp.php?${params}`, '_blank');
}

console.log('✅ Módulo Kardex cargado - Versión 2.0');

/**
 * Función para recalcular el kardex (corregir valores)
 */
async function recalcularKardex(idInventario) {
    if (!confirm('¿Desea recalcular todos los valores del kardex?\nEsto actualizará los costos y CPP de todos los movimientos.')) {
        return;
    }

    try {
        const response = await fetch(`${baseUrl}/api/kardex_mp.php?action=recalcular&id_inventario=${idInventario}`);
        const data = await response.json();

        if (data.success) {
            alert('✅ ' + data.message + '\n\nCPP Final: Bs. ' + formatNum(data.cpp_final, 4));
            // Recargar el kardex
            if (kardexActual.idInventario) {
                buscarKardex();
            }
        } else {
            alert('❌ Error: ' + data.message);
        }
    } catch (e) {
        console.error('Error al recalcular:', e);
        alert('Error al recalcular el kardex');
    }
}