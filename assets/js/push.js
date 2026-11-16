/* ============================================================
   Tenant opt-in for due-date push reminders.

   Binds to #pushCard on the tenant portal. All user-facing text
   comes from data-* attributes rendered by PHP, so the strings stay
   in lang/en.php + lang/bn.php instead of being duplicated here.

   States handled: unsupported browser, permission denied/blocked,
   subscribed, not subscribed, request in flight.
   ============================================================ */
(function () {
    'use strict';

    var card = document.getElementById('pushCard');
    var button = document.getElementById('pushToggle');
    var statusEl = document.getElementById('pushStatus');
    if (!card || !button || !statusEl) return;

    var d = card.dataset;
    var MSG = {
        on: d.msgOn || 'Reminders are on for this device',
        off: d.msgOff || 'Get a notification when your rent is due.',
        unsupported: d.msgUnsupported || 'This browser does not support notifications',
        blocked: d.msgBlocked || 'Notifications are blocked in your browser settings',
        denied: d.msgDenied || 'Notification permission was not granted',
        error: d.msgError || 'Something went wrong',
        service: d.msgService || 'Notification service is not ready. Reload the app and try again.',
        onBtn: d.btnOn || 'Turn off reminders',
        offBtn: d.btnOff || 'Turn on reminders'
    };
    var CSRF = d.csrf || '';
    var VAPID = d.vapid || '';
    var busy = false;

    function setState(state) {
        button.disabled = state === 'unsupported' || state === 'blocked' || busy;
        if (state === 'subscribed') {
            statusEl.textContent = MSG.on;
            button.dataset.mode = 'off';
            button.querySelector('span').textContent = MSG.onBtn;
            button.classList.remove('btn-primary');
            button.classList.add('btn-outline-secondary');
        } else {
            statusEl.textContent = state === 'unsupported' ? MSG.unsupported
                : state === 'blocked' ? MSG.blocked
                : state === 'denied' ? MSG.denied
                : MSG.off;
            button.dataset.mode = 'on';
            button.querySelector('span').textContent = MSG.offBtn;
            button.classList.add('btn-primary');
            button.classList.remove('btn-outline-secondary');
        }
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; ++i) out[i] = raw.charCodeAt(i);
        return out;
    }

    function post(url, formData) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(function (r) { return r.json().catch(function () { return {}; }); });
    }

    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !VAPID) {
        setState('unsupported');
        return;
    }

    var registration = null;
    var BASE = window.RMS_PWA_BASE || '/';

    // navigator.serviceWorker.ready never settles while no registration is
    // active for this scope, which left the button disabled forever on devices
    // whose service worker was stale or missing. Resolve from the registration
    // itself and register explicitly when there is none.
    function getRegistration() {
        if (registration) return Promise.resolve(registration);
        return navigator.serviceWorker.getRegistration(BASE).then(function (reg) {
            if (reg && reg.active) return reg;
            return navigator.serviceWorker.register(BASE + 'sw.js', { scope: BASE })
                .then(function (fresh) {
                    return navigator.serviceWorker.ready.catch(function () { return fresh; });
                });
        }).then(function (reg) {
            registration = reg;
            return reg;
        });
    }

    // Hard deadline so a hung service worker shows a message instead of a
    // button that looks clickable but does nothing.
    function withTimeout(promise, ms, message) {
        return new Promise(function (resolve, reject) {
            var timer = setTimeout(function () { reject(new Error(message)); }, ms);
            promise.then(function (value) {
                clearTimeout(timer);
                resolve(value);
            }, function (err) {
                clearTimeout(timer);
                reject(err);
            });
        });
    }

    function refresh() {
        if (!('Notification' in window)) { setState('unsupported'); return; }
        if (Notification.permission === 'denied') { setState('blocked'); return; }

        withTimeout(getRegistration(), 6000, MSG.service).then(function (reg) {
            return reg.pushManager.getSubscription();
        }).then(function (sub) {
            setState(sub ? 'subscribed' : 'off');
        }).catch(function () {
            // Still enable the button: tapping it retries registration and
            // surfaces the real reason in a toast.
            setState('off');
        });
    }

    button.addEventListener('click', function () {
        if (busy) return;
        var mode = button.dataset.mode;

        if (mode === 'off') {
            busy = true;
            button.disabled = true;
            getRegistration().then(function (reg) {
                return reg.pushManager.getSubscription();
            }).then(function (sub) {
                if (!sub) return null;
                var endpoint = sub.endpoint;
                return sub.unsubscribe().then(function () {
                    var fd = new FormData();
                    fd.append('csrf_token', CSRF);
                    fd.append('endpoint', endpoint);
                    return post(d.urlUnsubscribe || '', fd);
                });
            }).then(function () {
                busy = false;
                refresh();
            }).catch(function () {
                busy = false;
                button.disabled = false;
                if (window.showToast) window.showToast(MSG.error, 'danger');
            });
            return;
        }

        busy = true;
        button.disabled = true;

        var permission = Notification.permission === 'granted'
            ? Promise.resolve('granted')
            : Notification.requestPermission();

        permission.then(function (perm) {
            if (perm !== 'granted') {
                throw new Error(perm === 'denied' ? MSG.blocked : MSG.denied);
            }
            return withTimeout(getRegistration(), 8000, MSG.service);
        }).then(function (reg) {
            return reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID)
            });
        }).then(function (sub) {
            var fd = new FormData();
            fd.append('csrf_token', CSRF);
            fd.append('subscription', JSON.stringify(sub.toJSON()));
            return post(d.urlSubscribe || '', fd);
        }).then(function (res) {
            busy = false;
            button.disabled = false;
            if (!res || !res.success) {
                throw new Error((res && res.message) || MSG.error);
            }
            setState('subscribed');
            if (window.showToast) window.showToast(res.message || MSG.on, 'success');
        }).catch(function (err) {
            busy = false;
            button.disabled = false;
            refresh();
            if (window.showToast) {
                window.showToast(err && err.message ? err.message : MSG.error, 'danger');
            }
        });
    });

    refresh();
})();
