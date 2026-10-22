<?php
declare(strict_types=1);

$editId = positive_int($_GET["edit"] ?? 0);
$edit = null;
if ($editId) {
    $statement = db()->prepare("SELECT * FROM users WHERE user_id = ?");
    $statement->execute([$editId]);
    $edit = $statement->fetch();
}
$users = db()
    ->query("SELECT * FROM users ORDER BY role, full_name")
    ->fetchAll();
$formValues = aq_form_values('save_user', $edit ?: [], (int) ($edit['user_id'] ?? 0));
$formRetry = aq_form_retry('save_user', (int) ($edit['user_id'] ?? 0));
?>
<header class="page-header">
    <div>
        <p class="eyebrow mb-1">Administrator only</p>
        <h1 class="section-title mb-1">System users</h1>
        <p class="text-secondary mb-0">Edit existing accounts, reset passwords, and manage account status.</p>
    </div>
    <div class="page-header__actions"><a class="btn btn-primary" href="<?= e(url("portal/index.php?page=invitations")) ?>"><i class="bi bi-envelope-plus" aria-hidden="true"></i>Issue invitation</a></div>
</header>

<div class="alert alert-light border mb-4" role="note">
    New Staff and Administrator accounts can only be created from a secure invitation. Account roles cannot be changed here.
</div>

<?php if ($edit): ?>
    <section class="panel-card mb-4" aria-labelledby="edit-user-title">
        <div class="d-flex justify-content-between gap-3 align-items-start mb-3">
            <div>
                <h2 class="h5 mb-1" id="edit-user-title">Edit <?= e($edit["full_name"]) ?></h2>
                <p class="small text-secondary mb-0"><?= e($edit["role"]) ?> account</p>
            </div>
            <a class="btn btn-sm btn-light" href="<?= e(url("portal/index.php?page=users")) ?>">Close</a>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_user">
            <input type="hidden" name="user_id" value="<?= (int) $edit["user_id"] ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="full_name">Full name <span class="text-danger" aria-hidden="true">*</span></label>
                    <input class="form-control" id="full_name" name="full_name" value="<?= e($formValues["full_name"]) ?>" maxlength="100" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="username">Username <span class="text-danger" aria-hidden="true">*</span></label>
                    <input class="form-control" id="username" name="username" value="<?= e($formValues["username"]) ?>" maxlength="30" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="email">Email <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e($formValues["email"]) ?>" maxlength="100" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="contact_number">Contact number <span class="text-secondary">(optional)</span></label>
                    <input class="form-control" id="contact_number" name="contact_number" value="<?= e($formValues["contact_number"] ?? "") ?>" maxlength="30" placeholder="09171234567" inputmode="tel" autocomplete="tel">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password">New password <span class="text-secondary">(leave blank to keep current password)</span></label>
                    <input type="password" class="form-control" id="password" name="password" minlength="8" autocomplete="new-password">
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-primary" type="submit">Save account</button>
                <a class="btn btn-light" href="<?= e(url("portal/index.php?page=users")) ?>">Cancel</a>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="panel-card">
    <div class="table-responsive mobile-table-cards" role="region" tabindex="0" aria-label="Users table">
        <table data-layout="cards" role="table" class="table align-middle mb-0"><caption class="visually-hidden">Users</caption>
            <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">User</th>
                    <th scope="col">Role</th>
                    <th scope="col">Status</th>
                    <th scope="col">Date created</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td data-column-label="ID"><div class="table-cell-value"><?= (int) $user["user_id"] ?></div></td>
                        <td class="table-primary-cell" data-column-label="User"><div class="table-cell-value">
                            <strong><?= e($user["full_name"]) ?></strong>
                            <div class="small text-secondary"><?= e($user["username"]) ?> &middot; <?= e($user["email"]) ?></div>
                        </div></td>
                        <td data-column-label="Role"><div class="table-cell-value"><?= e($user["role"]) ?></div></td>
                        <td data-column-label="Status"><div class="table-cell-value"><span class="badge <?= e(badge_class($user["account_status"])) ?>"><?= e($user["account_status"]) ?></span></div></td>
                        <td data-column-label="Date created"><div class="table-cell-value"><?= e(date("M d, Y", strtotime($user["created_at"]))) ?></div></td>
                        <td data-column-label="Actions"><div class="table-cell-value">
                            <div class="d-flex gap-1 justify-content-end">
                                <a class="btn btn-sm btn-light" href="<?= e(url("portal/index.php?page=users&edit=" . $user["user_id"])) ?>">Edit / Password</a>
                                <?php if ((int) $user["user_id"] !== (int) current_account()["id"]): ?>
                                    <button type="button" class="btn btn-sm btn-light <?= $user["account_status"] === "Active" ? "text-danger" : "" ?>" data-confirm="<?= $user["account_status"] === "Active" ? "Deactivate " . e($user["full_name"]) . "? They will no longer be able to sign in until reactivated." : "Reactivate " . e($user["full_name"]) . "? They will regain access to the operations portal." ?>" data-confirm-title="<?= $user["account_status"] === "Active" ? "Deactivate user" : "Reactivate user" ?>" data-confirm-label="<?= $user["account_status"] === "Active" ? "Deactivate" : "Reactivate" ?>" data-action="<?= e(url("portal/index.php?page=users")) ?>" data-form-action="toggle_user" data-id="<?= (int) $user["user_id"] ?>"><?= $user["account_status"] === "Active" ? "Deactivate" : "Reactivate" ?></button>
                                <?php endif; ?>
                            </div>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($users === []): ?>
                    <tr class="table-empty-row"><td colspan="6" class="empty-state"><i class="bi bi-people d-block fs-3 mb-2" aria-hidden="true"></i><strong>No system users are registered yet.</strong></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
