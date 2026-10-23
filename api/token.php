<?php
require_once __DIR__ . '/../includes/api.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_err('Use POST with JSON {"email","password"} (or {"username","password"} for admin).', 405);
if (login_throttled()) api_err('Too many attempts. Try again in a minute.', 429);
$b = api_body(); $pass = (string)($b['password'] ?? ''); $raw = bin2hex(random_bytes(24)); $hash = hash('sha256', $raw);
if (!empty($b['username'])) {
    $s = $pdo->prepare('SELECT * FROM admin WHERE username=?'); $s->execute([(string)$b['username']]); $a = $s->fetch();
    if ($a && password_verify($pass, $a['password'])) { $pdo->prepare('UPDATE admin SET api_token=? WHERE id=?')->execute([$hash, $a['id']]); api_out(['token' => $raw, 'role' => 'admin']); }
} else {
    $s = $pdo->prepare('SELECT * FROM users WHERE email=? AND status=1'); $s->execute([strtolower((string)($b['email'] ?? ''))]); $u = $s->fetch();
    if ($u && password_verify($pass, $u['password'])) { $pdo->prepare('UPDATE users SET api_token=? WHERE id=?')->execute([$hash, $u['id']]); api_out(['token' => $raw, 'role' => $u['role'], 'name' => $u['name']]); }
}
login_fail(); api_err('Invalid credentials.', 401);
