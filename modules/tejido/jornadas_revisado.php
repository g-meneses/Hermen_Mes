<?php
/**
 * Jornadas y Cupos Revisado Crudo (PASO B1)
 * Sistema MES Hermen Ltda.
 */

require_once '../../config/database.php';

// Verificar sesión
if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Planificación Revisado Crudo';
$currentPage = 'jornadas_revisado';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Header principal c/ DatePicker -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center bg-white">
            <div>
                <h3 class="mb-1"><i class="fas fa-calendar-check text-primary"></i> Planificación Diaria</h3>
                <p class="text-muted mb-0">Gestión de jornadas y objetivos para Revisado Crudo</p>
            </div>
            <div class="d-flex align-items-center">
                <label for="fechaFiltro" class="font-weight-bold mr-2 mb-0">Fecha Operativa:</label>
                <input type="date" id="fechaFiltro" class="form-control" style="width: 160px;" value="<?php echo date('Y-m-d'); ?>" onchange="cargarDatosPlanificacion()">
                <button class="btn btn-primary ml-2" onclick="cargarDatosPlanificacion()">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Indicadores de Salud (Dashboard Cards) -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body py-1">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Operarias Asignadas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiOperarias">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body py-1">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Horas Disp.</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiHoras">0.00</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body py-1">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Docenas Objetivo</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiDocenas">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-bullseye fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body py-1">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Cupos Activos (Productos/Familias)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiCupos">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-boxes fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenido Principal: Dos Secciones en Bloques Vert/Horiz -->
    <div class="row">
        <!-- SECCIÓN 1: Jornadas -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold text-dark"><i class="fas fa-user-clock mr-2"></i> Jornadas del Día</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="abrirModalJornada()">
                        <i class="fas fa-plus"></i> Crear Jornada
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="tablaJornadas">
                            <thead class="bg-light">
                                <tr>
                                    <th>Operaria</th>
                                    <th class="text-center">Hs Programadas</th>
                                    <th class="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody id="bodyJornadas">
                                <tr><td colspan="3" class="text-center">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECCIÓN 2: Cupos -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold text-dark"><i class="fas fa-clipboard-list mr-2"></i> Cupos de Producción</h5>
                    <button class="btn btn-sm btn-outline-success" onclick="abrirModalCupo()">
                        <i class="fas fa-plus"></i> Agregar Cupo
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="tablaCupos">
                            <thead class="bg-light">
                                <tr>
                                    <th width="10%" class="text-center">Pri.</th>
                                    <th>Producto / Familia</th>
                                    <th class="text-right">Meta (Doc)</th>
                                    <th width="10%"></th>
                                </tr>
                            </thead>
                            <tbody id="bodyCupos">
                                <tr><td colspan="4" class="text-center">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <!-- SECCIÓN 3: Planificación por Operaria -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold text-dark"><i class="fas fa-tasks mr-2"></i> Planificación por Operaria</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" id="tablaPlanes">
                            <thead class="bg-light">
                                <tr>
                                    <th>Operaria</th>
                                    <th class="text-center">Hs Programadas</th>
                                    <th class="text-center">Hs Planificadas</th>
                                    <th class="text-center">Hs Restantes</th>
                                    <th class="text-center">Estado Jornada</th>
                                    <th>Líneas de Trabajo</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="bodyPlanes">
                                <tr><td colspan="7" class="text-center">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear Jornada -->
<div id="modalAddJornada" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header d-flex justify-content-between align-items-center">
            <h5 class="modal-title m-0">Aperturar Jornada Diaria</h5>
            <span class="close" onclick="closeModal('modalAddJornada')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formJornada" onsubmit="event.preventDefault(); guardarJornada();">
                <div class="form-group mb-3">
                    <label class="font-weight-bold">Fecha Planificada:</label>
                    <input type="text" id="jorFechaVisual" class="form-control font-weight-bold text-primary" readonly disabled>
                </div>
                <div class="form-group mb-4">
                    <label class="font-weight-bold">Seleccione Operaria (Revisora):</label>
                    <select id="jorIdOperaria" class="form-control" required>
                        <option value="">Cargando operarias...</option>
                    </select>
                </div>
                <!-- Callout warning -->
                <div class="alert alert-info py-2 px-3" style="font-size: 0.9em;">
                    <i class="fas fa-info-circle"></i> El sistema calculará automáticamente las horas hábiles de la operaria de acuerdo al día de la semana.
                </div>
                <hr>
                <div class="text-right">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalAddJornada')">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarJornada">Aperturar Jornada</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Agregar Cupo -->
<div id="modalAddCupo" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header d-flex justify-content-between align-items-center">
            <h5 class="modal-title m-0">Asignar Cupo Foco</h5>
            <span class="close" onclick="closeModal('modalAddCupo')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formCupo" onsubmit="event.preventDefault(); guardarCupo();">
                <input type="hidden" id="cupoId" value="">
                <div class="form-group mb-3">
                    <label class="font-weight-bold">Opciones de Foco:</label>
                    <div class="custom-control custom-radio mb-2">
                        <input type="radio" id="focoProducto" name="tipoFoco" value="prod" class="custom-control-input" onchange="toggleFocoInputs()" checked>
                        <label class="custom-control-label" for="focoProducto">Producto Específico</label>
                    </div>
                    <div class="custom-control custom-radio">
                        <input type="radio" id="focoFamilia" name="tipoFoco" value="fam" class="custom-control-input" onchange="toggleFocoInputs()">
                        <label class="custom-control-label" for="focoFamilia">General por Familia/Línea</label>
                    </div>
                </div>

                <div class="form-group mb-3" id="groupProducto">
                    <label>Producto:</label>
                    <select id="cupoProducto" class="form-control">
                        <option value="">Seleccione Producto...</option>
                    </select>
                </div>

                <div class="form-group mb-3" id="groupFamilia" style="display:none;">
                    <label>Familia / Línea:</label>
                    <select id="cupoFamilia" class="form-control">
                        <option value="">Seleccione Familia...</option>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group mb-4">
                        <label class="font-weight-bold text-success">Docenas Meta:</label>
                        <input type="number" id="cupoDocenas" class="form-control" min="1" required placeholder="Ej: 150">
                    </div>
                    <div class="col-md-6 form-group mb-4">
                        <label class="font-weight-bold text-info">Horas Meta:</label>
                        <input type="number" id="cupoHoras" class="form-control" min="0.1" step="0.1" required placeholder="Ej: 8.5">
                    </div>
                    <div class="col-md-12 form-group mb-4">
                        <label class="font-weight-bold">Prioridad:</label>
                        <select id="cupoPrioridad" class="form-control">
                            <option value="1">1 - Crítica</option>
                            <option value="2" selected>2 - Normal</option>
                            <option value="3">3 - Baja</option>
                        </select>
                    </div>
                </div>

                <hr>
                <div class="text-right">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalAddCupo')">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnGuardarCupo">Registrar Cupo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Agregar Plan -->
<div id="modalAddPlan" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header d-flex justify-content-between align-items-center">
            <h5 class="modal-title m-0">Asignar Línea de Trabajo a Operaria</h5>
            <span class="close" onclick="closeModal('modalAddPlan')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="alert alert-secondary py-2 px-3 mb-3">
                <strong>Operaria:</strong> <span id="planOpNombre" class="text-primary font-weight-bold"></span><br>
                <strong>Fecha:</strong> <span id="planFechaSpan"></span><br>
                <div class="d-flex justify-content-between mt-2">
                    <small>Prog: <b id="planHsProg">0</b> hs</small>
                    <small>Plan: <b id="planHsPlan">0</b> hs</small>
                    <small>Resto: <b id="planHsRest" class="text-success">0</b> hs</small>
                </div>
            </div>

            <form id="formPlan" onsubmit="event.preventDefault(); guardarPlan();">
                <input type="hidden" id="planIdJornada" value="">
                <input type="hidden" id="planIdOperaria" value="">
                
                <div class="form-group mb-3">
                    <label class="font-weight-bold">Opciones de Foco:</label>
                    <div class="custom-control custom-radio mb-2">
                        <input type="radio" id="planFocoProducto" name="planTipoFoco" value="PRODUCTO" class="custom-control-input" onchange="togglePlanFocoInputs()" checked>
                        <label class="custom-control-label" for="planFocoProducto">Producto Específico</label>
                    </div>
                    <div class="custom-control custom-radio">
                        <input type="radio" id="planFocoFamilia" name="planTipoFoco" value="FAMILIA" class="custom-control-input" onchange="togglePlanFocoInputs()">
                        <label class="custom-control-label" for="planFocoFamilia">General por Familia/Línea</label>
                    </div>
                </div>

                <div class="form-group mb-3" id="planGroupProducto">
                    <label>Producto:</label>
                    <select id="planProducto" class="form-control" onchange="autoCalcPlanDocenas()">
                        <option value="">Seleccione Producto...</option>
                    </select>
                </div>

                <div class="form-group mb-3" id="planGroupFamilia" style="display:none;">
                    <label>Familia / Línea:</label>
                    <select id="planFamilia" class="form-control" onchange="autoCalcPlanDocenas()">
                        <option value="">Seleccione Familia...</option>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group mb-3">
                        <label class="font-weight-bold text-info">Hs a Asignar:</label>
                        <input type="number" id="planHoras" class="form-control" min="0.1" step="0.1" required placeholder="Ej: 4" oninput="autoCalcPlanDocenas()">
                    </div>
                    <div class="col-md-4 form-group mb-3">
                        <label class="font-weight-bold text-success">Docenas Objetivas:</label>
                        <input type="number" id="planDocenas" class="form-control" min="0" required placeholder="Ej: 50">
                        <small class="form-text text-muted" style="font-size: 0.7em;">Auto-sugerido s/ Cupo</small>
                    </div>
                    <div class="col-md-4 form-group mb-3">
                        <label class="font-weight-bold">Prioridad:</label>
                        <select id="planPrioridad" class="form-control">
                            <option value="1">1 - Crítica</option>
                            <option value="2" selected>2 - Normal</option>
                            <option value="3">3 - Baja</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label>Observaciones (Opcional):</label>
                    <input type="text" id="planObs" class="form-control" maxlength="255">
                </div>

                <hr>
                <div class="text-right">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalAddPlan')">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarPlan">Agregar Línea</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Registrar Ejecución -->
<div id="modalAddEjecucion" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header d-flex justify-content-between align-items-center">
            <h5 class="modal-title m-0">Reportar Ejecución Diaria</h5>
            <span class="close" onclick="closeModal('modalAddEjecucion')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="alert alert-info py-2 px-3 mb-3">
                <strong>Operaria:</strong> <span id="ejecOpNombre"></span><br>
                <strong>Objetivo Línea:</strong> <span id="ejecObjNombre" class="font-weight-bold"></span> 
                (<span id="ejecObjDocenas"></span> doc)
            </div>

            <form id="formEjecucion" onsubmit="event.preventDefault(); guardarEjecucion();">
                <input type="hidden" id="ejecIdPlan" value="">
                
                <div class="row">
                    <div class="col-md-12 form-group mb-3">
                        <label class="font-weight-bold text-info">Horas Reales Trabajadas:</label>
                        <input type="number" id="ejecHoras" class="form-control" min="0.1" step="0.1" required placeholder="Ej: 2.5">
                    </div>
                </div>

                <div class="row bg-light p-2 mb-3 rounded border">
                    <div class="col-md-12"><label class="font-weight-bold">Desglose de Producción:</label></div>
                    
                    <div class="col-md-4 form-group mb-2">
                        <label class="small font-weight-bold">Primera (1ra):</label>
                        <div class="input-group input-group-sm">
                            <input type="number" id="ejecDcz1" class="form-control" placeholder="Dcz" min="0" oninput="calcSumExec()">
                            <input type="number" id="ejecUnd1" class="form-control" placeholder="Und" min="0" oninput="calcSumExec()">
                        </div>
                    </div>
                    
                    <div class="col-md-4 form-group mb-2">
                        <label class="small font-weight-bold">Segunda (2da):</label>
                        <div class="input-group input-group-sm">
                            <input type="number" id="ejecDcz2" class="form-control" placeholder="Dcz" min="0" oninput="calcSumExec()">
                            <input type="number" id="ejecUnd2" class="form-control" placeholder="Und" min="0" oninput="calcSumExec()">
                        </div>
                    </div>

                    <div class="col-md-4 form-group mb-2">
                        <label class="small font-weight-bold text-danger">Merma:</label>
                        <div class="input-group input-group-sm">
                            <input type="number" id="ejecDczM" class="form-control border-danger" placeholder="Dcz" min="0">
                            <input type="number" id="ejecUndM" class="form-control border-danger" placeholder="Und" min="0">
                        </div>
                    </div>

                    <div class="col-md-12 mt-2">
                        <div class="alert alert-secondary py-1 px-2 mb-0" style="font-size: 0.85rem;">
                            <strong>Total Computable:</strong> <span id="ejecTotalComp" class="font-weight-bold text-primary">0</span> docenas
                            <br><small class="text-muted">(Suma de Primera + Segunda. La merma no computa para el cupo).</small>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="font-weight-bold text-secondary">Lotes Asociados (Opcional):</label>
                    <select id="ejecLotes" class="form-control" multiple style="height: 120px;">
                        <!-- Se cargarán vía JS -->
                    </select>
                    <small class="form-text text-muted">Mantenga presionado Ctrl (o Cmd) para seleccionar varios lotes. Automáticamente filtramos por el producto/familia de la línea.</small>
                </div>
                
                <div class="form-group mb-3">
                    <label>Observaciones:</label>
                    <input type="text" id="ejecObs" class="form-control" maxlength="255">
                </div>

                <hr>
                <div class="text-right">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalAddEjecucion')">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarEjecucion">Registrar Ejecución</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let operariasCache = [];
let datosCuposCache = null;

document.addEventListener('DOMContentLoaded', () => {
    cargarDatosPlanificacion();
    cargarCatalogos();
});

// Cargar UI
async function cargarDatosPlanificacion() {
    const fecha = document.getElementById('fechaFiltro').value;
    if(!fecha) return;

    document.getElementById('bodyJornadas').innerHTML = '<tr><td colspan="3" class="text-center"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    document.getElementById('bodyCupos').innerHTML = '<tr><td colspan="4" class="text-center"><div class="spinner-border spinner-border-sm text-success"></div></td></tr>';
    document.getElementById('bodyPlanes').innerHTML = '<tr><td colspan="7" class="text-center"><div class="spinner-border spinner-border-sm text-secondary"></div></td></tr>';

    try {
        // Fetch jornadas
        const resJor = await fetch(`${window.baseUrl}/api/revisado_crudo.php?action=listar_jornadas&fecha=${fecha}`);
        const dataJor = await resJor.json();
        
        // Fetch cupos
        const resCup = await fetch(`${window.baseUrl}/api/revisado_crudo.php?action=listar_cupos&fecha=${fecha}`);
        const dataCup = await resCup.json();
        window.cuposDelDia = dataCup.success ? dataCup.data : [];

        // Fetch planes
        const resPlan = await fetch(`${window.baseUrl}/api/revisado_crudo.php?action=listar_plan_operaria&fecha=${fecha}`);
        const dataPlan = await resPlan.json();

        // Render Jornadas & Planes
        let htmlJor = '';
        let htmlPlanes = '';
        let totalHs = 0;
        
        const planesPorJornada = {};
        if(dataPlan.success) {
            dataPlan.data.forEach(p => {
                if(!planesPorJornada[p.id_jornada]) planesPorJornada[p.id_jornada] = [];
                planesPorJornada[p.id_jornada].push(p);
            });
        }

        if(dataJor.success && dataJor.data.length > 0) {
            htmlJor = dataJor.data.map(j => {
                totalHs += parseFloat(j.horas_programadas);
                let badge = j.estado === 'ABIERTA' ? '<span class="badge badge-success">ABIERTA</span>' : '<span class="badge badge-secondary">CERRADA</span>';
                return `
                <tr>
                    <td class="font-weight-bold">${j.operaria_nombre}</td>
                    <td class="text-center">${j.horas_programadas} hs</td>
                    <td class="text-center">${badge}</td>
                </tr>`;
            }).join('');
            
            htmlPlanes = dataJor.data.map(j => {
                const misPlanes = planesPorJornada[j.id_jornada] || [];
                const hsPlan = misPlanes.reduce((acc, vp) => acc + parseFloat(vp.horas_planificadas), 0);
                const hsRest = parseFloat(j.horas_programadas) - hsPlan;
                
                let colorRest = hsRest == 0 ? 'success' : (hsRest > 0 ? 'warning' : 'danger');
                let badgeJor = j.estado === 'ABIERTA' ? '<span class="badge badge-success">ABIERTA</span>' : '<span class="badge badge-secondary">CERRADA</span>';
                
                let lineasHtml = '';
                if(misPlanes.length === 0) {
                    lineasHtml = '<small class="text-muted">Sin líneas asignadas</small>';
                } else {
                    lineasHtml = misPlanes.map(p => {
                        let objStr = p.id_producto ? p.producto_codigo : p.familia_nombre;
                        
                        // Estado Linea
                        let badgeEst = '';
                        switch(p.estado_linea) {
                            case 'PENDIENTE': badgeEst = '<span class="badge badge-secondary" style="font-size:0.7rem;">PEND</span>'; break;
                            case 'EN_PROCESO': badgeEst = '<span class="badge badge-primary" style="font-size:0.7rem;">PROCESO</span>'; break;
                            case 'PARCIAL': badgeEst = '<span class="badge badge-warning" style="font-size:0.7rem;">PARCIAL</span>'; break;
                            case 'CUMPLIDA': badgeEst = '<span class="badge badge-success" style="font-size:0.7rem;">CUMPLIDA</span>'; break;
                            default: badgeEst = '<span class="badge badge-secondary" style="font-size:0.7rem;">PEND</span>'; break;
                        }

                        // Subtabla de ejecuciones
                        let ejecsHtml = '';
                        if(p.ejecuciones && p.ejecuciones.length > 0) {
                            let filas = p.ejecuciones.map(e => `
                                <div class="d-flex justify-content-between text-muted" style="font-size: 0.75rem; padding-left: 15px; border-left: 2px solid #ddd; margin-left: 5px; margin-top:2px;">
                                    <span>↳ ${e.horas_reales} hs | <b>1ra: ${e.dcz_primera} | 2da: ${e.dcz_segunda} | <span class="text-danger">M: ${e.dcz_merma} dcz</span></b></span>
                                    <span title="Registrado por: ${e.usuario_registra}">${e.fecha_ejecucion}</span>
                                </div>
                            `).join('');
                            ejecsHtml = `<div class="mt-1 mb-2">${filas}</div>`;
                        }

                        // Progreso acumulado
                        let progText = '';
                        if(parseFloat(p.horas_reales_acumuladas) > 0 || parseFloat(p.docenas_exactas_acumuladas) > 0) {
                            progText = `<br><small class="text-muted">
                                Acumulado Real: ${parseFloat(p.horas_reales_acumuladas)} hs / 
                                <b class="text-info">${p.docenas_exactas_acumuladas} doc computables</b> 
                                (1ra: ${p.dcz_primera_acum} | 2da: ${p.dcz_segunda_acum} | <span class="text-danger">Merma: ${p.dcz_merma_acum} dcz</span>)
                            </small>`;
                        }

                        return `<div class="py-2 border-bottom">
                            <div class="d-flex justify-content-between align-items-center text-sm">
                                <span>
                                    ${badgeEst} <strong>${objStr}</strong> 
                                    (Meta: <b class="text-success">${p.docenas_objetivo} doc</b> | ${parseFloat(p.horas_planificadas)} hs)
                                    ${progText}
                                </span>
                                <div>
                                    <button class="btn btn-sm btn-outline-success py-0 px-1 ml-1" onclick="abrirModalEjecucion(${p.id_plan}, '${j.operaria_nombre}', '${objStr}', ${p.docenas_objetivo}, ${p.id_producto || 'null'}, ${p.id_familia || 'null'})" title="Registrar Ejecución Real"><i class="fas fa-play"></i> Ejecutar</button>
                                    <button class="btn btn-sm text-danger ml-1 p-0" onclick="eliminarPlan(${p.id_plan})" title="Eliminar Plan"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                            ${ejecsHtml}
                        </div>`;
                    }).join('');
                }
                
                let btnPlanificar = j.estado === 'ABIERTA' ? 
                    `<button class="btn btn-sm btn-outline-primary" onclick="abrirModalPlan(${j.id_jornada}, ${j.id_operaria}, '${j.operaria_nombre}','${j.horas_programadas}','${hsPlan}')" ${hsRest <= 0 ? 'disabled' : ''}><i class="fas fa-plus"></i> Asignar</button>` : '';

                return `
                <tr>
                    <td class="align-middle font-weight-bold p-2">${j.operaria_nombre}</td>
                    <td class="text-center align-middle p-2">${parseFloat(j.horas_programadas).toFixed(2)} hs</td>
                    <td class="text-center align-middle p-2">${hsPlan.toFixed(2)} hs</td>
                    <td class="text-center align-middle p-2"><b class="text-${colorRest}">${hsRest.toFixed(2)} hs</b></td>
                    <td class="text-center align-middle p-2">${badgeJor}</td>
                    <td class="p-2">${lineasHtml}</td>
                    <td class="text-center align-middle p-2">${btnPlanificar}</td>
                </tr>`;
            }).join('');

            document.getElementById('kpiOperarias').textContent = dataJor.data.length;
            document.getElementById('kpiHoras').textContent = totalHs.toFixed(2);
        } else {
            htmlJor = '<tr><td colspan="3" class="text-center text-muted">No hay jornadas aperturadas para este día.</td></tr>';
            htmlPlanes = '<tr><td colspan="7" class="text-center text-muted">No hay planes requeridos.</td></tr>';
            document.getElementById('kpiOperarias').textContent = '0';
            document.getElementById('kpiHoras').textContent = '0.00';
        }
        document.getElementById('bodyJornadas').innerHTML = htmlJor;
        document.getElementById('bodyPlanes').innerHTML = htmlPlanes;

        // Render Cupos
        let htmlCup = '';
        let totDocenas = 0;
        if(dataCup.success && dataCup.data.length > 0) {
            htmlCup = dataCup.data.map(c => {
                totDocenas += parseInt(c.docenas_objetivo);
                let textObj = c.id_producto != null 
                              ? `<strong>${c.producto_codigo}</strong>: <br><small>${c.producto_nombre}</small>` 
                              : `Familia: <strong>${c.familia_nombre}</strong>`;
                              
                let horasText = c.horas_objetivo ? `<br><small class="text-info"><i class="fas fa-clock"></i> ${c.horas_objetivo} hs</small>` : '';
                let colorPri = c.prioridad == 1 ? 'danger' : (c.prioridad == 2 ? 'primary' : 'info');
                return `
                <tr>
                    <td class="text-center"><span class="badge badge-${colorPri}">${c.prioridad}</span></td>
                    <td>${textObj}</td>
                    <td class="text-right font-weight-bold text-success">${c.docenas_objetivo} doc${horasText}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-primary py-0" title="Editar Cupo" onclick="editarCupo(${c.id_cupo}, ${c.id_producto != null ? 1 : 0}, ${c.id_producto || 'null'}, ${c.id_familia || 'null'}, ${c.docenas_objetivo}, ${c.horas_objetivo || 'null'}, ${c.prioridad})"><i class="fas fa-edit"></i></button>
                    </td>
                </tr>`;
            }).join('');
            document.getElementById('kpiCupos').textContent = dataCup.data.length;
            document.getElementById('kpiDocenas').textContent = totDocenas;
        } else {
            htmlCup = '<tr><td colspan="4" class="text-center text-muted">Sin metas de producción registradas.</td></tr>';
            document.getElementById('kpiCupos').textContent = '0';
            document.getElementById('kpiDocenas').textContent = '0';
        }
        document.getElementById('bodyCupos').innerHTML = htmlCup;

    } catch(err) {
        console.error(err);
        alert('Error al sincronizar dashboard de planificación.');
    }
}

// Cargar selectores (operarias y catálogos de cupo)
async function cargarCatalogos() {
    try {
        const resOp = await fetch(`${window.baseUrl}/api/revisado_crudo.php?action=listar_operarias_revisado`);
        const opData = await resOp.json();
        if(opData.success) {
            operariasCache = opData.data;
            const selectOp = document.getElementById('jorIdOperaria');
            selectOp.innerHTML = '<option value="">-- Seleccione Operaria --</option>' + 
                opData.data.map(o => `<option value="${o.id_operaria}">${o.nombre_completo}</option>`).join('');
        }
        
        const resCupData = await fetch(`${window.baseUrl}/api/revisado_crudo.php?action=listar_datos_cupos`);
        const catData = await resCupData.json();
        if(catData.success) {
            datosCuposCache = catData.data;
            let optProd = '<option value="">-- Seleccione Producto --</option>' + 
                datosCuposCache.productos.map(p => `<option value="${p.id_producto}">${p.codigo_producto} - ${p.descripcion_completa}</option>`).join('');
            document.getElementById('cupoProducto').innerHTML = optProd;
            document.getElementById('planProducto').innerHTML = optProd;
            
            let optFam = '<option value="">-- Seleccione Familia --</option>' + 
                datosCuposCache.familias.map(f => `<option value="${f.id_familia}">${f.familia_nombre}</option>`).join('');
            document.getElementById('cupoFamilia').innerHTML = optFam;
            document.getElementById('planFamilia').innerHTML = optFam;
        }
    } catch(err) {
        console.error(err);
    }
}

// Lógica Formulario Jornada
function abrirModalJornada() {
    const fecha = document.getElementById('fechaFiltro').value;
    document.getElementById('formJornada').reset();
    document.getElementById('jorFechaVisual').value = formatearFechaLatina(fecha);
    document.getElementById('modalAddJornada').style.display = 'block';
}

async function guardarJornada() {
    const fecha = document.getElementById('fechaFiltro').value;
    const btn = document.getElementById('btnGuardarJornada');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    try {
        const payload = {
            action: 'crear_jornada',
            fecha: fecha,
            id_operaria: document.getElementById('jorIdOperaria').value
        };

        const res = await fetch(`${window.baseUrl}/api/revisado_crudo.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if(data.success) {
            closeModal('modalAddJornada');
            cargarDatosPlanificacion();
        } else {
            alert(`❌ Error: ${data.message}`);
        }
    } catch(err) {
        console.error(err);
        alert('Fallo de red al registrar jornada.');
    } finally {
        btn.disabled = false;
        btn.innerText = 'Aperturar Jornada';
    }
}

// Lógica Formulario Plan Operaria
function togglePlanFocoInputs() {
    const isProd = document.getElementById('planFocoProducto').checked;
    document.getElementById('planGroupProducto').style.display = isProd ? 'block' : 'none';
    document.getElementById('planGroupFamilia').style.display = isProd ? 'none' : 'block';
    if(isProd) document.getElementById('planFamilia').value = "";
    else document.getElementById('planProducto').value = "";
    autoCalcPlanDocenas();
}

function autoCalcPlanDocenas() {
    const isProd = document.getElementById('planFocoProducto').checked;
    const idProd = document.getElementById('planProducto').value;
    const idFam = document.getElementById('planFamilia').value;
    const horas = parseFloat(document.getElementById('planHoras').value) || 0;

    if(!window.cuposDelDia) return;

    let match = window.cuposDelDia.find(c => isProd ? c.id_producto == idProd : (c.id_familia == idFam && !c.id_producto));
    if(match && horas > 0) {
        let docenasPorHora = parseFloat(match.docenas_hora);
        if (!isNaN(docenasPorHora)) {
            document.getElementById('planDocenas').value = Math.round(horas * docenasPorHora);
        }
    }
}

function abrirModalPlan(idJornada, idOperaria, opNombre, hsProg, hsPlan) {
    document.getElementById('formPlan').reset();
    document.getElementById('planIdJornada').value = idJornada;
    document.getElementById('planIdOperaria').value = idOperaria;
    document.getElementById('planOpNombre').textContent = opNombre;
    document.getElementById('planFechaSpan').textContent = document.getElementById('jorFechaVisual').value || formatearFechaLatina(document.getElementById('fechaFiltro').value);
    
    document.getElementById('planHsProg').textContent = parseFloat(hsProg).toFixed(2);
    document.getElementById('planHsPlan').textContent = parseFloat(hsPlan).toFixed(2);
    let rest = parseFloat(hsProg) - parseFloat(hsPlan);
    document.getElementById('planHsRest').textContent = rest.toFixed(2);
    document.getElementById('planHoras').max = rest.toFixed(2);
    
    togglePlanFocoInputs();
    document.getElementById('modalAddPlan').style.display = 'block';
}

async function guardarPlan() {
    const fecha = document.getElementById('fechaFiltro').value;
    const btn = document.getElementById('btnGuardarPlan');
    
    const isProd = document.getElementById('planFocoProducto').checked;
    const idProd = document.getElementById('planProducto').value;
    const idFam = document.getElementById('planFamilia').value;

    if(isProd && !idProd) return alert('Debe seleccionar un producto.');
    if(!isProd && !idFam) return alert('Debe seleccionar una familia/línea.');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    try {
        const payload = {
            action: 'crear_plan_operaria',
            fecha: fecha,
            id_jornada: document.getElementById('planIdJornada').value,
            id_operaria: document.getElementById('planIdOperaria').value,
            tipo_objetivo: isProd ? 'PRODUCTO' : 'FAMILIA',
            id_producto: isProd ? idProd : null,
            id_familia: !isProd ? idFam : null,
            horas_planificadas: document.getElementById('planHoras').value,
            docenas_objetivo: document.getElementById('planDocenas').value,
            prioridad: document.getElementById('planPrioridad').value,
            observaciones: document.getElementById('planObs').value
        };

        const res = await fetch(`${window.baseUrl}/api/revisado_crudo.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (data.success) {
            closeModal('modalAddPlan');
            cargarDatosPlanificacion();
        } else {
            alert(`❌ Error: ${data.message}`);
        }
    } catch(err) {
        console.error(err);
        alert('Fallo de red al registrar planificación.');
    } finally {
        btn.disabled = false;
        btn.innerText = 'Agregar Línea';
    }
}

async function eliminarPlan(idPlan) {
    if(!confirm('¿Seguro que deseas eliminar esta asignación?')) return;
    try {
        const res = await fetch(`${window.baseUrl}/api/revisado_crudo.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'eliminar_plan_operaria', id_plan: idPlan })
        });
        const data = await res.json();
        if (data.success) {
            cargarDatosPlanificacion();
        } else alert(`❌ Error: ${data.message}`);
    } catch(err) { alert('Fallo de red al eliminar.'); }
}

function calcSumExec() {
    const d1 = parseInt(document.getElementById('ejecDcz1').value) || 0;
    const u1 = parseInt(document.getElementById('ejecUnd1').value) || 0;
    const d2 = parseInt(document.getElementById('ejecDcz2').value) || 0;
    const u2 = parseInt(document.getElementById('ejecUnd2').value) || 0;
    
    // Producción computable = (d1 + d2) + (u1 + u2) / 12
    const total = (d1 + d2) + parseFloat(((u1 + u2) / 12).toFixed(2));
    document.getElementById('ejecTotalComp').textContent = total;
}

// Lógica Formulario Ejecución (B3)
async function abrirModalEjecucion(idPlan, opNombre, objStr, docenasObj, idProd, idFam) {
    document.getElementById('formEjecucion').reset();
    document.getElementById('ejecIdPlan').value = idPlan;
    document.getElementById('ejecOpNombre').textContent = opNombre;
    document.getElementById('ejecObjNombre').textContent = objStr;
    document.getElementById('ejecObjDocenas').textContent = docenasObj;
    
    document.getElementById('ejecTotalComp').textContent = "0";

    // Cargar lotes disponibles
    document.getElementById('ejecLotes').innerHTML = '<option disabled>Cargando lotes...</option>';
    document.getElementById('modalAddEjecucion').style.display = 'block';

    try {
        let url = `${window.baseUrl}/api/revisado_crudo.php?action=listar_lotes_disponibles_para_linea`;
        if (idProd != null && idProd !== 'null') url += `&id_producto=${idProd}`;
        if (idFam != null && idFam !== 'null') url += `&id_familia=${idFam}`;

        const res = await fetch(url);
        const data = await res.json();

        if (data.success && data.data.length > 0) {
            document.getElementById('ejecLotes').innerHTML = data.data.map(l => 
                `<option value="${l.id_lote_wip}">${l.codigo_barras} - ${l.producto_nombre} (${l.cantidad_docenas} doc)</option>`
            ).join('');
        } else {
            document.getElementById('ejecLotes').innerHTML = '<option disabled value="">No hay lotes en WIP Crudo compatibles</option>';
        }
    } catch (err) {
        document.getElementById('ejecLotes').innerHTML = '<option disabled>Error de red al buscar lotes</option>';
    }
}

async function guardarEjecucion() {
    const btn = document.getElementById('btnGuardarEjecucion');
    const loteSelect = document.getElementById('ejecLotes');
    const lotesAsociados = Array.from(loteSelect.selectedOptions).map(opt => opt.value).filter(val => val !== "");

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    try {
        const payload = {
            action: 'registrar_ejecucion_linea',
            id_plan: document.getElementById('ejecIdPlan').value,
            fecha_ejecucion: document.getElementById('fechaFiltro').value,
            horas_reales: document.getElementById('ejecHoras').value,
            
            dcz_primera: document.getElementById('ejecDcz1').value,
            und_primera: document.getElementById('ejecUnd1').value,
            dcz_segunda: document.getElementById('ejecDcz2').value,
            und_segunda: document.getElementById('ejecUnd2').value,
            dcz_merma: document.getElementById('ejecDczM').value,
            und_merma: document.getElementById('ejecUndM').value,

            lotes_asociados: lotesAsociados,
            observaciones: document.getElementById('ejecObs').value
        };

        const res = await fetch(`${window.baseUrl}/api/revisado_crudo.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (data.success) {
            closeModal('modalAddEjecucion');
            cargarDatosPlanificacion(); // Refresca ui completa (estados y acumulados)
        } else {
            alert(`❌ Error: ${data.message}`);
        }
    } catch(err) {
        alert('Fallo de red al registrar ejecución.');
    } finally {
        btn.disabled = false;
        btn.innerText = 'Registrar Ejecución';
    }
}

// Lógica Formulario Cupo
function toggleFocoInputs() {
    const isProd = document.getElementById('focoProducto').checked;
    document.getElementById('groupProducto').style.display = isProd ? 'block' : 'none';
    document.getElementById('groupFamilia').style.display = isProd ? 'none' : 'block';
    if(isProd) document.getElementById('cupoFamilia').value = "";
    else document.getElementById('cupoProducto').value = "";
}

function abrirModalCupo() {
    document.getElementById('formCupo').reset();
    document.getElementById('cupoId').value = "";
    document.getElementById('focoProducto').disabled = false;
    document.getElementById('focoFamilia').disabled = false;
    document.getElementById('cupoProducto').disabled = false;
    document.getElementById('cupoFamilia').disabled = false;
    toggleFocoInputs();
    document.getElementById('modalAddCupo').style.display = 'block';
}

function editarCupo(id, isProd, idProd, idFam, docenas, horas, prioridad) {
    document.getElementById('formCupo').reset();
    document.getElementById('cupoId').value = id;
    
    if (isProd) {
        document.getElementById('focoProducto').checked = true;
        document.getElementById('cupoProducto').value = idProd;
    } else {
        document.getElementById('focoFamilia').checked = true;
        document.getElementById('cupoFamilia').value = idFam;
    }
    
    toggleFocoInputs();
    
    // Disable changing product/family when editing
    document.getElementById('focoProducto').disabled = true;
    document.getElementById('focoFamilia').disabled = true;
    document.getElementById('cupoProducto').disabled = true;
    document.getElementById('cupoFamilia').disabled = true;
    
    document.getElementById('cupoDocenas').value = docenas;
    document.getElementById('cupoHoras').value = horas !== null ? horas : "";
    document.getElementById('cupoPrioridad').value = prioridad;
    
    document.getElementById('modalAddCupo').style.display = 'block';
}

async function guardarCupo() {
    const fecha = document.getElementById('fechaFiltro').value;
    const btn = document.getElementById('btnGuardarCupo');
    const idCupo = document.getElementById('cupoId').value;
    
    const isProd = document.getElementById('focoProducto').checked;
    const idProd = document.getElementById('cupoProducto').value;
    const idFam = document.getElementById('cupoFamilia').value;

    if(!idCupo) {
        if(isProd && !idProd) return alert('Debe seleccionar un producto.');
        if(!isProd && !idFam) return alert('Debe seleccionar una familia/línea.');
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    try {
        const payload = {
            action: idCupo ? 'actualizar_cupo' : 'registrar_cupo',
            fecha: fecha,
            id_cupo: idCupo,
            id_producto: isProd ? idProd : null,
            id_familia: !isProd ? idFam : null,
            docenas_objetivo: document.getElementById('cupoDocenas').value,
            horas_objetivo: document.getElementById('cupoHoras').value,
            prioridad: document.getElementById('cupoPrioridad').value
        };

        const res = await fetch(`${window.baseUrl}/api/revisado_crudo.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if(data.success) {
            closeModal('modalAddCupo');
            cargarDatosPlanificacion();
        } else {
            alert(`❌ Error: ${data.message}`);
        }
    } catch(err) {
        console.error(err);
        alert('Fallo de red al agregar cupo.');
    } finally {
        btn.disabled = false;
        btn.innerText = 'Registrar Cupo';
    }
}

function formatearFechaLatina(fechaStr) {
    if(!fechaStr) return '';
    const partes = fechaStr.split('-');
    return `${partes[2]}/${partes[1]}/${partes[0]}`;
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}
</script>

<?php require_once '../../includes/footer.php'; ?>
