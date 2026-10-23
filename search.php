<?php
require_once __DIR__ . '/db.php';
$q = get('q'); $catSlug = get('category'); $min = get('min'); $max = get('max'); $rating = (int)get('rating'); $sort = get('sort', 'new'); $page = max(1, (int)get('page', '1')); $per = 12;
$where = ['p.status=1']; $params = [];
if ($q !== '') { $where[] = '(p.name LIKE ? OR p.description LIKE ? OR v.name LIKE ?)'; $like = '%' . addcslashes($q, '%_') . '%'; array_push($params, $like, $like, $like); }
if ($catSlug !== '') { $where[] = 'c.slug=?'; $params[] = $catSlug; }
if (is_numeric($min)) { $where[] = 'COALESCE(NULLIF(p.sale_price,0),p.price)>=?'; $params[] = $min; }
if (is_numeric($max)) { $where[] = 'COALESCE(NULLIF(p.sale_price,0),p.price)<=?'; $params[] = $max; }
$having = $rating > 0 ? 'HAVING avg_rating>=' . min(5, $rating) : '';
$order = ['new' => 'p.id DESC', 'low' => 'COALESCE(NULLIF(p.sale_price,0),p.price) ASC', 'high' => 'COALESCE(NULLIF(p.sale_price,0),p.price) DESC', 'rating' => 'avg_rating DESC', 'name' => 'p.name ASC'][$sort] ?? 'p.id DESC';
$base = product_select() . 'WHERE ' . implode(' AND ', $where) . ' ' . $having;
$all = $pdo->prepare($base); $all->execute($params); $total = $all->rowCount();
$st = $pdo->prepare($base . " ORDER BY $order LIMIT $per OFFSET " . (($page - 1) * $per)); $st->execute($params); $rows = $st->fetchAll();
$pages = max(1, (int)ceil($total / $per));
$curCat = null; foreach ($cats ?? $pdo->query('SELECT * FROM categories')->fetchAll() as $c) if ($c['slug'] === $catSlug) $curCat = $c;
$title = $curCat ? $curCat['name'] : ($q !== '' ? 'Search: ' . $q : 'All products'); require __DIR__ . '/includes/header.php';
$qs = fn(array $o) => '?' . http_build_query(array_filter(array_merge($_GET, $o), fn($v) => $v !== '' && $v !== null));
?>
<h1><?= e($title) ?> <small class="muted" style="font-size:1rem">(<?= $total ?> products)</small></h1>
<div class="grid filters">
  <form method="get" class="box"><h3 style="margin-top:0">Filters</h3>
    <label>Keyword</label><input name="q" value="<?= e($q) ?>">
    <label>Category</label><select name="category"><option value="">All categories</option><?php foreach ($cats as $c): ?><option value="<?= e($c['slug']) ?>" <?= $c['slug'] === $catSlug ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
    <label>Price range (₹)</label><div class="flex"><input name="min" type="number" min="0" placeholder="Min" value="<?= e($min) ?>" style="width:46%"><input name="max" type="number" min="0" placeholder="Max" value="<?= e($max) ?>" style="width:46%"></div>
    <label>Minimum rating</label><select name="rating"><option value="0">Any</option><?php for ($i = 4; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= $rating == $i ? 'selected' : '' ?>><?= $i ?>★ & up</option><?php endfor; ?></select>
    <label>Sort by</label><select name="sort"><?php foreach (['new' => 'Newest', 'low' => 'Price: low to high', 'high' => 'Price: high to low', 'rating' => 'Top rated', 'name' => 'Name A–Z'] as $k => $l): ?><option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <p><button class="btn btn-block">Apply filters</button> <a class="small" href="<?= url('search.php') ?>">Reset</a></p></form>
  <div><?php if (!$rows): ?><div class="box"><p>No products matched your search.</p></div><?php endif; ?>
    <div class="grid products"><?php foreach ($rows as $p) product_card($p); ?></div>
    <?php if ($pages > 1): ?><div class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><?= $i === $page ? '<span class="cur">' . $i . '</span>' : '<a href="' . e($qs(['page' => $i])) . '">' . $i . '</a>' ?><?php endfor; ?></div><?php endif; ?></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
