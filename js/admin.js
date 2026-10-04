// Admin panel: buttons and forms with data-action send that action to the
// panel and reload the page with the result (messages come from explorer.js)
(function () {
  var csrf = document.body.dataset.csrf;
  var gallery = window.SanakanGallery;

  function send(fields, controls) {
    var form = new FormData();
    form.append('csrf', csrf);
    Object.keys(fields).forEach(function (key) {
      form.append(key, fields[key]);
    });

    controls.forEach(function (control) { control.disabled = true; });

    fetch('index.php', { method: 'POST', body: form }).then(function (res) {
      return res.json();
    }).then(function (result) {
      if (result.ok) {
        gallery.flashAfterReload(result.message);
        location.reload();
      } else {
        gallery.toast(result.message, true);
        controls.forEach(function (control) { control.disabled = false; });
      }
    }).catch(function () {
      gallery.toast('Serwer nie odpowiedział poprawnie.', true);
      controls.forEach(function (control) { control.disabled = false; });
    });
  }

  // buttons: the action is in data-action, its fields in the other data-* attributes
  // (data-confirm asks first)
  document.querySelectorAll('button[data-action]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (button.dataset.confirm && !window.confirm(button.dataset.confirm)) return;

      var fields = {};
      Object.keys(button.dataset).forEach(function (key) {
        if (key !== 'confirm') fields[key] = button.dataset[key];
      });
      send(fields, [button]);
    });
  });

  // forms: the action is in data-action, the fields are the inputs
  document.querySelectorAll('form[data-action]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var fields = { action: form.dataset.action };
      Array.prototype.forEach.call(form.elements, function (field) {
        if (field.name) fields[field.name] = field.value.trim();
      });
      send(fields, Array.prototype.slice.call(form.querySelectorAll('button')));
    });
  });
})();
