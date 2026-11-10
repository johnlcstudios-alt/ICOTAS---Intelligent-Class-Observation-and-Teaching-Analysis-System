<?php
/**
 * @var bool $selfScan
 */
$pageScripts = array_merge((array) ($pageScripts ?? []), ['qr-scanner.js']);
$pageInlineScript = '<script src="' . e(asset('vendor/jsQR.min.js')) . '"></script>';
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Student portal</span>
        <h2>Scan evaluation QR</h2>
        <p>Point your camera at the QR code shown by your instructor, or enter the session code manually.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('student/dashboard') ?>"><i class="bi bi-house"></i> Portal</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><strong>Camera scanner</strong><span class="badge badge-soft ms-auto">On-device decoding</span></div>
            <div class="card-body text-center">
                <div class="qr-scanner-frame mx-auto mb-3">
                    <video id="qrVideo" playsinline muted aria-label="Camera preview for QR scanning"></video>
                    <div class="qr-scanner-overlay" aria-hidden="true"></div>
                </div>
                <div class="d-flex flex-wrap justify-content-center gap-2 mb-2">
                    <button class="btn btn-primary" id="qrStart" type="button"><i class="bi bi-camera-video"></i> Start camera</button>
                    <button class="btn btn-outline-secondary d-none" id="qrStop" type="button"><i class="bi bi-stop-circle"></i> Stop</button>
                </div>
                <p class="small text-muted mb-0" id="qrStatus" role="status" aria-live="polite">Camera is off. Your video never leaves this device; QR decoding happens in the browser.</p>
            </div>
            <div class="card-footer data-note">Camera access requires HTTPS or localhost. If the camera is unavailable, use the session code below.</div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header"><strong>Enter session code</strong></div>
            <div class="card-body">
                <form method="post" action="<?= url('student/join') ?>" data-single-submit>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="sessionCode">Session code</label>
                        <input class="form-control form-control-lg text-uppercase" id="sessionCode" name="session_code" type="text" maxlength="20" required autocomplete="off" spellcheck="false" placeholder="e.g. ABC123">
                        <div class="form-text">The code is printed on the QR poster or shown on the instructor's screen.</div>
                    </div>
                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right"></i> Open evaluation</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Before you start</strong></div>
            <div class="card-body">
                <ul class="small mb-0 ps-3">
                    <li class="mb-1">You must be enrolled in the class and evaluation period.</li>
                    <li class="mb-1">Only one response is accepted per evaluation session.</li>
                    <li class="mb-1">Your answers are not linked to your name or student ID.</li>
                    <li class="mb-1">Submit before the session closes; expired links cannot be reopened.</li>
                    <?php if (!$selfScan): ?><li>Portal quick-open is disabled, so use this scanner or the session code.</li><?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<canvas id="qrCanvas" class="d-none" aria-hidden="true"></canvas>
