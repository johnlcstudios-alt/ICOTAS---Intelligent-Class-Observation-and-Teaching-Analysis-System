<?php
/** Dean reports index. */
?>
<div class="grid grid--sidebar">
    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Printable reports</h2></div>
            <div class="card__body">
                <div class="report-list">
                    <a class="report-item" href="<?= e(url('/dean/reports/observations')) ?>">
                        <div class="report-item__icon"><?= icon('clipboard', 22) ?></div>
                        <div class="report-item__body">
                            <strong>Observation summary report</strong>
                            <div class="muted small">Published classroom observation results with institutional roll-ups.</div>
                        </div>
                        <span class="badge badge--neutral"><?= (int) $publishedCount ?> published</span>
                    </a>

                    <a class="report-item" href="<?= e(url('/dean/reports/observations/export')) ?>">
                        <div class="report-item__icon"><?= icon('download', 22) ?></div>
                        <div class="report-item__body">
                            <strong>Observation summary export (CSV)</strong>
                            <div class="muted small">Same rows as the printable summary, exported as CSV.</div>
                        </div>
                        <span class="badge badge--neutral"><?= (int) $publishedCount ?> published</span>
                    </a>

                    <a class="report-item" href="<?= e(url('/dean/reports/class-records')) ?>">
                        <div class="report-item__icon"><?= icon('layers', 22) ?></div>
                        <div class="report-item__body">
                            <strong>Class record report</strong>
                            <div class="muted small">Student counts and average grades per offering and period.</div>
                        </div>
                        <span class="badge badge--neutral"><?= (int) $classRecordCount ?> records</span>
                    </a>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Faculty history reports</h2></div>
            <div class="card__body">
                <?php if ($facultyCount === 0): ?>
                    <p class="muted">No faculty accounts exist yet.</p>
                <?php else: ?>
                    <p class="small">Individual chronological reports are available from each faculty member's detail page.</p>
                    <a class="btn btn--secondary btn--block mt-2" href="<?= e(url('/dean/users')) ?>">Browse faculty</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>Predictive references</h2></div>
            <div class="card__body">
                <p class="small">Prediction runs are produced in the predictive analytics workspace and are viewable there.</p>
                <a class="btn btn--secondary btn--block mt-2" href="<?= e(url('/dean/predictions')) ?>">Open predictive analytics</a>
            </div>
        </section>
    </div>

    <div class="stack">
        <section class="card">
            <div class="card__head"><h2>Workspace snapshot</h2></div>
            <div class="card__body">
                <ul class="meta-list">
                    <li><span>Published observations</span><strong><?= (int) $publishedCount ?></strong></li>
                    <li><span>Faculty accounts</span><strong><?= (int) $facultyCount ?></strong></li>
                    <li><span>Class records</span><strong><?= (int) $classRecordCount ?></strong></li>
                    <li><span>Latest prediction runs</span><strong><?= (int) $predictionCount ?></strong></li>
                </ul>
            </div>
        </section>

        <section class="card">
            <div class="card__head"><h2>About these reports</h2></div>
            <div class="card__body">
                <p class="small">Every report is generated from the current database contents. Paper and CSV outputs draw from the same queries, so printed and exported figures stay consistent.</p>
                <p class="small">Observation scores are professional judgements recorded through the <?= e(setting('system_name', 'ICOTAS')) ?> Classroom Observation Form and must be interpreted with professional discretion.</p>
            </div>
        </section>
    </div>
</div>
