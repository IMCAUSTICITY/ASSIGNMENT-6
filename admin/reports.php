<?php
require_once __DIR__ . '/../db.php'; require_admin();
$from = get('from', date('Y-m-d', strtotime('-29 day'))); $to = get('to', date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-29 day'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');
$P = [$from, $to];
$q = fn($sql) => (function ($s) use ($pdo, $P) { $st = $pdo->prepare($s); $st->execute($P); return $st->fetchAll(); })($sql);
$daily  = $q("SELECT DATE(created_at) d, COUNT(*) orders, SUM(total_amount) v, SUM(discount) disc FROM orders WHERE status<>'Cancelled' AND DATE(created_at) BETWEEN ? AND ? GROUP BY d ORDER BY d");
$byVendor = $q("SELECT v.name, SUM(i.quantity) units, SUM(i.quantity*i.price) revenue FROM order_items i JOIN orders o ON o.id=i.order_id JOIN vendors v ON v.id=i.vendor_id WHERE o.status<>'Cancelled' AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY v.id ORDER BY revenue DESC");
$byCat = $q("SELECT COALESCE(c.name,'Uncategorised') name, SUM(i.quantity) units, SUM(i.quantity*i.price) revenue FROM order_items i JOIN orders o ON o.id=i.order_id LEFT JOIN products p ON p.id=i.product_id LEFT JOIN categories c ON c.id=p.category_id WHERE o.status<>'Cancelled' AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY c.id ORDER BY revenue DESC");
$topProd = $q("SELECT i.product_name name, SUM(i.quantity) units, SUM(i.quantity*i.price) revenue FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.status<>'Cancelled' AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY i.product_id, i.product_name ORDER BY units DESC LIMIT 10");
$byStatus = $q("SELECT status, COUNT(*) n, SUM(total_amount) v FROM orders WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status");
$byPay = $q("SELECT payment_method, COUNT(*) n, SUM(total_amount) v FROM orders WHERE status<>'Cancelled' AND DATE(created_at) BETWEEN ? AND ? GROUP BY payment_method");
if ($x = get('export')) {
    $map = ['daily' => [['date', 'orders', 'revenue', 'discounts'], $daily], 'vendors' => [['vendor', 'units', 'revenue'], $byVendor], 'categories' => [['category', 'units', 'revenue'], $byCat], 'products' => [['product', 'units', 'revenue'], $topProd],
        'orders' => [['order', 'customer', 'date', 'status', 'payment', 'total'], array_map(fn($r) => [order_ref($r['id']), $r['name'], $r['created_at'], $r['status'], $r['payment_method'], $r['total_amount']], $q('SELECT o.*, u.name FROM orders o JOIN users u ON u.id=o.user_id WHERE DATE(o.created_at) BETWEEN ? AND ? ORDER BY o.id'))]];
    if (isset($map[$x])) csv_download('report-' . $x, $map[$x][0], $map[$x][1]);
}
$tRev = array_sum(array_column($daily, 'v')); $tOrd = array_sum(array_column($daily, 'orders')); $tDisc = array_sum(array_column($daily, 'disc'));
$panel = 'admin'; $title = 'Sales & Order Reports'; require __DIR__ . '/../includes/admin_header.php'; ?>
<div class="box no-print flex between center-v"><form class="inline-form"><label style="margin:0">From</label><input type="date" name="from" value="<?= e($from) ?>"><label style="margin:0">To</label><input type="date" name="to" value="<?= e($to) ?>"><button class="btn btn-sm">Apply</button></form>
<div class="flex"><button class="btn btn-sm" data-print>🖨 Print / Save as PDF</button><?php foreach (['daily' => 'Daily sales', 'vendors' => 'Vendors', 'categories' => 'Categories', 'products' => 'Products', 'orders' => 'All orders'] as $k => $l): ?><a class="btn btn-sm btn-outline" href="?from=<?= e($from) ?>&to=<?= e($to) ?>&export=<?= $k ?>">⬇ <?= $l ?> CSV</a><?php endforeach; ?></div></div>
<h3>Report: <?= e($from) ?> → <?= e($to) ?></h3>
<div class="grid stats"><div class="stat"><b><?= money_base($tRev) ?></b><span>Net revenue</span></div><div class="stat"><b><?= $tOrd ?></b><span>Orders (excl. cancelled)</span></div><div class="stat"><b><?= money_base($tOrd ? $tRev / $tOrd : 0) ?></b><span>Average order value</span></div><div class="stat"><b><?= money_base($tDisc) ?></b><span>Discounts given</span></div></div>
<?php $span = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1); if ($span <= 31): $s = []; for ($i = 0; $i < $span; $i++) $s[date('Y-m-d', strtotime("$from +$i day"))] = 0; foreach ($daily as $d) $s[$d['d']] = (float)$d['v']; $lab = []; foreach ($s as $k => $v) $lab[date('d/m', strtotime($k))] = $v; ?>
<div class="box"><h3>Daily revenue</h3><?php bar_chart($lab); ?></div><?php endif; ?>
<div class="grid two"><div class="box"><h3>Sales by vendor</h3><table><tr><th>Vendor</th><th>Units</th><th>Revenue</th></tr><?php foreach ($byVendor as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= $r['units'] ?></td><td><?= money_base($r['revenue']) ?></td></tr><?php endforeach; ?></table></div>
<div class="box"><h3>Sales by category</h3><table><tr><th>Category</th><th>Units</th><th>Revenue</th></tr><?php foreach ($byCat as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= $r['units'] ?></td><td><?= money_base($r['revenue']) ?></td></tr><?php endforeach; ?></table></div></div>
<div class="grid two"><div class="box"><h3>Top 10 products</h3><table><tr><th>Product</th><th>Units</th><th>Revenue</th></tr><?php foreach ($topProd as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= $r['units'] ?></td><td><?= money_base($r['revenue']) ?></td></tr><?php endforeach; ?></table></div>
<div class="box"><h3>Orders by status</h3><table><?php foreach ($byStatus as $r): ?><tr><td><?= status_badge($r['status']) ?></td><td><?= $r['n'] ?> orders</td><td><?= money_base($r['v']) ?></td></tr><?php endforeach; ?></table>
<h3>Payment methods</h3><table><?php foreach ($byPay as $r): ?><tr><td><?= e(strtoupper($r['payment_method'])) ?></td><td><?= $r['n'] ?> orders</td><td><?= money_base($r['v']) ?></td></tr><?php endforeach; ?></table></div></div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
