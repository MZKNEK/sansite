// The seasonal April discovery, independent of the account's selected skin.
// The temporary look wins over an account response that arrives after a click.
(function () {
  // On 1 April the last letter waits to be activated. It gives the find and
  // switches to Teto for this visit unless the page already speaks Japanese.
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
    // click only takes the find. Any other skin, or none — even one the
    // account keeps silent (skinwords off) — gives way to the April one for
    // the visit, its words on, and a reload brings the account's own back.
    var japanese = SanakanSkins.language() === 'ja';
    heading.replaceChildren(document.createTextNode(SanakanSkinJa.spell(japanese)));
    if (!japanese) {
      SanakanSkins.show('teto', true);

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
