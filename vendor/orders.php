<?php
require_once __DIR__ . '/../db.php'; $vend = require_vendor(); $vid = (int)$vend['id'];
if (is_post() && post('action') === 'item_status' && in_array(post('status'), ORDER_STATUSES, true)) {
    $iid = (int)post('id'); $new = post('status');
    $it = $pdo->prepare('SELECT * FROM order_items WHERE id=? AND vendor_id=?'); $it->execute([$iid, $vid]); $it = $it->fetch();
    if ($it && $it['item_status'] !== 'Cancelled' && $new !== 'Cancelled') {
        $pdo->prepare('UPDATE order_items SET item_status=? WHERE id=?')->execute([$new, $iid]);
        // If every live item of the order now shares one status, mirror it on the order
        $all = $pdo->prepare("SELECT DISTINCT item_status FROM order_items WHERE order_id=? AND item_status<>'Cancelled'"); $all->execute([$it['order_id']]); $sts = $all->fetchAll(PDO::FETCH_COLUMN);
        if (count($sts) === 1) $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$sts[0], $it['order_id']]);
        elseif (in_array('Processing', $sts) || in_array('Shipped', $sts)) $pdo->prepare("UPDATE orders SET status='Processing' WHERE id=? AND status='Pending'")->execute([$it['order_id']]);
        $u = $pdo->prepare('SELECT u.email,u.name FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?'); $u->execute([$it['order_id']]);
        if ($u = $u->fetch()) send_mail($u['email'], 'Update on ' . order_ref($it['order_id']), "Hi {$u['name']}, \"{$it['product_name']}\" is now: $new.");
        flash('success', 'Item marked as ' . $new . '.');
    } else flash('danger', 'This item cannot be updated.');
    redirect('vendor/orders.php');
}
require_once __DIR__ . '/../includes/orders.php';
$panel = 'vendor'; $title = 'My Orders'; require __DIR__ . '/../includes/admin_header.php';
$fs = get('status'); $w = 'i.vendor_id=?'; $pr = [$vid]; if (in_array($fs, ORDER_STATUSES, true)) { $w .= ' AND i.item_status=?'; $pr[] = $fs; }
$st = $pdo->prepare("SELECT i.*, o.created_at, o.ship_name, o.ship_phone, o.ship_address, o.ship_city, o.ship_postal, o.payment_method, o.payment_status FROM order_items i JOIN orders o ON o.id=i.order_id WHERE $w ORDER BY i.id DESC LIMIT 200"); $st->execute($pr); $rows = $st->fetchAll(); ?>
<div class="box"><form class="inline-form"><select name="status"><option value="">All statuses</option><?php foreach (ORDER_STATUSES as $s): ?><option <?= $fs === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select><button class="btn btn-sm">Filter</button></form>
<div class="table-wrap mt"><table><tr><th>Order</th><th>Product</th><th>Qty</th><th>Amount</th><th>Ship to</th><th>Status</th><th>Update</th></tr>
<?php foreach ($rows as $r): ?><tr><td><b><?= order_ref($r['order_id']) ?></b><div class="muted small"><?= e(date('d M Y', strtotime($r['created_at']))) ?></div></td><td><?= e($r['product_name']) ?><div class="muted small"><?= e($r['variation']) ?></div></td><td><?= $r['quantity'] ?></td><td><?= money_base($r['quantity'] * $r['price']) ?><div class="muted small"><?= e(strtoupper($r['payment_method'])) ?> · <?= e($r['payment_status']) ?></div></td>
<td class="small"><?= e($r['ship_name']) ?><br><?= e($r['ship_address']) ?>, <?= e($r['ship_city']) ?> <?= e($r['ship_postal']) ?><br>📞 <?= e($r['ship_phone']) ?></td><td><?= status_badge($r['item_status']) ?></td>
<td><?php if ($r['item_status'] !== 'Cancelled' && $r['item_status'] !== 'Delivered'): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="item_status"><input type="hidden" name="id" value="<?= $r['id'] ?>"><select name="status"><?php foreach (['Pending', 'Processing', 'Shipped', 'Delivered'] as $s): ?><option <?= $r['item_status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select><button class="btn btn-sm">Save</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; if (!$rows) echo '<tr><td colspan="7" class="muted">No orders yet.</td></tr>'; ?></table></div></div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
