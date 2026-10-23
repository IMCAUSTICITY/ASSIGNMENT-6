<?php
// Router for PHP's built-in server:  php -S localhost:8000 router.php   (mimics .htaccess rewrites)
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/sitemap\.xml$#', $path)) { require __DIR__ . '/sitemap.php'; return true; }
if (preg_match('#^/product/(\d+)(-[A-Za-z0-9-]*)?/?$#', $path, $m)) { $_GET['id'] = $m[1]; require __DIR__ . '/product.php'; return true; }
if (preg_match('#^/category/([a-z0-9-]+)/?$#', $path, $m)) { $_GET['category'] = $m[1]; require __DIR__ . '/search.php'; return true; }
if (preg_match('#^/(uploads|storage)/.*\.(php|phtml)$#', $path) || preg_match('#^/(config\.php|database\.sql)$#', $path)) { http_response_code(403); echo 'Forbidden'; return true; }
if ($path === '/' ) { require __DIR__ . '/index.php'; return true; }
return false;
