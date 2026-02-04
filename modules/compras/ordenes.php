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

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800">Órdenes de Compra</h1>
        <button class="btn btn-primary" onclick="abrirModalOrden()">
            <i class="fas fa-plus"></i> Nueva Orden
        </button>
    </div>

    <!-- Filtros -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label>Estado</label>
                    <select id="filtroEstado" class="form-control" onchange="cargarOrdenes()">
                        <option value="todos">Todos</option>
                        <option value="BORRADOR">Borrador</option>
                        <option value="EMITIDA">Emitida</option>
                        <option value="CONFIRMADA">Confirmada</option>
                        <option value="RECIBIDA_PARCIAL">Recibida Parcial</option>
                        <option value="RECIBIDA_TOTAL">Recibida Total</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label>Proveedor</label>
                    <select id="filtroProveedor" class="form-control" onchange="cargarOrdenes()">
                        <option value="">Todos</option>
                        <!-- Dynamic -->
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="tablaOrdenes" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nº Orden</th>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th>Solicitud Ref.</th>
                            <th>Total (BOB)</th>
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

<!-- Modal Nueva Orden -->
<div class="modal fade" id="modalOrden" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tituloModal">Nueva Orden de Compra</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formOrden">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Número (Auto)</label>
                                <input type="text" class="form-control" id="numero_orden" readonly>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>Proveedor</label>
                                <select class="form-control" id="id_proveedor" required>
                                    <option value="">Seleccione Proveedor</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Fecha Entrega Est.</label>
                                <input type="date" class="form-control" id="fecha_entrega">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <h6 class="font-weight-bold mt-3">Detalle de Productos</h6>
                            <div class="table-responsive">
                                <table class="table table-sm" id="tablaDetalles">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th width="10%">Cant.</th>
                                            <th width="10%">Unidad</th>
                                            <th width="15%">Precio Unit.</th>
                                            <th width="15%">Total</th>
                                            <th width="5%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="bodyDetalles">
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="6">
                                                <button type="button" class="btn btn-sm btn-info"
                                                    onclick="agregarFila()">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div class="text-right">
                                <h5>Total Orden: <span id="totalOrden">0.00</span></h5>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarOrden()">Generar Orden</button>
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
                    let badgeClass = 'secondary';
                    if (oc.estado === 'CONFIRMADA') badgeClass = 'success';
                    if (oc.estado === 'EMITIDA') badgeClass = 'primary';

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                    <td>${oc.numero_orden}</td>
                    <td>${oc.fecha_orden}</td>
                    <td>${oc.proveedor_nombre}</td>
                    <td>${oc.numero_solicitud || '-'}</td>
                    <td>${parseFloat(oc.total).toFixed(2)}</td>
                    <td><span class="badge badge-${badgeClass}">${oc.estado}</span></td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="verOrden(${oc.id_orden_compra})"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-sm btn-secondary" onclick="imprimirOrden(${oc.id_orden_compra})"><i class="fas fa-print"></i></button>
                    </td>
                `;
                    tbody.appendChild(tr);
                });
            });
    }

    function abrirModalOrden() {
        document.getElementById('formOrden').reset();
        document.getElementById('id_proveedor').value = '';
        itemsDetalle = [];
        renderDetalles();

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

    function renderDetalles() {
        const tbody = document.getElementById('bodyDetalles');
        tbody.innerHTML = '';
        let total = 0;

        itemsDetalle.forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
            <td><input type="text" class="form-control form-control-sm" value="${item.descripcion_producto}" onchange="actualizarItem(${index}, 'descripcion_producto', this.value)"></td>
            <td><input type="number" class="form-control form-control-sm" value="${item.cantidad}" onchange="actualizarItem(${index}, 'cantidad', this.value)"></td>
            <td><input type="text" class="form-control form-control-sm" value="${item.unidad_medida}" onchange="actualizarItem(${index}, 'unidad_medida', this.value)"></td>
            <td><input type="number" class="form-control form-control-sm" value="${item.precio_unitario}" onchange="actualizarItem(${index}, 'precio_unitario', this.value)"></td>
            <td>${parseFloat(item.total).toFixed(2)}</td>
            <td><button class="btn btn-sm btn-danger" onclick="eliminarFila(${index})"><i class="fas fa-trash"></i></button></td>
        `;
            tbody.appendChild(tr);
            total += parseFloat(item.total);
        });

        document.getElementById('totalOrden').textContent = total.toFixed(2);
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
        const data = {
            action: 'create',
            numero_orden: document.getElementById('numero_orden').value,
            id_proveedor: document.getElementById('id_proveedor').value,
            fecha_entrega_estimada: document.getElementById('fecha_entrega').value,
            total: document.getElementById('totalOrden').textContent,
            // Assuming MP (id=1) for general purchases for now, or add selector
            detalles: itemsDetalle.map(item => ({ ...item, id_tipo_inventario: 1 }))
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
                    Swal.fire('Éxito', 'Orden creada correctamente', 'success');
                    cargarOrdenes();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
    }
</script>

<?php include '../../includes/footer.php'; ?>