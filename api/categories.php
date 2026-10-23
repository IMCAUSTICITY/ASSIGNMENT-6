<?php
require_once __DIR__ . '/../includes/api.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') api_err('Method not allowed.', 405);
api_out(['data' => $pdo->query('SELECT c.id, c.name, c.slug, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id AND p.status=1) AS products FROM categories c ORDER BY c.name')->fetchAll()]);
