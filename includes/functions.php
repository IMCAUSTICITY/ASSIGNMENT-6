<?php
/* ============ Basic helpers ============ */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function url(string $p = ''): string { return BASE_URL . '/' . ltrim($p, '/'); }
function redirect(string $p): void {
    if (preg_match('#^(https?:)?//#i', $p)) { $p = 'index.php'; }   // never redirect off-site
    header('Location: ' . url($p));
    exit;
}
function back(string $fallback = 'index.php'): void {
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '')
        && !preg_match('/[\r\n]/', $ref)) { header('Location: ' . $ref); exit; }
    redirect($fallback);
}
function slugify(string $s): string {
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? $s : 'item';
}
function post(string $k, string $d = ''): string { return trim((string)($_POST[$k] ?? $d)); }
function get(string $k, string $d = ''): string { return trim((string)($_GET[$k] ?? $d)); }
function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flash_show(): void {
    foreach ($_SESSION['flash'] ?? [] as [$t, $m]) echo '<div class="alert alert-' . e($t) . '">' . e($m) . '</div>';
    unset($_SESSION['flash']);
}

/* ============ CSRF ============ */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    if (is_post() && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Security token expired. Please go back, refresh the page and try again.');
    }
}

/* ============ Settings, currency, language ============ */
function setting(string $k, string $d = ''): string {
    static $c = null; global $pdo;
    if ($c === null) { $c = []; foreach ($pdo->query('SELECT skey, svalue FROM settings') as $r) $c[$r['skey']] = $r['svalue']; }
    return $c[$k] ?? $d;
}
function currencies(): array {
    static $c = null; global $pdo;
    if ($c === null) { $c = []; foreach ($pdo->query('SELECT * FROM currencies ORDER BY code') as $r) $c[$r['code']] = $r; }
    return $c;
}
function cur(): array {
    $all = currencies();
    $code = $_SESSION['currency'] ?? setting('default_currency', 'INR');
    return $all[$code] ?? reset($all);
}
/** Convert a base-currency (INR) amount into the visitor's chosen currency */
function money(float $amt): string { $c = cur(); return $c['symbol'] . number_format($amt * (float)$c['rate'], 2); }
/** Base currency (what the store actually charges) – used in admin/vendor panels */
function money_base(float $amt): string { return '₹' . number_format($amt, 2); }

function t(string $key): string {
    static $L = null;
    if ($L === null) $L = require __DIR__ . '/lang.php';
    $lang = $_SESSION['lang'] ?? 'en';
    return $L[$lang][$key] ?? $L['en'][$key] ?? $key;
}

/* ============ Auth ============ */
function auto_login_from_cookie(): void {
    global $pdo;
    if (!empty($_SESSION['user_id']) || empty($_COOKIE['remember'])) return;
    $parts = explode(':', $_COOKIE['remember'], 2);
    if (count($parts) !== 2) return;
    $st = $pdo->prepare('SELECT id, role, remember_token FROM users WHERE id = ? AND status = 1');
    $st->execute([(int)$parts[0]]);
    $u = $st->fetch();
    if ($u && $u['remember_token'] && hash_equals($u['remember_token'], hash('sha256', $parts[1]))) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$u['id']; $_SESSION['role'] = $u['role'];
    }
}
function user(): ?array {
    static $u = false; global $pdo;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['user_id'])) {
            $st = $pdo->prepare('SELECT * FROM users WHERE id = ? AND status = 1');
            $st->execute([$_SESSION['user_id']]);
            $u = $st->fetch() ?: null;
            if (!$u) { unset($_SESSION['user_id'], $_SESSION['role']); }
        }
    }
    return $u;
}
function vendor(): ?array {
    static $v = false; global $pdo;
    if ($v === false) {
        $v = null;
        if ($u = user()) {
            $st = $pdo->prepare('SELECT * FROM vendors WHERE user_id = ?');
            $st->execute([$u['id']]);
            $v = $st->fetch() ?: null;
        }
    }
    return $v;
}
function require_user(): array {
    if (!($u = user())) { flash('warning', 'Please log in to continue.'); redirect('login.php?next=' . urlencode(basename($_SERVER['SCRIPT_NAME']))); }
    return $u;
}
function require_vendor(): array {
    $u = user();
    if (!$u || $u['role'] !== 'vendor' || !vendor()) { flash('warning', 'Vendor login required.'); redirect('login.php'); }
    return vendor();
}
function is_admin(): bool { return !empty($_SESSION['admin_id']); }
function require_admin(): void { if (!is_admin()) redirect('admin/index.php'); }
function login_throttled(): bool { return ($_SESSION['login_lock'] ?? 0) > time(); }
function login_fail(): void {
    $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
    if ($_SESSION['login_fails'] >= 5) { $_SESSION['login_lock'] = time() + 60; $_SESSION['login_fails'] = 0; }
}

/* ============ Media ============ */
function img_src(?string $file, string $name = ''): string {
    if ($file && is_file(ROOT_DIR . '/uploads/' . basename($file))) return url('uploads/' . basename($file));
    // generated SVG placeholder (works offline, no external images needed)
    $hue = crc32($name) % 360; $ch = strtoupper(mb_substr($name ?: '?', 0, 1));
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"><rect width="100%" height="100%" fill="hsl(' . $hue . ',55%,88%)"/><text x="50%" y="54%" font-family="Arial" font-size="150" font-weight="700" fill="hsl(' . $hue . ',45%,40%)" text-anchor="middle" dominant-baseline="middle">' . htmlspecialchars($ch) . '</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}
function save_upload(string $field): ?string {
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $f = $_FILES[$field];
    if ($f['size'] > 2 * 1024 * 1024) { flash('danger', 'Image must be under 2 MB.'); return null; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
    if (!$ext) { flash('danger', 'Only JPG, PNG, WEBP or GIF images are allowed.'); return null; }
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    return move_uploaded_file($f['tmp_name'], ROOT_DIR . '/uploads/' . $name) ? $name : null;
}

/* ============ Mail ============ */
function send_mail(string $to, string $subject, string $body): void {
    $from = setting('contact_email', 'noreply@omnimart.local');
    $headers = "From: " . setting('site_name', 'OmniMart') . " <$from>\r\nContent-Type: text/plain; charset=UTF-8";
    @mail($to, $subject, $body, $headers);
    // Always log, so it is visible on local servers where mail() is not configured
    @file_put_contents(ROOT_DIR . '/storage/mail.log', "[" . date('c') . "] TO:$to | $subject\n$body\n\n", FILE_APPEND);
}

/* ============ Product helpers ============ */
function product_url(array $p): string {
    return setting('seo_urls', '1') === '1' ? url('product/' . $p['id'] . '-' . ($p['slug'] ?? slugify($p['name']))) : url('product.php?id=' . $p['id']);
}
function category_url(array $c): string {
    return setting('seo_urls', '1') === '1' ? url('category/' . $c['slug']) : url('search.php?category=' . $c['slug']);
}
function stars(float $r): string {
    $full = (int)round($r); return '<span class="stars" title="' . number_format($r, 1) . '/5">' . str_repeat('★', $full) . str_repeat('☆', 5 - $full) . '</span>';
}
function unit_price(array $p): float { return (float)(($p['sale_price'] !== null && (float)$p['sale_price'] > 0) ? $p['sale_price'] : $p['price']); }

function wishlist_ids(): array {
    static $ids = null; global $pdo;
    if ($ids === null) {
        $ids = [];
        if ($u = user()) { $st = $pdo->prepare('SELECT product_id FROM wishlist WHERE user_id=?'); $st->execute([$u['id']]); $ids = array_column($st->fetchAll(), 'product_id'); }
    }
    return $ids;
}
function product_card(array $p): void {
    $price = unit_price($p); $on = $price < (float)$p['price'];
    $liked = in_array($p['id'], wishlist_ids());
    ?>
    <article class="card product-card">
      <form method="post" action="<?= url('wishlist.php') ?>" class="wish">
        <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
        <button type="submit" class="heart <?= $liked ? 'on' : '' ?>" title="Wishlist" aria-label="Toggle wishlist">♥</button>
      </form>
      <a href="<?= product_url($p) ?>"><img loading="lazy" src="<?= img_src($p['image'], $p['name']) ?>" alt="<?= e($p['name']) ?>"></a>
      <div class="card-body">
        <?php if ($on): ?><span class="badge-sale">-<?= round((1 - $price / $p['price']) * 100) ?>%</span><?php endif; ?>
        <h3><a href="<?= product_url($p) ?>"><?= e($p['name']) ?></a></h3>
        <div class="muted small"><?= e($p['vendor_name'] ?? '') ?></div>
        <div><?= stars((float)($p['avg_rating'] ?? 0)) ?> <span class="muted small">(<?= (int)($p['review_count'] ?? 0) ?>)</span></div>
        <div class="price"><?= money($price) ?> <?php if ($on): ?><del><?= money((float)$p['price']) ?></del><?php endif; ?></div>
        <?php if ((int)$p['stock'] <= 0): ?><span class="muted small">Out of stock</span><?php endif; ?>
      </div>
    </article>
    <?php
}
/** Base SELECT used by every storefront listing (adds vendor name + rating aggregate) */
function product_select(): string {
    return "SELECT p.*, v.name AS vendor_name, c.name AS category_name,
            COALESCE((SELECT AVG(rating) FROM reviews r WHERE r.product_id=p.id),0) AS avg_rating,
            (SELECT COUNT(*) FROM reviews r WHERE r.product_id=p.id) AS review_count
            FROM products p
            JOIN vendors v ON v.id=p.vendor_id AND v.status='approved'
            LEFT JOIN categories c ON c.id=p.category_id ";
}

/* ============ Cart & coupons ============ */
function cart_key(int $pid, array $vars): string { sort($vars); return $pid . '-' . implode('.', $vars); }
function cart_count(): int { return array_sum(array_column($_SESSION['cart'] ?? [], 'qty')); }

/** Resolve session cart into priced lines with stock limits */
function cart_lines(): array {
    global $pdo; $lines = []; $sub = 0.0;
    foreach ($_SESSION['cart'] ?? [] as $key => $it) {
        $st = $pdo->prepare(product_select() . 'WHERE p.id=? AND p.status=1'); $st->execute([$it['pid']]);
        $p = $st->fetch();
        if (!$p) { unset($_SESSION['cart'][$key]); continue; }
        $price = unit_price($p); $limit = (int)$p['stock']; $labels = [];
        foreach ($it['vars'] as $vid) {
            $vs = $pdo->prepare('SELECT * FROM product_variations WHERE id=? AND product_id=?'); $vs->execute([$vid, $p['id']]);
            if ($v = $vs->fetch()) { $price += (float)$v['price_adjust']; $limit = min($limit, (int)$v['stock']); $labels[] = $v['attribute'] . ': ' . $v['value']; }
        }
        $qty = max(1, min((int)$it['qty'], max($limit, 0)));
        if ($limit <= 0) $qty = 0;
        $lines[] = ['key' => $key, 'p' => $p, 'vars' => $it['vars'], 'label' => implode(', ', $labels), 'price' => $price, 'qty' => $qty, 'limit' => $limit, 'total' => $price * $qty];
        $sub += $price * $qty;
    }
    return ['lines' => $lines, 'subtotal' => $sub];
}
/** Validate a coupon against a subtotal. Returns [ok, discount, message, couponRow] */
function coupon_check(string $code, float $subtotal): array {
    global $pdo;
    $st = $pdo->prepare('SELECT * FROM coupons WHERE code=? AND status=1'); $st->execute([strtoupper($code)]);
    $c = $st->fetch();
    if (!$c) return [false, 0, 'Invalid coupon code.', null];
    if ($c['expiry'] && $c['expiry'] < date('Y-m-d')) return [false, 0, 'This coupon has expired.', null];
    if ($c['usage_limit'] > 0 && $c['used_count'] >= $c['usage_limit']) return [false, 0, 'Coupon usage limit reached.', null];
    if ($subtotal < (float)$c['min_order']) return [false, 0, 'Minimum order of ' . money_base((float)$c['min_order']) . ' required.', null];
    $d = $c['discount_type'] === 'percent' ? $subtotal * $c['value'] / 100 : (float)$c['value'];
    return [true, round(min($d, $subtotal), 2), 'Coupon applied!', $c];
}
function order_ref(int $id): string { return 'OM' . str_pad((string)$id, 6, '0', STR_PAD_LEFT); }
const ORDER_STATUSES = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

function status_badge(string $s): string {
    $cls = ['Pending' => 'warn', 'Processing' => 'info', 'Shipped' => 'info', 'Delivered' => 'ok', 'Cancelled' => 'bad', 'paid' => 'ok', 'unpaid' => 'warn', 'failed' => 'bad', 'approved' => 'ok', 'pending' => 'warn', 'suspended' => 'bad'][$s] ?? 'info';
    return '<span class="pill ' . $cls . '">' . e(ucfirst($s)) . '</span>';
}

/* ============ Sandbox payment gateways ============ */
/** Simulated sandbox payments. Returns [ok, message, reference]. Replace with real SDK calls when keys are available. */
function process_payment(string $method, array $in, float $amount): array {
    if ($method === 'cod') return [true, 'Pay on delivery', 'COD-' . strtoupper(bin2hex(random_bytes(3)))];
    if ($method === 'stripe') {
        $card = preg_replace('/\D/', '', $in['card_number'] ?? '');
        if (!preg_match('#^(0[1-9]|1[0-2])/(\d{2})$#', trim($in['card_expiry'] ?? ''), $m) || (int)('20' . $m[2] . $m[1]) < (int)date('Ym')) return [false, 'Card expiry is invalid or in the past.', ''];
        if (!preg_match('/^\d{3,4}$/', trim($in['card_cvc'] ?? ''))) return [false, 'Enter a valid CVC.', ''];
        if ($card === '4242424242424242') return [true, 'Stripe sandbox payment succeeded', 'ch_test_' . bin2hex(random_bytes(5))];
        return [false, 'Card declined (sandbox: use test card 4242 4242 4242 4242).', ''];
    }
    if ($method === 'razorpay') {
        $upi = strtolower(trim($in['upi_id'] ?? ''));
        if ($upi === 'success@razorpay') return [true, 'Razorpay sandbox payment succeeded', 'pay_test_' . bin2hex(random_bytes(5))];
        return [false, 'UPI payment failed (sandbox: use success@razorpay).', ''];
    }
    return [false, 'Unknown payment method.', ''];
}

/* ============ Panel helpers ============ */
/** Pure-CSS bar chart. $data = ['label' => number, ...] */
function bar_chart(array $data, bool $money = true): void {
    $max = max(1, max($data ?: [0]));
    echo '<div class="bars-wrap"><div class="bars">';
    foreach ($data as $l => $v) {
        echo '<div class="bar" style="height:' . round($v / $max * 100) . '%"><em>' . ($money ? ($v >= 1000 ? round($v / 1000, 1) . 'k' : round($v)) : $v) . '</em><span>' . e($l) . '</span></div>';
    }
    echo '</div></div>';
}
/** Fill missing days so charts always show a continuous range */
function daily_series(array $rows, int $days): array {
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) $out[date('Y-m-d', strtotime("-$i day"))] = 0;
    foreach ($rows as $r) if (isset($out[$r['d']])) $out[$r['d']] = (float)$r['v'];
    $lab = []; foreach ($out as $d => $v) $lab[date('d M', strtotime($d))] = $v;
    return $lab;
}
function csv_download(string $name, array $header, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $name . '-' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w'); fputcsv($o, $header); foreach ($rows as $r) fputcsv($o, array_values($r)); exit;
}
