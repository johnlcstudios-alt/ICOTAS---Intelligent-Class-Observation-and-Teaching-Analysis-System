<?php
/**
 * @var array $session
 * @var string $status
 * @var string $qrUrl
 */
?>
<style>
    .qr-poster { max-width: 720px; margin: 0 auto; text-align: center; }
    .qr-poster .poster-brand { font-family: Georgia, serif; font-size: 1.15rem; color: #701c2b; font-weight: 700; }
    .qr-poster .poster-institution { font-size: .8rem; color: #666; letter-spacing: .08em; text-transform: uppercase; }
    .qr-poster h1 { font-family: Georgia, serif; color: #701c2b; font-size: 2rem; margin: 1rem 0 .25rem; }
    .qr-poster .poster-meta { font-size: .95rem; color: #444; }
    .qr-poster .poster-qr { display: inline-block; border: 10px solid #fff; box-shadow: 0 0 0 1px #ddd; margin: 1.2rem 0; }
    .qr-poster .poster-code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 2.4rem; font-weight: 700; letter-spacing: .18em; color: #1c1415; }
    .qr-poster .poster-url { font-size: .72rem; word-break: break-all; color: #555; margin-top: .4rem; }
    .qr-poster .poster-steps { text-align: left; max-width: 470px; margin: 1.4rem auto; font-size: .88rem; }
    .qr-poster .poster-steps li { margin-bottom: .4rem; }
    .qr-poster .poster-footer { border-top: 1px solid #ddd; margin-top: 1.4rem; padding-top: .7rem; font-size: .7rem; color: #777; }
</style>

<div class="qr-poster">
    <div class="poster-brand"><?= e(setting('system_name', 'ICOTAS')) ?></div>
    <div class="poster-institution"><?= e(setting('institution_name', 'ACLC College Tacloban City')) ?></div>

    <h1>Student Evaluation</h1>
    <div class="poster-meta">
        <strong><?= e($session['course_code'] . ' — ' . $session['course_title']) ?></strong><br>
        <?= e($session['instructor_first'] ?? '') ?>
        <?= e($session['first_name'] . ' ' . $session['last_name']) ?> · <?= e($session['section_code']) ?><br>
        <?= e($session['academic_year_name'] . ' · ' . $session['semester_name']) ?>
    </div>

    <?php if ($qrUrl !== ''): ?>
        <div class="poster-qr">
            <div data-qr-url="<?= e($qrUrl) ?>" data-qr-size="260" data-qr-name="<?= e($session['session_code']) ?>"></div>
        </div>
        <div class="poster-code"><?= e($session['session_code']) ?></div>
        <div class="poster-url"><?= e($qrUrl) ?></div>
    <?php else: ?>
        <div class="alert alert-warning my-3">QR token unavailable. Regenerate the token in the session page.</div>
    <?php endif; ?>

    <ol class="poster-steps">
        <li>Connect to the campus network or mobile data.</li>
        <li>Scan the QR code with your phone camera, or open the link above and enter the session code.</li>
        <li>Read each question and answer honestly. Your response is anonymous.</li>
        <li>Submit once. The form closes at <strong><?= e(format_datetime($session['expires_at'])) ?></strong>.</li>
    </ol>

    <div class="poster-footer">
        Session status: <?= e(ucfirst($status)) ?> · Generated <?= e(date('M j, Y g:i A')) ?><br>
        This evaluation is anonymous. Responses are reported only in aggregate; student identities are never attached to answers.
    </div>
</div>

<script src="<?= asset('vendor/qrcode.min.js') ?>"></script>
<script src="<?= asset('js/qr-poster.js') ?>"></script>
