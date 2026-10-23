<?php
$panel = 'admin'; $title = 'Dashboard'; require __DIR__ . '/../includes/admin_header.php';
$one = fn($sql) => $pdo->query($sql)->fetchColumn();
$rev = (float)$one("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status<>'Cancelled'");
$ord = (int)$one('SELECT COUNT(*) FROM orders'); $pending = (int)$one("SELECT COUNT(*) FROM orders WHERE status='Pending'");
$users = (int)$one("SELECT COUNT(*) FROM users WHERE role='customer'"); $prods = (int)$one('SELECT COUNT(*) FROM products');
$vend = (int)$one("SELECT COUNT(*) FROM vendors WHERE status='approved'"); $pv = (int)$one("SELECT COUNT(*) FROM vendors WHERE status='pending'");
$today = (float)$one("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status<>'Cancelled' AND DATE(created_at)=CURDATE()");
$series = daily_series($pdo->query("SELECT DATE(created_at) d, SUM(total_amount) v FROM orders WHERE status<>'Cancelled' AND created_at>=DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY d")->fetchAll(), 7);
$top = $pdo->query("SELECT product_name, SUM(quantity) q, SUM(quantity*price) r FROM order_items WHERE item_status<>'Cancelled' GROUP BY product_id, product_name ORDER BY q DESC LIMIT 5")->fetchAll();
$status = $pdo->query('SELECT status, COUNT(*) n FROM orders GROUP BY status')->fetchAll();
$recent = $pdo->query('SELECT o.*, u.name FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.id DESC LIMIT 6')->fetchAll();
$low = $pdo->query('SELECT id,name,stock FROM products WHERE stock<=5 AND status=1 ORDER BY stock LIMIT 6')->fetchAll();
$newUsers = $pdo->query("SELECT name,email,created_at FROM users ORDER BY id DESC LIMIT 5")->fetchAll();
?>
<?php if ($pv): ?><div class="alert alert-warning"><?= $pv ?> vendor(s) awaiting approval. <a href="vendors.php">Review now →</a></div><?php endif; ?>
<div class="grid stats">
  <div class="stat"><b><?= money_base($rev) ?></b><span>Total revenue</span></div><div class="stat"><b><?= money_base($today) ?></b><span>Today's sales</span></div>
  <div class="stat"><b><?= $ord ?></b><span>Orders (<?= $pending ?> pending)</span></div><div class="stat"><b><?= $users ?></b><span>Customers</span></div>
  <div class="stat"><b><?= $prods ?></b><span>Products</span></div><div class="stat"><b><?= $vend ?></b><span>Active vendors</span></div></div>
<div class="grid cols">
  <div class="box"><h3>Sales – last 7 days</h3><?php bar_chart($series); ?></div>
  <div class="box"><h3>Orders by status</h3><?php $tot = max(1, array_sum(array_column($status, 'n'))); foreach ($status as $s): ?>
    <p class="flex between small" style="margin-bottom:2px"><span><?= e($s['status']) ?></span><b><?= $s['n'] ?></b></p><div class="hbar"><i style="width:<?= round($s['n'] / $tot * 100) ?>%"></i></div><?php endforeach; ?></div></div>
<div class="grid two">
  <div class="box"><h3>Top selling products</h3><table><tr><th>Product</th><th>Units</th><th>Revenue</th></tr><?php foreach ($top as $t): ?><tr><td><?= e($t['product_name']) ?></td><td><?= $t['q'] ?></td><td><?= money_base($t['r']) ?></td></tr><?php endforeach; if (!$top): ?><tr><td colspan="3" class="muted">No sales yet.</td></tr><?php endif; ?></table></div>
  <div class="box"><h3>Low stock alerts</h3><table><?php foreach ($low as $l): ?><tr><td><a href="products.php?edit=<?= $l['id'] ?>"><?= e($l['name']) ?></a></td><td><span class="pill bad"><?= $l['stock'] ?> left</span></td></tr><?php endforeach; if (!$low) echo '<tr><td class="muted">All products are well stocked.</td></tr>'; ?></table></div></div>
<div class="grid two">
  <div class="box"><h3>Recent orders</h3><table><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr><?php foreach ($recent as $o): ?><tr><td><a href="orders.php?view=<?= $o['id'] ?>"><?= order_ref($o['id']) ?></a></td><td><?= e($o['name']) ?></td><td><?= money_base($o['total_amount']) ?></td><td><?= status_badge($o['status']) ?></td></tr><?php endforeach; ?></table></div>
  <div class="box"><h3>Newest users</h3><table><?php foreach ($newUsers as $n): ?><tr><td><?= e($n['name']) ?><div class="muted small"><?= e($n['email']) ?></div></td><td class="muted small"><?= e(date('d M', strtotime($n['created_at']))) ?></td></tr><?php endforeach; ?></table></div></div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
