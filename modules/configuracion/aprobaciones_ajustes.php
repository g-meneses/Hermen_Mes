<?php
/**
 * Panel de Aprobaciones de Ajustes de Inventario
 * Sistema MES Hermen Ltda.
 */
require_once '../../config/database.php';

if (!isLoggedIn() || !in_array($_SESSION['user_role'] ?? '', ['admin', 'gerencia'])) {
    header("Location: ../../index.php");
    exit();
}

$pageTitle = "Aprobación de Ajustes";
$currentPage = 'aprobaciones_ajustes';

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Solicitudes de Ajustes Pendientes</h5>
                    <div>
                        <select id="filtroEstado" class="form-select form-select-sm" style="width: 200px;">
                            <option value="PENDIENTE">Pendientes de Aprobación</option>
                            <option value="APROBADO">Aprobados</option>
                            <option value="RECHAZADO">Rechazados</option>
                            <option value="TODOS">Todos</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="tablaSolicitudes">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Código / Tipo</th>
                                    <th>Solicitante</th>
                                    <th>Motivo Original</th>
                                    <th>Estado / Decisión</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Llenado por JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Aprobar/Rechazar Ajuste -->
<style>
    #modalAprobacion .modal-content {
        max-width: 1100px !important;
        width: 95% !important;
    }
    /* Asegurar que SweetAlert2 siempre aparezca por encima del modal */
    .swal2-container {
        z-index: 99999 !important;
    }
</style>
<div class="modal fade" id="modalAprobacion" tabindex="-1">
    <div class="modal-dialog" id="modalAprobacionDialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Evaluar Solicitud de Ajuste</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ajuste_id_actual">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Código:</strong> <span id="lbl_codigo"></span><br>
                        <strong>Fecha Solicitante:</strong> <span id="lbl_fecha"></span><br>
                        <strong>Solicitante:</strong> <span id="lbl_solicitante"></span>
                    </div>
                    <div class="col-md-6 text-end">
                        <h4><span id="lbl_tipo" class="badge"></span></h4>
                    </div>
                </div>
                
                <div class="alert alert-secondary" id="lbl_motivo"></div>

                <div class="row mb-3" id="seccion_autorizador" style="display:none;">
                    <div class="col-md-12">
                        <label class="fw-bold text-primary">¿Quién autorizó este ajuste? (Requerido para aprobar)</label>
                        <select class="form-select" id="ajuste_autorizado_por">
                            <option value="">Seleccione autorizador...</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Inventario Afectado</th>
                                <th class="text-end" style="width: 120px;">Cantidad</th>
                                <th class="text-end" style="width: 150px;">Costo Unit. (Bs)</th>
                                <th class="text-end" style="width: 150px;">Total (Bs)</th>
                            </tr>
                        </thead>
                        <tbody id="lista_productos_ajuste">
                            <!-- Detalle cargado por JS -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-end">Total Ajuste:</th>
                                <th class="text-end" id="total_ajuste_h">Bs. 0.00</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="form-group mb-3">
                    <label>Observaciones de Aprobación/Rechazo (Opcional):</label>
                    <textarea class="form-control" id="txt_observaciones_aprobador" rows="3" placeholder="Ej. Aprobado por rotura confirmada."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <div id="btn-actions-container">
                    <button type="button" class="btn btn-danger" onclick="procesarAjuste('RECHAZADO')">
                        <i class="fas fa-times"></i> Rechazar
                    </button>
                    <button type="button" class="btn btn-success" onclick="procesarAjuste('APROBADO')">
                        <i class="fas fa-check"></i> Aprobar (Afectar Stock)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const API_URL = '<?php echo SITE_URL; ?>/api/aprobaciones_ajuste.php';

let usuariosAutorizados = [];

document.addEventListener('DOMContentLoaded', function() {
    cargarSolicitudes();
    cargarUsuariosAutorizados();
    
    document.getElementById('filtroEstado').addEventListener('change', cargarSolicitudes);
});

async function cargarUsuariosAutorizados() {
    try {
        const url = '<?php echo SITE_URL; ?>/api/tipos_ingreso.php?action=usuarios_autorizacion';
        const response = await fetch(url);
        const data = await response.json();
        if (data.success) {
            usuariosAutorizados = data.usuarios;
        }
    } catch(e) { console.error('Error cargando autorizadores:', e); }
}

async function cargarSolicitudes() {
    const estado = document.getElementById('filtroEstado').value;
    const tbody = document.querySelector('#tablaSolicitudes tbody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Cargando solicitudes...</td></tr>';
    
    try {
        const response = await fetch(`${API_URL}?action=list&estado=${estado}`);
        const data = await response.json();
        
        if (data.success) {
            tbody.innerHTML = '';
            
            if (data.ajustes.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">No hay solicitudes ${estado.toLowerCase()}s.</td></tr>`;
                return;
            }
            
            data.ajustes.forEach(ajuste => {
                const isPendiente = ajuste.estado === 'PENDIENTE';
                
                let badgeClass = 'badge bg-warning';
                if(ajuste.estado === 'APROBADO') badgeClass = 'badge bg-success';
                if(ajuste.estado === 'RECHAZADO') badgeClass = 'badge bg-danger';
                
                let tipoClass = ajuste.tipo_ajuste === 'ENTRADA' ? 'text-success' : 'text-danger';
                let tipoIcon = ajuste.tipo_ajuste === 'ENTRADA' ? 'fa-arrow-up' : 'fa-arrow-down';
                
                // Formatear fechas
                const fechaSol = new Date(ajuste.fecha_solicitud).toLocaleString('es-BO');
                const fechaApr = ajuste.fecha_aprobacion ? new Date(ajuste.fecha_aprobacion).toLocaleString('es-BO') : '-';
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <div>${fechaSol}</div>
                        ${!isPendiente ? `<small class="text-muted" title="Fecha Decisión">${fechaApr}</small>` : ''}
                    </td>
                    <td>
                        <strong>${ajuste.codigo_ajuste}</strong><br>
                        <span class="${tipoClass}"><i class="fas ${tipoIcon}"></i> Ajuste ${ajuste.tipo_ajuste}</span>
                    </td>
                    <td>${ajuste.solicitante_nombre || '-'}</td>
                    <td><small>${ajuste.motivo || 'Sin motivo'}</small></td>
                    <td>
                        <span class="${badgeClass}">${ajuste.estado}</span><br>
                        ${!isPendiente ? `<small class="text-muted">Por: ${ajuste.aprobador_nombre || '-'}</small>` : ''}
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick="abrirAprobacion(${ajuste.id_ajuste}, '${ajuste.estado}')">
                            <i class="fas ${isPendiente ? 'fa-search' : 'fa-eye'}"></i> ${isPendiente ? 'Evaluar' : 'Ver'}
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            Swal.fire('Error', data.message || 'Error al cargar las solicitudes', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'Error de conexión con el servidor', 'error');
    }
}

async function abrirAprobacion(id, estadoActual) {
    try {
        const response = await fetch(`${API_URL}?action=get&id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            const aj = data.ajuste;
            const isPendiente = estadoActual === 'PENDIENTE';
            
            document.getElementById('ajuste_id_actual').value = aj.id_ajuste;
            document.getElementById('lbl_codigo').textContent = aj.codigo_ajuste;
            document.getElementById('lbl_fecha').textContent = new Date(aj.fecha_solicitud).toLocaleString('es-BO');
            document.getElementById('lbl_solicitante').textContent = aj.solicitante_nombre || '-';
            document.getElementById('lbl_motivo').innerHTML = `<strong>Motivo del encargado:</strong><br>${aj.motivo || '-'}`;
            
            const isPositivo = aj.tipo_ajuste === 'ENTRADA';
            const lblTipo = document.getElementById('lbl_tipo');
            lblTipo.textContent = isPositivo ? 'AJUSTE POSITIVO' : 'AJUSTE NEGATIVO';
            lblTipo.className = `badge ${isPositivo ? 'bg-success' : 'bg-danger'}`;
            
            // Autorizador Dropdown setup
            const selectAuth = document.getElementById('ajuste_autorizado_por');
            if (selectAuth) {
                selectAuth.innerHTML = '<option value="">Seleccione autorizador...</option>';
                usuariosAutorizados.forEach(u => {
                    const nombreCompleto = u.nombre_completo || u.nombre || '';
                    selectAuth.innerHTML += `<option value="${u.id_usuario}">${nombreCompleto}</option>`;
                });
            }

            if(isPendiente) {
                 document.getElementById('seccion_autorizador').style.display = 'flex';
            } else {
                 document.getElementById('seccion_autorizador').style.display = 'none';
            }

            // Llenar tabla de productos
            const tbody = document.getElementById('lista_productos_ajuste');
            tbody.innerHTML = '';
            let totalAjuste = 0;
            
            data.detalle.forEach(prod => {
                const cantidad = parseFloat(prod.cantidad_solicitada);
                // Si ya fue aprobado y guardó costo, o el costo actual del item
                const costoUnit = parseFloat(prod.costo_unitario_guardado || prod.costo_unitario || 0); 
                const total = cantidad * costoUnit;
                totalAjuste += total;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${prod.producto_codigo}</td>
                    <td>${prod.producto_nombre}</td>
                    <td>${prod.tipo_inventario_nombre || 'Materia Prima'}</td>
                    <td class="text-end font-monospace"><strong>${cantidad.toFixed(2)} ${prod.unidad}</strong></td>
                    <td class="text-end">
                        ${isPendiente ? 
                            `<input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end costo-ajuste" data-id="${prod.id_detalle}" data-cantidad="${cantidad}" value="${costoUnit.toFixed(4)}" onchange="recalcularFilaAjuste(this)" onkeyup="recalcularFilaAjuste(this)">` : 
                            `Bs. ${costoUnit.toFixed(4)}`
                        }
                    </td>
                    <td class="text-end fw-bold row-total">Bs. ${total.toFixed(2)}</td>
                `;
                tbody.appendChild(tr);
            });
            document.getElementById('total_ajuste_h').textContent = `Bs. ${totalAjuste.toFixed(2)}`;
            
            // Estado y Controles
            const txtObs = document.getElementById('txt_observaciones_aprobador');
            const btns = document.getElementById('btn-actions-container');
            
            if (isPendiente) {
                txtObs.value = '';
                txtObs.readOnly = false;
                btns.style.display = 'block';
            } else {
                txtObs.value = aj.observaciones_aprobador || 'Sin observaciones de cierre.';
                txtObs.readOnly = true;
                btns.style.display = 'none';
            }
            
            $('#modalAprobacion').modal('show');
            // Forzar ancho del modal (Bootstrap 4 limita por defecto)
            setTimeout(() => {
                const dialog = document.getElementById('modalAprobacionDialog');
                if (dialog) {
                    dialog.style.setProperty('max-width', '92vw', 'important');
                    dialog.style.setProperty('width', '92vw', 'important');
                    dialog.style.setProperty('margin', '1.75rem auto', 'important');
                }
            }, 50);
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'Error al consultar el detalle de la solicitud', 'error');
    }
}

function recalcularFilaAjuste(input) {
    const cantidad = parseFloat(input.dataset.cantidad);
    const costo = parseFloat(input.value) || 0;
    const total = cantidad * costo;
    input.closest('tr').querySelector('.row-total').textContent = `Bs. ${total.toFixed(2)}`;
    recalcularTotalAjuste();
}

function recalcularTotalAjuste() {
    let totalGeneral = 0;
    document.querySelectorAll('.costo-ajuste').forEach(input => {
        const cantidad = parseFloat(input.dataset.cantidad);
        const costo = parseFloat(input.value) || 0;
        totalGeneral += (cantidad * costo);
    });
    document.getElementById('total_ajuste_h').textContent = `Bs. ${totalGeneral.toFixed(2)}`;
}

function procesarAjuste(decision) {
    const id = document.getElementById('ajuste_id_actual').value;
    const observaciones = document.getElementById('txt_observaciones_aprobador').value;
    const autorizado_por = document.getElementById('ajuste_autorizado_por') ? document.getElementById('ajuste_autorizado_por').value : null;

    if (decision === 'APROBADO' && document.getElementById('seccion_autorizador').style.display !== 'none' && !autorizado_por) {
        Swal.fire('Atención', 'Debe seleccionar quién autorizó este ajuste para poder aprobarlo.', 'warning');
        return;
    }

    const costosDetalle = [];
    document.querySelectorAll('.costo-ajuste').forEach(input => {
        costosDetalle.push({
            id_detalle: input.dataset.id,
            costo_unitario: parseFloat(input.value) || 0
        });
    });

    const accionText = decision === 'APROBADO' ? 'aprobar y aplicar al Kardex' : 'rechazar';
    const confirmColor = decision === 'APROBADO' ? '#198754' : '#dc3545';
    
    $('#modalAprobacion').modal('hide');
    
    Swal.fire({
        title: `¿Está seguro de ${accionText} esta solicitud?`,
        text: decision === 'APROBADO' ? 'Esta acción modificará las existencias en inventario inmediatamente.' : 'La solicitud quedará anulada.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Sí, ${decision.toLowerCase()}`,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Procesando...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'procesar',
                    id_ajuste: id,
                    decision: decision === 'APROBADO' ? 'APROBAR' : 'RECHAZAR',
                    observaciones: observaciones,
                    autorizado_por: autorizado_por,
                    detalles_costo: costosDetalle
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    $('#modalAprobacion').modal('hide');
                    Swal.fire({
                        title: '¡Procesado!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonText: 'Continuar'
                    }).then(() => {
                        window.location.href = '<?php echo SITE_URL; ?>/modules/inventarios/index.php';
                    });
                } else {
                    Swal.fire('Error', data.message || 'Ha ocurrido un error al procesar', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Error de conexión con el servidor', 'error');
            });
        } else {
            // Si el usuario cancela, volvemos a mostrar el modal por si quiere cambiar algo
            $('#modalAprobacion').modal('show');
        }
    });
}
</script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php include '../../includes/footer.php'; ?>
