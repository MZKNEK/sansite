<?php
    // Used only by tests/browser.mjs in a disposable copy of the application.
    $root = getenv('SANAKAN_BROWSER_ROOT');
    if (!$root || !is_file($root . '/inc/gallery.php')) { http_response_code(500); exit; }
    define('SITE_DATA_DIR', $root . '/inc/data');
    define('DISCORD_CLIENT_ID', 'test');
    define('DISCORD_CLIENT_SECRET', 'test');
    define('DISCORD_REDIRECT_URI', '/__login');
    define('PANEL_ADMINS', ['900000000000000001']);
    define('GALLERY_ADMINS', ['900000000000000001', '900000000000000003']);
    define('BOT_APP_KEY', '');
    define('SAFEGUARD_SECRET', 'browser-register-secret');
    $GLOBALS['SANAKAN_HTTP'] = function () { return [false, 503]; };
    require $root . '/inc/gallery.php';
    installDataErrorHandler();
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($path === '/__login') {
        $id = (string)($_GET['id'] ?? '900000000000000002');
        if (!in_array($id, ['900000000000000001', '900000000000000002', '900000000000000003', '900000000000000004'], true)) exit;
        siteSession();
        $_COOKIE[SITE_SESSION] = session_id();
        $_SESSION['gallery_user'] = ['id' => $id, 'name' => 'User', 'avatar' => '/favicon.svg'];
        $_SESSION['login_time'] = time();
        updateData('roles', function (&$roles) use ($id) { $roles[$id] = ['roles' => ['user' => true, 'onGuild' => true], 'checked' => time()]; });
        updateData('gallery-usage', function (&$data) {
            $data = ['time' => time(), 'accounts' => ['900000000000000004' => ['time' => time(),
                'usage' => ['values' => ['files' => 300, 'fileBytes' => 1, 'totalBytes' => 1, 'trashBytes' => 1], 'hiddenBytes' => 0, 'exceeded' => ['files']]]]];
        });
        $own = ownFolder($root . '/i');
        @mkdir($root . '/i/' . $own . '/A', 0750, true);
        @mkdir($root . '/i/' . $own . '/B', 0750, true);
        if (!is_file($root . '/i/' . $own . '/A/test.png')) {
            $image = imagecreatetruecolor(10, 10);
            imagepng($image, $root . '/i/' . $own . '/A/test.png');
        }
        echo json_encode(['csrf' => siteCsrf(), 'own' => publicRel($own)]);
        return true;
    }
    if (str_starts_with($path, '/inc/') || str_starts_with($path, '/i/users/')) { http_response_code(404); return true; }
    $file = $root . $path;
    // The public account links and private media use the gallery entry point,
    // just as in server/nginx/sanakan.conf; keep REQUEST_URI unchanged.
    if (preg_match('~^/i/(?:u/[0-9a-f]{16}/|private/)~', $path))
        $file = $root . '/i/index.php';
    if (is_dir($file)) $file = rtrim($file, '/') . (is_file($file . '/index.php') ? '/index.php' : '/index.html');
    if (!is_file($file)) { http_response_code(404); return true; }
    if (!str_ends_with($file, '.php')) return false;
    $_SERVER['SCRIPT_FILENAME'] = $file;
    $_SERVER['SCRIPT_NAME'] = substr($file, strlen($root));
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    chdir(dirname($file));
    include $file;
    return true;
