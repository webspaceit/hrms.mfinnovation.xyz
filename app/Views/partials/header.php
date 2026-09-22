<?php
// ============================================================
// Shared admin header partial (used by MVC views AND legacy pages
// through inc/header.php). Nav links use routes (url()).
// ============================================================
$currentLang = Lang::current();
$langSuffix = '?lang=' . $currentLang;
$pageTitle = isset($pageTitle) ? $pageTitle . ' - ' . t('app_name') : t('app_name');
$user = Auth::user();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang === 'bn' ? 'bn' : 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>
    <!-- Tailwind CSS (runtime CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- Flatpickr (date picker) CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Custom CSS -->
    <link href="<?php echo url('assets/css/style.css'); ?>" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="<?php echo url('assets/images/favicon.svg'); ?>">
</head>
<body class="bg-[#f4f7f5]">
<div class="flex" id="wrapper">
    <!-- Mobile sidebar backdrop -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="<?php echo url('dashboard') . $langSuffix; ?>" class="flex items-center gap-2 text-white text-decoration-none">
                <i class="bi bi-buildings text-2xl"></i>
                <span class="sidebar-brand"><?php echo t('app_name'); ?></span>
            </a>
            <button class="btn btn-link md:hidden text-white sidebar-close" onclick="toggleSidebar()" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <nav class="flex flex-col mt-3">
            <a href="<?php echo url('dashboard') . $langSuffix; ?>" class="nav-link <?php echo routeIs('dashboard') ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> <span><?php echo t('dashboard'); ?></span>
            </a>
            <a href="<?php echo url('buildings') . $langSuffix; ?>" class="nav-link <?php echo routeIs('buildings') ? 'active' : ''; ?>">
                <i class="bi bi-buildings"></i> <span><?php echo t('buildings'); ?></span>
            </a>
            <a href="<?php echo url('flats') . $langSuffix; ?>" class="nav-link <?php echo routeIs('flats') ? 'active' : ''; ?>">
                <i class="bi bi-door-open"></i> <span><?php echo t('Flats or Shops'); ?></span>
            </a>
            <a href="<?php echo url('tenants') . $langSuffix; ?>" class="nav-link <?php echo routeIs('tenants') ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> <span><?php echo t('tenants'); ?></span>
            </a>
            <a href="<?php echo url('leases') . $langSuffix; ?>" class="nav-link <?php echo routeIs('leases') ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-text"></i> <span><?php echo t('leases'); ?></span>
            </a>
            <a href="<?php echo url('payments') . $langSuffix; ?>" class="nav-link <?php echo routeIs('payments') ? 'active' : ''; ?>">
                <i class="bi bi-cash-stack"></i> <span><?php echo t('payments'); ?></span>
            </a>
            <a href="<?php echo url('invoices') . $langSuffix; ?>" class="nav-link <?php echo routeIs('invoices') ? 'active' : ''; ?>">
                <i class="bi bi-envelope-paper"></i> <span><?php echo t('invoices'); ?></span>
            </a>
            <a href="<?php echo url('expenses') . $langSuffix; ?>" class="nav-link <?php echo routeIs('expenses') ? 'active' : ''; ?>">
                <i class="bi bi-receipt"></i> <span><?php echo t('expenses'); ?></span>
            </a>
            <a href="<?php echo url('reports') . $langSuffix; ?>" class="nav-link <?php echo routeIs('reports') ? 'active' : ''; ?>">
                <i class="bi bi-graph-up"></i> <span><?php echo t('reports'); ?></span>
            </a>
            <div class="sidebar-divider"></div>
            <a href="<?php echo url('logout') . $langSuffix; ?>" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i> <span><?php echo t('logout'); ?></span>
            </a>
        </nav>
    </div>

    <!-- Page Content Wrapper -->
    <div id="page-content-wrapper" class="flex-1 min-w-0">
        <!-- Top Navbar -->
        <nav class="topbar">
            <button class="btn btn-outline-secondary md:hidden" onclick="toggleSidebar()" aria-label="Menu">
                <i class="bi bi-list"></i>
            </button>
            <span class="topbar-title"><?php echo isset($pageTitle) ? e($pageTitle) : t('app_name'); ?></span>

            <div class="flex items-center gap-2 ml-auto">
                <!-- Language Switcher -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" onclick="toggleDropdown(this)">
                        <i class="bi bi-translate"></i> <?php echo $currentLang === 'bn' ? 'বাংলা' : 'EN'; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item <?php echo $currentLang === 'en' ? 'active' : ''; ?>" href="javascript:void(0)" onclick="setLanguage('en')">
                            <i class="bi bi-check-circle mr-1"></i> <?php echo t('english'); ?>
                        </a></li>
                        <li><a class="dropdown-item <?php echo $currentLang === 'bn' ? 'active' : ''; ?>" href="javascript:void(0)" onclick="setLanguage('bn')">
                            <i class="bi bi-check-circle mr-1"></i> <?php echo t('bengali'); ?>
                        </a></li>
                    </ul>
                </div>

                <!-- User -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" onclick="toggleDropdown(this)">
                        <i class="bi bi-person-circle mr-1"></i> <span class="user-name"><?php echo e($user['full_name']); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-muted"><?php echo e($user['email']); ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?php echo url('logout') . $langSuffix; ?>">
                            <i class="bi bi-box-arrow-right mr-1"></i> <?php echo t('logout'); ?>
                        </a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <?php
            // Flash messages
            foreach (['success', 'danger', 'warning', 'info'] as $type) {
                if (Session::hasFlash($type)) {
                    $msg = Session::getFlash($type);
                    if (is_array($msg)) {
                        $msg = $msg['message'] ?? '';
                    }
                    if ($msg) {
                        echo '<div class="alert alert-' . $type . ' alert-dismissible" role="alert">'
                           . e($msg)
                           . '<button type="button" class="btn-close" onclick="this.parentElement.remove()"></button></div>';
                    }
                }
            }
            ?>