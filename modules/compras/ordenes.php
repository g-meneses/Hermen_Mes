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
                                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Tipo
                                        de
                                        Compra</label>
                                    <select id="tipo_compra"
                                        class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all"
                                        onchange="filtrarProveedores()">
                                        <option value="LOCAL">🛒 Compra Local</option>
                                        <option value="IMPORTACION">🚢 Importación</option>
                                    </select>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <label
                                            class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Proveedor</label>
                                        <span id="badge_regimen"
                                            class="hidden text-[9px] font-black uppercase bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full shadow-sm">Directo
                                            / Sin Factura</span>
                                    </div>
                                    <select id="id_proveedor"
                                        class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all"
                                        onchange="actualizarBadgeRegimen()" required>
                                        <option value="">Seleccione proveedor...</option>
                                    </select>
                                </div>
                                <div class="space-y-1.5">
                                    <label
                                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Condición
                                        de
                                        Pago</label>
                                    <select id="condicion_pago"
                                        class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all">
                                        <option value="CONTADO">💵 Contado</option>
                                        <option value="CREDITO_15">⏳ Crédito 15 días</option>
                                        <option value="CREDITO_30">⏳ Crédito 30 días</option>
                                        <option value="CREDITO_60">⏳ Crédito 60 días</option>
                                        <option value="A_CONVENIR">🤝 A convenir con el proveedor</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Fecha
                                        Entrega
                                        Estimada</label>
                                    <input type="date" id="fecha_entrega"
                                        class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm">
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Lugar
                                        de
                                        Entrega / Puerto</label>
                                    <input type="text" id="lugar_entrega"
                                        class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm"
                                        placeholder="Bodega Central / Arica / Iquique">
                                </div>
                            </div>

                            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden mb-6">
                                <div
                                    class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                                    <h4
                                        class="text-xs font-bold text-slate-500 uppercase tracking-widest flex items-center gap-2">
                                        <span class="material-symbols-outlined text-sm">inventory_2</span>
                                        Detalle de Productos
                                    </h4>
                                    <button type="button"
                                        class="text-[10px] font-bold text-primary hover:text-blue-700 uppercase tracking-wider"
                                        onclick="togglePrecios()">
                                        <span class="material-symbols-outlined text-xs align-middle">visibility</span>
                                        Alternar Precios
                                    </button>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse table-premium" id="tablaDetalles">
                                        <thead>
                                            <tr>
                                                <th width="5%" class="text-center">#</th>
                                                <th width="45%">Producto / Descripción</th>
                                                <th width="15%" class="text-center">Cantidad</th>
                                                <th width="10%" class="text-center">Unidad</th>
                                                <th width="12%" class="text-right precio-col">Precio Unit.</th>
                                                <th width="13%" class="text-right precio-col">Subtotal</th>
                                                <th width="5%"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="bodyDetalles">
                                            <!-- Dinámico -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="p-4 border-t border-slate-50 bg-slate-50/30">
                                    <button type="button"
                                        class="flex items-center gap-2 text-xs font-bold text-primary hover:bg-white px-4 py-2 rounded-xl transition-all"
                                        onclick="agregarFila()">
                                        <span class="material-symbols-outlined text-sm">add_circle</span>
                                        AÑADIR NUEVA LÍNEA
                                    </button>
                                </div>
                            </div>

                            <!-- Gastos Adicionales -->
                            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden mb-6">
                                <div class="p-4 bg-slate-50 border-b border-slate-100">
                                    <h4
                                        class="text-xs font-bold text-slate-500 uppercase tracking-widest flex items-center gap-2">
                                        <span class="material-symbols-outlined text-sm">payments</span>
                                        Gastos Adicionales (Fletes, Estibajes, Varios)
                                    </h4>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse table-premium" id="tablaGastos">
                                        <thead>
                                            <tr>
                                                <th width="25%">Tipo de Gasto</th>
                                                <th width="40%">Descripción / Nota</th>
                                                <th width="15%" class="text-center">Moneda</th>
                                                <th width="15%" class="text-right">Monto</th>
                                                <th width="5%"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="bodyGastos">
                                            <!-- Dinámico -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="p-4 border-t border-slate-50 bg-slate-50/30">
                                    <button type="button"
                                        class="flex items-center gap-2 text-xs font-bold text-indigo-600 hover:bg-white px-4 py-2 rounded-xl transition-all shadow-sm active:scale-95"
                                        onclick="agregarGasto()">
                                        <span class="material-symbols-outlined text-sm">add_circle</span>
                                        AÑADIR GASTO ASOCIADO
                                    </button>
                                </div>
                            </div>

                            <div class="mt-8">
                                <div class="flex flex-col md:flex-row gap-6 items-start">
                                    <!-- Notas a la izquierda -->
                                    <div class="flex-1 w-full space-y-2">
                                        <label
                                            class="text-[10px] font-bold text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                            <span class="material-symbols-outlined text-sm">notes</span>
                                            Notas y Especificaciones
                                        </label>
                                        <textarea id="observaciones_orden" rows="4"
                                            class="w-full border-slate-200 rounded-2xl py-3 px-4 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none bg-slate-50/50"
                                            placeholder="Instrucciones especiales de entrega, especificaciones adicionales, etc."></textarea>
                                    </div>

                                    <!-- Totales a la derecha -->
                                    <div
                                        class="w-full md:w-80 bg-slate-900 rounded-3xl p-6 text-white shadow-xl shadow-slate-200">
                                        <div class="space-y-4">
                                            <div class="flex justify-between items-center opacity-60">
                                                <span class="text-xs font-bold uppercase tracking-wider">Subtotal</span>
                                                <span class="font-mono" id="subtotal_display">0.00</span>
                                            </div>
                                            <div class="flex justify-between items-center opacity-60">
                                                <span
                                                    class="text-xs font-bold uppercase tracking-wider">Descuentos</span>
                                                <span class="font-mono">0.00</span>
                                            </div>
                                            <div class="h-px bg-white/10 my-2"></div>
                                            <div class="flex justify-between items-end">
                                                <div>
                                                    <span
                                                        class="text-[10px] font-bold uppercase tracking-widest text-primary-400 block mb-1">Total
                                                        Orden</span>
                                                    <span class="text-3xl font-bold tracking-tighter"
                                                        id="totalOrdenCell">0.00</span>
                                                </div>
                                                <span class="text-sm font-bold opacity-40 mb-1 ml-2">BOB</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                </form>
            </div>
            <div class="modal-footer bg-white border-t border-slate-100 p-6 flex justify-end gap-3 rounded-b-[2rem]">
                <button type="button"
                    class="px-6 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition-all active:scale-95 flex items-center gap-2"
                    data-dismiss="modal">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                    Cancelar
                </button>
                <button type="button" id="btnGuardarOrden"
                    class="px-8 py-2.5 rounded-xl bg-primary hover:bg-blue-600 text-white font-bold transition-all active:scale-95 shadow-lg shadow-primary/20 flex items-center gap-2"
                    onclick="guardarOrden()">
                    <span class="material-symbols-outlined text-[20px]">task_alt</span>
                    Confirmar Orden
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let itemsDetalle = [];
    let itemsGastos = [];
    let solicitudesAprobadas = [];
    let listaProveedores = [];
    let totalGeneral = 0;
    let preciosVisibles = true;
    let ordenEnEdicion = null;

    function agregarGasto() {
        itemsGastos.push({
            tipo_gasto: 'FLETE',
            descripcion: '',
            moneda: 'BOB',
            monto: 0
        });
        renderGastos();
    }

    function renderGastos() {
        const tbody = document.getElementById('bodyGastos');
        if (!tbody) return;
        tbody.innerHTML = '';

        itemsGastos.forEach((gasto, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <select class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all"
                        onchange="actualizarGasto(${index}, 'tipo_gasto', this.value)">
                        <option value="FLETE" ${gasto.tipo_gasto === 'FLETE' ? 'selected' : ''}>🚚 Flete / Transporte</option>
                        <option value="ESTIBAJE" ${gasto.tipo_gasto === 'ESTIBAJE' ? 'selected' : ''}>💪 Estibaje / Carga</option>
                        <option value="COMISION" ${gasto.tipo_gasto === 'COMISION' ? 'selected' : ''}>💰 Comisión</option>
                        <option value="SEGURO" ${gasto.tipo_gasto === 'SEGURO' ? 'selected' : ''}>🛡️ Seguro</option>
                        <option value="OTROS" ${gasto.tipo_gasto === 'OTROS' ? 'selected' : ''}>⚙️ Otros</option>
                    </select>
                </td>
                <td>
                    <input type="text" class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all"
                        value="${gasto.descripcion || ''}" 
                        onchange="actualizarGasto(${index}, 'descripcion', this.value)"
                        placeholder="Ej: Transporte desde puerto a planta">
                </td>
                <td>
                    <select class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm text-center focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all"
                        onchange="actualizarGasto(${index}, 'moneda', this.value)">
                        <option value="BOB" ${gasto.moneda === 'BOB' ? 'selected' : ''}>BOB</option>
                        <option value="USD" ${gasto.moneda === 'USD' ? 'selected' : ''}>USD</option>
                    </select>
                </td>
                <td>
                    <input type="number" class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm text-right font-mono font-bold text-indigo-600 focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all"
                        value="${gasto.monto || 0}" min="0" step="0.01"
                        onchange="actualizarGasto(${index}, 'monto', parseFloat(this.value))">
                </td>
                <td class="text-center">
                    <button type="button" class="p-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 transition-colors" onclick="eliminarGasto(${index})">
                        <span class="material-symbols-outlined text-lg">delete</span>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        // No afectamos el total de la OC porque estos gastos pueden ser directos
        // Pero los guardamos para el costeo final
    }

    function actualizarGasto(index, field, value) {
        itemsGastos[index][field] = value;
    }

    function eliminarGasto(index) {
        itemsGastos.splice(index, 1);
        renderGastos();
    }

    document.addEventListener('DOMContentLoaded', function () {
        cargarProveedores();
        cargarOrdenes();
    });


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
        return fetch('../../api/proveedores.php?action=list&activo=1')
            .then(res => res.json())
            .then(data => {
                listaProveedores = data.proveedores || [];
                // El filtro se encarga de poblar el select del modal
                filtrarProveedores();

                // Poblar filtro de la tabla (todos siempre)
                const filtro = document.getElementById('filtroProveedor');
                filtro.innerHTML = '<option value="">Todos los proveedores</option>';
                listaProveedores.forEach(p => {
                    filtro.innerHTML += `<option value="${p.id_proveedor}">${p.razon_social}</option>`;
                });
            });
    }

    function filtrarProveedores() {
        const tipo = document.getElementById('tipo_compra').value;
        const select = document.getElementById('id_proveedor');
        const currentVal = select.value;

        select.innerHTML = '<option value="">Seleccione proveedor...</option>';

        const filtrados = listaProveedores.filter(p => {
            if (tipo === 'LOCAL') return p.tipo === 'LOCAL';
            if (tipo === 'IMPORTACION') return p.tipo === 'IMPORTACION';
            return true;
        });

        filtrados.forEach(p => {
            select.innerHTML += `<option value="${p.id_proveedor}" ${p.id_proveedor == currentVal ? 'selected' : ''} data-regimen="${p.regimen_tributario || ''}">${p.razon_social}</option>`;
        });

        actualizarBadgeRegimen();
    }

    function actualizarBadgeRegimen() {
        const select = document.getElementById('id_proveedor');
        const badge = document.getElementById('badge_regimen');
        if (!select || !badge) return;

        const opt = select.options[select.selectedIndex];
        if (opt && opt.dataset.regimen === 'DIRECTO_SIN_FACTURA') {
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
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
                        <button class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-blue-600 transition-colors" onclick="imprimirOrden(${oc.id_orden_compra})" title="Imprimir (ES)">
                            <span class="material-symbols-outlined text-lg">print</span>
                        </button>
                        <button class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-rose-600 transition-colors" onclick="imprimirOrdenEN(${oc.id_orden_compra})" title="Print (EN)">
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
        itemsGastos = [];
        renderDetalles();
        renderGastos();

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
        const btnGuardar = document.getElementById('btnGuardarOrden');
        if (btnGuardar) {
            btnGuardar.classList.remove('hidden');
            btnGuardar.style.display = 'flex'; // Usar flex por el gap de Tailwind
            btnGuardar.innerHTML = `
                <span class="material-symbols-outlined text-[20px]">task_alt</span>
                Confirmar Orden
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
        const esDraft = !ordenEnEdicion || (document.getElementById('tituloModal').innerText.includes('Editar') && !document.querySelectorAll('.btn-cambio-estado').length);
        // Simplificación: si ordenEnEdicion es null es nueva. Si es verOrden, se controla por el flag esEditable que ya existe en verOrden

        itemsDetalle.forEach((item, index) => {
            const subtotal = parseFloat(item.cantidad || 0) * parseFloat(item.precio_unitario || 0);
            item.total = subtotal;

            const tr = document.createElement('tr');
            const esCompleta = item.estado_recepcion === 'COMPLETA';
            if (esCompleta) tr.classList.add('bg-emerald-50/50');

            tr.innerHTML = `
                <td class="text-center font-mono text-xs text-slate-400">${index + 1}</td>
                <td>
                    ${!ordenEnEdicion ?
                    `<input type="text" 
                            class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all"
                            value="${item.descripcion_producto || ''}" 
                            onchange="actualizarItem(${index}, 'descripcion_producto', this.value)"
                            placeholder="Descripción del producto">` :
                    `<div class="flex flex-col"><span class="font-bold text-slate-700">${item.descripcion_producto}</span><span class="text-[10px] text-slate-400 font-mono">${item.codigo_producto || ''}</span></div>`
                }
                </td>
                <td>
                    ${!ordenEnEdicion ?
                    `<input type="number" 
                            class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm text-center font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all"
                            value="${item.cantidad}" min="0.01" step="0.01"
                            onchange="actualizarItem(${index}, 'cantidad', parseFloat(this.value))">` :
                    `<div class="text-center font-bold text-slate-700">${item.cantidad}</div>`
                }
                </td>
                <td class="text-center col-recibido hidden">
                    <span class="font-bold text-emerald-600">${item.cantidad_recibida || 0}</span>
                </td>
                <td class="text-center col-pendiente hidden">
                    <span class="font-bold ${(item.cantidad - (item.cantidad_recibida || 0)) > 0 ? 'text-amber-600' : 'text-slate-400'}">
                        ${Math.max(0, item.cantidad - (item.cantidad_recibida || 0)).toFixed(2)}
                    </span>
                </td>
                <td>
                    ${!ordenEnEdicion ?
                    `<input type="text" 
                            class="w-full border-slate-200 bg-slate-50 rounded-xl py-2 px-3 text-sm text-center text-slate-500 font-medium"
                            value="${item.unidad_medida}" 
                            onchange="actualizarItem(${index}, 'unidad_medida', this.value)">` :
                    `<div class="text-center text-xs font-medium text-slate-500">${item.unidad_medida}</div>`
                }
                </td>
                <td class="precio-col${preciosVisibles ? '' : ' hidden'}">
                    ${!ordenEnEdicion ?
                    `<input type="number" 
                            class="w-full border-slate-200 bg-white rounded-xl py-2 px-3 text-sm text-right font-mono font-bold text-primary focus:ring-2 focus:ring-primary/20 focus:border-primary shadow-sm transition-all"
                            value="${item.precio_unitario || 0}" min="0" step="0.01"
                            onchange="actualizarItem(${index}, 'precio_unitario', parseFloat(this.value))">` :
                    `<div class="text-right font-mono text-slate-600">${parseFloat(item.precio_unitario).toFixed(2)}</div>`
                }
                </td>
                <td class="precio-col text-right${preciosVisibles ? '' : ' hidden'} font-mono font-bold text-slate-700">
                    ${subtotal.toFixed(2)}
                </td>
                <td class="text-center">
                    ${!ordenEnEdicion ?
                    `<button type="button" class="p-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 transition-colors" onclick="eliminarFila(${index})">
                            <span class="material-symbols-outlined text-lg">delete</span>
                        </button>` : ''
                }
                </td>
            `;
            tbody.appendChild(tr);
            total += subtotal;
        });

        totalGeneral = total;
        document.getElementById('totalOrdenCell').textContent = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
            })),
            gastos: itemsGastos
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

    function imprimirOrdenEN(id) {
        window.open(`orden_pdf.php?id=${id}&lang=en`, '_blank');
    }


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
                filtrarProveedores(); // Refrescar lista según tipo
                document.getElementById('id_proveedor').value = orden.id_proveedor;
                document.getElementById('fecha_entrega').value = orden.fecha_entrega_estimada ? orden.fecha_entrega_estimada.split(' ')[0] : '';
                document.getElementById('lugar_entrega').value = orden.lugar_entrega || '';
                document.getElementById('condicion_pago').value = orden.condicion_pago || 'CONTADO';
                document.getElementById('observaciones_orden').value = orden.observaciones || '';
                document.getElementById('id_solicitud_origen').value = orden.id_solicitud || '';
                document.getElementById('numero_solicitud_ref').value = orden.numero_solicitud || '';

                // Cargar detalles en itemsDetalle
                itemsDetalle = (orden.detalles || []).map(det => ({
                    id_detalle: det.id_detalle_oc,
                    id_producto: det.id_producto,
                    codigo_producto: det.codigo_producto || '',
                    descripcion_producto: det.descripcion_producto,
                    cantidad: parseFloat(det.cantidad_ordenada) || 1,
                    cantidad_recibida: parseFloat(det.cantidad_recibida) || 0,
                    unidad_medida: det.unidad_medida || 'Unidad',
                    precio_unitario: parseFloat(det.precio_unitario) || 0,
                    total: parseFloat(det.total_linea) || 0,
                    id_tipo_inventario: det.id_tipo_inventario || 1,
                    estado_recepcion: det.estado_recepcion
                }));

                // Mostrar columnas de balance si no es borrador
                const colsB = document.querySelectorAll('.col-recibido, .col-pendiente');
                colsB.forEach(el => {
                    if (!esEditable) el.classList.remove('hidden');
                    else el.classList.add('hidden');
                });

                renderDetalles();

                // Cargar gastos en itemsGastos
                itemsGastos = (orden.gastos || []).map(g => ({
                    tipo_gasto: g.tipo_gasto,
                    descripcion: g.descripcion,
                    moneda: g.moneda,
                    monto: parseFloat(g.monto)
                }));
                renderGastos();

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
                const btnGuardar = document.getElementById('btnGuardarOrden');
                if (btnGuardar) {
                    if (esEditable) {
                        btnGuardar.classList.remove('hidden');
                        btnGuardar.style.display = 'flex';
                        btnGuardar.innerHTML = '<span class="material-symbols-outlined text-[20px]">save</span> Actualizar Orden';
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