<?php
require_once __DIR__ . '/db.php';
$id = (int)get('id');
$st = $pdo->prepare('SELECT o.*, u.name uname, u.email FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?'); $st->execute([$id]); $o = $st->fetch();
if (!$o || (!is_admin() && (!user() || user()['id'] != $o['user_id']))) { http_response_code(403); exit('Not allowed.'); }
$it = $pdo->prepare('SELECT * FROM order_items WHERE order_id=?'); $it->execute([$id]); $items = $it->fetchAll();
?><!DOCTYPE html><html><head><meta charset="utf-8"><title>Invoice <?= order_ref($id) ?></title><link rel="stylesheet" href="<?= url('assets/css/style.css') ?>"></head>
<body style="background:#fff"><div class="container" style="max-width:800px;padding:30px">
<div class="flex between"><h1 style="margin:0"><?= e(setting('site_name')) ?></h1><div class="right"><h2 style="margin:0">INVOICE</h2><?= order_ref($id) ?><br><?= e(date('d M Y', strtotime($o['created_at']))) ?></div></div><hr>
<p><b>Bill to:</b><br><?= e($o['ship_name']) ?><br><?= e($o['ship_address']) ?>, <?= e($o['ship_city']) ?> <?= e($o['ship_postal']) ?><br><?= e($o['ship_phone']) ?> · <?= e($o['email']) ?></p>
<table><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr>
<?php foreach ($items as $i): ?><tr><td><?= e($i['product_name']) ?> <span class="muted small"><?= e($i['variation']) ?></span></td><td><?= $i['quantity'] ?></td><td><?= money_base($i['price']) ?></td><td><?= money_base($i['price'] * $i['quantity']) ?></td></tr><?php endforeach; ?>
<tr><td colspan="3" class="right">Subtotal</td><td><?= money_base($o['subtotal']) ?></td></tr><tr><td colspan="3" class="right">Discount <?= e($o['coupon_code']) ?></td><td>− <?= money_base($o['discount']) ?></td></tr>
<tr><td colspan="3" class="right"><b>Total</b></td><td><b><?= money_base($o['total_amount']) ?></b></td></tr></table>
<p>Payment: <?= e(strtoupper($o['payment_method'])) ?> (<?= e($o['payment_status']) ?>) · Status: <?= e($o['status']) ?></p>
<p class="no-print"><button class="btn" data-print>Print / Save as PDF</button></p></div><script src="<?= url('assets/js/app.js') ?>"></script></body></html>
