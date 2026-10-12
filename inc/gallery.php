<?php
    // Picture gallery in i/ (used by i/index.php): safe paths inside the folder,
    // thumbnails, who may view and manage it, and adding, moving and deleting
    // files. The Discord login is in inc/auth.php, shared with the admin panel.
    //
    // Besides the viewers and the admins, an account can get a folder of its
    // own, i/users/<id>-<nick> (every account with a role on the bot's server,
    // user and up, but the ones GALLERY_UPLOADERS blocks), made the first time
    // it opens the gallery, and without other access see only that one: it adds pictures there,
    // saved as WebP when that is smaller, and renames, moves between its own folders, turns and deletes them, up to a number
    // of files set per account in the panel (USER_FILES_DEFAULT without one),
    // USER_FILE_MAX_BYTES each and USER_TOTAL_MAX_BYTES in all. Nobody but the
    // gallery admins sees the folders of others. In links such a folder is
    // u/<token>, a random name, so the account's ID stays out of them; its
    // files are given out by PHP for those links, and nginx gives nothing from
    // i/users/ straight from the disk. A gallery admin can also share
    // a folder by a link that works without a login, for some days or until it
    // is taken back in the panel.
    //
    // Thumbnails are made with GD and cached. Without GD small pictures are
    // shown as they are, and big ones (some GIFs have tens of MB) get a
    // placeholder and load only when opened.
    //
    // The code is in inc/gallery/, one file for each part; this file loads them
    // all, after the login they rely on. Loading them only defines things:
    // nothing is sent, read or run.
    require_once __DIR__ . '/auth.php';

    require_once __DIR__ . '/gallery/config.php';
    require_once __DIR__ . '/gallery/locking.php';
    require_once __DIR__ . '/gallery/quotas.php';
    require_once __DIR__ . '/gallery/conversions.php';
    require_once __DIR__ . '/gallery/paths.php';
    require_once __DIR__ . '/gallery/limits.php';
    require_once __DIR__ . '/gallery/media.php';
    require_once __DIR__ . '/gallery/metadata.php';
    require_once __DIR__ . '/gallery/thumbnails.php';
    require_once __DIR__ . '/gallery/listing.php';
    require_once __DIR__ . '/gallery/access.php';
    require_once __DIR__ . '/gallery/accounts.php';
    require_once __DIR__ . '/gallery/send.php';
    require_once __DIR__ . '/gallery/shares.php';
    require_once __DIR__ . '/gallery/export.php';
    require_once __DIR__ . '/gallery/moved.php';
    require_once __DIR__ . '/gallery/previews.php';
    require_once __DIR__ . '/gallery/jobs.php';
    require_once __DIR__ . '/gallery/trash.php';
    require_once __DIR__ . '/gallery/operations.php';
    require_once __DIR__ . '/gallery/uploads.php';
