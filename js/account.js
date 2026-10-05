// The account menu in the top right corner (accountMenuHtml() in inc/auth.php):
// a click on the account opens it, a click outside or Escape closes it. The
// home page builds its menu later and calls SanakanAccount.bind itself.
window.SanakanAccount = (function () {
  function bind(menu) {
    var toggle = menu.querySelector('.account-toggle');
    var drop = menu.querySelector('.account-drop');
    if (!toggle || !drop || menu.dataset.bound) return;
    menu.dataset.bound = '1';

    function show(open) {
      drop.hidden = !open;
      menu.classList.toggle('open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    toggle.addEventListener('click', function () {
      show(drop.hidden);
    });
    document.addEventListener('click', function (e) {
      if (!drop.hidden && !menu.contains(e.target)) show(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !drop.hidden) {
        show(false);
        toggle.focus();
      }
    });
  }

  document.querySelectorAll('.account-menu').forEach(bind);

  return { bind: bind };
})();
