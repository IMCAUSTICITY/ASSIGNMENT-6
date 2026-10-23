<?php
require_once __DIR__ . '/../db.php'; require_admin();
$tab = in_array(get('tab'), ['users', 'feedback', 'subscribers', 'reviews', 'questions'], true) ? get('tab') : 'users';
if (is_post()) {
    $act = post('action'); $id = (int)post('id');
    if ($act === 'user_toggle') { $pdo->prepare("UPDATE users SET status=1-status WHERE id=? AND role<>'admin'")->execute([$id]); flash('success', 'User status changed.'); }
    if ($act === 'user_delete') { $pdo->prepare("DELETE FROM users WHERE id=? AND role='customer'")->execute([$id]); flash('success', 'Customer deleted.'); }
    if ($act === 'feedback_delete') $pdo->prepare('DELETE FROM feedback WHERE id=?')->execute([$id]);
    if ($act === 'sub_delete') $pdo->prepare('DELETE FROM newsletter WHERE id=?')->execute([$id]);
    if ($act === 'review_delete') { $pdo->prepare('DELETE FROM reviews WHERE id=?')->execute([$id]); flash('success', 'Review removed.'); }
    if ($act === 'answer') { $pdo->prepare('UPDATE product_questions SET answer=? WHERE id=?')->execute([mb_substr(post('answer'), 0, 1000), $id]); flash('success', 'Answer published.'); }
    if ($act === 'q_delete') $pdo->prepare('DELETE FROM product_questions WHERE id=?')->execute([$id]);
    if ($act === 'reply') {
        $f = $pdo->prepare('SELECT * FROM feedback WHERE id=?'); $f->execute([$id]); if ($f = $f->fetch()) { send_mail($f['email'], 'Re: your message to ' . setting('site_name'), post('reply')); flash('success', 'Reply emailed to ' . $f['email']); }
    }
    if ($act === 'sub_export') { csv_download('subscribers', ['email', 'joined'], $pdo->query('SELECT email, created_at FROM newsletter')->fetchAll()); }
    redirect('admin/users.php?tab=' . $tab);
}
$panel = 'admin'; $title = 'Users & Inbox'; require __DIR__ . '/../includes/admin_header.php'; ?>
<div class="tabs"><?php foreach (['users' => 'Users', 'feedback' => 'Contact messages', 'reviews' => 'Reviews', 'questions' => 'Product Q&A', 'subscribers' => 'Newsletter'] as $k => $l): ?><a class="<?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?>"><?= $l ?></a><?php endforeach; ?></div>
<div class="box table-wrap">
<?php if ($tab === 'users'): $rows = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) n FROM users u ORDER BY id DESC")->fetchAll(); ?>
<table><tr><th>Name</th><th>Email</th><th>Role</th><th>Orders</th><th>Joined</th><th>Status</th><th></th></tr><?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['email']) ?></td><td><?= e($r['role']) ?></td><td><?= $r['n'] ?></td><td><?= e(date('d M Y', strtotime($r['created_at']))) ?></td><td><?= $r['status'] ? '<span class="pill ok">Active</span>' : '<span class="pill bad">Blocked</span>' ?></td>
<td class="flex"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="user_toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline"><?= $r['status'] ? 'Block' : 'Unblock' ?></button></form>
<?php if ($r['role'] === 'customer'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="user_delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-danger" data-confirm="Delete this customer and their orders?">Delete</button></form><?php endif; ?></td></tr><?php endforeach; ?></table>
<?php elseif ($tab === 'feedback'): $rows = $pdo->query('SELECT * FROM feedback ORDER BY id DESC')->fetchAll(); if (!$rows) echo '<p class="muted">No messages yet.</p>'; foreach ($rows as $r): ?>
<div class="review"><b><?= e($r['name']) ?></b> &lt;<?= e($r['email']) ?>&gt; <span class="muted small"><?= e($r['created_at']) ?></span><p><?= nl2br(e($r['message'])) ?></p>
<form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input name="reply" placeholder="Write a reply…" required><button class="btn btn-sm" name="action" value="reply">Email reply</button><button class="btn btn-sm btn-danger" name="action" value="feedback_delete" data-confirm="Delete message?">Delete</button></form></div><?php endforeach; ?>
<?php elseif ($tab === 'reviews'): $rows = $pdo->query('SELECT r.*, u.name uname, p.name pname FROM reviews r JOIN users u ON u.id=r.user_id JOIN products p ON p.id=r.product_id ORDER BY r.id DESC LIMIT 200')->fetchAll(); ?>
<table><tr><th>Product</th><th>User</th><th>Rating</th><th>Comment</th><th></th></tr><?php foreach ($rows as $r): ?><tr><td><?= e($r['pname']) ?></td><td><?= e($r['uname']) ?></td><td><?= stars((float)$r['rating']) ?></td><td><?= e($r['comment']) ?></td><td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="review_delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-danger" data-confirm="Remove review?">✕</button></form></td></tr><?php endforeach; ?></table>
<?php elseif ($tab === 'questions'): $rows = $pdo->query('SELECT q.*, u.name uname, p.name pname FROM product_questions q JOIN users u ON u.id=q.user_id JOIN products p ON p.id=q.product_id ORDER BY (q.answer IS NULL) DESC, q.id DESC')->fetchAll(); if (!$rows) echo '<p class="muted">No questions yet.</p>'; foreach ($rows as $r): ?>
<div class="review"><b><?= e($r['pname']) ?></b> — <?= e($r['uname']) ?>: <?= e($r['question']) ?><form method="post" class="inline-form mt"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input name="answer" value="<?= e($r['answer']) ?>" placeholder="Your answer…" required style="min-width:280px"><button class="btn btn-sm" name="action" value="answer">Answer</button><button class="btn btn-sm btn-danger" name="action" value="q_delete" data-confirm="Delete?">✕</button></form></div><?php endforeach; ?>
<?php else: $rows = $pdo->query('SELECT * FROM newsletter ORDER BY id DESC')->fetchAll(); ?>
<form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-outline" name="action" value="sub_export">⬇ Export CSV</button></form>
<table><tr><th>Email</th><th>Subscribed</th><th></th></tr><?php foreach ($rows as $r): ?><tr><td><?= e($r['email']) ?></td><td><?= e($r['created_at']) ?></td><td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="sub_delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-danger">✕</button></form></td></tr><?php endforeach; ?></table>
<?php endif; ?></div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
