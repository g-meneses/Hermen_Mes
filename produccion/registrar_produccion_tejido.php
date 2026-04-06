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
    background: linear-gradient(135deg, #16324f 0%, #245c73 52%, #d3e4ec 100%);
    color: #fff;
    border-radius: 18px;
    padding: 24px 28px;
    box-shadow: 0 20px 40px rgba(22, 50, 79, 0.18);
}

.tejido-hero h2 {
    margin: 0 0 8px;
    font-size: 2rem;
}

.tejido-hero p {
    margin: 0;
    max-width: 900px;
    color: rgba(255, 255, 255, 0.88);
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
}

.tejido-grid {
    display: grid;
    gap: 14px;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.tejido-field label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: #274c5e;
}

.tejido-field input,
.tejido-field select,
.tejido-field textarea {
    width: 100%;
    border: 1px solid #c9d8df;
    border-radius: 10px;
    padding: 10px 12px;
    background: #f9fbfc;
}

.tejido-field textarea {
    min-height: 90px;
    resize: vertical;
}

.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}

.toolbar-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.table-wrap {
    overflow-x: auto;
}

.tejido-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 960px;
}

.tejido-table th {
    background: #16324f;
    color: #fff;
    padding: 12px 10px;
    font-size: 0.9rem;
    text-align: left;
}

.tejido-table td {
    border-bottom: 1px solid #e4edf1;
    padding: 10px;
    vertical-align: top;
}

.tejido-table input,
.tejido-table select {
    width: 100%;
    border: 1px solid #c8d6de;
    border-radius: 8px;
    padding: 8px 10px;
    background: #fff;
}

.btn-tejido {
    border: none;
    border-radius: 999px;
    padding: 10px 18px;
    font-weight: 600;
    cursor: pointer;
}

.btn-principal {
    background: #1f6f78;
    color: #fff;
}

.btn-secundario {
    background: #e5eef2;
    color: #173042;
}

.btn-peligro {
    background: #fce8e6;
    color: #a33a2d;
}

.btn-linklite {
    background: #f2f7f9;
    color: #245c73;
}

.status-box {
    display: none;
    border-radius: 12px;
    padding: 14px 16px;
    margin-bottom: 14px;
}

.status-box.show {
    display: block;
}

.status-error {
    background: #fdecea;
    color: #9d2f1f;
}

.status-success {
    background: #e8f5ef;
    color: #16653c;
}

.result-summary {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    margin-bottom: 18px;
}

.result-pill {
    background: linear-gradient(135deg, #f2f7f9, #ffffff);
    border: 1px solid #d8e4ea;
    border-radius: 14px;
    padding: 14px 16px;
}

.result-pill strong {
    display: block;
    color: #16324f;
}

.analysis-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 14px;
}

.analysis-table th,
.analysis-table td {
    padding: 10px;
    border-bottom: 1px solid #e4edf1;
}

.muted {
    color: #61808f;
    font-size: 0.92rem;
}

@media (max-width: 768px) {
    .tejido-hero {
        padding: 18px;
    }

    .tejido-card {
        padding: 18px;
    }
}
</style>

<div class="tejido-shell">
    <section class="tejido-hero">
        <h2>Registro de Producción en Tejido</h2>
        <p>Cargue la producción semanal desde planillas de tejeduría usando un documento oficial <strong>SAL-TEJ</strong>. Este flujo crea lotes <strong>OP-TEJ</strong>, registra <strong>CREACION_EN_TEJIDO</strong> y no modifica inventario.</p>
    </section>

    <section class="tejido-card">
        <h3>Documento y contexto</h3>
        <div id="statusBox" class="status-box"></div>
        <div class="tejido-grid">
            <div class="tejido-field">
                <label for="idDocumentoSalida">Documento SAL-TEJ</label>
                <select id="idDocumentoSalida">
                    <option value="">Cargando documentos...</option>
                </select>
            </div>
            <div class="tejido-field">
                <label for="documentoFecha">Fecha documento</label>
                <input type="text" id="documentoFecha" readonly>
            </div>
            <div class="tejido-field">
                <label for="documentoEstado">Estado</label>
                <input type="text" id="documentoEstado" readonly>
            </div>
            <div class="tejido-field" style="grid-column: 1 / -1;">
                <label for="observaciones">Observaciones</label>
                <textarea id="observaciones" placeholder="Notas operativas de la semana"></textarea>
            </div>
        </div>
    </section>

    <section class="tejido-card">
        <div class="toolbar">
            <div>
                <h3>Planilla digital</h3>
                <div class="muted">Una fila representa una combinación fecha + turno + máquina + producto.</div>
            </div>
            <div class="toolbar-actions">
                <button type="button" class="btn-tejido btn-linklite" id="btnAgregarFila">+ Agregar fila</button>
                <button type="button" class="btn-tejido btn-secundario" id="btnLimpiar">Cancelar</button>
                <button type="button" class="btn-tejido btn-principal" id="btnRegistrar">Registrar</button>
            </div>
        </div>

        <div class="table-wrap">
            <table class="tejido-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Turno</th>
                        <th>Máquina</th>
                        <th>Producto</th>
                        <th>Docenas</th>
                        <th>Unidades</th>
                        <th>Tejedor</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody id="bodyLineas"></tbody>
            </table>
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
const state = {
    documentos: [],
    turnos: [],
    maquinas: [],
    productos: []
};

document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('btnAgregarFila').addEventListener('click', () => agregarFila());
    document.getElementById('btnLimpiar').addEventListener('click', limpiarFormulario);
    document.getElementById('btnRegistrar').addEventListener('click', registrarProduccion);
    document.getElementById('idDocumentoSalida').addEventListener('change', actualizarDocumentoSeleccionado);

    await cargarCatalogos();
    agregarFila();
});

async function cargarCatalogos() {
    try {
        const [docsResp, turnosResp, maquinasResp, productosResp] = await Promise.all([
            fetch(`${baseUrl}/api/wip.php?action=get_documentos_salida_tejido`),
            fetch(`${baseUrl}/api/catalogos.php?tipo=turnos`),
            fetch(`${baseUrl}/api/maquinas.php`),
            fetch(`${baseUrl}/api/bom_wip.php`)
        ]);

        const [docsData, turnosData, maquinasData, productosData] = await Promise.all([
            docsResp.json(),
            turnosResp.json(),
            maquinasResp.json(),
            productosResp.json()
        ]);

        state.documentos = docsData.documentos || [];
        state.turnos = turnosData.turnos || [];
        state.maquinas = (maquinasData.maquinas || []).filter(maquina => maquina.estado !== 'inactiva');
        state.productos = (productosData.productos || []).filter(producto => producto.id_bom);

        renderDocumentos();
    } catch (error) {
        mostrarEstado('No se pudieron cargar los catálogos iniciales.', 'error');
        console.error(error);
    }
}

function renderDocumentos() {
    const select = document.getElementById('idDocumentoSalida');
    select.innerHTML = '<option value="">Seleccione un documento SAL-TEJ</option>';

    state.documentos.forEach(documento => {
        const option = document.createElement('option');
        option.value = documento.id_documento;
        option.textContent = `${documento.numero_documento} · ${documento.fecha_documento}`;
        option.dataset.fecha = documento.fecha_documento;
        option.dataset.estado = documento.estado;
        select.appendChild(option);
    });
}

function actualizarDocumentoSeleccionado() {
    const option = document.getElementById('idDocumentoSalida').selectedOptions[0];
    document.getElementById('documentoFecha').value = option?.dataset?.fecha || '';
    document.getElementById('documentoEstado').value = option?.dataset?.estado || '';
}

function agregarFila(data = {}) {
    const tbody = document.getElementById('bodyLineas');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="date" class="linea-fecha" value="${data.fecha || hoyISO()}"></td>
        <td>${renderSelect('linea-turno', state.turnos, 'id_turno', turno => `${turno.codigo || turno.nombre_turno}`)}</td>
        <td>${renderSelect('linea-maquina', state.maquinas, 'id_maquina', maquina => maquina.numero_maquina)}</td>
        <td>${renderSelect('linea-producto', state.productos, 'id_producto', producto => `${producto.codigo_producto} - ${producto.descripcion_completa}`)}</td>
        <td><input type="number" class="linea-docenas" min="0" step="1" value="${data.cantidad_docenas || 0}"></td>
        <td><input type="number" class="linea-unidades" min="0" max="11" step="1" value="${data.cantidad_unidades || 0}"></td>
        <td><input type="text" class="linea-tejedor" value="${data.nombre_tejedor || ''}" placeholder="Nombre del tejedor"></td>
        <td><button type="button" class="btn-tejido btn-peligro btn-eliminar-fila">Eliminar</button></td>
    `;

    tr.querySelector('.btn-eliminar-fila').addEventListener('click', () => {
        tr.remove();
        if (!tbody.children.length) {
            agregarFila();
        }
    });

    tbody.appendChild(tr);
}

function renderSelect(className, items, valueKey, labelFn) {
    const options = ['<option value="">Seleccione</option>']
        .concat(items.map(item => `<option value="${item[valueKey]}">${labelFn(item)}</option>`))
        .join('');
    return `<select class="${className}">${options}</select>`;
}

function recolectarLineas() {
    const filas = [...document.querySelectorAll('#bodyLineas tr')];
    return filas.map((fila, index) => {
        const unidades = Number(fila.querySelector('.linea-unidades').value || 0);
        if (unidades < 0 || unidades > 11) {
            throw new Error(`La fila ${index + 1} tiene unidades fuera de rango (0-11).`);
        }

        return {
            fecha: fila.querySelector('.linea-fecha').value,
            id_turno: Number(fila.querySelector('.linea-turno').value),
            id_maquina: Number(fila.querySelector('.linea-maquina').value),
            id_producto: Number(fila.querySelector('.linea-producto').value),
            cantidad_docenas: Number(fila.querySelector('.linea-docenas').value || 0),
            cantidad_unidades: unidades,
            nombre_tejedor: fila.querySelector('.linea-tejedor').value.trim()
        };
    });
}

async function registrarProduccion() {
    try {
        const idDocumentoSalida = Number(document.getElementById('idDocumentoSalida').value);
        if (!idDocumentoSalida) {
            throw new Error('Seleccione un documento SAL-TEJ.');
        }

        const lineas = recolectarLineas();
        if (!lineas.length) {
            throw new Error('Debe registrar al menos una fila.');
        }

        lineas.forEach((linea, index) => {
            if (!linea.fecha || !linea.id_turno || !linea.id_maquina || !linea.id_producto) {
                throw new Error(`La fila ${index + 1} está incompleta.`);
            }
            if ((linea.cantidad_docenas * 12 + linea.cantidad_unidades) <= 0) {
                throw new Error(`La fila ${index + 1} debe tener una cantidad mayor a cero.`);
            }
        });

        mostrarEstado('Registrando producción de tejido...', 'success');

        const response = await fetch(`${baseUrl}/api/wip.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'registrar_produccion_tejido',
                id_documento_salida: idDocumentoSalida,
                observaciones: document.getElementById('observaciones').value.trim(),
                lineas_produccion: lineas
            })
        });

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'No se pudo registrar la producción.');
        }

        mostrarEstado(data.message, 'success');
        renderResultado(data);
    } catch (error) {
        mostrarEstado(error.message || 'No se pudo registrar la producción.', 'error');
    }
}

function renderResultado(data) {
    document.getElementById('resultadoCard').style.display = 'block';

    document.getElementById('resultadoResumen').innerHTML = `
        <div class="result-pill"><strong>${data.documento_salida.numero}</strong>Documento de salida</div>
        <div class="result-pill"><strong>${data.resumen.total_lotes_creados}</strong>Lotes WIP creados</div>
        <div class="result-pill"><strong>${data.resumen.cantidad_total_producida}</strong>Producción total</div>
        <div class="result-pill"><strong>Bs. ${Number(data.resumen.costo_mp_total || 0).toFixed(4)}</strong>Costo MP total</div>
    `;

    document.getElementById('resultadoLotes').innerHTML = (data.lotes_creados || []).map(lote => `
        <tr>
            <td>${lote.codigo_lote}</td>
            <td>${lote.producto}</td>
            <td>${lote.turno}</td>
            <td>${lote.maquina}</td>
            <td>${lote.tejedor || '-'}</td>
            <td>${lote.cantidad}</td>
            <td>${Number(lote.consumo_teorico_kg || 0).toFixed(4)} kg</td>
            <td>Bs. ${Number(lote.costo_mp || 0).toFixed(4)}</td>
        </tr>
    `).join('');

    const analisis = data.analisis?.diferencia || {};
    const filasAnalisis = Object.entries(analisis).map(([nombre, fila]) => `
        <tr>
            <td>${nombre}</td>
            <td>${Number(fila.salida_kg || 0).toFixed(4)}</td>
            <td>${Number(fila.teorico_kg || 0).toFixed(4)}</td>
            <td>${Number(fila.diferencia_kg || 0).toFixed(4)}</td>
            <td>${Number(fila.porcentaje || 0).toFixed(2)}%</td>
        </tr>
    `).join('');

    document.getElementById('resultadoAnalisis').innerHTML = filasAnalisis || `
        <tr><td colspan="5" class="muted">No hay análisis disponible para este documento.</td></tr>
    `;
}

function limpiarFormulario() {
    document.getElementById('idDocumentoSalida').value = '';
    document.getElementById('documentoFecha').value = '';
    document.getElementById('documentoEstado').value = '';
    document.getElementById('observaciones').value = '';
    document.getElementById('bodyLineas').innerHTML = '';
    document.getElementById('resultadoCard').style.display = 'none';
    agregarFila();
    ocultarEstado();
}

function mostrarEstado(message, type) {
    const box = document.getElementById('statusBox');
    box.className = `status-box show ${type === 'error' ? 'status-error' : 'status-success'}`;
    box.textContent = message;
}

function ocultarEstado() {
    const box = document.getElementById('statusBox');
    box.className = 'status-box';
    box.textContent = '';
}

function hoyISO() {
    return new Date().toISOString().slice(0, 10);
}
</script>

<?php require_once '../includes/footer.php'; ?>
