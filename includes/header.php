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

    <script>
        // Configuración global
        window.baseUrl = "<?php echo SITE_URL; ?>";
    </script>

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

        /* ESTADO COLAPSADO */
        .layout.collapsed .sidebar {
            width: 70px;
        }

        .layout.collapsed .sidebar .menu-text,
        .layout.collapsed .sidebar .menu-arrow,
        .layout.collapsed .sidebar .menu-badge,
        .layout.collapsed .sidebar .user-info,
        .layout.collapsed .sidebar .sidebar-user .user-avatar+div,
        .layout.collapsed .sidebar .logo-container img {
            display: none !important;
        }

        .layout.collapsed .sidebar .logo-container {
            padding: 15px 5px !important;
        }

        .layout.collapsed .sidebar .menu-link {
            justify-content: center;
            padding: 12px 0;
        }

        .layout.collapsed .sidebar .menu-link i {
            margin-right: 0;
            font-size: 18px;
        }

        .layout.collapsed .main-content {
            margin-left: 70px;
        }

        .layout.collapsed .sidebar .submenu {
            display: none !important;
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

        /* =====================================================
           NOTIFICACIONES
           ===================================================== */
        .notification-wrapper {
            position: relative;
        }

        .notification-btn {
            background: none;
            border: none;
            font-size: 20px;
            color: #666;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.2s;
            position: relative;
        }

        .notification-btn:hover {
            background: #f0f0f0;
            color: var(--primary-color);
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #dc3545;
            color: white;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4);
            }

            70% {
                box-shadow: 0 0 0 8px rgba(220, 53, 69, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 360px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            display: none;
            z-index: 1000;
            overflow: hidden;
            margin-top: 10px;
        }

        .notification-dropdown.show {
            display: block;
            animation: slideDown 0.2s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #eee;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .notification-header h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .notification-header a {
            color: rgba(255, 255, 255, 0.8);
            font-size: 12px;
            text-decoration: none;
        }

        .notification-header a:hover {
            color: white;
        }

        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }

        .notification-item {
            display: flex;
            gap: 12px;
            padding: 14px 20px;
            border-bottom: 1px solid #f5f5f5;
            cursor: pointer;
            transition: background 0.2s;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-item.unread {
            background: #f0f7ff;
        }

        .notification-item.unread:hover {
            background: #e6f0fa;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 16px;
        }

        .notification-icon.alerta {
            background: rgba(246, 173, 85, 0.2);
            color: #f6ad55;
        }

        .notification-icon.error {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        .notification-icon.info {
            background: rgba(102, 126, 234, 0.2);
            color: var(--primary-color);
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-content h5 {
            margin: 0 0 4px 0;
            font-size: 13px;
            font-weight: 600;
            color: #333;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .notification-content p {
            margin: 0;
            font-size: 12px;
            color: #666;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .notification-time {
            font-size: 11px;
            color: #999;
            margin-top: 4px;
        }

        .notification-empty {
            padding: 40px 20px;
            text-align: center;
            color: #999;
        }

        .notification-empty i {
            font-size: 40px;
            margin-bottom: 10px;
            opacity: 0.3;
        }

        .notification-empty p {
            margin: 0;
        }

        .notification-footer {
            padding: 12px 20px;
            text-align: center;
            border-top: 1px solid #eee;
            background: #fafafa;
        }

        .notification-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
        }

        .notification-footer a:hover {
            text-decoration: underline;
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
            <?php
            // Determinar si es un usuario con rol restringido de inventario
            $esOperadorInventario = ($_SESSION['user_role'] ?? '') === 'operador_inv';
            ?>
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
                        <?php if (!$esOperadorInventario): ?>
                            <li class="menu-item">
                                <a href="<?php echo SITE_URL; ?>/modules/inventarios/index.php"
                                    class="menu-link <?php echo $currentPage === 'centro_inventarios' ? 'active' : ''; ?>">
                                    <i class="fas fa-warehouse"></i>
                                    <span class="menu-text">Centro de Inventarios</span>
                                </a>
                            </li>
                        <?php endif; ?>
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
                                <?php if (!$esOperadorInventario): ?>
                                    <li><a href="javascript:void(0)" onclick="abrirReporte('consolidado')"
                                            class="menu-link">
                                            <i class="fas fa-file-invoice-dollar"></i><span class="menu-text">Reporte
                                                Consolidado</span></a>
                                    </li>
                                    <li><a href="javascript:void(0)" onclick="abrirReporte('stock_valorizado')"
                                            class="menu-link"><i class="fas fa-boxes"></i><span class="menu-text">Stock
                                                Valorizado</span></a></li>
                                <?php endif; ?>
                                <li><a href="javascript:void(0)" onclick="abrirReporte('movimientos')"
                                        class="menu-link"><i class="fas fa-exchange-alt"></i><span
                                            class="menu-text">Movimientos</span></a></li>
                                <?php if (!$esOperadorInventario): ?>
                                    <li><a href="javascript:void(0)" onclick="abrirReporte('analisis')" class="menu-link"><i
                                                class="fas fa-chart-pie"></i><span class="menu-text">Análisis</span></a>
                                    </li>
                                    <li><a href="javascript:void(0)" onclick="abrirReporte('tipos_categorias')"
                                            class="menu-link"><i class="fas fa-sitemap"></i><span class="menu-text">Tipos y
                                                Categorías</span></a>
                                    </li>
                                    <li><a href="<?php echo SITE_URL; ?>/modules/inventarios/reporte_rotacion.php"
                                            class="menu-link"><i class="fas fa-sync-alt"></i><span
                                                class="menu-text">Rotación de Inventario</span></a>
                                    </li>
                                <?php endif; ?>
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

                <?php if (!$esOperadorInventario): ?>
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
                    <!-- COMPRAS -->
                    <li class="menu-item">
                        <a class="menu-link" onclick="toggleSubmenu(this)">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="menu-text">COMPRAS</span>
                            <i class="fas fa-chevron-right menu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            <li class="menu-item">
                                <a href="<?php echo SITE_URL; ?>/modules/compras/index.php" class="menu-link">
                                    <i class="fas fa-chart-line"></i>
                                    <span class="menu-text">Dashboard</span>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="<?php echo SITE_URL; ?>/modules/compras/solicitudes.php" class="menu-link">
                                    <i class="fas fa-file-alt"></i>
                                    <span class="menu-text">Solicitudes</span>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="<?php echo SITE_URL; ?>/modules/compras/aprobaciones.php" class="menu-link">
                                    <i class="fas fa-check-double"></i>
                                    <span class="menu-text">Aprobaciones</span>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="<?php echo SITE_URL; ?>/modules/compras/ordenes.php" class="menu-link">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    <span class="menu-text">Órdenes de Compra</span>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="<?php echo SITE_URL; ?>/modules/compras/internaciones.php" class="menu-link">
                                    <i class="fas fa-ship"></i>
                                    <span class="menu-text">Internaciones</span>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="<?php echo SITE_URL; ?>/modules/compras/recepciones.php" class="menu-link">
                                    <i class="fas fa-truck-loading"></i>
                                    <span class="menu-text">Recepciones</span>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="<?php echo SITE_URL; ?>/modules/compras/proveedores.php" class="menu-link">
                                    <i class="fas fa-handshake"></i>
                                    <span class="menu-text">Proveedores</span>
                                </a>
                            </li>
                        </ul>
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
                <?php endif; ?>
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
                <div class="header-left d-flex align-items-center">
                    <button id="sidebarToggle" class="btn btn-link text-dark mr-3" style="font-size: 20px; padding: 0;">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="mb-0" style="font-size: 24px; color: var(--dark-color);"><?php echo $pageTitle; ?></h1>
                </div>
                <div class="topbar-right">
                    <!-- Notificaciones -->
                    <div class="notification-wrapper">
                        <button class="notification-btn" id="notificationBtn" title="Notificaciones">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                        </button>
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">
                                <h4>Notificaciones</h4>
                                <a href="#" id="markAllRead">Marcar leídas</a>
                            </div>
                            <div class="notification-list" id="notificationList">
                                <div class="notification-empty">
                                    <i class="fas fa-bell-slash"></i>
                                    <p>No hay notificaciones</p>
                                </div>
                            </div>
                            <div class="notification-footer">
                                <a href="<?php echo SITE_URL; ?>/modules/notificaciones/index.php">Ver todas</a>
                            </div>
                        </div>
                    </div>

                    <i class="fas fa-calendar"></i> <?php echo date('d/m/Y'); ?>
                </div>
            </div>

            <!-- Page Container -->
            <div class="page-container">
                <!-- El contenido de cada página va aquí -->