/* ============================================================
   Rent Management System - Main JavaScript
   ============================================================ */

// ---- Sidebar Toggle ----
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const show = sidebar.classList.toggle('show');
    if (backdrop) backdrop.classList.toggle('show', show);
    if (show) {
        document.addEventListener('keydown', closeOnEscape);
    } else {
        document.removeEventListener('keydown', closeOnEscape);
    }
}

function closeOnEscape(e) {
    if (e.key === 'Escape') {
        const sidebar = document.getElementById('sidebar');
        if (sidebar && sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (backdrop) backdrop.classList.remove('show');
            document.removeEventListener('keydown', closeOnEscape);
        }
    }
}

// Close the off-canvas sidebar when a nav link is tapped (persistent nav links only)
document.addEventListener('click', function(e) {
    const link = e.target.closest('#sidebar a[href]');
    const isMobile = window.matchMedia('(max-width: 767.98px)').matches;
    if (link && isMobile) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) sidebar.classList.remove('show');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (backdrop) backdrop.classList.remove('show');
    }
});

// ---- Set Language via AJAX ----
function setLanguage(lang) {
    fetch(BASE_URL + 'ajax/lang.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'lang=' + encodeURIComponent(lang) + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const url = new URL(window.location.href);
            url.searchParams.set('lang', lang);
            window.location.href = url.toString();
        } else {
            showToast(data.message || 'Could not change language', 'danger');
        }
    })
    .catch(() => showToast('Request failed', 'danger'));
}

// ---- Dropdowns (custom) ----
function toggleDropdown(btn) {
    const menu = btn && btn.nextElementSibling;
    if (!menu || !menu.classList.contains('dropdown-menu')) return;
    const isOpen = menu.classList.contains('open');

    // Close all other open dropdowns
    document.querySelectorAll('.dropdown-menu.open').forEach(m => m !== menu && m.classList.remove('open'));

    if (isOpen) {
        menu.classList.remove('open');
    } else {
        menu.classList.add('open');
    }
}

// Close dropdowns on outside click / Escape
document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
    }
});

// Table actions use inline buttons (no dropdown), so nothing to manage here.

// ---- Modals (custom) ----
function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('open');
    document.body.classList.add('modal-open');
    document.dispatchEvent(new CustomEvent('modal:opened', { detail: { id: id } }));
}

function closeModal(id) {
    let modal = id;
    if (typeof id === 'string') modal = document.getElementById(id);
    if (!modal || !modal.classList) return;
    modal.classList.remove('open');
    if (!document.querySelector('.modal.open')) {
        document.body.classList.remove('modal-open');
    }
    document.dispatchEvent(new CustomEvent('modal:closed', { detail: { id: modal.id } }));
}

// Escape closes the topmost open modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const open = document.querySelector('.modal.open');
        if (open) closeModal(open);
    }
});

// Backdrop click closes the clicked modal
document.addEventListener('click', function(e) {
    if (e.target.classList && e.target.classList.contains('modal')) {
        closeModal(e.target);
    }
});

// Re-attach helpers when a modal opens (fields inside modals)
document.addEventListener('modal:opened', function () {
    attachFieldTranslateButtons();
    localizeDateValues();
    initDatepickers();
});

// ---- Translate field English → Bengali ----
var FIELD_TRANSLATE_SRC = 'https://api.mymemory.translated.net/get?langpair=en|bn&q=';

// Convert ASCII digits to Bengali numerals
function toBengaliDigits(str) {
    return String(str || '').replace(/[0-9]/g, function (d) {
        return '০১২৩৪৫৬৭৮৯'[d];
    });
}

// Latin letter -> Bengali letter map for flat numbers / unit codes (5A -> ৫এ)
var BN_FLAT_LETTERS = {
    A: 'এ', B: 'বি', C: 'সি', D: 'ডি', E: 'ই', F: 'এফ', G: 'জি', H: 'এইচ', I: 'আই', J: 'জে',
    K: 'কে', L: 'এল', M: 'এম', N: 'এন', O: 'ও', P: 'পি', Q: 'কিউ', R: 'আর', S: 'এস', T: 'টি',
    U: 'ইউ', V: 'ভি', W: 'ডব্লিউ', X: 'এক্স', Y: 'ওয়াই', Z: 'জেড'
};

// Flat number: digits -> Bengali numerals AND letters -> Bengali letters (5A -> ৫এ)
function toBengaliFlat(str) {
    return String(str || '').replace(/[0-9]/g, function (d) {
        return '০১২৩৪৫৬৭৮৯'[d];
    }).replace(/[a-zA-Z]/g, function (l) {
        return BN_FLAT_LETTERS[l.toUpperCase()] || l;
    });
}

// Convert Bengali numerals back to ASCII digits
function toAsciiDigits(str) {
    return String(str || '').replace(/[০-৯]/g, function (d) {
        return '0123456789'['০১২৩৪৫৬৭৮৯'.indexOf(d)];
    });
}

// True when the value is basically a number (phone, NID, etc.)
function isNumericish(str) {
    return (/[\d]/).test(str) && !/[a-zA-Z\u00C0-\u017F]/.test(str) && (/^[\d+\-().,\s]*$/).test(str);
}

function resetTranslateBtn(btn) {
    if (!btn) return;
    btn.disabled = false;
    btn.title = 'EN → বাংলা';
    btn.innerHTML = '<i class="bi bi-translate"></i>';
}

function translateField(field, btn) {
    const text = field.value.trim();
    if (!text) {
        showToast('Add some text before translating', 'warning');
        return;
    }
    if (btn) {
        btn.disabled = true;
        btn.title = 'Translating...';
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
    }
    // Flat number / unit code (5A): digits AND letters become Bengali (5A -> ৫এ)
    if (field.hasAttribute('data-bn-flat')) {
        field.value = toBengaliFlat(text);
        resetTranslateBtn(btn);
        field.dispatchEvent(new Event('input', { bubbles: true }));
        showToast('Converted to Bengali', 'success');
        return;
    }
    // Code-like fields (floor number): convert digits only, keep letters
    if (field.hasAttribute('data-digits-only')) {
        field.value = toBengaliDigits(text);
        resetTranslateBtn(btn);
        field.dispatchEvent(new Event('input', { bubbles: true }));
        showToast('Converted to Bengali digits', 'success');
        return;
    }
    // Number-like value (e.g. mobile, amount): just convert digits to Bengali numerals
    if (isNumericish(text)) {
        field.value = toBengaliDigits(text);
        resetTranslateBtn(btn);
        field.dispatchEvent(new Event('input', { bubbles: true }));
        showToast('Converted to Bengali digits', 'success');
        return;
    }
    fetch(FIELD_TRANSLATE_SRC + encodeURIComponent(text))
        .then(res => res.json())
        .then(data => {
            const translated = data && data.responseData && data.responseData.translatedText;
            if (translated && translated.trim()) {
                field.value = translated;
                showToast('Translated to Bengali', 'success');
            } else {
                showToast(data.responseDetails || 'Translation failed', 'danger');
            }
        })
        .catch(() => showToast('Translation failed - check internet connection', 'danger'))
        .finally(() => resetTranslateBtn(btn));
}

// Add a small translate button next to every text input / textarea (Bengali version only)
function attachFieldTranslateButtons(scope) {
    scope = scope || document;
    if (document.documentElement.lang !== 'bn') return;
    const fields = scope.querySelectorAll('input.form-control[type="text"], textarea.form-control');
    fields.forEach(function (field) {
        if (field.closest('.field-translate') || field.closest('.input-group')) return;
        // Date fields have their own Bengali digit picker; skip translate button
        if (field.hasAttribute('data-date')) return;
        const wrap = document.createElement('div');
        wrap.className = 'input-group input-group-sm field-translate';
        field.parentNode.insertBefore(wrap, field);
        wrap.appendChild(field);
        const translateBtn = document.createElement('button');
        translateBtn.type = 'button';
        translateBtn.className = 'btn btn-outline-success btn-sm field-translate-btn';
        translateBtn.title = 'EN → বাংলা';
        translateBtn.innerHTML = '<i class="bi bi-translate"></i>';
        translateBtn.addEventListener('click', function () {
            translateField(field, translateBtn);
        });
        wrap.appendChild(translateBtn);
    });
}

// Convert prefilled ISO dates (YYYY-MM-DD) to Bengali digits in the Bengali version
function localizeDateValues(scope) {
    scope = scope || document;
    if (document.documentElement.lang !== 'bn') return;
    scope.querySelectorAll('input.form-control[type="text"]').forEach(function (field) {
        const v = field.value;
        if (v && /^\d{2}-\d{2}-\d{4}$/.test(v)) {
            field.value = toBengaliDigits(v);
        }
    });
}

// ---- Date picker (Flatpickr) for date fields ----
function initDatepickers(scope) {
    scope = scope || document;
    scope.querySelectorAll('input.form-control[type="text"][data-date]').forEach(function (input) {
        if (input._flatpickr) return;
        const isBn = document.documentElement.lang === 'bn';
        const fp = flatpickr(input, {
            dateFormat: 'd-m-Y',
            altInput: false,
            allowInput: true,
            disableMobile: 'true',
            locale: isBn ? window.flatpickr.l10ns.bn : undefined,
            onChange: function (selectedDates, dateStr) {
                input.value = isBn ? toBengaliDigits(dateStr) : dateStr;
            },
            onOpen: function () {
                // Flatpickr needs ASCII YYYY-MM-DD to restore selection
                if (isBn && input.value) {
                    input.value = toAsciiDigits(input.value);
                }
            },
            onClose: function (selectedDates, dateStr) {
                if (isBn && dateStr) {
                    input.value = toBengaliDigits(dateStr);
                } else if (isBn && input.value) {
                    input.value = toBengaliDigits(toAsciiDigits(input.value));
                }
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    attachFieldTranslateButtons();
    localizeDateValues();
    initDatepickers();
});

// ---- Toast Helper ----
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'toast' + (type && type !== 'success' ? ' ' + type : '');
    toast.innerHTML = `
        <div class="flex items-center gap-2 justify-between">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close" style="width:1.5rem;height:1.5rem;font-size:0.8rem" onclick="this.parentElement.parentElement.remove()" aria-label="Close"></button>
        </div>`;
    container.appendChild(toast);

    setTimeout(() => { toast.remove(); }, 3500);
}

// ---- Generic AJAX helper ----
function ajax(url, data, callback) {
    var params = new URLSearchParams();
    params.append('csrf_token', CSRF_TOKEN);
    for (var key in data) {
        var val = data[key];
        if (Array.isArray(val)) {
            val.forEach(function(v) { params.append(key + '[]', v); });
        } else {
            params.append(key, val);
        }
    }
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params
    })
    .then(res => res.json())
    .then(result => callback(result))
    .catch(err => {
        console.error('AJAX Error:', err);
        showToast('Request failed', 'danger');
    });
}

// ---- Generic Delete Confirmation ----
let deleteAction = null;

function confirmDelete(url, message) {
    // Extract ID from URL query string (e.g., "ajax/tenant_delete.php?id=5")
    const urlObj = new URL(url, window.location.origin);
    const id = urlObj.searchParams.get('id');
    deleteAction = { url: urlObj.pathname, message: message, id: id };
    const modal = document.getElementById('confirmModal');
    if (modal) {
        document.getElementById('confirmModalText').textContent = message;
        openModal('confirmModal');
    } else {
        if (confirm(message)) {
            executeDelete(urlObj.pathname, id);
        }
    }
}

function executeDelete(endpoint, id) {
    const params = new URLSearchParams();
    params.append('csrf_token', CSRF_TOKEN);
    if (id) params.append('id', id);
    // `endpoint` is the pathname taken from the URL passed to confirmDelete(),
    // which callers already build with BASE_URL (e.g. "/oop-rms/ajax/x_delete.php").
    // Do NOT prepend BASE_URL again here or the request 404s.
    fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            showToast(result.message, 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(result.message || 'Failed', 'danger');
        }
    })
    .catch(() => showToast('Request failed', 'danger'));
}

// Confirm modal action
document.addEventListener('DOMContentLoaded', function() {
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            if (deleteAction) {
                closeModal('confirmModal');
                executeDelete(deleteAction.url, deleteAction.id);
            }
        });
    }
});

// ---- Modal Form Submission (for modals that post via AJAX) ----
function submitModalForm(formId, url, onSuccess) {
    const form = document.getElementById(formId);
    if (!form) return;

    const data = new FormData(form);
    fetch(url, {
        method: 'POST',
        body: data
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            showToast(result.message, 'success');
            const modalEl = form.closest('.modal');
            if (modalEl) closeModal(modalEl);
            form.reset();
            if (typeof onSuccess === 'function') onSuccess(result);
            else setTimeout(() => location.reload(), 600);
        } else {
            showToast(result.message || 'Error', 'danger');
        }
    })
    .catch(() => showToast('Request failed', 'danger'));
}

// ---- Number / currency formatting ----
function formatMoney(amount) {
    const ascii = parseFloat(toAsciiDigits(amount)).toFixed(2);
    const digits = document.documentElement.lang === 'bn' ? toBengaliDigits(ascii) : ascii;
    return '৳' + digits;
}

// ---- Prevent double email submissions ----
function preventDoubleSubmit(formId) {
    const form = document.getElementById(formId);
    if (form) {
        form.addEventListener('submit', function(e) {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                setTimeout(() => { btn.disabled = false; }, 3000);
            }
        });
    }
}

// ---- Auto close alerts ----
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        document.querySelectorAll('.alert-dismissible').forEach(el => {
            if (el) el.remove();
        });
    }, 3500);
});