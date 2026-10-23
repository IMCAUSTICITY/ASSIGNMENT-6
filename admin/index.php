<?php
require_once __DIR__ . '/../db.php';
if (is_admin()) redirect('admin/dashboard.php');
if (is_post()) {
    if (login_throttled()) flash('danger', 'Too many failed attempts. Wait a minute.');
    else {
        $st = $pdo->prepare('SELECT * FROM admin WHERE username=?'); $st->execute([post('username')]); $a = $st->fetch();
        if ($a && password_verify($_POST['password'] ?? '', $a['password'])) {
            session_regenerate_id(true); $_SESSION['admin_id'] = (int)$a['id']; $_SESSION['login_fails'] = 0;
            flash('success', 'Welcome, admin!'); redirect('admin/dashboard.php');
        }
        login_fail(); flash('danger', 'Invalid username or password.');
    }
}
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin Login | <?= e(setting('site_name')) ?></title>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>"><style>:root{--primary:<?= e(setting('theme_primary', '#4f46e5')) ?>;--accent:<?= e(setting('theme_accent', '#f59e0b')) ?>}</style></head>
<body><div class="login-wrap"><div class="box" style="width:380px;max-width:92vw"><h2 style="margin-top:0">🔐 Admin Panel</h2><?php flash_show(); ?>
<form method="post"><?= csrf_field() ?><label>Username</label><input name="username" required autofocus><label>Password</label><input type="password" name="password" required>
<p><button class="btn btn-block">Sign in</button></p></form><p class="muted small">Demo: admin / admin123</p><a href="<?= url('index.php') ?>">← Back to store</a></div></div></body></html>
