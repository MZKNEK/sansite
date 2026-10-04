<?php
    // Gallery of this folder: browses the subfolders and shows the pictures as
    // a grid of thumbnails with a viewer. It needs a Discord login: accounts in
    // GALLERY_ADMINS (inc/config.php) can also add, move and delete files, the
    // ones in GALLERY_VIEWERS can look. Only this file is in git; the pictures
    // live on the server (see .gitignore). The logic is in inc/gallery.php.
    require __DIR__ . '/../inc/gallery.php';
    require __DIR__ . '/../inc/meta.php';

    $base = str_replace('\\', '/', __DIR__);

    if ($_SERVER['REQUEST_METHOD'] === 'POST')
        handlePost($base);

    // ?zip&p=folder: the whole folder as one ZIP
    if (isset($_GET['zip'])) {
        $zipDir = resolvePath($base, $_GET['p'] ?? '', true);
        if (!galleryCanView() || !$zipDir) {
            http_response_code(galleryCanView() ? 404 : 403);
            exit;
        }
        sendZip(galleryZipRoots([$zipDir]), ($zipDir[1] === '' ? 'galeria' : basename($zipDir[1])) . '.zip', folderUrl($zipDir[1]));
    }

    if (isset($_GET['thumb'])) {
        if (galleryCanView())
            sendThumb($base, $_GET['thumb']);
        else
            http_response_code(403);
        exit;
    }

    // Discord login (inc/auth.php); after it the visitor comes back to this folder
    $from = resolvePath($base, $_GET['p'] ?? '', true);
    handleLoginRequest(siteRoot() . 'i/' . ($from && $from[1] !== '' ? folderUrl($from[1]) : ''));

    // without access the page only offers the login; the folder is not even read
    $user = siteUser();
    $locked = !galleryCanView();
    // an account without access can ask for it; one request at a time
    $request = $user && $locked ? pendingRequest('gallery', $user['id']) : null;
    $admin = !$locked && galleryIsAdmin();
    $flash = takeFlash();
    $requested = trim((string)($_GET['p'] ?? ''), '/');
    $loginUrl = '?login' . ($requested === '' ? '' : '&p=' . rawurlencode($requested));
    if ($locked)
        http_response_code(!authConfigured() ? 503 : ($user ? 403 : 401));

    $dir = $locked ? [$base, ''] : resolvePath($base, $requested, true);
    $notFound = $dir === null;
    if ($notFound) {
        http_response_code(404);
        $dir = [$base, ''];
    }
    list($dirPath, $dirRel) = $dir;

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
    $totalBytes = array_sum(array_column($files, 'size'));

    $crumbs = [];
    $path = '';
    foreach ($dirRel === '' ? [] : explode('/', $dirRel) as $part) {
        $path = ltrim($path . '/' . $part, '/');
        $crumbs[] = ['name' => $part, 'rel' => $path];
    }

    // the first tile: one folder up, or back from the search results; null in the top folder
    $parent = null;
    if ($searching) {
        $parent = ['url' => folderUrl($dirRel), 'label' => 'Wróć do: ' . galleryPath($dirRel)];
    } else if ($dirRel !== '') {
        $parentRel = strpos($dirRel, '/') === false ? '' : substr($dirRel, 0, strrpos($dirRel, '/'));
        $parent = ['url' => folderUrl($parentRel), 'label' => 'Wyżej: ' . galleryPath($parentRel)];
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

?>
<!DOCTYPE html>
<html lang="pl">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="author" content="Sniku" />
<?=metaTags('Galeria · Sanakan', 'Galeria obrazków bota Sanakan, dostęp po zalogowaniu przez Discord.', '/i/')?>
  <title><?=e($searching ? 'Szukaj: ' . $query : ($dirRel === '' ? 'Galeria' : 'i/' . $dirRel))?> &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../apple-touch-icon.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Share+Tech+Mono&family=JetBrains+Mono:wght@400;700&display=swap" />
  <link href="../css/style.css?v=20" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=8" type="text/css" rel="stylesheet" />
</head>

<body class="explorer-page">
  <main class="content">
    <header class="ex-header">
      <div class="ex-top">
        <a class="back hud-corners" href="../" title="Strona główna">&larr; Sanakan</a>
<?php if ($user): ?>
        <div class="account">
          <img src="<?=e($user['avatar'])?>" alt="" width="28" height="28" />
          <span class="account-name"><?=e($user['name'])?></span>
          <button type="button" class="account-btn" id="act-logout" data-csrf="<?=e(siteCsrf())?>">Wyloguj</button>
        </div>
<?php endif; ?>
      </div>
      <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
      <h1 class="hud-title">Galeria</h1>
<?php if (!$locked): ?>
      <nav class="crumbs" aria-label="Ścieżka">
        <a href="./">i</a>
<?php foreach ($crumbs AS $crumb): ?>
        <span aria-hidden="true">/</span>
        <a href="<?=e(folderUrl($crumb['rel']))?>"><?=e($crumb['name'])?></a>
<?php endforeach; ?>
      </nav>
      <p class="ex-meta"><?=e(implode(' · ', $summary))?></p>
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

<?php if ($notFound): ?>
    <p class="notice">Nie ma takiego folderu, poniżej jest główny folder galerii.</p>
<?php endif; ?>

<?php if ($folders || $files || $admin): ?>
    <div class="toolbar" id="toolbar">
      <label class="search hud-corners">
        <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path d="M15.5 15.5 21 21" /></svg>
        <input id="ex-search" type="search" autocomplete="off" spellcheck="false" placeholder="Szukaj po nazwie, Enter: w całej galerii" aria-label="Szukaj po nazwie"
               value="<?=e($query)?>" data-dir="<?=e($dirRel)?>" data-searching="<?=$searching ? '1' : '0'?>" />
        <kbd aria-hidden="true" title="Naciśnij /, żeby szukać">/</kbd>
      </label>
      <div class="sort" role="group" aria-label="Sortowanie">
        <button type="button" data-sort="name" aria-pressed="true">Nazwa</button>
        <button type="button" data-sort="date" aria-pressed="false">Data</button>
        <button type="button" data-sort="size" aria-pressed="false">Rozmiar</button>
      </div>
<?php if (!$searching && ($folders || $files) && canZip()): ?>
      <a class="admin-btn zip-btn" href="?zip&amp;p=<?=e(rawurlencode($dirRel))?>" title="Cały folder <?=e(galleryPath($dirRel))?> razem z podfolderami, do <?=e(formatSize(ZIP_MAX_BYTES))?>">Pobierz folder (ZIP)</a>
<?php endif; ?>
<?php if ($admin): ?>
      <div class="admin-bar" id="admin-bar">
<?php if (!$searching): ?>
        <button type="button" class="admin-btn primary" id="act-upload">+ Dodaj pliki</button>
        <input type="file" id="upload-input" multiple accept="<?=e('.' . implode(',.', array_merge(IMAGE_TYPES, VIDEO_TYPES)))?>" hidden />
<?php if (canConvertToWebp()): ?>
        <label class="admin-check" title="PNG, JPG i GIF zapisują się jako WebP, GIF-y jako animowane (gdy serwer ma gif2webp). Gdy WebP nie wyjdzie mniejszy, zostaje oryginał. Filmy zostają bez zmian.">
          <input type="checkbox" id="upload-webp" /> Zamieniaj na WebP
        </label>
<?php endif; ?>
        <button type="button" class="admin-btn" id="act-mkdir">Nowy folder</button>
<?php endif; ?>
        <button type="button" class="admin-btn" id="act-select" aria-pressed="false">Zaznacz</button>
        <span class="admin-selection" id="admin-selection" hidden>
          <span class="admin-count" id="admin-count"></span>
          <button type="button" class="admin-btn" id="act-select-all">Wszystkie</button>
          <button type="button" class="admin-btn" id="act-rename">Zmień nazwę</button>
          <button type="button" class="admin-btn" id="act-move">Przenieś</button>
          <button type="button" class="admin-btn" id="act-rotate-left" title="Obróć w lewo (PNG, JPG, WebP)">&#8634; Obróć</button>
          <button type="button" class="admin-btn" id="act-rotate-right" title="Obróć w prawo (PNG, JPG, WebP)">Obróć &#8635;</button>
<?php if (canZip()): ?>
          <button type="button" class="admin-btn" id="act-zip">Pobierz ZIP</button>
<?php endif; ?>
          <button type="button" class="admin-btn danger" id="act-delete">Usuń</button>
        </span>
<?php if (!$searching): ?>
        <span class="admin-hint">Możesz też przeciągnąć pliki na stronę albo wkleić obrazek ze schowka (Ctrl+V). Metadane zdjęć (np. miejsce zrobienia) są usuwane. Limit: <?=e(formatSize(uploadLimit()))?> na plik.</span>
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
      <a class="tile folder hud-corners" href="<?=e(folderUrl($folder['rel']))?>" data-rel="<?=e($folder['rel'])?>" data-name="<?=e(lower($folder['name']))?>" data-date="<?=$folder['mtime']?>" data-size="-1">
        <span class="thumb">
<?php if ($admin): ?>
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
          <span class="where" title="<?=e(galleryPath(dirname($folder['rel']) === '.' ? '' : dirname($folder['rel'])))?>"><?=e(galleryPath(dirname($folder['rel']) === '.' ? '' : dirname($folder['rel'])))?></span>
<?php endif; ?>
        </span>
      </a>
<?php endforeach; ?>
<?php foreach ($files AS $file): ?>
      <a class="tile file hud-corners" href="<?=e(fileUrl($file['rel']))?>" target="_blank" rel="noopener" data-rel="<?=e($file['rel'])?>"
         data-name="<?=e(lower($file['name']))?>" data-date="<?=$file['mtime']?>" data-size="<?=$file['size']?>"
         data-kind="<?=$file['kind']?>" data-title="<?=e($file['name'])?>"
         data-details="<?=e(implode(' · ', array_filter([$file['dims'], formatSize($file['size']), date('d.m.Y', $file['mtime'])])))?>">
        <span class="thumb">
<?php if ($admin): ?>
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
        </span>
        <span class="label">
          <span class="name" title="<?=e($file['name'])?>"><?=e($file['name'])?></span>
          <span class="info"><?=e(implode(' · ', array_filter([$file['dims'], formatSize($file['size'])])))?></span>
<?php if ($searching): ?>
          <span class="where" title="<?=e(galleryPath(dirname($file['rel']) === '.' ? '' : dirname($file['rel'])))?>"><?=e(galleryPath(dirname($file['rel']) === '.' ? '' : dirname($file['rel'])))?></span>
<?php endif; ?>
        </span>
      </a>
<?php endforeach; ?>
    </div>

<?php if (!$folders && !$files): ?>
    <p class="empty"><?=$searching ? 'Nic nie znaleziono w całej galerii.' : 'Ten folder jest pusty.' . ($admin ? ' Przeciągnij tu pliki, wklej obrazek (Ctrl+V) albo użyj „Dodaj pliki”.' : '')?></p>
<?php endif; ?>
    <p class="empty" id="ex-no-results" hidden>Brak pasujących plików.</p>
<?php endif; ?>
  </main>

<?php if (!$locked): ?>
  <div class="viewer" id="viewer" hidden role="dialog" aria-modal="true" aria-label="Podgląd">
    <div class="viewer-bar">
      <div class="viewer-title">
        <span class="viewer-name" id="viewer-name"></span>
        <span class="viewer-details" id="viewer-details"></span>
      </div>
      <div class="viewer-actions">
        <button type="button" class="viewer-btn" id="viewer-copy">Kopiuj link</button>
        <a class="viewer-btn" id="viewer-open" href="#" target="_blank" rel="noopener">Otwórz</a>
        <button type="button" class="viewer-btn viewer-close" id="viewer-close" aria-label="Zamknij">&times;</button>
      </div>
    </div>
    <div class="viewer-stage" id="viewer-stage">
      <button type="button" class="viewer-nav prev hud-corners" id="viewer-prev" aria-label="Poprzedni">&larr;</button>
      <img class="viewer-img" id="viewer-img" alt="" />
      <video class="viewer-img" id="viewer-video" controls loop playsinline hidden></video>
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

<?php if ($admin): ?>
  <div class="drop" id="drop" hidden>
    <div class="drop-box hud-corners">Upuść pliki, żeby dodać je do <?=e($dirRel === '' ? 'i' : 'i/' . $dirRel)?></div>
  </div>

  <div class="progress" id="progress" hidden>
    <span class="progress-text" id="progress-text"></span>
    <span class="progress-bar"><span id="progress-fill"></span></span>
  </div>

  <dialog class="ex-dialog" id="dlg-mkdir">
    <form id="form-mkdir">
      <h2>Nowy folder</h2>
      <p>W folderze <?=e($dirRel === '' ? 'i' : 'i/' . $dirRel)?></p>
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

  <dialog class="ex-dialog" id="dlg-delete">
    <form id="form-delete">
      <h2>Usuń</h2>
      <p id="delete-what"></p>
      <p class="dialog-warning">Trafią do kosza na <?=TRASH_DAYS?> dni; przywrócić je można w panelu administratora.</p>
      <p class="dialog-error" hidden></p>
      <div class="dialog-actions">
        <button type="button" class="admin-btn" data-close>Anuluj</button>
        <button type="submit" class="admin-btn danger">Usuń</button>
      </div>
    </form>
  </dialog>

  <script type="application/json" id="gallery-data"><?=json_encode([
      // no single folder to upload to in the search results
      'dir' => $searching ? null : $dirRel,
      'csrf' => siteCsrf(),
      'folders' => allFolders($base),
      'uploadLimit' => uploadLimit(),
      'uploadLimitLabel' => formatSize(uploadLimit()),
      'types' => array_merge(IMAGE_TYPES, VIDEO_TYPES)
  ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)?></script>
<?php endif; ?>

  <script src="../js/explorer.js?v=7"></script>
<?php if ($admin): ?>
  <script src="../js/explorer-admin.js?v=8"></script>
<?php endif; ?>
</body>

</html>
