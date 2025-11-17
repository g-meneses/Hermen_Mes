<?php
require_once 'config/database.php';

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

require_once 'includes/header.php';
?>

<div class="dashboard-stats">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
            <i class="fas fa-cog"></i>
        </div>
        <div class="stat-content">
            <h3>Máquinas Operativas</h3>
            <p class="stat-number" id="maquinasOperativas">0</p>
            <small>de 60 máquinas totales</small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
            <i class="fas fa-industry"></i>
        </div>
        <div class="stat-content">
            <h3>Producción Hoy</h3>
            <p class="stat-number" id="produccionHoy">0</p>
            <small>docenas producidas</small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #ed8936, #dd6b20);">
            <i class="fas fa-warehouse"></i>
        </div>
        <div class="stat-content">
            <h3>Inventario Intermedio</h3>
            <p class="stat-number" id="inventarioIntermedio">0</p>
            <small>docenas en stock</small>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #4299e1, #3182ce);">
            <i class="fas fa-calendar-week"></i>
        </div>
        <div class="stat-content">
            <h3>Plan Semanal</h3>
            <p class="stat-number" id="planSemanal">--</p>
            <small>semana actual</small>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> Producción Últimos 7 Días</h3>
        </div>
        <div class="chart-container">
            <canvas id="chartProduccion"></canvas>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clipboard-list"></i> Estado de Máquinas</h3>
        </div>
        <div class="chart-container">
            <canvas id="chartMaquinas"></canvas>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-exclamation-triangle"></i> Alertas y Notificaciones</h3>
    </div>
    <div id="alertasContainer">
        <p class="text-muted">No hay alertas en este momento</p>
    </div>
</div>

<style>
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
}

.stat-content h3 {
    font-size: 14px;
    color: #718096;
    margin-bottom: 8px;
}

.stat-number {
    font-size: 32px;
    font-weight: 700;
    color: #2d3748;
    margin: 5px 0;
}

.stat-content small {
    font-size: 12px;
    color: #a0aec0;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

.text-muted {
    color: #a0aec0;
    text-align: center;
    padding: 20px;
}

@media (max-width: 768px) {
    .dashboard-stats {
        grid-template-columns: 1fr;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', async function() {
    // Cargar estadísticas
    await loadStats();
    
    // Inicializar gráficos
    initCharts();
});

async function loadStats() {
    try {
        const response = await fetch('api/dashboard-stats.php');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('maquinasOperativas').textContent = data.maquinas_operativas || 0;
            document.getElementById('produccionHoy').textContent = data.produccion_hoy || 0;
            document.getElementById('inventarioIntermedio').textContent = data.inventario_intermedio || 0;
            document.getElementById('planSemanal').textContent = data.plan_semanal || 'Sin plan';
        }
    } catch (error) {
        console.error('Error al cargar estadísticas:', error);
    }
}

function initCharts() {
    // Gráfico de producción
    const ctxProduccion = document.getElementById('chartProduccion').getContext('2d');
    new Chart(ctxProduccion, {
        type: 'line',
        data: {
            labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
            datasets: [{
                label: 'Docenas Producidas',
                data: [120, 150, 180, 170, 200, 160, 90],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value + ' doc';
                        }
                    }
                }
            }
        }
    });
    
    // Gráfico de máquinas
    const ctxMaquinas = document.getElementById('chartMaquinas').getContext('2d');
    new Chart(ctxMaquinas, {
        type: 'doughnut',
        data: {
            labels: ['Operativas', 'Mantenimiento', 'Inactivas', 'Sin Asignación'],
            datasets: [{
                data: [45, 5, 5, 5],
                backgroundColor: ['#48bb78', '#ed8936', '#f56565', '#718096']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
