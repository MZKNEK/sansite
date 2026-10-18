<?php
    // The gallery's settings: sizes, kinds of files, times and the limits of
    // the folders of the accounts. Part of the gallery, loaded by inc/gallery.php.

    const THUMB_SIZE = 360;

    // how long a thumbnail waits for the one being made before it asks to be
    // asked again (503), in seconds; waiting holds a PHP-FPM worker
    const THUMB_WAIT = 5;

    const THUMBLESS_MAX_BYTES = 1500000;

    const IMAGE_TYPES = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif'];

    const VIDEO_TYPES = ['webm', 'mp4'];

    const MEDIA_MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif', 'webm' => 'video/webm', 'mp4' => 'video/mp4'];

    // what "change to WebP" applies to; GIFs go through gif2webp to stay animated
    const WEBP_SOURCE_TYPES = ['png', 'jpg', 'jpeg', 'gif'];

    // an MP4 becomes WebM under the same rule as WebP: only when it is smaller
    const VIDEO_SOURCE_TYPES = ['mp4'];

    // formats a browser cannot be counted on to show: a HEIC or HEIF is always
    // written as WebP on upload (an AVIF follows the WebP rule instead)
    const CONVERT_IMAGE_TYPES = ['heic', 'heif'];

    // The quality cwebp writes a picture at when the page asks for none, per
    // kind; the panel may change them (inc/data/settings.json), these are the
    // defaults. A JPEG or PNG goes through cwebp itself, so its colours stay.
    const WEBP_QUALITY_PNG = 95;

    const WEBP_QUALITY_JPG = 80;

    const GIF_WEBP_QUALITY = 75;

    const AVIF_WEBP_QUALITY = 90;

    const TOOL_TIMEOUT = 50;

    // A picture or film is uploaded first and changed afterwards on the server
    // (inc/data/media-jobs.json, run by inc/media-worker.php started by the
    // upload, by inc/check-bot.php from cron or by hand), so the upload does
    // not wait. One film may
    // take MEDIA_JOB_TIMEOUT per encoder; a job left "converting" for longer
    // than MEDIA_JOB_STALE (a restart) is tried again, done ones are kept
    // MEDIA_JOB_KEEP_DAYS and the gallery shows them for MEDIA_JOB_DONE_SHOW
    // (5 minutes) after they finish
    const MEDIA_JOB_TIMEOUT = 600;

    const MEDIA_JOB_STALE = 1800;

    const MEDIA_JOB_KEEP_DAYS = 7;

    const MEDIA_JOB_DONE_SHOW = 300;

    const TRASH_DAYS = 30;

    const THUMB_KEEP_DAYS = 30;

    const SEARCH_LIMIT = 300;

    const ZIP_MAX_BYTES = 1073741824;

    // goes up when what browsers may keep of the thumbnails changes (thumbQuery())
    const THUMB_URL_VERSION = 2;

    const ROTATE_TYPES = ['png', 'jpg', 'jpeg', 'webp'];

    const MAX_NAME_LENGTH = 150;

    // the folders of the accounts with one, right in the top folder, and what
    // links say instead: u/<the folder's token>
    const USERS_DIR = 'users';

    const USERS_URL = 'u';

    // the folder Skalpelator and USkalpelator (/skalpel/, /uskalpel/) save the
    // cards into, inside the folder of the account
    const CROPPER_DIR = 'Skalpelator';

    // the folder only the panel admins and the GALLERY_PRIVATE list see, and
    // whose files nginx never gives straight from the disk
    const PRIVATE_DIR = 'private';

    const USER_FILES_DEFAULT = 200;

    const USER_FILES_MAX = 10000;

    const USER_FILE_MAX_BYTES = 10485760;

    const USER_TOTAL_MAX_BYTES = 104857600;

    // what the trash may hold of what was deleted from the folder of one account
    // (hidden entries and the originals of conversions included), apart from
    // what the folder itself holds
    const USER_TRASH_MAX_BYTES = 52428800;

    // what a shared link can last, in days; 0 is until it is taken back
    const SHARE_DAYS = [1, 7, 30, 0];
