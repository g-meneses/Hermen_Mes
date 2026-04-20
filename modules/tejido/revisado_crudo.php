<?php
/**
 * Revisado Crudo (PASO 1 & 2)
 * Sistema MES Hermen Ltda.
 */

require_once '../../config/database.php';

// Verificar sesión
if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Revisado Crudo';
$currentPage = 'revisado_crudo';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Header con título -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="mb-1"><i class="fas fa-search-plus"></i> Revisado Crudo</h3>
                <p class="text-muted mb-0">Recepción y revisión de lotes provenientes de Tejido</p>
            </div>
            <div>
                <button class="btn btn-outline-secondary" onclick="cargarPendientes()">
                    <i class="fas fa-sync-alt"></i> Actualizar
                </button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="tablaPendientes">
                    <thead class="thead-light">
                        <tr>
                            <th>Lote</th>
                            <th>Fecha Inicio</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Área actual</th>
                            <th>Estado revisión</th>
                            <th class="text-center" width="140">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="bodyPendientes">
                        <tr><td colspan="7" class="text-center">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Revisar Lote -->
<div id="modalRevision" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h5 class="modal-title">Registrar Revisión Crudo - Lote <span id="revLoteCodigo" class="text-primary"></span></h5>
            <span class="close" onclick="closeModal('modalRevision')">&times;</span>
        </div>
        <div class="modal-body">
            <!-- Info del Lote -->
            <div class="alert alert-info">
                <strong>Producto:</strong> <span id="revLoteProducto"></span><br>
                <strong>Cantidad Total a Revisar:</strong> <span id="revLoteCantidad"></span>
            </div>
            
            <form id="formRevision">
                <input type="hidden" id="revIdLote">
                
                <h6 class="mb-3 border-bottom pb-2">Cantidades de Revisión</h6>
                
                <div class="row align-items-center mb-3">
                    <div class="col-md-3"><strong>Revisado:</strong></div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="number" id="revRevisadoDoc" class="form-control" placeholder="Docenas" min="0" oninput="calcTotales()" required>
                            <div class="input-group-append"><span class="input-group-text">doc</span></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="number" id="revRevisadoUni" class="form-control" placeholder="Unidades" min="0" oninput="calcTotales()" required>
                            <div class="input-group-append"><span class="input-group-text">uni</span></div>
                        </div>
                    </div>
                </div>
                
                <div class="row align-items-center mb-3">
                    <div class="col-md-3 text-success"><strong>Aprobado:</strong></div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="number" id="revAprobadoDoc" class="form-control" placeholder="Docenas" min="0" oninput="calcTotales()" required>
                            <div class="input-group-append"><span class="input-group-text">doc</span></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="number" id="revAprobadoUni" class="form-control" placeholder="Unidades" min="0" oninput="calcTotales()" required>
                            <div class="input-group-append"><span class="input-group-text">uni</span></div>
                        </div>
                    </div>
                </div>

                <div class="row align-items-center mb-4">
                    <div class="col-md-3 text-danger"><strong>Observado:</strong></div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="number" id="revObservadoDoc" class="form-control" placeholder="Docenas" min="0" oninput="calcTotales()" required>
                            <div class="input-group-append"><span class="input-group-text">doc</span></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="number" id="revObservadoUni" class="form-control" placeholder="Unidades" min="0" oninput="calcTotales()" required>
                            <div class="input-group-append"><span class="input-group-text">uni</span></div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label><strong>Observaciones de Calidad</strong></label>
                    <textarea id="revObservaciones" class="form-control" rows="3" placeholder="Detalle fallas, agujeros, manchas, etc."></textarea>
                </div>
                
                <hr>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Operarias Participantes</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="agregarFilaOperaria()">
                        <i class="fas fa-plus"></i> Agregar Operaria
                    </button>
                </div>
                
                <div id="alertOperarias" class="alert alert-warning p-2" style="display:none; font-size: 0.9em;">
                    <i class="fas fa-exclamation-triangle"></i> Las sumas de las operarias no cuadran con los totales cabecera.
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="bg-light text-center" style="font-size:0.85em;">
                            <tr>
                                <th width="20%">Operaria</th>
                                <th width="20%">Revisado (d|u)</th>
                                <th width="20%">Aprobado (d|u)</th>
                                <th width="20%">Observado (d|u)</th>
                                <th width="15%">Observaciones</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody id="bodyOperarias"></tbody>
                        <tfoot class="bg-light font-weight-bold text-center" style="font-size:0.85em;">
                            <tr>
                                <td class="text-right">Sumas Operarias:</td>
                                <td id="sumOpRev">0 | 0</td>
                                <td id="sumOpApr">0 | 0</td>
                                <td id="sumOpObs">0 | 0</td>
                                <td colspan="2"></td>
                            </tr>
                            <tr>
                                <td class="text-right text-muted">Totales Cabecera:</td>
                                <td id="totCabRev" class="text-muted">0 | 0</td>
                                <td id="totCabApr" class="text-muted">0 | 0</td>
                                <td id="totCabObs" class="text-muted">0 | 0</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div id="revHistorialContainer" style="display:none;" class="mt-4">
                    <h6 class="mb-2 border-bottom pb-1">Historial de Revisiones Previas</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Revisado (doc|uni)</th>
                                    <th>Aprobado (doc|uni)</th>
                                    <th>Observado (doc|uni)</th>
                                    <th>Resultado</th>
                                </tr>
                            </thead>
                            <tbody id="bodyHistorialRevisiones">
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modalRevision')">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="guardarRevision()">
                <i class="fas fa-save"></i> Guardar revisión
            </button>
        </div>
    </div>
</div>

<!-- Modal: Enviar Lote -->
<div id="modalEnviarLote" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Enviar Lote <span id="envLoteCodigo" class="text-success"></span></h5>
            <span class="close" onclick="closeModal('modalEnviarLote')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="alert alert-success">
                <strong>Lote Aprobado!</strong> Seleccione el proceso destino para transferir las cantidades confirmadas: <strong id="envLoteCantidad"></strong>.
            </div>
            
            <form id="formEnviarLote">
                <input type="hidden" id="envIdLote">
                
                <div class="form-group">
                    <label><strong>Siguiente Proceso:</strong></label>
                    <select id="envAreaDestino" class="form-control" required>
                        <option value="">Cargando áreas...</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modalEnviarLote')">Cancelar</button>
            <button type="button" class="btn btn-success" onclick="confirmarEnvioLote()">
                <i class="fas fa-paper-plane"></i> Confirmar envío
            </button>
        </div>
    </div>
</div>

<!-- Modal: Tratar Observado -->
<div id="modalTratarLote" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title text-danger">Tratar Lote Observado <span id="trtLoteCodigo"></span></h5>
            <span class="close" onclick="closeModal('modalTratarLote')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="alert alert-warning">
                <strong>Cantidad Observada:</strong> <span id="trtLoteCantidad"></span>
            </div>
            
            <form id="formTratarLote">
                <input type="hidden" id="trtIdLote">
                
                <div class="form-group">
                    <label><strong>Decisión:</strong></label>
                    <select id="trtDecision" class="form-control" onchange="toggleAreaReproceso()" required>
                        <option value="">-- Seleccionar --</option>
                        <option value="RETENER">Pausar / Retener en Crudo</option>
                        <option value="REPROCESO">Enviar a Reproceso</option>
                        <option value="MERMA">Declarar Merma / Descarte</option>
                    </select>
                </div>
                
                <div class="form-group" id="grpAreaReproceso" style="display:none;">
                    <label><strong>Área Destino de Reproceso:</strong></label>
                    <select id="trtAreaDestino" class="form-control">
                        <option value="">Cargando áreas...</option>
                    </select>
                </div>

                <div class="form-group">
                    <label><strong>Motivo / Causa:</strong></label>
                    <input type="text" id="trtMotivo" class="form-control" placeholder="Ej: Agujeros constantes, Manchas graves" required>
                </div>

                <div class="form-group">
                    <label><strong>Observaciones y notas (Opcional):</strong></label>
                    <textarea id="trtObservaciones" class="form-control" rows="2"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modalTratarLote')">Cancelar</button>
            <button type="button" class="btn btn-danger" onclick="confirmarTratamiento()">
                <i class="fas fa-save"></i> Registrar Tratamiento
            </button>
        </div>
    </div>
</div>

<script>
const baseUrl = window.location.origin + '/mes_hermen';

document.addEventListener('DOMContentLoaded', function() {
    cargarPendientes();
});

async function cargarPendientes() {
    const tbody = document.getElementById('bodyPendientes');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando lotes...</td></tr>';
    
    try {
        const response = await fetch(`${baseUrl}/api/revisado_crudo.php?action=listar_pendientes`);
        const data = await response.json();
        
        if (data.success) {
            renderTabla(data.data);
        } else {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${data.message || 'Error al cargar datos'}</td></tr>`;
        }
    } catch (error) {
        console.error('Error:', error);
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Error de conexión con el servidor</td></tr>`;
    }
}

function renderTabla(loteList) {
    const tbody = document.getElementById('bodyPendientes');
    
    if (!loteList || loteList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No hay lotes en curso para recepción o revisión.</td></tr>';
        return;
    }
    
    tbody.innerHTML = loteList.map(lote => {
        const cantFormat = `${lote.cantidad_docenas} doc | ${String(lote.cantidad_unidades).padStart(2, '0')} uni`;
        const badgeRevisado = getBadgeRevision(lote.estado_revision);
        const areaColor = lote.area_codigo === 'TEJEDURIA' ? '#e2e8f0' : '#cce5ff';
        const areaTextColor = lote.area_codigo === 'TEJEDURIA' ? '#4a5568' : '#004085';
        
        let botonAccion = '';
        if (lote.area_codigo === 'TEJEDURIA') {
            botonAccion = `<button class="btn btn-sm btn-info w-100" onclick="recibirLote(${lote.id_lote_wip}, '${lote.codigo_lote}')" title="Recibir en Revisado Crudo">
                               <i class="fas fa-inbox"></i> Recibir
                           </button>`;
        } else if (lote.area_codigo === 'REVISADO_CRUDO') {
            if (lote.estado_revision === 'PARCIAL' && lote.id_revision_activa) {
                botonAccion = `<button class="btn btn-sm btn-warning w-100" onclick="separarLote(${lote.id_revision_activa}, '${lote.codigo_lote}')" title="Generar Split">
                                   <i class="fas fa-cut"></i> Separar
                               </button>`;
            } else if (lote.estado_revision === 'APROBADO') {
                botonAccion = `<button class="btn btn-sm btn-success w-100" onclick="abrirModalEnviar(${lote.id_lote_wip}, '${lote.codigo_lote}', '${cantFormat}')" title="Enviar Siguiente Proceso">
                                   <i class="fas fa-shipping-fast"></i> Enviar
                               </button>`;
            } else if (lote.estado_revision === 'OBSERVADO') {
                botonAccion = `<button class="btn btn-sm btn-danger w-100" onclick="abrirModalTratar(${lote.id_lote_wip}, '${lote.codigo_lote}', '${cantFormat}')" title="Tratar Observado">
                                   <i class="fas fa-tools"></i> Tratar
                               </button>`;
            } else {
                botonAccion = `<button class="btn btn-sm btn-primary w-100" onclick="abrirModalRevision(${lote.id_lote_wip})" title="Registrar Revisión">
                                   <i class="fas fa-check-double"></i> Revisar
                               </button>`;
            }
        }

        return `
            <tr>
                <td><strong>${lote.codigo_lote}</strong></td>
                <td>${formatDateToLocal(lote.fecha_inicio)}</td>
                <td><small>${lote.descripcion_completa}</small></td>
                <td>${cantFormat}</td>
                <td><span class="badge" style="background:${areaColor}; color:${areaTextColor};">${lote.area_actual_nombre}</span></td>
                <td>${badgeRevisado}</td>
                <td class="text-center px-2">
                    ${botonAccion}
                </td>
            </tr>
        `;
    }).join('');
}

async function separarLote(idRevision, codigoLote) {
    if (!confirm(`¿Está seguro de separar el lote ${codigoLote} basado en su revisión parcial?`)) return;
    
    try {
        const response = await fetch(`${baseUrl}/api/revisado_crudo.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'procesar_split_revision', id_revision: idRevision })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(`✅ ${data.message}`);
            cargarPendientes();
        } else {
            alert(`❌ Error: ${data.message}`);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error de red al intentar procesar la división del lote.');
    }
}

async function recibirLote(idLote, codigoLote) {
    if (!confirm(`¿Está seguro de recibir el lote ${codigoLote} en el área de Revisado Crudo?`)) return;
    
    try {
        const response = await fetch(`${baseUrl}/api/revisado_crudo.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'recibir_lote', id_lote_wip: idLote })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(`✅ Lote ${codigoLote} recibido exitosamente en Revisado Crudo.`);
            cargarPendientes();
        } else {
            alert(`❌ Error: ${data.message}`);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error de red al intentar procesar la solicitud.');
    }
}

async function abrirModalRevision(idLote) {
    document.getElementById('formRevision').reset();
    document.getElementById('revHistorialContainer').style.display = 'none';
    
    try {
        const response = await fetch(`${baseUrl}/api/revisado_crudo.php?action=detalle_lote&id_lote_wip=${idLote}`);
        const data = await response.json();
        
        if (data.success) {
            const lote = data.data.lote;
            const revisiones = data.data.revisiones;
            
            document.getElementById('revIdLote').value = lote.id_lote_wip;
            document.getElementById('revLoteCodigo').textContent = lote.codigo_lote;
            document.getElementById('revLoteProducto').textContent = lote.descripcion_completa;
            document.getElementById('revLoteCantidad').textContent = `${lote.cantidad_docenas} doc | ${String(lote.cantidad_unidades).padStart(2, '0')} uni`;
            
            // Sugerir revisar el saldo total actual del lote
            document.getElementById('revRevisadoDoc').value = lote.cantidad_docenas;
            document.getElementById('revRevisadoUni').value = lote.cantidad_unidades;
            
            // Cargar historial con operarias
            if (revisiones && revisiones.length > 0) {
                document.getElementById('revHistorialContainer').style.display = 'block';
                const tbody = document.getElementById('bodyHistorialRevisiones');
                
                let html = '';
                revisiones.forEach(r => {
                    html += `
                    <tr>
                        <td><small>${formatDateToLocal(r.fecha_revision)}</small></td>
                        <td><small>${r.usuario_nombre || 'Sistema'}</small></td>
                        <td>${r.cantidad_revisada_docenas} | ${String(r.cantidad_revisada_unidades).padStart(2,'0')}</td>
                        <td class="text-success">${r.cantidad_aprobada_docenas} | ${String(r.cantidad_aprobada_unidades).padStart(2,'0')}</td>
                        <td class="text-danger">${r.cantidad_observada_docenas} | ${String(r.cantidad_observada_unidades).padStart(2,'0')}</td>
                        <td>${getBadgeRevision(r.resultado)}</td>
                    </tr>`;
                    
                    if(r.operarias && r.operarias.length > 0) {
                        html += `<tr><td colspan="6" class="p-0 border-0">
                                   <div class="bg-light p-2 mb-2 ml-4 mr-4 text-muted" style="font-size:0.85em; border-left:3px solid #ccc;">
                                     <strong><i class="fas fa-users"></i> Operarias:</strong><br>
                                     ${r.operarias.map(o => `&bull; ${o.nombre_completo}: Rev (${o.cantidad_revisada_docenas}|${o.cantidad_revisada_unidades}) - Apr (${o.cantidad_aprobada_docenas}|${o.cantidad_aprobada_unidades}) - Obs (${o.cantidad_observada_docenas}|${o.cantidad_observada_unidades}) ${o.observaciones ? ' <i>['+o.observaciones+']</i>' : ''}`).join('<br>')}
                                   </div>
                                 </td></tr>`;
                    }
                });
                tbody.innerHTML = html;
            }
            
            document.getElementById('bodyOperarias').innerHTML = '';
            calcTotales();
            
            document.getElementById('modalRevision').style.display = 'block';
        } else {
            alert(`Error: ${data.message}`);
        }
    } catch (error) {
        console.error(error);
        alert('Error al cargar detalle del lote');
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

async function guardarRevision() {
    const idLote = document.getElementById('revIdLote').value;
    
    // parse operarias
    const operariasArr = [];
    document.querySelectorAll('.row-operaria').forEach(tr => {
        const idOp = parseInt(tr.querySelector('.sel-op').value || 0);
        if(idOp > 0) {
            operariasArr.push({
                id_operaria: idOp,
                cantidad_revisada_docenas: parseInt(tr.querySelector('.r-doc').value || 0),
                cantidad_revisada_unidades: parseInt(tr.querySelector('.r-uni').value || 0),
                cantidad_aprobada_docenas: parseInt(tr.querySelector('.a-doc').value || 0),
                cantidad_aprobada_unidades: parseInt(tr.querySelector('.a-uni').value || 0),
                cantidad_observada_docenas: parseInt(tr.querySelector('.o-doc').value || 0),
                cantidad_observada_unidades: parseInt(tr.querySelector('.o-uni').value || 0),
                observaciones: tr.querySelector('.obs-op').value
            });
        }
    });

    const payload = {
        action: 'registrar_revision',
        id_lote_wip: parseInt(idLote),
        cantidad_revisada_docenas: parseInt(document.getElementById('revRevisadoDoc').value || 0),
        cantidad_revisada_unidades: parseInt(document.getElementById('revRevisadoUni').value || 0),
        cantidad_aprobada_docenas: parseInt(document.getElementById('revAprobadoDoc').value || 0),
        cantidad_aprobada_unidades: parseInt(document.getElementById('revAprobadoUni').value || 0),
        cantidad_observada_docenas: parseInt(document.getElementById('revObservadoDoc').value || 0),
        cantidad_observada_unidades: parseInt(document.getElementById('revObservadoUni').value || 0),
        observaciones: document.getElementById('revObservaciones').value,
        operarias: operariasArr
    };
    
    if (operariasArr.length === 0) {
        alert("Debe agregar al menos una operaria.");
        return;
    }
    
    // validación básica cliente
    const totalRev = payload.cantidad_revisada_docenas*12 + payload.cantidad_revisada_unidades;
    const totalApr = payload.cantidad_aprobada_docenas*12 + payload.cantidad_aprobada_unidades;
    const totalObs = payload.cantidad_observada_docenas*12 + payload.cantidad_observada_unidades;
    
    if (totalRev <= 0) {
        alert("La cantidad revisada debe ser mayor a cero.");
        return;
    }
    if (totalApr + totalObs > totalRev) {
        alert("La suma de aprobado y observado no puede superar la cantidad revisada.");
        return;
    }
    
    try {
        const response = await fetch(`${baseUrl}/api/revisado_crudo.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert("✅ Revisión registrada correctamente.");
            closeModal('modalRevision');
            cargarPendientes();
        } else {
            alert(`❌ Error: ${data.message}`);
        }
    } catch(err) {
        console.error(err);
        alert('Error de red al intentar guardar.');
    }
}

let listaOperariasCache = [];
async function cargarListaOperarias() {
    if(listaOperariasCache.length > 0) return listaOperariasCache;
    try {
        const res = await fetch(`${baseUrl}/api/revisado_crudo.php?action=listar_operarias_revisado`);
        const data = await res.json();
        if(data.success) { listaOperariasCache = data.data; return data.data; }
    } catch(e){}
    return [];
}

function calcTotales() {
    let srDoc=0, srUni=0, saDoc=0, saUni=0, soDoc=0, soUni=0;
    document.querySelectorAll('.row-operaria').forEach(tr => {
        srDoc += parseInt(tr.querySelector('.r-doc').value||0); srUni += parseInt(tr.querySelector('.r-uni').value||0);
        saDoc += parseInt(tr.querySelector('.a-doc').value||0); saUni += parseInt(tr.querySelector('.a-uni').value||0);
        soDoc += parseInt(tr.querySelector('.o-doc').value||0); soUni += parseInt(tr.querySelector('.o-uni').value||0);
    });
    
    let norm = (d, u) => { d += Math.floor(u/12); u = u%12; return {d,u}; };
    let sumRev = norm(srDoc, srUni); let sumApr = norm(saDoc, saUni); let sumObs = norm(soDoc, soUni);
    
    document.getElementById('sumOpRev').textContent = `${sumRev.d} | ${sumRev.u}`;
    document.getElementById('sumOpApr').textContent = `${sumApr.d} | ${sumApr.u}`;
    document.getElementById('sumOpObs').textContent = `${sumObs.d} | ${sumObs.u}`;
    
    let crDoc=parseInt(document.getElementById('revRevisadoDoc').value||0), crUni=parseInt(document.getElementById('revRevisadoUni').value||0);
    let caDoc=parseInt(document.getElementById('revAprobadoDoc').value||0), caUni=parseInt(document.getElementById('revAprobadoUni').value||0);
    let coDoc=parseInt(document.getElementById('revObservadoDoc').value||0), coUni=parseInt(document.getElementById('revObservadoUni').value||0);
    let cabRev = norm(crDoc, crUni); let cabApr = norm(caDoc, caUni); let cabObs = norm(coDoc, coUni);
    
    document.getElementById('totCabRev').textContent = `${cabRev.d} | ${cabRev.u}`;
    document.getElementById('totCabApr').textContent = `${cabApr.d} | ${cabApr.u}`;
    document.getElementById('totCabObs').textContent = `${cabObs.d} | ${cabObs.u}`;
    
    let match = (sumRev.d===cabRev.d && sumRev.u===cabRev.u && sumApr.d===cabApr.d && sumApr.u===cabApr.u && sumObs.d===cabObs.d && sumObs.u===cabObs.u);
    let hasOps = document.querySelectorAll('.row-operaria').length > 0;
    document.getElementById('alertOperarias').style.display = (match || !cabRev.d && !cabRev.u && !hasOps) ? 'none' : 'block';
}

async function agregarFilaOperaria() {
    const ops = await cargarListaOperarias();
    const opsHtml = ops.map(o => `<option value="${o.id_operaria}">${o.nombre_completo}</option>`).join('');
    const tr = document.createElement('tr');
    tr.className = 'row-operaria';
    tr.innerHTML = `
        <td><select class="form-control form-control-sm sel-op" required><option value="">--Seleccionar--</option>${opsHtml}</select></td>
        <td>
            <div class="input-group input-group-sm">
                <input type="number" class="form-control r-doc" placeholder="d" min="0" oninput="calcTotales()">
                <input type="number" class="form-control r-uni" placeholder="u" min="0" oninput="calcTotales()">
            </div>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <input type="number" class="form-control a-doc" placeholder="d" min="0" oninput="calcTotales()">
                <input type="number" class="form-control a-uni" placeholder="u" min="0" oninput="calcTotales()">
            </div>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <input type="number" class="form-control o-doc" placeholder="d" min="0" oninput="calcTotales()">
                <input type="number" class="form-control o-uni" placeholder="u" min="0" oninput="calcTotales()">
            </div>
        </td>
        <td><input type="text" class="form-control form-control-sm obs-op" placeholder="Obs"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="this.closest('tr').remove(); calcTotales()"><i class="fas fa-times"></i></button></td>
    `;
    document.getElementById('bodyOperarias').appendChild(tr);
    calcTotales();
}

function getBadgeRevision(estado) {
    switch(estado) {
        case 'NO_REVISADO': return '<span class="badge badge-secondary">NO REVISADO</span>';
        case 'EN_REVISION': return '<span class="badge badge-warning text-dark">EN REVISIÓN</span>';
        case 'APROBADO': return '<span class="badge badge-success">APROBADO</span>';
        case 'OBSERVADO': return '<span class="badge badge-danger">OBSERVADO</span>';
        case 'PARCIAL': return '<span class="badge badge-info">PARCIAL</span>';
        default: return estado;
    }
}

function formatDateToLocal(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString();
}

let areasDestinoCache = null;

async function cargarAreasDestino() {
    if (areasDestinoCache !== null) return areasDestinoCache;
    try {
        const response = await fetch(`${baseUrl}/api/revisado_crudo.php?action=listar_areas_destino`);
        const data = await response.json();
        if (data.success) {
            areasDestinoCache = data.data;
            return areasDestinoCache;
        }
    } catch (e) { console.error(e); }
    return [];
}

async function abrirModalEnviar(idLote, codigoLote, cantidad) {
    document.getElementById('envIdLote').value = idLote;
    document.getElementById('envLoteCodigo').textContent = codigoLote;
    document.getElementById('envLoteCantidad').textContent = cantidad;
    
    const select = document.getElementById('envAreaDestino');
    select.innerHTML = '<option value="">Cargando áreas...</option>';
    
    const areas = await cargarAreasDestino();
    
    if (areas.length > 0) {
        select.innerHTML = '<option value="">-- Seleccione área destino --</option>' + areas.map(a => `<option value="${a.id_area}">${a.nombre}</option>`).join('');
    } else {
        select.innerHTML = '<option value="">No hay áreas destino disponibles</option>';
    }
    
    document.getElementById('modalEnviarLote').style.display = 'block';
}

async function confirmarEnvioLote() {
    const idLote = document.getElementById('envIdLote').value;
    const idAreaDestino = document.getElementById('envAreaDestino').value;
    
    if (!idAreaDestino) {
        alert("Seleccione un área destino.");
        return;
    }
    
    try {
        const response = await fetch(`${baseUrl}/api/revisado_crudo.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'enviar_siguiente_proceso', 
                id_lote_wip: parseInt(idLote),
                id_area_destino: parseInt(idAreaDestino)
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(`✅ ${data.message}`);
            closeModal('modalEnviarLote');
            cargarPendientes();
        } else {
            alert(`❌ Error: ${data.message}`);
        }
    } catch(err) {
        console.error(err);
        alert('Error de red al intentar enviar el lote.');
    }
}

let areasReprocesoCache = null;

async function cargarAreasReproceso() {
    if (areasReprocesoCache !== null) return areasReprocesoCache;
    try {
        const response = await fetch(`${baseUrl}/api/revisado_crudo.php?action=listar_areas_reproceso`);
        const data = await response.json();
        if (data.success) {
            areasReprocesoCache = data.data; return data.data;
        }
    } catch(e) {}
    return [];
}

function toggleAreaReproceso() {
    const dec = document.getElementById('trtDecision').value;
    document.getElementById('grpAreaReproceso').style.display = (dec === 'REPROCESO') ? 'block' : 'none';
    if (dec === 'REPROCESO') document.getElementById('trtAreaDestino').required = true;
    else document.getElementById('trtAreaDestino').required = false;
}

async function abrirModalTratar(idLote, codigoLote, cant) {
    document.getElementById('formTratarLote').reset();
    document.getElementById('trtIdLote').value = idLote;
    document.getElementById('trtLoteCodigo').textContent = codigoLote;
    document.getElementById('trtLoteCantidad').textContent = cant;
    toggleAreaReproceso();
    
    const areas = await cargarAreasReproceso();
    const sel = document.getElementById('trtAreaDestino');
    sel.innerHTML = '<option value="">-- Seleccione área --</option>' + areas.map(a => `<option value="${a.id_area}">${a.nombre}</option>`).join('');
    
    document.getElementById('modalTratarLote').style.display = 'block';
}

async function confirmarTratamiento() {
    const idLote = document.getElementById('trtIdLote').value;
    const dec = document.getElementById('trtDecision').value;
    const dest = document.getElementById('trtAreaDestino').value;
    const mot = document.getElementById('trtMotivo').value;
    const obs = document.getElementById('trtObservaciones').value;
    
    if (!dec) { alert('Seleccione decisión'); return; }
    if (dec === 'REPROCESO' && !dest) { alert('Seleccione área de reproceso'); return; }
    if (!mot.trim()) { alert('Ingrese un motivo'); return; }
    
    try {
        const response = await fetch(`${baseUrl}/api/revisado_crudo.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'procesar_observado',
                id_lote_wip: parseInt(idLote),
                decision: dec,
                id_area_destino: dest ? parseInt(dest) : null,
                motivo: mot,
                observaciones: obs
            })
        });
        const data = await response.json();
        if (data.success) {
            alert(`✅ ${data.message}`);
            closeModal('modalTratarLote');
            cargarPendientes();
        } else {
            alert(`❌ Error: ${data.message}`);
        }
    } catch(err) {
        alert('Error de red');
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
