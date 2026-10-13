// Reversible home translation. Source values belong to nodes, so fresh status
// and game text replaces old source text. WeakMap releases removed DOM nodes.
window.SanakanHomeLocale = (function () {
  var attributes = ['title', 'aria-label', 'alt', 'placeholder'];
  var originals = new WeakMap();
  var active = null;
  var baseLang = document.documentElement.lang;
  var baseTitle = document.title;
  function value(node, key, current, write, restore) {
    var saved = originals.get(node) || {};
    var before = saved[key];
    if (restore) {
      if (before && current === before.translated) write(before.source);
      return;
    }
    var source = before && current === before.translated ? before.source : current;
    var translated = active.translate(source);
    saved[key] = { source: source, translated: translated };
    originals.set(node, saved);
    if (translated !== current) write(translated);
  }
  function localize(node, restore) {
    var parent = node.nodeType === 1 ? node : node.parentElement;
    if (!parent || parent.closest('script, style, textarea, .account-name, .ns-cmd')) return;
    if (node.nodeType === 3) {
      value(node, 'text', node.data, function (text) { node.data = text; }, restore);
    } else if (node.nodeType === 1) {
      attributes.forEach(function (attr) {
        if (node.hasAttribute(attr)) value(node, attr, node.getAttribute(attr), function (text) { node.setAttribute(attr, text); }, restore);
      });
      Array.prototype.forEach.call(node.childNodes, function (child) { localize(child, restore); });
    }
  }
  var observer = new MutationObserver(function (changes) {
    changes.forEach(function (change) {
      if (change.type === 'childList') change.addedNodes.forEach(function (node) { localize(node, false); });
      else localize(change.target, false);
    });
  });
  function set(language) {
    if (language === active) return;
    observer.disconnect();
    if (active) {
      localize(document.body, true);
      if (active.restore) active.restore();
    }
    originals = new WeakMap();
    active = language;
    document.documentElement.lang = active ? active.lang : baseLang;
    document.title = active ? active.title : baseTitle;
    if (active) {
      window.SanakanHomeLanguage = { text: active.translate };
      if (active.decorate) active.decorate();
      localize(document.body, false);
      observer.observe(document.body, { childList: true, subtree: true, characterData: true,
        attributes: true, attributeFilter: attributes });
    } else delete window.SanakanHomeLanguage;
    document.dispatchEvent(new CustomEvent('sanakan:home-language'));
  }
  return { set: set, language: function () { return active ? active.lang : ''; } };
})();
