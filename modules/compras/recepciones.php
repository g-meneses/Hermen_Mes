<?php
// modules/compras/recepciones.php
require_once '../../config/database.php';

if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}
$pageTitle = "Recepciones de Compra";
include '../../includes/header.php';
?>

<!-- Tailwind y Fuentes -->
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#4f46e5",
                }
            },
        },
    };
</script>
<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
    rel="stylesheet" />

<style>
    .modal-content.xlarge {
        width: 100% !important;
        max-width: 1200px !important;
        border-radius: 1.5rem !important;
        overflow: hidden !important;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    .premium-modal-header {
        background: linear-gradient(135deg, #059669 0%, #064e3b 100%) !important;
        padding: 1.25rem 2rem !important;
        color: white !important;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .premium-card {
        @apply bg-white border border-slate-100 rounded-2xl p-4 shadow-sm;
    }

    .table-premium thead th {
        @apply bg-slate-50 text-slate-500 text-[10px] font-bold uppercase tracking-widest py-3 px-4 first:rounded-l-xl last:rounded-r-xl;
    }

    .table-premium tbody td {
        @apply py-4 px-4 align-middle border-b border-slate-100;
    }

    /* Asegurar que SweetAlert aparezca sobre los modales */
    .swal2-container {
        z-index: 9999 !important;
    }
</style>

<div class="container-fluid font-display py-4">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Recepciones de Compra</h1>
            <p class="text-sm text-slate-500">Ingreso de mercancía al almacén desde órdenes de compra</p>
        </div>
        <button
            class="flex items-center space-x-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg transition-all active:scale-95 shadow-sm"
            onclick="abrirModalRecepcion()">
            <span class="material-symbols-outlined text-lg">local_shipping</span>
            <span class="font-semibold text-sm">Nueva Recepción</span>
        </button>
    </div>

    <!-- Dashboard de Pendientes -->
    <div id="panelPendientes" class="mb-8 hidden">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xs font-bold text-slate-500 uppercase tracking-widest flex items-center gap-2">
                <span class="material-symbols-outlined text-sm text-amber-500">pending_actions</span>
                Órdenes con Entrega Pendiente / Parcial
            </h2>
        </div>
        <div id="gridPendientes" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- Cargado vía JS -->
        </div>
    </div>

    <!-- Tabla de Recepciones Realizadas -->
    <div class="flex items-center gap-2 mb-4">
        <h2 class="text-xs font-bold text-slate-500 uppercase tracking-widest flex items-center gap-2">
            <span class="material-symbols-outlined text-sm text-emerald-600">history</span>
            Historial de Recepciones
        </h2>
    </div>
    <div class="premium-card">
        <div class="overflow-x-auto">
            <table class="w-full table-premium" id="tablaRecepciones">
                <thead>
                    <tr>
                        <th class="text-left">Nº Recepción</th>
                        <th class="text-left">Fecha</th>
                        <th class="text-left">Orden Ref.</th>
                        <th class="text-left">Proveedor</th>
                        <th class="text-left">Tipo</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data loaded via JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nueva Recepción -->
<div class="modal fade" id="modalRecepcion" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 1200px;">
        <div class="modal-content xlarge">
            <div class="premium-modal-header">
                <h3 class="flex items-center gap-2 font-bold text-lg">
                    <span class="material-symbols-outlined">inventory_2</span>
                    Registrar Recepción de Compra
                </h3>
                <button type="button" class="text-white/70 hover:text-white transition-colors" data-dismiss="modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="modal-body p-6" style="max-height: 80vh; overflow-y: auto;">
                <form id="formRecepcion">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Seleccionar
                                Orden Pendiente</label>
                            <select id="id_orden_compra_select"
                                class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all font-mono"
                                onchange="if(this.value) cargarDetalleOrden(this.value)">
                                <option value="">-- Cargando órdenes --</option>
                            </select>
                            <input type="hidden" id="orden_busqueda">
                        </div>
                        <div class="space-y-1.5">
                            <label
                                class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Proveedor</label>
                            <input type="text"
                                class="w-full border-slate-200 bg-slate-100 rounded-xl py-2 px-3 text-sm font-medium text-slate-600"
                                id="nombre_proveedor" readonly>
                            <input type="hidden" id="id_proveedor">
                            <input type="hidden" id="id_orden_compra">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><span
                                    id="label_factura_remision">Factura / Remisión</span></label>
                            <input type="text"
                                class="w-full border-slate-200 bg-slate-50 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                                id="numero_factura" placeholder="Ej: F-9982">
                        </div>
                    </div>

                    <div id="panelDetalles" style="display:none;" class="space-y-4">
                        <div class="flex items-center justify-between border-t border-slate-100 pt-4">
                            <h4 class="font-bold text-slate-800 flex items-center gap-2">
                                <span class="material-symbols-outlined text-emerald-600">list_alt</span>
                                Detalles de la Orden
                            </h4>
                            <span class="text-xs text-slate-400">Ingrese las cantidades recibidas físicamente</span>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-slate-100">
                            <table class="w-full text-sm">
                                <thead
                                    class="bg-slate-50 text-slate-500 text-[10px] font-bold uppercase tracking-widest">
                                    <tr>
                                        <th class="py-3 px-4 text-left">Producto</th>
                                        <th class="py-3 px-4 text-left">Ordenado</th>
                                        <th class="py-3 px-4 text-left">Pendiente</th>
                                        <th class="py-3 px-4 text-left w-32">A Recibir</th>
                                        <th class="py-3 px-4 text-left">Lote</th>
                                        <th class="py-3 px-4 text-left">Calidad</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyDetalles" class="divide-y divide-slate-50">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-slate-50 border-t border-slate-100 p-4 flex justify-end gap-3">
                <button type="button"
                    class="px-4 py-2 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium transition-colors"
                    data-dismiss="modal">Cancelar</button>
                <button type="button"
                    class="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition-all shadow-sm active:scale-95"
                    onclick="confirmarRecepcion()">Confirmar Ingreso</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Validación Administrativa Detallada -->
<div class="modal fade" id="modalValidar" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 1100px;">
        <div class="modal-content xlarge">
            <div class="premium-modal-header !bg-emerald-700">
                <h3 class="flex items-center gap-2 font-bold text-lg">
                    <span class="material-symbols-outlined">rule</span>
                    Validación Administrativa de Recepción
                </h3>
                <button type="button" class="text-white/70 hover:text-white transition-colors" data-dismiss="modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="modal-body p-6">
                <!-- Header Info Validation -->
                <div
                    class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 bg-emerald-50 p-4 rounded-xl border border-emerald-100">
                    <div>
                        <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest">Nº Recepción</p>
                        <p class="font-bold text-emerald-800" id="val_numero"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest">Orden Relacionada
                        </p>
                        <p class="font-mono font-bold text-emerald-800" id="val_orden"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest">Proveedor</p>
                        <p class="font-bold text-emerald-800" id="val_proveedor"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest">Monto Factura</p>
                        <p class="font-bold text-emerald-800" id="val_factura"></p>
                    </div>
                </div>

                <!-- Tabla Comparativa de Validación -->
                <div class="overflow-x-auto rounded-xl border border-slate-100 mb-4">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 text-[10px] font-bold uppercase tracking-widest">
                            <tr>
                                <th class="py-3 px-4 text-left">Producto / Item</th>
                                <th class="py-3 px-4 text-center">Precio OC</th>
                                <th class="py-3 px-4 text-center">Cant. Ordenada</th>
                                <th class="py-3 px-4 text-center">Cant. Acumulada</th>
                                <th class="py-3 px-4 text-center bg-emerald-50 text-emerald-700">Esta Recepción</th>
                                <th class="py-3 px-4 text-center">Saldo</th>
                            </tr>
                        </thead>
                        <tbody id="val_body" class="divide-y divide-slate-50"></tbody>
                    </table>
                </div>

                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <p class="text-xs text-slate-500 leading-relaxed italic">
                        <i class="fas fa-info-circle mr-1"></i> Al confirmar la validación, se actualizará el stock
                        físico, se recalculará el costo promedio de los items y se registrarán los movimientos en el
                        kardex. Esta acción no se puede deshacer.
                    </p>
                </div>
            </div>
            <div class="modal-footer bg-slate-50 border-t border-slate-100 p-4 flex justify-end gap-3">
                <button type="button"
                    class="px-4 py-2 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium"
                    data-dismiss="modal">Revisar Después</button>
                <button type="button"
                    class="px-6 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition-all shadow-md active:scale-95 flex items-center gap-2"
                    id="btnConfirmarValidacion">
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                    Validar e Ingresar a Inventario
                </button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modalDetalle" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 900px;">
        <div class="modal-content xlarge">
            <div class="premium-modal-header !bg-slate-800">
                <h3 class="flex items-center gap-2 font-bold text-lg">
                    <span class="material-symbols-outlined">description</span>
                    Detalle de Recepción: <span id="det_numero"></span>
                </h3>
                <button type="button" class="text-white/70 hover:text-white transition-colors" data-dismiss="modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="modal-body p-6">
                <!-- Header Info -->
                <div
                    class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Orden de Compra</p>
                        <p class="font-mono font-bold text-slate-700" id="det_orden"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Proveedor</p>
                        <p class="font-bold text-slate-700" id="det_proveedor"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Fecha</p>
                        <p class="text-slate-600" id="det_fecha"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Factura</p>
                        <p class="text-slate-600" id="det_factura"></p>
                    </div>
                </div>

                <!-- Tabla Detalle -->
                <div class="overflow-x-auto rounded-xl border border-slate-100">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 text-[10px] font-bold uppercase tracking-widest">
                            <tr>
                                <th class="py-3 px-4 text-left">Producto</th>
                                <th class="py-3 px-4 text-center">Cant. Recibida</th>
                                <th class="py-3 px-4 text-left">Lote</th>
                                <th class="py-3 px-4 text-center">Calidad</th>
                            </tr>
                        </thead>
                        <tbody id="det_body" class="divide-y divide-slate-50"></tbody>
                    </table>
                </div>

                <div class="mt-4 p-4 bg-amber-50 rounded-xl border border-amber-100 hidden" id="det_obs_cont">
                    <p class="text-[10px] font-bold text-amber-600 uppercase tracking-widest mb-1">Observaciones</p>
                    <p class="text-xs text-amber-800 italic" id="det_observaciones"></p>
                </div>
            </div>
            <div class="modal-footer bg-slate-50 border-t border-slate-100 p-4 flex justify-end gap-3">
                <button type="button"
                    class="px-5 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-bold transition-all shadow-sm flex items-center gap-2"
                    onclick="imprimirRecepcion(currentIdRecepcion)">
                    <span class="material-symbols-outlined text-sm">print</span>
                    Imprimir Comprobante
                </button>
                <button type="button"
                    class="px-4 py-2 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium"
                    data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
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
                document.getElementById('val_factura').textContent = rec.numero_factura ? `Fact: ${rec.numero_factura}` : 'Sin Factura';

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

                fetch('../../api/compras/recepciones.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'procesar', id_recepcion: id })
                })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            Swal.fire('Éxito', res.message, 'success');
                            cargarRecepciones();
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

                    const card = document.createElement('div');
                    card.className = 'premium-card hover:border-emerald-200 transition-all cursor-pointer group hover:shadow-md';
                    card.onclick = () => recibirOC(o.id_orden_compra);

                    card.innerHTML = `
                        <div class="flex flex-col h-full">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-0.5">Orden de Compra</span>
                                    <h4 class="font-mono font-bold text-slate-800 text-sm">${o.numero_orden}</h4>
                                </div>
                                <span class="px-2 py-1 rounded-full text-[9px] font-black uppercase ${o.estado === 'RECIBIDA_PARCIAL' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700'}">
                                    ${o.estado === 'RECIBIDA_PARCIAL' ? '📦 Entrega Parcial' : '📤 Lista p/ Recibir'}
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
                                <button class="flex items-center gap-1.5 text-[10px] font-black uppercase text-emerald-600 group-hover:bg-emerald-50 px-3 py-1.5 rounded-lg transition-all">
                                    Recibir Ahora
                                    <span class="material-symbols-outlined text-xs">arrow_forward</span>
                                </button>
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
                        select.innerHTML += `<option value="${o.id_orden_compra}">
                            ${o.numero_orden} - ${o.proveedor_nombre} (${o.fecha_orden.split(' ')[0]})
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
            const pendiente = parseFloat(det.cantidad_ordenada) - parseFloat(det.cantidad_recibida || 0);
            if (pendiente <= 0) return;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="py-3 px-4 font-medium text-slate-800">${det.descripcion_producto} <br> <span class="text-[10px] text-slate-400 font-mono">${det.codigo_producto}</span></td>
                <td class="py-3 px-4 text-slate-600">${det.cantidad_ordenada} ${det.unidad_medida}</td>
                <td class="py-3 px-4 font-bold text-amber-600">${pendiente.toFixed(2)}</td>
                <td class="py-3 px-4">
                    <input type="number" class="w-full border-slate-200 bg-white rounded-lg py-1 px-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 font-bold" id="rec_${idx}" value="${pendiente}" max="${pendiente}" step="0.01">
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
                    cantidad_ordenada: det.cantidad_ordenada,
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
</script>

<?php include '../../includes/footer.php'; ?>