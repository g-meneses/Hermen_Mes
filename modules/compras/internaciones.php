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
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
        <div>
            <h1 class="text-4xl font-black text-slate-900 tracking-tight">Costo de Internación</h1>
            <p class="text-sm text-slate-500 mt-1 uppercase tracking-widest font-bold opacity-60">Liquidación de
                importaciones (Landed Cost)</p>
        </div>

        <div class="flex items-center gap-4 bg-white p-2 rounded-[2rem] shadow-xl border border-slate-100">
            <div class="pl-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-xl">inventory</span>
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Orden de
                    Importación</span>
            </div>
            <select id="selectorOrdenes" onchange="if(this.value) cargarOrden(this.value)"
                class="min-w-[300px] border-none bg-slate-50 rounded-2xl py-3 px-4 text-sm font-bold focus:ring-0 cursor-pointer hover:bg-slate-100 transition-all">
                <option value="">-- Seleccione una importación --</option>
            </select>
            <div class="pr-2">
                <span id="recuentoOrdenes"
                    class="bg-primary text-white text-[10px] px-3 py-1.5 rounded-full font-black shadow-lg shadow-primary/30">0</span>
            </div>
        </div>
    </div>

    <div class="w-full">
        <!-- Main Workspace Full Width -->
        <div class="w-full">
            <div id="vacioState"
                class="glass-card rounded-3xl p-12 text-center border-dashed border-2 flex flex-col items-center justify-center space-y-4">
                <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center text-slate-400">
                    <span class="material-symbols-outlined text-4xl">folder_open</span>
                </div>
                <h3 class="text-lg font-semibold text-slate-700">Seleccione una orden para liquidar</h3>
                <p class="text-sm text-slate-400 max-w-xs">Elija una importación de la lista izquierda para comenzar el
                    registro de gastos y prorrateo.</p>
            </div>

            <div id="detalleInternacion" class="hidden space-y-6 animate__animated animate__fadeIn">
                <!-- Header de Orden Seleccionada -->
                <div
                    class="glass-card rounded-[2.5rem] p-8 shadow-xl border-t-4 border-t-primary relative overflow-hidden">
                    <div class="absolute -right-20 -top-20 w-64 h-64 bg-primary/5 rounded-full blur-3xl"></div>

                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-3">
                                <span id="labelOC"
                                    class="text-xs font-mono font-black text-white bg-primary px-3 py-1 rounded-full shadow-lg shadow-primary/20 tracking-tighter">OC-CODE</span>
                                <span
                                    class="text-[10px] font-bold text-slate-400 uppercase tracking-widest bg-slate-100 px-2 py-1 rounded">Importación
                                    Activa</span>
                            </div>
                            <h2 id="labelProveedor" class="text-3xl font-black text-slate-900 tracking-tight mb-2">
                                Nombre del Proveedor</h2>
                            <div class="flex flex-wrap gap-6 text-sm">
                                <div class="flex items-center gap-2 text-slate-500">
                                    <span class="material-symbols-outlined text-sm text-primary">calendar_today</span>
                                    <span id="labelFecha" class="font-medium">---</span>
                                </div>
                                <div class="flex items-center gap-2 text-slate-500">
                                    <span
                                        class="material-symbols-outlined text-sm text-primary">currency_exchange</span>
                                    <span>Valor FOB: <b id="labelTotalFOB" class="text-slate-900">0.00</b> BOB</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <button onclick="finalizarLiquidacion()"
                                class="bg-slate-900 hover:bg-black text-white px-8 py-4 rounded-2xl font-black text-sm flex items-center gap-3 transition-all active:scale-95 shadow-2xl shadow-slate-900/20 group">
                                <span
                                    class="material-symbols-outlined group-hover:rotate-12 transition-transform">task_alt</span>
                                FINALIZAR LIQUIDACIÓN
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Quick View -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="glass-card p-4 rounded-3xl flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-amber-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-amber-600">payments</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Gastos Adic.</p>
                            <p class="text-lg font-black text-slate-800" id="statGastos">0.00</p>
                        </div>
                    </div>
                    <div class="glass-card p-4 rounded-3xl flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-blue-600">calculate</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Factor Prorrateo</p>
                            <p class="text-lg font-black text-slate-800" id="statFactor">1.0000</p>
                        </div>
                    </div>
                    <div class="glass-card p-4 rounded-3xl flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-emerald-600">show_chart</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Impacto Costo</p>
                            <p class="text-lg font-black text-slate-800" id="statImpacto">0.0%</p>
                        </div>
                    </div>
                </div>

                <!-- Gastos y Prorrateo -->
                <div class="grid grid-cols-1 md:grid-cols-7 gap-6">
                    <!-- Registro de Gastos -->
                    <div class="md:col-span-4 glass-card rounded-3xl p-6 shadow-sm overflow-hidden flex flex-col">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                                    <span
                                        class="material-symbols-outlined text-amber-500 font-variation-fill">receipt_long</span>
                                    Registro de Gastos
                                </h3>
                                <p class="text-[10px] text-slate-400 font-medium">Vincule facturas de flete, seguros y
                                    aduana</p>
                            </div>
                            <button onclick="abrirModalGasto()"
                                class="bg-primary hover:bg-primary/90 text-white text-[10px] font-black uppercase tracking-wider px-4 py-2 rounded-xl transition-all shadow-lg shadow-primary/20">
                                + Añadir Gasto
                            </button>
                        </div>
                        <div class="overflow-x-auto flex-1">
                            <table class="w-full text-sm" id="tablaGastos">
                                <thead class="text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                                    <tr class="border-b border-slate-100">
                                        <th class="py-3 text-left">Concepto</th>
                                        <th class="py-3 text-right">Monto (BOB)</th>
                                        <th class="py-3 text-center w-8"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <!-- Cargado via JS -->
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 p-4 bg-slate-50 rounded-2xl flex justify-between items-center">
                            <span class="text-xs font-bold text-slate-500 tracking-wider">TOTAL ACUMULADO</span>
                            <span id="totalGastosMonto" class="text-xl font-black text-slate-900 font-mono">0.00</span>
                        </div>
                    </div>

                    <!-- Resumen del Factor -->
                    <div
                        class="md:col-span-3 bg-slate-900 rounded-[2.5rem] p-8 shadow-2xl text-white relative overflow-hidden flex flex-col justify-between">
                        <div
                            class="absolute -right-10 -bottom-10 w-40 h-40 bg-primary/20 rounded-full blur-3xl opacity-50">
                        </div>

                        <div>
                            <div class="flex items-center gap-2 mb-8 opacity-60">
                                <span class="material-symbols-outlined text-sm">analytics</span>
                                <h3 class="font-bold text-white text-[10px] uppercase tracking-[0.2em]">Cálculo Final
                                    (Landed Cost)</h3>
                            </div>

                            <div class="space-y-6 relative z-10">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs opacity-60 uppercase font-bold tracking-widest">Valor
                                        FOB</span>
                                    <span id="factFob" class="text-lg font-mono font-bold text-white/90">0.00</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs opacity-60 uppercase font-bold tracking-widest">Sumatoria
                                        Gastos</span>
                                    <span id="factGastos" class="text-lg font-mono font-bold text-amber-400">+
                                        0.00</span>
                                </div>
                                <div class="h-px bg-white/10 my-4"></div>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs font-black text-primary uppercase tracking-widest">Total
                                        Liquidación</span>
                                    <span id="factTotal"
                                        class="text-3xl font-black font-mono tracking-tighter shadow-sm">0.00</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-12 group">
                            <div
                                class="relative p-6 bg-white/5 rounded-3xl border border-white/10 overflow-hidden hover:bg-white/10 transition-all cursor-default">
                                <div class="absolute top-0 left-0 w-1 h-full bg-primary"></div>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span
                                            class="block text-[9px] uppercase font-black text-primary tracking-[0.3em] mb-1">Factor
                                            de Prorrateo</span>
                                        <span id="factorProrrateo"
                                            class="text-5xl font-black text-white tracking-tighter">1.0000</span>
                                    </div>
                                    <div class="w-12 h-12 rounded-2xl bg-primary/20 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-primary text-3xl">hub</span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-[9px] text-white/30 italic mt-3 text-center tracking-widest uppercase">
                                Aplicado a todos los ítems de la orden</p>
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
                const select = document.getElementById('selectorOrdenes');
                const badge = document.getElementById('recuentoOrdenes');
                
                select.innerHTML = '<option value="">-- Seleccione una importación --</option>';
                
                if (!data.ordenes || data.ordenes.length === 0) {
                    badge.innerText = '0';
                    return;
                }
                
                badge.innerText = data.ordenes.length;
                data.ordenes.forEach(o => {
                    const opt = document.createElement('option');
                    opt.value = o.id_orden_compra;
                    opt.textContent = `${o.numero_orden} - ${o.nombre_proveedor} (${(o.fecha_orden || '').split(' ')[0]})`;
                    if (ordenActual && ordenActual.id_orden_compra == o.id_orden_compra) opt.selected = true;
                    select.appendChild(opt);
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

        // Actualizar Stats Top
        document.getElementById('statGastos').innerText = totalGastos.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('statFactor').innerText = factor.toFixed(4);
        const impacto = (factor - 1) * 100;
        document.getElementById('statImpacto').innerText = impacto.toFixed(1) + "%";

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