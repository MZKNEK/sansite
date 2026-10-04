<?php
    // Checks the bot once, without the one-minute cache, and every 5 minutes the
    // other Sanakan sites (inc/services.php). Run by cron every minute, as the
    // web server's user, so the history has no gaps also when nobody visits:
    //   * * * * * www-data php /var/www/html/inc/check-bot.php > /dev/null 2>&1
    // Only from the command line; inc/ is also blocked from the web in nginx.
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }

    require __DIR__ . '/bot.php';
    require __DIR__ . '/services.php';

    $state = botState(true);
    if (servicesDue())
        servicesCheckAll();
    // the panel warns when this stops coming
    botWriteFile(botFile('cron.txt'), (string)time());
    echo date('Y-m-d H:i:s'), ' ', $state['status'], ' ', $state['uptime'], "%\n";
