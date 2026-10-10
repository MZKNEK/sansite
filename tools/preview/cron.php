<?php
    // The cron of the local preview (tools/preview.sh): runs the site's own
    // bot check, inc/check-bot.php, every minute with the preview's settings,
    // as cron does on the server, so the status keeps its history and the
    // pictures uploaded to the gallery are changed to WebP in the background,
    // and the cleanup of the trash, inc/run-maintenance.php, every 10 minutes
    // as its own cron job does. Stopped with the servers by tools/preview-stop.sh.
    $root = dirname(__DIR__, 2);
    $command = [PHP_BINARY];
    if (!extension_loaded('gd'))
        array_push($command, '-d', 'extension=gd');
    array_push($command, '-d', 'auto_prepend_file=' . __DIR__ . '/config.php');
    $jobs = [[$root . '/inc/check-bot.php', 60], [$root . '/inc/run-maintenance.php', 600]];

    while (true) {
        foreach ($jobs as [$script, $every])
            if (time() % $every < 60) {
                $process = proc_open(array_merge($command, [$script]), [], $pipes, $root);
                if (is_resource($process))
                    proc_close($process);
            }
        sleep(60 - time() % 60);
    }
