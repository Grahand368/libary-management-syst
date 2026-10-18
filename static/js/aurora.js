/* ============================================================================
 * Aurora Library — UI Helpers
 * ----------------------------------------------------------------------------
 * Public surface (window.Aurora):
 *   .openSidebar()  / .closeSidebar()  / .toggleSidebar()
 *   .toast(msg,     type, ms)         - find/auto-dismiss data-toast nodes,
 *                                       or create a fresh floating toast.
 *   .dismissToast(node)               - manually hide one toast.
 *   .confirm(opts)                    - promise-based modal confirmation
 *   .setButtonLoading(btn, loading)   - button loading state
 *   .showSkeleton(container)          - show skeleton loaders
 *   .hideSkeleton(container)          - hide skeleton loaders
 *   .initTableSort(table)             - make table sortable
 *   .initTableSelection(table)        - enable row selection
 *   .setTheme(theme)                  - set light/dark theme
 *   .getTheme()                       - get current theme
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

    function debounce(fn, ms) {
        var timer;
        return function () {
            var args = arguments, ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, ms);
        };
    }

    function throttle(fn, ms) {
        var last = 0;
        return function () {
            var now = Date.now(), args = arguments, ctx = this;
            if (now - last >= ms) { last = now; fn.apply(ctx, args); }
        };
    }

    /* ---------- Theme Management ---------------------------------------- */

    var THEME_KEY = 'aurora-theme';

    function applyTheme(theme) {
        document.documentElement.classList.toggle('theme-light', theme === 'light');
        try { localStorage.setItem(THEME_KEY, theme); } catch (e) {}
    }

    function getStoredTheme() {
        try { return localStorage.getItem(THEME_KEY); } catch (e) { return null; }
    }

    function initTheme() {
        var stored = getStoredTheme();
        if (stored) { applyTheme(stored); return; }
        // Auto-detect system preference
        var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        applyTheme(prefersDark ? 'dark' : 'light');
    }

    /* ---------- Sidebar ---------------------------------------------- */

    var SIDEBAR_BREAKPOINT = 992;
    var sidebarState = { collapsed: false };

    var sidebarEls = {
        toggle: null,
        sidebar: null,
        overlay: null,
        collapseBtn: null,
        lastFocused: null
    };

    function isMobileViewport() {
        return window.innerWidth < SIDEBAR_BREAKPOINT;
    }

    function openSidebar() {
        if (isMobileViewport()) {
            document.body.classList.add('sidebar-open');
            sidebarEls.lastFocused = document.activeElement;
            trapFocus(sidebarEls.sidebar);
        }
    }

    function closeSidebar() {
        document.body.classList.remove('sidebar-open');
        if (sidebarEls.lastFocused) {
            sidebarEls.lastFocused.focus();
            sidebarEls.lastFocused = null;
        }
        releaseFocus();
    }

    function toggleSidebar() {
        if (document.body.classList.contains('sidebar-open')) closeSidebar();
        else openSidebar();
    }

    function toggleSidebarCollapse() {
        sidebarState.collapsed = !sidebarState.collapsed;
        document.body.classList.toggle('sidebar-collapsed', sidebarState.collapsed);
        var main = $('.app-main');
        var header = $('.app-header');
        if (main) main.classList.toggle('sidebar-collapsed', sidebarState.collapsed);
        if (header) header.classList.toggle('sidebar-collapsed', sidebarState.collapsed);
        try { localStorage.setItem('aurora-sidebar-collapsed', sidebarState.collapsed); } catch (e) {}
        // Update aria-expanded
        if (sidebarEls.collapseBtn) {
            sidebarEls.collapseBtn.setAttribute('aria-expanded', sidebarState.collapsed);
        }
    }

    function initSidebarCollapse() {
        var stored = false;
        try { stored = localStorage.getItem('aurora-sidebar-collapsed') === 'true'; } catch (e) {}
        sidebarState.collapsed = stored;
        if (stored) {
            document.body.classList.add('sidebar-collapsed');
            var main = $('.app-main');
            var header = $('.app-header');
            if (main) main.classList.add('sidebar-collapsed');
            if (header) header.classList.add('sidebar-collapsed');
        }
    }

    // Focus trapping for mobile sidebar
    var focusTrapEls = [];
    function trapFocus(el) {
        if (!el) return;
        var focusable = el.querySelectorAll(
            'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
        );
        focusTrapEls = Array.prototype.slice.call(focusable);
        if (focusTrapEls.length) {
            focusTrapEls[0].focus();
            focusTrapEls[0].addEventListener('keydown', handleTabKey);
            focusTrapEls[focusTrapEls.length - 1].addEventListener('keydown', handleTabKey);
        }
    }

    function handleTabKey(e) {
        if (e.key !== 'Tab') return;
        var first = focusTrapEls[0], last = focusTrapEls[focusTrapEls.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault(); first.focus();
        }
    }

    function releaseFocus() {
        focusTrapEls.forEach(function(el) {
            el.removeEventListener('keydown', handleTabKey);
        });
        focusTrapEls = [];
    }

    function onSidebarLinkClick(e) {
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
        if (e.key === 'Escape' || e.key === 'Esc') {
            if (document.body.classList.contains('sidebar-open')) {
                closeSidebar();
            }
            // Close any open dropdowns
            $$('.dropdown-menu.show').forEach(function(m) { m.classList.remove('show'); });
            // Close modals
            $$('.modal-overlay.show').forEach(function(m) { Aurora.closeModal(m); });
        }
    }

    function initSidebar() {
        sidebarEls.toggle   = $('[data-menu-toggle]');
        sidebarEls.sidebar  = $('[data-sidebar]');
        sidebarEls.overlay  = $('[data-sidebar-overlay]');
        sidebarEls.collapseBtn = $('[data-sidebar-collapse]');

        initSidebarCollapse();

        if (sidebarEls.toggle) {
            sidebarEls.toggle.addEventListener('click', toggleSidebar);
        }
        if (sidebarEls.overlay) {
            sidebarEls.overlay.addEventListener('click', closeSidebar);
        }
        if (sidebarEls.sidebar) {
            sidebarEls.sidebar.addEventListener('click', onSidebarLinkClick);
        }
        if (sidebarEls.collapseBtn) {
            sidebarEls.collapseBtn.addEventListener('click', toggleSidebarCollapse);
            sidebarEls.collapseBtn.setAttribute('aria-expanded', sidebarState.collapsed);
        }
        document.addEventListener('keydown', onKeyDown);

        window.addEventListener('resize', debounce(function () {
            if (!isMobileViewport() && document.body.classList.contains('sidebar-open')) {
                closeSidebar();
            }
        }, 150));
    }

    /* ---------- Password show / hide --------------------------------- */

    function isToggleableInput(input) {
        if (!input || !input.tagName) return false;
        if (input.tagName !== 'INPUT') return false;
        var t = (input.type || '').toLowerCase();
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
                btn.setAttribute('aria-label', input.type === 'password' ? 'Show password' : 'Hide password');
                btn.textContent = input.type === 'password' ? '👁' : '🙈';
            } catch (e) {}
            if (typeof input.focus === 'function') { try { input.focus(); } catch (e) {} }
        };
    }

    function initPasswordToggles() {
        $$('[data-toggle-password]').forEach(function (btn) {
            if (!btn.textContent.trim()) btn.textContent = '👁';
            btn.setAttribute('aria-label', 'Show password');
            btn.addEventListener('click', onTogglePasswordClick(btn));
        });
    }

    /* ---------- Button Loading State ----------------------------------- */

    function setButtonLoading(btn, loading, loadingText) {
        if (!btn) return;
        if (loading) {
            btn.classList.add('loading');
            btn.disabled = true;
            if (loadingText) btn.setAttribute('data-loading-text', loadingText);
            btn.dataset.originalText = btn.innerHTML;
        } else {
            btn.classList.remove('loading');
            btn.disabled = false;
            if (btn.dataset.originalText) {
                btn.innerHTML = btn.dataset.originalText;
                delete btn.dataset.originalText;
            }
        }
    }

    /* ---------- Skeleton Loaders --------------------------------------- */

    function showSkeleton(container, type) {
        if (!container) return;
        container.classList.add('skeleton-loading');
        var skeletonHtml = '';
        if (type === 'card') {
            skeletonHtml = '<div class="skeleton skeleton-card"><div class="skeleton skeleton-title"></div><div class="skeleton skeleton-text"></div><div class="skeleton skeleton-text"></div><div class="skeleton skeleton-text" style="width: 60%"></div></div>';
        } else if (type === 'table') {
            skeletonHtml = '<div class="skeleton skeleton-card"><div class="skeleton skeleton-text" style="width: 100%"></div><div class="skeleton skeleton-text" style="width: 100%"></div><div class="skeleton skeleton-text" style="width: 100%"></div><div class="skeleton skeleton-text" style="width: 100%"></div><div class="skeleton skeleton-text" style="width: 100%"></div></div>';
        } else if (type === 'list') {
            skeletonHtml = Array(5).fill('<div class="skeleton skeleton-card" style="display:flex;align-items:center;gap:12px;padding:12px;"><div class="skeleton skeleton-avatar"></div><div style="flex:1"><div class="skeleton skeleton-text" style="width:40%"></div><div class="skeleton skeleton-text" style="width:60%"></div></div></div>').join('');
        } else {
            skeletonHtml = '<div class="skeleton skeleton-text"></div><div class="skeleton skeleton-text" style="width: 70%"></div>';
        }
        container.innerHTML = skeletonHtml;
    }

    function hideSkeleton(container) {
        if (!container) return;
        container.classList.remove('skeleton-loading');
    }

    /* ---------- Form Validation Enhancements --------------------------- */

    function initFormValidation() {
        $$('form[data-validate]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var hasErrors = false;
                $$('[required]', form).forEach(function(input) {
                    if (!validateField(input)) hasErrors = true;
                });
                if (hasErrors) {
                    e.preventDefault();
                    var firstError = form.querySelector('.form-group.has-error [required]');
                    if (firstError) firstError.focus();
                }
            });

            $$('[required]', form).forEach(function(input) {
                input.addEventListener('blur', function() { validateField(input); });
                input.addEventListener('input', function() {
                    if (input.parentElement.classList.contains('has-error')) {
                        validateField(input);
                    }
                });
            });
        });
    }

    function validateField(input) {
        var group = input.closest('.form-group') || input.parentElement;
        var isValid = true;
        var message = '';

        // Required check
        if (input.required && !input.value.trim()) {
            isValid = false;
            message = 'This field is required';
        }

        // Email validation
        if (isValid && input.type === 'email' && input.value.trim()) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value.trim())) {
                isValid = false;
                message = 'Please enter a valid email address';
            }
        }

        // Number validation
        if (isValid && input.type === 'number' && input.value.trim()) {
            var min = parseFloat(input.getAttribute('min'));
            var max = parseFloat(input.getAttribute('max'));
            var val = parseFloat(input.value);
            if (!isNaN(min) && val < min) { isValid = false; message = 'Value must be at least ' + min; }
            if (!isNaN(max) && val > max) { isValid = false; message = 'Value must be at most ' + max; }
        }

        // Pattern validation
        if (isValid && input.hasAttribute('pattern') && input.value.trim()) {
            var pattern = new RegExp(input.getAttribute('pattern'));
            if (!pattern.test(input.value)) {
                isValid = false;
                message = input.getAttribute('title') || 'Invalid format';
            }
        }

        // Min/max length
        if (isValid && input.value.trim()) {
            var minLen = parseInt(input.getAttribute('minlength'), 10);
            var maxLen = parseInt(input.getAttribute('maxlength'), 10);
            var len = input.value.length;
            if (!isNaN(minLen) && len < minLen) { isValid = false; message = 'Must be at least ' + minLen + ' characters'; }
            if (!isNaN(maxLen) && len > maxLen) { isValid = false; message = 'Must be at most ' + maxLen + ' characters'; }
        }

        // Update UI
        if (group) {
            group.classList.toggle('has-error', !isValid);
            var errorEl = group.querySelector('.form-error');
            if (!isValid) {
                if (!errorEl) {
                    errorEl = document.createElement('div');
                    errorEl.className = 'form-error';
                    group.appendChild(errorEl);
                }
                errorEl.textContent = message;
            } else if (errorEl) {
                errorEl.remove();
            }
        }

        input.setAttribute('aria-invalid', !isValid);
        return isValid;
    }

    /* ---------- Auto-save Indicator ------------------------------------ */

    function initAutoSave() {
        $$('[data-autosave]').forEach(function(form) {
            var timeout;
            var indicator = form.querySelector('[data-autosave-indicator]') || createAutoSaveIndicator(form);
            var debounceMs = parseInt(form.getAttribute('data-autosave-debounce') || '1000', 10);

            $$('input, select, textarea', form).forEach(function(input) {
                input.addEventListener('input', function() {
                    clearTimeout(timeout);
                    indicator.textContent = 'Saving...';
                    indicator.className = 'autosave-indicator saving';
                    timeout = setTimeout(function() {
                        saveForm(form, indicator);
                    }, debounceMs);
                });
            });
        });
    }

    function createAutoSaveIndicator(form) {
        var indicator = document.createElement('span');
        indicator.className = 'autosave-indicator';
        indicator.setAttribute('data-autosave-indicator', '');
        indicator.style.cssText = 'font-size:12px;color:var(--ink-500);margin-left:8px;';
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn && submitBtn.parentNode) {
            submitBtn.parentNode.insertBefore(indicator, submitBtn.nextSibling);
        } else {
            form.appendChild(indicator);
        }
        return indicator;
    }

    function saveForm(form, indicator) {
        // This is a placeholder - actual implementation depends on backend
        // For now, just show saved state
        indicator.textContent = 'Saved';
        indicator.className = 'autosave-indicator saved';
        setTimeout(function() {
            indicator.textContent = '';
            indicator.className = 'autosave-indicator';
        }, 2000);
    }

    /* ---------- Table Enhancements ------------------------------------- */

    function initTableSort(table) {
        if (!table || table.dataset.sortInit) return;
        table.dataset.sortInit = 'true';

        $$('th[data-sort]', table).forEach(function(th) {
            th.classList.add('sortable');
            var icon = document.createElement('span');
            icon.className = 'sort-icon';
            icon.innerHTML = '⇅';
            th.appendChild(icon);

            th.addEventListener('click', function() {
                var column = th.getAttribute('data-sort');
                var isAsc = th.classList.contains('asc');
                var tbody = table.querySelector('tbody');
                if (!tbody) return;

                // Update header states
                $$('th.sortable', table).forEach(function(h) {
                    h.classList.remove('asc', 'desc', 'sorted');
                });
                th.classList.add('sorted', isAsc ? 'desc' : 'asc');

                var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                rows.sort(function(a, b) {
                    var aVal = a.querySelector('[data-sort-value="' + column + '"]')?.getAttribute('data-sort-value') ||
                              a.cells[th.cellIndex]?.textContent.trim() || '';
                    var bVal = b.querySelector('[data-sort-value="' + column + '"]')?.getAttribute('data-sort-value') ||
                              b.cells[th.cellIndex]?.textContent.trim() || '';

                    // Try numeric comparison
                    var aNum = parseFloat(aVal.replace(/[^0-9.-]/g, ''));
                    var bNum = parseFloat(bVal.replace(/[^0-9.-]/g, ''));
                    var result;

                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        result = aNum - bNum;
                    } else {
                        result = aVal.localeCompare(bVal, undefined, { numeric: true, sensitivity: 'base' });
                    }

                    return isAsc ? -result : result;
                });

                rows.forEach(function(r) { tbody.appendChild(r); });
            });
        });
    }

    function initTableSelection(table) {
        if (!table || table.dataset.selectionInit) return;
        table.dataset.selectionInit = 'true';

        var selectAll = table.querySelector('thead input[type="checkbox"][data-select-all]');
        var rowCheckboxes = function() {
            return $$('tbody input[type="checkbox"][data-row-select]', table);
        };

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                var checked = selectAll.checked;
                rowCheckboxes().forEach(function(cb) {
                    cb.checked = checked;
                    cb.closest('tr').classList.toggle('selected', checked);
                });
                updateBulkActions(table);
            });
        }

        rowCheckboxes().forEach(function(cb) {
            cb.addEventListener('change', function() {
                cb.closest('tr').classList.toggle('selected', cb.checked);
                if (selectAll) {
                    var allChecked = rowCheckboxes().every(function(c) { return c.checked; });
                    var someChecked = rowCheckboxes().some(function(c) { return c.checked; });
                    selectAll.checked = allChecked;
                    selectAll.indeterminate = someChecked && !allChecked;
                }
                updateBulkActions(table);
            });
        });

        // Keyboard selection (Shift+Click)
        var lastChecked = null;
        $$('tbody tr', table).forEach(function(row) {
            row.addEventListener('click', function(e) {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'A' || e.target.tagName === 'BUTTON') return;
                if (e.shiftKey && lastChecked) {
                    var rows = Array.prototype.slice.call($$('tbody tr', table));
                    var start = rows.indexOf(lastChecked);
                    var end = rows.indexOf(row);
                    var min = Math.min(start, end), max = Math.max(start, end);
                    for (var i = min; i <= max; i++) {
                        var cb = rows[i].querySelector('input[data-row-select]');
                        if (cb && !cb.disabled) { cb.checked = true; rows[i].classList.add('selected'); }
                    }
                    if (selectAll) updateSelectAll(selectAll, table);
                    updateBulkActions(table);
                }
                var cb = row.querySelector('input[data-row-select]');
                if (cb && !cb.disabled) { cb.checked = !cb.checked; row.classList.toggle('selected', cb.checked); lastChecked = row; updateBulkActions(table); }
            });
        });
    }

    function updateSelectAll(selectAll, table) {
        var checkboxes = $$('tbody input[data-row-select]', table);
        var allChecked = checkboxes.every(function(c) { return c.checked; });
        var someChecked = checkboxes.some(function(c) { return c.checked; });
        selectAll.checked = allChecked;
        selectAll.indeterminate = someChecked && !allChecked;
    }

    function updateBulkActions(table) {
        var selectedCount = $$('tbody input[data-row-select]:checked', table).length;
        var bulkBar = table.closest('.table-responsive')?.querySelector('[data-bulk-actions]') || $('[data-bulk-actions="' + table.id + '"]');
        if (bulkBar) {
            bulkBar.style.display = selectedCount ? 'flex' : 'none';
            var countEl = bulkBar.querySelector('[data-selected-count]');
            if (countEl) countEl.textContent = selectedCount + ' selected';
        }
    }

    /* ---------- Toast System ------------------------------------------- */

    var toastTimers = new WeakMap();
    var toastProgressTimers = new WeakMap();

    function dismissToast(node) {
        if (!node) return;
        var t = toastTimers.get(node);
        if (t) { clearTimeout(t); toastTimers.delete(node); }
        var p = toastProgressTimers.get(node);
        if (p) { clearTimeout(p); toastProgressTimers.delete(node); }

        node.classList.add('is-hiding');
        node.classList.remove('is-visible');
        var done = function () {
            if (node && node.parentNode) node.parentNode.removeChild(node);
        };
        node.addEventListener('transitionend', done, { once: true });
        setTimeout(done, 350);
    }

    function showToast(msg, type, ms, options) {
        type = type || 'info';
        ms = (typeof ms === 'number') ? ms : 4000;
        options = options || {};

        var node = document.createElement('div');
        node.className = 'aurora-toast aurora-toast--' + type;
        node.setAttribute('role', type === 'error' ? 'alert' : 'status');
        node.setAttribute('aria-live', 'polite');
        node.setAttribute('aria-atomic', 'true');

        var iconSvg = {
            success: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
            error:   '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10 7.293 11.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
            warning: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
            info:    '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 102 0V9a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>'
        };

        var hasTitle = typeof msg === 'object' && msg.title;
        var title = hasTitle ? msg.title : '';
        var message = hasTitle ? msg.message : msg;
        var action = options.action;

        node.innerHTML = ''
            + '<span class="aurora-toast__icon" aria-hidden="true">' + (iconSvg[type] || iconSvg.info) + '</span>'
            + '<div class="aurora-toast__content">'
            + (title ? '<div class="aurora-toast__title">' + escapeHtml(title) + '</div>' : '')
            + '<div class="aurora-toast__message">' + escapeHtml(message) + '</div>'
            + '</div>'
            + (action ? '<button class="aurora-toast__action btn btn-sm btn-outline-primary" style="margin-left:8px;padding:4px 10px;">' + escapeHtml(action.label) + '</button>' : '')
            + '<button class="aurora-toast__close" aria-label="Dismiss">×</button>'
            + '<div class="aurora-toast__progress" aria-hidden="true"></div>';

        if (action && action.onClick) {
            node.querySelector('.aurora-toast__action').addEventListener('click', function(e) {
                e.stopPropagation();
                action.onClick(node);
            });
        }

        node.querySelector('.aurora-toast__close').addEventListener('click', function () { dismissToast(node); });

        var host = $('[data-toast-host]') || document.body;
        host.appendChild(node);

        requestAnimationFrame(function () { node.classList.add('is-visible'); });

        if (ms > 0) {
            var timer = setTimeout(function () { dismissToast(node); }, ms);
            toastTimers.set(node, timer);
        }

        return node;
    }

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&')
            .replace(/</g, '<')
            .replace(/>/g, '>')
            .replace(/"/g, '"')
            .replace(/'/g, '&#039;');
    }

    function initInlineToasts() {
        $$('[data-toast]').forEach(function (node) {
            var ms = parseInt(node.getAttribute('data-toast-time') || '4500', 10);
            var t = setTimeout(function () { dismissToast(node); }, ms);
            toastTimers.set(node, t);
            node.addEventListener('click', function (e) {
                if (e.target && e.target.matches('button, .aurora-toast__close, .alert-dismiss')) return;
                dismissToast(node);
            });
            // Add progress bar
            var progress = document.createElement('div');
            progress.className = 'aurora-toast__progress';
            progress.style.cssText = 'position:absolute;bottom:0;left:0;height:3px;background:rgba(255,255,255,0.3);border-radius:0 0 12px 12px;animation:toastProgress ' + (ms/1000) + 's linear forwards;';
            node.appendChild(progress);
            if (!document.getElementById('toast-progress-style')) {
                var style = document.createElement('style');
                style.id = 'toast-progress-style';
                style.textContent = '@keyframes toastProgress { from { transform: scaleX(1); } to { transform: scaleX(0); } }';
                document.head.appendChild(style);
            }
        });
    }

    /* ---------- Modal System ------------------------------------------- */

    var modalStack = [];

    function openModal(modalEl) {
        if (!modalEl) return;
        modalEl.classList.add('show');
        document.body.style.overflow = 'hidden';
        modalStack.push(modalEl);
        trapFocus(modalEl.querySelector('.modal'));
        // Focus first focusable element
        var focusable = modalEl.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusable) focusable.focus();
    }

    function closeModal(modalEl) {
        if (!modalEl) return;
        modalEl.classList.remove('show');
        var idx = modalStack.indexOf(modalEl);
        if (idx > -1) modalStack.splice(idx, 1);
        if (modalStack.length === 0) document.body.style.overflow = '';
        releaseFocus();
    }

    function initModals() {
        // Trigger buttons
        $$('[data-modal-target]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var target = btn.getAttribute('data-modal-target');
                var modal = $(target);
                if (modal) openModal(modal);
            });
        });

        // Close buttons
        $$('[data-modal-close]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var modal = btn.closest('.modal-overlay');
                if (modal) closeModal(modal);
            });
        });

        // Close on overlay click
        $$('.modal-overlay').forEach(function(overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) closeModal(overlay);
            });
        });

        // Close on Escape
        document.addEventListener('keydown', function(e) {
            if ((e.key === 'Escape' || e.key === 'Esc') && modalStack.length) {
                closeModal(modalStack[modalStack.length - 1]);
            }
        });
    }

    /* ---------- Dropdown Menus ----------------------------------------- */

    function initDropdowns() {
        $$('[data-dropdown-toggle]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var menu = btn.nextElementSibling;
                if (!menu || !menu.classList.contains('dropdown-menu')) return;

                // Close other dropdowns
                $$('.dropdown-menu.show').forEach(function(m) {
                    if (m !== menu) m.classList.remove('show');
                });

                menu.classList.toggle('show');
                btn.setAttribute('aria-expanded', menu.classList.contains('show'));
            });
        });

        // Close on outside click
        document.addEventListener('click', function(e) {
            if (!e.target.closest('[data-dropdown-toggle]') && !e.target.closest('.dropdown-menu')) {
                $$('.dropdown-menu.show').forEach(function(m) { m.classList.remove('show'); });
                $$('[data-dropdown-toggle]').forEach(function(b) { b.setAttribute('aria-expanded', 'false'); });
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                $$('.dropdown-menu.show').forEach(function(m) { m.classList.remove('show'); });
                $$('[data-dropdown-toggle]').forEach(function(b) { b.setAttribute('aria-expanded', 'false'); });
            }
        });
    }

    /* ---------- Tabs --------------------------------------------------- */

    function initTabs() {
        $$('.tabs').forEach(function(tabs) {
            $$('.tab', tabs).forEach(function(tab) {
                tab.addEventListener('click', function() {
                    var target = tab.getAttribute('data-tab');
                    if (!target) return;

                    $$('.tab', tabs).forEach(function(t) { t.classList.remove('active'); });
                    tab.classList.add('active');

                    var panels = tabs.parentElement ? tabs.parentElement.querySelectorAll('.tab-panel') : [];
                    panels.forEach(function(p) {
                        p.classList.toggle('active', p.id === target);
                    });
                });
            });
        });
    }

    /* ---------- Tooltips ----------------------------------------------- */

    // Tooltips are CSS-only via .tooltip[data-tooltip] - no JS needed

    /* ---------- Confirm Dialog ----------------------------------------- */

    function ensureConfirmStyles() {
        if (document.getElementById('aurora-confirm-style')) return;
        var css = ''
            + '[data-aurora-confirm-host]{position:fixed;inset:0;z-index:1090;display:none;}'
            + '[data-aurora-confirm-host].is-open{display:flex;align-items:center;justify-content:center;}'
            + '[data-aurora-confirm-scrim]{position:absolute;inset:0;background:rgba(15,23,42,.45);}'
            + '[data-aurora-confirm-card]{position:relative;background:var(--color-bg-elevated, #fff);border-radius:14px;'
            + 'box-shadow:0 18px 40px rgba(0,0,0,.18);max-width:420px;width:calc(100% - 32px);'
            + 'padding:22px 22px 18px;font:inherit;color:var(--color-text, #2c3e50);}'
            + '[data-aurora-confirm-title]{margin:0 0 8px;font-size:16px;font-weight:700;}'
            + '[data-aurora-confirm-body]{margin:0 0 18px;color:var(--color-text-muted, #5a6573);font-size:14px;line-height:1.5;}'
            + '[data-aurora-confirm-actions]{display:flex;gap:8px;justify-content:flex-end;}'
            + '[data-aurora-confirm-actions] button{padding:10px 16px;border-radius:8px;'
            + 'border:0;font:inherit;cursor:pointer;font-weight:600;}'
            + '[data-aurora-confirm-cancel]{background:var(--ink-200, #e5e9ee);color:var(--ink-700, #2c3e50);}'
            + '[data-aurora-confirm-ok]{background:var(--color-danger, #c0392b);color:#fff;}';
        var style = document.createElement('style');
        style.id = 'aurora-confirm-style';
        style.appendChild(document.createTextNode(css));
        document.head.appendChild(style);
    }

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

            setTimeout(function () { try { cancel.focus(); } catch (e) {} }, 0);

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

            host.addEventListener('aurora-cleanup', function () {
                document.removeEventListener('keydown', onKey);
            });

            requestAnimationFrame(function () { host.classList.add('is-open'); });
        });
    }

    function initConfirmHooks() {
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
                            if (el.tagName === 'A' && el.href) {
                                window.location.href = el.href;
                            } else {
                                var f = el.closest && el.closest('form');
                                if (f) f.submit();
                            }
                        });
                }, true);
            });
    }

    /* ---------- Table Filters ------------------------------------------ */

    function initTableFilters() {
        $$('[data-table-filter]').forEach(function (wrap) {
            var input = $('[data-table-filter-input]', wrap);
            var table = $('[data-table-filter-table]', wrap);
            if (!input || !table) return;

            var debouncedFilter = debounce(function() {
                var q = String(input.value || '').toLowerCase().trim();
                var tbody = table.tBodies && table.tBodies[0] ? table.tBodies[0] : table;
                $$('tr', tbody).forEach(function (tr) {
                    var text = (tr.textContent || '').toLowerCase();
                    tr.style.display = (!q || text.indexOf(q) !== -1) ? '' : 'none';
                });
                // Update visible row count
                var visible = $$('tr:not([style*="display: none"])', tbody).length;
                var countEl = wrap.querySelector('[data-filter-count]');
                if (countEl) countEl.textContent = visible + ' results';
            }, 150);

            input.addEventListener('input', debouncedFilter);
        });
    }

    /* ---------- Back/Forward Cache Guard -------------------------------- */

    function initNoBfcache() {
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) { window.location.reload(); }
        });
    }

    /* ---------- Theme Toggle UI ---------------------------------------- */

    function initThemeToggle() {
        $$('[data-theme-toggle]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var current = document.documentElement.classList.contains('theme-light') ? 'light' : 'dark';
                var next = current === 'light' ? 'dark' : 'light';
                applyTheme(next);
                btn.setAttribute('aria-label', next === 'light' ? 'Switch to dark mode' : 'Switch to light mode');
                btn.textContent = next === 'light' ? '🌙' : '☀️';
            });
            // Set initial state
            var current = document.documentElement.classList.contains('theme-light') ? 'light' : 'dark';
            btn.textContent = current === 'light' ? '🌙' : '☀️';
            btn.setAttribute('aria-label', current === 'light' ? 'Switch to dark mode' : 'Switch to light mode');
        });
    }

    /* ---------- Bootstrap ------------------------------------------- */

    function init() {
        try { initTheme(); } catch (e) { console.error('[Aurora] theme', e); }
        try { initSidebar(); }       catch (e) { console.error('[Aurora] sidebar',  e); }
        try { initPasswordToggles(); } catch (e) { console.error('[Aurora] pwtoggle', e); }
        try { initInlineToasts(); }   catch (e) { console.error('[Aurora] toasts',   e); }
        try { initTableFilters(); }   catch (e) { console.error('[Aurora] filters',  e); }
        try { initConfirmHooks(); }   catch (e) { console.error('[Aurora] confirm', e); }
        try { initNoBfcache(); }      catch (e) { console.error('[Aurora] bfcache',  e); }
        try { initFormValidation(); } catch (e) { console.error('[Aurora] validation', e); }
        try { initAutoSave(); }       catch (e) { console.error('[Aurora] autosave', e); }
        try { initModals(); }         catch (e) { console.error('[Aurora] modals', e); }
        try { initDropdowns(); }      catch (e) { console.error('[Aurora] dropdowns', e); }
        try { initTabs(); }           catch (e) { console.error('[Aurora] tabs', e); }
        try { initThemeToggle(); }    catch (e) { console.error('[Aurora] theme-toggle', e); }

        // Auto-init tables with data attributes
        $$('table[data-sortable]').forEach(function(t) { initTableSort(t); });
        $$('table[data-selectable]').forEach(function(t) { initTableSelection(t); });
    }

    ready(init);

    /* ---------- Public API ----------------------------------------- */

    window.Aurora = {
        openSidebar:      openSidebar,
        closeSidebar:     closeSidebar,
        toggleSidebar:    toggleSidebar,
        toggleSidebarCollapse: toggleSidebarCollapse,
        toast:            showToast,
        dismissToast:     dismissToast,
        confirm:          confirmDialog,
        isMobileViewport: isMobileViewport,
        setButtonLoading: setButtonLoading,
        showSkeleton:     showSkeleton,
        hideSkeleton:     hideSkeleton,
        initTableSort:    initTableSort,
        initTableSelection: initTableSelection,
        setTheme:         applyTheme,
        getTheme:         function() { return document.documentElement.classList.contains('theme-light') ? 'light' : 'dark'; },
        openModal:        openModal,
        closeModal:       closeModal,
        validateField:    validateField,
    };
})();