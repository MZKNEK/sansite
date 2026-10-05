<?php
    // Checks the bot once, without the one-minute cache, every 5 minutes the
    // other Sanakan sites (inc/services.php) and once a day removes the thumbnails
    // nobody looks at any more (inc/gallery.php). Run by cron every minute, as the
    // web server's user, so the history has no gaps also when nobody visits:
    //   * * * * * www-data php /var/www/html/inc/check-bot.php > /dev/null 2>&1
    // Only from the command line; inc/ is also blocked from the web in nginx.
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }

    require __DIR__ . '/bot.php';
    require __DIR__ . '/services.php';
    require __DIR__ . '/gallery.php';

    $state = botState(true);
    if (servicesDue())
        servicesCheckAll();
    $pruned = botFile('thumbs-pruned.txt');
    if (!is_file($pruned) || time() - filemtime($pruned) >= 86400) {
        pruneThumbs();
        botWriteFile($pruned, (string)time());
    }
    // the panel warns when this stops coming
    botWriteFile(botFile('cron.txt'), (string)time());
    echo date('Y-m-d H:i:s'), ' ', $state['status'], ' ', $state['uptime'], "%\n";
