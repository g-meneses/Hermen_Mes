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
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formProveedor">
                    <input type="hidden" id="id_proveedor">

                    <nav>
                        <div class="nav nav-tabs mb-3" id="proveedorTabs" role="tablist">
                            <a class="nav-item nav-link active" id="datos-tab" data-toggle="tab" href="#datos" role="tab"
                                aria-controls="datos" aria-selected="true">Datos Generales</a>
                            <a class="nav-item nav-link" id="tributacion-tab" data-toggle="tab" href="#tributacion" role="tab"
                                aria-controls="tributacion" aria-selected="false">Clasificación y Tributación</a>
                            <a class="nav-item nav-link" id="contacto-tab" data-toggle="tab" href="#contacto" role="tab"
                                aria-controls="contacto" aria-selected="false">Contacto y Ubicación</a>
                            <a class="nav-item nav-link" id="comercial-tab" data-toggle="tab" href="#comercial" role="tab"
                                aria-controls="comercial" aria-selected="false">Comercial</a>
                        </div>
                    </nav>

                    <div class="tab-content" id="proveedorTabsContent">

                        <!-- Pestaña: Datos Generales -->
                        <div class="tab-pane fade show active" id="datos" role="tabpanel" aria-labelledby="datos-tab">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Código <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="codigo" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Razón Social <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="razon_social" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>NIT / RUC / Cédula</label>
                                        <input type="text" class="form-control" id="nit">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>Nombre Comercial</label>
                                        <input type="text" class="form-control" id="nombre_comercial">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Tipo Origen <span class="text-danger">*</span></label>
                                        <select class="form-control" id="tipo" required>
                                            <option value="LOCAL">Local</option>
                                            <option value="IMPORTACION">Internacional / Importación</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña: Tributación -->
                        <div class="tab-pane fade" id="tributacion" role="tabpanel" aria-labelledby="tributacion-tab">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Régimen Tributario</label>
                                        <select class="form-control" id="regimen_tributario">
                                            <option value="GENERAL">General (Con Factura)</option>
                                            <option value="SIMPLIFICADO">Simplificado</option>
                                            <option value="DIRECTO_SIN_FACTURA">Directo (Nota de venta / Recibo)
                                            </option>
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
                                        <label>Tipo Contribuyente</label>
                                        <select class="form-control" id="tipo_contribuyente">
                                            <option value="">Seleccionar...</option>
                                            <option value="NATURAL">Persona Natural</option>
                                            <option value="JURIDICA">Persona Jurídica</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña: Contacto y Ubicación -->
                        <div class="tab-pane fade" id="contacto" role="tabpanel" aria-labelledby="contacto-tab">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>Dirección</label>
                                        <input type="text" class="form-control" id="direccion">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Ciudad</label>
                                        <input type="text" class="form-control" id="ciudad">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>País</label>
                                        <select class="form-control" id="pais">
                                            <option value="Bolivia" selected>Bolivia</option>
                                            <option value="Perú">Perú</option>
                                            <option value="Brasil">Brasil</option>
                                            <option value="Argentina">Argentina</option>
                                            <option value="Chile">Chile</option>
                                            <option value="Colombia">Colombia</option>
                                            <option value="Estados Unidos">Estados Unidos</option>
                                            <option value="China">China</option>
                                            <option value="Alemania">Alemania</option>
                                            <option value="Italia">Italia</option>
                                            <option value="España">España</option>
                                            <option value="Corea del Sur">Corea del Sur</option>
                                            <option value="Taiwán">Taiwán</option>
                                            <option value="India">India</option>
                                            <option value="Turquía">Turquía</option>
                                            <option value="México">México</option>
                                            <option value="Otros">Otros</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Teléfono(s)</label>
                                        <input type="text" class="form-control" id="telefono">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Email General</label>
                                        <input type="email" class="form-control" id="email">
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <h6 class="font-weight-bold">Contacto Directo (Opcional)</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Nombre Contacto</label>
                                        <input type="text" class="form-control" id="nombre_contacto">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Celular / Teléfono Contacto</label>
                                        <input type="text" class="form-control" id="contacto_telefono">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pestaña: Comercial -->
                        <div class="tab-pane fade" id="comercial" role="tabpanel" aria-labelledby="comercial-tab">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Moneda <span class="text-danger">*</span></label>
                                        <select class="form-control" id="moneda" required>
                                            <option value="BOB" selected>Bolivianos (Bs)</option>
                                            <option value="USD">Dólares ($us)</option>
                                            <option value="EUR">Euros (€)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Cond. Pago</label>
                                        <select class="form-control" id="condicion_pago">
                                            <option value="CONTADO">Contado</option>
                                            <option value="CREDITO">Crédito</option>
                                            <option value="ANTICIPO">Anticipo</option>
                                            <option value="CREDITO_30">Crédito 30 días</option>
                                            <option value="CREDITO_60">Crédito 60 días</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Días Crédito</label>
                                        <input type="number" class="form-control" id="dias_credito" value="0">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Límite Crédito</label>
                                        <input type="number" class="form-control" id="limite_credito" step="0.01">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>Observaciones</label>
                                        <textarea class="form-control" id="observaciones" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group pt-4">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="es_preferente">
                                            <label class="custom-control-label" for="es_preferente">Proveedor
                                                Preferente</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
        
        // Autogenerar código
        document.getElementById('codigo').value = 'Cargando...';
        fetch('../../api/proveedores.php?action=siguiente_codigo')
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('codigo').value = data.codigo;
                } else {
                    document.getElementById('codigo').value = '';
                }
            })
            .catch(() => {
                document.getElementById('codigo').value = '';
            });

        $('#proveedorTabs a:first').tab('show');
        $('#modalProveedor').modal('show');
    }

    function editarProveedor(id) {
        fetch(`../../api/proveedores.php?action=get&id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const p = data.proveedor;
                    document.getElementById('id_proveedor').value = p.id_proveedor;
                    document.getElementById('codigo').value = p.codigo || '';
                    document.getElementById('razon_social').value = p.razon_social || '';
                    document.getElementById('nombre_comercial').value = p.nombre_comercial || '';
                    document.getElementById('nit').value = p.nit || '';
                    document.getElementById('tipo').value = p.tipo || 'LOCAL';
                    document.getElementById('regimen_tributario').value = p.regimen_tributario || 'GENERAL';
                    document.getElementById('categoria_proveedor').value = p.categoria_proveedor || 'MATERIAS_PRIMAS';
                    document.getElementById('tipo_contribuyente').value = p.tipo_contribuyente || '';

                    document.getElementById('direccion').value = p.direccion || '';
                    document.getElementById('ciudad').value = p.ciudad || '';
                    document.getElementById('pais').value = p.pais || 'Bolivia';
                    document.getElementById('telefono').value = p.telefono || '';
                    document.getElementById('email').value = p.email || '';
                    document.getElementById('nombre_contacto').value = p.nombre_contacto || '';
                    document.getElementById('contacto_telefono').value = p.contacto_telefono || '';

                    document.getElementById('moneda').value = p.moneda || 'BOB';
                    document.getElementById('condicion_pago').value = p.condicion_pago || 'CONTADO';
                    document.getElementById('dias_credito').value = p.dias_credito || '0';
                    document.getElementById('limite_credito').value = p.limite_credito || '';
                    document.getElementById('observaciones').value = p.observaciones || '';
                    document.getElementById('es_preferente').checked = p.es_preferente == 1;

                    document.getElementById('tituloModal').textContent = 'Editar Proveedor';
                    $('#proveedorTabs a:first').tab('show');
                    $('#modalProveedor').modal('show');
                }
            });
    }

    function guardarProveedor() {
        if (!document.getElementById('codigo').value || !document.getElementById('razon_social').value) {
            Swal.fire('Atención', 'El Código y Razón Social son obligatorios', 'warning');
            return;
        }

        const data = {
            action: document.getElementById('id_proveedor').value ? 'update' : 'create',
            id_proveedor: document.getElementById('id_proveedor').value,
            codigo: document.getElementById('codigo').value,
            razon_social: document.getElementById('razon_social').value,
            nombre_comercial: document.getElementById('nombre_comercial').value,
            nit: document.getElementById('nit').value,
            tipo: document.getElementById('tipo').value,
            regimen_tributario: document.getElementById('regimen_tributario').value,
            categoria_proveedor: document.getElementById('categoria_proveedor').value,
            tipo_contribuyente: document.getElementById('tipo_contribuyente').value,

            direccion: document.getElementById('direccion').value,
            ciudad: document.getElementById('ciudad').value,
            pais: document.getElementById('pais').value,
            telefono: document.getElementById('telefono').value,
            email: document.getElementById('email').value,
            nombre_contacto: document.getElementById('nombre_contacto').value,
            contacto_telefono: document.getElementById('contacto_telefono').value,

            moneda: document.getElementById('moneda').value,
            condicion_pago: document.getElementById('condicion_pago').value,
            dias_credito: document.getElementById('dias_credito').value,
            limite_credito: document.getElementById('limite_credito').value,
            observaciones: document.getElementById('observaciones').value,
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