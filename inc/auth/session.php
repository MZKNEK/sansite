<?php
    // The session shared by every page: its cookie, the account in it, its
    // CSRF token and messages, logging out. Part of the login, loaded by
    // inc/auth.php.

    // ---- Session ------------------------------------------------------------------

    // Whether the visitor's browser talks https. Cloudflare asks the server over
    // https with its Origin Certificate (HTTPS set); in its Flexible mode it
    // asks over plain http, saying the visitor's scheme in CF-Visitor
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

        // logged in before "log out everyone", or before this account was logged
        // out; a session from before the generations (sessionGeneration()) by
        // the times it was logged in and logged out at
        $generation = sessionGeneration($user['id']);
        $revoked = isset($_SESSION['generation'])
            ? $_SESSION['generation'] !== $generation
            : ($_SESSION['login_time'] ?? 0) < max(sessionsValidSince(), accountSessionsSince($user['id']));
        // and a login lasts SESSION_DAYS from when Discord confirmed it, however
        // often the session is used meanwhile (PHP only clears a session file
        // left alone that long)
        $expired = ($_SESSION['login_time'] ?? 0) < time() - SESSION_DAYS * 86400;
        if ($revoked || $expired) {
            unset($_SESSION['gallery_user']);
            $_SESSION['gallery_flash'] = 'Sesja wygasła, zaloguj się jeszcze raz.';
            return null;
        }
        $_SESSION['generation'] = $generation;

        noteAccountAddress($user['id']);

        return $user;
    }

    // The generation of the logins a session belongs to, [everyone's, the
    // account's], kept in the session at the login: "log out everyone" raises
    // the first, logging an account out the second, and a session of an older
    // one ends. A count, not a time, so a login in the very second of a log
    // out ends too.
    function sessionGeneration($id)
    {
        $sessions = readData('sessions');

        return [(int)($sessions['generation'] ?? 0), (int)($sessions['generations'][(string)$id] ?? 0)];
    }

    // Ends the sessions of everyone ($id null) or of one account; with $keepOwn
    // the session asking stays (it takes the new generation), without its login
    // lasting any longer. The time is kept for the panel to show.
    function endSessions($id = null, $keepOwn = false)
    {
        $now = time();
        $own = (string)($_SESSION['gallery_user']['id'] ?? '');
        // the asking session's generation right after this change, not after
        // another log out made meanwhile
        $generation = null;
        $saved = updateData('sessions', function (&$sessions) use ($id, $now, $own, &$generation) {
            if ($id === null) {
                $sessions['since'] = $now;
                $sessions['generation'] = (int)($sessions['generation'] ?? 0) + 1;
            } else {
                $sessions['accounts'][(string)$id] = $now;
                $sessions['generations'][(string)$id] = (int)($sessions['generations'][(string)$id] ?? 0) + 1;
            }
            $generation = [(int)($sessions['generation'] ?? 0), (int)($sessions['generations'][$own] ?? 0)];
        });
        if ($saved && $keepOwn && $own !== '') {
            $_SESSION['generation'] = $generation;
            // noted again on the next page, with the new generation, or the list
            // of devices would drop this one with the others
            unset($_SESSION['address_seen']);
        }

        return $saved;
    }

    // sessions started before this time are not valid any more (the sessions
    // from before the generations; the panel shows it)
    function sessionsValidSince()
    {
        return (int)(readData('sessions')['since'] ?? 0);
    }

    // sessions of this account started before this time are not valid any more
    function accountSessionsSince($id)
    {
        return (int)(readData('sessions')['accounts'][(string)$id] ?? 0);
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
        forgetHudCookies();
    }

    // The HUD colour kept in cookies for the pages without an account menu
    // (account.php, js/appearance.js) goes with the account, so a logged-out browser
    // shows the site's own purple, like the pages that know the account; the
    // snow turned off goes too (js/safeguard.js), and the skin with its words
    // (js/skins.js)
    function forgetHudCookies()
    {
        foreach (['hud', 'hudrole', 'snow', 'skin', 'skinwords', 'customskin'] as $name)
            if (isset($_COOKIE[$name]))
                setcookie($name, '', time() - 3600, '/');
    }
