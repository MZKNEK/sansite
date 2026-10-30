<?php
    // One change asked of the gallery's page (handlePost(), which ends the
    // process with its answer), for tests/test_gallery_access.php: the account
    // $argv[1] is logged in, the gallery is the folder $argv[2] and the fields
    // of the POST are the JSON of $argv[3], the CSRF token added, and the
    // optional JSON of $argv[4] is $_FILES. The answer's
    // JSON is the output. The accounts are those of tests/test_access.php, and
    // 445 a gallery admin who may see the private folder too. Not sent to the
    // server (tests/ is export-ignore).

    define('SITE_DATA_DIR', getenv('SANAKAN_TEST_DATA'));
    define('DISCORD_CLIENT_ID', 'test');
    define('DISCORD_CLIENT_SECRET', 'test');
    define('DISCORD_REDIRECT_URI', 'https://example.test/i/');
    define('PANEL_ADMINS', ['999']);
    define('GALLERY_ADMINS', ['111', '445']);
    define('GALLERY_PRIVATE', ['444', '445']);
    require __DIR__ . '/bootstrap.php';

    $_SERVER['SCRIPT_NAME'] = '/i/index.php';
    $_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__) . '/i/index.php';
    $_SERVER['REQUEST_URI'] = '/i/';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    sessionFor('sanakan-post-' . $argv[1] . '-' . bin2hex(random_bytes(4)), ['id' => $argv[1], 'name' => 'Test', 'avatar' => 'a']);
    $_POST = ['csrf' => siteCsrf()] + json_decode($argv[3], true);
    $_FILES = isset($argv[4]) ? json_decode($argv[4], true) : [];
    handlePost($argv[2]);
