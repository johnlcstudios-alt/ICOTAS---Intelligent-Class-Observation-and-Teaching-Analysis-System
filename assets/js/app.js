(function () {
    'use strict';

    // Mobile sidebar
    const sidebar = document.getElementById('appSidebar');
    const toggles = document.querySelectorAll('[data-sidebar-toggle]');
    if (sidebar) {
        toggles.forEach(function (button) {
            button.addEventListener('click', function () {
                sidebar.classList.toggle('open');
                let backdrop = document.querySelector('.sidebar-backdrop');
                if (sidebar.classList.contains('open')) {
                    if (!backdrop) {
                        backdrop = document.createElement('div');
                        backdrop.className = 'sidebar-backdrop';
                        document.body.appendChild(backdrop);
                        backdrop.addEventListener('click', closeSidebar);
                    }
                } else if (backdrop) {
                    backdrop.remove();
                }
            });
        });
    }
    function closeSidebar() {
        if (!sidebar) { return; }
        sidebar.classList.remove('open');
        const backdrop = document.querySelector('.sidebar-backdrop');
        if (backdrop) { backdrop.remove(); }
    }

    // Show / hide password fields
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            const field = document.querySelector(button.getAttribute('data-toggle-password'));
            if (!field) { return; }
            const reveal = field.type === 'password';
            field.type = reveal ? 'text' : 'password';
            button.innerHTML = '<i class="bi ' + (reveal ? 'bi-eye-slash' : 'bi-eye') + '"></i>';
            button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
        });
    });

    // Confirm destructive actions
    document.querySelectorAll('[data-confirm]').forEach(function (element) {
        element.addEventListener('click', function (event) {
            if (!window.confirm(element.getAttribute('data-confirm'))) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        });
    });

    // Copy buttons
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = document.querySelector(button.getAttribute('data-copy'));
            const text = target ? (target.value || target.textContent) : button.getAttribute('data-copy-text');
            if (!text) { return; }
            const done = function () {
                const original = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check2"></i> Copied';
                setTimeout(function () { button.innerHTML = original; }, 1800);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text.trim()).then(done).catch(function () {});
            } else {
                const area = document.createElement('textarea');
                area.value = text.trim();
                document.body.appendChild(area);
                area.select();
                try { document.execCommand('copy'); done(); } catch (e) {}
                area.remove();
            }
        });
    });

    // Dismiss alerts after a while
    document.querySelectorAll('.app-alert').forEach(function (alert) {
        setTimeout(function () {
            if (window.bootstrap && bootstrap.Alert) {
                bootstrap.Alert.getOrCreateInstance(alert).close();
            }
        }, 9000);
    });

    // Tooltips
    if (window.bootstrap) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    // Disable double submit on forms
    document.querySelectorAll('form[data-single-submit]').forEach(function (form) {
        form.addEventListener('submit', function () {
            const button = form.querySelector('button[type="submit"]');
            if (button) {
                setTimeout(function () {
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Working…';
                }, 10);
            }
        });
    });

    // Chart defaults
    if (window.Chart) {
        Chart.defaults.font.family = '"Segoe UI", system-ui, sans-serif';
        Chart.defaults.color = '#667085';
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.boxWidth = 8;
        Chart.defaults.plugins.tooltip.backgroundColor = '#241a1d';
        Chart.defaults.plugins.tooltip.padding = 10;
        Chart.defaults.plugins.tooltip.cornerRadius = 8;
    }

    window.ICOTAS = {
        palette: ['#701c2b', '#c9a227', '#2e7d6b', '#4f6d9a', '#a15c8a', '#b5651d', '#7a8b99'],
        closeSidebar: closeSidebar
    };
})();
