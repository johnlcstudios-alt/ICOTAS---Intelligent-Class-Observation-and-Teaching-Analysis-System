<?php
/** Dean QR scanner. */

$pageTitle = 'Classroom observation verification';
$pageSubtitle = 'Scan the QR code posted in the classroom to record the observation session. Only the faculty member whose QR code was issued may complete verification.';
?>
<div class="exam-shell">
    <div class="exam-shell__slot">
        <div class="exam-shell__slot-header">
            <h2><?= e($pageTitle) ?></h2>
            <p class="small"><?= e($pageSubtitle) ?></p>
        </div>

        <div class="exam-shell__slot-body">
            <div id="qr-ordered" class="qr-scanner-inner">
                <div class="qr-scanner-interactor vc vc--column">
                    <div id="qr-reader" class="room-reader" style="width:100%;max-width:30em;height:21em"></div>
                    <div class="room-reader__fallback">
                        <ul class="list list--muted">
                            <li>To verify an observation, tap <strong>Begin scan</strong> to allow your camera, then point the device at the classroom QR code.</li>
                            <li>You may also open the classroom observer access link in an incognito/private window and use its built-in scanner.</li>
                        </ul>
                        <div class="stack">
                            <button type="button" id="qr-start" class="btn btn--primary"><?= icon('scan', 16) ?> Begin scan</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="qr-status" class="qr-scanner-inner" style="display:none">
                <?php if (!empty($verifiedMessage)): ?>
                    <div class="alert alert--success"><span class="alert__icon"><?= icon('check', 18) ?></span><?= e($verifiedMessage) ?></div>
                <?php else: ?>
                    <div id="qr-error"></div>
                <?php endif; ?>
            </div>

            <div id="qr-ended" class="qr-scanner-inner" style="display:none"></div>
        </div>

        <div class="exam-shell__slot-foot">
            <span>Camera access is handled entirely in your browser. ICOTAS does not upload or store video frames.</span>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var STATUS_EL = document.getElementById('qr-status');
    var ERROR_EL = document.getElementById('qr-error');
    var ENDED_EL = document.getElementById('qr-ended');
    var QR_READER = document.getElementById('qr-reader');

    function showSuccess(message) {
        ERROR_EL.innerHTML = '';
        ENDED_EL.style.display = 'none';
        STATUS_EL.style.display = '';
        STATUS_EL.innerHTML = '<div class="alert alert--success"><span class="alert__icon"><?= json_encode($pageTitle === 'Classroom observation verification' ? icon('check', 18) : '') ?></span>' + message + '</div>';
    }

    function showError(message) {
        STATUS_EL.style.display = 'none';
        ERROR_EL.style.display = '';
        ERROR_EL.innerHTML = '<div class="alert alert--error"><span class="alert__icon"><?= json_encode(icon('alert', 18)) ?></span><?= json_encode($pageTitle === 'Classroom observation verification' ? icon('close', 18) : '') ?></span>' + message + '</div>';
    }

    function showEnded() {
        STATUS_EL.style.display = 'none';
        ERROR_EL.style.display = 'none';
        QR_READER.style.display = 'none';
        ENDED_EL.style.display = '';
        ENDED_EL.innerHTML = '<div class="alert alert--muted"><span class="alert__icon"><?= json_encode(icon('check', 18)) ?></span>Scanning stopped.</div>';
    }

    var scanApi = window.html5Qrcode || null;
    if (!scanApi) {
        showError('The QR scanning library did not load. Return to the faculty workspace to verify another observation.');
        return;
    }

    function stopAndClean() {
        var current = window.__qrScanner && window.__qrScanner.getState();
        if (current && 'idle' !== current) {
            try { window.__qrScanner.stop().catch(function () {}); } catch (e) {}
        }
        window.__qrScanner = null;
    }

    function startScan(readerEl) {
        stopAndClean();
        var scanner = new scanApi(readerEl, {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0
        });
        window.__qrScanner = scanner;

        scanner.start(
            function (decodedText) {
                showSuccess('Verification code accepted. Your session has been updated.');
                stopAndClean();
                QR_READER.style.display = 'none';
                ENDED_EL.style.display = '';
                ENDED_EL.innerHTML = '<div class="alert alert--success"><span class="alert__icon"><?= json_encode(icon('check', 18)) ?></span>Verification accepted. The observation session has been updated in ICOTAS.</div>';
            },
            function (err) {
                var check = /^Already scanning/i.test(err && err.message);
                if (!check) {
                    var text = err && err.message ? err.message : String(err);
                    if (/User denied camera access/i.test(text)) {
                        showError('Camera access was denied. Please allow camera use and try again, or use the access link.');
                    } else if (/QR Code camera resource/ig.test(text)) {
                        window.document.title = 'Scanning classroom observation code…';
                        return;
                    } else if (/frame decode failed/i.test(text)) {
                        return;
                    }
                }
            },
            function (err) {
                stopAndClean();
                var text = err && err.message ? err.message : String(err);
                if (/Unsupported browser/i.test(text)) {
                    showError('Your browser does not support the camera stream needed to scan a QR code. Use a camera-capable device.');
                } else if (/User denied camera access/i.test(text)) {
                    showError('Camera access was denied. Please allow camera use and try again.');
                } else {
                    showError(text);
                }
            }
        );
    }

    var startBtn = document.getElementById('qr-start');
    startBtn.addEventListener('click', function () {
        startBtn.disabled = true;
        startBtn.textContent = 'Starting…';
        if (!QR_READER) return;
        startScan(QR_READER);
        startBtn.disabled = false;
        startBtn.textContent = 'Begin scan';
    });

    window.addEventListener('beforeunload', function () {
        stopAndClean();
    });
})();
</script>
