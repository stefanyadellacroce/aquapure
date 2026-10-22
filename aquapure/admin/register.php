<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (current_account()) {
    redirect((is_customer() ? 'customer' : 'portal') . '/index.php');
}

if (is_post()) {
    verify_csrf();
    $fullName = input('full_name');
    $username = strtolower(input('username'));
    $password = isset($_POST['password']) && is_string($_POST['password'])
        ? $_POST['password']
        : '';
    $confirmation = isset($_POST['confirm_password']) && is_string($_POST['confirm_password'])
        ? $_POST['confirm_password']
        : '';
    $invitationCode = isset($_POST['invitation_code']) && is_string($_POST['invitation_code'])
        ? trim($_POST['invitation_code'])
        : '';
    $errors = [];

    if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 100) {
        $errors[] = 'Enter your complete name.';
    }
    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
        $errors[] = 'Username must be 3-30 lowercase letters, numbers, dots, underscores, or hyphens.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must contain at least 8 characters.';
    }
    if (!hash_equals($password, $confirmation)) {
        $errors[] = 'Passwords do not match.';
    }
    if ($invitationCode === '') {
        $errors[] = 'Enter your Administrator invitation code.';
    }

    if ($errors === []) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $invitation = claim_registration_invitation($pdo, 'Admin', $invitationCode);
            if ($invitation === null) {
                throw new RuntimeException(
                    'The Administrator invitation is invalid, expired, revoked, already used, or assigned to another role.',
                );
            }

            // Email and role come only from the locked database invitation.
            $email = $invitation['email'];
            $duplicate = $pdo->prepare(
                'SELECT user_id FROM users WHERE email = ? OR username = ? LIMIT 1',
            );
            $duplicate->execute([$email, $username]);
            if ($duplicate->fetch()) {
                throw new RuntimeException('That invitation email or username is already registered.');
            }
            $customerDuplicate = $pdo->prepare(
                'SELECT customer_id FROM customers WHERE email = ? LIMIT 1',
            );
            $customerDuplicate->execute([$email]);
            if ($customerDuplicate->fetch()) {
                throw new RuntimeException('The invitation email already belongs to a customer account.');
            }

            $statement = $pdo->prepare(
                "INSERT INTO users
                    (full_name, username, email, contact_number, password_hash, role, account_status)
                 VALUES (?, ?, ?, NULL, ?, 'Admin', 'Active')",
            );
            $statement->execute([
                $fullName,
                $username,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
            ]);
            $newUserId = (int) $pdo->lastInsertId();

            if (!consume_registration_invitation($pdo, $invitation['id'], $newUserId)) {
                throw new RuntimeException('That Administrator invitation is no longer valid.');
            }

            $pdo->commit();
            try {
                log_activity(
                    $newUserId,
                    'Administrator registered',
                    'Account created from an email-specific Administrator invitation.',
                );
            } catch (Throwable $exception) {
                error_log('AquaPure registration activity log failed: ' . $exception->getMessage());
            }
            clear_old();
            flash('success', 'Administrator account created for ' . $email . '. You can now log in.');
            redirect('login.php');
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('AquaPure Administrator registration failed: ' . $exception->getMessage());
            $errors[] = 'The Administrator account could not be created. Please try again.';
        }
    }

    if ($errors !== []) {
        keep_old($_POST);
        foreach ($errors as $error) {
            flash('danger', $error);
        }
    }
}

$pageTitle = 'Administrator registration';
$publicPage = 'register';
require __DIR__ . '/../includes/public-header.php';
?>
<main class="auth-shell" id="mainContent" tabindex="-1">
    <section class="auth-card auth-card-wide" aria-labelledby="admin-register-title">
        <div class="auth-brand">
            <img src="<?= e(url('assets/images/aquapure-logo-transparent.png')) ?>" alt="AquaPure" height="68" class="mb-2">
            <div class="brand">AquaPure</div>
            <div class="small text-secondary mt-1">Administrator account</div>
        </div>
        <h1 class="h4 section-title text-center" id="admin-register-title">Create Administrator account</h1>
        <p class="text-secondary text-center mb-4">This page requires an unused Administrator invitation. Your email and role are fixed by the invitation and cannot be changed in this form.</p>
        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="full_name">Full name <span class="text-danger">*</span></label>
                <input class="form-control" id="full_name" name="full_name" value="<?= old('full_name') ?>" maxlength="100" autocomplete="name" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="username">Username <span class="text-danger">*</span></label>
                <input class="form-control" id="username" name="username" value="<?= old('username') ?>" maxlength="30" autocomplete="username" required>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="password">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" minlength="8" autocomplete="new-password" required>
                        <button class="btn btn-outline-secondary" type="button" data-password-toggle="#password" aria-label="Show password"><i aria-hidden="true" class="bi bi-eye"></i></button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="confirm_password">Confirm password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                        <button class="btn btn-outline-secondary" type="button" data-password-toggle="#confirm_password" aria-label="Show password"><i aria-hidden="true" class="bi bi-eye"></i></button>
                    </div>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="invitation_code">Administrator invitation code <span class="text-danger">*</span></label>
                <input class="form-control" id="invitation_code" name="invitation_code" autocomplete="one-time-code" data-invitation-code required>
                <div class="form-text">The code is single-use and expires at the time chosen by the Admin who created it.</div>
            </div>
            <button class="btn btn-primary w-100" type="submit">Create Administrator account</button>
        </form>
        <p class="small text-center text-secondary mt-4 mb-0">Already have an account? <a href="<?= e(url('login.php')) ?>">Login</a></p>
    </section>
</main>
<?php require __DIR__ . '/../includes/public-footer.php'; ?>
