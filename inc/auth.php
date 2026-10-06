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
    //   GALLERY_UPLOADERS get a folder of their own in the gallery and see only it
    //   API_VIEWERS      may read the API documentation (the panel admins always can)
    //   BOT_APP_KEY      the site's key to the bot API (x-app-key with Info rights)
    // With the key the bot also says the account's roles on its Discord server:
    // dev, admin, semi-admin and tester may read the API documentation, the
    // other roles are only shown. The gallery never follows these roles.
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
    // the addresses of logged-in accounts: kept this many days, at most this
    // many per account, noted again after this many seconds in one session
    const ADDRESS_KEEP_DAYS = 30;
    const ADDRESS_PER_ACCOUNT = 30;
    const ADDRESS_NOTE_EVERY = 600;
    // at most this many devices (sessions) remembered per account
    const DEVICES_PER_ACCOUNT = 20;

    // the lists the panel can add to, and the config constant each one extends
    const ACCESS_LISTS = [
        'galleryAdmins' => 'GALLERY_ADMINS',
        'galleryViewers' => 'GALLERY_VIEWERS',
        'galleryUploaders' => 'GALLERY_UPLOADERS',
        'apiViewers' => 'API_VIEWERS'
    ];

    // what an account can ask for, as it reads after "dostęp do"
    const REQUEST_LABELS = ['gallery' => 'galerii', 'api' => 'API'];
    const REQUEST_NOTE_LENGTH = 200;

    // the roles the bot reports, highest first: Safeguard level, badge, name in the panel
    const BOT_ROLES = [
        'dev' => [9, 'DEV', 'dev'],
        'admin' => [8, 'ADMIN', 'admin'],
        'semiAdmin' => [6, 'SEMI-ADMIN', 'semi-admin'],
        'tester' => [5, 'TESTER', 'tester'],
        'moderator' => [3, 'MOD', 'moderator'],
        'user' => [1, 'USER', 'user']
    ];
    // roles that may read the API documentation, and that see the private commands on cmd/
    const API_ROLES = ['dev', 'admin', 'semiAdmin', 'tester'];
    const PRIVATE_COMMAND_ROLES = ['dev', 'admin'];
    const BOT_ROLES_URL = 'https://api.sanakan.pl/api/User/discord/%s/permissions';
    const BOT_ROLES_TTL = 600;
    const BOT_ROLES_KEEP = 200;

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

    // $who names who did it when no account did, e.g. a cron job
    function addHistory($action, $text, $who = null)
    {
        if (!is_dir(dataDir()))
            @mkdir(dataDir(), 0750, true);

        $user = $who === null ? siteUser() : ['id' => '', 'name' => $who];
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

    // in PANEL_ADMINS, whatever rights the account is trying meanwhile
    function realPanelAdminId($id)
    {
        $config = configList('PANEL_ADMINS');

        return $config !== true && in_array((string)$id, $config, true);
    }

    // The rights below are the ones the account is trying (testRights()) when
    // it is, for its own account only, and its real ones otherwise.

    function isPanelAdminId($id)
    {
        $test = testRights($id);

        return isset($test['panel']) ? $test['panel'] : realPanelAdminId($id);
    }

    function isGalleryAdminId($id)
    {
        $test = testRights($id);

        return isset($test['gallery']) ? $test['gallery'] === 'admin' : inAccessList('galleryAdmins', $id, false);
    }

    function canViewGalleryId($id)
    {
        $test = testRights($id);
        if (isset($test['gallery']))
            return in_array($test['gallery'], ['viewer', 'viewer-uploader', 'admin'], true);

        return isGalleryAdminId($id) || inAccessList('galleryViewers', $id, true);
    }

    // a folder of its own in the gallery (inc/gallery.php); never everyone
    function isGalleryUploaderId($id)
    {
        $test = testRights($id);

        return isset($test['gallery']) ? in_array($test['gallery'], ['uploader', 'viewer-uploader'], true) : inAccessList('galleryUploaders', $id, false);
    }

    // a role counts as the bot said it last; siteRoles() asks again for the logged-in account
    function canViewApiId($id)
    {
        $test = testRights($id);

        return isPanelAdminId($id) || (isset($test['api']) ? $test['api'] : inAccessList('apiViewers', $id, true)) || hasBotRole($id, API_ROLES);
    }

    // ---- Trying other rights ------------------------------------------------------
    // A panel admin can take other rights for a while and see the live site as
    // an account with them does: with or without the panel, as anyone in the
    // gallery, with or without the API list, with any role on the bot's server
    // (which decides the API and the private commands). Kept in its session,
    // for its own account only, until TEST_MINUTES are over or it goes back with
    // the bar every page shows meanwhile (accountMenuHtml(), account.php).
    // Its real place in PANEL_ADMINS decides whether it may; nothing else changes.

    const TEST_MINUTES = [15, 60, 240];
    const TEST_GALLERY = [
        'none' => 'bez dostępu',
        'viewer' => 'oglądający',
        'uploader' => 'tylko własny folder',
        'viewer-uploader' => 'oglądający z własnym folderem',
        'admin' => 'admin galerii'
    ];
    // a role on the bot's server, or none there at all
    const TEST_ROLE_OUT = 'out';

    // ['panel' => bool, 'gallery' => key of TEST_GALLERY, 'api' => bool, 'role'
    // => key of BOT_ROLES or TEST_ROLE_OUT, 'until' => time; each one null for
    // the real one] the logged-in account is trying, or null; with $id only
    // when that is its account. $reset reads the session again.
    function testRights($id = null, $reset = false)
    {
        static $rights = false;
        if ($rights === false || $reset) {
            $rights = null;
            if (sessionExists()) {
                siteSession();
                $test = $_SESSION['test_rights'] ?? null;
                $owner = (string)($_SESSION['gallery_user']['id'] ?? '');
                if (is_array($test) && $owner !== '' && ($test['until'] ?? 0) > time() && realPanelAdminId($owner))
                    $rights = $test + ['id' => $owner];
            }
        }
        if ($rights === null || ($id !== null && (string)$id !== $rights['id']))
            return null;

        return $rights;
    }

    // starts trying $rights (as testRights() gives them) for $minutes, or with null stops
    function setTestRights($rights, $minutes = 0)
    {
        siteSession();
        if ($rights === null)
            unset($_SESSION['test_rights']);
        else
            $_SESSION['test_rights'] = $rights + ['until' => time() + $minutes * 60];
        testRights(null, true);
    }

    // what is being tried, e.g. "panel: nie · galeria: oglądający"
    function testRightsText($test)
    {
        $parts = [];
        if (isset($test['panel']))
            $parts[] = 'panel: ' . ($test['panel'] ? 'tak' : 'nie');
        if (isset($test['gallery']))
            $parts[] = 'galeria: ' . TEST_GALLERY[$test['gallery']];
        if (isset($test['api']))
            $parts[] = 'lista API: ' . ($test['api'] ? 'tak' : 'nie');
        if (isset($test['role']))
            $parts[] = 'rola: ' . ($test['role'] === TEST_ROLE_OUT ? 'poza serwerem' : BOT_ROLES[$test['role']][2]);

        return $parts ? implode(' · ', $parts) : 'prawdziwe uprawnienia';
    }

    function canSeePrivateCommandsId($id)
    {
        return isPanelAdminId($id) || hasBotRole($id, PRIVATE_COMMAND_ROLES);
    }

    function apiCanView()
    {
        $user = siteUser();
        siteRoles();

        return $user !== null && canViewApiId($user['id']);
    }

    // ---- Roles on the bot's Discord server ---------------------------------------
    // The bot API says what an account is on its server. The answers are kept in
    // inc/data/roles.json as [id => ['roles' => [...], 'checked' => time,
    // 'tried' => time]], so the panel can show them for the recent logins too; the
    // logged-in account is asked again when its answer is older than BOT_ROLES_TTL.

    // the site's key to the bot API (x-app-key), or '' without one
    function botAppKey()
    {
        authConfigured();

        return defined('BOT_APP_KEY') ? (string)BOT_APP_KEY : '';
    }

    // GET from the bot API with the site's key: the answer as an array, or null
    function botAppGet($url, $timeout = 4)
    {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nx-app-key: " . botAppKey()
        ]]);
        $json = @file_get_contents($url, false, $context);

        $status = 0;
        foreach ($http_response_header ?? [] as $line)
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $match))
                $status = (int)$match[1];
        $data = $json === false || $status !== 200 ? null : json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    // The flags of an account (onGuild and the keys of BOT_ROLES), or null when
    // the bot was never asked; $refresh asks it again when the answer is old.
    function botRoles($id, $refresh = false)
    {
        // a role being tried: only that one, on the server or off it
        $test = testRights($id);
        if (isset($test['role'])) {
            $roles = ['onGuild' => $test['role'] !== TEST_ROLE_OUT];
            foreach (array_keys(BOT_ROLES) as $role)
                $roles[$role] = $role === $test['role'];
            return $roles;
        }

        $id = (string)$id;
        $entry = readData('roles')[$id] ?? null;
        $age = time() - max($entry['checked'] ?? 0, $entry['tried'] ?? 0);

        if ($refresh && $age >= BOT_ROLES_TTL && botAppKey() !== '' && preg_match('/^\d{17,20}$/', $id)) {
            $answer = botAppGet(sprintf(BOT_ROLES_URL, $id));
            if ($answer !== null) {
                $roles = ['onGuild' => !empty($answer['onGuild'])];
                foreach (array_keys(BOT_ROLES) as $role)
                    $roles[$role] = !empty($answer[$role]);
                $entry = ['roles' => $roles, 'checked' => time()];
            } else {
                // the bot does not answer: the last roles stay, asked again after BOT_ROLES_TTL
                $entry = ['tried' => time()] + ($entry ?? []);
            }

            // read again, another request may have written meanwhile
            $known = readData('roles');
            $known[$id] = $entry;
            uasort($known, function ($a, $b) {
                return max($b['checked'] ?? 0, $b['tried'] ?? 0) <=> max($a['checked'] ?? 0, $a['tried'] ?? 0);
            });
            writeData('roles', array_slice($known, 0, BOT_ROLES_KEEP, true));
        }

        return $entry['roles'] ?? null;
    }

    // roles of the logged-in account, asked again when old, or null
    function siteRoles()
    {
        $user = siteUser();

        return $user === null ? null : botRoles($user['id'], true);
    }

    function hasBotRole($id, $roles)
    {
        $known = botRoles($id);
        foreach ($roles as $role)
            if (!empty($known[$role]))
                return true;

        return false;
    }

    // the highest role as ['key', 'level', 'label', 'name', 'title'], or null when unknown
    function roleBadge($roles)
    {
        if ($roles === null)
            return null;

        foreach (BOT_ROLES as $key => [$level, $label, $name])
            if (!empty($roles[$key]))
                return ['key' => $key, 'level' => $level, 'label' => $label, 'name' => $name, 'title' => 'Rola na serwerze Sanakana: ' . $name];

        return empty($roles['onGuild'])
            ? ['key' => 'out', 'level' => 0, 'label' => 'POZA SERWEREM', 'name' => 'poza serwerem', 'title' => 'Tego konta nie ma na serwerze Sanakana']
            : ['key' => 'none', 'level' => 0, 'label' => 'BEZ ROLI', 'name' => 'bez roli', 'title' => 'Konto nie ma roli na serwerze Sanakana'];
    }

    // every role of an account, highest first, by its name in the panel
    function roleNames($roles)
    {
        $names = [];
        foreach (BOT_ROLES as $key => $role)
            if (!empty($roles[$key]))
                $names[] = $role[2];

        return $names;
    }

    // the level next to the account name, "LV.9" in the colour of the role,
    // which its title names; the menu below shows the role in full
    function roleBadgeHtml($roles)
    {
        $badge = roleBadge($roles);
        if ($badge === null)
            return '';

        return '<span class="lv role-' . $badge['key'] . '" title="' . htmlspecialchars($badge['title'], ENT_QUOTES, 'UTF-8') . '">'
            . 'LV.' . $badge['level'] . '</span>';
    }

    // ---- The account in the corner of every page ----------------------------------

    const ACCOUNT_ICONS = [
        'profile' => '<circle cx="12" cy="8" r="4" /><path d="M4 20a8 8 0 0 1 16 0" />',
        'gallery' => '<rect x="3" y="4" width="18" height="16" rx="1" /><circle cx="9" cy="10" r="2" /><path d="m21 16-5-5-9 9" />',
        'panel' => '<path d="M4 6h16M4 12h10M4 18h16" />',
        'api' => '<path d="m8 8-4 4 4 4M16 8l4 4-4 4" />',
        'logout' => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10" />',
        'caret' => '<path d="m6 9 6 6 6-6" />'
    ];

    // A path on this site to go back to, e.g. "/i/?p=x", or the site root
    function localPath($path)
    {
        $path = (string)$path;

        return preg_match('~^/(?![/\\\\])[^\r\n]*$~', $path) ? $path : siteRoot();
    }

    // The logged-in account for the top right corner: its avatar ringed in the
    // colour of its role, the name and the level. A click (js/account.js) opens
    // a menu with its profile, the places it may open, none it may not, and logging out,
    // which comes back to $back. Below them a dim line with the role and the ID.
    function accountMenuHtml($user, $roles, $back)
    {
        $text = function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
        $icon = function ($name) { return '<svg class="account-icon" viewBox="0 0 24 24" aria-hidden="true">' . ACCOUNT_ICONS[$name] . '</svg>'; };
        $root = siteRoot();
        $badge = roleBadge($roles);
        $here = strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?');

        // the account's own profile for everyone, the rest only where it may go
        $places = [['profile', 'Profil', 'account/']];
        // also for an account that sees only a folder of its own, where i/ opens it
        if (canViewGalleryId($user['id']) || isGalleryUploaderId($user['id']))
            $places[] = ['gallery', 'Galeria', 'i/'];
        if (isPanelAdminId($user['id']))
            $places[] = ['panel', 'Panel', 'admin/'];

        $html = '<div class="account-menu' . ($badge ? ' role-' . $badge['key'] : '') . '">'
            . '<button type="button" class="account-toggle" aria-expanded="false" aria-haspopup="true">'
            . '<img src="' . $text($user['avatar']) . '" alt="" width="28" height="28" />'
            . '<span class="account-name">' . $text($user['name']) . '</span>' . roleBadgeHtml($roles) . $icon('caret')
            . '</button><div class="account-drop hud-corners" hidden>';
        foreach ($places as [$key, $label, $path])
            $html .= '<a href="' . $text($root . $path) . '"' . (strpos($here, $root . $path) === 0 ? ' aria-current="page"' : '') . '>'
                . $icon($key) . $label . '<small>' . $path . '</small></a>';
        $html .= '<form method="post" action="' . $text($root . 'account.php') . '">'
            . '<input type="hidden" name="csrf" value="' . $text(siteCsrf()) . '" />'
            . '<input type="hidden" name="action" value="logout" />'
            . '<input type="hidden" name="back" value="' . $text(localPath($back)) . '" />'
            . '<button type="submit" class="account-out">' . $icon('logout') . 'Wyloguj</button></form>'
            . '<div class="account-scan">' . ($badge ? '<em>LV.' . $badge['level'] . ' ' . $badge['label'] . '</em> · ' : '') . 'ID ' . $text($user['id']) . '</div>'
            . '</div></div>';

        // other rights being tried: a bar at the bottom of every page, with the way back
        $test = testRights($user['id']);
        if ($test !== null)
            $html .= '<div class="test-bar" role="status"><span><b>Podgląd z innymi uprawnieniami</b> ' . $text(testRightsText($test))
                . ' · do ' . date('H:i', $test['until']) . '</span>'
                . '<form method="post" action="' . $text($root . 'account.php') . '">'
                . '<input type="hidden" name="csrf" value="' . $text(siteCsrf()) . '" />'
                . '<input type="hidden" name="action" value="test-off" />'
                . '<input type="hidden" name="back" value="' . $text(localPath($back)) . '" />'
                . '<button type="submit">Wróć do swoich</button></form></div>';

        return $html;
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

        // logged in before "log out everyone", or before this account was logged out, in the panel
        if (($_SESSION['login_time'] ?? 0) < max(sessionsValidSince(), accountSessionsSince($user['id']))) {
            unset($_SESSION['gallery_user']);
            $_SESSION['gallery_flash'] = 'Sesja wygasła, zaloguj się jeszcze raz.';
            return null;
        }

        noteAccountAddress($user['id']);

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

    // sessions of this account started before this time are not valid any more
    function accountSessionsSince($id)
    {
        static $accounts = null;
        if ($accounts === null)
            $accounts = readData('sessions')['accounts'] ?? [];

        return (int)($accounts[(string)$id] ?? 0);
    }

    // ---- Addresses of the accounts ------------------------------------------------
    // Which addresses a logged-in account comes from, so the panel can tell whose
    // an address in the traffic is and never blocks its own admins. Kept in
    // inc/data/addresses.json as [id => [ip => [first, last, times, country, user agent]]].

    // Opens a file of inc/data locked, gives its data to $change and writes back
    // what that returns, so two requests at once do not lose a change.
    function updateDataFile($name, $change)
    {
        if (!is_dir(dataDir()) && !@mkdir(dataDir(), 0750, true))
            return;
        $handle = @fopen(dataDir() . '/' . $name, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if ($handle !== false)
                fclose($handle);
            return;
        }
        $data = json_decode((string)stream_get_contents($handle), true);
        $data = $change(is_array($data) ? $data : []);
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    // Notes the address of the logged-in account, and the session as one of its
    // devices, at most every ADDRESS_NOTE_EVERY seconds per session and address.
    function noteAccountAddress($id)
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $seen = $_SESSION['address_seen'] ?? null;
        if ($ip === '' || (is_array($seen) && $seen[0] === $ip && time() - $seen[1] < ADDRESS_NOTE_EVERY))
            return;
        $_SESSION['address_seen'] = [$ip, time()];

        $now = time();
        $id = (string)$id;
        $cc = cutText((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''), 2);
        $agent = cutText((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 160);

        updateDataFile('addresses.json', function ($data) use ($id, $ip, $now, $cc, $agent) {
            $known = $data[$id][$ip] ?? [$now, $now, 0, '', ''];
            $data[$id][$ip] = [$known[0], $now, $known[2] + 1, $cc !== '' ? $cc : $known[3], $agent !== '' ? $agent : $known[4]];

            // only the recent ones, the newest first
            foreach ($data as $account => $addresses) {
                $addresses = array_filter($addresses, function ($address) use ($now) { return $address[1] >= $now - ADDRESS_KEEP_DAYS * 86400; });
                uasort($addresses, function ($a, $b) { return $b[1] <=> $a[1]; });
                $data[$account] = array_slice($addresses, 0, ADDRESS_PER_ACCOUNT, true);
                if (!$data[$account])
                    unset($data[$account]);
            }

            return $data;
        });

        // [id => [session key => [logged in, last seen, ip, country, user agent]]]
        $key = sessionKey(session_id());
        $login = (int)($_SESSION['login_time'] ?? $now);
        updateDataFile('devices.json', function ($data) use ($id, $key, $login, $ip, $now, $cc, $agent) {
            $data[$id][$key] = [$login, $now, $ip, $cc, $agent];
            foreach ($data as $account => $devices) {
                $devices = array_filter($devices, function ($device) use ($now) { return $device[1] >= $now - SESSION_DAYS * 86400; });
                uasort($devices, function ($a, $b) { return $b[1] <=> $a[1]; });
                $data[$account] = array_slice($devices, 0, DEVICES_PER_ACCOUNT, true);
                if (!$data[$account])
                    unset($data[$account]);
            }

            return $data;
        });
    }

    // A session in the list of devices: a hash of its ID, never the ID itself,
    // which would let anyone holding inc/data (a backup) log in as that account.
    function sessionKey($sessionId)
    {
        return substr(hash('sha256', (string)$sessionId), 0, 20);
    }

    // The devices an account is still logged in on, the latest first, as
    // [key => [logged in, last seen, ip, country, user agent]]: their session is
    // still on the server and none of the panel's "log out" came after the login.
    function accountDevices($id)
    {
        $devices = readData('devices')[(string)$id] ?? [];
        $files = sessionFiles();
        $since = max(sessionsValidSince(), accountSessionsSince($id));

        return array_filter($devices, function ($device, $key) use ($files, $since) {
            return isset($files[$key]) && $device[0] >= $since;
        }, ARRAY_FILTER_USE_BOTH);
    }

    // the session files of the site by their key: [key => path]
    function sessionFiles()
    {
        $files = [];
        foreach (glob(dataDir() . '/sessions/sess_*') ?: [] as $file)
            $files[sessionKey(substr(basename($file), 5))] = $file;

        return $files;
    }

    // "Firefox 130 · Windows" from a user agent
    function deviceName($agent)
    {
        $browser = 'przeglądarka';
        foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'Firefox' => 'Firefox', 'Chrome' => 'Chrome', 'Version' => 'Safari'] as $token => $name)
            if (preg_match('~' . $token . '/(\d+)~', $agent, $match)) {
                $browser = $name . ' ' . $match[1];
                break;
            }
        $system = '';
        foreach (['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows', 'Mac OS X' => 'macOS', 'Linux' => 'Linux'] as $token => $name)
            if (strpos($agent, $token) !== false) {
                $system = $name;
                break;
            }

        return $agent === '' ? 'nieznane urządzenie' : $browser . ($system !== '' ? ' · ' . $system : '');
    }

    // ends one session of an account, by its key: whether there was one
    function endDevice($id, $key)
    {
        $file = sessionFiles()[$key] ?? null;
        $ended = $file !== null && @unlink($file);
        updateDataFile('devices.json', function ($data) use ($id, $key) {
            unset($data[(string)$id][$key]);
            if (empty($data[(string)$id]))
                unset($data[(string)$id]);

            return $data;
        });

        return $ended;
    }

    // The two addresses are one: the same IPv4 address, or the same /64 of IPv6,
    // which one machine usually has to itself (and Cloudflare blocks whole).
    function sameAddress($a, $b)
    {
        $a = @inet_pton((string)$a);
        $b = @inet_pton((string)$b);
        if ($a === false || $b === false || strlen($a) !== strlen($b))
            return false;

        return strlen($a) === 4 ? $a === $b : substr($a, 0, 8) === substr($b, 0, 8);
    }

    // the accounts that came from this address (or its /64), as [id => [ip, last seen]]
    function accountsAtAddress($ip, $addresses)
    {
        $accounts = [];
        foreach ($addresses as $id => $known)
            foreach ($known as $address => $info)
                if (sameAddress($ip, $address) && ($info[1] > ($accounts[(string)$id][1] ?? 0)))
                    $accounts[(string)$id] = [(string)$address, $info[1]];

        return $accounts;
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
        // off the list of its devices
        if (isset($_SESSION['gallery_user']['id']))
            updateDataFile('devices.json', function ($data) {
                unset($data[(string)$_SESSION['gallery_user']['id']][sessionKey(session_id())]);

                return $data;
            });
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
        // a new session: its address and device are noted at once
        unset($_SESSION['address_seen']);
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
