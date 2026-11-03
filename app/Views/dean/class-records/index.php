<?php
/** Dean class records index. */
$labels = rating_labels();
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Class records</h2><p class="small">Current records, with class record status, counts, and average grades.</p></div>
            <div class="card__body">
                <form method="get" action="<?= e(url('/dean/class-records')) ?>" class="table-filter-top">
                    <div class="table-filter-top__row">
                        <div class="filter-row filter-row--small">
                            <div class="field field--compact">
                                <label for="filter_faculty_id">Faculty</label>
                                <select id="filter_faculty_id" name="faculty_id" class="select">
                                    <option value="">All faculty</option>
                                    <?php foreach ($facultyOptions as $opt): ?>
                                        <option value="<?= (int) $opt['id'] ?>" <?= (int) ($selectedFilters['faculty_id'] ?? 0) === (int) $opt['id'] ? 'selected' : '' ?>><?= e($opt['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field field--compact">
                                <label for="filter_department_id">Department</label>
                                <select id="filter_department_id" name="department_id" class="select">
                                    <option value="">All departments</option>
                                    <?php foreach ($departmentOptions as $opt): ?>
                                        <option value="<?= (int) $opt['id'] ?>" <?= (int) ($selectedFilters['department_id'] ?? 0) === (int) $opt['id'] ? 'selected' : '' ?>><?= e($opt['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field field--compact">
                                <label for="filter_class_id">Class</label>
                                <select id="filter_class_id" name="class_id" class="select">
                                    <option value="">All classes</option>
                                    <?php foreach ($classOptions as $opt): ?>
                                        <option value="<?= (int) $opt['id'] ?>" <?= (int) ($selectedFilters['class_id'] ?? 0) === (int) $opt['id'] ? 'selected' : '' ?>><?= e($opt['subject_code'] . ' / ' . $opt['section_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field field--compact">
                                <label for="filter_period_id">Period</label>
                                <select id="filter_period_id" name="period_id" class="select">
                                    <option value="">All periods</option>
                                    <?php foreach ($periodOptions as $opt): ?>
                                        <option value="<?= (int) $opt['id'] ?>" <?= (int) ($selectedFilters['period_id'] ?? 0) === (int) $opt['id'] ? 'selected' : '' ?>><?= e($opt['label_full']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field field--compact">
                                <label for="filter_academic_year">Academic year</label>
                                <input class="input input--sm<?= has_error('academic_year') ? ' is-invalid' : '' ?>" type="text" id="filter_academic_year" name="academic_year" maxlength="20" value="<?= e($selectedFilters['academic_year'] ?? '') ?>" placeholder="e.g. 2025–2026">
                            </div>
                            <div class="field field--compact">
                                <label for="filter_semester">Semester</label>
                                <select id="filter_semester" name="semester" class="select">
                                    <option value="">All semesters</option>
                                    <option value="1" <?= (string) ($selectedFilters['semester'] ?? '') === '1' ? 'selected' : '' ?>>1st Semester</option>
                                    <option value="2" <?= (string) ($selectedFilters['semester'] ?? '') === '2' ? 'selected' : '' ?>>2nd Semester</option>
                                    <option value="3" <?= (string) ($selectedFilters['semester'] ?? '') === '3' ? 'selected' : '' ?>>Summer</option>
                                </select>
                            </div>
                            <div class="field field--compact">
                                <label for="filter_record_status">Status</label>
                                <select id="filter_record_status" name="record_status" class="select">
                                    <option value="">All statuses</option>
                                    <option value="Unfinalized" <?= ($selectedFilters['record_status'] ?? '') === 'Unfinalized' ? 'selected' : '' ?>>Unfinalized</option>
                                    <option value="Finalized" <?= ($selectedFilters['record_status'] ?? '') === 'Finalized' ? 'selected' : '' ?>>Finalized</option>
                                    <option value="Expired" <?= ($selectedFilters['record_status'] ?? '') === 'Expired' ? 'selected' : '' ?>>Expired</option>
                                    <option value="Void" <?= ($selectedFilters['record_status'] ?? '') === 'Void' ? 'selected' : '' ?>>Void</option>
                                </select>
                            </div>
                            <div class="field field--compact">
                                <label for="filter_search">Search</label>
                                <input class="input input--sm<?= has_error('search') ? ' is-invalid' : '' ?>" type="text" id="filter_search" name="search" maxlength="100" value="<?= e($selectedFilters['search'] ?? '') ?>" placeholder="Subject / section / topic">
                            </div>
                        </div>
                        <div class="table-filter-top__actions">
                            <button type="submit" class="btn btn--primary btn--sm"><?= icon('filter', 16) ?> Filter</button>
                            <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/class-records')) ?>">Clear</a>
                        </div>
                    </div>
                </form>

                <div class="table-wrap">
                    <table class="table table--compact">
                        <thead>
                            <tr>
                                <th class="num"></th>
                                <th>Subject</th>
                                <th>Section</th>
                                <th class="num">Total</th>
                                <th class="num">Passed</th>
                                <th class="num">Failed</th>
                                <th class="num">Avg grade</th>
                                <th>Faculty</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th class="num"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td class="text-right"><?= (int) $row['academic_year_sort'] ?></td>
                                    <td><?= e($row['subject_code']) ?></td>
                                    <td><?= e($row['section_name']) ?></td>
                                    <td class="num"><?= (int) $row['total_students'] ?></td>
                                    <td class="num"><?= (int) $row['passed_count'] ?></td>
                                    <td class="num"><?= (int) $row['failed_count'] ?></td>
                                    <td class="num"><?= e(score($row['average_grade'])) ?></td>
                                    <td><?= e($row['faculty_name']) ?></td>
                                    <td><a href="<?= e(url('/dean/departments/' . (int) $row['department_id'])) ?>"><?= e($row['department_name']) ?></a></td>
                                    <td><?= status_badge($row['record_status']) ?></td>
                                    <td class="num">
                                        <a class="btn btn--ghost btn--xs" href="<?= e(url('/dean/class-records/' . (int) $row['id'])) ?>"><?= icon('eye', 16) ?></a>
                                        <a class="btn btn--ghost btn--xs" href="<?= e(url('/dean/class-records/' . (int) $row['id'] . '/edit')) ?>"><?= icon('pencil', 16) ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <?php if (empty($rows) && empty($selectedFilters)): ?>
            <section class="card">
                <div class="card__body table-empty"><strong>No class records yet.</strong> Create one to start recording grades.</div>
            </section>
        <?php endif; ?>

        <?php if ($pagination['total'] > 0): ?>
            <div class="stack">
                <div class="pagination-wrap">
                    <span class="muted small"><?= (int) $pagination['offset'] + 1 ?>–<?= min((int) $pagination['offset'] + (int) $pagination['per_page'], (int) $pagination['total']) ?> of <?= (int) $pagination['total'] ?></span>
                    <?php if ((int) $pagination['page'] > 1): ?>
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/class-records?page=' . ((int) $pagination['page'] - 1))) ?>"><?= icon('chevron-left', 16) ?> Prev</a>
                    <?php endif; ?>
                    <?php if ((int) $pagination['page'] < (int) $pagination['last_page']): ?>
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('/dean/class-records?page=' . ((int) $pagination['page'] + 1))) ?>">Next <?= icon('chevron-right', 16) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Quick actions</h2></div>
            <div class="card__body">
                <a class="btn btn--primary btn--block" href="<?= e(url('/dean/class-records/create')) ?>"><?= icon('plus', 16) ?> Add class record</a>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Summary</h2></div>
            <div class="card__body">
                <ul class="meta-list">
                    <li><span>Classes</span><strong><?= count($classOptions) ?></strong></li>
                    <li><span>Departments</span><strong><?= count($departmentOptions) ?></strong></li>
                    <li><span>Periods</span><strong><?= count($periodOptions) ?></strong></li>
                    <li><span>Total class records</span><strong><?= (int) $pagination['total'] ?></strong></li>
                </ul>
            </div>
        </section>
    </div>
</div>
