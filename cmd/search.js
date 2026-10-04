// copy buttons: the example, or the link to a command (data-anchor); the
// clipboard API needs https
(function () {
  if (!navigator.clipboard || !window.isSecureContext) return;

  document.querySelectorAll('.copy').forEach(function (button) {
    var label = button.textContent;
    button.hidden = false;
    button.addEventListener('click', function () {
      var text = button.dataset.anchor
        ? location.href.split('#')[0] + '#' + button.dataset.anchor
        : button.dataset.copy;
      navigator.clipboard.writeText(text).then(function () {
        button.textContent = 'Skopiowano';
        button.classList.add('done');
        clearTimeout(button.resetTimer);
        button.resetTimer = setTimeout(function () {
          button.textContent = label;
          button.classList.remove('done');
        }, 1500);
      });
    });
  });
})();

// search over names, aliases, descriptions, parameters and examples
(function () {
  var input = document.getElementById('cmd-search');
  if (!input) return;

  var toolbar = document.getElementById('toolbar');
  var info = document.getElementById('search-info');
  var noResults = document.getElementById('no-results');
  var modules = document.querySelectorAll('.module');

  var chips = {};
  document.querySelectorAll('.module-chips a').forEach(function (chip) {
    chips[chip.dataset.module] = chip;
  });

  // the copy button text stays out of the search text
  document.querySelectorAll('.cmd').forEach(function (cmd) {
    cmd.dataset.search = ['.cmd-name', '.cmd-body', '.cmd-example code'].map(function (selector) {
      return cmd.querySelector(selector).textContent;
    }).join(' ').replace(/\s+/g, ' ').toLowerCase();
  });

  // Polish plural: 1 polecenie, 2-4 polecenia, 5+ poleceń (but 12-14 poleceń)
  function plural(n, one, few, many) {
    if (n === 1) return one;
    var last = n % 10, lastTwo = n % 100;
    return last >= 2 && last <= 4 && (lastTwo < 12 || lastTwo > 14) ? few : many;
  }

  function filter() {
    var query = input.value.trim().toLowerCase();
    var found = 0;

    modules.forEach(function (module) {
      var moduleCount = 0;

      module.querySelectorAll('.submodule').forEach(function (sub) {
        var subCount = 0;
        sub.querySelectorAll('.cmd').forEach(function (cmd) {
          var match = !query || cmd.dataset.search.indexOf(query) !== -1;
          cmd.hidden = !match;
          if (match) subCount++;
        });
        sub.hidden = !subCount;
        moduleCount += subCount;
      });

      module.hidden = !moduleCount;
      var chip = chips[module.dataset.module];
      chip.hidden = !moduleCount;
      chip.querySelector('.chip-count').textContent = moduleCount;
      found += moduleCount;
    });

    noResults.hidden = found > 0;
    info.textContent = query ? 'Znaleziono ' + found + ' ' + plural(found, 'polecenie', 'polecenia', 'poleceń') : '';
    updateToolbarHeight();
  }

  // module anchors land below the sticky toolbar, whose height changes with the screen
  function updateToolbarHeight() {
    document.documentElement.style.setProperty('--toolbar-height', toolbar.offsetHeight + 'px');
  }

  input.addEventListener('input', filter);

  input.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      input.value = '';
      filter();
    }
  });

  // "/" jumps to the search, unless the user is already typing somewhere
  document.addEventListener('keydown', function (e) {
    var tag = document.activeElement && document.activeElement.tagName;
    if (e.key === '/' && tag !== 'INPUT' && tag !== 'TEXTAREA' && !e.ctrlKey && !e.metaKey && !e.altKey) {
      e.preventDefault();
      input.focus();
    }
  });

  // a link to a command (#daily): the search must not hide it, and the jump is
  // made again once the toolbar height is known, so it does not end up under it
  function showLinked() {
    var target = location.hash.length > 1 && document.getElementById(decodeURIComponent(location.hash.slice(1)));
    if (!target || !target.classList.contains('cmd')) return;
    if (target.hidden) {
      input.value = '';
      filter();
    }
    target.scrollIntoView();
  }

  window.addEventListener('resize', updateToolbarHeight);
  window.addEventListener('hashchange', showLinked);
  updateToolbarHeight();
  showLinked();
})();
