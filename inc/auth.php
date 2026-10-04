<?php
    // Discord login (OAuth2) shared by the gallery in i/, the API documentation
    // in api/ and the admin panel in admin/: one session for all, and the lists
    // of who may do what.
    //
    // inc/config.php (only on the server, see config.example.php) has the
    // Discord application keys and the fixed account lists:
    //   PANEL_ADMINS     may open the admin panel
    //   GALLERY_ADMINS   may view and manage the gallery
    //   GALLERY_VIEWERS  may view the gallery (true lets in anyone with Discord)
    //   API_VIEWERS      may read the API documentation (the panel admins always can)
    // The panel adds more gallery admins and viewers and API readers. An account
    // without access can ask for it; the requests wait in inc/data/requests.json. Those are kept in
    // inc/data/access.json, next to a list of recent logins; inc/ is not
    // reachable from the web.
    const SITE_SESSION = 'sanakan_gallery';  // the gallery's old cookie name, so logins stay valid

    // the times in the history and the panel follow Polish time, not the server's
    date_default_timezone_set('Europe/Warsaw');
    const LOGIN_LOG_SIZE = 50;
    const SESSION_DAYS = 7;
    const HISTORY_KEEP = 1000;

    // the lists the panel can add to, and the config constant each one extends
    const ACCESS_LISTS = [
        'galleryAdmins' => 'GALLERY_ADMINS',
        'galleryViewers' => 'GALLERY_VIEWERS',
        'apiViewers' => 'API_VIEWERS'
    ];

    // what an account can ask for, as it reads after "dostęp do"
    const REQUEST_LABELS = ['gallery' => 'galerii', 'api' => 'API'];
    const REQUEST_NOTE_LENGTH = 200;

    function authConfigured()
    {
        static $loaded = false;
        if (!$loaded) {
            $loaded = true;
            $file = __DIR__ . '/config.php';
            if (is_file($file))
                require $file;
        }

        return defined('DISCORD_CLIENT_ID') && DISCORD_CLIENT_ID !== ''
            && defined('DISCORD_CLIENT_SECRET') && defined('DISCORD_REDIRECT_URI');
    }

    // URL path of the site root, e.g. "/", worked out from the page in i/ or admin/
    function siteRoot()
    {
        $root = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/x/index.php')));

        return rtrim($root, '/') . '/';
    }

    // the first $length characters, also without the mbstring extension
    function cutText($text, $length)
    {
        if (function_exists('mb_substr'))
            return mb_substr($text, 0, $length, 'UTF-8');

        return preg_match('/^.{0,' . (int)$length . '}/us', $text, $match) ? $match[0] : substr($text, 0, $length);
    }

    // ---- Stored data ----------------------------------------------------------

    function dataDir()
    {
        return __DIR__ . '/data';
    }

    function dataWritable()
    {
        return is_dir(dataDir()) ? is_writable(dataDir()) : is_writable(__DIR__);
    }

    function readData($name)
    {
        $file = dataDir() . '/' . $name . '.json';
        $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;

        return is_array($data) ? $data : [];
    }

    // writes a temp file and renames it, so a parallel request never reads half a file
    function writeData($name, $data)
    {
        if (!is_dir(dataDir()) && !@mkdir(dataDir(), 0750, true))
            return false;

        $file = dataDir() . '/' . $name . '.json';
        $tmp = $file . '.' . getmypid();
        if (@file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false)
            return false;

        return @rename($tmp, $file);
    }

    // ---- History of changes ----------------------------------------------------
    // One JSON line per change in the gallery or the panel: when, who, what.
    // Only the newest HISTORY_KEEP entries are kept.

    function historyFile()
    {
        return dataDir() . '/history.log';
    }

    function addHistory($action, $text)
    {
        if (!is_dir(dataDir()))
            @mkdir(dataDir(), 0750, true);

        $user = siteUser();
        $line = json_encode([
            'time' => time(),
            'id' => $user['id'] ?? '',
            'name' => $user['name'] ?? '',
            'action' => $action,
            'text' => $text
        ], JSON_UNESCAPED_UNICODE) . "\n";

        $file = historyFile();
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

        // trimmed now and then instead of on every write
        clearstatcache();
        if (@filesize($file) > 600000) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            @file_put_contents($file, implode("\n", array_slice($lines, -HISTORY_KEEP)) . "\n", LOCK_EX);
        }
    }

    // the newest entries first
    function historyEntries($limit)
    {
        $lines = @file(historyFile(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $entries = [];
        foreach (array_reverse(array_slice($lines, -$limit)) as $line) {
            $entry = json_decode($line, true);
            if (is_array($entry))
                $entries[] = $entry;
        }

        return $entries;
    }

    // ---- Who may do what --------------------------------------------------------

    // IDs from a config constant, or true when it lets everyone in
    function configList($name)
    {
        authConfigured();
        if (!defined($name))
            return [];

        $value = constant($name);
        return $value === true ? true : array_map('strval', (array)$value);
    }

    // entries the panel added to a list: [id => ['note' => ..., 'added' => time, 'by' => id]]
    function panelList($list)
    {
        $access = readData('access');

        return isset($access[$list]) && is_array($access[$list]) ? $access[$list] : [];
    }

    // in the config list or added in the panel; "everyone" counts only where $allowAll
    function inAccessList($list, $id, $allowAll)
    {
        $config = configList(ACCESS_LISTS[$list]);
        if ($config === true)
            return $allowAll;

        return in_array((string)$id, $config, true) || isset(panelList($list)[(string)$id]);
    }

    function isPanelAdminId($id)
    {
        $config = configList('PANEL_ADMINS');

        return $config !== true && in_array((string)$id, $config, true);
    }

    function isGalleryAdminId($id)
    {
        return inAccessList('galleryAdmins', $id, false);
    }

    function canViewGalleryId($id)
    {
        return isGalleryAdminId($id) || inAccessList('galleryViewers', $id, true);
    }

    function canViewApiId($id)
    {
        return isPanelAdminId($id) || inAccessList('apiViewers', $id, true);
    }

    function apiCanView()
    {
        $user = siteUser();

        return $user !== null && canViewApiId($user['id']);
    }

    // ---- Requests for access ----------------------------------------------------
    // ['gallery' => [id => ['name', 'note', 'time']], 'api' => [...]]

    // the request of an account still waiting, or null
    function pendingRequest($for, $id)
    {
        return readData('requests')[$for][(string)$id] ?? null;
    }

    // a place on this site to go back to after a form: "?p=..." or "./"
    function localBack($back)
    {
        $back = (string)$back;

        return preg_match('/^(\?|\.\/)/', $back) ? $back : './';
    }

    // Saves the request of the logged-in account sent from a locked page and goes
    // back there with a message; one request at a time, again only after an hour.
    function handleAccessRequest($for, $allowed, $back)
    {
        $user = siteUser();
        $requests = readData('requests');
        $pending = $requests[$for][$user['id']] ?? null;

        if ($allowed) {
            setFlash('To konto ma już dostęp do ' . REQUEST_LABELS[$for] . '.');
        } else if ($pending && $pending['time'] > time() - 3600) {
            setFlash('Prośba już czeka na administratora.');
        } else {
            $note = cutText(trim((string)($_POST['note'] ?? '')), REQUEST_NOTE_LENGTH);
            $requests[$for][$user['id']] = ['name' => $user['name'], 'note' => $note, 'time' => time()];
            if (writeData('requests', $requests)) {
                addHistory('request', 'Prośba o dostęp do ' . REQUEST_LABELS[$for] . ($note === '' ? '.' : ': „' . $note . '”.'));
                setFlash('Wysłano prośbę o dostęp. Administrator zobaczy ją w panelu.');
            } else {
                setFlash('Nie udało się zapisać prośby, spróbuj później.');
            }
        }

        header('Location: ' . localBack($back), true, 303);
        exit;
    }

    // ---- Session ------------------------------------------------------------------

    // Whether the visitor's browser talks https. Cloudflare ends https and asks
    // the server over plain http, saying the visitor's scheme in CF-Visitor
    // (X-Forwarded-Proto from other proxies). Sent by anyone talking to the
    // server directly, these only make that one's own cookie https-only.
    function visitorUsesHttps()
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            return true;
        if (strpos((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''), '"https"') !== false)
            return true;

        return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    function siteSession()
    {
        if (session_status() === PHP_SESSION_ACTIVE)
            return;

        // A login lasts SESSION_DAYS. The sessions are kept in inc/data/sessions:
        // PHP's own folder is cleared by Ubuntu's cron after 24 minutes of quiet,
        // which would end a login much sooner. PHP clears this folder itself.
        $dir = dataDir() . '/sessions';
        if (is_dir($dir) || @mkdir($dir, 0700, true))
            session_save_path($dir);
        ini_set('session.gc_maxlifetime', (string)(SESSION_DAYS * 86400));
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');

        $secure = visitorUsesHttps();
        $lifetime = SESSION_DAYS * 86400;
        session_name(SITE_SESSION);
        // Lax, not Strict: the cookie has to come along when Discord sends the visitor back
        if (PHP_VERSION_ID >= 70300)
            session_set_cookie_params(['lifetime' => $lifetime, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
        else
            session_set_cookie_params($lifetime, '/; samesite=Lax', '', $secure, true);
        session_start();
    }

    // visitors get no session; one is started only when its cookie is there
    function sessionExists()
    {
        return !empty($_COOKIE[SITE_SESSION]);
    }

    // ['id' => ..., 'name' => ..., 'avatar' => URL] of the logged-in Discord account, or null;
    // what the account may do is checked separately on every request
    function siteUser()
    {
        if (!authConfigured() || !sessionExists())
            return null;

        siteSession();
        $user = $_SESSION['gallery_user'] ?? null;
        if (!isset($user['id']))
            return null;

        // logged in before "log out everyone" in the panel
        if (($_SESSION['login_time'] ?? 0) < sessionsValidSince()) {
            unset($_SESSION['gallery_user']);
            $_SESSION['gallery_flash'] = 'Sesja wygasła, zaloguj się jeszcze raz.';
            return null;
        }

        return $user;
    }

    // sessions started before this time are not valid any more
    function sessionsValidSince()
    {
        static $since = null;
        if ($since === null)
            $since = (int)(readData('sessions')['since'] ?? 0);

        return $since;
    }

    // token sent with every change, so another site cannot make the browser do one
    function siteCsrf()
    {
        siteSession();
        if (empty($_SESSION['gallery_csrf']))
            $_SESSION['gallery_csrf'] = bin2hex(random_bytes(32));

        return $_SESSION['gallery_csrf'];
    }

    function checkCsrf()
    {
        return hash_equals(siteCsrf(), (string)($_POST['csrf'] ?? ''));
    }

    // a message for the next page view, e.g. after the login
    function setFlash($message)
    {
        siteSession();
        $_SESSION['gallery_flash'] = $message;
    }

    function takeFlash()
    {
        if (!sessionExists())
            return null;

        siteSession();
        $message = $_SESSION['gallery_flash'] ?? null;
        unset($_SESSION['gallery_flash']);

        return $message;
    }

    function logout()
    {
        siteSession();
        $_SESSION = [];
        session_destroy();
        setcookie(SITE_SESSION, '', time() - 3600, '/');
    }

    // ---- Discord login -------------------------------------------------------------

    // DISCORD_API_URL in the config can point the login at a test server
    function discordApi()
    {
        return defined('DISCORD_API_URL') ? DISCORD_API_URL : 'https://discord.com/api';
    }

    function discordRequest($path, $form = null, $token = null)
    {
        $headers = ['Accept: application/json', 'User-Agent: SanakanSite (https://sanakan.pl, 1.0)'];
        if ($token !== null)
            $headers[] = 'Authorization: Bearer ' . $token;

        $http = ['method' => $form === null ? 'GET' : 'POST', 'timeout' => 10, 'ignore_errors' => true];
        if ($form !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $http['content'] = http_build_query($form);
        }
        $http['header'] = implode("\r\n", $headers);

        $json = @file_get_contents(discordApi() . $path, false, stream_context_create(['http' => $http]));
        $data = $json === false ? null : json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    // ?login starts the login; Discord comes back to DISCORD_REDIRECT_URI with ?state
    // and ?code (or ?error). The gallery and the panel both answer that, so either can
    // be the redirect address. $returnPath is where to go after a login started here.
    function handleLoginRequest($returnPath)
    {
        if (!authConfigured())
            return;

        if (isset($_GET['login'])) {
            startLogin($returnPath);
            exit;
        }
        if (isset($_GET['state']) && (isset($_GET['code']) || isset($_GET['error']))) {
            header('Location: ' . finishLogin());
            exit;
        }
    }

    // Step 1: the visitor goes to Discord; "state" ties the way back to this session
    function startLogin($returnPath)
    {
        siteSession();
        $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
        $_SESSION['oauth_return'] = $returnPath;

        header('Location: https://discord.com/oauth2/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => DISCORD_CLIENT_ID,
            'scope' => 'identify',
            'state' => $_SESSION['oauth_state'],
            'redirect_uri' => DISCORD_REDIRECT_URI,
            'prompt' => 'none'
        ]));
    }

    // Step 2: Discord sends the visitor back with a code, which is exchanged for the
    // account. The account is kept in the session and in the list of recent logins;
    // each page then decides what it may do. Returns the path to go back to.
    function finishLogin()
    {
        siteSession();
        $state = $_SESSION['oauth_state'] ?? '';
        $returnPath = $_SESSION['oauth_return'] ?? siteRoot();
        unset($_SESSION['oauth_state'], $_SESSION['oauth_return']);

        if (isset($_GET['error'])) {
            setFlash('Logowanie zostało anulowane.');
            return $returnPath;
        }
        if ($state === '' || !hash_equals($state, (string)($_GET['state'] ?? ''))) {
            setFlash('Logowanie wygasło, spróbuj jeszcze raz.');
            return $returnPath;
        }

        $token = discordRequest('/oauth2/token', [
            'grant_type' => 'authorization_code',
            'code' => (string)($_GET['code'] ?? ''),
            'redirect_uri' => DISCORD_REDIRECT_URI,
            'client_id' => DISCORD_CLIENT_ID,
            'client_secret' => DISCORD_CLIENT_SECRET
        ]);
        $account = empty($token['access_token']) ? [] : discordRequest('/users/@me', null, $token['access_token']);
        if (empty($account['id'])) {
            setFlash('Discord nie potwierdził logowania, spróbuj jeszcze raz.');
            return $returnPath;
        }

        $id = (string)$account['id'];
        $name = !empty($account['global_name']) ? $account['global_name'] : ($account['username'] ?? 'Discord');
        $avatar = empty($account['avatar'])
            ? 'https://cdn.discordapp.com/embed/avatars/' . (((int)$id >> 22) % 6) . '.png'
            : 'https://cdn.discordapp.com/avatars/' . rawurlencode($id) . '/' . rawurlencode($account['avatar']) . '.png?size=64';

        session_regenerate_id(true);
        $_SESSION['gallery_user'] = ['id' => $id, 'name' => $name, 'avatar' => $avatar];
        $_SESSION['login_time'] = time();
        siteCsrf();
        setFlash('Zalogowano jako ' . $name . '.');
        recordLogin($id, $name, $avatar, $account['username'] ?? '');

        return $returnPath;
    }

    // recent logins, also of accounts without access, so the panel can let them in
    function recordLogin($id, $name, $avatar, $username)
    {
        $logins = readData('logins');
        $count = isset($logins[$id]['count']) ? (int)$logins[$id]['count'] : 0;
        unset($logins[$id]);
        $logins = [$id => [
            'name' => $name,
            'username' => $username,
            'avatar' => $avatar,
            'last' => time(),
            'count' => $count + 1
        ]] + $logins;

        writeData('logins', array_slice($logins, 0, LOGIN_LOG_SIZE, true));
    }
