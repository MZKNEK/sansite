<?php
    // Who may do what, from the config and the panel's lists, and the panel's
    // preview with other rights. Part of the login, loaded by inc/auth.php.

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

    // a folder of its own in the gallery (inc/gallery.php): every account with
    // a role on the bot's server (user and up) has one, the gallery admins too
    // (it stays theirs should they lose the rights), except the ones on the
    // GALLERY_UPLOADERS list, which blocks it (true for everyone). An account
    // that loses its role, or is blocked, keeps the folder and its files on
    // the disk, only without getting in.
    function isGalleryUploaderId($id)
    {
        $test = testRights($id);
        if (isset($test['gallery']))
            return in_array($test['gallery'], ['uploader', 'viewer-uploader'], true);

        return !inAccessList('galleryUploaders', $id, true) && hasServerRole($id);
    }

    // the private folder of the gallery, i/private (inc/gallery.php): the panel
    // admins and the GALLERY_PRIVATE list; gallery admins and viewers do not get
    // it just for that
    function canSeePrivateGalleryId($id)
    {
        $test = testRights($id);
        if (isset($test['private']))
            return $test['private'];

        return isPanelAdminId($id) || inAccessList('galleryPrivate', $id, false);
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
    // gallery, with or without the private folder, with or without the API list,
    // with any role on the bot's server (which decides the API and the private
    // commands). Kept in its session,
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
        if (isset($test['private']))
            $parts[] = 'prywatna galeria: ' . ($test['private'] ? 'tak' : 'nie');
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
