<?php
// modules/compras/internaciones.php
require_once '../../config/database.php';

if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}
$pageTitle = "Liquidación e Internaciones";
include '../../includes/header.php';
?>

<!-- Tailwind y Estilos Premium -->
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: { primary: "#4f46e5", secondary: "#059669" }
            },
        },
    };
</script>
<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
    rel="stylesheet" />

<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(226, 232, 240, 0.8);
    }

    .table-premium thead th {
        @apply bg-slate-50 text-slate-500 text-[10px] font-bold uppercase tracking-widest py-3 px-4 first:rounded-l-xl last:rounded-r-xl;
    }

    .table-premium tbody td {
        @apply py-4 px-4 align-middle border-b border-slate-100;
    }

    .input-premium {
        @apply w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all;
    }
</style>

<div class="container-fluid font-display py-6 px-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Costo de Internación</h1>
            <p class="text-sm text-slate-500 mt-1">Liquidación de gastos para órdenes de importación (Landed Cost)</p>
        </div>
        <div class="flex space-x-3">
            <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-2 flex items-center gap-3">
                <span class="material-symbols-outlined text-blue-600">info</span>
                <span class="text-xs text-blue-700 font-medium">Solo se muestran órdenes de tipo
                    <b>IMPORTACIÓN</b></span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Sidebar: Listado de Órdenes -->
        <div class="lg:col-span-4 space-y-4">
            <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest px-2">Órdenes Pendientes</h2>
            <div id="listaOrdenes" class="space-y-3 overflow-y-auto pr-2" style="max-height: 70vh;">
                <!-- Cargado via JS -->
            </div>
        </div>

        <!-- Main Workspace -->
        <div class="lg:col-span-8">
            <div id="vacioState"
                class="glass-card rounded-3xl p-12 text-center border-dashed border-2 flex flex-col items-center justify-center space-y-4">
                <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center text-slate-400">
                    <span class="material-symbols-outlined text-4xl">folder_open</span>
                </div>
                <h3 class="text-lg font-semibold text-slate-700">Seleccione una orden para liquidar</h3>
                <p class="text-sm text-slate-400 max-w-xs">Elija una importación de la lista izquierda para comenzar el
                    registro de gastos y prorrateo.</p>
            </div>

            <div id="detalleInternacion" class="hidden space-y-6">
                <!-- Header de Orden Seleccionada -->
                <div
                    class="glass-card rounded-3xl p-6 shadow-sm flex items-center justify-between border-l-4 border-l-primary">
                    <div>
                        <span id="labelOC"
                            class="text-xs font-mono font-bold text-primary bg-primary/10 px-2 py-1 rounded mb-2 inline-block">OC-CODE</span>
                        <h2 id="labelProveedor" class="text-xl font-bold text-slate-800">Nombre del Proveedor</h2>
                        <div class="flex gap-4 mt-1 text-xs text-slate-500">
                            <span class="flex items-center gap-1"><span
                                    class="material-symbols-outlined text-xs">calendar_month</span> <span
                                    id="labelFecha">---</span></span>
                            <span class="flex items-center gap-1"><span
                                    class="material-symbols-outlined text-xs">payments</span> FOB: <span
                                    id="labelTotalFOB" class="font-bold">0.00</span></span>
                        </div>
                    </div>
                    <button onclick="finalizarLiquidacion()"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-2xl font-bold flex items-center gap-2 transition-all active:scale-95 shadow-lg shadow-emerald-500/20">
                        <span class="material-symbols-outlined">verified</span> Finalizar Liquidación
                    </button>
                </div>

                <!-- Gastos y Prorrateo -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Registro de Gastos -->
                    <div class="glass-card rounded-3xl p-6 shadow-sm overflow-hidden">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="font-bold text-slate-800 flex items-center gap-2">
                                <span class="material-symbols-outlined text-amber-500">receipt_long</span> Gastos de
                                Internación
                            </h3>
                            <button onclick="abrirModalGasto()"
                                class="text-primary hover:bg-primary/5 text-xs font-bold px-3 py-1.5 rounded-lg border border-primary/20 transition-all">+
                                Añadir Gasto</button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm" id="tablaGastos">
                                <thead class="text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                                    <tr class="border-b border-slate-50">
                                        <th class="py-2 text-left">Tipo</th>
                                        <th class="py-2 text-left">Fact/Monto</th>
                                        <th class="py-2 text-center w-8"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <!-- Cargado via JS -->
                                </tbody>
                                <tfoot>
                                    <tr class="bg-slate-50/50">
                                        <td class="py-3 px-2 font-bold text-slate-600">TOTAL GASTOS</td>
                                        <td id="totalGastosMonto" class="py-3 px-2 text-right font-bold text-slate-800">
                                            0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Resumen del Factor -->
                    <div class="bg-slate-900 rounded-3xl p-6 shadow-xl text-white relative overflow-hidden">
                        <div class="absolute top-0 right-0 p-4 opacity-10">
                            <span class="material-symbols-outlined text-9xl">calculate</span>
                        </div>
                        <h3 class="font-bold text-slate-400 text-xs uppercase tracking-widest mb-6">Factor de
                            Internación</h3>
                        <div class="space-y-4 relative z-10">
                            <div class="flex justify-between items-end">
                                <span class="text-sm opacity-60">Suma FOB:</span>
                                <span id="factFob" class="text-lg font-mono">0.00</span>
                            </div>
                            <div class="flex justify-between items-end">
                                <span class="text-sm opacity-60">Suma Gastos:</span>
                                <span id="factGastos" class="text-lg font-mono text-amber-400">+ 0.00</span>
                            </div>
                            <div class="flex justify-between items-end border-t border-white/10 pt-4">
                                <span class="text-sm font-bold">TOTAL INTERNACIÓN:</span>
                                <span id="factTotal" class="text-2xl font-bold font-mono">0.00</span>
                            </div>
                            <div
                                class="mt-8 p-4 bg-white/5 rounded-2xl border border-white/10 flex items-center justify-between">
                                <div>
                                    <span class="block text-[10px] uppercase font-bold text-primary">Factor
                                        Prorrateo</span>
                                    <span id="factorProrrateo" class="text-3xl font-black text-white">0.0000</span>
                                </div>
                                <div class="text-[10px] text-white/40 italic text-right max-w-[100px]">
                                    (Total / FOB)
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Recalculo de Items -->
                <div class="glass-card rounded-3xl p-6 shadow-sm">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2 mb-6">
                        <span class="material-symbols-outlined text-blue-500">inventory_2</span> Recálculo de Costo
                        Unitario
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full table-premium" id="tablaItemsRecalculo">
                            <thead>
                                <tr>
                                    <th class="text-left w-1/3 text-lg">Producto</th>
                                    <th class="text-center">Cant.</th>
                                    <th class="text-right">Costo FOB</th>
                                    <th class="text-right">Factor</th>
                                    <th class="text-right text-primary">Costo Internado</th>
                                    <th class="text-right font-bold text-emerald-600">Diferencia %</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <!-- Cargado via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Registrar Gasto -->
<div class="modal fade" id="modalGasto" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content !rounded-[2rem] border-none shadow-2xl overflow-hidden">
            <div class="bg-slate-900 px-8 py-6 flex justify-between items-center text-white">
                <h4 class="font-bold flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-400 font-variation-fill">receipt</span>
                    Registrar Nuevo Gasto
                </h4>
                <button type="button" class="text-white/50 hover:text-white" data-dismiss="modal">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="p-8 space-y-4">
                <div class="space-y-1.5">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Tipo de
                        Gasto</label>
                    <select id="g_tipo" class="input-premium">
                        <option value="FLETE MARITIMO">🚢 Flete Marítimo</option>
                        <option value="FLETE TERRESTRE">🚚 Flete Terrestre</option>
                        <option value="SEGURO">🛡️ Seguro de Carga</option>
                        <option value="GRAVAMEN ARANCELARIO">⚖️ GA (Impuestos Aduana)</option>
                        <option value="DESPACHANTE ADUANA">📝 Honorarios Despachante</option>
                        <option value="ESTIBA Y ALMACENAJE">🏗️ Estiba / Almacenaje ASPB</option>
                        <option value="OTROS GASTOS">⚙️ Otros Gastos</option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Descripción /
                        Proveedor</label>
                    <input type="text" id="g_desc" class="input-premium" placeholder="Ej: Factura BOLT Logistics">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Monto
                            (BOB)</label>
                        <input type="number" id="g_monto" class="input-premium font-bold text-slate-800" step="0.01">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Nº
                            Factura</label>
                        <input type="text" id="g_fact" class="input-premium font-mono">
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 px-8 py-4 flex justify-end gap-3 border-t border-slate-100">
                <button type="button" class="text-slate-500 font-bold px-4 py-2" data-dismiss="modal">Cancelar</button>
                <button onclick="guardarGasto()"
                    class="bg-primary hover:bg-indigo-700 text-white font-bold px-6 py-2 rounded-xl transition-all shadow-lg shadow-indigo-500/20">Registrar
                    Gasto</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let ordenActual = null;
    let itemsActuales = [];
    let gastosActuales = [];

    document.addEventListener('DOMContentLoaded', listarOrdenes);

    function listarOrdenes() {
        console.log('Listando órdenes...');
        fetch('../../api/compras/internaciones.php?action=list_pending')
            .then(res => res.json())
            .then(data => {
                const container = document.getElementById('listaOrdenes');
                container.innerHTML = '';
                if (!data.ordenes || data.ordenes.length === 0) {
                    container.innerHTML = '<div class="p-8 text-center bg-slate-50 rounded-3xl border border-dashed text-slate-400 text-sm">No hay importaciones pendientes</div>';
                    return;
                }
                data.ordenes.forEach(o => {
                    const card = document.createElement('div');
                    const isSelected = ordenActual && ordenActual.id_orden_compra == o.id_orden_compra;
                    card.className = `glass-card p-4 rounded-2xl cursor-pointer hover:border-primary/50 transition-all border-l-4 ${isSelected ? 'border-l-primary ring-2 ring-primary/10 shadow-md' : 'border-l-transparent'}`;
                    card.onclick = () => cargarOrden(o.id_orden_compra);
                    card.innerHTML = `
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-[10px] font-mono font-bold bg-slate-100 px-1.5 py-0.5 rounded text-slate-500">${o.numero_orden}</span>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter">${(o.fecha_orden || '').split(' ')[0]}</span>
                        </div>
                        <h4 class="font-bold text-slate-800 text-sm leading-tight">${o.nombre_proveedor || 'Sin Nombre'}</h4>
                        <div class="flex justify-between items-center mt-3 pt-3 border-t border-slate-50">
                            <span class="text-[10px] text-slate-400">Total FOB</span>
                            <span class="text-xs font-bold text-slate-700">${parseFloat(o.total || 0).toLocaleString()} BOB</span>
                        </div>
                    `;
                    container.appendChild(card);
                });
            })
            .catch(err => console.error('Error listando órdenes:', err));
    }

    function cargarOrden(id) {
        console.log('Cargando orden:', id);
        fetch(`../../api/compras/internaciones.php?action=get_details&id_orden_compra=${id}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) throw new Error(data.message);

                ordenActual = data.orden;
                itemsActuales = data.items || [];
                gastosActuales = data.gastos || [];

                document.getElementById('vacioState').classList.add('hidden');
                document.getElementById('detalleInternacion').classList.remove('hidden');

                document.getElementById('labelOC').innerText = ordenActual.numero_orden;
                document.getElementById('labelProveedor').innerText = ordenActual.nombre_proveedor || 'Proveedor';
                document.getElementById('labelFecha').innerText = (ordenActual.fecha_orden || '').split(' ')[0];
                document.getElementById('labelTotalFOB').innerText = parseFloat(ordenActual.total || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });

                listarOrdenes(); // Refrescar selección visual
                renderGastos();
                recalcular();
            })
            .catch(err => {
                console.error('Error cargando detalles:', err);
                Swal.fire('Error', 'No se pudo cargar la orden: ' + err.message, 'error');
            });
    }

    function renderGastos() {
        const tbody = document.querySelector('#tablaGastos tbody');
        tbody.innerHTML = '';
        let total = 0;
        gastosActuales.forEach(g => {
            total += parseFloat(g.monto);
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="py-3 px-2">
                    <span class="block font-bold text-slate-700 text-xs">${g.tipo_gasto}</span>
                    <span class="text-[10px] text-slate-400">${g.descripcion}</span>
                </td>
                <td class="py-3 px-2 text-right">
                    <span class="block font-mono font-bold text-slate-800 text-xs">${parseFloat(g.monto).toFixed(2)}</span>
                    <span class="text-[10px] text-slate-400">Fact: ${g.numero_factura_gasto || 'S/N'}</span>
                </td>
                <td class="text-center">
                    <button onclick="eliminarGasto(${g.id_gasto})" class="text-slate-300 hover:text-red-500 transition-colors">
                        <span class="material-symbols-outlined text-sm">close</span>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
        document.getElementById('totalGastosMonto').innerText = total.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('factGastos').innerText = "+ " + total.toLocaleString(undefined, { minimumFractionDigits: 2 });
    }

    function recalcular() {
        const totalFob = parseFloat(ordenActual.total);
        const totalGastos = gastosActuales.reduce((acc, g) => acc + parseFloat(g.monto), 0);
        const totalInternacion = totalFob + totalGastos;
        const factor = totalFob > 0 ? totalInternacion / totalFob : 1;

        document.getElementById('factFob').innerText = totalFob.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('factTotal').innerText = totalInternacion.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('factorProrrateo').innerText = factor.toFixed(4);

        const tbody = document.querySelector('#tablaItemsRecalculo tbody');
        tbody.innerHTML = '';
        itemsActuales.forEach(item => {
            const costoFob = parseFloat(item.precio_unitario);
            const costoInternado = costoFob * factor;
            const diffPorc = ((costoInternado / costoFob) - 1) * 100;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <span class="block font-bold text-slate-800 text-sm">${item.descripcion_producto}</span>
                    <span class="text-[10px] font-mono text-slate-400 uppercase">${item.codigo_producto}</span>
                </td>
                <td class="text-center text-slate-600 font-medium">${item.cantidad_ordenada}</td>
                <td class="text-right font-mono text-slate-600">${costoFob.toFixed(2)}</td>
                <td class="text-right font-mono text-slate-400 italic">x ${factor.toFixed(4)}</td>
                <td class="text-right">
                    <span class="block font-mono font-bold text-primary text-md">${costoInternado.toFixed(4)}</span>
                </td>
                <td class="text-right">
                    <span class="bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded-full text-[10px] font-black">+ ${diffPorc.toFixed(1)}%</span>
                </td>
            `;
            tbody.appendChild(tr);

            // Guardar para finalizar
            item.nuevo_costo = costoInternado;
        });
    }

    function abrirModalGasto() { $('#modalGasto').modal('show'); }

    function guardarGasto() {
        const monto = document.getElementById('g_monto').value;
        if (!monto || monto <= 0) {
            Swal.fire('Error', 'Ingrese un monto válido', 'warning');
            return;
        }

        const data = {
            action: 'add_gasto',
            id_orden_compra: ordenActual.id_orden_compra,
            tipo_gasto: document.getElementById('g_tipo').value,
            descripcion: document.getElementById('g_desc').value,
            monto: monto,
            factura: document.getElementById('g_fact').value
        };

        fetch('../../api/compras/internaciones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    $('#modalGasto').modal('hide');
                    Swal.fire('Gasto Registrado', 'El gasto se ha vinculado a la orden.', 'success');
                    cargarOrden(ordenActual.id_orden_compra);

                    // Limpiar campos
                    document.getElementById('g_desc').value = '';
                    document.getElementById('g_monto').value = '';
                    document.getElementById('g_fact').value = '';
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            })
            .catch(err => {
                console.error('Error guardando gasto:', err);
                Swal.fire('Error', 'No se pudo registrar el gasto', 'error');
            });
    }

    function eliminarGasto(id) {
        Swal.fire({
            title: '¿Eliminar Gasto?',
            text: 'Esta acción revertirá el prorrateo de este gasto.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('../../api/compras/internaciones.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'remove_gasto', id_gasto: id })
                })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            cargarOrden(ordenActual.id_orden_compra);
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    });
            }
        });
    }

    function finalizarLiquidacion() {
        const factor = parseFloat(document.getElementById('factorProrrateo').innerText);
        if (isNaN(factor) || itemsActuales.length === 0) {
            Swal.fire('Error', 'No hay datos suficientes para liquidar', 'error');
            return;
        }

        Swal.fire({
            title: '¿Finalizar Liquidación?',
            text: "Se guardarán los costos internados definitivos. El inventario usará estos valores al recibir la mercadería.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            confirmButtonText: 'Sí, finalizar y guardar',
            cancelButtonText: 'Revisar'
        }).then((result) => {
            if (result.isConfirmed) {
                const payload = {
                    action: 'finalizar_liquidacion',
                    id_orden_compra: ordenActual.id_orden_compra,
                    items: itemsActuales.map(i => ({
                        id_detalle_oc: i.id_detalle_oc,
                        costo_internacion: (parseFloat(i.precio_unitario) * factor).toFixed(4)
                    }))
                };

                fetch('../../api/compras/internaciones.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            Swal.fire('Liquidación Completa', 'Los costos de internación han sido actualizados satisfactoriamente.', 'success');
                            cargarOrden(ordenActual.id_orden_compra);
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    })
                    .catch(err => {
                        console.error('Error finalizando liquidación:', err);
                        Swal.fire('Error', 'No se pudo completar la liquidación', 'error');
                    });
            }
        });
    }
</script>

<?php include '../../includes/footer.php'; ?>