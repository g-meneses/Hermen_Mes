<?php
// modules/compras/ordenes.php
require_once '../../config/database.php';

if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}
$pageTitle = "Órdenes de Compra";
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
    /* Forzar carga de fuente para iconos */
    .material-symbols-outlined {
        font-family: 'Material Symbols Outlined' !important;
        font-weight: normal;
        font-style: normal;
        font-size: 24px;
        line-height: 1;
        letter-spacing: normal;
        text-transform: none;
        display: inline-block;
        white-space: nowrap;
        word-wrap: normal;
        direction: ltr;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        text-rendering: optimizeLegibility;
    }

    /* Estilos Premium estandarizados */
    .modal-content.xlarge {
        width: 100% !important;
        max-width: 1200px !important;
        border-radius: 1.5rem !important;
        overflow: hidden !important;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        max-height: 95vh;
        display: flex;
        flex-direction: column;
    }

    .modal-body-scroll {
        max-height: calc(95vh - 160px);
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }

    .modal-body-scroll::-webkit-scrollbar {
        width: 6px;
    }

    .modal-body-scroll::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 10px;
    }

    .premium-modal-header {
        background: linear-gradient(135deg, #1e40af 0%, #0f172a 100%) !important;
        padding: 1.25rem 2rem !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
    }

    .premium-modal-header h3 {
        color: white !important;
        margin: 0;
        font-weight: 700;
        letter-spacing: -0.025em;
        display: flex;
        align-items: center;
        gap: 0.75rem;
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

    .table-premium tbody tr:hover {
        @apply bg-slate-50;
    }

    .precio-col {
        transition: all 0.3s ease;
    }

    .precio-col.hidden {
        display: none !important;
    }
</style>

<div class="container-fluid font-display py-4">
    <!-- Header de Página -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Órdenes de Compra</h1>
            <p class="text-sm text-slate-500">Gestión de órdenes de compra a proveedores</p>
        </div>
        <div class="flex space-x-2">
            <button
                class="flex items-center space-x-2 bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-all active:scale-95 shadow-sm"
                onclick="abrirModalOrden()">
                <span class="material-symbols-outlined text-lg">add</span>
                <span class="font-semibold text-sm">Nueva Orden</span>
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="premium-card mb-4">
        <div class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <label
                    class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Estado</label>
                <select id="filtroEstado"
                    class="w-full border-slate-200 bg-slate-50 rounded-xl py-2 px-3 text-sm transition-all focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white"
                    onchange="cargarOrdenes()">
                    <option value="todos">Todos</option>
                    <option value="BORRADOR">📝 Borrador</option>
                    <option value="EMITIDA">📤 Emitida</option>
                    <option value="ENVIADA">✈️ Enviada</option>
                    <option value="RECIBIDA">✅ Recibida</option>
                    <option value="CANCELADA">❌ Cancelada</option>
                </select>
            </div>
            <div class="flex-1 min-w-[250px]">
                <label
                    class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Proveedor</label>
                <select id="filtroProveedor"
                    class="w-full border-slate-200 bg-slate-50 rounded-xl py-2 px-3 text-sm transition-all focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white"
                    onchange="cargarOrdenes()">
                    <option value="">Todos los proveedores</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Tabla de Órdenes -->
    <div class="premium-card">
        <div class="overflow-x-auto">
            <table class="w-full table-premium" id="tablaOrdenes">
                <thead>
                    <tr>
                        <th class="text-left">Nº Orden</th>
                        <th class="text-left">Fecha</th>
                        <th class="text-left">Proveedor</th>
                        <th class="text-left">Solicitud Ref.</th>
                        <th class="text-right">Total (BOB)</th>
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

<!-- Modal Nueva Orden -->
<div class="modal fade" id="modalOrden" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 1200px;">
        <div class="modal-content xlarge">
            <div class="premium-modal-header">
                <h3 id="tituloModal">
                    <span class="material-symbols-outlined">description</span>
                    Nueva Orden de Compra
                </h3>
                <button type="button" class="text-white/70 hover:text-white transition-colors" data-dismiss="modal">
                    <span class="material-symbols-outlined text-2xl">close</span>
                </button>
            </div>
            <div class="modal-body modal-body-scroll p-6">
                <form id="formOrden">
                    <!-- Selector de Solicitud Aprobada -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="alert alert-info py-2 mb-2">
                                <i class="fas fa-info-circle"></i>
                                Seleccione una solicitud aprobada para cargar sus productos automáticamente, o deje
                                vacío para crear una orden manual.
                            </div>
                            <div class="form-group">
                                <label><strong>Solicitud Origen</strong></label>
                                <select class="form-control" id="id_solicitud_origen"
                                    onchange="cargarDesdeSolicitud(this.value)">
                                    <option value="">-- Sin solicitud (Orden Manual) --</option>
                                    <!-- Se cargan dinámicamente -->
                                </select>
                                <input type="hidden" id="numero_solicitud_ref">
                            </div>
                        </div>
                    </div>

                    <!-- Datos Generales -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Nº
                                Orden</label>
                            <input type="text" id="numero_orden"
                                class="w-full border-slate-200 bg-slate-100 rounded-xl py-2 px-3 text-sm font-mono font-bold text-slate-600"
                                readonly>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Tipo de
                                Compra</label>
                            <select id="tipo_compra"
                                class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all">
                                <option value="LOCAL">🛒 Compra Local</option>
                                <option value="IMPORTACION">🚢 Importación</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label
                                class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Proveedor</label>
                            <select id="id_proveedor"
                                class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all"
                                required>
                                <option value="">Seleccione proveedor...</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Condición de
                                Pago</label>
                            <select id="condicion_pago"
                                class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all">
                                <option value="CONTADO">💵 Contado</option>
                                <option value="CREDITO_15">⏳ Crédito 15 días</option>
                                <option value="CREDITO_30">⏳ Crédito 30 días</option>
                                <option value="CREDITO_60">⏳ Crédito 60 días</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Fecha Entrega
                                Estimada</label>
                            <input type="date" id="fecha_entrega"
                                class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Lugar de
                                Entrega / Puerto</label>
                            <input type="text" id="lugar_entrega"
                                class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm"
                                placeholder="Bodega Central / Arica / Iquique">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                            <h6 class="font-weight-bold mb-0"><i class="fas fa-boxes"></i> Detalle de
                                Productos</h6>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="togglePrecios()">
                                <i class="fas fa-dollar-sign"></i> Mostrar/Ocultar Precios
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered" id="tablaDetalles">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="5%">#</th>
                                        <th>Producto / Descripción</th>
                                        <th width="12%">Cantidad</th>
                                        <th width="10%">Unidad</th>
                                        <th width="12%" class="precio-col">Precio Unit.</th>
                                        <th width="12%" class="precio-col">Subtotal</th>
                                        <th width="5%"></th>
                                    </tr>
                                </thead>
                                <tbody id="bodyDetalles">
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="7">
                                            <button type="button" class="btn btn-sm btn-info" onclick="agregarFila()">
                                                <i class="fas fa-plus"></i> Añadir Línea
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="precio-col">
                                        <td colspan="5" class="text-right font-weight-bold">Total Orden
                                            (BOB):</td>
                                        <td class="font-weight-bold text-primary" id="totalOrdenCell">0.00
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
            </div>

            <!-- Notas/Observaciones -->
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> Notas / Observaciones
                            Adicionales</label>
                        <textarea class="form-control" id="observaciones_orden" rows="3"
                            placeholder="Instrucciones especiales de entrega, especificaciones adicionales, etc."></textarea>
                    </div>
                </div>
            </div>
            </form>
        </div>
        <div class="modal-footer bg-slate-50 border-t border-slate-100 p-4 flex justify-end gap-3">
            <button type="button"
                class="px-4 py-2 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium transition-colors"
                data-dismiss="modal">
                <span class="material-symbols-outlined text-sm align-middle mr-1">close</span>
                Cancelar
            </button>
            <button type="button"
                class="px-5 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-semibold transition-colors shadow-sm"
                onclick="guardarOrden()">
                <span class="material-symbols-outlined text-sm align-middle mr-1">save</span>
                Guardar Orden
            </button>
        </div>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let itemsDetalle = [];

    document.addEventListener('DOMContentLoaded', function () {
        cargarProveedores();
        cargarOrdenes();
    });

    // Variable global para almacenar solicitudes aprobadas
    let solicitudesAprobadas = [];

    // Cargar solicitudes aprobadas disponibles
    function cargarSolicitudesAprobadas() {
        return fetch('../../api/compras/solicitudes.php?action=aprobadas')
            .then(res => res.json())
            .then(data => {
                solicitudesAprobadas = data.solicitudes || [];
                const select = document.getElementById('id_solicitud_origen');
                select.innerHTML = '<option value="">-- Sin solicitud (Orden Manual) --</option>';

                solicitudesAprobadas.forEach(s => {
                    select.innerHTML += `<option value="${s.id_solicitud}">
                        ${s.numero_solicitud} - ${s.solicitante_nombre} (${s.detalles?.length || 0} items)
                    </option>`;
                });
            })
            .catch(err => console.error('Error cargando solicitudes:', err));
    }

    // Cargar productos desde una solicitud aprobada
    function cargarDesdeSolicitud(idSolicitud) {
        if (!idSolicitud) {
            // Si selecciona "Sin solicitud", limpiar y permitir orden manual
            itemsDetalle = [];
            document.getElementById('numero_solicitud_ref').value = '';
            agregarFila();
            return;
        }

        const solicitud = solicitudesAprobadas.find(s => s.id_solicitud == idSolicitud);
        if (!solicitud) return;

        document.getElementById('numero_solicitud_ref').value = solicitud.numero_solicitud;

        // Limpiar y cargar detalles de la solicitud
        itemsDetalle = solicitud.detalles.map(det => ({
            id_detalle_solicitud: det.id_detalle,
            id_producto: det.id_producto,
            codigo_producto: det.codigo_producto || '',
            descripcion_producto: det.descripcion_producto,
            cantidad: parseFloat(det.cantidad_solicitada) || 1,
            unidad_medida: det.unidad_medida || 'Unidad',
            precio_unitario: parseFloat(det.precio_estimado) || 0,
            total: (parseFloat(det.cantidad_solicitada) || 1) * (parseFloat(det.precio_estimado) || 0),
            id_tipo_inventario: det.id_tipo_inventario || 1
        }));

        renderDetalles();

        Swal.fire({
            icon: 'info',
            title: 'Productos Cargados',
            text: `Se cargaron ${itemsDetalle.length} productos de la solicitud ${solicitud.numero_solicitud}. Puede modificar cantidades y precios antes de confirmar.`,
            timer: 3000,
            showConfirmButton: false
        });
    }

    function cargarProveedores() {
        fetch('../../api/proveedores.php?action=list&activo=1')
            .then(res => res.json())
            .then(data => {
                const select = document.getElementById('id_proveedor');
                const filtro = document.getElementById('filtroProveedor');
                data.proveedores.forEach(p => {
                    select.innerHTML += `<option value="${p.id_proveedor}">${p.razon_social}</option>`;
                    filtro.innerHTML += `<option value="${p.id_proveedor}">${p.razon_social}</option>`;
                });
            });
    }

    function cargarOrdenes() {
        const estado = document.getElementById('filtroEstado').value;
        const proveedor = document.getElementById('filtroProveedor').value;

        let url = `../../api/compras/ordenes.php?action=list&estado=${estado}`;
        if (proveedor) url += `&id_proveedor=${proveedor}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                const tbody = document.querySelector('#tablaOrdenes tbody');
                tbody.innerHTML = '';

                if (data.ordenes.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center">No hay órdenes</td></tr>';
                    return;
                }

                data.ordenes.forEach(oc => {
                    const estadoBase = (oc.estado || '').toUpperCase();
                    // Colores y emojis por estado (Tailwind classes)
                    let badgeClasses = '';
                    let estadoIcon = '';

                    switch (estadoBase) {
                        case 'BORRADOR':
                        case '':
                            badgeClasses = 'bg-amber-100 text-amber-700 border border-amber-200';
                            estadoIcon = '📝';
                            oc.estado = 'BORRADOR';
                            break;
                        case 'EMITIDA':
                            badgeClasses = 'bg-blue-100 text-blue-700 border border-blue-200';
                            estadoIcon = '📤';
                            break;
                        case 'ENVIADA':
                            badgeClasses = 'bg-cyan-100 text-cyan-700 border border-cyan-200';
                            estadoIcon = '✈️';
                            break;
                        case 'RECIBIDA':
                        case 'RECIBIDA_TOTAL':
                            badgeClasses = 'bg-emerald-100 text-emerald-700 border border-emerald-200';
                            estadoIcon = '✅';
                            break;
                        case 'CANCELADA':
                            badgeClasses = 'bg-red-100 text-red-700 border border-red-200';
                            estadoIcon = '❌';
                            break;
                        default:
                            badgeClasses = 'bg-slate-100 text-slate-600 border border-slate-200';
                            estadoIcon = '❓';
                    }

                    // Botones de acción con Tailwind
                    let accionesHtml = `
                        <button class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors" onclick="verOrden(${oc.id_orden_compra})" title="Ver detalle">
                            <span class="material-symbols-outlined text-lg">visibility</span>
                        </button>
                        <button class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors" onclick="imprimirOrden(${oc.id_orden_compra})" title="Imprimir PDF">
                            <span class="material-symbols-outlined text-lg">print</span>
                        </button>
                    `;

                    // Agregar botones de cambio de estado según estado actual
                    if (oc.estado === 'BORRADOR') {
                        accionesHtml += `<button class="p-1.5 rounded-lg bg-blue-500 hover:bg-blue-600 text-white transition-colors" onclick="cambiarEstado(${oc.id_orden_compra}, 'EMITIDA')" title="Emitir Orden">
                            <span class="material-symbols-outlined text-lg">check</span>
                        </button>`;
                    } else if (oc.estado === 'EMITIDA') {
                        accionesHtml += `<button class="p-1.5 rounded-lg bg-cyan-500 hover:bg-cyan-600 text-white transition-colors" onclick="cambiarEstado(${oc.id_orden_compra}, 'ENVIADA')" title="Marcar Enviada">
                            <span class="material-symbols-outlined text-lg">send</span>
                        </button>`;
                    } else if (oc.estado === 'ENVIADA') {
                        accionesHtml += `<button class="p-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white transition-colors" onclick="location.href='recepciones.php?id_oc=${oc.numero_orden}'" title="Recibir Mercancía">
                            <span class="material-symbols-outlined text-lg">inventory_2</span>
                        </button>`;
                    }

                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-slate-50 transition-colors';
                    tr.innerHTML = `
                        <td class="font-semibold text-slate-800">${oc.numero_orden}</td>
                        <td class="text-slate-600">${oc.fecha_orden}</td>
                        <td class="text-slate-700">${oc.proveedor_nombre}</td>
                        <td class="text-slate-500">${oc.numero_solicitud || '<span class="text-slate-300">—</span>'}</td>
                        <td class="text-right font-semibold text-slate-800">${parseFloat(oc.total).toFixed(2)}</td>
                        <td class="text-center">
                            <span class="${badgeClasses} inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold shadow-sm whitespace-nowrap">
                                <span>${estadoIcon}</span>
                                <span>${oc.estado || 'S/E'}</span>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="flex justify-center gap-1">${accionesHtml}</div>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            });
    }

    async function abrirModalOrden() {
        // Reset para nueva orden
        ordenEnEdicion = null;

        document.getElementById('formOrden').reset();
        document.getElementById('id_proveedor').value = '';
        document.getElementById('id_solicitud_origen').value = '';
        document.getElementById('numero_solicitud_ref').value = '';
        itemsDetalle = [];
        renderDetalles();

        // Restaurar UI para nueva orden
        document.getElementById('tituloModal').innerHTML = `
            <span class="material-symbols-outlined">description</span>
            Nueva Orden de Compra
        `;
        const alertInfo = document.querySelector('.alert-info');
        if (alertInfo) alertInfo.style.display = 'block';

        const selectorOrigen = document.getElementById('id_solicitud_origen');
        if (selectorOrigen && selectorOrigen.parentElement) {
            selectorOrigen.parentElement.style.display = 'block';
        }

        ['id_proveedor', 'tipo_compra', 'fecha_entrega', 'lugar_entrega', 'condicion_pago', 'observaciones_orden'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = false;
        });

        // Mostrar botón guardar
        const btnGuardar = document.querySelector('.modal-footer .btn-success');
        if (btnGuardar) {
            btnGuardar.style.display = 'inline-block';
            btnGuardar.classList.remove('hidden');
            btnGuardar.innerHTML = `
                <span class="material-symbols-outlined text-sm align-middle mr-1">save</span>
                Guardar Orden
            `;
        }

        // Cargar solicitudes aprobadas disponibles
        await cargarSolicitudesAprobadas();

        fetch('../../api/compras/ordenes.php?action=siguiente_numero')
            .then(res => res.json())
            .then(data => {
                document.getElementById('numero_orden').value = data.numero;
            });

        agregarFila();
        $('#modalOrden').modal('show');
    }

    function agregarFila() {
        itemsDetalle.push({
            descripcion_producto: '',
            cantidad: 1,
            unidad_medida: 'Unidad',
            precio_unitario: 0,
            total: 0
        });
        renderDetalles();
    }

    // Variable para controlar visibilidad de precios
    let preciosVisibles = true;

    function togglePrecios() {
        preciosVisibles = !preciosVisibles;
        document.querySelectorAll('.precio-col').forEach(el => {
            el.classList.toggle('hidden', !preciosVisibles);
        });
    }

    function renderDetalles() {
        const tbody = document.getElementById('bodyDetalles');
        tbody.innerHTML = '';
        let total = 0;

        itemsDetalle.forEach((item, index) => {
            const subtotal = parseFloat(item.cantidad || 0) * parseFloat(item.precio_unitario || 0);
            item.total = subtotal;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="text-center">${index + 1}</td>
                <td>
                    <input type="text" class="form-control form-control-sm" 
                        value="${item.descripcion_producto || ''}" 
                        onchange="actualizarItem(${index}, 'descripcion_producto', this.value)"
                        placeholder="Descripción del producto">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm text-center" 
                        value="${item.cantidad}" min="0.01" step="0.01"
                        onchange="actualizarItem(${index}, 'cantidad', parseFloat(this.value))">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm text-center" 
                        value="${item.unidad_medida}" 
                        onchange="actualizarItem(${index}, 'unidad_medida', this.value)">
                </td>
                <td class="precio-col${preciosVisibles ? '' : ' hidden'}">
                    <input type="number" class="form-control form-control-sm text-right" 
                        value="${item.precio_unitario || 0}" min="0" step="0.01"
                        onchange="actualizarItem(${index}, 'precio_unitario', parseFloat(this.value))">
                </td>
                <td class="precio-col text-right${preciosVisibles ? '' : ' hidden'}">
                    ${subtotal.toFixed(2)}
                </td>
                <td class="text-center">
                    <button type="button" class="p-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 transition-colors" onclick="eliminarFila(${index})">
                        <span class="material-symbols-outlined text-lg">delete</span>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
            total += subtotal;
        });

        // Actualizar total en footer
        const totalCell = document.getElementById('totalOrdenCell');
        if (totalCell) totalCell.textContent = total.toFixed(2);
    }

    function actualizarItem(index, field, value) {
        itemsDetalle[index][field] = value;
        if (field === 'cantidad' || field === 'precio_unitario') {
            itemsDetalle[index].total = itemsDetalle[index].cantidad * itemsDetalle[index].precio_unitario;
        }
        renderDetalles();
    }

    function eliminarFila(index) {
        itemsDetalle.splice(index, 1);
        renderDetalles();
    }

    function guardarOrden() {
        const idSolicitud = document.getElementById('id_solicitud_origen').value;
        const numeroSolicitud = document.getElementById('numero_solicitud_ref').value;

        // Calcular total desde los items
        let totalCalculado = 0;
        itemsDetalle.forEach(item => {
            totalCalculado += (parseFloat(item.cantidad) || 0) * (parseFloat(item.precio_unitario) || 0);
        });
        const totalGeneral = totalCalculado.toFixed(2);

        // Determinar si es creación o actualización
        const esActualizacion = ordenEnEdicion !== null;

        const data = {
            id_orden_compra: esActualizacion ? ordenEnEdicion : null,
            action: esActualizacion ? 'update' : (idSolicitud ? 'create_from_request' : 'create'),
            numero_orden: document.getElementById('numero_orden').value,
            tipo_compra: document.getElementById('tipo_compra').value,
            id_proveedor: document.getElementById('id_proveedor').value,
            id_solicitud: idSolicitud || null,
            numero_solicitud: numeroSolicitud || null,
            fecha_entrega_estimada: document.getElementById('fecha_entrega').value,
            lugar_entrega: document.getElementById('lugar_entrega').value,
            condicion_pago: document.getElementById('condicion_pago').value,
            observaciones: document.getElementById('observaciones_orden').value,
            total: totalGeneral,
            detalles: itemsDetalle.map(item => ({
                ...item,
                id_tipo_inventario: item.id_tipo_inventario || 1
            }))
        };

        if (data.detalles.length === 0 || !data.id_proveedor) {
            Swal.fire('Error', 'Complete los campos obligatorios', 'error');
            return;
        }

        fetch('../../api/compras/ordenes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    $('#modalOrden').modal('hide');
                    ordenEnEdicion = null; // Reset
                    Swal.fire('Éxito', esActualizacion ? 'Orden actualizada' : 'Orden creada', 'success');
                    cargarOrdenes();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
    }

    // Función para imprimir/generar PDF de la orden
    function imprimirOrden(id) {
        window.open(`orden_pdf.php?id=${id}`, '_blank');
    }

    // Variable para almacenar el ID de orden en edición
    let ordenEnEdicion = null;

    function verOrden(id) {
        // Cargar datos de la orden desde la API
        fetch(`../../api/compras/ordenes.php?action=get&id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    Swal.fire('Error', data.message || 'No se pudo cargar la orden', 'error');
                    return;
                }

                const orden = data.orden;
                ordenEnEdicion = orden.id_orden_compra;

                // Determinar si es editable (solo en BORRADOR)
                const esEditable = orden.estado === 'BORRADOR';

                // Actualizar título del modal
                document.getElementById('tituloModal').innerHTML = esEditable
                    ? `<span class="material-symbols-outlined">edit</span> Editar Orden: ${orden.numero_orden}`
                    : `<span class="material-symbols-outlined">visibility</span> Ver Orden: ${orden.numero_orden}`;

                // Poblar campos
                document.getElementById('numero_orden').value = orden.numero_orden;
                document.getElementById('tipo_compra').value = orden.tipo_compra || 'LOCAL';
                document.getElementById('id_proveedor').value = orden.id_proveedor;
                document.getElementById('fecha_entrega').value = orden.fecha_entrega_estimada ? orden.fecha_entrega_estimada.split(' ')[0] : '';
                document.getElementById('lugar_entrega').value = orden.lugar_entrega || '';
                document.getElementById('condicion_pago').value = orden.condicion_pago || 'CONTADO';
                document.getElementById('observaciones_orden').value = orden.observaciones || '';
                document.getElementById('id_solicitud_origen').value = orden.id_solicitud || '';
                document.getElementById('numero_solicitud_ref').value = orden.numero_solicitud || '';

                // Cargar detalles en itemsDetalle
                itemsDetalle = (orden.detalles || []).map(det => ({
                    id_detalle: det.id_detalle,
                    id_producto: det.id_producto,
                    codigo_producto: det.codigo_producto || '',
                    descripcion_producto: det.descripcion_producto,
                    cantidad: parseFloat(det.cantidad_ordenada) || 1,
                    unidad_medida: det.unidad_medida || 'Unidad',
                    precio_unitario: parseFloat(det.precio_unitario) || 0,
                    total: parseFloat(det.total_linea) || 0,
                    id_tipo_inventario: det.id_tipo_inventario || 1
                }));

                renderDetalles();

                // Habilitar/deshabilitar campos según estado
                const campos = ['id_proveedor', 'fecha_entrega', 'condicion_pago', 'observaciones_orden'];
                campos.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.disabled = !esEditable;
                });

                // Ocultar/mostrar selector de solicitud y botón agregar fila
                const alertInfo = document.querySelector('.alert-info');
                if (alertInfo) alertInfo.style.display = esEditable ? 'block' : 'none';

                const selectorOrigen = document.getElementById('id_solicitud_origen');
                if (selectorOrigen && selectorOrigen.parentElement) {
                    selectorOrigen.parentElement.style.display = esEditable ? 'block' : 'none';
                }

                // Botón añadir línea
                const btnAdd = document.querySelector('button[onclick="agregarFila()"]');
                if (btnAdd) btnAdd.style.display = esEditable ? 'inline-block' : 'none';

                // Botón guardar
                const btnGuardar = document.querySelector('.modal-footer .btn-success');
                if (btnGuardar) {
                    if (esEditable) {
                        btnGuardar.classList.remove('hidden');
                        btnGuardar.style.display = 'inline-block';
                        btnGuardar.innerHTML = '<span class="material-symbols-outlined text-sm align-middle mr-1">save</span> Actualizar Orden';
                    } else {
                        btnGuardar.classList.add('hidden');
                        btnGuardar.style.display = 'none';
                    }
                }

                $('#modalOrden').modal('show');
            })
            .catch(err => {
                console.error('Error cargando orden:', err);
                Swal.fire('Error', 'No se pudo cargar el detalle de la orden: ' + err.message, 'error');
            });
    }

    // Función para cambiar estado de la orden
    function cambiarEstado(idOrden, nuevoEstado) {
        const mensajes = {
            'EMITIDA': '¿Emitir esta orden? Una vez emitida, no podrá modificar los productos.',
            'ENVIADA': '¿Marcar como enviada al proveedor?',
            'RECIBIDA': '¿Confirmar recepción completa de esta orden?',
            'CANCELADA': '¿Cancelar esta orden? Esta acción no se puede deshacer.'
        };

        const iconos = {
            'EMITIDA': 'question',
            'ENVIADA': 'info',
            'RECIBIDA': 'success',
            'CANCELADA': 'warning'
        };

        Swal.fire({
            title: 'Cambiar Estado',
            text: mensajes[nuevoEstado] || '¿Confirmar cambio de estado?',
            icon: iconos[nuevoEstado] || 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                fetch('../../api/compras/ordenes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'change_status',
                        id_orden_compra: idOrden,
                        estado: nuevoEstado
                    })
                })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            Swal.fire('¡Actualizado!', 'El estado ha sido cambiado.', 'success');
                            cargarOrdenes();
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    });
            }
        });
    }
</script>

<?php include '../../includes/footer.php'; ?>