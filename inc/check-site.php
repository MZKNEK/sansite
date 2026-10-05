<?php
    // One minute of availability checks of the site, a round every 10 seconds
    // (inc/diag.php), for the "Dostępność strony" card of the admin panel. Run
    // by cron every minute, as the web server's user:
    //   * * * * * www-data php /var/www/html/inc/check-site.php > /dev/null 2>&1
    // A run takes a minute and, when the site does not answer, up to half a
    // minute more; the next one starts meanwhile, which is fine.
    // Only from the command line; inc/ is also blocked from the web in nginx.
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }

    require __DIR__ . '/diag.php';

    if (!diagAvailable()) {
        fwrite(STDERR, "Brak rozszerzenia curl: apt-get install -y php8.1-curl\n");
        exit(1);
    }
    diagRun();
