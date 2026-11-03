<?php
/** Dean observations index. */
$labels = rating_labels();
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Observations</h2><p class="small">Published and finalized classroom observation records.</p></div>
            <div class="card__body">
                <form method="get" action="<?= e(url('/dean/observations')) ?>" class="form-top">
                    <div class="form-top__row">
                        <div class="filter-row">
                            <div class="field field--small">
                                <label for="f_department_id">Department</label>
                                <select id="f_department_id" name="department_id" class="select">
                                    <option value="">All departments</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= (int) $d['id'] ?>" <?= (int) ($filters['department_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field field--small">
                                <label for="f_period_id">Period</label>
                                <select id="f_period_id" name="period_id" class="select">
                                    <option value="">All periods</option>
                                    <?php foreach ($periods as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>" <?= (int) ($filters['period_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field field--small">
                                <label for="f_academic_year">Academic year</label>
                                <input class="input input--sm<?= has_error('academic_year') ? ' is-invalid' : '' ?>" type="text" id="f_academic_year" name="academic_year" maxlength="20" value="<?= e($filters['academic_year'] ?? '') ?>" placeholder="e.g. 2025–2026">
                            </div>
                            <div class="field field--small">
                                <label for="f_status">Status</label>
                                <select id="f_status" name="status" class="select">
                                    <option value="">All statuses</option>
                                    <option value="Published" <?= ($filters['status'] ?? '') === 'Published' ? 'selected' : '' ?>>Published</option>
                                    <option value="Finalized" <?= ($filters['status'] ?? '') === 'Finalized' ? 'selected' : '' ?>>Finalized</option>
                                </select>
                            </div>
                            <div class="field field--small">
                                <label for="f_search">Search</label>
                                <input class="input input--sm<?= has_error('search') ? ' is-invalid' : '' ?>" type="text" id="f_search" name="search" maxlength="100" value="<?= e($filters['search'] ?? '') ?>" placeholder="Faculty / subject / section">
                            </div>
                        </div>
                        <div class="form-top__actions">
                            <button type="submit" class="btn btn--primary btn--sm"><?= icon('filter', 16) ?> Filter</button>
                            <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/observations')) ?>">Clear</a>
                        </div>
                    </div>
                </form>

                <div class="table-wrap">
                    <table class="table table--compact">
                        <thead>
                            <tr>
                                <th class="num"></th>
                                <th>Faculty</th>
                                <th>Subject / section</th>
                                <th>Academic year</th>
                                <th>Semester</th>
                                <th>Date</th>
                                <th class="num">Teaching</th>
                                <th class="num">Personality</th>
                                <th class="num">Overall</th>
                                <th>Status</th>
                                <th class="num"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($observations as $o): ?>
                                <tr>
                                    <td class="text-right"><?= (int) $o['id'] ?></td>
                                    <td><?= e($o['faculty_name']) ?></td>
                                    <td><?= e($o['subject_code']) ?> · <?= e($o['section_name']) ?></td>
                                    <td><?= e($o['academic_year']) ?></td>
                                    <td><?= e(semester_abbr((string) $o['semester'])) ?></td>
                                    <td class="nowrap"><?= e(fmt_date($o['observation_date'])) ?></td>
                                    <td class="num"><?= e(score($o['teaching_competence_avg'])) ?></td>
                                    <td class="num"><?= e(score($o['personality_avg'])) ?></td>
                                    <td class="num"><?= e(score($o['overall_avg'])) ?></td>
                                    <td><?= status_badge($o['status']) ?></td>
                                    <td class="text-right">
                                        <a class="btn btn--ghost btn--xs" href="<?= e(url('/dean/observations/' . (int) $o['id'])) ?>"><?= icon('eye', 16) ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <?php if ($observations === [] && empty($filters)): ?>
            <section class="card">
                <div class="card__body table-empty"><strong>No observations yet.</strong> Start by creating one.</div>
            </section>
        <?php endif; ?>

        <?php if ($pagination['total'] > 0): ?>
            <div class="stack">
                <div class="pagination-wrap">
                    <span class="muted small"><?= (int) $pagination['offset'] + 1 ?>–<?= min((int) $pagination['offset'] + (int) $pagination['per_page'], (int) $pagination['total']) ?> of <?= (int) $pagination['total'] ?></span>
                    <?php if ((int) $pagination['page'] > 1): ?>
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/observations?page=' . ((int) $pagination['page'] - 1))) ?>"><?= icon('chevron-left', 16) ?> Prev</a>
                    <?php endif; ?>
                    <?php if ((int) $pagination['page'] < (int) $pagination['last_page']): ?>
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/observations?page=' . ((int) $pagination['page'] + 1))) ?>">Next <?= icon('chevron-right', 16) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Quick actions</h2></div>
            <div class="card__body">
                <a class="btn btn--primary btn--block" href="<?= e(url('/dean/observations/create')) ?>"><?= icon('plus', 16) ?> New observation</a>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Summary</h2></div>
            <div class="card__body">
                <ul class="meta-list">
                    <li><span>Faculties</span><strong><?= count($faculties) ?></strong></li>
                    <li><span>Departments</span><strong><?= count($departments) ?></strong></li>
                    <li><span>Periods</span><strong><?= count($periods) ?></strong></li>
                    <li><span>Observations shown</span><strong><?= (int) $pagination['total'] ?></strong></li>
                </ul>
            </div>
        </section>
    </div>
</div>
