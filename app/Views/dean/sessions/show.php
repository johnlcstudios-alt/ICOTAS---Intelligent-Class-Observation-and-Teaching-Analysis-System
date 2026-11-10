<?php
/**
 * @var array $session
 * @var string $status
 * @var string $qrUrl
 * @var array $stats
 * @var bool $demo
 */
$active = $status === 'active';
$pageInlineScript = '<script src="' . asset('vendor/qrcode.min.js') . '"></script><script src="' . asset('js/qr-poster.js') . '"></script>';
?>
<div class="page-head">
    <div>
        <span class="eyebrow">QR evaluation session</span>
        <h2><code><?= e($session['session_code']) ?></code></h2>
        <p class="mb-1"><?= e($session['title']) ?> · <?= e($session['course_code']) ?> / <?= e($session['section_code']) ?> · <?= e(trim(($session['first_name'] ?? '') . ' ' . ($session['last_name'] ?? ''))) ?></p>
        <p class="mb-0"><?= status_badge($status) ?> <?= display_demo_badge($session['is_demo'] ?? 0) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/sessions') ?>"><i class="bi bi-arrow-left"></i> Sessions</a>
        <a class="btn btn-outline-primary" href="<?= url('dean/sessions/' . (int) $session['id'] . '/poster') ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Print poster</a>
        <?php if ($active && $qrUrl !== ''): ?>
            <a class="btn btn-success" href="<?= e($qrUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Open form</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Demonstration session.</strong> Responses remain inside the synthetic demo dataset.</span></div>
<?php endif; ?>

<div class="stat-grid mb-4">
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-people"></i></span>
        <div class="stat-label">Eligible students</div>
        <div class="stat-value"><?= (int) $stats['eligible'] ?></div>
        <div class="stat-note">Enrolled in this class</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-check2-square"></i></span>
        <div class="stat-label">Submitted</div>
        <div class="stat-value"><?= (int) $stats['submitted'] ?></div>
        <div class="stat-note">One response per student</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-inbox"></i></span>
        <div class="stat-label">Anonymous responses</div>
        <div class="stat-value"><?= (int) $stats['responses'] ?></div>
        <div class="stat-note">No student identifier stored</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-graph-up"></i></span>
        <div class="stat-label">Participation rate</div>
        <div class="stat-value"><?= e(format_percent($stats['rate'])) ?></div>
        <div class="stat-note">Submitted ÷ eligible</div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><strong>Scan code</strong><span class="text-muted small">Students scan or enter the code</span></div>
            <div class="card-body text-center">
                <?php if ($qrUrl !== ''): ?>
                    <div class="qr-frame mb-3" data-qr-url="<?= e($qrUrl) ?>" data-qr-size="230" data-qr-name="<?= e($session['session_code']) ?>"></div>
                    <div class="d-inline-flex gap-2">
                        <a class="btn btn-sm btn-outline-primary d-none" href="#" data-qr-download><i class="bi bi-download"></i> Download PNG</a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e($qrUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Open link</a>
                    </div>
                    <div class="mt-3">
                        <div class="text-tiny text-uppercase text-muted">Session code</div>
                        <div class="display-6 fw-bold" style="font-family: ui-monospace, monospace; letter-spacing: .12em;"><?= e($session['session_code']) ?></div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label small">Evaluation link</label>
                        <div class="input-group input-group-sm">
                            <input class="form-control" type="text" value="<?= e($qrUrl) ?>" readonly id="evaluationLink">
                            <button class="btn btn-outline-secondary" type="button" data-copy="#evaluationLink"><i class="bi bi-clipboard"></i> Copy</button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">This session's QR token cannot be recovered. Regenerate the token to issue a new link.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><strong>Session details</strong></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">Assignment</dt><dd class="col-sm-8"><?= e($session['title']) ?></dd>
                    <dt class="col-sm-4">Questionnaire</dt><dd class="col-sm-8"><?= e($session['survey_title']) ?> · version <?= (int) $session['version_number'] ?></dd>
                    <dt class="col-sm-4">Instructor</dt><dd class="col-sm-8"><?= e($session['first_name'] . ' ' . $session['last_name']) ?></dd>
                    <dt class="col-sm-4">Course / section</dt><dd class="col-sm-8"><?= e($session['course_code'] . ' — ' . $session['course_title']) ?> / <?= e($session['section_code']) ?></dd>
                    <dt class="col-sm-4">Academic period</dt><dd class="col-sm-8"><?= e($session['academic_year_name'] . ' · ' . $session['semester_name']) ?></dd>
                    <dt class="col-sm-4">Opens</dt><dd class="col-sm-8"><?= e(format_datetime($session['opens_at'])) ?></dd>
                    <dt class="col-sm-4">Expires</dt><dd class="col-sm-8"><?= e(format_datetime($session['expires_at'])) ?></dd>
                    <dt class="col-sm-4">Created</dt><dd class="col-sm-8"><?= e(format_datetime($session['created_at'])) ?></dd>
                </dl>
            </div>
            <div class="card-footer">
                <div class="d-flex flex-wrap gap-2 justify-content-end">
                    <?php if (in_array($status, ['scheduled', 'active'], true)): ?>
                        <form method="post" action="<?= url('dean/sessions/' . (int) $session['id'] . '/regenerate') ?>" data-confirm="Regenerate the QR token? All previously printed codes and links will stop working.">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi bi-arrow-repeat"></i> Regenerate token</button>
                        </form>
                    <?php endif; ?>
                    <?php if (!in_array($status, ['closed', 'archived'], true)): ?>
                        <form method="post" action="<?= url('dean/sessions/' . (int) $session['id'] . '/close') ?>" data-confirm="Close this session? Students will no longer be able to submit.">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-x-circle"></i> Close session</button>
                        </form>
                        <form method="post" action="<?= url('dean/sessions/' . (int) $session['id'] . '/revoke') ?>" data-confirm="Revoke the QR link permanently? The printed code will stop working and the session will close.">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-dark" type="submit"><i class="bi bi-slash-circle"></i> Revoke link</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($status !== 'archived'): ?>
                        <form method="post" action="<?= url('dean/sessions/' . (int) $session['id'] . '/archive') ?>" data-confirm="Archive this session? Response records remain available for analytics.">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-ghost" type="submit"><i class="bi bi-archive"></i> Archive</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="alert alert-info app-alert mt-3 mb-0">
            <i class="bi bi-shield-lock"></i>
            <span><strong>Participation is tracked separately from answers.</strong> The response tables contain no student identifier. The participation table records only that a student submitted, so duplicate responses can be blocked.</span>
        </div>
    </div>
</div>
