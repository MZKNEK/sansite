// The HUD colour chosen in the profile (/account/), kept in cookies so the
// pages that render no account menu (the home page, the privacy notice, the
// 404, the public status) can apply it too, before the first paint. A page
// that knows the account sets data-hud itself (hudHtmlAttributes() in
// inc/auth.php) and is left alone, but still gets the browser's bar coloured
// (below). Loaded in the head, without defer, so the colour is on the page
// before anything is drawn.
(function () {
  var root = document.documentElement;

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

  // the variant in the profile: preview it on the page itself as it is picked
  Array.prototype.forEach.call(document.querySelectorAll('[data-hud-choice]'), function (field) {
    field.addEventListener('change', function () {
      root.setAttribute('data-hud', field.value);
    });
  });

  // the colour of the HUD (a panel admin picks any role, or its own); a dot
  // next to the label shows the picked colour
  Array.prototype.forEach.call(document.querySelectorAll('[data-hud-color]'), function (field) {
    var dot = document.querySelector('[data-hud-dot]');

    function paint() {
      var option = field.options ? field.options[field.selectedIndex] : null;
      if (dot && option && option.getAttribute('data-hex'))
        dot.style.background = option.getAttribute('data-hex');
    }

    field.addEventListener('change', function () {
      var key = field.value === 'own' ? root.getAttribute('data-hud-own') : field.value;
      root.className = key ? 'role-' + key : '';
      paint();
    });

    paint();
  });

  // The browser's bar (theme-color, on Android and in an installed app) in the
  // colour of the HUD's accent, read from the CSS, so it follows the picked
  // colour, the preview in the profile and the HUD js/home.js puts on later.
  // The tag written in the page stays the site's purple for the link previews,
  // which read the HTML and run no script.
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
  new MutationObserver(paintBar).observe(root, { attributes: true, attributeFilter: ['class', 'data-hud'] });
})();
