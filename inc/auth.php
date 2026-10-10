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
    //   GALLERY_UPLOADERS have the folder of their own in the gallery blocked,
    //                    which every other account with a role on the bot's
    //                    server has (true blocks it for all)
    //   API_VIEWERS      may read the API documentation (the panel admins always can)
    //   BOT_APP_KEY      the site's key to the bot API (x-app-key with Info rights)
    // With the key the bot also says the account's roles on its Discord server:
    // dev, admin, semi-admin and tester may read the API documentation, any
    // role (user and up) gives a folder of its own in the gallery and picks the
    // colour of the HUD. Seeing and managing the gallery never follows them.
    // The panel adds more gallery admins and viewers, blocked folders and API readers. An account
    // without access can ask for it; the requests wait in inc/data/requests.json. Those are kept in
    // inc/data/access.json, next to a list of recent logins; inc/ is not
    // reachable from the web.
    //
    // The code is in inc/auth/, one file for each part; this file loads them
    // all. Loading them only defines things; the one thing done here is the
    // time zone below.
    require_once __DIR__ . '/text.php';
    require_once __DIR__ . '/storage.php';

    // the times in the history and the panel follow Polish time, not the server's
    date_default_timezone_set('Europe/Warsaw');

    require_once __DIR__ . '/auth/config.php';
    require_once __DIR__ . '/auth/history.php';
    require_once __DIR__ . '/auth/permissions.php';
    require_once __DIR__ . '/auth/roles.php';
    require_once __DIR__ . '/auth/preferences.php';
    require_once __DIR__ . '/auth/menu.php';
    require_once __DIR__ . '/auth/requests.php';
    require_once __DIR__ . '/auth/session.php';
    require_once __DIR__ . '/auth/addresses.php';
    require_once __DIR__ . '/auth/oauth.php';
