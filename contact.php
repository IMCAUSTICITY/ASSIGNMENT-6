<?php
require_once __DIR__ . '/db.php';
$f = ['name' => user()['name'] ?? '', 'email' => user()['email'] ?? '', 'message' => ''];
if (is_post()) {
    foreach ($f as $k => $_) $f[$k] = post($k);
    $errs = [];
    if (mb_strlen($f['name']) < 2) $errs[] = 'Please enter your name.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errs[] = 'Enter a valid email.';
    if (mb_strlen($f['message']) < 10) $errs[] = 'Message must be at least 10 characters.';
    if (post('website') !== '') $errs[] = 'Spam detected.';   // honeypot field
    if (!$errs) {
        $pdo->prepare('INSERT INTO feedback (name,email,message) VALUES (?,?,?)')->execute([$f['name'], $f['email'], mb_substr($f['message'], 0, 3000)]);
        send_mail(setting('contact_email'), 'New contact message from ' . str_replace(["\r", "\n"], ' ', $f['name']), "From: {$f['name']} <{$f['email']}>\n\n{$f['message']}");
        send_mail($f['email'], 'We received your message', "Hi {$f['name']}, thanks for contacting " . setting('site_name') . ". We will reply soon.");
        flash('success', 'Thanks! Your message has been sent.'); redirect('contact.php');
    }
    foreach ($errs as $er) flash('danger', $er);
}
$title = 'Contact us'; require __DIR__ . '/includes/header.php'; ?>
<div class="grid two"><div class="box"><h2>Contact us</h2>
  <form method="post"><?= csrf_field() ?><div style="display:none"><input name="website" tabindex="-1" autocomplete="off"></div>
    <label>Name</label><input name="name" value="<?= e($f['name']) ?>" required><label>Email</label><input type="email" name="email" value="<?= e($f['email']) ?>" required>
    <label>Message</label><textarea name="message" required minlength="10"><?= e($f['message']) ?></textarea><p><button class="btn">Send message</button></p></form></div>
  <div class="box"><h3>Get in touch</h3><p>📍 <?= e(setting('contact_address')) ?></p><p>📞 <?= e(setting('contact_phone')) ?></p><p>✉ <?= e(setting('contact_email')) ?></p><p class="muted">We usually reply within 24 hours.</p></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
