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

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800">Recepciones de Compra</h1>
        <button class="btn btn-primary" onclick="abrirModalRecepcion()">
            <i class="fas fa-truck-loading"></i> Nueva Recepción
        </button>
    </div>

    <!-- Tabla -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="tablaRecepciones" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nº Recepción</th>
                            <th>Fecha</th>
                            <th>Orden Ref.</th>
                            <th>Proveedor</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data loaded via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Recepción -->
<div class="modal fade" id="modalRecepcion" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registrar Recepción de Compra</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formRecepcion">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Buscar Orden de Compra</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="orden_busqueda"
                                        placeholder="Nº Orden (OC-...)">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" onclick="buscarOrden()">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Proveedor</label>
                                <input type="text" class="form-control" id="nombre_proveedor" readonly>
                                <input type="hidden" id="id_proveedor">
                                <input type="hidden" id="id_orden_compra">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Factura / Remisión</label>
                                <input type="text" class="form-control" id="numero_factura">
                            </div>
                        </div>
                    </div>

                    <div id="panelDetalles" style="display:none;">
                        <hr>
                        <h6 class="font-weight-bold">Detalles de la Orden</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cant. Ordenada</th>
                                        <th>Cant. Pendiente</th>
                                        <th width="15%">A Recibir</th>
                                        <th width="15%">Lote</th>
                                        <th width="15%">Vencimiento</th>
                                        <th width="10%">Calidad</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyDetalles">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="confirmarRecepcion()">Confirmar Ingreso</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let detallesOrden = [];

    document.addEventListener('DOMContentLoaded', function () {
        cargarRecepciones();
    });

    function cargarRecepciones() {
        fetch('../../api/compras/recepciones.php?action=list')
            .then(res => res.json())
            .then(data => {
                const tbody = document.querySelector('#tablaRecepciones tbody');
                tbody.innerHTML = '';

                if (!data.recepciones || data.recepciones.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center">No hay recepciones</td></tr>';
                    return;
                }

                data.recepciones.forEach(rec => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                    <td>${rec.numero_recepcion}</td>
                    <td>${rec.fecha_recepcion}</td>
                    <td>${rec.orden_numero}</td>
                    <td>${rec.proveedor_nombre}</td>
                    <td>${rec.tipo_recepcion}</td>
                    <td><span class="badge badge-success">${rec.estado}</span></td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="verRecepcion(${rec.id_recepcion})"><i class="fas fa-eye"></i></button>
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
        // Mockup: should be searching by number, but let's assume we search by ID for MVP or implement lookup
        // For now, let's list all EMITIDA or CONFIRMADA orders and filter

        // Better: Fetch orders logic
        // Implementation shortcut: For search, we need logic. For now, let's assume specific ID or exact Number match API support
        // Let's use List API and filter client side for MVP to avoid complex backend search logic
        fetch('../../api/compras/ordenes.php?action=list&estado=CONFIRMADA')
            .then(res => res.json())
            .then(data => {
                const orden = data.ordenes.find(o => o.numero_orden === ordenNum || o.id_orden_compra == ordenNum);
                if (orden) {
                    cargarDetalleOrden(orden.id_orden_compra);
                } else {
                    Swal.fire('No encontrada', 'No existe orden CONFIRMADA con ese número', 'warning');
                }
            });
    }

    function cargarDetalleOrden(idOrden) {
        fetch(`../../api/compras/ordenes.php?action=get&id=${idOrden}`)
            .then(res => res.json())
            .then(data => {
                const orden = data.orden;
                document.getElementById('id_orden_compra').value = orden.id_orden_compra;
                document.getElementById('nombre_proveedor').value = orden.proveedor_nombre;
                document.getElementById('id_proveedor').value = orden.id_proveedor;

                detallesOrden = orden.detalles;
                renderDetallesRecepcion();
                document.getElementById('panelDetalles').style.display = 'block';
            });
    }

    function renderDetallesRecepcion() {
        const tbody = document.getElementById('bodyDetalles');
        tbody.innerHTML = '';

        detallesOrden.forEach((det, idx) => {
            // Calcular pendiente
            const pendiente = parseFloat(det.cantidad_ordenada) - parseFloat(det.cantidad_recibida);
            if (pendiente <= 0) return; // Skip completed items

            const tr = document.createElement('tr');
            tr.innerHTML = `
            <td>${det.descripcion_producto}</td>
            <td>${det.cantidad_ordenada} ${det.unidad_medida}</td>
            <td>${pendiente}</td>
            <td><input type="number" class="form-control form-control-sm" id="rec_${idx}" value="${pendiente}" max="${pendiente}"></td>
            <td><input type="text" class="form-control form-control-sm" id="lote_${idx}"></td>
            <td><input type="date" class="form-control form-control-sm" id="venc_${idx}"></td>
            <td>
                <select class="form-control form-control-sm" id="calidad_${idx}">
                    <option value="APROBADO">Aprobado</option>
                    <option value="OBSERVADO">Observado</option>
                    <option value="RECHAZADO">Rechazado</option>
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
            if (!inputRec) return; // Skip rows not rendered (completed)

            const cantRec = parseFloat(inputRec.value);
            if (cantRec > 0) {
                itemsRecepcion.push({
                    id_detalle_oc: det.id_detalle_oc,
                    id_producto: det.id_producto,
                    id_tipo_inventario: det.id_tipo_inventario,
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
            Swal.fire('Error', 'No hay cantidades a recibir', 'warning');
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

        fetch('../../api/compras/recepciones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    $('#modalRecepcion').modal('hide');
                    Swal.fire('Éxito', 'Recepción registrada. Inventario actualizado.', 'success');
                    cargarRecepciones();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
    }
</script>

<?php include '../../includes/footer.php'; ?>