<?php
$statement = db()->prepare('SELECT * FROM customers WHERE customer_id = ?');
$statement->execute([(int) current_account()['id']]);
$customer = $statement->fetch();
$customer = aq_form_values('update_profile', $customer);
?>
<header class="page-header">
    <div>
    <p class="eyebrow mb-1">Account settings</p>
    <h1 class="section-title mb-1">My profile</h1>
    <p class="text-secondary mb-0">Keep your contact and delivery information accurate.</p>
    </div>
</header>

<form method="post" class="row g-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_profile">

    <div class="col-xl-7">
        <section class="panel-card h-100">
            <h2 class="h5 mb-1">Personal information</h2>
            <p class="small text-secondary mb-3">These details are used for your AquaPure account and delivery information.</p>
            <div class="mb-3">
                <label class="form-label" for="full_name">Full name <span class="text-danger">*</span></label>
                <input class="form-control" id="full_name" name="full_name" value="<?= e($customer['full_name']) ?>" maxlength="100" autocomplete="name" required>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e($customer['email']) ?>" maxlength="100" autocomplete="email" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="contact_number">Contact number <span class="text-danger">*</span></label>
                    <input class="form-control" id="contact_number" name="contact_number" value="<?= e($customer['contact_number']) ?>" maxlength="20" autocomplete="tel" required inputmode="tel">
                </div>
            </div>
            <div class="mb-0">
                <label class="form-label" for="address">Address <span class="text-danger">*</span></label>
                <textarea class="form-control" id="address" name="address" rows="3" maxlength="255" autocomplete="street-address" required><?= e($customer['address']) ?></textarea>
            </div>
        </section>
    </div>

    <div class="col-xl-5">
        <section class="panel-card h-100">
            <h2 class="h5 mb-1">Change password</h2>
            <p class="small text-secondary mb-3">Leave all password fields blank if you only want to update your profile.</p>
            <div class="mb-3">
                <label class="form-label" for="current_password">Current password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password">
                    <button class="btn btn-outline-secondary" type="button" data-password-toggle="#current_password" aria-label="Show password"><i aria-hidden="true" class="bi bi-eye"></i></button>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">New password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" minlength="8" autocomplete="new-password">
                    <button class="btn btn-outline-secondary" type="button" data-password-toggle="#password" aria-label="Show password"><i aria-hidden="true" class="bi bi-eye"></i></button>
                </div>
            </div>
            <div class="mb-0">
                <label class="form-label" for="confirm_password">Confirm new password</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password">
                    <button class="btn btn-outline-secondary" type="button" data-password-toggle="#confirm_password" aria-label="Show password"><i aria-hidden="true" class="bi bi-eye"></i></button>
                </div>
            </div>
        </section>
    </div>

    <div class="col-12">
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2" aria-hidden="true"></i>Save changes</button>
    </div>
</form>
