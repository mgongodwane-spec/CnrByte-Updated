<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';

function check_signup_errors(){
    if(isset($_SESSION['errors_signup'])){
        $errors = $_SESSION['errors_signup'];
        echo "<br>";
        foreach ($errors as $error){
            echo '<p class="form-error" role="alert">' . htmlspecialchars((string)$error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }//correct
        unset($_SESSION["errors_signup"]);
    }
}
function check_login_errors(){
    if(isset($_SESSION["errors_login"])){
        $errors = $_SESSION["errors_login"];

        echo "<br>";

        foreach($errors as $error){
            echo '<p class="form-error" role="alert">' . htmlspecialchars((string)$error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }

        unset($_SESSION["errors_login"]);
    }
}
function check_signup_success(): void {
    if (!empty($_SESSION['signup_success'])) {
        echo '<p class="form-success" role="status">' . htmlspecialchars((string)$_SESSION['signup_success'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        unset($_SESSION['signup_success']);
    }
}

function login_form($conn): void {
    ?>
    <div class="login-box">
        <form action="/cnrbyte/signup_login/login.run.php" method="post" class="login-form">
            <h3 class="form-title">Login</h3>
            <?php check_login_errors(); ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <label for="login_identifier_legacy">Username or email</label>
            <input id="login_identifier_legacy" type="text" name="username" maxlength="150" autocomplete="username" required>
            <label for="login_password_legacy">Password</label>
            <input id="login_password_legacy" type="password" name="pass_word" maxlength="4096" autocomplete="current-password" required>
            <button type="submit" class="form-btn">Login</button>
        </form>
    </div>
    <?php
}

function signup_form($conn): void {
    ?>
    <div class="signup-box">
        <form action="/cnrbyte/signup_login/signup.run.php" method="post" class="signup-form">
            <h3 class="form-title">Create an account</h3>
            <?php check_signup_errors(); check_signup_success(); ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <label for="legacy_username_signup">Username</label>
            <input id="legacy_username_signup" type="text" name="username" minlength="3" maxlength="40" pattern="[A-Za-z0-9_.-]+" autocomplete="username" required>
            <label for="legacy_email_signup">Email</label>
            <input id="legacy_email_signup" type="email" name="email" maxlength="150" autocomplete="email" required>
            <label for="legacy_phone_signup">Phone (optional)</label>
            <input id="legacy_phone_signup" type="tel" name="phone" maxlength="50" autocomplete="tel">
            <label for="legacy_password_signup">Password (at least 12 characters)</label>
            <input id="legacy_password_signup" type="password" name="pass_word" minlength="12" maxlength="4096" autocomplete="new-password" required>
            <label for="legacy_confirm_signup">Confirm password</label>
            <input id="legacy_confirm_signup" type="password" name="c_password" minlength="12" maxlength="4096" autocomplete="new-password" required>
            <button type="submit" class="form-btn">Sign up</button>
        </form>
    </div>
    <?php
}
