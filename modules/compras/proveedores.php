<?php
// modules/compras/proveedores.php
require_once '../../config/database.php';

if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}
$pageTitle = "Gestión de Proveedores";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800">Proveedores</h1>
        <button class="btn btn-primary" onclick="abrirModalProveedor()">
            <i class="fas fa-plus"></i> Nuevo Proveedor
        </button>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="tablaProveedores" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Razón Social</th>
                            <th>NIT</th>
                            <th>Tipo</th>
                            <th>Categoría</th>
                            <th>Rating</th>
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

<!-- Modal Proveedor -->
<div class="modal fade" id="modalProveedor" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tituloModal">Nuevo Proveedor</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="formProveedor">
                    <input type="hidden" id="id_proveedor">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Código</label>
                                <input type="text" class="form-control" id="codigo" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Razón Social</label>
                                <input type="text" class="form-control" id="razon_social" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>NIT/RUC</label>
                                <input type="text" class="form-control" id="nit">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Tipo Origen</label>
                                <select class="form-control" id="tipo">
                                    <option value="LOCAL">Local</option>
                                    <option value="IMPORTACION">Importación</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Categoría</label>
                                <select class="form-control" id="categoria_proveedor">
                                    <option value="MATERIAS_PRIMAS">Materias Primas</option>
                                    <option value="INSUMOS">Insumos</option>
                                    <option value="REPUESTOS">Repuestos</option>
                                    <option value="SERVICIOS">Servicios</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Cond. Pago</label>
                                <select class="form-control" id="condicion_pago">
                                    <option value="CONTADO">Contado</option>
                                    <option value="CREDITO_30">Crédito 30 días</option>
                                    <option value="CREDITO_60">Crédito 60 días</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Teléfono</label>
                                <input type="text" class="form-control" id="telefono">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" id="email">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Límite Crédito (BOB)</label>
                                <input type="number" class="form-control" id="limite_credito">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="es_preferente">
                            <label class="custom-control-label" for="es_preferente">Proveedor Preferente</label>
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarProveedor()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        cargarProveedores();
    });

    function cargarProveedores() {
        fetch('../../api/proveedores.php?action=list')
            .then(res => res.json())
            .then(data => {
                const tbody = document.querySelector('#tablaProveedores tbody');
                tbody.innerHTML = '';

                data.proveedores.forEach(p => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                    <td>${p.codigo}</td>
                    <td>${p.razon_social}</td>
                    <td>${p.nit || '-'}</td>
                    <td>${p.tipo}</td>
                    <td>${p.categoria_proveedor || '-'}</td>
                    <td>${renderStars(p.rating_general)}</td>
                    <td>${p.activo == 1 ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-danger">Inactivo</span>'}</td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="editarProveedor(${p.id_proveedor})"><i class="fas fa-edit"></i></button>
                    </td>
                `;
                    tbody.appendChild(tr);
                });
            });
    }

    function renderStars(rating) {
        if (!rating) return '-';
        let stars = '';
        for (let i = 0; i < Math.round(rating); i++) stars += '<i class="fas fa-star text-warning"></i>';
        return stars;
    }

    function abrirModalProveedor() {
        document.getElementById('formProveedor').reset();
        document.getElementById('id_proveedor').value = '';
        document.getElementById('tituloModal').textContent = 'Nuevo Proveedor';
        $('#modalProveedor').modal('show');
    }

    function editarProveedor(id) {
        fetch(`../../api/proveedores.php?action=get&id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const p = data.proveedor;
                    document.getElementById('id_proveedor').value = p.id_proveedor;
                    document.getElementById('codigo').value = p.codigo;
                    document.getElementById('razon_social').value = p.razon_social;
                    document.getElementById('nit').value = p.nit;
                    document.getElementById('tipo').value = p.tipo;
                    document.getElementById('categoria_proveedor').value = p.categoria_proveedor;
                    document.getElementById('condicion_pago').value = p.condicion_pago;
                    document.getElementById('telefono').value = p.telefono;
                    document.getElementById('email').value = p.email;
                    document.getElementById('limite_credito').value = p.limite_credito;
                    document.getElementById('es_preferente').checked = p.es_preferente == 1;

                    document.getElementById('tituloModal').textContent = 'Editar Proveedor';
                    $('#modalProveedor').modal('show');
                }
            });
    }

    function guardarProveedor() {
        const data = {
            action: document.getElementById('id_proveedor').value ? 'update' : 'create',
            id_proveedor: document.getElementById('id_proveedor').value,
            codigo: document.getElementById('codigo').value,
            razon_social: document.getElementById('razon_social').value,
            nit: document.getElementById('nit').value,
            tipo: document.getElementById('tipo').value,
            categoria_proveedor: document.getElementById('categoria_proveedor').value,
            condicion_pago: document.getElementById('condicion_pago').value,
            telefono: document.getElementById('telefono').value,
            email: document.getElementById('email').value,
            limite_credito: document.getElementById('limite_credito').value,
            es_preferente: document.getElementById('es_preferente').checked ? 1 : 0
        };

        fetch('../../api/proveedores.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    $('#modalProveedor').modal('hide');
                    Swal.fire('Éxito', res.message, 'success');
                    cargarProveedores();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
    }
</script>

<?php include '../../includes/footer.php'; ?>