<?php
$title = '';
require __DIR__ . '/includes/header.php';
$n = max(4, min(24, (int)setting('home_items_count', '8')));
$hero  = $pdo->query("SELECT * FROM banners WHERE position='hero'  AND is_active=1 ORDER BY sort_order, id")->fetchAll();
$promo = $pdo->query("SELECT * FROM banners WHERE position='promo' AND is_active=1 ORDER BY sort_order, id")->fetchAll();
$colors = ['#4f46e5', '#db2777', '#059669', '#d97706', '#0891b2', '#7c3aed', '#dc2626', '#475569'];
?>
<?php if ($hero): ?>
<div class="slider">
  <?php foreach ($hero as $i => $b): $bg = $b['image'] ? "url('" . img_src($b['image']) . "')" : "linear-gradient(120deg," . $colors[$i % 8] . ",#111827)"; ?>
    <div class="slide" style="background-image:<?= $bg ?>">
      <h2><?= e($b['title']) ?></h2><p><?= e($b['subtitle']) ?></p>
      <?php if ($b['link']): ?><a class="btn" href="<?= url($b['link']) ?>"><?= e($b['button_text'] ?: 'Shop now') ?></a><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <button class="prev" aria-label="Previous">‹</button><button class="next" aria-label="Next">›</button>
</div>
<?php endif; ?>

<?php if (setting('home_show_categories', '1') === '1'): ?>
<div class="section-title"><h2><?= t('categories') ?></h2></div>
<div class="grid cat-tiles">
  <?php foreach ($cats as $i => $c): ?><a class="cat-tile" style="background:<?= $colors[$i % 8] ?>" href="<?= category_url($c) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (setting('home_show_featured', '1') === '1'):
  $rows = $pdo->query(product_select() . "WHERE p.status=1 AND p.is_featured=1 ORDER BY p.id DESC LIMIT $n")->fetchAll(); ?>
<div class="section-title"><h2><?= e(setting('home_featured_title', t('featured'))) ?></h2><a href="<?= url('search.php') ?>">View all →</a></div>
<div class="grid products"><?php foreach ($rows as $p) product_card($p); ?></div>
<?php endif; ?>

<?php if ($promo && setting('home_show_promo', '1') === '1'): ?>
<div class="grid promo mt">
  <?php foreach ($promo as $b): ?><div class="promo-card"><h3><?= e($b['title']) ?></h3><p><?= e($b['subtitle']) ?></p>
    <?php if ($b['link']): ?><a class="btn" href="<?= url($b['link']) ?>"><?= e($b['button_text'] ?: 'Shop now') ?></a><?php endif; ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (setting('home_show_new', '1') === '1'):
  $rows = $pdo->query(product_select() . "WHERE p.status=1 ORDER BY p.created_at DESC, p.id DESC LIMIT $n")->fetchAll(); ?>
<div class="section-title"><h2><?= e(setting('home_new_title', t('new'))) ?></h2></div>
<div class="grid products"><?php foreach ($rows as $p) product_card($p); ?></div>
<?php endif; ?>

<?php
$rv = array_map('intval', $_SESSION['recent'] ?? []);
if ($rv):
  $in = implode(',', $rv);
  $rows = $pdo->query(product_select() . "WHERE p.status=1 AND p.id IN ($in) ORDER BY FIELD(p.id,$in)")->fetchAll();
  if ($rows): ?>
<div class="section-title"><h2><?= t('recent') ?></h2></div>
<div class="grid products"><?php foreach ($rows as $p) product_card($p); ?></div>
<?php endif; endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
