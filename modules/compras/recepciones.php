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

    <!-- Tabla -->
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
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Buscar
                                Orden</label>
                            <div class="flex">
                                <input type="text"
                                    class="flex-1 border-slate-200 bg-slate-50 rounded-l-xl py-2 px-3 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all font-mono"
                                    id="orden_busqueda" placeholder="OC-202602-001">
                                <button type="button"
                                    class="bg-slate-100 border border-l-0 border-slate-200 px-3 rounded-r-xl hover:bg-slate-200 transition-colors"
                                    onclick="buscarOrden()">
                                    <span class="material-symbols-outlined text-slate-600">search</span>
                                </button>
                            </div>
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
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Factura /
                                Remisión</label>
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
                                        <th class="py-3 px-4 text-left w-40">Vencimiento</th>
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let detallesOrden = [];

    document.addEventListener('DOMContentLoaded', function () {
        cargarRecepciones();

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
                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-slate-50 transition-colors';
                    tr.innerHTML = `
                        <td class="font-semibold text-slate-800">${rec.numero_recepcion}</td>
                        <td class="text-slate-600">${rec.fecha_recepcion}</td>
                        <td class="text-slate-700 font-mono text-xs">${rec.orden_numero}</td>
                        <td class="text-slate-700">${rec.proveedor_nombre}</td>
                        <td class="text-slate-500"><span class="px-2 py-0.5 bg-slate-100 rounded text-[10px] font-bold">${rec.tipo_recepcion}</span></td>
                        <td class="text-center"><span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-[10px] font-bold">✅ ${rec.estado}</span></td>
                        <td class="text-center">
                            <button class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors" onclick="verRecepcion(${rec.id_recepcion})">
                                <span class="material-symbols-outlined text-lg">visibility</span>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            });
    }

    function abrirModalRecepcion() {
        document.getElementById('formRecepcion').reset();
        document.getElementById('panelDetalles').style.display = 'none';
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
                    if (['EMITIDA', 'ENVIADA', 'RECIBIDA_PARCIAL'].includes(orden.estado)) {
                        cargarDetalleOrden(orden.id_orden_compra);
                    } else {
                        Swal.fire('Estado No Válido', `La orden está en estado ${orden.estado}. Debe estar EMITIDA o ENVIADA para recibir mercancía.`, 'warning');
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
                document.getElementById('orden_busqueda').value = orden.numero_orden; // Asegurar número si se buscó por ID

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
                    <input type="date" class="w-full border-slate-200 bg-white rounded-lg py-1 px-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" id="venc_${idx}">
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
                    fecha_vencimiento: document.getElementById(`venc_${idx}`).value,
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
                    title: 'Ingreso Correcto',
                    text: 'La mercancía ha sido registrada en el inventario.',
                    icon: 'success',
                    confirmButtonColor: '#059669'
                });
                cargarRecepciones();
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
</script>

<?php include '../../includes/footer.php'; ?>