<?php
    // Syncs the pictures of the bot's cards (inc/pw.php) at once and whole,
    // without waiting for cron, which takes them 40 seconds a run: after the
    // first deploy, or with --force to ask GitHub again before the 6 hours:
    //   sudo -u www-data php /var/www/html/inc/pw-sync.php [--force]
    // Only from the command line; inc/ is also blocked from the web in nginx.
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }

    require __DIR__ . '/pw.php';

    if (in_array('--force', $argv, true)) {
        $state = readData('pw');
        $state['checked'] = 0;
        writeData('pw', $state);
    }
    $done = pwSync(time(), 600);
    $status = pwStatus();
    echo date('Y-m-d H:i:s'), ' pobrano: ', $done, ', plików: ', $status['files'], ', czeka: ', $status['pending'],
        $status['error'] ? ', błąd: ' . $status['error'] : '', "\n";
