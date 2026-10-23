<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/orders.php';
$u = require_user();
$tab = in_array(get('tab'), ['profile', 'orders', 'wishlist'], true) ? get('tab') : 'orders';

if (is_post()) {
    $act = post('action');
    if ($act === 'profile') {
        $name = post('name'); $phone = post('phone'); $addr = post('address');
        if (mb_strlen($name) < 2) flash('danger', 'Name is too short.');
        elseif ($phone !== '' && !preg_match('/^[0-9+\-\s]{8,15}$/', $phone)) flash('danger', 'Invalid phone number.');
        else { $pdo->prepare('UPDATE users SET name=?, phone=?, address=? WHERE id=?')->execute([$name, $phone ?: null, $addr ?: null, $u['id']]); flash('success', 'Profile updated.'); }
        redirect('profile.php?tab=profile');
    }
    if ($act === 'password') {
        $new = $_POST['new'] ?? '';
        if (!password_verify($_POST['current'] ?? '', $u['password'])) flash('danger', 'Current password is incorrect.');
        elseif (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) flash('danger', 'New password must be 8+ characters with letters and numbers.');
        else { $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]); flash('success', 'Password changed.'); }
        redirect('profile.php?tab=profile');
    }
    if ($act === 'cancel') {
        $oid = (int)post('order_id');
        $c = $pdo->prepare("SELECT 1 FROM orders WHERE id=? AND user_id=? AND status='Pending'"); $c->execute([$oid, $u['id']]);
        if ($c->fetchColumn()) { cancel_order($oid); flash('info', 'Order cancelled.'); } else flash('danger', 'Only pending orders can be cancelled.');
        redirect('profile.php?tab=orders');
    }
}
$title = 'My Account'; require __DIR__ . '/includes/header.php';
?>
<h1>Hello, <?= e($u['name']) ?></h1>
<div class="tabs"><?php foreach (['orders' => 'My orders', 'wishlist' => 'Wishlist', 'profile' => 'Profile & password'] as $k => $l): ?><a class="<?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?>"><?= $l ?></a><?php endforeach; ?></div>

<?php if ($tab === 'orders'):
  $os = $pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC'); $os->execute([$u['id']]); $orders = $os->fetchAll();
  if (!$orders): ?><div class="box"><p>No orders yet.</p><a class="btn" href="<?= url('search.php') ?>">Start shopping</a></div><?php endif;
  foreach ($orders as $o):
    $it = $pdo->prepare('SELECT * FROM order_items WHERE order_id=?'); $it->execute([$o['id']]); $items = $it->fetchAll(); ?>
  <div class="box"><div class="flex between center-v"><div><b><?= order_ref($o['id']) ?></b> <span class="muted small"><?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?></span></div>
    <div><?= status_badge($o['status']) ?> <?= status_badge($o['payment_status']) ?></div></div>
    <div class="table-wrap"><table><?php foreach ($items as $i): ?><tr><td><?= e($i['product_name']) ?><div class="muted small"><?= e($i['variation']) ?></div></td><td>× <?= $i['quantity'] ?></td><td><?= money_base($i['price'] * $i['quantity']) ?></td><td><?= status_badge($i['item_status']) ?></td></tr><?php endforeach; ?></table></div>
    <p class="flex between center-v"><b>Total: <?= money_base($o['total_amount']) ?><?= $o['coupon_code'] ? ' <span class="muted small">(coupon ' . e($o['coupon_code']) . ')</span>' : '' ?></b>
      <span><a class="btn btn-sm btn-outline" target="_blank" href="<?= url('invoice.php?id=' . $o['id']) ?>">Invoice</a>
      <?php if ($o['status'] === 'Pending'): ?><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="order_id" value="<?= $o['id'] ?>"><button class="btn btn-sm btn-danger" data-confirm="Cancel this order?">Cancel</button></form><?php endif; ?></span></p></div>
  <?php endforeach; ?>

<?php elseif ($tab === 'wishlist'):
  $w = $pdo->prepare(product_select() . 'JOIN wishlist w ON w.product_id=p.id WHERE w.user_id=? AND p.status=1 ORDER BY w.created_at DESC'); $w->execute([$u['id']]); $rows = $w->fetchAll();
  if (!$rows): ?><div class="box"><p>Your wishlist is empty. Tap the ♥ on any product to save it.</p></div><?php endif; ?>
  <div class="grid products"><?php foreach ($rows as $p) product_card($p); ?></div>

<?php else: ?>
<div class="grid two">
  <div class="box"><h3>Profile</h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <label>Name</label><input name="name" value="<?= e($u['name']) ?>" required><label>Email</label><input value="<?= e($u['email']) ?>" disabled>
    <label>Phone</label><input name="phone" value="<?= e($u['phone']) ?>"><label>Default address</label><textarea name="address"><?= e($u['address']) ?></textarea>
    <p><button class="btn">Save profile</button></p></form></div>
  <div class="box"><h3>Change password</h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password">
    <label>Current password</label><input type="password" name="current" required><label>New password</label><input type="password" name="new" minlength="8" required>
    <p><button class="btn">Update password</button></p></form></div>
</div>
<?php endif; require __DIR__ . '/includes/footer.php'; ?>
