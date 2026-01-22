// ========== MODAL HISTORIAL DE MOVIMIENTOS - EMP ==========

let documentosHistorial = [];

function abrirModalHistorial() {
    // Establecer fechas por defecto (últimos 3 meses)
    const hoy = new Date();
    const hace3Meses = new Date(hoy.getFullYear(), hoy.getMonth() - 3, 1);

    document.getElementById('historialDesde').value = hace3Meses.toISOString().split('T')[0];
    document.getElementById('historialHasta').value = hoy.toISOString().split('T')[0];
    document.getElementById('historialTipo').value = '';
    document.getElementById('historialEstado').value = 'todos';

    // Cargar historial automáticamente
    buscarHistorial();

    // Mostrar modal
    document.getElementById('modalHistorial').classList.add('show');
}

async function buscarHistorial() {
    const desde = document.getElementById('historialDesde').value;
    const hasta = document.getElementById('historialHasta').value;
    const tipo = document.getElementById('historialTipo').value;
    const estado = document.getElementById('historialEstado').value;

    if (!desde || !hasta) {
        Swal.fire({
            icon: 'warning',
            title: '⚠️ Advertencia',
            text: 'Seleccione rango de fechas'
        });
        return;
    }

    try {
        const params = new URLSearchParams({
            desde: desde,
            hasta: hasta,
            estado: estado
        });

        if (tipo) params.append('tipo', tipo);

        // Llamar a ambos endpoints (ingresos y salidas) de EMP
        const [rIngresos, rSalidas] = await Promise.all([
            fetch(`${baseUrl}/api/ingresos_emp.php?action=list&${params}`),
            fetch(`${baseUrl}/api/salidas_emp.php?action=list&${params}`)
        ]);

        const dIngresos = await rIngresos.json();
        const dSalidas = await rSalidas.json();

        documentosHistorial = [];

        // Agregar ingresos
        if (dIngresos.success && dIngresos.documentos) {
            documentosHistorial = documentosHistorial.concat(
                dIngresos.documentos.map(d => ({ ...d, tipo_mov: 'INGRESO' }))
            );
        }

        // Agregar salidas
        if (dSalidas.success && dSalidas.documentos) {
            documentosHistorial = documentosHistorial.concat(
                dSalidas.documentos.map(d => ({ ...d, tipo_mov: 'SALIDA' }))
            );
        }

        // Filtrar por tipo si es necesario
        if (tipo) {
            if (tipo === 'INGRESO') {
                // Filtrar solo ingresos
                documentosHistorial = documentosHistorial.filter(d => d.tipo_mov === 'INGRESO');

            } else if (tipo === 'SALIDA') {
                // Filtrar todas las salidas (cualquier subtipo)
                documentosHistorial = documentosHistorial.filter(d => d.tipo_mov === 'SALIDA');

            } else if (['PRODUCCION', 'VENTA', 'DEVOLUCION', 'MUESTRAS', 'AJUSTE'].includes(tipo)) {
                // Filtrar por subtipo específico de salida
                documentosHistorial = documentosHistorial.filter(d =>
                    d.tipo_mov === 'SALIDA' && d.tipo_salida === tipo
                );
            }
        }

        // Ordenar por fecha de creación descendente (más reciente primero)
        documentosHistorial.sort((a, b) => {
            const fechaA = new Date(a.fecha_creacion || a.fecha_documento);
            const fechaB = new Date(b.fecha_creacion || b.fecha_documento);
            return fechaB - fechaA;
        });

        renderHistorial();

    } catch (e) {
        console.error('Error:', e);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al cargar historial'
        });
    }
}

function renderHistorial() {
    const tbody = document.getElementById('historialBody');

    if (documentosHistorial.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align:center; padding:30px; color:#6c757d;">
                    <i class="fas fa-inbox" style="font-size:3rem; margin-bottom:10px; display:block;"></i>
                    No se encontraron documentos en este período
                </td>
            </tr>`;
        return;
    }

    tbody.innerHTML = documentosHistorial.map(doc => {
        const fecha = new Date(doc.fecha_documento).toLocaleDateString('es-BO');
        const tipo = doc.tipo_mov || 'INGRESO';
        const estado = doc.estado || 'CONFIRMADO';

        // Movimiento (I/S)
        let badgeMov = '';
        if (tipo === 'INGRESO') {
            badgeMov = '<span class="badge-tipo-mov ingreso"><i class="fas fa-arrow-down"></i> INGRESO</span>';
        } else {
            badgeMov = '<span class="badge-tipo-mov salida"><i class="fas fa-arrow-up"></i> SALIDA</span>';
        }

        // Subtipo descriptivo
        let nombreSubtipo = '-';
        let claseSubtipo = '';

        if (tipo === 'INGRESO') {
            const sub = doc.tipo_ingreso || '';
            const ref = (doc.referencia_externa || '').toUpperCase();

            if (sub === 'COMPRA') {
                nombreSubtipo = 'Compra Proveedor'; claseSubtipo = 'badge-sub-compra';
            }
            else if (sub === 'DEVOLUCION_PROD' || ref.includes('DEVOLUCION')) {
                nombreSubtipo = 'Devolución Prod.'; claseSubtipo = 'badge-sub-devolucion';
            }
            else if (sub === 'AJUSTE_POS' || ref.includes('AJUSTE')) {
                nombreSubtipo = 'Ajuste (+)'; claseSubtipo = 'badge-sub-ajuste';
            }
            else if (sub === 'INICIAL' || ref.includes('INICIAL')) {
                nombreSubtipo = 'Inventario Inicial'; claseSubtipo = 'badge-sub-inicial';
            }
            else {
                // Fallback inteligente: si no tiene proveedor, probablemente es interno
                if (!doc.id_proveedor) {
                    nombreSubtipo = 'Devolución Prod.';
                    claseSubtipo = 'badge-sub-devolucion';
                } else {
                    nombreSubtipo = 'Compra Proveedor';
                    claseSubtipo = 'badge-sub-compra';
                }
            }
        } else {
            const sub = doc.tipo_salida || '';
            if (sub === 'PRODUCCION' || (doc.referencia_externa && doc.referencia_externa.includes('PRODUCCION'))) {
                nombreSubtipo = 'Producción'; claseSubtipo = 'badge-sub-produccion';
            } else if (sub === 'VENTA' || (doc.referencia_externa && doc.referencia_externa.includes('VENTA'))) {
                nombreSubtipo = 'Venta'; claseSubtipo = 'badge-sub-venta';
            } else if (sub === 'DEVOLUCION' || (doc.referencia_externa && doc.referencia_externa.includes('DEVOLUCION'))) {
                nombreSubtipo = 'Dev. a Proveedor'; claseSubtipo = 'badge-sub-devolucion';
            } else if (sub === 'MUESTRAS' || (doc.referencia_externa && doc.referencia_externa.includes('MUESTRAS'))) {
                nombreSubtipo = 'Muestras'; claseSubtipo = 'badge-sub-muestras';
            } else if (sub === 'AJUSTE' || (doc.referencia_externa && doc.referencia_externa.includes('AJUSTE'))) {
                nombreSubtipo = 'Ajuste (-)'; claseSubtipo = 'badge-sub-ajuste';
            } else {
                nombreSubtipo = 'Salida Gral.';
            }
        }

        const badgeSubtipo = `<span class="badge-subtipo ${claseSubtipo}">${nombreSubtipo}</span>`;

        // Badge de estado
        let badgeEstado = '';
        if (estado === 'CONFIRMADO') {
            badgeEstado = '<span class="badge-estado confirmado">CONFIRMADO</span>';
        } else if (estado === 'ANULADO') {
            badgeEstado = '<span class="badge-estado anulado">ANULADO</span>';
        } else {
            badgeEstado = '<span class="badge-estado pendiente">PENDIENTE</span>';
        }

        return `
            <tr>
                <td style="font-size:0.85rem;">${fecha}</td>
                <td style="font-weight:600;">${doc.numero_documento}</td>
                <td>${badgeMov}</td>
                <td>${badgeSubtipo}</td>
                <td style="text-align:right; font-weight:600;">Bs. ${formatNum(parseFloat(doc.total), 2)}</td>
                <td style="text-align:center;">${badgeEstado}</td>
                <td style="font-size:0.85rem; color:#6c757d; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    ${doc.observaciones || '-'}
                </td>
                <td style="text-align:center;">
                    <button class="btn-icon ver" onclick="verDetalleDocumento(${doc.id_documento}, '${tipo}')" title="Ver Detalle">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${estado === 'CONFIRMADO' ? `
                        <button class="btn-icon anular" onclick="confirmarAnulacion(${doc.id_documento}, '${tipo}')" title="Anular">
                            <i class="fas fa-ban"></i>
                        </button>
                    ` : ''}
                </td>
            </tr>`;
    }).join('');
}

async function verDetalleDocumento(idDocumento, tipo) {
    try {
        let url = '';
        if (tipo === 'INGRESO') {
            url = `${baseUrl}/api/ingresos_emp.php?action=get&id=${idDocumento}`;
        } else {
            url = `${baseUrl}/api/salidas_emp.php?action=get&id=${idDocumento}`;
        }

        const r = await fetch(url);
        const d = await r.json();

        if (d.success) {
            mostrarDetalleDocumento(d.documento, d.detalle || d.lineas, tipo);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al cargar detalle del documento'
            });
        }
    } catch (e) {
        console.error('Error:', e);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al cargar detalle'
        });
    }
}

function mostrarDetalleDocumento(doc, detalle, tipo) {
    const tipoTexto = tipo === 'INGRESO' ? 'Ingreso' : 'Salida';
    const colorHeader = tipo === 'INGRESO' ? '#28a745' : '#dc3545';

    let html = `
        <div style="background:#f8f9fa; padding:20px; border-radius:8px; margin-bottom:20px;">
            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:15px;">
                <div>
                    <strong>Documento:</strong><br>
                    <span style="font-size:1.1rem; font-weight:700; color:${colorHeader};">${doc.numero_documento}</span>
                </div>
                <div>
                    <strong>Fecha:</strong><br>
                    ${new Date(doc.fecha_documento).toLocaleDateString('es-BO')}
                </div>
                <div>
                    <strong>Estado:</strong><br>
                    ${doc.estado === 'CONFIRMADO' ? '<span class="badge-estado confirmado">CONFIRMADO</span>' : '<span class="badge-estado anulado">ANULADO</span>'}
                </div>
                <div>
                    <strong>Total:</strong><br>
                    <span style="font-size:1.2rem; font-weight:700;">Bs. ${formatNum(parseFloat(doc.total || doc.total_documento), 2)}</span>
                </div>
            </div>
            
            ${doc.observaciones ? `
                <div style="margin-top:15px; padding-top:15px; border-top:1px solid #dee2e6;">
                    <strong>Observaciones:</strong><br>
                    ${doc.observaciones}
                </div>
            ` : ''}
        </div>
        
        <h5><i class="fas fa-list"></i> Detalle de Líneas</h5>
        <table class="tabla-detalle">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th style="text-align:center;">Cantidad</th>
                    <th style="text-align:right;">Costo Unit.</th>
                    <th style="text-align:right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                ${detalle.map(linea => `
                    <tr>
                        <td>
                            <strong>${linea.producto_codigo || linea.codigo || ''}</strong><br>
                            <span style="color:#6c757d; font-size:0.85rem;">${linea.producto_nombre || linea.nombre || ''}</span>
                        </td>
                        <td style="text-align:center;">${formatNum(parseFloat(linea.cantidad), 2)} ${linea.unidad || ''}</td>
                        <td style="text-align:right;">Bs. ${formatNum(parseFloat(linea.costo_unitario), 4)}</td>
                        <td style="text-align:right; font-weight:600;">Bs. ${formatNum(parseFloat(linea.subtotal || linea.costo_total), 2)}</td>
                    </tr>
                `).join('')}
            </tbody>
            <tfoot>
                <tr style="background:#f8f9fa; font-weight:700;">
                    <td colspan="3" style="text-align:right; padding-right:15px;">TOTAL:</td>
                    <td style="text-align:right; font-size:1.1rem; color:${colorHeader};">Bs. ${formatNum(parseFloat(doc.total || doc.total_documento), 2)}</td>
                </tr>
            </tfoot>
        </table>
    `;

    document.getElementById('detalleContenido').innerHTML = html;
    document.getElementById('detalleTitulo').innerHTML = `<i class="fas fa-file-alt"></i> Detalle de ${tipoTexto}`;

    // Mostrar botón anular solo si está confirmado
    const btnAnular = document.getElementById('btnAnularDetalle');
    if (doc.estado === 'CONFIRMADO') {
        btnAnular.style.display = 'inline-block';
        btnAnular.onclick = () => {
            cerrarModal('modalDetalle');
            confirmarAnulacion(doc.id_documento, tipo);
        };
    } else {
        btnAnular.style.display = 'none';
    }

    document.getElementById('modalDetalle').classList.add('show');
}

function confirmarAnulacion(idDocumento, tipo) {
    Swal.fire({
        title: '⚠️ Confirmar Anulación',
        text: '¿Está seguro de anular este documento?',
        input: 'text',
        inputLabel: 'Motivo de anulación',
        inputPlaceholder: 'Ingrese el motivo...',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, anular',
        cancelButtonText: 'Cancelar',
        inputValidator: (value) => {
            if (!value) {
                return 'El motivo es obligatorio';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            anularDocumento(idDocumento, tipo, result.value);
        }
    });
}

async function anularDocumento(idDocumento, tipo, motivo) {
    try {
        let url = '';
        if (tipo === 'INGRESO') {
            url = `${baseUrl}/api/ingresos_emp.php`;
        } else {
            url = `${baseUrl}/api/salidas_emp.php`;
        }

        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'anular',
                id_documento: idDocumento,
                motivo: motivo
            })
        });

        const d = await r.json();

        if (d.success) {
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: d.message
            });
            buscarHistorial(); // Recargar historial
            cargarDatos(); // Actualizar datos generales
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: d.message
            });
        }
    } catch (e) {
        console.error('Error:', e);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al anular documento'
        });
    }
}

console.log('✅ Módulo Historial EMP cargado');
