</main>
<footer class="site-footer"><div class="container grid footer-grid">
  <div><h4><?= e(setting('site_name')) ?></h4><p><?= e(setting('tagline')) ?></p><p class="muted small"><?= e(setting('contact_address')) ?></p></div>
  <div><h4>Quick links</h4><a href="<?= url('index.php') ?>">Home</a><a href="<?= url('search.php') ?>">All products</a><a href="<?= url('contact.php') ?>">Contact us</a><a href="<?= url('signup.php?type=vendor') ?>">Become a vendor</a><a href="<?= url('sitemap.xml') ?>">Sitemap</a></div>
  <div><h4><?= t('newsletter') ?></h4>
    <form method="post" action="<?= url('newsletter.php') ?>" class="inline-form">
      <?= csrf_field() ?><input type="email" name="email" placeholder="you@example.com" required>
      <button class="btn btn-sm" type="submit"><?= t('subscribe') ?></button>
    </form></div>
</div><div class="copy"><?= e(setting('footer_text')) ?></div></footer>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body></html>
