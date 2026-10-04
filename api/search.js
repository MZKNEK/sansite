// Search for the API docs. Swagger UI filters only by group name; this plugin
// replaces its filter, so the search covers methods, paths, descriptions and
// groups. The search box sets the filter through Swagger, which then renders
// only the matching endpoints, also after groups are collapsed and expanded.
window.SanakanApiSearch = (function () {
  // every word has to match, e.g. "get waifu" finds GET endpoints under /api/waifu
  function filterOps(taggedOps, phrase) {
    var words = phrase.toLowerCase().split(/\s+/).filter(Boolean);

    return taggedOps
      .map(function (tag, tagName) {
        return tag.set('operations', tag.get('operations').filter(function (op) {
          var text = [
            tagName,
            op.get('method'),
            op.get('path'),
            op.getIn(['operation', 'summary']) || '',
            op.getIn(['operation', 'description']) || ''
          ].join(' ').toLowerCase();

          return words.every(function (word) {
            return text.indexOf(word) !== -1;
          });
        }));
      })
      .filter(function (tag) {
        return tag.get('operations').size > 0;
      });
  }

  function plugin() {
    return {
      fn: {
        opsFilter: filterOps
      }
    };
  }

  // Polish plural: 1 endpoint, 2-4 endpointy, 5+ endpointów (but 12-14 endpointów)
  function plural(n, one, few, many) {
    if (n === 1) return one;
    var last = n % 10, lastTwo = n % 100;
    return last >= 2 && last <= 4 && (lastTwo < 12 || lastTwo > 14) ? few : many;
  }

  function bind(ui) {
    var input = document.getElementById('api-search');
    var info = document.getElementById('api-search-info');
    var noResults = document.getElementById('api-no-results');

    function update() {
      var phrase = input.value.trim();
      ui.layoutActions.updateFilter(phrase);

      if (!phrase) {
        info.textContent = '';
        noResults.hidden = true;
        return;
      }

      var found = 0;
      filterOps(ui.specSelectors.taggedOperations(), phrase).forEach(function (tag) {
        found += tag.get('operations').size;
      });

      info.textContent = 'Znaleziono ' + found + ' ' + plural(found, 'endpoint', 'endpointy', 'endpointów');
      noResults.hidden = found > 0;
    }

    input.addEventListener('input', update);

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        input.value = '';
        update();
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
  }

  // The search bar goes right below the API description. Swagger renders the
  // description, so the bar waits hidden until the docs are ready. Its new
  // parent holds all the docs, so the bar can stay at the top while scrolling.
  function mount() {
    var toolbar = document.getElementById('api-toolbar');
    var info = document.querySelector('.swagger-ui .information-container');
    if (info)
      info.parentNode.insertBefore(toolbar, info.nextSibling);
    toolbar.hidden = false;
  }

  return {
    plugin: plugin,
    bind: bind,
    mount: mount
  };
})();
