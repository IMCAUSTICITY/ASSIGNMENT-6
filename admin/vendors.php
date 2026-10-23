<?php
require_once __DIR__ . '/../db.php'; require_admin();
if (is_post()) {
    $act = post('action'); $id = (int)post('id');
    if ($act === 'status' && in_array(post('status'), ['approved', 'pending', 'suspended'], true)) {
        $pdo->prepare('UPDATE vendors SET status=? WHERE id=?')->execute([post('status'), $id]);
        $v = $pdo->prepare('SELECT u.email, v.name FROM vendors v JOIN users u ON u.id=v.user_id WHERE v.id=?'); $v->execute([$id]); if ($v = $v->fetch()) send_mail($v['email'], 'Your vendor account is ' . post('status'), "Your store \"{$v['name']}\" is now " . post('status') . '.');
        flash('success', 'Vendor status updated.');
    }
    if ($act === 'delete') { $u = $pdo->prepare('SELECT user_id FROM vendors WHERE id=?'); $u->execute([$id]); if ($uid = $u->fetchColumn()) $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]); flash('success', 'Vendor and their account deleted.'); }
    if ($act === 'add') {
        $name = post('name'); $email = strtolower(post('email')); $store = post('store'); $pass = $_POST['password'] ?? '';
        $dup = $pdo->prepare('SELECT 1 FROM users WHERE email=?'); $dup->execute([$email]);
        if (mb_strlen($name) < 2 || mb_strlen($store) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) flash('danger', 'Fill all fields correctly (password min 8 chars).');
        elseif ($dup->fetchColumn()) flash('danger', 'Email already registered.');
        else {
            $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'vendor')")->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            $pdo->prepare("INSERT INTO vendors (user_id,name,status) VALUES (?,?, 'approved')")->execute([$pdo->lastInsertId(), $store]); flash('success', 'Vendor created and approved.');
        }
    }
    redirect('admin/vendors.php');
}
$panel = 'admin'; $title = 'Vendors'; require __DIR__ . '/../includes/admin_header.php';
$rows = $pdo->query("SELECT v.*, u.name owner, u.email, (SELECT COUNT(*) FROM products p WHERE p.vendor_id=v.id) np,
  (SELECT COALESCE(SUM(i.quantity*i.price),0) FROM order_items i WHERE i.vendor_id=v.id AND i.item_status<>'Cancelled') sales FROM vendors v JOIN users u ON u.id=v.user_id ORDER BY FIELD(v.status,'pending','approved','suspended'), v.id")->fetchAll(); ?>
<div class="box table-wrap"><table><tr><th>Store</th><th>Owner</th><th>Products</th><th>Sales</th><th>Status</th><th>Actions</th></tr>
<?php foreach ($rows as $r): ?><tr><td><b><?= e($r['name']) ?></b></td><td><?= e($r['owner']) ?><div class="muted small"><?= e($r['email']) ?></div></td><td><?= $r['np'] ?></td><td><?= money_base($r['sales']) ?></td><td><?= status_badge($r['status']) ?></td>
<td class="flex"><?php foreach (['approved' => 'Approve', 'suspended' => 'Suspend'] as $s => $l): if ($r['status'] !== $s): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="status" value="<?= $s ?>"><button class="btn btn-sm <?= $s === 'approved' ? 'btn-ok' : 'btn-outline' ?>"><?= $l ?></button></form><?php endif; endforeach; ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-danger" data-confirm="Delete this vendor, their account and ALL their products?">Delete</button></form></td></tr><?php endforeach; ?></table></div>
<div class="box"><h3>Add vendor</h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add"><div class="grid three">
<div><label>Owner name</label><input name="name" required></div><div><label>Email</label><input type="email" name="email" required></div><div><label>Store name</label><input name="store" required></div></div>
<label>Password (min 8)</label><input type="password" name="password" minlength="8" required style="max-width:300px"><p><button class="btn">Create vendor</button></p></form></div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
