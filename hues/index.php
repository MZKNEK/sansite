<?php
    // 0x40, only for those inc/members.php lets in; the others get the 403
    // page. An owner of the site gets the link to the middles of the pictures.
    require __DIR__ . '/../inc/members.php';
    $owner = ownerAllowed();
    memberGate();
?>
<!DOCTYPE html>
<html lang="pl">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Obrazy migają w rytm muzyki w 64 barwach, w stylu 0x40 Hues." />
  <meta name="theme-color" content="#9b59b6" />
  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="Sanakan" />
  <meta property="og:locale" content="pl_PL" />
  <meta property="og:title" content="0x40" />
  <meta property="og:description" content="Obrazy migają w rytm muzyki w 64 barwach, w stylu 0x40 Hues." />
  <meta property="og:url" content="https://sanakan.pl/hues/" />
  <meta property="og:image" content="https://sanakan.pl/og.php?p=hues" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta name="twitter:card" content="summary_large_image" />
  <title>0x40 &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../img/favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../apple-touch-icon.png" />
  <link rel="manifest" href="../site.webmanifest" />
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=f2ef38f341" type="text/css" rel="stylesheet" />
  <link href="../css/palette.css?v=94c96c7c93" type="text/css" rel="stylesheet" />
  <link href="../css/skins.css?v=50e6f40617" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=2f5ac20877" type="text/css" rel="stylesheet" />
  <link href="hues.css?v=6c5285720b" type="text/css" rel="stylesheet" />
  <script src="../js/appearance.js?v=2e6e831727"></script>
</head>

<body class="hues hues-on" data-backdrop="transparent">
  <canvas class="hues-stage" id="hues-stage" aria-hidden="true"></canvas>
  <a class="hues-back back hud-corners" href="../" title="Strona główna">&larr; Sanakan</a>

  <p class="hues-status" id="hues-status" role="status" hidden></p>
  <div class="hues-loading" id="hues-loading" role="progressbar" aria-label="Wczytywanie obrazów" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" hidden>
    <span class="progress-text" id="hues-loading-text">Wczytuję obrazy&hellip;</span>
    <span class="progress-bar"><span id="hues-loading-fill"></span></span>
  </div>

  <div class="hues-ui" id="hues-ui">
    <div class="hues-controls">
      <button class="player-btn hud-corners" id="hues-pause" type="button" aria-label="Pauza" aria-pressed="false" title="Pauza (Spacja)"><svg class="when-off" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14M16 5v14" /></svg><svg class="when-on" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5v14l12-7z" /></svg></button>
      <button class="player-btn hud-corners" id="hues-mute" type="button" aria-label="Wycisz" aria-pressed="false" title="Wycisz (M)"><svg class="when-off" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9zM16.5 8.5a5 5 0 0 1 0 7M19 6a8.5 8.5 0 0 1 0 12" /></svg><svg class="when-on" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9zm12 0 6 6m0-6-6 6" /></svg></button>
      <input class="player-range player-volume" id="hues-volume" type="range" min="0" max="100" step="1" value="20" aria-label="Głośność" />
      <span class="hues-gap"></span>
      <button class="hud-button hud-corners" id="hues-lock" type="button" aria-pressed="false" title="Zablokuj obraz (K)">Blokada obrazu</button>
      <button class="player-btn hud-corners" id="hues-settings-toggle" type="button" aria-label="Ustawienia" aria-expanded="false" aria-controls="hues-settings" title="Ustawienia (S)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" /><circle cx="12" cy="12" r="3" /></svg></button>
      <button class="player-btn hud-corners" id="hues-help-toggle" type="button" aria-label="Pomoc" aria-expanded="false" aria-controls="hues-help" title="Pomoc (?)"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6M12 17.5v.01" /></svg></button>
      <button class="player-btn hud-corners" id="hues-full" type="button" aria-label="Pełny ekran" title="Pełny ekran (F)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5" /></svg></button>
      <button class="player-btn hud-corners" id="hues-hide" type="button" aria-label="Ukryj interfejs" title="Ukryj interfejs (H)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg></button>
    </div>
    <div class="hues-beats" aria-hidden="true">
      <span class="hues-prompt">&gt;</span><span class="hues-beat-now" id="hues-beat-now">.</span><span class="hues-beat-next" id="hues-beat-next"></span>
    </div>
    <div class="hues-row">
      <div class="hues-counters" aria-hidden="true">
        <span>T=$0x<b id="hues-time">0000</b></span>
        <span>B=$0x<b id="hues-beatno">0000</b></span>
        <span>BPM <b id="hues-bpm">&mdash;</b></span>
        <span><b id="hues-hue-index">$0x00</b> <b id="hues-hue-hex">#000000</b></span>
      </div>
      <div class="hues-names">
        <span class="hues-name"><i>Barwa</i><b class="hues-hue" id="hues-hue">Czerń</b></span>
        <span class="hues-name"><i>Obraz</i><span class="hues-steps"><button class="hues-step" id="hues-image-prev" type="button" title="Poprzedni obraz (←)" aria-label="Poprzedni obraz"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 5-7 7 7 7" /></svg></button><button class="hues-link" id="hues-image" type="button" title="Szukaj obrazu (O)" aria-haspopup="dialog" aria-expanded="false" aria-controls="hues-picker">&mdash;</button><button class="hues-step" id="hues-image-next" type="button" title="Następny obraz (→)" aria-label="Następny obraz"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7" /></svg></button></span></span>
        <span class="hues-name"><i>Utwór</i><span class="hues-steps"><button class="hues-step" id="hues-song-prev" type="button" title="Poprzedni utwór (P)" aria-label="Poprzedni utwór"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 5-7 7 7 7" /></svg></button><button class="hues-link" id="hues-song" type="button" title="Szukaj utworu (U)" aria-haspopup="dialog" aria-expanded="false" aria-controls="hues-picker">&mdash;</button><button class="hues-step" id="hues-song-next" type="button" title="Następny utwór (N)" aria-label="Następny utwór"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7" /></svg></button></span></span>
      </div>
    </div>
  </div>

  <section class="hues-help hues-settings hud-corners" id="hues-settings" aria-labelledby="hues-settings-title" hidden>
    <button class="player-btn hud-corners hues-help-close" id="hues-settings-close" type="button" aria-label="Zamknij ustawienia"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg></button>
    <h2 id="hues-settings-title">Ustawienia</h2>
    <div class="hues-part">
      <h3>Przejścia</h3>
      <fieldset class="hues-setting hues-modes">
        <legend class="hues-setting-label">Mapy i efekty</legend>
        <label class="hues-mode"><input type="radio" name="hues-mode" id="hues-mode-classic" value="classic" checked /> <span><b>Klasyczne</b> oryginalne mapy utworów z 0x40 Hues i tylko jego pierwsze efekty: rozmycia, cięcia barw i obrazów, przejścia barw, wygaszenia</span></label>
        <label class="hues-mode"><input type="radio" name="hues-mode" id="hues-mode-extended" value="extended" /> <span><b>Rozszerzone</b> te same oryginalne mapy, a na najmocniejszych uderzeniach gdzieniegdzie odwrócenie kolorów i cięcie obrazu w paski</span></label>
      </fieldset>
    </div>
    <div class="hues-part">
      <h3>Grafika</h3>
      <fieldset class="hues-setting hues-choice">
        <legend class="hues-setting-label">Siła rozmycia</legend>
        <div class="hues-segments">
          <label><input type="radio" name="hues-blur-amount" value="low" /><span>Słaba</span></label>
          <label><input type="radio" name="hues-blur-amount" value="medium" checked /><span>Średnia</span></label>
          <label><input type="radio" name="hues-blur-amount" value="high" /><span>Mocna</span></label>
        </div>
      </fieldset>
      <fieldset class="hues-setting hues-choice">
        <legend class="hues-setting-label">Zanikanie rozmycia</legend>
        <div class="hues-segments">
          <label><input type="radio" name="hues-blur-decay" value="slow" /><span>Wolne</span></label>
          <label><input type="radio" name="hues-blur-decay" value="medium" /><span>Średnie</span></label>
          <label><input type="radio" name="hues-blur-decay" value="fast" checked /><span>Szybkie</span></label>
          <label><input type="radio" name="hues-blur-decay" value="faster" /><span>Najszybsze</span></label>
        </div>
      </fieldset>
      <fieldset class="hues-setting hues-choice">
        <legend class="hues-setting-label">Jakość rozmycia</legend>
        <div class="hues-segments">
          <label><input type="radio" name="hues-blur-quality" value="low" /><span>Niska</span></label>
          <label><input type="radio" name="hues-blur-quality" value="medium" checked /><span>Średnia</span></label>
          <label><input type="radio" name="hues-blur-quality" value="high" /><span>Wysoka</span></label>
          <label><input type="radio" name="hues-blur-quality" value="extreme" /><span>Ekstremalna</span></label>
        </div>
      </fieldset>
      <div class="hues-setting">
        <label class="hues-setting-label" for="hues-speed">Prędkość animacji <output id="hues-speed-value" for="hues-speed">×1,00</output></label>
        <div class="hues-setting-row">
          <input class="player-range hues-range" id="hues-speed" type="range" min="0.25" max="2" step="0.05" value="1" />
          <button class="hud-button hud-corners" id="hues-speed-reset" type="button">Domyślna</button>
        </div>
        <p>×1 to jeden obieg animacji na cztery uderzenia utworu.</p>
      </div>
    </div>
    <div class="hues-part">
      <h3>Działanie</h3>
      <fieldset class="hues-setting hues-choice">
        <legend class="hues-setting-label">Zmiana utworu</legend>
        <div class="hues-segments">
          <label><input type="radio" name="hues-opt-autoSong" value="off" checked /><span>Wył.</span></label>
          <label><input type="radio" name="hues-opt-autoSong" value="loop" /><span>Po pętlach</span></label>
          <label><input type="radio" name="hues-opt-autoSong" value="time" /><span>Po czasie</span></label>
          <span class="hues-after">po <input class="field hues-number" id="hues-auto-after" type="number" min="1" max="99" step="1" value="5" aria-label="Po ilu" /> <span id="hues-auto-unit">minutach</span></span>
        </div>
      </fieldset>
      <fieldset class="hues-setting hues-choice">
        <legend class="hues-setting-label">Losowa kolejność utworów</legend>
        <div class="hues-segments">
          <label><input type="radio" name="hues-opt-autoShuffle" value="off" /><span>Wył.</span></label>
          <label><input type="radio" name="hues-opt-autoShuffle" value="on" checked /><span>Wł.</span></label>
        </div>
      </fieldset>
      <fieldset class="hues-setting hues-choice">
        <legend class="hues-setting-label">Wyciszenie przed zmianą</legend>
        <div class="hues-segments">
          <label><input type="radio" name="hues-opt-autoFade" value="off" /><span>Wył.</span></label>
          <label><input type="radio" name="hues-opt-autoFade" value="on" checked /><span>Wł.</span></label>
        </div>
      </fieldset>
      <fieldset class="hues-setting hues-choice">
        <legend class="hues-setting-label">Buildupy</legend>
        <div class="hues-segments">
          <label><input type="radio" name="hues-opt-buildups" value="off" /><span>Wył.</span></label>
          <label><input type="radio" name="hues-opt-buildups" value="once" /><span>Raz</span></label>
          <label><input type="radio" name="hues-opt-buildups" value="on" checked /><span>Zawsze</span></label>
        </div>
      </fieldset>
    </div>
    <div class="hues-part">
      <h3>Wygląd</h3>
      <fieldset class="hues-setting hues-choice">
        <legend class="hues-setting-label">Wyśrodkowanie obrazów na postaci</legend>
        <div class="hues-segments">
          <label><input type="radio" name="hues-opt-smartAlign" value="off" /><span>Wył.</span></label>
          <label><input type="radio" name="hues-opt-smartAlign" value="on" checked /><span>Wł.</span></label>
        </div>
<?php if ($owner): ?>
        <p class="hues-owner"><a class="hud-button hud-corners" href="centres.php">Środki obrazów</a> <span>ręczny środek postaci dla obrazów bez przezroczystości, tylko dla właściciela</span></p>
<?php endif; ?>
      </fieldset>
      <fieldset class="hues-setting hues-choice">
        <legend class="hues-setting-label">Losowe obrazy</legend>
        <div class="hues-segments">
          <label><input type="radio" name="hues-opt-shuffleImages" value="off" /><span>Wył.</span></label>
          <label><input type="radio" name="hues-opt-shuffleImages" value="on" checked /><span>Wł.</span></label>
        </div>
      </fieldset>
      <fieldset class="hues-setting hues-choice">
        <legend class="hues-setting-label">Zestaw barw</legend>
        <div class="hues-segments">
          <label><input type="radio" name="hues-opt-colours" value="normal" checked /><span>Zwykłe</span></label>
          <label><input type="radio" name="hues-opt-colours" value="pastel" /><span>Pastelowe</span></label>
        </div>
      </fieldset>
      <fieldset class="hues-setting hues-choice">
        <legend class="hues-setting-label">Mieszanie barwy</legend>
        <div class="hues-segments">
          <label><input type="radio" name="hues-opt-blend" value="hard-light" checked /><span>Hard light</span></label>
          <label><input type="radio" name="hues-opt-blend" value="screen" /><span>Screen</span></label>
          <label><input type="radio" name="hues-opt-blend" value="multiply" /><span>Multiply</span></label>
        </div>
      </fieldset>
      <fieldset class="hues-setting hues-choice">
        <legend class="hues-setting-label">Tło</legend>
        <div class="hues-segments">
          <label><input type="radio" name="hues-opt-backdrop" value="white" /><span>Białe</span></label>
          <label><input type="radio" name="hues-opt-backdrop" value="black" /><span>Czarne</span></label>
          <label><input type="radio" name="hues-opt-backdrop" value="transparent" checked /><span>Przezroczyste</span></label>
        </div>
      </fieldset>
      <fieldset class="hues-setting hues-choice">
        <legend class="hues-setting-label">Analizator widma</legend>
        <div class="hues-segments">
          <label><input type="radio" name="hues-opt-spectrum" value="off" checked /><span>Wył.</span></label>
          <label><input type="radio" name="hues-opt-spectrum" value="on" /><span>Wł.</span></label>
        </div>
      </fieldset>
    </div>
    <div class="hues-part">
      <h3>Własne pliki</h3>
      <div class="hues-setting">
        <label class="hud-button hud-corners">Dodaj pliki<input id="hues-files" type="file" accept="audio/*,image/*" multiple /></label>
        <p>Utwór lub obrazki; można je też upuścić na stronę. Pliki są czytane tylko w przeglądarce i nigdzie nie są wysyłane.</p>
      </div>
    </div>
  </section>

  <section class="hues-help hud-corners" id="hues-help" aria-labelledby="hues-help-title" hidden>
    <button class="player-btn hud-corners hues-help-close" id="hues-help-close" type="button" aria-label="Zamknij pomoc"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg></button>
    <h2 id="hues-help-title">Pomoc</h2>
    <p>Mapa nad licznikami mówi, co stanie się na kolejnych uderzeniach. To oryginalne mapy utworów z 0x40 Hues; w trybie rozszerzonym na najmocniejszych uderzeniach, wykrytych w przeglądarce, dochodzą odwrócenie kolorów i cięcia obrazu. Własny utwór dostaje mapę liczoną z dźwięku: każdy znak to pół uderzenia.</p>
    <div class="hues-help-cols">
      <dl class="hues-legend">
        <dt>x</dt><dd>pionowe rozmycie, nowy obraz i barwa</dd>
        <dt>o</dt><dd>poziome rozmycie, nowy obraz i barwa</dd>
        <dt>X</dt><dd>pionowe rozmycie i barwa</dd>
        <dt>O</dt><dd>poziome rozmycie i barwa</dd>
        <dt>-</dt><dd>nowy obraz i barwa</dd>
        <dt>:</dt><dd>nowa barwa</dd>
        <dt>~</dt><dd>płynne przejście barwy</dd>
        <dt>=</dt><dd>przejście barwy i nowy obraz</dd>
        <dt>|</dt><dd>krótkie wygaszenie</dd>
        <dt>+</dt><dd>wygaszenie do następnego znaku</dd>
        <dt>i</dt><dd>odwrócenie kolorów (rozszerzone)</dd>
        <dt>s v #</dt><dd>cięcie do nowego obrazu w poziomie, w pionie, w obu (rozszerzone)</dd>
        <dt>.</dt><dd>nic</dd>
      </dl>
      <dl class="hues-keys">
        <dt>Spacja</dt><dd>pauza</dd>
        <dt>&larr; &rarr;</dt><dd>poprzedni, następny obraz</dd>
        <dt>K</dt><dd>blokada obrazu</dd>
        <dt>N P</dt><dd>następny, poprzedni utwór</dd>
        <dt>&uarr; &darr;</dt><dd>głośność</dd>
        <dt>M</dt><dd>wyciszenie</dd>
        <dt>H</dt><dd>ukrycie interfejsu</dd>
        <dt>F</dt><dd>pełny ekran</dd>
        <dt>O U</dt><dd>szukanie obrazu, utworu</dd>
        <dt>S</dt><dd>ustawienia</dd>
        <dt>?</dt><dd>pomoc</dd>
      </dl>
    </div>
  </section>

  <section class="hues-picker hud-corners" id="hues-picker" role="dialog" aria-labelledby="hues-picker-title" hidden>
    <button class="player-btn hud-corners hues-help-close" id="hues-picker-close" type="button" aria-label="Zamknij wyszukiwanie"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg></button>
    <h2 id="hues-picker-title">Obraz</h2>
    <input class="field hues-search" id="hues-picker-search" type="search" autocomplete="off" spellcheck="false" role="combobox" aria-expanded="true" aria-autocomplete="list" aria-controls="hues-picker-list" aria-labelledby="hues-picker-title" />
    <ul class="hues-picker-list" id="hues-picker-list" role="listbox"></ul>
    <p class="hues-picker-empty" id="hues-picker-empty" hidden>Nic nie pasuje.</p>
  </section>

  <script src="beats.js?v=a741788131"></script>
  <script src="hues.js?v=e757f89fd1"></script>
  <script src="../js/palette.js?v=10c535e8a5"></script>
</body>

</html>
