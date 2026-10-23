<?php
/** Shared header for admin & vendor panels. Set $panel ('admin'|'vendor') and $title before including. */
require_once __DIR__ . '/../db.php';
$panel = $panel ?? 'admin';
if ($panel === 'admin') { require_admin(); $who = 'Administrator'; $home = url('admin/dashboard.php'); }
else { $vend = require_vendor(); $who = $vend['name']; $home = url('vendor/dashboard.php'); }
$menu = $panel === 'admin' ? [
  'dashboard.php' => '📊 Dashboard', 'products.php' => '📦 Products', 'categories.php' => '🏷 Categories', 'vendors.php' => '🏪 Vendors',
  'orders.php' => '🧾 Orders', 'coupons.php' => '🎟 Coupons', 'banners.php' => '🖼 Banners', 'users.php' => '👥 Users & Inbox',
  'reports.php' => '📈 Reports', 'settings.php' => '⚙ Settings',
] : ['dashboard.php' => '📊 Dashboard', 'products.php' => '📦 My Products', 'orders.php' => '🧾 My Orders'];
$cur_page = basename($_SERVER['SCRIPT_NAME']);
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Panel') ?> | <?= e(setting('site_name')) ?> <?= ucfirst($panel) ?></title>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
<style>:root{--primary:<?= e(setting('theme_primary', '#4f46e5')) ?>;--accent:<?= e(setting('theme_accent', '#f59e0b')) ?>}</style>
</head><body class="panel">
<aside class="sidebar" id="sidebar">
  <a class="logo" href="<?= $home ?>"><?= e(setting('site_name')) ?> <small><?= $panel ?></small></a>
  <?php foreach ($menu as $f => $label): ?><a href="<?= url($panel . '/' . $f) ?>" class="<?= $cur_page === $f ? 'active' : '' ?>"><?= $label ?></a><?php endforeach; ?>
  <a href="<?= url('index.php') ?>" target="_blank">🌐 View store</a>
  <a href="<?= $panel === 'admin' ? url('admin/logout.php') : url('logout.php') ?>">🚪 Logout</a>
</aside>
<div class="panel-main">
  <div class="panel-top"><button class="btn btn-sm menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
    <h1><?= e($title ?? '') ?></h1><span class="muted">Signed in as <b><?= e($who) ?></b></span></div>
  <div class="panel-body"><?php flash_show(); ?>
