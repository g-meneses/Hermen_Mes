
    let detallesOrden = [];

    document.addEventListener('DOMContentLoaded', function () {
        cargarRecepciones();
        cargarPendientes();

        // Verificar si viene id_oc por URL
        const urlParams = new URLSearchParams(window.location.search);
        const idOC = urlParams.get('id_oc');
        if (idOC) {
            abrirModalRecepcion();
            document.getElementById('orden_busqueda').value = idOC;
            buscarOrden();
        }
    });

    function cargarRecepciones() {
        fetch('../../api/compras/recepciones.php?action=list')
            .then(res => res.json())
            .then(data => {
                const tbody = document.querySelector('#tablaRecepciones tbody');
                tbody.innerHTML = '';

                if (!data.recepciones || data.recepciones.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-8 text-slate-400">No hay recepciones registradas</td></tr>';
                    return;
                }

                data.recepciones.forEach(rec => {
                    const isPendiente = rec.estado === 'PENDIENTE';
                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-slate-50 transition-colors';
                    tr.innerHTML = `
                        <td class="font-semibold text-slate-800">${rec.numero_recepcion}</td>
                        <td class="text-slate-600">${rec.fecha_recepcion}</td>
                        <td class="text-slate-700 font-mono text-xs">${rec.orden_numero}</td>
                        <td class="text-slate-700">${rec.proveedor_nombre}</td>
                        <td class="text-slate-500"><span class="px-2 py-0.5 bg-slate-100 rounded text-[10px] font-bold">${rec.tipo_recepcion}</span></td>
                        <td class="text-center">
                            <span class="px-3 py-1 rounded-full text-[10px] font-bold ${isPendiente ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}">
                                ${isPendiente ? '🕒 PENDIENTE' : '✅ CONFIRMADA'}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="flex justify-center gap-1">
                                <button class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors" onclick="verRecepcion(${rec.id_recepcion})" title="Ver detalle">
                                    <span class="material-symbols-outlined text-lg">visibility</span>
                                </button>
                                ${isPendiente ? `
                                <button class="p-1.5 px-3 rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-700 transition-colors flex items-center gap-2 font-bold text-xs" onclick="procesarRecepcion(${rec.id_recepcion})" title="Validar e Ingresar a Inventario">
                                    <span class="material-symbols-outlined text-lg">task_alt</span>
                                    VALIDAR
                                </button>
                                <button class="p-1.5 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-700 transition-colors" onclick="eliminarRecepcion(${rec.id_recepcion})" title="Anular Registro Erróneo">
                                    <span class="material-symbols-outlined text-lg">delete</span>
                                </button>
                                ` : `
                                <button class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors" onclick="imprimirRecepcion(${rec.id_recepcion})" title="Imprimir comprobante">
                                    <span class="material-symbols-outlined text-lg">print</span>
                                </button>
                                `}
                            </div>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            });
    }

    function procesarRecepcion(id) {
        // Cargar datos para el modal de validación
        fetch(`../../api/compras/recepciones.php?action=get&id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) throw new Error(data.message);

                const rec = data.recepcion;
                document.getElementById('val_numero').textContent = rec.numero_recepcion;
                document.getElementById('val_orden').textContent = rec.numero_orden;
                document.getElementById('val_proveedor').textContent = rec.razon_social;
                document.getElementById('val_factura').textContent = rec.numero_factura ? `Fact: ${rec.numero_factura}` : 'Ninguno';

                if (rec.numero_factura) {
                    document.getElementById('val_condicion_fiscal').value = 'CON_FACTURA';
                } else {
                    document.getElementById('val_condicion_fiscal').value = 'SIN_FACTURA';
                }

                const tbody = document.getElementById('val_body');
                tbody.innerHTML = '';

                rec.detalles.forEach(det => {
                    const cantOrd = parseFloat(det.cantidad_ordenada);
                    const cantAcumRaw = parseFloat(det.cant_acumulada_oc || 0); // Esto ya incluye lo recibido previamente
                    const cantEsta = parseFloat(det.cantidad_recibida);
                    const precioOC = parseFloat(det.precio_oc || 0);

                    const saldo = cantOrd - (cantAcumRaw + cantEsta);
                    const sinInventario = !det.id_producto;

                    const tr = document.createElement('tr');
                    tr.className = sinInventario ? 'bg-rose-50/30' : '';
                    tr.innerHTML = `
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2">
                                <div>
                                    <p class="font-medium text-slate-800">${det.descripcion_producto}</p>
                                    <span class="text-[9px] font-mono text-slate-400">${det.codigo_producto}</span>
                                </div>
                                ${sinInventario ? `
                                    <span class="material-symbols-outlined text-rose-500 text-sm" title="Ítem no vinculado al catálogo de inventarios. No afectará stock.">warning</span>
                                ` : ''}
                            </div>
                        </td>
                        <td class="py-3 px-4 text-center font-bold text-slate-600">Bs. ${precioOC.toFixed(2)}</td>
                        <td class="py-3 px-4 text-center text-slate-500">${cantOrd} ${det.unidad_oc || ''}</td>
                        <td class="py-3 px-4 text-center text-slate-500">${cantAcumRaw}</td>
                        <td class="py-3 px-4 text-center font-bold bg-emerald-50 text-emerald-700">${cantEsta}</td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 rounded font-bold text-[10px] ${saldo <= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}">
                                ${saldo <= 0 ? 'COMPLETO' : saldo.toFixed(2) + ' PEND.'}
                            </span>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });

                // Configurar botón de confirmación
                document.getElementById('btnConfirmarValidacion').onclick = () => validarRecepcionFinal(id);

                $('#modalValidar').modal('show');
            })
            .catch(err => Swal.fire('Error', err.message, 'error'));
    }

    function validarRecepcionFinal(id) {
        Swal.fire({
            title: '¿Confirmar Ingreso?',
            text: "Esta acción actualizará el inventario físico con los datos verificados.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Sí, Confirmar Todo',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#modalValidar').modal('hide');

                Swal.fire({
                    title: 'Procesando...',
                    text: 'Actualizando stock y kardex',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                const validacionFiscal = document.getElementById('val_condicion_fiscal').value;

                fetch('../../api/compras/recepciones.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'procesar', id_recepcion: id, condicion_fiscal: validacionFiscal })
                })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            Swal.fire({
                                title: 'Éxito',
                                text: res.message,
                                icon: 'success'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    })
                    .catch(err => Swal.fire('Error', 'Error en la conexión', 'error'));
            }
        });
    }

    function eliminarRecepcion(id) {
        Swal.fire({
            title: '¿Anular Recepción?',
            text: "Esta acción eliminará el registro de almacén para que pueda ingresarse nuevamente. No afectará inventarios.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Sí, Anular Registro',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('../../api/compras/recepciones.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id_recepcion: id })
                })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            Swal.fire('Anulado', res.message, 'success');
                            cargarRecepciones();
                            cargarPendientes();
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    })
                    .catch(err => Swal.fire('Error', 'Error en la conexión', 'error'));
            }
        });
    }

    function cargarPendientes() {
        console.log("Cargando órdenes pendientes para dashboard...");
        fetch('../../api/compras/ordenes.php?action=list&estado=todos')
            .then(res => res.json())
            .then(data => {
                const grid = document.getElementById('gridPendientes');
                const panel = document.getElementById('panelPendientes');

                const pendientes = data.ordenes.filter(o =>
                    ['EMITIDA', 'ENVIADA', 'CONFIRMADA', 'RECIBIDA_PARCIAL'].includes(o.estado)
                );

                if (pendientes.length === 0) {
                    panel.classList.add('hidden');
                    return;
                }

                panel.classList.remove('hidden');
                grid.innerHTML = '';

                pendientes.forEach(o => {
                    const totalOrdenado = parseFloat(o.total_ordenado) || 1;
                    const totalRecibido = parseFloat(o.total_recibido) || 0;
                    const porcentaje = Math.min(100, Math.round((totalRecibido / totalOrdenado) * 100));
                    const esImportacionNoLiquidada = o.tipo_compra === 'IMPORTACION' && parseFloat(o.items_liquidados || 0) < parseFloat(o.total_items || 1);

                    const card = document.createElement('div');
                    card.className = `premium-card transition-all ${esImportacionNoLiquidada ? 'opacity-75 grayscale-[30%]' : 'hover:border-emerald-200 cursor-pointer group hover:shadow-md'}`;
                    if (!esImportacionNoLiquidada) {
                        card.onclick = () => recibirOC(o.id_orden_compra);
                    }

                    card.innerHTML = `
                        <div class="flex flex-col h-full">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-0.5">Orden de Compra</span>
                                    <h4 class="font-mono font-bold text-slate-800 text-sm">${o.numero_orden}</h4>
                                </div>
                                <span class="px-2 py-1 rounded-full text-[9px] font-black uppercase ${esImportacionNoLiquidada ? 'bg-slate-100 text-slate-600' : (o.estado === 'RECIBIDA_PARCIAL' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700')}">
                                    ${esImportacionNoLiquidada ? '⏳ Pend. Liquidación' : (o.estado === 'RECIBIDA_PARCIAL' ? '📦 Entrega Parcial' : '📤 Lista p/ Recibir')}
                                </span>
                            </div>
                            
                            <p class="text-xs text-slate-600 font-bold mb-3 truncate" title="${o.proveedor_nombre}">${o.proveedor_nombre}</p>
                            
                            <div class="mt-auto pt-3 border-t border-slate-50">
                                <div class="flex justify-between items-end mb-1">
                                    <span class="text-[9px] font-bold text-slate-400 uppercase">Progreso de Recepción</span>
                                    <span class="text-[10px] font-mono font-bold text-emerald-600">${porcentaje}%</span>
                                </div>
                                <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-emerald-500 rounded-full transition-all duration-1000" style="width: ${porcentaje}%"></div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between mt-4">
                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tight">${o.fecha_orden.split(' ')[0]}</span>
                                ${esImportacionNoLiquidada ?
                            `<button disabled class="flex items-center gap-1.5 text-[10px] font-black uppercase text-slate-400 px-3 py-1.5 rounded-lg bg-slate-50 cursor-not-allowed">
                                    Requiere Internación
                                    <span class="material-symbols-outlined text-xs">lock</span>
                                </button>` :
                            `<button class="flex items-center gap-1.5 text-[10px] font-black uppercase text-emerald-600 group-hover:bg-emerald-50 px-3 py-1.5 rounded-lg transition-all">
                                    Recibir Ahora
                                    <span class="material-symbols-outlined text-xs">arrow_forward</span>
                                </button>`}
                            </div>
                        </div>
                    `;
                    grid.appendChild(card);
                });
            });
    }

    function recibirOC(id) {
        abrirModalRecepcion();
        setTimeout(() => {
            const selectOC = document.getElementById('id_orden_compra_select');
            if (selectOC) {
                selectOC.value = id;
                cargarDetalleOrden(id);
            }
        }, 500);
    }

    function abrirModalRecepcion() {
        document.getElementById('formRecepcion').reset();
        document.getElementById('panelDetalles').style.display = 'none';

        // Cargar las órdenes pendientes en el select
        const select = document.getElementById('id_orden_compra_select');
        select.innerHTML = '<option value="">-- Buscando órdenes pendientes --</option>';

        fetch('../../api/compras/ordenes.php?action=list&estado=todos')
            .then(res => res.json())
            .then(data => {
                const pendientes = data.ordenes.filter(o =>
                    ['EMITIDA', 'ENVIADA', 'CONFIRMADA', 'RECIBIDA_PARCIAL'].includes(o.estado)
                );

                select.innerHTML = '<option value="">-- Seleccione una orden --</option>';
                if (pendientes.length === 0) {
                    select.innerHTML = '<option value="">No hay órdenes pendientes</option>';
                } else {
                    pendientes.forEach(o => {
                        const esImportacionNoLiquidada = o.tipo_compra === 'IMPORTACION' && parseFloat(o.items_liquidados || 0) < parseFloat(o.total_items || 1);
                        select.innerHTML += `<option value="${o.id_orden_compra}" ${esImportacionNoLiquidada ? 'disabled' : ''}>
                            ${o.numero_orden} - ${o.proveedor_nombre} (${o.fecha_orden.split(' ')[0]})${esImportacionNoLiquidada ? ' (PENDIENTE LIQUIDACIÓN)' : ''}
                        </option>`;
                    });
                }
            })
            .catch(err => {
                console.error(err);
                select.innerHTML = '<option value="">Error al cargar órdenes</option>';
            });

        $('#modalRecepcion').modal('show');
    }

    function buscarOrden() {
        const ordenNum = document.getElementById('orden_busqueda').value;
        if (!ordenNum) return;

        // Buscamos en todas las órdenes no finalizadas
        fetch('../../api/compras/ordenes.php?action=list&estado=todos')
            .then(res => res.json())
            .then(data => {
                const orden = data.ordenes.find(o => o.numero_orden === ordenNum || o.id_orden_compra == ordenNum);

                if (orden) {
                    const esImportacionNoLiquidada = orden.tipo_compra === 'IMPORTACION' && parseFloat(orden.items_liquidados || 0) < parseFloat(orden.total_items || 1);

                    if (esImportacionNoLiquidada) {
                        Swal.fire('Requiere Internación', 'Esta orden de importación aún no tiene sus gastos de internación liquidados. Vaya al módulo de Internaciones antes de recibir la mercancía.', 'warning');
                        return;
                    }

                    // Validar estado para recibir
                    if (['EMITIDA', 'ENVIADA', 'CONFIRMADA', 'RECIBIDA_PARCIAL'].includes(orden.estado)) {
                        cargarDetalleOrden(orden.id_orden_compra);
                    } else {
                        Swal.fire('Estado No Válido', `La orden está en estado ${orden.estado}. Debe estar EMITIDA, ENVIADA o CONFIRMADA para recibir mercancía.`, 'warning');
                    }
                } else {
                    Swal.fire('No encontrada', 'No existe una orden con ese número', 'warning');
                }
            });
    }

    function cargarDetalleOrden(idOrden) {
        fetch(`../../api/compras/ordenes.php?action=get&id=${idOrden}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) throw new Error(data.message);

                const orden = data.orden;
                document.getElementById('id_orden_compra').value = orden.id_orden_compra;
                document.getElementById('nombre_proveedor').value = orden.proveedor_nombre;
                document.getElementById('id_proveedor').value = orden.id_proveedor;
                document.getElementById('orden_busqueda').value = orden.numero_orden;
                document.getElementById('id_orden_compra_select').value = orden.id_orden_compra;

                // Manejo de Proveedor Directo (Sin Factura)
                const labelFact = document.getElementById('label_factura_remision');
                const inputFact = document.getElementById('numero_factura');

                if (orden.regimen_tributario === 'DIRECTO_SIN_FACTURA') {
                    labelFact.textContent = 'Nota de Entrega / Recibo';
                    inputFact.placeholder = 'Ej: Recibo Local #045';
                    inputFact.classList.add('bg-amber-50');
                    inputFact.classList.add('border-amber-200');
                } else {
                    labelFact.textContent = 'Factura / Remisión';
                    inputFact.placeholder = 'Ej: F-9982';
                    inputFact.classList.remove('bg-amber-50');
                    inputFact.classList.remove('border-amber-200');
                }

                detallesOrden = orden.detalles;
                renderDetallesRecepcion();
                document.getElementById('panelDetalles').style.display = 'block';
            })
            .catch(err => Swal.fire('Error', err.message, 'error'));
    }

    function renderDetallesRecepcion() {
        const tbody = document.getElementById('bodyDetalles');
        tbody.innerHTML = '';

        detallesOrden.forEach((det, idx) => {
            const numOC = parseFloat(det.cantidad_ordenada || 0);
            const numPacking = det.cantidad_embarcada !== null && det.cantidad_embarcada !== undefined ? parseFloat(det.cantidad_embarcada) : null;
            const meta = numPacking !== null ? numPacking : numOC;

            const pendiente = meta - parseFloat(det.cantidad_recibida || 0);
            if (pendiente <= 0) return;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="py-3 px-4 font-medium text-slate-800">${det.descripcion_producto} <br> <span class="text-[10px] text-slate-400 font-mono">${det.codigo_producto}</span></td>
                <td class="py-3 px-4 text-slate-500 text-center font-mono text-sm">${numOC.toFixed(2)} ${det.unidad_medida}</td>
                <td class="py-3 px-4 text-primary text-center font-bold font-mono text-sm">${numPacking !== null ? numPacking.toFixed(2) : '-'}</td>
                <td class="py-3 px-4 font-bold text-amber-600 text-center font-mono text-sm">${pendiente.toFixed(2)}</td>
                <td class="py-3 px-4 bg-emerald-50/50">
                    <input type="number" class="w-full border-emerald-200 bg-white rounded-lg py-1 px-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 font-bold text-emerald-700 text-center" id="rec_${idx}" value="${pendiente}" max="${pendiente}" step="0.01">
                </td>
                <td class="py-3 px-4">
                    <input type="text" class="w-full border-slate-200 bg-white rounded-lg py-1 px-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" id="lote_${idx}" placeholder="Opcional">
                </td>
                <td class="py-3 px-4">
                    <select class="w-full border-slate-200 bg-white rounded-lg py-1 px-2 text-[10px] font-bold focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" id="calidad_${idx}">
                        <option value="APROBADO" class="text-emerald-600">APROBADO</option>
                        <option value="OBSERVADO" class="text-amber-600">OBSERVADO</option>
                        <option value="RECHAZADO" class="text-red-600">RECHAZADO</option>
                    </select>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function confirmarRecepcion() {
        const itemsRecepcion = [];
        detallesOrden.forEach((det, idx) => {
            const inputRec = document.getElementById(`rec_${idx}`);
            if (!inputRec) return;

            const cantRec = parseFloat(inputRec.value);
            if (cantRec > 0) {
                itemsRecepcion.push({
                    id_detalle_oc: det.id_detalle_oc,
                    id_producto: det.id_producto,
                    id_tipo_inventario: det.id_tipo_inventario || 1,
                    codigo_producto: det.codigo_producto,
                    descripcion_producto: det.descripcion_producto,
                    cantidad_ordenada: det.cantidad_embarcada !== null && det.cantidad_embarcada !== undefined ? det.cantidad_embarcada : det.cantidad_ordenada,
                    cantidad_recibida: cantRec,
                    numero_lote: document.getElementById(`lote_${idx}`).value,
                    estado_calidad: document.getElementById(`calidad_${idx}`).value
                });
            }
        });

        if (itemsRecepcion.length === 0) {
            Swal.fire('Error', 'No hay cantidades válidas a recibir', 'warning');
            return;
        }

        const data = {
            action: 'create',
            id_orden_compra: document.getElementById('id_orden_compra').value,
            numero_orden: document.getElementById('orden_busqueda').value,
            id_proveedor: document.getElementById('id_proveedor').value,
            nombre_proveedor: document.getElementById('nombre_proveedor').value,
            numero_factura: document.getElementById('numero_factura').value,
            detalles: itemsRecepcion
        };

        // 1. Cerrar modal principal inmediatamente
        $('#modalRecepcion').modal('hide');

        // 2. Mostrar estado de carga (Aviso de proceso)
        Swal.fire({
            title: 'Procesando Ingreso',
            text: 'Actualizando inventario y kardex...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('../../api/compras/recepciones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    // 3. Aviso de Confirmación (Éxito)
                    Swal.fire({
                        title: 'Recepción Registrada',
                        text: 'La mercancía ha sido registrada como PENDIENTE. Un administrador debe validarla para que ingrese al stock.',
                        icon: 'info',
                        confirmButtonColor: '#059669'
                    });
                    cargarRecepciones();
                    cargarPendientes();
                } else {
                    // En caso de error, permitir corregir
                    Swal.fire({
                        title: 'Error',
                        text: res.message,
                        icon: 'error'
                    }).then(() => {
                        $('#modalRecepcion').modal('show');
                    });
                }
            })
            .catch(err => {
                console.error('Error en recepción:', err);
                Swal.fire('Error Inesperado', 'Verifique su conexión o contacte soporte.', 'error')
                    .then(() => {
                        $('#modalRecepcion').modal('show');
                    });
            });
    }

    let currentIdRecepcion = null;

    function verRecepcion(id) {
        currentIdRecepcion = id;
        fetch(`../../api/compras/recepciones.php?action=get&id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) throw new Error(data.message);

                const rec = data.recepcion;
                document.getElementById('det_numero').textContent = rec.numero_recepcion;
                document.getElementById('det_orden').textContent = rec.numero_orden || rec.id_orden_compra;
                document.getElementById('det_proveedor').textContent = rec.razon_social;
                document.getElementById('det_fecha').textContent = rec.fecha_recepcion;
                document.getElementById('det_factura').textContent = rec.numero_factura || 'N/A';

                if (rec.observaciones) {
                    document.getElementById('det_obs_cont').classList.remove('hidden');
                    document.getElementById('det_observaciones').textContent = rec.observaciones;
                } else {
                    document.getElementById('det_obs_cont').classList.add('hidden');
                }

                const tbody = document.getElementById('det_body');
                tbody.innerHTML = '';
                rec.detalles.forEach(det => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="py-3 px-4">
                            <p class="font-medium text-slate-800">${det.descripcion_producto}</p>
                            <p class="text-[10px] text-slate-400 font-mono">${det.codigo_producto}</p>
                        </td>
                        <td class="py-3 px-4 text-center font-bold text-slate-700">${parseFloat(det.cantidad_recibida).toLocaleString()}</td>
                        <td class="py-3 px-4 text-slate-600">${det.numero_lote || '-'}</td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-1 rounded-lg text-[10px] font-bold ${det.estado_calidad === 'APROBADO' ? 'bg-emerald-100 text-emerald-700' :
                            det.estado_calidad === 'OBSERVADO' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700'
                        }">${det.estado_calidad}</span>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });

                $('#modalDetalle').modal('show');
            })
            .catch(err => Swal.fire('Error', err.message, 'error'));
    }

    function imprimirRecepcion(id) {
        if (!id) id = currentIdRecepcion;
        window.open(`recepcion_pdf.php?id=${id}`, '_blank');
    }
