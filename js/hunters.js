// The ranking of the hunters (hunters/): a secret someone found opens in a
// dialog over the page, with everyone who found it and when, so the grid of
// the secrets stays as it is. Without this script it opens in its own card
// (the <details> of hunters/index.php).
(function () {
  var secrets = document.querySelectorAll('.secrets details');
  if (!secrets.length || !window.HTMLDialogElement) return;

  var dialog = document.createElement('dialog');
  dialog.className = 'ex-dialog secret-dialog';
  dialog.innerHTML = '<div class="secret-dialog-head"><h2></h2>'
    + '<button type="button" class="secret-dialog-close" title="Zamknij (Esc)" aria-label="Zamknij">×</button></div>'
    + '<p></p><div class="secret-dialog-list"></div>';
  document.body.appendChild(dialog);
  var title = dialog.querySelector('h2');
  var about = dialog.querySelector('p');
  var list = dialog.querySelector('.secret-dialog-list');
  var opener = null;

  function open(details) {
    var summary = details.querySelector('summary');
    title.textContent = summary.querySelector('b').textContent;
    about.textContent = summary.querySelector('span').textContent.replace(/\s+/g, ' ').trim();
    list.replaceChildren(details.querySelector('.secret-hunters').cloneNode(true));
    opener = summary;
    dialog.showModal();
    list.scrollTop = 0;
  }

  Array.prototype.forEach.call(secrets, function (details) {
    details.querySelector('summary').addEventListener('click', function (e) {
      e.preventDefault();
      open(details);
    });
  });

  dialog.querySelector('.secret-dialog-close').addEventListener('click', function () { dialog.close(); });
  // a click on the backdrop, outside the dialog's box, closes it
  dialog.addEventListener('click', function (e) {
    if (e.target !== dialog) return;
    var r = dialog.getBoundingClientRect();
    if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) dialog.close();
  });
  dialog.addEventListener('close', function () {
    if (opener) opener.focus();
  });
})();
