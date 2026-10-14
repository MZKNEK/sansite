// Sakura and Perseids: background decorations never cover controls. A pointer
// click on empty space may catch a visible petal/comet; one is also reachable
// by keyboard. The server witnesses the find before anything is remembered.
(function () {
  var guard = window.SanakanSafeguard;
  if (!guard) return;
  var small = innerWidth < 600;
  var protectedArea = 'a, button, input, select, textarea, [contenteditable], [role="button"], [role="dialog"], '
    + '.logo-wrap, .card, .notice-bar, .test-bar, .account-menu, .sg-toast, .sg-silicon, .ns-overlay, .pl-overlay, .pp-overlay, article.cmd';
  var PETAL = '<svg viewBox="0 0 24 30" aria-hidden="true"><path d="M12 29C3 22 0 13 3 6 5 1 9 1 12 6 15 1 19 1 21 6 24 13 21 22 12 29Z" /></svg>';

  function between(min, max) { return min + Math.random() * (max - min); }
  function variable(node, name, value) { node.style.setProperty('--season-' + name, value); }

  function scene(key, name, label, count, decorate, particle) {
    if (!guard.holiday(key)) return;
    var layer = document.createElement('div');
    layer.className = 'sg-season-background sg-' + key;
    layer.setAttribute('role', 'group');
    layer.setAttribute('aria-label', name);
    var targets = [];
    var pending = false;
    if (decorate) decorate(layer);

    function catchTarget(button) {
      if (pending || guard.finds()[key] || !guard.holiday(key)) return;
      pending = true;
      layer.setAttribute('aria-busy', 'true');
      button.classList.add('catching');
      guard.witness(key).then(function (noted) {
        pending = false;
        layer.removeAttribute('aria-busy');
        button.classList.remove('catching');
        if (noted) targets.forEach(function (target) { target.button.disabled = true; });
        else guard.notice(name, 'Safeguard nie odebrał sygnału', 'Spróbuj ponownie.', null);
      });
    }

    for (var i = 0; i < count; i++) {
      var mover = document.createElement('div');
      mover.className = 'seasonal-particle';
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'seasonal-catch';
      button.setAttribute('aria-label', label);
      button.tabIndex = i === 0 ? 0 : -1;
      if (i !== 0) button.setAttribute('aria-hidden', 'true');
      button.disabled = !!guard.finds()[key];
      button.addEventListener('click', function (e) { catchTarget(e.currentTarget); });
      particle(mover, button, i, count);
      targets.push({ button: button, mover: mover });
      layer.appendChild(mover);
    }
    document.body.classList.add('seasonal-background');
    document.body.appendChild(layer);

    document.addEventListener('click', function (e) {
      if (e.defaultPrevented || !e.target.closest || e.target.closest(protectedArea)) return;
      targets.some(function (target) {
        if (!target.button.getClientRects().length || getComputedStyle(target.mover).opacity < 0.1) return false;
        var r = target.button.getBoundingClientRect();
        if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) return false;
        catchTarget(target.button);
        return true;
      });
    }, { passive: true });
    function visibility() { layer.classList.toggle('paused', document.hidden); }
    document.addEventListener('visibilitychange', visibility);
    visibility();
  }

  scene('sakura', 'Sakura', 'Złap płatek sakury', small ? 12 : 20, null, function (mover, button, i, count) {
    var duration = between(15, 26);
    mover.classList.add('sakura-fall');
    mover.style.left = ((i + Math.random()) * 100 / count).toFixed(2) + '%';
    variable(mover, 'duration', duration.toFixed(2) + 's');
    variable(mover, 'delay', (-((i * 7) % count + 0.5) * duration / count).toFixed(2) + 's');
    variable(mover, 'rest-top', between(12, 84).toFixed(2) + 'vh');
    variable(mover, 'size', between(13, 23).toFixed(2) + 'px');
    variable(mover, 'color', ['#efb2c9', '#e5a0b9', '#f4ced9'][i % 3]);
    variable(mover, 'opacity', between(0.35, 0.6).toFixed(2));
    variable(mover, 'sway', between(4, 8).toFixed(2) + 's');
    var drift = document.createElement('div');
    drift.className = 'sakura-drift';
    button.classList.add('sakura-petal');
    button.innerHTML = PETAL;
    drift.appendChild(button);
    mover.appendChild(drift);
  });

  scene('perseids', 'Perseidy', 'Złap kometę', small ? 2 : 3, function (layer) {
    for (var i = 0; i < (small ? 32 : 56); i++) {
      var star = document.createElement('i');
      star.className = 'perseid-star';
      star.setAttribute('aria-hidden', 'true');
      star.style.left = between(1, 99).toFixed(2) + '%';
      star.style.top = between(1, 99).toFixed(2) + '%';
      variable(star, 'size', between(1, 2.5).toFixed(2) + 'px');
      variable(star, 'duration', between(3, 8).toFixed(2) + 's');
      variable(star, 'delay', (-between(0, 8)).toFixed(2) + 's');
      layer.appendChild(star);
    }
  }, function (mover, button, i, count) {
    var duration = between(10, 16);
    mover.classList.add('perseid-flight');
    mover.style.left = ((i + 0.5) * 70 / count).toFixed(2) + '%';
    variable(mover, 'duration', duration.toFixed(2) + 's');
    variable(mover, 'delay', (-(0.62 + i * 0.09) * duration).toFixed(2) + 's');
    variable(mover, 'rest-top', (20 + i * 24) + 'vh');
    button.classList.add('perseid-comet');
    mover.appendChild(button);
  });
})();
