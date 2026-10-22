<?php
declare(strict_types=1);

$pdo = db();
expire_registration_invitations($pdo);
$newInvitation = pull_created_invitation();
$invitationValues = aq_form_values('create_invitation', ['email' => '', 'expires_in_hours' => '48']);
$invitations = $pdo
    ->query(
        "SELECT i.invitation_id, i.email, i.role, i.created_at, i.expires_at,
                i.used_at, i.revoked_at, i.status,
                creator.full_name AS created_by_name,
                used_user.full_name AS used_by_name
         FROM user_invitations i
         LEFT JOIN users creator ON creator.user_id = i.created_by
         LEFT JOIN users used_user ON used_user.user_id = i.used_by
         ORDER BY i.created_at DESC, i.invitation_id DESC",
    )
    ->fetchAll();
?>
<header class="page-header">
    <div>
        <p class="eyebrow mb-1">Administrator only</p>
        <h1 class="section-title mb-1">Registration invitations</h1>
        <p class="text-secondary mb-0">Create email-specific, single-use Staff or Administrator registration codes.</p>
    </div>
</header>

<?php if ($newInvitation): ?>
    <section class="panel-card border border-success mb-4" aria-labelledby="new-invitation-title">
        <div class="d-flex align-items-start gap-3">
            <div class="icon-tile text-success"><i aria-hidden="true" class="bi bi-shield-check"></i></div>
            <div class="flex-grow-1">
                <h2 class="h5" id="new-invitation-title">Copy this code now</h2>
                <p class="text-secondary mb-2">The usable code is shown only on this screen. Copy it before leaving.</p>
                <div class="input-group mb-2">
                    <input id="newInvitationCode" class="form-control font-monospace" value="<?= e($newInvitation['token']) ?>" readonly aria-label="New <?= e($newInvitation['role']) ?> invitation code">
                    <button class="btn btn-outline-primary" type="button" data-copy-target="#newInvitationCode"><i aria-hidden="true" class="bi bi-copy me-1"></i>Copy code</button>
                </div>
                <?php
                $registrationPage = $newInvitation['role'] === 'Admin' ? '/admin/register.php' : '/staff/register.php';
                // Put the one-time code in the URL fragment so Apache access logs never receive it.
                $registrationLink = APP_URL . $registrationPage . '#invite=' . rawurlencode($newInvitation['token']);
                ?>
                <div class="input-group mb-2">
                    <input id="newInvitationLink" class="form-control" value="<?= e($registrationLink) ?>" readonly aria-label="Registration invitation link">
                    <button class="btn btn-outline-primary" type="button" data-copy-target="#newInvitationLink"><i aria-hidden="true" class="bi bi-link-45deg me-1"></i>Copy link</button>
                </div>
                <p class="small text-secondary mb-0">
                    <?= e($newInvitation['role']) ?> invitation for <strong><?= e($newInvitation['email']) ?></strong>.
                    Expires <?= e(date('M d, Y g:i A', strtotime($newInvitation['expires_at']))) ?>.
                </p>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="panel-card mb-4" aria-labelledby="create-invitation-title">
    <div class="mb-3">
        <h2 class="h5 mb-1" id="create-invitation-title">Create invitation</h2>
        <p class="small text-secondary mb-0">The role and recipient email are stored in the database and cannot be changed during registration.</p>
    </div>
    <form method="post" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_invitation">
        <div class="col-lg-3 col-md-4">
            <label class="form-label" for="invitation_role">Account type <span class="text-danger">*</span></label>
            <select class="form-select" id="invitation_role" name="invitation_role" required>
                <option value="Staff">Staff</option>
                <option value="Admin">Administrator</option>
            </select>
        </div>
        <div class="col-lg-5 col-md-8">
            <label class="form-label" for="email">Recipient email <span class="text-danger">*</span></label>
            <input type="email" class="form-control" id="email" name="email" maxlength="100" placeholder="name@example.com" autocomplete="email" value="<?= e($invitationValues['email']) ?>" required>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label" for="expires_in_hours">Expires in <span class="text-danger">*</span></label>
            <select class="form-select" id="expires_in_hours" name="expires_in_hours" required>
                <?php foreach (['24', '48', '72'] as $hours): ?>
                    <option value="<?= e($hours) ?>" <?= $invitationValues['expires_in_hours'] === $hours ? 'selected' : '' ?>><?= e($hours) ?> hours</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2 col-md-6 d-flex align-items-end">
            <button class="btn btn-primary w-100" type="submit"><i aria-hidden="true" class="bi bi-envelope-plus me-1"></i>Generate</button>
        </div>
    </form>
</section>

<section class="panel-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">Invitation history</h2>
            <p class="small text-secondary mb-0">Expired codes are disabled automatically. Used and revoked codes cannot be reused.</p>
        </div>
        <div class="search-box local-table-search"><label class="form-label" for="invitationTableSearch">Filter displayed invitations</label><div class="search-input"><i aria-hidden="true" class="bi bi-search"></i>
            <input id="invitationTableSearch" type="search" class="form-control" placeholder="Filter displayed invitations" data-table-search="#invitationTable" aria-label="Filter displayed invitations"></div></div>
    </div>
    <div class="table-responsive mobile-table-cards" role="region" tabindex="0" aria-label="Invitations table">
        <table data-layout="cards" role="table" class="table align-middle mb-0" id="invitationTable"><caption class="visually-hidden">Invitation history</caption>
            <thead>
                <tr>
                    <th scope="col">Role</th>
                    <th scope="col">Recipient</th>
                    <th scope="col">Created by</th>
                    <th scope="col">Expires</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invitations as $invitation): ?>
                    <tr>
                        <td data-column-label="Role"><div class="table-cell-value"><?= e($invitation['role']) ?></div></td>
                        <td data-column-label="Recipient"><div class="table-cell-value"><?= e($invitation['email']) ?></div></td>
                        <td data-column-label="Created by"><div class="table-cell-value"><?= e($invitation['created_by_name'] ?: 'Former user') ?></div></td>
                        <td data-column-label="Expires"><div class="table-cell-value">
                            <?= e(date('M d, Y g:i A', strtotime($invitation['expires_at']))) ?>
                            <?php if ($invitation['used_at']): ?>
                                <div class="small text-secondary">Used <?= e(date('M d, Y g:i A', strtotime($invitation['used_at']))) ?><?= $invitation['used_by_name'] ? ' by ' . e($invitation['used_by_name']) : '' ?></div>
                            <?php elseif ($invitation['revoked_at']): ?>
                                <div class="small text-secondary">Revoked <?= e(date('M d, Y g:i A', strtotime($invitation['revoked_at']))) ?></div>
                            <?php endif; ?>
                        </div></td>
                        <td data-column-label="Status"><div class="table-cell-value"><span class="badge <?= e(badge_class($invitation['status'])) ?>"><?= e($invitation['status']) ?></span></div></td>
                        <td class="text-end" data-column-label="Action"><div class="table-cell-value">
                            <?php if ($invitation['status'] === 'Active' && strtotime((string) $invitation['expires_at']) > time()): ?>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="revoke_invitation">
                                    <input type="hidden" name="id" value="<?= (int) $invitation['invitation_id'] ?>">
                                    <button class="btn btn-sm btn-light text-danger" type="submit" data-inline-confirm="Revoke this invitation? It cannot be used after revocation.">Revoke</button>
                                </form>
                            <?php else: ?>
                                <span class="text-secondary small">—</span>
                            <?php endif; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($invitations === []): ?>
                    <tr class="table-empty-row"><td colspan="6" class="empty-state"><i class="bi bi-envelope-plus d-block fs-3 mb-2" aria-hidden="true"></i><strong>No invitations have been created yet.</strong><div class="small mt-1">Create an email-specific invitation above.</div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
