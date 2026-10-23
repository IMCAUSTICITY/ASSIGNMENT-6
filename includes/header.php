<?php
/** Frontend header. Set $title / $meta_desc before including. */
require_once __DIR__ . '/../db.php';
$title = ($title ?? '') ? $title . ' | ' . setting('site_name') : setting('seo_title');
$meta_desc = $meta_desc ?? setting('seo_description');
$cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$me = user();
?><!DOCTYPE html>
<html lang="<?= e($_SESSION['lang'] ?? 'en') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($meta_desc) ?>">
<meta name="keywords" content="<?= e(setting('seo_keywords')) ?>">
<meta property="og:title" content="<?= e($title) ?>"><meta property="og:description" content="<?= e($meta_desc) ?>"><meta property="og:type" content="website">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
<style>:root{--primary:<?= e(setting('theme_primary', '#4f46e5')) ?>;--accent:<?= e(setting('theme_accent', '#f59e0b')) ?>}</style>
<?= $head_extra ?? '' ?>
</head>
<body class="<?= setting('theme_dark_mode') === '1' ? 'dark' : '' ?>">
<div class="topbar"><div class="container flex between">
  <span>📞 <?= e(setting('contact_phone')) ?> &nbsp;|&nbsp; ✉ <?= e(setting('contact_email')) ?></span>
  <form method="post" action="<?= url('set_currency.php') ?>" class="inline-form">
    <?= csrf_field() ?>
    <select name="lang" onchange="this.form.submit()" aria-label="Language">
      <?php foreach (['en' => 'English', 'hi' => 'हिन्दी', 'gu' => 'ગુજરાતી'] as $k => $n): ?><option value="<?= $k ?>" <?= ($_SESSION['lang'] ?? 'en') === $k ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?>
    </select>
    <select name="currency" onchange="this.form.submit()" aria-label="Currency">
      <?php foreach (currencies() as $c): ?><option value="<?= e($c['code']) ?>" <?= cur()['code'] === $c['code'] ? 'selected' : '' ?>><?= e($c['symbol'] . ' ' . $c['code']) ?></option><?php endforeach; ?>
    </select>
  </form>
</div></div>
<header class="site-header"><div class="container flex between center-v">
  <a class="logo" href="<?= url('index.php') ?>"><?= e(setting('site_name')) ?></a>
  <form class="search" action="<?= url('search.php') ?>" method="get">
    <input type="search" name="q" placeholder="<?= e(t('search')) ?>" value="<?= e(get('q')) ?>" aria-label="Search">
    <button class="btn" type="submit">🔍</button>
  </form>
  <nav class="actions">
    <a href="<?= url('index.php') ?>"><?= t('home') ?></a>
    <a href="<?= url('search.php') ?>"><?= t('shop') ?></a>
    <a href="<?= url('contact.php') ?>"><?= t('contact') ?></a>
    <a href="<?= url('profile.php?tab=wishlist') ?>">♥ <?= t('wishlist') ?></a>
    <a href="<?= url('cart.php') ?>" class="cart-link">🛒 <?= t('cart') ?> <span class="count"><?= cart_count() ?></span></a>
    <?php if ($me): ?>
      <a href="<?= $me['role'] === 'vendor' ? url('vendor/dashboard.php') : url('profile.php') ?>">👤 <?= e(explode(' ', $me['name'])[0]) ?></a>
      <a href="<?= url('logout.php') ?>"><?= t('logout') ?></a>
    <?php else: ?>
      <a href="<?= url('login.php') ?>"><?= t('login') ?></a>
      <a class="btn btn-sm" href="<?= url('signup.php') ?>"><?= t('signup') ?></a>
    <?php endif; ?>
  </nav>
</div>
<nav class="catnav"><div class="container">
  <?php foreach ($cats as $c): ?><a href="<?= category_url($c) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
</div></nav>
</header>
<main class="container main"><?php flash_show(); ?>
