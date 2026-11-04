<?php
// Settings view - data: $defaultLanguage
$pageTitle = t('settings');
require dirname(__DIR__) . '/partials/header.php';
$langLabels = [
    'bn' => t('bengali'),
    'en' => t('english'),
];
?>

<div class="flex flex-wrap items-center justify-between gap-2 mb-4 fade-in">
    <h4 class="mb-0"><i class="bi bi-gear mr-2"></i><?php echo t('settings'); ?></h4>
</div>

<div class="row fade-in">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white">
                <i class="bi bi-translate mr-1"></i> <?php echo t('language'); ?>
            </div>
            <div class="card-body">
                <form id="settingsForm">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label for="default_language" class="form-label fw-semibold">
                            <?php echo t('default_language'); ?>
                        </label>
                        <select class="form-select" id="default_language" name="language">
                            <?php foreach ($langLabels as $val => $label): ?>
                                <option value="<?php echo e($val); ?>" <?php echo $defaultLanguage === $val ? 'selected' : ''; ?>>
                                    <?php echo e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><?php echo t('default_language_hint'); ?></div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg mr-1"></i> <?php echo t('save'); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('settingsForm').addEventListener('submit', function (e) {
    e.preventDefault();
    submitModalForm('settingsForm', BASE_URL + 'ajax/settings_save.php');
});
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>