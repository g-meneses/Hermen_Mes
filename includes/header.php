<?php
if (!isLoggedIn()) {
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Dashboard'; ?> - MES Hermen</title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-industry"></i> MES Hermen</h3>
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <div class="sidebar-user">
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="user-info">
                    <h4><?php echo $_SESSION['user_name']; ?></h4>
                    <span class="user-role"><?php echo ucfirst($_SESSION['user_role']); ?></span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/dashboard.php" class="<?php echo ($currentPage ?? '') == 'dashboard' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
    
                    <li class="<?php echo ($currentPage == 'inventarios') ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/modules/inventarios/">
                            <i class="fas fa-warehouse"></i>
                            <span>Inventarios</span>
                        </a>
                    </li>
                    
                    <?php if (hasRole(['admin', 'coordinador', 'gerencia', 'tejedor'])): ?>
                    <li class="menu-section">
                        <span>TEJEDURÍA</span>
                    </li>
                    
                    <li>
                        <a href="<?php echo SITE_URL; ?>/modules/tejido/maquinas.php" class="<?php echo ($currentPage ?? '') == 'maquinas' ? 'active' : ''; ?>">
                            <i class="fas fa-cog"></i>
                            <span>Máquinas</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="<?php echo SITE_URL; ?>/modules/tejido/productos.php" class="<?php echo ($currentPage ?? '') == 'productos' ? 'active' : ''; ?>">
                            <i class="fas fa-box"></i>
                            <span>Productos Tejidos</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="<?php echo SITE_URL; ?>/modules/tejido/insumos.php" class="<?php echo ($currentPage ?? '') == 'insumos' ? 'active' : ''; ?>">
                            <i class="fas fa-boxes"></i>
                            <span>Hilos e Insumos</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="<?php echo SITE_URL; ?>/modules/tejido/plan_generico.php" class="<?php echo ($currentPage ?? '') == 'plan_generico' ? 'active' : ''; ?>">
                            <i class="fas fa-clipboard-list"></i>
                            <span>Plan Genérico</span>
                        </a>
                    </li>

                    <li class="<?php echo $currentPage === 'recetas' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/modules/tejido/recetas.php">
                            <i class="fas fa-flask"></i> Recetas (BOM)
                        </a>
                    </li>
                    
                    <li>
                        <a href="<?php echo SITE_URL; ?>/modules/tejido/produccion.php" class="<?php echo ($currentPage ?? '') == 'produccion' ? 'active' : ''; ?>">
                            <i class="fas fa-industry"></i>
                            <span>Registro Producción</span>
                        </a>
                    </li>
                    
                    <li class="<?php echo $currentPage == 'inventario' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>/modules/inventario/index.php">
                            <i class="fas fa-boxes"></i>
                            <span>Inventario Intermedio</span>
                        </a>
                    </li>
                    <li class="<?php echo $currentPage == 'revisado' ? 'active' : ''; ?>">
                            <a href="<?php echo SITE_URL; ?>/modules/revisado/index.php">
                                <i class="fas fa-check-double"></i>
                                <span>Revisado Crudo</span>
                            </a>
                     </li>

                     <li class="<?php echo $currentPage == 'vaporizado' ? 'active' : ''; ?>">
                            <a href="<?php echo SITE_URL; ?>/modules/vaporizado/index.php">
                                <i class="fas fa-wind"></i>
                                <span>Vaporizado</span>
                            </a>
                     </li>    


                    <?php endif; ?>
                    
                    <?php if (hasRole(['admin', 'coordinador', 'gerencia'])): ?>
                    <li class="menu-section">
                        <span>PLANIFICACIÓN</span>
                    </li>
                    
                    <li>
                        <a href="<?php echo SITE_URL; ?>/modules/tejido/plan_semanal.php" class="<?php echo ($currentPage ?? '') == 'plan_semanal' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-week"></i>
                            <span>Plan Semanal</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasRole(['admin'])): ?>
                    <li class="menu-section">
                        <span>ADMINISTRACIÓN</span>
                    </li>
                    
                    <li>
                        <a href="<?php echo SITE_URL; ?>/modules/admin/usuarios.php" class="<?php echo ($currentPage ?? '') == 'usuarios' ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i>
                            <span>Usuarios</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="toggle-btn mobile-toggle" id="mobileToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2><?php echo $pageTitle ?? 'Dashboard'; ?></h2>
                </div>
                
                <div class="header-right">
                    <div class="header-item">
                        <i class="fas fa-clock"></i>
                        <span id="currentDateTime"></span>
                    </div>
                    
                    <div class="header-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>La Paz, Bolivia</span>
                    </div>
                    
                    <div class="header-item dropdown">
                        <button class="btn-icon" id="userMenuBtn">
                            <i class="fas fa-user-circle"></i>
                        </button>
                        <div class="dropdown-menu" id="userMenu">
                            <a href="#"><i class="fas fa-user"></i> Mi Perfil</a>
                            <a href="#"><i class="fas fa-cog"></i> Configuración</a>
                            <hr>
                            <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Content Area -->
            <main class="content">
