<?php
    // WAP, only for those inc/members.php lets in (wapAllowed()); the others
    // get the 403 page
    require __DIR__ . '/../inc/members.php';
    memberGate(true, wapAllowed());
?>
<!DOCTYPE html>
<html lang="pl">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Weeaboo Animu Playlist: piosenki z anime, po kolei albo w losowej kolejności." />
  <meta name="theme-color" content="#9b59b6" />
  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="Sanakan" />
  <meta property="og:locale" content="pl_PL" />
  <meta property="og:title" content="WAP" />
  <meta property="og:description" content="Weeaboo Animu Playlist: piosenki z anime, po kolei albo w losowej kolejności." />
  <meta property="og:url" content="https://sanakan.pl/wap/" />
  <meta property="og:image" content="https://sanakan.pl/og.php" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta name="twitter:card" content="summary_large_image" />
  <title>WAP &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../img/favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../apple-touch-icon.png" />
  <link rel="manifest" href="../site.webmanifest" />
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=b75bcdfb18" type="text/css" rel="stylesheet" />
  <link href="../css/palette.css?v=94c96c7c93" type="text/css" rel="stylesheet" />
  <link href="../css/skins.css?v=50e6f40617" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=e330d129f9" type="text/css" rel="stylesheet" />
  <link href="wap.css?v=051b14a735" type="text/css" rel="stylesheet" />
  <script src="../js/appearance.js?v=2e6e831727"></script>
</head>

<body class="wap">
  <main class="content">
    <header class="ex-header">
      <div class="ex-top">
        <a class="back hud-corners" href="../" title="Strona główna">&larr; Sanakan</a>
      </div>
      <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
      <h1 class="hud-title">WAP</h1>
    </header>

    <div class="wap-deck">
      <section class="wap-player hud-corners" aria-label="Odtwarzacz">
        <div class="wap-now">
          <span class="wap-label" id="wap-state">Gotowy</span>
          <span class="wap-label wap-position" id="wap-position"></span>
        </div>
        <p class="wap-title" id="wap-title">&mdash;</p>
        <p class="wap-artist" id="wap-artist"></p>

        <div class="wap-seek-row">
          <span class="player-time" id="wap-time">0:00</span>
          <input type="range" class="player-range player-seek" id="wap-seek" min="0" max="1000" step="1" value="0" aria-label="Przewijanie" disabled />
          <span class="player-time" id="wap-duration">0:00</span>
        </div>

        <div class="wap-controls">
          <button type="button" class="player-btn hud-corners" id="wap-prev" aria-label="Poprzedni" title="Poprzedni (P)">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 5.5v13M18 5.5v13L8.5 12z" /></svg>
          </button>
          <button type="button" class="player-btn hud-corners wap-play" id="wap-play" aria-label="Odtwórz" title="Odtwórz / pauza (spacja)" aria-pressed="false">
            <svg class="when-off" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l10-6.5z" /></svg>
            <svg class="when-on" viewBox="0 0 24 24" aria-hidden="true"><path d="M8.5 5.5v13M15.5 5.5v13" /></svg>
          </button>
          <button type="button" class="player-btn hud-corners" id="wap-next" aria-label="Następny" title="Następny (N)">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 5.5v13M6 5.5v13l9.5-6.5z" /></svg>
          </button>
          <button type="button" class="player-btn hud-corners player-switch" id="wap-shuffle" aria-label="Losowa kolejność" title="Losowa kolejność (S)" aria-pressed="false">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h3.5c2 0 3.2 1 4.3 2.6l2.4 4.8c1.1 1.6 2.3 2.6 4.3 2.6H21M3 17h3.5c1.4 0 2.4-.5 3.2-1.3M14.3 8.3c.8-.8 1.8-1.3 3.2-1.3H21M18 4l3 3-3 3M18 14l3 3-3 3" /></svg>
          </button>
          <span class="wap-gap"></span>
          <button type="button" class="player-btn hud-corners" id="wap-mute" aria-label="Wycisz" title="Wycisz (M)" aria-pressed="false">
            <svg class="when-off" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9.5h3.5L12 5.5v13l-4.5-4H4zM15.5 9a4 4 0 0 1 0 6M18 6.5a7.5 7.5 0 0 1 0 11" /></svg>
            <svg class="when-on" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9.5h3.5L12 5.5v13l-4.5-4H4zM16 9.5l5 5M21 9.5l-5 5" /></svg>
          </button>
          <input type="range" class="player-range player-volume" id="wap-volume" min="0" max="100" step="1" value="70" aria-label="Głośność" title="Głośność (&uarr; &darr;)" />
        </div>
      </section>

      <section class="wap-playlist" aria-labelledby="wap-list-title">
        <div class="wap-list-head">
          <h2 id="wap-list-title">Lista</h2>
          <span class="wap-count" id="wap-count"></span>
          <label class="hud-button hud-corners" title="Utwory z dysku, tylko w tej karcie"><span id="wap-add-label">Dodaj folder</span><input type="file" id="wap-files" webkitdirectory multiple accept="audio/*" /></label>
          <button type="button" class="hud-button hud-corners" id="wap-remember" title="Folder wczyta się sam przy następnej wizycie" hidden>Zapamiętaj folder</button>
          <button type="button" class="hud-button hud-corners" id="wap-clear" title="Zdejmuje z listy utwory z dysku; pliki zostają na dysku" hidden>Usuń własne</button>
        </div>
        <div class="wap-folders" id="wap-folders" aria-label="Zapamiętane foldery" hidden></div>
        <ol class="wap-list" id="wap-list"></ol>
        <p class="wap-status" id="wap-status" role="status">Wczytywanie&hellip;</p>
        <p class="wap-hint">Utwory z dysku grają tylko w tej karcie i nigdzie nie są wysyłane. Folder lub pliki można też upuścić na stronę.<span id="wap-remember-hint" hidden> Zapamiętany folder wraca przy następnej wizycie; przeglądarka może najpierw poprosić o zgodę na odczyt.</span></p>
      </section>
    </div>
  </main>
  <footer class="site-foot"><span>&copy; 2017&ndash;<span id="foot-year">2026</span> Sniku</span><i aria-hidden="true">&middot;</i><a href="../state/">Status</a><i aria-hidden="true">&middot;</i><a href="../privacy/">Prywatność</a><i aria-hidden="true">&middot;</i><a href="../terms/">Zasady</a></footer>
  <script src="../js/year.js?v=27252b35ac"></script>
  <script src="tags.js?v=8941e3a820"></script>
  <script src="wap.js?v=f5f476273d"></script>
  <script src="../js/palette.js?v=7ab481b465"></script>
</body>

</html>
