<?php
// modules/compras/index.php
require_once '../../config/database.php';

if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}

$pageTitle = "Dashboard de Compras";
$currentPage = "dashboard"; // Para marcar activo en el menú
include '../../includes/header.php';
?>

<!-- Estilos para integrar el Dashboard Analítico dentro de la estructura original -->
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700&display=swap" rel="stylesheet" />
<link
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
    rel="stylesheet" />

<script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#4f46e5",
                    "background-light": "#f8fafc",
                    "background-dark": "#0f172a",
                    "card-light": "#ffffff",
                    "card-dark": "#1e293b",
                },
                fontFamily: {
                    sans: ["Inter", "sans-serif"],
                    display: ["Outfit", "sans-serif"],
                },
                borderRadius: {
                    DEFAULT: "0.75rem",
                },
            },
        },
    };
</script>

<style>
    /* Reset de algunos estilos de Bootstrap que pueden chocar con el diseño premium */
    .page-container {
        padding: 0 !important;
        /* El dashboard ya tiene su propio padding */
    }

    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }

    .progress-ring__circle {
        transition: stroke-dashoffset 0.35s;
        transform: rotate(-90deg);
        transform-origin: 50% 50%;
    }

    .scrollbar-hide::-webkit-scrollbar {
        display: none;
    }

    .scrollbar-hide {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    /* Ajuste para que Tailwind no rompa el estilo del sidebar original */
    aside,
    nav {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
    }

    /* Asegurar que el contenido respete el fondo del dashboard */
    .dashboard-wrapper {
        background-color: #f8fafc;
        min-height: calc(100vh - 60px);
        padding: 2rem;
    }

    .dark .dashboard-wrapper {
        background-color: #0f172a;
    }
</style>

<div class="dashboard-wrapper font-sans transition-colors duration-200">
    <header class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-display font-bold text-slate-800 dark:text-white">Dashboard de Compras Analítico
            </h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm">Vista general de operaciones de suministro e
                indicadores clave.</p>
        </div>
        <div class="flex items-center gap-3">
            <div
                class="hidden sm:flex items-center gap-2 bg-card-light dark:bg-card-dark px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-800 text-sm text-slate-500 dark:text-slate-400">
                <span class="material-symbols-outlined text-sm">calendar_today</span>
                <span><?php echo date('d M Y'); ?></span>
            </div>
            <a href="solicitudes.php"
                class="flex items-center gap-2 bg-primary hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg font-medium shadow-md shadow-primary/20 transition-all"
                style="text-decoration: none;">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Nueva Solicitud
            </a>
            <button class="p-2 text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-800 rounded-lg transition-all"
                id="theme-toggle">
                <span class="material-symbols-outlined">dark_mode</span>
            </button>
        </div>
    </header>

    <!-- KPI Cards -->
    <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Solicitudes Pendientes -->
        <div
            class="bg-card-light dark:bg-card-dark p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">
                    Solicitudes Pendientes</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-display font-bold text-slate-800 dark:text-white"
                        id="stat-solicitudes">--</span>
                    <span class="text-xs font-medium text-emerald-500 flex items-center" id="stat-sol-tendency">
                        <span class="material-symbols-outlined text-[14px]">trending_up</span> 0%
                    </span>
                </div>
            </div>
            <div class="relative w-14 h-14">
                <svg class="w-full h-full" viewBox="0 0 36 36">
                    <path class="text-slate-100 dark:text-slate-800 stroke-current"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none"
                        stroke-width="3"></path>
                    <path class="text-indigo-500 stroke-current progress-ring__circle" id="ring-solicitudes"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none"
                        stroke-dasharray="0, 100" stroke-linecap="round" stroke-width="3"></path>
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-indigo-500"
                    id="percent-solicitudes">0%</span>
            </div>
        </div>

        <!-- Por Aprobar -->
        <div
            class="bg-card-light dark:bg-card-dark p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Por
                    Aprobar</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-display font-bold text-slate-800 dark:text-white"
                        id="stat-aprobaciones">--</span>
                    <span class="text-xs font-medium text-amber-500 flex items-center">
                        <span class="material-symbols-outlined text-[14px]">priority_high</span>
                    </span>
                </div>
            </div>
            <div class="relative w-14 h-14">
                <svg class="w-full h-full" viewBox="0 0 36 36">
                    <path class="text-slate-100 dark:text-slate-800 stroke-current"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none"
                        stroke-width="3"></path>
                    <path class="text-amber-500 stroke-current progress-ring__circle" id="ring-aprobaciones"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none"
                        stroke-dasharray="0, 100" stroke-linecap="round" stroke-width="3"></path>
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-amber-500"
                    id="percent-aprobaciones">0%</span>
            </div>
        </div>

        <!-- Compras del Mes -->
        <div
            class="bg-card-light dark:bg-card-dark p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">
                    Compras del Mes</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-display font-bold text-slate-800 dark:text-white" id="stat-monto">Bs.
                        0</span>
                </div>
            </div>
            <div
                class="w-14 h-8 bg-emerald-100 dark:bg-emerald-900/30 rounded flex items-end justify-center gap-0.5 p-1">
                <div class="w-1.5 h-3 bg-emerald-500 rounded-t-sm"></div>
                <div class="w-1.5 h-4 bg-emerald-500 rounded-t-sm"></div>
                <div class="w-1.5 h-2 bg-emerald-500 rounded-t-sm"></div>
                <div class="w-1.5 h-5 bg-emerald-500 rounded-t-sm"></div>
                <div class="w-1.5 h-6 bg-emerald-500 rounded-t-sm"></div>
            </div>
        </div>

        <!-- Recepciones Pendientes -->
        <div
            class="bg-card-light dark:bg-card-dark p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">
                    Recepciones Pendientes</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-display font-bold text-slate-800 dark:text-white"
                        id="stat-recepciones">--</span>
                </div>
            </div>
            <div class="relative w-14 h-14">
                <svg class="w-full h-full" viewBox="0 0 36 36">
                    <path class="text-slate-100 dark:text-slate-800 stroke-current"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none"
                        stroke-width="3"></path>
                    <path class="text-rose-500 stroke-current progress-ring__circle" id="ring-recepciones"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none"
                        stroke-dasharray="0, 100" stroke-linecap="round" stroke-width="3"></path>
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-rose-500"
                    id="percent-recepciones">0%</span>
            </div>
        </div>
    </section>

    <!-- Shortcuts -->
    <section class="mb-8 overflow-x-auto pb-2 scrollbar-hide">
        <div class="flex items-center gap-4 min-w-max">
            <div class="flex items-center gap-2 text-slate-500 dark:text-slate-400 mr-2">
                <span class="material-symbols-outlined text-[18px]">bolt</span>
                <span class="text-xs font-bold uppercase tracking-widest">Atajos:</span>
            </div>
            <a href="ordenes.php"
                class="flex items-center gap-3 bg-card-light dark:bg-card-dark px-5 py-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-primary dark:hover:border-primary transition-all group"
                style="text-decoration: none; color: inherit;">
                <span
                    class="material-symbols-outlined text-primary group-hover:scale-110 transition-transform">receipt_long</span>
                <span class="text-sm font-medium">Órdenes</span>
            </a>
            <a href="recepciones.php"
                class="flex items-center gap-3 bg-card-light dark:bg-card-dark px-5 py-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-primary dark:hover:border-primary transition-all group"
                style="text-decoration: none; color: inherit;">
                <span
                    class="material-symbols-outlined text-primary group-hover:scale-110 transition-transform">package_2</span>
                <span class="text-sm font-medium">Recepciones</span>
            </a>
            <a href="aprobaciones.php"
                class="flex items-center gap-3 bg-card-light dark:bg-card-dark px-5 py-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-primary dark:hover:border-primary transition-all group"
                style="text-decoration: none; color: inherit;">
                <span
                    class="material-symbols-outlined text-primary group-hover:scale-110 transition-transform">fact_check</span>
                <span class="text-sm font-medium">Aprobaciones</span>
            </a>
            <a href="proveedores.php"
                class="flex items-center gap-3 bg-card-light dark:bg-card-dark px-5 py-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-primary dark:hover:border-primary transition-all group"
                style="text-decoration: none; color: inherit;">
                <span
                    class="material-symbols-outlined text-primary group-hover:scale-110 transition-transform">groups</span>
                <span class="text-sm font-medium">Proveedores</span>
            </a>
            <a href="../inventarios/materias_primas.php"
                class="flex items-center gap-3 bg-card-light dark:bg-card-dark px-5 py-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-primary dark:hover:border-primary transition-all group"
                style="text-decoration: none; color: inherit;">
                <span
                    class="material-symbols-outlined text-primary group-hover:scale-110 transition-transform">inventory</span>
                <span class="text-sm font-medium">Stock Telas</span>
            </a>
        </div>
    </section>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Chart Section -->
        <div
            class="lg:col-span-2 bg-card-light dark:bg-card-dark rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col">
            <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h3 class="font-display font-bold text-lg">Evolución de Compras</h3>
                    <p class="text-sm text-slate-500">Comparativa mensual de gastos</p>
                </div>
                <div class="flex gap-2">
                    <select
                        class="text-xs bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-800 rounded-lg outline-none focus:ring-1 focus:ring-primary">
                        <option>Últimos 6 meses</option>
                    </select>
                </div>
            </div>
            <div class="p-8 flex-1 flex flex-col justify-end">
                <div class="flex items-end justify-between gap-4 h-64 mb-4" id="main-chart-bars">
                    <!-- Bars inserted by JS -->
                </div>
                <div class="flex items-center gap-6 mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-indigo-500"></div>
                        <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Total Compras</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts Section -->
        <div
            class="bg-card-light dark:bg-card-dark rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col">
            <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <h3 class="font-display font-bold text-lg">Avisos Importantes</h3>
                <span
                    class="bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full"
                    id="alerts-count">-- Notif.</span>
            </div>
            <div class="p-4 flex flex-col gap-3" id="alerts-container">
                <!-- Loading state -->
                <div class="animate-pulse flex space-x-4 p-4 text-slate-400">
                    Cargando avisos...
                </div>
            </div>
            <div class="mt-auto p-4 text-center border-t border-slate-100 dark:border-slate-800">
                <button class="text-xs font-bold text-primary hover:underline">Ver todo el historial</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Theme Toggle Logic
    const themeToggle = document.getElementById('theme-toggle');
    const html = document.documentElement;

    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        html.classList.add('dark');
        themeToggle.querySelector('.material-symbols-outlined').innerText = 'light_mode';
    }

    themeToggle.addEventListener('click', () => {
        html.classList.toggle('dark');
        const isDark = html.classList.contains('dark');
        localStorage.theme = isDark ? 'dark' : 'light';
        themeToggle.querySelector('.material-symbols-outlined').innerText = isDark ? 'light_mode' : 'dark_mode';
    });

    // Dashboard Data Logic
    document.addEventListener('DOMContentLoaded', () => {
        fetchData();
    });

    async function fetchData() {
        try {
            const response = await fetch('../../api/compras/reportes.php?action=dashboard');
            const data = await response.json();

            if (data.success) {
                updateStats(data.stats);
                updateChart(data.chart);
                updateAlerts(data.stats);
            }
        } catch (error) {
            console.error('Error fetching dashboard data:', error);
        }
    }

    function updateStats(stats) {
        document.getElementById('stat-solicitudes').innerText = stats.solicitudes_pendientes;
        document.getElementById('stat-aprobaciones').innerText = stats.solicitudes_pendientes;
        document.getElementById('stat-monto').innerText = `Bs. ${stats.compras_mes}`;
        document.getElementById('stat-recepciones').innerText = stats.recepciones_pendientes;

        setRingProgress('ring-solicitudes', 'percent-solicitudes', 75);
        setRingProgress('ring-aprobaciones', 'percent-aprobaciones', 40);
        setRingProgress('ring-recepciones', 'percent-recepciones', 15);
    }

    function setRingProgress(ringId, textId, percent) {
        const ring = document.getElementById(ringId);
        const text = document.getElementById(textId);
        if (!ring) return;
        ring.setAttribute('stroke-dasharray', `${percent}, 100`);
        text.innerText = `${percent}%`;
    }

    function updateChart(chart) {
        const container = document.getElementById('main-chart-bars');
        container.innerHTML = '';
        if (!chart.data || chart.data.length === 0) return;
        const maxVal = Math.max(...chart.data, 1000);
        chart.data.forEach((val, idx) => {
            const height = (val / maxVal) * 90;
            const label = chart.labels[idx].split(' ')[0].substring(0, 3).toUpperCase();
            const isCurrent = idx === chart.data.length - 1;
            const seg1 = 30 + (Math.random() * 20);
            const seg2 = 20 + (Math.random() * 20);
            const bar = document.createElement('div');
            bar.className = 'flex flex-col items-center gap-2 flex-1 group h-full';
            bar.innerHTML = `
                <div class="w-full ${isCurrent ? 'bg-indigo-600' : 'bg-slate-100 dark:bg-slate-800'} rounded-t-lg relative flex flex-col justify-end overflow-hidden" style="height: ${height}%;">
                    <div class="${isCurrent ? 'bg-white/20' : 'bg-indigo-500/20'} w-full" style="height: ${seg1}%;"></div>
                    <div class="${isCurrent ? 'bg-white/10' : 'bg-indigo-500'} w-full" style="height: ${seg2}%;"></div>
                    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                        <span class="${isCurrent ? 'bg-white text-indigo-600' : 'bg-indigo-600 text-white'} text-[10px] px-1.5 py-0.5 rounded font-bold shadow-sm whitespace-nowrap">Bs. ${val.toLocaleString()}</span>
                    </div>
                </div>
                <span class="text-[10px] ${isCurrent ? 'font-bold text-primary' : 'font-medium text-slate-400'}">${label}</span>
            `;
            container.appendChild(bar);
        });
    }

    function updateAlerts(stats) {
        const container = document.getElementById('alerts-container');
        const countBadge = document.getElementById('alerts-count');
        container.innerHTML = '';
        const alerts = [];
        if (stats.solicitudes_pendientes > 0) {
            alerts.push({ title: 'Solicitudes Pendientes', desc: `Hay ${stats.solicitudes_pendientes} solicitudes esperando gestión.`, type: 'rose', icon: 'priority_high', action: 'Revisar ahora', link: 'solicitudes.php' });
        }
        if (stats.recepciones_pendientes > 0) {
            alerts.push({ title: 'Recepciones Pendientes', desc: `Existen ${stats.recepciones_pendientes} órdenes esperando ingreso.`, type: 'blue', icon: 'local_shipping', action: 'Ver órdenes', link: 'recepciones.php' });
        }
        if (alerts.length === 0) {
            alerts.push({ title: 'Operaciones al día', desc: 'No se detectaron retrasos críticos.', type: 'emerald', icon: 'check_circle', action: null });
        }
        countBadge.innerText = `${alerts.length} Notific.`;
        alerts.forEach(alert => {
            const div = document.createElement('div');
            div.className = `p-4 rounded-xl bg-${alert.type}-50 dark:bg-${alert.type}-900/20 border border-${alert.type}-100 dark:border-${alert.type}-900/30 flex gap-4 transition-all hover:shadow-md`;
            div.innerHTML = `
                <div class="w-10 h-10 rounded-full bg-${alert.type}-500 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-white text-[20px]">${alert.icon}</span>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-${alert.type}-900 dark:text-${alert.type}-100">${alert.title}</h4>
                    <p class="text-xs text-${alert.type}-700 dark:text-${alert.type}-300 mt-1">${alert.desc}</p>
                    ${alert.action ? `<a href="${alert.link}" class="mt-2 inline-block text-xs font-bold text-${alert.type}-600 dark:text-${alert.type}-400 underline">${alert.action}</a>` : ''}
                </div>
            `;
            container.appendChild(div);
        });
    }
</script>

<?php include '../../includes/footer.php'; ?>