<?php
/**
 * Registro de Revisado Crudo (Vista Jerárquica)
 * Sistema MES Hermen Ltda.
 */

require_once '../../config/database.php';

// Verificar sesión
if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Registro de Revisado Crudo';
$currentPage = 'registro_revisado';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="card bg-dark text-white mb-4 shadow">
        <div class="card-body d-flex justify-content-between align-items-center py-3">
            <div>
                <h3 class="mb-0 text-white"><i class="fas fa-boxes"></i> Registro de Revisado Crudo</h3>
                <p class="text-white-50 mb-0 small">Tablero Operativo: Lotes Pendientes por Familia y Producto</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="cargarJerarquia()">
                    <i class="fas fa-sync-alt"></i> Actualizar Tablero
                </button>
            </div>
        </div>
    </div>

    <!-- KPIs Globales -->
    <div class="row mb-2">
        <div class="col-3 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Lotes en Piso</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiTotalLotes">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-boxes fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-3 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Recibido (Tejido)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiTotalRecibido">0 doc</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-truck-loading fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-3 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Saldo Pendiente</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiTotalPendiente">0 doc</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hourglass-half fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-3 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Avance Global</div>
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800" id="kpiAvancePorc">0%</div>
                                </div>
                                <div class="col">
                                    <div class="progress progress-sm mr-2">
                                        <div class="progress-bar bg-info" id="kpiAvanceBar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenedor de Jerarquía -->
    <div id="containerJerarquia">
        <div class="text-center py-5">
            <i class="fas fa-spinner fa-spin fa-3x text-muted"></i>
            <p class="mt-3">Cargando tablero operativo...</p>
        </div>
    </div>
</div>

<!-- Modal de Revisión (Reutilizando lógica B3) -->
<div id="modalRevisionLote" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">Registrar Producción de Revisado Crudo</h5>
            <span class="close text-white" onclick="cerrarRevision()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="alert alert-info py-2 mb-3">
                <div class="row">
                    <div class="col-md-6"><strong>Lote:</strong> <span id="revCodigoLote"></span></div>
                    <div class="col-md-6"><strong>Producto:</strong> <span id="revProducto"></span></div>
                </div>
                <div class="mt-1"><strong>Saldo Pendiente:</strong> <b id="revSaldoTotal">0</b> doc</div>
            </div>

            <!-- Formulario de Calidad -->
            <form id="formRegRevision">
                <div class="row">
                    <!-- Primera -->
                    <div class="col-md-4">
                        <div class="card border-success border-2 shadow-sm mb-3">
                            <div class="card-header bg-success text-white py-1">PRIMERA</div>
                            <div class="card-body p-2">
                                <div class="form-row">
                                    <div class="col"><input type="number" id="revDcz1" class="form-control" placeholder="Doc"></div>
                                    <div class="col"><input type="number" id="revUnd1" class="form-control" placeholder="Und"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Segunda -->
                    <div class="col-md-4">
                        <div class="card border-info border-2 shadow-sm mb-3">
                            <div class="card-header bg-info text-white py-1">SEGUNDA</div>
                            <div class="card-body p-2">
                                <div class="form-row">
                                    <div class="col"><input type="number" id="revDcz2" class="form-control" placeholder="Doc"></div>
                                    <div class="col"><input type="number" id="revUnd2" class="form-control" placeholder="Und"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Merma -->
                    <div class="col-md-4">
                        <div class="card border-danger border-2 shadow-sm mb-3">
                            <div class="card-header bg-danger text-white py-1">MERMA</div>
                            <div class="card-body p-2">
                                <div class="form-row">
                                    <div class="col"><input type="number" id="revDczM" class="form-control" placeholder="Doc"></div>
                                    <div class="col"><input type="number" id="revUndM" class="form-control" placeholder="Und"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-dark d-flex justify-content-between align-items-center py-2 mb-3">
                    <span>Total Computable (1ra + 2da):</span>
                    <h5 class="mb-0"><b id="revTotalComp">0</b> doc</h5>
                </div>

                <div class="form-group mb-0">
                    <label class="small font-weight-bold">Observaciones:</label>
                    <textarea id="revObs" class="form-control" rows="2" placeholder="Notas opcionales..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="cerrarRevision()">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnGuardarRev" onclick="guardarRevision()">
                <i class="fas fa-save"></i> Guardar Revisión
            </button>
        </div>
    </div>
</div>

<script>
const baseUrl = window.location.origin + '/mes_hermen';
let dataTree = [];

document.addEventListener('DOMContentLoaded', () => {
    cargarJerarquia();
    
    // Auto-calculador de modal
    ['revDcz1','revUnd1','revDcz2','revUnd2'].forEach(id => {
        document.getElementById(id).addEventListener('input', updateModalSum);
    });
});

async function cargarJerarquia() {
    const container = document.getElementById('containerJerarquia');
    container.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-3x text-muted"></i></div>';
    
    try {
        const response = await fetch(`${baseUrl}/api/revisado_crudo.php?action=listar_lotes_jerarquia`);
        const data = await response.json();
        
        if (data.success) {
            dataTree = data.data;
            renderTablero(data.data);
            actualizarKPIs(data.data);
        } else {
            container.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        }
    } catch (error) {
        container.innerHTML = `<div class="alert alert-danger">Error de conexión con el servidor.</div>`;
    }
}

function actualizarKPIs(tree) {
    let tLotes = 0, tRec = 0, tPen = 0;
    tree.forEach(f => {
        tLotes += f.totales.lotes;
        tRec += f.totales.recibido;
        tPen += f.totales.pendiente;
    });
    
    document.getElementById('kpiTotalLotes').textContent = tLotes;
    document.getElementById('kpiTotalRecibido').textContent = tRec.toFixed(2);
    document.getElementById('kpiTotalPendiente').textContent = tPen.toFixed(2);
    
    let avance = tRec > 0 ? ((tRec - tPen) / tRec) * 100 : 0;
    document.getElementById('kpiAvancePorc').textContent = Math.round(avance) + '%';
    document.getElementById('kpiAvanceBar').style.width = avance + '%';
}

function renderTablero(tree) {
    const container = document.getElementById('containerJerarquia');
    if (tree.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No hay lotes activos en el área de Revisado Crudo.</div>';
        return;
    }

    let html = '';
    tree.forEach((fam, fIdx) => {
        html += `
        <div class="card mb-3 shadow-sm border-bottom-primary">
            <div class="card-header bg-light d-flex justify-content-between align-items-center clickable" 
                 data-toggle="collapse" data-target="#famCollapse${fIdx}">
                <h5 class="mb-0 text-primary font-weight-bold">
                    <i class="fas fa-tags mr-2"></i> Familia: ${fam.familia}
                </h5>
                <div class="text-muted small">
                    <span class="mr-3"><b>${fam.totales.productos}</b> Prod</span>
                    <span class="mr-3"><b>${fam.totales.lotes}</b> Lotes</span>
                    <span class="mr-3">Recibido: <b>${fam.totales.recibido}</b> doc</span>
                    <span class="text-warning font-weight-bold">Pendiente: ${fam.totales.pendiente} doc</span>
                    <i class="fas fa-chevron-down ml-3"></i>
                </div>
            </div>
            <div id="famCollapse${fIdx}" class="collapse show">
                <div class="card-body p-3">
                    ${fam.productos.map(prod => renderProducto(prod)).join('')}
                </div>
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

function renderProducto(prod) {
    return `
    <div class="mb-4 border rounded p-3 bg-white shadow-sm">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
            <h6 class="mb-0 font-weight-bold text-dark"><i class="fas fa-box-open mr-2"></i> ${prod.producto} <small class="text-muted">(${prod.codigo})</small></h6>
            <div class="small">
                Rec: <b class="text-primary">${prod.totales.recibido}</b> | 
                1ra: <b class="text-success">${prod.totales.primera}</b> | 
                2da: <b class="text-info">${prod.totales.segunda}</b> | 
                Mer: <b class="text-danger">${prod.totales.merma}</b> | 
                Pen: <b class="text-warning">${prod.totales.pendiente}</b>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" style="font-size: 0.85rem;">
                <thead>
                    <tr class="bg-gray-100">
                        <th>Lote</th>
                        <th>Fecha Ingreso</th>
                        <th class="text-center">Recibido (doc)</th>
                        <th class="text-center">1ra (doc)</th>
                        <th class="text-center">2da (doc)</th>
                        <th class="text-center">Merma (doc)</th>
                        <th class="text-center">Pendiente (doc)</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    ${prod.lotes.map(l => `
                    <tr>
                        <td class="font-weight-bold text-primary">${l.codigo_lote}</td>
                        <td>${new Date(l.fecha_ingreso).toLocaleDateString()}</td>
                        <td class="text-center">${l.received}</td>
                        <td class="text-center text-success">${l.primera}</td>
                        <td class="text-center text-info">${l.segunda}</td>
                        <td class="text-center text-danger">${l.merma}</td>
                        <td class="text-center font-weight-bold ${l.pendiente > 0 ? 'text-warning' : 'text-success'}">${l.pendiente}</td>
                        <td class="text-center">${renderBadge(l.estado_revision)}</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary py-0" onclick="abrirRevision(${l.id_lote_wip}, '${l.codigo_lote}', '${prod.producto}', ${l.pendiente})">
                                <i class="fas fa-edit"></i> Revisar
                            </button>
                        </td>
                    </tr>`).join('')}
                </tbody>
            </table>
        </div>
    </div>`;
}

function renderBadge(est) {
    let cls = 'secondary';
    if(est === 'PARCIAL') cls = 'warning';
    if(est === 'EN_REVISION') cls = 'info';
    if(est === 'OBSERVADO') cls = 'danger';
    if(est === 'APROBADO') cls = 'success';
    return `<span class="badge badge-${cls}">${est}</span>`;
}

// Lógica de Revisión
let currentLoteId = null;

function abrirRevision(id, cod, prod, saldo) {
    currentLoteId = id;
    document.getElementById('formRegRevision').reset();
    document.getElementById('revCodigoLote').textContent = cod;
    document.getElementById('revProducto').textContent = prod;
    document.getElementById('revSaldoTotal').textContent = saldo;
    document.getElementById('revTotalComp').textContent = "0";
    document.getElementById('modalRevisionLote').style.display = 'block';
}

function cerrarRevision() {
    document.getElementById('modalRevisionLote').style.display = 'none';
}

function updateModalSum() {
    let d1 = parseInt(document.getElementById('revDcz1').value || 0);
    let u1 = parseInt(document.getElementById('revUnd1').value || 0);
    let d2 = parseInt(document.getElementById('revDcz2').value || 0);
    let u2 = parseInt(document.getElementById('revUnd2').value || 0);
    
    let total = (d1 + d2) + parseFloat(((u1 + u2) / 12).toFixed(2));
    document.getElementById('revTotalComp').textContent = total;
}

async function guardarRevision() {
    const btn = document.getElementById('btnGuardarRev');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    const d1 = parseInt(document.getElementById('revDcz1').value || 0);
    const u1 = parseInt(document.getElementById('revUnd1').value || 0);
    const d2 = parseInt(document.getElementById('revDcz2').value || 0);
    const u2 = parseInt(document.getElementById('revUnd2').value || 0);
    const dm = parseInt(document.getElementById('revDczM').value || 0);
    const um = parseInt(document.getElementById('revUndM').value || 0);

    if (d1+u1+d2+u2+dm+um === 0) {
        alert("Debe ingresar al menos una cantidad de producción.");
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Guardar Revisión';
        return;
    }

    // El backend espera registrar_ejecucion_linea vinculado a un plan
    // Pero en esta vista estamos cargando por material.
    // Buscaremos si hay un plan activo para este producto de alguna operaria, 
    // o pediremos al backend que asocie al responsable por defecto si no hay plan explícito.
    
    // Por simplicidad en esta fase, usaremos una acción directa de revisión por material 
    // que el backend ya maneja en la lógica de inspección.
    
    try {
        // En lugar de plan ficticio, usamos el endpoint de registro_revision directa (reforzado con desglose)
        const payload = {
            action: 'registrar_revision', // Lógica de inspección técnica tradicional ajustada
            id_lote_wip: currentLoteId,
            dcz_primera: d1, und_primera: u1,
            dcz_segunda: d2, und_segunda: u2,
            dcz_merma: dm, und_merma: um,
            observaciones: document.getElementById('revObs').value,
            // Agregamos una operaria ficticia o el sistema para esta vista rápida
            operarias: [
                {
                    id_operaria: 1, // Usuario sistema o pedir selección
                    cantidad_revisada_docenas: d1+d2+dm,
                    cantidad_revisada_unidades: u1+u2+um,
                    cantidad_aprobada_docenas: d1+d2,
                    cantidad_aprobada_unidades: u1+u2,
                    cantidad_observada_docenas: dm,
                    cantidad_observada_unidades: um,
                    observaciones: 'Registro desde tablero de materiales'
                }
            ]
        };

        const res = await fetch(`${baseUrl}/api/revisado_crudo.php`, {
            method: 'POST',
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            alert("✅ Revisión registrada con éxito.");
            cerrarRevision();
            cargarJerarquia();
        } else {
            alert("❌ " + data.message);
        }
    } catch (e) {
        alert("Error de red.");
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Guardar Revisión';
    }
}
</script>

<style>
.clickable { cursor: pointer; }
.clickable:hover { background-color: #f8f9fa !important; }
.border-2 { border-width: 2px !important; }
.text-xs { font-size: .7rem; }
.card-header .fa-chevron-down { transition: transform 0.3s; }
.card-header[aria-expanded="true"] .fa-chevron-down { transform: rotate(180deg); }
</style>

<?php require_once '../../includes/footer.php'; ?>
