<?php
require_once __DIR__ . '/../db.php'; require_admin();
if (is_post()) {
    $act = post('action'); $id = (int)post('id'); $name = post('name');
    if ($act === 'save') {
        if (mb_strlen($name) < 2) flash('danger', 'Category name is too short.');
        else {
            $slug = slugify($name);
            $dup = $pdo->prepare('SELECT 1 FROM categories WHERE slug=? AND id<>?'); $dup->execute([$slug, $id]);
            if ($dup->fetchColumn()) flash('danger', 'A category with this name already exists.');
            elseif ($id) { $pdo->prepare('UPDATE categories SET name=?, slug=? WHERE id=?')->execute([$name, $slug, $id]); flash('success', 'Category updated.'); }
            else { $pdo->prepare('INSERT INTO categories (name,slug) VALUES (?,?)')->execute([$name, $slug]); flash('success', 'Category added.'); }
        }
    }
    if ($act === 'delete') { $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]); flash('success', 'Category deleted (its products are now uncategorised).'); }
    redirect('admin/categories.php');
}
$panel = 'admin'; $title = 'Categories'; require __DIR__ . '/../includes/admin_header.php';
$edit = null; if (get('edit')) { $s = $pdo->prepare('SELECT * FROM categories WHERE id=?'); $s->execute([(int)get('edit')]); $edit = $s->fetch(); }
$rows = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) n FROM categories c ORDER BY name')->fetchAll(); ?>
<div class="grid cols"><div class="box"><div class="table-wrap"><table><tr><th>Name</th><th>Slug</th><th>Products</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><b><?= e($r['name']) ?></b></td><td class="muted"><?= e($r['slug']) ?></td><td><?= $r['n'] ?></td><td class="flex"><a class="btn btn-sm" href="?edit=<?= $r['id'] ?>">Edit</a>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-danger" data-confirm="Delete this category?">Delete</button></form></td></tr><?php endforeach; ?></table></div></div>
<div class="box"><h3><?= $edit ? 'Edit category' : 'Add category' ?></h3><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
<label>Name</label><input name="name" value="<?= e($edit['name'] ?? '') ?>" required><p><button class="btn">Save</button> <?php if ($edit): ?><a class="btn btn-outline" href="categories.php">Cancel</a><?php endif; ?></p></form></div></div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
