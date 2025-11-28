<?php
/**
 * Módulo de Inventarios Centralizado
 * Sistema ERP Hermen Ltda.
 * Versión: 1.0
 */
require_once '../../config/database.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$pageTitle = 'Inventarios';
$currentPage = 'inventarios';

require_once '../../includes/header.php';
?>

<!-- Estilos específicos del módulo -->
<style>
/* ========== VARIABLES ========== */
:root {
    --inv-mp: #007bff;
    --inv-caq: #6f42c1;
    --inv-emp: #fd7e14;
    --inv-acc: #e83e8c;
    --inv-wip: #17a2b8;
    --inv-pt: #28a745;
    --inv-rep: #6c757d;
}

/* ========== DASHBOARD CARDS ========== */
.inv-dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.inv-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    cursor: pointer;
    transition: all 0.3s ease;
    border-left: 4px solid var(--card-color, #6c757d);
    position: relative;
    overflow: hidden;
}

.inv-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.inv-card.active {
    background: linear-gradient(135deg, var(--card-color) 0%, color-mix(in srgb, var(--card-color) 80%, black) 100%);
    color: white;
}

.inv-card.active .inv-card-title,
.inv-card.active .inv-card-value,
.inv-card.active .inv-card-subtitle {
    color: white;
}

.inv-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    margin-bottom: 12px;
    background: color-mix(in srgb, var(--card-color) 15%, white);
    color: var(--card-color);
}

.inv-card.active .inv-card-icon {
    background: rgba(255,255,255,0.2);
    color: white;
}

.inv-card-title {
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 4px;
    font-weight: 500;
}

.inv-card-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: 8px;
}

.inv-card-subtitle {
    font-size: 0.75rem;
    color: #888;
}

.inv-card-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

.badge-critico {
    background: #dc3545;
    color: white;
}

.badge-ok {
    background: #28a745;
    color: white;
}

/* ========== RESUMEN TOTAL ========== */
.inv-resumen-total {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 24px;
    color: white;
}

.inv-resumen-item {
    text-align: center;
}

.inv-resumen-item .label {
    font-size: 0.8rem;
    opacity: 0.7;
    margin-bottom: 4px;
}

.inv-resumen-item .value {
    font-size: 1.6rem;
    font-weight: 700;
}

.inv-resumen-item .value.warning {
    color: #ffc107;
}

.inv-resumen-item .value.success {
    color: #28a745;
}

/* ========== FILTROS ========== */
.inv-filtros {
    background: white;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.inv-filtros .filtro-grupo {
    display: flex;
    align-items: center;
    gap: 8px;
}

.inv-filtros label {
    font-size: 0.85rem;
    color: #666;
    white-space: nowrap;
}

.inv-filtros select,
.inv-filtros input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.9rem;
    min-width: 160px;
}

.inv-filtros select:focus,
.inv-filtros input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
}

.inv-filtros .btn-limpiar {
    padding: 8px 16px;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.2s;
}

.inv-filtros .btn-limpiar:hover {
    background: #e9ecef;
}

.inv-filtros .search-box {
    flex: 1;
    min-width: 200px;
    position: relative;
}

.inv-filtros .search-box input {
    width: 100%;
    padding-left: 36px;
}

.inv-filtros .search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #aaa;
}

/* ========== TABLA ========== */
.inv-tabla-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    overflow: hidden;
}

.inv-tabla-header {
    padding: 16px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.inv-tabla-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: #1a1a2e;
}

.inv-tabla-header .total-registros {
    font-size: 0.85rem;
    color: #666;
    background: #f0f0f0;
    padding: 4px 12px;
    border-radius: 20px;
}

.inv-tabla {
    width: 100%;
    border-collapse: collapse;
}

.inv-tabla th {
    background: #f8f9fa;
    padding: 12px 16px;
    text-align: left;
    font-size: 0.8rem;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #eee;
}

.inv-tabla td {
    padding: 14px 16px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.9rem;
    vertical-align: middle;
}

.inv-tabla tr:hover {
    background: #f8f9fa;
}

.inv-tabla .codigo {
    font-family: 'Consolas', monospace;
    font-size: 0.85rem;
    color: #555;
}

.inv-tabla .nombre {
    font-weight: 500;
    color: #1a1a2e;
}

.inv-tabla .descripcion {
    font-size: 0.8rem;
    color: #888;
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Badges de tipo */
.tipo-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

.tipo-badge i {
    font-size: 0.7rem;
}

/* Badges de estado de stock */
.stock-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.stock-badge.sin-stock {
    background: #f8d7da;
    color: #721c24;
}

.stock-badge.critico {
    background: #fff3cd;
    color: #856404;
}

.stock-badge.bajo {
    background: #d4edda;
    color: #155724;
}

.stock-badge.ok {
    background: #d1ecf1;
    color: #0c5460;
}

/* Valores numéricos */
.inv-tabla .valor-numerico {
    text-align: right;
    font-family: 'Consolas', monospace;
}

.inv-tabla .stock-actual {
    font-weight: 600;
    font-size: 1rem;
}

.inv-tabla .costo {
    color: #28a745;
}

/* Acciones */
.inv-tabla .acciones {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.inv-tabla .btn-accion {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.btn-accion.ver {
    background: #e3f2fd;
    color: #1976d2;
}

.btn-accion.editar {
    background: #fff3e0;
    color: #f57c00;
}

.btn-accion.movimiento {
    background: #e8f5e9;
    color: #388e3c;
}

.btn-accion.eliminar {
    background: #ffebee;
    color: #d32f2f;
}

.btn-accion:hover {
    transform: scale(1.1);
}

/* ========== MODAL ========== */
.modal-inventario {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-inventario.show {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    background: white;
    z-index: 10;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.2rem;
    color: #1a1a2e;
}

.modal-close {
    width: 36px;
    height: 36px;
    border: none;
    background: #f0f0f0;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.modal-close:hover {
    background: #e0e0e0;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    position: sticky;
    bottom: 0;
    background: white;
}

/* Formulario en grid */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.form-grid .full-width {
    grid-column: 1 / -1;
}

.form-group {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 500;
    color: #555;
    margin-bottom: 6px;
}

.form-group label .required {
    color: #dc3545;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

/* Botones */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 500;
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

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,123,255,0.3);
}

.btn-secondary {
    background: #f0f0f0;
    color: #333;
}

.btn-secondary:hover {
    background: #e0e0e0;
}

.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    color: white;
}

.btn-nuevo {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    color: white;
    padding: 10px 20px;
}

/* ========== MODAL MOVIMIENTO ========== */
.mov-tipo-btns {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
}

.mov-tipo-btn {
    flex: 1;
    padding: 16px;
    border: 2px solid #ddd;
    border-radius: 12px;
    background: white;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
}

.mov-tipo-btn:hover {
    border-color: #007bff;
}

.mov-tipo-btn.active.entrada {
    border-color: #28a745;
    background: #e8f5e9;
}

.mov-tipo-btn.active.salida {
    border-color: #dc3545;
    background: #ffebee;
}

.mov-tipo-btn i {
    font-size: 1.5rem;
    display: block;
    margin-bottom: 8px;
}

.mov-tipo-btn.entrada i {
    color: #28a745;
}

.mov-tipo-btn.salida i {
    color: #dc3545;
}

.mov-tipo-btn span {
    font-weight: 600;
    font-size: 0.9rem;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .inv-dashboard {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .inv-filtros {
        flex-direction: column;
        align-items: stretch;
    }
    
    .inv-filtros .filtro-grupo {
        width: 100%;
    }
    
    .inv-filtros select,
    .inv-filtros input {
        width: 100%;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .inv-tabla {
        font-size: 0.8rem;
    }
    
    .inv-tabla th,
    .inv-tabla td {
        padding: 10px 8px;
    }
}

/* ========== ANIMACIONES ========== */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.modal-inventario.show .modal-content {
    animation: fadeIn 0.3s ease;
}

/* ========== LOADING ========== */
.loading {
    text-align: center;
    padding: 40px;
    color: #888;
}

.loading i {
    font-size: 2rem;
    margin-bottom: 12px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* ========== EMPTY STATE ========== */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #888;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 16px;
    opacity: 0.3;
}

.empty-state h4 {
    margin-bottom: 8px;
    color: #555;
}
</style>

<!-- Contenido Principal -->
<div class="content-wrapper">
    <div class="page-header">
        <h2><i class="fas fa-warehouse"></i> Inventarios</h2>
        <button class="btn btn-nuevo" onclick="openModal()">
            <i class="fas fa-plus"></i> Nuevo Item
        </button>
    </div>
    
    <!-- Resumen Total -->
    <div class="inv-resumen-total">
        <div class="inv-resumen-item">
            <div class="label">Total Items</div>
            <div class="value" id="totalItems">--</div>
        </div>
        <div class="inv-resumen-item">
            <div class="label">Valor Total Inventario</div>
            <div class="value success" id="totalValor">--</div>
        </div>
        <div class="inv-resumen-item">
            <div class="label">Alertas de Stock</div>
            <div class="value warning" id="totalAlertas">--</div>
        </div>
    </div>
    
    <!-- Dashboard Cards -->
    <div class="inv-dashboard" id="dashboardCards">
        <div class="loading">
            <i class="fas fa-spinner"></i>
            <p>Cargando...</p>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="inv-filtros">
        <div class="filtro-grupo">
            <label>Tipo:</label>
            <select id="filtroTipo" onchange="filtrarInventario()">
                <option value="">Todos los tipos</option>
            </select>
        </div>
        <div class="filtro-grupo">
            <label>Categoría:</label>
            <select id="filtroCategoria" onchange="filtrarInventario()">
                <option value="">Todas las categorías</option>
            </select>
        </div>
        <div class="filtro-grupo">
            <label>Estado:</label>
            <select id="filtroEstado" onchange="filtrarInventario()">
                <option value="">Todos</option>
                <option value="SIN_STOCK">Sin Stock</option>
                <option value="CRITICO">Crítico</option>
                <option value="BAJO">Bajo</option>
                <option value="OK">OK</option>
            </select>
        </div>
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="buscarInput" placeholder="Buscar por código o nombre..." onkeyup="buscarInventario()">
        </div>
        <button class="btn-limpiar" onclick="limpiarFiltros()">
            <i class="fas fa-times"></i> Limpiar
        </button>
    </div>
    
    <!-- Tabla de Inventarios -->
    <div class="inv-tabla-container">
        <div class="inv-tabla-header">
            <h3><i class="fas fa-list"></i> Listado de Inventario</h3>
            <span class="total-registros" id="totalRegistros">0 registros</span>
        </div>
        <table class="inv-tabla">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Tipo / Categoría</th>
                    <th class="text-right">Stock</th>
                    <th>Estado</th>
                    <th class="text-right">Costo Unit.</th>
                    <th class="text-right">Valor Total</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaBody">
                <tr>
                    <td colspan="8" class="loading">
                        <i class="fas fa-spinner"></i>
                        <p>Cargando inventario...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Crear/Editar -->
<div class="modal-inventario" id="modalInventario">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitulo"><i class="fas fa-box"></i> Nuevo Item de Inventario</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formInventario">
                <input type="hidden" id="idInventario" name="id_inventario">
                
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
                        <label>Tipo de Inventario <span class="required">*</span></label>
                        <select id="idTipoInventario" name="id_tipo_inventario" required onchange="cargarCategoriasPorTipo()">
                            <option value="">Seleccione...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Categoría <span class="required">*</span></label>
                        <select id="idCategoria" name="id_categoria" required>
                            <option value="">Seleccione tipo primero...</option>
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
                            <option value="">Sin asignar</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Stock Actual</label>
                        <input type="number" id="stockActual" name="stock_actual" step="0.01" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>Stock Mínimo</label>
                        <input type="number" id="stockMinimo" name="stock_minimo" step="0.01" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>Costo Unitario (Bs.)</label>
                        <input type="number" id="costoUnitario" name="costo_unitario" step="0.0001" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>Línea de Producción</label>
                        <select id="idLineaProduccion" name="id_linea_produccion">
                            <option value="">Todas las líneas</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
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

<!-- Modal Movimiento -->
<div class="modal-inventario" id="modalMovimiento">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="modalMovTitulo"><i class="fas fa-exchange-alt"></i> Registrar Movimiento</h3>
            <button class="modal-close" onclick="closeModalMovimiento()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="movIdInventario">
            
            <div style="background: #f8f9fa; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                <strong id="movProductoNombre">Producto</strong>
                <div style="font-size: 0.85rem; color: #666;">
                    Stock actual: <strong id="movStockActual">0</strong> <span id="movUnidad">kg</span>
                </div>
            </div>
            
            <div class="mov-tipo-btns">
                <button type="button" class="mov-tipo-btn entrada" onclick="selectTipoMov('entrada')">
                    <i class="fas fa-arrow-down"></i>
                    <span>Entrada</span>
                </button>
                <button type="button" class="mov-tipo-btn salida" onclick="selectTipoMov('salida')">
                    <i class="fas fa-arrow-up"></i>
                    <span>Salida</span>
                </button>
            </div>
            
            <div class="form-group" style="margin-bottom: 16px;">
                <label>Tipo de Movimiento <span class="required">*</span></label>
                <select id="movTipoMovimiento" required>
                    <option value="">Seleccione tipo de movimiento...</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 16px;">
                <label>Cantidad <span class="required">*</span></label>
                <input type="number" id="movCantidad" step="0.01" min="0.01" required>
            </div>
            
            <div class="form-group" style="margin-bottom: 16px;">
                <label>Costo Unitario (Bs.)</label>
                <input type="number" id="movCostoUnitario" step="0.0001" min="0">
                <small style="color: #888; font-size: 0.75rem;">Dejar vacío para usar costo actual</small>
            </div>
            
            <div class="form-group">
                <label>Observaciones</label>
                <textarea id="movObservaciones" rows="2"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModalMovimiento()">Cancelar</button>
            <button type="button" class="btn btn-success" onclick="guardarMovimiento()">
                <i class="fas fa-check"></i> Registrar
            </button>
        </div>
    </div>
</div>

<script>
const baseUrl = window.location.origin + '/mes_hermen';

// Datos globales
let inventarios = [];
let tipos = [];
let categorias = [];
let unidades = [];
let ubicaciones = [];
let lineas = [];
let tipoFiltroActivo = null;

// ========== INICIALIZACIÓN ==========
document.addEventListener('DOMContentLoaded', function() {
    cargarDashboard();
    cargarCatalogos();
    cargarInventario();
});

// ========== DASHBOARD ==========
async function cargarDashboard() {
    try {
        const response = await fetch(`${baseUrl}/api/inventarios.php?action=resumen`);
        const data = await response.json();
        
        if (data.success) {
            renderDashboard(data.resumen);
            
            // Actualizar totales
            document.getElementById('totalItems').textContent = data.totales.items.toLocaleString();
            document.getElementById('totalValor').textContent = 'Bs. ' + parseFloat(data.totales.valor).toLocaleString('es-BO', {minimumFractionDigits: 2});
            document.getElementById('totalAlertas').textContent = data.totales.alertas;
        }
    } catch (error) {
        console.error('Error cargando dashboard:', error);
    }
}

function renderDashboard(resumen) {
    const container = document.getElementById('dashboardCards');
    
    if (!resumen || resumen.length === 0) {
        container.innerHTML = '<div class="empty-state"><i class="fas fa-box-open"></i><h4>Sin datos</h4></div>';
        return;
    }
    
    container.innerHTML = resumen.map(tipo => {
        const alertas = parseInt(tipo.sin_stock) + parseInt(tipo.stock_critico);
        return `
            <div class="inv-card ${tipoFiltroActivo == tipo.id_tipo_inventario ? 'active' : ''}" 
                 style="--card-color: ${tipo.color}"
                 onclick="filtrarPorTipo(${tipo.id_tipo_inventario})">
                ${alertas > 0 ? `<span class="inv-card-badge badge-critico">${alertas} alertas</span>` : ''}
                <div class="inv-card-icon">
                    <i class="fas ${tipo.icono}"></i>
                </div>
                <div class="inv-card-title">${tipo.nombre}</div>
                <div class="inv-card-value">${tipo.total_items}</div>
                <div class="inv-card-subtitle">
                    Valor: Bs. ${parseFloat(tipo.valor_total).toLocaleString('es-BO', {minimumFractionDigits: 2})}
                </div>
            </div>
        `;
    }).join('');
}

// ========== CATÁLOGOS ==========
async function cargarCatalogos() {
    try {
        // Cargar tipos
        const resTipos = await fetch(`${baseUrl}/api/inventarios.php?action=tipos`);
        const dataTipos = await resTipos.json();
        if (dataTipos.success) {
            tipos = dataTipos.tipos;
            poblarSelectTipos();
        }
        
        // Cargar categorías
        const resCat = await fetch(`${baseUrl}/api/inventarios.php?action=categorias`);
        const dataCat = await resCat.json();
        if (dataCat.success) {
            categorias = dataCat.categorias;
            poblarSelectCategorias();
        }
        
        // Cargar unidades
        const resUni = await fetch(`${baseUrl}/api/inventarios.php?action=unidades`);
        const dataUni = await resUni.json();
        if (dataUni.success) {
            unidades = dataUni.unidades;
            poblarSelectUnidades();
        }
        
        // Cargar ubicaciones
        const resUbi = await fetch(`${baseUrl}/api/inventarios.php?action=ubicaciones`);
        const dataUbi = await resUbi.json();
        if (dataUbi.success) {
            ubicaciones = dataUbi.ubicaciones;
            poblarSelectUbicaciones();
        }
        
        // Cargar líneas
        const resLin = await fetch(`${baseUrl}/api/inventarios.php?action=lineas`);
        const dataLin = await resLin.json();
        if (dataLin.success) {
            lineas = dataLin.lineas;
            poblarSelectLineas();
        }
        
    } catch (error) {
        console.error('Error cargando catálogos:', error);
    }
}

function poblarSelectTipos() {
    // Filtro
    const filtro = document.getElementById('filtroTipo');
    filtro.innerHTML = '<option value="">Todos los tipos</option>' + 
        tipos.map(t => `<option value="${t.id_tipo_inventario}">${t.nombre}</option>`).join('');
    
    // Modal
    const modal = document.getElementById('idTipoInventario');
    modal.innerHTML = '<option value="">Seleccione...</option>' + 
        tipos.map(t => `<option value="${t.id_tipo_inventario}">${t.nombre}</option>`).join('');
}

function poblarSelectCategorias(tipoId = null) {
    const filtro = document.getElementById('filtroCategoria');
    const modal = document.getElementById('idCategoria');
    
    let cats = categorias;
    if (tipoId) {
        cats = categorias.filter(c => c.id_tipo_inventario == tipoId);
    }
    
    const opciones = '<option value="">Todas las categorías</option>' + 
        cats.map(c => `<option value="${c.id_categoria}">${c.nombre}</option>`).join('');
    
    filtro.innerHTML = opciones;
    modal.innerHTML = '<option value="">Seleccione...</option>' + 
        cats.map(c => `<option value="${c.id_categoria}">${c.nombre}</option>`).join('');
}

function poblarSelectUnidades() {
    const modal = document.getElementById('idUnidad');
    modal.innerHTML = '<option value="">Seleccione...</option>' + 
        unidades.map(u => `<option value="${u.id_unidad}">${u.nombre} (${u.abreviatura})</option>`).join('');
}

function poblarSelectUbicaciones() {
    const modal = document.getElementById('idUbicacion');
    modal.innerHTML = '<option value="">Sin asignar</option>' + 
        ubicaciones.map(u => `<option value="${u.id_ubicacion}">${u.nombre}</option>`).join('');
}

function poblarSelectLineas() {
    const modal = document.getElementById('idLineaProduccion');
    modal.innerHTML = '<option value="">Todas las líneas</option>' + 
        lineas.map(l => `<option value="${l.id_linea_produccion}">${l.nombre}</option>`).join('');
}

function cargarCategoriasPorTipo() {
    const tipoId = document.getElementById('idTipoInventario').value;
    poblarSelectCategorias(tipoId);
}

// ========== INVENTARIO ==========
async function cargarInventario() {
    try {
        const tipoId = document.getElementById('filtroTipo').value;
        const categoriaId = document.getElementById('filtroCategoria').value;
        const estadoStock = document.getElementById('filtroEstado').value;
        const buscar = document.getElementById('buscarInput').value;
        
        let url = `${baseUrl}/api/inventarios.php?action=list`;
        if (tipoId) url += `&tipo_id=${tipoId}`;
        if (categoriaId) url += `&categoria_id=${categoriaId}`;
        if (estadoStock) url += `&estado_stock=${estadoStock}`;
        if (buscar) url += `&buscar=${encodeURIComponent(buscar)}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            inventarios = data.inventarios;
            renderTabla(inventarios);
            document.getElementById('totalRegistros').textContent = `${data.total} registros`;
        }
    } catch (error) {
        console.error('Error cargando inventario:', error);
    }
}

function renderTabla(items) {
    const tbody = document.getElementById('tablaBody');
    
    if (!items || items.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8">
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h4>No se encontraron items</h4>
                        <p>Intenta con otros filtros o crea un nuevo item</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = items.map(item => {
        const estadoBadge = getEstadoBadge(item.estado_stock);
        return `
            <tr>
                <td class="codigo">${item.codigo}</td>
                <td>
                    <div class="nombre">${item.nombre}</div>
                    ${item.descripcion ? `<div class="descripcion">${item.descripcion}</div>` : ''}
                </td>
                <td>
                    <span class="tipo-badge" style="background: ${item.tipo_color}20; color: ${item.tipo_color}">
                        ${item.tipo_nombre}
                    </span>
                    <div style="font-size: 0.75rem; color: #888; margin-top: 4px;">${item.categoria_nombre}</div>
                </td>
                <td class="valor-numerico stock-actual">${parseFloat(item.stock_actual).toLocaleString('es-BO')} ${item.unidad}</td>
                <td>${estadoBadge}</td>
                <td class="valor-numerico costo">Bs. ${parseFloat(item.costo_unitario).toFixed(4)}</td>
                <td class="valor-numerico">Bs. ${parseFloat(item.valor_total).toLocaleString('es-BO', {minimumFractionDigits: 2})}</td>
                <td class="acciones">
                    <button class="btn-accion ver" title="Ver Kardex" onclick="verKardex(${item.id_inventario})">
                        <i class="fas fa-book"></i>
                    </button>
                    <button class="btn-accion movimiento" title="Registrar Movimiento" onclick="openModalMovimiento(${item.id_inventario})">
                        <i class="fas fa-exchange-alt"></i>
                    </button>
                    <button class="btn-accion editar" title="Editar" onclick="editarInventario(${item.id_inventario})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-accion eliminar" title="Eliminar" onclick="eliminarInventario(${item.id_inventario})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function getEstadoBadge(estado) {
    const badges = {
        'SIN_STOCK': '<span class="stock-badge sin-stock">Sin Stock</span>',
        'CRITICO': '<span class="stock-badge critico">Crítico</span>',
        'BAJO': '<span class="stock-badge bajo">Bajo</span>',
        'OK': '<span class="stock-badge ok">OK</span>'
    };
    return badges[estado] || estado;
}

// ========== FILTROS ==========
function filtrarPorTipo(tipoId) {
    if (tipoFiltroActivo === tipoId) {
        tipoFiltroActivo = null;
        document.getElementById('filtroTipo').value = '';
    } else {
        tipoFiltroActivo = tipoId;
        document.getElementById('filtroTipo').value = tipoId;
    }
    
    // Actualizar categorías según tipo
    poblarSelectCategorias(tipoFiltroActivo);
    
    cargarInventario();
    cargarDashboard();
}

function filtrarInventario() {
    tipoFiltroActivo = document.getElementById('filtroTipo').value || null;
    
    // Actualizar categorías si cambia el tipo
    const tipoId = document.getElementById('filtroTipo').value;
    poblarSelectCategorias(tipoId || null);
    
    cargarInventario();
    cargarDashboard();
}

function buscarInventario() {
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        cargarInventario();
    }, 300);
}

function limpiarFiltros() {
    document.getElementById('filtroTipo').value = '';
    document.getElementById('filtroCategoria').value = '';
    document.getElementById('filtroEstado').value = '';
    document.getElementById('buscarInput').value = '';
    tipoFiltroActivo = null;
    
    poblarSelectCategorias();
    cargarInventario();
    cargarDashboard();
}

// ========== MODAL CREAR/EDITAR ==========
function openModal() {
    document.getElementById('formInventario').reset();
    document.getElementById('idInventario').value = '';
    document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-box"></i> Nuevo Item de Inventario';
    document.getElementById('modalInventario').classList.add('show');
}

function closeModal() {
    document.getElementById('modalInventario').classList.remove('show');
}

async function editarInventario(id) {
    try {
        const response = await fetch(`${baseUrl}/api/inventarios.php?action=detalle&id=${id}`);
        const data = await response.json();
        
        if (data.success && data.item) {
            const item = data.item;
            
            document.getElementById('idInventario').value = item.id_inventario;
            document.getElementById('codigo').value = item.codigo;
            document.getElementById('nombre').value = item.nombre;
            document.getElementById('descripcion').value = item.descripcion || '';
            document.getElementById('idTipoInventario').value = item.id_tipo_inventario;
            
            // Cargar categorías del tipo y seleccionar
            await cargarCategoriasPorTipo();
            document.getElementById('idCategoria').value = item.id_categoria;
            
            document.getElementById('idUnidad').value = item.id_unidad;
            document.getElementById('stockActual').value = item.stock_actual;
            document.getElementById('stockMinimo').value = item.stock_minimo;
            document.getElementById('costoUnitario').value = item.costo_unitario;
            document.getElementById('idUbicacion').value = item.id_ubicacion || '';
            document.getElementById('idLineaProduccion').value = item.id_linea_produccion || '';
            document.getElementById('proveedorPrincipal').value = item.proveedor_principal || '';
            
            document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-edit"></i> Editar Item';
            document.getElementById('modalInventario').classList.add('show');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al cargar los datos');
    }
}

async function guardarInventario() {
    const form = document.getElementById('formInventario');
    
    // Validar campos requeridos
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const data = {
        id_inventario: document.getElementById('idInventario').value || null,
        codigo: document.getElementById('codigo').value,
        nombre: document.getElementById('nombre').value,
        descripcion: document.getElementById('descripcion').value,
        id_tipo_inventario: document.getElementById('idTipoInventario').value,
        id_categoria: document.getElementById('idCategoria').value,
        id_unidad: document.getElementById('idUnidad').value,
        stock_actual: document.getElementById('stockActual').value,
        stock_minimo: document.getElementById('stockMinimo').value,
        costo_unitario: document.getElementById('costoUnitario').value,
        id_ubicacion: document.getElementById('idUbicacion').value,
        id_linea_produccion: document.getElementById('idLineaProduccion').value,
        proveedor_principal: document.getElementById('proveedorPrincipal').value
    };
    
    try {
        const response = await fetch(`${baseUrl}/api/inventarios.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeModal();
            cargarDashboard();
            cargarInventario();
            showNotification(result.message, 'success');
        } else {
            alert(result.message || 'Error al guardar');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al guardar');
    }
}

async function eliminarInventario(id) {
    if (!confirm('¿Está seguro de eliminar este item?')) return;
    
    try {
        const response = await fetch(`${baseUrl}/api/inventarios.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_inventario: id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            cargarDashboard();
            cargarInventario();
            showNotification(result.message, 'success');
        } else {
            alert(result.message || 'Error al eliminar');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al eliminar');
    }
}

// ========== MODAL MOVIMIENTO ==========
let movTipoSeleccionado = null;

function openModalMovimiento(id) {
    const item = inventarios.find(i => i.id_inventario == id);
    if (!item) return;
    
    document.getElementById('movIdInventario').value = id;
    document.getElementById('movProductoNombre').textContent = item.nombre;
    document.getElementById('movStockActual').textContent = parseFloat(item.stock_actual).toLocaleString('es-BO');
    document.getElementById('movUnidad').textContent = item.unidad;
    document.getElementById('movCantidad').value = '';
    document.getElementById('movCostoUnitario').value = '';
    document.getElementById('movObservaciones').value = '';
    document.getElementById('movTipoMovimiento').innerHTML = '<option value="">Seleccione tipo de movimiento...</option>';
    
    // Reset botones
    document.querySelectorAll('.mov-tipo-btn').forEach(btn => btn.classList.remove('active'));
    movTipoSeleccionado = null;
    
    document.getElementById('modalMovimiento').classList.add('show');
}

function closeModalMovimiento() {
    document.getElementById('modalMovimiento').classList.remove('show');
}

function selectTipoMov(tipo) {
    movTipoSeleccionado = tipo;
    
    document.querySelectorAll('.mov-tipo-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`.mov-tipo-btn.${tipo}`).classList.add('active');
    
    const select = document.getElementById('movTipoMovimiento');
    
    if (tipo === 'entrada') {
        select.innerHTML = `
            <option value="">Seleccione...</option>
            <option value="ENTRADA_COMPRA">Entrada por Compra</option>
            <option value="ENTRADA_PRODUCCION">Entrada por Producción</option>
            <option value="ENTRADA_DEVOLUCION">Entrada por Devolución</option>
            <option value="ENTRADA_AJUSTE">Ajuste de Inventario (+)</option>
        `;
    } else {
        select.innerHTML = `
            <option value="">Seleccione...</option>
            <option value="SALIDA_PRODUCCION">Salida a Producción</option>
            <option value="SALIDA_VENTA">Salida por Venta</option>
            <option value="SALIDA_MERMA">Salida por Merma</option>
            <option value="SALIDA_MUESTRA">Salida por Muestra</option>
            <option value="SALIDA_AJUSTE">Ajuste de Inventario (-)</option>
        `;
    }
}

async function guardarMovimiento() {
    const idInventario = document.getElementById('movIdInventario').value;
    const tipoMovimiento = document.getElementById('movTipoMovimiento').value;
    const cantidad = parseFloat(document.getElementById('movCantidad').value);
    const costoUnitario = parseFloat(document.getElementById('movCostoUnitario').value) || 0;
    const observaciones = document.getElementById('movObservaciones').value;
    
    if (!tipoMovimiento) {
        alert('Seleccione un tipo de movimiento');
        return;
    }
    
    if (!cantidad || cantidad <= 0) {
        alert('Ingrese una cantidad válida');
        return;
    }
    
    try {
        const response = await fetch(`${baseUrl}/api/inventarios.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'movimiento',
                id_inventario: idInventario,
                tipo_movimiento: tipoMovimiento,
                cantidad: cantidad,
                costo_unitario: costoUnitario,
                observaciones: observaciones
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeModalMovimiento();
            cargarDashboard();
            cargarInventario();
            showNotification(result.message, 'success');
        } else {
            alert(result.message || 'Error al registrar movimiento');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al registrar movimiento');
    }
}

// ========== UTILIDADES ==========
function showNotification(message, type = 'info') {
    // Crear notificación
    const notif = document.createElement('div');
    notif.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#007bff'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 9999;
        animation: fadeIn 0.3s ease;
    `;
    notif.textContent = message;
    document.body.appendChild(notif);
    
    setTimeout(() => {
        notif.style.opacity = '0';
        setTimeout(() => notif.remove(), 300);
    }, 3000);
}

// =============================================
// KARDEX FÍSICO-VALORADO
// Método: Costo Promedio Ponderado (CPP)
// =============================================

let kardexData = {
    inventario: null,
    movimientos: []
};

function crearModalKardex() {
    if (document.getElementById('modalKardex')) return;
    
    const modalHTML = `
    <div class="modal-inventario" id="modalKardex">
        <div class="modal-content" style="max-width: 1200px;">
            <div class="modal-header">
                <h3 id="kardexTitulo"><i class="fas fa-book"></i> Kardex Físico-Valorado</h3>
                <button class="modal-close" onclick="closeModalKardex()">&times;</button>
            </div>
            <div class="modal-body" style="padding: 0;">
                <div id="kardexProductoInfo" style="padding: 16px 24px; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px;">
                        <div>
                            <small style="opacity: 0.7;">Código</small>
                            <div id="kardexCodigo" style="font-weight: 600; font-size: 1.1rem;">-</div>
                        </div>
                        <div>
                            <small style="opacity: 0.7;">Producto</small>
                            <div id="kardexNombre" style="font-weight: 600; font-size: 1.1rem;">-</div>
                        </div>
                        <div>
                            <small style="opacity: 0.7;">Stock Actual</small>
                            <div id="kardexStock" style="font-weight: 600; font-size: 1.1rem; color: #28a745;">-</div>
                        </div>
                        <div>
                            <small style="opacity: 0.7;">Costo Promedio</small>
                            <div id="kardexCPP" style="font-weight: 600; font-size: 1.1rem; color: #ffc107;">-</div>
                        </div>
                        <div>
                            <small style="opacity: 0.7;">Valor Total</small>
                            <div id="kardexValorTotal" style="font-weight: 600; font-size: 1.1rem; color: #17a2b8;">-</div>
                        </div>
                    </div>
                </div>
                
                <div style="padding: 16px 24px; background: #f8f9fa; border-bottom: 1px solid #eee; display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                    <div>
                        <label style="font-size: 0.85rem; color: #666;">Desde:</label>
                        <input type="date" id="kardexFechaDesde" style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="font-size: 0.85rem; color: #666;">Hasta:</label>
                        <input type="date" id="kardexFechaHasta" style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                    <button onclick="filtrarKardex()" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer;">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <button onclick="imprimirKardex()" style="padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 6px; cursor: pointer;">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>
                
                <div style="overflow-x: auto; max-height: 500px; overflow-y: auto;">
                    <table id="tablaKardex" style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                        <thead style="position: sticky; top: 0; z-index: 5;">
                            <tr style="background: #343a40; color: white;">
                                <th rowspan="2" style="padding: 10px; border: 1px solid #454d55; text-align: center; vertical-align: middle;">FECHA</th>
                                <th rowspan="2" style="padding: 10px; border: 1px solid #454d55; text-align: center; vertical-align: middle;">DOCUMENTO</th>
                                <th rowspan="2" style="padding: 10px; border: 1px solid #454d55; text-align: center; vertical-align: middle; min-width: 150px;">CONCEPTO</th>
                                <th colspan="3" style="padding: 8px; border: 1px solid #454d55; text-align: center; background: #28a745;">ENTRADAS</th>
                                <th colspan="3" style="padding: 8px; border: 1px solid #454d55; text-align: center; background: #dc3545;">SALIDAS</th>
                                <th colspan="3" style="padding: 8px; border: 1px solid #454d55; text-align: center; background: #007bff;">SALDO</th>
                            </tr>
                            <tr style="background: #495057; color: white;">
                                <th style="padding: 8px; border: 1px solid #454d55; text-align: right; background: #218838;">Cant.</th>
                                <th style="padding: 8px; border: 1px solid #454d55; text-align: right; background: #218838;">C.Unit.</th>
                                <th style="padding: 8px; border: 1px solid #454d55; text-align: right; background: #218838;">Total Bs.</th>
                                <th style="padding: 8px; border: 1px solid #454d55; text-align: right; background: #c82333;">Cant.</th>
                                <th style="padding: 8px; border: 1px solid #454d55; text-align: right; background: #c82333;">C.Unit.</th>
                                <th style="padding: 8px; border: 1px solid #454d55; text-align: right; background: #c82333;">Total Bs.</th>
                                <th style="padding: 8px; border: 1px solid #454d55; text-align: right; background: #0069d9;">Cant.</th>
                                <th style="padding: 8px; border: 1px solid #454d55; text-align: right; background: #0069d9;">C.Prom.</th>
                                <th style="padding: 8px; border: 1px solid #454d55; text-align: right; background: #0069d9;">Total Bs.</th>
                            </tr>
                        </thead>
                        <tbody id="kardexBody">
                            <tr>
                                <td colspan="12" style="text-align: center; padding: 40px; color: #888;">
                                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i>
                                    <p>Cargando movimientos...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

async function verKardex(idInventario) {
    crearModalKardex();
    
    const item = inventarios.find(i => i.id_inventario == idInventario);
    if (!item) {
        alert('Producto no encontrado');
        return;
    }
    
    kardexData.inventario = item;
    
    document.getElementById('kardexCodigo').textContent = item.codigo;
    document.getElementById('kardexNombre').textContent = item.nombre;
    document.getElementById('kardexStock').textContent = parseFloat(item.stock_actual).toLocaleString('es-BO') + ' ' + item.unidad;
    document.getElementById('kardexCPP').textContent = 'Bs. ' + parseFloat(item.costo_unitario).toFixed(4);
    document.getElementById('kardexValorTotal').textContent = 'Bs. ' + parseFloat(item.valor_total).toLocaleString('es-BO', {minimumFractionDigits: 2});
    
    const hoy = new Date();
    const hace30Dias = new Date();
    hace30Dias.setDate(hace30Dias.getDate() - 30);
    
    document.getElementById('kardexFechaHasta').value = hoy.toISOString().split('T')[0];
    document.getElementById('kardexFechaDesde').value = hace30Dias.toISOString().split('T')[0];
    
    await cargarMovimientosKardex(idInventario);
    
    document.getElementById('modalKardex').classList.add('show');
}

async function cargarMovimientosKardex(idInventario) {
    try {
        const response = await fetch(`${baseUrl}/api/inventarios.php?action=kardex&id=${idInventario}`);
        const data = await response.json();
        
        if (data.success) {
            kardexData.movimientos = data.movimientos;
            renderKardex(data.movimientos);
        } else {
            document.getElementById('kardexBody').innerHTML = `
                <tr><td colspan="12" style="text-align: center; padding: 40px; color: #dc3545;">
                    Error al cargar movimientos
                </td></tr>
            `;
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

function renderKardex(movimientos) {
    const tbody = document.getElementById('kardexBody');
    
    if (!movimientos || movimientos.length === 0) {
        tbody.innerHTML = `
            <tr><td colspan="12" style="text-align: center; padding: 40px; color: #888;">
                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                No hay movimientos registrados.<br>
                <small>Registre una entrada o salida para ver el kardex.</small>
            </td></tr>
        `;
        return;
    }
    
    const movsOrdenados = [...movimientos].sort((a, b) => 
        new Date(a.fecha_movimiento) - new Date(b.fecha_movimiento)
    );
    
    let saldoCantidad = parseFloat(movsOrdenados[0].stock_anterior);
    let costoProm = parseFloat(movsOrdenados[0].costo_unitario);
    let saldoValor = saldoCantidad * costoProm;
    
    let filas = [];
    
    // Saldo inicial
    if (saldoCantidad > 0) {
        filas.push(`
            <tr style="background: #f8f9fa; font-style: italic;">
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: center;">-</td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: center;">-</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;"><strong>SALDO INICIAL</strong></td>
                <td colspan="3" style="padding: 8px; border: 1px solid #dee2e6; background: #e8f5e9;"></td>
                <td colspan="3" style="padding: 8px; border: 1px solid #dee2e6; background: #ffebee;"></td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: right; background: #e3f2fd; font-weight: 600;">${saldoCantidad.toLocaleString('es-BO', {minimumFractionDigits: 2})}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: right; background: #e3f2fd; font-weight: 600;">${costoProm.toFixed(4)}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: right; background: #e3f2fd; font-weight: 600;">${saldoValor.toLocaleString('es-BO', {minimumFractionDigits: 2})}</td>
            </tr>
        `);
    }
    
    movsOrdenados.forEach((mov, index) => {
        const fecha = new Date(mov.fecha_movimiento);
        const fechaStr = fecha.toLocaleDateString('es-BO');
        const horaStr = fecha.toLocaleTimeString('es-BO', {hour: '2-digit', minute: '2-digit'});
        
        const esEntrada = mov.tipo_movimiento.includes('ENTRADA') || mov.tipo_movimiento === 'TRANSFERENCIA_ENTRADA';
        const cantidad = parseFloat(mov.cantidad);
        const costoUnit = parseFloat(mov.costo_unitario);
        const valorMov = parseFloat(mov.costo_total);
        const stockNuevo = parseFloat(mov.stock_nuevo);
        
        if (esEntrada && stockNuevo > 0) {
            saldoValor = saldoValor + valorMov;
            costoProm = saldoValor / stockNuevo;
        } else if (!esEntrada) {
            saldoValor = saldoValor - valorMov;
        }
        saldoCantidad = stockNuevo;
        
        const conceptos = {
            'ENTRADA_COMPRA': 'Compra',
            'ENTRADA_PRODUCCION': 'Producción (Entrada)',
            'ENTRADA_DEVOLUCION': 'Devolución',
            'ENTRADA_AJUSTE': 'Ajuste (+)',
            'ENTRADA_INICIAL': 'Inventario Inicial',
            'SALIDA_PRODUCCION': 'Producción (Salida)',
            'SALIDA_VENTA': 'Venta',
            'SALIDA_MERMA': 'Merma',
            'SALIDA_MUESTRA': 'Muestra',
            'SALIDA_AJUSTE': 'Ajuste (-)',
            'TRANSFERENCIA_ENTRADA': 'Transferencia (+)',
            'TRANSFERENCIA_SALIDA': 'Transferencia (-)'
        };
        const concepto = conceptos[mov.tipo_movimiento] || mov.tipo_movimiento;
        const documento = mov.documento_numero ? `${mov.documento_tipo || ''} ${mov.documento_numero}`.trim() : '-';
        
        filas.push(`
            <tr style="${index % 2 === 0 ? '' : 'background: #f8f9fa;'}">
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: center; white-space: nowrap;">
                    ${fechaStr}<br><small style="color: #888;">${horaStr}</small>
                </td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: center; font-size: 0.8rem;">${documento}</td>
                <td style="padding: 8px; border: 1px solid #dee2e6;">
                    ${concepto}
                    ${mov.observaciones ? `<br><small style="color: #888;">${mov.observaciones}</small>` : ''}
                </td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: right; background: #e8f5e9;">
                    ${esEntrada ? cantidad.toLocaleString('es-BO', {minimumFractionDigits: 2}) : ''}
                </td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: right; background: #e8f5e9;">
                    ${esEntrada ? costoUnit.toFixed(4) : ''}
                </td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: right; background: #e8f5e9; font-weight: 500;">
                    ${esEntrada ? valorMov.toLocaleString('es-BO', {minimumFractionDigits: 2}) : ''}
                </td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: right; background: #ffebee;">
                    ${!esEntrada ? cantidad.toLocaleString('es-BO', {minimumFractionDigits: 2}) : ''}
                </td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: right; background: #ffebee;">
                    ${!esEntrada ? costoUnit.toFixed(4) : ''}
                </td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: right; background: #ffebee; font-weight: 500;">
                    ${!esEntrada ? valorMov.toLocaleString('es-BO', {minimumFractionDigits: 2}) : ''}
                </td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: right; background: #e3f2fd; font-weight: 600;">
                    ${saldoCantidad.toLocaleString('es-BO', {minimumFractionDigits: 2})}
                </td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: right; background: #e3f2fd; font-weight: 600;">
                    ${costoProm.toFixed(4)}
                </td>
                <td style="padding: 8px; border: 1px solid #dee2e6; text-align: right; background: #e3f2fd; font-weight: 600;">
                    ${(saldoCantidad * costoProm).toLocaleString('es-BO', {minimumFractionDigits: 2})}
                </td>
            </tr>
        `);
    });
    
    tbody.innerHTML = filas.join('');
}

function filtrarKardex() {
    const desde = document.getElementById('kardexFechaDesde').value;
    const hasta = document.getElementById('kardexFechaHasta').value;
    
    let movsFiltrados = kardexData.movimientos;
    
    if (desde) {
        const fechaDesde = new Date(desde);
        movsFiltrados = movsFiltrados.filter(m => new Date(m.fecha_movimiento) >= fechaDesde);
    }
    
    if (hasta) {
        const fechaHasta = new Date(hasta + 'T23:59:59');
        movsFiltrados = movsFiltrados.filter(m => new Date(m.fecha_movimiento) <= fechaHasta);
    }
    
    renderKardex(movsFiltrados);
}

function closeModalKardex() {
    document.getElementById('modalKardex').classList.remove('show');
}

function imprimirKardex() {
    const item = kardexData.inventario;
    if (!item) return;
    
    const tabla = document.getElementById('tablaKardex').outerHTML;
    
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Kardex - ${item.codigo}</title>
            <style>
                @page { size: landscape; margin: 1cm; }
                body { font-family: Arial, sans-serif; font-size: 10px; }
                .header { text-align: center; margin-bottom: 20px; }
                .header h1 { margin: 0; font-size: 18px; }
                .header h2 { margin: 5px 0; font-size: 14px; font-weight: normal; }
                .info { display: flex; justify-content: space-between; margin-bottom: 15px; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #333; padding: 4px 6px; }
                th { background: #333; color: white; font-size: 9px; }
                .text-right { text-align: right; }
                .footer { margin-top: 30px; display: flex; justify-content: space-around; }
                .firma { text-align: center; padding-top: 40px; border-top: 1px solid #333; width: 200px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>HERMEN LTDA.</h1>
                <h2>KARDEX FÍSICO-VALORADO</h2>
                <p>Método: Costo Promedio Ponderado (CPP)</p>
            </div>
            
            <div class="info">
                <div><strong>Código:</strong> ${item.codigo}</div>
                <div><strong>Producto:</strong> ${item.nombre}</div>
                <div><strong>Unidad:</strong> ${item.unidad}</div>
                <div><strong>Fecha:</strong> ${new Date().toLocaleDateString('es-BO')}</div>
            </div>
            
            ${tabla}
            
            <div class="footer">
                <div class="firma">Elaborado por</div>
                <div class="firma">Revisado por</div>
                <div class="firma">Aprobado por</div>
            </div>
            
            <scr` + `ipt>window.onload = function() { window.print(); }<\/scr` + `ipt>
        </body>
        </html>
    `);
    ventana.document.close();
}
</script>

<?php require_once '../../includes/footer.php'; ?>