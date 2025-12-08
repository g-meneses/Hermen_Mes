<?php
/**
 * Módulo de Inventarios - Materias Primas
 * Sistema MES Hermen Ltda. v1.0
 * Página independiente para gestión completa de Materias Primas
 */
require_once '../../config/database.php';
if (!isLoggedIn()) { redirect('index.php'); }

$pageTitle = 'Materias Primas - Inventarios';
$currentPage = 'inventarios';

$db = getDB();
$stmt = $db->prepare("SELECT * FROM tipos_inventario WHERE codigo = 'MP' AND activo = 1");
$stmt->execute();
$tipoInventario = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$tipoInventario) { die('Error: Tipo de inventario "Materias Primas" no encontrado'); }

$tipoId = $tipoInventario['id_tipo_inventario'];
$tipoColor = $tipoInventario['color'] ?? '#007bff';
$tipoIcono = $tipoInventario['icono'] ?? 'fa-box';

require_once '../../includes/header.php';
?>

<link rel="stylesheet" href="css/inventario_tipo.css">
<style>
:root { --tipo-color: <?php echo $tipoColor; ?>; }

.mp-module { padding: 20px; background: #f4f6f9; min-height: calc(100vh - 60px); }

.mp-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: white; padding: 20px 25px; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); flex-wrap: wrap; gap: 15px; }
.mp-header-left { display: flex; align-items: center; gap: 20px; }
.btn-volver { display: flex; align-items: center; gap: 8px; padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 8px; text-decoration: none; }
.btn-volver:hover { background: #5a6268; color: white; }
.mp-title { display: flex; align-items: center; gap: 15px; }
.mp-title-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; background: var(--tipo-color); }
.mp-title h1 { font-size: 1.6rem; color: #1a1a2e; margin: 0; }
.mp-title p { font-size: 0.85rem; color: #6c757d; margin: 0; }
.mp-header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.btn-action { display: flex; align-items: center; gap: 8px; padding: 10px 18px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 500; }
.btn-ingreso { background: #28a745; color: white; }
.btn-salida { background: #dc3545; color: white; }
.btn-historial { background: #17a2b8; color: white; }
.btn-nuevo { background: var(--tipo-color); color: white; }

.mp-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
.kpi-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 15px; }
.kpi-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: white; }
.kpi-icon.items { background: linear-gradient(135deg, #667eea, #764ba2); }
.kpi-icon.valor { background: linear-gradient(135deg, #11998e, #38ef7d); }
.kpi-icon.alertas { background: linear-gradient(135deg, #eb3349, #f45c43); }
.kpi-icon.categorias { background: linear-gradient(135deg, #4facfe, #00f2fe); }
.kpi-label { font-size: 0.8rem; color: #6c757d; }
.kpi-value { font-size: 1.4rem; font-weight: 700; color: #1a1a2e; }
.kpi-value.danger { color: #dc3545; }

.categorias-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px; }
.categoria-card { background: white; border-radius: 12px; padding: 18px; cursor: pointer; transition: all 0.2s; border: 2px solid transparent; border-top: 3px solid var(--tipo-color); }
.categoria-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.12); }
.categoria-card.active { border-color: var(--tipo-color); }
.categoria-header { display: flex; justify-content: space-between; margin-bottom: 12px; }
.categoria-nombre { font-weight: 600; color: #1a1a2e; }
.categoria-badge { background: var(--tipo-color); color: white; font-size: 0.75rem; padding: 2px 8px; border-radius: 10px; }
.categoria-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; text-align: center; }
.cat-stat-value { font-size: 1rem; font-weight: 700; }
.cat-stat-value.alerta { color: #dc3545; }
.cat-stat-label { font-size: 0.65rem; color: #6c757d; text-transform: uppercase; }

.subcategorias-grid { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; padding: 15px; background: #e9ecef; border-radius: 8px; }
.subcategoria-chip { padding: 8px 16px; background: white; border: 2px solid #dee2e6; border-radius: 20px; cursor: pointer; }
.subcategoria-chip:hover { border-color: var(--tipo-color); }
.subcategoria-chip.active { background: var(--tipo-color); color: white; border-color: var(--tipo-color); }

.mp-productos { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.productos-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid #e9ecef; flex-wrap: wrap; gap: 10px; }
.productos-table { width: 100%; border-collapse: collapse; }
.productos-table th { background: #f8f9fa; padding: 12px 15px; text-align: left; font-size: 0.8rem; color: #6c757d; text-transform: uppercase; }
.productos-table td { padding: 12px 15px; border-bottom: 1px solid #f1f1f1; }
.productos-table tr:hover { background: #f8f9fa; }

.stock-badge { padding: 4px 10px; border-radius: 15px; font-size: 0.75rem; font-weight: 600; }
.stock-badge.ok { background: #d4edda; color: #155724; }
.stock-badge.bajo { background: #fff3cd; color: #856404; }
.stock-badge.critico { background: #f8d7da; color: #721c24; }
.stock-badge.sin-stock { background: #e9ecef; color: #6c757d; }

.btn-icon { width: 32px; height: 32px; border: none; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; margin: 0 2px; }
.btn-icon.kardex { background: #6f42c1; color: white; }
.btn-icon.editar { background: #ffc107; color: #212529; }

/* MODALES */
.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
.modal.show { display: flex; }
.modal-content { background: white; border-radius: 16px; width: 95%; max-width: 900px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
.modal-content.large { max-width: 1100px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 25px; border-bottom: 1px solid #e9ecef; background: #f8f9fa; }
.modal-header h3 { margin: 0; font-size: 1.2rem; }
.modal-close { width: 35px; height: 35px; border: none; background: #e9ecef; border-radius: 50%; cursor: pointer; font-size: 1.2rem; }
.modal-close:hover { background: #dc3545; color: white; }
.modal-body { padding: 25px; overflow-y: auto; flex: 1; }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 15px 25px; border-top: 1px solid #e9ecef; }

.form-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 15px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 0.85rem; font-weight: 500; color: #495057; }
.form-group input, .form-group select, .form-group textarea { padding: 10px 14px; border: 1px solid #dee2e6; border-radius: 8px; font-size: 0.9rem; }
.form-group input:focus, .form-group select:focus { outline: none; border-color: var(--tipo-color); }

.btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }
.btn-primary { background: var(--tipo-color); color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-danger { background: #dc3545; color: white; }

.tabla-lineas { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
.tabla-lineas th { background: #343a40; color: white; padding: 10px; font-size: 0.8rem; }
.tabla-lineas td { padding: 8px; border-bottom: 1px solid #e9ecef; }
.tabla-lineas select, .tabla-lineas input { width: 100%; padding: 8px; border: 1px solid #dee2e6; border-radius: 4px; }

.totales-box { background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: right; }
.totales-box .total-final { font-size: 1.2rem; font-weight: 700; color: var(--tipo-color); }

.checkbox-iva { display: flex; align-items: center; gap: 10px; padding: 10px; background: #e7f3ff; border-radius: 8px; margin-bottom: 15px; }
.checkbox-iva input { width: 18px; height: 18px; }

.badge-activo { background: #28a745; color: white; padding: 3px 10px; border-radius: 10px; font-size: 0.75rem; }
.badge-anulado { background: #dc3545; color: white; padding: 3px 10px; border-radius: 10px; font-size: 0.75rem; }

.kardex-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.kardex-table th { background: #343a40; color: white; padding: 10px; }
.kardex-table td { padding: 10px; border-bottom: 1px solid #e9ecef; }
.kardex-table tr.entrada td { background: #d4edda; }
.kardex-table tr.salida td { background: #f8d7da; }

@media (max-width: 768px) {
    .mp-header { flex-direction: column; }
    .form-row { grid-template-columns: 1fr; }
}
</style>

<div class="mp-module">
    <!-- HEADER -->
    <div class="mp-header">
        <div class="mp-header-left">
            <a href="index.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver</a>
            <div class="mp-title">
                <div class="mp-title-icon"><i class="fas <?php echo $tipoIcono; ?>"></i></div>
                <div>
                    <h1>Materias Primas</h1>
                    <p>Gestión de inventario de materias primas</p>
                </div>
            </div>
        </div>
        <div class="mp-header-actions">
            <button class="btn-action btn-ingreso" onclick="abrirModalIngreso()"><i class="fas fa-arrow-down"></i> Ingreso</button>
            <button class="btn-action btn-salida" onclick="abrirModalSalida()"><i class="fas fa-arrow-up"></i> Salida</button>
            <button class="btn-action btn-historial" onclick="abrirModalHistorial()"><i class="fas fa-history"></i> Historial</button>
            <button class="btn-action btn-nuevo" onclick="abrirModalNuevoItem()"><i class="fas fa-plus"></i> Nuevo Item</button>
        </div>
    </div>

    <!-- KPIs -->
    <div class="mp-kpis">
        <div class="kpi-card">
            <div class="kpi-icon items"><i class="fas fa-boxes"></i></div>
            <div class="kpi-info"><div class="kpi-label">Total Items</div><div class="kpi-value" id="kpiItems">0</div></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon valor"><i class="fas fa-dollar-sign"></i></div>
            <div class="kpi-info"><div class="kpi-label">Valor Total</div><div class="kpi-value" id="kpiValor">Bs. 0</div></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon alertas"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="kpi-info"><div class="kpi-label">Alertas Stock</div><div class="kpi-value danger" id="kpiAlertas">0</div></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon categorias"><i class="fas fa-folder"></i></div>
            <div class="kpi-info"><div class="kpi-label">Categorías</div><div class="kpi-value" id="kpiCategorias">0</div></div>
        </div>
    </div>

    <!-- CATEGORÍAS -->
    <h3 style="margin-bottom: 15px;"><i class="fas fa-folder-open"></i> Categorías</h3>
    <div class="categorias-grid" id="categoriasGrid">
        <p style="padding: 20px; text-align: center;"><i class="fas fa-spinner fa-spin"></i> Cargando...</p>
    </div>

    <!-- SUBCATEGORÍAS -->
    <div id="subcategoriasSection" style="display: none;">
        <h4 style="margin-bottom: 10px;"><i class="fas fa-folder"></i> Subcategorías de <span id="subcategoriaTitulo"></span></h4>
        <div class="subcategorias-grid" id="subcategoriasGrid"></div>
    </div>

    <!-- PRODUCTOS -->
    <div class="mp-productos" id="productosSection" style="display: none;">
        <div class="productos-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <h3 id="productosTitulo">Productos</h3>
                <span class="categoria-badge" id="productosCount">0 items</span>
            </div>
            <input type="text" id="buscarProducto" placeholder="Buscar..." onkeyup="filtrarProductos()" style="padding: 8px 15px; border: 1px solid #dee2e6; border-radius: 8px; width: 200px;">
        </div>
        <table class="productos-table">
            <thead>
                <tr>
                    <th>Código</th><th>Nombre</th><th>Stock</th><th>Unidad</th><th>Estado</th><th>Costo</th><th>Valor</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody id="productosBody"></tbody>
        </table>
    </div>
</div>

<!-- MODALES -->
<!-- Modal Nuevo/Editar Item -->
<div class="modal" id="modalItem">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-box"></i> <span id="modalItemTitulo">Nuevo Item</span></h3>
            <button class="modal-close" onclick="cerrarModal('modalItem')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="formItem">
                <input type="hidden" id="itemId">
                <div class="form-row">
                    <div class="form-group"><label>Código *</label><input type="text" id="itemCodigo" required></div>
                    <div class="form-group"><label>Nombre *</label><input type="text" id="itemNombre" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Categoría *</label><select id="itemCategoria" required onchange="cargarSubcategoriasItem()"></select></div>
                    <div class="form-group"><label>Subcategoría</label><select id="itemSubcategoria"><option value="">Sin subcategoría</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Stock Actual</label><input type="number" id="itemStockActual" step="0.01" value="0"></div>
                    <div class="form-group"><label>Stock Mínimo</label><input type="number" id="itemStockMinimo" step="0.01" value="0"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Unidad *</label><select id="itemUnidad" required></select></div>
                    <div class="form-group"><label>Costo Unitario (Bs.)</label><input type="number" id="itemCosto" step="0.01" value="0"></div>
                </div>
                <div class="form-group"><label>Descripción</label><textarea id="itemDescripcion"></textarea></div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModal('modalItem')">Cancelar</button>
            <button class="btn btn-success" onclick="guardarItem()"><i class="fas fa-save"></i> Guardar</button>
        </div>
    </div>
</div>

<!-- Modal Ingreso -->
<div class="modal" id="modalIngreso">
    <div class="modal-content large">
        <div class="modal-header" style="background: linear-gradient(135deg, #28a745, #20c997); color: white;">
            <h3><i class="fas fa-arrow-down"></i> Ingreso de Materias Primas</h3>
            <button class="modal-close" onclick="cerrarModal('modalIngreso')" style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group"><label>Documento Nº</label><input type="text" id="ingresoDocumento" readonly></div>
                <div class="form-group"><label>Fecha</label><input type="date" id="ingresoFecha"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Proveedor</label><select id="ingresoProveedor"></select></div>
                <div class="form-group"><label>Nº Factura</label><input type="text" id="ingresoReferencia"></div>
            </div>
            <div class="checkbox-iva">
                <input type="checkbox" id="ingresoConFactura" onchange="recalcularIngreso()">
                <label for="ingresoConFactura"><strong>Con Factura</strong> - Deducir IVA 13%</label>
            </div>
            <h4><i class="fas fa-list"></i> Líneas de Ingreso</h4>
            <table class="tabla-lineas">
                <thead><tr><th>Producto</th><th>Cantidad</th><th>Costo Unit.</th><th>Costo Neto</th><th>Subtotal</th><th></th></tr></thead>
                <tbody id="ingresoLineasBody"></tbody>
            </table>
            <button class="btn btn-primary" onclick="agregarLineaIngreso()"><i class="fas fa-plus"></i> Agregar Línea</button>
            <div class="totales-box" style="margin-top: 15px;">
                <p>Total Neto: <strong id="ingresoTotalNeto">Bs. 0.00</strong></p>
                <p>IVA 13%: <strong id="ingresoIVA">Bs. 0.00</strong></p>
                <p class="total-final">TOTAL: <span id="ingresoTotal">Bs. 0.00</span></p>
            </div>
            <div class="form-group" style="margin-top: 15px;"><label>Observaciones</label><textarea id="ingresoObservaciones"></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModal('modalIngreso')">Cancelar</button>
            <button class="btn btn-success" onclick="guardarIngreso()"><i class="fas fa-check"></i> Registrar Ingreso</button>
        </div>
    </div>
</div>

<!-- Modal Salida -->
<div class="modal" id="modalSalida">
    <div class="modal-content large">
        <div class="modal-header" style="background: linear-gradient(135deg, #dc3545, #c82333); color: white;">
            <h3><i class="fas fa-arrow-up"></i> Salida de Materias Primas</h3>
            <button class="modal-close" onclick="cerrarModal('modalSalida')" style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group"><label>Documento Nº</label><input type="text" id="salidaDocumento" readonly></div>
                <div class="form-group"><label>Fecha</label><input type="date" id="salidaFecha"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Tipo de Salida</label>
                    <select id="salidaTipo">
                        <option value="SALIDA_PRODUCCION">Producción</option>
                        <option value="SALIDA_AJUSTE">Ajuste</option>
                        <option value="SALIDA_MERMA">Merma</option>
                    </select>
                </div>
                <div class="form-group"><label>Referencia</label><input type="text" id="salidaReferencia"></div>
            </div>
            <h4><i class="fas fa-list"></i> Líneas de Salida</h4>
            <table class="tabla-lineas">
                <thead><tr><th>Producto</th><th>Stock Disp.</th><th>Cantidad</th><th>Costo CPP</th><th>Subtotal</th><th></th></tr></thead>
                <tbody id="salidaLineasBody"></tbody>
            </table>
            <button class="btn btn-primary" onclick="agregarLineaSalida()"><i class="fas fa-plus"></i> Agregar Línea</button>
            <div class="totales-box" style="margin-top: 15px;">
                <p class="total-final">TOTAL: <span id="salidaTotal">Bs. 0.00</span></p>
            </div>
            <div class="form-group" style="margin-top: 15px;"><label>Observaciones</label><textarea id="salidaObservaciones"></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModal('modalSalida')">Cancelar</button>
            <button class="btn btn-danger" onclick="guardarSalida()"><i class="fas fa-check"></i> Registrar Salida</button>
        </div>
    </div>
</div>

<!-- Modal Historial -->
<div class="modal" id="modalHistorial">
    <div class="modal-content large">
        <div class="modal-header">
            <h3><i class="fas fa-history"></i> Historial de Movimientos</h3>
            <button class="modal-close" onclick="cerrarModal('modalHistorial')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <div class="form-group" style="flex:1; min-width: 120px;"><label>Desde</label><input type="date" id="historialDesde"></div>
                <div class="form-group" style="flex:1; min-width: 120px;"><label>Hasta</label><input type="date" id="historialHasta"></div>
                <div class="form-group" style="flex:1; min-width: 120px;"><label>Tipo</label>
                    <select id="historialTipo"><option value="">Todos</option><option value="ENTRADA">Entradas</option><option value="SALIDA">Salidas</option></select>
                </div>
                <div class="form-group" style="align-self: flex-end;"><button class="btn btn-primary" onclick="buscarHistorial()"><i class="fas fa-search"></i> Buscar</button></div>
            </div>
            <table class="productos-table">
                <thead><tr><th>Fecha</th><th>Documento</th><th>Tipo</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody id="historialBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Detalle -->
<div class="modal" id="modalDetalle">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-file-alt"></i> <span id="detalleTitulo">Detalle</span></h3>
            <button class="modal-close" onclick="cerrarModal('modalDetalle')">&times;</button>
        </div>
        <div class="modal-body" id="detalleContenido"></div>
        <div class="modal-footer">
            <button class="btn btn-danger" id="btnAnular" onclick="anularDocumento()" style="display:none;"><i class="fas fa-ban"></i> Anular</button>
            <button class="btn btn-primary" onclick="imprimirDocumento()"><i class="fas fa-print"></i> Imprimir</button>
            <button class="btn btn-secondary" onclick="cerrarModal('modalDetalle')">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal Kardex -->
<div class="modal" id="modalKardex">
    <div class="modal-content large">
        <div class="modal-header" style="background: linear-gradient(135deg, #6f42c1, #e83e8c); color: white;">
            <h3><i class="fas fa-book"></i> <span id="kardexTitulo">Kardex</span></h3>
            <button class="modal-close" onclick="cerrarModal('modalKardex')" style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body">
            <div id="kardexHeader" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;"></div>
            <table class="kardex-table">
                <thead><tr><th>Fecha</th><th>Documento</th><th>Tipo</th><th>Entrada</th><th>Salida</th><th>Saldo</th><th>Costo</th><th>CPP</th></tr></thead>
                <tbody id="kardexBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script src="js/materias_primas.js"></script>
<?php require_once '../../includes/footer.php'; ?>