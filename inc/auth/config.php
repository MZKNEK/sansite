<?php
    // The login's settings and its config: inc/config.php, the site's root,
    // the access lists and the roles of the bot's server. Part of the login,
    // loaded by inc/auth.php.

    const SITE_SESSION = 'sanakan_gallery';  // the gallery's old cookie name, so logins stay valid

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
        'galleryPrivate' => 'GALLERY_PRIVATE',
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

    const BOT_ROLES_TTL = 600;

    const BOT_ROLES_KEEP = 200;

    function authConfigured()
    {
        static $loaded = false;
        if (!$loaded) {
            $loaded = true;
            // inc/config.php, next to inc/auth.php (this file is inc/auth/config.php)
            $file = __DIR__ . '/../config.php';
            if (is_file($file))
                require $file;
        }

        return defined('DISCORD_CLIENT_ID') && DISCORD_CLIENT_ID !== ''
            && defined('DISCORD_CLIENT_SECRET') && defined('DISCORD_REDIRECT_URI');
    }

    // URL path of the site root, e.g. "/": the page's address (SCRIPT_NAME)
    // less the page's place in the site's folder, so a page at any depth
    // (admin/, state/wersje/) gets the same root. Without the page's file in
    // the site's folder, the page is taken to be one folder deep (i/, admin/).
    function siteRoot()
    {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/x/index.php'));
        $site = realpath(dirname(__DIR__, 2));
        $file = isset($_SERVER['SCRIPT_FILENAME']) ? realpath((string)$_SERVER['SCRIPT_FILENAME']) : false;
        if ($site !== false && $file !== false) {
            $site = str_replace('\\', '/', $site);
            $file = str_replace('\\', '/', $file);
            // "/state/wersje/index.php" of the page, when SCRIPT_NAME ends with it
            $inSite = strpos($file, $site . '/') === 0 ? substr($file, strlen($site)) : '';
            if ($inSite !== '' && substr($script, -strlen($inSite)) === $inSite)
                return substr($script, 0, -strlen($inSite)) . '/';
        }

        $root = str_replace('\\', '/', dirname(dirname($script)));

        return rtrim($root, '/') . '/';
    }
