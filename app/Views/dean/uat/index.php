<?php
/**
 * @var array $surveys
 * @var array $references
 * @var bool $demo
 */
?>
<div class="page-head">
    <div>
        <span class="eyebrow">ISO/IEC 25010 validation</span>
        <h2>User Acceptance Testing</h2>
        <p>Collect independent acceptance ratings against ISO/IEC 25010 characteristics. UAT data is stored separately from student evaluation data.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline-secondary" href="<?= url('dean/reports/view?type=uat') ?>"><i class="bi bi-file-earmark-bar-graph"></i> UAT report</a>
        <a class="btn btn-primary" href="<?= url('dean/uat/create') ?>"><i class="bi bi-plus-lg"></i> New UAT survey</a>
    </div>
</div>

<?php if ($demo): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-database"></i><span><strong>Synthetic demonstration data.</strong> Demo UAT responses are generated for interface testing and are not respondent evidence.</span></div>
<?php endif; ?>

<div class="alert alert-info app-alert"><i class="bi bi-info-circle"></i><span>
    <strong>Interpretation scale:</strong> 4.50–5.00 Very Satisfactory · 3.50–4.49 Satisfactory · 2.50–3.49 Fair · 1.50–2.49 Poor · 1.00–1.49 Very Poor.
    A mean of 4.48 is <strong>Satisfactory</strong>, not Very Satisfactory.
</span></div>

<div class="card mb-4">
    <div class="card-header"><strong>UAT surveys</strong><span class="text-muted small">Invitation-only, single response per respondent</span></div>
    <div class="card-body p-0">
        <?php if (empty($surveys)): ?>
            <div class="empty-state"><i class="bi bi-patch-check"></i>No UAT survey has been created in this data scope.<div class="mt-2"><a class="btn btn-sm btn-primary" href="<?= url('dean/uat/create') ?>">Create the first UAT survey</a></div></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Survey</th>
                        <th>Status</th>
                        <th class="text-center">Statements</th>
                        <th class="text-center">Invited</th>
                        <th class="text-center">Responses</th>
                        <th>Schedule</th>
                        <th>Created by</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($surveys as $survey): ?>
                        <tr>
                            <td>
                                <strong><?= e($survey['title']) ?></strong>
                                <div class="text-tiny text-muted"><?= e(str_limit($survey['description'] ?? '', 80)) ?></div>
                            </td>
                            <td><?= status_badge($survey['status']) ?></td>
                            <td class="text-center"><?= (int) $survey['question_count'] ?></td>
                            <td class="text-center"><?= (int) $survey['respondent_count'] ?></td>
                            <td class="text-center"><?= (int) $survey['response_count'] ?></td>
                            <td class="text-tiny">
                                <?= e(format_date($survey['opens_at'])) ?> – <?= e(format_date($survey['closes_at'])) ?>
                            </td>
                            <td class="text-tiny"><?= e(trim(($survey['first_name'] ?? '') . ' ' . ($survey['last_name'] ?? '')) ?: '—') ?></td>
                            <td class="text-end"><?= display_demo_badge($survey['is_demo'] ?? 0) ?><a class="btn btn-sm btn-outline-primary" href="<?= url('dean/uat/' . (int) $survey['id']) ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header"><strong>How UAT differs from evaluation</strong></div>
            <div class="card-body">
                <ul class="list-clean small">
                    <li class="d-block mb-2"><i class="bi bi-people text-primary"></i> Respondents are invited testers, not students rating their instructor.</li>
                    <li class="d-block mb-2"><i class="bi bi-diagram-3 text-primary"></i> Statements are grouped under six ISO/IEC 25010 characteristics.</li>
                    <li class="d-block mb-2"><i class="bi bi-shield-lock text-primary"></i> Invitation tokens are single-use, hashed at rest and separate from response content.</li>
                    <li class="d-block mb-2"><i class="bi bi-bar-chart text-primary"></i> Results report means, population standard deviations and the configured interpretation.</li>
                    <li class="d-block"><i class="bi bi-journal-text text-primary"></i> Prior manuscript UAT scores, if any, are shown only as labeled reference — never merged with new responses.</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header">
                <strong>Previously reported research reference results</strong>
                <span class="badge badge-warning ms-auto">Reference only</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($references)): ?>
                    <div class="empty-state"><i class="bi bi-journal-x"></i>No prior research reference scores have been recorded in the reference table. New UAT responses will not be combined with unpublished manuscript values.</div>
                <?php else: ?>
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
                <?php endif; ?>
            </div>
            <div class="card-footer data-note">These values are historical manuscript reference points. They are not new UAT data collected through this system and must not be presented as this implementation's results.</div>
        </div>
    </div>
</div>
