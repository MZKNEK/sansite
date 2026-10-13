// Teto, the skin of the Japanese home page of 1 April (inc/auth/skins.php): the
// reds and the logo of the weakened Safeguard, and the glitched letter it is
// found by. Its words are the Japanese ones it shares with Miku (js/skin-ja.js).
// The skin is the account's to wear from its profile (/account/), its colours
// go over the whole site (css/skins.css), its logo and words stay on the home
// page; the letter switches the April skin on for the visit, as the language
// once did, and gives the seasonal find — a skin of the account's own that does
// not speak Japanese gives way to it for that visit. A worn skin that speaks
// Japanese is already the April look: the glitch then only waits on its last
// kana for the find alone.
(function () {
  SanakanSkins.define({
    id: 'teto',
    name: 'Teto',
    lang: 'ja',
    title: 'サナカン',
    logo: { src: './skins/teto.webp', alt: 'テト' },
    decorate: SanakanSkinJa.decorate,
    translate: SanakanSkinJa.translate
  });

  // ---- The glitched letter, the way this skin is found ---------------------
  // On 1 April only, the last letter of the name glitches and waits to be
  // activated; that gives the seasonal find and, without a skin of the
  // account's own, wears Teto for the visit (the profile keeps a skin, once it
  // is unlocked).
  if (!document.body.classList.contains('home') || !window.SanakanSafeguard
      || !SanakanSafeguard.holiday('aprilfools')) return;

  var heading = document.querySelector('h1.hud-title');
  var letter = document.createElement('button');
  letter.type = 'button';
  letter.className = 'april-letter';
  letter.setAttribute('aria-label', 'Zbadaj zakłócony znak');
  var original = document.createElement('span');
  original.className = 'april-letter-original';
  original.setAttribute('aria-hidden', 'true');
  var kana = document.createElement('span');
  kana.className = 'april-letter-kana';
  kana.setAttribute('aria-hidden', 'true');
  letter.append(original, kana);
  // the name as the page speaks it now: Japanese while a skin that speaks it is
  // worn, with the glitch waiting on its last kana; plain Polish otherwise
  SanakanSkinJa.writeName(SanakanSkins.language() === 'ja', letter);
  letter.addEventListener('click', activate, { once: true });

  function activate() {
    // a skin that speaks Japanese (Teto, Miku) is already the April look: the
    // click only takes the find. Any other skin, or none, gives way to the
    // April one for the visit, and a reload brings the account's own back.
    var japanese = SanakanSkins.language() === 'ja';
    heading.replaceChildren(document.createTextNode(SanakanSkinJa.spell(japanese)));
    if (!japanese) {
      SanakanSkins.show('teto');

      function returnButton(text, label) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'home-language-reset';
        button.textContent = text;
        button.setAttribute('aria-label', label);
        button.title = label;
        button.addEventListener('click', function () { location.reload(); });
        return button;
      }
      // Reloading restores the original language, logo and the account's HUD
      // without saving anything — the skin is the account's, from the profile.
      heading.replaceChildren(returnButton(heading.textContent, '通常表示に戻る'));
      document.querySelector('.kana').replaceChildren(returnButton('SANAKAN', 'Return to normal appearance'));
    }
    SanakanSafeguard.witness('aprilfools');
  }
})();
