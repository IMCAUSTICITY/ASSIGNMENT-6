<?php
require_once __DIR__ . '/../db.php'; require_admin();
if (is_post()) {
    $act = post('action'); $id = (int)post('id');
    if ($act === 'save') {
        $title = post('title'); $pos = post('position') === 'promo' ? 'promo' : 'hero';
        if (mb_strlen($title) < 3) { flash('danger', 'Banner title is required.'); redirect('admin/banners.php'); }
        $old = null; if ($id) { $o = $pdo->prepare('SELECT image FROM banners WHERE id=?'); $o->execute([$id]); $old = $o->fetchColumn(); }
        $img = save_upload('image') ?? ($old ?: null); $link = preg_match('#^(https?:)?//#i', post('link')) ? '' : ltrim(post('link'), '/');
        $vals = [$title, post('subtitle'), $img, $link, post('button_text'), $pos, (int)post('sort_order'), post('is_active') ? 1 : 0];
        if ($id) $pdo->prepare('UPDATE banners SET title=?,subtitle=?,image=?,link=?,button_text=?,position=?,sort_order=?,is_active=? WHERE id=?')->execute([...$vals, $id]);
        else $pdo->prepare('INSERT INTO banners (title,subtitle,image,link,button_text,position,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?)')->execute($vals);
        flash('success', 'Banner saved.');
    }
    if ($act === 'delete') { $pdo->prepare('DELETE FROM banners WHERE id=?')->execute([$id]); flash('success', 'Banner deleted.'); }
    if ($act === 'toggle') { $pdo->prepare('UPDATE banners SET is_active=1-is_active WHERE id=?')->execute([$id]); }
    redirect('admin/banners.php');
}
$panel = 'admin'; $title = 'Homepage Banners'; require __DIR__ . '/../includes/admin_header.php';
$edit = null; if (get('edit')) { $s = $pdo->prepare('SELECT * FROM banners WHERE id=?'); $s->execute([(int)get('edit')]); $edit = $s->fetch(); }
$b = $edit ?: ['id' => 0, 'title' => '', 'subtitle' => '', 'link' => '', 'button_text' => '', 'position' => 'hero', 'sort_order' => 0, 'is_active' => 1, 'image' => null];
$rows = $pdo->query('SELECT * FROM banners ORDER BY position, sort_order, id')->fetchAll(); ?>
<p class="muted">Hero banners appear in the homepage slider; promo banners appear as highlight cards. Leave the image empty for a coloured gradient.</p>
<div class="grid cols"><div class="box table-wrap"><table><tr><th>Title</th><th>Position</th><th>Order</th><th>Active</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><b><?= e($r['title']) ?></b><div class="muted small"><?= e($r['subtitle']) ?></div></td><td><?= e($r['position']) ?></td><td><?= $r['sort_order'] ?></td>
<td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm <?= $r['is_active'] ? 'btn-ok' : 'btn-outline' ?>"><?= $r['is_active'] ? 'On' : 'Off' ?></button></form></td>
<td class="flex"><a class="btn btn-sm" href="?edit=<?= $r['id'] ?>">Edit</a><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-danger" data-confirm="Delete banner?">✕</button></form></td></tr><?php endforeach; ?></table></div>
<div class="box"><h3><?= $edit ? 'Edit banner' : 'New banner' ?></h3><form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
<label>Title</label><input name="title" value="<?= e($b['title']) ?>" required><label>Subtitle</label><input name="subtitle" value="<?= e($b['subtitle']) ?>"><label>Button text</label><input name="button_text" value="<?= e($b['button_text']) ?>">
<label>Link (e.g. search.php?category=fashion)</label><input name="link" value="<?= e($b['link']) ?>"><label>Position</label><select name="position"><option value="hero" <?= $b['position'] === 'hero' ? 'selected' : '' ?>>Hero slider</option><option value="promo" <?= $b['position'] === 'promo' ? 'selected' : '' ?>>Promo card</option></select>
<label>Sort order</label><input type="number" name="sort_order" value="<?= (int)$b['sort_order'] ?>"><label>Image (optional)</label><input type="file" name="image" accept="image/*">
<p><label style="display:inline"><input type="checkbox" name="is_active" value="1" <?= $b['is_active'] ? 'checked' : '' ?>> Active</label></p><button class="btn">Save banner</button> <?php if ($edit): ?><a class="btn btn-outline" href="banners.php">Cancel</a><?php endif; ?></form></div></div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
