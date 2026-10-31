<?php
    // Profilator: the look of the bot's profile set here and shown as the bot
    // draws it (profile.js, from the bot's own pictures mirrored at /pw/np/,
    // inc/pw.php), with the code `konfiguracja profilu kod` takes to set it all
    // at once, and a code pasted back to show it. The numbers, cards and list
    // statistics on it are made up; the logged-in account lends its name,
    // avatar and the colour of its role. The pictures picked for the preview
    // stay in the browser, unless they are put in the code: then they go to the
    // folder Profilator of the account's gallery and the code carries their links.
    require __DIR__ . '/../inc/bot.php';
    installDataErrorHandler();
    require __DIR__ . '/../inc/meta.php';
    require __DIR__ . '/../inc/pw.php';

    $user = siteUser();
    $badge = $user !== null ? roleBadge(siteRoles()) : null;
    $accountMenu = $user !== null ? accountMenuHtml($user, siteRoles(), $_SERVER['REQUEST_URI'] ?? '') : '';
    // the pictures put in a code go to the account's own folder of the gallery (i/, action profilator)
    $own = $user !== null && isGalleryUploaderId($user['id']);
    $csrf = $own ? siteCsrf() : '';
    if (session_status() === PHP_SESSION_ACTIVE)
        session_write_close();
    if ($user !== null)
        header('Cache-Control: private, no-store');

    $prefix = (string)(botCommands()['prefix'] ?? '');

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
    $ready = is_file(pwDir() . '/np/pbase.png');
    $board = glob(__DIR__ . '/../skalpel/assets/empty-*.webp')[0] ?? null;

    $avatar = $user !== null && $user['avatar'] !== '' ? preg_replace('~\?size=\d+$~', '?size=128', $user['avatar']) : '';

    const PROFILE_STYLES = [
        'Stats' => 'statystyki', 'Img' => 'obrazek', 'StatsWithImg' => 'obrazek na statystykach', 'Cards' => 'duża galeria',
        'CardsOnImg' => 'duża galeria na obrazku', 'StatsOnImg' => 'statystyki na obrazku', 'MiniGallery' => 'galeria z karcianką',
        'MiniGalleryOnImg' => 'galeria z karcianką na obrazku'
    ];
    const PROFILE_BORDERS = [
        'None' => 'brak', 'Base' => 'domyślna', 'PurpleLeaves' => 'liście', 'Dzedai' => 'dzidowa', 'Water' => 'woda',
        'Crows' => 'kruki', 'Bow' => 'wstążka', 'Metal' => 'metalowa', 'RedThinLeaves' => 'kwiatki', 'Skull' => 'czaszka',
        'Fire' => 'ogień', 'Ice' => 'lód', 'Promium' => 'promium', 'Gold' => 'złota', 'Red' => 'czerwona',
        'Rainbow' => 'tęcza', 'Pink' => 'różowa', 'Simple' => 'prosta'
    ];
    const PROFILE_SWITCHES = [
        'BarOnTop' => ['Pasek na górze', 'Pasek z poziomem i walutami na górze profilu zamiast na dole.'],
        'BarOpacity' => ['Przeźroczysty pasek', 'Pasek ma przeźroczystość cieni zamiast stałej.'],
        'ShowWaifu' => ['Mini waifu', 'Karta waifu w prawym górnym rogu.'],
        'RoundAvatar' => ['Okrągły awatar', 'Zaokrąglony awatar, gdy nie ma ramki.'],
        'BorderColor' => ['Ramka na poziom', 'Ramka zależna od poziomu, tylko przy ramkach, które ją mają.'],
        'ShowAnime' => ['Anime', 'Panel statystyk anime.'],
        'ShowManga' => ['Manga', 'Panel statystyk mang.'],
        'ShowCards' => ['Karcianka', 'Panel statystyk karcianki.'],
        'ShowGallery' => ['Mini galeria', 'Panel mini galerii.'],
        'HalfGallery' => ['2 karty w mini galerii', 'Dwie duże karty w mini galerii zamiast sześciu.'],
        'Flip' => ['Zamiana paneli', 'Prawy i lewy panel zamienione miejscami.'],
        'ShowOverlay' => ['Nakładka', 'Widoczność nakładki.'],
        'ShowOverlayPro' => ['Ultra nakładka', 'Widoczność ultra nakładki.']
    ];
    const PROFILE_IMAGES = [
        'combined' => ['Tło i styl razem', '750 × 500'],
        'background' => ['Tło', '750 × 160'],
        'style' => ['Obrazek stylu', '750 × 340'],
        'overlay' => ['Nakładka', '750 × 402'],
        'premium' => ['Ultra nakładka', '750 × 500']
    ];
?>
<!DOCTYPE html>
<html lang="pl"<?=hudHtmlAttributes($user)?>>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
<?=metaTags('Profilator', 'Wygląd profilu bota Sanakan: ustaw styl, ramkę i panele, zobacz podgląd i skopiuj polecenie.', '/profilator/', 'home')?>
  <title>Profilator &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../img/favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../apple-touch-icon.png" />
  <link rel="manifest" href="../site.webmanifest" />
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=5b1bfc9d73" type="text/css" rel="stylesheet" />
  <link href="../css/palette.css?v=94c96c7c93" type="text/css" rel="stylesheet" />
  <link href="../css/account.css?v=66047e5fc2" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=2f5ac20877" type="text/css" rel="stylesheet" />
  <link href="../css/status.css?v=df34270dd6" type="text/css" rel="stylesheet" />
  <link href="../css/admin.css?v=41f5842dd2" type="text/css" rel="stylesheet" />
  <link href="../css/skins.css?v=50e6f40617" type="text/css" rel="stylesheet" />
  <link href="profilator.css?v=3c7247ce71" type="text/css" rel="stylesheet" />
  <script src="../js/appearance.js?v=2e6e831727"></script>
  <script src="../js/discovery-storage.js?v=5cd5704e81"></script>
  <link href="../css/seasonal-background.css?v=47cc796474" type="text/css" rel="stylesheet" />
</head>

<body class="state-page">
  <main class="content profilator-page" id="profilator" data-pw="../pw/np/" data-ready="<?=$ready ? '1' : '0'?>"
    data-levels="<?=e(json_encode((object)$levels))?>" data-prefix="<?=e($prefix)?>"
    data-nick="<?=e($user['name'] ?? '')?>" data-avatar="<?=e($avatar)?>" data-own="<?=$own ? '1' : '0'?>" data-csrf="<?=e($csrf)?>" data-me="<?=$user !== null ? 'me.php' : ''?>"
    data-color="<?=e($badge !== null ? (HUD_COLOR_HEX[$badge['key']] ?? '') : '')?>"
    data-art="../img/logo.webp" data-board="<?=$board !== null ? '../skalpel/assets/' . e(basename($board)) : ''?>" data-cards="../pw/">
    <header class="ex-header">
      <div class="ex-top">
        <a class="back hud-corners" href="../" title="Strona główna">&larr; Sanakan</a>
        <?=$accountMenu?>
<?php if ($user === null && authConfigured()): ?>
        <a class="home-login hud-corners" id="profile-login" href="<?=e(siteRoot() . 'account.php?login&back=' . rawurlencode(siteRoot() . 'profilator/'))?>" data-login="<?=e(siteRoot() . 'account.php?login&back=')?>">Zaloguj przez Discord</a>
<?php endif; ?>
      </div>
      <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
      <h1 class="hud-title">Profilator</h1>
      <p class="version-intro">Wygląd profilu bota: ustaw, zobacz i skopiuj polecenie <code>konfiguracja profilu kod</code></p>
    </header>

    <div class="profilator-grid">
      <section class="card profilator-preview">
        <h2><i>01</i>Podgląd</h2>
        <div class="profilator-canvas">
          <canvas id="profile-canvas" width="750" height="500" role="img" aria-label="Podgląd profilu"></canvas>
        </div>
<?php if (!$ready): ?>
        <p class="hint warn">Grafiki profilu nie zostały jeszcze pobrane z repozytorium bota, więc podgląd jest niepełny. Strona pobiera je co kilka godzin.</p>
<?php endif; ?>
        <p class="hint" id="profile-source">Układ, grafiki i czcionki są te same co w bocie. <?=$user !== null ? 'Wczytywanie twojego profilu z bota…' : 'Poziom, waluty, karty i statystyki z Shindena są przykładowe; po zalogowaniu zobaczysz swój profil.'?></p>

        <div class="profilator-command">
          <label class="hud-field">
            <span class="hud-field-label">Polecenie dla bota</span>
            <input class="field hud-corners" id="profile-command" type="text" readonly spellcheck="false" />
          </label>
          <button type="button" class="admin-btn primary" id="profile-copy">Kopiuj</button>
        </div>
        <p class="hint profilator-note" id="profile-note" aria-live="polite"></p>
        <p class="hint">Płacisz tylko za to, co się zmienia: styl lub nowy obrazek stylu kosztuje 3000 SC albo 1000 TC, nowa płatna ramka 25000 SC albo 8000 TC. Przełączniki i przeźroczystość są za darmo. Dopisz <code>TC</code> na końcu polecenia, żeby zapłacić w TC. Bot pokaże koszt i zapyta o potwierdzenie.</p>
      </section>

      <div class="profilator-settings">
        <section class="card">
          <h2><i>02</i>Kod</h2>
          <p class="hint">Kod obecnego profilu da ci bot poleceniem <code><?=e($prefix)?>konp kod</code>. Wklej go tutaj, żeby zobaczyć swój profil i zmieniać go dalej.</p>
          <label class="hud-field">
            <span class="hud-field-label">Kod profilu <span class="muted" id="code-state" aria-live="polite"></span></span>
            <input class="field hud-corners" id="code-input" type="text" maxlength="2000" spellcheck="false" autocomplete="off" placeholder="np. AQADJxke" />
          </label>
        </section>

        <section class="card">
          <h2><i>03</i>Styl i ramka</h2>
          <div class="hud-fields">
            <label class="hud-field">
              <span class="hud-field-label">Styl</span>
              <span class="hud-select">
                <select id="style-select">
<?php foreach (PROFILE_STYLES as $key => $label): ?>
                  <option value="<?=e($key)?>"><?=e($label)?></option>
<?php endforeach; ?>
                </select>
                <svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg>
              </span>
            </label>
            <label class="hud-field">
              <span class="hud-field-label">Ramka awatara</span>
              <span class="hud-select">
                <select id="border-select">
<?php foreach (PROFILE_BORDERS as $key => $label): ?>
                  <option value="<?=e($key)?>"><?=e($label)?></option>
<?php endforeach; ?>
                  <option value="TurqLeaves" hidden>turkusowe liście (specjalna)</option>
                </select>
                <svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg>
              </span>
            </label>
          </div>
          <p class="hint profilator-after" id="style-note"></p>
        </section>

        <section class="card">
          <h2><i>04</i>Elementy</h2>
          <p class="hint">Przygaszone przełączniki nic nie zmieniają na wybranym stylu, ale trafiają do kodu.</p>
          <div class="profilator-switches">
<?php foreach (PROFILE_SWITCHES as $key => [$label, $title]): ?>
            <label class="switch">
              <span class="switch-label"><?=e($label)?><small><?=e($title)?></small></span>
              <input type="checkbox" data-flag="<?=e($key)?>" />
              <span class="switch-state" aria-hidden="true">WYŁ</span>
              <span class="switch-track" aria-hidden="true"></span>
            </label>
<?php endforeach; ?>
          </div>
          <label class="hud-field profilator-range">
            <span class="hud-field-label">Przeźroczystość cieni <span class="muted" id="shadows-value"></span></span>
            <input class="player-range" id="shadows-range" type="range" min="0" max="100" step="1" />
          </label>
          <p class="hint profilator-after" id="shadows-note"></p>
        </section>

        <section class="card">
          <h2><i>05</i>Obrazki</h2>
          <p class="hint">Bez obrazków w kodzie zostają w przeglądarce, tylko do podglądu. Z nimi trafiają do folderu Profilator w twojej galerii, a kod dostaje ich linki: bot ustawi je razem z resztą i policzy każdy jak osobną zmianę obrazka. Tło i styl razem bot dzieli tak jak przy <code>konp jestem leniwy</code>.</p>
          <div class="profilator-images-head">
            <label class="switch">
              <span class="switch-label">Obrazki w kodzie</span>
              <input type="checkbox" id="images-in-code" />
              <span class="switch-state" aria-hidden="true">WYŁ</span>
              <span class="switch-track" aria-hidden="true"></span>
            </label>
<?php if (!$own): ?>
            <span class="hint">Wysyłanie obrazków wymaga zalogowania i własnego folderu w galerii (rola na serwerze bota).</span>
<?php endif; ?>
          </div>
          <ul class="profilator-images">
<?php foreach (PROFILE_IMAGES as $key => [$label, $size]): ?>
            <li data-image="<?=e($key)?>">
              <span class="profilator-image-name"><?=e($label)?> <span class="muted"><?=e($size)?> px</span></span>
              <span class="profilator-image-file muted" data-image-file>nie wybrano</span>
              <span class="hud-button hud-corners">Wybierz<input type="file" accept="image/*" aria-label="<?=e($label)?>: wybierz obrazek" /></span>
              <button type="button" class="admin-btn small" data-image-clear hidden>Usuń</button>
            </li>
<?php endforeach; ?>
          </ul>
        </section>
      </div>
    </div>
  </main>
  <footer class="site-foot"><span>&copy; 2017&ndash;<?=date('Y')?> Sniku</span><i aria-hidden="true">&middot;</i><a href="../state/">Status</a><i aria-hidden="true">&middot;</i><a href="../privacy/">Prywatność</a><i aria-hidden="true">&middot;</i><a href="../terms/">Zasady</a></footer>
  <script src="../js/account.js?v=9ad24ea77c"></script>
  <script src="../js/netsphere.js?v=adf9386966"></script>
  <script src="../js/safeguard.js?v=ec477d1123"></script>
  <script src="../js/seasonal-background.js?v=4ba6c522d2"></script>
  <script src="../js/palette.js?v=10c535e8a5"></script>
  <script src="profile.js?v=4665d8b9ea"></script>
  <script src="profilator.js?v=c568ccca70"></script>
</body>

</html>
