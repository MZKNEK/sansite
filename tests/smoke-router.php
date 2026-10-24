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

    // Isolated register endpoints for the security regressions in http.php.
    // The router is never deployed, and these accounts have no external API.
    if (in_array($path, ['/__mega', '/__mega-account', '/__mega-login'], true)) {
        define('DISCORD_CLIENT_ID', 'smoke');
        define('DISCORD_CLIENT_SECRET', 'smoke');
        define('DISCORD_REDIRECT_URI', '/__mega-login');
        define('SAFEGUARD_SECRET', 'smoke-register-secret');
        if ($path === '/__mega-login') {
            require $root . '/inc/auth.php';
            siteSession();
            $_SESSION['gallery_user'] = ['id' => '900000000000000011', 'name' => 'Smoke', 'avatar' => ''];
            $_SESSION['login_time'] = time();
            echo 'ok';
            return true;
        }
        require $root . ($path === '/__mega-account' ? '/account.php' : '/megastructure.php');
        return true;
    }

    // the configuration and the data are never served (nginx blocks inc/ too)
    if (strpos($path, '/inc/') === 0) {
        http_response_code(403);
        include $root . '/403.html';
        return true;
    }

    // a page that fails halfway, by an exception or by a fatal error of PHP
    // (a function declared twice), with display_errors off as on the site; the
    // fatal error is not memory run out, which now and then crashed PHP 8.1's
    // built-in server in CI, and every test after it with the server
    if ($path === '/__exception' || $path === '/__fatal') {
        require $root . '/inc/auth.php';
        ini_set('display_errors', '0');
        ini_set('log_errors', '0');
        installDataErrorHandler();
        ob_start();
        echo 'half a page';
        if ($path === '/__exception')
            throw new RuntimeException('the test of the error page');
        eval('function smokeTwice() {} function smokeTwice() {}');
        return true;
    }

    // short links to commands, as nginx gives them to cmd/index.php (decoded)
    if (preg_match('~^/cmd/(?!(?:changes|zmiany)(?:/|$))([^/.]+)/?$~', $path, $match)) {
        $_GET['go'] = rawurldecode($match[1]);
        $path = '/cmd/';
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
