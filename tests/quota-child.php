<?php
    define('SITE_DATA_DIR', getenv('SANAKAN_TEST_DATA'));
    require __DIR__ . '/../inc/gallery.php';
    [$script, $mode, $base, $signal] = $argv;
    $id = '123456789012345678';
    $wait = function () use ($signal) {
        $deadline = microtime(true) + 10;
        while (!is_file($signal . '.go')) {
            clearstatcache();
            if (microtime(true) > $deadline) exit(2);
            usleep(10000);
        }
    };
    if ($mode === 'policy-reader') {
        $policy = galleryLock('policy', false);
        file_put_contents($signal . '.ready', '1');
        $wait();
        file_put_contents($signal . '.result', (string)accountLimits($id)['values']['files']);
        $policy();
    } elseif ($mode === 'policy-writer') {
        file_put_contents($signal . '.started', '1');
        saveGalleryLimits($id, ['files' => 1], 0, '999');
    } else {
        file_put_contents($signal . '.ready-' . getmypid(), '1');
        $wait();
        galleryWrite(function () use ($base, $id) {
            $policy = galleryLock('policy', false);
            $account = accountLock($id);
            try {
                if (accountQuotaError($base, $id, 1, 1, 1) === null)
                    file_put_contents($base . '/users/' . $id . '-U/' . getmypid() . '.bin', 'x');
            } finally { $account(); $policy(); }
        });
    }
