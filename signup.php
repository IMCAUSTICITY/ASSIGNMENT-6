<?php
require_once __DIR__ . '/db.php';
if (user()) redirect('profile.php');
$f = ['name' => '', 'email' => '', 'phone' => '', 'type' => get('type') === 'vendor' ? 'vendor' : 'customer', 'store' => ''];
if (is_post()) {
    foreach ($f as $k => $_) $f[$k] = post($k, $f[$k]);
    $pass = $_POST['password'] ?? ''; $f['email'] = strtolower($f['email']); $errs = [];
    if (mb_strlen($f['name']) < 2) $errs[] = 'Please enter your name.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errs[] = 'Enter a valid email address.';
    if ($f['phone'] !== '' && !preg_match('/^[0-9+\-\s]{8,15}$/', $f['phone'])) $errs[] = 'Enter a valid phone number.';
    if (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) $errs[] = 'Password must be at least 8 characters with letters and numbers.';
    if ($pass !== ($_POST['confirm'] ?? '')) $errs[] = 'Passwords do not match.';
    if (!in_array($f['type'], ['customer', 'vendor'], true)) $f['type'] = 'customer';
    if ($f['type'] === 'vendor' && mb_strlen($f['store']) < 3) $errs[] = 'Enter your store name (min 3 characters).';
    if (!$errs) { $c = $pdo->prepare('SELECT 1 FROM users WHERE email=?'); $c->execute([$f['email']]); if ($c->fetchColumn()) $errs[] = 'An account with this email already exists.'; }
    if (!$errs) {
        $pdo->prepare('INSERT INTO users (name,email,password,role,phone) VALUES (?,?,?,?,?)')->execute([$f['name'], $f['email'], password_hash($pass, PASSWORD_DEFAULT), $f['type'], $f['phone'] ?: null]);
        $uid = (int)$pdo->lastInsertId();
        if ($f['type'] === 'vendor') $pdo->prepare("INSERT INTO vendors (user_id,name,status) VALUES (?,?, 'pending')")->execute([$uid, $f['store']]);
        session_regenerate_id(true); $_SESSION['user_id'] = $uid; $_SESSION['role'] = $f['type'];
        send_mail($f['email'], 'Welcome to ' . setting('site_name'), "Hi {$f['name']}, your account is ready." . ($f['type'] === 'vendor' ? " Your store is awaiting admin approval." : ''));
        flash('success', $f['type'] === 'vendor' ? 'Vendor account created. An admin will approve your store shortly.' : 'Account created. Happy shopping!');
        redirect($f['type'] === 'vendor' ? 'vendor/dashboard.php' : 'index.php');
    }
    foreach ($errs as $er) flash('danger', $er);
}
$title = 'Sign up'; require __DIR__ . '/includes/header.php'; ?>
<div class="box auth"><h2>Create your account</h2>
  <form method="post"><?= csrf_field() ?>
    <label>I want to</label><select name="type" onchange="document.getElementById('store').style.display=this.value==='vendor'?'block':'none'"><option value="customer" <?= $f['type'] === 'customer' ? 'selected' : '' ?>>Shop as a customer</option><option value="vendor" <?= $f['type'] === 'vendor' ? 'selected' : '' ?>>Sell as a vendor</option></select>
    <div id="store" style="display:<?= $f['type'] === 'vendor' ? 'block' : 'none' ?>"><label>Store name</label><input name="store" value="<?= e($f['store']) ?>"></div>
    <label>Full name</label><input name="name" value="<?= e($f['name']) ?>" required>
    <label>Email</label><input type="email" name="email" value="<?= e($f['email']) ?>" required>
    <label>Phone (optional)</label><input name="phone" value="<?= e($f['phone']) ?>">
    <label>Password</label><input type="password" name="password" minlength="8" required>
    <label>Confirm password</label><input type="password" name="confirm" required>
    <p><button class="btn btn-block">Sign up</button></p></form>
  <p>Already registered? <a href="<?= url('login.php') ?>">Login</a></p></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
