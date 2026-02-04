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

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Centro de Aprobaciones</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item">
                    <a class="nav-link active" id="tab-pendientes" data-toggle="tab" href="#pendientes"
                        role="tab">Pendientes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-historial" data-toggle="tab" href="#historial" role="tab">Historial</a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <!-- Pendientes -->
                <div class="tab-pane fade show active" id="pendientes" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="tablaPendientes" width="100%">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Nº Documento</th>
                                    <th>Fecha</th>
                                    <th>Solicitante</th>
                                    <th>Monto</th>
                                    <th>Prioridad</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- Historial -->
                <div class="tab-pane fade" id="historial" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="tablaHistorial" width="100%">
                            <thead>
                                <tr>
                                    <th>Documento</th>
                                    <th>Fecha Acción</th>
                                    <th>Decisión</th>
                                    <th>Comentarios</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Acción -->
<div class="modal fade" id="modalAccion" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tituloAccion">Procesar Documento</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="formAccion">
                    <input type="hidden" id="id_documento">
                    <input type="hidden" id="tipo_documento">
                    <input type="hidden" id="decision">

                    <div class="form-group">
                        <label>Comentarios / Observaciones</label>
                        <textarea class="form-control" id="comentarios" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="enviarDecision()">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        cargarPendientes();

        // Load history when tab is clicked
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            if (e.target.id === 'tab-historial') {
                cargarHistorial();
            }
        });
    });

    function cargarPendientes() {
        fetch('../../api/compras/aprobaciones.php?action=pendientes')
            .then(res => res.json())
            .then(data => {
                const tbody = document.querySelector('#tablaPendientes tbody');
                tbody.innerHTML = '';

                if (!data.pendientes || data.pendientes.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center">No tiene aprobaciones pendientes</td></tr>';
                    return;
                }

                data.pendientes.forEach(p => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                    <td><span class="badge badge-info">${p.tipo}</span></td>
                    <td>${p.numero}</td>
                    <td>${p.fecha}</td>
                    <td>${p.solicitante}</td>
                    <td>${parseFloat(p.monto).toFixed(2)}</td>
                    <td>${p.prioridad}</td>
                    <td>
                        <button class="btn btn-sm btn-success" onclick="abrirAccion('${p.tipo}', ${p.id}, 'APROBADO')"><i class="fas fa-check"></i></button>
                        <button class="btn btn-sm btn-danger" onclick="abrirAccion('${p.tipo}', ${p.id}, 'RECHAZADO')"><i class="fas fa-times"></i></button>
                         <button class="btn btn-sm btn-warning" onclick="abrirAccion('${p.tipo}', ${p.id}, 'OBSERVADO')"><i class="fas fa-exclamation"></i></button>
                    </td>
                `;
                    tbody.appendChild(tr);
                });
            });
    }

    function cargarHistorial() {
        fetch('../../api/compras/aprobaciones.php?action=historial')
            .then(res => res.json())
            .then(data => {
                const tbody = document.querySelector('#tablaHistorial tbody');
                tbody.innerHTML = '';

                if (!data.historial || data.historial.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center">No hay historial</td></tr>';
                    return;
                }

                data.historial.forEach(h => {
                    let color = 'secondary';
                    if (h.accion === 'APROBADO') color = 'success';
                    if (h.accion === 'RECHAZADO') color = 'danger';

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                    <td>${h.tipo_documento} #${h.documento_numero}</td>
                    <td>${h.fecha_aprobacion}</td>
                    <td><span class="badge badge-${color}">${h.accion}</span></td>
                    <td>${h.comentarios}</td>
                `;
                    tbody.appendChild(tr);
                });
            });
    }

    function abrirAccion(tipo, id, decision) {
        document.getElementById('id_documento').value = id;
        document.getElementById('tipo_documento').value = tipo === 'SOLICITUD' ? 'SOLICITUD_COMPRA' : 'ORDEN_COMPRA'; // Match ENUM
        document.getElementById('decision').value = decision;

        let titulo = 'Procesar';
        if (decision === 'APROBADO') titulo = 'Aprobar Documento';
        if (decision === 'RECHAZADO') titulo = 'Rechazar Documento';
        document.getElementById('tituloModal').textContent = titulo;

        $('#modalAccion').modal('show');
    }

    function enviarDecision() {
        const data = {
            action: 'procesar',
            id_documento: document.getElementById('id_documento').value,
            tipo_documento: document.getElementById('tipo_documento').value,
            decision: document.getElementById('decision').value,
            comentarios: document.getElementById('comentarios').value
        };

        fetch('../../api/compras/aprobaciones.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    $('#modalAccion').modal('hide');
                    Swal.fire('Éxito', 'Acción registrada correctamente', 'success');
                    cargarPendientes();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
    }
</script>

<?php include '../../includes/footer.php'; ?>