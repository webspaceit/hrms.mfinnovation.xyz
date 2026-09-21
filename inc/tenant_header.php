<?php
// ============================================================
// Tenant Portal header template (used by tenant_*.php)
// Opens the same DOM structure as inc/header.php so that
// inc/footer.php can close it.
// ============================================================
$currentLang = Lang::current();
$langSuffix = '?lang=' . $currentLang;
$pageTitle = isset($pageTitle) ? $pageTitle . ' - ' . t('app_name') : t('app_name');
$portalTenant = (new Tenant())->find(Auth::tenantId());
$portalCurrent = basename($_SERVER['PHP_SELF']);
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
    <!-- Custom CSS -->
    <link href="<?php echo BASE_URL; ?>assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="<?php echo BASE_URL; ?>assets/images/favicon.svg">
</head>
<body class="bg-[#f4f7f5]">
<div class="flex" id="wrapper">
    <!-- Mobile sidebar backdrop -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="<?php echo BASE_URL; ?>tenant_dashboard.php<?php echo $langSuffix; ?>" class="flex items-center gap-2 text-white text-decoration-none">
                <i class="bi bi-house-door text-2xl"></i>
                <span class="sidebar-brand"><?php echo t('tenant_portal'); ?></span>
            </a>
            <button class="btn btn-link md:hidden text-white sidebar-close" onclick="toggleSidebar()" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <nav class="flex flex-col mt-3">
            <a href="<?php echo BASE_URL; ?>tenant_dashboard.php<?php echo $langSuffix; ?>" class="nav-link <?php echo $portalCurrent === 'tenant_dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> <span><?php echo t('my_home'); ?></span>
            </a>
            <a href="<?php echo BASE_URL; ?>tenant_payments.php<?php echo $langSuffix; ?>" class="nav-link <?php echo $portalCurrent === 'tenant_payments.php' ? 'active' : ''; ?>">
                <i class="bi bi-cash-stack"></i> <span><?php echo t('my_rent'); ?></span>
            </a>
            <a href="<?php echo BASE_URL; ?>tenant_dues.php<?php echo $langSuffix; ?>" class="nav-link <?php echo $portalCurrent === 'tenant_dues.php' ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-text"></i> <span><?php echo t('my_dues'); ?></span>
            </a>
            <a href="<?php echo BASE_URL; ?>tenant_profile.php<?php echo $langSuffix; ?>" class="nav-link <?php echo $portalCurrent === 'tenant_profile.php' ? 'active' : ''; ?>">
                <i class="bi bi-person-vcard"></i> <span><?php echo t('my_info'); ?></span>
            </a>
            <div class="sidebar-divider"></div>
            <a href="<?php echo BASE_URL; ?>tenant_logout.php<?php echo $langSuffix; ?>" class="nav-link text-danger">
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
            <span class="topbar-title"><?php echo e($pageTitle); ?></span>

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

                <!-- Tenant -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" onclick="toggleDropdown(this)">
                        <i class="bi bi-person-circle mr-1"></i> <span class="user-name"><?php echo e(localizeName(Auth::tenantName())); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-muted"><?php echo e($portalTenant['phone'] ?? ''); ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>tenant_profile.php<?php echo $langSuffix; ?>">
                            <i class="bi bi-person-vcard mr-1"></i> <?php echo t('my_info'); ?>
                        </a></li>
                        <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>tenant_logout.php<?php echo $langSuffix; ?>">
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
