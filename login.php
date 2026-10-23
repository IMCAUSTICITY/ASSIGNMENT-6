<?php
require_once __DIR__ . '/db.php';
if (user()) redirect(user()['role'] === 'vendor' ? 'vendor/dashboard.php' : 'profile.php');
$next = get('next', 'profile.php');
if (is_post()) {
    $email = strtolower(post('email')); $pass = $_POST['password'] ?? '';
    if (login_throttled()) flash('danger', 'Too many failed attempts. Please wait a minute and try again.');
    else {
        $st = $pdo->prepare('SELECT * FROM users WHERE email=? AND status=1'); $st->execute([$email]); $u = $st->fetch();
        if ($u && password_verify($pass, $u['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$u['id']; $_SESSION['role'] = $u['role']; $_SESSION['login_fails'] = 0;
            if (post('remember')) {
                $tok = bin2hex(random_bytes(32));
                $pdo->prepare('UPDATE users SET remember_token=? WHERE id=?')->execute([hash('sha256', $tok), $u['id']]);
                setcookie('remember', $u['id'] . ':' . $tok, ['expires' => time() + 86400 * 30, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
            }
            flash('success', 'Welcome back, ' . $u['name'] . '!');
            if ($u['role'] === 'vendor') redirect('vendor/dashboard.php');
            $next = basename($next); redirect(preg_match('/^[a-z_]+\.php$/', $next) ? $next : 'profile.php');
        }
        login_fail(); flash('danger', 'Invalid email or password.');
    }
}
$title = 'Login'; require __DIR__ . '/includes/header.php'; ?>
<div class="box auth"><h2>Welcome back</h2>
  <form method="post"><?= csrf_field() ?>
    <label>Email</label><input type="email" name="email" value="<?= e(post('email')) ?>" required autofocus>
    <label>Password</label><input type="password" name="password" required>
    <label style="font-weight:400"><input type="checkbox" name="remember" value="1"> Remember me for 30 days</label>
    <p><button class="btn btn-block">Login</button></p></form>
  <p class="muted small">Demo: customer@demo.com / password123 · vendor1@demo.com / password123</p>
  <p>New here? <a href="<?= url('signup.php') ?>">Create an account</a> · <a href="<?= url('admin/index.php') ?>">Admin login</a></p></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
