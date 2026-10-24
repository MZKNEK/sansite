<?php
    // One of the processes tests/test_concurrency.php starts. With "add" it adds
    // 1 to the counter in $argv[2] $argv[3] times through updateData(), so the
    // parent can check that processes at once lose no change; with "read" it
    // reads the file $argv[3] times while others change it and exits with 1 when
    // a read ever finds it not whole (no counter in it), with 2 when it could not
    // get the lock in time. Not sent to the server (tests/ is export-ignore).

    $dir = getenv('SANAKAN_TEST_DATA');
    if ($dir !== false && $dir !== '')
        define('SITE_DATA_DIR', $dir);
    require __DIR__ . '/../inc/auth.php';

    $mode = $argv[1] ?? 'add';
    $file = $argv[2] ?? 'concurrency';
    $times = (int)($argv[3] ?? 1);
    for ($i = 0; $i < $times; $i++) {
        if ($mode === 'read') {
            forgetData($file);
            try { $data = readData($file); }
            catch (DataStoreException $error) { exit(2); }
            if (!isset($data['n']))
                exit(1);
            continue;
        }
        updateData($file, function (&$data) {
            $data['n'] = ($data['n'] ?? 0) + 1;
        });
        // a request makes one change and ends; taking the lock again at once
        // would leave a reader polling every 20 ms no gap to get in (CI, 255)
        usleep(1000);
    }
