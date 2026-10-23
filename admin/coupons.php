<?php
require_once __DIR__ . '/../db.php'; require_admin();
if (is_post()) {
    $act = post('action'); $id = (int)post('id');
    if ($act === 'save') {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', post('code'))); $type = post('discount_type') === 'fixed' ? 'fixed' : 'percent'; $val = (float)post('value');
        $exp = post('expiry') ?: null; $min = max(0, (float)post('min_order')); $lim = max(0, (int)post('usage_limit')); $st = post('status') ? 1 : 0;
        $dup = $pdo->prepare('SELECT 1 FROM coupons WHERE code=? AND id<>?'); $dup->execute([$code, $id]);
        if (strlen($code) < 3) flash('danger', 'Coupon code must be at least 3 characters (letters/numbers).');
        elseif ($val <= 0 || ($type === 'percent' && $val > 100)) flash('danger', 'Enter a valid discount value (percent must be 1–100).');
        elseif ($dup->fetchColumn()) flash('danger', 'This coupon code already exists.');
        elseif ($id) { $pdo->prepare('UPDATE coupons SET code=?,discount_type=?,value=?,expiry=?,min_order=?,usage_limit=?,status=? WHERE id=?')->execute([$code, $type, $val, $exp, $min, $lim, $st, $id]); flash('success', 'Coupon updated.'); }
        else { $pdo->prepare('INSERT INTO coupons (code,discount_type,value,expiry,min_order,usage_limit,status) VALUES (?,?,?,?,?,?,?)')->execute([$code, $type, $val, $exp, $min, $lim, $st]); flash('success', 'Coupon created.'); }
    }
    if ($act === 'delete') { $pdo->prepare('DELETE FROM coupons WHERE id=?')->execute([$id]); flash('success', 'Coupon deleted.'); }
    redirect('admin/coupons.php');
}
$panel = 'admin'; $title = 'Coupons & Discounts'; require __DIR__ . '/../includes/admin_header.php';
$edit = null; if (get('edit')) { $s = $pdo->prepare('SELECT * FROM coupons WHERE id=?'); $s->execute([(int)get('edit')]); $edit = $s->fetch(); }
$c = $edit ?: ['id' => 0, 'code' => '', 'discount_type' => 'percent', 'value' => '', 'expiry' => '', 'min_order' => 0, 'usage_limit' => 0, 'status' => 1];
$rows = $pdo->query('SELECT * FROM coupons ORDER BY id DESC')->fetchAll(); ?>
<div class="grid cols"><div class="box table-wrap"><table><tr><th>Code</th><th>Discount</th><th>Min order</th><th>Used</th><th>Expiry</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): $expired = $r['expiry'] && $r['expiry'] < date('Y-m-d'); ?><tr><td><b><?= e($r['code']) ?></b></td><td><?= $r['discount_type'] === 'percent' ? (float)$r['value'] . '%' : money_base($r['value']) ?></td><td><?= money_base($r['min_order']) ?></td>
<td><?= $r['used_count'] ?><?= $r['usage_limit'] ? '/' . $r['usage_limit'] : '' ?></td><td><?= $r['expiry'] ? e($r['expiry']) : '—' ?></td><td><?= $expired ? '<span class="pill bad">Expired</span>' : ($r['status'] ? '<span class="pill ok">Active</span>' : '<span class="pill warn">Disabled</span>') ?></td>
<td class="flex"><a class="btn btn-sm" href="?edit=<?= $r['id'] ?>">Edit</a><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-danger" data-confirm="Delete coupon?">✕</button></form></td></tr><?php endforeach; ?></table></div>
<div class="box"><h3><?= $edit ? 'Edit coupon' : 'New coupon' ?></h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
<label>Code</label><input name="code" value="<?= e($c['code']) ?>" required style="text-transform:uppercase"><label>Type</label><select name="discount_type"><option value="percent" <?= $c['discount_type'] === 'percent' ? 'selected' : '' ?>>Percentage (%)</option><option value="fixed" <?= $c['discount_type'] === 'fixed' ? 'selected' : '' ?>>Fixed amount (₹)</option></select>
<label>Value</label><input name="value" type="number" step="0.01" min="0.01" value="<?= e($c['value']) ?>" required><label>Expiry date</label><input name="expiry" type="date" value="<?= e($c['expiry']) ?>">
<label>Minimum order (₹)</label><input name="min_order" type="number" min="0" step="0.01" value="<?= e($c['min_order']) ?>"><label>Usage limit (0 = unlimited)</label><input name="usage_limit" type="number" min="0" value="<?= e($c['usage_limit']) ?>">
<p><label style="display:inline"><input type="checkbox" name="status" value="1" <?= $c['status'] ? 'checked' : '' ?>> Active</label></p><button class="btn">Save coupon</button> <?php if ($edit): ?><a class="btn btn-outline" href="coupons.php">Cancel</a><?php endif; ?></form></div></div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
