<?php
require_once '../../config/database.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Auditoría de Hilos (WIP)';
$currentPage = 'auditoria_hilos';
require_once '../../includes/header.php';
?>

<div class="audit-shell">
    <header class="audit-header">
        <div class="header-main">
            <h1><i class="fas fa-clipboard-check"></i> Auditoría de Hilos en Planta</h1>
            <p>Conciliación física de materia prima entregada a Tejeduría vs Consumo Teórico FIFO.</p>
        </div>
        <div class="header-actions">
            <button class="btn-primary" onclick="registrarAuditoria()"><i class="fas fa-save"></i> Guardar Auditoría</button>
        </div>
    </header>

    <div class="audit-card">
        <div class="card-toolbar">
            <div class="search-box">
                <i class="fas fa-filter"></i>
                <input type="text" id="filtroHilo" placeholder="Filtrar por código o nombre..." oninput="filtrarTabla()">
            </div>
            <div class="last-sync">
                <span id="syncTime">Sincronizado: Justo ahora</span>
                <button class="btn-icon" onclick="cargarEstadoTeorico()"><i class="fas fa-sync"></i></button>
            </div>
        </div>

        <div class="table-container">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>Hilo / Materia Prima</th>
                        <th class="text-right">Stock Teórico (Sistema)</th>
                        <th class="text-center" style="width: 200px;">Conteo Físico (Planta)</th>
                        <th class="text-right">Diferencia</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="bodyAudit">
                    <tr><td colspan="5" class="text-center loading">Calculando saldos FIFO disponibles...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="audit-footer">
            <div class="form-group">
                <label>Observaciones de la Auditoría</label>
                <textarea id="auditObservaciones" rows="2" placeholder="Notas sobre discrepancias encontradas, mermas inusuales, etc."></textarea>
            </div>
        </div>
    </div>
</div>

<style>
.audit-shell {
    padding: 24px;
    max-width: 1200px;
    margin: 0 auto;
}

.audit-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.header-main h1 {
    margin: 0;
    font-size: 1.75rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-main h1 i { color: #2563eb; }

.header-main p {
    margin: 4px 0 0;
    color: #64748b;
}

.audit-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.card-toolbar {
    padding: 20px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
}

.search-box {
    position: relative;
    flex: 1;
    max-width: 400px;
}

.search-box i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
}

.search-box input {
    width: 100%;
    padding: 10px 10px 10px 40px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    transition: all 0.2s;
}

.search-box input:focus {
    border-color: #2563eb;
    outline: none;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.last-sync {
    font-size: 0.85rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 10px;
}

.audit-table {
    width: 100%;
    border-collapse: collapse;
}

.audit-table th {
    background: #fff;
    padding: 16px 20px;
    text-align: left;
    font-size: 0.75rem;
    text-transform: uppercase;
    font-weight: 700;
    color: #64748b;
    border-bottom: 2px solid #f1f5f9;
}

.audit-table td {
    padding: 12px 20px;
    border-bottom: 1px solid #f8fafc;
    vertical-align: middle;
}

.audit-table tr:hover { background: #f9fafb; }

.text-right { text-align: right; }
.text-center { text-align: center; }

.input-count {
    width: 120px;
    padding: 8px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    text-align: right;
    font-weight: 700;
    font-size: 1rem;
    color: #1e293b;
    transition: all 0.2s;
}

.input-count:focus {
    border-color: #2563eb;
    outline: none;
}

.diff-value {
    font-weight: 700;
    font-size: 0.95rem;
}

.diff-ok { color: #10b981; }
.diff-error { color: #dc2626; }

.badge-status {
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
}

.status-match { background: #dcfce7; color: #166534; }
.status-missing { background: #fee2e2; color: #991B1B; }
.status-surplus { background: #e0f2fe; color: #075985; }

.audit-footer {
    padding: 24px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
}

.audit-footer label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #475569;
}

.audit-footer textarea {
    width: 100%;
    padding: 12px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    resize: none;
}

.btn-primary {
    background: #2563eb;
    color: #fff;
    border: none;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
}

.btn-primary:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
    box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
}

.btn-icon {
    background: none;
    border: none;
    cursor: pointer;
    color: #94a3b8;
    padding: 4px;
}

.btn-icon:hover { color: #2563eb; }

</style>

<script>
let teoricoWIP = [];

document.addEventListener('DOMContentLoaded', () => {
    cargarEstadoTeorico();
});

async function cargarEstadoTeorico() {
    const tbody = document.getElementById('bodyAudit');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center loading">Calculando saldos FIFO disponibles...</td></tr>';
    
    try {
        const response = await fetch(`${baseUrl}/api/wip.php?action=get_estado_auditoria_hilos`);
        const data = await response.json();
        
        if (!data.success) throw new Error(data.message);
        
        teoricoWIP = data.inventario_wip || [];
        renderTabla();
        document.getElementById('syncTime').textContent = `Sincronizado: ${new Date().toLocaleTimeString()}`;
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center" style="color:red">Error: ${e.message}</td></tr>`;
    }
}

function renderTabla() {
    const tbody = document.getElementById('bodyAudit');
    const query = document.getElementById('filtroHilo').value.toLowerCase();
    
    const filtrados = teoricoWIP.filter(h => 
        h.codigo.toLowerCase().includes(query) || 
        h.nombre.toLowerCase().includes(query)
    );

    tbody.innerHTML = filtrados.map(h => `
        <tr data-id="${h.id_inventario}">
            <td>
                <strong>${h.codigo}</strong><br>
                <small style="color:#64748b">${h.nombre}</small>
            </td>
            <td class="text-right">
                <span class="teorico-val">${Number(h.stock_teorico_wip).toFixed(4)}</span> ${h.unidad}
            </td>
            <td class="text-center">
                <input type="number" step="0.001" class="input-count" 
                       data-teorico="${h.stock_teorico_wip}" 
                       oninput="recalcularFila(this)" 
                       placeholder="0.000">
            </td>
            <td class="text-right">
                <span class="diff-value">0.0000</span>
            </td>
            <td>
                <span class="badge-status status-match">SIN CONTEO</span>
            </td>
        </tr>
    `).join('');
}

function recalcularFila(input) {
    const tr = input.closest('tr');
    const teorico = parseFloat(input.dataset.teorico);
    const fisico = parseFloat(input.value || 0);
    const diff = fisico - teorico;
    
    const diffSpan = tr.querySelector('.diff-value');
    const badge = tr.querySelector('.badge-status');
    
    diffSpan.textContent = (diff > 0 ? '+' : '') + diff.toFixed(4);
    diffSpan.className = 'diff-value ' + (Math.abs(diff) < 0.0001 ? 'diff-ok' : 'diff-error');

    if (Math.abs(diff) < 0.0001) {
        badge.textContent = 'MATCH';
        badge.className = 'badge-status status-match';
    } else if (diff < 0) {
        badge.textContent = 'FALTANTE';
        badge.className = 'badge-status status-missing';
    } else {
        badge.textContent = 'SOBRANTE';
        badge.className = 'badge-status status-surplus';
    }
}

function filtrarTabla() { renderTabla(); }

async function registrarAuditoria() {
    const lineas = [];
    let hasCount = false;
    
    document.querySelectorAll('#bodyAudit tr').forEach(tr => {
        const input = tr.querySelector('.input-count');
        if (input.value !== '') {
            hasCount = true;
            lineas.push({
                id_inventario: tr.dataset.id,
                stock_teorico_wip: input.dataset.teorico,
                conteo_fisico: input.value
            });
        }
    });

    if (!hasCount) {
        alert("Por favor, ingrese al menos un conteo físico para registrar la auditoría.");
        return;
    }

    if (!confirm("¿Está seguro de registrar este conteo físico? Esto servirá como base para ajustes de saldo FIFO.")) {
        return;
    }

    const payload = {
        action: 'registrar_auditoria_hilos',
        observaciones: document.getElementById('auditObservaciones').value,
        detalles: lineas
    };

    try {
        const response = await fetch(`${baseUrl}/api/wip.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        
        if (!data.success) throw new Error(data.message);
        
        alert("Auditoría registrada exitosamente.");
        window.location.reload();
    } catch (e) {
        alert("Error al registrar auditoría: " + e.message);
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
