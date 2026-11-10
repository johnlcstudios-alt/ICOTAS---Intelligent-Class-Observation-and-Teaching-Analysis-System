<?php
/**
 * @var array $sessions
 * @var array $filters
 * @var array $assignments
 * @var bool $demo
 */
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Field data collection</span>
        <h2>QR evaluation sessions</h2>
        <p>Each session issues a cryptographically random QR link, enrolls eligible students and enforces a single anonymous response per student.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= url('dean/sessions/create') ?>"><i class="bi bi-qr-code"></i> Open session</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Demonstration scope.</strong> Session links work and remain isolated from live data.</span></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= url('dean/sessions') ?>" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="sessionSearch">Search</label>
                <input class="form-control" type="search" id="sessionSearch" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Session code, assignment, course, instructor">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="sessionAssignment">Assignment</label>
                <select class="form-select" id="sessionAssignment" name="assignment_id">
                    <option value="">All assignments</option>
                    <?php foreach ($assignments as $assignment): ?>
                        <option value="<?= (int) $assignment['id'] ?>" <?= selected($filters['assignment_id'] ?? '', $assignment['id']) ?>><?= e(str_limit($assignment['title'], 60)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="sessionStatus">Status</label>
                <select class="form-select" id="sessionStatus" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (['scheduled', 'active', 'expired', 'closed', 'archived'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= selected($filters['status'] ?? '', $status) ?>><?= e(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary" href="<?= url('dean/sessions') ?>" aria-label="Reset filters"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Sessions</strong><span class="text-muted small"><?= count($sessions) ?> record(s)</span></div>
    <div class="card-body p-0">
        <?php if (empty($sessions)): ?>
            <div class="empty-state"><i class="bi bi-qr-code"></i><h3>No QR sessions found</h3><p class="text-muted">Create a session from an evaluation assignment to begin.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Session</th><th>Assignment</th><th>Class</th><th>Window</th><th class="text-center">Eligible</th><th class="text-center">Submitted</th><th class="text-center">Rate</th><th class="text-center">Avg</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($sessions as $session): ?>
                        <?php
                        $eligible = (int) $session['eligible_count'];
                        $submitted = (int) $session['submitted_count'];
                        $rate = $eligible > 0 ? round($submitted / $eligible * 100, 1) : 0.0;
                        ?>
                        <tr>
                            <td>
                                <a class="fw-semibold" href="<?= url('dean/sessions/' . (int) $session['id']) ?>"><code><?= e($session['session_code']) ?></code></a>
                                <div class="text-tiny text-muted"><?= e($session['survey_title']) ?> · v<?= (int) $session['version_number'] ?></div>
                            </td>
                            <td class="small"><?= e(str_limit($session['title'], 45)) ?></td>
                            <td class="small"><?= e($session['course_code']) ?><br><span class="text-muted"><?= e($session['section_code']) ?></span></td>
                            <td class="small"><?= e(format_datetime($session['opens_at'])) ?><br><span class="text-muted">to <?= e(format_datetime($session['expires_at'])) ?></span></td>
                            <td class="text-center"><?= $eligible ?></td>
                            <td class="text-center"><?= $submitted ?></td>
                            <td class="text-center"><?= e(format_percent($rate)) ?></td>
                            <td class="text-center"><?= e(format_rating($session['average_rating'])) ?></td>
                            <td><?= status_badge((string) $session['status']) ?></td>
                            <td class="text-end">
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= url('dean/sessions/' . (int) $session['id']) ?>" title="Open session"><i class="bi bi-box-arrow-up-right"></i></a>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= url('dean/sessions/' . (int) $session['id'] . '/poster') ?>" target="_blank" rel="noopener" title="Printable poster"><i class="bi bi-file-earmark-image"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
