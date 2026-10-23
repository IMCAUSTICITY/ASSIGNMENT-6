<?php
require_once __DIR__ . '/db.php';
if (!is_post()) redirect('profile.php?tab=wishlist');
$u = require_user(); $pid = (int)post('product_id');
$has = $pdo->prepare('SELECT 1 FROM wishlist WHERE user_id=? AND product_id=?'); $has->execute([$u['id'], $pid]);
if ($has->fetchColumn()) { $pdo->prepare('DELETE FROM wishlist WHERE user_id=? AND product_id=?')->execute([$u['id'], $pid]); flash('info', 'Removed from wishlist.'); }
elseif ($pdo->query('SELECT 1 FROM products WHERE id=' . $pid)->fetchColumn()) { $pdo->prepare('INSERT INTO wishlist (user_id, product_id) VALUES (?,?)')->execute([$u['id'], $pid]); flash('success', 'Added to wishlist ♥'); }
back('profile.php?tab=wishlist');
