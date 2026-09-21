<?php
// Tenant portal login view - data: $error (string)
$currentLang = Lang::current();
$langSuffix = '?lang=' . $currentLang;
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang === 'bn' ? 'bn' : 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('tenant_login'); ?> - <?php echo t('app_name'); ?></title>
    <!-- Tailwind CSS (runtime CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="<?php echo BASE_URL; ?>assets/images/favicon.svg">
</head>
<body>
<div class="auth-wrapper">
    <div class="card auth-card">
        <div class="card-body p-4 md:p-8">
            <div class="auth-logo">
                <i class="bi bi-house-door"></i>
                <h4 class="fw-bold mt-2"><?php echo t('tenant_portal'); ?></h4>
                <p class="text-muted"><?php echo t('tenant_login_hint'); ?></p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-person mr-1"></i><?php echo t('login_id'); ?></label>
                    <input type="text" name="identifier" class="form-control" required autofocus autocomplete="username">
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-lock mr-1"></i><?php echo t('password'); ?></label>
                    <input type="password" name="password" class="form-control" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-auth-primary">
                    <i class="bi bi-box-arrow-in-right mr-1"></i> <?php echo t('login'); ?>
                </button>
            </form>

            <hr class="border-top my-4">

            <div class="flex justify-center gap-2">
                <a href="javascript:void(0)" onclick="setLanguage('en')" class="btn btn-sm <?php echo $currentLang === 'en' ? 'btn-success' : 'btn-outline-secondary'; ?>"><?php echo t('english'); ?></a>
                <a href="javascript:void(0)" onclick="setLanguage('bn')" class="btn btn-sm <?php echo $currentLang === 'bn' ? 'btn-success' : 'btn-outline-secondary'; ?>"><?php echo t('bengali'); ?></a>
            </div>

            <div class="text-center mt-3">
                <a href="<?php echo url('login') . $langSuffix; ?>" class="small text-muted">
                    <i class="bi bi-shield-lock mr-1"></i><?php echo t('admin_login'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

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