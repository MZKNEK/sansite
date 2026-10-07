<?php
    // The router of the HTTP smoke test (tests/http.php). `php -S` runs it for
    // every request: it points the data folder and the bot API at a temporary
    // place and a canned answer, then serves the site's own files, so the real
    // pages can be exercised without touching inc/data or reaching
    // api.sanakan.pl. It is not sent to the server (tests/ is export-ignore).

    $root = str_replace('\\', '/', dirname(__DIR__));

    $dataDir = getenv('SANAKAN_SMOKE_DATA');
    if ($dataDir === false || $dataDir === '')
        $dataDir = sys_get_temp_dir() . '/sanakan-smoke-' . getmypid();
    if (!defined('SITE_DATA_DIR'))
        define('SITE_DATA_DIR', rtrim(str_replace('\\', '/', $dataDir), '/'));
    if (!is_dir(SITE_DATA_DIR))
        mkdir(SITE_DATA_DIR, 0700, true);
    if (!defined('BOT_API_BASE')) {
        // the canned bot API runs on its own server (tests/bot-stub.php), so the
        // single-threaded site server never asks itself
        $api = getenv('SANAKAN_SMOKE_API');
        if ($api === false || $api === '')
            $api = 'http://127.0.0.1:' . ($_SERVER['SERVER_PORT'] ?? '8799') . '/bot-api';
        define('BOT_API_BASE', rtrim($api, '/'));
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    // the configuration and the data are never served (nginx blocks inc/ too)
    if (strpos($path, '/inc/') === 0) {
        http_response_code(404);
        return true;
    }

    // a folder gets its index file
    $file = $root . $path;
    $script = $path;
    if (is_dir($file)) {
        $dir = rtrim($file, '/');
        if (is_file($dir . '/index.php')) {
            $file = $dir . '/index.php';
            $script = rtrim($path, '/') . '/index.php';
        } else if (is_file($dir . '/index.html')) {
            $file = $dir . '/index.html';
        }
    }

    if (is_file($file) && substr($file, -4) !== '.php')
        return false;   // a static file: let the server send it

    if (!is_file($file)) {
        http_response_code(404);
        include $root . '/404.html';
        return true;
    }

    $_SERVER['SCRIPT_FILENAME'] = $file;
    $_SERVER['SCRIPT_NAME'] = $script;
    $_SERVER['PHP_SELF'] = $script;
    chdir(dirname($file));
    include $file;
    return true;
