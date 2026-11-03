/* =====================================================================
   ICOTAS client interactions.
   Progressive enhancement only: every workflow remains usable without JS.
   Designed for a strict CSP (no inline handlers, no eval).
   ===================================================================== */
(function () {
    'use strict';

    var doc = document;
    var meta = doc.querySelector('meta[name="csrf-token"]');
    var csrfToken = meta ? meta.getAttribute('content') : '';

    function ready(fn) {
        if (doc.readyState === 'loading') {
            doc.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    function toArray(nodes) {
        return Array.prototype.slice.call(nodes || []);
    }

    /* ---------------------------------------------------------------
       Sidebar (mobile drawer)
       --------------------------------------------------------------- */
    function initSidebar() {
        var shell = doc.querySelector('[data-app]');
        if (!shell) { return; }
        var overlay = shell.querySelector('.sidebar-overlay');
        var openBtn = shell.querySelector('[data-sidebar-open]');
        var closeBtns = toArray(shell.querySelectorAll('[data-sidebar-close]'));

        function open() {
            shell.classList.add('is-sidebar-open');
            if (overlay) { overlay.hidden = false; }
            if (openBtn) { openBtn.setAttribute('aria-expanded', 'true'); }
            var first = shell.querySelector('.sidebar .nav-link');
            if (first) { first.focus({ preventScroll: true }); }
        }
        function close() {
            shell.classList.remove('is-sidebar-open');
            if (overlay) { overlay.hidden = true; }
            if (openBtn) { openBtn.setAttribute('aria-expanded', 'false'); }
        }

        if (openBtn) { openBtn.addEventListener('click', open); }
        closeBtns.forEach(function (btn) { btn.addEventListener('click', close); });
        doc.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { close(); }
        });
        window.addEventListener('resize', function () {
            if (window.innerWidth > 1024) { close(); }
        });
    }

    /* ---------------------------------------------------------------
       Dropdown menus
       --------------------------------------------------------------- */
    function initDropdowns() {
        var dropdowns = toArray(doc.querySelectorAll('[data-dropdown]'));
        if (!dropdowns.length) { return; }

        function closeAll(except) {
            dropdowns.forEach(function (dropdown) {
                if (dropdown === except) { return; }
                var toggle = dropdown.querySelector('[data-dropdown-toggle]');
                var panel = dropdown.querySelector('[data-dropdown-panel]');
                if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
                if (panel) { panel.hidden = true; }
            });
        }

        dropdowns.forEach(function (dropdown) {
            var toggle = dropdown.querySelector('[data-dropdown-toggle]');
            var panel = dropdown.querySelector('[data-dropdown-panel]');
            if (!toggle || !panel) { return; }

            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                var willOpen = panel.hidden;
                closeAll(dropdown);
                panel.hidden = !willOpen;
                toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                if (willOpen) {
                    var focusTarget = panel.querySelector('a, button, input');
                    if (focusTarget) { focusTarget.focus({ preventScroll: true }); }
                }
            });

            panel.addEventListener('click', function (event) { event.stopPropagation(); });
        });

        doc.addEventListener('click', function () { closeAll(null); });
        doc.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { closeAll(null); }
        });
    }

    /* ---------------------------------------------------------------
       Dismissible alerts
       --------------------------------------------------------------- */
    function initAlerts() {
        toArray(doc.querySelectorAll('[data-dismiss]')).forEach(function (btn) {
            btn.addEventListener('click', function () {
                var alert = btn.closest('.alert');
                if (alert) {
                    alert.style.transition = 'opacity .15s ease';
                    alert.style.opacity = '0';
                    window.setTimeout(function () { alert.remove(); }, 160);
                }
            });
        });
    }

    /* ---------------------------------------------------------------
       Confirmation prompts and print buttons
       --------------------------------------------------------------- */
    function initConfirmations() {
        toArray(doc.querySelectorAll('[data-confirm]')).forEach(function (el) {
            var message = el.getAttribute('data-confirm') || 'Are you sure?';
            if (el.tagName === 'FORM') {
                el.addEventListener('submit', function (event) {
                    if (!window.confirm(message)) { event.preventDefault(); }
                });
                return;
            }
            el.addEventListener('click', function (event) {
                if (!window.confirm(message)) { event.preventDefault(); event.stopPropagation(); }
            });
        });

        toArray(doc.querySelectorAll('[data-print]')).forEach(function (btn) {
            btn.addEventListener('click', function () { window.print(); });
        });
    }

    /* ---------------------------------------------------------------
       Password visibility
       --------------------------------------------------------------- */
    function initPasswordToggles() {
        toArray(doc.querySelectorAll('[data-password-toggle]')).forEach(function (btn) {
            btn.addEventListener('click', function () {
                var field = doc.getElementById(btn.getAttribute('data-password-toggle'));
                if (!field) { return; }
                var show = field.type === 'password';
                field.type = show ? 'text' : 'password';
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                field.focus({ preventScroll: true });
            });
        });
    }

    /* ---------------------------------------------------------------
       Copy-to-clipboard buttons
       --------------------------------------------------------------- */
    function initCopyButtons() {
        toArray(doc.querySelectorAll('[data-copy]')).forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = doc.querySelector(btn.getAttribute('data-copy'));
                if (!target) { return; }
                var text = target.value !== undefined ? target.value : target.textContent.trim();
                var original = btn.getAttribute('data-copy-label') || btn.textContent.trim();

                function done(ok) {
                    btn.textContent = ok ? 'Copied' : 'Copy failed';
                    window.setTimeout(function () { btn.textContent = original; }, 1800);
                }

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(false); });
                    return;
                }
                var range = doc.createRange();
                range.selectNodeContents(target);
                var selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
                try { done(doc.execCommand('copy')); } catch (err) { done(false); }
                selection.removeAllRanges();
            });
        });
    }

    /* ---------------------------------------------------------------
       COF live score summary
       --------------------------------------------------------------- */
    function initCofSummary() {
        var form = doc.querySelector('[data-cof-form]');
        if (!form) { return; }

        var groups = {
            'Teaching Competence': { min: 1, max: 13, output: form.querySelector('[data-cof-teaching]') },
            'Personality': { min: 14, max: 20, output: form.querySelector('[data-cof-personality]') }
        };
        var overall = form.querySelector('[data-cof-overall]');
        var progress = form.querySelector('[data-cof-progress]');
        var answered = form.querySelector('[data-cof-answered]');
        var total = 20;

        function recalc() {
            var values = { 'Teaching Competence': [], 'Personality': [] };
            var count = 0;

            toArray(form.querySelectorAll('input[type="radio"][data-criterion]')).forEach(function (input) {
                if (!input.checked) { return; }
                var number = parseInt(input.getAttribute('data-criterion'), 10);
                var category = number <= 13 ? 'Teaching Competence' : 'Personality';
                values[category].push(parseInt(input.value, 10));
                count++;
            });

            function average(list) {
                if (!list.length) { return null; }
                return list.reduce(function (a, b) { return a + b; }, 0) / list.length;
            }

            var teachingAvg = average(values['Teaching Competence']);
            var personalityAvg = average(values['Personality']);

            if (groups['Teaching Competence'].output) {
                groups['Teaching Competence'].output.textContent = teachingAvg === null ? '—' : teachingAvg.toFixed(2);
            }
            if (groups['Personality'].output) {
                groups['Personality'].output.textContent = personalityAvg === null ? '—' : personalityAvg.toFixed(2);
            }

            var overallAvg = null;
            if (teachingAvg !== null && personalityAvg !== null) {
                overallAvg = ((teachingAvg * 13) + (personalityAvg * 7)) / total;
            }
            if (overall) { overall.textContent = overallAvg === null ? '—' : overallAvg.toFixed(2); }
            if (answered) { answered.textContent = count + ' / ' + total; }
            if (progress) {
                progress.style.width = Math.round((count / total) * 100) + '%';
                progress.setAttribute('aria-valuenow', String(count));
            }

            toArray(form.querySelectorAll('.cof-criterion')).forEach(function (row) {
                var radios = toArray(row.querySelectorAll('input[type="radio"]'));
                var checked = radios.some(function (radio) { return radio.checked; });
                row.classList.toggle('is-missing', !checked);
            });
        }

        form.addEventListener('change', function (event) {
            if (event.target && event.target.matches('input[type="radio"][data-criterion]')) { recalc(); }
        });
        recalc();

        var submitButtons = toArray(form.querySelectorAll('[data-cof-require-complete]'));
        submitButtons.forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                var missing = form.querySelectorAll('.cof-criterion.is-missing').length;
                if (missing > 0) {
                    event.preventDefault();
                    window.alert('Please rate all 20 criteria before submitting. ' + missing + ' item(s) are still missing.');
                    var firstMissing = form.querySelector('.cof-criterion.is-missing');
                    if (firstMissing) { firstMissing.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                }
            });
        });
    }

    /* ---------------------------------------------------------------
       QR credential rendering (Dean preview)
       --------------------------------------------------------------- */
    function initQrPreview() {
        var holder = doc.querySelector('[data-qr-payload]');
        if (!holder) { return; }
        var payload = holder.getAttribute('data-qr-payload');
        var target = doc.getElementById('qr-canvas');
        if (!target || !payload) { return; }

        if (typeof window.QRCode === 'undefined') {
            target.innerHTML = '<p class="muted small">QR image library did not load. Use the raw credential URL below.</p>';
            return;
        }

        target.innerHTML = '';
        try {
            new window.QRCode(target, {
                text: payload,
                width: 260,
                height: 260,
                colorDark: '#3d0f18',
                colorLight: '#ffffff',
                correctLevel: window.QRCode.CorrectLevel.M
            });
        } catch (err) {
            target.innerHTML = '<p class="muted small">The QR image could not be generated. Use the credential URL below.</p>';
        }
    }

    /* ---------------------------------------------------------------
       QR scanner (Faculty)
       --------------------------------------------------------------- */
    function initQrScanner() {
        var reader = doc.getElementById('qr-reader');
        var status = doc.getElementById('scan-status');
        var form = doc.querySelector('[data-qr-manual]');
        if (!reader && !form) { return; }

        var busy = false;
        var scanner = null;

        function setStatus(message, tone) {
            if (!status) { return; }
            status.textContent = message;
            status.classList.remove('is-success', 'is-error');
            if (tone === 'success') { status.classList.add('is-success'); }
            if (tone === 'error') { status.classList.add('is-error'); }
        }

        function submitToken(raw) {
            if (busy) { return; }
            var token = extractToken(raw);
            if (!token) {
                setStatus('The scanned value does not contain a recognisable ICOTAS credential.', 'error');
                return;
            }
            busy = true;
            setStatus('Verifying credential…');

            fetch(reader ? reader.getAttribute('data-verify-url') : form.getAttribute('data-verify-url'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ token: token })
            }).then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (body) {
                    return { ok: response.ok, status: response.status, body: body };
                });
            }).then(function (result) {
                if (result.ok && result.body && result.body.success) {
                    setStatus(result.body.message || 'QR credential verified.', 'success');
                    if (scanner) {
                        try { scanner.clear(); } catch (err) { /* ignore */ }
                    }
                    var redirect = result.body.data && result.body.data.redirect;
                    if (redirect) {
                        window.setTimeout(function () { window.location.assign(redirect); }, 900);
                    }
                    return;
                }
                busy = false;
                var message = (result.body && result.body.message) || 'The credential could not be verified.';
                setStatus(message, 'error');
            }).catch(function () {
                busy = false;
                setStatus('Network error while verifying the credential. Please try again.', 'error');
            });
        }

        function extractToken(raw) {
            var value = String(raw || '').trim();
            if (!value) { return ''; }
            try {
                var url = new URL(value, window.location.origin);
                var token = url.searchParams.get('token');
                if (token) { return token; }
            } catch (err) {
                /* not a URL-like value */
            }
            return /^([a-f0-9]{64}|[A-F0-9]{64})$/i.test(value) ? value : '';
        }

        function startCamera() {
            if (!reader || typeof Html5Qrcode === 'undefined') {
                return;
            }
            if (scanner !== null) { return; }

            setStatus('Opening camera…', 'info');

            try {
                scanner = new Html5Qrcode('qr-reader', {
                    fps: 10,
                    qrbox: { width: 250, height: 250 }
                });
            } catch (err) {
                setStatus('Could not start the scanner. Use the manual entry below.', 'error');
                scanner = null;
                return;
            }

            scanner.start({
                facingMode: 'environment'
            }).then(function () {
                setStatus('Point the camera at your QR credential.', 'info');
            }).catch(function (err) {
                setStatus('Camera access was denied or unavailable. Use the manual entry below.', 'error');
                scanner = null;
            });
        }

        var startButton = doc.getElementById('scan-start');
        if (startButton) {
            startButton.addEventListener('click', function () {
                startCamera();
            });
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                var input = form.querySelector('[data-qr-token-input]');
                if (input) {
                    submitToken(input.value);
                }
                event.preventDefault();
            });
        }
    });
                if (token) { return token; }
            } catch (err) {
                /* not a URL-like value */
            }
            return /^([a-f0-9]{64}|[A-F0-9]{64})$/i.test(value) ? value : '';
        }

        function startCamera() {
            if (!reader || typeof Html5Qrcode === 'undefined') {
                return;
            }
            if (scanner !== null) { return; }

            setStatus('Opening camera…', 'info');

            try {
                scanner = new Html5Qrcode('qr-reader', {
                    fps: 10,
                    qrbox: { width: 250, height: 250 }
                });
            } catch (err) {
                setStatus('Could not start the scanner. Use the manual entry below.', 'error');
                scanner = null;
                return;
            }

            scanner.start({
                facingMode: 'environment'
            }).then(function () {
                setStatus('Point the camera at your QR credential.', 'info');
            }).catch(function (err) {
                setStatus('Camera access was denied or unavailable. Use the manual entry below.', 'error');
                scanner = null;
            });
        }

        var startButton = doc.getElementById('scan-start');
        if (startButton) {
            startButton.addEventListener('click', function () {
                startCamera();
            });
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                var input = form.querySelector('[data-qr-token-input]');
                if (input) {
                    submitToken(input.value);
                }
                event.preventDefault();
            });
        }
    });
                if (token) { return token.trim(); }
            } catch (err) { /* not a URL */ }
            return value.replace(/^token[:=]\s*/i, '').trim();
        }

        if (reader && typeof window.Html5QrcodeScanner !== 'undefined') {
            scanner = new window.Html5QrcodeScanner('qr-reader', {
                fps: 10,
                qrbox: { width: 240, height: 240 },
                rememberLastUsedCamera: true,
                supportedScanTypes: window.Html5QrcodeScanType
                    ? [window.Html5QrcodeScanType.SCAN_TYPE_CAMERA]
                    : undefined
            }, false);
            scanner.render(function (decodedText) { submitToken(decodedText); }, function () { /* ignore per-frame errors */ });
        } else if (reader) {
            setStatus('The camera scanner library could not load. Paste the credential URL below instead.', 'error');
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var input = form.querySelector('input[name="token"]');
                submitToken(input ? input.value : '');
            });
        }
    }

    /* ---------------------------------------------------------------
       Optional auto-submit filters
       --------------------------------------------------------------- */
    function initAutoSubmitFilters() {
        toArray(doc.querySelectorAll('[data-autosubmit]')).forEach(function (field) {
            field.addEventListener('change', function () {
                var form = field.closest('form');
                if (form) { form.submit(); }
            });
        });
    }

    /* ---------------------------------------------------------------
       Charts (Chart.js, vendored; no CDN and no inline scripts)
       --------------------------------------------------------------- */
    function initCharts() {
        var canvases = toArray(doc.querySelectorAll('canvas[data-chart-data]'));
        if (!canvases.length) { return; }
        if (typeof window.Chart === 'undefined') {
            canvases.forEach(function (canvas) {
                var note = doc.createElement('p');
                note.className = 'muted small';
                note.textContent = 'Chart library did not load. The underlying values are shown in the tables on this page.';
                canvas.replaceWith(note);
            });
            return;
        }

        canvases.forEach(function (canvas) {
            var raw = canvas.getAttribute('data-chart-data');
            var type = canvas.getAttribute('data-chart-type') || 'line';
            if (!raw) { return; }
            var config;
            try {
                config = JSON.parse(raw);
            } catch (err) {
                return;
            }

            var isScore = canvas.hasAttribute('data-chart-score');
            var baseOptions = {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, boxHeight: 12, usePointStyle: true, font: { size: 11 } }
                    },
                    tooltip: {
                        backgroundColor: '#172033',
                        padding: 10,
                        titleFont: { size: 12 },
                        bodyFont: { size: 12 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: !isScore,
                        suggestedMin: isScore ? 1 : undefined,
                        suggestedMax: isScore ? 5 : undefined,
                        grid: { color: 'rgba(23,32,51,.07)' },
                        ticks: { font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 }, maxRotation: 45, minRotation: 0 }
                    }
                }
            };

            if (config.options && typeof config.options === 'object') {
                config.options = Object.assign({}, baseOptions, config.options);
            } else {
                config.options = baseOptions;
            }

            try {
                new window.Chart(canvas.getContext('2d'), config);
            } catch (err) {
                /* Leave the canvas blank rather than breaking the page. */
            }
        });
    }

    /* ---------------------------------------------------------------
       Character counters
       --------------------------------------------------------------- */
    function initCounters() {
        toArray(doc.querySelectorAll('[data-counter-for]')).forEach(function (counter) {
            var field = doc.getElementById(counter.getAttribute('data-counter-for'));
            if (!field) { return; }
            var max = parseInt(field.getAttribute('maxlength') || '2000', 10);
            function update() {
                counter.textContent = field.value.length + ' / ' + max;
            }
            field.addEventListener('input', update);
            update();
        });
    }

    /* ---------------------------------------------------------------
       Table row links (optional)
       --------------------------------------------------------------- */
    function initRowLinks() {
        toArray(doc.querySelectorAll('[data-row-href]')).forEach(function (row) {
            row.addEventListener('click', function (event) {
                if (event.target.closest('a, button, input, select, textarea, label, form')) { return; }
                window.location.assign(row.getAttribute('data-row-href'));
            });
            row.style.cursor = 'pointer';
        });
    }

    ready(function () {
        initSidebar();
        initDropdowns();
        initAlerts();
        initConfirmations();
        initPasswordToggles();
        initCopyButtons();
        initCofSummary();
        initQrPreview();
        initQrScanner();
        initAutoSubmitFilters();
        initCharts();
        initCounters();
        initRowLinks();
    });
})();
