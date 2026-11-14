/* ============================================================
   PWA glue - service worker registration + "Install app" prompt.

   Loaded from app/Views/partials/pwa_head.php on every layout, so
   the admin panel, the tenant portal and both login screens all
   behave the same. Everything degrades to a no-op on browsers
   without service worker or install support.
   ============================================================ */
(function () {
    'use strict';

    var script = document.querySelector('script[src*="pwa.js"]');
    if (!script) return;

    // .../assets/js/pwa.js -> ".../" (works in a sub-folder install too)
    var BASE = script.src.replace(/assets\/js\/pwa\.js.*$/, '');
    var LANG = (document.documentElement.lang || 'en').toLowerCase().indexOf('bn') === 0 ? 'bn' : 'en';

    var TEXT = {
        en: {
            title: 'Install the app',
            body: 'Add it to your home screen for one-tap access to rent and invoices.',
            install: 'Install',
            later: 'Not now',
            iosTitle: 'Add to Home Screen',
            iosBody: 'Tap Share, then "Add to Home Screen" to use the app full screen.',
            got: 'Got it',
            update: 'A new version is available. Reload to update?',
        },
        bn: {
            title: 'অ্যাপ ইনস্টল করুন',
            body: 'হোম স্ক্রিনে যোগ করে এক ট্যাপে ভাড়া ও ইনভয়েস দেখুন।',
            install: 'ইনস্টল',
            later: 'পরে',
            iosTitle: 'হোম স্ক্রিনে যোগ করুন',
            iosBody: 'শেয়ার বাটনে ট্যাপ করে "Add to Home Screen" নির্বাচন করুন।',
            got: 'বুঝেছি',
            update: 'নতুন সংস্করণ পাওয়া গেছে। আপডেট করতে রিলোড করবেন?',
        }
    }[LANG];

    var DISMISS_KEY = 'rms_pwa_dismissed_at';
    var DISMISS_DAYS = 14;

    function isStandalone() {
        return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
            || window.navigator.standalone === true;
    }

    function dismissedRecently() {
        try {
            var at = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
            return at && (Date.now() - at) < DISMISS_DAYS * 864e5;
        } catch (e) {
            return false;
        }
    }

    function dismiss() {
        try { localStorage.setItem(DISMISS_KEY, String(Date.now())); } catch (e) {}
    }

    /* ---------- install banner ---------- */

    function showBanner(opts) {
        if (document.getElementById('pwaInstallBar')) return;

        var bar = document.createElement('div');
        bar.id = 'pwaInstallBar';
        bar.className = 'pwa-install-bar';
        bar.setAttribute('role', 'dialog');
        bar.setAttribute('aria-label', opts.title);

        var icon = document.createElement('img');
        icon.src = BASE + 'assets/icons/icon-192.png';
        icon.alt = '';
        icon.className = 'pwa-install-icon';

        var text = document.createElement('div');
        text.className = 'pwa-install-text';
        var strong = document.createElement('strong');
        strong.textContent = opts.title;
        var span = document.createElement('span');
        span.textContent = opts.body;
        text.appendChild(strong);
        text.appendChild(span);

        var primary = document.createElement('button');
        primary.type = 'button';
        primary.className = 'pwa-install-primary';
        primary.textContent = opts.actionLabel;

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'pwa-install-close';
        close.setAttribute('aria-label', opts.actionLabel === TEXT.install ? TEXT.later : TEXT.got);
        close.innerHTML = '&times;';

        bar.appendChild(icon);
        bar.appendChild(text);
        bar.appendChild(primary);
        bar.appendChild(close);
        document.body.appendChild(bar);
        // Trigger the CSS slide-in on the next frame.
        requestAnimationFrame(function () { bar.classList.add('is-visible'); });

        close.addEventListener('click', function () {
            bar.classList.remove('is-visible');
            setTimeout(function () { bar.remove(); }, 250);
            dismiss();
        });

        primary.addEventListener('click', function () {
            if (opts.onAction) opts.onAction(bar);
        });
    }

    var deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;
        if (isStandalone() || dismissedRecently()) return;
        // Let the page settle before asking.
        setTimeout(function () {
            showBanner({
                title: TEXT.title,
                body: TEXT.body,
                actionLabel: TEXT.install,
                onAction: function (bar) {
                    if (!deferredPrompt) return;
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function () {
                        deferredPrompt = null;
                        bar.classList.remove('is-visible');
                        setTimeout(function () { bar.remove(); }, 250);
                    });
                }
            });
        }, 2500);
    });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        dismiss();
        var bar = document.getElementById('pwaInstallBar');
        if (bar) bar.remove();
    });

    // iOS Safari never fires beforeinstallprompt - show the manual hint once.
    (function iosHint() {
        var ua = window.navigator.userAgent || '';
        var isIOS = /iPad|iPhone|iPod/.test(ua)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        var isSafari = /Safari/.test(ua) && !/CriOS|FxiOS|EdgiOS|OPiOS/.test(ua);
        if (!isIOS || !isSafari || isStandalone() || dismissedRecently()) return;

        setTimeout(function () {
            showBanner({
                title: TEXT.iosTitle,
                body: TEXT.iosBody,
                actionLabel: TEXT.got,
                onAction: function (bar) {
                    bar.classList.remove('is-visible');
                    setTimeout(function () { bar.remove(); }, 250);
                    dismiss();
                }
            });
        }, 4000);
    })();

    /* ---------- service worker ---------- */

    if (!('serviceWorker' in navigator)) return;

    window.addEventListener('load', function () {
        navigator.serviceWorker.register(BASE + 'sw.js', { scope: BASE }).then(function (reg) {
            // A worker is already waiting (fresh deploy): offer a reload.
            if (reg.waiting && navigator.serviceWorker.controller) {
                offerUpdate(reg);
            }
            reg.addEventListener('updatefound', function () {
                var installing = reg.installing;
                if (!installing) return;
                installing.addEventListener('statechange', function () {
                    if (installing.state === 'installed' && navigator.serviceWorker.controller) {
                        offerUpdate(reg);
                    }
                });
            });
        }).catch(function () {
            /* installability stays available; only offline mode is lost */
        });
    });

    var updateOffered = false;

    function offerUpdate(reg) {
        if (updateOffered) return;
        updateOffered = true;
        if (typeof window.showToast === 'function') {
            window.showToast(TEXT.update, 'info');
        }
        // apply the new worker on the next full navigation so nothing
        // reloads under the user's fingers mid-form
        if (reg.waiting) reg.waiting.postMessage('SKIP_WAITING');
    }
})();
