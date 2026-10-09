<?php
    // Checks the bot once, without the one-minute cache, every 5 minutes the
    // other Sanakan sites (inc/services.php) and once a day removes the thumbnails
    // nobody looks at any more (inc/gallery.php); it also changes the pictures and
    // films uploaded since the last run to WebP or WebM in the background. Run by
    // cron every minute, as the web server's user, so the history has no gaps also
    // when nobody visits:
    //   * * * * * www-data php /var/www/html/inc/check-bot.php > /dev/null 2>&1
    // Only from the command line; inc/ is also blocked from the web in nginx.
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }

    require __DIR__ . '/bot.php';
    require __DIR__ . '/verdiff.php';
    require __DIR__ . '/services.php';
    require __DIR__ . '/gallery.php';
    require __DIR__ . '/panel-stats.php';

    $state = botState(true);
    // the bot's changelog from its repository, asked for again every
    // VERDIFF_TTL (sooner while a new version has no section in it yet), so
    // state/wersje/ opens without waiting on the network
    verdiff();
    if (servicesDue())
        servicesCheckAll();
    $pruned = botFile('thumbs-pruned.txt');
    if (!is_file($pruned) || time() - filemtime($pruned) >= 86400) {
        pruneThumbs();
        botWriteFile($pruned, (string)time());
    }
    // the WebP results of a manual change that nobody accepted or rejected
    pruneWebpPreviews();
    // the panel warns when this stops coming; written before the changes
    // below, which may take a while, so a long conversion never looks like cron
    // having stopped (the next run finds the lock taken and comes back at once)
    botWriteFile(botFile('cron.txt'), (string)time());
    // uploaded pictures and films waiting as WebP or WebM (inc/gallery.php);
    // one run at a time
    $mediaBase = str_replace('\\', '/', dirname(__DIR__)) . '/i';
    // the panel's gallery and inc/data numbers, counted again once an hour here
    // so opening the panel never walks the whole gallery (inc/panel-stats.php)
    panelStats($mediaBase);
    runMediaJobs($mediaBase, 900);
    echo date('Y-m-d H:i:s'), ' ', $state['status'], ' ', $state['uptime'], "%\n";
