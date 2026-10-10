<?php
    // API documentation of the bot (Swagger UI), behind the Discord login of the
    // gallery and the panel (inc/auth.php): accounts in API_VIEWERS
    // (inc/config.php) or added in the panel, and the panel admins. Others see
    // the login, or can ask for access. read_swagger.php checks the same.
    require __DIR__ . '/../inc/gallery.php';
    require __DIR__ . '/../inc/meta.php';

    // a plain form: asking for access, then back here
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!authConfigured() || !siteUser() || !checkCsrf()) {
            setFlash('Sesja wygasła, spróbuj jeszcze raz.');
        } else if (($_POST['action'] ?? '') === 'request-access') {
            handleAccessRequest('api', apiCanView(), './');
        }
        header('Location: ./', true, 303);
        exit;
    }

    handleLoginRequest(siteRoot() . 'api/');

    $user = siteUser();
    $allowed = apiCanView();
    $flash = takeFlash();
    // the login page itself is a 200, as Discord shows no link preview for a 401
    if (!$allowed)
        http_response_code(!authConfigured() ? 503 : ($user ? 403 : 200));
    $request = $user && !$allowed ? pendingRequest('api', $user['id']) : null;
    $csrf = $user ? siteCsrf() : '';
    $description = 'Dokumentacja API bota Sanakan: endpointy, parametry i odpowiedzi.';

    if (!$allowed):
?>
<!DOCTYPE html>
<html lang="pl"<?=hudHtmlAttributes($user)?>>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
<?=metaTags('API', $description, '/api/', 'api')?>
  <title>API &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../apple-touch-icon.png" />
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=a52b240f16" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=ed18f56732" type="text/css" rel="stylesheet" />
</head>

<body>
  <main class="content">
    <header class="ex-header">
      <div class="ex-top">
        <a class="back hud-corners" href="../" title="Strona główna">&larr; Sanakan</a>
<?php if ($user): ?>
        <?=accountMenuHtml($user, siteRoles(), $_SERVER['REQUEST_URI'] ?? '')?>
<?php endif; ?>
      </div>
      <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
      <h1 class="hud-title">API</h1>
    </header>

    <section class="locked hud-corners">
      <svg class="lock-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="1.5" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
<?php if (!authConfigured()): ?>
      <h2>Dokumentacja jest wyłączona</h2>
      <p>Logowanie nie jest jeszcze skonfigurowane na serwerze.</p>
<?php elseif ($user): ?>
      <h2>Brak dostępu</h2>
      <p>Konto <?=e($user['name'])?> nie ma dostępu do dokumentacji API. Wyloguj się, jeśli chcesz użyć innego konta.</p>
<?php if ($request): ?>
      <p class="request-sent">Prośba o dostęp wysłana <?=e(date('j.m H:i', $request['time']))?>. Administrator zobaczy ją w panelu.</p>
<?php else: ?>
      <form class="request-form" method="post" action="./">
        <input type="hidden" name="csrf" value="<?=e($csrf)?>" />
        <input type="hidden" name="action" value="request-access" />
        <input type="text" name="note" maxlength="<?=REQUEST_NOTE_LENGTH?>" placeholder="Do czego? (opcjonalnie)" aria-label="Notatka do prośby" />
        <button type="submit" class="admin-btn primary">Poproś o dostęp</button>
      </form>
<?php endif; ?>
<?php else: ?>
      <h2>Dokumentacja wymaga logowania</h2>
      <p>Zaloguj się kontem Discord, żeby zobaczyć dokumentację API.</p>
      <a class="admin-btn primary" href="?login">Zaloguj przez Discord</a>
<?php endif; ?>
    </section>
  </main>
  <footer class="site-foot"><span>&copy; 2017&ndash;<?=date('Y')?> Sniku</span><i aria-hidden="true">&middot;</i><a href="../state/">Status</a><i aria-hidden="true">&middot;</i><a href="../privacy/">Prywatność</a></footer>

<?php if ($flash): ?>
  <div class="toast" id="toast" role="status"><?=e($flash)?></div>
<?php else: ?>
  <div class="toast" id="toast" role="status" hidden></div>
<?php endif; ?>
  <script src="../js/sanakan-util.js?v=f417e538a8"></script>
  <script src="../js/explorer.js?v=6728775ca3"></script>
  <script src="../js/account.js?v=c8dfe2b1f3"></script>
  <script src="../js/netsphere.js?v=1c8be049a6"></script>
</body>

</html>
<?php
        exit;
    endif;
?>
<!-- HTML for static distribution bundle build -->
<!DOCTYPE html>
<html lang="pl"<?=hudHtmlAttributes($user)?>>
<head>
  <meta charset="UTF-8">
<?=metaTags('API', $description, '/api/', 'api')?>
  <title>API &middot; Sanakan</title>
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link rel="stylesheet" type="text/css" href="./swagger-ui.css?v=c6b71aa0c3" >
  <link rel="stylesheet" type="text/css" href="../css/style.css?v=a52b240f16" />
  <link rel="stylesheet" type="text/css" href="./theme.css?v=7c513a4fe9" >
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../favicon.svg" type="image/svg+xml" />
  <link rel="apple-touch-icon" href="../apple-touch-icon.png" />
  <style>
    html
    {
      box-sizing: border-box;
      overflow: -moz-scrollbars-vertical;
      overflow-y: scroll;
    }
    *,
    *:before,
    *:after
    {
      box-sizing: inherit;
    }

    body {
      margin:0;
    }
  </style>
</head>

<body>

<header class="api-header">
  <div class="api-top">
    <a class="back hud-corners" href="../" title="Strona główna">&larr; Sanakan</a>
    <?=accountMenuHtml($user, siteRoles(), $_SERVER['REQUEST_URI'] ?? '')?>
  </div>
  <div class="tag" aria-hidden="true">SAFEGUARD &middot; LV.9<span class="cursor">_</span></div>
  <h1 class="hud-title">API<span class="api-version" id="api-version" hidden></span></h1>
</header>

<div class="api-toolbar" id="api-toolbar" hidden>
  <div class="api-toolbar-inner">
    <label class="search hud-corners">
      <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path d="M15.5 15.5 21 21" /></svg>
      <input id="api-search" type="search" autocomplete="off" spellcheck="false" placeholder="Szukaj endpointu: metoda, ścieżka lub opis" aria-label="Szukaj endpointu" />
      <kbd aria-hidden="true" title="Naciśnij /, żeby szukać">/</kbd>
    </label>
    <div class="search-info" id="api-search-info" aria-live="polite"></div>
  </div>
</div>

<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" style="position:absolute;width:0;height:0">
  <defs>
    <symbol viewBox="0 0 20 20" id="unlocked">
          <path d="M15.8 8H14V5.6C14 2.703 12.665 1 10 1 7.334 1 6 2.703 6 5.6V6h2v-.801C8 3.754 8.797 3 10 3c1.203 0 2 .754 2 2.199V8H4c-.553 0-1 .646-1 1.199V17c0 .549.428 1.139.951 1.307l1.197.387C5.672 18.861 6.55 19 7.1 19h5.8c.549 0 1.428-.139 1.951-.307l1.196-.387c.524-.167.953-.757.953-1.306V9.199C17 8.646 16.352 8 15.8 8z"></path>
    </symbol>

    <symbol viewBox="0 0 20 20" id="locked">
      <path d="M15.8 8H14V5.6C14 2.703 12.665 1 10 1 7.334 1 6 2.703 6 5.6V8H4c-.553 0-1 .646-1 1.199V17c0 .549.428 1.139.951 1.307l1.197.387C5.672 18.861 6.55 19 7.1 19h5.8c.549 0 1.428-.139 1.951-.307l1.196-.387c.524-.167.953-.757.953-1.306V9.199C17 8.646 16.352 8 15.8 8zM12 8H8V5.199C8 3.754 8.797 3 10 3c1.203 0 2 .754 2 2.199V8z"/>
    </symbol>

    <symbol viewBox="0 0 20 20" id="close">
      <path d="M14.348 14.849c-.469.469-1.229.469-1.697 0L10 11.819l-2.651 3.029c-.469.469-1.229.469-1.697 0-.469-.469-.469-1.229 0-1.697l2.758-3.15-2.759-3.152c-.469-.469-.469-1.228 0-1.697.469-.469 1.228-.469 1.697 0L10 8.183l2.651-3.031c.469-.469 1.228-.469 1.697 0 .469.469.469 1.229 0 1.697l-2.758 3.152 2.758 3.15c.469.469.469 1.229 0 1.698z"/>
    </symbol>

    <symbol viewBox="0 0 20 20" id="large-arrow">
      <path d="M13.25 10L6.109 2.58c-.268-.27-.268-.707 0-.979.268-.27.701-.27.969 0l7.83 7.908c.268.271.268.709 0 .979l-7.83 7.908c-.268.271-.701.27-.969 0-.268-.269-.268-.707 0-.979L13.25 10z"/>
    </symbol>

    <symbol viewBox="0 0 20 20" id="large-arrow-down">
      <path d="M17.418 6.109c.272-.268.709-.268.979 0s.271.701 0 .969l-7.908 7.83c-.27.268-.707.268-.979 0l-7.908-7.83c-.27-.268-.27-.701 0-.969.271-.268.709-.268.979 0L10 13.25l7.418-7.141z"/>
    </symbol>


    <symbol viewBox="0 0 24 24" id="jump-to">
      <path d="M19 7v4H5.83l3.58-3.59L8 6l-6 6 6 6 1.41-1.41L5.83 13H21V7z"/>
    </symbol>

    <symbol viewBox="0 0 24 24" id="expand">
      <path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/>
    </symbol>

  </defs>
</svg>

<div id="swagger-ui"></div>

<p class="api-no-results" id="api-no-results" hidden>Brak pasujących endpointów.</p>
<?php if ($flash): ?>
<p class="api-flash" role="status"><?=e($flash)?></p>
<?php endif; ?>

<footer class="site-foot"><span>&copy; 2017&ndash;<?=date('Y')?> Sniku</span><i aria-hidden="true">&middot;</i><a href="../state/">Status</a><i aria-hidden="true">&middot;</i><a href="../privacy/">Prywatność</a></footer>

<script src="./swagger-ui-bundle.js?v=9910755d8a"> </script>
<script src="./swagger-ui-standalone-preset.js?v=0ba407ddfc"> </script>
<script src="../js/sanakan-util.js?v=f417e538a8"></script>
<script src="./search.js?v=52c2c6eb3f"> </script>
<script src="../js/account.js?v=c8dfe2b1f3"></script>
<script src="./init.js?v=9e46400eb6"></script>
<script src="../js/netsphere.js?v=1c8be049a6"></script>
</body>
<style> .swagger-ui .scheme-container, .swagger-ui .topbar { display: none !important; } </style>
<style> .swagger-ui.swagger-container .wrapper span a img { display: none !important; } </style>
<style> .swagger-ui .information-container .info .url { display: none !important; } </style>
<style> .try-out { display: none !important; } </style>
</html>
