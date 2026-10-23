<?php
require_once __DIR__ . '/db.php';
if (is_post()) {
    $act = isset($_POST['remove']) ? 'remove' : post('action');
    if ($act === 'add') {
        $pid = (int)post('product_id'); $qty = max(1, (int)post('qty', '1'));
        $st = $pdo->prepare(product_select() . 'WHERE p.id=? AND p.status=1'); $st->execute([$pid]); $p = $st->fetch();
        $ids = array_map('intval', array_values((array)($_POST['var'] ?? [])));
        $required = $pdo->prepare('SELECT COUNT(DISTINCT attribute) FROM product_variations WHERE product_id=?'); $required->execute([$pid]);
        if (!$p || $p['stock'] <= 0) flash('danger', 'This product is unavailable.');
        elseif (count($ids) < (int)$required->fetchColumn()) flash('warning', 'Please choose all options (size, colour…).');
        else {
            $ok = true;
            foreach ($ids as $vid) { $c = $pdo->prepare('SELECT COUNT(*) FROM product_variations WHERE id=? AND product_id=? AND stock>0'); $c->execute([$vid, $pid]); if (!$c->fetchColumn()) $ok = false; }
            if (!$ok) flash('danger', 'Selected option is not available.');
            else {
                $k = cart_key($pid, $ids);
                $_SESSION['cart'][$k] = ['pid' => $pid, 'vars' => $ids, 'qty' => ($_SESSION['cart'][$k]['qty'] ?? 0) + $qty];
                flash('success', 'Added to cart.');
                if (post('buy_now')) redirect('checkout.php');
            }
        }
        back('cart.php');
    }
    if ($act === 'update') { foreach ((array)($_POST['qty'] ?? []) as $k => $q) { if (isset($_SESSION['cart'][$k])) { (int)$q <= 0 ? $_SESSION['cart'][$k] = null : $_SESSION['cart'][$k]['qty'] = (int)$q; if ($_SESSION['cart'][$k] === null) unset($_SESSION['cart'][$k]); } } flash('success', 'Cart updated.'); }
    if ($act === 'remove') { unset($_SESSION['cart'][post('remove')]); flash('info', 'Item removed.'); }
    if ($act === 'clear') { $_SESSION['cart'] = []; unset($_SESSION['coupon']); }
    if ($act === 'coupon') {
        $cart = cart_lines(); [$ok, $d, $msg] = coupon_check(post('code'), $cart['subtotal']);
        if ($ok) $_SESSION['coupon'] = strtoupper(post('code'));
        flash($ok ? 'success' : 'danger', $msg);
    }
    if ($act === 'coupon_remove') unset($_SESSION['coupon']);
    redirect('cart.php');
}
$title = 'Shopping Cart'; require __DIR__ . '/includes/header.php';
$cart = cart_lines(); $lines = $cart['lines']; $discount = 0; $coupon = $_SESSION['coupon'] ?? null;
if ($coupon) { [$ok, $discount, $msg] = coupon_check($coupon, $cart['subtotal']); if (!$ok) { unset($_SESSION['coupon']); $coupon = null; $discount = 0; echo '<div class="alert alert-warning">' . e($msg) . '</div>'; } }
$total = $cart['subtotal'] - $discount;
?>
<h1>Shopping cart</h1>
<?php if (!$lines): ?><div class="box"><p>Your cart is empty.</p><a class="btn" href="<?= url('search.php') ?>">Continue shopping</a></div>
<?php else: ?>
<div class="grid cols">
  <form method="post" class="box"><?= csrf_field() ?><input type="hidden" name="action" value="update">
    <div class="table-wrap"><table><thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Total</th><th></th></tr></thead><tbody>
    <?php foreach ($lines as $l): $p = $l['p']; ?>
      <tr><td><div class="flex center-v"><img class="thumb" src="<?= img_src($p['image'], $p['name']) ?>" alt=""><div><a href="<?= product_url($p) ?>"><b><?= e($p['name']) ?></b></a><div class="muted small"><?= e($l['label']) ?></div><?php if ($l['qty'] == 0): ?><span class="pill bad">Out of stock</span><?php endif; ?></div></div></td>
        <td><?= money($l['price']) ?></td>
        <td><input class="qty" type="number" name="qty[<?= e($l['key']) ?>]" value="<?= $l['qty'] ?>" min="0" max="<?= max(0, $l['limit']) ?>"></td>
        <td><?= money($l['total']) ?></td>
        <td><button class="btn btn-sm btn-danger" name="remove" value="<?= e($l['key']) ?>" aria-label="Remove">✕</button></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <p class="flex between"><button class="btn btn-outline" type="submit">Update cart</button>
      <button class="btn btn-sm btn-danger" name="action" value="clear" data-confirm="Empty your cart?">Clear cart</button></p>
  </form>
  <div class="box"><h3>Order summary</h3>
    <p class="flex between"><span>Subtotal</span><b><?= money($cart['subtotal']) ?></b></p>
    <?php if ($coupon): ?><p class="flex between"><span>Coupon <b><?= e($coupon) ?></b></span><b style="color:#16a34a">− <?= money($discount) ?></b></p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="coupon_remove"><button class="btn btn-sm btn-outline">Remove coupon</button></form>
    <?php else: ?>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="coupon"><input name="code" placeholder="Coupon code" required><button class="btn btn-sm">Apply</button></form>
      <p class="muted small">Try WELCOME10 or FLAT200</p>
    <?php endif; ?>
    <hr><p class="flex between" style="font-size:1.2rem"><span>Total</span><b><?= money($total) ?></b></p>
    <a class="btn btn-block" href="<?= url('checkout.php') ?>">Proceed to checkout</a>
  </div>
</div>
<?php endif; require __DIR__ . '/includes/footer.php'; ?>
