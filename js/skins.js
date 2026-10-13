// The skins of the home page (inc/auth/skins.php, css/skins.css): the colours,
// the logo and, if a skin brings any, the words it speaks. An account picks one
// in its profile (/account/), which keeps the choice in inc/data/skins.json and
// in the cookie "skin"; the colours go over the whole site (js/hud.js wears the
// skin from that cookie before the first paint, or the page itself carries it,
// skinHtmlAttribute()), while the logo and the words stay on the home page,
// where each skin defines itself with define() (js/skin-<id>.js): the colours
// are its own block in css/skins.css, the rest comes from here, and the skin
// may add its own pieces in decorate(). The account may also keep the words off
// (the cookie "skinwords"), wearing the colours and the logo alone, and a skin
// may bring no words at all (Hu Tao, Rin: colours and logo only, the page
// keeping its own). The profile previews the colours of the picked skin on
// itself ([data-skin-choice]).
window.SanakanSkins = (function () {
  var root = document.documentElement;
  var defs = {};
  // the skin worn now, '' for the site's own look; also set when the April
  // letter switches a skin on for the visit (js/skin-teto.js), and the language
  // that skin speaks, '' when it brings no words (Hu Tao, Rin) or the account
  // keeps them off
  var applied = '';
  var appliedLang = '';
  var observer = null;

  function cookie(name) {
    var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
  }

  // whether the worn skin speaks on the home page: the account's own choice,
  // which account.php keeps in the cookie "skinwords" and says in its answer
  var speaking = cookie('skinwords') !== 'off';

  // The words a skin speaks: the translator replaces the texts of the home
  // page as they arrive, in text nodes and in the attributes that read as
  // words. User names, typed commands and script sources are never translated.
  var ATTRIBUTES = ['title', 'aria-label', 'alt', 'placeholder'];
  function localize(node) {
    var parent = node.nodeType === 1 ? node : node.parentElement;
    if (!parent || parent.closest('script, style, textarea, .account-name, .ns-cmd')) return;
    if (node.nodeType === 3) {
      var text = window.SanakanHomeLanguage.text(node.data);
      if (text !== node.data) node.data = text;
    } else if (node.nodeType === 1) {
      ATTRIBUTES.forEach(function (attr) {
        if (!node.hasAttribute(attr)) return;
        var value = node.getAttribute(attr);
        var text = window.SanakanHomeLanguage.text(value);
        if (text !== value) node.setAttribute(attr, text);
      });
      Array.prototype.forEach.call(node.childNodes, localize);
    }
  }

  function words(translate) {
    window.SanakanHomeLanguage = { text: translate };
    localize(document.body);
    if (!observer) {
      // The home page builds its texts and status lines as it goes, so keep
      // translating what arrives while the skin is worn.
      observer = new MutationObserver(function (changes) {
        changes.forEach(function (change) {
          if (change.type === 'childList') change.addedNodes.forEach(localize);
          else localize(change.target);
        });
      });
    }
    observer.observe(document.body, { childList: true, subtree: true, characterData: true,
      attributes: true, attributeFilter: ATTRIBUTES });
    document.dispatchEvent(new CustomEvent('sanakan:home-language'));
  }

  // puts a skin on the page: the colours (css/skins.css) and the logo always,
  // the words of the skin when it speaks (its language, title, own pieces and
  // translator). Without words it only recolours and swaps the logo.
  function wear(skin) {
    applied = skin.id;
    appliedLang = '';
    root.setAttribute('data-skin', skin.id);
    var logo = document.querySelector('img.logo');
    if (logo && skin.logo) {
      logo.src = skin.logo.src;
      logo.alt = skin.logo.alt;
    }
    if (!speaking) return;
    if (skin.lang) {
      appliedLang = skin.lang;
      root.lang = skin.lang;
    }
    if (skin.title) document.title = skin.title;
    if (skin.decorate) skin.decorate();
    if (skin.translate && document.body.classList.contains('home')) words(skin.translate);
  }

  // what js/skin-<id>.js calls with its skin, on the home page;
  // the cookie's own skin is worn as soon as it defines itself
  function define(skin) {
    if (!skin || !skin.id) return;
    defs[skin.id] = skin;
    if (root.getAttribute('data-skin') === skin.id && applied !== skin.id) wear(skin);
  }

  // the skins a script asks to wear itself, e.g. April's letter for the visit
  // (js/skin-teto.js); the pick in the profile is the cookie's own way
  function show(id) {
    if (defs[id] && applied !== id) wear(defs[id]);
  }

  // the profile's picker: the colours come on the page as they are picked; the
  // logo and the words belong to the home page and wait there
  Array.prototype.forEach.call(document.querySelectorAll('[data-skin-choice]'), function (field) {
    field.addEventListener('change', function () {
      if (field.value) root.setAttribute('data-skin', field.value);
      else root.removeAttribute('data-skin');
    });
  });

  // a login on the page (js/account.js): the account's skin comes on as soon as
  // account.php says it, as the colour of the HUD does; taking it off waits for
  // the page account.php clears the cookie on (a find taken back)
  document.addEventListener('sanakan:account', function (e) {
    var me = e.detail || {};
    if ('skinwords' in me) speaking = me.skinwords !== false;
    if (me.skin && defs[me.skin] && applied !== me.skin) show(me.skin);
  });

  return {
    define: define,
    show: show,
    current: function () { return applied; },
    // the language the worn skin speaks, '' when it brings no words or the
    // account keeps them off (js/skin-teto.js asks before its letter of April)
    language: function () { return appliedLang; }
  };
})();
