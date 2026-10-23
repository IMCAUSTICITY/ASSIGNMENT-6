<?php
require_once __DIR__ . '/db.php';
if (!empty($_SESSION['user_id'])) $pdo->prepare('UPDATE users SET remember_token=NULL WHERE id=?')->execute([$_SESSION['user_id']]);
setcookie('remember', '', time() - 3600, '/');
$_SESSION = []; session_destroy();
session_start(); flash('success', 'You have been logged out.');
redirect('index.php');
