<?php
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Registrar Producción Tejido';
$currentPage = 'registrar_produccion_tejido';
require_once '../includes/header.php';
?>

<style>
.tejido-shell {
    display: grid;
    gap: 20px;
}

.tejido-hero {
    background: linear-gradient(135deg, #1e3a5f 0%, #16324f 100%);
    color: #fff;
    border-radius: 18px;
    padding: 24px 28px;
    box-shadow: 0 20px 40px rgba(22, 50, 79, 0.18);
    position: relative;
    overflow: hidden;
}

.tejido-hero h2 {
    margin: 0 0 8px;
    font-size: 2rem;
    position: relative;
    z-index: 2;
}

.tejido-hero p {
    margin: 0;
    max-width: 900px;
    color: rgba(255, 255, 255, 0.88);
    position: relative;
    z-index: 2;
}

.tejido-card {
    background: #fff;
    border-radius: 18px;
    padding: 22px;
    box-shadow: 0 12px 30px rgba(25, 42, 70, 0.08);
}

.tejido-card h3 {
    margin: 0 0 16px;
    color: #16324f;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.tejido-grid {
    display: grid;
    gap: 14px;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

.tejido-field label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: #274c5e;
    font-size: 0.9rem;
}

.tejido-field select,
.tejido-field input,
.tejido-field textarea {
    width: 100%;
    border: 1px solid #c9d8df;
    border-radius: 10px;
    padding: 10px 12px;
    background: #f9fbfc;
    transition: all 0.2s;
}

.tejido-field select:focus,
.tejido-field input:focus {
    border-color: #1f6f78;
    background: #fff;
    outline: none;
    box-shadow: 0 0 0 3px rgba(31, 111, 120, 0.1);
}

.table-wrap {
    overflow-x: auto;
    margin-top: 15px;
}

.tejido-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

.tejido-table th {
    background: #f1f5f9;
    color: #475569;
    padding: 14px 10px;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: left;
    border-bottom: 2px solid #e2e8f0;
}

.tejido-table td {
    padding: 8px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

/* Indicador de Máquina */
.machine-cell {
    display: flex;
    align-items: center;
    gap: 8px;
}

.led-status {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #CBD5E1;
    display: inline-block;
}

.led-active {
    background: #22C55E;
    box-shadow: 0 0 8px rgba(34, 197, 94, 0.5);
}

.table-input {
    border: 1px solid #e2e8f0 !important;
    padding: 6px 8px !important;
    font-size: 0.9rem !important;
}

.linea-unidades {
    width: 60px !important;
}

.linea-docenas {
    width: 80px !important;
}

.btn-tejido {
    border: none;
    border-radius: 10px;
    padding: 10px 20px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-principal { background: #1f6f78; color: #fff; }
.btn-principal:hover { background: #175a61; transform: translateY(-1px); }

.btn-secundario { background: #f1f5f9; color: #475569; }
.btn-secundario:hover { background: #e2e8f0; }

.btn-peligro { background: #FEE2E2; color: #991B1B; }
.btn-peligro:hover { background: #FECACA; }

/* Sección Desperdicio */
.waste-section {
    margin-top: 25px;
    padding-top: 20px;
    border-top: 2px dashed #e2e8f0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.waste-box {
    background: #f8fafc;
    border-radius: 12px;
    padding: 15px;
    border: 1px solid #f1f5f9;
}

.waste-box h4 {
    margin: 0 0 12px;
    color: #334155;
    font-size: 0.95rem;
    display: flex;
    justify-content: space-between;
}

.waste-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.totals-bar {
    background: #1e293b;
    color: #fff;
    padding: 15px 25px;
    border-radius: 14px;
    display: flex;
    justify-content: flex-end;
    gap: 40px;
    margin-top: 20px;
    font-weight: 600;
}

.totals-bar .val {
    font-size: 1.2rem;
    color: #38bdf8;
    margin-left: 10px;
}

.status-box {
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: none;
}
.status-box.show { display: block; }
.status-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }
.status-success { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }

</style>

<div class="tejido-shell">
    <section class="tejido-hero">
        <h2>Registro de Producción por Turno</h2>
        <p>Herramienta operativa para el control de máquinas WIP. Registro de producción real para trazabilidad de calidad y eficiencia por tejedor.</p>
    </section>

    <section class="tejido-card">
        <h3><i class="fas fa-user-hard-hat"></i> Información del Turno</h3>
        <div id="statusBox" class="status-box"></div>
        <div class="tejido-grid">
            <div class="tejido-field">
                <label for="fechaProduccion">Fecha</label>
                <input type="date" id="fechaProduccion" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="tejido-field">
                <label for="idTurno">Turno operativo</label>
                <select id="idTurno">
                    <option value="">Seleccione turno...</option>
                </select>
            </div>
            <div class="tejido-field">
                <label for="idTecnico">Técnico encargado</label>
                <select id="idTecnico">
                    <option value="">Seleccione técnico...</option>
                </select>
            </div>
            <div class="tejido-field">
                <label for="idTejedor">Tejedor (Principal)</label>
                <select id="idTejedor">
                    <option value="">Seleccione tejedor...</option>
                </select>
            </div>
            <div class="tejido-field">
                <label for="idAsistente">Asistente (Opcional)</label>
                <select id="idAsistente">
                    <option value="">Sin asistente</option>
                </select>
            </div>
            <div class="tejido-field" style="grid-column: 1 / -1;">
                <label for="observaciones">Observaciones operativas</label>
                <textarea id="observaciones" rows="2" placeholder="Incidencias, cambios de agujas, etc."></textarea>
            </div>
        </div>
    </section>

    <section class="tejido-card">
        <div class="toolbar" style="margin-bottom: 0;">
            <h3><i class="fas fa-microchip"></i> Planilla de Registro (Capa WIP)</h3>
            <div class="toolbar-actions">
                <button type="button" class="btn-tejido btn-secundario" id="btnAgregarFila"><i class="fas fa-plus"></i> Fila</button>
                <button type="button" class="btn-tejido btn-peligro" id="btnLimpiar">Limpiar</button>
                <button type="button" class="btn-tejido btn-principal" id="btnRegistrar"><i class="fas fa-save"></i> Registrar Producción</button>
            </div>
        </div>
        <div class="muted" style="margin-bottom: 15px;">Las máquinas se precargan según el último registro de producción.</div>

        <div class="table-wrap">
            <table class="tejido-table" id="tablaProduccion">
                <thead>
                    <tr>
                        <th style="width: 150px;">Máquina</th>
                        <th style="width: 140px;">Familia</th>
                        <th>Producto</th>
                        <th style="width: 80px;">Docenas</th>
                        <th style="width: 60px;">Unidades</th>
                        <th style="width: 140px;">Calidad</th>
                        <th style="width: 60px;"></th>
                    </tr>
                </thead>
                <tbody id="bodyLineas"></tbody>
            </table>
        </div>

        <div class="waste-section">
            <div class="waste-box">
                <h4><span>Desperdicio por Familia</span> <span class="muted">Kilos (Kg)</span></h4>
                <div class="waste-grid" id="wasteContainer">
                    <div class="tejido-field">
                        <label>Lujo</label>
                        <input type="number" step="0.01" class="waste-input" data-familia="Lujo" value="0.00">
                    </div>
                    <div class="tejido-field">
                        <label>Stretch</label>
                        <input type="number" step="0.01" class="waste-input" data-familia="Stretch" value="0.00">
                    </div>
                    <div class="tejido-field">
                        <label>Camisetas</label>
                        <input type="number" step="0.01" class="waste-input" data-familia="Camisetas" value="0.00">
                    </div>
                </div>
            </div>
            <div>
                <div class="totals-bar">
                    <div>DOCENAS <span class="val" id="totalDocenas">0</span></div>
                    <div>PARES <span class="val" id="totalPares">0</span></div>
                </div>
                <div class="muted" style="text-align: right; margin-top: 10px;">
                    <i class="fas fa-info-circle"></i> Las unidades > 11 se convertirán automáticamente a docenas.
                </div>
            </div>
        </div>
    </section>

    <section class="tejido-card" id="resultadoCard" style="display:none;">
        <h3>Resultado del registro</h3>
        <div class="result-summary" id="resultadoResumen"></div>
        <div class="table-wrap">
            <table class="tejido-table">
                <thead>
                    <tr>
                        <th>Lote</th>
                        <th>Producto</th>
                        <th>Turno</th>
                        <th>Máquina</th>
                        <th>Tejedor</th>
                        <th>Cantidad</th>
                        <th>Consumo teórico</th>
                        <th>Costo MP</th>
                    </tr>
                </thead>
                <tbody id="resultadoLotes"></tbody>
            </table>
        </div>

        <h3 style="margin-top:20px;">Análisis de salida vs consumo teórico</h3>
        <div class="table-wrap">
            <table class="analysis-table">
                <thead>
                    <tr>
                        <th>Componente</th>
                        <th>Salida almacén (kg)</th>
                        <th>Teórico (kg)</th>
                        <th>Diferencia (kg)</th>
                        <th>Porcentaje</th>
                    </tr>
                </thead>
                <tbody id="resultadoAnalisis"></tbody>
            </table>
        </div>
    </section>
</div>

<script>
const FAMILIAS = ['Lujo', 'Stretch', 'Camisetas'];

const state = {
    documentos: [],
    turnos: [],
    maquinas: [],
    productos: [],
    usuarios: [], // Para técnico, tejedor, asistente
    ultimas_configuraciones: null
};

document.addEventListener('DOMContentLoaded', async () => {
    // Eventos principales
    document.getElementById('btnAgregarFila').addEventListener('click', () => agregarFilaManual());
    document.getElementById('btnLimpiar').addEventListener('click', limpiarFormulario);
    document.getElementById('btnRegistrar').addEventListener('click', registrarProduccion);
    
    // Delegación de eventos para la tabla
    const bodyLineas = document.getElementById('bodyLineas');
    bodyLineas.addEventListener('change', (e) => {
        if (e.target.classList.contains('linea-familia')) {
            actualizarFiltroProductos(e.target.closest('tr'));
        }
        if (e.target.classList.contains('linea-docenas') || e.target.classList.contains('linea-unidades')) {
            recalcularTotales();
        }
        if (e.target.classList.contains('linea-maquina')) {
            actualizarLedMaquina(e.target);
        }
    });

    await cargarCatalogos();
    await checkPrecarga();
});

async function cargarCatalogos() {
    try {
        const [docsResp, turnosResp, maquinasResp, productosResp, usuariosResp] = await Promise.all([
            fetch(`${baseUrl}/api/wip.php?action=get_documentos_salida_tejido`),
            fetch(`${baseUrl}/api/catalogos.php?tipo=turnos`),
            fetch(`${baseUrl}/api/maquinas.php`),
            fetch(`${baseUrl}/api/bom_wip.php`),
            fetch(`${baseUrl}/api/catalogos.php?tipo=usuarios`)
        ]);

        const [docsData, turnosData, maquinasData, productosData, usuariosData] = await Promise.all([
            docsResp.json(),
            turnosResp.json(),
            maquinasResp.json(),
            productosResp.json(),
            usuariosResp.json()
        ]);

        state.documentos = docsData.documentos || [];
        state.turnos = turnosData.turnos || [];
        state.maquinas = (maquinasData.maquinas || []).filter(m => m.estado !== 'inactiva');
        state.productos = (productosData.productos || []).filter(p => p.id_bom);
        state.usuarios = usuariosData.usuarios || [];

        renderSelectOptions('idTurno', state.turnos, 'id_turno', t => t.nombre_turno || t.nombre);
        renderSelectOptions('idTecnico', state.usuarios, 'id_usuario', u => u.nombre_completo);
        renderSelectOptions('idTejedor', state.usuarios, 'id_usuario', u => u.nombre_completo);
        renderSelectOptions('idAsistente', state.usuarios, 'id_usuario', u => u.nombre_completo, 'Sin asistente');

    } catch (error) {
        mostrarEstado('Error cargando catálogos operativos.', 'error');
        console.error(error);
    }
}

async function checkPrecarga() {
    try {
        const resp = await fetch(`${baseUrl}/api/wip.php?action=get_ultimo_registro_tejido`);
        const data = await resp.json();
        
        if (data.success && data.ultimo_registro) {
            state.ultimas_configuraciones = data.ultimo_registro;
            cargarValoresAnteriores();
        } else {
            agregarFilaManual();
        }
    } catch (e) {
        console.error("Error en precarga:", e);
        agregarFilaManual();
    }
}

function cargarValoresAnteriores() {
    const config = state.ultimas_configuraciones;
    if (!config) return;

    // Poblar encabezado
    if (config.id_tecnico) document.getElementById('idTecnico').value = config.id_tecnico;
    if (config.id_tejedor) document.getElementById('idTejedor').value = config.id_tejedor;
    if (config.id_asistente) document.getElementById('idAsistente').value = config.id_asistente;

    // Poblar tabla
    const tbody = document.getElementById('bodyLineas');
    tbody.innerHTML = '';

    if (config.lineas_produccion && config.lineas_produccion.length > 0) {
        config.lineas_produccion.forEach(linea => {
            // Buscamos el producto para identificar su familia (si no viene en el JSON)
            const prodObj = state.productos.find(p => p.id_producto == linea.id_producto);
            const familia = prodObj ? identificarFamilia(prodObj) : (linea.familia || 'Lujo');
            
            agregarFila({
                id_maquina: linea.id_maquina,
                familia: familia,
                id_producto: linea.id_producto,
                cantidad_docenas: 0, // Siempre en 0 para nuevo turno
                cantidad_unidades: 0
            });
        });
    } else {
        agregarFilaManual();
    }
    recalculateLedStatus();
    recalculateTotals();
}

function identificarFamilia(producto) {
    const desc = (producto.descripcion_completa || '').toLowerCase();
    if (desc.includes('lujo')) return 'Lujo';
    if (desc.includes('stretch')) return 'Stretch';
    if (desc.includes('camiseta')) return 'Camisetas';
    return FAMILIAS[0];
}

function agregarFilaManual() {
    agregarFila({
        id_maquina: '',
        familia: 'Lujo',
        id_producto: '',
        cantidad_docenas: 0,
        cantidad_unidades: 0
    });
}

function agregarFila(data) {
    const tbody = document.getElementById('bodyLineas');
    const tr = document.createElement('tr');
    
    // HTML de la fila
    tr.innerHTML = `
        <td>
            <div class="machine-cell">
                <span class="led-status ${data.id_maquina ? 'led-active' : ''}"></span>
                <select class="linea-maquina table-input">
                    <option value="">--</option>
                    ${state.maquinas.map(m => `<option value="${m.id_maquina}" ${m.id_maquina == data.id_maquina ? 'selected' : ''}>${m.numero_maquina}</option>`).join('')}
                </select>
            </div>
        </td>
        <td>
            <select class="linea-familia table-input">
                ${FAMILIAS.map(f => `<option value="${f}" ${f == data.familia ? 'selected' : ''}>${f}</option>`).join('')}
            </select>
        </td>
        <td>
            <select class="linea-producto table-input">
                <option value="">Seleccione producto...</option>
            </select>
        </td>
        <td><input type="number" class="linea-docenas table-input" min="0" value="${data.cantidad_docenas}"></td>
        <td><input type="number" class="linea-unidades table-input" min="0" value="${data.cantidad_unidades}"></td>
        <td>
            <select class="linea-calidad table-input">
                <option value="PRIMERA">PRIMERA</option>
                <option value="OBSERVADA">OBSERVADA</option>
            </select>
        </td>
        <td>
            <button type="button" class="btn-tejido btn-peligro btn-sm btn-eliminar-fila" style="padding: 5px 10px;">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;

    tr.querySelector('.btn-eliminar-fila').addEventListener('click', () => {
        tr.remove();
        recalculateTotals();
        if (tbody.children.length === 0) agregarFilaManual();
    });

    tbody.appendChild(tr);
    actualizarFiltroProductos(tr, data.id_producto);
}

function actualizarFiltroProductos(tr, selectedId = null) {
    const familia = tr.querySelector('.linea-familia').value;
    const prodSelect = tr.querySelector('.linea-producto');
    
    // Filtrado simple basado en texto para este MVP
    const filtered = state.productos.filter(p => {
        const desc = p.descripcion_completa.toLowerCase();
        if (familia === 'Lujo') return desc.includes('lujo');
        if (familia === 'Stretch') return desc.includes('stretch');
        if (familia === 'Camisetas') return desc.includes('camiseta');
        return true;
    });

    prodSelect.innerHTML = '<option value="">Seleccione producto...</option>' + 
        filtered.map(p => `<option value="${p.id_producto}" ${p.id_producto == selectedId ? 'selected' : ''}>${p.descripcion_completa}</option>`).join('');
}

function actualizarLedMaquina(select) {
    const led = select.closest('tr').querySelector('.led-status');
    if (select.value) led.classList.add('led-active');
    else led.classList.remove('led-active');
}

function recalculateLedStatus() {
    document.querySelectorAll('.linea-maquina').forEach(sel => actualizarLedMaquina(sel));
}

function recalculeTotals() {
    let totalDoc = 0;
    let totalUni = 0;

    document.querySelectorAll('#bodyLineas tr').forEach(tr => {
        const doc = parseInt(tr.querySelector('.linea-docenas').value || 0);
        const uni = parseInt(tr.querySelector('.linea-unidades').value || 0);
        totalDoc += doc;
        totalUni += uni;
    });

    // Conversión visual
    const docExtra = Math.floor(totalUni / 12);
    const finalDoc = totalDoc + docExtra;
    const finalUni = totalUni % 12;

    document.getElementById('totalDocenas').textContent = finalDoc;
    document.getElementById('totalPares').textContent = (finalDoc * 12 + finalUni);
}

// Alias para los eventos
const recalcularTotales = recalculeTotals;

function renderSelectOptions(id, items, valueKey, labelFn, emptyLabel = 'Seleccione...') {
    const select = document.getElementById(id);
    select.innerHTML = `<option value="">${emptyLabel}</option>` + 
        items.map(item => `<option value="${item[valueKey]}">${labelFn(item)}</option>`).join('');
}

async function registrarProduccion() {
    try {
        ocultarEstado();
        const idTecnico = document.getElementById('idTecnico').value;
        const idTejedor = document.getElementById('idTejedor').value;
        const idTurno = document.getElementById('idTurno').value;
        const fecha = document.getElementById('fechaProduccion').value;

        if (!fecha || !idTurno || !idTecnico || !idTejedor) {
            throw new Error('Complete la información del turno (Fecha, Turno, Técnico y Tejedor son obligatorios).');
        }

        const lineas = [];
        const maquinasUsadas = new Set();
        let errorFila = false;

        document.querySelectorAll('#bodyLineas tr').forEach((tr, index) => {
            const idMaq = tr.querySelector('.linea-maquina').value;
            const idProd = tr.querySelector('.linea-producto').value;
            const doc = parseInt(tr.querySelector('.linea-docenas').value || 0);
            const uni = parseInt(tr.querySelector('.linea-unidades').value || 0);
            const calidad = tr.querySelector('.linea-calidad').value;

            if (idMaq && idProd) {
                if (maquinasUsadas.has(idMaq)) {
                    errorFila = `La máquina ${tr.querySelector('.linea-maquina option:checked').text} está duplicada.`;
                }
                maquinasUsadas.add(idMaq);

                lineas.push({
                    id_maquina: idMaq,
                    id_producto: idProd,
                    cantidad_docenas: doc,
                    cantidad_unidades: uni,
                    calidad: calidad,
                    id_turno: idTurno,
                    fecha: fecha
                });
            }
        });

        if (errorFila) throw new Error(errorFila);
        if (lineas.length === 0) throw new Error('Debe registrar producción en al menos una máquina.');

        const desperdicios = [];
        document.querySelectorAll('.waste-input').forEach(inp => {
            desperdicios.push({
                familia: inp.dataset.familia,
                kg: parseFloat(inp.value || 0)
            });
        });

        mostrarEstado('Procesando registro WIP...', 'success');

        const payload = {
            action: 'registrar_produccion_tejido',
            fecha: fecha,
            id_turno: idTurno,
            id_tecnico: idTecnico,
            id_tejedor: idTejedor,
            id_asistente: document.getElementById('idAsistente').value,
            observaciones: document.getElementById('observaciones').value.trim(),
            lineas_produccion: lineas,
            desperdicio: desperdicios
        };

        const response = await fetch(`${baseUrl}/api/wip.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'Error en el servidor');

        mostrarEstado(data.message, 'success');
        mostrarResultadosWIP(data);
        
        if(confirm("¡Registro Exitoso! ¿Desea observar los lotes e incidencias generadas?")) {
            document.getElementById('resultadoCard').scrollIntoView({ behavior: 'smooth' });
        }

    } catch (e) {
        mostrarEstado(e.message, 'error');
    }
}

function mostrarResultadosWIP(data) {
    const card = document.getElementById('resultadoCard');
    const bodyLotes = document.getElementById('resultadoLotes');
    const resumen = document.getElementById('resultadoResumen');
    
    card.style.display = 'block';
    
    // Resumen General
    resumen.innerHTML = `
        <div style="display:flex; gap:20px; margin-bottom:15px; background:#f8fafc; padding:15px; border-radius:12px;">
            <div><strong>Lotes Creados:</strong> ${data.lotes_creados.length}</div>
            <div><strong>Costo MP Total:</strong> Bs. ${data.resumen.costo_total}</div>
            <div style="color:${data.incidencias_generadas > 0 ? '#dc2626' : '#15803d'}">
                <strong>Incidencias de Stock:</strong> ${data.incidencias_generadas} ${data.incidencias_generadas > 0 ? '(PENDIENTES)' : '(TODO CONSUMIDO)'}
            </div>
        </div>
    `;

    // Tabla de Lotes
    bodyLotes.innerHTML = data.lotes_creados.map(l => `
        <tr>
            <td><strong>${l.codigo_lote}</strong></td>
            <td>${l.producto}</td>
            <td>${document.getElementById('idTurno').options[document.getElementById('idTurno').selectedIndex].text}</td>
            <td>-</td>
            <td>${document.getElementById('idTejedor').options[document.getElementById('idTejedor').selectedIndex].text}</td>
            <td>${l.cantidad}</td>
            <td>Calculado</td>
            <td>Bs. ${l.costo_mp}</td>
        </tr>
    `).join('');
}

function limpiarFormulario() {
    document.getElementById('observaciones').value = '';
    document.querySelectorAll('.waste-input').forEach(i => i.value = '0.00');
    checkPrecarga();
}

function mostrarEstado(msg, type) {
    const box = document.getElementById('statusBox');
    box.className = `status-box show status-${type}`;
    box.innerHTML = `<i class="fas ${type==='error'?'fa-exclamation-circle':'fa-check-circle'}"></i> ${msg}`;
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function ocultarEstado() {
    document.getElementById('statusBox').className = 'status-box';
}

</script>

<?php require_once '../includes/footer.php'; ?>

<?php require_once '../includes/footer.php'; ?>
