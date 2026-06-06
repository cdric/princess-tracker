<?php
require_once __DIR__ . '/_init.php';

if (is_logged_in()) {
    redirect_to('index.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $account = authenticate_user($username, $password);
    if ($account !== null) {
        login_user($account);
        redirect_to('index.php');
    }
    $error = 'Invalid username or password.';
}

render_header('Login');
?>
<section class="card narrow">
  <?php if ($error): ?><div class="flash error"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <label>Username</label>
    <input name="username" autocomplete="username" required>

    <label>Password</label>
    <input type="password" name="password" autocomplete="current-password" required>

    <button type="submit">Login</button>
  </form>
</section>
<?php render_footer(); ?>
