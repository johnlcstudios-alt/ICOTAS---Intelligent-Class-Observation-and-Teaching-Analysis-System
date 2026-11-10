<?php
/**
 * @var array $data
 * @var int $unread
 * @var bool $selfScan
 * @var bool $demo
 */
use App\Core\Auth;

$available = $data['available'] ?? [];
$history = $data['history'] ?? [];
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Student portal</span>
        <h2>Hello, <?= e(Auth::user()['first_name'] ?? 'Student') ?></h2>
        <p>Evaluate your classes anonymously using the QR code or session code provided by your instructor.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= url('student/scan') ?>"><i class="bi bi-qr-code-scan"></i> Scan QR</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Demonstration account.</strong> Evaluation sessions shown here use synthetic data.</span></div>
<?php endif; ?>

<div class="stat-grid mb-4">
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-unlock"></i></span>
        <div class="stat-label">Available now</div>
        <div class="stat-value"><?= (int) ($data['available_count'] ?? 0) ?></div>
        <div class="stat-note">Open evaluation sessions</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-check2-circle"></i></span>
        <div class="stat-label">Completed</div>
        <div class="stat-value"><?= (int) ($data['completed'] ?? 0) ?></div>
        <div class="stat-note">Submitted evaluations</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-hourglass-split"></i></span>
        <div class="stat-label">Pending</div>
        <div class="stat-value"><?= (int) ($data['pending'] ?? 0) ?></div>
        <div class="stat-note">Invited but not submitted</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-bell"></i></span>
        <div class="stat-label">Notifications</div>
        <div class="stat-value"><?= (int) $unread ?></div>
        <div class="stat-note">Unread messages</div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card mb-4">
            <div class="card-header"><strong>Available evaluations</strong><span class="text-muted small">One response per session</span></div>
            <div class="card-body p-0">
                <?php if (empty($available)): ?>
                    <div class="empty-state"><i class="bi bi-clipboard-check"></i>No evaluation is currently open for you. Scan the QR code shown by your instructor when a session begins.</div>
                <?php else: ?>
                    <div class="list-clean">
                        <?php foreach ($available as $session): ?>
                            <?php $already = ($session['participation_status'] ?? '') === 'submitted'; ?>
                            <div class="d-block">
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <div>
                                        <strong><?= e($session['course_code']) ?> · <?= e($session['section_code']) ?></strong>
                                        <div class="small text-muted"><?= e($session['course_title'] ?? $session['title']) ?> · <?= e(trim($session['first_name'] . ' ' . $session['last_name'])) ?></div>
                                        <div class="text-tiny text-muted">Closes <?= e(format_datetime($session['expires_at'])) ?> · <?= (int) $session['question_count'] ?> question(s)</div>
                                    </div>
                                    <div class="text-end">
                                        <?php if ($already): ?>
                                            <span class="badge badge-success">Submitted</span>
                                        <?php elseif ($selfScan): ?>
                                            <a class="btn btn-sm btn-primary" href="<?= url('student/open/' . (int) $session['id']) ?>"><i class="bi bi-pencil-square"></i> Evaluate</a>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Use QR / session code</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!$selfScan): ?>
                <div class="card-footer data-note">Portal quick-open is disabled. Use the QR code or the session code provided by your instructor.</div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header"><strong>Recent history</strong><a class="ms-auto btn btn-sm btn-outline-primary" href="<?= url('student/history') ?>">View all</a></div>
            <div class="card-body p-0">
                <?php if (empty($history)): ?>
                    <div class="empty-state"><i class="bi bi-clock-history"></i>No evaluation participation records yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead><tr><th>Course</th><th>Survey</th><th>Status</th><th>Submitted</th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($history, 0, 5) as $row): ?>
                                <tr>
                                    <td><?= e($row['course_code']) ?><div class="text-tiny text-muted"><?= e($row['section_code']) ?></div></td>
                                    <td class="small"><?= e($row['survey_title']) ?></td>
                                    <td><?= status_badge($row['status']) ?></td>
                                    <td class="text-tiny"><?= e(format_datetime($row['submitted_at'] ?? null)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card mb-4">
            <div class="card-header"><strong>How your privacy works</strong></div>
            <div class="card-body">
                <ul class="list-clean small">
                    <li class="d-block mb-2"><i class="bi bi-person-x text-primary"></i> Your name and student ID are never written to the response or answer records.</li>
                    <li class="d-block mb-2"><i class="bi bi-key text-primary"></i> A separate participation record ensures one response per session.</li>
                    <li class="d-block mb-2"><i class="bi bi-shield-lock text-primary"></i> Instructors see aggregates and anonymized comments only, and only above the privacy threshold.</li>
                    <li class="d-block"><i class="bi bi-check2-circle text-primary"></i> After submission, this portal shows only that you participated — not your answers.</li>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Need help?</strong></div>
            <div class="card-body">
                <p class="small mb-2">If a QR code does not open, enter the session code manually on the scan page. If you believe you are not on the eligible class roster, contact your instructor or the academic office.</p>
                <a class="btn btn-outline-primary btn-sm" href="<?= url('student/scan') ?>"><i class="bi bi-qr-code"></i> Open scanner</a>
            </div>
        </div>
    </div>
</div>
