<?php
    // The middles of the figures of the pack of 0x40 (the centres of
    // respack.json, inc/hues-centres.php): the pictures drawn as hues.js
    // draws them with its centring on, still, on a stage of a chosen shape,
    // with a slider for the middle and a button that keeps it (centres.js).
    // On the site it is only for an owner (PANEL_ADMINS), who reaches it from
    // the settings of 0x40, and what is kept goes to inc/data. Run locally, it
    // starts PHP's own server on 127.0.0.1 with the site's folder as its root
    // and itself as the router, opens the browser and writes respack.json:
    //   php hues/centres.php [port]
    // Only that server is told so (SANAKAN_CENTRES_LOCAL); any other, as
    // tools/preview.sh's, gets the site's page with its owner check.
    if (PHP_SAPI === 'cli') {
        $port = (int)($argv[1] ?? 8740);
        $url = "http://127.0.0.1:$port/hues/centres.php";
        $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', dirname(__DIR__), __FILE__], [STDIN, STDOUT, STDERR], $pipes,
            null, getenv() + ['SANAKAN_CENTRES_LOCAL' => '1']);
        if (!is_resource($server))
            exit(1);
        usleep(400000);
        echo "Środki obrazów: $url (Ctrl+C kończy)\n";
        if (PHP_OS_FAMILY === 'Windows')
            pclose(popen('start "" ' . escapeshellarg($url), 'r'));
        elseif (PHP_OS_FAMILY === 'Darwin')
            exec('open ' . escapeshellarg($url));
        else
            exec('xdg-open ' . escapeshellarg($url) . ' >/dev/null 2>&1 &');
        exit(proc_close($server));
    }

    $local = PHP_SAPI === 'cli-server' && getenv('SANAKAN_CENTRES_LOCAL') === '1';
    if ($local) {
        // the router of the local server: the site's own files (its styles,
        // fonts, scripts) as they are, none in a dot folder, this page, and
        // nothing else
        $path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
        if ($path !== '/hues/centres.php') {
            if (!preg_match('~(^|/)\.~', $path) && preg_match('~\.(?:css|js|woff2|svg|ico|png)$~', $path)
                && is_file(dirname(__DIR__) . $path))
                return false;
            http_response_code(404);
            exit;
        }
        require __DIR__ . '/../inc/hues-centres.php';
        $csrf = '';
    } else {
        require __DIR__ . '/../inc/members.php';
        require __DIR__ . '/../inc/hues-centres.php';
        $owner = ownerAllowed();
        $csrf = $owner ? siteCsrf() : '';
        $saving = ($_GET['a'] ?? '') === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST';
        $csrfOk = $owner && (!$saving || checkCsrf());
        memberGate(!isset($_GET['a']), $owner);
        if (!$csrfOk) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            exit('{"error":"csrf"}');
        }
    }

    $file = __DIR__ . '/.pack/respack.json';
    $action = (string)($_GET['a'] ?? '');

    function centresPack($file, $local)
    {
        $pack = huesPack($file, !$local);
        if ($pack === null) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            exit('{"error":"respack.json"}');
        }
        return $pack;
    }

    // the pictures and their middles, kept ones only
    if ($action === 'pack') {
        $pack = centresPack($file, $local);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['images' => $pack['images'], 'centres' => (object)$pack['centres']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'image') {
        $name = (string)($_GET['n'] ?? '');
        if (!in_array($name, centresPack($file, $local)['images'], true) || !is_file(__DIR__ . '/.pack/Images/' . $name . '.webp')) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: image/webp');
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile(__DIR__ . '/.pack/Images/' . $name . '.webp');
        exit;
    }

    // name and cx (0 to 1) keep a picture's middle; an empty cx gives it back
    // to the one hues.js finds
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $name = (string)($_POST['name'] ?? '');
        $cx = (string)($_POST['cx'] ?? '');
        if (!in_array($name, centresPack($file, $local)['images'], true)) {
            http_response_code(400);
            exit('{"error":"name"}');
        }
        if ($cx !== '' && !is_numeric($cx)) {
            http_response_code(400);
            exit('{"error":"cx"}');
        }
        $value = $cx === '' ? null : huesCentreValue($cx);
        if (!($local ? setPackHuesCentre($file, $name, $value) : setSiteHuesCentre($name, $value))) {
            http_response_code(500);
            exit('{"error":"write"}');
        }
        echo json_encode(['cx' => centresPack($file, $local)['centres'][$name] ?? null]);
        exit;
    }

    if ($action !== '') {
        http_response_code(404);
        exit;
    }
?>
<!DOCTYPE html>
<html lang="pl">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex" />
  <meta name="theme-color" content="#9b59b6" />
  <title>Środki obrazów &middot; 0x40 &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../img/favicon.svg" type="image/svg+xml" />
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=f2ef38f341" type="text/css" rel="stylesheet" />
  <link href="../css/skins.css?v=50e6f40617" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=2f5ac20877" type="text/css" rel="stylesheet" />
  <link href="centres.css?v=36c33841e7" type="text/css" rel="stylesheet" />
  <script src="../js/appearance.js?v=2e6e831727"></script>
</head>

<body class="centres" data-csrf="<?=htmlspecialchars($csrf, ENT_QUOTES)?>" data-local="<?=$local ? '1' : '0'?>">
  <div class="centres-bar">
    <a class="back hud-corners" href="./" title="0x40">&larr; 0x40</a>
    <div class="centres-group">
      <button type="button" class="player-btn hud-corners" id="prev" aria-label="Poprzedni obraz" title="Poprzedni (&larr;)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 5-7 7 7 7" /></svg></button>
      <span class="hud-select centres-pick">
        <select id="pick" aria-label="Obraz"></select>
        <svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg>
      </span>
      <button type="button" class="player-btn hud-corners" id="next" aria-label="Następny obraz" title="Następny (&rarr;)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7" /></svg></button>
      <span class="centres-note" id="count"></span>
    </div>
    <div class="centres-slider">
      <label class="centres-label" for="cx">Środek</label>
      <input class="player-range" id="cx" type="range" min="0" max="1" step="0.01" />
      <span class="centres-value" id="value"></span>
    </div>
    <div class="centres-group">
      <span class="centres-state" id="state"></span>
      <button type="button" class="hud-button hud-corners" id="save" title="<?=$local ? 'Zapisz do respack.json' : 'Zapisz na stronie'?> (Ctrl+S)">Zapisz</button>
      <button type="button" class="hud-button hud-corners" id="revert" title="Wróć do zapisanego">Cofnij</button>
      <button type="button" class="hud-button hud-corners" id="auto" title="Usuń ręczny środek, hues.js znajdzie go sam">Automatycznie</button>
    </div>
  </div>
  <main class="centres-main">
    <div class="centres-stage" id="stage-box"><canvas id="stage"></canvas></div>
    <aside class="centres-side">
      <div class="centres-group" id="shapes">
        <button type="button" class="hud-button hud-corners" data-shape="0.5625" aria-pressed="true">9:16</button>
        <button type="button" class="hud-button hud-corners" data-shape="0.75" aria-pressed="false">3:4</button>
        <button type="button" class="hud-button hud-corners" data-shape="1" aria-pressed="false">1:1</button>
        <button type="button" class="hud-button hud-corners" data-shape="0" aria-pressed="false">Okno</button>
      </div>
      <label class="centres-check"><input id="guide" type="checkbox" checked /> linia środka sceny</label>
      <canvas id="whole"></canvas>
      <p class="centres-note">Na całym obrazie: jasne okno to to, co widać na scenie, fioletowa linia to środek, szara przerywana to ten, który hues.js znajduje sam.</p>
      <p class="centres-note">&larr; &rarr; obrazy, <kbd>,</kbd> <kbd>.</kbd> środek o 0,01 (z Shift o 0,05), Ctrl+S zapisuje.<?=$local ? '' : ' Zapisane tu środki trzyma strona; lokalny respack.json ich nie dostaje.'?></p>
    </aside>
  </main>
  <script src="centres.js?v=690488a66f"></script>
</body>

</html>
