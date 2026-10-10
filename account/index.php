<?php
    // The profile of the logged-in account, for itself only: who it is on the
    // bot's server, what it may open on the site, the devices it is logged in on
    // (each can be logged out, or all the others at once), what it did in the
    // gallery and its own history. The addresses the site keeps are left out;
    // the panel shows them and more in admin/?konto=ID.
    require __DIR__ . '/../inc/gallery.php';
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
            // like "log out everyone" of the panel, for this account: the sessions
            // started before now end, this one starts again now
            $now = time();
            $sessions = readData('sessions');
            $sessions['accounts'][$user['id']] = $now;
            if (!writeData('sessions', $sessions)) {
                setFlash('Nie udało się zapisać, spróbuj później.');
            } else {
                $_SESSION['login_time'] = $now;
                // noted again on the next page, with the new login time, or the
                // list of devices would drop this one with the others
                unset($_SESSION['address_seen']);
                addHistory('sessions', 'Wylogowano się na pozostałych urządzeniach.');
                setFlash('Wylogowano pozostałe urządzenia.');
            }
        } else if (($_POST['action'] ?? '') === 'hud') {
            $mode = (string)($_POST['hud'] ?? '');
            // the colour is picked from the role user up; the others keep their own
            $color = canPickHudColor($user['id'], roleBadge(siteRoles())) ? (string)($_POST['hudcolor'] ?? 'own') : 'own';
            if (!setHud($user['id'], $mode, $color)) {
                setFlash('Nie udało się zapisać wyglądu, spróbuj później.');
            } else {
                addHistory('hud', 'Zmieniono wygląd na: ' . HUD_MODES[$mode] . ($mode !== 'default' ? ', ' . lower(HUD_COLORS[$color]) : '') . '.');
                setFlash('Zapisano wygląd.');
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
        $showGallery = canViewGalleryId($id) || array_sum($gallery) > 0;
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
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=c6ba77c342" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=ed18f56732" type="text/css" rel="stylesheet" />
  <link href="../css/status.css?v=303fe8ce60" type="text/css" rel="stylesheet" />
  <link href="../css/admin.css?v=d13e53eeb9" type="text/css" rel="stylesheet" />
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
        <p class="hint">Kolor strony. Domyślnie jest fioletowy, jak dotąd. W wariancie <b>kolor tylko w akcentach</b> wybrany kolor wchodzi w narożniki, linki, wyszukiwarkę i menu konta, a <b>na całym HUD</b> także w tło, poświatę tytułu i linie pod SAFEGUARD. Kolor wybiera konto z rolą na serwerze bota (od user w górę); bez wyboru jest to kolor jego roli. Zmiana widoczna od razu, zapisuje się przyciskiem.</p>
        <form method="post" action="./" class="hud-form">
          <input type="hidden" name="csrf" value="<?=e($csrf)?>" />
          <input type="hidden" name="action" value="hud" />
<?php $hudNow = hudMode($id); $colorNow = hudColor($id); $ownHex = HUD_COLOR_HEX[$badge['key'] ?? ''] ?? '#b670d3'; ?>
          <div class="hud-fields">
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
          <button type="submit" class="admin-btn primary">Zapisz wygląd</button>
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
  <script src="../js/explorer.js?v=6728775ca3"></script>
  <script src="../js/hud.js?v=2894733f39"></script>
  <script src="../js/account.js?v=51972de250"></script>
  <script src="../js/netsphere.js?v=1c8be049a6"></script>
</body>

</html>
