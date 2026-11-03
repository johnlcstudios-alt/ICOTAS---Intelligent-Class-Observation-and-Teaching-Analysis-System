<?php
/**
 * Class offerings directory.
 *
 * @var array $rows
 * @var array $filters
 * @var array $pagination
 * @var array $departments
 * @var array $faculty
 * @var array $periods
 */
?>
<div class="page-tools" style="margin-bottom:16px">
    <a class="btn btn--primary" href="<?= e(url('/dean/classes/create')) ?>"><?= icon('plus', 16) ?> Add class offering</a>
    <a class="btn btn--secondary" href="<?= e(url('/dean/observations/create')) ?>"><?= icon('clipboard', 16) ?> New observation</a>
</div>

<section class="card">
    <div class="card__body">
        <form class="filters" method="get" action="<?= e(url('/dean/classes')) ?>">
            <div class="field" style="grid-column:span 2">
                <label for="search">Search</label>
                <input class="input" type="search" id="search" name="search" value="<?= e($filters['search'] ?? '') ?>"
                       placeholder="Subject, section or faculty">
            </div>
            <div class="field">
                <label for="faculty_id">Faculty</label>
                <select class="select" id="faculty_id" name="faculty_id" data-autosubmit>
                    <option value="">All faculty</option>
                    <?php foreach ($faculty as $member): ?>
                        <option value="<?= (int) $member['user_id'] ?>"<?= (string) ($filters['faculty_id'] ?? '') === (string) $member['user_id'] ? ' selected' : '' ?>>
                            <?= e($member['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="department_id">Department</label>
                <select class="select" id="department_id" name="department_id" data-autosubmit>
                    <option value="">All departments</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= (int) $department['department_id'] ?>"<?= (string) ($filters['department_id'] ?? '') === (string) $department['department_id'] ? ' selected' : '' ?>>
                            <?= e($department['department_code']) ?> — <?= e($department['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="period_id">Period</label>
                <select class="select" id="period_id" name="period_id" data-autosubmit>
                    <option value="">All periods</option>
                    <?php foreach ($periods as $period): ?>
                        <option value="<?= (int) $period['period_id'] ?>"<?= (string) ($filters['period_id'] ?? '') === (string) $period['period_id'] ? ' selected' : '' ?>>
                            <?= e($period['academic_year']) ?> · <?= e(semester_abbr((string) $period['semester'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select class="select" id="status" name="status" data-autosubmit>
                    <option value="">All statuses</option>
                    <?php foreach (['Active', 'Archived'] as $status): ?>
                        <option value="<?= e($status) ?>"<?= ($filters['status'] ?? '') === $status ? ' selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filters__actions">
                <button class="btn btn--secondary btn--sm" type="submit"><?= icon('search', 15) ?> Filter</button>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/classes')) ?>">Clear</a>
            </div>
        </form>
    </div>
</section>

<section class="card">
    <div class="card__head">
        <div><h2>Class offerings</h2><p><?= (int) $pagination['total'] ?> offering(s) match the current view.</p></div>
    </div>
    <div class="card__body card__body--flush">
        <?php if ($rows === []): ?>
            <div class="table-empty"><strong>No class offerings found</strong>Create the first class offering to begin scheduling observations.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Subject / section</th><th>Faculty</th><th>Department</th><th>Period</th><th class="num">Observations</th><th class="num">Records</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <span class="row-title"><?= e($row['subject_code']) ?> · <?= e($row['section_name']) ?></span>
                                    <span class="row-sub"><?= e($row['subject_name']) ?></span>
                                </td>
                                <td><?= e($row['faculty_name']) ?></td>
                                <td><?= e($row['department_code']) ?></td>
                                <td class="nowrap"><?= e($row['academic_year']) ?> · <?= e(semester_abbr((string) $row['semester'])) ?></td>
                                <td class="num"><?= (int) $row['observation_count'] ?></td>
                                <td class="num"><?= (int) $row['record_count'] ?></td>
                                <td><?= status_badge($row['status']) ?></td>
                                <td>
                                    <div class="table-actions">
                                        <a class="btn btn--ghost btn--xs" href="<?= e(url('/dean/classes/' . (int) $row['class_id'] . '/edit')) ?>">Edit</a>
                                        <?php if ($row['status'] === 'Active'): ?>
                                            <form method="post" action="<?= e(url('/dean/classes/' . (int) $row['class_id'] . '/archive')) ?>"
                                                  data-confirm="Archive this class? Existing observations and class records are preserved.">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn--ghost btn--xs">Archive</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php require BASE_PATH . '/app/Views/partials/pagination.php'; ?>
        <?php endif; ?>
    </div>
</section>
