// Profile previews. The words switch follows the selected option; its marker
// distinguishes an unchecked switch from one unavailable for this selection.
(function () {
  var root = document.documentElement;
  document.querySelectorAll('[data-hud-choice]').forEach(function (field) {
    field.addEventListener('change', function () { root.setAttribute('data-hud', field.value); });
  });
  document.querySelectorAll('[data-hud-color]').forEach(function (field) {
    var dot = document.querySelector('[data-hud-dot]');
    function paint() {
      var option = field.options[field.selectedIndex];
      if (dot && option && option.hasAttribute('data-hex')) dot.style.background = option.getAttribute('data-hex');
    }
    field.addEventListener('change', function () {
      var key = field.value === 'own' ? root.getAttribute('data-hud-own') : field.value;
      root.className = key ? 'role-' + key : '';
      paint();
    });
    paint();
  });
  document.querySelectorAll('[data-skin-choice]').forEach(function (field) {
    var label = field.form.querySelector('.skin-words');
    var input = label.querySelector('input');
    var marker = field.form.querySelector('[data-skin-words-present]');
    var editor = field.form.querySelector('[data-custom-editor]');
    var image = editor && editor.querySelector('[data-custom-image]');
    var logo = editor && editor.querySelector('[data-custom-logo]');
    var baseLogo = logo && new URL('../logo.webp', location.href).pathname;
    var panel = null;
    function design() {
      if (!editor) return null;
      var colors = {};
      editor.querySelectorAll('[data-custom-color]').forEach(function (color) {
        colors[color.getAttribute('data-custom-color')] = color.value;
      });
      var option = image.options[image.selectedIndex];
      return { colors: colors, image: option.getAttribute('data-image-url') || '' };
    }
    function preview() {
      var custom = design();
      SanakanAppearance.preview(field.value, custom);
      if (logo) logo.src = custom.image || baseLogo;
      if (panel) panel.refresh();
    }
    function update() {
      var option = field.options[field.selectedIndex];
      var speaks = !field.value || option.getAttribute('data-skin-words') === 'true';
      label.hidden = !speaks;
      input.disabled = !speaks;
      marker.value = speaks ? 'on' : 'off';
      if (editor) {
        editor.hidden = field.value !== 'custom';
        editor.disabled = editor.hidden;
      }
    }
    field.addEventListener('change', function () {
      preview();
      update();
    });
    if (editor) {
      image.addEventListener('change', preview);
      logo.addEventListener('error', function () { if (logo.getAttribute('src') !== baseLogo) logo.src = baseLogo; });
      editor.querySelectorAll('[data-custom-color]').forEach(function (color) {
        var swatch = editor.querySelector('[data-custom-swatch="' + color.getAttribute('data-custom-color') + '"]');
        color.addEventListener('input', function () {
          if (!/^#[0-9a-f]{6}$/i.test(color.value)) return;
          swatch.value = color.value;
          preview();
        });
        swatch.addEventListener('input', function () {
          color.value = swatch.value;
          preview();
        });
      });
    }
    update();
    if (window.SanakanSkinPicker) panel = SanakanSkinPicker(field, design);
  });
})();
