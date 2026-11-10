<?php
/**
 * @var array $survey
 * @var array $questions
 * @var array $respondents
 * @var array $links
 * @var array $summary
 * @var array $references
 * @var array $characteristics
 * @var string $latestLink
 * @var bool $demo
 */
$characteristicsSummary = $summary['characteristics'] ?? [];
$questionSummary = array_values(array_filter($summary['questions'] ?? [], static fn ($row) => (int) $row['rating_count'] > 0));
$pageScripts = array_merge((array) ($pageScripts ?? []), ['charts.js']);
$means = array_map(static fn ($row) => $row['mean'] !== null ? (float) $row['mean'] : 0, $characteristicsSummary);
$labels = array_map(static fn ($row) => $row['characteristic'], $characteristicsSummary);
$nextStatus = $survey['status'] === 'draft' ? 'active' : ($survey['status'] === 'active' ? 'closed' : 'active');
$nextLabel = $survey['status'] === 'draft' ? 'Activate survey' : ($survey['status'] === 'active' ? 'Close survey' : 'Reopen survey');
$opensValue = !empty($survey['opens_at']) ? date('Y-m-d\TH:i', strtotime((string) $survey['opens_at'])) : '';
$closesValue = !empty($survey['closes_at']) ? date('Y-m-d\TH:i', strtotime((string) $survey['closes_at'])) : '';
?>
<div class="page-head">
    <div>
        <span class="eyebrow">ISO/IEC 25010 validation</span>
        <h2><?= e($survey['title']) ?></h2>
        <p class="mb-1"><?= e($survey['description'] ?: 'No description provided.') ?></p>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <?= status_badge($survey['status']) ?>
            <span class="badge badge-soft"><?= count($questions) ?> statements</span>
            <span class="badge badge-soft"><?= (int) $summary['overall']['respondents'] ?> submitted respondent(s)</span>
            <?= display_demo_badge($survey['is_demo'] ?? 0) ?>
        </div>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/uat') ?>"><i class="bi bi-arrow-left"></i> All UAT surveys</a>
        <a class="btn btn-outline-primary" href="<?= url('dean/reports/view?type=uat&uat_survey_id=' . (int) $survey['id']) ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-bar-graph"></i> UAT report</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Synthetic demonstration UAT.</strong> Results below are generated for interface testing, not acceptance evidence.</span></div>
<?php endif; ?>

<?php if ($latestLink): ?>
    <div class="alert alert-success app-alert">
        <i class="bi bi-link-45deg"></i>
        <span>
            <strong>New single-use invitation link.</strong> Share it directly with the invited respondent; it is shown only in this session.
            <span class="d-block mt-1"><code id="latestUatLink" class="text-break"><?= e($latestLink) ?></code></span>
        </span>
        <button class="btn btn-sm btn-outline-success ms-auto" type="button" data-copy="#latestUatLink"><i class="bi bi-clipboard"></i> Copy</button>
    </div>
<?php endif; ?>

<div class="stat-grid mb-4">
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-list-check"></i></span>
        <div class="stat-label">Statements</div>
        <div class="stat-value"><?= count($questions) ?></div>
        <div class="stat-note">Across six characteristics</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-envelope-paper"></i></span>
        <div class="stat-label">Invited</div>
        <div class="stat-value"><?= count($respondents) ?></div>
        <div class="stat-note">Single-use invitations</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-check2-circle"></i></span>
        <div class="stat-label">Submitted</div>
        <div class="stat-value"><?= (int) $summary['overall']['respondents'] ?></div>
        <div class="stat-note"><?= (int) $summary['overall']['rating_count'] ?> ratings recorded</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="bi bi-award"></i></span>
        <div class="stat-label">Composite mean</div>
        <div class="stat-value"><?= e(format_rating($summary['overall']['mean'])) ?></div>
        <div class="stat-note"><?= e(interpret_uat($summary['overall']['mean'])) ?> · SD <?= e(format_rating($summary['overall']['sd_pop'])) ?></div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header"><strong>ISO/IEC 25010 characteristic results</strong><span class="text-muted small">Descriptive means</span></div>
            <div class="card-body">
                <?php if (empty($characteristicsSummary) || (int) $summary['overall']['rating_count'] === 0): ?>
                    <div class="empty-state"><i class="bi bi-bar-chart"></i>No UAT responses have been submitted yet.</div>
                <?php else: ?>
                    <div class="chart-box mb-3"><canvas id="uatCharacteristicChart" data-chart="bar" aria-label="UAT characteristic means"></canvas></div>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead><tr><th>Characteristic</th><th class="text-center">Mean</th><th class="text-center">SD (population)</th><th class="text-center">Ratings</th><th>Interpretation</th></tr></thead>
                            <tbody>
                            <?php foreach ($characteristicsSummary as $row): ?>
                                <tr>
                                    <td><?= e($row['characteristic']) ?></td>
                                    <td class="text-center"><strong><?= e(format_rating($row['mean'])) ?></strong></td>
                                    <td class="text-center"><?= e(format_rating($row['sd_pop'])) ?></td>
                                    <td class="text-center"><?= (int) $row['rating_count'] ?></td>
                                    <td><?= e($row['interpretation']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="data-note mt-3 mb-0">Thresholds: 4.50–5.00 Very Satisfactory; 3.50–4.49 Satisfactory; 2.50–3.49 Fair; 1.50–2.49 Poor; 1.00–1.49 Very Poor.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card mb-4">
            <div class="card-header"><strong>Survey lifecycle</strong></div>
            <div class="card-body">
                <form method="post" action="<?= url('dean/uat/' . (int) $survey['id'] . '/status') ?>" class="mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="<?= e($nextStatus) ?>">
                    <button class="btn btn-sm <?= $nextStatus === 'closed' ? 'btn-outline-danger' : 'btn-primary' ?>" type="submit" data-confirm="Change this UAT survey status?"><?= e($nextLabel) ?></button>
                </form>
                <form method="post" action="<?= url('dean/uat/' . (int) $survey['id'] . '/dates') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label small" for="uatOpens">Opens</label>
                        <input class="form-control form-control-sm" id="uatOpens" name="opens_at" type="datetime-local" value="<?= e($opensValue) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small" for="uatCloses">Closes</label>
                        <input class="form-control form-control-sm" id="uatCloses" name="closes_at" type="datetime-local" value="<?= e($closesValue) ?>">
                    </div>
                    <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-calendar-check"></i> Save schedule</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><strong>Invite a respondent</strong></div>
            <div class="card-body">
                <form method="post" action="<?= url('dean/uat/' . (int) $survey['id'] . '/respondents') ?>" data-single-submit>
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label small" for="respondentName">Name <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm" id="respondentName" name="name" type="text" maxlength="150" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small" for="respondentType">Respondent type</label>
                        <input class="form-control form-control-sm" id="respondentType" name="respondent_type" type="text" list="respondentTypes" maxlength="80" value="IT Professional">
                        <datalist id="respondentTypes">
                            <option value="IT Professional">
                            <option value="Faculty">
                            <option value="Student">
                            <option value="Administrator">
                            <option value="Industry Expert">
                        </datalist>
                    </div>
                    <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-person-plus"></i> Create invitation</button>
                    <p class="data-note mb-0 mt-2">The invitation link is single-use and stored hashed. The plain link is revealed only once after creation.</p>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <strong>Statements</strong>
        <span class="text-muted small">Question-level results appear after responses are submitted</span>
        <button class="btn btn-sm btn-outline-primary ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#uatAddQuestion"><i class="bi bi-plus-lg"></i> Add statement</button>
    </div>
    <div class="collapse" id="uatAddQuestion">
        <div class="card-body border-bottom">
            <form method="post" action="<?= url('dean/uat/' . (int) $survey['id'] . '/questions') ?>" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-md-4">
                    <label class="form-label small" for="questionCharacteristic">Characteristic</label>
                    <select class="form-select form-select-sm" id="questionCharacteristic" name="characteristic" required>
                        <?php foreach ($characteristics as $characteristic): ?>
                            <option value="<?= e($characteristic) ?>"><?= e($characteristic) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small" for="questionOrder">Order</label>
                    <input class="form-control form-control-sm" id="questionOrder" name="order_index" type="number" min="1" value="<?= count($questions) + 1 ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small" for="questionText">Statement</label>
                    <input class="form-control form-control-sm" id="questionText" name="question_text" type="text" maxlength="500" required>
                </div>
                <div class="col-12"><button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-plus-lg"></i> Add statement</button></div>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($questions)): ?>
            <div class="empty-state"><i class="bi bi-list-check"></i>No statements yet. Add statements before activating the survey.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>#</th><th>Statement</th><th>Characteristic</th><th class="text-end">Remove</th></tr></thead>
                    <tbody>
                    <?php foreach ($questions as $question): ?>
                        <tr>
                            <td><?= (int) $question['order_index'] ?></td>
                            <td><?= e($question['question_text']) ?></td>
                            <td><span class="badge badge-soft"><?= e($question['characteristic']) ?></span></td>
                            <td class="text-end">
                                <form method="post" action="<?= url('dean/uat/' . (int) $survey['id'] . '/questions/' . (int) $question['id'] . '/delete') ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit" data-confirm="Remove this UAT statement? Statements with responses cannot be deleted."><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><strong>Respondents and invitations</strong><span class="text-muted small">Only submitted invitations count toward results</span></div>
    <div class="card-body p-0">
        <?php if (empty($respondents)): ?>
            <div class="empty-state"><i class="bi bi-envelope-paper"></i>No respondents have been invited yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Name</th><th>Type</th><th>Status</th><th>Invitation link</th><th>Submitted</th></tr></thead>
                    <tbody>
                    <?php foreach ($respondents as $respondent): ?>
                        <?php $link = $links[(int) $respondent['id']] ?? ''; ?>
                        <tr>
                            <td><?= e($respondent['name']) ?></td>
                            <td><span class="badge badge-soft"><?= e($respondent['respondent_type']) ?></span></td>
                            <td><?= status_badge($respondent['status']) ?></td>
                            <td>
                                <?php if ($respondent['status'] === 'submitted'): ?>
                                    <span class="text-muted small">Consumed</span>
                                <?php elseif ($link !== ''): ?>
                                    <div class="d-flex align-items-center gap-1">
                                        <code class="text-tiny text-break" id="uatLink<?= (int) $respondent['id'] ?>"><?= e($link) ?></code>
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-copy="#uatLink<?= (int) $respondent['id'] ?>" title="Copy link"><i class="bi bi-clipboard"></i></button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">Link unavailable</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-tiny"><?= e(format_datetime($respondent['submitted_at'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-footer data-note">Invitation links are single-use and should be sent through a private channel. A submitted link cannot be reused. Regenerating links is intentionally not offered because it would bypass the single-response guarantee; create a new invitation if a respondent loses access before submitting.</div>
</div>

<?php if (!empty($questionSummary)): ?>
    <div class="card mb-4">
        <div class="card-header"><strong>Question-level results</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Characteristic</th><th>Statement</th><th class="text-center">N</th><th class="text-center">Mean</th><th class="text-center">SD</th></tr></thead>
                    <tbody>
                    <?php foreach ($questionSummary as $row): ?>
                        <tr>
                            <td><span class="badge badge-soft"><?= e($row['characteristic']) ?></span></td>
                            <td><?= e($row['question_text']) ?></td>
                            <td class="text-center"><?= (int) $row['rating_count'] ?></td>
                            <td class="text-center"><?= e(format_rating($row['mean'])) ?></td>
                            <td class="text-center"><?= e(format_rating($row['sd'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($references)): ?>
    <div class="card">
        <div class="card-header"><strong>Previously reported research reference results</strong><span class="badge badge-warning ms-auto">Not new UAT data</span></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Characteristic</th><th class="text-center">Mean</th><th class="text-center">SD</th><th>Interpretation</th><th>Source</th></tr></thead>
                    <tbody>
                    <?php foreach ($references as $reference): ?>
                        <tr>
                            <td><?= e($reference['characteristic']) ?></td>
                            <td class="text-center"><?= e(format_rating($reference['mean_score'])) ?></td>
                            <td class="text-center"><?= e(format_rating($reference['sd_score'])) ?></td>
                            <td><?= e($reference['interpretation']) ?></td>
                            <td class="text-tiny"><?= e($reference['source_label']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer data-note">Reference values are historical manuscript results, kept separate from new implementation data.</div>
    </div>
<?php endif; ?>

<?php if (!empty($labels)): ?>
    <script type="application/json" id="uatCharacteristicChart_data"><?= json_encode(['labels' => $labels, 'values' => $means, 'label' => 'Mean rating', 'max' => 5], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php endif; ?>
