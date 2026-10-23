<?php
/** Shared API helpers: JSON responses, bearer-token auth. API endpoints define API_MODE (tokens instead of CSRF). */
define('API_MODE', true);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/orders.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function api_out($data, int $code = 200): void { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); exit; }
function api_err(string $msg, int $code = 400): void { api_out(['error' => $msg], $code); }
function api_body(): array { $j = json_decode(file_get_contents('php://input'), true); return is_array($j) ? $j : $_POST; }
/** Returns ['type'=>'admin'|'user','id'=>int,'role'=>string,'vendor_id'=>?int] or null */
function api_auth(bool $required = true): ?array {
    global $pdo;
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? (function_exists('getallheaders') ? (getallheaders()['Authorization'] ?? '') : '');
    if (preg_match('/^Bearer\s+([A-Fa-f0-9]{48})$/', $h, $m)) {
        $hash = hash('sha256', $m[1]);
        $a = $pdo->prepare('SELECT id FROM admin WHERE api_token=?'); $a->execute([$hash]);
        if ($id = $a->fetchColumn()) return ['type' => 'admin', 'id' => (int)$id, 'role' => 'admin', 'vendor_id' => null];
        $u = $pdo->prepare('SELECT id, role FROM users WHERE api_token=? AND status=1'); $u->execute([$hash]);
        if ($row = $u->fetch()) {
            $v = $pdo->prepare("SELECT id FROM vendors WHERE user_id=? AND status='approved'"); $v->execute([$row['id']]);
            return ['type' => 'user', 'id' => (int)$row['id'], 'role' => $row['role'], 'vendor_id' => ($vid = $v->fetchColumn()) ? (int)$vid : null];
        }
    }
    if ($required) api_err('Missing or invalid bearer token. POST /api/token.php first.', 401);
    return null;
}
function api_product_row(array $p): array {
    return ['id' => (int)$p['id'], 'name' => $p['name'], 'description' => $p['description'], 'price' => (float)$p['price'], 'sale_price' => $p['sale_price'] !== null ? (float)$p['sale_price'] : null,
        'final_price' => unit_price($p), 'stock' => (int)$p['stock'], 'category' => $p['category_name'] ?? null, 'vendor' => $p['vendor_name'] ?? null,
        'rating' => round((float)($p['avg_rating'] ?? 0), 2), 'reviews' => (int)($p['review_count'] ?? 0), 'image' => $p['image'] ? url('uploads/' . $p['image']) : null, 'url' => product_url($p)];
}
