<?php
/** Faculty profile index. */

$profile = $profile ?? [];
$predictions = $predictions ?? [];
$stats = $stats ?? [];
$isSelf = $isSelf ?? false;

$fullName = ($profile['full_name'] ?? '') ?: '—';
$email = ($profile['email'] ?? '') ?: '—';
$employeeId = ($profile['employee_id'] ?? '') ?: '—';
$department = ($profile['department_name'] ?? '') ?: '—';
$role = ($profile['role'] ?? '') ?: '—';
$lastActive = $profile['last_active'] ?? null;
$avatarUrl = $profile['avatar_url'] ?? null;

$statsOverall = $stats['overall'] ?? null;
$statsTeaching = $stats['teaching'] ?? null;
$statsPersonality = $stats['personality'] ?? null;
?>
<div class="page-tools">
    <a class="btn btn--ghost" href="<?= e(url('/faculty/scenarios')) ?>"><?= icon('list', 16) ?> Evaluations</a>
</div>

<div class="profile-page">
    <div class="profile-page__header">
        <div class="profile-page__avatar">
            <img src="<?= e($avatarUrl ?: asset('images/default-avatar.png')) ?>" alt="Profile photo" class="profile-page__img">
        </div>
        <div class="profile-page__info">
            <h1 class="profile-page__name"><?= e($fullName) ?></h1>
            <p class="profile-page__meta">
                <?php if ($employeeId !== '—'): ?>
                    <span class="profile-page__id"><?= e($employeeId) ?></span>
                <?php endif; ?>
                <?php if ($department !== '—'): ?>
                    <span class="profile-page__dept"><?= e($department) ?></span>
                <?php endif; ?>
                <?php if ($role !== '—'): ?>
                    <span class="profile-page__role"><?= e($role) ?></span>
                <?php endif; ?>
            </p>
            <?php if ($lastActive): ?>
                <p class="profile-page__last-active muted small">Last active <?= e(fmt_datetime($lastActive)) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid--sidebar mt-3">
        <div class="stack">
            <section class="card">
                <div class="card__head"><h2>Account</h2></div>
                <div class="card__body">
                    <dl class="profile-dl">
                        <dt>Email</dt>
                        <dd><?= e($email) ?></dd>
                        <dt>Employee ID</dt>
                        <dd><?= e($employeeId) ?></dd>
                        <dt>Department</dt>
                        <dd><?= e($department) ?></dd>
                        <dt>Role</dt>
                        <dd><?= e($role) ?></dd>
                        <dt>Last active</dt>
                        <dd><?= $lastActive ? e(fmt_datetime($lastActive)) : '—' ?></dd>
                    </dl>
                </div>
            </section>

            <section class="card">
                <div class="card__head"><h2>Observations summary</h2></div>
                <div class="card__body">
                    <div class="stat-grid stat-grid--wide">
                        <div class="stat stat--flat">
                            <div class="stat__label">Overall average</div>
                            <div class="stat__value"><?= e(score($statsOverall)) ?></div>
                            <div class="stat__meta">of <?= e(score(5)) ?></div>
                        </div>
                        <div class="stat stat--flat">
                            <div class="stat__label">Teaching Competence</div>
                            <div class="stat__value"><?= e(score($statsTeaching)) ?></div>
                            <div class="stat__meta">average</div>
                        </div>
                        <div class="stat stat--flat">
                            <div class="stat__label">Personality</div>
                            <div class="stat__value"><?= e(score($statsPersonality)) ?></div>
                            <div class="stat__meta">average</div>
                        </div>
                        <div class="stat stat--flat">
                            <div class="stat__label">Published evaluations</div>
                            <div class="stat__value"><?= (int) ($stats['published'] ?? 0) ?></div>
                            <div class="stat__meta">finalized only</div>
                        </div>
                    </div>
                </div>
            </section>

            <?php if (!empty($predictions)): ?>
                <section class="card">
                    <div class="card__head"><h2>Predictions summary</h2></div>
                    <div class="card__body">
                        <div class="predictions-summary">
                            <?php if (empty($predictions)): ?>
                                <p class="muted">No predictions yet.</p>
                            <?php else: ?>
                                <?php foreach ($predictions as $p): ?>
                                    <div class="prediction-item">
                                        <div class="prediction-item__stat">
                                            <div class="prediction-item__label"><?= e($p['label'] ?? 'Prediction') ?></div>
                                            <div class="prediction-item__value"><?= e(score($p['score'] ?? 0)) ?></div>
                                            <div class="prediction-item__meta muted small"><?= e($p['sub'] ?? '') ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <div class="stack">
            <section class="card">
                <div class="card__head"><h2>Quick actions</h2></div>
                <div class="card__body">
                    <a class="btn btn--primary btn--block" href="<?= e(url('/faculty/scenarios/create')) ?>"><?= icon('plus', 16) ?> New evaluation</a>
                    <a class="btn btn--secondary btn--block" href="<?= e(url('/faculty/trends')) ?>"><?= icon('chart-line', 16) ?> View trends</a>
                    <a class="btn btn--ghost btn--block" href="<?= e(url('/faculty/profile/edit')) ?>"><?= icon('edit', 16) ?> Edit profile</a>
                </div>
            </section>

            <?php if ($isSelf): ?>
                <section class="card">
                    <div class="card__head"><h2>Support</h2></div>
                    <div class="card__body">
                        <a class="btn btn--secondary btn--block" href="<?= e(url('/account')) ?>"><?= icon('cog', 16) ?> Account settings</a>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</div>
