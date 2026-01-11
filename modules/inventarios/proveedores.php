<?php
/**
 * Módulo de Proveedores
 * Sistema MES Hermen Ltda. v1.0
 * Gestión de proveedores locales e importación
 */
require_once '../../config/database.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Proveedores';
$currentPage = 'proveedores';

require_once '../../includes/header.php';
?>

<style>
    :root {
        --color-local: #28a745;
        --color-import: #007bff;
    }

    .prov-module {
        padding: 20px;
        background: #f4f6f9;
        min-height: calc(100vh - 60px);
    }

    .prov-header {
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

    .prov-header-left {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .prov-title {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .prov-title-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        background: linear-gradient(135deg, #1a237e, #4fc3f7);
    }

    .prov-title h1 {
        font-size: 1.6rem;
        color: #1a1a2e;
        margin: 0;
    }

    .prov-title p {
        font-size: 0.85rem;
        color: #6c757d;
        margin: 0;
    }

    .prov-header-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn-action {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.2s;
    }

    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-nuevo {
        background: linear-gradient(135deg, #1a237e, #4fc3f7);
        color: white;
    }

    .prov-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .kpi-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .kpi-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: white;
    }

    .kpi-icon.total {
        background: linear-gradient(135deg, #1a237e, #4fc3f7);
    }

    .kpi-icon.local {
        background: linear-gradient(135deg, #28a745, #20c997);
    }

    .kpi-icon.import {
        background: linear-gradient(135deg, #007bff, #00d4ff);
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

    .filtros-bar {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
        align-items: center;
        background: white;
        padding: 15px 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .filtros-bar input,
    .filtros-bar select {
        padding: 10px 15px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        font-size: 0.9rem;
    }

    .filtros-bar input {
        min-width: 250px;
    }

    .filtro-tipo {
        display: flex;
        gap: 8px;
    }

    .filtro-tipo button {
        padding: 8px 16px;
        border: 2px solid #dee2e6;
        background: white;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.2s;
    }

    .filtro-tipo button.active {
        border-color: #1a237e;
        background: #1a237e;
        color: white;
    }

    .filtro-tipo button:hover:not(.active) {
        border-color: #1a237e;
        color: #1a237e;
    }

    .prov-table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }

    .prov-table {
        width: 100%;
        border-collapse: collapse;
    }

    .prov-table th {
        background: #f8f9fa;
        padding: 14px 16px;
        text-align: left;
        font-size: 0.8rem;
        color: #6c757d;
        text-transform: uppercase;
        font-weight: 600;
        border-bottom: 2px solid #e9ecef;
    }

    .prov-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #f1f1f1;
        vertical-align: middle;
    }

    .prov-table tr:hover {
        background: #f8f9fa;
    }

    .badge-tipo {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-tipo.local {
        background: #d4edda;
        color: #155724;
    }

    .badge-tipo.import {
        background: #cce5ff;
        color: #004085;
    }

    .badge-moneda {
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .badge-moneda.bob {
        background: #fff3cd;
        color: #856404;
    }

    .badge-moneda.usd {
        background: #d1ecf1;
        color: #0c5460;
    }

    .prov-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .prov-codigo {
        font-weight: 700;
        color: #1a1a2e;
    }

    .prov-razon {
        font-size: 0.9rem;
        color: #495057;
    }

    .prov-comercial {
        font-size: 0.8rem;
        color: #6c757d;
        font-style: italic;
    }

    .prov-contacto {
        font-size: 0.85rem;
    }

    .prov-contacto i {
        width: 16px;
        color: #6c757d;
        margin-right: 5px;
    }

    .btn-icon {
        width: 34px;
        height: 34px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 0 3px;
        transition: all 0.2s;
    }

    .btn-icon:hover {
        transform: scale(1.1);
    }

    .btn-icon.ver {
        background: #17a2b8;
        color: white;
    }

    .btn-icon.editar {
        background: #ffc107;
        color: #212529;
    }

    .btn-icon.eliminar {
        background: #dc3545;
        color: white;
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
    }

    .modal.show {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 16px;
        width: 95%;
        max-width: 800px;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 25px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        background: linear-gradient(135deg, #1a237e, #4fc3f7);
        color: white;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.2rem;
    }

    .modal-close {
        width: 35px;
        height: 35px;
        border: none;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.2rem;
        color: white;
    }

    .modal-close:hover {
        background: rgba(255, 255, 255, 0.3);
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
        gap: 20px;
        margin-bottom: 15px;
    }

    .form-row.three {
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

    .form-group label .required {
        color: #dc3545;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 10px 14px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: border-color 0.2s;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #1a237e;
        box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.1);
    }

    .form-group textarea {
        min-height: 80px;
        resize: vertical;
    }

    .form-section {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .form-section h4 {
        margin: 0 0 15px 0;
        font-size: 0.95rem;
        color: #495057;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-section h4 i {
        color: #1a237e;
    }

    .tipo-selector {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
    }

    .tipo-option {
        flex: 1;
        padding: 20px;
        border: 2px solid #dee2e6;
        border-radius: 12px;
        cursor: pointer;
        text-align: center;
        transition: all 0.2s;
    }

    .tipo-option:hover {
        border-color: #1a237e;
    }

    .tipo-option.active.local {
        border-color: var(--color-local);
        background: rgba(40, 167, 69, 0.1);
    }

    .tipo-option.active.import {
        border-color: var(--color-import);
        background: rgba(0, 123, 255, 0.1);
    }

    .tipo-option i {
        font-size: 2rem;
        margin-bottom: 10px;
        display: block;
    }

    .tipo-option.local i {
        color: var(--color-local);
    }

    .tipo-option.import i {
        color: var(--color-import);
    }

    .tipo-option span {
        font-weight: 600;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.2s;
    }

    .btn:hover {
        transform: translateY(-1px);
    }

    .btn-primary {
        background: linear-gradient(135deg, #1a237e, #4fc3f7);
        color: white;
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-success {
        background: #28a745;
        color: white;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
    }

    .empty-state h3 {
        margin-bottom: 10px;
        color: #495057;
    }

    @media (max-width: 768px) {
        .prov-header {
            flex-direction: column;
            align-items: stretch;
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .form-row.three {
            grid-template-columns: 1fr;
        }

        .form-group.full {
            grid-column: span 1;
        }

        .filtros-bar {
            flex-direction: column;
        }

        .filtros-bar input {
            min-width: 100%;
        }
    }
</style>

<div class="prov-module">
    <!-- HEADER -->
    <div class="prov-header">
        <div class="prov-header-left">
            <div class="prov-title">
                <div class="prov-title-icon"><i class="fas fa-truck"></i></div>
                <div>
                    <h1>Proveedores</h1>
                    <p>Gestión de proveedores locales e importación</p>
                </div>
            </div>
        </div>
        <div class="prov-header-actions">
            <button class="btn-action btn-nuevo" onclick="abrirModalNuevo()">
                <i class="fas fa-plus"></i> Nuevo Proveedor
            </button>
        </div>
    </div>

    <!-- KPIs -->
    <div class="prov-kpis">
        <div class="kpi-card">
            <div class="kpi-icon total"><i class="fas fa-building"></i></div>
            <div class="kpi-info">
                <div class="kpi-label">Total Proveedores</div>
                <div class="kpi-value" id="kpiTotal">0</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon local"><i class="fas fa-map-marker-alt"></i></div>
            <div class="kpi-info">
                <div class="kpi-label">Locales (Bolivia)</div>
                <div class="kpi-value" id="kpiLocal">0</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon import"><i class="fas fa-globe-americas"></i></div>
            <div class="kpi-info">
                <div class="kpi-label">Importación</div>
                <div class="kpi-value" id="kpiImport">0</div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="filtros-bar">
        <input type="text" id="buscarProveedor" placeholder="Buscar por código, nombre o NIT..."
            onkeyup="filtrarProveedores()">
        <div class="filtro-tipo">
            <button class="active" onclick="filtrarPorTipo('todos')">Todos</button>
            <button onclick="filtrarPorTipo('LOCAL')">🇧🇴 Locales</button>
            <button onclick="filtrarPorTipo('IMPORTACION')">🌎 Importación</button>
        </div>
    </div>

    <!-- TABLA -->
    <div class="prov-table-container">
        <table class="prov-table">
            <thead>
                <tr>
                    <th>Proveedor</th>
                    <th>Tipo</th>
                    <th>País</th>
                    <th>Contacto</th>
                    <th>Condición Pago</th>
                    <th>Moneda</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="proveedoresBody">
                <tr>
                    <td colspan="7" style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i>
                        Cargando...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL NUEVO/EDITAR -->
<div class="modal" id="modalProveedor">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-truck"></i> <span id="modalTitulo">Nuevo Proveedor</span></h3>
            <button class="modal-close" onclick="cerrarModal()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="provId">

            <!-- Selector de Tipo -->
            <div class="tipo-selector">
                <div class="tipo-option local active" onclick="seleccionarTipo('LOCAL')">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>🇧🇴 Local (Bolivia)</span>
                </div>
                <div class="tipo-option import" onclick="seleccionarTipo('IMPORTACION')">
                    <i class="fas fa-globe-americas"></i>
                    <span>🌎 Importación</span>
                </div>
            </div>

            <!-- Datos Básicos -->
            <div class="form-section">
                <h4><i class="fas fa-info-circle"></i> Datos Básicos</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Código <span class="required">*</span></label>
                        <input type="text" id="provCodigo" placeholder="PROV-001">
                    </div>
                    <div class="form-group">
                        <label>NIT / Tax ID</label>
                        <input type="text" id="provNit" placeholder="1234567890">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group full">
                        <label>Razón Social <span class="required">*</span></label>
                        <input type="text" id="provRazonSocial" placeholder="Nombre legal completo">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group full">
                        <label>Nombre Comercial</label>
                        <input type="text" id="provNombreComercial" placeholder="Nombre corto o comercial">
                    </div>
                </div>
            </div>

            <!-- Ubicación -->
            <div class="form-section">
                <h4><i class="fas fa-map-marked-alt"></i> Ubicación</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>País</label>
                        <select id="provPais">
                            <option value="Bolivia">Bolivia</option>
                            <option value="Perú">Perú</option>
                            <option value="Brasil">Brasil</option>
                            <option value="Argentina">Argentina</option>
                            <option value="Chile">Chile</option>
                            <option value="Colombia">Colombia</option>
                            <option value="Estados Unidos">Estados Unidos</option>
                            <option value="China">China</option>
                            <option value="Alemania">Alemania</option>
                            <option value="Italia">Italia</option>
                            <option value="Corea del Sur">Corea del Sur</option>
                            <option value="Taiwán">Taiwán</option>
                            <option value="India">India</option>
                            <option value="Turquía">Turquía</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ciudad</label>
                        <input type="text" id="provCiudad" placeholder="Ciudad">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group full">
                        <label>Dirección</label>
                        <input type="text" id="provDireccion" placeholder="Dirección completa">
                    </div>
                </div>
            </div>

            <!-- Contacto -->
            <div class="form-section">
                <h4><i class="fas fa-address-book"></i> Contacto</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre Contacto</label>
                        <input type="text" id="provContactoNombre" placeholder="Nombre del contacto">
                    </div>
                    <div class="form-group">
                        <label>Teléfono Contacto</label>
                        <input type="text" id="provContactoTel" placeholder="Celular del contacto">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Teléfono Empresa</label>
                        <input type="text" id="provTelefono" placeholder="Teléfono fijo">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="provEmail" placeholder="correo@empresa.com">
                    </div>
                </div>
            </div>

            <!-- Condiciones Comerciales -->
            <div class="form-section">
                <h4><i class="fas fa-handshake"></i> Condiciones Comerciales</h4>
                <div class="form-row three">
                    <div class="form-group">
                        <label>Moneda</label>
                        <select id="provMoneda">
                            <option value="BOB">BOB - Bolivianos</option>
                            <option value="USD">USD - Dólares</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Condición de Pago</label>
                        <select id="provCondicionPago">
                            <option value="Contado">Contado</option>
                            <option value="15 días">15 días</option>
                            <option value="30 días">30 días</option>
                            <option value="45 días">45 días</option>
                            <option value="60 días">60 días</option>
                            <option value="90 días">90 días</option>
                            <option value="30 días T/T">30 días T/T</option>
                            <option value="60 días L/C">60 días L/C</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Días Crédito</label>
                        <input type="number" id="provDiasCredito" value="0" min="0">
                    </div>
                </div>
            </div>

            <!-- Observaciones -->
            <div class="form-group">
                <label>Observaciones</label>
                <textarea id="provObservaciones" placeholder="Notas adicionales sobre el proveedor..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
            <button class="btn btn-success" onclick="guardarProveedor()"><i class="fas fa-save"></i> Guardar</button>
        </div>
    </div>
</div>

<!-- MODAL VER DETALLE -->
<div class="modal" id="modalDetalle">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-building"></i> <span id="detalleTitulo">Detalle Proveedor</span></h3>
            <button class="modal-close" onclick="cerrarModalDetalle()">&times;</button>
        </div>
        <div class="modal-body" id="detalleContenido">
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModalDetalle()">Cerrar</button>
        </div>
    </div>
</div>

<script>
    const baseUrl = window.location.origin + '/mes_hermen';
    let proveedores = [];
    let tipoSeleccionado = 'LOCAL';
    let filtroTipoActual = 'todos';

    document.addEventListener('DOMContentLoaded', cargarProveedores);

    async function cargarProveedores() {
        try {
            const r = await fetch(`${baseUrl}/api/proveedores.php?action=list`);
            const d = await r.json();
            console.log('Proveedores:', d);

            if (d.success) {
                proveedores = d.proveedores || [];

                // Actualizar KPIs
                document.getElementById('kpiTotal').textContent = d.totales.LOCAL + d.totales.IMPORTACION;
                document.getElementById('kpiLocal').textContent = d.totales.LOCAL;
                document.getElementById('kpiImport').textContent = d.totales.IMPORTACION;

                renderProveedores();
            }
        } catch (e) {
            console.error('Error:', e);
        }
    }

    function renderProveedores() {
        const tbody = document.getElementById('proveedoresBody');
        const buscar = document.getElementById('buscarProveedor').value.toLowerCase();

        let filtrados = proveedores.filter(p => {
            const matchTipo = filtroTipoActual === 'todos' || p.tipo === filtroTipoActual;
            const matchBuscar = !buscar ||
                (p.codigo?.toLowerCase().includes(buscar)) ||
                (p.razon_social?.toLowerCase().includes(buscar)) ||
                (p.nombre_comercial?.toLowerCase().includes(buscar)) ||
                (p.nit?.toLowerCase().includes(buscar));
            return matchTipo && matchBuscar;
        });

        if (filtrados.length === 0) {
            tbody.innerHTML = `
            <tr><td colspan="7">
                <div class="empty-state">
                    <i class="fas fa-truck"></i>
                    <h3>No se encontraron proveedores</h3>
                    <p>Intenta cambiar los filtros o agrega un nuevo proveedor</p>
                </div>
            </td></tr>`;
            return;
        }

        tbody.innerHTML = filtrados.map(p => `
        <tr>
            <td>
                <div class="prov-info">
                    <span class="prov-codigo">${p.codigo}</span>
                    <span class="prov-razon">${p.razon_social}</span>
                    ${p.nombre_comercial ? `<span class="prov-comercial">${p.nombre_comercial}</span>` : ''}
                </div>
            </td>
            <td><span class="badge-tipo ${p.tipo === 'LOCAL' ? 'local' : 'import'}">${p.tipo === 'LOCAL' ? '🇧🇴 Local' : '🌎 Import'}</span></td>
            <td>${p.pais || '-'}</td>
            <td>
                <div class="prov-contacto">
                    ${p.nombre_contacto ? `<div><i class="fas fa-user"></i>${p.nombre_contacto}</div>` : ''}
                    ${p.telefono ? `<div><i class="fas fa-phone"></i>${p.telefono}</div>` : ''}
                    ${p.email ? `<div><i class="fas fa-envelope"></i>${p.email}</div>` : ''}
                </div>
            </td>
            <td>${p.condicion_pago || 'Contado'}</td>
            <td><span class="badge-moneda ${p.moneda?.toLowerCase() || 'bob'}">${p.moneda || 'BOB'}</span></td>
            <td>
                <button class="btn-icon ver" onclick="verDetalle(${p.id_proveedor})" title="Ver detalle"><i class="fas fa-eye"></i></button>
                <button class="btn-icon editar" onclick="editarProveedor(${p.id_proveedor})" title="Editar"><i class="fas fa-edit"></i></button>
                <button class="btn-icon eliminar" onclick="eliminarProveedor(${p.id_proveedor})" title="Eliminar"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
    }

    function filtrarProveedores() {
        renderProveedores();
    }

    function filtrarPorTipo(tipo) {
        filtroTipoActual = tipo;
        document.querySelectorAll('.filtro-tipo button').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
        renderProveedores();
    }

    function seleccionarTipo(tipo) {
        tipoSeleccionado = tipo;
        document.querySelectorAll('.tipo-option').forEach(opt => opt.classList.remove('active'));
        document.querySelector(`.tipo-option.${tipo === 'LOCAL' ? 'local' : 'import'}`).classList.add('active');

        // Cambiar país por defecto según tipo
        if (tipo === 'LOCAL') {
            document.getElementById('provPais').value = 'Bolivia';
            document.getElementById('provMoneda').value = 'BOB';
        } else {
            document.getElementById('provMoneda').value = 'USD';
        }
    }

    function abrirModalNuevo() {
        document.getElementById('provId').value = '';
        document.getElementById('provCodigo').value = generarCodigo();
        document.getElementById('provRazonSocial').value = '';
        document.getElementById('provNombreComercial').value = '';
        document.getElementById('provNit').value = '';
        document.getElementById('provPais').value = 'Bolivia';
        document.getElementById('provCiudad').value = '';
        document.getElementById('provDireccion').value = '';
        document.getElementById('provContactoNombre').value = '';
        document.getElementById('provContactoTel').value = '';
        document.getElementById('provTelefono').value = '';
        document.getElementById('provEmail').value = '';
        document.getElementById('provMoneda').value = 'BOB';
        document.getElementById('provCondicionPago').value = 'Contado';
        document.getElementById('provDiasCredito').value = '0';
        document.getElementById('provObservaciones').value = '';

        seleccionarTipo('LOCAL');
        document.getElementById('modalTitulo').textContent = 'Nuevo Proveedor';
        document.getElementById('modalProveedor').classList.add('show');
    }

    function editarProveedor(id) {
        const p = proveedores.find(x => x.id_proveedor == id);
        if (!p) return;

        document.getElementById('provId').value = p.id_proveedor;
        document.getElementById('provCodigo').value = p.codigo || '';
        document.getElementById('provRazonSocial').value = p.razon_social || '';
        document.getElementById('provNombreComercial').value = p.nombre_comercial || '';
        document.getElementById('provNit').value = p.nit || '';
        document.getElementById('provPais').value = p.pais || 'Bolivia';
        document.getElementById('provCiudad').value = p.ciudad || '';
        document.getElementById('provDireccion').value = p.direccion || '';
        document.getElementById('provContactoNombre').value = p.nombre_contacto || '';
        document.getElementById('provContactoTel').value = p.contacto_telefono || '';
        document.getElementById('provTelefono').value = p.telefono || '';
        document.getElementById('provEmail').value = p.email || '';
        document.getElementById('provMoneda').value = p.moneda || 'BOB';
        document.getElementById('provCondicionPago').value = p.condicion_pago || 'Contado';
        document.getElementById('provDiasCredito').value = p.dias_credito || '0';
        document.getElementById('provObservaciones').value = p.observaciones || '';

        seleccionarTipo(p.tipo || 'LOCAL');
        document.getElementById('modalTitulo').textContent = 'Editar Proveedor';
        document.getElementById('modalProveedor').classList.add('show');
    }

    async function guardarProveedor() {
        const codigo = document.getElementById('provCodigo').value.trim();
        const razonSocial = document.getElementById('provRazonSocial').value.trim();

        if (!codigo || !razonSocial) {
            alert('❌ Código y Razón Social son requeridos');
            return;
        }

        const data = {
            action: document.getElementById('provId').value ? 'update' : 'create',
            id_proveedor: document.getElementById('provId').value || null,
            codigo: codigo,
            razon_social: razonSocial,
            nombre_comercial: document.getElementById('provNombreComercial').value,
            tipo: tipoSeleccionado,
            nit: document.getElementById('provNit').value,
            pais: document.getElementById('provPais').value,
            ciudad: document.getElementById('provCiudad').value,
            direccion: document.getElementById('provDireccion').value,
            nombre_contacto: document.getElementById('provContactoNombre').value,
            contacto_telefono: document.getElementById('provContactoTel').value,
            telefono: document.getElementById('provTelefono').value,
            email: document.getElementById('provEmail').value,
            moneda: document.getElementById('provMoneda').value,
            condicion_pago: document.getElementById('provCondicionPago').value,
            dias_credito: document.getElementById('provDiasCredito').value,
            observaciones: document.getElementById('provObservaciones').value
        };

        try {
            const r = await fetch(`${baseUrl}/api/proveedores.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const d = await r.json();

            if (d.success) {
                alert('✅ ' + d.message);
                cerrarModal();
                cargarProveedores();
            } else {
                alert('❌ ' + d.message);
            }
        } catch (e) {
            console.error('Error:', e);
            alert('❌ Error al guardar');
        }
    }

    async function eliminarProveedor(id) {
        const p = proveedores.find(x => x.id_proveedor == id);
        if (!confirm(`¿Eliminar el proveedor "${p?.razon_social}"?`)) return;

        try {
            const r = await fetch(`${baseUrl}/api/proveedores.php`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_proveedor: id })
            });
            const d = await r.json();

            if (d.success) {
                alert('✅ ' + d.message);
                cargarProveedores();
            } else {
                alert('❌ ' + d.message);
            }
        } catch (e) {
            alert('❌ Error al eliminar');
        }
    }

    function verDetalle(id) {
        const p = proveedores.find(x => x.id_proveedor == id);
        if (!p) return;

        document.getElementById('detalleTitulo').textContent = p.nombre_comercial || p.razon_social;
        document.getElementById('detalleContenido').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div class="form-section">
                <h4><i class="fas fa-info-circle"></i> Información General</h4>
                <p><strong>Código:</strong> ${p.codigo}</p>
                <p><strong>Razón Social:</strong> ${p.razon_social}</p>
                <p><strong>Nombre Comercial:</strong> ${p.nombre_comercial || '-'}</p>
                <p><strong>NIT:</strong> ${p.nit || '-'}</p>
                <p><strong>Tipo:</strong> <span class="badge-tipo ${p.tipo === 'LOCAL' ? 'local' : 'import'}">${p.tipo === 'LOCAL' ? '🇧🇴 Local' : '🌎 Importación'}</span></p>
            </div>
            <div class="form-section">
                <h4><i class="fas fa-map-marked-alt"></i> Ubicación</h4>
                <p><strong>País:</strong> ${p.pais || '-'}</p>
                <p><strong>Ciudad:</strong> ${p.ciudad || '-'}</p>
                <p><strong>Dirección:</strong> ${p.direccion || '-'}</p>
            </div>
            <div class="form-section">
                <h4><i class="fas fa-address-book"></i> Contacto</h4>
                <p><strong>Nombre:</strong> ${p.nombre_contacto || '-'}</p>
                <p><strong>Teléfono:</strong> ${p.contacto_telefono || '-'}</p>
                <p><strong>Tel. Empresa:</strong> ${p.telefono || '-'}</p>
                <p><strong>Email:</strong> ${p.email || '-'}</p>
            </div>
            <div class="form-section">
                <h4><i class="fas fa-handshake"></i> Condiciones</h4>
                <p><strong>Moneda:</strong> <span class="badge-moneda ${p.moneda?.toLowerCase()}">${p.moneda}</span></p>
                <p><strong>Condición Pago:</strong> ${p.condicion_pago || 'Contado'}</p>
                <p><strong>Días Crédito:</strong> ${p.dias_credito || 0}</p>
            </div>
        </div>
        ${p.observaciones ? `<div class="form-section"><h4><i class="fas fa-sticky-note"></i> Observaciones</h4><p>${p.observaciones}</p></div>` : ''}
    `;
        document.getElementById('modalDetalle').classList.add('show');
    }

    function cerrarModal() {
        document.getElementById('modalProveedor').classList.remove('show');
    }

    function cerrarModalDetalle() {
        document.getElementById('modalDetalle').classList.remove('show');
    }

    function generarCodigo() {
        const num = proveedores.length + 1;
        return `PROV-${String(num).padStart(3, '0')}`;
    }

    console.log('✅ Módulo Proveedores v1.0 cargado');
</script>

<?php require_once '../../includes/footer.php'; ?>