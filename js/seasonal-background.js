// Sakura and Perseids: seasonal backgrounds, also worn by their skins all year.
// Decorations never cover controls. A pointer
// click on empty space may catch a visible petal/comet; one is also reachable
// by keyboard. The server witnesses the find before anything is remembered.
(function () {
  var guard = window.SanakanSafeguard;
  if (!guard) return;
  var small = innerWidth < 600;
  var protectedArea = 'a, button, input, select, textarea, [contenteditable], [role="button"], [role="dialog"], '
    + '.logo-wrap, .card, .notice-bar, .test-bar, .account-menu, .sg-toast, .sg-silicon, .ns-overlay, .pl-overlay, .pp-overlay, article.cmd';
  var PETAL = '<svg viewBox="0 0 34 40" aria-hidden="true"><path d="M13.1 38.8C12.2 37.9 9.1 35.4 7.7 33.6C6.3 31.8 5.3 29.8 4.6 27.8C3.9 25.9 3.6 23.8 3.5 21.9C3.5 19.9 3.9 18 4.4 16.1C4.9 14.2 5.8 12.1 6.5 10.3C7.2 8.5 8.1 6.4 8.7 5.3C9.2 4.2 9.2 4 10 3.6C10.8 3.2 12.2 2.5 13.3 3C14.4 3.5 16.1 5.8 16.7 6.3C17.4 5.8 19.6 3.5 20.9 3.1C22.2 2.7 23.5 3.5 24.3 3.9C25.1 4.3 25.1 4.5 25.6 5.6C26.1 6.7 26.8 8.6 27.5 10.3C28.2 12.1 29.1 14.2 29.6 16.1C30.1 18 30.5 19.9 30.4 21.9C30.4 23.8 30.1 25.9 29.4 27.8C28.7 29.8 27.7 31.8 26.3 33.6C24.9 35.4 21.8 37.9 20.9 38.8Z" /></svg>';

  function between(min, max) { return min + Math.random() * (max - min); }
  function variable(node, name, value) { node.style.setProperty('--season-' + name, value); }

  function scene(key, name, label, count, decorate, particle) {
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
    // Hit-test when the press begins: a comet can leave its 44px target before
    // release. The native click still confirms the tap; a drag or scroll cancels it.
    var press = null;
    function moved(e) {
      var dx = e.clientX - press.x, dy = e.clientY - press.y;
      return dx * dx + dy * dy > 100;
    }
    function cancelPress() { press = null; }
    document.addEventListener('pointerdown', function (e) {
      cancelPress();
      if (!layer.isConnected || layer.getAttribute('aria-hidden') === 'true') return;
      if (!e.isPrimary || e.button !== 0 || e.defaultPrevented || !e.target.closest || e.target.closest(protectedArea)) return;
      targets.some(function (target) {
        if (!target.button.getClientRects().length || getComputedStyle(target.mover).opacity < 0.1) return false;
        var r = target.button.getBoundingClientRect();
        if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) return false;
        press = { id: e.pointerId, button: target.button, x: e.clientX, y: e.clientY };
        return true;
      });
    }, { passive: true });
    document.addEventListener('pointermove', function (e) {
      if (press && e.pointerId === press.id && moved(e)) cancelPress();
    }, { passive: true });
    document.addEventListener('pointercancel', cancelPress, { passive: true });
    document.addEventListener('scroll', cancelPress, { passive: true, capture: true });
    document.addEventListener('contextmenu', cancelPress, { passive: true });
    window.addEventListener('blur', cancelPress);
    document.addEventListener('click', function (e) {
      var hit = press;
      if (hit && moved(e)) hit = null;
      cancelPress();
      if (!hit || e.detail === 0 || (e.pointerId != null && e.pointerId !== hit.id)
          || e.defaultPrevented || !e.target.closest || e.target.closest(protectedArea)) return;
      catchTarget(hit.button);
    }, { passive: true });
    function visibility() {
      cancelPress();
      layer.classList.toggle('paused', document.hidden);
    }
    document.addEventListener('visibilitychange', visibility);
    visibility();
    return function (show) {
      cancelPress();
      if (!show) {
        layer.remove();
        return;
      }
      var seasonal = guard.holiday(key);
      if (seasonal) layer.removeAttribute('aria-hidden');
      else layer.setAttribute('aria-hidden', 'true');
      targets.forEach(function (target) {
        target.button.disabled = !seasonal || !!guard.finds()[key];
      });
      if (!layer.isConnected) document.body.appendChild(layer);
    };
  }

  function sakura() {
    return scene('sakura', 'Sakura', 'Złap płatek sakury', small ? 12 : 20, null, function (mover, button, i, count) {
      var duration = between(9, 16);
      mover.classList.add('sakura-fall');
      mover.style.left = ((i + Math.random()) * 100 / count).toFixed(2) + '%';
      variable(mover, 'duration', duration.toFixed(2) + 's');
      variable(mover, 'delay', (-((i * 7) % count + 0.5) * duration / count).toFixed(2) + 's');
      variable(mover, 'rest-top', between(12, 84).toFixed(2) + 'vh');
      variable(mover, 'size', between(13, 23).toFixed(2) + 'px');
      variable(mover, 'color', ['#efb2c9', '#e5a0b9', '#f4ced9'][i % 3]);
      variable(mover, 'opacity', between(0.35, 0.6).toFixed(2));
      // The fall leans with the wind; sway, flutter and turn keep their own
      // times, so the motions never line up into one pendulum.
      variable(mover, 'lean', between(-90, 90).toFixed(0) + 'px');
      variable(mover, 'sway', between(3.4, 5.8).toFixed(2) + 's');
      variable(mover, 'sway-amp', between(22, 40).toFixed(2) + 'px');
      variable(mover, 'flutter', between(1.5, 2.6).toFixed(2) + 's');
      variable(mover, 'flutter-amp', between(3, 7).toFixed(2) + 'px');
      variable(mover, 'flutter-delay', (-between(0, 17)).toFixed(2) + 's');
      variable(mover, 'turn', between(3, 5.4).toFixed(2) + 's');
      variable(mover, 'turn-delay', (-between(0, 37)).toFixed(2) + 's');
      var drift = document.createElement('div');
      drift.className = 'sakura-drift';
      button.classList.add('sakura-petal');
      button.innerHTML = PETAL;
      drift.appendChild(button);
      mover.appendChild(drift);
    });
  }

  function perseids() {
    return scene('perseids', 'Perseidy', 'Złap kometę', small ? 2 : 3, function (layer) {
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
  }

  // Lazily create each scene once; profile previews reuse its particles and
  // listeners. CSS declares which background belongs to the active skin.
  var scenes = { sakura: { create: sakura }, perseids: { create: perseids } };
  function update() {
    var skin = getComputedStyle(document.documentElement).getPropertyValue('--season-background').trim();
    var visible = false;
    Object.keys(scenes).forEach(function (key) {
      var show = guard.holiday(key) || skin === key;
      var entry = scenes[key];
      if (show && !entry.render) entry.render = entry.create();
      if (entry.render) entry.render(show);
      if (show) visible = true;
    });
    document.body.classList.toggle('seasonal-background', visible);
  }
  new MutationObserver(update).observe(document.documentElement, { attributes: true, attributeFilter: ['data-skin'] });
  update();
})();
