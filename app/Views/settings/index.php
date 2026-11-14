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
    <div class="col-lg-6 col-xl-5">
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
                        <select class="form-select settings-language-select" id="default_language" name="language">
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

    <div class="col-lg-6 col-xl-7">
        <div class="card">
            <div class="card-header bg-white">
                <i class="bi bi-bell mr-1"></i> <?php echo t('reminders'); ?>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3"><?php echo t('reminders_hint'); ?></p>
                <form id="reminderForm">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="reminder_enabled"><?php echo t('reminders_enabled'); ?></label>
                        <select class="form-select" id="reminder_enabled" name="reminder_enabled">
                            <option value="1"><?php echo t('yes'); ?></option>
                            <option value="0"><?php echo t('no'); ?></option>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 col-md-4 mb-3">
                            <label class="form-label" for="reminder_days_before"><?php echo t('reminder_days_before'); ?></label>
                            <input type="number" min="0" max="30" class="form-control" id="reminder_days_before" name="reminder_days_before">
                        </div>
                        <div class="col-6 col-md-4 mb-3">
                            <label class="form-label" for="reminder_repeat_days"><?php echo t('reminder_repeat_days'); ?></label>
                            <input type="number" min="0" max="60" class="form-control" id="reminder_repeat_days" name="reminder_repeat_days">
                        </div>
                        <div class="col-6 col-md-4 mb-3">
                            <label class="form-label" for="reminder_max"><?php echo t('reminder_max'); ?></label>
                            <input type="number" min="1" max="10" class="form-control" id="reminder_max" name="reminder_max">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="reminder_channels"><?php echo t('reminder_channels'); ?></label>
                        <input type="text" class="form-control" id="reminder_channels" name="reminder_channels" placeholder="push,whatsapp,email,sms">
                        <div class="form-text"><?php echo t('reminder_channels_hint'); ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="reminder_cron_token"><?php echo t('reminder_cron_token'); ?></label>
                        <input type="text" class="form-control" id="reminder_cron_token" name="reminder_cron_token" autocomplete="off">
                        <div class="form-text"><?php echo t('reminder_cron_hint'); ?></div>
                    </div>
                    <div class="alert alert-light border small mb-3" id="reminderSchedule" data-label="<?php echo e(t('reminder_schedule_preview')); ?>"></div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg mr-1"></i> <?php echo t('save'); ?>
                    </button>
                </form>

                <hr class="my-4">

                <h6 class="fw-semibold mb-2"><i class="bi bi-phone-vibrate mr-1"></i><?php echo t('push_settings'); ?></h6>
                <p class="text-muted small mb-2"><?php echo t('push_keys_hint'); ?></p>
                <div class="mb-3">
                    <label class="form-label" for="push_subject"><?php echo t('push_subject'); ?></label>
                    <input type="text" class="form-control" id="push_subject" name="push_subject" placeholder="mailto:admin@example.com" autocomplete="off">
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="generateKeys">
                        <i class="bi bi-key mr-1"></i><?php echo t('generate_keys'); ?>
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="testPush">
                        <i class="bi bi-send mr-1"></i><?php echo t('push_test'); ?>
                    </button>
                </div>
                <div class="form-text mt-2" id="pushState"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('settingsForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var lang = document.getElementById('default_language').value;
    submitModalForm('settingsForm', BASE_URL + 'ajax/settings_save.php', function () {
        // The default only applies to browsers that have not picked a language
        // themselves, so the admin who just saved it would otherwise still see
        // their own older choice (session/cookie rms_lang) and think the save
        // did nothing. Switch this browser too, like the topbar's
        // "set as site default" action already does (setDefaultLanguage()).
        setTimeout(function () { setLanguage(lang); }, 700);
    });
});
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>

<script>
// ---- Rent reminders + Web Push settings ----
(function () {
    var endpoint = BASE_URL + 'ajax/reminder_settings.php';
    var form = document.getElementById('reminderForm');
    var scheduleBox = document.getElementById('reminderSchedule');
    var pushState = document.getElementById('pushState');
    var csrf = form.querySelector('input[name="csrf_token"]').value;

    function fill(d) {
        if (!d) return;
        document.getElementById('reminder_enabled').value = d.reminder_enabled === '1' ? '1' : '0';
        document.getElementById('reminder_days_before').value = d.reminder_days_before;
        document.getElementById('reminder_repeat_days').value = d.reminder_repeat_days;
        document.getElementById('reminder_max').value = d.reminder_max;
        document.getElementById('reminder_channels').value = d.reminder_channels;
        document.getElementById('reminder_cron_token').value = d.reminder_cron_token;
        document.getElementById('push_subject').value = d.push_subject || '';
        scheduleBox.textContent = d.schedule
            ? (scheduleBox.dataset.label || '%s').replace('%s', d.schedule)
            : '';
        pushState.innerHTML = (d.has_keys ? '&#10003; ' : '&#9888; ') + (d.last_run ? 'last run: ' + d.last_run : '');
    }

    fetch(endpoint, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) { fill(res.data); })
        .catch(function () {});

    function post(fields, onDone) {
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
        fetch(endpoint, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); })
            .then(onDone)
            .catch(function () { showToast('Request failed', 'danger'); });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var fields = { action: 'save' };
        ['reminder_enabled', 'reminder_days_before', 'reminder_repeat_days', 'reminder_max',
         'reminder_channels', 'reminder_cron_token', 'push_subject'].forEach(function (id) {
            fields[id] = document.getElementById(id).value;
        });
        post(fields, function (res) {
            if (res.success) { fill(res.data); showToast(res.message, 'success'); }
            else { showToast(res.message || 'Error', 'danger'); }
        });
    });

    document.getElementById('generateKeys').addEventListener('click', function () {
        post({ action: 'generate_keys', push_subject: document.getElementById('push_subject').value }, function (res) {
            showToast(res.message, res.success ? 'success' : 'danger');
            if (res.success) { pushState.innerHTML = '&#10003; keys ready'; }
        });
    });

    document.getElementById('testPush').addEventListener('click', function () {
        post({ action: 'test_push' }, function (res) {
            showToast(res.message, res.success ? 'success' : 'danger');
        });
    });
})();
</script>

