/**
 * Render a QR code into elements marked with data-qr-url using qrcodejs.
 * The URL is never generated client-side; it comes from the server session record.
 */
(function () {
    'use strict';

    function render() {
        var targets = document.querySelectorAll('[data-qr-url]');
        if (!targets.length) {
            return;
        }
        if (typeof window.QRCode === 'undefined') {
            targets.forEach(function (target) {
                target.innerHTML = '<div class="alert alert-warning mb-0">QR library did not load. Use the session link instead.</div>';
            });
            return;
        }
        targets.forEach(function (target) {
            var value = target.getAttribute('data-qr-url');
            if (!value) {
                return;
            }
            var size = parseInt(target.getAttribute('data-qr-size') || '240', 10);
            if (isNaN(size) || size < 120) {
                size = 240;
            }
            target.innerHTML = '';
            new window.QRCode(target, {
                text: value,
                width: size,
                height: size,
                colorDark: '#1c1415',
                colorLight: '#ffffff',
                correctLevel: window.QRCode.CorrectLevel.H
            });

            var download = target.parentNode ? target.parentNode.querySelector('[data-qr-download]') : null;
            if (download) {
                window.setTimeout(function () {
                    var canvas = target.querySelector('canvas');
                    if (canvas) {
                        download.href = canvas.toDataURL('image/png');
                        download.download = 'icotas-qr-' + (target.getAttribute('data-qr-name') || 'session') + '.png';
                        download.classList.remove('d-none');
                    }
                }, 120);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', render);
    } else {
        render();
    }
}());
