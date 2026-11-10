<?php
/**
 * @var array $sessions
 * @var bool $demo
 */
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Private workspace</span>
        <h2>My evaluations</h2>
        <p>Evaluation sessions assigned to your classes. Results appear only after eligible students submit anonymously.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" href="<?= url('instructor/analytics') ?>"><i class="bi bi-graph-up"></i> My analytics</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Demonstration workspace.</strong> Session records and results are synthetic.</span></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><strong>Assigned sessions</strong><span class="text-muted small"><?= count($sessions) ?> record(s)</span></div>
    <div class="card-body p-0">
        <?php if (empty($sessions)): ?>
            <div class="empty-state"><i class="bi bi-clipboard-data"></i>No evaluation sessions are assigned to you in this data scope.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Session</th><th>Class</th><th>Questionnaire</th><th>Period</th><th>Opened</th><th>Status</th><th class="text-center">Participation</th><th class="text-center">Mean</th><th class="text-end">Results</th></tr></thead>
                    <tbody>
                    <?php foreach ($sessions as $session): ?>
                        <?php $rate = ((int) $session['eligible_count'] > 0) ? ((int) $session['submitted_count'] / (int) $session['eligible_count']) * 100 : null; ?>
                        <tr>
                            <td><code><?= e($session['session_code']) ?></code></td>
                            <td>
                                <?= e($session['course_code']) ?>
                                <div class="text-tiny text-muted"><?= e($session['section_code']) ?> · <?= e($session['title']) ?></div>
                            </td>
                            <td class="text-tiny"><?= e($session['survey_title']) ?><div class="text-muted">v<?= (int) $session['version_number'] ?></div></td>
                            <td class="text-tiny"><?= e($session['academic_year_name'] . ' · ' . $session['semester_name']) ?></td>
                            <td class="text-tiny"><?= e(format_datetime($session['opens_at'])) ?></td>
                            <td><?= status_badge($session['effective_status'] ?? $session['status']) ?></td>
                            <td class="text-center text-tiny"><?= (int) $session['submitted_count'] ?> / <?= (int) $session['eligible_count'] ?><div class="text-muted"><?= e(format_percent($rate)) ?></div></td>
                            <td class="text-center"><?= e(format_rating($session['average_rating'])) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('instructor/evaluations/' . (int) $session['id']) ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-footer data-note">Only aggregate results are shown. Student identities and individual answer sheets are not available to instructors.</div>
</div>
