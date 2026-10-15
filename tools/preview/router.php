<?php
    // The router of the local preview (tools/preview.sh): php -S runs it for
    // every request. It does what nginx does on the server (server/nginx/):
    // keeps inc/, the data and the dot files out, gives the gallery's links of
    // the accounts' folders and its private folder to i/index.php, sends the
    // short links of the commands on, and adds the security headers of the
    // site (the croppers' own ones for them), so a page that would break the
    // policy breaks here too. Logging in goes to /__login, which lists the
    // made-up accounts of data.php instead of asking Discord.
    require __DIR__ . '/config.php';

    $root = str_replace('\\', '/', dirname(__DIR__, 2));
    $path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

    // the security headers of an nginx rule file, its variable set as nginx does
    function previewHeaders($file, $path)
    {
        $conf = (string)@file_get_contents($file);
        preg_match_all('/^add_header\s+(\S+)\s+"([^"]*)"/m', $conf, $matches, PREG_SET_ORDER);
        foreach ($matches as [, $name, $value])
            if ($name !== 'Strict-Transport-Security')
                header($name . ': ' . str_replace('$sanakan_app_eval', strpos($path, '/uskalpel/') === 0 ? " 'unsafe-eval'" : '', $value));
    }

    // the site's 404 or 403 page, as nginx shows them
    function previewError($root, $status)
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        readfile($root . '/' . $status . '.html');
        return true;
    }

    // the next day of a seasonal event within 430 days (the longest wait, for
    // a Friday the 13th), as "Y-m-d"; a day without other events when there is
    // one, so the preview shows that event alone
    function previewEventDay($event)
    {
        $day = new DateTimeImmutable('today 12:00', new DateTimeZone('Europe/Warsaw'));
        $best = null;
        for ($in = 0; $in <= 430; $in++) {
            $date = $day->modify('+' . $in . ' days');
            $list = safeguardHolidays($date);
            if (in_array($event, $list, true) && ($best === null || count($list) < $best[1]))
                $best = [$date->format('Y-m-d'), count($list)];
        }
        return $best[0];
    }

    // the site's pictures moved to img/, as nginx redirects them (sanakan.conf)
    if (preg_match('~^/(logo\.webp|favicon\.svg|favicon-(?:192x192|256x256|512x512)\.png|sanakan-og\.png|wiki-og\.png)$~', $path, $moved) === 1) {
        header('Location: /img/' . $moved[1], true, 301);
        return true;
    }

    $app = preg_match('~^/u?skalpel/~', $path) === 1;
    previewHeaders($root . '/server/nginx/' . ($app ? 'sanakan-app-headers.conf' : 'sanakan-headers.conf'), $path);

    // Direct links to every seasonal event (?event=<key of SAFEGUARD_SEASONAL>,
    // ?event=none back to today) and skin (?skin=<key of SKINS>), no login
    // needed. The simulated date stays in the host's localStorage; different
    // hosts (127.0.0.1, localhost) keep separate dates, so two events can stay
    // open at once. A skin override lasts this page visit, leaving account
    // preferences alone.
    require_once $root . '/inc/skin-catalog.php';
    $event = is_string($_GET['event'] ?? null) ? $_GET['event'] : '';
    $skin = is_string($_GET['skin'] ?? null) ? $_GET['skin'] : '';
    $skin = isset(SKINS[$skin]) ? $skin : '';
    if ($event !== '' && $event !== 'none') {
        require_once $root . '/inc/auth/finds.php';
        $event = isset(SAFEGUARD_SEASONAL[$event]) ? $event : '';
    }
    if (($event !== '' || $skin !== '') && ($path === '/' || $path === '/__event.js')) {
        header('Cache-Control: no-store');
        if ($path === '/__event.js') {
            header('Content-Type: text/javascript; charset=utf-8');
            if ($event === 'none')
                echo 'localStorage.removeItem("safeguard-date");';
            elseif ($event !== '')
                echo 'localStorage.setItem("safeguard-date", ' . json_encode(previewEventDay($event)) . ');';
            if ($skin !== '') {
                echo 'document.documentElement.setAttribute("data-skin", ' . json_encode($skin) . ');';
                echo 'document.addEventListener("DOMContentLoaded", function () {'
                    . 'SanakanAppearance.forVisit(' . json_encode($skin) . ', false);'
                    . 'document.title = ' . json_encode(SKINS[$skin]['name'] . ' · podgląd Sanakan') . ';'
                    . '});';
            }
        } else {
            header('Content-Type: text/html; charset=utf-8');
            $query = htmlspecialchars(http_build_query(['event' => $event, 'skin' => $skin]), ENT_QUOTES, 'UTF-8');
            echo str_replace('<head>', '<head><script src="/__event.js?' . $query . '"></script>',
                (string)file_get_contents($root . '/index.html'));
        }
        return true;
    }

    // never served: the configuration and the data and the dot files (.preview,
    // .git), refused as nginx does; the folders of the accounts by their IDs
    // and the sources of the apps, missing as on the site
    if (strpos($path, '/inc/') === 0 || preg_match('~/\.(?!well-known/)~', $path))
        return previewError($root, 403);
    if (strpos($path, '/i/users/') === 0 || preg_match('~^/(apps|tools|tests|server)/~', $path))
        return previewError($root, 404);

    // The made-up login: the list of the accounts, or a session as one of them,
    // as Discord would have given it, and back to the page that asked
    if ($path === '/__login') {
        require $root . '/inc/auth.php';
        $back = localPath($_GET['back'] ?? '/');
        $id = (string)($_GET['as'] ?? '');
        $account = $previewData['accounts'][$id] ?? null;
        if ($account !== null) {
            siteSession();
            session_regenerate_id(true);
            $avatar = 'https://cdn.discordapp.com/embed/avatars/' . ((int)substr($id, -1) % 6) . '.png';
            $_SESSION['gallery_user'] = ['id' => $id, 'name' => $account[0], 'avatar' => $avatar];
            $_SESSION['login_time'] = time();
            unset($_SESSION['address_seen']);
            siteCsrf();
            setFlash('Zalogowano jako ' . $account[0] . ' (podgląd).');
            recordLogin($id, $account[0], $avatar, strtolower($account[0]));
            header('Location: ' . $back, true, 303);
            return true;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Podgląd: logowanie</title><link rel="stylesheet" href="/css/fonts.css"><link rel="stylesheet" href="/css/style.css"><link rel="stylesheet" href="/css/account.css"><link rel="stylesheet" href="/__preview.css"></head>'
            . '<body class="preview-login"><main class="content"><div class="tag">PODGLĄD LOKALNY</div><h1 class="hud-title">Zaloguj jako</h1>'
            . '<p>Konta są zmyślone (tools/preview/data.php), role daje zmyślone API bota.</p><ul>';
        foreach ($previewData['accounts'] as $accountId => [$name, $role, $about])
            echo '<li><a class="hud-corners" href="/__login?as=' . $accountId . '&amp;back=' . rawurlencode($back) . '"><b>' . htmlspecialchars($name) . '</b>'
                . ($role ? ' <span class="lv role-' . $role . '">' . strtoupper($role) . '</span>' : '') . '<small>' . htmlspecialchars($about) . '</small></a></li>';
        echo '</ul><p><a href="' . htmlspecialchars($back) . '">&larr; wróć</a></p></main></body></html>';
        return true;
    }

    // the look of that list, kept with the preview and not among the site's files
    if ($path === '/__preview.css') {
        header('Content-Type: text/css');
        readfile(__DIR__ . '/preview.css');
        return true;
    }

    // the pictures of the bot's cards, mirrored by the bot check into the data
    // folder (inc/pw.php), as nginx serves them at /pw/
    if (strpos($path, '/pw/') === 0) {
        $pwRoot = realpath(SITE_DATA_DIR . '/pw');
        $pwFile = $pwRoot === false ? false : realpath($pwRoot . '/' . substr($path, 4));
        if ($pwFile === false || strpos(str_replace('\\', '/', $pwFile), str_replace('\\', '/', $pwRoot) . '/') !== 0 || !is_file($pwFile))
            return previewError($root, 404);
        $file = $pwFile;
        $types = ['png' => 'image/png', 'webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'json' => 'application/json'];
        header('Content-Type: ' . ($types[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($file));
        readfile($file);
        return true;
    }

    // short links to commands: /cmd/daily goes to cmd/index.php, which sends
    // on to /cmd/#daily or asks for a login
    if (preg_match('~^/cmd/(?!(?:changes|zmiany)(?:/|$))([^/.]+)/?$~', $path, $match)) {
        $_GET['go'] = $match[1];
        $path = '/cmd/';
    }

    // what nginx gives to the gallery: the links of the accounts' folders, the
    // private folder, and a picture that is gone (its old link after the change to WebP)
    $file = $root . $path;
    $script = $path;
    if (preg_match('~^/i/(u/[0-9a-f]{16}/|private/)~', $path)
        || (preg_match('~^/i/.+\.(?:png|jpe?g|gif|avif|webm|mp4)$~i', $path) && !is_file($file))) {
        $file = $root . '/i/index.php';
        $script = '/i/index.php';
    }

    // a folder: its index file, or the address with the slash nginx adds
    if (is_dir($file)) {
        if (substr($path, -1) !== '/') {
            header('Location: ' . $path . '/' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
            return true;
        }
        $dir = rtrim($file, '/');
        if (is_file($dir . '/index.php')) {
            $file = $dir . '/index.php';
            $script = rtrim($path, '/') . '/index.php';
        } else if (is_file($dir . '/index.html')) {
            $file = $dir . '/index.html';
        }
    }

    if (!is_file($file))
        return previewError($root, 404);

    if (substr($file, -4) !== '.php') {
        $types = ['html' => 'text/html; charset=utf-8', 'css' => 'text/css', 'js' => 'text/javascript', 'mjs' => 'text/javascript',
            'json' => 'application/json', 'wasm' => 'application/wasm', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif', 'ico' => 'image/x-icon',
            'woff2' => 'font/woff2', 'webm' => 'video/webm', 'mp4' => 'video/mp4', 'mp3' => 'audio/mpeg',
            'txt' => 'text/plain; charset=utf-8', 'xml' => 'application/xml'];
        header('Content-Type: ' . ($types[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
        header('Accept-Ranges: bytes');
        $size = filesize($file);
        // one byte range, as nginx answers it, so a skin's song can go on
        // from the middle on the next page
        if (preg_match('/^bytes=(\d*)-(\d*)$/', (string)($_SERVER['HTTP_RANGE'] ?? ''), $range) === 1 && $range[1] . $range[2] !== '') {
            $start = $range[1] === '' ? max(0, $size - (int)$range[2]) : (int)$range[1];
            $end = $range[1] === '' || $range[2] === '' ? $size - 1 : min((int)$range[2], $size - 1);
            if ($start > $end) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                return true;
            }
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
            header('Content-Length: ' . ($end - $start + 1));
            $handle = fopen($file, 'rb');
            fseek($handle, $start);
            for ($left = $end - $start + 1; $left > 0 && !feof($handle); $left -= strlen($chunk)) {
                $chunk = (string)fread($handle, min(65536, $left));
                echo $chunk;
            }
            fclose($handle);
            return true;
        }
        header('Content-Length: ' . $size);
        readfile($file);
        return true;
    }

    // ?login on any page (and account.php?login&back=) starts the made-up
    // login instead of Discord's, coming back to that page
    if (isset($_GET['login'])) {
        $back = $script === '/account.php' ? (string)($_GET['back'] ?? '/') : $path;
        header('Location: /__login?back=' . rawurlencode($back), true, 302);
        return true;
    }

    $_SERVER['SCRIPT_FILENAME'] = $file;
    $_SERVER['SCRIPT_NAME'] = $script;
    $_SERVER['PHP_SELF'] = $script;
    chdir(dirname($file));
    include $file;
    return true;
