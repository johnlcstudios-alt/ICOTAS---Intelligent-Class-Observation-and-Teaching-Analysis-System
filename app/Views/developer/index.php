<?php
/** @var array $invites */
/** @var array|null $generated */
/** @var string $developerKey */
?>
    <div class="container-xl">
        <div class="page-head mb-4">
            <div>
                <span class="eyebrow">Unlinked developer console</span>
                <h2>Dean invitation codes</h2>
                <p>Issue single-purpose codes that let a Dean create their own account. This page is not linked from ICOTAS and must remain private.</p>
            </div>
            <div class="page-actions">
                <a class="btn btn-outline-secondary" href="<?= url('login') ?>"><i class="bi bi-box-arrow-in-right"></i> Sign-in page</a>
            </div>
        </div>

        <?php App\Core\View::partial('partials.flash'); ?>

        <div class="alert alert-danger app-alert">
            <i class="bi bi-shield-exclamation"></i>
            <span><strong>Treat this URL like a password.</strong> Anyone who knows it can create Dean invitation codes. Rotate <code>developer_key</code> in <code>config/local.php</code> if it is exposed.</span>
        </div>

        <?php if (!empty($generated)): ?>
            <div class="card mb-4 border-success">
                <div class="card-header bg-success-subtle"><strong><i class="bi bi-key"></i> New invitation code — copy it now</strong></div>
                <div class="card-body">
                    <p class="mb-2">This is the only time the full code is shown. Only a hash is stored.</p>
                    <div class="input-group input-group-lg mb-2">
                        <input class="form-control font-monospace" id="generatedInvite" type="text" value="<?= e($generated['code']) ?>" readonly>
                        <button class="btn btn-outline-primary" type="button" data-copy="#generatedInvite"><i class="bi bi-clipboard"></i> Copy</button>
                    </div>
                    <div class="small text-muted">
                        Role: Dean · Uses: <?= (int) $generated['max_uses'] ?> · Expires: <?= e(format_datetime($generated['expires_at'])) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header"><strong>Create a code</strong><span class="text-muted small">Dean accounts only</span></div>
                    <div class="card-body">
                        <form method="post" action="<?= url('developer/' . rawurlencode($developerKey)) ?>" data-single-submit>
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label" for="maxUses">Maximum uses</label>
                                <input class="form-control" id="maxUses" name="max_uses" type="number" min="1" max="<?= (int) \App\Services\InviteService::MAX_USES_LIMIT ?>" value="1">
                                <div class="form-text">Use 1 for a single Dean. Values above 1 share one code.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="validDays">Valid for (days)</label>
                                <input class="form-control" id="validDays" name="valid_days" type="number" min="1" max="<?= (int) \App\Services\InviteService::MAX_VALID_DAYS ?>" value="7">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="note">Internal note</label>
                                <input class="form-control" id="note" name="note" maxlength="190" placeholder="For example: Dean, College of Computer Studies">
                                <div class="form-text">Visible only on this page.</div>
                            </div>
                            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-plus-circle"></i> Create invitation code</button>
                        </form>
                    </div>
                </div>
                <div class="card mt-4">
                    <div class="card-body small text-muted">
                        <div class="mb-2"><strong>How it works</strong></div>
                        <ol class="mb-0 ps-3">
                            <li>Create a code and copy it.</li>
                            <li>Send it privately to the intended Dean.</li>
                            <li>The Dean opens <code>/dean-signup</code>, enters the code, and chooses their own password.</li>
                            <li>Dean self-registration must be enabled under Settings → Evaluation access, and demo mode must be off.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header"><strong>Issued codes</strong><span class="text-muted small">Newest first · last 100</span></div>
                    <div class="card-body p-0">
                        <?php if (empty($invites)): ?>
                            <div class="empty-state"><i class="bi bi-key"></i>No invitation codes have been created.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table mb-0 align-middle">
                                    <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Note</th>
                                        <th>Uses</th>
                                        <th>Expires</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($invites as $invite): ?>
                                        <?php
                                        $status = \App\Services\InviteService::status($invite);
                                        $badge = ['active' => 'badge-success', 'used' => 'badge-soft', 'expired' => 'badge-warning', 'revoked' => 'badge-danger'][$status] ?? 'badge-soft';
                                        ?>
                                        <tr>
                                            <td><code><?= e($invite['code_hint']) ?></code></td>
                                            <td class="small"><?= $invite['note'] !== null && $invite['note'] !== '' ? e($invite['note']) : '—' ?></td>
                                            <td><?= (int) $invite['used_count'] ?> / <?= (int) $invite['max_uses'] ?></td>
                                            <td class="small"><?= e(format_datetime($invite['expires_at'])) ?></td>
                                            <td><span class="badge <?= e($badge) ?>"><?= e(ucfirst($status)) ?></span></td>
                                            <td class="text-end">
                                                <?php if ($status === 'active'): ?>
                                                    <form method="post" action="<?= url('developer/' . rawurlencode($developerKey) . '/invites/' . (int) $invite['id'] . '/revoke') ?>" class="d-inline" data-confirm="Revoke this invitation code? It will stop working immediately.">
                                                        <?= csrf_field() ?>
                                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-slash-circle"></i> Revoke</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

