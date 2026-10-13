<?php
    // Admin panel: who may use the gallery and the API, the bot status, the
    // roles the bot reports for the recent logins, and server tools. The home page links here for the accounts that may open it. The
    // Discord login is the gallery's (inc/auth.php, one session for all); only
    // the accounts in PANEL_ADMINS (inc/config.php) get in.
    require __DIR__ . '/../inc/bot.php';
    installDataErrorHandler();
    require __DIR__ . '/../inc/services.php';
    require __DIR__ . '/../inc/gallery.php';
    require __DIR__ . '/../inc/maintenance.php';
    require __DIR__ . '/../inc/status-card.php';
    require_once __DIR__ . '/../inc/system.php';
    require __DIR__ . '/../inc/diag.php';
    require __DIR__ . '/../inc/panel-stats.php';
    require __DIR__ . '/../inc/cloudflare.php';
    require __DIR__ . '/../inc/autoblock.php';
    require __DIR__ . '/../inc/meta.php';
    require __DIR__ . '/../inc/pw.php';
    require __DIR__ . '/../inc/admin/format.php';
    require __DIR__ . '/../inc/admin/charts.php';
    require __DIR__ . '/../inc/admin/actions.php';
    require __DIR__ . '/../inc/admin/view-data.php';

    $galleryDir = str_replace('\\', '/', dirname(__DIR__)) . '/i';
    $thumbsDir = thumbsDir();

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
        if (!isPanelAdminId($user['id']))
            reply(false, 'To konto nie ma dostępu do panelu.', 403);

        $logins = readData('logins');
        $list = (string)($_POST['list'] ?? '');
        $id = trim((string)($_POST['id'] ?? ''));

        panelAction($action, $user, $logins, $list, $id, $galleryDir, $thumbsDir);
    }

    // ---- Page -----------------------------------------------------------------

    handleLoginRequest(siteRoot() . 'admin/');

    $user = siteUser();
    $allowed = $user !== null && isPanelAdminId($user['id']);

    // ?kosz=ID shows what is in the trash: the file itself, &plik=... a file
    // of a folder, &pliki the pictures and films of a folder
    if (isset($_GET['kosz'])) {
        if (!$allowed) {
            http_response_code(403);
            exit;
        }
        if (isset($_GET['pliki'])) {
            list($files, $count) = trashFolderMedia((string)$_GET['kosz'], TRASH_PREVIEW_MAX);
            reply(true, '', 200, ['files' => array_map(function ($file) {
                return ['path' => $file[0], 'size' => formatSize($file[1]), 'video' => isVideo($file[0])];
            }, $files), 'count' => $count]);
        }

        $file = trashFile((string)$_GET['kosz'], (string)($_GET['plik'] ?? ''));
        if ($file)
            sendMedia($file[0], 'private, max-age=3600');
        else
            http_response_code(404);
        exit;
    }

    // ?cloudflare: the changes of the Cloudflare list sent from the panel, each
    // waiting one asked about once (js/admin.js asks again while one waits);
    // the session is let go first, so the panel's other requests do not wait
    if (isset($_GET['cloudflare'])) {
        if (!$allowed)
            reply(false, 'To konto nie ma dostępu do panelu.', 403);
        session_write_close();
        $ops = cloudflareConfigured() ? cloudflareCheckPending() : [];
        reply(true, '', 200, ['ops' => array_map(function ($op) {
            return ['id' => $op['id'], 'status' => $op['status'], 'text' => cloudflareOpStatus($op), 'message' => $op['message']];
        }, $ops)]);
    }

    // without a login: the site's 401 page, whose login comes back to this
    // very address (the panel's link is never shared, so a 401 costs no
    // preview on Discord, unlike the gallery's or the API's)
    if (!$allowed && authConfigured() && $user === null) {
        sendErrorPage(401);
        exit;
    }

    $flash = takeFlash();
    if (!$allowed)
        http_response_code(!authConfigured() ? 503 : 403);

    // ?konto=ID shows the profile of an account instead (inc/panel-account.php),
    // ?szukaj=... what the search found (inc/panel-search.php)
    $profileId = isset($_GET['konto']) && preg_match('/^\d{17,20}$/', (string)$_GET['konto']) ? (string)$_GET['konto'] : null;
    $searchQuery = $profileId === null && isset($_GET['szukaj']) && trim((string)$_GET['szukaj']) !== '' ? cutText(trim((string)$_GET['szukaj']), 100) : null;
    if ($allowed && $profileId !== null)
        require __DIR__ . '/../inc/panel-account.php';
    if ($allowed && $searchQuery !== null)
        require __DIR__ . '/../inc/panel-search.php';

    if ($allowed && $profileId === null && $searchQuery === null)
        extract(panelOverviewData($galleryDir));

    $csrf = $user ? siteCsrf() : '';
    $root = siteRoot();
?>
<!DOCTYPE html>
<html lang="pl"<?=hudHtmlAttributes($user)?>>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex" />
<?=metaTags('Panel', 'Panel administracyjny strony Sanakan, tylko dla administracji.', '/admin/', 'admin')?>
  <title><?=$allowed && $profileId !== null ? e($profile['name']) . ' &middot; Konto &middot; Panel' : ($allowed && $searchQuery !== null ? 'Szukaj &middot; Panel' : 'Panel')?> &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../apple-touch-icon.png" />
  <link rel="manifest" href="../site.webmanifest" />
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=7eb53c9ad4" type="text/css" rel="stylesheet" />
  <link href="../css/palette.css?v=ff07306679" type="text/css" rel="stylesheet" />
  <link href="../css/account.css?v=1f284a3102" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=4e82c4b35a" type="text/css" rel="stylesheet" />
  <link href="../css/status.css?v=b20c9359a9" type="text/css" rel="stylesheet" />
  <link href="../css/admin.css?v=45903195cd" type="text/css" rel="stylesheet" />
  <script src="../js/hud.js?v=2894733f39"></script>
</head>

<body class="admin-page" data-csrf="<?=e($csrf)?>">
  <main class="content">
    <header class="ex-header">
      <div class="ex-top">
<?php if ($allowed && ($profileId !== null || $searchQuery !== null)): ?>
        <a class="back hud-corners" href="./" title="Panel">&larr; Panel</a>
<?php else: ?>
        <a class="back hud-corners" href="../" title="Strona główna">&larr; Sanakan</a>
<?php endif; ?>
<?php if ($user): ?>
        <?=accountMenuHtml($user, siteRoles(), $_SERVER['REQUEST_URI'] ?? '')?>
<?php endif; ?>
      </div>
      <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
      <h1 class="hud-title"><?=$allowed && $profileId !== null ? 'Konto' : ($allowed && $searchQuery !== null ? 'Szukaj' : 'Panel')?></h1>
<?php if ($allowed): ?>
      <form class="panel-search" method="get" action="./" role="search">
        <label class="search hud-corners">
          <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path d="M15.5 15.5 21 21" /></svg>
          <input type="search" name="szukaj" value="<?=e($searchQuery ?? '')?>" autocomplete="off" spellcheck="false" placeholder="Konto, ID albo adres IP" aria-label="Szukaj konta albo adresu IP" />
          <kbd aria-hidden="true" title="Enter szuka">Enter</kbd>
        </label>
      </form>
<?php endif; ?>
    </header>

<?php if ($allowed && cloudflareConfigured()): ?>
<?=cloudflarePendingCard(cloudflarePending())?>
<?php endif; ?>
<?php if (!$allowed): ?>
    <section class="locked hud-corners">
      <svg class="lock-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="1.5" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
<?php if (!authConfigured()): ?>
      <h2>Panel jest wyłączony</h2>
      <p>Logowanie nie jest jeszcze skonfigurowane na serwerze.</p>
<?php else: ?>
      <h2>Brak dostępu</h2>
      <p>Konto <?=e($user['name'])?> nie ma dostępu do panelu. Wyloguj się, jeśli chcesz użyć innego konta.</p>
<?php endif; ?>
    </section>
<?php elseif ($profileId !== null): ?>
<?php profilePage($profile); ?>
<?php elseif ($searchQuery !== null): ?>
<?php searchPage($search); ?>
<?php else: ?>
    <div class="admin-layout">
      <nav class="admin-nav" id="admin-nav" aria-label="Sekcje panelu"></nav>
      <div class="panel-grid">

<?php require __DIR__ . '/../inc/admin/views/alerts.php'; ?>

<?php require __DIR__ . '/../inc/admin/views/overview.php'; ?>

<?php require __DIR__ . '/../inc/admin/views/access.php'; ?>

<?php require __DIR__ . '/../inc/admin/views/gallery.php'; ?>

<?php require __DIR__ . '/../inc/admin/views/server.php'; ?>

      </div>
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
  <script src="../js/account.js?v=ff288d9afb"></script>
  <script src="../js/netsphere.js?v=3716e0908c"></script>
  <script src="../js/safeguard.js?v=66d6049453"></script>
  <script src="../js/palette.js?v=15434ca4d0"></script>
<?php if ($allowed): ?>
  <script src="../js/admin.js?v=7a08a49a3c"></script>
<?php endif; ?>
</body>

</html>
