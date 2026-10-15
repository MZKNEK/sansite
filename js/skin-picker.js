// Enhance the profile's native select with a keyboard-accessible modal picker.
// The select stays the form's source of truth and works without JavaScript.
window.SanakanSkinPicker = function (field, design) {
  var form = field.form;
  var dialog = form.querySelector('[data-skin-dialog]');
  if (!dialog || typeof dialog.showModal !== 'function') return null;
  var summary = form.querySelector('[data-skin-current]');
  var open = summary.querySelector('[data-skin-open]');
  var grid = dialog.querySelector('[data-skin-grid]');
  var tiles = Array.from(grid.querySelectorAll('.skin-tile'));
  var filters = Array.from(dialog.querySelectorAll('[data-skin-filter]'));
  var candidate = field.value;
  var filter = 'unlocked';
  var baseLogo = new URL('../img/logo.webp', location.href).pathname;
  var palettes = {};

  // Read the actual skin colours so swatches follow css/skins.css changes.
  // Several rules may set one palette (e.g. a later :root with event colours
  // only), so a rule fills in what it sets and never blanks the rest.
  Array.from(document.styleSheets).forEach(function (sheet) {
    if (!sheet.href || !/\/css\/skins\.css(?:\?|$)/.test(sheet.href)) return;
    try {
      Array.from(sheet.cssRules).forEach(function (rule) {
        var match = /^html\[data-skin="([a-z0-9-]+)"\]$/.exec(rule.selectorText || '');
        if (!match && rule.selectorText !== ':root') return;
        var id = match ? match[1] : '';
        var palette = palettes[id] = palettes[id] || [];
        ['--accent', '--bg-dark', '--text'].forEach(function (key, i) {
          var value = rule.style.getPropertyValue(key).trim();
          if (value) palette[i] = value;
        });
      });
    } catch (_) { /* Default swatches remain if the stylesheet is unavailable. */ }
  });
  function tileFor(id) {
    return tiles.find(function (tile) { return !tile.disabled && tile.getAttribute('data-skin-tile') === id; }) || tiles[0];
  }
  function imageFor(tile) {
    var custom = design();
    return tile.hasAttribute('data-skin-custom') ? custom && custom.image || '' : tile.getAttribute('data-skin-thumbnail');
  }
  function loadImage(img, src) {
    if (src && img.getAttribute('src') !== src) img.src = src;
  }
  function refresh() {
    var selected = tileFor(field.value);
    summary.querySelector('[data-skin-current-name]').textContent = selected.getAttribute('data-skin-name');
    summary.querySelector('[data-skin-current-description]').textContent = selected.getAttribute('data-skin-description');
    loadImage(summary.querySelector('img'), imageFor(selected) || baseLogo);
    tiles.forEach(function (tile) {
      var id = tile.getAttribute('data-skin-tile');
      var custom = id === 'custom' && design();
      var colors = custom ? [custom.colors.accent, custom.colors['bg-dark'], custom.colors.text]
        : palettes[id] || palettes[''] || [];
      ['--tile-accent', '--tile-bg', '--tile-text'].forEach(function (key, i) {
        if (/^#[0-9a-f]{6}$/i.test(colors[i])) tile.style.setProperty(key, colors[i]);
      });
      if (!tile.disabled && dialog.open) {
        var img = tile.querySelector('img');
        var src = imageFor(tile);
        if (img) {
          img.hidden = !src;
          loadImage(img, src);
        }
        var icon = tile.querySelector('[data-skin-custom-icon]');
        if (icon) icon.toggleAttribute('hidden', !!src);
      }
    });
  }
  function showSelection() {
    var selected = tileFor(candidate);
    tiles.forEach(function (tile) {
      tile.setAttribute('aria-pressed', String(tile === selected));
    });
    dialog.querySelector('[data-skin-selection-name]').textContent = selected.getAttribute('data-skin-name');
    dialog.querySelector('[data-skin-selection-description]').textContent = selected.getAttribute('data-skin-description');
  }
  function showFilter(value) {
    filter = value;
    filters.forEach(function (button) {
      button.setAttribute('aria-pressed', String(button.getAttribute('data-skin-filter') === value));
    });
    tiles.forEach(function (tile) {
      // Keep the named completion reward visible even before it is unlocked.
      tile.hidden = value === 'unlocked' && tile.disabled && !tile.hasAttribute('data-skin-custom');
    });
    var count = tiles.filter(function (tile) { return !tile.hidden; }).length;
    dialog.querySelector('[data-skin-count]').textContent = count + ' / ' + tiles.length;
  }
  open.addEventListener('click', function () {
    candidate = field.value;
    showFilter(filter);
    showSelection();
    dialog.showModal();
    document.body.classList.add('skin-picker-open');
    refresh();
    var selected = tileFor(candidate);
    selected.focus({ preventScroll: true });
    selected.scrollIntoView({ block: 'nearest' });
  });
  tiles.forEach(function (tile) {
    tile.addEventListener('click', function () {
      if (tile.disabled) return;
      candidate = tile.getAttribute('data-skin-tile');
      SanakanAppearance.preview(candidate, design());
      showSelection();
    });
  });
  filters.forEach(function (button) {
    button.addEventListener('click', function () { showFilter(button.getAttribute('data-skin-filter')); });
  });
  function restore() {
    document.body.classList.remove('skin-picker-open');
    SanakanAppearance.preview(field.value, design());
    refresh();
  }
  function close() {
    restore();
    dialog.close();
  }
  dialog.querySelectorAll('[data-skin-cancel]').forEach(function (button) {
    button.addEventListener('click', close);
  });
  dialog.querySelector('[data-skin-confirm]').addEventListener('click', function () {
    field.value = candidate;
    field.dispatchEvent(new Event('change', { bubbles: true }));
    close();
  });
  // Native Escape closes the dialog before its queued `close` event runs in
  // Chromium. Restore on `cancel` too, while the dismissal is being handled.
  dialog.addEventListener('cancel', restore);
  dialog.addEventListener('close', function () {
    if (!dialog.open) restore();
  });
  dialog.addEventListener('click', function (event) {
    var rect = dialog.getBoundingClientRect();
    if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right
      || event.clientY < rect.top || event.clientY > rect.bottom)) close();
  });
  grid.addEventListener('keydown', function (event) {
    var visible = tiles.filter(function (tile) { return !tile.hidden; });
    var index = visible.indexOf(document.activeElement);
    if (index < 0) return;
    var columns = getComputedStyle(grid).gridTemplateColumns.split(' ').length;
    var steps = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: columns, ArrowUp: -columns };
    var next = event.key === 'Home' ? 0 : event.key === 'End' ? visible.length - 1
      : Object.prototype.hasOwnProperty.call(steps, event.key) ? index + steps[event.key] : null;
    if (next === null) return;
    event.preventDefault();
    var step = event.key === 'Home' ? 1 : event.key === 'End' ? -1 : steps[event.key];
    while (next >= 0 && next < visible.length && visible[next].disabled) next += step;
    if (next >= 0 && next < visible.length) visible[next].focus();
  });
  summary.querySelector('img').addEventListener('error', function (event) {
    if (event.target.getAttribute('src') !== baseLogo) event.target.src = baseLogo;
  });
  form.querySelector('[data-skin-native]').hidden = true;
  summary.hidden = false;
  refresh();
  return { refresh: refresh };
};
