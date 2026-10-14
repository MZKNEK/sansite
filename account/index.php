<?php
    // The profile of the logged-in account, for itself only: who it is on the
    // bot's server, what it may open on the site, the devices it is logged in on
    // (each can be logged out, or all the others at once), what it did in the
    // gallery and its own history. The addresses the site keeps are left out;
    // the panel shows them and more in admin/?konto=ID.
    require __DIR__ . '/../inc/gallery.php';
    installDataErrorHandler();
    require __DIR__ . '/../inc/status-card.php';
    require __DIR__ . '/../inc/meta.php';

    const OWN_HISTORY_SHOWN = 60;
    const OWN_UPLOADS_SHOWN = 12;

    // a plain form: logging out a device or all the others, then back here
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = authConfigured() ? siteUser() : null;
        if (!$user || !checkCsrf()) {
            setFlash('Sesja wygasła, spróbuj jeszcze raz.');
        } else if (($_POST['action'] ?? '') === 'logout-device') {
            // only a device of this account, and never the one asking
            $key = (string)($_POST['session'] ?? '');
            $devices = accountDevices($user['id']);
            if ($key === sessionKey(session_id()))
                setFlash('To urządzenie, z którego teraz korzystasz. Wyloguj się z menu konta.');
            else if (!isset($devices[$key]) || !endDevice($user['id'], $key))
                setFlash('Tego urządzenia już nie ma na liście.');
            else {
                addHistory('sessions', 'Wylogowano się na jednym urządzeniu (' . deviceName($devices[$key][4]) . ').');
                setFlash('Wylogowano urządzenie.');
            }
        } else if (($_POST['action'] ?? '') === 'logout-others') {
            // like "log out everyone" of the panel, for this account: its other
            // sessions end, this one stays
            if (!endSessions($user['id'], true)) {
                setFlash('Nie udało się zapisać, spróbuj później.');
            } else {
                addHistory('sessions', 'Wylogowano się na pozostałych urządzeniach.');
                setFlash('Wylogowano pozostałe urządzenia.');
            }
        } else if (($_POST['action'] ?? '') === 'hud') {
            $mode = (string)($_POST['hud'] ?? '');
            // the colour is picked from the role user up; the others keep their own
            $color = canPickHudColor($user['id'], roleBadge(siteRoles())) ? (string)($_POST['hudcolor'] ?? 'own') : 'own';
            // the snow's switch shows only while it snows (js/safeguard.js), but
            // goes with the form all year, so it keeps what was picked
            $snowOff = !isset($_POST['snow']);
            $snowChanged = $snowOff !== snowOff($user['id']);
            if (!setHud($user['id'], $mode, $color) || ($snowChanged && !setSnowOff($user['id'], $snowOff))) {
                setFlash('Nie udało się zapisać wyglądu, spróbuj później.');
            } else {
                addHistory('hud', 'Zmieniono wygląd na: ' . HUD_MODES[$mode] . ($mode !== 'default' ? ', ' . lower(HUD_COLORS[$color]) : '') . '.');
                if ($snowChanged)
                    addHistory('hud', $snowOff ? 'Wyłączono śnieg.' : 'Włączono śnieg.');
                sendSnowCookie($user['id']);
                setFlash('Zapisano wygląd.');
            }
        } else if (($_POST['action'] ?? '') === 'skin') {
            $skin = (string)($_POST['skin'] ?? '');
            $before = skinPrefs($user['id']);
            // The picker marks whether the selected skin offers its switch.
            // Without it the stored words preference stays; older forms fall
            // back to the switch the server originally rendered.
            $hadSwitch = isset($_POST['skinwords_present'])
                ? $_POST['skinwords_present'] === 'on'
                : ($before['skin'] === '' || skinSpeaks($before['skin']));
            $words = $hadSwitch ? isset($_POST['skinwords']) : $before['words'];
            $custom = $skin === 'custom' ? ['image' => $_POST['customimage'] ?? '', 'colors' => $_POST['customcolors'] ?? null, 'base' => $_POST['custombase'] ?? 'dark'] : null;
            if (!setSkin($user['id'], $skin, $words, $custom)) {
                setFlash($skin === 'custom' && skinUnlocked('custom', $user['id'])
                    ? 'Nie udało się zapisać skina. Podaj kolory jako #RRGGBB i wybierz dostępny obrazek z własnej galerii.'
                    : 'Ten skin jest jeszcze zablokowany.');
            } else {
                if ($skin === 'custom')
                    addHistory('skin', 'Zapisano własny skin.');
                else if ($skin !== $before['skin'])
                    addHistory('skin', $skin === '' ? 'Wyłączono skin.' : 'Zmieniono skin na: ' . SKINS[$skin]['name'] . '.');
                if ($skin !== '' && $words !== $before['words'])
                    addHistory('skin', $words ? 'Włączono tłumaczenie strony głównej w skinie.' : 'Wyłączono tłumaczenie strony głównej w skinie.');
                sendSkinCookie($user['id']);
                setFlash($skin === '' ? 'Wyłączono skin.' : 'Włączono skin ' . SKINS[$skin]['name'] . ' — zobaczysz go na stronie głównej.');
            }
        } else if (($_POST['action'] ?? '') === 'ranking') {
            // the ranking of the hunters (/hunters/) shows the account unless it left it
            $hide = !isset($_POST['ranking']);
            if ($hide === hunterHidden($user['id']))
                setFlash('Bez zmian.');
            else if (!setHunterHidden($user, $hide))
                setFlash('Nie udało się zapisać, spróbuj później.');
            else {
                addHistory('ranking', $hide ? 'Wypisano się z rankingu łowców.' : 'Wrócono do rankingu łowców.');
                setFlash($hide ? 'Konta nie ma już w rankingu łowców.' : 'Konto wróciło do rankingu łowców.');
            }
        }
        header('Location: ./', true, 303);
        exit;
    }

    handleLoginRequest(siteRoot() . 'account/');

    $user = siteUser();
    $flash = takeFlash();
    if (!$user)
        // the login page itself is a 200, as Discord shows no link preview for a 401
        http_response_code(!authConfigured() ? 503 : 200);
    header('Cache-Control: private, no-store');

    if ($user) {
        $id = (string)$user['id'];
        $roles = siteRoles();
        $badge = roleBadge($roles);
        $rolesChecked = readData('roles')[$id]['checked'] ?? null;
        $login = readData('logins')[$id] ?? null;
        $csrf = siteCsrf();

        // what it may open: [label, yes, why]
        $since = function ($list) use ($id) {
            $added = panelList($list)[$id]['added'] ?? 0;
            return $added ? 'nadany ' . date('j.m.Y', $added) : 'nadany w panelu';
        };
        $why = function ($list) use ($id, $since) {
            $config = configList(ACCESS_LISTS[$list]);
            if ($config === true)
                return 'dla każdego zalogowanego';
            return in_array($id, $config, true) ? 'na stałe' : $since($list);
        };
        $access = [];
        if (isPanelAdminId($id))
            $access[] = ['Panel administratora', true, 'na stałe'];
        if (isGalleryAdminId($id))
            $access[] = ['Galeria', true, 'oglądanie i zarządzanie plikami, ' . $why('galleryAdmins')];
        else if (canViewGalleryId($id))
            $access[] = ['Galeria', true, 'oglądanie, ' . $why('galleryViewers')];
        else if (!isGalleryUploaderId($id))
            $access[] = ['Galeria', false, ''];
        if (!isGalleryAdminId($id) && isGalleryUploaderId($id)) {
            $limit = userFilesLimit($id);
            $access[] = ['Własny folder w galerii', true, 'do ' . $limit . ' ' . plural($limit, 'zdjęcia', 'zdjęć', 'zdjęć') . ', widzisz go tylko ty i administratorzy galerii, ' . $why('galleryUploaders')];
        }
        if (canSeePrivateGalleryId($id))
            $access[] = ['Prywatny folder w galerii', true, isPanelAdminId($id) ? 'jako administrator panelu' : $why('galleryPrivate')];
        if (isPanelAdminId($id))
            $access[] = ['Dokumentacja API', true, 'jako administrator panelu'];
        else if (inAccessList('apiViewers', $id, true))
            $access[] = ['Dokumentacja API', true, $why('apiViewers')];
        else if (hasBotRole($id, API_ROLES))
            $access[] = ['Dokumentacja API', true, 'przez rolę ' . ($badge['name'] ?? '') . ' na serwerze bota'];
        else
            $access[] = ['Dokumentacja API', false, ''];
        $access[] = ['Polecenia moderatorskie i debug', canSeePrivateCommandsId($id), ''];

        $requests = [];
        foreach (REQUEST_LABELS as $for => $label)
            if ($request = pendingRequest($for, $id))
                $requests[] = $request + ['for' => $for];

        $devices = accountDevices($id);
        uasort($devices, function ($a, $b) { return $b[1] <=> $a[1]; });
        $thisDevice = sessionKey(session_id());

        [$history, $gallery, $uploads] = accountActivity($id, false, OWN_HISTORY_SHOWN, OWN_UPLOADS_SHOWN);
        $galleryRoot = dirname(__DIR__) . '/i/';
        $showGallery = canViewGalleryId($id) || isGalleryUploaderId($id) || array_sum($gallery) > 0;
        $finds = safeguardFinds($id);
        // the name and avatar the ranking of the hunters shows, as they are now
        if ($finds)
            noteHunter($user);
    }
?>
<!DOCTYPE html>
<html lang="pl"<?=hudHtmlAttributes($user)?>>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex" />
<?=metaTags('Profil', 'Twoje konto na stronie Sanakan: role, dostęp i urządzenia, na których jesteś zalogowany.', '/account/', 'account')?>
  <title>Profil &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../apple-touch-icon.png" />
  <link rel="manifest" href="../site.webmanifest" />
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=0d6c821266" type="text/css" rel="stylesheet" />
  <link href="../css/palette.css?v=3bb1398e4e" type="text/css" rel="stylesheet" />
  <link href="../css/account.css?v=66047e5fc2" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=08cb01faf8" type="text/css" rel="stylesheet" />
  <link href="../css/status.css?v=c4ddf09879" type="text/css" rel="stylesheet" />
  <link href="../css/admin.css?v=76c08c2c33" type="text/css" rel="stylesheet" />
  <link href="../css/skins.css?v=2627f8bdee" type="text/css" rel="stylesheet" />
  <link href="../css/seasonal-background.css?v=bd96103a30" type="text/css" rel="stylesheet" />
</head>

<body class="admin-page own-profile">
  <main class="content">
    <header class="ex-header">
      <div class="ex-top">
        <a class="back hud-corners" href="../" title="Strona główna">&larr; Sanakan</a>
<?php if ($user): ?>
        <?=accountMenuHtml($user, $roles, $_SERVER['REQUEST_URI'] ?? '')?>
<?php endif; ?>
      </div>
      <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
      <h1 class="hud-title">Profil</h1>
    </header>

<?php if (!$user): ?>
    <section class="locked hud-corners">
      <svg class="lock-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="1.5" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
<?php if (!authConfigured()): ?>
      <h2>Profil jest wyłączony</h2>
      <p>Logowanie nie jest jeszcze skonfigurowane na serwerze.</p>
<?php else: ?>
      <h2>Profil wymaga logowania</h2>
      <p>Zaloguj się kontem Discord, żeby zobaczyć, co strona o nim zapisuje.</p>
      <a class="admin-btn primary" href="?login">Zaloguj przez Discord</a>
<?php endif; ?>
    </section>
<?php else: ?>
    <div class="panel-grid">
      <section class="card wide profile">
        <h2><i>01</i>Konto</h2>
        <div class="profile-head">
          <img src="<?=e($user['avatar'])?>" alt="" width="64" height="64" />
          <div>
            <b class="profile-name"><?=e($user['name'])?></b>
<?php if (!empty($login['username'])): ?>
            <span class="muted">@<?=e($login['username'])?></span>
<?php endif; ?>
            <code><?=e($id)?></code>
<?php if ($badge): ?>
            <span class="lv role-<?=e($badge['key'])?>" title="<?=e($badge['title'])?>">LV.<?=$badge['level']?> <?=e($badge['name'])?></span>
<?php endif; ?>
          </div>
        </div>
        <dl class="server">
          <dt>Role na serwerze bota</dt>
          <dd><?=$roles === null ? 'nieznane (bot nie odpowiedział)' : e(roleNames($roles) ? implode(', ', roleNames($roles)) : $badge['name']) . ($rolesChecked ? ' <span class="muted">(sprawdzone ' . e(ago($rolesChecked)) . ')</span>' : '')?></dd>

<?php if ($login): ?>
          <dt>Logowania</dt>
          <dd><?=(int)($login['count'] ?? 0)?>&times;, ostatnio <?=e(ago($login['last'] ?? 0))?></dd>
<?php endif; ?>
        </dl>
      </section>

      <section class="card wide">
        <h2><i>02</i>Dostępy</h2>
        <dl class="server">
<?php foreach ($access as [$label, $yes, $from]): ?>
          <dt><?=e($label)?></dt>
          <dd><?=$yes ? '<b class="profile-yes">tak</b>' . ($from !== '' ? ' <span class="muted">(' . e($from) . ')</span>' : '') : '<span class="muted">nie</span>'?></dd>
<?php endforeach; ?>
<?php foreach ($requests as $request): ?>
          <dt>Prośba o dostęp do <?=e(REQUEST_LABELS[$request['for']])?></dt>
          <dd>wysłana <?=e(ago($request['time']))?><?=($request['note'] ?? '') !== '' ? ': „' . e($request['note']) . '”' : ''?> <span class="muted">(czeka na administratora)</span></dd>
<?php endforeach; ?>
        </dl>
        <p class="hint">O dostęp do galerii albo do dokumentacji API można poprosić na ich stronach: <a href="../i/">galeria</a>, <a href="../api/">API</a>.</p>
      </section>

      <section class="card wide">
        <h2><i>03</i>Zalogowane urządzenia</h2>
        <p class="hint">Przeglądarki, w których to konto jest teraz zalogowane. Logowanie trwa <?=SESSION_DAYS?> dni. Nieznane urządzenie lepiej wylogować.</p>
<?php if (!$devices): ?>
        <p class="nobody">Żadnych na liście: urządzenia są notowane przy wejściach na stronę.</p>
<?php else: ?>
        <ul class="devices">
<?php foreach ($devices as $key => [$since, $last, $ip, $cc, $agent]): ?>
          <li>
            <span class="device-name" title="<?=e($agent)?>"><?=e(deviceName($agent))?><?=(string)$key === $thisDevice ? ' <span class="role protected">to urządzenie</span>' : ''?></span>
            <span class="muted">zalogowane <?=e(date('j.m H:i', $since))?> &middot; ostatnio <?=e(ago($last))?></span>
<?php if ((string)$key !== $thisDevice): ?>
            <form method="post" action="./">
              <input type="hidden" name="csrf" value="<?=e($csrf)?>" />
              <input type="hidden" name="action" value="logout-device" />
              <input type="hidden" name="session" value="<?=e($key)?>" />
              <button type="submit" class="admin-btn small danger">Wyloguj</button>
            </form>
<?php endif; ?>
          </li>
<?php endforeach; ?>
        </ul>
<?php if (count($devices) > 1): ?>
        <form class="devices-all" method="post" action="./">
          <input type="hidden" name="csrf" value="<?=e($csrf)?>" />
          <input type="hidden" name="action" value="logout-others" />
          <button type="submit" class="admin-btn danger">Wyloguj na pozostałych urządzeniach</button>
        </form>
<?php endif; ?>
<?php endif; ?>
      </section>

<?php if ($showGallery): ?>
      <section class="card wide">
        <h2><i>04</i>Galeria</h2>
<?php if (isGalleryUploaderId($id)): ?>
        <h3>Limity i miejsce w galerii</h3>
        <p class="hint">Ile zajmuje twój folder względem jego limitów. Limity ustawia administrator; usunięte pliki leżą w koszu <?=TRASH_DAYS?> dni i do tego czasu zajmują jego miejsce.</p>
        <?php galleryUsageHtml($galleryRoot, $id); ?>
        <h3>Historia w galerii</h3>
<?php endif; ?>
        <p class="hint">Co konto zrobiło w galerii, z historii zmian strony (ostatnie <?=HISTORY_KEEP?> wpisów).</p>
<?php if (!array_sum($gallery)): ?>
        <p class="nobody">Nic jeszcze nie zmieniło.</p>
<?php else: ?>
        <div class="stats-summary">
<?php foreach (GALLERY_ACTIONS as $action => $label): if (!$gallery[$action]) continue; ?>
          <span><b><?=formatCount($gallery[$action])?></b> <?=e($label)?></span>
<?php endforeach; ?>
        </div>
<?php if ($uploads): ?>
        <div class="stats-grid">
          <div>
            <h3>Ostatnio dodane</h3>
            <ul class="stat-list">
<?php foreach ($uploads as $upload): $there = is_file($galleryRoot . $upload['rel']); ?>
              <li style="--share: 0"><?=$there ? '<a href="' . e(siteRoot() . 'i/' . fileUrl($upload['rel'])) . '" target="_blank" rel="noopener">' . e('i/' . $upload['rel']) . '</a>' : '<span class="muted" title="Plik przeniesiono albo usunięto">' . e('i/' . $upload['rel']) . '</span>'?><span><?=e(date('j.m.Y H:i', $upload['time']))?></span></li>
<?php endforeach; ?>
            </ul>
          </div>
        </div>
<?php endif; ?>
<?php endif; ?>
      </section>
<?php endif; ?>

      <section class="card wide">
        <h2><i><?=$showGallery ? '05' : '04'?></i>Historia</h2>
        <p class="hint">Co konto zmieniło na stronie.</p>
<?php if (!$history): ?>
        <p class="nobody">Nic.</p>
<?php else: ?>
        <ol class="history own-history">
<?php foreach ($history as $entry): ?>
          <li>
            <time datetime="<?=e(date('c', $entry['time'] ?? 0))?>"><?=e(date('j.m H:i', $entry['time'] ?? 0))?></time>
            <span class="history-text"><?=e($entry['text'] ?? '')?></span>
          </li>
<?php endforeach; ?>
        </ol>
<?php endif; ?>
      </section>

      <section class="card wide">
        <h2><i><?=$showGallery ? '06' : '05'?></i>Wygląd</h2>
        <p class="hint" data-default-skin-only>Kolory domyślnego skina. W wariancie <b>kolor tylko w akcentach</b> wybrany kolor wchodzi w narożniki, linki, wyszukiwarkę i menu konta, a <b>na całym HUD</b> także w tło, poświatę tytułu i linie pod SAFEGUARD. Kolor wybiera konto z rolą na serwerze bota (od user w górę); bez wyboru jest to kolor jego roli. Zmiana widoczna od razu, zapisuje się przyciskiem.</p>
        <form method="post" action="./" class="hud-form">
          <input type="hidden" name="csrf" value="<?=e($csrf)?>" />
          <input type="hidden" name="action" value="hud" />
<?php $hudNow = hudMode($id); $colorNow = hudColor($id); $ownHex = HUD_COLOR_HEX[$badge['key'] ?? ''] ?? '#b670d3'; ?>
          <div class="hud-fields" data-default-skin-only>
            <label class="hud-field">
              <span class="hud-field-label">Wariant</span>
              <span class="hud-select">
                <select name="hud" data-hud-choice>
<?php foreach (HUD_MODES as $key => $label): ?>
                  <option value="<?=e($key)?>"<?=$hudNow === $key ? ' selected' : ''?>><?=e($label)?></option>
<?php endforeach; ?>
                </select>
                <svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg>
              </span>
            </label>
<?php if (canPickHudColor($id, $badge)): ?>
            <label class="hud-field">
              <span class="hud-field-label">Kolor<i class="hud-dot" data-hud-dot></i></span>
              <span class="hud-select">
                <select name="hudcolor" data-hud-color>
<?php foreach (HUD_COLORS as $key => $label): $hex = $key === 'own' ? $ownHex : HUD_COLOR_HEX[$key]; ?>
                  <option value="<?=e($key)?>" data-hex="<?=e($hex)?>"<?=$colorNow === $key ? ' selected' : ''?>><?=e($label)?></option>
<?php endforeach; ?>
                </select>
                <svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg>
              </span>
            </label>
<?php endif; ?>
          </div>
          <label class="admin-check snow-field" data-snow-pref hidden>
            <input type="checkbox" name="snow" value="on"<?=snowOff($id) ? '' : ' checked'?> />
            <span>Śnieg na stronie zimą <span class="muted">(od 20 grudnia do 20 marca)</span></span>
          </label>
          <button type="submit" class="admin-btn primary">Zapisz wygląd</button>
        </form>
        <h3>Skin</h3>
        <p class="hint">Skin zmienia stronę główną: jej kolory, logo i, jeśli skin je ma, napisy. Skiny odblokowują sekrety z rejestru Safeguarda; właściciel strony ma je wszystkie od razu, również własny skin. Własny skin jest nagrodą za zebranie wszystkich zwykłych i sezonowych znajdziek. Kolory widać od razu na tej stronie, całość na stronie głównej.</p>
<?php $customUnlocked = skinUnlocked('custom', $id); ?>
<?php if (!$customUnlocked): ?>
        <p class="hint">Do własnego skina: zwykłe znajdźki <b><?=count(array_intersect_key($finds, SAFEGUARD_FINDS))?> / <?=count(SAFEGUARD_FINDS)?></b>, sezonowe <b><?=count(array_intersect_key($finds, SAFEGUARD_SEASONAL))?> / <?=count(SAFEGUARD_SEASONAL)?></b>.</p>
<?php endif; ?>
        <form method="post" action="./" class="skin-form">
          <input type="hidden" name="csrf" value="<?=e($csrf)?>" />
          <input type="hidden" name="action" value="skin" />
<?php $skinNow = skinPref($id); $skinSwitch = $skinNow === '' || skinSpeaks($skinNow); $skinGroups = []; foreach (SKINS as $key => $skin) { $skinGroups[(string)$skin['find']][] = $key; } ?>
          <label class="hud-field" data-skin-native>
            <span class="hud-field-label">Skin</span>
            <span class="hud-select">
              <select name="skin" data-skin-choice>
                <option value="">Domyślny (fiolet strony)</option>
<?php foreach ($skinGroups as $find => $keys): ?>
                <optgroup label="<?=e($find === '' ? 'Zawsze' : (SKIN_GROUPS[$find] ?? (SAFEGUARD_FINDS[$find][0] ?? SAFEGUARD_SEASONAL[$find][0] ?? $find)))?>">
<?php foreach ($keys as $key): $entry = SKINS[$key]; $unlocked = skinUnlocked($key, $id); ?>
<?php if ($unlocked): ?>
                  <option value="<?=e($key)?>" data-skin-words="<?=skinSpeaks($key) ? 'true' : 'false'?>"<?=$skinNow === $key ? ' selected' : ''?>><?=e($entry['name'])?> &middot; <?=e($entry['what'])?></option>
<?php elseif ($key === 'custom'): ?>
                  <option value="custom" disabled>Własny skin &middot; zablokowany: wszystkie zwykłe i sezonowe znajdźki</option>
<?php else: ?>
                  <option value="" disabled>??? &middot; zablokowany: <?=e($find !== '' ? (SAFEGUARD_FINDS[$find][0] ?? SAFEGUARD_SEASONAL[$find][0] ?? SKIN_GROUPS[$find] ?? $find) : '')?></option>
<?php endif; ?>
<?php endforeach; ?>
                </optgroup>
<?php endforeach; ?>
              </select>
              <svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg>
            </span>
          </label>
          <div class="skin-current" data-skin-current hidden>
            <img src="../logo.webp" alt="" width="64" height="64" data-skin-current-image />
            <div class="skin-current-text">
              <span class="hud-field-label">Wybrany skin</span>
              <b data-skin-current-name></b>
              <p data-skin-current-description></p>
            </div>
            <button type="button" class="admin-btn" data-skin-open aria-haspopup="dialog" aria-controls="skin-picker">Zmień skin</button>
          </div>
          <dialog class="skin-dialog" id="skin-picker" data-skin-dialog aria-labelledby="skin-picker-title" aria-describedby="skin-picker-hint">
            <div class="skin-dialog-head">
              <div>
                <span class="tag">Wygląd strony</span>
                <h2 id="skin-picker-title">Wybierz skin</h2>
              </div>
              <button type="button" class="skin-dialog-close" data-skin-cancel aria-label="Zamknij wybór skina"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg></button>
            </div>
            <p class="skin-dialog-hint" id="skin-picker-hint">Kliknij skin, aby podejrzeć jego kolory. Pełny opis znajdziesz pod kafelkami.</p>
            <div class="skin-dialog-toolbar">
              <div class="skin-filters" role="group" aria-label="Pokaż skiny">
                <button type="button" data-skin-filter="unlocked" aria-pressed="true">Odblokowane</button>
                <button type="button" data-skin-filter="all" aria-pressed="false">Wszystkie</button>
              </div>
              <span class="skin-count" data-skin-count aria-live="polite"></span>
            </div>
            <div class="skin-grid" data-skin-grid role="group" aria-label="Dostępne skiny">
<?php
    $panelSkins = ['' => ['name' => 'Domyślny', 'find' => null, 'what' => 'Fiolet strony i oryginalne logo Sanakan.'], 'custom' => SKINS['custom']] + SKINS;
    foreach ($panelSkins as $key => $entry):
        $unlocked = $key === '' || skinUnlocked($key, $id);
        $name = $unlocked || $key === 'custom' ? $entry['name'] : '???';
        $group = $key === '' ? 'Sanakan' : ($key === 'custom' ? 'Komplet znajdziek' : (SKIN_GROUPS[$entry['find']] ?? ''));
        $description = $unlocked ? $entry['what'] : ($key === 'custom' ? 'Zbierz wszystkie zwykłe i sezonowe znajdźki.' : 'Odkryj: ' . (SAFEGUARD_FINDS[$entry['find']][0] ?? SAFEGUARD_SEASONAL[$entry['find']][0] ?? ''));
        $thumbnail = $key === '' ? '../logo.webp' : ($key === 'custom' || !$unlocked ? '' : '../skins/' . $key . '.webp');
?>
              <button type="button" class="skin-tile"<?=$unlocked ? '' : ' disabled title="' . e($description) . '"'?><?=$key === 'custom' ? ' data-skin-custom' : ''?><?=$unlocked || $key === 'custom' ? ' data-skin-tile="' . e($key) . '"' : ' data-skin-locked'?> data-skin-name="<?=e($name)?>" data-skin-description="<?=e($description)?>" data-skin-thumbnail="<?=e($thumbnail)?>" aria-pressed="<?=$unlocked && $skinNow === $key ? 'true' : 'false'?>">
                <span class="skin-tile-art" aria-hidden="true">
<?php if (!$unlocked): ?>
                  <svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" /></svg>
<?php elseif ($key === 'custom'): ?>
                  <svg viewBox="0 0 24 24" data-skin-custom-icon><path d="m14 5 5 5M4 20l5-1L21 7l-5-5L4 14z" /></svg>
                  <img alt="" width="56" height="56" data-skin-tile-image hidden />
<?php else: ?>
                  <img alt="" width="56" height="56" data-skin-tile-image />
<?php endif; ?>
                </span>
                <b><?=e($name)?></b>
                <span class="skin-tile-group"><?=e($group)?></span>
<?php if ($unlocked): ?>
                <span class="skin-swatches" aria-hidden="true"><i></i><i></i><i></i></span>
<?php elseif ($key === 'custom'): ?>
                <span class="skin-tile-status"><?=count($finds)?> / <?=count(SAFEGUARD_FINDS + SAFEGUARD_SEASONAL)?> znajdziek</span>
<?php else: ?>
                <span class="skin-tile-status">Zablokowany</span>
<?php endif; ?>
                <span class="skin-tile-check" aria-hidden="true">&#10003;</span>
              </button>
<?php endforeach; ?>
            </div>
            <div class="skin-selection" aria-live="polite" aria-atomic="true">
              <b data-skin-selection-name></b>
              <p data-skin-selection-description></p>
            </div>
            <div class="skin-dialog-foot">
              <span>Po wyborze kliknij „Zapisz skin” w profilu.</span>
              <div>
                <button type="button" class="admin-btn" data-skin-cancel>Anuluj</button>
                <button type="button" class="admin-btn primary" data-skin-confirm>Wybierz</button>
              </div>
            </div>
          </dialog>
<?php if ($customUnlocked): $customPrefs = customSkinPrefs($id); $customBase = str_replace('\\', '/', realpath($galleryRoot)); $customImage = customSkinImage($id, $customPrefs['image'], $customBase); $customImages = customSkinImages($id, $customBase); ?>
          <fieldset class="custom-skin" data-custom-editor data-custom-palettes="<?=e(json_encode(customSkinPalettes(), JSON_UNESCAPED_SLASHES))?>"<?=$skinNow === 'custom' ? '' : ' hidden disabled'?>>
            <legend>Własny skin</legend>
            <p class="hint">Komplet znajdziek odblokował twój własny wygląd. Wpisz kolory w formacie #RRGGBB lub wybierz je próbnikiem. Zmiany podejrzysz od razu; zapisze je przycisk „Zapisz skin”.</p>
            <div class="custom-skin-image">
              <img src="<?=e($customImage === null ? '../logo.webp' : siteRoot() . 'i/' . fileUrl($customImage))?>" alt="Podgląd obrazka własnego skina" width="112" height="112" data-custom-logo />
              <label class="hud-field">
                <span class="hud-field-label">Obrazek z własnej galerii</span>
                <span class="hud-select">
                  <select name="customimage" data-custom-image>
                    <option value="">Domyślne logo Sanakan</option>
<?php foreach ($customImages as $image => $name): ?>
                    <option value="<?=e($image)?>" data-image-url="<?=e(siteRoot() . 'i/' . fileUrl($image))?>"<?=$customImage === $image ? ' selected' : ''?>><?=e($name)?></option>
<?php endforeach; ?>
                  </select>
                  <svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg>
                </span>
                <span class="hint"><?php if (!$customImages): ?>Brak dostępnych obrazków w twoim folderze. <?php endif; ?><a href="../i/" target="_blank" rel="noopener">Otwórz galerię</a> — po dodaniu obrazka odśwież profil.</span>
              </label>
            </div>
            <div class="hud-fields">
<?php foreach (CUSTOM_SKIN_COLORS as $key => $label): $default = customSkinPalettes()[$customPrefs['base']][$key]; ?>
              <div class="hud-field">
                <label class="hud-field-label" for="custom-<?=e($key)?>"><?=e($label)?></label>
                <div class="custom-skin-color">
                  <input type="color" value="<?=e($customPrefs['colors'][$key])?>" aria-label="<?=e('Wybierz kolor: ' . $label)?>" data-custom-swatch="<?=e($key)?>" />
                  <input type="text" id="custom-<?=e($key)?>" name="customcolors[<?=e($key)?>]" value="<?=e($customPrefs['colors'][$key])?>" pattern="#[0-9a-fA-F]{6}" maxlength="7" placeholder="<?=e($default)?>" title="Kolor w formacie #RRGGBB, np. #9b59b6" required spellcheck="false" autocomplete="off" data-custom-color="<?=e($key)?>" />
                </div>
              </div>
<?php endforeach; ?>
              <label class="hud-field">
                <span class="hud-field-label">Baza</span>
                <span class="hud-select">
                  <select name="custombase" data-custom-base>
                    <option value="dark"<?=$customPrefs['base'] === 'dark' ? ' selected' : ''?>>Ciemna</option>
                    <option value="light"<?=$customPrefs['base'] === 'light' ? ' selected' : ''?>>Jasna</option>
                  </select>
                  <svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg>
                </span>
              </label>
            </div>
          </fieldset>
<?php endif; ?>
          <input type="hidden" name="skinwords_present" value="<?=$skinSwitch ? 'on' : 'off'?>" data-skin-words-present />
          <label class="admin-check skin-words"<?=$skinSwitch ? '' : ' hidden'?>>
            <input type="checkbox" name="skinwords" value="on"<?=$skinSwitch ? '' : ' disabled'?><?=skinWords($id) ? ' checked' : ''?> />
            <span>Tłumaczenie strony głównej <span class="muted">(napisy skina; bez tego zostają kolory i logo)</span></span>
          </label>
          <button type="submit" class="admin-btn primary">Zapisz skin</button>
        </form>
      </section>

      <section class="card wide" id="rejestr" data-safeguard-register data-found="<?=e(implode(' ', array_keys($finds)))?>">
        <h2><i><?=$showGallery ? '07' : '06'?></i>Rejestr Safeguarda<span class="card-sum"><?=count(array_intersect_key($finds, SAFEGUARD_FINDS))?> / <?=count(SAFEGUARD_FINDS)?></span></h2>
        <p class="hint">Sekrety strony, które to konto już odkryło. Te znalezione bez logowania zostają w przeglądarce i trafiają tutaj po zalogowaniu. Reszta czeka, a Safeguard daje tylko wskazówki. Kto znalazł najwięcej: <a href="../hunters/">ranking łowców</a>.</p>
<?php if ($flag = safeguardFlag($id)): ?>
        <p class="register-flag">Safeguard uznał wpisy tego konta za oszukane: <?=e($flag['reason'] ?? '')?> W rankingu łowców konto jest na końcu, bez miejsca.</p>
<?php endif; ?>
        <ol class="register">
<?php foreach (SAFEGUARD_FINDS as $key => [$name, $what, $hint]): $time = $finds[$key] ?? null; ?>
<?php if ($time !== null): ?>
          <li class="found">
            <b><?=e($name)?></b>
            <span><?=e($what)?></span>
            <time datetime="<?=e(date('c', $time))?>"><?=e(date('j.m.Y H:i', $time))?></time>
          </li>
<?php else: ?>
          <li>
            <b>Wpis niezidentyfikowany</b>
            <span><?=e($hint)?></span>
          </li>
<?php endif; ?>
<?php endforeach; ?>
        </ol>
        <h3>Sezonowe</h3>
        <p class="hint">Tylko w swoim czasie w roku, poza licznikiem.</p>
        <ol class="register">
<?php foreach (SAFEGUARD_SEASONAL as $key => [$name, $what, $when]): $time = $finds[$key] ?? null; $soon = safeguardHolidayIn($key, new DateTimeImmutable('now')); ?>
<?php if ($time !== null): ?>
          <li class="found">
            <b><?=e($name)?></b>
            <span><?=e($what)?></span>
            <time datetime="<?=e(date('c', $time))?>"><?=e(date('j.m.Y H:i', $time))?></time>
          </li>
<?php else: ?>
          <li<?=$soon === 0 ? ' class="now"' : ''?>>
            <b><?=e($name)?></b>
            <span><?=e($when)?></span>
            <small class="register-soon"><?=e(safeguardHolidayWhen($soon))?></small>
          </li>
<?php endif; ?>
<?php endforeach; ?>
        </ol>
        <h3>Ranking łowców</h3>
        <form method="post" action="./" class="ranking-form">
          <input type="hidden" name="csrf" value="<?=e($csrf)?>" />
          <input type="hidden" name="action" value="ranking" />
          <label class="admin-check">
            <input type="checkbox" name="ranking" value="on"<?=hunterHidden($id) ? '' : ' checked'?> />
            <span>Pokazuj to konto w <a href="../hunters/">rankingu łowców</a> <span class="muted">(nazwa, awatar, rola i znaleziska, widoczne dla każdego)</span></span>
          </label>
          <button type="submit" class="admin-btn">Zapisz</button>
        </form>
      </section>

      <p class="hint own-note">Tak wyglądają dane tego konta zapisane przez stronę. Więcej w <a href="../privacy/">informacji o prywatności</a>. O usunięcie danych można poprosić pod adresem <a href="mailto:privacy@sanakan.pl">privacy@sanakan.pl</a>.</p>
    </div>
<?php endif; ?>
  </main>
  <footer class="site-foot"><span>&copy; 2017&ndash;<?=date('Y')?> Sniku</span><i aria-hidden="true">&middot;</i><a href="../state/">Status</a><i aria-hidden="true">&middot;</i><a href="../privacy/">Prywatność</a></footer>

<?php if ($flash): ?>
  <div class="toast" id="toast" role="status"><?=e($flash)?></div>
<?php else: ?>
  <div class="toast" id="toast" role="status" hidden></div>
<?php endif; ?>
  <script src="../js/sanakan-util.js?v=f417e538a8"></script>
  <script src="../js/explorer.js?v=0247b47c99"></script>
  <script src="../js/appearance.js?v=c787589afa"></script>
  <script src="../js/skin-picker.js?v=d04ca5eaa7"></script>
  <script src="../js/appearance-picker.js?v=cfa79b055d"></script>
  <script src="../js/account.js?v=9ad24ea77c"></script>
  <script src="../js/netsphere.js?v=a98676efe2"></script>
  <script src="../js/safeguard.js?v=060589dc14"></script>
  <script src="../js/seasonal-background.js?v=07fa3ad00c"></script>
  <script src="../js/palette.js?v=9d395acc33"></script>
</body>

</html>
