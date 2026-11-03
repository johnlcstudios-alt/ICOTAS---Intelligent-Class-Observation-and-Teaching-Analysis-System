<?php
/** Dean QR workspace index. */
$labels = rating_labels();
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Prepared QR codes</h2></div>
            <div class="card__body">
                <?php if ($codes === []): ?>
                    <p class="muted">No QR codes have been generated for this term yet.</p>
                    <p class="muted small">Each code grants classroom verification access for one observation session.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table table--compact">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th class="num">Department</th>
                                    <th>Created</th>
                                    <th>Expiry</th>
                                    <th>Used</th>
                                    <th>Status</th>
                                    <th class="num"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($codes as $code): ?>
                                    <tr>
                                        <td><a href="<?= e(url('/dean/qr/' . (int) $code['id'])) ?>"><?= e($code['token']) ?></a></td>
                                        <td class="num"><?= e($code['department_name']) ?></td>
                                        <td class="nowrap"><?= e(fmt_datetime($code['created_at'])) ?></td>
                                        <td class="nowrap small"><?= e(fmt_datetime($code['expires_at'])) ?></td>
                                        <td class="num"><?= (int) ($code['used_count'] ?? 0) ?></td>
                                        <td><?= status_badge($code['status_label'] ?? 'Expired' , 'label') ?></td>
                                        <td class="text-right"><a class="btn btn--ghost btn--xs" href="<?= e(url('/dean/qr/' . (int) $code['id'] . '/preview')) ?>">Preview</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head">
                <h2>Generate observation QR code</h2>
                <p class="small">Each code is uniquely owned by one department and restricted to its own principals.</p>
            </div>
            <div class="card__body">
                <form method="post" action="<?= e(url('/faculty/qr/create')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="form-grid" style="grid-template-columns: 1fr">
                        <div class="field field--full">
                            <label for="department_id">Department <span class="required">*</span></label>
                            <select id="department_id" name="department_id" class="select" required>
                                <option value="">— Select department —</option>
                                <?php foreach ($departmentOptions as $opt): ?>
                                    <option value="<?= (int) $opt['id'] ?>" <?= (int) ($selectedDepartmentId ?? 0) === (int) $opt['id'] ? 'selected' : '' ?>><?= e($opt['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (has_error('department_id')): ?><span class="field-error"><?= e(error('department_id')) ?></span><?php endif; ?>
                        </div>
                        <div class="field field--full">
                            <label for="academic_year">Academic year <span class="required">*</span></label>
                            <input class="input<?= has_error('academic_year') ? ' is-invalid' : '' ?>" type="text" id="academic_year" name="academic_year" maxlength="20" value="<?= e(old('academic_year', $selectedAcademicYear ?? '')) ?>" required>
                            <?php if (has_error('academic_year')): ?><span class="field-error"><?= e(error('academic_year')) ?></span><?php endif; ?>
                        </div>
                        <div class="field field--full">
                            <label for="semester">Semester <span class="required">*</span></label>
                            <select id="semester" name="semester" class="select" required>
                                <option value="">— Select semester —</option>
                                <option value="1" <?= (int) ($selectedSemester ?? 0) === 1 ? 'selected' : '' ?>>1st Semester</option>
                                <option value="2" <?= (int) ($selectedSemester ?? 0) === 2 ? 'selected' : '' ?>>2nd Semester</option>
                                <option value="3" <?= (int) ($selectedSemester ?? 0) === 3 ? 'selected' : '' ?>>Summer</option>
                            </select>
                            <?php if (has_error('semester')): ?><span class="field-error"><?= e(error('semester')) ?></span><?php endif; ?>
                        </div>
                        <div class="field field--full">
                            <label for="faculty_id">Classroom observer <span class="required">*</span></label>
                            <select id="faculty_id" name="faculty_id" class="select" required>
                                <option value="">— Select observer —</option>
                                <?php foreach ($observerOptions as $opt): ?>
                                    <option value="<?= (int) $opt['user_id'] ?>" <?= (int) ($selectedObserverId ?? 0) === (int) $opt['user_id'] ? 'selected' : '' ?>>
                                        <?= e($opt['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (has_error('faculty_id')): ?><span class="field-error"><?= e(error('faculty_id')) ?></span><?php endif; ?>
                        </div>
                        <div class="field field--full">
                            <label for="expires_at">Expiration date <span class="required">*</span></label>
                            <input class="input<?= has_error('expires_at') ? ' is-invalid' : '' ?>" type="date" id="expires_at" name="expires_at" value="<?= e(old('expires_at', $selectedExpiresAt ?? '')) ?>" required>
                            <?php if (has_error('expires_at')): ?><span class="field-error"><?= e(error('expires_at')) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn--primary"><?= icon('plus', 16) ?> Generate QR code</button>
                        <a class="btn btn--ghost" href="<?= e(url('/dean/qr')) ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Inspector access link</h2></div>
            <div class="card__body">
                <p class="small muted">Share this link with the classroom observer once a code is generated and assigned to their department.</p>
                <div class="stack stack--flush">
                    <input class="input input--mono" type="text" value="<?= e(url('/faculty/access')) ?>" readonly tabindex="-1">
                    <button type="button" class="btn btn--ghost btn--sm" onclick="(function(t){var n=document.createElement('textarea');n.value=t.value;n.style.position='fixed';document.body.appendChild(n);n.select();try{document.execCommand('copy')}catch(e){}document.body.removeChild(n);var p=document.createElement('p');p.className='muted small';p.innerHTML='Copied';document.body.appendChild(p);setTimeout(function(){document.body.removeChild(p)},1500);})(this)"><?= icon('copy', 16) ?> Copy</button>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Scan classroom code</h2></div>
            <div class="card__body">
                <p class="small">Verify an observation from a QR code posted in the classroom. Faculty members confirm they are physically present when they scan it.</p>
                <a class="btn btn--secondary btn--block mt-2" href="<?= e(url('/dean/qr/scanner')) ?>"><?= icon('scan', 16) ?> Open scanner</a>
                <a class="btn btn--ghost btn--block mt-2" href="<?= e(url('/faculty/qr/scanner')) ?>">Open faculty scanner</a>
            </div>
        </section>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>How observation QR works</h2></div>
            <div class="card__body">
                <ol class="small" style="padding-left:18px;line-height:1.7">
                    <li>Dean generates a one-time QR code for a faculty-class session pair.</li>
                    <li>Each code carries a unique random token; only the <?= e('SHA-256') ?> hash is stored.</li>
                    <li>Observer scans the code in the classroom; the system records the session and attaches the check to the observation.</li>
                    <li>Codes expire on their expiry date and cannot be reused for verification after use.</li>
                </ol>
            </div>
        </section>
    </div>
</div>
