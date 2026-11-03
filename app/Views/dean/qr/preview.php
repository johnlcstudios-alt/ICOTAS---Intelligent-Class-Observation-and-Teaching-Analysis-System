<?php
/**
 * @var int $codeId
 * @var array $code  (possibly empty)
 */

if (empty($code)) {
    $pageTitle = 'QR code not found';
    $pageSubtitle = 'This code may have been rotated or expired.';
} else {
    $pageTitle = 'QR code preview';
    $pageSubtitle = 'Scan from this page to verify the observation, or download the image.';
    $fullPath = 'https://' . (empty($_SERVER['HTTP_HOST']) ? 'localhost' : $_SERVER['HTTP_HOST']) . url('/faculty/access', ['token' => $code['token']]);
}
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2><?= e($pageTitle) ?></h2><p class="small"><?= e($pageSubtitle) ?></p></div>
            <div class="card__body">
                <?php if ($code): ?>
                    <div class="qr-code-card">
                        <img src="<?= e(url('/faculty/qr/' . rawurlencode($code['token']) . '/image')) ?>" alt="QR code" class="qr-code-image" style="max-width:6in;max-height:6in">
                        <div class="qr-code-meta">
                            <div class="qr-code-link">
                                <label for="qr-url">Access link</label>
                                <input id="qr-url" class="input input--mono" type="text" value="<?= e($fullPath) ?>" readonly tabindex="-1">
                                <button type="button" class="btn btn--ghost btn--sm" onclick="(function(){var t=document.getElementById('qr-url');var n=document.createElement('textarea');n.value=t.value;n.style.position='fixed';document.body.appendChild(n);n.select();try{document.execCommand('copy')}catch(e){}document.body.removeChild(n);var p=document.createElement('p');p.className='muted small';p.innerHTML='Copied';document.body.appendChild(p);setTimeout(function(){document.body.removeChild(p)},1500);})()"><?= icon('copy', 16) ?> Copy</button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert--warning">
                        <span class="alert__icon"><?= icon('alert', 18) ?></span>
                        <div class="alert__body">
                            <strong>Code not available.</strong>
                            This code is no longer available for preview — it may have been rotated, expired, or served as an invalid-access token.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Export</h2></div>
            <div class="card__body">
                <?php if ($code): ?>
                    <a class="btn btn--secondary" href="<?= e(url('/faculty/qr/' . rawurlencode($code['token']) . '/image')) ?>" download="icotas-observation-qr.png"><?= icon('download', 16) ?> Download image</a>
                    <a class="btn btn--ghost" href="<?= e(url('/dean/qr')) ?>">Back to codes</a>
                <?php else: ?>
                    <a class="btn btn--ghost" href="<?= e(url('/dean/qr')) ?>">Back to codes</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Generated code details</h2></div>
            <div class="card__body">
                <?php if (!empty($code)): ?>
                    <p class="small muted">The QR code encodes the observer access link above.</p>
                <?php else: ?>
                    <p class="muted">Details not available — this code may have expired or been rotated.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
