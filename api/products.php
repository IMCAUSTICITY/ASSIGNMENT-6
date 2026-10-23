<?php
require_once __DIR__ . '/../includes/api.php';
$m = $_SERVER['REQUEST_METHOD'];

if ($m === 'GET') {                                       // public catalogue
    if ($id = (int)get('id')) {
        $s = $pdo->prepare(product_select() . 'WHERE p.id=? AND p.status=1'); $s->execute([$id]); $p = $s->fetch();
        if (!$p) api_err('Product not found.', 404);
        $v = $pdo->prepare('SELECT id, attribute, value, stock, price_adjust FROM product_variations WHERE product_id=?'); $v->execute([$id]);
        api_out(['data' => api_product_row($p) + ['variations' => $v->fetchAll()]]);
    }
    $w = ['p.status=1']; $pr = [];
    if (get('q') !== '') { $w[] = '(p.name LIKE ? OR p.description LIKE ?)'; array_push($pr, '%' . get('q') . '%', '%' . get('q') . '%'); }
    if (get('category') !== '') { $w[] = '(c.slug=? OR c.id=?)'; array_push($pr, get('category'), (int)get('category')); }
    $limit = max(1, min(50, (int)get('limit', '12'))); $page = max(1, (int)get('page', '1'));
    $s = $pdo->prepare(product_select() . 'WHERE ' . implode(' AND ', $w) . " ORDER BY p.id DESC LIMIT $limit OFFSET " . (($page - 1) * $limit)); $s->execute($pr);
    api_out(['page' => $page, 'limit' => $limit, 'data' => array_map('api_product_row', $s->fetchAll())]);
}

$auth = api_auth();                                       // writes need a token (vendor or admin)
if (!in_array($auth['role'], ['admin', 'vendor'], true) || ($auth['role'] === 'vendor' && !$auth['vendor_id'])) api_err('Only approved vendors or admins can modify products.', 403);
$b = api_body();
$owns = function (int $id) use ($pdo, $auth) { $s = $pdo->prepare('SELECT * FROM products WHERE id=?' . ($auth['vendor_id'] ? ' AND vendor_id=' . $auth['vendor_id'] : '')); $s->execute([$id]); return $s->fetch(); };

if ($m === 'POST') {
    $name = trim((string)($b['name'] ?? '')); $price = (float)($b['price'] ?? 0); $vendorId = $auth['vendor_id'] ?: (int)($b['vendor_id'] ?? 0);
    if (mb_strlen($name) < 3 || $price <= 0 || !$vendorId) api_err('name (min 3), price (>0) and vendor_id (admin only) are required.', 422);
    $sale = isset($b['sale_price']) && $b['sale_price'] !== '' ? (float)$b['sale_price'] : null; if ($sale !== null && ($sale <= 0 || $sale >= $price)) api_err('sale_price must be lower than price.', 422);
    $pdo->prepare('INSERT INTO products (vendor_id,name,slug,description,price,sale_price,stock,category_id,status) VALUES (?,?,?,?,?,?,?,?,1)')
        ->execute([$vendorId, $name, slugify($name), (string)($b['description'] ?? ''), $price, $sale, max(0, (int)($b['stock'] ?? 0)), ((int)($b['category_id'] ?? 0)) ?: null]);
    api_out(['message' => 'Product created.', 'id' => (int)$pdo->lastInsertId()], 201);
}
$id = (int)get('id') ?: (int)($b['id'] ?? 0); $p = $id ? $owns($id) : null;
if (!$p) api_err('Product not found (or not yours).', 404);
if ($m === 'PUT' || $m === 'PATCH') {
    $name = isset($b['name']) ? trim((string)$b['name']) : $p['name']; $price = isset($b['price']) ? (float)$b['price'] : (float)$p['price'];
    $sale = array_key_exists('sale_price', $b) ? ($b['sale_price'] === null || $b['sale_price'] === '' ? null : (float)$b['sale_price']) : $p['sale_price'];
    if (mb_strlen($name) < 3 || $price <= 0 || ($sale !== null && ($sale <= 0 || $sale >= $price))) api_err('Invalid values.', 422);
    $pdo->prepare('UPDATE products SET name=?, slug=?, description=?, price=?, sale_price=?, stock=?, status=? WHERE id=?')
        ->execute([$name, slugify($name), (string)($b['description'] ?? $p['description']), $price, $sale, max(0, (int)($b['stock'] ?? $p['stock'])), isset($b['status']) ? (int)(bool)$b['status'] : $p['status'], $id]);
    api_out(['message' => 'Product updated.']);
}
if ($m === 'DELETE') { $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]); api_out(['message' => 'Product deleted.']); }
api_err('Method not allowed.', 405);
