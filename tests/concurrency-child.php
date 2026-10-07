<?php
    // One of the processes tests/test_concurrency.php starts: it adds 1 to the
    // counter in $argv[1] $argv[2] times through updateDataFile(), so the parent
    // can check that two processes at once lose no change. Not sent to the
    // server (tests/ is export-ignore).

    $dir = getenv('SANAKAN_TEST_DATA');
    if ($dir !== false && $dir !== '')
        define('SITE_DATA_DIR', $dir);
    require __DIR__ . '/../inc/auth.php';

    $file = $argv[1] ?? 'concurrency.json';
    $times = (int)($argv[2] ?? 1);
    for ($i = 0; $i < $times; $i++)
        updateDataFile($file, function ($data) {
            $data['n'] = ($data['n'] ?? 0) + 1;
            return $data;
        });
