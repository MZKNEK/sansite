// The trash of an account's own gallery folder (i/?kosz=1): bringing something
// back or dropping it for good. The panel does the same for the whole gallery.
(function () {
  var list = document.getElementById('trash-list');
  if (!list) return;

  function post(fields) {
    return new Promise(function (resolve) {
      var form = new FormData();
      Object.keys(fields).forEach(function (key) {
        form.append(key, fields[key]);
      });

      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'index.php');
      xhr.onload = function () {
        try {
          resolve(JSON.parse(xhr.responseText));
        } catch (err) {
          resolve({ ok: false, message: 'Serwer odpowiedział błędem (' + xhr.status + ').' });
        }
      };
      xhr.onerror = function () {
        resolve({ ok: false, message: 'Brak połączenia z serwerem.' });
      };
      xhr.send(form);
    });
  }

  list.addEventListener('click', function (e) {
    var button = e.target.closest('button[data-action]');
    if (!button) return;
    if (button.dataset.confirm && !window.confirm(button.dataset.confirm)) return;

    button.disabled = true;
    post({ action: button.dataset.action, item: button.dataset.item, csrf: list.dataset.csrf }).then(function (result) {
      if (result.ok) {
        window.SanakanGallery.flashAfterReload(result.message, false);
        location.reload();
      } else {
        button.disabled = false;
        window.SanakanGallery.toast(result.message, true);
      }
    });
  });
})();
