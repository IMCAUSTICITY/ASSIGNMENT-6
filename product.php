<?php
require_once __DIR__ . '/db.php';
$id = (int)get('id');
$st = $pdo->prepare(product_select() . 'WHERE p.id=? AND p.status=1'); $st->execute([$id]);
$p = $st->fetch();
if (!$p) { http_response_code(404); $title = 'Product not found'; require __DIR__ . '/includes/header.php'; echo '<div class="box"><h2>Product not found</h2><a href="' . url('index.php') . '">Back to shop</a></div>'; require __DIR__ . '/includes/footer.php'; exit; }

/* ---- POST: review / question ---- */
if (is_post()) {
    $u = require_user();
    $act = post('action');
    if ($act === 'review') {
        $rating = (int)post('rating'); $comment = mb_substr(post('comment'), 0, 1000);
        if ($rating < 1 || $rating > 5) flash('danger', 'Please choose a rating between 1 and 5.');
        else {
            $pdo->prepare('INSERT INTO reviews (product_id,user_id,rating,comment) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE rating=VALUES(rating), comment=VALUES(comment), created_at=NOW()')
                ->execute([$id, $u['id'], $rating, $comment]);
            flash('success', 'Thanks! Your review has been saved.');
        }
    } elseif ($act === 'question') {
        $q = mb_substr(post('question'), 0, 500);
        if (mb_strlen($q) < 5) flash('danger', 'Please write a longer question.');
        else { $pdo->prepare('INSERT INTO product_questions (product_id,user_id,question) VALUES (?,?,?)')->execute([$id, $u['id'], $q]); flash('success', 'Question posted. The vendor will answer soon.'); }
    }
    header('Location: ' . product_url($p) . '#reviews'); exit;
}

/* ---- recently viewed ---- */
$_SESSION['recent'] = array_slice(array_unique(array_merge([$id], $_SESSION['recent'] ?? [])), 0, 8);

$vars = [];
$vs = $pdo->prepare('SELECT * FROM product_variations WHERE product_id=? ORDER BY attribute, id'); $vs->execute([$id]);
foreach ($vs->fetchAll() as $v) $vars[$v['attribute']][] = $v;
$rv = $pdo->prepare('SELECT r.*, u.name FROM reviews r JOIN users u ON u.id=r.user_id WHERE r.product_id=? ORDER BY r.created_at DESC'); $rv->execute([$id]); $reviews = $rv->fetchAll();
$qa = $pdo->prepare('SELECT q.*, u.name FROM product_questions q JOIN users u ON u.id=q.user_id WHERE q.product_id=? ORDER BY q.created_at DESC'); $qa->execute([$id]); $questions = $qa->fetchAll();
$rel = $pdo->prepare(product_select() . 'WHERE p.status=1 AND p.category_id=? AND p.id<>? ORDER BY RAND() LIMIT 4'); $rel->execute([$p['category_id'], $id]); $related = $rel->fetchAll();
$mine = null; if ($u = user()) { foreach ($reviews as $r) if ($r['user_id'] == $u['id']) $mine = $r; }

$price = unit_price($p);
$title = $p['name']; $meta_desc = mb_substr(strip_tags($p['description']), 0, 155);
$head_extra = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p['name'], 'description' => $p['description'],
    'offers' => ['@type' => 'Offer', 'priceCurrency' => 'INR', 'price' => $price, 'availability' => $p['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'],
    'aggregateRating' => $p['review_count'] ? ['@type' => 'AggregateRating', 'ratingValue' => round($p['avg_rating'], 1), 'reviewCount' => (int)$p['review_count']] : null,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
require __DIR__ . '/includes/header.php';
?>
<p class="muted small"><a href="<?= url('index.php') ?>">Home</a> › <?= e($p['category_name'] ?? 'Products') ?> › <?= e($p['name']) ?></p>
<div class="grid detail box">
  <div><img class="main-img" src="<?= img_src($p['image'], $p['name']) ?>" alt="<?= e($p['name']) ?>"></div>
  <div>
    <h1 style="margin-top:0"><?= e($p['name']) ?></h1>
    <div><?= stars((float)$p['avg_rating']) ?> <span class="muted"><?= number_format($p['avg_rating'], 1) ?> (<?= (int)$p['review_count'] ?> reviews)</span></div>
    <div class="price" style="font-size:1.9rem"><?= money($price) ?> <?php if ($price < $p['price']): ?><del><?= money((float)$p['price']) ?></del> <span class="badge-sale">Save <?= round((1 - $price / $p['price']) * 100) ?>%</span><?php endif; ?></div>
    <p class="muted">Sold by <b><?= e($p['vendor_name']) ?></b> · <?= $p['stock'] > 0 ? '<span class="pill ok">In stock (' . (int)$p['stock'] . ')</span>' : '<span class="pill bad">Out of stock</span>' ?></p>
    <p><?= nl2br(e($p['description'])) ?></p>
    <form method="post" action="<?= url('cart.php') ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= $id ?>">
      <?php foreach ($vars as $attr => $list): ?>
        <label><?= e($attr) ?></label>
        <div class="opts"><?php foreach ($list as $k => $v): ?>
          <label class="opt <?= $v['stock'] <= 0 ? 'off' : '' ?>"><input type="radio" name="var[<?= e($attr) ?>]" value="<?= (int)$v['id'] ?>" required <?= $v['stock'] <= 0 ? 'disabled' : '' ?>>
            <span><?= e($v['value']) ?><?= $v['price_adjust'] > 0 ? ' (+' . money((float)$v['price_adjust']) . ')' : '' ?></span></label>
        <?php endforeach; ?></div>
      <?php endforeach; ?>
      <label>Quantity</label><input class="qty" type="number" name="qty" value="1" min="1" max="<?= max(1, (int)$p['stock']) ?>">
      <div class="flex mt">
        <button class="btn" <?= $p['stock'] <= 0 ? 'disabled' : '' ?>>🛒 <?= t('add_to_cart') ?></button>
        <button class="btn btn-outline" name="buy_now" value="1" <?= $p['stock'] <= 0 ? 'disabled' : '' ?>>Buy now</button>
      </div>
    </form>
    <form method="post" action="<?= url('wishlist.php') ?>" class="mt"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= $id ?>">
      <button class="btn btn-sm btn-outline">♥ <?= in_array($id, wishlist_ids()) ? 'Remove from wishlist' : 'Add to wishlist' ?></button></form>
  </div>
</div>

<div class="grid two" id="reviews">
  <div class="box"><h2>Customer reviews</h2>
    <?php if (!$reviews): ?><p class="muted">No reviews yet. Be the first!</p><?php endif; ?>
    <?php foreach ($reviews as $r): ?><div class="review"><b><?= e($r['name']) ?></b> <?= stars((float)$r['rating']) ?> <span class="muted small"><?= e(date('d M Y', strtotime($r['created_at']))) ?></span><p><?= nl2br(e($r['comment'])) ?></p></div><?php endforeach; ?>
    <h3><?= $mine ? 'Update your review' : 'Write a review' ?></h3>
    <?php if (user()): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="review">
      <label>Rating</label><select name="rating" required><option value="">Choose…</option><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= ($mine['rating'] ?? 0) == $i ? 'selected' : '' ?>><?= str_repeat('★', $i) ?> (<?= $i ?>)</option><?php endfor; ?></select>
      <label>Comment</label><textarea name="comment" maxlength="1000"><?= e($mine['comment'] ?? '') ?></textarea>
      <p><button class="btn">Submit review</button></p></form>
    <?php else: ?><p><a href="<?= url('login.php') ?>">Log in</a> to write a review.</p><?php endif; ?>
  </div>
  <div class="box"><h2>Questions & answers</h2>
    <?php if (!$questions): ?><p class="muted">No questions yet.</p><?php endif; ?>
    <?php foreach ($questions as $q): ?><div class="review"><b>Q:</b> <?= e($q['question']) ?> <span class="muted small">— <?= e($q['name']) ?></span>
      <p><b>A:</b> <?= $q['answer'] ? e($q['answer']) : '<span class="muted">Awaiting answer…</span>' ?></p></div><?php endforeach; ?>
    <?php if (user()): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="question">
      <label>Ask a question</label><textarea name="question" maxlength="500" required></textarea><p><button class="btn btn-outline">Post question</button></p></form>
    <?php else: ?><p><a href="<?= url('login.php') ?>">Log in</a> to ask a question.</p><?php endif; ?>
  </div>
</div>
<?php if ($related): ?><div class="section-title"><h2>You may also like</h2></div><div class="grid products"><?php foreach ($related as $r) product_card($r); ?></div><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
