<?php
/**
 * Módulo de Ingresos de Materias Primas
 * Sistema MES Hermen Ltda. v1.0
 */
require_once '../../config/database.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Ingresos de Materias Primas';
$currentPage = 'ingresos_mp';
require_once '../../includes/header.php';
?>

<style>
    :root {
        --color-ingreso: #28a745;
        --color-ingreso-dark: #1e7e34;
    }

    .ing-module {
        padding: 20px;
        background: #f4f6f9;
        min-height: calc(100vh - 60px);
    }

    .ing-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        background: white;
        padding: 20px 25px;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        flex-wrap: wrap;
        gap: 15px;
    }

    .ing-header-left {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .ing-title {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .ing-title-icon {
        width: 55px;
        height: 55px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        color: white;
        background: linear-gradient(135deg, var(--color-ingreso), #20c997);
    }

    .ing-title h1 {
        font-size: 1.5rem;
        color: #1a1a2e;
        margin: 0;
    }

    .ing-title p {
        font-size: 0.85rem;
        color: #6c757d;
        margin: 0;
    }

    .btn-volver {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        background: #6c757d;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-size: 0.85rem;
    }

    .btn-volver:hover {
        background: #5a6268;
    }

    .btn-nuevo {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: linear-gradient(135deg, var(--color-ingreso), #20c997);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    }

    .btn-nuevo:hover {
        transform: translateY(-2px);
    }

    .ing-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .kpi-card {
        background: white;
        border-radius: 14px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .kpi-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: white;
    }

    .kpi-icon.docs {
        background: linear-gradient(135deg, #1a237e, #4fc3f7);
    }

    .kpi-icon.valor {
        background: linear-gradient(135deg, var(--color-ingreso), #20c997);
    }

    .kpi-icon.factura {
        background: linear-gradient(135deg, #ffc107, #ff9800);
    }

    .kpi-icon.anulados {
        background: linear-gradient(135deg, #dc3545, #c82333);
    }

    .kpi-label {
        font-size: 0.8rem;
        color: #6c757d;
    }

    .kpi-value {
        font-size: 1.4rem;
        font-weight: 700;
        color: #1a1a2e;
    }

    .kpi-value.success {
        color: var(--color-ingreso);
    }

    .filtros-card {
        background: white;
        border-radius: 14px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
    }

    .filtros-card h4 {
        margin: 0 0 15px 0;
        font-size: 0.95rem;
        color: #495057;
    }

    .filtros-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        align-items: end;
    }

    .filtro-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .filtro-group label {
        font-size: 0.8rem;
        color: #6c757d;
    }

    .filtro-group input,
    .filtro-group select {
        padding: 10px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        font-size: 0.9rem;
    }

    .btn-filtrar {
        padding: 10px 20px;
        background: var(--color-ingreso);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }

    .tabla-card {
        background: white;
        border-radius: 14px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }

    .tabla-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid #e9ecef;
    }

    .tabla-header h3 {
        margin: 0;
        font-size: 1rem;
    }

    .tabla-count {
        background: var(--color-ingreso);
        color: white;
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 0.8rem;
    }

    .ing-table {
        width: 100%;
        border-collapse: collapse;
    }

    .ing-table th {
        background: #f8f9fa;
        padding: 12px 15px;
        text-align: left;
        font-size: 0.75rem;
        color: #6c757d;
        text-transform: uppercase;
    }

    .ing-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #f1f1f1;
    }

    .ing-table tr:hover {
        background: #f8f9fa;
    }

    .doc-info {
        display: flex;
        flex-direction: column;
    }

    .doc-numero {
        font-weight: 700;
        color: var(--color-ingreso);
    }

    .doc-fecha {
        font-size: 0.8rem;
        color: #6c757d;
    }

    .prov-info {
        display: flex;
        flex-direction: column;
    }

    .prov-nombre {
        font-weight: 500;
    }

    .badge {
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-confirmado {
        background: #d4edda;
        color: #155724;
    }

    .badge-anulado {
        background: #f8d7da;
        color: #721c24;
    }

    .badge-local {
        background: #d4edda;
        color: #155724;
    }

    .badge-import {
        background: #cce5ff;
        color: #004085;
    }

    .badge-factura {
        background: #fff3cd;
        color: #856404;
    }

    .badge-moneda {
        background: #e9ecef;
        color: #495057;
    }

    .valor-total {
        font-weight: 700;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 0 2px;
    }

    .btn-icon.ver {
        background: #17a2b8;
        color: white;
    }

    .btn-icon.anular {
        background: #dc3545;
        color: white;
    }

    .empty-state {
        text-align: center;
        padding: 50px;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 3rem;
        opacity: 0.3;
        margin-bottom: 15px;
        display: block;
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal.show {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 16px;
        width: 100%;
        max-width: 950px;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 18px 25px;
        background: linear-gradient(135deg, var(--color-ingreso), #20c997);
        color: white;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.1rem;
    }

    .modal-close {
        width: 34px;
        height: 34px;
        border: none;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.2rem;
        color: white;
    }

    .modal-body {
        padding: 25px;
        overflow-y: auto;
        flex: 1;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 15px 25px;
        border-top: 1px solid #e9ecef;
        background: #f8f9fa;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        margin-bottom: 15px;
    }

    .form-row.tres {
        grid-template-columns: repeat(3, 1fr);
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .form-group.full {
        grid-column: span 2;
    }

    .form-group label {
        font-size: 0.85rem;
        font-weight: 500;
        color: #495057;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 10px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        font-size: 0.9rem;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--color-ingreso);
    }

    .form-group input[readonly] {
        background: #e9ecef;
    }

    .form-section {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 15px;
    }

    .form-section h4 {
        margin: 0 0 12px 0;
        font-size: 0.9rem;
        color: #495057;
    }

    .form-section h4 i {
        color: var(--color-ingreso);
        margin-right: 8px;
    }

    .info-box {
        display: flex;
        gap: 12px;
        align-items: center;
        padding: 10px 15px;
        background: #e8f5e9;
        border-radius: 8px;
        margin-bottom: 12px;
        font-size: 0.85rem;
    }

    .info-box.hidden {
        display: none;
    }

    .checkbox-factura {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 15px;
        background: #fff3cd;
        border-radius: 8px;
        border: 1px solid #ffc107;
        margin-bottom: 15px;
    }

    .checkbox-factura input {
        width: 18px;
        height: 18px;
    }

    .checkbox-factura label {
        cursor: pointer;
        font-weight: 500;
    }

    .tabla-lineas-container {
        border: 1px solid #e9ecef;
        border-radius: 8px;
        overflow: hidden;
        max-height: 250px;
        overflow-y: auto;
    }

    .tabla-lineas {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .tabla-lineas th {
        background: #343a40;
        color: white;
        padding: 10px 8px;
        font-size: 0.75rem;
        text-transform: uppercase;
        position: sticky;
        top: 0;
    }

    .tabla-lineas td {
        padding: 8px;
        border-bottom: 1px solid #e9ecef;
    }

    .tabla-lineas input,
    .tabla-lineas select {
        width: 100%;
        padding: 6px 8px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        font-size: 0.85rem;
    }

    .tabla-lineas input[type="number"] {
        text-align: right;
    }

    .tabla-lineas .col-calc {
        background: #f8f9fa;
        text-align: right;
        padding-right: 10px;
        font-weight: 500;
    }

    .tabla-lineas .col-iva {
        background: #fff8e1;
    }

    .btn-agregar {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 15px;
        background: var(--color-ingreso);
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.85rem;
        margin-top: 10px;
    }

    .totales-section {
        display: flex;
        justify-content: flex-end;
        margin-top: 15px;
    }

    .totales-box {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px 20px;
        min-width: 280px;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid #e9ecef;
    }

    .total-row:last-child {
        border-bottom: none;
    }

    .total-row.final {
        background: linear-gradient(135deg, var(--color-ingreso), #20c997);
        margin: 8px -20px -15px;
        padding: 12px 20px;
        border-radius: 0 0 10px 10px;
        color: white;
    }

    .total-label {
        color: #6c757d;
    }

    .total-value {
        font-weight: 700;
    }

    .total-row.final .total-label,
    .total-row.final .total-value {
        color: white;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-success {
        background: var(--color-ingreso);
        color: white;
    }
</style>

<div class="ing-module">
    <div class="ing-header">
        <div class="ing-header-left">
            <a href="materias_primas.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver</a>
            <div class="ing-title">
                <div class="ing-title-icon"><i class="fas fa-arrow-down"></i></div>
                <div>
                    <h1>Ingresos de Materias Primas</h1>
                    <p>Gestión de documentos de ingreso</p>
                </div>
            </div>
        </div>
        <button class="btn-nuevo" onclick="abrirModalNuevo()"><i class="fas fa-plus"></i> Nuevo Ingreso</button>
    </div>

    <div class="ing-kpis">
        <div class="kpi-card">
            <div class="kpi-icon docs"><i class="fas fa-file-alt"></i></div>
            <div class="kpi-info">
                <div class="kpi-label">Documentos del Mes</div>
                <div class="kpi-value" id="kpiDocs">0</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon valor"><i class="fas fa-dollar-sign"></i></div>
            <div class="kpi-info">
                <div class="kpi-label">Valor Total</div>
                <div class="kpi-value success" id="kpiValor">Bs. 0</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon factura"><i class="fas fa-file-invoice"></i></div>
            <div class="kpi-info">
                <div class="kpi-label">Con Factura</div>
                <div class="kpi-value" id="kpiFactura">Bs. 0</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon anulados"><i class="fas fa-ban"></i></div>
            <div class="kpi-info">
                <div class="kpi-label">Anulados</div>
                <div class="kpi-value" id="kpiAnulados">0</div>
            </div>
        </div>
    </div>

    <div class="filtros-card">
        <h4><i class="fas fa-filter"></i> Filtros</h4>
        <div class="filtros-grid">
            <div class="filtro-group"><label>Desde</label><input type="date" id="filtroDesde"></div>
            <div class="filtro-group"><label>Hasta</label><input type="date" id="filtroHasta"></div>
            <div class="filtro-group"><label>Proveedor</label><select id="filtroProveedor">
                    <option value="">Todos</option>
                </select></div>
            <div class="filtro-group"><label>Estado</label><select id="filtroEstado">
                    <option value="todos">Todos</option>
                    <option value="CONFIRMADO">Confirmados</option>
                    <option value="ANULADO">Anulados</option>
                </select></div>
            <div class="filtro-group"><label>&nbsp;</label><button class="btn-filtrar" onclick="cargarDocumentos()"><i
                        class="fas fa-search"></i> Buscar</button></div>
        </div>
    </div>

    <div class="tabla-card">
        <div class="tabla-header">
            <h3><i class="fas fa-list"></i> Documentos</h3><span class="tabla-count" id="totalDocs">0</span>
        </div>
        <table class="ing-table">
            <thead>
                <tr>
                    <th>Documento</th>
                    <th>Proveedor</th>
                    <th>Referencia</th>
                    <th>Moneda</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="documentosBody">
                <tr>
                    <td colspan="7" style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Nuevo -->
<div class="modal" id="modalIngreso">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-arrow-down"></i> Nuevo Ingreso</h3><button class="modal-close"
                onclick="cerrarModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-section">
                <h4><i class="fas fa-file-alt"></i> Documento</h4>
                <div class="form-row tres">
                    <div class="form-group"><label>Nº Documento</label><input type="text" id="docNumero" readonly></div>
                    <div class="form-group"><label>Fecha</label><input type="date" id="docFecha"></div>
                    <div class="form-group"><label>Nº Factura</label><input type="text" id="docReferencia"
                            placeholder="FAC-001234"></div>
                </div>
            </div>
            <div class="form-section">
                <h4><i class="fas fa-truck"></i> Proveedor</h4>
                <div class="form-row">
                    <div class="form-group"><label>Tipo</label><select id="tipoProveedor"
                            onchange="filtrarProveedores()">
                            <option value="TODOS">Todos</option>
                            <option value="LOCAL">🇧🇴 Locales</option>
                            <option value="IMPORTACION">🌎 Importación</option>
                        </select></div>
                    <div class="form-group"><label>Proveedor *</label><select id="docProveedor"
                            onchange="actualizarInfoProveedor()">
                            <option value="">Seleccione...</option>
                        </select></div>
                </div>
                <div class="info-box hidden" id="infoProveedor"><span id="provTipo" class="badge"></span><span
                        id="provMoneda" class="badge badge-moneda"></span><span id="provPago"></span></div>
            </div>
            <div class="checkbox-factura"><input type="checkbox" id="docConFactura" onchange="toggleFactura()"><label
                    for="docConFactura"><strong>Con Factura</strong> - IVA 13%</label></div>
            <div class="form-section">
                <h4><i class="fas fa-filter"></i> Filtrar Productos</h4>
                <div class="form-row">
                    <div class="form-group"><label>Categoría</label><select id="filtroCat"
                            onchange="cargarSubcategorias()">
                            <option value="">Todas</option>
                        </select></div>
                    <div class="form-group"><label>Subcategoría</label><select id="filtroSubcat"
                            onchange="filtrarProductos()">
                            <option value="">Todas</option>
                        </select></div>
                </div>
            </div>
            <h4 style="margin:15px 0 10px;"><i class="fas fa-list"></i> Líneas</h4>
            <div class="tabla-lineas-container">
                <table class="tabla-lineas">
                    <thead id="lineasHead"></thead>
                    <tbody id="lineasBody"></tbody>
                </table>
            </div>
            <button class="btn-agregar" onclick="agregarLinea()"><i class="fas fa-plus"></i> Agregar</button>
            <div class="totales-section">
                <div class="totales-box">
                    <div class="total-row"><span class="total-label">Subtotal:</span><span class="total-value"
                            id="totalSubtotal">Bs. 0.00</span></div>
                    <div class="total-row" id="rowIva" style="display:none;"><span class="total-label">IVA
                            13%:</span><span class="total-value" id="totalIva">Bs. 0.00</span></div>
                    <div class="total-row final"><span class="total-label">TOTAL:</span><span class="total-value"
                            id="totalFinal">Bs. 0.00</span></div>
                </div>
            </div>
            <div class="form-group" style="margin-top:15px;"><label>Observaciones</label><textarea id="docObservaciones"
                    rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
            <button class="btn btn-success" onclick="guardarIngreso()"><i class="fas fa-check"></i> Registrar</button>
        </div>
    </div>
</div>

<!-- Modal Detalle -->
<div class="modal" id="modalDetalle">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-file-alt"></i> <span id="detalleTitulo">Detalle</span></h3><button class="modal-close"
                onclick="cerrarModalDetalle()">&times;</button>
        </div>
        <div class="modal-body" id="detalleContenido"></div>
        <div class="modal-footer"><button class="btn btn-secondary" onclick="cerrarModalDetalle()">Cerrar</button></div>
    </div>
</div>

<script>
    const baseUrl = window.location.origin + '/mes_hermen';
    let documentos = [], proveedores = [], categorias = [], productos = [], productosFiltrados = [], lineas = [];
    let conFactura = false, monedaActual = 'BOB';

    document.addEventListener('DOMContentLoaded', async function () {
        const hoy = new Date();
        document.getElementById('filtroDesde').value = new Date(hoy.getFullYear(), hoy.getMonth(), 1).toISOString().split('T')[0];
        document.getElementById('filtroHasta').value = hoy.toISOString().split('T')[0];
        await cargarDatosIniciales();
        await cargarDocumentos();
        await cargarResumen();
    });

    async function cargarDatosIniciales() {
        try { const r = await fetch(`${baseUrl}/api/ingresos_mp.php?action=proveedores`); const d = await r.json(); if (d.success) { proveedores = d.proveedores; poblarSelectProveedores(); } } catch (e) { }
        try { const r = await fetch(`${baseUrl}/api/ingresos_mp.php?action=categorias`); const d = await r.json(); if (d.success) categorias = d.categorias; } catch (e) { }
        try { const r = await fetch(`${baseUrl}/api/ingresos_mp.php?action=productos`); const d = await r.json(); if (d.success) { productos = d.productos; productosFiltrados = [...productos]; } } catch (e) { }
    }

    function poblarSelectProveedores() {
        document.getElementById('filtroProveedor').innerHTML = '<option value="">Todos</option>' + proveedores.map(p => `<option value="${p.id_proveedor}">${p.codigo} - ${p.nombre_comercial || p.razon_social}</option>`).join('');
    }

    async function cargarDocumentos() {
        const desde = document.getElementById('filtroDesde').value, hasta = document.getElementById('filtroHasta').value;
        const prov = document.getElementById('filtroProveedor').value, estado = document.getElementById('filtroEstado').value;
        try {
            let url = `${baseUrl}/api/ingresos_mp.php?action=list&desde=${desde}&hasta=${hasta}&estado=${estado}`;
            if (prov) url += `&proveedor=${prov}`;
            const r = await fetch(url); const d = await r.json();
            if (d.success) { documentos = d.documentos; renderDocumentos(); }
        } catch (e) { }
    }

    async function cargarResumen() {
        try {
            const r = await fetch(`${baseUrl}/api/ingresos_mp.php?action=resumen`); const d = await r.json();
            if (d.success && d.resumen) {
                document.getElementById('kpiDocs').textContent = d.resumen.confirmados || 0;
                document.getElementById('kpiValor').textContent = 'Bs. ' + formatNum(d.resumen.valor_total || 0);
                document.getElementById('kpiFactura').textContent = 'Bs. ' + formatNum(d.resumen.total_con_factura || 0);
                document.getElementById('kpiAnulados').textContent = d.resumen.anulados || 0;
            }
        } catch (e) { }
    }

    function renderDocumentos() {
        const tbody = document.getElementById('documentosBody');
        document.getElementById('totalDocs').textContent = documentos.length + ' docs';
        if (documentos.length === 0) { tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><i class="fas fa-file-alt"></i><h3>Sin documentos</h3></div></td></tr>'; return; }
        tbody.innerHTML = documentos.map(doc => {
            const prov = doc.proveedor_comercial || doc.proveedor_nombre || '-';
            return `<tr>
            <td><div class="doc-info"><span class="doc-numero">${doc.numero_documento}</span><span class="doc-fecha">${formatFecha(doc.fecha_documento)}</span></div></td>
            <td><div class="prov-info"><span class="prov-nombre">${prov}</span><span class="badge ${doc.proveedor_tipo === 'LOCAL' ? 'badge-local' : 'badge-import'}">${doc.proveedor_tipo === 'LOCAL' ? '🇧🇴' : '🌎'}</span></div></td>
            <td>${doc.referencia_externa || '-'} ${doc.con_factura == 1 ? '<span class="badge badge-factura">CF</span>' : ''}</td>
            <td><span class="badge badge-moneda">${doc.moneda}</span></td>
            <td class="valor-total">${doc.moneda} ${formatNum(doc.total)}</td>
            <td><span class="badge ${doc.estado === 'CONFIRMADO' ? 'badge-confirmado' : 'badge-anulado'}">${doc.estado}</span></td>
            <td><button class="btn-icon ver" onclick="verDetalle(${doc.id_documento})"><i class="fas fa-eye"></i></button>${doc.estado === 'CONFIRMADO' ? `<button class="btn-icon anular" onclick="anularDocumento(${doc.id_documento})"><i class="fas fa-ban"></i></button>` : ''}</td>
        </tr>`;
        }).join('');
    }

    async function abrirModalNuevo() {
        try { const r = await fetch(`${baseUrl}/api/ingresos_mp.php?action=siguiente_numero`); const d = await r.json(); if (d.success) document.getElementById('docNumero').value = d.numero; } catch (e) { }
        document.getElementById('docFecha').value = new Date().toISOString().split('T')[0];
        document.getElementById('tipoProveedor').value = 'TODOS'; filtrarProveedores();
        document.getElementById('docProveedor').value = ''; document.getElementById('docReferencia').value = '';
        document.getElementById('docConFactura').checked = false; document.getElementById('docObservaciones').value = '';
        document.getElementById('infoProveedor').classList.add('hidden');
        document.getElementById('filtroCat').innerHTML = '<option value="">Todas</option>' + categorias.map(c => `<option value="${c.id_categoria}">${c.nombre}</option>`).join('');
        document.getElementById('filtroSubcat').innerHTML = '<option value="">Todas</option>';
        lineas = []; conFactura = false; productosFiltrados = [...productos];
        toggleFactura(); renderLineas();
        document.getElementById('modalIngreso').classList.add('show');
    }

    function filtrarProveedores() {
        const tipo = document.getElementById('tipoProveedor').value;
        let filtrados = tipo === 'TODOS' ? proveedores : proveedores.filter(p => p.tipo === tipo);

        // Debug detallado
        console.log('=== DEBUG PROVEEDORES ===');
        console.log('Total proveedores cargados:', proveedores.length);
        proveedores.forEach(p => console.log(`ID: ${p.id_proveedor}, Código: ${p.codigo}, Tipo: "${p.tipo}"`));
        console.log('Tipo filtro seleccionado:', tipo);
        console.log('Filtrados:', filtrados.length);

        const loc = filtrados.filter(p => p.tipo === 'LOCAL');
        const imp = filtrados.filter(p => p.tipo === 'IMPORTACION');

        console.log('Locales encontrados:', loc.length);
        console.log('Importación encontrados:', imp.length);
        console.log('=========================');

        let html = '<option value="">Seleccione...</option>';
        if (loc.length) {
            html += '<optgroup label="🇧🇴 Locales">';
            loc.forEach(p => html += `<option value="${p.id_proveedor}" data-tipo="${p.tipo}" data-moneda="${p.moneda || 'BOB'}" data-pago="${p.condicion_pago || 'Contado'}">${p.codigo} - ${p.nombre_comercial || p.razon_social}</option>`);
            html += '</optgroup>';
        }
        if (imp.length) {
            html += '<optgroup label="🌎 Importación">';
            imp.forEach(p => html += `<option value="${p.id_proveedor}" data-tipo="${p.tipo}" data-moneda="${p.moneda || 'USD'}" data-pago="${p.condicion_pago || 'Contado'}">${p.codigo} - ${p.nombre_comercial || p.razon_social} (${p.pais || 'Exterior'})</option>`);
            html += '</optgroup>';
        }
        document.getElementById('docProveedor').innerHTML = html;
        document.getElementById('infoProveedor').classList.add('hidden');
    }

    function actualizarInfoProveedor() {
        const opt = document.getElementById('docProveedor').selectedOptions[0];
        if (!opt || !opt.value) { document.getElementById('infoProveedor').classList.add('hidden'); return; }
        monedaActual = opt.dataset.moneda || 'BOB';
        document.getElementById('provTipo').textContent = opt.dataset.tipo === 'LOCAL' ? '🇧🇴 Local' : '🌎 Importación';
        document.getElementById('provTipo').className = `badge ${opt.dataset.tipo === 'LOCAL' ? 'badge-local' : 'badge-import'}`;
        document.getElementById('provMoneda').textContent = monedaActual;
        document.getElementById('provPago').textContent = opt.dataset.pago || 'Contado';
        document.getElementById('infoProveedor').classList.remove('hidden');
        recalcularTotales();
    }

    async function cargarSubcategorias() {
        const catId = document.getElementById('filtroCat').value;
        if (catId) {
            try {
                const r = await fetch(`${baseUrl}/api/ingresos_mp.php?action=subcategorias&categoria_id=${catId}`);
                const d = await r.json();
                document.getElementById('filtroSubcat').innerHTML = '<option value="">Todas</option>' + (d.subcategorias || []).map(s => `<option value="${s.id_subcategoria}">${s.nombre}</option>`).join('');
            } catch (e) { }
        } else {
            document.getElementById('filtroSubcat').innerHTML = '<option value="">Todas</option>';
        }
        filtrarProductos();
    }

    function filtrarProductos() {
        const catId = document.getElementById('filtroCat').value;
        const subcatId = document.getElementById('filtroSubcat').value;
        productosFiltrados = productos.filter(p => (!catId || p.id_categoria == catId) && (!subcatId || p.id_subcategoria == subcatId));
        renderLineas();
    }

    function toggleFactura() { conFactura = document.getElementById('docConFactura').checked; document.getElementById('rowIva').style.display = conFactura ? 'flex' : 'none'; renderLineas(); }

    function agregarLinea() { lineas.push({ id_inventario: '', cantidad: '', subtotal_doc: '', unidad: '' }); renderLineas(); }

    function renderLineas() {
        const thead = document.getElementById('lineasHead');
        // Nuevo flujo: Producto → Cantidad → Subtotal Doc → Costo Unit (calculado)
        // Con factura: adicional columna Costo Unit IVA (Costo - 13%)
        if (conFactura) {
            thead.innerHTML = `<tr>
            <th style="min-width:180px;">Producto</th>
            <th style="width:50px;">Und</th>
            <th style="width:80px;">Cantidad</th>
            <th style="width:100px;">Subtotal Doc</th>
            <th style="width:90px;">Costo Bruto</th>
            <th style="width:90px;">Costo -IVA</th>
            <th style="width:40px;"></th>
        </tr>`;
        } else {
            thead.innerHTML = `<tr>
            <th style="min-width:180px;">Producto</th>
            <th style="width:50px;">Und</th>
            <th style="width:80px;">Cantidad</th>
            <th style="width:100px;">Subtotal Doc</th>
            <th style="width:90px;">Costo Unit.</th>
            <th style="width:40px;"></th>
        </tr>`;
        }

        const tbody = document.getElementById('lineasBody');
        if (lineas.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${conFactura ? 7 : 6}" style="text-align:center;padding:25px;color:#6c757d;"><i class="fas fa-inbox"></i> Agregar líneas</td></tr>`;
            recalcularTotales();
            return;
        }

        tbody.innerHTML = lineas.map((l, i) => {
            const prod = productos.find(p => p.id_inventario == l.id_inventario);
            const und = prod?.unidad || '-';
            const cant = parseFloat(l.cantidad) || 0;
            const subtotalDoc = parseFloat(l.subtotal_doc) || 0;

            // Calcular costo unitario
            let costoBruto = cant > 0 ? subtotalDoc / cant : 0;
            let costoIVA = costoBruto * 0.87;  // Costo Bruto menos 13%

            // Guardar el costo calculado para usar al guardar
            lineas[i].costo_bruto = costoBruto;
            lineas[i].costo_iva = costoIVA;

            let html = `<tr>
            <td><select onchange="actualizarLinea(${i},'id_inventario',this.value)">
                <option value="">Seleccione...</option>
                ${productosFiltrados.map(p => `<option value="${p.id_inventario}" ${p.id_inventario == l.id_inventario ? 'selected' : ''}>${p.codigo} - ${p.nombre}</option>`).join('')}
            </select></td>
            <td style="text-align:center;font-weight:600;">${und}</td>
            <td><input type="number" step="0.01" min="0" value="${l.cantidad}" onchange="actualizarLinea(${i},'cantidad',this.value)" placeholder="0.00"></td>
            <td><input type="number" step="0.01" min="0" value="${l.subtotal_doc}" onchange="actualizarLinea(${i},'subtotal_doc',this.value)" placeholder="0.00"></td>`;

            if (conFactura) {
                // Con factura: mostrar Costo Bruto y Costo IVA (Bruto * 0.87)
                html += `<td class="col-calc">${formatNum(costoBruto, 4)}</td>`;
                html += `<td class="col-calc" style="background:#d4edda;color:#155724;font-weight:600;">${formatNum(costoIVA, 4)}</td>`;
            } else {
                // Sin factura: solo Costo Unitario
                html += `<td class="col-calc" style="font-weight:600;">${formatNum(costoBruto, 4)}</td>`;
            }

            html += `<td><button class="btn-icon anular" onclick="eliminarLinea(${i})"><i class="fas fa-trash"></i></button></td></tr>`;
            return html;
        }).join('');

        recalcularTotales();
    }

    function actualizarLinea(i, campo, valor) { lineas[i][campo] = campo === 'id_inventario' ? valor : valor; renderLineas(); }
    function eliminarLinea(i) { lineas.splice(i, 1); renderLineas(); }

    function recalcularTotales() {
        let totalDoc = 0;
        let totalNeto = 0;
        let totalIva = 0;

        lineas.forEach(l => {
            const subtotalDoc = parseFloat(l.subtotal_doc) || 0;
            totalDoc += subtotalDoc;

            if (conFactura) {
                // Neto = Subtotal * 0.87 (descontando 13%)
                const neto = subtotalDoc * 0.87;
                totalNeto += neto;
                totalIva += subtotalDoc * 0.13;  // El 13%
            } else {
                totalNeto = totalDoc;
            }
        });

        const mon = monedaActual === 'USD' ? 'USD' : 'Bs.';

        if (conFactura) {
            document.getElementById('totalSubtotal').textContent = `${mon} ${formatNum(totalNeto)}`;
            document.getElementById('totalIva').textContent = `${mon} ${formatNum(totalIva)}`;
            document.getElementById('totalFinal').textContent = `${mon} ${formatNum(totalDoc)}`;
        } else {
            document.getElementById('totalSubtotal').textContent = `${mon} ${formatNum(totalDoc)}`;
            document.getElementById('totalFinal').textContent = `${mon} ${formatNum(totalDoc)}`;
        }
    }

    async function guardarIngreso() {
        if (!document.getElementById('docProveedor').value) { alert('❌ Seleccione proveedor'); return; }
        if (lineas.length === 0) { alert('❌ Agregue líneas'); return; }
        if (lineas.some(l => !l.id_inventario || !l.cantidad || !l.subtotal_doc)) { alert('❌ Complete las líneas (Producto, Cantidad y Subtotal)'); return; }

        // Preparar datos con el nuevo flujo
        const lineasData = lineas.map(l => {
            const cant = parseFloat(l.cantidad);
            const subtotalDoc = parseFloat(l.subtotal_doc);
            const costoBruto = cant > 0 ? subtotalDoc / cant : 0;
            const costoNeto = conFactura ? costoBruto / 1.13 : costoBruto;

            return {
                id_inventario: l.id_inventario,
                cantidad: cant,
                costo_unitario: costoNeto,  // Costo sin IVA (para el inventario)
                costo_con_iva: costoBruto,  // Costo con IVA (del documento)
                subtotal: subtotalDoc
            };
        });

        const data = {
            action: 'crear',
            fecha: document.getElementById('docFecha').value,
            id_proveedor: document.getElementById('docProveedor').value,
            referencia: document.getElementById('docReferencia').value,
            con_factura: conFactura,
            moneda: monedaActual,
            observaciones: document.getElementById('docObservaciones').value,
            lineas: lineasData
        };

        console.log('Guardando ingreso:', data);

        try {
            const r = await fetch(`${baseUrl}/api/ingresos_mp.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const d = await r.json();
            if (d.success) {
                alert('✅ ' + d.message);
                cerrarModal();
                cargarDocumentos();
                cargarResumen();
            } else {
                alert('❌ ' + d.message);
            }
        } catch (e) {
            console.error('Error:', e);
            alert('Error al guardar');
        }
    }

    async function verDetalle(id) {
        try {
            const r = await fetch(`${baseUrl}/api/ingresos_mp.php?action=get&id=${id}`); const d = await r.json();
            if (d.success) {
                const doc = d.documento, det = d.detalle;
                document.getElementById('detalleTitulo').textContent = doc.numero_documento;
                document.getElementById('detalleContenido').innerHTML = `
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px;">
                    <div class="form-section"><h4><i class="fas fa-file-alt"></i> Documento</h4><p><strong>Número:</strong> ${doc.numero_documento}</p><p><strong>Fecha:</strong> ${formatFecha(doc.fecha_documento)}</p><p><strong>Ref:</strong> ${doc.referencia_externa || '-'}</p><p><strong>Estado:</strong> <span class="badge ${doc.estado === 'CONFIRMADO' ? 'badge-confirmado' : 'badge-anulado'}">${doc.estado}</span></p></div>
                    <div class="form-section"><h4><i class="fas fa-truck"></i> Proveedor</h4><p><strong>Código:</strong> ${doc.proveedor_codigo}</p><p><strong>Nombre:</strong> ${doc.proveedor_comercial || doc.proveedor_nombre}</p><p><strong>Tipo:</strong> <span class="badge ${doc.proveedor_tipo === 'LOCAL' ? 'badge-local' : 'badge-import'}">${doc.proveedor_tipo}</span></p></div>
                </div>
                <h4><i class="fas fa-list"></i> Detalle</h4>
                <table class="ing-table" style="font-size:0.85rem;"><thead><tr><th>Código</th><th>Producto</th><th>Cant</th><th>Und</th><th>Costo</th><th>Subtotal</th></tr></thead>
                <tbody>${det.map(l => `<tr><td>${l.producto_codigo}</td><td>${l.producto_nombre}</td><td style="text-align:right;">${formatNum(l.cantidad)}</td><td>${l.unidad || '-'}</td><td style="text-align:right;">${formatNum(l.costo_unitario, 4)}</td><td style="text-align:right;font-weight:600;">${formatNum(l.subtotal)}</td></tr>`).join('')}</tbody></table>
                <div style="display:flex;justify-content:flex-end;margin-top:15px;"><div class="totales-box" style="min-width:250px;">
                    <div class="total-row"><span class="total-label">Subtotal:</span><span class="total-value">${doc.moneda} ${formatNum(doc.subtotal)}</span></div>
                    ${doc.con_factura == 1 ? `<div class="total-row"><span class="total-label">IVA:</span><span class="total-value">${doc.moneda} ${formatNum(doc.iva)}</span></div>` : ''}
                    <div class="total-row final"><span class="total-label">TOTAL:</span><span class="total-value">${doc.moneda} ${formatNum(doc.total)}</span></div>
                </div></div>
                ${doc.estado === 'ANULADO' ? `<div class="form-section" style="margin-top:15px;background:#f8d7da;"><h4 style="color:#721c24;"><i class="fas fa-ban"></i> Anulado</h4><p>${doc.motivo_anulacion}</p></div>` : ''}`;
                document.getElementById('modalDetalle').classList.add('show');
            }
        } catch (e) { }
    }

    async function anularDocumento(id) {
        const motivo = prompt('Motivo de anulación:'); if (!motivo) return;
        if (!confirm('¿Anular documento? Se revertirá el stock.')) return;
        try {
            const r = await fetch(`${baseUrl}/api/ingresos_mp.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'anular', id_documento: id, motivo: motivo }) });
            const d = await r.json(); if (d.success) { alert('✅ ' + d.message); cargarDocumentos(); cargarResumen(); } else alert('❌ ' + d.message);
        } catch (e) { alert('Error'); }
    }

    function cerrarModal() { document.getElementById('modalIngreso').classList.remove('show'); }
    function cerrarModalDetalle() { document.getElementById('modalDetalle').classList.remove('show'); }
    function formatNum(v, d = 2) { return (parseFloat(v) || 0).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d }); }
    function formatFecha(f) { if (!f) return '-'; return new Date(f + 'T00:00:00').toLocaleDateString('es-BO', { day: '2-digit', month: 'short', year: 'numeric' }); }

    console.log('✅ Módulo Ingresos MP v1.0');
</script>

<?php require_once '../../includes/footer.php'; ?>