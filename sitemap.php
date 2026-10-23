<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/xml; charset=utf-8');
$host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
$out = fn($u, $p = '0.6') => print('<url><loc>' . htmlspecialchars($host . $u) . "</loc><priority>$p</priority></url>\n");
$out(url('index.php'), '1.0'); $out(url('search.php'), '0.8'); $out(url('contact.php'), '0.4');
foreach ($pdo->query('SELECT * FROM categories') as $c) $out(category_url($c), '0.7');
foreach ($pdo->query("SELECT p.id,p.name,p.slug FROM products p JOIN vendors v ON v.id=p.vendor_id AND v.status='approved' WHERE p.status=1") as $p) $out(product_url($p));
echo '</urlset>';
