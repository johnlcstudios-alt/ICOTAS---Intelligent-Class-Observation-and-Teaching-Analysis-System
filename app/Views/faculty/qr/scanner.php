<?php
/** Faculty QR scanner. */
$pageTitle = 'Scan observation QR code';
$pageSubtitle = 'Point your camera at the QR code posted in the classroom to verify presence.';
?>
<div class="exam-shell">
    <div class="exam-shell__slot">
        <div class="exam-shell__slot-header">
            <h2><?= e($pageTitle) ?></h2>
            <p class="small muted"><?= e($pageSubtitle) ?></p>
        </div>

        <div class="exam-shell__slot-body">
            <div id="scanner-status" class="scanner-status">Stand by.</div>

            <div id="qr-reader" class="qr-reader"></div>

            <div class="exam-shell__slot-foot">
                <p>ICOTAS does not store or transmit video frames. Nothing leaves your browser except the text decoded from a QR code.</p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var STATUS_EL = document.getElementById('scanner-status');
    var READER_EL = document.getElementById('qr-reader');

    var state = 'idle';
    var scanner = null;

    function setStateText(next) {
        STATUS_EL.textContent = next;
    }

    function showError(message) {
        setStateText('Error: ' + message);
    }

    function showSuccess(message) {
        setStateText('Success: ' + message);
    }

    function beginScan() {
        if (state === 'scanning') {
            return;
        }
        if (!window.html5Qrcode) {
            showError('QR scanner library not loaded');
            return;
        }

        try {
            scanner = new window.html5Qrcode(READER_EL);
        } catch (e) {
            showError('Could not create scanner: ' + (e && e.message ? e.message : 'unknown error'));
            return;
        }

        var scanConfig = {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0
        };

        setStateText('Starting camera…');
        scanner.start(scanConfig, function onScan(decodedText, decodedResult) {
            // Decode once, then stop.
            try {
                scanner.stop().catch(function () {});
            } catch (stopErr) {
                /* ignore */
            }
            scanner = null;
            state = 'decoded';

            var raw = decodedText;
            if (!raw) {
                setStateText('Scanning…');
                state = 'scanning';
                return;
            }

            setStateText('Decoded code. Submitting…');

            var payload = {
                token: raw
            };
            var form = new FormData();
            form.append('token', raw);

            fetch('/qr/verify', {
                method: 'POST',
                body: form,
                credentials: 'same-origin'
            }).then(function (res) {
                if (!res.ok) {
                    return res.text().then(function (body) {
                        throw new Error(res.status + ' ' + (body || 'request failed'));
                    });
                }
                return res.json().then(function (json) {
                    if (json && json.success) {
                        window.location.href = json.redirect || '/?verified=1';
                    } else {
                        throw new Error(json.error || 'Unexpected response');
                    }
                });
            }).catch(function (err) {
                var msg = err && err.message ? err.message : 'Request failed';
                // Friendly mapping for common failures.
                if (/denied/i.test(msg) || /not found/i.test(msg) || /invalid/i.test(msg)) {
                    showError('Verification failed: the code was not recognised. Make sure you are scanning the correct classroom code.');
                } else if (/duplicate/i.test(msg)) {
                    showError('This code was already used. Choose another code.');
                } else {
                    showError(msg);
                }
            });
        }, function onScanError(err) {
            // Most frames will fail; only surface real problems.
            var text = err && err.message ? err.message : '';
            if (/User denied camera access/i.test(text)) {
                showError('Camera access was denied. Allow camera use and try again.');
            } else if (/QR Code camera resource/i.test(text)) {
                // Keep scanning; this is transient.
            } else if (/frame decode failed/i.test(text)) {
                // Keep scanning.
            } else if (/Unsupported browser/i.test(text)) {
                showError('Your browser does not support scanning QR codes.');
            }
        }, function onError(err) {
            var text = err && err.message ? err.message : '';
            if (/User denied camera access/i.test(text)) {
                showError('Camera access was denied. Allow camera use and try again.');
            } else if (/Unsupported browser/i.test(text)) {
                showError('Your browser does not support scanning QR codes.');
            } else {
                showError('Could not start scanner: ' + (text || 'unknown error'));
            }
        });

        state = 'scanning';
    }

    // Start immediately so the scanner is ready when the page loads.
    beginScan();

    // Stop when leaving the page.
    window.addEventListener('pagehide', function () {
        try {
            if (scanner) {
                scanner.stop().catch(function () {});
            }
        } catch (e) {
            /* ignore */
        }
        scanner = null;
        state = 'idle';
    });
})();
</script>
