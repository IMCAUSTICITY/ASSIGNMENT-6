<?php
require_once __DIR__ . '/../db.php'; $vend = require_vendor(); $vid = (int)$vend['id'];
if (is_post() && post('action') === 'answer' && $vend['status'] === 'approved') {
    $pdo->prepare('UPDATE product_questions q JOIN products p ON p.id=q.product_id SET q.answer=? WHERE q.id=? AND p.vendor_id=?')->execute([mb_substr(post('answer'), 0, 1000), (int)post('id'), $vid]);
    flash('success', 'Answer published.'); redirect('vendor/dashboard.php');
}
$panel = 'vendor'; $title = 'Vendor Dashboard'; require __DIR__ . '/../includes/admin_header.php';
$one = function ($sql) use ($pdo, $vid) { $s = $pdo->prepare($sql); $s->execute([$vid]); return $s->fetchColumn(); };
if ($vend['status'] !== 'approved'): ?><div class="alert alert-warning">Your store <b><?= e($vend['name']) ?></b> is <b><?= e($vend['status']) ?></b>. You can manage products once an admin approves your account.</div><?php endif;
$rev = (float)$one("SELECT COALESCE(SUM(quantity*price),0) FROM order_items WHERE vendor_id=? AND item_status<>'Cancelled'");
$month = (float)$one("SELECT COALESCE(SUM(i.quantity*i.price),0) FROM order_items i JOIN orders o ON o.id=i.order_id WHERE i.vendor_id=? AND i.item_status<>'Cancelled' AND o.created_at>=DATE_FORMAT(NOW(),'%Y-%m-01')");
$orders = (int)$one('SELECT COUNT(DISTINCT order_id) FROM order_items WHERE vendor_id=?'); $pending = (int)$one("SELECT COUNT(*) FROM order_items WHERE vendor_id=? AND item_status IN ('Pending','Processing')");
$prods = (int)$one('SELECT COUNT(*) FROM products WHERE vendor_id=?'); $units = (int)$one("SELECT COALESCE(SUM(quantity),0) FROM order_items WHERE vendor_id=? AND item_status<>'Cancelled'");
$s = $pdo->prepare("SELECT DATE(o.created_at) d, SUM(i.quantity*i.price) v FROM order_items i JOIN orders o ON o.id=i.order_id WHERE i.vendor_id=? AND i.item_status<>'Cancelled' AND o.created_at>=DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY d"); $s->execute([$vid]); $series = daily_series($s->fetchAll(), 7);
$t = $pdo->prepare("SELECT product_name, SUM(quantity) q, SUM(quantity*price) r FROM order_items WHERE vendor_id=? AND item_status<>'Cancelled' GROUP BY product_id, product_name ORDER BY q DESC LIMIT 5"); $t->execute([$vid]); $top = $t->fetchAll();
$l = $pdo->prepare('SELECT id,name,stock FROM products WHERE vendor_id=? AND stock<=5 AND status=1 ORDER BY stock LIMIT 6'); $l->execute([$vid]); $low = $l->fetchAll();
$qs = $pdo->prepare('SELECT q.*, p.name pname, u.name uname FROM product_questions q JOIN products p ON p.id=q.product_id JOIN users u ON u.id=q.user_id WHERE p.vendor_id=? ORDER BY (q.answer IS NULL) DESC, q.id DESC LIMIT 5'); $qs->execute([$vid]); $qa = $qs->fetchAll(); ?>
<div class="grid stats"><div class="stat"><b><?= money_base($rev) ?></b><span>Total sales</span></div><div class="stat"><b><?= money_base($month) ?></b><span>This month</span></div><div class="stat"><b><?= $orders ?></b><span>Orders (<?= $pending ?> items to fulfil)</span></div><div class="stat"><b><?= $units ?></b><span>Units sold</span></div><div class="stat"><b><?= $prods ?></b><span>Products</span></div></div>
<div class="box"><h3>Sales – last 7 days</h3><?php bar_chart($series); ?></div>
<div class="grid two"><div class="box"><h3>Best sellers</h3><table><tr><th>Product</th><th>Units</th><th>Revenue</th></tr><?php foreach ($top as $r): ?><tr><td><?= e($r['product_name']) ?></td><td><?= $r['q'] ?></td><td><?= money_base($r['r']) ?></td></tr><?php endforeach; if (!$top) echo '<tr><td colspan="3" class="muted">No sales yet.</td></tr>'; ?></table></div>
<div class="box"><h3>Low stock</h3><table><?php foreach ($low as $r): ?><tr><td><a href="products.php?edit=<?= $r['id'] ?>"><?= e($r['name']) ?></a></td><td><span class="pill bad"><?= $r['stock'] ?> left</span></td></tr><?php endforeach; if (!$low) echo '<tr><td class="muted">Stock levels look healthy.</td></tr>'; ?></table></div></div>
<div class="box"><h3>Customer questions</h3><?php if (!$qa) echo '<p class="muted">No questions yet.</p>'; foreach ($qa as $r): ?><div class="review"><b><?= e($r['pname']) ?></b> — <?= e($r['uname']) ?>: <?= e($r['question']) ?>
<form method="post" class="inline-form mt"><?= csrf_field() ?><input type="hidden" name="action" value="answer"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input name="answer" value="<?= e($r['answer']) ?>" placeholder="Your answer…" required style="min-width:260px"><button class="btn btn-sm">Answer</button></form></div><?php endforeach; ?></div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
