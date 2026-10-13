// Miku, the skin of the home page (inc/auth/skins.php): the teal of the
// television singer and the logo she brings, with the Japanese words it shares
// with Teto (js/skin-ja.js). Its colours go over the whole site
// (css/skins.css), its logo and words stay on the home page; the account wears
// it from its profile (/account/), where the find of 1 April unlocks it
// (js/skin-teto.js).
(function () {
  SanakanSkins.define({
    id: 'miku',
    name: 'Miku',
    lang: 'ja',
    title: 'サナカン',
    logo: { src: './skins/miku.webp', alt: 'ミク' },
    decorate: SanakanSkinJa.decorate,
    translate: SanakanSkinJa.translate
  });
})();
