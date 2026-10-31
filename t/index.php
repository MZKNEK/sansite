<?php
    // Sending files between two devices (inc/transfer.php, t.js): a logged-in
    // account opens a transfer and shows its QR code; the device that scans it
    // (#<id> after the address, never sent in a page request) joins without a
    // login. Anyone else is asked to log in.
    require __DIR__ . '/../inc/transfer.php';
    installDataErrorHandler();
    $user = siteUser();
    $csrf = $user ? siteCsrf() : '';
    if (session_status() === PHP_SESSION_ACTIVE)
        session_write_close();
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex');
?>
<!DOCTYPE html>
<html lang="pl">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex" />
  <meta name="referrer" content="no-referrer" />
  <meta name="theme-color" content="#9b59b6" />
  <title>Przesyłanie &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../img/favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../apple-touch-icon.png" />
  <link rel="manifest" href="../site.webmanifest" />
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=f2ef38f341" type="text/css" rel="stylesheet" />
  <link href="../css/palette.css?v=94c96c7c93" type="text/css" rel="stylesheet" />
  <link href="../css/skins.css?v=50e6f40617" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=2f5ac20877" type="text/css" rel="stylesheet" />
  <link href="t.css?v=e010d817c1" type="text/css" rel="stylesheet" />
  <script src="../js/appearance.js?v=2e6e831727"></script>
</head>

<body class="transfer" data-csrf="<?= htmlspecialchars($csrf) ?>" data-store="store.js?v=bb1843caea">
  <main class="content">
    <header class="ex-header">
      <div class="ex-top">
        <a class="back hud-corners" href="../" title="Strona główna">&larr; Sanakan</a>
      </div>
      <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
      <h1 class="hud-title">Przesyłanie</h1>
    </header>

    <section class="t-panel hud-corners" aria-live="polite">
      <!-- the first device: a new transfer, or a login first -->
      <div class="t-step" id="t-start" hidden>
        <?php if ($user): ?>
        <p class="t-lead">Przesyłaj pliki między dwoma urządzeniami, prosto z jednego na drugie.</p>
        <button type="button" class="hud-button hud-corners t-main" id="t-new">Nowe przesyłanie</button>
        <?php else: ?>
        <p class="t-lead">Zaloguj się, żeby otworzyć przesyłanie. Urządzenie, które zeskanuje kod, nie musi być zalogowane.</p>
        <a class="hud-button hud-corners t-main" href="../account.php?login&amp;back=%2Ft%2F">Zaloguj się</a>
        <?php endif; ?>
      </div>

      <!-- the first device: the code to scan -->
      <div class="t-step" id="t-code" hidden>
        <p class="t-label">Zeskanuj na drugim urządzeniu</p>
        <canvas class="t-qr" id="t-qr" width="1" height="1" role="img" aria-label="Kod QR z adresem przesyłania"></canvas>
        <div class="t-link-row">
          <input class="field t-address" id="t-address" type="text" readonly aria-label="Adres przesyłania" />
          <button type="button" class="hud-button hud-corners" id="t-copy">Kopiuj</button>
        </div>
        <p class="t-note"><span id="t-ttl"></span> &middot; jedno urządzenie, jeden raz</p>
        <button type="button" class="hud-button hud-corners" id="t-cancel">Anuluj</button>
      </div>

      <!-- the second device: asked before it joins -->
      <div class="t-step" id="t-ask" hidden>
        <p class="t-lead" id="t-ask-text"></p>
        <div class="t-buttons">
          <button type="button" class="hud-button hud-corners t-main" id="t-join">Połącz</button>
          <a class="hud-button hud-corners" href="./">Nie</a>
        </div>
      </div>

      <!-- both: connecting, then the files either way -->
      <div class="t-step" id="t-link" hidden>
        <div class="t-state-row">
          <span class="t-label" id="t-state">Łączenie&hellip;</span>
          <span class="t-route" id="t-route"></span>
        </div>
        <p class="t-peer" id="t-peer"></p>
        <div class="t-relay" id="t-relay" hidden>
          <p>Nie udało się połączyć urządzeń bezpośrednio. Pliki mogą pójść przez serwer: kawałek po kawałku, każdy usuwany z pamięci serwera zaraz po odebraniu.</p>
          <button type="button" class="hud-button hud-corners" id="t-relay-go">Przez serwer</button>
        </div>
        <div class="t-drop" id="t-drop" hidden>
          <label class="hud-button hud-corners t-main">Wybierz pliki<input type="file" id="t-files" multiple /></label>
          <span class="t-drop-hint">albo upuść je tutaj</span>
        </div>
        <ol class="t-list" id="t-list"></ol>
        <button type="button" class="hud-button hud-corners" id="t-end">Rozłącz</button>
      </div>

      <p class="t-message" id="t-message" role="status" hidden></p>
      <button type="button" class="hud-button hud-corners" id="t-again" hidden>Od nowa</button>
    </section>

    <p class="t-hint">Pliki idą prosto między urządzeniami (WebRTC, szyfrowane) i nie są zapisywane na serwerze, który tylko pomaga im się odnaleźć. W tej samej sieci idą po niej. Odebrany plik czeka w pamięci przeglądarki, aż go zapiszesz, więc jeden może mieć do 2 GB. W sieci lokalnej duży plik (od 256 MB) zapisuje się w trakcie na dysku, w miejscu przeglądarki, więc może być większy, póki starczy miejsca; ta kopia znika po „Usuń” albo przy następnej wizycie. Nie zamykaj karty ani nie wygaszaj ekranu w trakcie.</p>
  </main>
  <footer class="site-foot"><span>&copy; 2017&ndash;<span id="foot-year">2026</span> Sniku</span><i aria-hidden="true">&middot;</i><a href="../state/">Status</a><i aria-hidden="true">&middot;</i><a href="../privacy/">Prywatność</a><i aria-hidden="true">&middot;</i><a href="../terms/">Zasady</a></footer>
  <script src="../js/year.js?v=27252b35ac"></script>
  <script src="qrcode.js?v=18ae399f81"></script>
  <script src="t.js?v=c51e1701ef"></script>
  <script src="../js/palette.js?v=10c535e8a5"></script>
</body>

</html>
