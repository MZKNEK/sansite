<?php
    // A maker of the songs of 0x40's pack (songs.js): any song in, cut into a
    // buildup played once and the loop after it, with a seamless join, its
    // beat map (what happens on every half beat, the characters of beats.js)
    // written by hand or counted from the sound, heard and seen as 0x40 shows
    // it. A song of the pack is opened the same way, to change its cut or
    // only its map. The cuts go to .pack/Songs/loop_<Name>.ogg and
    // build_<Name>.ogg as Opus (ffmpeg, from FFMPEG or the PATH), the maps to
    // respack.json through a songs.xml given to respack.php. Only run
    // locally: it starts PHP's own server on 127.0.0.1 with the site's folder
    // as its root and itself as the router, and opens the browser:
    //   php hues/songs.php [port]
    //   FFMPEG=/path/to/ffmpeg php hues/songs.php
    // Any other server (the site's, tools/preview.sh's) gets a 404.
    if (PHP_SAPI === 'cli') {
        $port = (int)($argv[1] ?? 8742);
        $url = "http://127.0.0.1:$port/hues/songs.php";
        // a cut of a few minutes as WAV is tens of megabytes
        $server = proc_open([PHP_BINARY, '-d', 'upload_max_filesize=1G', '-d', 'post_max_size=1G', '-d', 'memory_limit=512M',
            '-S', "127.0.0.1:$port", '-t', dirname(__DIR__), __FILE__], [STDIN, STDOUT, STDERR], $pipes,
            null, getenv() + ['SANAKAN_SONGS_LOCAL' => '1']);
        if (!is_resource($server))
            exit(1);
        usleep(400000);
        echo "Utwory 0x40: $url (Ctrl+C kończy)\n";
        if (PHP_OS_FAMILY === 'Windows')
            pclose(popen('start "" ' . escapeshellarg($url), 'r'));
        elseif (PHP_OS_FAMILY === 'Darwin')
            exec('open ' . escapeshellarg($url));
        else
            exec('xdg-open ' . escapeshellarg($url) . ' >/dev/null 2>&1 &');
        exit(proc_close($server));
    }

    if (PHP_SAPI !== 'cli-server' || getenv('SANAKAN_SONGS_LOCAL') !== '1') {
        http_response_code(404);
        exit;
    }

    // the router of the local server: the site's own files (its styles,
    // fonts, scripts) as they are, none in a dot folder, this page, and
    // nothing else
    $path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if ($path !== '/hues/songs.php') {
        if (!preg_match('~(^|/)\.~', $path) && preg_match('~\.(?:css|js|woff2|svg|ico|png)$~', $path)
            && is_file(dirname(__DIR__) . $path))
            return false;
        http_response_code(404);
        exit;
    }

    $pack = __DIR__ . '/.pack';
    $action = (string)($_GET['a'] ?? '');

    function songsAnswer($status, $data)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    function songsList($pack)
    {
        $json = json_decode((string)@file_get_contents($pack . '/respack.json'), true);
        return is_array($json) ? $json : ['images' => [], 'songs' => []];
    }

    // the songs with their maps, and the pictures for the preview
    if ($action === 'pack') {
        $json = songsList($pack);
        songsAnswer(200, ['songs' => $json['songs'] ?? [], 'images' => $json['images'] ?? []]);
    }

    // a song or a picture of the pack, by its name in the list
    if ($action === 'file') {
        $name = (string)($_GET['f'] ?? '');
        $types = ['Songs' => ['ogg', 'audio/ogg'], 'Images' => ['webp', 'image/webp']];
        if (!preg_match('~^(Songs|Images)/([^/\\\\]+)$~', $name, $m) || $m[2][0] === '.'
            || pathinfo($m[2], PATHINFO_EXTENSION) !== $types[$m[1]][0] || !is_file("$pack/$name")) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . $types[$m[1]][1]);
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        readfile("$pack/$name");
        exit;
    }

    // a WAV made Opus in an Ogg, as the pack's songs are, written whole or
    // not at all
    function songsEncode($wav, $target)
    {
        $ffmpeg = getenv('FFMPEG') ?: 'ffmpeg';
        $temp = $target . '.tmp.ogg';
        exec(escapeshellarg($ffmpeg) . ' -hide_banner -loglevel error -y -i ' . escapeshellarg($wav)
            . ' -c:a libopus -b:a 96k -f ogg ' . escapeshellarg($temp) . ' 2>&1', $output, $code);
        if ($code !== 0 || !is_file($temp) || filesize($temp) === 0) {
            @unlink($temp);
            return 'ffmpeg (' . $ffmpeg . '): ' . trim(implode("\n", $output) ?: "kod $code");
        }
        return rename($temp, $target) ? null : 'nie udało się zapisać ' . basename($target);
    }

    // key (the file's name: loop_<key>.ogg), title, rhythm and buildupRhythm
    // (beat strings), fresh (1 for a new song, which may not take the place of
    // one there is unless overwrite is 1), loop and build (WAV of the cut,
    // left out to keep the files there are) and nobuild (1 to take the
    // buildup away)
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $key = (string)($_POST['key'] ?? '');
        if (!preg_match("~^[A-Za-z0-9][A-Za-z0-9'()_-]{0,79}$~", $key))
            songsAnswer(400, ['error' => 'key']);
        $title = trim(preg_replace('/\s+/u', ' ', (string)($_POST['title'] ?? '')));
        $rhythm = (string)($_POST['rhythm'] ?? '');
        $buildupRhythm = (string)($_POST['buildupRhythm'] ?? '');
        if (!preg_match('/^[xoXO:*~=|+isv#.-]{1,20000}$/', $rhythm) || !preg_match('/^[xoXO:*~=|+isv#.-]{0,20000}$/', $buildupRhythm)
            || mb_strlen($title) > 200)
            songsAnswer(400, ['error' => 'map']);
        $loopFile = "$pack/Songs/loop_$key.ogg";
        $buildFile = "$pack/Songs/build_$key.ogg";
        $there = is_file($loopFile);
        if (($_POST['fresh'] ?? '') === '1' && $there && ($_POST['overwrite'] ?? '') !== '1')
            songsAnswer(409, ['error' => 'exists']);
        $uploads = [];
        foreach (['loop', 'build'] as $part) {
            $upload = $_FILES[$part] ?? null;
            if (!$upload || $upload['error'] === UPLOAD_ERR_NO_FILE)
                continue;
            if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name']))
                songsAnswer(400, ['error' => 'upload']);
            $head = (string)file_get_contents($upload['tmp_name'], false, null, 0, 12);
            if (substr($head, 0, 4) !== 'RIFF' || substr($head, 8, 4) !== 'WAVE')
                songsAnswer(400, ['error' => 'wav']);
            $uploads[$part] = $upload['tmp_name'];
        }
        if (!$there && !isset($uploads['loop']))
            songsAnswer(400, ['error' => 'loop']);
        if (!is_dir("$pack/Songs") && !mkdir("$pack/Songs", 0777, true))
            songsAnswer(500, ['error' => 'write']);
        foreach ($uploads as $part => $wav) {
            $error = songsEncode($wav, $part === 'loop' ? $loopFile : $buildFile);
            if ($error !== null)
                songsAnswer(500, ['error' => 'encode', 'output' => $error]);
        }
        if (($_POST['nobuild'] ?? '') === '1' && is_file($buildFile))
            unlink($buildFile);

        // the map through a songs.xml, which respack.php keeps from then on
        $xml = new SimpleXMLElement('<songs/>');
        $song = $xml->addChild('song');
        $song->addAttribute('name', 'loop_' . $key);
        $song->addChild('title', htmlspecialchars($title, ENT_XML1 | ENT_QUOTES));
        $song->addChild('rhythm', $rhythm);
        if ($buildupRhythm !== '' && is_file($buildFile))
            $song->addChild('buildupRhythm', $buildupRhythm);
        $xmlFile = tempnam(sys_get_temp_dir(), 'songs');
        file_put_contents($xmlFile, $xml->asXML());
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/respack.php') . ' ' . escapeshellarg($xmlFile) . ' 2>&1', $output, $code);
        unlink($xmlFile);
        $entry = null;
        foreach (songsList($pack)['songs'] ?? [] as $listed) {
            if ($listed['loop'] === 'loop_' . $key . '.ogg')
                $entry = $listed;
        }
        songsAnswer(200, ['code' => $code, 'output' => implode("\n", $output), 'song' => $entry]);
    }

    // key: a song taken out of the pack, its loop and buildup, then
    // respack.php lists what is left (deploy.sh never removes a file on the
    // server, but the list it sends there no longer has it)
    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $key = (string)($_POST['key'] ?? '');
        if (!preg_match("~^[A-Za-z0-9][A-Za-z0-9'()_-]{0,79}$~", $key))
            songsAnswer(400, ['error' => 'key']);
        $loopFile = "$pack/Songs/loop_$key.ogg";
        $buildFile = "$pack/Songs/build_$key.ogg";
        if (!is_file($loopFile))
            songsAnswer(404, ['error' => 'missing']);
        if (!unlink($loopFile) || (is_file($buildFile) && !unlink($buildFile)))
            songsAnswer(500, ['error' => 'write']);
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/respack.php') . ' 2>&1', $output, $code);
        songsAnswer(200, ['code' => $code, 'output' => implode("\n", $output)]);
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
  <title>Utwory &middot; 0x40 &middot; Sanakan</title>
  <link rel="icon" href="../favicon.ico" sizes="32x32" />
  <link rel="icon" href="../img/favicon.svg" type="image/svg+xml" />
  <link href="../css/fonts.css?v=8b0e8a863d" type="text/css" rel="stylesheet" />
  <link href="../css/style.css?v=e72561c5d0" type="text/css" rel="stylesheet" />
  <link href="../css/skins.css?v=e7e220aaa9" type="text/css" rel="stylesheet" />
  <link href="../css/explorer.css?v=e330d129f9" type="text/css" rel="stylesheet" />
  <link href="songs.css?v=ec96574321" type="text/css" rel="stylesheet" />
  <script src="../js/appearance.js?v=2e6e831727"></script>
</head>

<body class="songs">
  <div class="songs-bar">
    <label class="hud-button hud-corners" title="Otwórz plik dźwiękowy (albo upuść go tu)">Otwórz<input type="file" id="open" accept="audio/*,.ogg,.opus,.flac,.wav,.mp3,.m4a" /></label>
    <span class="hud-select songs-pick">
      <select id="pick" aria-label="Utwór z paczki"><option value="">Z paczki…</option></select>
      <svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg>
    </span>
    <div class="songs-group">
      <button type="button" class="hud-button hud-corners" id="play-build" title="Od buildupu, potem pętla (Spacja)">Od początku</button>
      <button type="button" class="hud-button hud-corners" id="play-loop" title="Sama pętla, w kółko (P)">Pętla</button>
      <button type="button" class="hud-button hud-corners" id="play-seam" title="Ostatnie 2 sekundy pętli i jej początek: czy łączy się bez skoku (J)">Szew</button>
      <button type="button" class="hud-button hud-corners" id="stop" title="Zatrzymaj (Esc)">Stop</button>
      <span class="songs-clock" id="clock">0:00.000</span>
      <input class="player-range player-volume" id="volume" type="range" min="0" max="100" step="1" value="20" aria-label="Głośność" title="Głośność" />
    </div>
    <div class="songs-group songs-save">
      <input type="text" class="field" id="title" maxlength="200" autocomplete="off" spellcheck="false" placeholder="Wykonawca - Tytuł" aria-label="Tytuł" />
      <input type="text" class="field songs-key" id="key" maxlength="80" autocomplete="off" spellcheck="false" placeholder="NazwaPliku" aria-label="Nazwa pliku (loop_Nazwa.ogg)" title="Nazwa pliku: loop_Nazwa.ogg i build_Nazwa.ogg" />
      <button type="button" class="hud-button hud-corners" id="save" title="Zapisz do hues/.pack (Ctrl+S)">Do paczki</button>
      <button type="button" class="hud-button hud-corners" id="remove" title="Usuń ten utwór z paczki (loop i build)" hidden>Usuń z paczki</button>
    </div>
  </div>
  <main class="songs-main">
    <div class="songs-work">
      <canvas id="overview" class="songs-wave" title="Cały utwór: przeciągnij znaczniki B, L, E; kliknij, żeby słuchać od tego miejsca"></canvas>
      <canvas id="detail" class="songs-wave" title="Kółko przybliża, przeciąganie tła przesuwa, znaczniki można chwytać; dwuklik gra od tego miejsca"></canvas>
      <canvas id="seam" class="songs-wave" title="Szew pętli, tak jak go słychać: z lewej koniec pętli (przed E), z prawej jej początek (od L); szara linia to to, co w utworze naprawdę jest po E. Im bliżej szarej, tym gładziej. Kółko przybliża."></canvas>
      <p class="songs-empty" id="empty">Upuść tu utwór, otwórz plik albo wybierz utwór z paczki.</p>
      <div class="songs-maps" id="maps" tabindex="0" aria-label="Mapa uderzeń">
        <section id="build-map" class="songs-map"><h2 class="songs-heading">Buildup <span id="build-count"></span></h2><div class="songs-cells" id="build-cells"></div></section>
        <section id="loop-map" class="songs-map"><h2 class="songs-heading">Pętla <span id="loop-count"></span></h2><div class="songs-cells" id="loop-cells"></div></section>
      </div>
    </div>
    <aside class="songs-side">
      <canvas id="preview" class="songs-preview"></canvas>
      <section class="songs-section">
        <h2 class="songs-heading">Tempo</h2>
        <label class="songs-row"><span>BPM</span><input type="number" class="field" id="bpm" min="40" max="300" step="0.01" /></label>
        <label class="songs-row"><span>Pierwsze uderzenie</span><input type="number" class="field" id="phase" min="0" step="0.001" /></label>
        <div class="songs-group">
          <button type="button" class="hud-button hud-corners" id="detect" title="Policz tempo z dźwięku">Wykryj</button>
          <button type="button" class="hud-button hud-corners" id="half" title="Połowa tempa">÷2</button>
          <button type="button" class="hud-button hud-corners" id="double" title="Dwa razy szybciej">×2</button>
        </div>
        <label class="songs-check"><input id="snap" type="checkbox" checked /> znaczniki do uderzeń</label>
      </section>
      <section class="songs-section">
        <h2 class="songs-heading">Wokal</h2>
        <label class="songs-check"><input id="vocals" type="checkbox" /> usuń wokal</label>
        <label class="songs-row songs-range"><span>Siła</span><input class="player-range" id="vocals-strength" type="range" min="0" max="100" step="1" value="100" /></label>
        <p class="songs-note">Wycina to, co jest na środku stereo, od ok. 150 Hz do 9 kHz; bas i boki stereo zostają. Zostaje trochę echa wokalu, a inne rzeczy ze środka też cichną. Do paczki idzie wersja bez wokalu.</p>
      </section>
      <section class="songs-section">
        <h2 class="songs-heading">Cięcie</h2>
        <div class="songs-group">
          <span class="songs-label">Pętla</span>
          <button type="button" class="hud-button hud-corners" data-bars="4">4 takty</button>
          <button type="button" class="hud-button hud-corners" data-bars="8">8</button>
          <button type="button" class="hud-button hud-corners" data-bars="16">16</button>
          <button type="button" class="hud-button hud-corners" data-bars="32">32</button>
        </div>
        <div class="songs-group">
          <button type="button" class="hud-button hud-corners" id="no-build" title="Buildup zaczyna się tam, gdzie pętla">Bez buildupu</button>
          <button type="button" class="hud-button hud-corners" id="build-bars" title="Buildup na 4 takty przed pętlą">Buildup 4 takty</button>
        </div>
        <div class="songs-marks">
          <button type="button" class="hud-button hud-corners" data-mark="b" aria-pressed="false" title="Wybierz znacznik początku buildupu">B</button><input type="number" class="field" id="mark-b" min="0" step="0.0001" aria-label="Początek buildupu (s)" />
          <button type="button" class="hud-button hud-corners" data-mark="l" aria-pressed="false" title="Wybierz znacznik początku pętli">L</button><input type="number" class="field" id="mark-l" min="0" step="0.0001" aria-label="Początek pętli (s)" />
          <button type="button" class="hud-button hud-corners" data-mark="e" aria-pressed="true" title="Wybierz znacznik końca pętli">E</button><input type="number" class="field" id="mark-e" min="0" step="0.0001" aria-label="Koniec pętli (s)" />
        </div>
        <div class="songs-group">
          <span class="hud-select songs-step"><select id="nudge-step" aria-label="Krok przesunięcia">
            <option value="sample">1 próbka</option>
            <option value="ms" selected>1 ms</option>
            <option value="10ms">10 ms</option>
            <option value="sixteenth">1/16 uderzenia</option>
          </select><svg class="hud-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" /></svg></span>
          <button type="button" class="hud-button hud-corners" id="nudge-back" aria-label="W lewo" title="Wybrany znacznik w lewo o krok ([)">&minus;</button>
          <button type="button" class="hud-button hud-corners" id="nudge-on" aria-label="W prawo" title="Wybrany znacznik w prawo o krok (])">+</button>
        </div>
        <div class="songs-group">
          <button type="button" class="hud-button hud-corners" id="zero" title="Wybrany znacznik na najbliższe przejście przez zero (do 10 ms), żeby nie trzaskało">Do zera</button>
          <button type="button" class="hud-button hud-corners" id="fit-seam" title="Koniec pętli (E) w ±30 ms tam, gdzie jej początek najlepiej pasuje do tego, co w utworze jest po E">Dopasuj szew</button>
        </div>
        <p class="songs-note" id="cut"></p>
      </section>
      <section class="songs-section">
        <h2 class="songs-heading">Mapa</h2>
        <div class="songs-group">
          <button type="button" class="hud-button hud-corners" id="fill" title="Policz mapę z dźwięku (zastępuje zaznaczone albo całą)">Z dźwięku</button>
          <button type="button" class="hud-button hud-corners" id="clear" title="Kropki w zaznaczonych albo w całej">Wyczyść</button>
        </div>
        <dl class="songs-legend" id="legend"></dl>
        <p class="songs-note">Na fali u góry wykryte momenty: <b>A</b> akcent, <b>T</b> talerz, <b>P</b> pianino, <b>C</b> cymbałki. „Z dźwięku” daje na nich odwrócenie albo cięcie (i s v), cięcie w obu (#), nowy obraz i barwę (-), nowy obraz (*).</p>
      </section>
      <p class="songs-note" id="state"></p>
      <p class="songs-note">Mapa: klik zaznacza, Shift poszerza, znak wpisuje, <kbd>Del</kbd> kropka, strzałki, Ctrl+C/V kopiują. Znaczniki: <kbd>B</kbd> buildup, <kbd>L</kbd> pętla, <kbd>E</kbd> koniec (tam, gdzie gra), <kbd>[</kbd> <kbd>]</kbd> wybrany o krok. Spacja gra od początku, Ctrl+S zapisuje.</p>
    </aside>
  </main>
  <script src="beats.js?v=a741788131"></script>
  <script src="songs.js?v=e77f508de8"></script>
</body>

</html>
