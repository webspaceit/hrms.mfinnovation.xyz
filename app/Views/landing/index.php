<?php
// Landing view - data: $availableUnits (array)
$currentLang = Lang::current();
$siteName = t('app_name');
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang === 'bn' ? 'bn' : 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($siteName); ?></title>
    <!-- Tailwind CSS (runtime CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo url('assets/css/style.css'); ?>" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="<?php echo url('assets/images/favicon.svg'); ?>">
    <style>
        .landing-hero {
            background:
                radial-gradient(900px 400px at 85% -10%, rgba(255,255,255,.14), transparent 60%),
                radial-gradient(700px 380px at -10% 110%, rgba(255,255,255,.10), transparent 60%),
                linear-gradient(135deg, #005a33 0%, #007c47 45%, #0d9488 100%);
        }
        .landing-card {
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .landing-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 40px -18px rgba(0, 92, 51, .45);
        }
        .feature-chip {
            transition: background .15s ease, border-color .15s ease;
        }
        .feature-chip:hover {
            background: #eefaf3;
            border-color: #a7dfc0;
        }
    </style>
</head>
<body class="bg-[#f4f7f5]">

    <!-- Top bar: brand + language switch -->
    <header class="py-4 px-5 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i class="bi bi-buildings text-3xl" style="color:#007c47;"></i>
            <span class="font-bold text-lg" style="color:#1d3a2b;"><?php echo e($siteName); ?></span>
        </div>
        <div class="flex items-center gap-2">
            <a href="javascript:void(0)" onclick="setLanguage('en')" class="btn btn-sm <?php echo $currentLang === 'en' ? 'btn-success' : 'btn-outline-secondary'; ?>"><?php echo t('english'); ?></a>
            <a href="javascript:void(0)" onclick="setLanguage('bn')" class="btn btn-sm <?php echo $currentLang === 'bn' ? 'btn-success' : 'btn-outline-secondary'; ?>"><?php echo t('bengali'); ?></a>
        </div>
    </header>

    <!-- Hero -->
    <main class="landing-hero text-white">
        <div class="max-w-5xl mx-auto px-4 py-16 md:py-20 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white/15 border border-white/25 mb-5">
                <i class="bi bi-buildings text-3xl"></i>
            </div>
            <h1 class="text-3xl md:text-5xl font-extrabold leading-tight mb-4"><?php echo e(t('landing_tagline')); ?></h1>
            <p class="text-base md:text-lg opacity-90 max-w-2xl mx-auto mb-10"><?php echo e(t('landing_subtitle')); ?></p>

            <!-- Login gates -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 max-w-2xl mx-auto text-left">
                <div class="landing-card bg-white rounded-2xl p-6 text-slate-800">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="bi bi-shield-lock text-2xl" style="color:#007c47;"></i>
                        <h2 class="text-lg font-bold mb-0"><?php echo e(t('landing_admin_login')); ?></h2>
                    </div>
                    <p class="text-sm text-muted mb-4"><?php echo e(t('landing_admin_desc')); ?></p>
                    <a href="<?php echo url('login'); ?>" class="btn btn-success btn-lg w-100">
                        <i class="bi bi-box-arrow-in-right mr-1"></i> <?php echo e(t('login')); ?>
                    </a>
                </div>
                <div class="landing-card bg-white rounded-2xl p-6 text-slate-800">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="bi bi-house-door text-2xl" style="color:#0d9488;"></i>
                        <h2 class="text-lg font-bold mb-0"><?php echo e(t('landing_tenant_login')); ?></h2>
                    </div>
                    <p class="text-sm text-muted mb-4"><?php echo e(t('landing_tenant_desc')); ?></p>
                    <a href="<?php echo url('tenant-login'); ?>" class="btn btn-outline-success btn-lg w-100">
                        <i class="bi bi-box-arrow-in-right mr-1"></i> <?php echo e(t('tenant_portal')); ?>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Available for rent -->
    <section class="max-w-5xl mx-auto px-4 py-12" id="available-units">
        <h2 class="text-center text-xl md:text-2xl font-bold mb-2" style="color:#1d3a2b;">
            <i class="bi bi-house-check mr-1" style="color:#007c47;"></i> <?php echo e(t('landing_available_units')); ?>
        </h2>
        <p class="text-center text-sm text-muted mb-6"><?php echo e(t('landing_available_units_sub')); ?></p>

        <?php if (empty($availableUnits)): ?>
            <div class="text-center bg-white rounded-2xl border border-slate-200 p-8">
                <i class="bi bi-calendar-x text-4xl mb-2" style="color:#94a3b8;"></i>
                <p class="text-muted mb-0"><?php echo e(t('landing_no_units')); ?></p>
            </div>
        <?php else: ?>
            <!-- Type filter -->
            <div class="flex justify-center flex-wrap gap-2 mb-6" id="unitFilters">
                <button type="button" class="unit-filter btn btn-sm btn-success" data-type="all"><?php echo e(t('landing_filter_all')); ?></button>
                <button type="button" class="unit-filter btn btn-sm btn-outline-success" data-type="flat"><?php echo e(t('landing_unit_flat')); ?></button>
                <button type="button" class="unit-filter btn btn-sm btn-outline-success" data-type="shop"><?php echo e(t('landing_unit_shop')); ?></button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="unitGrid">
                <?php foreach ($availableUnits as $u):
                    $isShop = ($u['unit_type'] === 'shop');
                    $buildingLabel = localizeText((string)($u['building_name'] ?? ''));
                    if ($buildingLabel === '') $buildingLabel = '-';
                    $detailItems = [];
                    if (!empty($u['floor'])) $detailItems[] = ['bi-stairs', e(t('floor') . ': ' . floorLabel($u['floor']))];
                    if (!$isShop && !empty($u['bedrooms'])) $detailItems[] = ['bi-door-open', e(t('bedrooms') . ': ' . bnNumeral((string)(int)$u['bedrooms']))];
                    if (!$isShop && !empty($u['bathrooms'])) $detailItems[] = ['bi-droplet', e(t('bathrooms') . ': ' . bnNumeral((string)(int)$u['bathrooms']))];
                    if (!empty($u['size_sqft'])) {
                        $sq = rtrim(rtrim(number_format((float)$u['size_sqft'], 2), '0'), '.');
                        $detailItems[] = ['bi-rulers', e(t('size_sqft') . ': ' . bnNumeral($sq))];
                    }
                ?>
                <div class="landing-card bg-white rounded-2xl border border-slate-200 p-5 unit-card" data-type="<?php echo $u['unit_type']; ?>">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <span class="text-lg font-bold" style="color:#1d3a2b;"><?php echo e(bnFlatCode($u['flat_no'])); ?></span>
                        <span class="badge <?php echo $isShop ? 'bg-info' : 'bg-success'; ?> text-white"><?php echo e($isShop ? t('landing_unit_shop') : t('landing_unit_flat')); ?></span>
                    </div>
                    <p class="text-sm text-muted mb-3 truncate">
                        <i class="bi bi-building mr-1"></i><?php echo e($buildingLabel); ?>
                    </p>
                    <?php if (!empty($detailItems)): ?>
                    <ul class="text-sm text-muted mb-3 flex flex-wrap gap-x-4 gap-y-1 p-0 m-0 list-none">
                        <?php foreach ($detailItems as $d): ?>
                        <li class="flex items-center gap-1"><i class="bi <?php echo $d[0]; ?>"></i><?php echo $d[1]; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <div class="border-t pt-3" style="border-color:#e2e8f0;">
                        <div class="text-xs text-muted"><?php echo e(t('landing_rent_label')); ?></div>
                        <div class="text-lg font-bold" style="color:#007c47;">
                            <?php echo money($u['rent_amount']); ?><span class="text-sm font-normal text-muted"> <?php echo e(t('landing_monthly')); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <script>
                (function () {
                    const filterButtons = document.querySelectorAll('#unitFilters .unit-filter');
                    const unitCards = document.querySelectorAll('#unitGrid .unit-card');
                    function applyFilter(type) {
                        filterButtons.forEach(btn => {
                            const active = btn.dataset.type === type;
                            btn.classList.toggle('btn-success', active);
                            btn.classList.toggle('btn-outline-success', !active);
                        });
                        unitCards.forEach(card => {
                            card.style.display = (type === 'all' || card.dataset.type === type) ? '' : 'none';
                        });
                    }
                    filterButtons.forEach(btn => {
                        btn.addEventListener('click', () => applyFilter(btn.dataset.type));
                    });
                })();
            </script>
        <?php endif; ?>
    </section>

    <!-- Features -->
    <section class="max-w-5xl mx-auto px-4 py-12">
        <h2 class="text-center text-xl md:text-2xl font-bold mb-6" style="color:#1d3a2b;">
            <i class="bi bi-stars mr-1" style="color:#007c47;"></i> <?php echo e(t('landing_features')); ?>
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            <?php
            $features = [
                ['bi-buildings', t('buildings')],
                ['bi-door-open', t('flats')],
                ['bi-shop', t('shops')],
                ['bi-people', t('tenants')],
                ['bi-file-earmark-text', t('leases')],
                ['bi-envelope-paper', t('invoices')],
                ['bi-cash-stack', t('payments')],
                ['bi-receipt', t('money_receipt')],
                ['bi-graph-up', t('reports')],
            ];
            foreach ($features as $f):
            ?>
            <div class="feature-chip flex items-center gap-3 bg-white rounded-xl border border-slate-200 p-4">
                <i class="bi <?php echo $f[0]; ?> text-xl" style="color:#007c47;"></i>
                <span class="font-semibold text-sm" style="color:#1d3a2b;"><?php echo e($f[1]); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="text-center text-sm text-muted py-6 border-t border-slate-200">
        &copy; <?php echo bnNumeral(date('Y')); ?> <?php echo e($siteName); ?>
    </footer>

    <script>
        const BASE_URL = '<?php echo BASE_URL; ?>';
        const CSRF_TOKEN = '<?php echo csrf_token(); ?>';
        function setLanguage(lang) {
            fetch(BASE_URL + 'ajax/lang.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'lang=' + encodeURIComponent(lang) + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN)
            }).then(res => res.json()).then(data => {
                if (!data.success) {
                    console.error('Language switch failed:', data.message || data);
                }
                reloadWithLang(lang);
            }).catch(err => {
                console.error('Language switch request failed:', err);
                reloadWithLang(lang);
            });
        }
        function reloadWithLang(lang) {
            const url = new URL(window.location.href);
            url.searchParams.set('lang', lang);
            window.location.href = url.toString();
        }
    </script>
</body>
</html>