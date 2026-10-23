<?php
/**
 * Product management shared by admin/products.php (all vendors) and vendor/products.php (own products).
 * Expects: $panel ('admin'|'vendor'), $scopeVendor (int|null) and admin_header.php already included BEFORE output.
 * Because actions redirect, this file is included BEFORE the header (see callers) via two phases: handle() then render().
 */
$self = url($panel . '/products.php');
$cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$vendorsList = $pdo->query("SELECT id,name FROM vendors WHERE status='approved' ORDER BY name")->fetchAll();

function owned_product(int $id, ?int $scope): ?array {
    global $pdo;
    $st = $pdo->prepare('SELECT * FROM products WHERE id=?' . ($scope ? ' AND vendor_id=' . (int)$scope : '')); $st->execute([$id]);
    return $st->fetch() ?: null;
}
function variations_text(int $pid): string {
    global $pdo; $o = [];
    $st = $pdo->prepare('SELECT * FROM product_variations WHERE product_id=? ORDER BY id'); $st->execute([$pid]);
    foreach ($st->fetchAll() as $v) $o[] = "{$v['attribute']}:{$v['value']}:{$v['stock']}:{$v['price_adjust']}";
    return implode("\n", $o);
}
function sync_variations(int $pid, string $text): void {
    global $pdo; $keep = [];
    foreach (preg_split('/\r?\n/', $text) as $line) {
        $x = array_map('trim', explode(':', $line)); if (count($x) < 2 || $x[0] === '' || $x[1] === '') continue;
        $stock = max(0, (int)($x[2] ?? 0)); $adj = (float)($x[3] ?? 0);
        $f = $pdo->prepare('SELECT id FROM product_variations WHERE product_id=? AND attribute=? AND value=?'); $f->execute([$pid, $x[0], $x[1]]); $vid = $f->fetchColumn();
        if ($vid) $pdo->prepare('UPDATE product_variations SET stock=?, price_adjust=? WHERE id=?')->execute([$stock, $adj, $vid]);
        else { $pdo->prepare('INSERT INTO product_variations (product_id,attribute,value,stock,price_adjust) VALUES (?,?,?,?,?)')->execute([$pid, $x[0], $x[1], $stock, $adj]); $vid = $pdo->lastInsertId(); }
        $keep[] = (int)$vid;
    }
    $in = $keep ? implode(',', $keep) : '0';
    $pdo->exec("DELETE FROM product_variations WHERE product_id=" . (int)$pid . " AND id NOT IN ($in)");
}

/* ---- CSV export ---- */
if (get('export')) {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="products-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w'); fputcsv($out, ['name', 'description', 'category', 'price', 'sale_price', 'stock', 'status', 'vendor']);
    $st = $pdo->query('SELECT p.*, c.name cname, v.name vname FROM products p LEFT JOIN categories c ON c.id=p.category_id JOIN vendors v ON v.id=p.vendor_id' . ($scopeVendor ? ' WHERE p.vendor_id=' . (int)$scopeVendor : ''));
    foreach ($st as $r) fputcsv($out, [$r['name'], $r['description'], $r['cname'], $r['price'], $r['sale_price'], $r['stock'], $r['status'], $r['vname']]);
    exit;
}

if (is_post()) {
    $act = post('action');
    if ($act === 'save') {
        $id = (int)post('id'); $old = $id ? owned_product($id, $scopeVendor) : null;
        if ($id && !$old) { flash('danger', 'Product not found.'); redirect("$panel/products.php"); }
        $name = post('name'); $price = (float)post('price'); $sale = post('sale_price') !== '' ? (float)post('sale_price') : null;
        $stock = (int)post('stock'); $cat = (int)post('category_id') ?: null; $vid = $scopeVendor ?: (int)post('vendor_id');
        $errs = [];
        if (mb_strlen($name) < 3) $errs[] = 'Product name must be at least 3 characters.';
        if ($price <= 0) $errs[] = 'Price must be greater than 0.';
        if ($sale !== null && ($sale <= 0 || $sale >= $price)) $errs[] = 'Sale price must be lower than the regular price.';
        if ($stock < 0) $errs[] = 'Stock cannot be negative.';
        if (!$vid) $errs[] = 'Choose a vendor.';
        if ($errs) { foreach ($errs as $er) flash('danger', $er); redirect("$panel/products.php?" . ($id ? "edit=$id" : 'new=1')); }
        $img = save_upload('image') ?? ($old['image'] ?? null);
        $feat = $panel === 'admin' ? (post('is_featured') ? 1 : 0) : ($old['is_featured'] ?? 0);
        $status = post('status') ? 1 : 0;
        $slug = slugify($name);
        if ($id) {
            $pdo->prepare('UPDATE products SET vendor_id=?,name=?,slug=?,description=?,price=?,sale_price=?,stock=?,image=?,category_id=?,is_featured=?,status=? WHERE id=?')
                ->execute([$vid, $name, $slug, post('description'), $price, $sale, $stock, $img, $cat, $feat, $status, $id]);
        } else {
            $pdo->prepare('INSERT INTO products (vendor_id,name,slug,description,price,sale_price,stock,image,category_id,is_featured,status) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$vid, $name, $slug, post('description'), $price, $sale, $stock, $img, $cat, $feat, $status]);
            $id = (int)$pdo->lastInsertId();
        }
        sync_variations($id, $_POST['variations'] ?? '');
        flash('success', 'Product saved.'); redirect("$panel/products.php");
    }
    if ($act === 'delete') {
        if (owned_product((int)post('id'), $scopeVendor)) { $pdo->prepare('DELETE FROM products WHERE id=?')->execute([(int)post('id')]); flash('success', 'Product deleted.'); }
        redirect("$panel/products.php");
    }
    if ($act === 'toggle' && $panel === 'admin') {
        $col = post('col') === 'is_featured' ? 'is_featured' : 'status';
        $pdo->prepare("UPDATE products SET $col = 1-$col WHERE id=?")->execute([(int)post('id')]); redirect("$panel/products.php");
    }
    if ($act === 'import') {
        $vid = $scopeVendor ?: (int)post('vendor_id'); $n = 0;
        if (!$vid) flash('danger', 'Choose a vendor for the import.');
        elseif (empty($_FILES['csv']['tmp_name']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) flash('danger', 'Choose a CSV file first.');
        else {
            $h = fopen($_FILES['csv']['tmp_name'], 'r'); $hdr = array_map(fn($x) => strtolower(trim($x)), fgetcsv($h) ?: []);
            while (($r = fgetcsv($h)) !== false) {
                $row = @array_combine($hdr, array_pad($r, count($hdr), '')); if (!$row || trim($row['name'] ?? '') === '' || (float)($row['price'] ?? 0) <= 0) continue;
                $c = $pdo->prepare('SELECT id FROM categories WHERE name=?'); $c->execute([trim($row['category'] ?? '')]); $cid = $c->fetchColumn() ?: null;
                $sp = (float)($row['sale_price'] ?? 0);
                $pdo->prepare('INSERT INTO products (vendor_id,name,slug,description,price,sale_price,stock,category_id,status) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$vid, trim($row['name']), slugify($row['name']), $row['description'] ?? '', (float)$row['price'], ($sp > 0 && $sp < $row['price']) ? $sp : null, max(0, (int)($row['stock'] ?? 0)), $cid, ($row['status'] ?? '1') === '0' ? 0 : 1]);
                $n++;
            }
            fclose($h); flash('success', "$n products imported.");
        }
        redirect("$panel/products.php");
    }
}

/* ---- render (called after admin_header is included) ---- */
function render_products(string $panel, ?int $scopeVendor, string $self, array $cats, array $vendorsList): void {
    global $pdo;
    $editing = null;
    if (get('edit')) $editing = owned_product((int)get('edit'), $scopeVendor);
    $showForm = $editing || get('new');
    $p = $editing ?: ['id' => 0, 'vendor_id' => $scopeVendor, 'name' => '', 'description' => '', 'price' => '', 'sale_price' => '', 'stock' => 10, 'category_id' => '', 'is_featured' => 0, 'status' => 1, 'image' => null];
    if ($showForm): ?>
    <div class="box"><h3><?= $editing ? 'Edit product' : 'Add product' ?></h3>
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
      <div class="grid two"><div><label>Name</label><input name="name" value="<?= e($p['name']) ?>" required></div>
        <div><label>Category</label><select name="category_id"><option value="">— none —</option><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $p['category_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div></div>
      <div class="grid three"><div><label>Price (₹)</label><input name="price" type="number" step="0.01" min="0.01" value="<?= e($p['price']) ?>" required></div>
        <div><label>Sale price (₹, optional)</label><input name="sale_price" type="number" step="0.01" min="0" value="<?= e($p['sale_price']) ?>"></div>
        <div><label>Stock</label><input name="stock" type="number" min="0" value="<?= e($p['stock']) ?>" required></div></div>
      <?php if ($panel === 'admin'): ?><label>Vendor</label><select name="vendor_id" required><option value="">Choose…</option><?php foreach ($vendorsList as $v): ?><option value="<?= $v['id'] ?>" <?= $p['vendor_id'] == $v['id'] ? 'selected' : '' ?>><?= e($v['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
      <label>Description</label><textarea name="description"><?= e($p['description']) ?></textarea>
      <label>Image (JPG/PNG/WEBP, max 2 MB)</label><input type="file" name="image" accept="image/*"><?php if ($p['image']): ?><img class="thumb" src="<?= img_src($p['image']) ?>" alt="" style="margin-top:6px"><?php endif; ?>
      <label>Variations <span class="muted small">— one per line: <code>Attribute:Value:Stock:PriceAdjust</code> (e.g. <code>Size:M:10:0</code>, <code>Color:Red:5:100</code>)</span></label>
      <textarea name="variations" placeholder="Size:S:10:0&#10;Size:M:10:0&#10;Color:Black:20:0"><?= e($editing ? variations_text((int)$p['id']) : '') ?></textarea>
      <p><label style="display:inline"><input type="checkbox" name="status" value="1" <?= $p['status'] ? 'checked' : '' ?>> Active</label>
      <?php if ($panel === 'admin'): ?> &nbsp; <label style="display:inline"><input type="checkbox" name="is_featured" value="1" <?= $p['is_featured'] ? 'checked' : '' ?>> Featured on homepage</label><?php endif; ?></p>
      <button class="btn">Save product</button> <a class="btn btn-outline" href="<?= $self ?>">Cancel</a></form></div>
    <?php endif;
    $q = get('q'); $w = []; $pr = [];
    if ($scopeVendor) { $w[] = 'p.vendor_id=?'; $pr[] = $scopeVendor; }
    if ($q !== '') { $w[] = 'p.name LIKE ?'; $pr[] = "%$q%"; }
    $st = $pdo->prepare('SELECT p.*, c.name cname, v.name vname FROM products p LEFT JOIN categories c ON c.id=p.category_id JOIN vendors v ON v.id=p.vendor_id' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY p.id DESC LIMIT 200');
    $st->execute($pr); $rows = $st->fetchAll(); ?>
    <div class="box"><div class="flex between center-v"><form class="inline-form"><input name="q" value="<?= e($q) ?>" placeholder="Search products"><button class="btn btn-sm">Search</button></form>
      <div class="flex"><a class="btn btn-sm" href="<?= $self ?>?new=1">+ Add product</a><a class="btn btn-sm btn-outline" href="<?= $self ?>?export=1">⬇ Export CSV</a></div></div>
    <div class="table-wrap mt"><table><thead><tr><th></th><th>Product</th><?php if ($panel === 'admin'): ?><th>Vendor</th><?php endif; ?><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr><td><img class="thumb" src="<?= img_src($r['image'], $r['name']) ?>" alt=""></td><td><b><?= e($r['name']) ?></b><?= $r['is_featured'] ? ' ⭐' : '' ?></td>
      <?php if ($panel === 'admin'): ?><td><?= e($r['vname']) ?></td><?php endif; ?><td><?= e($r['cname']) ?></td>
      <td><?= money_base(unit_price($r)) ?><?= $r['sale_price'] ? ' <del class="muted small">' . money_base($r['price']) . '</del>' : '' ?></td>
      <td><?= $r['stock'] <= 5 ? '<span class="pill bad">' . $r['stock'] . '</span>' : $r['stock'] ?></td><td><?= $r['status'] ? '<span class="pill ok">Active</span>' : '<span class="pill warn">Hidden</span>' ?></td>
      <td class="flex"><a class="btn btn-sm" href="<?= $self ?>?edit=<?= $r['id'] ?>">Edit</a>
        <?php if ($panel === 'admin'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="col" value="is_featured"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline"><?= $r['is_featured'] ? 'Unfeature' : 'Feature' ?></button></form><?php endif; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-danger" data-confirm="Delete this product permanently?">Delete</button></form></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="muted">No products found.</td></tr><?php endif; ?></tbody></table></div></div>
    <div class="box"><h3>Import products from CSV</h3><p class="muted small">Columns: <code>name, description, category, price, sale_price, stock, status</code> (use Export CSV for a template).</p>
    <form method="post" enctype="multipart/form-data" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="import">
      <?php if ($panel === 'admin'): ?><select name="vendor_id" required><option value="">Vendor…</option><?php foreach ($vendorsList as $v): ?><option value="<?= $v['id'] ?>"><?= e($v['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
      <input type="file" name="csv" accept=".csv" required><button class="btn btn-sm">Import</button></form></div>
<?php }
