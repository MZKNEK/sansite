<?php
    // tests/test_seams.php runs this to check that an empty SITE_DATA_DIR does
    // not move the data folder: it defines the constant empty and prints
    // dataDir(). Not sent to the server (tests/ is export-ignore).

    define('SITE_DATA_DIR', '');
    require __DIR__ . '/../inc/auth.php';

    echo dataDir();
