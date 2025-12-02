<?php
/**
 * Módulo de Inventarios - Dashboard Profesional
 * Sistema MES Hermen Ltda.
 * Versión: 2.0
 * 
 * Características:
 * - Dashboard con KPIs por tipo de inventario
 * - Vista por categorías con valores
 * - Control de acceso por tipo (preparado)
 * - Movimientos contextuales
 */

require_once '../../config/database.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Inventarios';
$currentPage = 'inventarios';
require_once '../../includes/header.php';
?>

<style>
/* ========== VARIABLES CSS ========== */
:root {
    --color-mp: #007bff;      /* Materias Primas - Azul */
    --color-caq: #6f42c1;     /* Colorantes - Púrpura */
    --color-emp: #fd7e14;     /* Empaque - Naranja */
    --color-acc: #e83e8c;     /* Accesorios - Rosa */
    --color-wip: #17a2b8;     /* En Proceso - Cyan */
    --color-pt: #28a745;      /* Terminados - Verde */
    --color-rep: #6c757d;     /* Repuestos - Gris */
}

/* ========== LAYOUT PRINCIPAL ========== */
.inv-module {
    padding: 20px;
    background: #f4f6f9;
    min-height: calc(100vh - 60px);
}

.inv-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.inv-header h1 {
    font-size: 1.8rem;
    color: #1a1a2e;
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
}

.inv-header h1 i {
    color: #007bff;
}

/* ========== RESUMEN GENERAL (KPIs) ========== */
.inv-kpis {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.kpi-card {
    background: white;
    border-radius: 16px;
    padding: 20px 25px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}

.kpi-icon {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.kpi-icon.items { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.kpi-icon.valor { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.kpi-icon.alertas { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }
.kpi-icon.tipos { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

.kpi-info {
    flex: 1;
}

.kpi-label {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 4px;
}

.kpi-value {
    font-size: 1.6rem;
    font-weight: 700;
    color: #1a1a2e;
}

.kpi-value.success { color: #28a745; }
.kpi-value.warning { color: #ffc107; }
.kpi-value.danger { color: #dc3545; }

/* ========== TIPOS DE INVENTARIO (Cards principales) ========== */
.inv-tipos-section {
    margin-bottom: 30px;
}

.section-title {
    font-size: 1.1rem;
    color: #495057;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title i {
    color: #007bff;
}

.inv-tipos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.tipo-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 3px solid transparent;
    position: relative;
    overflow: hidden;
}

.tipo-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--tipo-color);
}

.tipo-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
}

.tipo-card.active {
    border-color: var(--tipo-color);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.tipo-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
}

.tipo-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
    background: var(--tipo-color);
}

.tipo-nombre {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1a1a2e;
    line-height: 1.2;
}

.tipo-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.tipo-stat {
    text-align: center;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 8px;
}

.tipo-stat-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1a1a2e;
}

.tipo-stat-label {
    font-size: 0.7rem;
    color: #6c757d;
    text-transform: uppercase;
}

.tipo-valor-total {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e9ecef;
    text-align: center;
}

.tipo-valor-total .valor {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--tipo-color);
}

.tipo-valor-total .label {
    font-size: 0.7rem;
    color: #6c757d;
}

/* ========== ÁREA DE TRABAJO (Tipo Seleccionado) ========== */
.inv-workspace {
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    display: none;
}

.inv-workspace.active {
    display: block;
}

.workspace-header {
    padding: 20px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e9ecef;
}

.workspace-title {
    display: flex;
    align-items: center;
    gap: 15px;
}

.workspace-title .icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: white;
}

.workspace-title h2 {
    margin: 0;
    font-size: 1.3rem;
    color: #1a1a2e;
}

.workspace-title .subtitle {
    font-size: 0.85rem;
    color: #6c757d;
}

.workspace-actions {
    display: flex;
    gap: 10px;
}

.workspace-actions .btn {
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.workspace-actions .btn-ingreso {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    color: white;
}

.workspace-actions .btn-salida {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
}

.workspace-actions .btn-historial {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
}

.workspace-actions .btn-nuevo {
    background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
    color: white;
}

.workspace-actions .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

/* ========== CATEGORÍAS DEL TIPO ========== */
.workspace-body {
    padding: 25px;
}

.categorias-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.categoria-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 16px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.categoria-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    border-color: var(--tipo-color);
}

.categoria-card.active {
    background: white;
    border-color: var(--tipo-color);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.categoria-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.categoria-info h4 {
    margin: 0 0 4px 0;
    font-size: 1rem;
    color: #1a1a2e;
}

.categoria-info .codigo {
    font-size: 0.75rem;
    color: #6c757d;
    font-family: 'Consolas', monospace;
}

.categoria-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    background: var(--tipo-color);
    color: white;
}

.categoria-stats {
    display: flex;
    justify-content: space-between;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
}

.categoria-stat {
    text-align: center;
}

.categoria-stat .value {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1a1a2e;
}

.categoria-stat .label {
    font-size: 0.7rem;
    color: #6c757d;
    text-transform: uppercase;
}

.categoria-stat .value.money {
    color: #28a745;
}

/* ========== TABLA DE PRODUCTOS ========== */
.productos-section {
    margin-top: 25px;
    display: none;
}

.productos-section.active {
    display: block;
}

.productos-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
}

.productos-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 10px;
}

.productos-search {
    display: flex;
    align-items: center;
    gap: 10px;
}

.productos-search input {
    padding: 8px 15px;
    border: 1px solid #ced4da;
    border-radius: 8px;
    width: 250px;
}

.productos-tabla {
    width: 100%;
    border-collapse: collapse;
}

.productos-tabla th {
    background: #1a1a2e;
    color: white;
    padding: 14px 12px;
    text-align: left;
    font-size: 0.85rem;
    font-weight: 500;
}

.productos-tabla td {
    padding: 12px;
    border-bottom: 1px solid #e9ecef;
    font-size: 0.9rem;
}

.productos-tabla tr:hover {
    background: #f8f9fa;
}

.productos-tabla .codigo {
    font-family: 'Consolas', monospace;
    font-size: 0.85rem;
    color: #495057;
}

.productos-tabla .nombre {
    font-weight: 500;
    color: #1a1a2e;
}

.productos-tabla .stock {
    font-weight: 600;
}

.productos-tabla .stock.ok { color: #28a745; }
.productos-tabla .stock.bajo { color: #ffc107; }
.productos-tabla .stock.critico { color: #dc3545; }

.productos-tabla .valor {
    text-align: right;
    color: #28a745;
    font-weight: 500;
}

.productos-tabla .acciones {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.productos-tabla .btn-accion {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.btn-accion.ver { background: #e3f2fd; color: #1976d2; }
.btn-accion.editar { background: #fff3e0; color: #f57c00; }
.btn-accion.movimiento { background: #e8f5e9; color: #388e3c; }
.btn-accion.kardex { background: #f3e5f5; color: #7b1fa2; }

.btn-accion:hover {
    transform: scale(1.1);
}

/* Estado badges */
.estado-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.estado-badge.ok { background: #d4edda; color: #155724; }
.estado-badge.bajo { background: #fff3cd; color: #856404; }
.estado-badge.critico { background: #f8d7da; color: #721c24; }
.estado-badge.sin-stock { background: #6c757d; color: white; }

/* ========== MENSAJE INICIAL ========== */
.workspace-placeholder {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.workspace-placeholder i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.3;
}

.workspace-placeholder h3 {
    margin: 0 0 10px 0;
    color: #495057;
}

.workspace-placeholder p {
    margin: 0;
    font-size: 0.95rem;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 1200px) {
    .inv-kpis {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .inv-kpis {
        grid-template-columns: 1fr;
    }
    
    .inv-tipos-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .workspace-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .workspace-actions {
        flex-wrap: wrap;
        justify-content: center;
    }
}

/* ========== ANIMACIONES ========== */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.fade-in {
    animation: fadeIn 0.3s ease;
}

/* ========== LOADING ========== */
.loading-spinner {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: #6c757d;
}

.loading-spinner i {
    font-size: 2rem;
    margin-bottom: 10px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* ========== BOTÓN VOLVER ========== */
.btn-volver {
    background: #f8f9fa;
    border: 1px solid #ced4da;
    color: #495057;
    padding: 8px 15px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    transition: all 0.2s;
}

.btn-volver:hover {
    background: #e9ecef;
}
</style>

<!-- ========== HTML PRINCIPAL ========== -->
<div class="inv-module">
    <!-- Header -->
    <div class="inv-header">
        <h1><i class="fas fa-warehouse"></i> Centro de Inventarios</h1>
    </div>
    
    <!-- KPIs Generales -->
    <div class="inv-kpis">
        <div class="kpi-card">
            <div class="kpi-icon items">
                <i class="fas fa-boxes"></i>
            </div>
            <div class="kpi-info">
                <div class="kpi-label">Total de Items</div>
                <div class="kpi-value" id="kpiTotalItems">--</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon valor">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="kpi-info">
                <div class="kpi-label">Valor Total Inventario</div>
                <div class="kpi-value success" id="kpiValorTotal">--</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon alertas">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="kpi-info">
                <div class="kpi-label">Alertas de Stock</div>
                <div class="kpi-value" id="kpiAlertas">--</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon tipos">
                <i class="fas fa-layer-group"></i>
            </div>
            <div class="kpi-info">
                <div class="kpi-label">Tipos de Inventario</div>
                <div class="kpi-value" id="kpiTipos">--</div>
            </div>
        </div>
    </div>
    
    <!-- Tipos de Inventario -->
    <div class="inv-tipos-section">
        <div class="section-title">
            <i class="fas fa-th-large"></i>
            <span>Seleccione un tipo de inventario para gestionar</span>
        </div>
        <div class="inv-tipos-grid" id="tiposGrid">
            <div class="loading-spinner">
                <i class="fas fa-spinner"></i>
                <span>Cargando tipos...</span>
            </div>
        </div>
    </div>
    
    <!-- Área de Trabajo (se muestra al seleccionar un tipo) -->
    <div class="inv-workspace" id="workspace">
        <div class="workspace-header">
            <div class="workspace-title">
                <div class="icon" id="workspaceIcon">
                    <i class="fas fa-box"></i>
                </div>
                <div>
                    <h2 id="workspaceTitulo">Tipo de Inventario</h2>
                    <div class="subtitle" id="workspaceSubtitulo">Seleccione una categoría</div>
                </div>
            </div>
            <div class="workspace-actions">
                <button class="btn btn-volver" onclick="volverATipos()">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
                <button class="btn btn-ingreso" onclick="abrirIngreso()">
                    <i class="fas fa-arrow-down"></i> Ingreso
                </button>
                <button class="btn btn-salida" onclick="abrirSalida()">
                    <i class="fas fa-arrow-up"></i> Salida
                </button>
                <button class="btn btn-historial" onclick="abrirHistorial()">
                    <i class="fas fa-history"></i> Historial
                </button>
                <button class="btn btn-nuevo" onclick="abrirNuevoItem()">
                    <i class="fas fa-plus"></i> Nuevo Item
                </button>
            </div>
        </div>
        
        <div class="workspace-body">
            <!-- Categorías del tipo seleccionado -->
            <div class="categorias-grid" id="categoriasGrid">
                <!-- Se llena dinámicamente -->
            </div>
            
            <!-- Tabla de productos (se muestra al seleccionar categoría) -->
            <div class="productos-section" id="productosSection">
                <div class="productos-header">
                    <h3>
                        <i class="fas fa-list"></i>
                        <span id="productosTitulo">Productos</span>
                        <span class="categoria-badge" id="productosCategoria">CAT</span>
                    </h3>
                    <div class="productos-search">
                        <input type="text" id="buscarProducto" placeholder="Buscar por código o nombre..." onkeyup="filtrarProductos()">
                        <span id="totalProductos">0 items</span>
                    </div>
                </div>
                <table class="productos-tabla">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th class="text-right">Stock</th>
                            <th>Unidad</th>
                            <th>Estado</th>
                            <th class="text-right">Costo Unit.</th>
                            <th class="text-right">Valor Total</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="productosBody">
                        <!-- Se llena dinámicamente -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ========== MODALES ========== -->

<!-- Modal Crear/Editar Item -->
<div class="modal-inventario" id="modalInventario">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitulo"><i class="fas fa-box"></i> Nuevo Item de Inventario</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formInventario">
                <input type="hidden" id="idInventario" name="id_inventario">
                <input type="hidden" id="tipoInventarioHidden" name="tipo_inventario_hidden">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Código <span class="required">*</span></label>
                        <input type="text" id="codigo" name="codigo" required maxlength="30">
                    </div>
                    <div class="form-group">
                        <label>Nombre <span class="required">*</span></label>
                        <input type="text" id="nombre" name="nombre" required maxlength="150">
                    </div>
                    <div class="form-group">
                        <label>Tipo de Inventario</label>
                        <input type="text" id="tipoInventarioDisplay" readonly class="readonly-input">
                    </div>
                    <div class="form-group">
                        <label>Categoría <span class="required">*</span></label>
                        <select id="idCategoria" name="id_categoria" required>
                            <option value="">Seleccione...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Unidad de Medida <span class="required">*</span></label>
                        <select id="idUnidad" name="id_unidad" required>
                            <option value="">Seleccione...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ubicación</label>
                        <select id="idUbicacion" name="id_ubicacion">
                            <option value="">Sin ubicación específica</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Stock Mínimo</label>
                        <input type="number" id="stockMinimo" name="stock_minimo" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label>Stock Actual</label>
                        <input type="number" id="stockActual" name="stock_actual" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label>Costo Unitario (Bs.)</label>
                        <input type="number" id="costoUnitario" name="costo_unitario" step="0.0001" value="0">
                    </div>
                    <div class="form-group">
                        <label>Proveedor Principal</label>
                        <input type="text" id="proveedorPrincipal" name="proveedor_principal" maxlength="100">
                    </div>
                    <div class="form-group full-width">
                        <label>Descripción</label>
                        <textarea id="descripcion" name="descripcion" rows="2"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="guardarInventario()">
                <i class="fas fa-save"></i> Guardar
            </button>
        </div>
    </div>
</div>

<!-- Modal Kardex -->
<div class="modal-inventario" id="modalKardex">
    <div class="modal-content" style="max-width: 1100px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); color: white;">
            <h3><i class="fas fa-book"></i> <span id="kardexTitulo">Kardex del Producto</span></h3>
            <button class="modal-close" onclick="closeModalKardex()" style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
            <div id="kardexContent">
                <div class="loading-spinner">
                    <i class="fas fa-spinner"></i>
                    <span>Cargando kardex...</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModalKardex()">Cerrar</button>
        </div>
    </div>
</div>

<style>
/* Estilos adicionales para modales y formularios */
.modal-inventario {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-inventario.show {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 700px;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px 25px;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-close {
    background: rgba(255,255,255,0.1);
    border: none;
    color: white;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    font-size: 1.5rem;
    cursor: pointer;
    transition: background 0.2s;
}

.modal-close:hover {
    background: rgba(255,255,255,0.2);
}

.modal-body {
    padding: 25px;
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 25px;
    background: #f8f9fa;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid #e9ecef;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group.full-width {
    grid-column: span 2;
}

.form-group label {
    font-size: 0.85rem;
    font-weight: 500;
    color: #495057;
}

.form-group .required {
    color: #dc3545;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 10px 14px;
    border: 1px solid #ced4da;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.15);
}

.readonly-input {
    background: #e9ecef;
    cursor: not-allowed;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 0.9rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.btn-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

/* Kardex styles */
.kardex-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.kardex-table th {
    background: #343a40;
    color: white;
    padding: 10px 8px;
    text-align: left;
}

.kardex-table td {
    padding: 8px;
    border-bottom: 1px solid #e9ecef;
}

.kardex-table tr:nth-child(even) {
    background: #f8f9fa;
}

.kardex-entrada { color: #28a745; }
.kardex-salida { color: #dc3545; }
</style>


<script>
// ========== VARIABLES GLOBALES ==========
const baseUrl = window.location.origin + '/mes_hermen';

let tiposInventario = [];
let categoriasInventario = [];
let productosInventario = [];
let unidadesMedida = [];
let ubicaciones = [];

let tipoSeleccionado = null;
let categoriaSeleccionada = null;

// ========== INICIALIZACIÓN ==========
document.addEventListener('DOMContentLoaded', function() {
    cargarDashboard();
    cargarCatalogos();
});

// ========== CARGA DE DATOS ==========
async function cargarDashboard() {
    try {
        const response = await fetch(`${baseUrl}/api/inventarios.php?action=resumen`);
        const data = await response.json();
        
        if (data.success) {
            // KPIs generales
            document.getElementById('kpiTotalItems').textContent = data.totales.items;
            document.getElementById('kpiValorTotal').textContent = 'Bs. ' + parseFloat(data.totales.valor).toLocaleString('es-BO', {minimumFractionDigits: 2});
            document.getElementById('kpiAlertas').textContent = data.totales.alertas;
            document.getElementById('kpiAlertas').className = 'kpi-value ' + (data.totales.alertas > 0 ? 'danger' : 'success');
            document.getElementById('kpiTipos').textContent = data.resumen.length;
            
            // Guardar tipos y renderizar
            tiposInventario = data.resumen;
            renderTiposInventario(tiposInventario);
        }
    } catch (error) {
        console.error('Error cargando dashboard:', error);
    }
}

async function cargarCatalogos() {
    try {
        // Cargar unidades de medida
        const resUnidades = await fetch(`${baseUrl}/api/inventarios.php?action=unidades`);
        const dataUnidades = await resUnidades.json();
        if (dataUnidades.success) {
            unidadesMedida = dataUnidades.unidades;
        }
        
        // Cargar ubicaciones
        const resUbicaciones = await fetch(`${baseUrl}/api/inventarios.php?action=ubicaciones`);
        const dataUbicaciones = await resUbicaciones.json();
        if (dataUbicaciones.success) {
            ubicaciones = dataUbicaciones.ubicaciones;
        }
    } catch (error) {
        console.error('Error cargando catálogos:', error);
    }
}

// ========== RENDERIZADO DE TIPOS ==========
function renderTiposInventario(tipos) {
    const container = document.getElementById('tiposGrid');
    
    if (tipos.length === 0) {
        container.innerHTML = '<p class="empty">No hay tipos de inventario configurados</p>';
        return;
    }
    
    container.innerHTML = tipos.map(tipo => {
        const color = tipo.color || '#007bff';
        const tieneItems = parseInt(tipo.total_items) > 0;
        const alertas = parseInt(tipo.sin_stock) + parseInt(tipo.stock_critico);
        
        return `
            <div class="tipo-card fade-in" 
                 style="--tipo-color: ${color}"
                 onclick="seleccionarTipo(${tipo.id_tipo_inventario})"
                 id="tipoCard_${tipo.id_tipo_inventario}">
                <div class="tipo-card-header">
                    <div class="tipo-icon" style="background: ${color}">
                        <i class="fas ${tipo.icono || 'fa-box'}"></i>
                    </div>
                    <div class="tipo-nombre">${tipo.nombre}</div>
                </div>
                <div class="tipo-stats">
                    <div class="tipo-stat">
                        <div class="tipo-stat-value">${tipo.total_items}</div>
                        <div class="tipo-stat-label">Items</div>
                    </div>
                    <div class="tipo-stat">
                        <div class="tipo-stat-value ${alertas > 0 ? 'text-danger' : ''}">${alertas}</div>
                        <div class="tipo-stat-label">Alertas</div>
                    </div>
                </div>
                <div class="tipo-valor-total">
                    <div class="valor">Bs. ${parseFloat(tipo.valor_total).toLocaleString('es-BO', {minimumFractionDigits: 0, maximumFractionDigits: 0})}</div>
                    <div class="label">Valor Total</div>
                </div>
            </div>
        `;
    }).join('');
}

// ========== SELECCIÓN DE TIPO ==========
async function seleccionarTipo(idTipo) {
    // Marcar card como activo
    document.querySelectorAll('.tipo-card').forEach(card => card.classList.remove('active'));
    document.getElementById(`tipoCard_${idTipo}`).classList.add('active');
    
    // Guardar tipo seleccionado
    tipoSeleccionado = tiposInventario.find(t => t.id_tipo_inventario == idTipo);
    
    if (!tipoSeleccionado) return;
    
    // Actualizar header del workspace
    const color = tipoSeleccionado.color || '#007bff';
    document.getElementById('workspaceIcon').style.background = color;
    document.getElementById('workspaceIcon').innerHTML = `<i class="fas ${tipoSeleccionado.icono || 'fa-box'}"></i>`;
    document.getElementById('workspaceTitulo').textContent = tipoSeleccionado.nombre;
    document.getElementById('workspaceSubtitulo').textContent = `${tipoSeleccionado.total_items} items | Bs. ${parseFloat(tipoSeleccionado.valor_total).toLocaleString('es-BO', {minimumFractionDigits: 2})}`;
    
    // Mostrar workspace
    document.getElementById('workspace').classList.add('active');
    document.getElementById('workspace').style.setProperty('--tipo-color', color);
    
    // Ocultar sección de productos
    document.getElementById('productosSection').classList.remove('active');
    categoriaSeleccionada = null;
    
    // Cargar categorías del tipo
    await cargarCategoriasDelTipo(idTipo);
    
    // Scroll al workspace
    document.getElementById('workspace').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function cargarCategoriasDelTipo(idTipo) {
    const container = document.getElementById('categoriasGrid');
    container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner"></i><span>Cargando categorías...</span></div>';
    
    try {
        // Obtener categorías con sus valores
        const response = await fetch(`${baseUrl}/api/inventarios.php?action=categorias_resumen&tipo_id=${idTipo}`);
        const data = await response.json();
        
        if (data.success && data.categorias) {
            categoriasInventario = data.categorias;
            renderCategorias(data.categorias);
        } else {
            // Si no hay endpoint especial, cargar categorías básicas
            const resCat = await fetch(`${baseUrl}/api/inventarios.php?action=categorias&tipo_id=${idTipo}`);
            const dataCat = await resCat.json();
            
            if (dataCat.success) {
                // Cargar productos para calcular totales por categoría
                const resProd = await fetch(`${baseUrl}/api/inventarios.php?action=list&tipo_id=${idTipo}`);
                const dataProd = await resProd.json();
                
                if (dataProd.success) {
                    productosInventario = dataProd.inventarios;
                    
                    // Calcular totales por categoría
                    const categoriasConTotales = dataCat.categorias.map(cat => {
                        const productosCat = productosInventario.filter(p => p.id_categoria == cat.id_categoria);
                        return {
                            ...cat,
                            total_items: productosCat.length,
                            valor_total: productosCat.reduce((sum, p) => sum + parseFloat(p.valor_total || 0), 0),
                            alertas: productosCat.filter(p => {
                                const stock = parseFloat(p.stock_actual);
                                const minimo = parseFloat(p.stock_minimo);
                                return stock <= 0 || stock <= minimo;
                            }).length
                        };
                    });
                    
                    categoriasInventario = categoriasConTotales;
                    renderCategorias(categoriasConTotales);
                }
            }
        }
    } catch (error) {
        console.error('Error cargando categorías:', error);
        container.innerHTML = '<p class="error">Error al cargar categorías</p>';
    }
}

function renderCategorias(categorias) {
    const container = document.getElementById('categoriasGrid');
    const color = tipoSeleccionado?.color || '#007bff';
    
    if (categorias.length === 0) {
        container.innerHTML = `
            <div class="workspace-placeholder">
                <i class="fas fa-folder-open"></i>
                <h3>Sin categorías</h3>
                <p>Este tipo de inventario no tiene categorías configuradas</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = categorias.map(cat => `
        <div class="categoria-card fade-in" 
             style="--tipo-color: ${color}"
             onclick="seleccionarCategoria(${cat.id_categoria})"
             id="catCard_${cat.id_categoria}">
            <div class="categoria-header">
                <div class="categoria-info">
                    <h4>${cat.nombre}</h4>
                    <div class="codigo">${cat.codigo}</div>
                </div>
                <span class="categoria-badge" style="background: ${color}">${cat.total_items || 0}</span>
            </div>
            <div class="categoria-stats">
                <div class="categoria-stat">
                    <div class="value">${cat.total_items || 0}</div>
                    <div class="label">Items</div>
                </div>
                <div class="categoria-stat">
                    <div class="value ${(cat.alertas || 0) > 0 ? 'text-danger' : ''}">${cat.alertas || 0}</div>
                    <div class="label">Alertas</div>
                </div>
                <div class="categoria-stat">
                    <div class="value money">Bs. ${parseFloat(cat.valor_total || 0).toLocaleString('es-BO', {minimumFractionDigits: 0, maximumFractionDigits: 0})}</div>
                    <div class="label">Valor</div>
                </div>
            </div>
        </div>
    `).join('');
}

// ========== SELECCIÓN DE CATEGORÍA ==========
async function seleccionarCategoria(idCategoria) {
    // Marcar card como activo
    document.querySelectorAll('.categoria-card').forEach(card => card.classList.remove('active'));
    document.getElementById(`catCard_${idCategoria}`).classList.add('active');
    
    // Guardar categoría seleccionada
    categoriaSeleccionada = categoriasInventario.find(c => c.id_categoria == idCategoria);
    
    if (!categoriaSeleccionada) return;
    
    // Actualizar título de productos
    document.getElementById('productosTitulo').textContent = categoriaSeleccionada.nombre;
    document.getElementById('productosCategoria').textContent = categoriaSeleccionada.codigo;
    document.getElementById('productosCategoria').style.background = tipoSeleccionado?.color || '#007bff';
    
    // Mostrar sección de productos con loading
    document.getElementById('productosSection').classList.add('active');
    document.getElementById('productosBody').innerHTML = '<tr><td colspan="8" class="text-center" style="padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Cargando productos...</td></tr>';
    
    // Cargar productos de la categoría desde la API
    try {
        const response = await fetch(`${baseUrl}/api/inventarios.php?action=list&tipo_id=${tipoSeleccionado.id_tipo_inventario}&categoria_id=${idCategoria}`);
        const data = await response.json();
        
        if (data.success) {
            const productosCategoria = data.inventarios || [];
            renderProductos(productosCategoria);
        } else {
            document.getElementById('productosBody').innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error al cargar productos</td></tr>';
        }
    } catch (error) {
        console.error('Error:', error);
        document.getElementById('productosBody').innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error de conexión</td></tr>';
    }
    
    // Scroll a la tabla
    document.getElementById('productosSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function renderProductos(productos) {
    const tbody = document.getElementById('productosBody');
    document.getElementById('totalProductos').textContent = `${productos.length} items`;
    document.getElementById('buscarProducto').value = '';
    
    if (productos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center" style="padding: 40px; color: #6c757d;">No hay productos en esta categoría</td></tr>';
        return;
    }
    
    tbody.innerHTML = productos.map(prod => {
        const stock = parseFloat(prod.stock_actual);
        const minimo = parseFloat(prod.stock_minimo);
        let estadoClass = 'ok';
        let estadoText = 'OK';
        
        if (stock <= 0) {
            estadoClass = 'sin-stock';
            estadoText = 'Sin Stock';
        } else if (stock <= minimo) {
            estadoClass = 'critico';
            estadoText = 'Crítico';
        } else if (stock <= minimo * 1.5) {
            estadoClass = 'bajo';
            estadoText = 'Bajo';
        }
        
        const valor = parseFloat(prod.valor_total || 0);
        const costo = parseFloat(prod.costo_unitario || 0);
        
        return `
            <tr>
                <td class="codigo">${prod.codigo}</td>
                <td class="nombre">${prod.nombre}</td>
                <td class="text-right stock ${estadoClass}">${stock.toFixed(2)}</td>
                <td>${prod.unidad || '-'}</td>
                <td><span class="estado-badge ${estadoClass}">${estadoText}</span></td>
                <td class="text-right">Bs. ${costo.toFixed(4)}</td>
                <td class="text-right valor">Bs. ${valor.toLocaleString('es-BO', {minimumFractionDigits: 2})}</td>
                <td class="acciones">
                    <button class="btn-accion kardex" onclick="verKardex(${prod.id_inventario})" title="Ver Kardex">
                        <i class="fas fa-book"></i>
                    </button>
                    <button class="btn-accion editar" onclick="editarItem(${prod.id_inventario})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function filtrarProductos() {
    const buscar = document.getElementById('buscarProducto').value.toLowerCase();
    
    if (!categoriaSeleccionada) return;
    
    let productosFiltrados = productosInventario.filter(p => p.id_categoria == categoriaSeleccionada.id_categoria);
    
    if (buscar) {
        productosFiltrados = productosFiltrados.filter(p => 
            p.codigo.toLowerCase().includes(buscar) || 
            p.nombre.toLowerCase().includes(buscar)
        );
    }
    
    renderProductos(productosFiltrados);
}

// ========== NAVEGACIÓN ==========
function volverATipos() {
    document.getElementById('workspace').classList.remove('active');
    document.querySelectorAll('.tipo-card').forEach(card => card.classList.remove('active'));
    tipoSeleccionado = null;
    categoriaSeleccionada = null;
}

// ========== ACCIONES CONTEXTUALES ==========
function abrirIngreso() {
    if (!tipoSeleccionado) {
        alert('Seleccione un tipo de inventario primero');
        return;
    }
    // Abrir modal de ingreso filtrado por tipo
    openModalMultiContextual('ENTRADA');
}

function abrirSalida() {
    if (!tipoSeleccionado) {
        alert('Seleccione un tipo de inventario primero');
        return;
    }
    // Abrir modal de salida filtrado por tipo
    openModalSalidaContextual();
}

function abrirHistorial() {
    if (!tipoSeleccionado) {
        alert('Seleccione un tipo de inventario primero');
        return;
    }
    // Abrir historial filtrado por tipo
    openModalHistoricoContextual();
}

function abrirNuevoItem() {
    if (!tipoSeleccionado) {
        alert('Seleccione un tipo de inventario primero');
        return;
    }
    openModalNuevoItem();
}

// ========== MODAL NUEVO ITEM ==========
async function openModalNuevoItem() {
    const modal = document.getElementById('modalInventario');
    
    // Limpiar formulario
    document.getElementById('formInventario').reset();
    document.getElementById('idInventario').value = '';
    document.getElementById('tipoInventarioHidden').value = tipoSeleccionado.id_tipo_inventario;
    document.getElementById('tipoInventarioDisplay').value = tipoSeleccionado.nombre;
    document.getElementById('modalTitulo').innerHTML = `<i class="fas fa-plus"></i> Nuevo Item - ${tipoSeleccionado.nombre}`;
    
    // Cargar categorías del tipo seleccionado
    await cargarSelectCategorias();
    cargarSelectUnidades();
    cargarSelectUbicaciones();
    
    modal.classList.add('show');
}

async function cargarSelectCategorias() {
    const select = document.getElementById('idCategoria');
    select.innerHTML = '<option value="">Cargando...</option>';
    
    try {
        const response = await fetch(`${baseUrl}/api/inventarios.php?action=categorias&tipo_id=${tipoSeleccionado.id_tipo_inventario}`);
        const data = await response.json();
        
        if (data.success) {
            select.innerHTML = '<option value="">Seleccione...</option>' +
                data.categorias.map(cat => `<option value="${cat.id_categoria}">${cat.codigo} - ${cat.nombre}</option>`).join('');
        }
    } catch (error) {
        console.error('Error:', error);
        select.innerHTML = '<option value="">Error al cargar</option>';
    }
}

function cargarSelectUnidades() {
    const select = document.getElementById('idUnidad');
    select.innerHTML = '<option value="">Seleccione...</option>' +
        unidadesMedida.map(u => `<option value="${u.id_unidad}">${u.abreviatura} - ${u.nombre}</option>`).join('');
}

function cargarSelectUbicaciones() {
    const select = document.getElementById('idUbicacion');
    select.innerHTML = '<option value="">Sin ubicación específica</option>' +
        ubicaciones.map(u => `<option value="${u.id_ubicacion}">${u.codigo} - ${u.nombre}</option>`).join('');
}

function closeModal() {
    document.getElementById('modalInventario').classList.remove('show');
}

async function guardarInventario() {
    const form = document.getElementById('formInventario');
    
    const payload = {
        id_inventario: document.getElementById('idInventario').value || null,
        codigo: document.getElementById('codigo').value.trim(),
        nombre: document.getElementById('nombre').value.trim(),
        descripcion: document.getElementById('descripcion').value.trim(),
        id_tipo_inventario: document.getElementById('tipoInventarioHidden').value,
        id_categoria: document.getElementById('idCategoria').value,
        id_unidad: document.getElementById('idUnidad').value,
        stock_actual: parseFloat(document.getElementById('stockActual').value) || 0,
        stock_minimo: parseFloat(document.getElementById('stockMinimo').value) || 0,
        costo_unitario: parseFloat(document.getElementById('costoUnitario').value) || 0,
        id_ubicacion: document.getElementById('idUbicacion').value || null,
        proveedor_principal: document.getElementById('proveedorPrincipal').value.trim()
    };
    
    if (!payload.codigo || !payload.nombre || !payload.id_categoria || !payload.id_unidad) {
        alert('⚠️ Complete los campos requeridos');
        return;
    }
    
    try {
        const response = await fetch(`${baseUrl}/api/inventarios.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ ' + data.message);
            closeModal();
            cargarDashboard();
            if (tipoSeleccionado) {
                seleccionarTipo(tipoSeleccionado.id_tipo_inventario);
            }
        } else {
            alert('❌ ' + (data.message || 'Error al guardar'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('❌ Error de conexión');
    }
}

async function editarItem(idInventario) {
    try {
        const response = await fetch(`${baseUrl}/api/inventarios.php?action=detalle&id=${idInventario}`);
        const data = await response.json();
        
        if (data.success && data.item) {
            const item = data.item;
            
            document.getElementById('idInventario').value = item.id_inventario;
            document.getElementById('codigo').value = item.codigo;
            document.getElementById('nombre').value = item.nombre;
            document.getElementById('descripcion').value = item.descripcion || '';
            document.getElementById('tipoInventarioHidden').value = item.id_tipo_inventario;
            document.getElementById('tipoInventarioDisplay').value = item.tipo_nombre;
            document.getElementById('stockActual').value = item.stock_actual;
            document.getElementById('stockMinimo').value = item.stock_minimo;
            document.getElementById('costoUnitario').value = item.costo_unitario;
            document.getElementById('proveedorPrincipal').value = item.proveedor_principal || '';
            
            await cargarSelectCategorias();
            cargarSelectUnidades();
            cargarSelectUbicaciones();
            
            // Esperar un momento para que se carguen los selects
            setTimeout(() => {
                document.getElementById('idCategoria').value = item.id_categoria;
                document.getElementById('idUnidad').value = item.id_unidad;
                document.getElementById('idUbicacion').value = item.id_ubicacion || '';
            }, 300);
            
            document.getElementById('modalTitulo').innerHTML = `<i class="fas fa-edit"></i> Editar: ${item.nombre}`;
            document.getElementById('modalInventario').classList.add('show');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('❌ Error al cargar datos del item');
    }
}

// ========== KARDEX ==========
async function verKardex(idInventario) {
    const modal = document.getElementById('modalKardex');
    const content = document.getElementById('kardexContent');
    
    content.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner"></i><span>Cargando kardex...</span></div>';
    modal.classList.add('show');
    
    try {
        const response = await fetch(`${baseUrl}/api/inventarios.php?action=kardex&id=${idInventario}`);
        const data = await response.json();
        
        if (data.success) {
            const producto = productosInventario.find(p => p.id_inventario == idInventario);
            document.getElementById('kardexTitulo').textContent = `Kardex: ${producto?.nombre || 'Producto'}`;
            
            if (data.movimientos.length === 0) {
                content.innerHTML = '<p class="text-center" style="padding: 40px; color: #6c757d;">No hay movimientos registrados</p>';
                return;
            }
            
            content.innerHTML = `
                <table class="kardex-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Documento</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">Costo Unit.</th>
                            <th class="text-right">Stock</th>
                            <th class="text-right">CPP</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.movimientos.map(mov => {
                            const esEntrada = mov.tipo_movimiento.includes('ENTRADA');
                            const fecha = new Date(mov.fecha_movimiento);
                            return `
                                <tr>
                                    <td>${fecha.toLocaleDateString('es-BO')}</td>
                                    <td class="${esEntrada ? 'kardex-entrada' : 'kardex-salida'}">
                                        ${esEntrada ? '↓' : '↑'} ${mov.tipo_movimiento.replace('ENTRADA_', '').replace('SALIDA_', '')}
                                    </td>
                                    <td>${mov.documento_numero || '-'}</td>
                                    <td class="text-right ${esEntrada ? 'kardex-entrada' : 'kardex-salida'}">
                                        ${esEntrada ? '+' : '-'}${parseFloat(mov.cantidad).toFixed(2)}
                                    </td>
                                    <td class="text-right">Bs. ${parseFloat(mov.costo_unitario).toFixed(4)}</td>
                                    <td class="text-right">${parseFloat(mov.stock_nuevo).toFixed(2)}</td>
                                    <td class="text-right">Bs. ${parseFloat(mov.costo_promedio_resultado).toFixed(4)}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
        }
    } catch (error) {
        console.error('Error:', error);
        content.innerHTML = '<p class="text-center text-danger">Error al cargar kardex</p>';
    }
}

function closeModalKardex() {
    document.getElementById('modalKardex').classList.remove('show');
}

// ========== FUNCIONES PLACEHOLDER PARA MODALES CONTEXTUALES ==========
// Estas funciones conectarán con los modales existentes pero filtrados por tipo

function openModalMultiContextual(tipo) {
    // Por ahora mostrar alerta - esto se conectará con el modal existente
    alert(`Abrir modal de ${tipo} para: ${tipoSeleccionado.nombre}\n\nEsta funcionalidad se conectará con el modal de ingreso múltiple existente, filtrado por el tipo de inventario seleccionado.`);
}

function openModalSalidaContextual() {
    alert(`Abrir modal de SALIDA para: ${tipoSeleccionado.nombre}\n\nEsta funcionalidad se conectará con el modal de salida existente, filtrado por el tipo de inventario seleccionado.`);
}

function openModalHistoricoContextual() {
    alert(`Abrir HISTORIAL de: ${tipoSeleccionado.nombre}\n\nEsta funcionalidad mostrará solo los movimientos del tipo de inventario seleccionado.`);
}

</script>

<?php require_once '../../includes/footer.php'; ?>