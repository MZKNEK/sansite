<?php
    // A maker of pictures for the pack of 0x40 (maker.js): any picture in,
    // its background taken away (from the edges, with a wand or a brush),
    // placed in a frame 720 high and made the 1-bit line art of the pack,
    // black and white with alpha either full or none, seen as hues.js colours
    // it. What it makes goes into .pack/Images/<name>.png, then respack.php
    // makes it WebP and lists it. Only run locally: it starts PHP's own
    // server on 127.0.0.1 with the site's folder as its root and itself as
    // the router, and opens the browser:
    //   php hues/maker.php [port]
    //   CWEBP=/path/to/cwebp php hues/maker.php
    // Any other server (the site's, tools/preview.sh's) gets a 404.
    if (PHP_SAPI === 'cli') {
        $port = (int)($argv[1] ?? 8741);
        $url = "http://127.0.0.1:$port/hues/maker.php";
        $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', dirname(__DIR__), __FILE__], [STDIN, STDOUT, STDERR], $pipes,
            null, getenv() + ['SANAKAN_MAKER_LOCAL' => '1']);
        if (!is_resource($server))
            exit(1);
        usleep(400000);
        echo "Kreator obrazów 0x40: $url (Ctrl+C kończy)\n";
        if (PHP_OS_FAMILY === 'Windows')
            pclose(popen('start "" ' . escapeshellarg($url), 'r'));
        elseif (PHP_OS_FAMILY === 'Darwin')
            exec('open ' . escapeshellarg($url));
        else
            exec('xdg-open ' . escapeshellarg($url) . ' >/dev/null 2>&1 &');
        exit(proc_close($server));
    }

    if (PHP_SAPI !== 'cli-server' || getenv('SANAKAN_MAKER_LOCAL') !== '1') {
        http_response_code(404);
        exit;
    }

    // the router of the local server: the site's own files (its styles,
    // fonts, scripts) as they are, none in a dot folder, this page, and
    // nothing else
    $path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if ($path !== '/hues/maker.php') {
        if (!preg_match('~(^|/)\.~', $path) && preg_match('~\.(?:css|js|woff2|svg|ico|png)$~', $path)
            && is_file(dirname(__DIR__) . $path))
            return false;
        http_response_code(404);
        exit;
    }

    $images = __DIR__ . '/.pack/Images';
    $action = (string)($_GET['a'] ?? '');

    function makerAnswer($status, $data)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // a name the pack can list and a file can carry on any system
    function makerName($name)
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string)$name));
        return preg_match('~^[^./\\\\:*?"<>|\x00-\x1f][^/\\\\:*?"<>|\x00-\x1f]{0,99}$~u', $name) && substr($name, -1) !== '.' ? $name : null;
    }

    // the names there are, to warn before one is overwritten
    if ($action === 'names') {
        $names = array_map(function ($file) { return pathinfo($file, PATHINFO_FILENAME); },
            array_merge(glob($images . '/*.webp') ?: [], glob($images . '/*.png') ?: []));
        makerAnswer(200, array_values(array_unique($names)));
    }

    // name, picture (a PNG 720 high, 1280 to 2560 wide) and overwrite (1 to
    // put it over one there is): the picture goes into Images/, then
    // respack.php runs, whose words come back
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = makerName($_POST['name'] ?? '');
        if ($name === null)
            makerAnswer(400, ['error' => 'name']);
        $upload = $_FILES['picture'] ?? null;
        if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name']))
            makerAnswer(400, ['error' => 'picture']);
        $size = @getimagesize($upload['tmp_name']);
        if (!$size || $size[2] !== IMAGETYPE_PNG || $size[1] !== 720 || $size[0] < 1280 || $size[0] > 2560)
            makerAnswer(400, ['error' => 'size']);
        $there = is_file("$images/$name.webp") || is_file("$images/$name.png");
        if ($there && ($_POST['overwrite'] ?? '') !== '1')
            makerAnswer(409, ['error' => 'exists']);
        if (!is_dir($images) && !mkdir($images, 0777, true))
            makerAnswer(500, ['error' => 'write']);
        if (!move_uploaded_file($upload['tmp_name'], "$images/$name.png.tmp") || !rename("$images/$name.png.tmp", "$images/$name.png"))
            makerAnswer(500, ['error' => 'write']);
        // the WebP of the same name goes, so respack.php makes it from the PNG
        if (is_file("$images/$name.webp"))
            unlink("$images/$name.webp");
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/respack.php') . ' 2>&1', $output, $code);
        makerAnswer(200, ['name' => $name, 'webp' => is_file("$images/$name.webp"), 'code' => $code, 'output' => implode("\n", $output)]);
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
  <title>Kreator obrazów &middot; 0x40 &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../img/favicon.svg" type="image/svg+xml" />
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=f2ef38f341" type="text/css" rel="stylesheet" />
  <link href="../css/skins.css?v=50e6f40617" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=2f5ac20877" type="text/css" rel="stylesheet" />
  <link href="maker.css?v=0579a7e25d" type="text/css" rel="stylesheet" />
  <script src="../js/appearance.js?v=2e6e831727"></script>
</head>

<body class="maker">
  <div class="maker-bar">
    <label class="hud-button hud-corners" title="Otwórz obraz (albo upuść go tu lub wklej Ctrl+V)">Otwórz<input type="file" id="open" accept="image/*" /></label>
    <div class="maker-group" id="tools">
      <button type="button" class="hud-button hud-corners" data-tool="move" aria-pressed="true" title="Przesuwanie (V, albo spacja z przeciąganiem); kółko powiększa">Przesuń</button>
      <button type="button" class="hud-button hud-corners" data-tool="wand" aria-pressed="false" title="Różdżka (W): usuwa podobny kolor wokół kliknięcia, z Alt przywraca">Różdżka</button>
      <button type="button" class="hud-button hud-corners" data-tool="erase" aria-pressed="false" title="Gumka (E): wymazuje tło">Gumka</button>
      <button type="button" class="hud-button hud-corners" data-tool="restore" aria-pressed="false" title="Pędzel (B): przywraca wymazane">Pędzel</button>
      <button type="button" class="hud-button hud-corners" id="undo" title="Cofnij zmianę maski (Ctrl+Z)">Cofnij</button>
    </div>
    <div class="maker-group" id="views">
      <button type="button" class="hud-button hud-corners" data-view="mask" aria-pressed="false" title="Oryginał z zaznaczonym usuniętym tłem (1)">Maska</button>
      <button type="button" class="hud-button hud-corners" data-view="ink" aria-pressed="true" title="Wynik, czarno-biały (2)">Wynik</button>
      <button type="button" class="hud-button hud-corners" data-view="hues" aria-pressed="false" title="Wynik z kolorami, jak w 0x40 (3)">Hues</button>
    </div>
    <div class="maker-group maker-save">
      <input type="text" class="field" id="name" maxlength="100" autocomplete="off" spellcheck="false" placeholder="Imię postaci" aria-label="Nazwa obrazu" />
      <button type="button" class="hud-button hud-corners" id="save" title="Zapisz do hues/.pack/Images i przebuduj paczkę (Ctrl+S)">Do paczki</button>
      <button type="button" class="hud-button hud-corners" id="download" title="Pobierz PNG">PNG</button>
    </div>
  </div>
  <main class="maker-main">
    <div class="maker-stage" id="stage-box">
      <canvas id="stage"></canvas>
      <p class="maker-empty" id="empty">Upuść tu obraz, wklej go (Ctrl+V) albo otwórz.<br />Najlepiej postać na gładkim tle albo PNG z przezroczystością.</p>
    </div>
    <aside class="maker-side">
      <section class="maker-section">
        <h2 class="maker-heading">Kadr</h2>
        <label class="maker-row"><span>Szerokość</span>
          <span class="hud-select"><select id="width">
            <option value="1280">1280 (16:9)</option>
            <option value="1440">1440</option>
            <option value="1600">1600</option>
            <option value="1920">1920</option>
          </select><svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg></span>
        </label>
        <label class="maker-row"><span>Skala</span><input class="player-range" id="zoom" type="range" min="-3" max="3" step="0.01" value="0" /><output id="zoom-value"></output></label>
        <div class="maker-group">
          <button type="button" class="hud-button hud-corners" id="fit" title="Cała wysokość, na środku, na dolnej krawędzi">Dopasuj</button>
          <button type="button" class="hud-button hud-corners" id="ground" title="Postać stoi na dolnej krawędzi, jak w paczce">Na dół</button>
          <button type="button" class="hud-button hud-corners" id="centre" title="Postać na środku szerokości">Na środek</button>
          <button type="button" class="hud-button hud-corners" id="flip" aria-pressed="false" title="Odbij w poziomie">Odbij</button>
        </div>
      </section>
      <section class="maker-section">
        <h2 class="maker-heading">Tło</h2>
        <div class="maker-group">
          <button type="button" class="hud-button hud-corners" id="edges" title="Usuń kolor tła stykający się z krawędziami obrazu">Od krawędzi</button>
          <button type="button" class="hud-button hud-corners" id="reset-mask" title="Przywróć całe tło">Przywróć całe</button>
        </div>
        <label class="maker-row"><span>Tolerancja</span><input class="player-range" id="tolerance" type="range" min="0" max="120" step="1" value="32" /><output></output></label>
        <label class="maker-row"><span>Pędzel</span><input class="player-range" id="brush" type="range" min="2" max="200" step="1" value="30" /><output></output></label>
        <label class="maker-row"><span>Przytnij brzeg</span><input class="player-range" id="shrink" type="range" min="0" max="8" step="1" value="1" /><output></output></label>
      </section>
      <section class="maker-section">
        <h2 class="maker-heading">Tusz</h2>
        <label class="maker-row"><span>Wypełnienie</span>
          <span class="hud-select"><select id="mode">
            <option value="threshold">Próg (płaskie)</option>
            <option value="bayer">Raster (siatka)</option>
            <option value="dots">Kropki (rastrowe)</option>
            <option value="diffuse">Rozproszenie</option>
          </select><svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg></span>
        </label>
        <label class="maker-row"><span>Jasność</span><input class="player-range" id="brightness" type="range" min="-128" max="128" step="1" value="40" /><output></output></label>
        <label class="maker-row"><span>Kontrast</span><input class="player-range" id="contrast" type="range" min="0.2" max="3" step="0.05" value="1.2" /><output></output></label>
        <label class="maker-row"><span>Wygładzenie</span><input class="player-range" id="smooth" type="range" min="0" max="3" step="0.1" value="0.6" /><output></output></label>
        <label class="maker-row"><span>Linie</span><input class="player-range" id="lines" type="range" min="0" max="100" step="1" value="60" /><output></output></label>
        <label class="maker-row"><span>Grubość linii</span><input class="player-range" id="weight" type="range" min="0.5" max="4" step="0.1" value="1.2" /><output></output></label>
        <label class="maker-row"><span>Obrys</span><input class="player-range" id="outline" type="range" min="0" max="12" step="1" value="3" /><output></output></label>
        <label class="maker-check"><input id="clean" type="checkbox" checked /> usuń drobinki</label>
        <label class="maker-check"><input id="invert" type="checkbox" /> odwróć czerń i biel</label>
      </section>
      <p class="maker-note" id="state"></p>
      <p class="maker-note"><kbd>V</kbd> <kbd>W</kbd> <kbd>E</kbd> <kbd>B</kbd> narzędzia, spacja przesuwa, kółko skaluje, <kbd>[</kbd> <kbd>]</kbd> pędzel, <kbd>1</kbd>–<kbd>3</kbd> widok, Ctrl+Z cofa, Ctrl+S zapisuje.</p>
      <p class="maker-note">Paczka ma obrazy 720 px wysokości, tylko czerń i biel, a tło całkiem przezroczyste; postać stoi na dolnej krawędzi. Białe miejsca biorą kolor 0x40, czarne zostają ciemne.</p>
    </aside>
  </main>
  <script src="maker.js?v=d0b3e3c54b"></script>
</body>

</html>
