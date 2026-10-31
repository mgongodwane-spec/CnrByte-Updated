<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/session.run.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/conn.dbh.php';

require_once "../signup_login/signup_login.shw.php";
unset($_SESSION['current_dashboard'], $_SESSION["selected_pge"]);
$_SESSION['selected_pge'] = 'signup_login_pge';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login or Signup — CNRBYTE</title>

  <!-- Core site styles -->
  <link rel="stylesheet" href="../css/main.css" />
  <link rel="stylesheet" href="../css/forms.css" />
</head>
<body>
  <!-- Global header (consistent with index) -->
  <header class="page-header" role="banner" aria-label="Site header">
    <div class="brand"><h1>CNRBYTE</h1></div>
    <button class="form-btn"><a href="/cnrbyte/marketplace/dashboard.pge.php">Home</a></button>
  </header>

  <!-- Login Form -->
  <main>
    <div class="user_form" aria-labelledby="login-title">
      <form action="login.run.php" method="post" class="input_form">
        <h3 id="login-title" class="form-title">Client Login</h3>

        <?php check_signup_success(); check_login_errors(); ?>

        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

        <div class="form-card">
          <label for="username">Username or email</label>
          <input id="username" type="text" name="username" placeholder="Username or email" required maxlength="150" autocomplete="username">
        </div>

        <div class="form-card">
          <label for="password">Password</label>
          <input id="password" type="password" name="pass_word" placeholder="Password" required autocomplete="current-password">
        </div>

        <div style="display:flex; gap:0.5rem; align-items:center; justify-content:space-between;">
          <a href="forgot_password.pge.php" class="back-link" style="background:#fff; border:1px solid #e6e9ef;">Forgot password?</a>
          <button type="submit" class="form-btn">Login</button>
        </div>
      </form>
    </div>

    <!-- Signup Form -->
    <div class="user_form" aria-labelledby="signup-title">
      <form action="signup.run.php" method="post" class="input_form">
        <h3 id="signup-title" class="form-title">Create an Account</h3>

        <?php check_signup_errors(); ?>

        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

        <div class="form-card">
          <label for="username_signup">Username</label>
          <input id="username_signup" type="text" name="username" placeholder="Choose a username" required minlength="3" maxlength="40" pattern="[A-Za-z0-9_.-]+" autocomplete="username">
        </div>

        <div class="form-card">
          <label for="email">Email</label>
          <input id="email" type="email" name="email" placeholder="you@example.com" required maxlength="150" autocomplete="email">
        </div>

        <div class="form-card">
          <label for="phone">Phone</label>
          <input id="phone" type="tel" name="phone" placeholder="+27 71 000 0000" maxlength="50" autocomplete="tel">
        </div>

        <div class="form-card">
          <label for="password_signup">Password</label>
          <input id="password_signup" type="password" name="pass_word" placeholder="At least 12 characters" required minlength="12" maxlength="4096" autocomplete="new-password">
        </div>

        <div class="form-card">
          <label for="c_password_signup">Confirm Password</label>
          <input id="c_password_signup" type="password" name="c_password" placeholder="Confirm password" required minlength="12" maxlength="4096" autocomplete="new-password">
        </div>

        <div style="display:flex; gap:0.5rem; align-items:center; justify-content:flex-end;">
          <button type="submit" class="form-btn">Signup</button>
        </div>
      </form>
    </div>
  </main>
</body>
</html>