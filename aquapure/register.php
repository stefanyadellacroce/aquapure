<?php
require_once __DIR__ . "/includes/bootstrap.php";

if (is_post()) {
    verify_csrf();
    $fullName = input("full_name");
    $email = strtolower(input("email"));
    $phone = preg_replace("/[\s-]/", "", input("contact_number"));
    $address = input("address");
    $password = isset($_POST["password"]) && is_string($_POST["password"])
        ? $_POST["password"]
        : "";
    $confirm = isset($_POST["confirm_password"]) &&
        is_string($_POST["confirm_password"])
        ? $_POST["confirm_password"]
        : "";
    $errors = [];

    if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 100) {
        $errors[] = "Enter your complete name.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
        $errors[] = "Enter a valid email address.";
    }
    if (!valid_phone($phone)) {
        $errors[] =
            "Use a valid Philippine mobile number, such as 09171234567.";
    }
    if (mb_strlen($address) < 8 || mb_strlen($address) > 255) {
        $errors[] = "Enter your complete address.";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password must contain at least 8 characters.";
    }
    if (!hash_equals($password, $confirm)) {
        $errors[] = "Passwords do not match.";
    }

    $check = db()->prepare(
        "SELECT email FROM customers WHERE email = ?
         UNION ALL
         SELECT email FROM users WHERE email = ?
         LIMIT 1",
    );
    $check->execute([$email, $email]);
    if ($check->fetch()) {
        $errors[] = "That email address is already registered.";
    }

    if ($errors) {
        keep_old($_POST);
        foreach ($errors as $error) {
            flash("danger", $error);
        }
    } else {
        try {
            $statement = db()->prepare(
                "INSERT INTO customers (full_name, email, contact_number, address, password_hash, customer_type, account_status)
                 VALUES (?, ?, ?, ?, ?, 'Online Customer', 'Active')",
            );
            $statement->execute([
                $fullName,
                $email,
                $phone,
                $address,
                password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== "23000") {
                throw $exception;
            }
            keep_old($_POST);
            flash("danger", "That email address is already registered.");
            redirect("register.php");
        }
        clear_old();
        flash(
            "success",
            "Your account was created. You can now log in and order water.",
        );
        redirect("login.php");
    }
}

$pageTitle = "Create customer account";
$publicPage = "register";
require __DIR__ . "/includes/public-header.php";
?>
<main class="auth-shell" id="mainContent" tabindex="-1">
    <section class="auth-card auth-card-wide" aria-labelledby="register-title">
        <div class="auth-brand">
            <img src="<?= e(url("assets/images/aquapure-logo-transparent.png")) ?>" alt="AquaPure" height="68" class="mb-2">
            <div class="brand">AquaPure</div>
            <div class="small text-secondary mt-1">Customer account</div>
        </div>
        <h1 class="h4 section-title text-center" id="register-title">Create your account</h1>
        <p class="text-secondary text-center mb-4">Save your delivery details and track every AquaPure order.</p>
        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label" for="full_name">Full name <span class="text-danger">*</span></label><input class="form-control" id="full_name" name="full_name" value="<?= old(
                "full_name",
            ) ?>" maxlength="100" autocomplete="name" required></div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label" for="email">Email address <span class="text-danger">*</span></label><input type="email" class="form-control" id="email" name="email" value="<?= old(
                    "email",
                ) ?>" maxlength="100" autocomplete="email" required></div>
                <div class="col-md-6"><label class="form-label" for="contact_number">Contact number <span class="text-danger">*</span></label><input class="form-control" id="contact_number" name="contact_number" value="<?= old(
                    "contact_number",
                ) ?>" maxlength="20" placeholder="09171234567" autocomplete="tel" required inputmode="tel"></div>
            </div>
            <div class="mb-3"><label class="form-label" for="address">Address <span class="text-danger">*</span></label><textarea class="form-control" id="address" name="address" rows="2" maxlength="255" autocomplete="street-address" required><?= old(
                "address",
            ) ?></textarea></div>
            <div class="row g-3 mb-4">
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
            <button class="btn btn-primary w-100" type="submit">Create account</button>
        </form>
        <p class="small text-center text-secondary mt-4 mb-0">Already registered? <a href="<?= e(
            url("login.php"),
        ) ?>">Login</a></p>
    </section>
</main>
<?php require __DIR__ . "/includes/public-footer.php"; ?>
