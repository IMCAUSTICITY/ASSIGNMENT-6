<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/orders.php';
$u = require_user();

/* ---- Order confirmation view ---- */
if ($oid = (int)get('success')) {
    $st = $pdo->prepare('SELECT * FROM orders WHERE id=? AND user_id=?'); $st->execute([$oid, $u['id']]); $o = $st->fetch();
    if (!$o) redirect('profile.php?tab=orders');
    $it = $pdo->prepare('SELECT * FROM order_items WHERE order_id=?'); $it->execute([$oid]); $items = $it->fetchAll();
    $title = 'Order confirmed'; require __DIR__ . '/includes/header.php'; ?>
    <div class="box" style="text-align:center"><div style="font-size:3rem">✅</div><h1>Thank you, <?= e($u['name']) ?>!</h1>
      <p>Your order <b><?= order_ref($oid) ?></b> has been placed. A confirmation email was sent to <?= e($u['email']) ?>.</p></div>
    <div class="box"><h3>Order summary</h3><div class="table-wrap"><table><tr><th>Item</th><th>Qty</th><th>Price</th></tr>
      <?php foreach ($items as $i): ?><tr><td><?= e($i['product_name']) ?><div class="muted small"><?= e($i['variation']) ?></div></td><td><?= $i['quantity'] ?></td><td><?= money_base($i['price'] * $i['quantity']) ?></td></tr><?php endforeach; ?>
      <tr><td colspan="2" class="right">Discount</td><td>− <?= money_base($o['discount']) ?></td></tr>
      <tr><td colspan="2" class="right"><b>Total paid / payable</b></td><td><b><?= money_base($o['total_amount']) ?></b></td></tr></table></div>
      <p class="muted">Payment: <?= e(strtoupper($o['payment_method'])) ?> · <?= status_badge($o['payment_status']) ?> · Ship to <?= e($o['ship_name']) ?>, <?= e($o['ship_address']) ?>, <?= e($o['ship_city']) ?> <?= e($o['ship_postal']) ?></p>
      <a class="btn" href="<?= url('invoice.php?id=' . $oid) ?>" target="_blank">Print invoice</a> <a class="btn btn-outline" href="<?= url('profile.php?tab=orders') ?>">My orders</a></div>
    <?php require __DIR__ . '/includes/footer.php'; exit;
}

$cart = cart_lines();
if (!$cart['lines']) { flash('warning', 'Your cart is empty.'); redirect('cart.php'); }
$coupon = $_SESSION['coupon'] ?? null; $discount = 0;
if ($coupon) { [$ok, $discount] = coupon_check($coupon, $cart['subtotal']); if (!$ok) { $coupon = null; $discount = 0; unset($_SESSION['coupon']); } }
$total = $cart['subtotal'] - $discount;
$f = ['name' => $u['name'], 'phone' => (string)$u['phone'], 'address' => (string)$u['address'], 'city' => '', 'postal' => '', 'payment_method' => 'cod'];

if (is_post()) {
    foreach ($f as $k => $_) $f[$k] = post($k, $f[$k]);
    $errs = [];
    if (mb_strlen($f['name']) < 2) $errs[] = 'Please enter your full name.';
    if (!preg_match('/^[0-9+\-\s]{8,15}$/', $f['phone'])) $errs[] = 'Enter a valid phone number.';
    if (mb_strlen($f['address']) < 6) $errs[] = 'Please enter your full address.';
    if ($f['city'] === '') $errs[] = 'City is required.';
    if (!preg_match('/^[A-Za-z0-9\- ]{4,10}$/', $f['postal'])) $errs[] = 'Enter a valid postal code.';
    foreach ($cart['lines'] as $l) if ($l['qty'] < 1) $errs[] = '"' . $l['p']['name'] . '" is out of stock. Remove it from the cart.';
    if (!$errs) {
        $items = array_map(fn($l) => ['pid' => $l['p']['id'], 'vars' => $l['vars'], 'qty' => $l['qty']], $cart['lines']);
        [$ok, $msg, $oid] = place_order($u['id'], $items, $f, $coupon, $f['payment_method'], $_POST);
        if ($ok) { $_SESSION['cart'] = []; unset($_SESSION['coupon']); redirect('checkout.php?success=' . $oid); }
        $errs[] = $msg;
    }
    foreach ($errs as $er) flash('danger', $er);
}
$title = 'Checkout'; require __DIR__ . '/includes/header.php';
?>
<h1>Checkout</h1>
<form method="post" class="grid cols" novalidate>
  <?= csrf_field() ?>
  <div class="box"><h3>Shipping details</h3>
    <label>Full name</label><input name="name" value="<?= e($f['name']) ?>" required>
    <div class="grid two"><div><label>Phone</label><input name="phone" value="<?= e($f['phone']) ?>" required></div><div><label>Postal code</label><input name="postal" value="<?= e($f['postal']) ?>" required></div></div>
    <label>Address</label><textarea name="address" required><?= e($f['address']) ?></textarea>
    <label>City</label><input name="city" value="<?= e($f['city']) ?>" required>
    <h3 class="mt">Payment</h3>
    <label class="pay-opt"><input type="radio" name="payment_method" value="cod" <?= $f['payment_method'] === 'cod' ? 'checked' : '' ?>> 💵 Cash on delivery</label>
    <label class="pay-opt"><input type="radio" name="payment_method" value="stripe" <?= $f['payment_method'] === 'stripe' ? 'checked' : '' ?>> 💳 Card (Stripe sandbox)</label>
    <div class="pay-box" data-method="stripe"><div class="grid three"><div><label>Card number</label><input name="card_number" placeholder="4242 4242 4242 4242" autocomplete="off"></div><div><label>Expiry (MM/YY)</label><input name="card_expiry" placeholder="12/30"></div><div><label>CVC</label><input name="card_cvc" placeholder="123"></div></div><p class="muted small">Sandbox only – no real charge. Use test card 4242 4242 4242 4242.</p></div>
    <label class="pay-opt"><input type="radio" name="payment_method" value="razorpay" <?= $f['payment_method'] === 'razorpay' ? 'checked' : '' ?>> 🇮🇳 UPI (Razorpay sandbox)</label>
    <div class="pay-box" data-method="razorpay"><label>UPI ID</label><input name="upi_id" placeholder="success@razorpay" autocomplete="off"><p class="muted small">Sandbox only. Use success@razorpay to simulate a successful payment.</p></div>
  </div>
  <div class="box"><h3>Your order</h3>
    <?php foreach ($cart['lines'] as $l): ?><p class="flex between small"><span><?= e($l['p']['name']) ?> × <?= $l['qty'] ?><br><span class="muted"><?= e($l['label']) ?></span></span><b><?= money($l['total']) ?></b></p><?php endforeach; ?>
    <hr><p class="flex between"><span>Subtotal</span><b><?= money($cart['subtotal']) ?></b></p>
    <?php if ($coupon): ?><p class="flex between"><span>Coupon <?= e($coupon) ?></span><b>− <?= money($discount) ?></b></p><?php endif; ?>
    <p class="flex between" style="font-size:1.2rem"><span>Total</span><b><?= money($total) ?></b></p>
    <p class="muted small">Charged in INR (<?= money_base($total) ?>). Currency selector is for display.</p>
    <button class="btn btn-block">Place order</button></div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
