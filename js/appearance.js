// Shared appearance: cookies before the first paint, account preferences
// afterwards, and a temporary skin for this visit. js/skins.js renders home.
window.SanakanAppearance = (function () {
  var root = document.documentElement;
  var serverAppearance = root.hasAttribute('data-hud');
  var visit = null;
  function cookie(name) {
    var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
  }
  if (!root.hasAttribute('data-hud')) {
    var hud = cookie('hud');
    if (hud === 'accent' || hud === 'full') {
      root.setAttribute('data-hud', hud);
      var role = cookie('hudrole');
      if (role) root.classList.add('role-' + role);
    }
  }
  // A PHP page carrying data-hud already knows the account's pick, including
  // no skin. An old cookie must not override that empty server preference.
  if (!root.hasAttribute('data-skin') && !serverAppearance) {
    var skin = cookie('skin');
    if (/^[a-z][a-z0-9-]*$/.test(skin)) root.setAttribute('data-skin', skin);
  }
  var preferred = { skin: root.getAttribute('data-skin') || '', words: cookie('skinwords') !== 'off' };
  function current() {
    var state = visit || preferred;
    return { skin: state.skin, words: state.words };
  }
  function paintSkin(id) {
    if (id) root.setAttribute('data-skin', id);
    else root.removeAttribute('data-skin');
  }
  function apply() {
    var state = current();
    paintSkin(state.skin);
    document.dispatchEvent(new CustomEvent('sanakan:appearance', { detail: state }));
  }
  document.addEventListener('sanakan:account', function (e) {
    var me = e.detail || {};
    root.className = root.className.replace(/\brole-\S+/g, '').trim();
    if (me.hud === 'accent' || me.hud === 'full') {
      root.setAttribute('data-hud', me.hud);
      if (me.role) root.classList.add('role-' + me.role);
    } else root.removeAttribute('data-hud');
    // Reconcile empty preferences and a vanished account too. A deliberate
    // April override still lasts this visit, independently of the account.
    preferred = { skin: me.skin || '', words: me.skinwords !== false };
    apply();
  });
  function paintBar() {
    var rgb = getComputedStyle(root).getPropertyValue('--accent-rgb').trim();
    if (!rgb) return;
    var meta = document.querySelector('meta[name="theme-color"]');
    if (!meta) {
      meta = document.createElement('meta');
      meta.name = 'theme-color';
      document.head.appendChild(meta);
    }
    meta.setAttribute('content', 'rgb(' + rgb + ')');
  }
  paintBar();
  new MutationObserver(paintBar).observe(root, { attributes: true, attributeFilter: ['class', 'data-hud', 'data-skin'] });
  return {
    current: current,
    preview: paintSkin,
    forVisit: function (id, words) {
      visit = { skin: id, words: words };
      apply();
    }
  };
})();
