// Seasonal backgrounds and skin decorations (snow shares safeguard.js's layer).
// Decorations never cover controls. A pointer
// click on empty space may catch a visible petal/comet/firefly; one is also reachable
// by keyboard. The server witnesses the find before anything is remembered.
(function () {
  var guard = window.SanakanSafeguard;
  if (!guard) return;
  var small = innerWidth < 600;
  var protectedArea = 'a, button, input, select, textarea, [contenteditable], [role="button"], [role="dialog"], '
    + '.logo-wrap, .card, .notice-bar, .test-bar, .account-menu, .sg-toast, .discovery-prompt, .discovery-settings, .sg-silicon, .ns-overlay, .pl-overlay, .pp-overlay, article.cmd';
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
      if (pending || !guard.holiday(key)) return;
      // a catch already in the register answers too, rather than nothing
      if (guard.finds()[key]) {
        guard.notice(name, 'Ten wpis jest już w rejestrze', 'Wpis sezonowy złapany wcześniej.', null);
        return;
      }
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
    var headings = [];
    var render = scene('perseids', 'Perseidy', 'Złap kometę', small ? 2 : 3, function (layer) {
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
      // Every comet leaves the same radiant off the top-left corner: the higher
      // and the further right one is born, the more it leans down-right, so the
      // trails all point back to that corner. Each keeps its own speed, trail
      // length, brightness and how far it falls, so they neither move nor
      // vanish together; the lean stays inside what keeps the streak on screen.
      var x = between(0, 52);
      var from = between(-38, -8), to = between(78, 118);
      var spread = between(0.85, 1);
      function heading() {
        var tilt = Math.atan2((x + 6) / 100 * innerWidth, 0.55 * innerHeight) * 180 / Math.PI;
        var room = (100 - x) / 100 * innerWidth - 30;
        var maxLean = Math.asin(Math.min(1, room / (to / 100 * innerHeight))) * 180 / Math.PI;
        variable(mover, 'angle', (-Math.min(tilt, maxLean) * spread).toFixed(1) + 'deg');
      }
      heading();
      headings.push(heading);
      var duration = (to - from) / between(9, 20);
      mover.classList.add('perseid-flight');
      mover.style.left = x.toFixed(2) + '%';
      variable(mover, 'duration', duration.toFixed(2) + 's');
      variable(mover, 'delay', (-((i + 0.5 + between(-0.15, 0.15)) / count) * duration).toFixed(2) + 's');
      variable(mover, 'from', from.toFixed(1) + 'vh');
      variable(mover, 'to', to.toFixed(1) + 'vh');
      variable(mover, 'tail', between(55, 165).toFixed(0) + 'px');
      variable(mover, 'bright', between(0.55, 0.95).toFixed(2));
      variable(mover, 'head', between(3.5, 6).toFixed(2) + 'px');
      variable(mover, 'rest-top', (20 + i * 24) + 'vh');
      button.classList.add('perseid-comet');
      mover.appendChild(button);
    });
    // The vh/% positions follow the viewport; keep the heading and edge limit
    // in sync too, preserving each meteor's random choices and animation phase.
    window.addEventListener('resize', function () {
      headings.forEach(function (heading) { heading(); });
    });
    return render;
  }

  function fireflies() {
    return scene('fireflies', 'Noc świetlików', 'Złap świetlika', small ? 8 : 14, null, function (mover, button, i, count) {
      mover.classList.add('firefly-wander');
      mover.style.left = ((i + 0.5) * 88 / count + 4).toFixed(2) + '%';
      variable(mover, 'rest-top', between(18, 84).toFixed(2) + 'vh');
      variable(mover, 'duration', between(9, 17).toFixed(2) + 's');
      variable(mover, 'delay', (-between(0, 17)).toFixed(2) + 's');
      variable(mover, 'wind', between(-24, 24).toFixed(2) + 'px');
      variable(mover, 'rise', between(12, 30).toFixed(2) + 'px');
      variable(mover, 'pulse', between(2.3, 4.8).toFixed(2) + 's');
      variable(mover, 'pulse-delay', (-between(0, 5)).toFixed(2) + 's');
      variable(mover, 'size', between(5, 8).toFixed(2) + 'px');
      variable(mover, 'color', i % 2 ? 'var(--firefly-peach)' : 'var(--firefly-mint)');
      button.classList.add('firefly-catch');
      button.innerHTML = '<span class="firefly-body" aria-hidden="true"><i class="firefly-wings"></i><i class="firefly-light"></i></span>';
      mover.appendChild(button);
    });
  }

  // Decorative skins have no targets or finds; their effects stay behind the
  // content, reusing the same layer when a profile preview is cancelled.
  function decoration(key, count, particle, decorate) {
    var layer = document.createElement('div');
    layer.className = 'sg-season-background sg-' + key;
    layer.setAttribute('aria-hidden', 'true');
    for (var i = 0; i < count; i++) {
      var bit = document.createElement('i');
      particle(bit, i, count);
      layer.appendChild(bit);
    }
    if (decorate) decorate(layer);
    function visibility() { layer.classList.toggle('paused', document.hidden); }
    document.addEventListener('visibilitychange', visibility);
    visibility();
    return function (show) {
      if (show && !layer.isConnected) document.body.appendChild(layer);
      else if (!show) layer.remove();
    };
  }

  function ink() {
    return decoration('ink', small ? 5 : 9, function (bit, i, count) {
      bit.className = 'ink-spill';
      bit.style.left = ((i + 0.5) * 90 / count + 5).toFixed(2) + '%';
      bit.style.top = between(16, 88).toFixed(2) + '%';
      variable(bit, 'size', between(65, 145).toFixed(0) + 'px');
      variable(bit, 'angle', between(-35, 35).toFixed(0) + 'deg');
      var duration = between(14, 23);
      variable(bit, 'duration', duration.toFixed(2) + 's');
      variable(bit, 'delay', (-duration * (i + 0.5) / count).toFixed(2) + 's');
    });
  }

  // Raiden Shogun: a violet storm of the Electro Archon. Long bolts of
  // different strengths fall from darkened clouds through faint rain, each
  // along its own random path with branches; a strike flickers twice and
  // lights the sky around it. Strikes stay rare and dim, never a strobe.
  function lightning() {
    // a path down a 40 x 100 box from (x, y), drifting sideways with momentum
    function path(x, y, steps, height, spread) {
      var points = [[x, y]], drift = 0;
      for (var s = 0; s < steps && y < 100; s++) {
        drift = drift * 0.45 + between(-spread, spread);
        x = Math.max(2, Math.min(38, x + drift));
        y = Math.min(100, y + height / steps * between(0.6, 1.4));
        points.push([x, y]);
      }
      return points;
    }
    function line(points) {
      return 'M' + points.map(function (p) { return p[0].toFixed(1) + ' ' + p[1].toFixed(1); }).join('L');
    }
    return decoration('lightning', small ? 3 : 5, function (bit, i, count) {
      bit.className = 'lightning-bolt';
      bit.style.left = ((i + Math.random() * 0.8) * 92 / count + 2).toFixed(2) + '%';
      bit.style.top = between(-6, 3).toFixed(2) + '%';
      // usually long, now and then a shorter one that dies out mid-air
      variable(bit, 'length', (Math.random() < 0.75 ? between(62, 96) : between(35, 55)).toFixed(1) + 'vh');
      variable(bit, 'width', between(0.9, 3.4).toFixed(2) + 'px');
      var duration = between(6, 13);
      variable(bit, 'duration', duration.toFixed(2) + 's');
      variable(bit, 'delay', (-between(0, duration)).toFixed(2) + 's');
      var main = path(between(14, 26), 0, Math.round(between(14, 22)), 100, 4.5);
      var svg = '<svg viewBox="0 0 40 100" preserveAspectRatio="none" aria-hidden="true">'
        + '<path class="lightning-glow" d="' + line(main) + '"/><path class="lightning-core" d="' + line(main) + '"/>';
      var branches = Math.round(between(2, 4));
      for (var b = 0; b < branches; b++) {
        var from = main[1 + Math.floor(Math.random() * Math.max(1, Math.floor(main.length * 0.7) - 1))];
        var side = Math.random() < 0.5 ? -1 : 1;
        var arm = path(from[0], from[1], Math.round(between(3, 7)), between(14, 34), 3);
        // lean the branch away from the main channel
        arm.forEach(function (p, n) { p[0] = Math.max(1, Math.min(39, p[0] + side * n * between(1.2, 2.6))); });
        svg += '<path class="lightning-branch" d="' + line(arm) + '"/>';
        if (Math.random() < 0.4 && arm.length > 2) {
          var twig = path(arm[2][0], arm[2][1], 3, between(8, 14), 2.5);
          twig.forEach(function (p, n) { p[0] = Math.max(1, Math.min(39, p[0] + side * n * 1.5)); });
          svg += '<path class="lightning-branch lightning-twig" d="' + line(twig) + '"/>';
        }
      }
      bit.innerHTML = svg + '</svg>';
    });
  }

  function wired() {
    return decoration('wired', small ? 7 : 12, function (bit, i, count) {
      bit.className = 'wired-line';
      bit.style.left = between(-12, 55).toFixed(2) + '%';
      bit.style.top = ((i + Math.random()) * 100 / count).toFixed(2) + '%';
      variable(bit, 'size', between(25, 65).toFixed(2) + 'vw');
      variable(bit, 'branch', between(18, 90).toFixed(0) + 'px');
      var duration = between(9, 17);
      variable(bit, 'duration', duration.toFixed(2) + 's');
      variable(bit, 'delay', (-between(0, duration)).toFixed(2) + 's');
    });
  }

  function butterflies() {
    return decoration('butterflies', small ? 4 : 7, function (bit, i, count) {
      bit.className = 'witch-butterfly';
      bit.style.left = ((i + 0.5) * 84 / count + 8).toFixed(2) + '%';
      bit.style.top = between(20, 84).toFixed(2) + '%';
      variable(bit, 'size', between(22, 37).toFixed(0) + 'px');
      variable(bit, 'wind', between(-65, 65).toFixed(0) + 'px');
      variable(bit, 'flutter', between(0.7, 1.3).toFixed(2) + 's');
      var duration = between(13, 23);
      variable(bit, 'duration', duration.toFixed(2) + 's');
      variable(bit, 'delay', (-duration * (i + 0.5) / count).toFixed(2) + 's');
      bit.innerHTML = '<svg viewBox="0 0 40 34" aria-hidden="true">'
        + '<g class="butterfly-wing"><path d="M20 18C18 9 10 1 5 3C-1 8 6 20 18 19C7 13 3 21 8 27C13 34 20 25 20 18Z"/></g>'
        + '<g class="butterfly-wing"><path d="M20 18C22 9 30 1 35 3C41 8 34 20 22 19C33 13 37 21 32 27C27 34 20 25 20 18Z"/></g>'
        + '<path class="butterfly-body" d="M20 12v14m0-14-3-4m3 4 3-4"/></svg>';
    });
  }

  function magic() {
    return decoration('magic', small ? 14 : 24, function (bit, i, count) {
      var star = i % 4 === 0;
      bit.className = star ? 'magic-star' : 'magic-dust';
      bit.style.left = ((i + Math.random()) * 100 / count).toFixed(2) + '%';
      bit.style.top = between(12, 92).toFixed(2) + '%';
      variable(bit, 'size', between(star ? 11 : 1.5, star ? 17 : 3).toFixed(2) + 'px');
      variable(bit, 'angle', between(-35, -12).toFixed(0) + 'deg');
      variable(bit, 'tail', between(24, 48).toFixed(0) + 'px');
      var duration = between(10, 19);
      variable(bit, 'duration', duration.toFixed(2) + 's');
      variable(bit, 'delay', (-between(0, duration)).toFixed(2) + 's');
      if (star) bit.textContent = i % 8 === 0 ? '✧' : '✦';
    });
  }

  function lavender() {
    return decoration('lavender', small ? 12 : 20, function (bit, i, count) {
      bit.className = 'lavender-mote';
      bit.style.left = ((i + Math.random()) * 94 / count + 3).toFixed(2) + '%';
      bit.style.top = between(12, 92).toFixed(2) + '%';
      variable(bit, 'size', between(1.8, 4.2).toFixed(2) + 'px');
      variable(bit, 'wind', between(-32, 32).toFixed(0) + 'px');
      var duration = between(12, 24);
      variable(bit, 'duration', duration.toFixed(2) + 's');
      variable(bit, 'delay', (-duration * (i + 0.5) / count).toFixed(2) + 's');
    });
  }

  // Hoshino Ai: an idol's stage. Beams of stage lights sweep slowly from the
  // top in the skin's colours, and star sparkles twinkle between them, a few
  // six-pointed like the stars in her eyes.
  function idol() {
    var beams = small ? 2 : 4, stars = small ? 10 : 18;
    var FOUR = '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 0C10.8 6.2 13.8 9.2 20 10C13.8 10.8 10.8 13.8 10 20C9.2 13.8 6.2 10.8 0 10C6.2 9.2 9.2 6.2 10 0Z"/></svg>';
    var SIX = '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 0L11.6 6.5L17.8 4.4L13.2 10L17.8 15.6L11.6 13.5L10 20L8.4 13.5L2.2 15.6L6.8 10L2.2 4.4L8.4 6.5Z"/></svg>';
    return decoration('idol', beams + stars, function (bit, i) {
      if (i < beams) {
        bit.className = 'idol-beam' + (i % 2 ? ' idol-beam-light' : '');
        bit.style.left = ((i + 0.5) * 100 / beams + between(-6, 6)).toFixed(2) + '%';
        variable(bit, 'spread', between(small ? 9 : 7, small ? 14 : 12).toFixed(1) + 'deg');
        variable(bit, 'swing', between(10, 22).toFixed(1) + 'deg');
        variable(bit, 'tilt', ((i + 0.5) / beams * 2 - 1) * -14 + between(-4, 4) + 'deg');
        var sweep = between(9, 16);
        variable(bit, 'duration', sweep.toFixed(2) + 's');
        variable(bit, 'delay', (-between(0, sweep)).toFixed(2) + 's');
        return;
      }
      var six = i % 5 === 0;
      bit.className = 'idol-star' + (six ? ' idol-star-eye' : '');
      bit.style.left = between(3, 97).toFixed(2) + '%';
      bit.style.top = between(6, 92).toFixed(2) + '%';
      variable(bit, 'size', between(six ? 14 : 7, six ? 22 : 15).toFixed(1) + 'px');
      var twinkle = between(2.6, 5.5);
      variable(bit, 'duration', twinkle.toFixed(2) + 's');
      variable(bit, 'delay', (-between(0, twinkle)).toFixed(2) + 's');
      bit.innerHTML = six ? SIX : FOUR;
    });
  }

  function rabbits() {
    return decoration('rabbits', small ? 2 : 4, function (bit, i, count) {
      bit.className = 'rabbit-run';
      bit.style.bottom = (14 + i % 2 * 17) + 'px';
      variable(bit, 'size', between(38, 50).toFixed(2) + 'px');
      variable(bit, 'direction', i % 2 ? -1 : 1);
      variable(bit, 'route', i % 2 ? 'reverse' : 'normal');
      variable(bit, 'hop', between(1, 1.4).toFixed(2) + 's');
      variable(bit, 'height', between(12, 21).toFixed(2) + 'px');
      variable(bit, 'rest-left', ((i + 0.5) * 84 / count + 8).toFixed(2) + '%');
      var duration = between(small ? 22 : 32, small ? 30 : 44);
      variable(bit, 'duration', duration.toFixed(2) + 's');
      variable(bit, 'delay', (-duration * (i + 0.5) / count).toFixed(2) + 's');
      // Keep facing separate from the hop so reverse runners land feet first.
      bit.innerHTML = '<span class="rabbit-facing"><svg viewBox="0 0 60 52" aria-hidden="true">'
        + '<g class="rabbit-coat"><circle cx="8" cy="33" r="5"/>'
        + '<ellipse cx="25" cy="35" rx="16" ry="12"/>'
        + '<ellipse cx="40" cy="13" rx="4" ry="12" transform="rotate(-12 40 13)"/>'
        + '<ellipse cx="48" cy="13" rx="3.6" ry="12" transform="rotate(10 48 13)"/>'
        + '<ellipse cx="44" cy="28" rx="11" ry="9"/>'
        + '<ellipse cx="28" cy="45" rx="12" ry="4"/>'
        + '<ellipse cx="46" cy="44" rx="7" ry="3"/></g>'
        + '<path class="rabbit-ear" d="m40 6 2 13m7-13-2 13"/>'
        + '<circle class="rabbit-eye" cx="49" cy="26" r="1.2"/></svg></span>';
    });
  }

  function netsphere() {
    var layer, width, height;
    function element(name, attributes) {
      var node = document.createElementNS('http://www.w3.org/2000/svg', name);
      Object.keys(attributes).forEach(function (key) { node.setAttribute(key, attributes[key]); });
      return node;
    }
    function build() {
      width = innerWidth;
      height = innerHeight;
      var cols = Math.max(3, Math.min(14, Math.ceil(width / (small ? 150 : 190))));
      var rows = Math.max(3, Math.min(9, Math.ceil(height / 180)));
      var points = [], edges = [];
      function position(point) { return point.x.toFixed(1) + ' ' + point.y.toFixed(1); }
      function connect(a, b) {
        points[a].links.push(b);
        points[b].links.push(a);
        edges.push('M' + position(points[a]) + 'L' + position(points[b]));
      }
      // A jittered grid gives the whole viewport a connected mesh, without
      // dense clusters or an expensive all-pairs search on large screens.
      for (var row = 0; row < rows; row++) {
        for (var col = 0; col < cols; col++) {
          var index = points.length;
          points.push({ x: (col + between(0.2, 0.8)) * width / cols,
            y: (row + between(0.2, 0.8)) * height / rows, links: [] });
          if (col) connect(index, index - 1);
          if (row) connect(index, index - cols);
          if (row && col && Math.random() < 0.35) connect(index, index - cols - 1);
        }
      }
      var svg = element('svg', { class: 'netsphere-mesh', viewBox: '0 0 ' + width + ' ' + height,
        'aria-hidden': 'true', preserveAspectRatio: 'none' });
      svg.appendChild(element('path', { class: 'netsphere-links', d: edges.join('') }));
      points.forEach(function (point) {
        svg.appendChild(element('circle', { class: 'netsphere-node', cx: point.x, cy: point.y, r: between(1.2, 1.8).toFixed(2) }));
      });
      var routes = small ? 2 : 3;
      for (var i = 0; i < routes; i++) {
        var current = Math.floor(Math.random() * points.length), previous = -1;
        var route = 'M' + position(points[current]);
        for (var step = 0; step < 10; step++) {
          var choices = points[current].links.filter(function (next) { return next !== previous; });
          var next = choices[Math.floor(Math.random() * choices.length)];
          previous = current;
          current = next;
          route += 'L' + position(points[current]);
        }
        var duration = between(22, 34);
        ['netsphere-trail', 'netsphere-signal'].forEach(function (name) {
          var packet = element('path', { class: name, d: route, pathLength: 1 });
          variable(packet, 'duration', duration.toFixed(2) + 's');
          variable(packet, 'delay', (-duration * (i + 0.5) / routes).toFixed(2) + 's');
          svg.appendChild(packet);
        });
      }
      layer.replaceChildren(svg);
    }
    var render = decoration('netsphere', 0, null, function (node) {
      layer = node;
      build();
    });
    window.addEventListener('resize', function () {
      if (layer.isConnected && (width !== innerWidth || height !== innerHeight)) build();
    });
    return function (show) {
      if (show && (width !== innerWidth || height !== innerHeight)) build();
      render(show);
    };
  }

  function emitter() {
    return decoration('emitter', small ? 9 : 17, function (bit, i, count) {
      var ember = i % 3 === 0;
      bit.className = ember ? 'emitter-ember' : 'emitter-dust';
      bit.style.left = ((i + Math.random()) * 90 / count + 5).toFixed(2) + '%';
      bit.style.top = between(15, 92).toFixed(2) + '%';
      variable(bit, 'size', between(1.5, ember ? 3.5 : 2.5).toFixed(2) + 'px');
      variable(bit, 'wind', between(-25, 25).toFixed(0) + 'px');
      var duration = between(12, 22);
      variable(bit, 'duration', duration.toFixed(2) + 's');
      variable(bit, 'delay', (-duration * (i + 0.5) / count).toFixed(2) + 's');
    });
  }

  function fireworks() {
    var layer = document.createElement('div');
    layer.className = 'sg-season-background sg-fireworks';
    layer.setAttribute('aria-hidden', 'true');
    var active = false, timer = null;
    var motion = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)');
    function stopTimer() {
      if (timer !== null) clearTimeout(timer);
      timer = null;
    }
    function clear() {
      stopTimer();
      layer.getAnimations({ subtree: true }).forEach(function (animation) { animation.cancel(); });
      layer.replaceChildren();
    }
    function schedule() { timer = setTimeout(launch, between(3200, 5200)); }
    function launch() {
      timer = null;
      if (!active || document.hidden || (motion && motion.matches)) return;
      guard.fireworks(1, null, { layer: layer, colour: '#e9c56b', small: small });
      schedule();
    }
    document.addEventListener('visibilitychange', function () {
      if (!active) return;
      stopTimer();
      layer.getAnimations({ subtree: true }).forEach(function (animation) {
        if (document.hidden) animation.pause();
        else animation.play();
      });
      if (!document.hidden && !(motion && motion.matches)) schedule();
    });
    if (motion) motion.addEventListener('change', function () {
      clear();
      if (active) launch();
    });
    return function (show) {
      if (!show) {
        active = false;
        clear();
        layer.remove();
      } else if (!active) {
        active = true;
        document.body.appendChild(layer);
        launch();
      }
    };
  }

  // The song of Teto and Miku (audio/<song>.webm in Opus, half the MP3's size
  // at the same quality, or audio/<song>.mp3 for a browser without Opus such as
  // an older Safari; kept out of git and sent by deploy.sh), one for the whole
  // page. Nothing is downloaded until its button
  // in the bottom left corner is pressed; it then loops at 40%, or at the
  // volume of the slider next to it (kept in this browser), routed through an
  // analyser for the equalizer. The tab remembers whether it played and where,
  // so the next page carries on, or at the first touch when the browser holds
  // sound back until then.
  var SONG_VOLUME = 0.4;
  var SONG_KEY = 'sanakan-song';
  var VOLUME_KEY = 'sanakan-song-volume';
  var songs = null;
  function songPlayer() {
    var Context = window.AudioContext || window.webkitAudioContext;
    var audio = new Audio();
    var type = audio.canPlayType('audio/webm; codecs="opus"') ? '.webm' : '.mp3';
    audio.preload = 'none';
    audio.loop = true;
    var volume = SONG_VOLUME;
    try {
      var kept = parseFloat(localStorage.getItem(VOLUME_KEY));
      if (kept >= 0 && kept <= 1) volume = kept;
    } catch (_) { /* the default volume */ }
    audio.volume = volume;
    var context = null, analyser = null, gain = null, data = null;
    var song = '', name = '', wanted = false, waiting = false, listeners = [];
    var control = document.createElement('div');
    control.className = 'music-control';
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'music-toggle hud-corners';
    button.innerHTML = '<svg class="music-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>'
      + '<svg class="music-pause" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14M16 5v14"/></svg>';
    var slider = document.createElement('input');
    slider.type = 'range';
    slider.className = 'music-volume';
    slider.min = '0';
    slider.max = '100';
    slider.step = '5';
    slider.value = String(Math.round(volume * 100));
    slider.setAttribute('aria-label', 'Głośność piosenki');
    control.appendChild(button);
    control.appendChild(slider);

    function recalled() {
      try {
        var value = JSON.parse(sessionStorage.getItem(SONG_KEY) || 'null');
        return value && typeof value === 'object' ? value : null;
      } catch (_) { return null; }
    }
    function remember() {
      if (!song) return;
      try {
        sessionStorage.setItem(SONG_KEY, JSON.stringify({ song: song, on: wanted, time: audio.currentTime || 0 }));
      } catch (_) { /* the next page starts the song from the top */ }
    }
    // a song that failed to load may still read as not paused
    function playing() {
      return !audio.paused && !audio.error && !button.disabled;
    }
    function paint() {
      var on = playing();
      button.setAttribute('aria-pressed', on ? 'true' : 'false');
      button.setAttribute('aria-label', button.disabled ? 'Nie udało się wczytać piosenki ' + name
        : on ? 'Zatrzymaj piosenkę ' + name
        : waiting ? 'Wznów piosenkę ' + name + ' (przeglądarka czeka na kliknięcie)'
        : 'Włącz piosenkę ' + name);
      button.title = button.getAttribute('aria-label');
      control.classList.toggle('waiting', waiting && !on);
      slider.disabled = button.disabled;
      slider.title = 'Głośność ' + slider.value + '%';
      control.style.setProperty('--music-volume', slider.value + '%');
      if (media && control.isConnected) media.playbackState = on ? 'playing' : 'paused';
      listeners.forEach(function (listener) { listener(); });
    }
    // The analyser joins once the context runs: a suspended one would silence
    // the song. The element plays at full volume into it and the gain takes
    // the chosen volume, whether or not a browser applies the element's own.
    function connect() {
      if (analyser || !Context) return;
      try {
        if (!context) {
          context = new Context();
          context.addEventListener('statechange', connect);
        }
        if (context.state !== 'running') {
          context.resume().catch(function () {});
          return;
        }
        var source = context.createMediaElementSource(audio);
        gain = context.createGain();
        analyser = context.createAnalyser();
        analyser.fftSize = 2048;
        analyser.minDecibels = -90;
        analyser.maxDecibels = -20;
        analyser.smoothingTimeConstant = 0.7;
        gain.gain.value = volume;
        source.connect(analyser);
        analyser.connect(gain);
        gain.connect(context.destination);
        audio.volume = 1;
        data = new Uint8Array(analyser.frequencyBinCount);
        paint();
      } catch (_) { /* the song plays on its own, the bars sway idly */ }
    }
    // a browser holding sound back until the page is touched: the first touch
    // anywhere but the button (which starts the song itself) does it
    function touched(e) {
      if (e.target && e.target.closest && e.target.closest('.music-control')) return;
      untouched();
      if (wanted && song && !playing()) start();
    }
    function untouched() {
      if (!waiting) return;
      waiting = false;
      document.removeEventListener('pointerdown', touched, true);
      document.removeEventListener('keydown', touched, true);
      paint();
    }
    function start() {
      if (!song || button.disabled) return;
      wanted = true;
      connect();
      var playing = audio.play();
      if (playing && playing.then) playing.then(function () {
        untouched();
        connect();
      }, function (error) {
        if (!error || error.name !== 'NotAllowedError' || waiting) return;
        waiting = true;
        document.addEventListener('pointerdown', touched, true);
        document.addEventListener('keydown', touched, true);
        paint();
      });
    }
    function stop() {
      wanted = false;
      audio.pause();
    }
    button.addEventListener('click', function () {
      untouched();
      if (!playing()) start();
      else stop();
      remember();
    });
    // The lock screen, headphones and media keys: the song's name with the
    // skin's logo, and play and pause doing what the button does
    var media = navigator.mediaSession && window.MediaMetadata ? navigator.mediaSession : null;
    function described() {
      if (!media) return;
      media.metadata = new MediaMetadata({
        title: name,
        artist: 'Sanakan',
        album: 'sanakan.pl',
        artwork: [{ src: '/skins/' + song + '.webp', sizes: '1254x1254', type: 'image/webp' }]
      });
    }
    if (media) {
      [['play', function () { untouched(); start(); remember(); }],
        ['pause', function () { stop(); remember(); }],
        ['stop', function () { stop(); remember(); }]].forEach(function (action) {
        try { media.setActionHandler(action[0], action[1]); } catch (_) { /* an action this browser lacks */ }
      });
    }
    slider.addEventListener('input', function () {
      volume = Math.max(0, Math.min(100, Number(slider.value) || 0)) / 100;
      if (gain) gain.gain.value = volume;
      else audio.volume = volume;
      paint();
      try { localStorage.setItem(VOLUME_KEY, String(volume)); } catch (_) { /* this page only */ }
    });
    ['play', 'pause'].forEach(function (type) { audio.addEventListener(type, paint); });
    audio.addEventListener('error', function () {
      if (!audio.getAttribute('src')) return;
      wanted = false;
      button.disabled = true;
      paint();
    });
    window.addEventListener('pagehide', remember);
    document.addEventListener('visibilitychange', function () { if (document.hidden) remember(); });
    var last = recalled();
    wanted = !!(last && last.on);

    return {
      // the song of a skin, its button in the corner; a song carried over
      // from the last page continues where it was
      show: function (next, label) {
        if (next !== song) {
          audio.pause();
          song = next;
          name = label;
          button.disabled = false;
          audio.src = '/audio/' + next + type;
          if (last && last.song === next && last.time > 0) audio.currentTime = last.time;
          last = null;
          described();
          paint();
        }
        if (!control.isConnected) document.body.appendChild(control);
        if (wanted && audio.paused) start();
      },
      // a skin without this song: it stops, still wanted for when one returns
      // (another song's scene may have taken over already)
      hide: function (which) {
        if (which !== song) return;
        untouched();
        audio.pause();
        control.remove();
        if (media) {
          media.metadata = null;
          media.playbackState = 'none';
        }
      },
      // the analyser's spectrum while the song plays, else null
      spectrum: function () {
        if (!analyser || !playing()) return null;
        analyser.getByteFrequencyData(data);
        return data;
      },
      rate: function () { return context ? context.sampleRate : 44100; },
      bins: function () { return analyser ? analyser.fftSize : 2048; },
      listen: function (listener) { listeners.push(listener); }
    };
  }

  // Teto and Miku: an equalizer along the bottom edge, low notes in the middle
  // and high ones toward the sides, with peaks that fall slowly. It follows
  // the skin's song while it plays and sways idly otherwise.
  function equalizer(song, label) {
    if (!songs) songs = songPlayer();
    var half = small ? 12 : 24;
    var layer = document.createElement('div');
    layer.className = 'sg-season-background sg-equalizer sg-' + song + 'song';
    layer.setAttribute('aria-hidden', 'true');
    var bars = [], peaks = [], levels = new Float32Array(half), tops = new Float32Array(half);
    for (var i = 0; i < half * 2; i++) {
      var band = document.createElement('span');
      band.className = 'equalizer-band';
      var bar = document.createElement('i');
      bar.className = 'equalizer-bar';
      // the idle sway: a slow wave from the middle out, each bar a little apart
      var away = Math.abs(i - half + 0.5) / half;
      variable(bar, 'rest', (0.16 - away * 0.09).toFixed(3));
      variable(bar, 'duration', between(2.2, 3.4).toFixed(2) + 's');
      variable(bar, 'delay', (-away * 2.6 - between(0, 0.4)).toFixed(2) + 's');
      var peak = document.createElement('i');
      peak.className = 'equalizer-peak';
      band.appendChild(bar);
      band.appendChild(peak);
      layer.appendChild(band);
      bars.push(bar);
      peaks.push(peak);
    }
    var motion = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)');
    var edges = null, frame = 0;
    // bands on a logarithmic scale from 45 Hz to 15 kHz, at least a bin each
    function bandEdges() {
      var size = songs.bins(), rate = songs.rate(), list = [];
      for (var b = 0; b <= half; b++) {
        var bin = Math.round(45 * Math.pow(15000 / 45, b / half) * size / rate);
        list.push(Math.min(size / 2, b ? Math.max(bin, list[b - 1] + 1) : bin));
      }
      return list;
    }
    function rest() {
      layer.classList.remove('live');
      bars.forEach(function (bar, i) {
        bar.style.removeProperty('transform');
        peaks[i].style.removeProperty('transform');
      });
      levels.fill(0);
      tops.fill(0);
    }
    function tick() {
      frame = 0;
      var data = layer.isConnected && !(motion && motion.matches) ? songs.spectrum() : null;
      if (!data) {
        if (layer.classList.contains('live')) rest();
        return;
      }
      if (!edges) edges = bandEdges();
      layer.classList.add('live');
      for (var b = 0; b < half; b++) {
        var loudest = 0;
        for (var n = edges[b]; n < edges[b + 1]; n++) if (data[n] > loudest) loudest = data[n];
        // above a floor that sinks toward the treble, which carries less
        // energy and gets a lift too, so quiet passages fall and loud ones rise
        var floor = 0.42 - 0.22 * b / half;
        var level = Math.max(0, (loudest / 255 - floor) / (1 - floor));
        level = Math.min(1, Math.pow(level, 1.3) * (1 + 0.6 * b / half));
        levels[b] = level > levels[b] ? level : levels[b] + (level - levels[b]) * 0.3;
        tops[b] = Math.max(levels[b], tops[b] - 0.01);
        var scale = 'scaleY(' + Math.max(0.02, levels[b]).toFixed(3) + ')';
        var drop = 'translateY(' + ((1 - tops[b]) * 100).toFixed(1) + '%)';
        bars[half - 1 - b].style.transform = bars[half + b].style.transform = scale;
        peaks[half - 1 - b].style.transform = peaks[half + b].style.transform = drop;
      }
      frame = requestAnimationFrame(tick);
    }
    function wake() {
      if (!frame && layer.isConnected) frame = requestAnimationFrame(tick);
    }
    songs.listen(wake);
    if (motion) motion.addEventListener('change', wake);
    function visibility() { layer.classList.toggle('paused', document.hidden); }
    document.addEventListener('visibilitychange', visibility);
    visibility();
    return function (show) {
      if (!show) {
        if (!layer.isConnected) return;
        layer.remove();
        songs.hide(song);
        return;
      }
      if (!layer.isConnected) document.body.appendChild(layer);
      songs.show(song, label);
      wake();
    };
  }

  // Lazily create each scene once; profile previews reuse its particles and
  // listeners. CSS declares which background belongs to the active skin.
  var scenes = {
    sakura: { create: sakura, seasonal: true },
    perseids: { create: perseids, seasonal: true },
    fireflies: { create: fireflies, seasonal: true },
    ink: { create: ink },
    wired: { create: wired },
    lightning: { create: lightning },
    butterflies: { create: butterflies },
    magic: { create: magic },
    lavender: { create: lavender },
    idol: { create: idol },
    rabbits: { create: rabbits },
    netsphere: { create: netsphere },
    emitter: { create: emitter },
    fireworks: { create: fireworks },
    tetosong: { create: function () { return equalizer('teto', 'Teto'); } },
    mikusong: { create: function () { return equalizer('miku', 'Miku'); } }
  };
  function update() {
    var skin = getComputedStyle(document.documentElement).getPropertyValue('--season-background').trim();
    var enabled = !window.SanakanAppearance || SanakanAppearance.animations();
    var visible = false;
    Object.keys(scenes).forEach(function (key) {
      var entry = scenes[key];
      var show = (enabled && skin === key) || (!!entry.seasonal && guard.holiday(key));
      if (show && !entry.render) entry.render = entry.create();
      if (entry.render) entry.render(show);
      if (show) visible = true;
    });
    document.body.classList.toggle('seasonal-background', visible);
  }
  new MutationObserver(update).observe(document.documentElement, { attributes: true, attributeFilter: ['data-skin', 'data-skin-animations', 'data-skin-background'] });
  update();
})();
