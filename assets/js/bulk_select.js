/* ============================================================
   Bulk Select & Delete - Shared JavaScript Module
   Provides reusable bulk selection and deletion functionality
   for all entity pages (buildings, flats, tenants, leases,
   payments, expenses).
   ============================================================ */

let BulkSelect = {
    selectedItems: [],
    entity: null,

    init(options) {
        this.entity = options.entity || 'buildings';
        this.setupSelectAll();
        this.setupCheckboxes();
        this.updateButton();
    },

    setupSelectAll() {
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.addEventListener('change', () => {
                this.toggleSelectAll(selectAll.checked);
            });
        }
    },

    setupCheckboxes() {
        const checkboxes = document.querySelectorAll('.bulk-checkbox');
        checkboxes.forEach(cb => {
            cb.addEventListener('change', () => {
                this.toggleCheckbox(cb);
            });
        });
    },

    toggleSelectAll(checked) {
        const checkboxes = document.querySelectorAll('.bulk-checkbox');
        this.selectedItems = [];
        
        checkboxes.forEach(cb => {
            cb.checked = checked;
            if (checked) {
                const id = parseInt(cb.value);
                if (this.selectedItems.indexOf(id) === -1) {
                    this.selectedItems.push(id);
                }
            }
        });
        
        this.updateButton();
    },

    toggleCheckbox(cb) {
        const id = parseInt(cb.value);
        
        if (cb.checked) {
            if (this.selectedItems.indexOf(id) === -1) {
                this.selectedItems.push(id);
            }
        } else {
            const idx = this.selectedItems.indexOf(id);
            if (idx !== -1) {
                this.selectedItems.splice(idx, 1);
            }
            const selectAll = document.getElementById('selectAll');
            if (selectAll) selectAll.checked = false;
        }
        
        this.updateButton();
    },

    updateButton() {
        const count = this.selectedItems.length;
        const btn = document.getElementById('bulkDeleteBtn');
        if (btn) {
            if (count > 0) {
                btn.style.display = 'inline-block';
                btn.textContent = btn.getAttribute('data-delete-text') + ' (' + count + ')';
            } else {
                btn.style.display = 'none';
            }
        }
    },

    confirmAndDelete() {
        if (this.selectedItems.length === 0) return;
        
        const count = this.selectedItems.length;
        const type = count > 1 ? BULK_LANGS.records : BULK_LANGS.record;
        
        document.getElementById('bulkDeleteCount').textContent = count;
        document.getElementById('bulkDeleteType').textContent = type;
        
        openModal('bulkDeleteConfirmModal');
    },

    performDelete() {
        const btn = document.getElementById('bulkDeleteBtn');
        if (btn) btn.style.display = 'none';
        
        const formData = new URLSearchParams();
        formData.append('entity', this.entity);
        formData.append('csrf_token', CSRF_TOKEN);
        this.selectedItems.forEach(id => formData.append('ids[]', id));
        
        fetch(BASE_URL + 'ajax/bulk_delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        })
        .then(res => res.json())
        .then(result => {
            if (result.success) {
                showToast(result.message, 'success');
                
                this.selectedItems.forEach(id => {
                    const row = document.querySelector('tr[data-id="' + id + '"]');
                    if (row) row.remove();
                    const card = document.querySelector('.flat-card[data-id="' + id + '"]');
                    if (card) card.remove();
                });
                
                this.selectedItems = [];
                const selectAll = document.getElementById('selectAll');
                if (selectAll) selectAll.checked = false;
            } else {
                showToast(result.message || 'Delete failed', 'danger');
                this.updateButton();
            }
        })
        .catch(err => {
            console.error('Bulk delete error:', err);
            showToast('Request failed: ' + err.message, 'danger');
            this.updateButton();
        });
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const entityEl = document.querySelector('[data-bulk-entity]');
    if (entityEl) {
        BulkSelect.init({
            entity: entityEl.getAttribute('data-bulk-entity') || 'buildings'
        });
    }
    
    // Bind bulk delete confirm button
    const confirmBtn = document.getElementById('bulkDeleteConfirmBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            closeModal('bulkDeleteConfirmModal');
            BulkSelect.performDelete();
        });
    }
});
