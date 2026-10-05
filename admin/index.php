<?php
    // Admin panel: who may use the gallery and the API, the bot status, the
    // roles the bot reports for the recent logins, and server tools. The home page links here for the accounts that may open it. The
    // Discord login is the gallery's (inc/auth.php, one session for all); only
    // the accounts in PANEL_ADMINS (inc/config.php) get in.
    require __DIR__ . '/../inc/bot.php';
    require __DIR__ . '/../inc/services.php';
    require __DIR__ . '/../inc/gallery.php';
    require __DIR__ . '/../inc/status-card.php';
    require __DIR__ . '/../inc/system.php';

    $galleryDir = str_replace('\\', '/', dirname(__DIR__)) . '/i';
    $thumbsDir = thumbsDir();

    const LIST_LABELS = [
        'galleryAdmins' => 'administratorzy galerii',
        'galleryViewers' => 'oglądający galerię',
        'apiViewers' => 'dostęp do API'
    ];

    // title and description of the card of each list
    const LIST_CARDS = [
        'galleryAdmins' => ['Administratorzy galerii', 'Oglądają galerię i dodają, przenoszą oraz usuwają pliki.'],
        'galleryViewers' => ['Oglądający galerię', 'Tylko oglądają galerię.'],
        'apiViewers' => ['Dostęp do API', 'Czytają dokumentację API w api/. Administratorzy panelu mają ją zawsze, a z ról na serwerze bota dev, admin, semi-admin i tester.']
    ];

    // name of an account from the recent logins, or its ID
    function accountLabel($id, $logins)
    {
        return isset($logins[$id]['name']) ? $logins[$id]['name'] . ' (' . $id . ')' : $id;
    }

    function dataError()
    {
        return 'Nie udało się zapisać danych w inc/data. PHP musi mieć tam prawo zapisu, np.: '
            . 'mkdir -p ' . __DIR__ . '/../inc/data && chown www-data:www-data ' . __DIR__ . '/../inc/data';
    }

    const LARGEST_SHOWN = 10;
    const REPO_URL = 'https://github.com/MZKNEK/sansite';

    // files and bytes in a folder and its subfolders
    function folderTotals($dir)
    {
        $files = 0;
        $bytes = 0;
        foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $name) {
            if ($name === '.' || $name === '..')
                continue;
            $path = $dir . '/' . $name;
            if (is_dir($path) && !is_link($path)) {
                list($innerFiles, $innerBytes) = folderTotals($path);
                $files += $innerFiles;
                $bytes += $innerBytes;
            } else if (is_file($path)) {
                $files++;
                $bytes += filesize($path);
            }
        }

        return [$files, $bytes];
    }
    const NOTICE_LENGTH = 300;

    // a time from a datetime-local field ("2026-10-04T22:00", Polish time), or null
    function noticeTime($value)
    {
        $value = trim((string)$value);
        $time = $value === '' ? false : strtotime($value);

        return $time === false ? null : $time;
    }

    // a time for a datetime-local field
    function fieldTime($time)
    {
        return $time ? date('Y-m-d\TH:i', $time) : '';
    }
    const CRON_LATE = 300;

    // The gallery in numbers: files and bytes in all, per top folder ('' for the
    // files right in i/), per file type, and the biggest files as [rel, size].
    // Hidden files and the gallery script do not count, as in the gallery itself.
    function galleryStats($base)
    {
        $stats = ['files' => 0, 'bytes' => 0, 'folders' => [], 'types' => [], 'largest' => []];
        if (!is_dir($base))
            return $stats;

        foreach (listNames($base, '') as $name)
            if (is_dir($base . '/' . $name) && !is_link($base . '/' . $name))
                $stats['folders'][$name] = ['files' => 0, 'bytes' => 0];

        $walk = function ($dirPath, $dirRel, $top, $depth) use (&$walk, &$stats) {
            foreach (listNames($dirPath, $dirRel) as $name) {
                $full = $dirPath . '/' . $name;
                $rel = ltrim($dirRel . '/' . $name, '/');
                if (is_dir($full) && !is_link($full)) {
                    if ($depth < 10)
                        $walk($full, $rel, $top ?? $name, $depth + 1);
                    continue;
                }
                if (!is_file($full))
                    continue;

                $size = filesize($full);
                $type = strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: 'bez rozszerzenia';
                $stats['files']++;
                $stats['bytes'] += $size;
                foreach ([['folders', $top ?? ''], ['types', $type]] as [$group, $key]) {
                    $stats[$group][$key]['files'] = ($stats[$group][$key]['files'] ?? 0) + 1;
                    $stats[$group][$key]['bytes'] = ($stats[$group][$key]['bytes'] ?? 0) + $size;
                }

                // only the biggest are kept while walking
                $stats['largest'][] = [$rel, $size];
                if (count($stats['largest']) > 5 * LARGEST_SHOWN)
                    $stats['largest'] = largestFirst($stats['largest']);
            }
        };
        $walk($base, '', null, 0);

        $stats['largest'] = largestFirst($stats['largest']);
        foreach (['folders', 'types'] as $group)
            uasort($stats[$group], function ($a, $b) { return $b['bytes'] <=> $a['bytes']; });

        return $stats;
    }

    function largestFirst($files)
    {
        usort($files, function ($a, $b) { return $b[1] <=> $a[1]; });

        return array_slice($files, 0, LARGEST_SHOWN);
    }

    // share of the whole for the bar behind a row, as a CSS percentage
    function share($part, $whole)
    {
        return $whole > 0 ? round(100 * $part / $whole, 1) . '%' : '0%';
    }

    // a number with a Polish decimal comma, "0,52"
    function decimal($number, $digits = 1)
    {
        return number_format((float)$number, $digits, ',', '');
    }

    // a resource bar of the server card: yellow from 75%, red from 90%
    function meter($title, $part, $whole, $value, $details)
    {
        $percent = $whole > 0 ? 100 * $part / $whole : 0;
        $level = $percent >= 90 ? ' critical' : ($percent >= 75 ? ' high' : '');

        return '<div class="meter' . $level . '">'
            . '<div class="meter-head"><span>' . e($title) . '</span><b>' . $value . '</b></div>'
            . '<div class="meter-bar"><span style="width: ' . share($part, $whole) . '"></span></div>'
            . '<p>' . $details . '</p></div>';
    }

    // ---- Changes ------------------------------------------------------------

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!authConfigured())
            reply(false, 'Logowanie nie jest włączone na serwerze.', 503);

        $user = siteUser();
        if (!$user)
            reply(false, 'Trzeba się zalogować.', 401);
        if (!checkCsrf())
            reply(false, 'Sesja wygasła, odśwież stronę.', 403);

        $action = (string)($_POST['action'] ?? '');
        if ($action === 'logout') {
            logout();
            reply(true, 'Wylogowano.');
        }

        if (!isPanelAdminId($user['id']))
            reply(false, 'To konto nie ma dostępu do panelu.', 403);

        $logins = readData('logins');
        $list = (string)($_POST['list'] ?? '');
        $id = trim((string)($_POST['id'] ?? ''));

        switch ($action) {
            case 'grant':
                if (!isset(ACCESS_LISTS[$list]))
                    reply(false, 'Nieznana lista.', 400);
                if (!preg_match('/^\d{17,20}$/', $id))
                    reply(false, 'ID konta Discord to 17-20 cyfr.', 400);

                $config = configList(ACCESS_LISTS[$list]);
                if ($config !== true && in_array($id, $config, true))
                    reply(false, 'To konto jest już na tej liście w konfiguracji.', 409);

                $access = readData('access');
                if (isset($access[$list][$id]))
                    reply(false, 'To konto jest już na tej liście.', 409);

                $access[$list][$id] = [
                    'note' => cutText(trim((string)($_POST['note'] ?? '')), 60),
                    'added' => time(),
                    'by' => $user['id']
                ];
                if (!writeData('access', $access))
                    reply(false, dataError(), 500);
                // a request of this account is answered now
                $for = $list === 'apiViewers' ? 'api' : 'gallery';
                $requests = readData('requests');
                if (isset($requests[$for][$id])) {
                    unset($requests[$for][$id]);
                    writeData('requests', $requests);
                }
                done('access', 'Dodano ' . accountLabel($id, $logins) . ': ' . LIST_LABELS[$list] . '.');

            case 'revoke':
                $access = readData('access');
                if (!isset(ACCESS_LISTS[$list]) || !isset($access[$list][$id]))
                    reply(false, 'Nie ma takiego wpisu (wpisy z konfiguracji zmienia się w inc/config.php).', 404);

                unset($access[$list][$id]);
                if (!writeData('access', $access))
                    reply(false, dataError(), 500);
                done('access', 'Usunięto ' . accountLabel($id, $logins) . ': ' . LIST_LABELS[$list] . '.');

            case 'request-dismiss':
                $for = (string)($_POST['for'] ?? '');
                $requests = readData('requests');
                if (!isset(REQUEST_LABELS[$for], $requests[$for][$id]))
                    reply(false, 'Tej prośby już nie ma, odśwież stronę.', 404);
                unset($requests[$for][$id]);
                if (!writeData('requests', $requests))
                    reply(false, dataError(), 500);
                done('access', 'Odrzucono prośbę o dostęp do ' . REQUEST_LABELS[$for] . ': ' . accountLabel($id, $logins) . '.');

            case 'backup':
                // a plain form: the answer is the ZIP itself, a problem comes back as a message
                $withGallery = ($_POST['gallery'] ?? '') === '1';
                // without the logins of the moment (sessions), which would only log people
                // in again, and the thumbnails, which are made again by themselves
                $roots = [[dataDir(), 'data', ['sessions', 'thumbs']]];
                if ($withGallery)
                    $roots[] = [$galleryDir, 'i', ['index.php']];
                addHistory('backup', 'Pobrano kopię danych' . ($withGallery ? ' z galerią' : '') . '.');
                sendZip($roots, 'sanakan-kopia-' . date('Y-m-d-His') . '.zip', './', null);

            case 'logout-all':
                $now = time();
                if (!writeData('sessions', ['since' => $now]))
                    reply(false, dataError(), 500);
                // the one who asked for it stays logged in
                $_SESSION['login_time'] = $now;
                done('sessions', 'Wylogowano wszystkich z galerii i panelu (poza sobą).');

            case 'notice':
                $text = cutText(trim(str_replace("\r", '', (string)($_POST['text'] ?? ''))), NOTICE_LENGTH);
                if ($text === '')
                    reply(false, 'Wpisz treść ogłoszenia.', 400);

                $from = noticeTime($_POST['from'] ?? '');
                $to = noticeTime($_POST['to'] ?? '');
                $maintenance = null;
                if (($_POST['maintenance'] ?? '') === '1') {
                    $from = $from ?? time();
                    if ($to === null)
                        reply(false, 'Przerwa techniczna potrzebuje godziny końca.', 400);
                    if ($to <= $from)
                        reply(false, 'Koniec przerwy musi być po jej początku.', 400);
                    $maintenance = ['from' => $from, 'to' => $to];
                } else if ($to !== null && $to <= time()) {
                    reply(false, 'Czas zniknięcia ogłoszenia już minął.', 400);
                }

                $notice = ['text' => $text, 'to' => $to, 'maintenance' => $maintenance, 'by' => $user['id'], 'set' => time()];
                botSaveNotice($notice);
                done('notice', 'Ustawiono ogłoszenie: ' . noticeText($notice));

            case 'notice-clear':
                botSaveNotice(null);
                done('notice', 'Usunięto ogłoszenie.');

            case 'clear-thumbs':
                $removed = 0;
                foreach (glob($thumbsDir . '/*') ?: [] as $file)
                    if (is_file($file) && @unlink($file))
                        $removed++;
                done('cache', 'Usunięto ' . $removed . ' ' . plural($removed, 'miniaturę', 'miniatury', 'miniatur') . ' z cache; utworzą się od nowa przy oglądaniu galerii.');

            case 'refresh-spec':
                @unlink(botFile('swagger.json'));
                done('cache', 'Odświeżono specyfikację API; zostanie pobrana przy następnym wejściu do API.');

            case 'restore':
                $item = trashItems()[(string)($_POST['item'] ?? '')] ?? null;
                if (!$item)
                    reply(false, 'Tego już nie ma w koszu, odśwież stronę.', 404);
                $where = restoreFromTrash($galleryDir, (string)$_POST['item']);
                if ($where === null)
                    reply(false, 'Nie udało się przywrócić ' . $item['name'] . '.', 500);
                done('restore', 'Przywrócono z kosza ' . galleryPath($where) . '.');

            case 'trash-delete':
                $item = trashItems()[(string)($_POST['item'] ?? '')] ?? null;
                if (!$item || !deleteFromTrash((string)$_POST['item']))
                    reply(false, 'Tego już nie ma w koszu, odśwież stronę.', 404);
                done('purge', 'Usunięto na zawsze ' . galleryPath($item['from']) . ' (z kosza).');

            case 'trash-empty':
                $removed = 0;
                foreach (array_keys(trashItems()) as $item)
                    if (deleteFromTrash($item))
                        $removed++;
                done('purge', 'Opróżniono kosz: ' . countLabel($removed) . ' usunięte na zawsze.');
        }

        reply(false, 'Nieznana akcja.', 400);
    }

    // ---- Page -----------------------------------------------------------------

    handleLoginRequest(siteRoot() . 'admin/');

    $user = siteUser();
    $allowed = $user !== null && isPanelAdminId($user['id']);
    $flash = takeFlash();
    if (!$allowed)
        http_response_code(!authConfigured() ? 503 : ($user ? 403 : 401));

    if ($allowed) {
        $autoChecks = checksLastHour(botHistory());

        $logins = readData('logins');

        // both gallery lists, the config entries first; config "true" means everyone
        $lists = [];
        foreach (ACCESS_LISTS as $list => $constant) {
            $entries = [];
            $config = configList($constant);
            foreach ($config === true ? [] : $config as $id)
                $entries[] = ['id' => (string)$id, 'note' => '', 'config' => true];
            foreach (panelList($list) as $id => $entry)
                $entries[] = ['id' => (string)$id, 'note' => $entry['note'] ?? '', 'config' => false, 'added' => $entry['added'] ?? 0];
            $lists[$list] = ['everyone' => $config === true, 'entries' => $entries];
        }

        // accounts that read the API through their role on the bot's server, as the bot said it last
        $knownRoles = readData('roles');
        $apiByRole = [];
        foreach ($knownRoles as $id => $known) {
            if (isPanelAdminId($id))
                continue;
            foreach (API_ROLES as $role) {
                if (!empty($known['roles'][$role])) {
                    $apiByRole[(string)$id] = $role;
                    break;
                }
            }
        }
        foreach ($lists['apiViewers']['entries'] as $entry)
            unset($apiByRole[$entry['id']]);

        $panelAdmins = configList('PANEL_ADMINS');
        $notice = botNotice();

        // requests for access, newest first; ones of accounts let in meanwhile go away
        $stored = readData('requests');
        $kept = [];
        $requests = [];
        foreach (REQUEST_LABELS as $for => $label) {
            foreach ($stored[$for] ?? [] as $id => $request) {
                $id = (string)$id;
                if ($for === 'gallery' ? canViewGalleryId($id) : canViewApiId($id))
                    continue;
                $kept[$for][$id] = $request;
                $requests[] = $request + ['id' => $id, 'for' => $for];
            }
        }
        if ($kept != $stored)
            writeData('requests', $kept);
        usort($requests, function ($a, $b) { return $b['time'] <=> $a['time']; });

        // what deploy.sh put on the server: commit, its date and subject
        $deployed = null;
        $deployFile = dataDir() . '/deployed-info';
        if (is_file($deployFile)) {
            $lines = explode("\n", trim((string)file_get_contents($deployFile)));
            $deployed = ['hash' => $lines[0], 'date' => strtotime($lines[1] ?? '') ?: null, 'subject' => $lines[2] ?? '', 'at' => filemtime($deployFile)];
        } else if (is_file(dataDir() . '/deployed-commit')) {
            $deployed = ['hash' => trim((string)file_get_contents(dataDir() . '/deployed-commit')), 'date' => null, 'subject' => '', 'at' => filemtime(dataDir() . '/deployed-commit')];
        }
        list(, $dataBytes) = folderTotals(dataDir());
        $stats = galleryStats($galleryDir);
        $diskFree = @disk_free_space($galleryDir);
        $diskTotal = @disk_total_space($galleryDir);
        $sessionsSince = sessionsValidSince();
        $cronLast = botCronLast();
        $cronLate = $cronLast === null || time() - $cronLast > CRON_LATE;
        $cronCommand = "echo '* * * * * www-data php " . str_replace('\\', '/', realpath(__DIR__ . '/../inc/check-bot.php'))
            . " > /dev/null 2>&1' > /etc/cron.d/sanakan-status";
        $thumbFiles = glob($thumbsDir . '/*') ?: [];
        $thumbBytes = array_sum(array_map('filesize', $thumbFiles));
        $specFile = botFile('swagger.json');
        $privateTime = botPrivateCommandsTime();
        $system = systemStats();

        purgeTrash();
        $trash = trashItems();
        $history = historyEntries(100);
    }

    $csrf = $user ? siteCsrf() : '';
    $root = siteRoot();

    // avatar and name of an account, from its latest login when there was one
    function accountCell($id, $logins)
    {
        $known = $logins[$id] ?? null;
        $avatar = $known['avatar'] ?? 'https://cdn.discordapp.com/embed/avatars/' . (((int)$id >> 22) % 6) . '.png';
        $name = $known['name'] ?? 'nieznane konto';

        return '<img src="' . e($avatar) . '" alt="" width="28" height="28" loading="lazy" />'
            . '<span class="who"><span class="who-name">' . e($name) . '</span><code>' . e($id) . '</code></span>';
    }
?>
<!DOCTYPE html>
<html lang="pl">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex" />
  <title>Panel &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../apple-touch-icon.png" />
  <link href="../css/fonts.css?v=1" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=25" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=8" type="text/css" rel="stylesheet" />
  <link href="../css/status.css?v=7" type="text/css" rel="stylesheet" />
  <link href="../css/admin.css?v=8" type="text/css" rel="stylesheet" />
</head>

<body class="admin-page" data-csrf="<?=e($csrf)?>">
  <main class="content">
    <header class="ex-header">
      <div class="ex-top">
        <a class="back hud-corners" href="../" title="Strona główna">&larr; Sanakan</a>
<?php if ($user): ?>
        <div class="account">
          <img src="<?=e($user['avatar'])?>" alt="" width="28" height="28" />
          <span class="account-name"><?=e($user['name'])?></span>
          <?=roleBadgeHtml(siteRoles())?>
          <button type="button" class="account-btn" id="act-logout" data-csrf="<?=e($csrf)?>">Wyloguj</button>
        </div>
<?php endif; ?>
      </div>
      <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
      <h1 class="hud-title">Panel</h1>
    </header>

<?php if (!$allowed): ?>
    <section class="locked hud-corners">
      <svg class="lock-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="1.5" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
<?php if (!authConfigured()): ?>
      <h2>Panel jest wyłączony</h2>
      <p>Logowanie nie jest jeszcze skonfigurowane na serwerze.</p>
<?php elseif ($user): ?>
      <h2>Brak dostępu</h2>
      <p>Konto <?=e($user['name'])?> nie ma dostępu do panelu. Wyloguj się, jeśli chcesz użyć innego konta.</p>
<?php else: ?>
      <h2>Panel administratora</h2>
      <p>Zaloguj się kontem Discord, które ma dostęp do panelu.</p>
      <a class="admin-btn primary" href="?login">Zaloguj przez Discord</a>
<?php endif; ?>
    </section>
<?php else: ?>
    <div class="panel-grid">

<?php if ($cronLate): ?>
      <section class="card wide alarm" role="alert">
        <h2><i>!</i>Cron nie sprawdza bota</h2>
        <p><?=$cronLast === null
            ? 'Automatyczne sprawdzanie jeszcze ani razu nie zadziałało.'
            : 'Ostatnie automatyczne sprawdzenie było ' . e(ago($cronLast)) . ' (' . e(date('d.m H:i', $cronLast)) . ').'?>
          Bez niego historia dostępności ma dziury, a awarie, gdy nikt nie odwiedza strony, nie są zapisywane.</p>
        <p class="hint">Zadanie cron (na serwerze, jako root): <code><?=e($cronCommand)?></code><br />Czy cron działa: <code>systemctl status cron</code></p>
      </section>
<?php endif; ?>

<?php if ($requests): ?>
      <section class="card wide requests">
        <h2><i>+</i>Prośby o dostęp</h2>
        <p class="hint">Konta, które zalogowały się do galerii albo do API bez dostępu i o niego poprosiły.</p>
        <ul class="people">
<?php foreach ($requests as $request): $id = $request['id']; ?>
          <li>
            <?=accountCell($id, $logins)?>
            <span class="role"><?=$request['for'] === 'api' ? 'API' : 'galeria'?></span>
<?php if (($request['note'] ?? '') !== ''): ?>
            <span class="note">„<?=e($request['note'])?>”</span>
<?php endif; ?>
            <span class="muted"><?=e(ago($request['time']))?></span>
            <span class="login-actions">
<?php if ($request['for'] === 'api'): ?>
              <button type="button" class="admin-btn small" data-action="grant" data-list="apiViewers" data-id="<?=e($id)?>">+ Dostęp do API</button>
<?php else: ?>
              <button type="button" class="admin-btn small" data-action="grant" data-list="galleryViewers" data-id="<?=e($id)?>">+ Oglądający</button>
              <button type="button" class="admin-btn small" data-action="grant" data-list="galleryAdmins" data-id="<?=e($id)?>">+ Admin galerii</button>
<?php endif; ?>
              <button type="button" class="admin-btn small danger" data-action="request-dismiss" data-for="<?=e($request['for'])?>" data-id="<?=e($id)?>" data-confirm="Odrzucić prośbę <?=e(accountLabel($id, $logins))?> o dostęp do <?=e(REQUEST_LABELS[$request['for']])?>?">Odrzuć</button>
            </span>
          </li>
<?php endforeach; ?>
        </ul>
      </section>
<?php endif; ?>

      <section class="card wide">
        <h2><i>01</i>Status bota</h2>
<?=statusSummary()?>
        <p class="hint status-more">Bota sprawdza cron co minutę. Wykresy, czasy odpowiedzi i awarie: <a href="<?=e($root)?>state/">strona statusu</a>.</p>

        <form class="notice-form" data-action="notice">
          <h3>Ogłoszenie</h3>
          <p class="hint">Widać je na stronie statusu. W czasie przerwy technicznej status bota (także kropka na stronie głównej) pokazuje „nie przeszkadzać”, a awarie są oznaczane jako planowane.</p>
<?php if ($notice): ?>
          <p class="notice-now">Teraz widać: <b><?=e(noticeText($notice))?></b><?=!empty($notice['to']) ? ' <span class="muted">(do ' . e(date('j.m H:i', $notice['to'])) . ')</span>' : ''?></p>
<?php endif; ?>
          <textarea name="text" rows="2" maxlength="<?=NOTICE_LENGTH?>" placeholder="Np. Dziś wieczorem aktualizacja bota, przez chwilę może nie odpowiadać." aria-label="Treść ogłoszenia"><?=e($notice['text'] ?? '')?></textarea>
          <div class="notice-fields">
            <label class="admin-check"><input type="checkbox" name="maintenance" value="1"<?=!empty($notice['maintenance']) ? ' checked' : ''?> /> Przerwa techniczna</label>
            <label>Od <input type="datetime-local" name="from" value="<?=e(fieldTime($notice['maintenance']['from'] ?? null))?>" /></label>
            <label>Do <input type="datetime-local" name="to" value="<?=e(fieldTime($notice['to'] ?? null))?>" /></label>
          </div>
          <p class="hint">„Od” liczy się tylko dla przerwy (puste: od teraz). „Do” to koniec przerwy, a bez przerwy czas, kiedy ogłoszenie zniknie (puste: zostaje, aż się je usunie).</p>
          <div class="notice-actions">
            <button type="submit" class="admin-btn primary">Zapisz ogłoszenie</button>
<?php if ($notice): ?>
            <button type="button" class="admin-btn danger" data-action="notice-clear" data-confirm="Usunąć ogłoszenie?">Usuń</button>
<?php endif; ?>
          </div>
        </form>
      </section>

      <section class="card">
        <h2><i>02</i>Dostęp do panelu</h2>
        <ul class="people">
<?php foreach ($panelAdmins === true ? [] : $panelAdmins as $id): ?>
          <li><?=accountCell($id, $logins)?><span class="badge-config" title="Ustawione w inc/config.php">config</span></li>
<?php endforeach; ?>
        </ul>
        <p class="hint">Listę zmienia się w <code>inc/config.php</code> (<code>PANEL_ADMINS</code>), panel nie może nadawać dostępu do samego siebie.</p>
      </section>

<?php $number = 3; foreach ($lists as $list => $info): ?>
      <section class="card">
        <h2><i>0<?=$number++?></i><?=e(LIST_CARDS[$list][0])?></h2>
        <p class="hint"><?=e(LIST_CARDS[$list][1])?></p>
<?php if ($info['everyone']): ?>
        <p class="everyone">Każde konto Discord (<code><?=e(ACCESS_LISTS[$list])?> = true</code> w konfiguracji).</p>
<?php endif; ?>
        <ul class="people">
<?php foreach ($info['entries'] as $entry): ?>
          <li>
            <?=accountCell($entry['id'], $logins)?>
<?php if ($entry['note'] !== ''): ?>
            <span class="note"><?=e($entry['note'])?></span>
<?php endif; ?>
<?php if ($entry['config']): ?>
            <span class="badge-config" title="Ustawione w inc/config.php">config</span>
<?php else: ?>
            <button type="button" class="admin-btn danger small" data-action="revoke" data-list="<?=e($list)?>" data-id="<?=e($entry['id'])?>" data-confirm="Odebrać dostęp: <?=e(accountLabel($entry['id'], $logins))?>?">Usuń</button>
<?php endif; ?>
          </li>
<?php endforeach; ?>
<?php if ($list === 'apiViewers'): foreach ($apiByRole as $id => $role): ?>
          <li>
            <?=accountCell($id, $logins)?>
            <span class="role bot role-<?=e($role)?>" title="Rola na serwerze Sanakana, według bota">rola: <?=e(BOT_ROLES[$role][2])?></span>
          </li>
<?php endforeach; endif; ?>
<?php if (!$info['entries'] && !$info['everyone'] && ($list !== 'apiViewers' || !$apiByRole)): ?>
          <li class="nobody">Nikogo jeszcze nie ma.</li>
<?php endif; ?>
        </ul>
        <form class="grant" data-action="grant">
          <input type="hidden" name="list" value="<?=e($list)?>" />
          <input type="text" name="id" inputmode="numeric" pattern="\d{17,20}" placeholder="ID konta Discord" aria-label="ID konta Discord" required />
          <input type="text" name="note" maxlength="60" placeholder="Notatka (opcjonalnie)" aria-label="Notatka" />
          <button type="submit" class="admin-btn primary">Dodaj</button>
        </form>
      </section>
<?php endforeach; ?>

      <section class="card wide">
        <h2><i>06</i>Ostatnie logowania</h2>
        <p class="hint">Każdy, kto zalogował się przez Discord w galerii, w API albo w panelu, także bez dostępu. Stąd najłatwiej komuś go nadać. Kolorowe są role na serwerze bota, odświeżane, gdy konto odwiedza stronę; galerii nie dają.</p>
<?php if (!$logins): ?>
        <p class="nobody">Nikt się jeszcze nie logował.</p>
<?php else: ?>
        <div class="logins">
<?php foreach ($logins as $id => $login):
        $id = (string)$id;
        $roles = [];
        if (isPanelAdminId($id))
            $roles[] = 'panel';
        if (isGalleryAdminId($id))
            $roles[] = 'admin galerii';
        else if (canViewGalleryId($id))
            $roles[] = 'ogląda galerię';
        if (!isPanelAdminId($id) && canViewApiId($id))
            $roles[] = 'API';
?>
          <div class="login-row">
            <span class="login-who"><?=accountCell($id, $logins)?></span>
            <span class="login-when"><?=e(ago($login['last'] ?? 0))?> &middot; <?=(int)($login['count'] ?? 0)?>&times;</span>
            <span class="login-roles">
<?php foreach (BOT_ROLES as $key => $role): if (!empty($knownRoles[$id]['roles'][$key])): ?>
              <span class="role bot role-<?=e($key)?>" title="Rola na serwerze Sanakana, według bota"><?=e($role[2])?></span>
<?php endif; endforeach; ?>
<?php if (isset($knownRoles[$id]['roles']) && empty($knownRoles[$id]['roles']['onGuild'])): ?>
              <span class="role bot role-out" title="Według bota tego konta nie ma na serwerze Sanakana">poza serwerem</span>
<?php endif; ?>
<?php foreach ($roles as $role): ?>
              <span class="role"><?=e($role)?></span>
<?php endforeach; ?>
<?php if (!$roles): ?>
              <span class="role none">bez dostępu</span>
<?php endif; ?>
            </span>
            <span class="login-actions">
<?php if (!canViewGalleryId($id)): ?>
              <button type="button" class="admin-btn small" data-action="grant" data-list="galleryViewers" data-id="<?=e($id)?>">+ Oglądający</button>
<?php endif; ?>
<?php if (!isGalleryAdminId($id)): ?>
              <button type="button" class="admin-btn small" data-action="grant" data-list="galleryAdmins" data-id="<?=e($id)?>">+ Admin galerii</button>
<?php endif; ?>
<?php if (!canViewApiId($id)): ?>
              <button type="button" class="admin-btn small" data-action="grant" data-list="apiViewers" data-id="<?=e($id)?>">+ API</button>
<?php endif; ?>
            </span>
          </div>
<?php endforeach; ?>
        </div>
<?php endif; ?>
        <p class="logout-all">
          <span class="hint"><?=$sessionsSince ? 'Ostatnio wylogowano wszystkich ' . e(ago($sessionsSince)) . '.' : 'Po odebraniu komuś dostępu można też zakończyć wszystkie sesje.'?></span>
          <button type="button" class="admin-btn small danger" data-action="logout-all" data-confirm="Wylogować wszystkich z galerii i panelu? Ty zostaniesz zalogowany, inni muszą zalogować się jeszcze raz.">Wyloguj wszystkich</button>
        </p>
      </section>

      <section class="card wide">
        <h2><i>07</i>Kosz</h2>
        <p class="hint">Usunięte w galerii pliki i foldery leżą tu <?=TRASH_DAYS?> dni, potem znikają same. Przywrócone wracają do swojego folderu.</p>
<?php if (!$trash): ?>
        <p class="nobody">Kosz jest pusty.</p>
<?php else: ?>
        <div class="trash">
<?php foreach ($trash as $id => $item):
        $daysLeft = max(0, (int)ceil((($item['deleted'] ?? 0) + TRASH_DAYS * 86400 - time()) / 86400));
?>
          <div class="trash-row">
            <span class="trash-name">
              <b><?=e($item['name'])?><?=!empty($item['folder']) ? '/' : ''?></b>
              <code><?=e(galleryPath($item['from']))?></code>
            </span>
            <span class="trash-info"><?=e(formatSize($item['size'] ?? 0))?> &middot; usunięte <?=e(ago($item['deleted'] ?? 0))?><?=empty($item['by']) ? '' : ' przez ' . e($item['by'])?> &middot; zostało <?=$daysLeft?> <?=plural($daysLeft, 'dzień', 'dni', 'dni')?></span>
            <span class="login-actions">
              <button type="button" class="admin-btn small" data-action="restore" data-item="<?=e($id)?>">Przywróć</button>
              <button type="button" class="admin-btn small danger" data-action="trash-delete" data-item="<?=e($id)?>" data-confirm="Usunąć na zawsze <?=e(galleryPath($item['from']))?>? Tego nie da się cofnąć.">Usuń na zawsze</button>
            </span>
          </div>
<?php endforeach; ?>
        </div>
        <p class="trash-all"><button type="button" class="admin-btn small danger" data-action="trash-empty" data-confirm="Usunąć na zawsze wszystko z kosza (<?=e(countLabel(count($trash)))?>)? Tego nie da się cofnąć.">Opróżnij kosz</button></p>
<?php endif; ?>
      </section>

      <section class="card wide">
        <h2><i>08</i>Galeria w liczbach</h2>
        <div class="stats-summary">
          <span><b><?=$stats['files']?></b> <?=plural($stats['files'], 'plik', 'pliki', 'plików')?></span>
          <span><b><?=e(formatSize($stats['bytes']))?></b> razem</span>
          <span><b><?=count($stats['folders'])?></b> <?=plural(count($stats['folders']), 'folder', 'foldery', 'folderów')?> w i/</span>
        </div>
<?php if ($diskTotal): $diskUsed = $diskTotal - $diskFree; $diskLow = $diskFree < $diskTotal * 0.1; ?>
        <div class="disk<?=$diskLow ? ' low' : ''?>">
          <div class="bar-head"><span>Dysk serwera<?=$diskLow ? ': <b class="warn">mało miejsca</b>' : ''?></span><b>wolne <?=e(formatSize($diskFree))?> z <?=e(formatSize($diskTotal))?></b></div>
          <div class="disk-bar" title="Zajęte <?=e(formatSize($diskUsed))?>, w tym galeria <?=e(formatSize($stats['bytes']))?>">
            <span class="gallery" style="width: <?=share($stats['bytes'], $diskTotal)?>"></span><span class="used" style="width: <?=share(max(0, $diskUsed - $stats['bytes']), $diskTotal)?>"></span>
          </div>
          <div class="timeline-legend"><span><i class="gallery"></i>galeria <i class="used"></i>reszta serwera <i class="none"></i>wolne</span></div>
        </div>
<?php endif; ?>
<?php if ($stats['files']): ?>
        <div class="stats-grid">
          <div>
            <h3>Foldery</h3>
            <ul class="stat-list">
<?php foreach ($stats['folders'] as $name => $folder): ?>
              <li style="--share: <?=share($folder['bytes'], $stats['bytes'])?>"><a href="<?=e($root . 'i/' . folderUrl((string)$name))?>"><?=e($name === '' ? 'i/ (bez folderu)' : 'i/' . $name)?></a><span><?=$folder['files']?> &middot; <?=e(formatSize($folder['bytes']))?></span></li>
<?php endforeach; ?>
            </ul>
          </div>
          <div>
            <h3>Typy plików</h3>
            <ul class="stat-list">
<?php foreach ($stats['types'] as $type => $group): ?>
              <li style="--share: <?=share($group['bytes'], $stats['bytes'])?>"><span><?=e((string)$type)?></span><span><?=$group['files']?> &middot; <?=e(formatSize($group['bytes']))?></span></li>
<?php endforeach; ?>
            </ul>
          </div>
          <div>
            <h3>Największe pliki</h3>
            <ol class="stat-list">
<?php foreach ($stats['largest'] as [$rel, $size]): ?>
              <li style="--share: <?=share($size, $stats['largest'][0][1])?>"><a href="<?=e($root . 'i/' . fileUrl($rel))?>" target="_blank" rel="noopener" title="<?=e(galleryPath($rel))?>"><?=e(galleryPath($rel))?></a><span><?=e(formatSize($size))?></span></li>
<?php endforeach; ?>
            </ol>
          </div>
        </div>
<?php else: ?>
        <p class="nobody">Galeria jest pusta.</p>
<?php endif; ?>
      </section>

      <section class="card wide">
        <h2><i>09</i>Historia zmian</h2>
        <p class="hint">Ostatnie zmiany w galerii i w panelu: kto, kiedy i co.</p>
<?php if (!$history): ?>
        <p class="nobody">Jeszcze nic się nie zmieniło.</p>
<?php else: ?>
        <ol class="history">
<?php foreach ($history as $entry): ?>
          <li>
            <time datetime="<?=e(date('c', $entry['time'] ?? 0))?>"><?=e(date('d.m H:i', $entry['time'] ?? 0))?></time>
            <span class="history-who"><?=e($entry['name'] ?: $entry['id'])?></span>
            <span class="history-text"><?=e($entry['text'] ?? '')?></span>
          </li>
<?php endforeach; ?>
        </ol>
<?php endif; ?>
      </section>

      <section class="card wide">
        <h2><i>10</i>Zasoby serwera</h2>
        <p class="hint">Stan z chwili otwarcia panelu, odśwież stronę po nowy. <?=e($system['name'])?><?=$system['uptime'] !== null ? ' · serwer działa od ' . e(duration($system['uptime'])) : ''?>.</p>
<?php if ($system['cpu'] === null && $system['memory'] === null): ?>
        <p class="nobody">Brak danych: serwer nie ma <code>/proc</code> (to nie Linux) albo PHP nie może go czytać (<code>open_basedir</code>).</p>
<?php else:
        $cores = $system['cpuInfo']['cores'];
        $load = $system['load'];
        $memory = $system['memory'];
        $opcache = $system['opcache'];
?>
        <div class="meters">
<?php if ($system['cpu']): $cpu = $system['cpu']; ?>
          <?=meter('Procesor', $cpu['busy'], 100, decimal($cpu['busy']) . '%',
              ($load ? 'obciążenie ' . decimal($load[0], 2) . ' · ' . decimal($load[1], 2) . ' · ' . decimal($load[2], 2) . ' (1, 5, 15 min)'
                  . ($cores && $load[1] > $cores ? ' <b class="warn">więcej niż rdzeni</b>' : '') . '<br />' : '')
              . ($cores ? $cores . ' ' . plural($cores, 'rdzeń', 'rdzenie', 'rdzeni') : '')
              . ($system['cpuInfo']['model'] ? ' · ' . e($system['cpuInfo']['model']) : '') . '<br />'
              . 'czekanie na dysk ' . decimal($cpu['iowait']) . '%' . ($cpu['iowait'] >= 10 ? ' <b class="warn">dysk nie nadąża</b>' : '')
              . ' · zabrane przez hosta ' . decimal($cpu['steal']) . '%' . ($cpu['steal'] >= 10 ? ' <b class="warn">host jest przeciążony</b>' : ''))?>

<?php endif; ?>
<?php if ($memory): $used = $memory['total'] - $memory['available']; ?>
          <?=meter('Pamięć RAM', $used, $memory['total'], e(formatSize($used)) . ' z ' . e(formatSize($memory['total'])),
              'wolne do użycia ' . e(formatSize($memory['available'])) . '<br />w tym cache dysku ' . e(formatSize($memory['cached'])) . ', które system odda, gdy zabraknie')?>

<?php $swapUsed = $memory['swapTotal'] - $memory['swapFree']; ?>
          <?=$memory['swapTotal'] > 0
              ? meter('Swap', $swapUsed, $memory['swapTotal'], e(formatSize($swapUsed)) . ' z ' . e(formatSize($memory['swapTotal'])),
                  $swapUsed > $memory['swapTotal'] * 0.5 ? '<b class="warn">mocno używany</b>: brakuje RAM-u, wszystko zwalnia' : 'pamięć na dysku, gdy brakuje RAM-u')
              : meter('Swap', 0, 1, 'brak', 'bez swapu, gdy zabraknie RAM-u, system zabija procesy (OOM)')?>

<?php endif; ?>
<?php if ($opcache): ?>
          <?=meter('OPcache', $opcache['used'], $opcache['size'], e(formatSize($opcache['used'])) . ' z ' . e(formatSize($opcache['size'])),
              ($opcache['hits'] !== null ? 'trafienia ' . decimal($opcache['hits']) . '% · ' : '') . $opcache['scripts'] . ' ' . plural($opcache['scripts'], 'skrypt', 'skrypty', 'skryptów')
              . ($opcache['full'] ? ' · <b class="warn">pełny</b>, zwiększ <code>opcache.memory_consumption</code>' : ''))?>

<?php else: ?>
          <?=meter('OPcache', 0, 1, 'wyłączony', 'PHP kompiluje każdy skrypt przy każdym wejściu; włącz <code>opcache.enable</code>')?>

<?php endif; ?>
        </div>
<?php endif; ?>
        <div class="stats-grid">
<?php if ($system['processes'] && !empty($memory)): ?>
          <div>
            <h3>Najwięcej pamięci</h3>
            <ul class="stat-list">
<?php foreach ($system['processes'] as $name => $program): ?>
              <li style="--share: <?=share($program['bytes'], $memory['total'])?>"><span><?=e($name)?><?=$program['count'] > 1 ? ' <span class="muted">&times;' . $program['count'] . '</span>' : ''?></span><span><?=e(formatSize($program['bytes']))?> &middot; <?=decimal(100 * $program['bytes'] / $memory['total'])?>%</span></li>
<?php endforeach; ?>
            </ul>
          </div>
<?php endif; ?>
          <div>
            <h3>PHP</h3>
            <ul class="stat-list">
              <li style="--share: 0"><span>limit pamięci skryptu</span><span><?=e(ini_get('memory_limit'))?></span></li>
              <li style="--share: 0"><span>limit czasu skryptu</span><span><?=(int)ini_get('max_execution_time')?> s</span></li>
              <li style="--share: 0"><span>ten widok zużył</span><span><?=e(formatSize(memory_get_peak_usage(true)))?></span></li>
              <li style="--share: 0"><span>pomiar procesora trwał</span><span><?=SYSTEM_CPU_SAMPLE / 1000?> ms</span></li>
            </ul>
          </div>
        </div>
      </section>

      <section class="card wide">
        <h2><i>11</i>Serwer</h2>
        <dl class="server">
          <dt>PHP</dt>
          <dd><?=e(PHP_VERSION)?></dd>

          <dt>Miniatury (GD)</dt>
          <dd><?=hasGd() ? 'włączone' . (function_exists('imagewebp') ? ', WebP' : ', PNG') : '<b class="warn">GD wyłączone</b>: duże pliki nie mają podglądów'?></dd>

          <dt>GIF → WebP</dt>
          <dd><?=canConvertGifToWebp() ? 'gif2webp: ' . e(findTool('gif2webp')) . (findTool('webpmux') ? '' : ' <b class="warn">(bez webpmux miniatury animowanych WebP się nie zrobią)</b>') : '<b class="warn">brak gif2webp</b>: GIF-y zostają GIF-ami. Instalacja: <code>apt-get install -y webp</code>'?></dd>

          <dt>Miniatury filmów</dt>
          <dd><?=canThumbVideo() ? 'ffmpeg: ' . e(findTool('ffmpeg')) : '<b class="warn">brak ffmpeg</b>: filmy w galerii nie mają miniatur, kafelek wczytuje cały film. Instalacja: <code>apt-get install -y ffmpeg</code>'?></dd>

          <dt>Pobieranie ZIP</dt>
          <dd><?=canZip() ? 'włączone' : '<b class="warn">brak modułu ZIP</b>: przyciski pobierania są ukryte. Instalacja: <code>apt-get install -y php8.1-zip &amp;&amp; systemctl restart php8.1-fpm</code>'?></dd>

          <dt>Limit wysyłania</dt>
          <dd><?=e(formatSize(uploadLimit()))?> <span class="muted">(PHP; nginx ma osobny client_max_body_size)</span></dd>

          <dt>Auto-sprawdzanie</dt>
          <dd><?=$cronLate
              ? '<b class="warn">nie działa</b>: ' . ($cronLast === null ? 'cron jeszcze ani razu nie sprawdził bota' : 'ostatnio ' . e(ago($cronLast))) . '. Zadanie cron: <code>' . e($cronCommand) . '</code>'
              : 'działa: ostatnio ' . e(ago($cronLast)) . ', ' . $autoChecks . ' ' . plural($autoChecks, 'sprawdzenie', 'sprawdzenia', 'sprawdzeń') . ' w ostatniej godzinie'?></dd>

          <dt>Cache miniatur</dt>
          <dd>
            <?=count($thumbFiles)?> <?=plural(count($thumbFiles), 'plik', 'pliki', 'plików')?>, <?=e(formatSize($thumbBytes))?>
            <button type="button" class="admin-btn small" data-action="clear-thumbs" data-confirm="Usunąć wszystkie miniatury? Utworzą się od nowa przy oglądaniu galerii.">Wyczyść</button>
          </dd>

          <dt>Specyfikacja API</dt>
          <dd>
            <?=is_file($specFile) ? 'pobrana ' . e(ago(filemtime($specFile))) : 'jeszcze nie pobrana'?>
            <button type="button" class="admin-btn small" data-action="refresh-spec">Odśwież</button>
          </dd>

          <dt>Klucz API bota</dt>
          <dd><?=botAppKey() === ''
              ? '<b class="warn">brak BOT_APP_KEY</b> w <code>inc/config.php</code>: bez niego strona nie zna ról z serwera bota i nie pokazuje poleceń moderatorskich'
              : 'ustawiony; role znane dla ' . count($knownRoles) . ' ' . plural(count($knownRoles), 'konta', 'kont', 'kont') . ', polecenia moderatorskie '
                  . ($privateTime ? 'pobrane ' . e(ago($privateTime)) : '<b class="warn">jeszcze nie pobrane</b> (klucz musi mieć uprawnienie Info)')?></dd>

          <dt>Zapis danych</dt>
          <dd><?=dataWritable() ? 'inc/data: OK' : '<b class="warn">brak prawa zapisu</b>: ' . e(dataError())?></dd>

          <dt>Wersja strony</dt>
          <dd><?php if ($deployed): ?><a href="<?=e(REPO_URL . '/commit/' . $deployed['hash'])?>" target="_blank" rel="noopener"><code><?=e(substr($deployed['hash'], 0, 7))?></code></a><?=$deployed['subject'] !== '' ? ' ' . e($deployed['subject']) : ''?> <span class="muted">(<?=$deployed['date'] ? 'commit z ' . e(date('j.m.Y', $deployed['date'])) . ', ' : ''?>wdrożone <?=e(ago($deployed['at']))?>)</span><?php else: ?>brak informacji <span class="muted">(strona nie była wdrażana przez deploy.sh)</span><?php endif; ?></dd>

          <dt>Kopia danych</dt>
          <dd>
<?php if (canZip()): ?>
            <form class="backup" method="post" action="./">
              <input type="hidden" name="csrf" value="<?=e($csrf)?>" />
              <input type="hidden" name="action" value="backup" />
              <label class="admin-check"><input type="checkbox" name="gallery" value="1" /> z galerią (<?=e(formatSize($stats['bytes']))?>)</label>
              <button type="submit" class="admin-btn small">Pobierz ZIP</button>
            </form>
            <span class="muted">inc/data: <?=e(formatSize($dataBytes))?> (dostępy, historia, statusy, awarie, kosz). Bez inc/config.php, w którym jest sekret aplikacji Discord.</span>
<?php else: ?>
            <b class="warn">brak modułu ZIP</b>: <code>apt-get install -y php8.1-zip &amp;&amp; systemctl restart php8.1-fpm</code>
<?php endif; ?>
          </dd>
        </dl>
      </section>

    </div>
<?php endif; ?>
  </main>
  <footer class="site-foot"><span>&copy; 2017&ndash;<?=date('Y')?> Sniku</span><i aria-hidden="true">&middot;</i><a href="../privacy/">Prywatność</a></footer>

<?php if ($flash): ?>
  <div class="toast" id="toast" role="status"><?=e($flash)?></div>
<?php else: ?>
  <div class="toast" id="toast" role="status" hidden></div>
<?php endif; ?>

  <script src="../js/explorer.js?v=7"></script>
<?php if ($allowed): ?>
  <script src="../js/admin.js?v=3"></script>
<?php endif; ?>
</body>

</html>
