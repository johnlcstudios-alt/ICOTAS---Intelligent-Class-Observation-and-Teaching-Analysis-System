<?php
/**
 * @var array $history
 */
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Student portal</span>
        <h2>My evaluation history</h2>
        <p>Record of evaluation sessions you were eligible to complete. Submitted answers are never displayed here because responses are anonymous.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= url('student/scan') ?>"><i class="bi bi-qr-code-scan"></i> Scan QR</a>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Participation history</strong><span class="text-muted small">Up to 100 records</span></div>
    <div class="card-body p-0">
        <?php if (empty($history)): ?>
            <div class="empty-state"><i class="bi bi-clock-history"></i>No evaluation participation records yet. Your history appears after you are included in an evaluation session.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Session</th><th>Course</th><th>Instructor</th><th>Period</th><th>Window</th><th>Status</th><th>Submitted</th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $row): ?>
                        <tr>
                            <td><code><?= e($row['session_code']) ?></code><div class="text-tiny text-muted"><?= e($row['survey_title']) ?></div></td>
                            <td><?= e($row['course_code']) ?><div class="text-tiny text-muted"><?= e($row['section_code']) ?></div></td>
                            <td class="text-tiny"><?= e(trim($row['first_name'] . ' ' . $row['last_name'])) ?></td>
                            <td class="text-tiny"><?= e($row['academic_year_name']) ?><div class="text-muted"><?= e($row['semester_name']) ?></div></td>
                            <td class="text-tiny"><?= e(format_datetime($row['opens_at'])) ?><div class="text-muted">to <?= e(format_datetime($row['expires_at'])) ?></div></td>
                            <td><?= status_badge($row['status']) ?></td>
                            <td class="text-tiny"><?= e(format_datetime($row['submitted_at'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-footer data-note">This history is linked to your account for participation tracking only. It does not link to the anonymous response content.</div>
</div>
