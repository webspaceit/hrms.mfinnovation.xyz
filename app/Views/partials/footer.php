</main>

        <footer class="footer text-center py-3">
            <small class="text-muted">© <?php echo date('Y'); ?> <?php echo t('app_name'); ?></small>
        </footer>
    </div>
</div>

<!-- Toast / Notification container -->
<div class="fixed bottom-0 right-0 p-3" style="z-index: 1060">
    <div id="toast-container" class="flex flex-col gap-2"></div>
</div>

<!-- Bulk Delete Confirmation Modal -->
<div class="modal" id="bulkDeleteConfirmModal">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle mr-1"></i><?php echo t('delete_selected'); ?></h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeModal('bulkDeleteConfirmModal')" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <p class="mb-0"><?php echo t('bulk_delete_confirm'); ?></p>
                <p class="mb-0 mt-2"><strong id="bulkDeleteCount">0</strong> <span id="bulkDeleteType"><?php echo t('record'); ?></span></p>
            </div>
            <div class="modal-footer border-0 bg-light py-3">
                <button type="button" class="btn btn-secondary px-4" onclick="closeModal('bulkDeleteConfirmModal')"><?php echo t('cancel'); ?></button>
                <button type="button" class="btn btn-danger px-4" id="bulkDeleteConfirmBtn"><?php echo t('delete_selected'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/bn.js"></script>
<script>
const BASE_URL = '<?php echo BASE_URL; ?>';
const CSRF_TOKEN = '<?php echo csrf_token(); ?>';
const BULK_LANGS = {
    confirm_delete: '<?php echo t("bulk_delete_confirm"); ?>',
    record: '<?php echo t("record"); ?>',
    records: '<?php echo t("records"); ?>',
    delete_selected: '<?php echo t("delete_selected"); ?>'
};
</script>
<script src="<?php echo url('assets/js/app.js'); ?>"></script>
<script src="<?php echo url('assets/js/bulk_select.js'); ?>"></script>
<?php if (isset($extraJs)) echo $extraJs; ?>
</body>
</html>