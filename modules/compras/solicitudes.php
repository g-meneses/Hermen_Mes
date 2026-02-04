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

<style>
    :root {
        --tipo-color: #4e73df;
        /* Color principal para Compras */
    }

    /* Estilos copiados y adaptados de materias_primas.php */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
        align-items: center;
        justify-content: center;
    }

    .modal.show {
        display: flex;
    }

    .modal-content {
        background-color: #fefefe;
        margin: auto;
        padding: 0;
        border: 1px solid #888;
        width: 95%;
        max-width: 900px;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        display: flex;
        flex-direction: column;
        max-height: 90vh;
    }

    .modal-content.xlarge {
        max-width: 1200px;
        /* Más ancho como pidió el usuario */
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 25px;
        background: linear-gradient(135deg, #1a237e, #4e73df);
        /* Azul profesional */
        color: white;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-close {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        transition: background 0.2s;
    }

    .modal-close:hover {
        background: #dc3545;
    }

    .modal-body {
        padding: 25px;
        overflow-y: auto;
        background: #fff;
    }

    .modal-footer {
        padding: 15px 25px;
        border-top: 1px solid #e9ecef;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        background: #f8f9fa;
        border-radius: 0 0 16px 16px;
    }

    /* Form Styles */
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-group label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #5a5c69;
    }

    .form-control {
        padding: 10px 12px;
        border: 1px solid #d1d3e2;
        border-radius: 8px;
        font-size: 0.9rem;
        color: #6e707e;
    }

    .form-control:focus {
        border-color: var(--tipo-color);
        outline: none;
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
    }

    /* Tabla de Líneas */
    .tabla-container {
        border: 1px solid #e3e6f0;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 15px;
    }

    .tabla-lineas {
        width: 100%;
        border-collapse: collapse;
    }

    .tabla-lineas th {
        background: #2c3e50;
        color: white;
        padding: 12px;
        font-size: 0.8rem;
        text-transform: uppercase;
        font-weight: 700;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .tabla-lineas td {
        padding: 8px;
        border-bottom: 1px solid #e3e6f0;
        vertical-align: middle;
    }

    .tabla-lineas input,
    .tabla-lineas select {
        width: 100%;
        padding: 6px;
        border: 1px solid #d1d3e2;
        border-radius: 4px;
        font-size: 0.85rem;
    }

    .btn-action-sm {
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        color: white;
    }

    /* Totales Box */
    .totales-box {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        display: flex;
        justify-content: flex-end;
    }

    .totales-grid {
        display: grid;
        gap: 10px;
        text-align: right;
        min-width: 250px;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 1rem;
    }

    .total-final {
        background: #28a745;
        color: white;
        padding: 10px 15px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 1.2rem;
        margin-top: 10px;
    }

    /* Filtros productos box */
    .filtros-box {
        background: #f8f9fc;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #e3e6f0;
        margin-bottom: 20px;
    }

    .filtros-header {
        font-size: 0.9rem;
        font-weight: 700;
        color: #4e73df;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800">Solicitudes de Compra</h1>
        <button class="btn btn-primary shadow-sm" onclick="abrirModalSolicitud()">
            <i class="fas fa-plus"></i> Nueva Solicitud
        </button>
    </div>

    <!-- Filtros Principales -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filtros de Búsqueda</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label>Estado</label>
                    <select id="filtroEstado" class="form-control" onchange="cargarSolicitudes()">
                        <option value="todos">Todos</option>
                        <option value="PENDIENTE">Pendientes</option>
                        <option value="APROBADA">Aprobadas</option>
                        <option value="RECHAZADA">Rechazadas</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>Prioridad</label>
                    <select id="filtroPrioridad" class="form-control" onchange="cargarSolicitudes()">
                        <option value="todas">Todas</option>
                        <option value="NORMAL">Normal</option>
                        <option value="ALTA">Alta</option>
                        <option value="URGENTE">Urgente</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label>Rango de Fechas</label>
                    <div class="input-group">
                        <input type="date" id="fechaInicio" class="form-control" onchange="cargarSolicitudes()">
                        <input type="date" id="fechaFin" class="form-control" onchange="cargarSolicitudes()">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Principal -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="tablaSolicitudes" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th>Nº Solicitud</th>
                            <th>Fecha</th>
                            <th>Solicitante</th>
                            <th>Prioridad</th>
                            <th>Monto Est.</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Solicitud (Rediseñado) -->
<div class="modal" id="modalSolicitud">
    <div class="modal-content xlarge"> <!-- Clase xlarge aplicada -->
        <div class="modal-header">
            <h3><i class="fas fa-file-invoice"></i> Nueva Solicitud de Compra</h3>
            <button class="modal-close" onclick="$('#modalSolicitud').modal('hide')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="formSolicitud">
                <input type="hidden" id="id_solicitud">

                <!-- Cabecera del Documento -->
                <div class="form-row">
                    <div class="form-group col-md-2">
                        <label>Número</label>
                        <input type="text" class="form-control" id="numero_solicitud" readonly
                            style="background: #eaecf4; font-weight: bold;">
                    </div>
                    <div class="form-group col-md-2">
                        <label>Prioridad</label>
                        <select class="form-control" id="prioridad" required>
                            <option value="NORMAL">Normal</option>
                            <option value="ALTA">Alta</option>
                            <option value="URGENTE">Urgente</option>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label>Tipo Compra</label>
                        <select class="form-control" id="tipo_compra">
                            <option value="REPOSICION">Reposición de Stock</option>
                            <option value="PRODUCCION">Producción Específica</option>
                            <option value="PROYECTO">Proyecto</option>
                            <option value="URGENTE">Emergencia</option>
                        </select>
                    </div>
                    <div class="form-group col-md-5">
                        <label>Motivo / Justificación</label>
                        <input type="text" class="form-control" id="motivo" required
                            placeholder="¿Por qué se requiere esta compra?">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Centro de Costo</label>
                        <input type="text" class="form-control" id="centro_costo"
                            placeholder="Ej. Producción - Línea 1">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Tipo Inventario Predeterminado</label>
                        <select class="form-control" id="id_tipo_inventario">
                            <option value="1">Materia Prima</option>
                            <option value="2">Insumos</option>
                            <option value="3">Repuestos</option>
                        </select>
                    </div>
                </div>

                <hr class="my-4">

                <!-- Sección Detalles -->
                <div class="filtros-box">
                    <div class="filtros-header">
                        <i class="fas fa-filter"></i> Detalle de Requerimientos
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <p class="m-0 text-muted small">Agregue los ítems que necesita comprar. Puede especificar un
                            precio estimado.</p>
                        <button type="button" class="btn btn-primary" onclick="agregarFila()">
                            <i class="fas fa-plus"></i> Agregar Línea
                        </button>
                    </div>
                </div>

                <div class="tabla-container">
                    <table class="tabla-lineas">
                        <thead>
                            <tr>
                                <th style="width: 35%;">Producto / Descripción</th>
                                <th style="width: 15%;">Unidad</th>
                                <th style="width: 15%;">Cantidad</th>
                                <th style="width: 15%;">Precio Est. (BOB)</th>
                                <th style="width: 15%;">Subtotal</th>
                                <th style="width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="bodyDetalles">
                            <!-- JS render -->
                        </tbody>
                    </table>
                </div>

                <!-- Totales -->
                <div class="totales-box">
                    <div class="totales-grid">
                        <div class="total-row total-final">
                            <span>TOTAL ESTIMADO:</span>
                            <span id="totalEstimado">Bs. 0.00</span>
                        </div>
                    </div>
                </div>

            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary"
                onclick="$('#modalSolicitud').modal('hide')">Cancelar</button>
            <button type="button" class="btn btn-success" onclick="guardarSolicitud()">
                <i class="fas fa-save"></i> Guardar Solicitud
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let itemsDetalle = [];

    document.addEventListener('DOMContentLoaded', function () {
        cargarSolicitudes();
    });

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

                if (data.solicitudes.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron solicitudes</td></tr>';
                    return;
                }

                data.solicitudes.forEach(sol => {
                    let badgeClass = 'secondary';
                    if (sol.estado === 'PENDIENTE') badgeClass = 'warning';
                    if (sol.estado === 'APROBADA') badgeClass = 'success';
                    if (sol.estado === 'RECHAZADA') badgeClass = 'danger';

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                    <td class="font-weight-bold text-primary">${sol.numero_solicitud}</td>
                    <td>${sol.fecha_solicitud}</td>
                    <td>${sol.solicitante_nombre}</td>
                    <td><span class="badge badge-${sol.prioridad === 'URGENTE' ? 'danger' : (sol.prioridad === 'ALTA' ? 'warning' : 'info')}">${sol.prioridad}</span></td>
                    <td class="text-right">Bs ${parseFloat(sol.monto_estimado).toFixed(2)}</td>
                    <td><span class="badge badge-${badgeClass}">${sol.estado}</span></td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="editarSolicitud(${sol.id_solicitud})" title="Ver Detalle"><i class="fas fa-eye"></i></button>
                        ${sol.estado === 'PENDIENTE' ?
                            `<button class="btn btn-sm btn-success" onclick="aprobarSolicitud(${sol.id_solicitud})" title="Aprobar"><i class="fas fa-check"></i></button>` : ''}
                    </td>
                `;
                    tbody.appendChild(tr);
                });
            });
    }

    function abrirModalSolicitud() {
        document.getElementById('formSolicitud').reset();
        document.getElementById('id_solicitud').value = '';
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
            descripcion_producto: '',
            cantidad_solicitada: 1,
            unidad_medida: 'Unidad',
            precio_estimado: 0,
            subtotal_estimado: 0
        });
        renderDetalles();
    }

    function renderDetalles() {
        const tbody = document.getElementById('bodyDetalles');
        tbody.innerHTML = '';
        let total = 0;

        itemsDetalle.forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
            <td><input type="text" value="${item.descripcion_producto}" placeholder="Descripción el ítem..." onchange="actualizarItem(${index}, 'descripcion_producto', this.value)"></td>
            <td>
                <select onchange="actualizarItem(${index}, 'unidad_medida', this.value)">
                    <option value="Unidad" ${item.unidad_medida === 'Unidad' ? 'selected' : ''}>Unidad</option>
                    <option value="Kg" ${item.unidad_medida === 'Kg' ? 'selected' : ''}>Kg</option>
                    <option value="Lt" ${item.unidad_medida === 'Lt' ? 'selected' : ''}>Lt</option>
                    <option value="Paquete" ${item.unidad_medida === 'Paquete' ? 'selected' : ''}>Paquete</option>
                </select>
            </td>
            <td><input type="number" step="0.01" value="${item.cantidad_solicitada}" onchange="actualizarItem(${index}, 'cantidad_solicitada', this.value)" class="text-center"></td>
            <td><input type="number" step="0.01" value="${item.precio_estimado}" onchange="actualizarItem(${index}, 'precio_estimado', this.value)" class="text-right"></td>
            <td class="text-right font-weight-bold">Bs ${parseFloat(item.subtotal_estimado).toFixed(2)}</td>
            <td class="text-center">
                <button type="button" class="btn-action-sm bg-danger" onclick="eliminarFila(${index})"><i class="fas fa-trash"></i></button>
            </td>
        `;
            tbody.appendChild(tr);
            total += parseFloat(item.subtotal_estimado);
        });

        document.getElementById('totalEstimado').textContent = 'Bs. ' + total.toFixed(2);
    }

    function actualizarItem(index, field, value) {
        itemsDetalle[index][field] = value;
        if (field === 'cantidad_solicitada' || field === 'precio_estimado') {
            const cant = parseFloat(itemsDetalle[index].cantidad_solicitada) || 0;
            const precio = parseFloat(itemsDetalle[index].precio_estimado) || 0;
            itemsDetalle[index].subtotal_estimado = cant * precio;
        }
        renderDetalles();
    }

    function eliminarFila(index) {
        if (itemsDetalle.length > 1) {
            itemsDetalle.splice(index, 1);
            renderDetalles();
        } else {
            // Limpiar si es la última
            itemsDetalle[0].descripcion_producto = '';
            itemsDetalle[0].cantidad_solicitada = 1;
            itemsDetalle[0].precio_estimado = 0;
            itemsDetalle[0].subtotal_estimado = 0;
            renderDetalles();
        }
    }

    function guardarSolicitud() {
        const data = {
            action: 'create',
            numero_solicitud: document.getElementById('numero_solicitud').value,
            prioridad: document.getElementById('prioridad').value,
            motivo: document.getElementById('motivo').value,
            tipo_compra: document.getElementById('tipo_compra').value,
            id_tipo_inventario: document.getElementById('id_tipo_inventario').value,
            centro_costo: document.getElementById('centro_costo').value,
            id_usuario_solicitante: <?php echo $_SESSION['user_id']; ?>,
            area_solicitante: 'Producción',
            monto_estimado: parseFloat(document.getElementById('totalEstimado').textContent.replace('Bs. ', '')),
            detalles: itemsDetalle.filter(i => i.descripcion_producto.trim() !== '').map(item => ({
                ...item,
                id_tipo_inventario: document.getElementById('id_tipo_inventario').value
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
                        title: 'Solicitud Creada',
                        text: 'La solicitud ha sido registrada correctamente.',
                        timer: 2000
                    });
                    cargarSolicitudes();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
    }

    function aprobarSolicitud(id) {
        Swal.fire({
            title: '¿Aprobar Solicitud?',
            text: "Esta acción habilitará la creación de órden de compra.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, aprobar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('../../api/compras/solicitudes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_status',
                        id_solicitud: id,
                        estado: 'APROBADA',
                        motivo: 'Aprobación directa'
                    })
                })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            Swal.fire('Aprobada', 'La solicitud ha sido aprobada', 'success');
                            cargarSolicitudes();
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    });
            }
        });
    }
</script>

<?php include '../../includes/footer.php'; ?>