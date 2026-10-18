/* ============================================================================
 * Aurora Library — UI Helpers
 * ----------------------------------------------------------------------------
 * Public surface (window.Aurora):
 *   .openSidebar()  / .closeSidebar()  / .toggleSidebar()
 *   .toast(msg,     type, ms)         - find/auto-dismiss data-toast nodes,
 *                                       or create a fresh floating toast.
 *   .dismissToast(node)               - manually hide one toast.
 *
 * Conventions:
 *   - All initialisation waits for DOMContentLoaded.
 *   - Every element access is null-checked; missing elements are skipped
 *     silently so a page that omits a particular feature still loads.
 *   - Listeners are attached with { once:false, passive:true } where
 *     appropriate, and never duplicated (we use a single guard flag).
 *   - No setInterval loops: every timer is tracked so we can clear it.
 * ========================================================================== */

(function () {
    'use strict';

    /* ---------- helpers ----------------------------------------------- */

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            try { fn(); } catch (err) { console.error('[Aurora] init failed', err); }
        }
    }

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $$(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    /* ---------- sidebar ---------------------------------------------- */

    var SIDEBAR_BREAKPOINT = 992;

    var sidebarEls = {
        toggle: null,
        sidebar: null,
        overlay: null,
    };

    function isMobileViewport() {
        return window.innerWidth < SIDEBAR_BREAKPOINT;
    }

    function openSidebar() {
        document.body.classList.add('sidebar-open');
    }
    function closeSidebar() {
        document.body.classList.remove('sidebar-open');
    }
    function toggleSidebar() {
        if (document.body.classList.contains('sidebar-open')) closeSidebar();
        else openSidebar();
    }

    function onSidebarLinkClick(e) {
        // Only auto-close on actual anchor taps, not nested button/form submits.
        var t = e.target;
        while (t && t !== sidebarEls.sidebar) {
            if (t.tagName === 'A' && isMobileViewport()) {
                closeSidebar();
                return;
            }
            t = t.parentNode;
        }
    }

    function onKeyDown(e) {
        if (!document.body.classList.contains('sidebar-open')) return;
        if (e.key === 'Escape' || e.key === 'Esc') {
            closeSidebar();
        }
    }

    function initSidebar() {
        sidebarEls.toggle   = $('[data-menu-toggle]');
        sidebarEls.sidebar  = $('[data-sidebar]');
        sidebarEls.overlay  = $('[data-sidebar-overlay]');

        if (sidebarEls.toggle) {
            sidebarEls.toggle.addEventListener('click', toggleSidebar);
        }
        if (sidebarEls.overlay) {
            sidebarEls.overlay.addEventListener('click', closeSidebar);
        }
        if (sidebarEls.sidebar) {
            sidebarEls.sidebar.addEventListener('click', onSidebarLinkClick);
        }
        document.addEventListener('keydown', onKeyDown);

        // If the viewport grows past the breakpoint while the body still has
        // .sidebar-open (e.g. device rotation), drop the class so the
        // desktop layout takes over.
        window.addEventListener('resize', function () {
            if (!isMobileViewport() && document.body.classList.contains('sidebar-open')) {
                closeSidebar();
            }
        });
    }

    /* ---------- password show / hide --------------------------------- */

    function isToggleableInput(input) {
        if (!input || !input.tagName) return false;
        if (input.tagName !== 'INPUT') return false;
        var t = (input.type || '').toLowerCase();
        // Some browsers reject reassigning type on file/button/submit etc.
        return (t === 'password' || t === 'text');
    }

    function onTogglePasswordClick(btn) {
        return function () {
            var sel = btn.getAttribute('data-toggle-password');
            if (!sel) return;
            var input = $(sel);
            if (!isToggleableInput(input)) return;
            try {
                input.type = (input.type === 'password') ? 'text' : 'password';
            } catch (e) {
                // Setting an input's type can throw in some legacy engines;
                // silently no-op rather than breaking the page.
            }
            // Move focus back so keyboard users keep their place.
            if (typeof input.focus === 'function') {
                try { input.focus(); } catch (e) { /* */
                }
            }
        };
    }

    function initPasswordToggles() {
        $$('[data-toggle-password]').forEach(function (btn) {
            btn.addEventListener('click', onTogglePasswordClick(btn));
        });
    }

    /* ---------- toasts ----------------------------------------------- */

    var toastTimers = new WeakMap();

    function dismissToast(node) {
        if (!node) return;
        // Pause any tracked auto-dismiss timer.
        var t = toastTimers.get(node);
        if (t) { clearTimeout(t); toastTimers.delete(node); }
        // Animate out if the page provided CSS for .is-hiding; otherwise just remove.
        node.classList.add('is-hiding');
        var done = function () {
            if (node && node.parentNode) node.parentNode.removeChild(node);
        };
        node.addEventListener('transitionend', done, { once: true });
        // Fallback: 350ms hard-remove if no transition fired.
        setTimeout(done, 350);
    }

    function showToast(msg, type, ms) {
        type = type || 'info';
        ms   = (typeof ms === 'number') ? ms : 4000;

        var node = document.createElement('div');
        node.className = 'aurora-toast aurora-toast--' + type;
        node.setAttribute('role', type === 'error' ? 'alert' : 'status');
        node.textContent = String(msg == null ? '' : msg);

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'aurora-toast__close';
        closeBtn.setAttribute('aria-label', 'Dismiss');
        closeBtn.textContent = '×';
        closeBtn.addEventListener('click', function () { dismissToast(node); });
        node.appendChild(closeBtn);

        var host = $('[data-toast-host]') || document.body;
        host.appendChild(node);

        // Trigger the in animation on the next frame so the transition fires.
        requestAnimationFrame(function () {
            node.classList.add('is-visible');
        });

        var timer = setTimeout(function () { dismissToast(node); }, ms);
        toastTimers.set(node, timer);
        return node;
    }

    function initInlineToasts() {
        // Auto-dismiss any server-rendered [data-toast] blocks (the login
        // pages emit these for error/success banners). They hide after a
        // short delay so they don't linger forever.
        $$('[data-toast]').forEach(function (node) {
            var ms = parseInt(node.getAttribute('data-toast-time') || '4500', 10);
            var t  = setTimeout(function () { dismissToast(node); }, ms);
            toastTimers.set(node, t);
            // Click-to-dismiss.
            node.addEventListener('click', function (e) {
                if (e.target && e.target.matches('button, .aurora-toast__close')) return;
                dismissToast(node);
            });
        });
    }

    /* ---------- table filters (client-side, opt-in) ---------------- */

    function initTableFilters() {
        // Opt-in: a wrapper with [data-table-filter] containing an
        // <input data-table-filter-input> and a <table data-table-filter-table>.
        $$('[data-table-filter]').forEach(function (wrap) {
            var input = $('[data-table-filter-input]', wrap);
            var table = $('[data-table-filter-table]', wrap);
            if (!input || !table) return;

            input.addEventListener('input', function () {
                var q = String(input.value || '').toLowerCase().trim();
                $$('tr', table.tBodies && table.tBodies[0] ? table.tBodies[0] : table)
                    .forEach(function (tr) {
                        var text = (tr.textContent || '').toLowerCase();
                        tr.style.display = (!q || text.indexOf(q) !== -1) ? '' : 'none';
                    });
            });
        });
    }

    /* ---------- confirm modal --------------------------------------- */

    function ensureConfirmStyles() {
        if (document.getElementById('aurora-confirm-style')) return;
        var css = ''
            + '[data-aurora-confirm-host]{position:fixed;inset:0;z-index:1090;display:none;}'
            + '[data-aurora-confirm-host].is-open{display:flex;align-items:center;justify-content:center;}'
            + '[data-aurora-confirm-scrim]{position:absolute;inset:0;background:rgba(15,23,42,.45);}'
            + '[data-aurora-confirm-card]{position:relative;background:#fff;border-radius:14px;'
            + 'box-shadow:0 18px 40px rgba(0,0,0,.18);max-width:420px;width:calc(100% - 32px);'
            + 'padding:22px 22px 18px;font:inherit;color:#2c3e50;}'
            + '[data-aurora-confirm-title]{margin:0 0 8px;font-size:16px;font-weight:700;}'
            + '[data-aurora-confirm-body]{margin:0 0 18px;color:#5a6573;font-size:14px;line-height:1.5;}'
            + '[data-aurora-confirm-actions]{display:flex;gap:8px;justify-content:flex-end;}'
            + '[data-aurora-confirm-actions] button{padding:8px 14px;border-radius:8px;'
            + 'border:0;font:inherit;cursor:pointer;font-weight:600;}'
            + '[data-aurora-confirm-cancel]{background:#e5e9ee;color:#2c3e50;}'
            + '[data-aurora-confirm-ok]{background:#c0392b;color:#fff;}';
        var style = document.createElement('style');
        style.id = 'aurora-confirm-style';
        style.appendChild(document.createTextNode(css));
        document.head.appendChild(style);
    }

    /**
     * Promise-based modal confirmation. Replaces window.confirm() with a
     * styled dialog that matches the rest of the Aurora UI.
     *
     *   await Aurora.confirm({ title:'Delete book?', body:'This cannot be undone.', ok:'Delete' });
     */
    function confirmDialog(opts) {
        opts = opts || {};
        return new Promise(function (resolve) {
            try { ensureConfirmStyles(); } catch (e) { resolve(window.confirm(opts.body || '')); return; }

            var host  = document.createElement('div');
            host.setAttribute('data-aurora-confirm-host', '');
            var scrim = document.createElement('div'); scrim.setAttribute('data-aurora-confirm-scrim', '');
            var card  = document.createElement('div'); card.setAttribute('data-aurora-confirm-card', 'role');
            card.setAttribute('role', 'alertdialog');
            card.setAttribute('aria-modal', 'true');

            var h = document.createElement('h2');
            h.setAttribute('data-aurora-confirm-title', '');
            h.textContent = opts.title || 'Are you sure?';

            var b = document.createElement('p');
            b.setAttribute('data-aurora-confirm-body', '');
            b.textContent = opts.body || '';

            var actions = document.createElement('div');
            actions.setAttribute('data-aurora-confirm-actions', '');

            var cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.setAttribute('data-aurora-confirm-cancel', '');
            cancel.textContent = opts.cancel || 'Cancel';

            var ok = document.createElement('button');
            ok.type = 'button';
            ok.setAttribute('data-aurora-confirm-ok', '');
            ok.textContent = opts.ok || 'Confirm';

            actions.appendChild(cancel);
            actions.appendChild(ok);
            card.appendChild(h);
            card.appendChild(b);
            card.appendChild(actions);
            host.appendChild(scrim);
            host.appendChild(card);
            document.body.appendChild(host);

            // Focus the safer button by default.
            setTimeout(function () {
                try { cancel.focus(); } catch (e) { /* */
                }
            }, 0);

            function close(result) {
                host.classList.remove('is-open');
                setTimeout(function () {
                    if (host.parentNode) host.parentNode.removeChild(host);
                }, 120);
                resolve(result);
            }

            cancel.addEventListener('click', function () { close(false); });
            ok.addEventListener('click',     function () { close(true);  });
            scrim.addEventListener('click',  function () { close(false); });

            function onKey(e) {
                if (e.key === 'Escape' || e.key === 'Esc') close(false);
                else if (e.key === 'Enter')                close(true);
            }
            document.addEventListener('keydown', onKey);

            // Expose teardown via class
            host.addEventListener('aurora-cleanup', function () {
                document.removeEventListener('keydown', onKey);
            });

            requestAnimationFrame(function () { host.classList.add('is-open'); });
        });
    }

    /* ---------- confirm-modal hooks for forms & buttons ----------- */

    function initConfirmHooks() {
        // Hook: <form data-aurora-confirm="Message?"> OR < button / a >
        $$('form[data-aurora-confirm], a[data-aurora-confirm], button[data-aurora-confirm]')
            .forEach(function (el) {
                el.addEventListener('click', function (ev) {
                    var msg = el.getAttribute('data-aurora-confirm');
                    if (!msg) return;
                    ev.preventDefault();
                    confirmDialog({ title: el.getAttribute('data-aurora-title') || 'Confirm', body: msg })
                        .then(function (ok) {
                            if (!ok) return;
                            if (el.tagName === 'FORM') { el.submit(); return; }
                            // Anchor or button: navigate or call click
                            if (el.tagName === 'A' && el.href) {
                                window.location.href = el.href;
                            } else {
                                // Already-prevented button click — submit its parent form if any
                                var f = el.closest && el.closest('form');
                                if (f) f.submit();
                            }
                        });
                }, true);
            });
    }

    /* ---------- back/forward-cache guard -------------------------------- */

    /**
     * Chrome's bfcache serves stale pages from memory during Back/Forward
     * navigation WITHOUT contacting the server — so Cache-Control headers
     * and server-side session checks are bypassed entirely. When a page is
     * restored from bfcache we force a full reload so the server can
     * re-validate the session and the user isn't silently "logged in"
     * via a cached page.
     */
    function initNoBfcache() {
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) {
                window.location.reload();
            }
        });
    }

    /* ---------- bootstrap ------------------------------------------- */

    function init() {
        try { initSidebar(); }       catch (e) { console.error('[Aurora] sidebar',  e); }
        try { initPasswordToggles(); } catch (e) { console.error('[Aurora] pwtoggle', e); }
        try { initInlineToasts(); }   catch (e) { console.error('[Aurora] toasts',   e); }
        try { initTableFilters(); }   catch (e) { console.error('[Aurora] filters',  e); }
        try { initConfirmHooks(); }   catch (e) { console.error('[Aurora] confirm', e); }
        try { initNoBfcache(); }      catch (e) { console.error('[Aurora] bfcache',  e); }
    }

    ready(init);

    /* ---------- public API ----------------------------------------- */

    window.Aurora = {
        openSidebar:      openSidebar,
        closeSidebar:     closeSidebar,
        toggleSidebar:    toggleSidebar,
        toast:            showToast,
        dismissToast:     dismissToast,
        confirm:          confirmDialog,
        isMobileViewport: isMobileViewport,
    };
})();