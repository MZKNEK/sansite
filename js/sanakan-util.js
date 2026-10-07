// Small helpers shared by the pages' scripts (js/explorer.js, js/explorer-admin.js,
// api/search.js, cmd/search.js). Loaded before them, so they can use
// window.SanakanUtil; kept out of the pages themselves so the security policy can
// allow only the site's own script files (server/nginx).
window.SanakanUtil = (function () {
  // Polish plural: 1 element, 2-4 elementy, 5+ elementów (but 12-14 elementów)
  function plural(n, one, few, many) {
    if (n === 1) return one;
    var last = n % 10, lastTwo = n % 100;
    return last >= 2 && last <= 4 && (lastTwo < 12 || lastTwo > 14) ? few : many;
  }

  return {
    plural: plural,
    countLabel: function (n) {
      return n + ' ' + plural(n, 'element', 'elementy', 'elementów');
    }
  };
})();
