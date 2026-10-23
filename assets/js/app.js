// Hero slider
(function () {
  var s = document.querySelector('.slider'); if (!s) return;
  var slides = s.querySelectorAll('.slide'), i = 0;
  function show(n) { slides.forEach(function (x, k) { x.classList.toggle('active', k === n); }); }
  show(0);
  if (slides.length > 1) setInterval(function () { i = (i + 1) % slides.length; show(i); }, 5000);
  var prev = s.querySelector('.prev'), next = s.querySelector('.next');
  if (prev) prev.onclick = function () { i = (i - 1 + slides.length) % slides.length; show(i); };
  if (next) next.onclick = function () { i = (i + 1) % slides.length; show(i); };
})();
// Payment method toggle on checkout
(function () {
  var radios = document.querySelectorAll('input[name=payment_method]'); if (!radios.length) return;
  function upd() { var v = document.querySelector('input[name=payment_method]:checked').value;
    document.querySelectorAll('.pay-box').forEach(function (b) { b.style.display = b.dataset.method === v ? 'block' : 'none'; }); }
  radios.forEach(function (r) { r.addEventListener('change', upd); }); upd();
})();
// Confirm dialogs
document.addEventListener('click', function (e) {
  var el = e.target.closest('[data-confirm]');
  if (el && !confirm(el.getAttribute('data-confirm'))) e.preventDefault();
});
// Star rating helper
document.querySelectorAll('.rate-input label').forEach(function (l) {
  l.addEventListener('click', function () { var g = l.parentNode; g.dataset.val = l.dataset.v; });
});
// Print button
document.querySelectorAll('[data-print]').forEach(function (b) { b.onclick = function () { window.print(); }; });
