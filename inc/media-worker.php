<?php
    // Changes the uploaded pictures and films waiting as WebP or WebM
    // (inc/data/media-jobs.json). Normally inc/check-bot.php runs this every
    // minute from cron; this file is for running it by hand, e.g. after cron was
    // off:
    //   php /var/www/html/inc/media-worker.php
    // Only from the command line; inc/ is also blocked from the web in nginx.
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }

    require __DIR__ . '/gallery.php';

    $base = str_replace('\\', '/', dirname(__DIR__)) . '/i';
    $done = runMediaJobs($base);
    echo date('Y-m-d H:i:s'), ' zamieniono plików: ', $done, "\n";
