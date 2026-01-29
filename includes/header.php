<?php
/**
 * Header con Menú Lateral Multi-nivel
 * ERP Hermen Ltda. v2.0.0
 */

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}

$currentPage = $currentPage ?? '';
$pageTitle = $pageTitle ?? 'ERP Hermen';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - ERP Hermen</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Estilos principales -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/main.css">

    <style>
        /* Variables CSS */
        :root {
            --sidebar-width: 260px;
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --dark-color: #2c3e50;
            --transition-speed: 0.3s;
        }

        /* Reset y Layout */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--dark-color) 0%, #34495e 100%);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            transition: all var(--transition-speed);
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }

        /* Logo/Header */
        .sidebar-header {
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-header .logo-icon {
            font-size: 24px;
            color: var(--primary-color);
        }

        .sidebar-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        /* Ajustes para el logo de Hermen Ltda. en el sidebar */
        .logo-container {
            padding: 15px 20px !important;
            /* Reducir espacios superior e inferior */
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo-container img {
            width: 60% !important;
            /* Reducir tamaño al 60% */
            height: auto !important;
            max-width: 180px;
            /* Límite máximo */
        }

        /* Si el logo está dentro de un link */
        .logo-container a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .sidebar-header p {
            margin: 2px 0 0 0;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Menú */
        .sidebar-menu {
            list-style: none;
            padding: 10px 0;
        }

        .menu-link {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            font-size: 13px;
        }

        .menu-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .menu-link.active {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .menu-link i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
            font-size: 14px;
        }

        .menu-link .menu-text {
            flex: 1;
        }

        .menu-link .menu-badge {
            background: #ffc107;
            color: #000;
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 8px;
            font-weight: 600;
            margin-left: 6px;
        }

        .menu-link .menu-arrow {
            margin-left: auto;
            transition: transform 0.3s;
            font-size: 10px;
        }

        .menu-link .menu-arrow.rotated {
            transform: rotate(90deg);
        }

        /* Submenús */
        .submenu {
            list-style: none;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: rgba(0, 0, 0, 0.15);
        }

        .submenu.open {
            max-height: 2000px;
        }

        .submenu .menu-link {
            padding-left: 46px;
            font-size: 12px;
        }

        .submenu .submenu .menu-link {
            padding-left: 66px;
        }

        .submenu .submenu .submenu .menu-link {
            padding-left: 86px;
        }

        /* Separador */
        .menu-separator {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 10px 16px;
        }

        /* Badges especiales */
        .badge-soon {
            background: rgba(255, 193, 7, 0.2) !important;
            color: #ffc107 !important;
            border: 1px solid #ffc107;
        }

        .badge-new {
            background: rgba(40, 167, 69, 0.2) !important;
            color: #28a745 !important;
            border: 1px solid #28a745;
        }

        /* Usuario */
        .sidebar-user {
            padding: 12px 16px;
            background: rgba(0, 0, 0, 0.2);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            position: sticky;
            bottom: 0;
        }

        .sidebar-user .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .sidebar-user .user-name {
            font-size: 13px;
            font-weight: 600;
            margin: 0;
        }

        .sidebar-user .user-role {
            font-size: 10px;
            color: rgba(255, 255, 255, 0.6);
            margin: 2px 0 0 0;
        }

        .sidebar-user .logout-btn {
            color: rgba(255, 255, 255, 0.7);
            transition: all 0.2s;
        }

        .sidebar-user .logout-btn:hover {
            color: #dc3545;
        }

        /* CONTENIDO PRINCIPAL */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-speed);
            min-height: 100vh;
        }

        /* Top Bar */
        .topbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .topbar h1 {
            margin: 0;
            font-size: 24px;
            color: var(--dark-color);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
            color: #666;
            font-size: 14px;
        }

        /* Page Container */
        .page-container {
            padding: 30px;
        }

        /* Ajuste para que el usuario siempre sea visible */
        .sidebar {
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            flex-shrink: 0;
        }

        .sidebar-menu {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar-user {
            flex-shrink: 0;
            position: static;
            /* Cambiar de sticky a static */
        }
    </style>
</head>

<body>
    <div class="layout">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <!-- Logo -->
            <div class="logo-container">
                <a href="<?php echo SITE_URL; ?>/modules/dashboard/index.php">
                    <img src="<?php echo SITE_URL; ?>/assets/img/logo_sidebar.png?v=9" alt="HerMen Ltda.">
                </a>
            </div>

            <!-- Menú -->
            <ul class="sidebar-menu">
                <!-- Dashboard -->
                <li class="menu-item">
                    <a href="<?php echo SITE_URL; ?>/modules/dashboard/index.php"
                        class="menu-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span class="menu-text">Dashboard</span>
                    </a>
                </li>

                <div class="menu-separator"></div>

                <!-- INVENTARIOS -->
                <li class="menu-item">
                    <a class="menu-link" onclick="toggleSubmenu(this)">
                        <i class="fas fa-boxes"></i>
                        <span class="menu-text">INVENTARIOS</span>
                        <i class="fas fa-chevron-right menu-arrow"></i>
                    </a>
                    <ul class="submenu">
                        <li class="menu-item">
                            <a href="<?php echo SITE_URL; ?>/modules/inventarios/index.php"
                                class="menu-link <?php echo $currentPage === 'centro_inventarios' ? 'active' : ''; ?>">
                                <i class="fas fa-warehouse"></i>
                                <span class="menu-text">Centro de Inventarios</span>
                            </a>
                        </li>
                        <li class="menu-item">
                            <a class="menu-link" onclick="toggleSubmenu(this)">
                                <i class="fas fa-list"></i>
                                <span class="menu-text">Gestión por Tipo</span>
                                <i class="fas fa-chevron-right menu-arrow"></i>
                            </a>
                            <ul class="submenu">
                                <li><a href="<?php echo SITE_URL; ?>/modules/inventarios/materias_primas.php"
                                        class="menu-link <?php echo $currentPage === 'materias_primas' ? 'active' : ''; ?>"><i
                                            class="fas fa-cubes"></i><span class="menu-text">Materia Prima</span></a>
                                </li>
                                <li><a href="<?php echo SITE_URL; ?>/modules/inventarios/colorantes_quimicos.php"
                                        class="menu-link <?php echo $currentPage === 'colorantes_quimicos' ? 'active' : ''; ?>"><i
                                            class="fas fa-palette"></i><span class="menu-text">Colorantes</span></a>
                                </li>
                                <li><a href="<?php echo SITE_URL; ?>/modules/inventarios/empaque.php"
                                        class="menu-link <?php echo $currentPage === 'empaque' ? 'active' : ''; ?>"><i
                                            class="fas fa-box"></i><span class="menu-text">Empaque</span></a>
                                </li>
                                <li><a href="<?php echo SITE_URL; ?>/modules/inventarios/accesorios.php"
                                        class="menu-link <?php echo $currentPage === 'accesorios' ? 'active' : ''; ?>"><i
                                            class="fas fa-shirt"></i><span class="menu-text">Accesorios</span></a>
                                </li>
                                <li><a href="<?php echo SITE_URL; ?>/modules/inventarios/repuestos.php"
                                        class="menu-link <?php echo $currentPage === 'repuestos' ? 'active' : ''; ?>"><i
                                            class="fas fa-wrench"></i><span class="menu-text">Repuestos</span></a>
                                </li>
                                <li><a href="#" class="menu-link"><i class="fas fa-sync-alt"></i><span
                                            class="menu-text">WIP</span><span
                                            class="menu-badge badge-soon">Pronto</span></a></li>
                                <li><a href="#" class="menu-link"><i class="fas fa-check-circle"></i><span
                                            class="menu-text">Prod. Terminados</span><span
                                            class="menu-badge badge-soon">Pronto</span></a></li>
                            </ul>
                        </li>
                        <li class="menu-item">
                            <a class="menu-link" onclick="toggleSubmenu(this)">
                                <i class="fas fa-chart-bar"></i>
                                <span class="menu-text">Reportes</span>
                                <i class="fas fa-chevron-right menu-arrow"></i>
                            </a>
                            <ul class="submenu">
                                <li><a href="javascript:void(0)" onclick="abrirReporte('consolidado')"
                                        class="menu-link">
                                        <i class="fas fa-file-invoice-dollar"></i><span class="menu-text">Reporte
                                            Consolidado</span></a>
                                </li>
                                <li><a href="javascript:void(0)" onclick="abrirReporte('stock_valorizado')"
                                        class="menu-link"><i class="fas fa-boxes"></i><span class="menu-text">Stock
                                            Valorizado</span></a></li>
                                <li><a href="javascript:void(0)" onclick="abrirReporte('movimientos')"
                                        class="menu-link"><i class="fas fa-exchange-alt"></i><span
                                            class="menu-text">Movimientos</span></a></li>
                                <li><a href="javascript:void(0)" onclick="abrirReporte('analisis')" class="menu-link"><i
                                            class="fas fa-chart-pie"></i><span class="menu-text">Análisis</span></a>
                                </li>
                                <li><a href="javascript:void(0)" onclick="abrirReporte('tipos_categorias')"
                                        class="menu-link"><i class="fas fa-sitemap"></i><span class="menu-text">Tipos y
                                            Categorías</span></a>
                                </li>
                            </ul>
                        </li>
                        <li class="menu-item">
                            <a href="<?php echo SITE_URL; ?>/modules/inventarios/proveedores.php"
                                class="menu-link <?php echo $currentPage === 'proveedores' ? 'active' : ''; ?>">
                                <i class="fas fa-truck"></i>
                                <span class="menu-text">Proveedores</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <div class="menu-separator"></div>

                <!-- PRODUCCIÓN -->
                <li class="menu-item">
                    <a class="menu-link" onclick="toggleSubmenu(this)">
                        <i class="fas fa-industry"></i>
                        <span class="menu-text">PRODUCCIÓN (MES)</span>
                        <i class="fas fa-chevron-right menu-arrow"></i>
                    </a>
                    <ul class="submenu">
                        <!-- Planificación -->
                        <li class="menu-item">
                            <a class="menu-link" onclick="toggleSubmenu(this)">
                                <i class="fas fa-calendar-alt"></i>
                                <span class="menu-text">PLANIFICACIÓN</span>
                                <i class="fas fa-chevron-right menu-arrow"></i>
                            </a>
                            <ul class="submenu">
                                <li><a href="#" class="menu-link"><i class="fas fa-project-diagram"></i><span
                                            class="menu-text">Config. Flujos</span><span
                                            class="menu-badge badge-new">Nuevo</span></a></li>
                                <li><a href="#" class="menu-link"><i class="fas fa-flask"></i><span
                                            class="menu-text">Recetas (BOM)</span><span
                                            class="menu-badge badge-soon">Pronto</span></a></li>
                                <li><a href="<?php echo SITE_URL; ?>/modules/tejido/plan_generico.php"
                                        class="menu-link <?php echo $currentPage === 'plan_generico' ? 'active' : ''; ?>"><i
                                            class="fas fa-clipboard-list"></i><span class="menu-text">Plan
                                            Genérico</span></a></li>
                                <li><a href="#" class="menu-link"><i class="fas fa-calendar-week"></i><span
                                            class="menu-text">Plan Semanal</span><span
                                            class="menu-badge badge-soon">Pronto</span></a></li>
                            </ul>
                        </li>

                        <!-- Ejecución -->
                        <li class="menu-item">
                            <a class="menu-link" onclick="toggleSubmenu(this)">
                                <i class="fas fa-tasks"></i>
                                <span class="menu-text">EJECUCIÓN</span>
                                <i class="fas fa-chevron-right menu-arrow"></i>
                            </a>
                            <ul class="submenu">
                                <!-- Poliamida -->
                                <li class="menu-item">
                                    <a class="menu-link" onclick="toggleSubmenu(this)">
                                        <i class="fas fa-socks"></i>
                                        <span class="menu-text">POLIAMIDA</span>
                                        <i class="fas fa-chevron-right menu-arrow"></i>
                                    </a>
                                    <ul class="submenu">
                                        <li><a href="#" class="menu-link"><i class="fas fa-clipboard"></i><span
                                                    class="menu-text">Órdenes</span><span
                                                    class="menu-badge badge-soon">Pronto</span></a></li>
                                        <li><a href="#" class="menu-link"><i class="fas fa-clock"></i><span
                                                    class="menu-text">Por Turno</span><span
                                                    class="menu-badge badge-soon">Pronto</span></a></li>
                                        <li><a href="#" class="menu-link"><i class="fas fa-route"></i><span
                                                    class="menu-text">Control</span><span
                                                    class="menu-badge badge-soon">Pronto</span></a></li>
                                        <li><a href="#" class="menu-link"><i class="fas fa-chart-line"></i><span
                                                    class="menu-text">Reportes</span><span
                                                    class="menu-badge badge-soon">Pronto</span></a></li>
                                    </ul>
                                </li>
                                <!-- Algodón -->
                                <li class="menu-item">
                                    <a class="menu-link" onclick="toggleSubmenu(this)">
                                        <i class="fas fa-tshirt"></i>
                                        <span class="menu-text">ALGODÓN</span>
                                        <i class="fas fa-chevron-right menu-arrow"></i>
                                    </a>
                                    <ul class="submenu">
                                        <li><a href="#" class="menu-link"><i class="fas fa-clipboard"></i><span
                                                    class="menu-text">Órdenes</span><span
                                                    class="menu-badge badge-soon">Pronto</span></a></li>
                                        <li><a href="#" class="menu-link"><i class="fas fa-clock"></i><span
                                                    class="menu-text">Por Turno</span><span
                                                    class="menu-badge badge-soon">Pronto</span></a></li>
                                    </ul>
                                </li>
                                <!-- Confección -->
                                <li class="menu-item">
                                    <a class="menu-link" onclick="toggleSubmenu(this)">
                                        <i class="fas fa-cut"></i>
                                        <span class="menu-text">CONFECCIÓN</span>
                                        <i class="fas fa-chevron-right menu-arrow"></i>
                                    </a>
                                    <ul class="submenu">
                                        <li><a href="#" class="menu-link"><i class="fas fa-clipboard"></i><span
                                                    class="menu-text">Órdenes</span><span
                                                    class="menu-badge badge-soon">Pronto</span></a></li>
                                    </ul>
                                </li>
                            </ul>
                        </li>

                        <!-- Recursos -->
                        <li class="menu-item">
                            <a class="menu-link" onclick="toggleSubmenu(this)">
                                <i class="fas fa-cogs"></i>
                                <span class="menu-text">RECURSOS</span>
                                <i class="fas fa-chevron-right menu-arrow"></i>
                            </a>
                            <ul class="submenu">
                                <li><a href="<?php echo SITE_URL; ?>/modules/tejido/maquinas.php"
                                        class="menu-link <?php echo $currentPage === 'maquinas' ? 'active' : ''; ?>"><i
                                            class="fas fa-cog"></i><span class="menu-text">Máquinas</span></a></li>
                                <li><a href="<?php echo SITE_URL; ?>/modules/tejido/productos.php"
                                        class="menu-link <?php echo $currentPage === 'productos' ? 'active' : ''; ?>"><i
                                            class="fas fa-box-open"></i><span class="menu-text">Productos</span></a>
                                </li>
                                <li><a href="<?php echo SITE_URL; ?>/modules/tejido/insumos.php"
                                        class="menu-link <?php echo $currentPage === 'insumos' ? 'active' : ''; ?>"><i
                                            class="fas fa-cube"></i><span class="menu-text">Insumos</span></a></li>
                                <li><a href="#" class="menu-link"><i class="fas fa-users"></i><span
                                            class="menu-text">Personal</span><span
                                            class="menu-badge badge-soon">Pronto</span></a></li>
                            </ul>
                        </li>
                    </ul>
                </li>

                <div class="menu-separator"></div>

                <!-- INVENTARIO INTERMEDIO (MES) -->
                <li class="menu-item">
                    <a href="<?php echo SITE_URL; ?>/modules/inventario_intermedio/index.php"
                        class="menu-link <?php echo $currentPage === 'inventario_intermedio' ? 'active' : ''; ?>">
                        <i class="fas fa-layer-group"></i>
                        <span class="menu-text">Inventario Intermedio</span>
                    </a>
                </li>

                <!-- VENTAS -->
                <li class="menu-item">
                    <a class="menu-link">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="menu-text">VENTAS</span>
                        <span class="menu-badge">Fase Futura</span>
                    </a>
                </li>

                <!-- COMPRAS -->
                <li class="menu-item">
                    <a class="menu-link">
                        <i class="fas fa-shopping-bag"></i>
                        <span class="menu-text">COMPRAS</span>
                        <span class="menu-badge">Fase Futura</span>
                    </a>
                </li>

                <div class="menu-separator"></div>

                <!-- ADMINISTRACIÓN -->
                <li class="menu-item">
                    <a class="menu-link" onclick="toggleSubmenu(this)">
                        <i class="fas fa-user-shield"></i>
                        <span class="menu-text">ADMINISTRACIÓN</span>
                        <i class="fas fa-chevron-right menu-arrow"></i>
                    </a>
                    <ul class="submenu">
                        <li><a href="#" class="menu-link"><i class="fas fa-users-cog"></i><span
                                    class="menu-text">Usuarios</span><span
                                    class="menu-badge badge-soon">Pronto</span></a></li>
                        <li><a href="#" class="menu-link"><i class="fas fa-cog"></i><span
                                    class="menu-text">Configuración</span><span
                                    class="menu-badge badge-soon">Pronto</span></a></li>
                    </ul>
                </li>
            </ul>

            <!-- Usuario -->
            <div class="sidebar-user">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-info">
                    <p class="user-name"><?php echo $_SESSION['nombre_completo'] ?? 'Usuario'; ?></p>
                    <p class="user-role"><?php echo ucfirst($_SESSION['user_role'] ?? 'usuario'); ?></p>
                </div>
                <a href="<?php echo SITE_URL; ?>/logout.php" class="logout-btn" title="Cerrar sesión">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </aside>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="topbar">
                <h1><?php echo $pageTitle; ?></h1>
                <div class="topbar-right">
                    <i class="fas fa-calendar"></i> <?php echo date('d/m/Y'); ?>
                </div>
            </div>

            <!-- Page Container -->
            <div class="page-container">
                <!-- El contenido de cada página va aquí -->