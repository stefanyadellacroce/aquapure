<?php
require_once __DIR__ . "/includes/bootstrap.php";

if (current_account()) {
    redirect((is_customer() ? "customer" : "portal") . "/index.php");
}

if (is_post()) {
    verify_csrf();
    $identifier = strtolower(input("identifier"));
    $password = isset($_POST["password"]) && is_string($_POST["password"])
        ? $_POST["password"]
        : "";

    if ($identifier === "" || $password === "") {
        flash("danger", "Enter your email or username and password.");
    } else {
        $customer = db()->prepare(
            "SELECT * FROM customers WHERE email = ? AND account_status = 'Active' LIMIT 1",
        );
        $customer->execute([$identifier]);
        $record = $customer->fetch();

        if (
            $record &&
            is_string($record["password_hash"]) &&
            password_verify($password, $record["password_hash"])
        ) {
            session_regenerate_id(true);
            $_SESSION["account"] = [
                "id" => (int) $record["customer_id"],
                "name" => $record["full_name"],
                "email" => $record["email"],
                "role" => "Customer",
                "type" => "customer",
            ];
            clear_old();
            redirect("customer/index.php");
        }

        $user = db()->prepare(
            "SELECT * FROM users WHERE (email = ? OR username = ?) AND account_status = 'Active' LIMIT 1",
        );
        $user->execute([$identifier, $identifier]);
        $record = $user->fetch();

        if ($record && password_verify($password, $record["password_hash"])) {
            session_regenerate_id(true);
            $_SESSION["account"] = [
                "id" => (int) $record["user_id"],
                "name" => $record["full_name"],
                "email" => $record["email"],
                "role" => $record["role"],
                "type" => "user",
            ];
            log_activity(
                (int) $record["user_id"],
                "Login",
                $record["role"] . " signed in.",
            );
            clear_old();
            redirect("portal/index.php");
        }

        keep_old(["identifier" => $identifier]);
        flash(
            "danger",
            "The email/username or password is incorrect, or the account is inactive.",
        );
    }
}

$pageTitle = "Login";
$publicPage = "login";
require __DIR__ . "/includes/public-header.php";
?>
<main class="auth-shell" id="mainContent" tabindex="-1">
    <section class="auth-card" aria-labelledby="login-title">
        <div class="auth-brand">
            <img src="<?= e(url("assets/images/aquapure-logo-transparent.png")) ?>" alt="AquaPure" height="68" class="mb-2">
            <div class="brand">AquaPure</div>
            <div class="small text-secondary mt-1">Water Refilling Station</div>
        </div>
        <h1 class="h4 section-title text-center" id="login-title">Login to your account</h1>
        <p class="text-secondary text-center mb-4">Use your email or username to continue.</p>
        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="identifier" class="form-label">Email or username <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" class="form-control" id="identifier" name="identifier" value="<?= old(
                    "identifier",
                ) ?>" autocomplete="username" required>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password <span class="text-danger" aria-hidden="true">*</span></label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                    <button class="btn btn-outline-secondary" type="button" data-password-toggle="#password" aria-label="Show password"><i aria-hidden="true" class="bi bi-eye"></i></button>
                </div>
            </div>
            <button class="btn btn-primary w-100" type="submit">Log in</button>
        </form>
        <p class="small text-center text-secondary mt-4 mb-0">Don't have an account? <a href="<?= e(
            url("register.php"),
        ) ?>">Create customer account</a></p>
    </section>
</main>
<?php require __DIR__ . "/includes/public-footer.php"; ?>
