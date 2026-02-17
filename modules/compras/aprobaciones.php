<?php
// modules/compras/aprobaciones.php
require_once '../../config/database.php';

if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}
$pageTitle = "Centro de Aprobaciones";
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

<style type="text/tailwindcss">
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f8fafc;
    }

    .kpi-card-premium {
        @apply bg-white p-6 rounded-3xl border border-slate-100 shadow-sm transition-all hover:shadow-md;
    }

    .tab-trigger {
        @apply px-6 py-3 text-sm font-semibold text-slate-500 rounded-xl transition-all cursor-pointer border border-transparent;
    }

    .tab-trigger.active {
        @apply bg-white text-primary border-slate-100 shadow-sm;
    }

    .status-badge {
        @apply px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider;
    }

    .status-pend {
        @apply bg-amber-50 text-amber-600 border border-amber-100;
    }

    .status-aprob {
        @apply bg-emerald-50 text-emerald-600 border border-emerald-100;
    }

    .status-rech {
        @apply bg-rose-50 text-rose-600 border border-rose-100;
    }

    /* Estilos Modal Detail */
    .modal-content.xlarge {
        width: 100% !important;
        max-width: 1000px !important;
        border-radius: 1.5rem !important;
        overflow: hidden !important;
        display: flex !important;
        flex-direction: column !important;
        max-height: 90vh !important;
    }

    .modal-body-scroll {
        max-height: 70vh;
        overflow-y: auto;
    }
</style>

<div class="p-4 lg:p-8 max-w-[1600px] mx-auto">
    <!-- Header y Stats -->
    <header class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Centro de Aprobaciones</h1>
            <p class="text-slate-500 mt-1">Gestione las validaciones de solicitudes y órdenes de compra</p>
        </div>

        <div class="flex gap-4">
            <div class="bg-white px-4 py-2 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center">
                    <span class="material-symbols-outlined">pending_actions</span>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase">Pendientes</p>
                    <p class="text-xl font-bold text-slate-800" id="stat-pendientes">0</p>
                </div>
            </div>
            <div class="bg-white px-4 py-2 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center">
                    <span class="material-symbols-outlined">task_alt</span>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase">Aprobados Hoy</p>
                    <p class="text-xl font-bold text-slate-800" id="stat-aprobados">0</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Tabs Navigation -->
    <div class="bg-slate-200/50 p-1.5 rounded-2xl inline-flex mb-8">
        <div onclick="switchTab('pendientes')" id="btn-tab-pendientes" class="tab-trigger active">Pendientes por Validar
        </div>
        <div onclick="switchTab('historial')" id="btn-tab-historial" class="tab-trigger">Historial de Decisiones</div>
    </div>

    <!-- Contenido: Pendientes -->
    <div id="content-pendientes" class="space-y-4">
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Documento
                        </th>
                        <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Fecha</th>
                        <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Solicitante
                        </th>
                        <th
                            class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">
                            Prioridad</th>
                        <th
                            class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">
                            Acciones</th>
                    </tr>
                </thead>
                <tbody id="lista-pendientes" class="divide-y divide-slate-50">
                    <!-- Dinámico -->
                </tbody>
            </table>
            <div id="empty-pendientes" class="hidden p-20 text-center">
                <span class="material-symbols-outlined text-6xl text-slate-200 mb-4">check_circle</span>
                <p class="text-slate-500 font-medium">¡Todo al día! No hay documentos pendientes de aprobación.</p>
            </div>
        </div>
    </div>

    <!-- Contenido: Historial -->
    <div id="content-historial" class="hidden space-y-4">
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden text-sm">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Documento
                        </th>
                        <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Fecha
                            Acción</th>
                        <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Decisión
                        </th>
                        <th class="py-4 px-6 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Comentarios
                        </th>
                    </tr>
                </thead>
                <tbody id="lista-historial" class="divide-y divide-slate-50">
                    <!-- Dinámico -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Procesar Aprobación -->
<div class="modal" id="modalAccion">
    <div class="modal-content !max-w-md !rounded-[2rem] overflow-hidden border-none shadow-2xl">
        <div class="p-8 pb-0">
            <div id="icon-dec" class="w-16 h-16 rounded-2xl flex items-center justify-center mb-6">
                <span class="material-symbols-outlined text-3xl">fact_check</span>
            </div>
            <h3 class="text-2xl font-bold text-slate-900" id="tituloAccion">Procesar Documento</h3>
            <p class="text-slate-500 mt-2 text-sm">Añada un comentario u observación para finalizar el proceso de
                validación.</p>
        </div>

        <div class="p-8">
            <form id="formAccion" class="space-y-6">
                <input type="hidden" id="id_documento">
                <input type="hidden" id="tipo_documento">
                <input type="hidden" id="decision">

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Comentarios
                        Requeridos</label>
                    <textarea
                        class="w-full border-slate-200 rounded-2xl py-3 px-4 text-sm focus:ring-primary focus:border-primary min-h-[100px]"
                        id="comentarios" placeholder="Ej: Aprobado según presupuesto mensual..."></textarea>
                </div>
            </form>
        </div>

        <div class="p-8 bg-slate-50 flex gap-3">
            <button type="button"
                class="flex-1 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-white transition-all"
                onclick="$('#modalAccion').modal('hide')">
                Cancelar
            </button>
            <button type="button" id="btnConfirmDecision"
                class="flex-[2] py-3 rounded-xl text-white font-bold transition-all shadow-lg"
                onclick="enviarDecision()">
                Confirmar Acción
            </button>
        </div>
    </div>
</div>

<!-- Modal Vista Detalle (Solo Lectura) -->
<div class="modal" id="modalVerDetalle">
    <div class="modal-content xlarge">
        <div class="bg-slate-900 p-6 flex justify-between items-center text-white">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined">visibility</span>
                <span class="font-bold tracking-tight" id="detail-title">Detalle del Documento</span>
            </div>
            <button onclick="$('#modalVerDetalle').modal('hide')"
                class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/10">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="modal-body-scroll p-8 bg-slate-50/50" id="detail-body">
            <!-- Cargado dinámicamente -->
        </div>
        <div class="p-6 bg-white border-t flex justify-end">
            <button onclick="$('#modalVerDetalle').modal('hide')"
                class="px-8 py-2 bg-slate-800 text-white rounded-xl font-bold">Cerrar Vista</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        cargarPendientes();
        cargarHistorial(); // Para actualizar los contadores de la cabecera
    });

    function switchTab(tab) {
        document.getElementById('content-pendientes').classList.toggle('hidden', tab !== 'pendientes');
        document.getElementById('content-historial').classList.toggle('hidden', tab !== 'historial');

        document.getElementById('btn-tab-pendientes').classList.toggle('active', tab === 'pendientes');
        document.getElementById('btn-tab-historial').classList.toggle('active', tab === 'historial');

        if (tab === 'historial') cargarHistorial();
    }

    async function cargarPendientes() {
        const res = await fetch('../../api/compras/aprobaciones.php?action=pendientes');
        const data = await res.json();

        const tbody = document.getElementById('lista-pendientes');
        const empty = document.getElementById('empty-pendientes');
        tbody.innerHTML = '';

        if (!data.pendientes || data.pendientes.length === 0) {
            empty.classList.remove('hidden');
            document.getElementById('stat-pendientes').textContent = '0';
            return;
        }

        empty.classList.add('hidden');
        document.getElementById('stat-pendientes').textContent = data.pendientes.length;

        data.pendientes.forEach(p => {
            const tr = document.createElement('tr');
            tr.className = "hover:bg-slate-50 transition-all";

            const prioClass = p.prioridad === 'URGENTE' ? 'bg-rose-50 text-rose-600' :
                p.prioridad === 'ALTA' ? 'bg-amber-50 text-amber-600' : 'bg-slate-100 text-slate-600';

            tr.innerHTML = `
                <td class="py-4 px-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px]">${p.tipo === 'SOLICITUD' ? 'description' : 'shopping_cart'}</span>
                        </div>
                        <div>
                            <p class="font-bold text-slate-800">${p.numero}</p>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">${p.tipo}</p>
                        </div>
                    </div>
                </td>
                <td class="py-4 px-6 text-sm text-slate-500">${p.fecha}</td>
                <td class="py-4 px-6">
                    <p class="text-sm font-medium text-slate-700">${p.solicitante}</p>
                </td>
                <td class="py-4 px-6 text-center">
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold ${prioClass}">${p.prioridad}</span>
                </td>
                <td class="py-4 px-6">
                    <div class="flex justify-center gap-2">
                        <button onclick="verDetalle('${p.tipo}', ${p.id})" class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-800 hover:text-white transition-all flex items-center justify-center shadow-sm" title="Ver Detalle">
                            <span class="material-symbols-outlined text-[18px]">visibility</span>
                        </button>
                        <button onclick="abrirAccion('${p.tipo}', ${p.id}, 'APROBADO')" class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white transition-all flex items-center justify-center shadow-sm" title="Aprobar">
                            <span class="material-symbols-outlined text-[18px]">check_circle</span>
                        </button>
                        <button onclick="abrirAccion('${p.tipo}', ${p.id}, 'RECHAZADO')" class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all flex items-center justify-center shadow-sm" title="Rechazar">
                            <span class="material-symbols-outlined text-[18px]">cancel</span>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    async function cargarHistorial() {
        const res = await fetch('../../api/compras/aprobaciones.php?action=historial');
        const data = await res.json();

        const tbody = document.getElementById('lista-historial');
        tbody.innerHTML = '';

        if (!data.historial || data.historial.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="py-12 text-center text-slate-400">No hay registros en el historial</td></tr>';
            return;
        }

        let aprobadosHoy = 0;
        const now = new Date();
        const hoje = now.getFullYear() + '-' +
            String(now.getMonth() + 1).padStart(2, '0') + '-' +
            String(now.getDate()).padStart(2, '0');

        data.historial.forEach(h => {
            if (h.fecha_aprobacion.includes(hoje) && h.accion === 'APROBADO') aprobadosHoy++;

            const statusClass = h.accion === 'APROBADO' ? 'status-aprob' : h.accion === 'RECHAZADO' ? 'status-rech' : 'status-pend';
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="py-4 px-6 font-bold text-slate-700">${h.documento_numero}</td>
                <td class="py-4 px-6 text-slate-500">${h.fecha_aprobacion}</td>
                <td class="py-4 px-6">
                    <span class="status-badge ${statusClass}">${h.accion}</span>
                </td>
                <td class="py-4 px-6 text-slate-500 italic">${h.comentarios || '--'}</td>
            `;
            tbody.appendChild(tr);
        });
        document.getElementById('stat-aprobados').textContent = aprobadosHoy;
    }

    function abrirAccion(tipo, id, decision) {
        document.getElementById('id_documento').value = id;
        document.getElementById('tipo_documento').value = tipo === 'SOLICITUD' ? 'SOLICITUD_COMPRA' : 'ORDEN_COMPRA';
        document.getElementById('decision').value = decision;
        document.getElementById('comentarios').value = '';

        const iconDiv = document.getElementById('icon-dec');
        const btn = document.getElementById('btnConfirmDecision');
        const title = document.getElementById('tituloAccion');

        if (decision === 'APROBADO') {
            title.textContent = "Aprobar Documento";
            iconDiv.className = "w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-6";
            iconDiv.innerHTML = '<span class="material-symbols-outlined text-3xl">verified</span>';
            btn.className = "flex-[2] py-3 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold transition-all shadow-lg shadow-emerald-200";
        } else {
            title.textContent = "Rechazar Documento";
            iconDiv.className = "w-16 h-16 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mb-6";
            iconDiv.innerHTML = '<span class="material-symbols-outlined text-3xl">dangerous</span>';
            btn.className = "flex-[2] py-3 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold transition-all shadow-lg shadow-rose-200";
        }

        $('#modalAccion').modal('show');
    }

    async function verDetalle(tipo, id) {
        let url = tipo === 'SOLICITUD' ? `../../api/compras/solicitudes.php?action=get&id=${id}` : `../../api/compras/ordenes.php?action=get&id=${id}`;

        try {
            const res = await fetch(url);
            const data = await res.json();

            if (data.success) {
                const doc = tipo === 'SOLICITUD' ? data.solicitud : data.orden;
                document.getElementById('detail-title').textContent = `${tipo} #${doc.numero_solicitud || doc.numero_orden}`;

                let html = `
                    <div class="grid grid-cols-2 gap-8 mb-8">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Solicitante</p>
                            <p class="text-lg font-bold text-slate-800">${doc.solicitante_nombre}</p>
                            <p class="text-sm text-slate-500">${doc.area_solicitante || 'Planta Hermen'}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Prioridad</p>
                            <span class="px-3 py-1 rounded-lg text-xs font-bold ${doc.prioridad === 'URGENTE' ? 'bg-rose-50 text-rose-600' : 'bg-slate-100 text-slate-600'}">${doc.prioridad}</span>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 mb-8">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Justificación</p>
                        <p class="text-slate-700">${doc.motivo || 'No se proporcionó justificación'}</p>
                    </div>

                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-4">Ítems del Documento</p>
                    <table class="w-full text-sm text-left border-collapse bg-white rounded-xl overflow-hidden border border-slate-100">
                        <thead>
                            <tr class="bg-slate-50">
                                <th class="py-3 px-4 font-bold text-slate-500">Producto</th>
                                <th class="py-3 px-4 font-bold text-slate-500 text-center">Stock Sol.</th>
                                <th class="py-3 px-4 font-bold text-slate-500 text-center">Stock Act.</th>
                                <th class="py-3 px-4 font-bold text-slate-500 text-center">Cant. Pedida</th>
                                <th class="py-3 px-4 font-bold text-slate-500 text-center">Unidad</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                `;

                doc.detalles.forEach(d => {
                    html += `
                        <tr>
                            <td class="py-3 px-4 font-medium text-slate-700">${d.descripcion_producto}</td>
                            <td class="py-3 px-4 text-center font-mono font-bold text-slate-500">${parseFloat(d.stock_solicitud || 0).toLocaleString(undefined, { minimumFractionDigits: 1 })}</td>
                            <td class="py-3 px-4 text-center font-mono font-bold ${parseFloat(d.stock_actual || 0) <= 0 ? 'text-rose-500' : 'text-blue-500'}">${parseFloat(d.stock_actual || 0).toLocaleString(undefined, { minimumFractionDigits: 1 })}</td>
                            <td class="py-3 px-4 text-center font-bold text-slate-900">${parseFloat(d.cantidad_solicitada || d.cantidad).toLocaleString(undefined, { minimumFractionDigits: 0 })}</td>
                            <td class="py-3 px-4 text-center text-slate-400 font-medium">${d.unidad_medida}</td>
                        </tr>
                    `;
                });

                html += `</tbody></table>`;

                document.getElementById('detail-body').innerHTML = html;
                $('#modalVerDetalle').modal('show');
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo cargar el detalle', 'error');
        }
    }

    async function enviarDecision() {
        const comentarios = document.getElementById('comentarios').value;
        if (!comentarios.trim()) {
            Swal.fire('Atención', 'Por favor ingrese un comentario para la aprobación/rechazo', 'warning');
            return;
        }

        const data = {
            action: 'procesar',
            id_documento: document.getElementById('id_documento').value,
            tipo_documento: document.getElementById('tipo_documento').value,
            decision: document.getElementById('decision').value,
            comentarios: comentarios
        };

        const res = await fetch('../../api/compras/aprobaciones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await res.json();
        if (result.success) {
            $('#modalAccion').modal('hide');
            Swal.fire({
                icon: 'success',
                title: 'Procesado',
                text: 'El documento ha sido actualizado correctamente.',
                timer: 2000,
                showConfirmButton: false
            });
            cargarPendientes();
            if (document.getElementById('btn-tab-historial').classList.contains('active')) cargarHistorial();
        } else {
            Swal.fire('Error', result.message, 'error');
        }
    }
</script>

<?php include '../../includes/footer.php'; ?>