<?php
require_once __DIR__ . '/../includes/api.php';
$auth = api_auth(); $m = $_SERVER['REQUEST_METHOD'];

function order_json(array $o, array $items): array {
    return ['id' => (int)$o['id'], 'reference' => order_ref((int)$o['id']), 'status' => $o['status'], 'payment_method' => $o['payment_method'], 'payment_status' => $o['payment_status'],
        'subtotal' => (float)$o['subtotal'], 'discount' => (float)$o['discount'], 'total' => (float)$o['total_amount'], 'coupon' => $o['coupon_code'], 'created_at' => $o['created_at'],
        'items' => array_map(fn($i) => ['product_id' => (int)$i['product_id'], 'name' => $i['product_name'], 'variation' => $i['variation'], 'quantity' => (int)$i['quantity'], 'price' => (float)$i['price'], 'status' => $i['item_status']], $items)];
}
if ($m === 'GET') {
    $w = []; $pr = [];
    if ($auth['role'] === 'admin') { /* all */ }
    elseif ($auth['role'] === 'vendor' && $auth['vendor_id']) { $w[] = 'o.id IN (SELECT order_id FROM order_items WHERE vendor_id=?)'; $pr[] = $auth['vendor_id']; }
    else { $w[] = 'o.user_id=?'; $pr[] = $auth['id']; }
    if ($id = (int)get('id')) { $w[] = 'o.id=?'; $pr[] = $id; }
    $s = $pdo->prepare('SELECT o.* FROM orders o' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY o.id DESC LIMIT 50'); $s->execute($pr);
    $out = [];
    foreach ($s->fetchAll() as $o) {
        $q = 'SELECT * FROM order_items WHERE order_id=?' . ($auth['role'] === 'vendor' && $auth['vendor_id'] ? ' AND vendor_id=' . $auth['vendor_id'] : ''); $i = $pdo->prepare($q); $i->execute([$o['id']]);
        $out[] = order_json($o, $i->fetchAll());
    }
    if ($id && !$out) api_err('Order not found.', 404);
    api_out(['data' => $id ? $out[0] : $out]);
}
if ($m === 'POST') {
    if ($auth['type'] !== 'user' || $auth['role'] !== 'customer') api_err('Only customer tokens can place orders.', 403);
    $b = api_body(); $sh = (array)($b['shipping'] ?? []);
    $ship = ['name' => trim((string)($sh['name'] ?? '')), 'phone' => trim((string)($sh['phone'] ?? '')), 'address' => trim((string)($sh['address'] ?? '')), 'city' => trim((string)($sh['city'] ?? '')), 'postal' => trim((string)($sh['postal'] ?? ''))];
    if (in_array('', $ship, true) || !preg_match('/^[0-9+\-\s]{8,15}$/', $ship['phone'])) api_err('shipping needs name, phone, address, city and postal.', 422);
    $items = [];
    foreach ((array)($b['items'] ?? []) as $it) $items[] = ['pid' => (int)($it['product_id'] ?? 0), 'vars' => array_map('intval', (array)($it['variation_ids'] ?? [])), 'qty' => (int)($it['quantity'] ?? 1)];
    [$ok, $msg, $oid] = place_order($auth['id'], $items, $ship, !empty($b['coupon']) ? (string)$b['coupon'] : null, (string)($b['payment_method'] ?? 'cod'), (array)($b['payment'] ?? []));
    if (!$ok) api_err($msg, 422);
    api_out(['message' => $msg, 'order_id' => $oid, 'reference' => order_ref($oid)], 201);
}
if ($m === 'PATCH' || $m === 'PUT') {                      // admin: change order status
    if ($auth['role'] !== 'admin') api_err('Admin token required.', 403);
    $b = api_body(); $id = (int)($b['id'] ?? get('id'));
    if (!in_array($b['status'] ?? '', ORDER_STATUSES, true)) api_err('status must be one of: ' . implode(', ', ORDER_STATUSES), 422);
    if ($b['status'] === 'Cancelled') cancel_order($id);
    else { $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$b['status'], $id]); $pdo->prepare("UPDATE order_items SET item_status=? WHERE order_id=? AND item_status<>'Cancelled'")->execute([$b['status'], $id]); }
    api_out(['message' => 'Order updated.']);
}
api_err('Method not allowed.', 405);
