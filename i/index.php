<?php
    // Gallery of this folder: browses the subfolders and shows the pictures as
    // a grid of thumbnails with a viewer. It needs a Discord login: accounts in
    // GALLERY_ADMINS (inc/config.php) can also add, move and delete files, the
    // ones in GALLERY_VIEWERS can look, and every other account sees and fills
    // only a folder of its own (blocked for the ones in GALLERY_UPLOADERS). A shared link (?s=) opens one folder
    // without a login. Only this file is in git; the pictures live on the
    // server (see .gitignore). The logic is in inc/gallery.php.
    require __DIR__ . '/../inc/gallery.php';
    require __DIR__ . '/../inc/meta.php';

    $base = str_replace('\\', '/', __DIR__);

    // i/u/<token>/<file> or in a subfolder, i/u/<token>/<folder>/<file>: a file
    // of the folder of an account, by the random name of its link (nginx sends
    // these here, server/nginx/sanakan.conf)
    if (($linkRel = userLinkRel($_SERVER['REQUEST_URI'] ?? '')) !== null) {
        sendUserFile($base, $linkRel);
        exit;
    }

    // i/private/<file>: a file of the private folder, given out only to those
    // who may see it (nginx sends these here too, server/nginx/sanakan.conf)
    $requested = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
    if (strpos($requested, siteRoot() . 'i/' . PRIVATE_DIR . '/') === 0) {
        sendPrivateFile($base, substr($requested, strlen(siteRoot() . 'i/')));
        exit;
    }

    // i/<file>.png|jpg|gif|webm|mp4 that is not there (nginx sends these here
    // too): the old link of a picture changed to WebP, or of a film changed to
    // WebM, goes on to the new one; anything else is a 404
    if (strpos($requested, siteRoot() . 'i/') === 0 && preg_match('/\.(?:png|jpe?g|gif|webm|mp4)$/i', $requested)) {
        sendMovedLink(substr($requested, strlen(siteRoot() . 'i/')));
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST')
        handlePost($base);

    // ?mediajobs: how the pictures and films this account uploaded are
    // converting, for the gallery to keep its status line and badges up to date
    // (js/explorer.js)
    if (isset($_GET['mediajobs'])) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $user = siteUser();
        $jobs = [];
        $summary = '';
        if ($user !== null) {
            $mine = userMediaJobs((string)$user['id']);
            $summary = mediaJobsSummary($mine);
            foreach ($mine as $job)
                $jobs[] = [
                    'rel' => publicRel($job['rel']),
                    'target' => !empty($job['targetRel']) ? publicRel($job['targetRel']) : null,
                    'status' => $job['status'] ?? '',
                    'label' => mediaJobLabel($job),
                    'text' => mediaJobText($job),
                    'short' => mediaJobShort($job),
                    'active' => mediaJobActive($job)
                ];
        }
        echo json_encode(['jobs' => $jobs, 'summary' => $summary]);
        exit;
    }

    // ?s=token: a shared link, its folder opens from now on in this session
    if (isset($_GET['s'])) {
        $shared = openShare($base, $_GET['s']);
        if ($shared === null)
            setFlash('Ten link wygasł albo został wyłączony.');
        header('Location: ' . ($shared === null ? './' : folderUrl($shared)), true, 303);
        exit;
    }

    // ?zip&p=folder: the whole folder as one ZIP
    if (isset($_GET['zip'])) {
        $zipDir = resolvePath($base, $_GET['p'] ?? '', true);
        if (!$zipDir || !galleryCanSee($base, $zipDir[1])) {
            http_response_code($zipDir || !galleryCanView() ? 403 : 404);
            exit;
        }
        sendZip(galleryZipRoots([$zipDir]), ($zipDir[1] === '' ? 'galeria' : displayName($zipDir[1])) . '.zip', folderUrl($zipDir[1]));
    }

    // ?preview=token: the WebP made for a manual change, shown next to the
    // original until the admin accepts or rejects it (inc/gallery.php). Only the
    // admin who made it, and only while it waits.
    if (isset($_GET['preview'])) {
        $user = siteUser();
        $preview = webpPreviews()[(string)$_GET['preview']] ?? null;
        $path = is_array($preview) ? webpPreviewDir() . '/' . ($preview['candidate'] ?? '') : '';
        if ($user === null || !galleryIsAdmin() || !is_array($preview)
                || (string)($preview['by'] ?? '') !== (string)$user['id'] || !is_file($path)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: image/webp');
        header('Cache-Control: no-store');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    if (isset($_GET['thumb'])) {
        $thumb = resolvePath($base, $_GET['thumb'], false);
        $canView = $thumb && galleryCanSee($base, $thumb[1]);
        // PHP locks the session file until the request ends; closed, the many
        // thumbnails of one page do not wait for each other in line
        if (session_status() === PHP_SESSION_ACTIVE)
            session_write_close();
        if ($canView)
            sendThumb($base, $_GET['thumb']);
        else
            http_response_code($thumb ? 403 : 404);
        exit;
    }

    // Discord login (inc/auth.php); after it the visitor comes back to this folder
    $from = resolvePath($base, $_GET['p'] ?? '', true);
    handleLoginRequest(siteRoot() . 'i/' . ($from && $from[1] !== '' ? folderUrl($from[1]) : ''));

    // without access the page only offers the login; the folder is not even read
    $user = siteUser();
    // its own folder and the shared ones, for who does not see the whole gallery
    $homes = galleryHomes($base);
    $whole = galleryCanView();
    $locked = !$whole && !$homes;
    // an account without access can ask for it; one request at a time
    $request = $user && $locked ? pendingRequest('gallery', $user['id']) : null;
    $admin = !$locked && galleryIsAdmin();
    $own = $admin ? null : ownFolder($base);
    // the account's own trash (?kosz=1): what it deleted from its folder, to
    // bring back or drop for good without asking a gallery admin
    $ownTrash = $own === null ? [] : ownTrashItems((string)$user['id']);
    $showTrash = $own !== null && isset($_GET['kosz']);
    // the pictures and films this account uploaded that are converting or just
    // done, shown in the gallery; and the job of each file in the folder, for
    // its badge
    $myJobs = $user ? userMediaJobs((string)$user['id']) : [];
    // the full list of the account's jobs (?zadania=1), with what is still going
    $showTasks = $user && !$locked && isset($_GET['zadania']);
    $taskJobs = $showTasks ? userMediaJobs((string)$user['id'], 500, true) : [];
    $finishedTasks = $showTasks ? count(array_filter($taskJobs, function ($job) { return !mediaJobActive($job); })) : 0;
    $jobByRel = [];
    foreach (mediaJobs() as $job) {
        if (!empty($job['rel']))
            $jobByRel[$job['rel']] = $job;
        if (!empty($job['targetRel']))
            $jobByRel[$job['targetRel']] = $job;
    }
    // what an upload may be: the images (AVIF included), the HEIC and HEIF the
    // server can write as WebP, and for a gallery admin the films too
    $uploadTypes = array_merge(IMAGE_TYPES, canConvertHeif() ? CONVERT_IMAGE_TYPES : []);
    if ($admin)
        $uploadTypes = array_merge($uploadTypes, VIDEO_TYPES);
    $flash = takeFlash();
    $requested = trim((string)($_GET['p'] ?? ''), '/');
    $loginUrl = '?login' . ($requested === '' ? '' : '&p=' . rawurlencode($requested));
    // the login page itself is a 200, as Discord shows no link preview for a 401
    if ($locked)
        http_response_code(!authConfigured() ? 503 : ($user ? 403 : 200));

    // what the visitor does not see is not there; without the whole gallery
    // it starts in its own or a shared folder
    $dir = $locked ? [$base, ''] : resolvePath($base, $requested, true);
    if (!$locked && $dir && !galleryCanSee($base, $dir[1]))
        $dir = null;
    $notFound = $dir === null && !($requested === '' && !$whole);
    if ($dir === null) {
        if ($notFound)
            http_response_code(404);
        $dir = $whole || $locked ? [$base, ''] : resolvePath($base, $homes[0], true);
    }
    list($dirPath, $dirRel) = $dir;
    // the visitor may change things here: an admin anywhere, an account in its own folder
    $manage = $admin || ($own !== null && inFolder($dirRel, $own));
    $ownUse = $manage && !$admin && $own !== null ? treeUse($base . '/' . $own) : null;

    // ?q= searches the whole gallery; ?p= then is the folder the search started from
    $query = trim((string)($_GET['q'] ?? ''));
    $searching = !$locked && $query !== '';
    $moreFound = false;

    $folders = [];
    $files = [];
    if ($searching) {
        list($folders, $files, $moreFound) = searchGallery($base, $query);
    } else {
        foreach ($locked ? [] : listNames($dirPath, $dirRel) as $name) {
            $full = $dirPath . '/' . $name;
            $rel = ltrim($dirRel . '/' . $name, '/');
            if (is_dir($full))
                $folders[] = folderEntry($full, $rel);
            else
                $files[] = fileEntry($full, $rel);
        }
    }
    // a film still converting (or one that failed) gets its status on the tile
    foreach ($files as &$file)
        $file['job'] = $jobByRel[$file['rel']] ?? null;
    unset($file);
    $totalBytes = array_sum(array_column($files, 'size'));

    // the folders on the way here, as links where the visitor may go
    $crumbs = [];
    $path = '';
    foreach ($dirRel === '' ? [] : explode('/', $dirRel) as $part) {
        $path = ltrim($path . '/' . $part, '/');
        $crumbs[] = ['name' => displayName($path), 'rel' => $path, 'open' => galleryCanSee($base, $path)];
    }

    // the first tile: one folder up, or back from the search results; null in
    // the top folder and where the folder up is not the visitor's to see
    $parent = null;
    if ($searching) {
        $parent = ['url' => folderUrl($dirRel), 'label' => 'Wróć do: ' . displayPath($dirRel)];
    } else if ($dirRel !== '') {
        $parentRel = strpos($dirRel, '/') === false ? '' : substr($dirRel, 0, strrpos($dirRel, '/'));
        if (galleryCanSee($base, $parentRel))
            $parent = ['url' => folderUrl($parentRel), 'label' => 'Wyżej: ' . displayPath($parentRel)];
    }

    $summary = [];
    if ($searching)
        $summary[] = 'Wyniki dla „' . $query . '” w całej galerii';
    if ($folders)
        $summary[] = count($folders) . ' ' . plural(count($folders), 'folder', 'foldery', 'folderów');
    $summary[] = count($files) . ' ' . plural(count($files), 'plik', 'pliki', 'plików');
    if ($totalBytes)
        $summary[] = formatSize($totalBytes);
    if ($moreFound)
        $summary[] = 'pokazano pierwsze ' . SEARCH_LIMIT;
    if (!$admin && in_array($dirRel, sessionShares($base), true) && $dirRel !== $own)
        $summary[] = 'udostępnione linkiem';
    if ($showTrash)
        $summary = ['Kosz: ' . count($ownTrash) . ' ' . plural(count($ownTrash), 'element', 'elementy', 'elementów')];

?>
<!DOCTYPE html>
<html lang="pl"<?=hudHtmlAttributes($user)?>>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="author" content="Sniku" />
<?=metaTags('Galeria', 'Galeria obrazków bota Sanakan, dostęp po zalogowaniu przez Discord.', '/i/', 'i')?>
  <title><?=e($searching ? 'Szukaj: ' . $query : ($dirRel === '' ? 'Galeria' : displayPath($dirRel)))?> &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../apple-touch-icon.png" />
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=61db60f595" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=ed18f56732" type="text/css" rel="stylesheet" />
  <script src="../js/hud.js?v=2894733f39"></script>
</head>

<body class="explorer-page">
  <main class="content">
    <header class="ex-header">
      <div class="ex-top">
        <a class="back hud-corners" href="../" title="Strona główna">&larr; Sanakan</a>
<?php if ($user): ?>
        <?=accountMenuHtml($user, siteRoles(), $_SERVER['REQUEST_URI'] ?? '')?>
<?php endif; ?>
      </div>
      <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
      <h1 class="hud-title">Galeria</h1>
<?php if (!$locked): ?>
      <nav class="crumbs" aria-label="Ścieżka">
<?php if ($whole): ?>
        <a href="./">i</a>
<?php else: ?>
        <span>i</span>
<?php endif; ?>
<?php foreach ($crumbs AS $crumb): ?>
        <span aria-hidden="true">/</span>
<?php if ($crumb['open']): ?>
        <a href="<?=e(folderUrl($crumb['rel']))?>"><?=e($crumb['name'])?></a>
<?php else: ?>
        <span><?=e($crumb['name'])?></span>
<?php endif; ?>
<?php endforeach; ?>
      </nav>
      <p class="ex-meta"><?=e(implode(' · ', $summary))?></p>
<?php if (count($homes) > 1 || ($whole && $own !== null)): ?>
      <p class="ex-meta">Twoje foldery:
<?php foreach ($homes as $i => $home): ?>
        <?=$i ? '&middot; ' : ''?><a href="<?=e(folderUrl($home))?>"><?=e($home === $own ? 'własny, ' . displayName($home) : 'udostępniony, ' . displayPath($home))?></a>
<?php endforeach; ?>
      </p>
<?php endif; ?>
<?php endif; ?>
    </header>

<?php if ($locked): ?>
    <section class="locked hud-corners">
      <svg class="lock-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="1.5" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
<?php if (!authConfigured()): ?>
      <h2>Galeria jest wyłączona</h2>
      <p>Logowanie nie jest jeszcze skonfigurowane na serwerze.</p>
<?php elseif ($user): ?>
      <h2>Brak dostępu</h2>
      <p>Konto <?=e($user['name'])?> nie ma dostępu do galerii. Wyloguj się, jeśli chcesz użyć innego konta.</p>
<?php if (!hasServerRole($user['id']) && !inAccessList('galleryUploaders', $user['id'], true)): ?>
      <p>Własny folder w galerii mają konta z rolą na serwerze Sanakana (od user w górę).</p>
<?php endif; ?>
<?php if ($request): ?>
      <p class="request-sent">Prośba o dostęp wysłana <?=e(date('j.m H:i', $request['time']))?>. Administrator zobaczy ją w panelu.</p>
<?php else: ?>
      <form class="request-form" method="post" action="index.php">
        <input type="hidden" name="csrf" value="<?=e(siteCsrf())?>" />
        <input type="hidden" name="action" value="request-access" />
        <input type="hidden" name="back" value="<?=e($requested === '' ? './' : folderUrl($requested))?>" />
        <input type="text" name="note" maxlength="<?=REQUEST_NOTE_LENGTH?>" placeholder="Kim jesteś? (opcjonalnie)" aria-label="Notatka do prośby" />
        <button type="submit" class="admin-btn primary">Poproś o dostęp</button>
      </form>
<?php endif; ?>
<?php else: ?>
      <h2>Galeria wymaga logowania</h2>
      <p>Zaloguj się kontem Discord, żeby ją zobaczyć.</p>
      <a class="admin-btn primary" href="<?=e($loginUrl)?>">Zaloguj przez Discord</a>
<?php endif; ?>
    </section>
<?php else: ?>

<?php if ($showTasks): ?>
    <div class="toolbar" id="toolbar">
      <a class="admin-btn" href="<?=e(folderUrl($dirRel))?>">&larr; Wróć do galerii</a>
      <form class="media-clear-form" method="post" action="index.php">
        <input type="hidden" name="csrf" value="<?=e(siteCsrf())?>" />
        <input type="hidden" name="action" value="media-clear" />
        <input type="hidden" name="back" value="<?=e('?zadania=1' . ($dirRel === '' ? '' : '&p=' . rawurlencode(publicRel($dirRel))))?>" />
        <button type="submit" class="admin-btn"<?=$finishedTasks ? '' : ' disabled'?>>Wyczyść zakończone</button>
      </form>
      <span class="admin-hint">Zamiana zdjęć i filmów na WebP/WebM w tle. Zakończone znikają same po <?=intdiv(MEDIA_JOB_DONE_SHOW, 60)?> min; możesz je wyczyścić od razu. Co jest w toku, zostaje.</span>
    </div>
    <div class="media-tasks" id="media-tasks" data-media-jobs data-active="<?=$taskJobs && array_filter($taskJobs, 'mediaJobActive') ? '1' : '0'?>">
<?php if (!$taskJobs): ?>
      <p class="empty">Brak zadań konwersji.</p>
<?php else: foreach ($taskJobs AS $job): ?>
      <div class="media-task media-job <?=e($job['status'])?>" data-job="<?=e(publicRel($job['rel']))?>" data-status="<?=e($job['status'])?>" title="<?=e(mediaJobText($job))?>">
        <span class="media-job-dot" aria-hidden="true"></span>
        <span class="media-task-name"><?=e($job['name'])?></span>
        <span class="media-task-state"><?=e(mediaJobText($job))?></span>
        <span class="media-task-time"><?=e(date('j.m H:i', $job['updated'] ?? $job['created'] ?? 0))?></span>
      </div>
<?php endforeach; endif; ?>
    </div>
<?php else: ?>

<?php if ($myJobs): ?>
    <section class="media-jobs hud-corners" id="media-jobs" data-media-jobs data-active="<?=array_filter($myJobs, 'mediaJobActive') ? '1' : '0'?>">
      <div class="media-jobs-head">
        <span class="media-jobs-title">Konwersja plików</span>
        <span class="media-jobs-summary" data-media-jobs-summary id="media-jobs-summary"><?=e(mediaJobsSummary($myJobs))?></span>
        <a class="media-jobs-link" href="?zadania=1<?= $dirRel === '' ? '' : '&p=' . rawurlencode(publicRel($dirRel)) ?>">Zadania &rarr;</a>
      </div>
      <ul class="media-jobs-list">
<?php foreach ($myJobs AS $job): ?>
        <li class="media-job <?=e($job['status'])?>" data-job="<?=e(publicRel($job['rel']))?>" data-status="<?=e($job['status'])?>" title="<?=e(mediaJobText($job))?>">
          <span class="media-job-dot" aria-hidden="true"></span>
          <span class="media-job-name"><?=e($job['name'])?></span>
          <span class="media-job-state"><?=e(mediaJobShort($job))?></span>
        </li>
<?php endforeach; ?>
      </ul>
    </section>
<?php endif; ?>

<?php if ($showTrash): ?>
    <div class="toolbar" id="toolbar">
      <a class="admin-btn" href="<?=e(folderUrl($own))?>">&larr; Wróć do folderu</a>
      <span class="admin-hint">Usunięte z twojego folderu; po <?=TRASH_DAYS?> dniach znikają na zawsze.</span>
    </div>
    <div class="gallery-trash" id="trash-list" data-csrf="<?=e(siteCsrf())?>">
<?php if (!$ownTrash): ?>
      <p class="empty">Kosz jest pusty.</p>
<?php else: foreach ($ownTrash as $itemId => $item): $daysLeft = max(0, TRASH_DAYS - intdiv(time() - ($item['deleted'] ?? 0), 86400)); ?>
      <div class="trash-row">
        <span class="trash-name">
          <b><?=e($item['name'])?></b>
          <code><?=e(displayPath($item['from'] ?? ''))?></code>
        </span>
        <span class="trash-info"><?=e(formatSize($item['size'] ?? 0))?> &middot; usunięte <?=e(date('j.m.Y H:i', $item['deleted'] ?? 0))?> &middot; zostało <?=$daysLeft?> <?=plural($daysLeft, 'dzień', 'dni', 'dni')?></span>
        <span class="trash-actions">
          <button type="button" class="admin-btn" data-action="restore" data-item="<?=e($itemId)?>">Przywróć</button>
          <button type="button" class="admin-btn danger" data-action="trash-delete" data-item="<?=e($itemId)?>" data-confirm="Usunąć <?=e($item['name'])?> z kosza? Plik zostanie jeszcze przez <?=TRASH_DAYS?> dni; administrator może go przywrócić.">Usuń z kosza</button>
        </span>
      </div>
<?php endforeach; endif; ?>
    </div>
<?php else: ?>

<?php if ($notFound): ?>
    <p class="notice"><?=$whole ? 'Nie ma takiego folderu, poniżej jest główny folder galerii.' : 'Nie ma tu takiego folderu, poniżej jest ' . ($dirRel === $own ? 'twój folder' : 'udostępniony folder') . '.'?></p>
<?php endif; ?>

<?php if ($folders || $files || $manage): ?>
    <div class="toolbar" id="toolbar">
      <label class="search hud-corners">
        <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path d="M15.5 15.5 21 21" /></svg>
        <input id="ex-search" type="search" autocomplete="off" spellcheck="false" placeholder="Szukaj po nazwie, Enter: <?=$whole ? 'w całej galerii' : ($user ? 'we wszystkich twoich folderach' : 'w całym udostępnionym folderze')?>" aria-label="Szukaj po nazwie"
               value="<?=e($query)?>" data-dir="<?=e(publicRel($dirRel))?>" data-searching="<?=$searching ? '1' : '0'?>" />
        <kbd aria-hidden="true" title="Naciśnij /, żeby szukać">/</kbd>
      </label>
      <div class="sort" role="group" aria-label="Sortowanie">
        <button type="button" data-sort="name" aria-pressed="true">Nazwa</button>
        <button type="button" data-sort="date" aria-pressed="false">Data</button>
        <button type="button" data-sort="size" aria-pressed="false">Rozmiar</button>
      </div>
<?php if (!$searching && ($folders || $files) && canZip()): ?>
      <a class="admin-btn zip-btn" href="?zip&amp;p=<?=e(rawurlencode(publicRel($dirRel)))?>" title="Cały folder <?=e(displayPath($dirRel))?> razem z podfolderami, do <?=e(formatSize(ZIP_MAX_BYTES))?>">Pobierz folder (ZIP)</a>
<?php endif; ?>
<?php if ($manage): ?>
      <div class="admin-bar" id="admin-bar">
<?php if (!$searching): ?>
        <button type="button" class="admin-btn primary" id="act-upload">+ Dodaj <?=$admin ? 'pliki' : 'zdjęcia'?></button>
        <input type="file" id="upload-input" multiple accept="<?=e('.' . implode(',.', $uploadTypes))?>" hidden />
<?php if ($admin && (canConvertToWebp() || canConvertVideo())): ?>
        <label class="admin-check" title="PNG, JPG, GIF i AVIF zapisują się jako WebP, a MP4 jako WebM (gdy serwer ma potrzebne narzędzia); plik zapisuje się od razu, zmiana idzie w tle. Gdy nowy plik nie wyjdzie mniejszy, zostaje oryginał.">
          <input type="checkbox" id="upload-webp" /> Zamieniaj na WebP/WebM
        </label>
<?php endif; ?>
        <button type="button" class="admin-btn" id="act-mkdir">Nowy folder</button>
<?php if (!$admin): ?>
        <a class="admin-btn" href="?kosz=1" title="Rzeczy usunięte z twojego folderu">Kosz<?=$ownTrash ? ' (' . count($ownTrash) . ')' : ''?></a>
<?php endif; ?>
<?php if ($admin && $dirRel !== '' && $dirRel !== USERS_DIR): ?>
        <button type="button" class="admin-btn" id="act-share" title="Link, który otwiera ten folder bez logowania">Udostępnij</button>
<?php endif; ?>
<?php endif; ?>
        <button type="button" class="admin-btn" id="act-select" aria-pressed="false">Zaznacz</button>
        <span class="admin-selection" id="admin-selection" hidden>
          <span class="admin-count" id="admin-count"></span>
          <button type="button" class="admin-btn" id="act-select-all">Wszystkie</button>
          <button type="button" class="admin-btn" id="act-rename">Zmień nazwę</button>
          <button type="button" class="admin-btn" id="act-move"<?=$admin ? '' : ' hidden'?>>Przenieś</button>
          <button type="button" class="admin-btn" id="act-rotate-left" title="Obróć w lewo (PNG, JPG, WebP)">&#8634; Obróć</button>
          <button type="button" class="admin-btn" id="act-rotate-right" title="Obróć w prawo (PNG, JPG, WebP)">Obróć &#8635;</button>
<?php if ($admin && canConvertToWebp()): ?>
          <button type="button" class="admin-btn" id="act-webp" title="Zamienia na WebP z wybraną jakością; wynik zobaczysz obok oryginału i zapiszesz po akceptacji. Działa też na plikach WebP.">Na WebP…</button>
<?php endif; ?>
<?php if (canZip()): ?>
          <button type="button" class="admin-btn" id="act-zip">Pobierz ZIP</button>
<?php endif; ?>
          <button type="button" class="admin-btn danger" id="act-delete">Usuń</button>
        </span>
<?php if (!$searching && $admin): ?>
        <span class="admin-hint">Możesz też przeciągnąć pliki na stronę albo wkleić obrazek ze schowka (Ctrl+V). Metadane zdjęć (np. miejsce zrobienia) są usuwane. Pliki zapisują się od razu, a jako WebP (zdjęcia) lub WebM (filmy) zamieniają się w tle, gdy wyjdą mniejsze; status zobaczysz wyżej. Limit: <?=e(formatSize(uploadLimit()))?> na plik.</span>
<?php elseif (!$searching): $ownLimit = userFilesLimit($user['id']); ?>
        <span class="admin-hint">Twój folder: <b><?=$ownUse[0]?> z <?=$ownLimit?></b> <?=plural($ownLimit, 'zdjęcia', 'zdjęć', 'zdjęć')?>, <b><?=e(formatSize($ownUse[1]))?> z <?=e(formatSize(USER_TOTAL_MAX_BYTES))?></b>. Zdjęcia (PNG, JPG, GIF, WebP, AVIF, HEIC) do <?=e(formatSize(min(USER_FILE_MAX_BYTES, uploadLimit() ?: USER_FILE_MAX_BYTES)))?> zapisują się od razu i zamieniają w tle na WebP, gdy wychodzi mniejszy, zawsze bez metadanych (np. miejsca zrobienia). Status zobaczysz wyżej. Możesz też przeciągnąć je na stronę albo wkleić ze schowka (Ctrl+V). Widzisz go tylko ty i administratorzy galerii.</span>
<?php endif; ?>
      </div>
<?php endif; ?>
      <div class="search-info" id="ex-search-info" aria-live="polite"></div>
    </div>
<?php endif; ?>

    <div class="grid" id="grid">
<?php if ($parent): ?>
      <a class="tile up hud-corners" href="<?=e($parent['url'])?>" id="ex-up" title="<?=e($parent['label'])?> (Backspace)">
        <span class="thumb">
          <svg class="up-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M5.5 11.5 12 5l6.5 6.5" /></svg>
        </span>
        <span class="label">
          <span class="name">..</span>
          <span class="info"><?=e($parent['label'])?></span>
        </span>
      </a>
<?php endif; ?>
<?php foreach ($folders AS $folder): ?>
      <a class="tile folder hud-corners" href="<?=e(folderUrl($folder['rel']))?>" data-rel="<?=e(publicRel($folder['rel']))?>" data-name="<?=e(lower($folder['name']))?>" data-date="<?=$folder['mtime']?>" data-size="-1">
        <span class="thumb">
<?php if ($manage): ?>
          <span class="check" aria-hidden="true"></span>
<?php endif; ?>
<?php if ($folder['previews']): ?>
          <span class="mosaic n<?=count($folder['previews'])?>">
<?php foreach ($folder['previews'] AS $preview): ?>
            <img src="<?=e($preview)?>" alt="" loading="lazy" decoding="async" />
<?php endforeach; ?>
          </span>
<?php else: ?>
          <svg class="folder-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5V18a1.5 1.5 0 0 0 1.5 1.5h15A1.5 1.5 0 0 0 21 18V9a1.5 1.5 0 0 0-1.5-1.5h-8L9.5 5h-5A1.5 1.5 0 0 0 3 6.5z" /></svg>
<?php endif; ?>
        </span>
        <span class="label">
          <span class="name"><svg class="name-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5V18a1.5 1.5 0 0 0 1.5 1.5h15A1.5 1.5 0 0 0 21 18V9a1.5 1.5 0 0 0-1.5-1.5h-8L9.5 5h-5A1.5 1.5 0 0 0 3 6.5z" /></svg><?=e($folder['name'])?></span>
          <span class="info"><?=$folder['count']?> <?=plural($folder['count'], 'element', 'elementy', 'elementów')?></span>
<?php if ($searching): ?>
          <span class="where" title="<?=e(displayPath(dirname($folder['rel']) === '.' ? '' : dirname($folder['rel'])))?>"><?=e(displayPath(dirname($folder['rel']) === '.' ? '' : dirname($folder['rel'])))?></span>
<?php endif; ?>
        </span>
      </a>
<?php endforeach; ?>
<?php foreach ($files AS $file): ?>
      <a class="tile file hud-corners" href="<?=e(fileUrl($file['rel']))?>" target="_blank" rel="noopener" data-rel="<?=e(publicRel($file['rel']))?>"
         data-name="<?=e(lower($file['name']))?>" data-date="<?=$file['mtime']?>" data-size="<?=$file['size']?>"
         data-kind="<?=$file['kind']?>" data-title="<?=e($file['name'])?>"
         data-details="<?=e(implode(' · ', array_filter([$file['dims'], formatSize($file['size']), date('j.m.Y', $file['mtime'])])))?>">
        <span class="thumb">
<?php if ($manage): ?>
          <span class="check" aria-hidden="true"></span>
<?php endif; ?>
<?php if ($file['thumb']): ?>
          <img src="<?=e($file['thumb'])?>" alt="" loading="lazy" decoding="async" />
<?php elseif ($file['kind'] === 'video'): ?>
          <video class="tile-video" data-src="<?=e(fileUrl($file['rel']))?>#t=0.1" muted loop playsinline preload="none"></video>
<?php else: ?>
          <span class="placeholder"><b><?=e(strtoupper($file['ext']) ?: 'PLIK')?></b><?=e(formatSize($file['size']))?></span>
<?php endif; ?>
<?php if ($file['ext'] === 'gif' || $file['kind'] === 'video'): ?>
          <span class="badge"><?=e(strtoupper($file['ext']))?></span>
<?php endif; ?>
<?php if (!empty($file['job']) && in_array($file['job']['status'] ?? '', ['pending', 'converting', 'failed'], true)): ?>
          <span class="job-badge <?=e($file['job']['status'])?>" data-job="<?=e(publicRel($file['rel']))?>" title="<?=e(mediaJobText($file['job']))?>"><?=e(mediaJobLabel($file['job']))?></span>
<?php endif; ?>
        </span>
        <span class="label">
          <span class="name" title="<?=e($file['name'])?>"><?=e($file['name'])?></span>
          <span class="info"><?=e(implode(' · ', array_filter([$file['dims'], formatSize($file['size'])])))?></span>
<?php if ($searching): ?>
          <span class="where" title="<?=e(displayPath(dirname($file['rel']) === '.' ? '' : dirname($file['rel'])))?>"><?=e(displayPath(dirname($file['rel']) === '.' ? '' : dirname($file['rel'])))?></span>
<?php endif; ?>
        </span>
      </a>
<?php endforeach; ?>
    </div>

<?php if (!$folders && !$files): ?>
    <p class="empty"><?=$searching ? 'Nic nie znaleziono' . ($whole ? ' w całej galerii.' : '.') : 'Ten folder jest pusty.' . ($manage ? ' Przeciągnij tu ' . ($admin ? 'pliki' : 'zdjęcia') . ', wklej obrazek (Ctrl+V) albo użyj „Dodaj ' . ($admin ? 'pliki' : 'zdjęcia') . '”.' : '')?></p>
<?php endif; ?>
    <p class="empty" id="ex-no-results" hidden>Brak pasujących plików.</p>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
  </main>
  <footer class="site-foot"><span>&copy; 2017&ndash;<?=date('Y')?> Sniku</span><i aria-hidden="true">&middot;</i><a href="../state/">Status</a><i aria-hidden="true">&middot;</i><a href="../privacy/">Prywatność</a></footer>

<?php if (!$locked): ?>
  <div class="viewer" id="viewer" hidden role="dialog" aria-modal="true" aria-label="Podgląd">
    <div class="viewer-bar">
      <div class="viewer-title">
        <span class="viewer-name" id="viewer-name"></span>
        <span class="viewer-details" id="viewer-details"></span>
      </div>
      <div class="viewer-actions">
<?php if ($manage): ?>
        <button type="button" class="viewer-btn" id="viewer-rename" title="Zmień nazwę (F2)">Zmień nazwę</button>
        <button type="button" class="viewer-btn danger" id="viewer-delete" title="Usuń do kosza (Delete)">Usuń</button>
<?php endif; ?>
        <button type="button" class="viewer-btn" id="viewer-copy">Kopiuj link</button>
        <a class="viewer-btn" id="viewer-open" href="#" target="_blank" rel="noopener">Otwórz</a>
        <button type="button" class="viewer-btn viewer-close" id="viewer-close" aria-label="Zamknij">&times;</button>
      </div>
    </div>
    <div class="viewer-stage" id="viewer-stage">
      <button type="button" class="viewer-nav prev hud-corners" id="viewer-prev" aria-label="Poprzedni">&larr;</button>
      <img class="viewer-img" id="viewer-img" alt="" draggable="false" />
      <video class="viewer-img" id="viewer-video" controls loop playsinline draggable="false" hidden></video>
      <span class="viewer-loading" id="viewer-loading">Wczytywanie&hellip;</span>
      <button type="button" class="viewer-nav next hud-corners" id="viewer-next" aria-label="Następny">&rarr;</button>
    </div>
  </div>
<?php endif; ?>

<?php if ($flash): ?>
  <div class="toast" id="toast" role="status"><?=e($flash)?></div>
<?php else: ?>
  <div class="toast" id="toast" role="status" hidden></div>
<?php endif; ?>

<?php if ($manage && !$showTrash): ?>
  <div class="drop" id="drop" hidden>
    <div class="drop-box hud-corners">Upuść pliki, żeby dodać je do <?=e(displayPath($dirRel))?></div>
  </div>

  <div class="progress" id="progress" hidden>
    <span class="progress-text" id="progress-text"></span>
    <span class="progress-bar"><span id="progress-fill"></span></span>
  </div>

  <dialog class="ex-dialog" id="dlg-mkdir">
    <form id="form-mkdir">
      <h2>Nowy folder</h2>
      <p>W folderze <?=e(displayPath($dirRel))?></p>
      <input type="text" name="name" maxlength="150" autocomplete="off" spellcheck="false" placeholder="Nazwa folderu" required />
      <p class="dialog-error" hidden></p>
      <div class="dialog-actions">
        <button type="button" class="admin-btn" data-close>Anuluj</button>
        <button type="submit" class="admin-btn primary">Utwórz</button>
      </div>
    </form>
  </dialog>

  <dialog class="ex-dialog" id="dlg-rename">
    <form id="form-rename">
      <h2>Zmień nazwę</h2>
      <p id="rename-what"></p>
      <input type="text" name="name" maxlength="150" autocomplete="off" spellcheck="false" required />
      <p class="dialog-error" hidden></p>
      <div class="dialog-actions">
        <button type="button" class="admin-btn" data-close>Anuluj</button>
        <button type="submit" class="admin-btn primary">Zmień</button>
      </div>
    </form>
  </dialog>

  <dialog class="ex-dialog" id="dlg-move">
    <form id="form-move">
      <h2>Przenieś</h2>
      <p id="move-what"></p>
      <select name="target" size="8" required aria-label="Folder docelowy"></select>
      <p class="dialog-error" hidden></p>
      <div class="dialog-actions">
        <button type="button" class="admin-btn" data-close>Anuluj</button>
        <button type="submit" class="admin-btn primary">Przenieś</button>
      </div>
    </form>
  </dialog>

<?php if ($admin): ?>
  <dialog class="ex-dialog" id="dlg-share">
    <form id="form-share">
      <h2>Udostępnij folder</h2>
      <p>Każdy, kto ma link, zobaczy <?=e(displayPath($dirRel))?> z podfolderami i pobierze je, także bez logowania. Wyłączyć go można w panelu.</p>
      <select name="days" aria-label="Jak długo link działa">
        <option value="1">na 1 dzień</option>
        <option value="7" selected>na 7 dni</option>
        <option value="30">na 30 dni</option>
        <option value="0">bez końca, do wyłączenia</option>
      </select>
      <input type="text" name="link" readonly hidden aria-label="Link" />
      <p class="dialog-error" hidden></p>
      <div class="dialog-actions">
        <button type="button" class="admin-btn" data-close>Zamknij</button>
        <button type="submit" class="admin-btn primary">Utwórz link</button>
      </div>
    </form>
  </dialog>
<?php endif; ?>

<?php if ($admin && canConvertToWebp()): ?>
  <dialog class="ex-dialog" id="dlg-webp">
    <form id="form-webp">
      <h2>Zamień na WebP</h2>
      <p id="webp-what"></p>
      <label class="webp-quality">Jakość <output id="webp-quality-value"><?=WEBP_QUALITY_DEFAULT?></output>
        <input type="range" id="webp-quality" name="quality" min="<?=WEBP_QUALITY_MIN?>" max="<?=WEBP_QUALITY_MAX?>" step="1" value="<?=WEBP_QUALITY_DEFAULT?>" />
      </label>
      <p>Niższa jakość to mniejszy plik. Wynik zobaczysz obok oryginału i zapiszesz dopiero po akceptacji; gdy WebP wyjdzie większy, nic się nie zmieni. Można tak przeliczyć też plik WebP.</p>
      <p class="dialog-error" hidden></p>
      <div class="dialog-actions">
        <button type="button" class="admin-btn" data-close>Anuluj</button>
        <button type="submit" class="admin-btn primary">Zamień</button>
      </div>
    </form>
  </dialog>

  <dialog class="ex-dialog webp-result" id="dlg-webp-result">
    <h2>Konwersja na WebP</h2>
    <p id="webp-result-info"></p>
    <div class="webp-processing" id="webp-processing" hidden>
      <span class="webp-spinner" aria-hidden="true"></span>
      <span id="webp-processing-text">Przetwarzanie…</span>
    </div>
    <div id="webp-result-body" hidden>
      <div class="webp-compare" id="webp-compare">
        <img id="webp-orig" alt="Oryginał" />
        <img id="webp-new" class="webp-new" alt="WebP" />
        <span class="webp-tag orig">Oryginał</span>
        <span class="webp-tag new">WebP</span>
        <span class="webp-divider" id="webp-divider" aria-hidden="true"></span>
      </div>
      <input type="range" id="webp-slider" class="webp-slider" min="0" max="100" value="50" aria-label="Porównanie oryginału z WebP" />
    </div>
    <div class="dialog-actions" id="webp-result-actions" hidden>
      <a class="admin-btn" id="webp-open" href="#" target="_blank" rel="noopener">Otwórz wynik</a>
      <button type="button" class="admin-btn danger" id="webp-reject">Odrzuć</button>
      <button type="button" class="admin-btn primary" id="webp-accept">Akceptuj</button>
    </div>
  </dialog>
<?php endif; ?>

  <dialog class="ex-dialog" id="dlg-delete">
    <form id="form-delete">
      <h2>Usuń</h2>
      <p id="delete-what"></p>
      <p class="dialog-warning">Trafią do kosza na <?=TRASH_DAYS?> dni; przywrócić je można w koszu.</p>
      <p class="dialog-error" hidden></p>
      <div class="dialog-actions">
        <button type="button" class="admin-btn" data-close>Anuluj</button>
        <button type="submit" class="admin-btn danger">Usuń</button>
      </div>
    </form>
  </dialog>

<?php
    $fileLimit = $admin ? uploadLimit() : min(USER_FILE_MAX_BYTES, uploadLimit() ?: USER_FILE_MAX_BYTES);
    // an account in its own folder moves nothing anywhere
    $moveTo = $admin ? allFolders($base) : [];
    // the paths go as links show them; the folders of the accounts get the name the gallery shows for the labels
    $labels = [];
    foreach (array_merge($moveTo, [$dirRel]) as $rel)
        if (publicRel($rel) !== $rel)
            $labels[publicRel($rel)] = substr(displayPath($rel), 2);
?>
  <script type="application/json" id="gallery-data"><?=json_encode([
      // no single folder to upload to in the search results
      'dir' => $searching ? null : publicRel($dirRel),
      'csrf' => siteCsrf(),
      'folders' => array_map('publicRel', $moveTo),
      'labels' => (object)$labels,
      'uploadLimit' => $fileLimit,
      'uploadLimitLabel' => formatSize($fileLimit),
      'types' => $uploadTypes,
      'shareUrl' => SITE_URL . siteRoot() . 'i/?s='
  ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)?></script>
<?php endif; ?>

  <script src="../js/sanakan-util.js?v=f417e538a8"></script>
  <script src="../js/explorer.js?v=6728775ca3"></script>
  <script src="../js/account.js?v=51972de250"></script>
  <script src="../js/netsphere.js?v=1c8be049a6"></script>
<?php if ($manage && !$showTrash): ?>
  <script src="../js/explorer-admin.js?v=42b6f19501"></script>
<?php endif; ?>
<?php if ($showTrash): ?>
  <script src="../js/gallery-trash.js?v=3f8e29c453"></script>
<?php endif; ?>
</body>

</html>
