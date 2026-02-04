<?php
// modules/compras/index.php
require_once '../../config/database.php';

if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}
$pageTitle = "Dashboard de Compras";
include '../../includes/header.php';
?>

<!-- Estilos inspirados en Centro de Inventarios -->
<style>
    :root {
        --color-solicitud: #4e73df;
        /* Azul */
        --color-aprobacion: #f6c23e;
        /* Amarillo */
        --color-orden: #36b9cc;
        /* Cyan */
        --color-recepcion: #1cc88a;
        /* Verde */
        --color-proveedor: #e74a3b;
        /* Rojo */
        --bg-light: #f8f9fc;
        --text-dark: #5a5c69;
    }

    .compras-module {
        padding: 25px;
        background-color: var(--bg-light);
        min-height: calc(100vh - 60px);
    }

    /* HEADER */
    .module-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .module-title {
        font-size: 1.8rem;
        color: #2e59d9;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .module-title i {
        background: rgba(78, 115, 223, 0.1);
        padding: 10px;
        border-radius: 12px;
    }

    /* KPI CARDS */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 25px;
        margin-bottom: 35px;
    }

    .kpi-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border-left: 5px solid transparent;
        position: relative;
        overflow: hidden;
    }

    .kpi-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    /* Variantes de KPI */
    .kpi-solicitudes {
        border-color: var(--color-solicitud);
    }

    .kpi-solicitudes .kpi-icon {
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    }

    .kpi-solicitudes .kpi-value {
        color: var(--color-solicitud);
    }

    .kpi-dinero {
        border-color: var(--color-orden);
    }

    .kpi-dinero .kpi-icon {
        background: linear-gradient(135deg, #36b9cc 0%, #258391 100%);
    }

    .kpi-dinero .kpi-value {
        color: var(--color-orden);
    }

    .kpi-recepciones {
        border-color: var(--color-recepcion);
    }

    .kpi-recepciones .kpi-icon {
        background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
    }

    .kpi-recepciones .kpi-value {
        color: var(--color-recepcion);
    }

    .kpi-aprobaciones {
        border-color: var(--color-aprobacion);
    }

    .kpi-aprobaciones .kpi-icon {
        background: linear-gradient(135deg, #f6c23e 0%, #dda20a 100%);
    }

    .kpi-aprobaciones .kpi-value {
        color: var(--color-aprobacion);
    }

    .kpi-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        color: white;
        flex-shrink: 0;
    }

    .kpi-content {
        flex: 1;
    }

    .kpi-label {
        font-size: 0.85rem;
        color: #858796;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .kpi-value {
        font-size: 1.8rem;
        font-weight: 800;
        line-height: 1.2;
    }

    /* SECCIONES PRINCIPALES */
    .main-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
    }

    .content-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        padding: 30px;
        height: 100%;
    }

    .card-header-custom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .card-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: #4e73df;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* ACCIONES RÁPIDAS (Grid like inventory types) */
    .actions-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }

    .action-btn {
        padding: 20px;
        border-radius: 15px;
        background: white;
        border: 2px solid #eaecf4;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none !important;
        color: var(--text-dark);
    }

    .action-btn:hover {
        border-color: var(--color-solicitud);
        background: #fdfdfe;
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
    }

    .action-icon {
        font-size: 2rem;
        margin-bottom: 10px;
        color: var(--color-solicitud);
    }

    .action-label {
        font-weight: 600;
        font-size: 0.9rem;
    }

    /* ALERTAS */
    .alert-item {
        background: #fff;
        border-left: 4px solid #f6c23e;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        display: flex;
        gap: 15px;
    }

    .alert-icon-box {
        color: #f6c23e;
        font-size: 1.2rem;
        padding-top: 2px;
    }

    @media (max-width: 992px) {
        .main-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="compras-module">
    <!-- Header -->
    <div class="module-header">
        <h1 class="module-title">
            <i class="fas fa-shopping-cart"></i>
            Gestión de Compras
        </h1>
        <div class="d-flex gap-2">
            <a href="solicitudes.php" class="btn btn-primary btn-lg shadow-sm" style="border-radius: 10px;">
                <i class="fas fa-plus fa-sm text-white-50 mr-2"></i> Nueva Solicitud
            </a>
        </div>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
        <!-- Solicitudes -->
        <div class="kpi-card kpi-solicitudes">
            <div class="kpi-icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-label">Solicitudes Pendientes</div>
                <div class="kpi-value" id="stat-solicitudes">0</div>
            </div>
        </div>

        <!-- Aprobaciones -->
        <div class="kpi-card kpi-aprobaciones">
            <div class="kpi-icon">
                <i class="fas fa-check-double"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-label">Por Aprobar</div>
                <div class="kpi-value" id="stat-aprobaciones">0</div> <!-- Placeholder -->
            </div>
        </div>

        <!-- Compras Mes -->
        <div class="kpi-card kpi-dinero">
            <div class="kpi-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-label">Compras del Mes (BOB)</div>
                <div class="kpi-value" id="stat-monto">0.00</div>
            </div>
        </div>

        <!-- Recepciones -->
        <div class="kpi-card kpi-recepciones">
            <div class="kpi-icon">
                <i class="fas fa-truck-loading"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-label">Recepciones Pendientes</div>
                <div class="kpi-value" id="stat-recepciones">0</div>
            </div>
        </div>
    </div>

    <!-- Contenido Principal -->
    <div class="main-grid">
        <!-- Gráfico -->
        <div class="content-card">
            <div class="card-header-custom">
                <div class="card-title">
                    <i class="fas fa-chart-area"></i>
                    Evolución de Compras
                </div>
            </div>
            <div class="chart-area" style="height: 320px;">
                <canvas id="comprasChart"></canvas>
            </div>
        </div>

        <!-- Sidebar Derecho (Accesos y Alertas) -->
        <div style="display: flex; flex-direction: column; gap: 30px;">
            <!-- Accesos Rápidos -->
            <div class="content-card">
                <div class="card-header-custom">
                    <div class="card-title">
                        <i class="fas fa-rocket"></i>
                        Accesos Rápidos
                    </div>
                </div>
                <div class="actions-grid">
                    <a href="ordenes.php" class="action-btn">
                        <i class="fas fa-file-invoice action-icon"></i>
                        <span class="action-label">Órdenes</span>
                    </a>
                    <a href="recepciones.php" class="action-btn">
                        <i class="fas fa-boxes action-icon"></i>
                        <span class="action-label">Recepciones</span>
                    </a>
                    <a href="aprobaciones.php" class="action-btn">
                        <i class="fas fa-thumbs-up action-icon"></i>
                        <span class="action-label">Aprobaciones</span>
                    </a>
                    <a href="proveedores.php" class="action-btn">
                        <i class="fas fa-users action-icon"></i>
                        <span class="action-label">Proveedores</span>
                    </a>
                </div>
            </div>

            <!-- Avisos -->
            <div class="content-card">
                <div class="card-header-custom">
                    <div class="card-title" style="color: #f6c23e;">
                        <i class="fas fa-bell"></i>
                        Avisos Importantes
                    </div>
                </div>
                <div class="alert-item">
                    <div class="alert-icon-box"><i class="fas fa-exclamation-triangle"></i></div>
                    <div>
                        <strong>Solicitudes Urgentes</strong>
                        <p class="mb-0 text-muted small">Hay 3 solicitudes marcadas como urgentes esperando aprobación.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        loadDashboardStats();
    });

    function loadDashboardStats() {
        fetch('../../api/compras/reportes.php?action=dashboard')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update stats with nice animation or simple text
                    document.getElementById('stat-solicitudes').textContent = data.stats.solicitudes_pendientes;
                    document.getElementById('stat-monto').textContent = data.stats.compras_mes;
                    document.getElementById('stat-recepciones').textContent = data.stats.recepciones_pendientes;
                    // Assuming approbaciones stat is same as solicitudes for now or similar logic
                    document.getElementById('stat-aprobaciones').textContent = data.stats.solicitudes_pendientes;

                    renderChart(data.chart);
                }
            })
            .catch(error => console.error('Error cargando stats:', error));
    }

    function renderChart(chartData) {
        var ctx = document.getElementById("comprasChart");
        var myLineChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: "Monto Comprado (BOB)",
                    lineTension: 0.4, // Curvier lines like standard dashboard
                    backgroundColor: "rgba(78, 115, 223, 0.05)",
                    borderColor: "rgba(78, 115, 223, 1)",
                    pointRadius: 4,
                    pointBackgroundColor: "rgba(78, 115, 223, 1)",
                    pointBorderColor: "#fff",
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                    pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    data: chartData.data,
                }],
            },
            options: {
                maintainAspectRatio: false,
                layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
                scales: {
                    x: { grid: { display: false, drawBorder: false }, ticks: { maxTicksLimit: 7 } },
                    y: {
                        ticks: { maxTicksLimit: 5, padding: 10, callback: function (value) { return 'Bs ' + value.toLocaleString(); } },
                        grid: { color: "rgb(234, 236, 244)", drawBorder: false, borderDash: [2], zeroLineBorderDash: [2] }
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyColor: "#858796",
                        titleColor: "#6e707e",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        xPadding: 15,
                        yPadding: 15,
                        displayColors: false,
                        intersect: false,
                        mode: 'index',
                        caretPadding: 10,
                        callbacks: {
                            label: function (tooltipItem) {
                                return 'Total: Bs ' + tooltipItem.raw.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
</script>

<?php include '../../includes/footer.php'; ?>