<?php
/** QR code verify page. */
$pageTitle = 'Classroom observation verification';
$pageSubtitle = 'Verify your presence in the classroom by scanning the posted QR code or entering the token manually.';

$labels = $labels ?? rating_labels();
$qrcodeSrc = $qrcodeSrc ?? '';
$token = $token ?? '';
$tokenHint = $tokenHint ?? '';

// Scope the form to the active route; keep the checkout card out of this page.
$showForm = ($token !== '' && $token !== null && $token !== '0');
$tokenUrlEncoded = rawurlencode((string) $token);
?>
<div class="page-tools">
    <a class="btn btn--ghost" href="<?= e(url('/faculty/access')) ?>"><?= icon('list', 16) ?> Courses</a>
</div>

<div class="verify-shell">
    <div class="verify-shell__intro">
        <h1><?= e($pageTitle) ?></h1>
        <p class="muted small"><?= e($pageSubtitle) ?></p>
    </div>

    <?php if ($messages): ?>
        <div class="verify-shell__messages">
            <?php foreach ($messages as $msg): ?>
                <div class="alert alert--<?= e($msg['type'] === 'error' ? 'danger' : ($msg['type'] === 'success' ? 'success' : 'info')) ?>">
                    <span class="alert__icon"><?= icon($msg['type'] === 'error' ? 'alert-circle' : ($msg['type'] === 'success' ? 'check-circle' : 'info'), 18) ?></span>
                    <div class="alert__body"><?= e($msg['text']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="verify-shell__body">
        <div class="verify-shell__scan">
            <div class="scanner-card">
                <div class="scanner-card__header">
                    <h2>Scan the posted QR code</h2>
                    <p class="small muted">Point your camera at the code; the link will be read automatically.</p>
                </div>
                <div class="scanner-card__body">
                    <div id="qr-reader" class="qr-reader"></div>
                    <p class="small muted">ICOTAS does not store or transmit video frames.</p>
                </div>
                <div class="scanner-card__footer">
                    <p class="small muted">A private browser window is recommended so the scanner session stays separate from your main session.</p>
                </div>
            </div>

            <script>
            (function () {
                'use strict';

                var READER_EL = document.getElementById('qr-reader');
                if (!READER_EL) {
                    return;
                }

                var api = window.html5Qrcode;
                if (!api) {
                    // Scanner library not available yet; leave placeholder.
                    return;
                }

                var state = 'idle';
                var scanner = null;

                function startScanner() {
                    if (state === 'scanning' || state === 'decoded') {
                        return;
                    }
                    try {
                        scanner = new api(READER_EL, {
                            fps: 10,
                            qrbox: { width: 250, height: 250 },
                            aspectRatio: 1.0
                        });
                    } catch (e) {
                        return;
                    }

                    scanner.start(
                        function onScan(decodedText) {
                            // Decode the link once, then stop.
                            try {
                                scanner.stop().catch(function () {});
                            } catch (stopErr) {
                                /* ignore */
                            }
                            scanner = null;
                            state = 'decoded';

                            var raw = decodedText;
                            if (!raw) {
                                state = 'scanning';
                                return;
                            }

                            // Submit the decoded token to /qr/verify and redirect on success.
                            var form = new FormData();
                            form.append('token', raw);

                            fetch('/qr/verify', {
                                method: 'POST',
                                body: form,
                                credentials: 'same-origin'
                            }).then(function (res) {
                                if (!res.ok) {
                                    return res.json().then(function (json) {
                                        throw new Error((json && json.error) || res.status + ' request failed');
                                    }).catch(function () {
                                        throw new Error(res.status + ' request failed');
                                    });
                                }
                                return res.json();
                            }).then(function (json) {
                                if (json && json.success) {
                                    var redirect = json.redirect || '/?verified=1';
                                    if (typeof redirect === 'string') {
                                        window.location.href = redirect;
                                    }
                                } else {
                                    throw new Error(json && json.error ? json.error : 'Unexpected response');
                                }
                            }).catch(function (err) {
                                var msg = err && err.message ? err.message : 'Request failed';
                                // Friendly mappings.
                                if (/denied/i.test(msg) || /not found/i.test(msg) || /invalid/i.test(msg)) {
                                    alert('Verification failed: the code was not recognised. Make sure you are scanning the correct classroom code.');
                                } else if (/duplicate/i.test(msg)) {
                                    alert('This code was already used. Choose another code.');
                                } else {
                                    alert(msg);
                                }
                            });
                        },
                        function onScanError(err) {
                            var text = err && err.message ? err.message : '';
                            if (/User denied camera access/i.test(text)) {
                                // Surface the denial once.
                                if (state !== 'denied') {
                                    state = 'denied';
                                    console.warn('Camera access denied.');
                                }
                            } else if (/QR Code camera resource/i.test(text)) {
                                // Transient; keep scanning.
                            } else if (/frame decode failed/i.test(text)) {
                                // Keep scanning.
                            } else if (/Unsupported browser/i.test(text)) {
                                console.warn('Browser does not support scanning.');
                            }
                        },
                        function onError(err) {
                            var text = err && err.message ? err.message : '';
                            if (/User denied camera access/i.test(text)) {
                                state = 'denied';
                            } else if (/Unsupported browser/i.test(text)) {
                                console.warn('Browser does not support scanning.');
                            }
                        }
                    );

                    state = 'scanning';
                }

                // Start scanning once the library is ready.
                if (state === 'idle') {
                    startScanner();
                }

                // Cleanup on navigation.
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
        </div>

        <div class="verify-shell__form">
            <div class="card">
                <div class="card__head"><h2>Enter code manually</h2><p class="small">If you cannot scan the code, type the token shown on the posted QR code.</p></div>
                <div class="card__body">
                    <?php if ($showForm): ?>
                        <form method="post" action="<?= e(url('/qr/verify')) ?>" class="form-grid">
                            <?= csrf_field() ?>
                            <div class="field field--full">
                                <label for="edit-token">Token <span class="required">*</span></label>
                                <input id="edit-token" class="input input--mono" type="text" name="token" value="<?= e($token) ?>" required autocomplete="off">
                                <?php if ($tokenHint): ?>
                                    <p class="muted small"><?= e($tokenHint) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn--primary">Verify</button>
                                <a class="btn btn--ghost" href="<?= e(url('/faculty/qr/scanner')) ?>">Cancel</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="small muted">Use the scanner, or open the access link in a private window.</p>
                        <a class="btn btn--secondary" href="<?= e(url('/faculty/qr/scanner')) ?>"><?= icon('scan', 16) ?> Open scanner</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
