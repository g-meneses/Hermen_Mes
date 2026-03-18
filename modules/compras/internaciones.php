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
                                    <span>Valor FOB: <b id="labelTotalFOB" class="text-slate-900">0.00</b> <span
                                            id="labelMonedaFOB">BOB</span></span>
                                </div>
                                <div id="divTCFOB"
                                    class="flex items-center gap-2 text-slate-500 hidden focus-within:text-primary">
                                    <span class="material-symbols-outlined text-sm">payments</span>
                                    <label
                                        class="whitespace-nowrap font-bold text-[10px] uppercase tracking-widest text-primary">TC
                                        Aplicado:</label>
                                    <input type="number" id="inputTCFOB"
                                        class="w-20 border-b-2 border-slate-300 bg-transparent text-slate-900 font-bold focus:outline-none focus:border-primary px-1 text-center"
                                        value="1.00" step="0.01" onchange="recalcular()"
                                        title="Presione enter u oculta para recalcular">
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <button id="btnVolverListado" onclick="cerrarOrdenSeleccionada()"
                                class="hidden bg-white/10 hover:bg-white/20 text-white border border-white/20 px-6 py-4 rounded-2xl font-bold text-sm items-center gap-2 transition-all active:scale-95 shadow-sm">
                                <span class="material-symbols-outlined text-lg">arrow_back</span>
                                VOLVER
                            </button>
                            <div id="badgeLiquidadaFinalizada"
                                class="hidden bg-emerald-50 text-emerald-600 px-6 py-3 rounded-2xl font-black text-sm flex items-center gap-2 border border-emerald-200">
                                <span class="material-symbols-outlined">verified</span> LIQUIDACIÓN FINALIZADA
                            </div>
                            <button id="btnFinalizarLiquidacion" onclick="finalizarLiquidacion()"
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
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Factor Logístico</p>
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
                            <button id="btnAbrirModalGasto" onclick="abrirModalGasto()"
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
                                    <span class="text-xs opacity-60 uppercase font-bold tracking-widest">Valor FOB</span>
                                    <span id="factFob" class="text-lg font-mono font-bold text-white/90">0.00</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs opacity-60 uppercase font-bold tracking-widest" title="Gastos logísticos comunes (Logística, Seguros, Puerto)">Gastos Logísticos</span>
                                    <span id="factGastos" class="text-lg font-mono font-bold text-amber-400">+ 0.00</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs opacity-60 uppercase font-bold tracking-widest" title="Suma de Gravámenes Arancelarios directos">Total GA</span>
                                    <span id="factGA" class="text-lg font-mono font-bold text-orange-400">+ 0.00</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs opacity-60 uppercase font-bold tracking-widest" title="Suma de Otros gastos directos introducidos en tabla">Otros Directos</span>
                                    <span id="factOtros" class="text-lg font-mono font-bold text-orange-400">+ 0.00</span>
                                </div>
                                <div class="h-px bg-white/10 my-4"></div>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs font-black text-primary uppercase tracking-widest">Total Liquidación</span>
                                    <span id="factTotal" class="text-3xl font-black font-mono tracking-tighter shadow-sm">0.00</span>
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
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="font-bold text-slate-800 flex items-center gap-2">
                            <span class="material-symbols-outlined text-blue-500">inventory_2</span> Ajuste Packing List
                            y Recálculo de Costo
                        </h3>
                        <button onclick="guardarPackingList()" id="btnGuardarPacking"
                            class="bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase tracking-wider px-4 py-2 rounded-xl transition-all shadow-lg shadow-blue-500/20 hidden">
                            Guardar Packing List
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full table-premium" id="tablaItemsRecalculo">
                            <thead>
                                <tr>
                                    <th class="text-left w-1/4 text-[13px] font-bold">Producto</th>
                                    <th class="text-center w-16 text-[13px] font-bold">Cant. OC</th>
                                    <th class="text-center text-primary w-20 text-[13px] font-black">Cant. Packing</th>
                                    <th class="text-right text-[12px] font-bold">Precio FOB USD</th>
                                    <th class="text-right text-[12px] font-bold">Ítem FOB USD</th>
                                    <th class="text-right text-orange-600 w-20 text-[12px] font-bold">GA Real (BOB)</th>
                                    <th class="text-right text-orange-600 w-20 text-[12px] font-bold">Otros Dir. (BOB)</th>
                                    <th class="text-right text-[12px] font-bold text-slate-400">Factor Log.</th>
                                    <th class="text-right text-primary text-[12px] font-bold">Costo Internado</th>
                                    <th class="text-right font-bold text-indigo-600 text-[12px] border-l border-slate-200">Total Internado</th>
                                    <th class="text-right font-bold text-emerald-600 text-[10px]">Dif. %</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <!-- Cargado via JS -->
                            </tbody>
                            <tfoot class="bg-slate-50 border-t-2 border-slate-200" id="tfootItemsRecalculo">
                                <!-- Totales inyectados vía JS -->
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Panel de Importaciones Pendientes de Liquidación -->
            <div id="panelPendientes" class="mt-8 transition-all duration-500">
                <div class="glass-card rounded-[2rem] p-8 shadow-sm border border-amber-100">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-2xl font-black text-slate-800 flex items-center gap-2">
                                <span class="material-symbols-outlined text-amber-500">pending_actions</span>
                                Importaciones Pendientes de Liquidación
                            </h3>
                            <p class="text-xs text-slate-500 font-medium">Órdenes de Compra internacionales que aún
                                requieren nacionalización e ingreso de gastos.</p>
                        </div>
                        <button onclick="cargarPendientesLiquidacion()"
                            class="p-2 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-600 transition-colors"
                            title="Actualizar Pendientes">
                            <span class="material-symbols-outlined">refresh</span>
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full table-premium" id="tablaPendientes">
                            <thead>
                                <tr>
                                    <th class="text-left w-24">Nº Importación</th>
                                    <th class="text-left">Proveedor</th>
                                    <th class="text-center">Fecha Orden</th>
                                    <th class="text-right">Total OC</th>
                                    <th class="text-center">Estado Prorrateo</th>
                                    <th class="text-center w-24">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr>
                                    <td colspan="6" class="text-center py-6 text-slate-400 text-sm">Cargando
                                        pendientes...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Panel de Historial de Liquidaciones Pasadas -->
            <div id="panelHistorial" class="mt-8 transition-all duration-500">
                <div class="glass-card rounded-[2rem] p-8 shadow-sm">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h3 class="text-2xl font-black text-slate-800 flex items-center gap-2">
                                <span class="material-symbols-outlined text-slate-400">history</span> Historial de
                                Importaciones Liquidadas
                            </h3>
                            <p class="text-xs text-slate-500 font-medium">Registro de Órdenes de Compra con prorrateo de
                                costos aplicado.</p>
                        </div>
                        <button onclick="cargarHistorialLiquidaciones()"
                            class="p-2 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-600 transition-colors"
                            title="Actualizar Historial">
                            <span class="material-symbols-outlined">refresh</span>
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full table-premium" id="tablaHistorial">
                            <thead>
                                <tr>
                                    <th class="text-left">Nº Importación</th>
                                    <th class="text-left">Proveedor</th>
                                    <th class="text-center">Fecha Liquidación</th>
                                    <th class="text-right">Valor FOB Original</th>
                                    <th class="text-right">Gastos Acumulados</th>
                                    <th class="text-right">Costo Total Internado</th>
                                    <th class="text-center text-primary">Factor Promedio</th>
                                    <th class="text-center">Estado de Recepción</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <!-- Cargado vía JS -->
                                <tr>
                                    <td colspan="9" class="text-center py-6 text-slate-400 text-sm">Cargando
                                        historial...</td>
                                </tr>
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
                        <label
                            class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Moneda</label>
                        <select id="g_moneda" class="input-premium font-bold" onchange="toggleTCGasto()">
                            <option value="BOB">BOB</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                    <div class="space-y-1.5" id="divTCGasto" style="display:none;">
                        <label
                            class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1 text-primary">Tipo
                            de Cambio</label>
                        <input type="number" id="g_tc" class="input-premium font-bold text-primary" step="0.01"
                            value="6.96">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Monto
                            (Original)</label>
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

<!-- Modal Detalle Liquidación -->
<div class="modal fade" id="modalDetalleLiquidada" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 1200px;">
        <div class="modal-content xlarge">
            <div class="premium-modal-header !bg-slate-900 text-white">
                <h3 class="font-bold flex items-center gap-2 text-xl">
                    <span class="material-symbols-outlined text-primary">inventory</span>
                    Detalle de Liquidación: <span id="det_numero_orden" class="text-white ml-2"></span>
                </h3>
                <button type="button" class="text-white/50 hover:text-white transition-colors" data-dismiss="modal">
                    <span class="material-symbols-outlined text-2xl">close</span>
                </button>
            </div>
            <div class="modal-body modal-body-scroll p-6 bg-slate-50">
                <!-- Info Header -->
                <div
                    class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Proveedor</p>
                        <p class="font-bold text-slate-800" id="det_proveedor"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Fecha Importación
                        </p>
                        <p class="font-medium text-slate-600" id="det_fecha"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Valor FOB Total
                        </p>
                        <p class="font-mono font-bold text-slate-700 text-lg" id="det_fob"></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-primary uppercase tracking-widest mb-1">Costo Total
                            Internado</p>
                        <p class="font-mono font-black text-primary text-xl" id="det_total_internado"></p>
                    </div>
                </div>

                <!-- Secciones (Gastos e Items) -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Gastos -->
                    <div class="lg:col-span-1 glass-card rounded-3xl p-6 shadow-sm h-fit">
                        <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                            <span
                                class="material-symbols-outlined text-amber-500 font-variation-fill">receipt_long</span>
                            Gastos Adicionales
                        </h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="text-left text-slate-400 border-b border-slate-100">
                                        <th class="py-2 font-bold uppercase tracking-widest text-[9px]">Concepto</th>
                                        <th class="py-2 text-right font-bold uppercase tracking-widest text-[9px]">Monto
                                            (BOB)</th>
                                    </tr>
                                </thead>
                                <tbody id="det_gastos_body" class="divide-y divide-slate-50"></tbody>
                            </table>
                        </div>
                        <div class="mt-4 pt-4 border-t border-slate-200 flex justify-between items-center">
                            <span class="font-black text-slate-500 text-[10px] uppercase tracking-wider">Total
                                Gastos:</span>
                            <span class="font-mono font-black text-slate-800 text-base" id="det_gastos_total"></span>
                        </div>
                    </div>

                    <!-- Items -->
                    <div class="lg:col-span-2 glass-card rounded-3xl p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="font-bold text-slate-800 flex items-center gap-2">
                                <span
                                    class="material-symbols-outlined text-blue-500 font-variation-fill">inventory_2</span>
                                Costo Unitario Prorrateado
                            </h4>
                            <div
                                class="bg-blue-50 px-4 py-2 rounded-xl border border-blue-100 text-right flex items-center gap-3">
                                <span
                                    class="text-[10px] font-bold text-blue-500 uppercase tracking-widest">Factor</span>
                                <span class="font-mono font-black text-blue-700 text-lg" id="det_factor"></span>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead
                                    class="bg-slate-50 text-slate-500 text-[10px] font-bold uppercase tracking-widest">
                                    <tr>
                                        <th class="py-3 px-4 text-left rounded-l-xl">Producto</th>
                                        <th class="py-3 px-4 text-center">Cant.</th>
                                        <th class="py-3 px-4 text-right">FOB Unit.</th>
                                        <th
                                            class="py-3 px-4 text-right text-primary font-black border-l border-slate-200 rounded-r-xl">
                                            Costo Internado</th>
                                    </tr>
                                </thead>
                                <tbody id="det_items_body" class="divide-y divide-slate-50"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-t border-slate-100 p-6 flex justify-end gap-3">
                <button type="button" onclick="imprimirLiquidacion()"
                    class="px-6 py-3 rounded-xl bg-slate-900 text-white font-bold hover:bg-black transition-colors shadow-lg shadow-slate-900/20 active:scale-95 flex items-center gap-2">
                    <span class="material-symbols-outlined text-lg">print</span>
                    Imprimir Liquidación
                </button>
                <button type="button"
                    class="px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition-colors active:scale-95 flex items-center gap-2"
                    data-dismiss="modal">
                    <span class="material-symbols-outlined text-lg">close</span>
                    Cerrar Vista Detalle
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let ordenActual = null;
    let itemsActuales = [];
    let gastosActuales = [];
    let currentLiquidacionId = null;

    document.addEventListener('DOMContentLoaded', () => {
        listarOrdenes();
        cargarPendientesLiquidacion();
        cargarHistorialLiquidaciones();
    });

    function cargarPendientesLiquidacion() {
        fetch('../../api/compras/internaciones.php?action=list_pending')
            .then(res => res.json())
            .then(data => {
                const tbody = document.querySelector('#tablaPendientes tbody');
                const ordenesPendientes = data.ordenes.filter(o => parseFloat(o.items_liquidados || 0) < parseFloat(o.total_items || 1));

                if (ordenesPendientes.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-slate-400 font-medium text-sm">No hay importaciones pendientes de liquidación.</td></tr>';
                    return;
                }

                ordenesPendientes.forEach(o => {
                    const estadoHtml = '<span class="px-3 py-1 rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold border border-amber-200">⏳ PENDIENTE</span>';

                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-slate-50 transition-colors cursor-pointer group';

                    // Al hacer clic en la fila completa, simular que se carga la orden (igual que en el selector)
                    tr.onclick = () => {
                        const select = document.getElementById('selectorOrdenes');
                        select.value = o.id_orden_compra;
                        cargarOrden(o.id_orden_compra);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    };

                    tr.innerHTML = `
                        <td>
                            <span class="block font-black text-slate-800 text-xs">${o.numero_orden}</span>
                        </td>
                        <td>
                            <span class="block text-slate-600 font-medium text-xs max-w-[200px] truncate"
                                title="${o.nombre_proveedor}">${o.nombre_proveedor}</span>
                        </td>
                        <td class="text-center">
                            <span class="text-xs text-slate-500">${(o.fecha_orden || '').split(' ')[0]}</span>
                        </td>
                        <td class="text-right">
                            <span class="font-mono text-slate-700 text-xs font-bold">${parseFloat(o.total).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                        </td>
                        <td class="text-center">
                            ${estadoHtml}
                        </td>
                        <td class="text-center relative z-10">
                            <button class="bg-primary hover:bg-blue-700 text-white font-bold px-4 py-1.5 rounded-lg text-xs transition-colors whitespace-nowrap"
                                onclick="event.stopPropagation(); document.getElementById('selectorOrdenes').value = ${o.id_orden_compra}; cargarOrden(${o.id_orden_compra}); window.scrollTo({ top: 0, behavior: 'smooth' });">
                                Liquidar
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            })
            .catch(err => {
                console.error('Error cargando pendientes:', err);
                const tbody = document.querySelector('#tablaPendientes tbody');
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-6 text-red-400 text-sm">Error cargando datos.</td></tr>';
            });
    }

    function cargarHistorialLiquidaciones() {
        fetch('../../api/compras/internaciones.php?action=list_history')
            .then(res => res.json())
            .then(data => {
                const tbody = document.querySelector('#tablaHistorial tbody');
                tbody.innerHTML = '';

                if (!data.historial || data.historial.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-8 text-slate-400 font-medium text-sm">No hay importaciones liquidadas registradas aún.</td></tr>';
                    return;
                }
                data.historial.forEach(h => {
                    // Estados de Recepción Simplificados
                    let estadoRec = '';
                    if (h.estado === 'RECIBIDA_PARCIAL') estadoRec = '<span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold">📦 PARCIAL</span>';
                    else if (h.estado === 'RECIBIDA') estadoRec = '<span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold">✅ COMPLETA</span>';
                    else estadoRec = '<span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-bold">🕒 PEND. RECEPCIÓN</span>';

                    const factorProm = (parseFloat(h.total_internado) / parseFloat(h.fob_total_bob)).toFixed(4);

                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-slate-50 transition-colors cursor-pointer group';
                    tr.onclick = () => verDetalleLiquidada(h.id_orden_compra); // Para futura función de vista detallada

                    tr.innerHTML = `
<td>
    <span class="block font-black text-slate-800 text-xs">${h.numero_orden}</span>
</td>
<td>
    <span class="block text-slate-600 font-medium text-xs max-w-[150px] truncate"
        title="${h.nombre_proveedor}">${h.nombre_proveedor}</span>
</td>
<td class="text-center">
    <span class="text-xs text-slate-500">${h.fecha_ultima_liq || h.fecha_orden.split(' ')[0]}</span>
</td>
<td class="text-right">
    <span class="font-mono text-slate-700 text-xs font-bold">${parseFloat(h.fob_total_bob).toLocaleString(undefined, {
                        minimumFractionDigits: 2
                    })} <span class="text-[9px] text-slate-400">BOB</span></span>
</td>
<td class="text-right">
    <span class="font-mono text-amber-600 text-xs font-bold">+ ${parseFloat(h.gastos_totales).toLocaleString(undefined,
                        { minimumFractionDigits: 2 })} <span class="text-[9px] text-amber-500/50">BOB</span></span>
</td>
<td class="text-right">
    <span class="font-mono text-slate-900 text-sm font-black">${parseFloat(h.total_internado).toLocaleString(undefined,
                            { minimumFractionDigits: 2 })} <span class="text-[9px] text-slate-400">BOB</span></span>
</td>
<td class="text-center">
    <span
        class="font-mono text-primary group-hover:bg-primary group-hover:text-white px-2 py-0.5 rounded transition-colors text-xs font-bold">x
        ${factorProm}</span>
</td>
<td class="text-center">
    ${estadoRec}
</td>
<td class="text-center">
    <div class="flex justify-center gap-1">
        <button class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors" onclick="event.stopPropagation(); window.open('../../modules/compras/liquidacion_pdf.php?id=${h.id_orden_compra}', '_blank')" title="Imprimir Liquidación">
            <span class="material-symbols-outlined text-lg">print</span>
        </button>
    </div>
</td>
`;
                    tbody.appendChild(tr);
                });
            })
            .catch(err => console.error('Error cargando historial:', err));
    }

    function verDetalleLiquidada(idOC) {
        currentLiquidacionId = idOC;
        Swal.fire({
            title: 'Cargando detalle...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        fetch(`../../api/compras/internaciones.php?action=get_details&id_orden_compra=${idOC}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) throw new Error(data.message);

                Swal.close();

                const orden = data.orden;
                const gastos = data.gastos || [];
                const items = data.items || [];

                document.getElementById('det_numero_orden').innerText = orden.numero_orden;
                document.getElementById('det_proveedor').innerText = orden.nombre_proveedor || 'N/A';
                document.getElementById('det_fecha').innerText = (orden.fecha_orden || '').split(' ')[0];

                const tcInternacion = orden.moneda === 'USD' ? (parseFloat(orden.tipo_cambio) || 6.96) : 1;

                // Calcular FOB Total
                let fobTotalBob = 0;
                items.forEach(item => {
                    const cant = parseFloat(item.cantidad_embarcada) || parseFloat(item.cantidad_ordenada);
                    fobTotalBob += (cant * parseFloat(item.precio_unitario)) * tcInternacion;
                });

                document.getElementById('det_fob').innerText = fobTotalBob.toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' BOB';

                // Mostrar gastos
                const tbodyGastos = document.getElementById('det_gastos_body');
                tbodyGastos.innerHTML = '';
                let totalGastos = 0;
                gastos.forEach(g => {
                    const montoBob = (g.monto_bob && parseFloat(g.monto_bob) > 0) ? parseFloat(g.monto_bob) : parseFloat(g.monto);
                    totalGastos += montoBob;
                    tbodyGastos.innerHTML += `
                        <tr>
                            <td class="py-3 pr-2">
                                <span class="block font-bold text-slate-700 text-[11px]">${g.tipo_gasto}</span>
                                <span class="text-[9px] text-slate-400 block truncate max-w-[150px]" title="${g.descripcion}">${g.descripcion}</span>
                            </td>
                            <td class="py-3 text-right font-mono font-bold text-slate-800 text-xs">${montoBob.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                        </tr>
                    `;
                });
                document.getElementById('det_gastos_total').innerText = totalGastos.toLocaleString('en-US', { minimumFractionDigits: 2 });

                const totalInternado = fobTotalBob + totalGastos;
                document.getElementById('det_total_internado').innerText = totalInternado.toLocaleString('en-US', { minimumFractionDigits: 2 }) + ' BOB';

                const factor = fobTotalBob > 0 ? (totalInternado / fobTotalBob) : 1;
                document.getElementById('det_factor').innerText = 'x ' + factor.toFixed(4);

                // Mostrar items
                const tbodyItems = document.getElementById('det_items_body');
                tbodyItems.innerHTML = '';
                items.forEach(item => {
                    const cant = parseFloat(item.cantidad_embarcada) || parseFloat(item.cantidad_ordenada);
                    const fobUnitBob = parseFloat(item.precio_unitario) * tcInternacion;
                    const costoInternado = parseFloat(item.precio_unitario_internacion) || (fobUnitBob * factor);

                    tbodyItems.innerHTML += `
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4">
                                <span class="block font-bold text-slate-700 text-xs">${item.descripcion_producto}</span>
                                <span class="text-[9px] font-mono text-slate-400">${item.codigo_producto}</span>
                            </td>
                            <td class="py-3 px-4 text-center text-slate-600 font-medium">${cant.toFixed(2)}</td>
                            <td class="py-3 px-4 text-right font-mono text-slate-500">${fobUnitBob.toFixed(2)}</td>
                            <td class="py-3 px-4 text-right font-mono font-black text-primary border-l border-slate-100 text-[15px] bg-slate-50/50">${costoInternado.toFixed(4)}</td>
                        </tr>
                    `;
                });

                $('#modalDetalleLiquidada').modal('show');
            })
            .catch(err => {
                Swal.close();
                console.error('Error:', err);
                Swal.fire('Error', 'No se pudo cargar el detalle: ' + err.message, 'error');
            });
    }

    function imprimirLiquidacion() {
        if (currentLiquidacionId) {
            window.open(`../../modules/compras/liquidacion_pdf.php?id=${currentLiquidacionId}`, '_blank');
        } else {
            Swal.fire('Error', 'No hay ninguna liquidación seleccionada.', 'error');
        }
    }

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
                    const esLiquidada = parseFloat(o.items_liquidados || 0) >= parseFloat(o.total_items || 1);
                    const tagEst = esLiquidada ? '✓ LIQUIDADA' : '⏳ PENDIENTE DE LIQUIDACIÓN';
                    opt.textContent = `${o.numero_orden} - ${o.nombre_proveedor} [${tagEst}]`;
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

                const estaLiquidada = itemsActuales.some(i => parseFloat(i.precio_unitario_internacion) > 0);
                window.ordenActualLiquidada = estaLiquidada;

                if (estaLiquidada) {
                    document.getElementById('btnFinalizarLiquidacion').classList.add('hidden');
                    document.getElementById('badgeLiquidadaFinalizada').classList.remove('hidden');
                    document.getElementById('btnAbrirModalGasto').classList.add('hidden');
                    if (document.getElementById('inputTCFOB')) document.getElementById('inputTCFOB').disabled = true;
                } else {
                    document.getElementById('btnFinalizarLiquidacion').classList.remove('hidden');
                    document.getElementById('badgeLiquidadaFinalizada').classList.add('hidden');
                    document.getElementById('btnAbrirModalGasto').classList.remove('hidden');
                    if (document.getElementById('inputTCFOB')) document.getElementById('inputTCFOB').disabled = false;
                }

                document.getElementById('panelPendientes').classList.add('hidden');
                document.getElementById('panelHistorial').classList.add('hidden');
                document.getElementById('btnVolverListado').classList.remove('hidden');
                document.getElementById('btnVolverListado').classList.add('flex');
                document.getElementById('detalleInternacion').classList.remove('hidden');

                document.getElementById('labelOC').innerText = ordenActual.numero_orden;
                document.getElementById('labelProveedor').innerText = ordenActual.nombre_proveedor || 'Proveedor';
                document.getElementById('labelFecha').innerText = (ordenActual.fecha_orden || '').split(' ')[0];
                document.getElementById('labelTotalFOB').innerText = parseFloat(ordenActual.total || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2
                });
                document.getElementById('labelMonedaFOB').innerText = ordenActual.moneda || 'BOB';

                if (ordenActual.moneda === 'USD') {
                    document.getElementById('divTCFOB').classList.remove('hidden');
                    document.getElementById('inputTCFOB').value = ordenActual.tipo_cambio || 6.96;
                } else {
                    document.getElementById('divTCFOB').classList.add('hidden');
                    document.getElementById('inputTCFOB').value = 1.0;
                }

                listarOrdenes(); // Refrescar selección visual
                renderGastos();
                recalcular();
            })
            .catch(err => {
                console.error('Error cargando detalles:', err);
                Swal.fire('Error', 'No se pudo cargar la orden: ' + err.message, 'error');
            });
    }

    function cerrarOrdenSeleccionada() {
        ordenActual = null;
        itemsActuales = [];
        gastosActuales = [];

        document.getElementById('detalleInternacion').classList.add('hidden');
        document.getElementById('btnVolverListado').classList.add('hidden');
        document.getElementById('btnVolverListado').classList.remove('flex');

        document.getElementById('panelPendientes').classList.remove('hidden');
        document.getElementById('panelHistorial').classList.remove('hidden');

        document.getElementById('selectorOrdenes').value = "";

        listarOrdenes();
        cargarPendientesLiquidacion();
        cargarHistorialLiquidaciones();

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function renderGastos() {
        const tbody = document.querySelector('#tablaGastos tbody');
        tbody.innerHTML = '';
        let total = 0;
        gastosActuales.forEach(g => {
            const montoBob = (g.monto_bob && parseFloat(g.monto_bob) > 0) ? parseFloat(g.monto_bob) : parseFloat(g.monto);
            total += montoBob;
            const tr = document.createElement('tr');
            tr.innerHTML = `
<td class="py-3 px-2">
    <span class="block font-bold text-slate-700 text-xs">${g.tipo_gasto}</span>
    <span class="text-[10px] text-slate-400">${g.descripcion}</span>
</td>
<td class="py-3 px-2 text-right">
    <span class="block font-mono font-bold text-slate-800 text-xs">${montoBob.toFixed(2)}</span>
    <span class="text-[10px] text-slate-400">${g.moneda === 'USD' ? `(${parseFloat(g.monto).toFixed(2)} USD x
        ${parseFloat(g.tipo_cambio || 1).toFixed(2)}) ` : ''}Fact: ${g.numero_factura_gasto || 'S/N'}</span>
</td>
<td class="text-center">
    ${window.ordenActualLiquidada ? '' : `
    <button onclick="eliminarGasto(${g.id_gasto})" class="text-slate-300 hover:text-red-500 transition-colors">
        <span class="material-symbols-outlined text-sm">close</span>
    </button>
    `}
</td>
`;
            tbody.appendChild(tr);
        });
        document.getElementById('totalGastosMonto').innerText = total.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('factGastos').innerText = "+ " + total.toLocaleString(undefined, { minimumFractionDigits: 2 });
    }

    function recalcular() {
        let fFobOriginal = 0;
        let sumGaItems = 0;
        let sumOtrosItems = 0;

        itemsActuales.forEach(item => {
            const cant = parseFloat(item.cantidad_embarcada) || parseFloat(item.cantidad_ordenada);
            fFobOriginal += cant * parseFloat(item.precio_unitario);
            sumGaItems += parseFloat(item.ga_real) || 0;
            sumOtrosItems += parseFloat(item.otros_directos) || 0;
        });

        const tcInternacion = parseFloat(document.getElementById('inputTCFOB').value) || 1;
        const totalFobBOB = fFobOriginal * tcInternacion;

        const totalGastosLogisticos = gastosActuales.reduce((acc, g) => acc + ((g.monto_bob && parseFloat(g.monto_bob) > 0) ?
            parseFloat(g.monto_bob) : parseFloat(g.monto)), 0);
            
        const totalInternacion = totalFobBOB + totalGastosLogisticos + sumGaItems + sumOtrosItems;
        const factorLogistico = totalFobBOB > 0 ? (totalFobBOB + totalGastosLogisticos) / totalFobBOB : 1;
        const factorGlobal = totalFobBOB > 0 ? totalInternacion / totalFobBOB : factorLogistico;

        document.getElementById('factFob').innerText = totalFobBOB.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('factGastos').innerText = "+ " + totalGastosLogisticos.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('factGA').innerText = "+ " + sumGaItems.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('factOtros').innerText = "+ " + sumOtrosItems.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('factTotal').innerText = totalInternacion.toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('factorProrrateo').innerText = factorLogistico.toFixed(4);

        // Actualizar Stats Top
        document.getElementById('statGastos').innerText = (totalGastosLogisticos + sumGaItems + sumOtrosItems).toLocaleString(undefined, { minimumFractionDigits: 2 });
        document.getElementById('statFactor').innerText = factorLogistico.toFixed(4);
        const impacto = (factorGlobal - 1) * 100;
        document.getElementById('statImpacto').innerText = impacto.toFixed(1) + "%";

        const tbody = document.querySelector('#tablaItemsRecalculo tbody');
        tbody.innerHTML = '';
        
        let sumCantOC = 0;
        let sumCantPacking = 0;
        let sumFobItemUsd = 0;
        let sumTotalInternadoBob = 0;
        
        itemsActuales.forEach(item => {
            const cantidadActiva = parseFloat(item.cantidad_embarcada) || parseFloat(item.cantidad_ordenada);
            const cantOC = parseFloat(item.cantidad_ordenada);
            const costoFobOriginal = parseFloat(item.precio_unitario);
            const costoFobBOB = costoFobOriginal * tcInternacion;
            
            const itemGA = parseFloat(item.ga_real) || 0;
            const itemOtros = parseFloat(item.otros_directos) || 0;

            const totalFobItemBob = costoFobBOB * cantidadActiva;
            const totalInternadoItem = (totalFobItemBob * factorLogistico) + itemGA + itemOtros;
            const costoInternado = cantidadActiva > 0 ? totalInternadoItem / cantidadActiva : 0;
            const diffPorc = costoFobBOB > 0 ? ((costoInternado / costoFobBOB) - 1) * 100 : 0;
            
            const totalFobItemUsd = costoFobOriginal * cantidadActiva;

            sumCantOC += cantOC;
            sumCantPacking += cantidadActiva;
            sumFobItemUsd += totalFobItemUsd;
            sumTotalInternadoBob += totalInternadoItem;

            const tr = document.createElement('tr');
            tr.innerHTML = `
<td>
    <span class="block font-bold text-slate-800 text-[11px] leading-tight max-w-[150px] truncate" title="${item.descripcion_producto}">${item.descripcion_producto}</span>
    <span class="text-[9px] font-mono text-slate-400 uppercase">${item.codigo_producto}</span>
</td>
<td class="text-center text-slate-500 font-medium align-middle">
    <span class="block text-sm">${cantOC.toFixed(2)}</span>
</td>
<td class="text-center align-middle">
    ${window.ordenActualLiquidada ? `
    <span class="block font-bold text-primary text-md">${cantidadActiva.toFixed(2)}</span>
    ` : `
    <input type="number"
        class="w-16 text-center bg-blue-50/50 border border-blue-100 rounded-lg py-1 font-bold text-primary focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all text-xs"
        value="${cantidadActiva.toFixed(2)}" step="0.01"
        onchange="cambiarDatoItem(${item.id_detalle_oc}, 'cantidad_embarcada', this.value)">
    `}
</td>
<td class="text-right align-middle">
    <span class="block font-mono text-slate-600 text-xs">${costoFobOriginal.toFixed(2)}</span>
</td>
<td class="text-right align-middle">
    <span class="block font-mono font-bold text-slate-800 text-[12px]">${totalFobItemUsd.toFixed(2)}</span>
</td>
<td class="text-center align-middle">
    ${window.ordenActualLiquidada ? `
    <span class="block font-mono text-orange-600 text-xs">${itemGA.toFixed(2)}</span>
    ` : `
    <input type="number"
        class="w-16 text-right bg-orange-50/50 border border-orange-100 rounded-md py-1 font-mono text-orange-700 focus:outline-none focus:ring-1 focus:ring-orange-500 transition-all text-[11px]"
        value="${itemGA.toFixed(2)}" step="0.01"
        onchange="cambiarDatoItem(${item.id_detalle_oc}, 'ga_real', this.value)">
    `}
</td>
<td class="text-center align-middle">
    ${window.ordenActualLiquidada ? `
    <span class="block font-mono text-orange-600 text-xs">${itemOtros.toFixed(2)}</span>
    ` : `
    <input type="number"
        class="w-16 text-right bg-orange-50/50 border border-orange-100 rounded-md py-1 font-mono text-orange-700 focus:outline-none focus:ring-1 focus:ring-orange-500 transition-all text-[11px]"
        value="${itemOtros.toFixed(2)}" step="0.01"
        onchange="cambiarDatoItem(${item.id_detalle_oc}, 'otros_directos', this.value)">
    `}
</td>
<td class="text-right font-mono text-slate-400 italic text-[10px] align-middle">x ${factorLogistico.toFixed(4)}</td>
<td class="text-right align-middle bg-slate-50/30">
    <span class="block font-mono font-bold text-primary text-[11px]">${costoInternado.toFixed(4)} <span class="text-[8px] text-slate-400 font-normal">BOB</span></span>
</td>
<td class="text-right align-middle border-l border-slate-100 bg-slate-50/80">
    <span class="block font-mono font-black text-indigo-700 text-[12px]">${totalInternadoItem.toFixed(2)} <span class="text-[8px] text-indigo-400/50 font-normal">BOB</span></span>
</td>
<td class="text-right align-middle">
    <span class="bg-emerald-50 text-emerald-600 px-1 py-0.5 rounded-sm text-[8px] font-black border border-emerald-100">+${diffPorc.toFixed(1)}%</span>
</td>
`;
            tbody.appendChild(tr);

            item.nuevo_costo = costoInternado;
            item.ga_real = itemGA;
            item.otros_directos = itemOtros;
        });

        const tfoot = document.getElementById('tfootItemsRecalculo');
        if (tfoot) {
            tfoot.innerHTML = `
                <tr>
                    <td class="text-right font-black text-slate-700 text-[10px] uppercase tracking-widest py-3 pr-2">TOT:</td>
                    <td class="text-center font-bold text-slate-700 text-xs align-middle">${sumCantOC.toFixed(2)}</td>
                    <td class="text-center font-black text-primary text-xs align-middle">${sumCantPacking.toFixed(2)}</td>
                    <td></td>
                    <td class="text-right font-black text-slate-800 text-xs align-middle">${sumFobItemUsd.toFixed(2)} <span class="text-[8px] text-slate-500 font-normal">USD</span></td>
                    <td class="text-center font-black text-orange-600 text-xs align-middle">${sumGaItems.toFixed(2)}</td>
                    <td class="text-center font-black text-orange-600 text-xs align-middle">${sumOtrosItems.toFixed(2)}</td>
                    <td></td>
                    <td></td>
                    <td class="text-right font-black text-indigo-700 text-[13px] align-middle border-l border-slate-200">${sumTotalInternadoBob.toLocaleString(undefined, {minimumFractionDigits: 2})} <span class="text-[8px] text-indigo-500 font-normal">BOB</span></td>
                    <td></td>
                </tr>
            `;
        }
    }

    function cambiarDatoItem(idDetalle, campo, valor) {
        const item = itemsActuales.find(i => i.id_detalle_oc == idDetalle);
        if (item) {
            item[campo] = parseFloat(valor) || 0;
            document.getElementById('btnGuardarPacking').classList.remove('hidden');
            recalcular();
        }
    }

    function guardarPackingList() {
        Swal.fire({
            title: '¿Confirmar Packing List?',
            text: "Al guardar, este será el nuevo total de referencia para el cálculo de costos y la recepción en almacén. Verifique que las cantidades coinciden con el Documento de Embarque definitivo.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Sí, guardar cantidades',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const btn = document.getElementById('btnGuardarPacking');
                btn.innerHTML = '<span class="material-symbols-outlined animate-spin text-sm">sync</span> Guardando...';
                btn.disabled = true;

                const payload = {
                    action: 'guardar_packing',
                    items: itemsActuales.map(i => ({
                        id_detalle_oc: i.id_detalle_oc,
                        cantidad_embarcada: parseFloat(i.cantidad_embarcada) || parseFloat(i.cantidad_ordenada),
                        ga_real: parseFloat(i.ga_real) || 0,
                        otros_directos: parseFloat(i.otros_directos) || 0
                    }))
                };

                fetch('../../api/compras/internaciones.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'Cantidades actualizadas sobre el Packing List.',
                                showConfirmButton: false,
                                timer: 3000
                            });
                            btn.classList.add('hidden');
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .finally(() => {
                        btn.innerHTML = 'Guardar Packing List';
                        btn.disabled = false;
                    });
            }
        });
    }

    function toggleTCGasto() {
        const moneda = document.getElementById('g_moneda').value;
        document.getElementById('divTCGasto').style.display = moneda === 'USD' ? 'block' : 'none';

        // Si cambia a USD y no hay TC, sugerir el de la orden
        if (moneda === 'USD' && !document.getElementById('g_tc').value) {
            document.getElementById('g_tc').value = document.getElementById('inputTCFOB').value || 6.96;
        }
    }

    function abrirModalGasto() {
        document.getElementById('g_moneda').value = 'BOB';
        document.getElementById('g_monto').value = '';
        document.getElementById('g_tc').value = document.getElementById('inputTCFOB').value || 6.96;
        toggleTCGasto();
        $('#modalGasto').modal('show');
    }

    function guardarGasto() {
        const monto = document.getElementById('g_monto').value;
        if (!monto || monto <= 0) { Swal.fire('Error', 'Ingrese un monto válido', 'warning'); return; } const
            moneda = document.getElementById('g_moneda').value; const tc = parseFloat(document.getElementById('g_tc').value) || 1;
        const data = {
            action: 'add_gasto', id_orden_compra: ordenActual.id_orden_compra, tipo_gasto:
                document.getElementById('g_tipo').value, descripcion: document.getElementById('g_desc').value, monto: monto, moneda:
                moneda, tipo_cambio: tc, monto_bob: moneda === 'USD' ? monto * tc : monto, factura:
                document.getElementById('g_fact').value
        }; fetch('../../api/compras/internaciones.php', {
            method: 'POST', headers:
                { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
        }).then(res => res.json())
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
                // El factor ya engloba todo pasado a BOB, por lo tanto multiplicamos el TC
                // para que el costo internado final se exprese obligatoriamente en moneda base (BOB).
                const tcInternacion = parseFloat(document.getElementById('inputTCFOB').value) || 1;

                const payload = {
                    action: 'finalizar_liquidacion',
                    id_orden_compra: ordenActual.id_orden_compra,
                    items: itemsActuales.map(i => ({
                        id_detalle_oc: i.id_detalle_oc,
                        // Convertir a BOB y multiplicar por el factor de prorrateo
                        costo_internacion: (parseFloat(i.precio_unitario) * tcInternacion * factor).toFixed(4)
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