// Shared appearance: cookies before the first paint, account preferences
// afterwards, and a temporary skin for this visit. js/skins.js renders home.
window.SanakanAppearance = (function () {
  var root = document.documentElement;
  var serverAppearance = root.hasAttribute('data-hud');
  var visit = null;
  function cookie(name) {
    var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    try { return match ? decodeURIComponent(match[1]) : ''; } catch (_) { return ''; }
  }
  function customDesign(value) {
    try { if (typeof value === 'string') value = JSON.parse(value); } catch (_) { return null; }
    if (!value || !value.colors) return null;
    var colors = {};
    var keys = ['accent', 'accent-light', 'accent-dark', 'bg-dark', 'bg-mid', 'text', 'home-title'];
    for (var i = 0; i < keys.length; i++) {
      var color = value.colors[keys[i]];
      if (typeof color !== 'string' || !/^#[0-9a-f]{6}$/i.test(color)) return null;
      colors[keys[i]] = color;
    }
    var image = '';
    if (typeof value.image === 'string' && value.image.charAt(0) === '/') {
      try {
        var url = new URL(value.image, location.href);
        if (url.origin === location.origin && /\/i\/u\/[0-9a-f]{16}\//.test(url.pathname)) image = url.pathname;
      } catch (_) { /* An unavailable picture falls back to Sanakan. */ }
    }
    return { colors: colors, image: image };
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
  var preferred = {
    skin: root.getAttribute('data-skin') || '', words: cookie('skinwords') !== 'off',
    custom: customDesign(serverAppearance ? root.getAttribute('data-custom-skin') : cookie('customskin'))
  };
  function current() {
    var state = visit || preferred;
    return { skin: state.skin, words: state.words, custom: state.skin === 'custom' ? state.custom : null };
  }
  function paintSkin(id, custom) {
    custom = customDesign(custom);
    root.style.removeProperty('--custom-logo');
    ['accent', 'accent-light', 'accent-dark', 'bg-dark', 'bg-mid', 'text', 'home-title',
      'accent-rgb', 'accent-light-rgb', 'accent-dark-rgb'].forEach(function (key) {
      root.style.removeProperty('--custom-' + key);
    });
    if (id === 'custom' && custom) {
      Object.keys(custom.colors).forEach(function (key) {
        var hex = custom.colors[key];
        root.style.setProperty('--custom-' + key, hex);
        if (key.indexOf('accent') === 0) {
          var rgb = [1, 3, 5].map(function (offset) { return parseInt(hex.slice(offset, offset + 2), 16); });
          root.style.setProperty('--custom-' + key + '-rgb', rgb.join(', '));
        }
      });
      if (custom.image) root.style.setProperty('--custom-logo', 'url(' + JSON.stringify(custom.image) + ')');
    }
    if (id) root.setAttribute('data-skin', id);
    else root.removeAttribute('data-skin');
  }
  function apply() {
    var state = current();
    paintSkin(state.skin, state.custom);
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
    preferred = { skin: me.skin || '', words: me.skinwords !== false, custom: customDesign(me.customskin) };
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
  paintSkin(preferred.skin, preferred.custom);
  paintBar();
  new MutationObserver(paintBar).observe(root, { attributes: true, attributeFilter: ['class', 'data-hud', 'data-skin', 'style'] });
  return {
    current: current,
    preview: paintSkin,
    forVisit: function (id, words) {
      visit = { skin: id, words: words };
      apply();
    }
  };
})();
