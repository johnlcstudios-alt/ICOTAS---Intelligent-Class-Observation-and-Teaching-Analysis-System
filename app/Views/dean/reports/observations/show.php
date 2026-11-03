<?php
/** Single observation printable report. */

$labels = rating_labels();
$group = $report['grouped'];
?>
<div class="report-body">
    <div class="report-body__header">
        <div>
            <span class="eyebrow">Observation report</span>
            <h2><?= e($report['observation']['faculty_name']) ?></h2>
            <div class="muted small">
                <?= e($report['observation']['employee_no'] ?? '') ?>
                · <?= e($report['observation']['department_name']) ?>
            </div>
        </div>
        <div class="report-body__meta">
            <div class="meta-list">
                <li><span>Observation #</span><strong><?= (int) $report['observation']['observation_id'] ?></strong></li>
                <li><span>Academic year</span><strong><?= e($report['observation']['academic_year']) ?></strong></li>
                <li><span>Semester</span><strong><?= e($report['observation']['semester']) ?></strong></li>
                <li><span>Observation date</span><strong><?= e(fmt_date($report['observation']['observation_date'])) ?></strong></li>
                <li><span>Lesson topic</span><strong><?= e($report['observation']['lesson_topic']) ?></strong></li>
                <li><span>Subject / section</span><strong><?= e($report['observation']['subject_code']) ?> · <?= e($report['observation']['section_name']) ?></strong></li>
                <li><span>Observer</span><strong><?= e($report['observation']['observer_name']) ?></strong></li>
            </div>
        </div>
    </div>

    <div class="report-body__scores">
        <div class="stat-grid" style="grid-template-columns:1fr 1fr 1fr">
            <div class="stat stat--flat">
                <div class="stat__label">Teaching Competence</div>
                <div class="stat__value"><?= e(score($report['observation']['teaching_competence_avg'])) ?></div>
                <div class="stat__meta"><?= count($group['Teaching Competence']) ?> criteria</div>
            </div>
            <div class="stat stat--flat">
                <div class="stat__label">Personality</div>
                <div class="stat__value"><?= e(score($report['observation']['personality_avg'])) ?></div>
                <div class="stat__meta"><?= count($group['Personality']) ?> criteria</div>
            </div>
            <div class="stat stat--flat">
                <div class="stat__label">Overall</div>
                <div class="stat__value"><?= e(score($report['observation']['overall_avg'])) ?></div>
                <div class="stat__meta">20 criteria</div>
            </div>
        </div>
    </div>

    <div class="report-body__criteria">
        <div class="report-body__section">
            <h3>Teaching Competence</h3>
            <div class="report-body__table">
                <?php foreach ($group['Teaching Competence'] as $item): ?>
                    <div class="report-body__row">
                        <span class="report-body__num"><?= (int) $item['number'] ?></span>
                        <span class="report-body__text"><?= e($item['text']) ?></span>
                        <span class="report-body__score"><?= e(score($item['score'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="report-body__section">
            <h3>Personality</h3>
            <div class="report-body__table">
                <?php foreach ($group['Personality'] as $item): ?>
                    <div class="report-body__row">
                        <span class="report-body__num"><?= (int) $item['number'] ?></span>
                        <span class="report-body__text"><?= e($item['text']) ?></span>
                        <span class="report-body__score"><?= e(score($item['score'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($report['comments'])): ?>
        <div class="report-body__section">
            <h3>Ratee comments</h3>
            <div class="report-body__comments">
                <?php foreach ($report['comments'] as $comment): ?>
                    <div class="report-body__comment">
                        <div class="report-body__comment-meta">
                            <span class="muted small">Faculty · <?= e(fmt_datetime($comment['created_at'])) ?></span>
                        </div>
                        <p><?= e($comment['comment_text']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($report['amendments'])): ?>
        <div class="report-body__section">
            <h3>Amendment history</h3>
            <div class="report-body__table report-body__table--compact">
                <?php foreach ($report['amendments'] as $amendment): ?>
                    <div class="report-body__row">
                        <span class="report-body__text"><?= e($amendment['reason']) ?></span>
                        <span class="report-body__meta"><?= e($amendment['changed_by_name']) ?> · <?= e(fmt_datetime($amendment['created_at'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
