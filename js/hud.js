// The HUD colour chosen in the profile (/account/), kept in cookies so the
// pages that render no account menu (the home page, the privacy notice, the
// 404, the public status) can apply it too, before the first paint. A page
// that knows the account sets data-hud itself (hudHtmlAttributes() in
// inc/auth.php) and is left alone. Loaded in the head, without defer, so the
// colour is on the page before anything is drawn.
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

  // the choice in the profile: preview it on the page itself as it is picked
  Array.prototype.forEach.call(document.querySelectorAll('[data-hud-choice]'), function (input) {
    input.addEventListener('change', function () {
      if (input.checked) root.setAttribute('data-hud', input.value);
    });
  });

  // the colour of the HUD (a panel admin picks any role, or its own)
  Array.prototype.forEach.call(document.querySelectorAll('[data-hud-color]'), function (input) {
    input.addEventListener('change', function () {
      if (!input.checked) return;
      var key = input.value === 'own' ? root.getAttribute('data-hud-own') : input.value;
      root.className = key ? 'role-' + key : '';
    });
  });
})();
