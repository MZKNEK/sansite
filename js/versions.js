// state/wersje/: the arrow keys step to the previous (older) and the next
// (newer) version on a version's page, and on the list the search box shows
// only the versions whose number or changes have every word typed, opening
// the series they are in, and the older line (1.3, 1.2, ...) the series is
// folded into. "/" goes to the search box.
(function () {
  function typing(t) {
    return t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName));
  }

  document.addEventListener('keydown', function (e) {
    if (e.ctrlKey || e.metaKey || e.altKey || e.shiftKey || typing(e.target)) return;
    var rel = e.key === 'ArrowLeft' ? 'prev' : e.key === 'ArrowRight' ? 'next' : null;
    var link = rel && document.querySelector('link[rel="' + rel + '"]');
    if (link) {
      e.preventDefault();
      location.href = link.href;
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('version-search');
    if (!input) return;
    var info = document.getElementById('version-search-info');
    var none = document.getElementById('version-search-none');
    var groups = [].slice.call(document.querySelectorAll('.version-group'));
    var lines = [].slice.call(document.querySelectorAll('.version-line'));
    // which series and lines were open before a search, to put back when it is cleared
    var opened = groups.map(function (group) { return group.open; });
    var openedLines = lines.map(function (line) { return line.open; });
    var timer = null;

    function plural(n, one, few, many) {
      if (n === 1) return one;
      var last = n % 10, lastTwo = n % 100;
      return last >= 2 && last <= 4 && (lastTwo < 12 || lastTwo > 14) ? few : many;
    }

    function filter() {
      var words = input.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
      var found = 0;
      groups.forEach(function (group, i) {
        var shown = 0;
        [].forEach.call(group.querySelectorAll('.version-entry'), function (entry) {
          var text = entry.getAttribute('data-q') || '';
          var match = words.every(function (word) { return text.indexOf(word) !== -1; });
          entry.hidden = !match;
          if (match) shown++;
        });
        group.hidden = shown === 0;
        group.open = words.length ? shown > 0 : opened[i];
        found += shown;
      });
      lines.forEach(function (line, i) {
        var shown = line.querySelector('.version-group:not([hidden])') !== null;
        line.hidden = !shown;
        line.open = words.length ? shown : openedLines[i];
      });
      info.textContent = words.length ? found + ' ' + plural(found, 'wersja pasuje', 'wersje pasują', 'wersji pasuje') : '';
      none.hidden = !words.length || found > 0;
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(filter, 120);
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && input.value) {
        input.value = '';
        filter();
      }
    });
    // a series or a line opened or closed by hand stays so after the search is cleared
    groups.forEach(function (group, i) {
      group.addEventListener('toggle', function () {
        if (!input.value.trim()) opened[i] = group.open;
      });
    });
    lines.forEach(function (line, i) {
      line.addEventListener('toggle', function () {
        if (!input.value.trim()) openedLines[i] = line.open;
      });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey || typing(e.target)) return;
      e.preventDefault();
      input.focus();
    });
  });
})();
