<?php
// modules/compras/solicitudes.php
require_once '../../config/database.php';

if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}
$pageTitle = "Solicitudes de Compra";
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
    /* Estilos específicos para el modal Premium */
    .modal-content.xlarge {
        width: 100% !important;
        max-width: 1100px !important;
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
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
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

    .premium-input {
        @apply w-full border-slate-200 bg-slate-50 rounded-xl py-2 px-3 text-sm transition-all focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white;
    }

    .section-label {
        @apply block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5;
    }

    .premium-card {
        @apply bg-white border border-slate-100 rounded-2xl p-4 shadow-sm;
    }

    .table-premium thead th {
        @apply bg-slate-50 text-slate-500 text-[10px] font-bold uppercase tracking-widest py-3 px-4 first:rounded-l-xl last:rounded-r-xl;
    }

    .table-premium tbody td {
        @apply py-4 px-4 align-middle;
    }

    .badge-premium-total {
        @apply bg-emerald-50 text-emerald-700 border border-emerald-100 px-6 py-4 rounded-2xl flex items-center gap-3 font-bold text-lg;
    }
</style>

<div class="container-fluid font-display py-4">
    <!-- Header de Página -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Solicitudes de Compra</h1>
            <p class="text-sm text-slate-500">Gestión y control de requerimientos de adquisición</p>
        </div>
        <div class="flex space-x-2">
            <button
                class="flex items-center space-x-2 bg-primary hover:bg-blue-600 text-white px-4 py-2 transition-all active:scale-95 shadow-sm"
                onclick="abrirModalSolicitud()">
                <span class="material-symbols-outlined text-lg">add</span>
                <span class="font-semibold text-sm">Nueva Solicitud</span>
            </button>
        </div>
    </div>

    <!-- Tarjetas de Estadísticas -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 border border-slate-200 shadow-sm flex items-center space-x-4">
            <div class="bg-amber-100 p-2 text-amber-600">
                <span class="material-symbols-outlined text-2xl">pending_actions</span>
            </div>
            <div>
                <p class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Pendientes</p>
                <p class="text-xl font-bold text-slate-800" id="stat-pendientes">--</p>
            </div>
        </div>
        <div class="bg-white p-4 border border-slate-200 shadow-sm flex items-center space-x-4">
            <div class="bg-blue-100 p-2 text-blue-600">
                <span class="material-symbols-outlined text-2xl">verified</span>
            </div>
            <div>
                <p class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Aprobados</p>
                <p class="text-xl font-bold text-slate-800" id="stat-aprobados">--</p>
            </div>
        </div>
        <div class="bg-white p-4 border border-slate-200 shadow-sm flex items-center space-x-4">
            <div class="bg-red-100 p-2 text-red-600">
                <span class="material-symbols-outlined text-2xl">priority_high</span>
            </div>
            <div>
                <p class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Urgentes</p>
                <p class="text-xl font-bold text-slate-800" id="stat-urgentes">--</p>
            </div>
        </div>
    </div>

    <!-- Contenedor Principal (Tabla y Filtros) -->
    <div class="bg-white border border-slate-200 shadow-sm overflow-hidden mb-6">
        <!-- Barra de Acciones y Búsqueda -->
        <div class="p-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <span
                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                    <input id="searchTable"
                        class="pl-10 pr-4 py-2 text-sm border-slate-200 bg-slate-50 rounded-sm w-72 focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all"
                        placeholder="Buscar por Nº o Solicitante..." type="text" onkeyup="filtrarTablaLocal()" />
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <button class="p-2 border border-slate-200 hover:bg-slate-50 text-slate-600 transition-colors"
                    title="Exportar CSV">
                    <span class="material-symbols-outlined">download</span>
                </button>
                <button class="p-2 border border-slate-200 hover:bg-slate-50 text-slate-600 transition-colors"
                    title="Imprimir Lista">
                    <span class="material-symbols-outlined">print</span>
                </button>
                <button
                    class="flex items-center space-x-1 px-3 py-2 border border-slate-200 hover:bg-slate-50 text-sm text-slate-600 transition-colors"
                    onclick="toggleFiltros()">
                    <span class="material-symbols-outlined text-lg">filter_alt</span>
                    <span>Filtros</span>
                </button>
            </div>
        </div>

        <!-- Filtros Avanzados (Opcional Toggle) -->
        <div id="filtrosAvanzados"
            class="p-4 grid grid-cols-1 md:grid-cols-4 gap-4 bg-slate-50 border-b border-slate-200">
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Estado</label>
                <select id="filtroEstado" class="w-full text-xs border-slate-200 bg-white rounded-sm focus:ring-primary"
                    onchange="cargarSolicitudes()">
                    <option value="todos">Todos los estados</option>
                    <option value="PENDIENTE">⏳ Pendientes</option>
                    <option value="APROBADA">✅ Aprobadas</option>
                    <option value="RECHAZADA">❌ Rechazadas</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Prioridad</label>
                <select id="filtroPrioridad"
                    class="w-full text-xs border-slate-200 bg-white rounded-sm focus:ring-primary"
                    onchange="cargarSolicitudes()">
                    <option value="todas">Todas</option>
                    <option value="NORMAL">Normal</option>
                    <option value="ALTA">Alta</option>
                    <option value="URGENTE">🔥 Urgente</option>
                </select>
            </div>
            <div class="col-span-1 md:col-span-2 flex items-end space-x-2">
                <div class="flex-1">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Rango de Fecha</label>
                    <div class="flex items-center space-x-2">
                        <input id="fechaInicio"
                            class="w-full text-xs border-slate-200 bg-white rounded-sm focus:ring-primary" type="date"
                            onchange="cargarSolicitudes()" />
                        <span class="text-slate-400">a</span>
                        <input id="fechaFin"
                            class="w-full text-xs border-slate-200 bg-white rounded-sm focus:ring-primary" type="date"
                            onchange="cargarSolicitudes()" />
                    </div>
                </div>
                <button
                    class="bg-slate-200 hover:bg-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 h-[34px] transition-colors"
                    onclick="limpiarFiltros()">
                    Limpiar
                </button>
            </div>
        </div>

        <!-- Tabla -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-modern" id="tablaSolicitudes">
                <thead>
                    <tr class="bg-slate-100 border-b border-slate-200">
                        <th class="px-4 py-3">Nº Solicitud</th>
                        <th class="px-4 py-3">Fecha / Hora</th>
                        <th class="px-4 py-3">Solicitante</th>
                        <th class="px-4 py-3">Prioridad</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <!-- JS render -->
                </tbody>
            </table>
        </div>

        <!-- Footer Tabla / Pagination -->
        <div
            class="px-4 py-3 border-t border-slate-200 bg-slate-50 flex items-center justify-between text-xs text-slate-500">
            <div id="infoPaginacion">
                Mostrando <span class="font-bold text-slate-700">--</span> de <span
                    class="font-bold text-slate-700">--</span> solicitudes
            </div>
            <div class="flex items-center space-x-1">
                <button
                    class="px-2 py-1 border border-slate-200 bg-white hover:bg-slate-50 transition-colors disabled:opacity-50"
                    disabled>
                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                </button>
                <button class="px-3 py-1 bg-primary text-white font-bold">1</button>
                <button class="px-2 py-1 border border-slate-200 bg-white hover:bg-slate-50 transition-colors">
                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Solicitud (Rediseño Estético Premium) -->
<div class="modal" id="modalSolicitud">
    <div class="modal-content xlarge">
        <div class="premium-modal-header">
            <h3>
                <span class="material-symbols-outlined text-white">description</span>
                Solicitud de Compra
            </h3>
            <button
                class="w-10 h-10 rounded-full flex items-center justify-center hover:bg-white/10 text-white transition-all outline-none"
                onclick="$('#modalSolicitud').modal('hide')">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <div class="modal-body-scroll p-6 lg:p-8 bg-slate-50/50">
            <form id="formSolicitud">
                <input type="hidden" id="id_solicitud">

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">
                    <!-- Columna Izquierda: Datos del Documento -->
                    <div class="lg:col-span-4 space-y-4">
                        <div class="premium-card">
                            <h4 class="text-xs font-bold text-slate-800 mb-4 flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm text-primary">info</span>
                                Información General
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="section-label">Número de Documento</label>
                                    <input type="text"
                                        class="w-full bg-slate-100 border-none rounded-xl py-2.5 px-4 font-bold text-slate-600 cursor-not-allowed"
                                        id="numero_solicitud" readonly>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="section-label">Prioridad</label>
                                        <select
                                            class="w-full border-slate-200 bg-slate-50 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
                                            id="prioridad" required>
                                            <option value="NORMAL">Normal</option>
                                            <option value="ALTA">Alta</option>
                                            <option value="URGENTE">Urgente</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="section-label">Tipo Compra</label>
                                        <select
                                            class="w-full border-slate-200 bg-slate-50 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
                                            id="tipo_compra">
                                            <option value="REPOSICION">Reposición</option>
                                            <option value="PRODUCCION">Producción</option>
                                            <option value="PROYECTO">Proyecto</option>
                                            <option value="URGENTE">Urgente</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="premium-card">
                            <h4 class="text-xs font-bold text-slate-800 mb-4 flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm text-primary">analytics</span>
                                Clasificación Contable
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="section-label">Centro de Costo</label>
                                    <select
                                        class="w-full border-slate-200 bg-slate-50 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
                                        id="centro_costo">
                                        <option value="">Seleccione línea...</option>
                                        <option value="Línea Poliamida">Línea Poliamida</option>
                                        <option value="Línea Calcetería">Línea Calcetería</option>
                                        <option value="Línea Confecciones">Línea Confecciones</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="section-label">Tipo Inventario</label>
                                    <select
                                        class="w-full border-slate-200 bg-slate-50 rounded-xl py-2 px-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary font-semibold text-primary"
                                        id="id_tipo_inventario">
                                        <option value="">Seleccione tipo...</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Motivo y Detalles -->
                    <div class="lg:col-span-8 flex flex-col">
                        <div class="premium-card flex-1">
                            <h4 class="text-xs font-bold text-slate-800 mb-4 flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm text-primary">chat_bubble_outline</span>
                                Justificación de Requerimiento
                            </h4>
                            <textarea
                                class="w-full border-slate-200 bg-slate-50 rounded-2xl py-3 px-4 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary min-h-[120px]"
                                id="motivo"
                                placeholder="Escriba el motivo detallado de esta solicitud de compra..."></textarea>

                            <div class="mt-6 pt-6 border-t border-slate-100 filtros-box">
                                <div class="flex items-center justify-between mb-4">
                                    <h5 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Búsqueda
                                        Rápida de Ítems</h5>
                                    <div class="flex gap-2">
                                        <select id="filtro_categoria" onchange="cargarSubcategoriasFiltro()"
                                            class="text-xs border-slate-200 bg-white rounded-lg px-2 py-1 outline-none focus:ring-1 focus:ring-primary">
                                            <option value="">Categorías</option>
                                        </select>
                                        <select id="filtro_subcategoria" onchange="cargarProductosFiltrados()"
                                            class="text-xs border-slate-200 bg-white rounded-lg px-2 py-1 outline-none focus:ring-1 focus:ring-primary">
                                            <option value="">Subcategorías</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Productos -->
                <div class="premium-card bg-white p-2">
                    <div class="overflow-hidden rounded-xl border border-slate-100">
                        <table class="w-full text-left border-collapse table-premium">
                            <thead>
                                <tr>
                                    <th style="width: 40%;">Producto / Descripción</th>
                                    <th style="width: 12%;" class="text-center">Unidad</th>
                                    <th style="width: 18%;" class="text-center">Stock Actual</th>
                                    <th style="width: 20%;" class="text-center">Cantidad</th>
                                    <th style="width: 10%;" class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="bodyDetalles" class="divide-y divide-slate-50">
                                <!-- JS render -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Resumen y Acción Principal -->
                <div class="flex flex-col md:flex-row items-center justify-between gap-6 mt-8">
                    <button type="button"
                        class="flex items-center gap-2 bg-slate-800 hover:bg-slate-900 text-white px-8 py-4 rounded-2xl font-bold transition-all shadow-lg shadow-slate-200"
                        id="btnAgregarFila" onclick="agregarFila()">
                        <span class="material-symbols-outlined">add_circle</span>
                        Añadir otra línea
                    </button>

                    <div class="badge-premium-total">
                        <div
                            class="w-12 h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-600">
                            <span class="material-symbols-outlined">format_list_numbered</span>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-widest opacity-60">Total de requerimientos</p>
                            <span class="text-2xl" id="totalItems">0</span> items
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="px-8 py-6 bg-white border-t border-slate-100 flex justify-end gap-3">
            <button type="button"
                class="px-8 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition-all"
                onclick="$('#modalSolicitud').modal('hide')">
                Cancelar
            </button>
            <button type="button"
                class="px-10 py-3 rounded-xl bg-primary text-white font-bold hover:bg-indigo-700 transition-all shadow-lg shadow-primary/20 flex items-center gap-2"
                id="btnGuardarSolicitud" onclick="guardarSolicitud()">
                <span class="material-symbols-outlined text-[20px]">save</span>
                Guardar Solicitud
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let itemsDetalle = [];
    let productosDisponibles = []; // Almacena todos los productos del tipo seleccionado
    let productosFiltrados = []; // Almacena productos filtrados por categoría/subcategoría
    let currentIsReadOnly = false;

    document.addEventListener('DOMContentLoaded', function () {
        cargarTiposInventario();
        cargarSolicitudes();
    });

    function cargarTiposInventario() {
        fetch('../../api/centro_inventarios.php?action=tipos')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('id_tipo_inventario');

                    // Filtrar tipos de inventario excluyendo "Productos en proceso" y "Productos terminados"
                    const tiposFiltrados = data.tipos.filter(tipo => {
                        const nombreLower = tipo.nombre.toLowerCase();
                        return !nombreLower.includes('productos en proceso') &&
                            !nombreLower.includes('productos terminados') &&
                            !nombreLower.includes('producto en proceso') &&
                            !nombreLower.includes('producto terminado');
                    });

                    // Limpiar opciones existentes excepto la primera
                    select.innerHTML = '<option value="">Seleccione tipo...</option>';

                    // Agregar las opciones filtradas
                    tiposFiltrados.forEach(tipo => {
                        const option = document.createElement('option');
                        option.value = tipo.id_tipo_inventario;
                        option.textContent = tipo.nombre;
                        select.appendChild(option);
                    });

                    // Agregar listener para cargar categorías y productos cuando cambie el tipo
                    select.addEventListener('change', function () {
                        const tipoId = this.value;
                        if (tipoId) {
                            cargarCategoriasPorTipo(tipoId);
                            cargarProductosPorTipo(tipoId);
                        } else {
                            // Limpiar filtros
                            document.getElementById('filtro_categoria').innerHTML = '<option value="">Todas las categorías</option>';
                            document.getElementById('filtro_subcategoria').innerHTML = '<option value="">Todas las subcategorías</option>';
                            productosDisponibles = [];
                            productosFiltrados = [];
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error al cargar tipos de inventario:', error);
            });
    }

    function cargarCategoriasPorTipo(tipoId) {
        fetch(`../../api/centro_inventarios.php?action=categorias&tipo_id=${tipoId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('filtro_categoria');
                    select.innerHTML = '<option value="">Todas las categorías</option>';

                    data.categorias.forEach(cat => {
                        const option = document.createElement('option');
                        option.value = cat.id_categoria;
                        option.textContent = cat.nombre;
                        select.appendChild(option);
                    });

                    // Resetear subcategorías
                    document.getElementById('filtro_subcategoria').innerHTML = '<option value="">Todas las subcategorías</option>';
                }
            })
            .catch(error => console.error('Error al cargar categorías:', error));
    }

    function cargarSubcategoriasFiltro() {
        const categoriaId = document.getElementById('filtro_categoria').value;
        const selectSubcat = document.getElementById('filtro_subcategoria');

        if (!categoriaId) {
            selectSubcat.innerHTML = '<option value="">Todas las subcategorías</option>';
            cargarProductosFiltrados();
            return;
        }

        fetch(`../../api/centro_inventarios.php?action=subcategorias&categoria_id=${categoriaId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    selectSubcat.innerHTML = '<option value="">Todas las subcategorías</option>';

                    data.subcategorias.forEach(sub => {
                        const option = document.createElement('option');
                        option.value = sub.id_subcategoria;
                        option.textContent = sub.nombre;
                        selectSubcat.appendChild(option);
                    });

                    cargarProductosFiltrados();
                }
            })
            .catch(error => console.error('Error al cargar subcategorías:', error));
    }

    function cargarProductosPorTipo(tipoId) {
        fetch(`../../api/centro_inventarios.php?action=list&tipo_id=${tipoId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    productosDisponibles = data.inventarios || [];
                    productosFiltrados = [...productosDisponibles];
                }
            })
            .catch(error => console.error('Error al cargar productos:', error));
    }

    function cargarProductosFiltrados() {
        const categoriaId = document.getElementById('filtro_categoria').value;
        const subcategoriaId = document.getElementById('filtro_subcategoria').value;

        // Filtrar productos según categoría y subcategoría
        productosFiltrados = productosDisponibles.filter(prod => {
            let cumpleFiltro = true;

            if (categoriaId && prod.id_categoria != categoriaId) {
                cumpleFiltro = false;
            }

            if (subcategoriaId && prod.id_subcategoria != subcategoriaId) {
                cumpleFiltro = false;
            }

            return cumpleFiltro;
        });
    }

    function limpiarFiltros() {
        document.getElementById('filtroEstado').value = 'todos';
        document.getElementById('filtroPrioridad').value = 'todas';
        document.getElementById('fechaInicio').value = '';
        document.getElementById('fechaFin').value = '';
        cargarSolicitudes();
    }

    function cargarSolicitudes() {
        const estado = document.getElementById('filtroEstado').value;
        const prioridad = document.getElementById('filtroPrioridad').value;
        const inicio = document.getElementById('fechaInicio').value;
        const fin = document.getElementById('fechaFin').value;

        let url = `../../api/compras/solicitudes.php?action=list&estado=${estado}&prioridad=${prioridad}`;
        if (inicio && fin) url += `&fecha_inicio=${inicio}&fecha_fin=${fin}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                const tbody = document.querySelector('#tablaSolicitudes tbody');
                tbody.innerHTML = '';

                // Actualizar Estadísticas
                actualizarEstadisticas(data.solicitudes);

                if (data.solicitudes.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-slate-400 text-sm">No se encontraron solicitudes que coincidan con los filtros</td></tr>';
                    document.getElementById('infoPaginacion').innerHTML = 'Mostrando 0 de 0 solicitudes';
                    return;
                }

                data.solicitudes.forEach(sol => {
                    // Configuración de Estados
                    let estadoBadge = '';
                    switch (sol.estado) {
                        case 'PENDIENTE':
                            estadoBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700 uppercase">Pendiente</span>`;
                            break;
                        case 'APROBADA':
                            estadoBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700 uppercase">Aprobado</span>`;
                            break;
                        case 'RECHAZADA':
                            estadoBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700 uppercase">Rechazado</span>`;
                            break;
                        default:
                            estadoBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 uppercase">${sol.estado}</span>`;
                    }

                    // Configuración de Prioridad
                    let prioBadge = '';
                    switch (sol.prioridad) {
                        case 'URGENTE':
                            prioBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700 uppercase">Urgente</span>`;
                            break;
                        case 'ALTA':
                            prioBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-orange-100 text-orange-700 uppercase">Alta</span>`;
                            break;
                        default:
                            prioBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700 uppercase">Normal</span>`;
                    }

                    // Separar Fecha y Hora
                    const [fecha, hora] = sol.fecha_solicitud.split(' ');

                    const tr = document.createElement('tr');
                    tr.className = "hover:bg-slate-50 transition-colors";
                    tr.innerHTML = `
                        <td class="px-4 py-3 text-xs font-semibold text-primary">${sol.numero_solicitud}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            ${fecha} <span class="text-[10px] block opacity-70">${hora || ''}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-700 font-medium">${sol.solicitante_nombre}</td>
                        <td class="px-4 py-3 text-xs">${prioBadge}</td>
                        <td class="px-4 py-3 text-xs">${estadoBadge}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end space-x-1">
                                <button onclick="editarSolicitud(${sol.id_solicitud})" class="p-1 hover:bg-slate-100 text-slate-500 rounded transition-colors" title="Ver Detalle">
                                    <span class="material-symbols-outlined text-lg">visibility</span>
                                </button>
                                <button onclick="imprimirSolicitud(${sol.id_solicitud})" class="p-1 hover:bg-slate-100 text-slate-500 rounded transition-colors" title="Imprimir">
                                    <span class="material-symbols-outlined text-lg">print</span>
                                </button>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });

                document.getElementById('infoPaginacion').innerHTML = `Mostrando <span class="font-bold text-slate-700">1-${data.solicitudes.length}</span> de <span class="font-bold text-slate-700">${data.solicitudes.length}</span> solicitudes`;
            });
    }

    function actualizarEstadisticas(solicitudes) {
        const pendientes = solicitudes.filter(s => s.estado === 'PENDIENTE').length;
        const aprobadas = solicitudes.filter(s => s.estado === 'APROBADA').length;
        const urgentes = solicitudes.filter(s => s.prioridad === 'URGENTE' && s.estado === 'PENDIENTE').length;

        document.getElementById('stat-pendientes').textContent = pendientes;
        document.getElementById('stat-aprobados').textContent = aprobadas;
        document.getElementById('stat-urgentes').textContent = urgentes;
    }

    function toggleFiltros() {
        const div = document.getElementById('filtrosAvanzados');
        div.classList.toggle('hidden');
    }

    function filtrarTablaLocal() {
        const busqueda = document.getElementById('searchTable').value.toLowerCase();
        const filas = document.querySelectorAll('#tablaSolicitudes tbody tr');

        filas.forEach(fila => {
            const texto = fila.textContent.toLowerCase();
            fila.style.display = texto.includes(busqueda) ? '' : 'none';
        });
    }

    function abrirModalSolicitud() {
        currentIsReadOnly = false;
        toggleFormReadOnly(false);
        document.getElementById('formSolicitud').reset();
        document.getElementById('id_solicitud').value = '';
        document.getElementById('btnGuardarSolicitud').style.display = 'block';
        itemsDetalle = [];
        renderDetalles();

        // Obtener siguiente número
        fetch('../../api/compras/solicitudes.php?action=siguiente_numero')
            .then(res => res.json())
            .then(data => {
                document.getElementById('numero_solicitud').value = data.numero;
            });

        // Agregar primera fila vacía
        agregarFila();

        $('#modalSolicitud').modal('show');
    }

    function agregarFila() {
        itemsDetalle.push({
            id_producto: null,
            codigo_producto: '',
            descripcion_producto: '',
            cantidad_solicitada: 1,
            unidad_medida: 'Unidad',
            stock_actual: 0
        });
        renderDetalles();
    }

    function renderDetalles() {
        const tbody = document.getElementById('bodyDetalles');
        tbody.innerHTML = '';

        itemsDetalle.forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.className = "group hover:bg-slate-50 transition-all";

            // Generar opciones de productos
            let productosOptions = '<option value="">-- Seleccionar producto --</option>';
            productosOptions += '<option value="CUSTOM">✏️ Descripción personalizada</option>';

            productosFiltrados.forEach(prod => {
                const selected = item.id_producto == prod.id_inventario ? 'selected' : '';
                productosOptions += `<option value="${prod.id_inventario}" ${selected}>${prod.codigo} - ${prod.nombre}</option>`;
            });

            // Determinar si mostrar selector o input de texto
            const esPersonalizado = item.id_producto === 'CUSTOM' || (!item.id_producto && item.descripcion_producto);

            tr.innerHTML = `
            <td>
                <div class="flex items-center gap-3">
                    ${esPersonalizado ?
                    `<div class="flex-1 relative group/input">
                            <input type="text" value="${item.descripcion_producto}" placeholder="Escriba la descripción..." 
                             class="w-full bg-slate-50 border-slate-200 rounded-xl py-2 px-4 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all"
                             onchange="actualizarItem(${index}, 'descripcion_producto', this.value)"
                             ${currentIsReadOnly ? 'disabled' : ''}>
                             <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg bg-slate-200 text-slate-500 hover:bg-slate-300 transition-all flex items-center justify-center ${currentIsReadOnly ? 'hidden' : ''}" 
                             onclick="cambiarASelector(${index})" title="Cambiar a selector">
                                <span class="material-symbols-outlined text-sm">list</span>
                             </button>
                        </div>`
                    :
                    `<select onchange="seleccionarProducto(${index}, this.value)" 
                         class="w-full bg-slate-50 border-slate-200 rounded-xl py-2 px-4 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all font-medium text-slate-700"
                         ${currentIsReadOnly ? 'disabled' : ''}>
                            ${productosOptions}
                        </select>`
                }
                </div>
            </td>
            <td class="text-center font-mono text-xs font-bold text-slate-400">
                ${item.unidad_medida}
            </td>
            <td class="text-center">
                <div class="${(item.stock_actual <= 0) ? 'bg-rose-50 text-rose-600' : 'bg-blue-50 text-blue-600'} px-3 py-1.5 rounded-xl inline-block font-mono font-bold text-xs ring-1 ring-inset ${(item.stock_actual <= 0) ? 'ring-rose-200' : 'ring-blue-100'}">
                    ${parseFloat(item.stock_actual || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                </div>
            </td>
            <td>
                <div class="flex justify-center">
                    <input type="number" step="0.01" value="${item.cantidad_solicitada}" 
                    class="w-24 bg-slate-50 border-slate-200 rounded-xl py-2 px-3 text-sm text-center font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                    onchange="actualizarItem(${index}, 'cantidad_solicitada', this.value)"
                    ${currentIsReadOnly ? 'disabled' : ''}>
                </div>
            </td>
            <td>
                <div class="flex justify-center">
                    ${currentIsReadOnly ?
                    `<span class="w-10 h-10 flex items-center justify-center text-slate-300">
                            <span class="material-symbols-outlined">lock</span>
                        </span>` :
                    `<button type="button" class="w-10 h-10 rounded-xl bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white transition-all flex items-center justify-center shadow-sm" 
                         onclick="eliminarFila(${index})" title="Eliminar línea">
                            <span class="material-symbols-outlined text-lg">delete</span>
                        </button>`
                }
                </div>
            </td>
        `;
            tbody.appendChild(tr);
        });

        // Actualizar total de ítems
        document.getElementById('totalItems').textContent = itemsDetalle.length;
    }

    function seleccionarProducto(index, productoId) {
        if (productoId === 'CUSTOM') {
            // Cambiar a modo personalizado
            itemsDetalle[index].id_producto = 'CUSTOM';
            itemsDetalle[index].descripcion_producto = '';
            itemsDetalle[index].unidad_medida = 'Unidad';
            itemsDetalle[index].stock_actual = 0;
        } else if (productoId) {
            // Buscar el producto seleccionado
            const producto = productosFiltrados.find(p => p.id_inventario == productoId);
            if (producto) {
                itemsDetalle[index].id_producto = producto.id_inventario;
                itemsDetalle[index].codigo_producto = producto.codigo;
                itemsDetalle[index].descripcion_producto = producto.nombre;
                itemsDetalle[index].unidad_medida = producto.unidad || 'Unidad';
                itemsDetalle[index].stock_actual = producto.stock_actual || 0;
            }
        } else {
            // Limpiar selección
            itemsDetalle[index].id_producto = null;
            itemsDetalle[index].descripcion_producto = '';
            itemsDetalle[index].unidad_medida = 'Unidad';
            itemsDetalle[index].stock_actual = 0;
        }
        renderDetalles();
    }

    function cambiarASelector(index) {
        // Cambiar de modo personalizado a selector
        itemsDetalle[index].id_producto = null;
        itemsDetalle[index].descripcion_producto = '';
        renderDetalles();
    }

    function editarSolicitud(id) {
        fetch(`../../api/compras/solicitudes.php?action=get&id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const sol = data.solicitud;

                    // Llenar cabecera
                    document.getElementById('id_solicitud').value = sol.id_solicitud;
                    document.getElementById('numero_solicitud').value = sol.numero_solicitud;
                    document.getElementById('prioridad').value = sol.prioridad;
                    document.getElementById('tipo_compra').value = sol.tipo_compra;
                    document.getElementById('motivo').value = sol.motivo;
                    document.getElementById('centro_costo').value = sol.centro_costo;
                    document.getElementById('id_tipo_inventario').value = sol.id_tipo_inventario;

                    // Configurar Modo Solo Lectura
                    currentIsReadOnly = (sol.estado !== 'PENDIENTE');
                    toggleFormReadOnly(currentIsReadOnly);

                    // Cargar categorías y productos del tipo seleccionado antes de renderizar detalles
                    if (sol.id_tipo_inventario) {
                        // Deshabilitar botón de guardar si no es PENDIENTE
                        if (sol.estado !== 'PENDIENTE') {
                            document.getElementById('btnGuardarSolicitud').style.display = 'none';
                        } else {
                            document.getElementById('btnGuardarSolicitud').style.display = 'block';
                        }

                        Promise.all([
                            fetch(`../../api/centro_inventarios.php?action=categorias&tipo_id=${sol.id_tipo_inventario}`).then(r => r.json()),
                            fetch(`../../api/centro_inventarios.php?action=list&tipo_id=${sol.id_tipo_inventario}`).then(r => r.json())
                        ]).then(([catData, prodData]) => {
                            // Cargar filtros de categorías
                            const selectCat = document.getElementById('filtro_categoria');
                            selectCat.innerHTML = '<option value="">Todas las categorías</option>';
                            if (catData.success) {
                                catData.categorias.forEach(cat => {
                                    const option = document.createElement('option');
                                    option.value = cat.id_categoria;
                                    option.textContent = cat.nombre;
                                    selectCat.appendChild(option);
                                });
                            }

                            // Cargar lista de productos disponibles
                            productosDisponibles = prodData.success ? prodData.inventarios : [];
                            productosFiltrados = [...productosDisponibles];

                            // Mapear detalles
                            itemsDetalle = sol.detalles.map(d => ({
                                id_producto: d.id_producto,
                                codigo_producto: d.codigo_producto,
                                descripcion_producto: d.descripcion_producto,
                                cantidad_solicitada: parseFloat(d.cantidad_solicitada),
                                unidad_medida: d.unidad_medida,
                                stock_actual: d.stock_actual || 0
                            }));

                            renderDetalles();
                            $('#modalSolicitud').modal('show');
                        });
                    } else {
                        // Si no tiene tipo de inventario (raro pero posible)
                        if (sol.estado !== 'PENDIENTE') {
                            document.getElementById('btnGuardarSolicitud').style.display = 'none';
                        } else {
                            document.getElementById('btnGuardarSolicitud').style.display = 'block';
                        }

                        itemsDetalle = sol.detalles.map(d => ({
                            id_producto: d.id_producto,
                            codigo_producto: d.codigo_producto,
                            descripcion_producto: d.descripcion_producto,
                            cantidad_solicitada: parseFloat(d.cantidad_solicitada),
                            unidad_medida: d.unidad_medida
                        }));
                        renderDetalles();
                        $('#modalSolicitud').modal('show');
                    }
                } else {
                    Swal.fire('Error', 'No se pudo cargar la solicitud: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Error de conexión al cargar la solicitud', 'error');
            });
    }

    function toggleFormReadOnly(isReadOnly) {
        // Campos de cabecera
        const fields = ['prioridad', 'tipo_compra', 'motivo', 'centro_costo', 'id_tipo_inventario'];
        fields.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = isReadOnly;
        });

        // Controles de edición de líneas
        const btnAgregar = document.getElementById('btnAgregarFila');
        if (btnAgregar) btnAgregar.style.display = isReadOnly ? 'none' : 'inline-block';

        const filtrosBox = document.querySelector('.filtros-box');
        if (filtrosBox) filtrosBox.style.display = isReadOnly ? 'none' : 'block';
    }

    function actualizarItem(index, field, value) {
        itemsDetalle[index][field] = value;
        // No llamamos a renderDetalles() aquí para no perder el foco si el usuario está escribiendo,
        // a menos que sea un selector que cambie otros campos.
    }

    function eliminarFila(index) {
        if (itemsDetalle.length > 1) {
            itemsDetalle.splice(index, 1);
            renderDetalles();
        } else {
            // Limpiar si es la última
            itemsDetalle[0].descripcion_producto = '';
            itemsDetalle[0].cantidad_solicitada = 1;
            renderDetalles();
        }
    }

    function guardarSolicitud() {
        const id_sol = document.getElementById('id_solicitud').value;
        const data = {
            action: id_sol ? 'update' : 'create',
            id_solicitud: id_sol,
            numero_solicitud: document.getElementById('numero_solicitud').value,
            prioridad: document.getElementById('prioridad').value,
            motivo: document.getElementById('motivo').value,
            tipo_compra: document.getElementById('tipo_compra').value,
            id_tipo_inventario: document.getElementById('id_tipo_inventario').value,
            centro_costo: document.getElementById('centro_costo').value,
            id_usuario_solicitante: <?php echo $_SESSION['user_id']; ?>,
            area_solicitante: 'Producción',
            monto_estimado: 0, // Sin precios en solicitud
            detalles: itemsDetalle.filter(i => i.descripcion_producto.trim() !== '').map(item => ({
                ...item,
                id_tipo_inventario: document.getElementById('id_tipo_inventario').value,
                stock_solicitud: item.stock_actual,
                precio_estimado: 0,
                subtotal_estimado: 0
            }))
        };

        if (data.detalles.length === 0) {
            Swal.fire('Error', 'Debe agregar al menos un producto válido', 'error');
            return;
        }

        fetch('../../api/compras/solicitudes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    $('#modalSolicitud').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: id_sol ? 'Solicitud Actualizada' : 'Solicitud Creada',
                        text: res.message || 'La solicitud ha sido registrada correctamente.',
                        timer: 2000
                    });
                    cargarSolicitudes();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
    }

    function imprimirSolicitud(id) {
        window.open(`solicitud_pdf.php?id=${id}`, '_blank');
    }
</script>

<?php include '../../includes/footer.php'; ?>