// Home-only rendering. SanakanAppearance owns colours and account/visit
// preferences; the locale controller translates and restores the page's words.
window.SanakanSkins = (function () {
  var applied = '';
  var languages = { ja: window.SanakanSkinJa };
  var logo = document.querySelector('img.logo');
  var baseLogo = { src: logo.getAttribute('src'), alt: logo.alt };
  function known(id) {
    return Object.prototype.hasOwnProperty.call(SanakanSkinCatalog, id);
  }
  function render(state) {
    var skin = known(state.skin) ? SanakanSkinCatalog[state.skin] : null;
    applied = skin ? state.skin : '';
    logo.src = skin ? './skins/' + applied + '.webp' : baseLogo.src;
    logo.alt = skin ? skin.alt : baseLogo.alt;
    SanakanHomeLocale.set(skin && state.words ? languages[skin.lang] || null : null);
  }
  document.addEventListener('sanakan:appearance', function (e) { render(e.detail); });
  render(SanakanAppearance.current());
  return {
    show: function (id, force) {
      if (known(id)) SanakanAppearance.forVisit(id, force || SanakanAppearance.current().words);
    },
    current: function () { return applied; },
    language: SanakanHomeLocale.language
  };
})();
