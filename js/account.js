// The account menu in the top right corner (accountMenuHtml() in inc/auth.php):
// a click on the account opens it, a click outside or Escape closes it. The
// static pages (the home page, the privacy notice, the 404) cannot render it,
// so they ask account.php for it: a page with a [data-account-slot] gets it
// there by itself, the home page calls SanakanAccount.load for the rest of
// what account.php says.
window.SanakanAccount = (function () {
  // the site's root, from this script's own address (js/account.js)
  var ROOT = new URL('../', document.currentScript.src).pathname;

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

  // this page, where logging out (and a login started here) comes back to
  function here() {
    return location.pathname + location.search;
  }

  // Asks account.php for the account, puts its menu into box and its HUD
  // colour on the page; resolves to what account.php said, or null.
  function load(box) {
    var root = document.documentElement;

    return fetch(ROOT + 'account.php?back=' + encodeURIComponent(here()), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (me) {
        if (!me) return null;
        if (!me.menu) {
          // the colour of an account that is gone (js/hud.js read it from the
          // cookies account.php has just removed)
          root.removeAttribute('data-hud');
          root.className = root.className.replace(/\brole-\S+/g, '').trim();
          return me;
        }

        // the HUD colour chosen in the profile (/account/), applied at once
        if (me.hud === 'accent' || me.hud === 'full') {
          root.setAttribute('data-hud', me.hud);
          if (me.role) root.classList.add('role-' + me.role);
        }

        // the menu comes ready from the server, its texts escaped there
        box.innerHTML = me.menu;
        bind(box.firstElementChild);
        box.hidden = false;
        return me;
      })
      .catch(function () { return null; });
  }

  document.querySelectorAll('.account-menu').forEach(bind);
  document.querySelectorAll('[data-account-slot]').forEach(load);

  // the address of a Discord login that comes back to this page
  function loginUrl() {
    return ROOT + 'account.php?login&back=' + encodeURIComponent(here());
  }

  return { bind: bind, load: load, loginUrl: loginUrl };
})();
