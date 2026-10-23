<?php
require_once __DIR__ . '/../db.php'; require_admin();
require_once __DIR__ . '/../includes/orders.php';
if (is_post()) {
    $id = (int)post('id'); $act = post('action');
    if ($act === 'status' && in_array(post('status'), ORDER_STATUSES, true)) {
        $new = post('status');
        if ($new === 'Cancelled') cancel_order($id);
        else {
            $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$new, $id]);
            $pdo->prepare("UPDATE order_items SET item_status=? WHERE order_id=? AND item_status<>'Cancelled'")->execute([$new, $id]);
            if ($new === 'Delivered') $pdo->prepare("UPDATE orders SET payment_status='paid' WHERE id=? AND payment_method='cod'")->execute([$id]);
        }
        $u = $pdo->prepare('SELECT u.email,u.name FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?'); $u->execute([$id]);
        if ($u = $u->fetch()) send_mail($u['email'], 'Order ' . order_ref($id) . ' is now ' . $new, "Hi {$u['name']}, the status of your order " . order_ref($id) . " changed to: $new.");
        flash('success', 'Order status updated to ' . $new . '.');
    }
    redirect('admin/orders.php' . (post('back_view') ? '?view=' . $id : ''));
}
$panel = 'admin'; $title = 'Orders'; require __DIR__ . '/../includes/admin_header.php';

if ($vid = (int)get('view')):
  $o = $pdo->prepare('SELECT o.*, u.name uname, u.email FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?'); $o->execute([$vid]); $o = $o->fetch();
  if (!$o) { echo '<div class="box">Order not found.</div>'; require __DIR__ . '/../includes/admin_footer.php'; exit; }
  $it = $pdo->prepare('SELECT i.*, v.name vname FROM order_items i JOIN vendors v ON v.id=i.vendor_id WHERE i.order_id=?'); $it->execute([$vid]); $items = $it->fetchAll(); ?>
  <p><a href="orders.php">← All orders</a></p>
  <div class="grid cols"><div class="box"><h3><?= order_ref($vid) ?> <?= status_badge($o['status']) ?></h3><div class="table-wrap"><table><tr><th>Item</th><th>Vendor</th><th>Qty</th><th>Price</th><th>Item status</th></tr>
    <?php foreach ($items as $i): ?><tr><td><?= e($i['product_name']) ?><div class="muted small"><?= e($i['variation']) ?></div></td><td><?= e($i['vname']) ?></td><td><?= $i['quantity'] ?></td><td><?= money_base($i['price'] * $i['quantity']) ?></td><td><?= status_badge($i['item_status']) ?></td></tr><?php endforeach; ?>
    <tr><td colspan="3" class="right">Subtotal</td><td colspan="2"><?= money_base($o['subtotal']) ?></td></tr><tr><td colspan="3" class="right">Discount <?= e($o['coupon_code']) ?></td><td colspan="2">− <?= money_base($o['discount']) ?></td></tr>
    <tr><td colspan="3" class="right"><b>Total</b></td><td colspan="2"><b><?= money_base($o['total_amount']) ?></b></td></tr></table></div></div>
  <div class="box"><h3>Customer</h3><p><b><?= e($o['uname']) ?></b><br><?= e($o['email']) ?></p><p><?= e($o['ship_name']) ?><br><?= e($o['ship_address']) ?><br><?= e($o['ship_city']) ?> <?= e($o['ship_postal']) ?><br>📞 <?= e($o['ship_phone']) ?></p>
    <p>Payment: <?= e(strtoupper($o['payment_method'])) ?> <?= status_badge($o['payment_status']) ?><br><span class="muted small">Ref: <?= e($o['payment_ref']) ?></span></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $vid ?>"><input type="hidden" name="back_view" value="1">
      <label>Change status</label><select name="status"><?php foreach (ORDER_STATUSES as $s): ?><option <?= $o['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select><p><button class="btn btn-block" data-confirm="Update order status? The customer will be emailed.">Update status</button></p></form>
    <a class="btn btn-outline btn-block" target="_blank" href="<?= url('invoice.php?id=' . $vid) ?>">View invoice</a></div></div>
<?php else:
  $fs = get('status'); $q = get('q'); $w = []; $pr = [];
  if (in_array($fs, ORDER_STATUSES, true)) { $w[] = 'o.status=?'; $pr[] = $fs; }
  if ($q !== '') { $w[] = '(u.name LIKE ? OR u.email LIKE ? OR o.id=?)'; array_push($pr, "%$q%", "%$q%", (int)preg_replace('/\D/', '', $q)); }
  $st = $pdo->prepare('SELECT o.*, u.name FROM orders o JOIN users u ON u.id=o.user_id' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY o.id DESC LIMIT 200'); $st->execute($pr); $rows = $st->fetchAll(); ?>
  <div class="box"><form class="inline-form"><input name="q" placeholder="Order #, name, email" value="<?= e($q) ?>"><select name="status"><option value="">All statuses</option><?php foreach (ORDER_STATUSES as $s): ?><option <?= $fs === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select><button class="btn btn-sm">Filter</button></form>
  <div class="table-wrap mt"><table><tr><th>Order</th><th>Customer</th><th>Date</th><th>Total</th><th>Payment</th><th>Status</th><th>Update</th></tr>
  <?php foreach ($rows as $r): ?><tr><td><a href="?view=<?= $r['id'] ?>"><b><?= order_ref($r['id']) ?></b></a></td><td><?= e($r['name']) ?></td><td><?= e(date('d M Y', strtotime($r['created_at']))) ?></td><td><?= money_base($r['total_amount']) ?></td><td><?= e(strtoupper($r['payment_method'])) ?> <?= status_badge($r['payment_status']) ?></td><td><?= status_badge($r['status']) ?></td>
  <td><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $r['id'] ?>"><select name="status"><?php foreach (ORDER_STATUSES as $s): ?><option <?= $r['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select><button class="btn btn-sm">Go</button></form></td></tr><?php endforeach; if (!$rows) echo '<tr><td colspan="7" class="muted">No orders found.</td></tr>'; ?></table></div></div>
<?php endif; require __DIR__ . '/../includes/admin_footer.php'; ?>
