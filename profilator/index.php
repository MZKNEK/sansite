<?php
    // Profilator: the bot's profile of layers set here (editor.js) and drawn as the bot
    // draws it (layers.js). The layout, the player's data, pictures and one-time unlocks
    // come from the bot through api.php; a save goes back to the bot, which charges what
    // is not unlocked yet. Without a login it shows made-up data and saves nothing.
    require __DIR__ . '/../inc/bot.php';
    installDataErrorHandler();
    require __DIR__ . '/../inc/meta.php';
    require __DIR__ . '/../inc/pw.php';
    $user = siteUser();
    $accountMenu = $user !== null ? accountMenuHtml($user, siteRoles(), $_SERVER['REQUEST_URI'] ?? '') : '';
    $csrf = $user !== null ? siteCsrf() : '';
    if (session_status() === PHP_SESSION_ACTIVE)
        session_write_close();
    if ($user !== null)
        header('Cache-Control: private, no-store');
    // the levels each avatar border has a look for (np/ab/<border>Lv/<level>.png)
    $levels = [];
    foreach (glob(pwDir() . '/np/ab/*Lv', GLOB_ONLYDIR) ?: [] as $dir) {
        $found = [];
        foreach (glob($dir . '/*.png') ?: [] as $file)
            if (ctype_digit($level = pathinfo($file, PATHINFO_FILENAME)))
                $found[] = (int)$level;
        sort($found);
        $levels[substr(basename($dir), 0, -2)] = $found;
    }
    $board = glob(__DIR__ . '/../skalpel/assets/empty-*.webp')[0] ?? null;
    // USkalpelator's background removal, built as a module of its own (apps/uskalpel/vite.config.js)
    $cutout = glob(__DIR__ . '/../uskalpel/assets/cutout-*.js')[0] ?? null;
    $avatar = $user !== null && $user['avatar'] !== '' ? preg_replace('~\?size=\d+$~', '?size=128', $user['avatar']) : '';
?>
<!DOCTYPE html>
<html lang="pl"<?=hudHtmlAttributes($user)?>>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
<?=metaTags('Profilator', 'Profil bota Sanakan z warstw: ułóż panele, karty i obrazki, wybierz ramkę i kolory, zobacz podgląd i zapisz.', '/profilator/', 'home')?>
  <title>Profilator &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../img/favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../apple-touch-icon.png" />
  <link rel="manifest" href="../site.webmanifest" />
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=f2ef38f341" type="text/css" rel="stylesheet" />
  <link href="../css/palette.css?v=94c96c7c93" type="text/css" rel="stylesheet" />
  <link href="../css/account.css?v=66047e5fc2" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=855c6b6aac" type="text/css" rel="stylesheet" />
  <link href="../css/status.css?v=df34270dd6" type="text/css" rel="stylesheet" />
  <link href="../css/admin.css?v=41f5842dd2" type="text/css" rel="stylesheet" />
  <link href="../css/skins.css?v=50e6f40617" type="text/css" rel="stylesheet" />
  <link href="profilator.css?v=452dc479f3" type="text/css" rel="stylesheet" />
  <script src="../js/appearance.js?v=2e6e831727"></script>
  <script src="../js/discovery-storage.js?v=5cd5704e81"></script>
  <link href="../css/seasonal-background.css?v=47cc796474" type="text/css" rel="stylesheet" />
</head>

<body class="state-page">
  <main class="content profilator-page" id="profilator" data-api="api.php" data-pw="../pw/np/" data-logged="<?=$user !== null ? '1' : '0'?>"
    data-levels="<?=e(json_encode((object)$levels))?>" data-csrf="<?=e($csrf)?>" data-avatar="<?=e($avatar)?>"
    data-art="../img/logo.webp" data-board="<?=$board !== null ? '../skalpel/assets/' . e(basename($board)) : ''?>" data-cards="../pw/" data-bot-cards="cards/"
    data-cutout="<?=$cutout !== null ? '../uskalpel/assets/' . e(basename($cutout)) : ''?>">
    <header class="ex-header">
      <div class="ex-top">
        <a class="back hud-corners" href="../" title="Strona główna">&larr; Sanakan</a>
        <?=$accountMenu?>
<?php if ($user === null && authConfigured()): ?>
        <a class="home-login hud-corners" id="profile-login" href="<?=e(siteRoot() . 'account.php?login&back=' . rawurlencode(siteRoot() . 'profilator/'))?>">Zaloguj przez Discord</a>
<?php endif; ?>
      </div>
      <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
      <h1 class="hud-title">Profilator</h1>
      <p class="version-intro">Profil bota z warstw: ułóż panele, karty i obrazki, zobacz podgląd i zapisz</p>
    </header>

    <div class="profilator-grid">
      <section class="card profilator-preview">
        <h2><i>01</i>Podgląd</h2>
        <div class="profilator-tools">
          <button type="button" class="admin-btn small" id="undo" title="Ctrl+Z" disabled>Cofnij</button>
          <button type="button" class="admin-btn small" id="redo" title="Ctrl+Y" disabled>Ponów</button>
          <button type="button" class="admin-btn small" id="snap" aria-pressed="true">Przyciąganie</button>
          <button type="button" class="admin-btn small" id="outlines" aria-pressed="false">Obrysy</button>
          <span class="profilator-gap"></span>
          <button type="button" class="admin-btn small" id="compare" aria-pressed="false"<?=$user === null ? ' hidden' : ''?>>Pokaż obecny</button>
          <button type="button" class="admin-btn small" id="bot-preview"<?=$user === null ? ' hidden' : ''?>>Sprawdź u bota</button>
          <button type="button" class="admin-btn small" id="share">Udostępnij układ</button>
          <button type="button" class="admin-btn small" id="motion-toggle" aria-pressed="false" hidden>Zatrzymaj animację</button>
        </div>
        <div class="profilator-canvas">
          <canvas id="profile-canvas" width="750" height="500" tabindex="0" role="img" aria-label="Podgląd profilu. Kliknij element i przeciągnij go; strzałki przesuwają zaznaczony."></canvas>
        </div>
        <div class="profilator-loading waiting" id="profile-loading" role="status">
          <span class="progress-text">Wczytywanie profilu&hellip;</span>
          <span class="progress-bar"><span></span></span>
        </div>
        <p class="hint" id="profile-status" aria-live="polite">Kliknij element, żeby go zaznaczyć, i przeciągnij. Strzałki przesuwają o 1 px, z Shiftem o 10 px.</p>
        <div class="profilator-bot-picture" id="bot-picture" hidden>
          <img id="bot-picture-img" alt="Profil narysowany przez bota" width="750" height="500" />
          <button type="button" class="admin-btn small" id="bot-picture-close">Zamknij obraz od bota</button>
        </div>
        <h3 class="profilator-sub">Gotowe układy <span class="muted">darmowe</span></h3>
        <div class="profilator-seg" id="presets"></div>
        <p class="hint" id="profile-source">Wczytywanie&hellip;</p>
      </section>

      <div class="profilator-settings" id="editor" inert>
        <section class="card">
          <h2><i>02</i>Warstwy</h2>
          <p class="hint">Na górze listy to, co leży na wierzchu. Awatar, pasek i nick mają stałe miejsca w kolejności, resztę układasz strzałkami.</p>
          <ul class="profilator-layers" id="layers"></ul>
          <div class="profilator-add">
            <span class="hud-select">
              <select id="add-kind" aria-label="Element do dodania"></select>
              <svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg>
            </span>
            <button type="button" class="admin-btn" id="add-btn">Dodaj</button>
          </div>
        </section>

        <section class="card">
          <h2><i>03</i><span id="props-title">Właściwości</span></h2>
          <div class="profilator-props" id="props"></div>
        </section>

        <section class="card">
          <h2><i>04</i>Wygląd całości</h2>
          <div class="profilator-group">
            <span class="hud-field-label">Kolor akcentu</span>
            <div class="profilator-swatches"><span id="accents"></span><label class="profilator-pick"><input type="color" id="accent-pick" value="#ff7a45" aria-label="Dowolny kolor akcentu" /> dowolny</label></div>
          </div>
          <div class="profilator-group"><span class="hud-field-label">Przyciemnienie nagłówka pod nickiem</span><div id="look-shade"></div></div>
          <div class="profilator-group"><span class="hud-field-label">Panele</span><div id="look-panels"></div></div>
          <label class="hud-field profilator-range">
            <span class="hud-field-label">Krycie cienia paneli <span class="muted" id="look-shadow-value"></span></span>
            <input class="player-range" id="look-shadow" type="range" min="0" max="100" step="1" />
          </label>
          <div class="profilator-group"><span class="hud-field-label">Pasek</span><div id="look-bar"></div></div>
          <div class="profilator-group"><span class="hud-field-label">Wygląd paska (szkło jak przy panelach)</span><div id="look-bar-look"></div></div>
          <label class="hud-field profilator-range">
            <span class="hud-field-label">Krycie paska <span class="muted" id="look-bar-opacity-value"></span></span>
            <input class="player-range" id="look-bar-opacity" type="range" min="20" max="100" step="1" />
          </label>
          <div class="profilator-group"><span class="hud-field-label">Pozycje paska (kliknij, by ukryć)</span><div id="look-bar-items"></div></div>
          <label class="hud-field">
            <span class="hud-field-label">Co się rusza (jedna rzecz)</span>
            <span class="hud-select">
              <select id="look-animate"></select>
              <svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg>
            </span>
          </label>
        </section>

        <section class="card">
          <h2><i>05</i>Obrazki</h2>
          <p class="hint">Obrazki trafiają do bota i są tylko twoje. Po odblokowaniu miejsca zmiany obrazków są darmowe; nieużywane znikają godzinę po zapisie profilu.</p>
          <ul class="profilator-pictures" id="pictures"></ul>
<?php if ($user !== null): ?>
          <div class="profilator-drop hud-corners" id="picture-drop" role="button" tabindex="0" aria-label="Przeciągnij obraz albo kliknij, by wybrać plik">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5" /><path d="M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4" /></svg>
            <span class="drop-text"><b>Przeciągnij obraz</b><small id="picture-name">albo kliknij, by wybrać plik: png, jpg, gif, webp, do 10 MB</small></span>
            <input id="picture-file" type="file" accept="image/png,image/jpeg,image/gif,image/webp" hidden />
          </div>
          <div class="hud-fields">
            <label class="hud-field">
              <span class="hud-field-label">Na</span>
              <span class="hud-select">
                <select id="picture-use">
                  <option value="overlay">nakładkę</option>
                  <option value="header">tło nagłówka</option>
                  <option value="style">obrazek stylu</option>
                  <option value="ultra">ultra nakładkę</option>
                  <option value="frame">własną ramkę awatara</option>
                </select>
                <svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg>
              </span>
            </label>
          </div>
          <div class="profilator-cut" id="cut-box" hidden>
            <label class="switch">
              <span class="switch-label">Usuń tło przed wysłaniem<small>Wycina postać jak USkalpelator. Model pobiera się przy pierwszym użyciu (44 lub 88 MB) i liczy się na procesorze.</small></span>
              <input type="checkbox" id="picture-cut" />
              <span class="switch-state" aria-hidden="true">WYŁ</span>
              <span class="switch-track" aria-hidden="true"></span>
            </label>
            <label class="hud-field">
              <span class="hud-field-label">Model usuwania tła</span>
              <span class="hud-select">
                <select id="cut-model"></select>
                <svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg>
              </span>
            </label>
          </div>
          <button type="button" class="admin-btn primary" id="picture-send">Wyślij obrazek</button>
<?php else: ?>
          <input type="file" id="picture-file" hidden />
          <select id="picture-use" hidden><option value="overlay">nakładka</option></select>
          <div id="cut-box" hidden><input type="checkbox" id="picture-cut" /><select id="cut-model"></select></div>
          <button type="button" id="picture-send" hidden></button>
<?php endif; ?>
        </section>

        <section class="card">
          <h2><i>06</i>Zapis</h2>
          <p class="hint">Każda opcja jest płatna raz. Bot pobiera opłatę tylko za to, czego jeszcze nie masz odblokowane; podgląd i gotowe układy są za darmo.</p>
          <ul class="profilator-cost" id="cost-list"></ul>
          <ul class="profilator-errors" id="cost-errors"></ul>
          <div class="profilator-total" id="cost-total"></div>
          <div class="profilator-save">
            <label class="hud-field" id="currency-field" hidden>
              <span class="hud-field-label">Płacę w</span>
              <span class="hud-select">
                <select id="currency"><option value="sc">SC</option><option value="tc">TC</option></select>
                <svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg>
              </span>
            </label>
            <button type="button" class="admin-btn primary" id="save" disabled>Zapisz</button>
          </div>
        </section>
      </div>
    </div>
  </main>
  <footer class="site-foot"><span>&copy; 2017&ndash;<?=date('Y')?> Sniku</span><i aria-hidden="true">&middot;</i><a href="../state/">Status</a><i aria-hidden="true">&middot;</i><a href="../privacy/">Prywatność</a><i aria-hidden="true">&middot;</i><a href="../terms/">Zasady</a></footer>
  <script src="../js/account.js?v=9ad24ea77c"></script>
  <script src="../js/netsphere.js?v=adf9386966"></script>
  <script src="../js/safeguard.js?v=7d172e8a71"></script>
  <script src="../js/seasonal-background.js?v=4ba6c522d2"></script>
  <script src="../js/palette.js?v=10c535e8a5"></script>
  <script src="layers.js?v=3b3f06307f"></script>
  <script src="editor.js?v=8febcb7caa"></script>
</body>

</html>
