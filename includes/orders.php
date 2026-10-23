<?php
/**
 * Shared order engine used by checkout.php and api/orders.php.
 * $items: [['pid'=>int,'vars'=>int[],'qty'=>int], ...]
 * $ship : name, phone, address, city, postal
 * Returns [bool ok, string message, int orderId]
 */
function place_order(int $userId, array $items, array $ship, ?string $couponCode, string $method, array $payIn = []): array {
    global $pdo;
    if (!$items) return [false, 'Your cart is empty.', 0];
    if (!in_array($method, ['cod', 'stripe', 'razorpay'], true)) return [false, 'Invalid payment method.', 0];
    try {
        $pdo->beginTransaction();
        $rows = []; $subtotal = 0.0;
        foreach ($items as $it) {
            $qty = (int)$it['qty'];
            if ($qty < 1) throw new RuntimeException('Invalid quantity.');
            $st = $pdo->prepare("SELECT p.*, v.status AS vstatus FROM products p JOIN vendors v ON v.id=p.vendor_id WHERE p.id=? AND p.status=1 FOR UPDATE");
            $st->execute([(int)$it['pid']]); $p = $st->fetch();
            if (!$p || $p['vstatus'] !== 'approved') throw new RuntimeException('A product in your cart is no longer available.');
            if ($p['stock'] < $qty) throw new RuntimeException('Not enough stock for "' . $p['name'] . '" (only ' . $p['stock'] . ' left).');
            $price = unit_price($p); $labels = []; $vids = [];
            foreach ((array)($it['vars'] ?? []) as $vid) {
                $vs = $pdo->prepare('SELECT * FROM product_variations WHERE id=? AND product_id=? FOR UPDATE'); $vs->execute([(int)$vid, $p['id']]); $v = $vs->fetch();
                if (!$v) throw new RuntimeException('Invalid option selected for "' . $p['name'] . '".');
                if ($v['stock'] < $qty) throw new RuntimeException('"' . $p['name'] . ' – ' . $v['value'] . '" is low on stock.');
                $price += (float)$v['price_adjust']; $labels[] = $v['attribute'] . ': ' . $v['value']; $vids[] = (int)$v['id'];
            }
            $rows[] = ['p' => $p, 'qty' => $qty, 'price' => $price, 'label' => implode(', ', $labels), 'vids' => $vids];
            $subtotal += $price * $qty;
        }
        $discount = 0.0; $couponRow = null;
        if ($couponCode) {
            [$ok, $discount, $msg, $couponRow] = coupon_check($couponCode, $subtotal);
            if (!$ok) throw new RuntimeException($msg);
        }
        $total = round($subtotal - $discount, 2);
        [$paid, $pmsg, $ref] = process_payment($method, $payIn, $total);
        if (!$paid) throw new RuntimeException($pmsg);

        $pdo->prepare('INSERT INTO orders (user_id,subtotal,discount,total_amount,coupon_code,payment_method,payment_status,payment_ref,ship_name,ship_phone,ship_address,ship_city,ship_postal) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$userId, $subtotal, $discount, $total, $couponRow['code'] ?? null, $method, $method === 'cod' ? 'unpaid' : 'paid', $ref,
                $ship['name'], $ship['phone'], $ship['address'], $ship['city'], $ship['postal']]);
        $oid = (int)$pdo->lastInsertId();
        foreach ($rows as $r) {
            $pdo->prepare('INSERT INTO order_items (order_id,product_id,vendor_id,product_name,variation,variation_ids,quantity,price) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$oid, $r['p']['id'], $r['p']['vendor_id'], $r['p']['name'], $r['label'] ?: null, implode(',', $r['vids']) ?: null, $r['qty'], $r['price']]);
            $pdo->prepare('UPDATE products SET stock=stock-? WHERE id=?')->execute([$r['qty'], $r['p']['id']]);
            foreach ($r['vids'] as $vid) $pdo->prepare('UPDATE product_variations SET stock=stock-? WHERE id=?')->execute([$r['qty'], $vid]);
        }
        if ($couponRow) $pdo->prepare('UPDATE coupons SET used_count=used_count+1 WHERE id=?')->execute([$couponRow['id']]);
        $pdo->commit();

        $u = $pdo->prepare('SELECT name,email FROM users WHERE id=?'); $u->execute([$userId]); $u = $u->fetch();
        send_mail($u['email'], 'Order ' . order_ref($oid) . ' confirmed', "Hi {$u['name']},\n\nThank you for your order " . order_ref($oid) . ".\nTotal: " . money_base($total) . "\nPayment: " . strtoupper($method) . "\n\nWe'll keep you posted.\n" . setting('site_name'));
        return [true, 'Order placed successfully.', $oid];
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, $e->getMessage(), 0];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, 'Could not place the order. Please try again.', 0];
    }
}

/** Cancel an order and give the stock back (used by customers & admin) */
function cancel_order(int $orderId): void {
    global $pdo;
    $pdo->beginTransaction();
    $o = $pdo->prepare('SELECT status FROM orders WHERE id=? FOR UPDATE'); $o->execute([$orderId]); $s = $o->fetchColumn();
    if ($s && $s !== 'Cancelled') {
        $it = $pdo->prepare('SELECT * FROM order_items WHERE order_id=?'); $it->execute([$orderId]);
        foreach ($it->fetchAll() as $i) {
            if ($i['item_status'] === 'Cancelled') continue;
            if ($i['product_id']) $pdo->prepare('UPDATE products SET stock=stock+? WHERE id=?')->execute([$i['quantity'], $i['product_id']]);
            foreach (array_filter(explode(',', (string)$i['variation_ids'])) as $vid) $pdo->prepare('UPDATE product_variations SET stock=stock+? WHERE id=?')->execute([$i['quantity'], (int)$vid]);
        }
        $pdo->prepare("UPDATE order_items SET item_status='Cancelled' WHERE order_id=?")->execute([$orderId]);
        $pdo->prepare("UPDATE orders SET status='Cancelled' WHERE id=?")->execute([$orderId]);
    }
    $pdo->commit();
}
