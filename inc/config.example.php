<?php
    // Copy this file to inc/config.php on the server and fill it in. That file
    // holds a secret, so it is not in git. Without it the gallery in i/ and the
    // admin panel stay closed (the pictures still work by their direct links).
    //
    // The same login guards the API documentation in api/.
    //
    // Discord application: https://discord.com/developers/applications
    // → your application → OAuth2. Copy the Client ID and Client Secret, and
    // add DISCORD_REDIRECT_URI below to the Redirects list there, exactly the same.
    const DISCORD_CLIENT_ID = '';
    const DISCORD_CLIENT_SECRET = '';
    const DISCORD_REDIRECT_URI = 'https://sanakan.pl/i/';

    // Discord accounts that may open the admin panel (admin/, linked from the home
    // page once logged in). Only here, the panel cannot change it.
    const PANEL_ADMINS = [
        // '123456789012345678',
    ];

    // Discord accounts that may view the gallery and add, move and delete pictures.
    // The admin panel can add more; this list is the fixed part.
    // User ID: Discord settings → Advanced → Developer Mode, then right click
    // the user → Copy User ID.
    const GALLERY_ADMINS = [
        // '123456789012345678',
    ];

    // Other accounts that may only view the gallery (the panel can add more);
    // true lets in anyone logged in with Discord. Without this line only the
    // admins see it.
    const GALLERY_VIEWERS = [
        // '234567890123456789',
    ];

    // Every account with a role on the bot's server, user and up (the gallery
    // admins too, so it stays theirs should they lose the rights; BOT_APP_KEY
    // below tells the roles) has a folder of its own in the gallery, i/users/<id>-<nick>, made the first time it
    // opens the gallery: without access to the rest it sees only that one and
    // adds pictures there, 50 by default (the panel sets it per account). The
    // accounts here have it blocked (the panel can block more); their folder
    // and files stay. true blocks it for everyone. Optional.
    const GALLERY_UPLOADERS = [
        // '345678901234567890',
    ];

    // Accounts that may see the private folder of the gallery, i/private (the
    // panel can add more). Administratorzy panelu widzą je zawsze. Optional.
    const GALLERY_PRIVATE = [
        // '456789012345678901',
    ];

    // Accounts that may read the API documentation in api/ (the panel can add
    // more, and PANEL_ADMINS always can); true lets in anyone logged in with Discord.
    const API_VIEWERS = [
        // '234567890123456789',
    ];

    // Key of this site's application in the bot API, sent as x-app-key; it needs
    // the Info right (Site covers it too). With it the site asks the bot for the
    // roles of the logged-in account on its Discord server: dev, admin,
    // semi-admin and tester may read the API documentation, admin and dev see
    // the moderator and debug commands on cmd/. Without it neither happens.
    const BOT_APP_KEY = '';

    // Secret the bot sends its report to alive/ with, every minute (Heartbeat
    // in the bot's Config.json: Url https://sanakan.pl/alive/, the same Secret).
    // While the reports come the site does not ask the bot API for its state,
    // and sees the bot also when the API cannot be reached. Without it alive/
    // answers 404 and the site asks the API every minute, as before.
    const BOT_HEARTBEAT_SECRET = '';

    // Optional: the changelog of the bot, verdiff.md in the root of its
    // repository, read by state/wersje/. Give the raw address of the file, e.g.
    // https://raw.githubusercontent.com/OWNER/REPO/main/verdiff.md. A heading per
    // version with a "Data:" and an optional "Commit:" line and the changes under
    // it. Without it the page still lists the versions the bot reported, but
    // shows no changes.
    // const BOT_REPO_VERDIFF_URL = '';

    // Optional: blocking addresses in Cloudflare from the panel (inc/cloudflare.php,
    // README). A token with Account Filter Lists: Edit, the account ID (domain
    // overview in Cloudflare, right column) and the name of the IP list that a
    // custom rule blocks: (ip.src in $sanakan_blokada).
    // const CLOUDFLARE_API_TOKEN = '';
    // const CLOUDFLARE_ACCOUNT_ID = '';
    // const CLOUDFLARE_LIST = 'sanakan_blokada';

    // Optional, for the availability checks of the panel (inc/diag.php); the
    // values below are the defaults. Where the server answers past Cloudflare
    // (https://127.0.0.1 when nginx serves the site only on https), the site's
    // nginx log and PHP-FPM's slow log.
    // const DIAG_LOCAL_ORIGIN = 'http://127.0.0.1';
    // const DIAG_ACCESS_LOG = '/var/log/nginx/sanakan-access.log';
    // const DIAG_SLOW_LOG = '/var/log/php8.1-fpm.slow.log';
