<?php
require_once __DIR__ . '/../db.php'; require_admin();
$tabs = ['general' => 'General', 'theme' => 'Theme', 'seo' => 'SEO', 'home' => 'Homepage sections', 'currency' => 'Currencies', 'security' => 'Admin password'];
$tab = isset($tabs[get('tab')]) ? get('tab') : 'general';
$groups = [
  'general' => ['site_name', 'tagline', 'contact_email', 'contact_phone', 'contact_address', 'footer_text', 'default_currency'],
  'theme'   => ['theme_primary', 'theme_accent', 'theme_dark_mode'],
  'seo'     => ['seo_title', 'seo_description', 'seo_keywords', 'seo_urls'],
  'home'    => ['home_show_categories', 'home_show_featured', 'home_show_new', 'home_show_promo', 'home_featured_title', 'home_new_title', 'home_items_count'],
];
$checks = ['theme_dark_mode', 'seo_urls', 'home_show_categories', 'home_show_featured', 'home_show_new', 'home_show_promo'];
if (is_post()) {
    $act = post('action');
    if ($act === 'settings' && isset($groups[$tab])) {
        $err = null;
        foreach (['theme_primary', 'theme_accent'] as $c) if (isset($_POST[$c]) && !preg_match('/^#[0-9a-fA-F]{6}$/', $_POST[$c])) $err = 'Colours must be hex like #4f46e5.';
        if (isset($_POST['contact_email']) && !filter_var($_POST['contact_email'], FILTER_VALIDATE_EMAIL)) $err = 'Enter a valid contact email.';
        if ($err) flash('danger', $err);
        else {
            foreach ($groups[$tab] as $k) {
                $v = in_array($k, $checks, true) ? (isset($_POST[$k]) ? '1' : '0') : post($k);
                $pdo->prepare('INSERT INTO settings (skey,svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)')->execute([$k, $v]);
            }
            flash('success', 'Settings saved.');
        }
    }
    if ($act === 'currency') {
        foreach ((array)($_POST['rate'] ?? []) as $code => $rate) if ((float)$rate > 0) $pdo->prepare('UPDATE currencies SET rate=? WHERE code=?')->execute([(float)$rate, $code]);
        if (post('new_code') !== '' && preg_match('/^[A-Za-z]{3}$/', post('new_code')) && (float)post('new_rate') > 0)
            $pdo->prepare('INSERT INTO currencies (code,name,symbol,rate) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE rate=VALUES(rate)')->execute([strtoupper(post('new_code')), post('new_name') ?: strtoupper(post('new_code')), post('new_symbol') ?: strtoupper(post('new_code')), (float)post('new_rate')]);
        flash('success', 'Currencies updated.');
    }
    if ($act === 'password') {
        $a = $pdo->prepare('SELECT * FROM admin WHERE id=?'); $a->execute([$_SESSION['admin_id']]); $a = $a->fetch(); $new = $_POST['new'] ?? '';
        if (!password_verify($_POST['current'] ?? '', $a['password'])) flash('danger', 'Current password is wrong.');
        elseif (strlen($new) < 8) flash('danger', 'New password must be at least 8 characters.');
        else { $pdo->prepare('UPDATE admin SET password=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $a['id']]); flash('success', 'Admin password changed.'); }
    }
    redirect('admin/settings.php?tab=' . $tab);
}
$panel = 'admin'; $title = 'Settings'; require __DIR__ . '/../includes/admin_header.php';
$labels = ['site_name' => 'Site name', 'tagline' => 'Tagline', 'contact_email' => 'Contact email (receives messages)', 'contact_phone' => 'Phone', 'contact_address' => 'Address', 'footer_text' => 'Footer text', 'default_currency' => 'Default currency',
  'theme_primary' => 'Primary colour', 'theme_accent' => 'Accent colour', 'theme_dark_mode' => 'Enable dark mode for storefront', 'seo_title' => 'Default meta title', 'seo_description' => 'Default meta description', 'seo_keywords' => 'Meta keywords',
  'seo_urls' => 'Use SEO-friendly URLs (/product/12-name) – needs .htaccess or router.php', 'home_show_categories' => 'Show category tiles', 'home_show_featured' => 'Show featured products', 'home_show_new' => 'Show new arrivals', 'home_show_promo' => 'Show promo banners',
  'home_featured_title' => 'Featured section title', 'home_new_title' => 'New arrivals section title', 'home_items_count' => 'Products per section (4–24)']; ?>
<div class="tabs"><?php foreach ($tabs as $k => $l): ?><a class="<?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?>"><?= $l ?></a><?php endforeach; ?></div>
<div class="box" style="max-width:720px">
<?php if (isset($groups[$tab])): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
<?php foreach ($groups[$tab] as $k): $v = setting($k); if (in_array($k, $checks, true)): ?><p><label style="display:inline"><input type="checkbox" name="<?= $k ?>" value="1" <?= $v === '1' ? 'checked' : '' ?>> <?= e($labels[$k]) ?></label></p>
<?php elseif ($k === 'default_currency'): ?><label><?= $labels[$k] ?></label><select name="<?= $k ?>"><?php foreach (currencies() as $c): ?><option <?= $v === $c['code'] ? 'selected' : '' ?>><?= e($c['code']) ?></option><?php endforeach; ?></select>
<?php elseif (strpos($k, 'theme_') === 0 && $k !== 'theme_dark_mode'): ?><label><?= $labels[$k] ?></label><input type="color" name="<?= $k ?>" value="<?= e($v) ?>" style="height:44px;max-width:120px">
<?php elseif ($k === 'seo_description'): ?><label><?= $labels[$k] ?></label><textarea name="<?= $k ?>"><?= e($v) ?></textarea>
<?php else: ?><label><?= $labels[$k] ?></label><input name="<?= $k ?>" value="<?= e($v) ?>"><?php endif; endforeach; ?>
<p><button class="btn">Save settings</button></p></form>
<?php elseif ($tab === 'currency'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="currency"><p class="muted">Prices are stored in INR. Rate = how many units of the currency equal ₹1.</p>
<table><tr><th>Code</th><th>Name</th><th>Rate (per ₹1)</th></tr><?php foreach (currencies() as $c): ?><tr><td><?= e($c['symbol'] . ' ' . $c['code']) ?></td><td><?= e($c['name']) ?></td><td><input name="rate[<?= e($c['code']) ?>]" type="number" step="0.000001" value="<?= e($c['rate']) ?>" <?= $c['code'] === 'INR' ? 'readonly' : '' ?>></td></tr><?php endforeach; ?></table>
<h4>Add currency</h4><div class="grid three"><input name="new_code" placeholder="Code e.g. AED" maxlength="3"><input name="new_symbol" placeholder="Symbol"><input name="new_rate" type="number" step="0.000001" placeholder="Rate"></div><input name="new_name" placeholder="Name (optional)" style="margin-top:8px"><p><button class="btn">Save currencies</button></p></form>
<?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password"><label>Current password</label><input type="password" name="current" required><label>New password (min 8)</label><input type="password" name="new" minlength="8" required><p><button class="btn">Change password</button></p></form><?php endif; ?></div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
