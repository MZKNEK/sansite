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
    function update() {
      var option = field.options[field.selectedIndex];
      var speaks = !field.value || option.getAttribute('data-skin-words') === 'true';
      label.hidden = !speaks;
      input.disabled = !speaks;
      marker.value = speaks ? 'on' : 'off';
    }
    field.addEventListener('change', function () {
      SanakanAppearance.preview(field.value);
      update();
    });
    update();
  });
})();
