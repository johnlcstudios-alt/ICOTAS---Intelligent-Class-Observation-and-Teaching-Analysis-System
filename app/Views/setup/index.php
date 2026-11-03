<?php
/** One-time setup page. */

// `app/Views/setup/index.php` uses shared data from the controller.
// Expected shared vars:
//  - $setupToken  string|null   — token read from storage/setup_token.txt
//  - $nextSteps   array         — human-friendly next actions
use App\Services\SetupService;

$pageTitle = 'Initial setup';
$pageSubtitle = 'Create the first administrator account and configure the system.';

$setupMode = $setupMode ?? false;
$setupToken = $setupToken ?? null;
$nextSteps = $nextSteps ?? [];

// Only allow verified setups via the controller; do not render provisioning UI here.
$isProvisional = false;
?>
<div class="setup-shell">
    <div class="setup-shell__intro">
        <h1><?= e($pageTitle) ?></h1>
        <p class="muted small"><?= e($pageSubtitle) ?></p>
    </div>

    <div class="setup-shell__card">
        <div class="setup-shell__card-head">
            <h2>System setup</h2>
            <p class="small muted">Complete the steps below to make the system ready for first use.</p>
        </div>

        <div class="setup-shell__card-body">
            <?php if ($setupMode === 'pending'): ?>
                <?php if ($setupToken): ?>
                    <div class="setup-shell__notice">
                        <strong>Setup token found.</strong>
                        <p class="small muted">The installation token is present in <code>storage/setup_token.txt</code>. Enter it below to continue.</p>
                    </div>
                <?php else: ?>
                    <div class="setup-shell__notice setup-shell__notice--warning">
                        <strong>Setup token missing.</strong>
                        <p class="small muted">Run <code>php tools/install.php</code> from the project root to generate the setup token and prepare the required directories.</p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($nextSteps)): ?>
                    <div class="setup-shell__steps">
                        <h3 class="setup-shell__steps-title">Next steps</h3>
                        <ol class="setup-shell__steps-list">
                            <?php foreach ($nextSteps as $step): ?>
                                <li><?= e($step) ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/setup')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="setup-shell__fields">
                        <div class="field field--full">
                            <label for="setup_token">Setup token <span class="required">*</span></label>
                            <input
                                id="setup_token"
                                name="setup_token"
                                type="text"
                                class="input input--mono<?= has_error('setup_token') ? ' is-invalid' : '' ?>"
                                value="<?= e(old('setup_token', '')) ?>"
                                autocomplete="off"
                                required
                            >
                            <?php if (has_error('setup_token')): ?>
                                <span class="field-error"><?= e(error('setup_token')) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="setup-shell__actions">
                        <button type="submit" class="btn btn--primary"><?= icon('check', 16) ?> Continue</button>
                        <a class="btn btn--ghost" href="<?= e(url('/')) ?>">Exit</a>
                    </div>
                </form>
            <?php elseif ($setupMode === 'complete'): ?>
                <div class="setup-shell__notice setup-shell__notice--success">
                    <strong><?= e($pageTitle) ?> complete.</strong>
                    <p class="small muted">You can now log in as the first administrator account.</p>
                    <a class="btn btn--primary mt-2" href="<?= e(url('/login')) ?>">Go to login</a>
                </div>
            <?php else: ?>
                <div class="setup-shell__notice setup-shell__notice--warning">
                    <strong>Setup token required.</strong>
                    <p class="small muted">Enter the setup token to proceed with the initial setup.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
