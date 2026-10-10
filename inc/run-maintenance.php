<?php
    // The regular cleanup of the gallery's data (inc/maintenance.php): operations
    // cut short and the trash past its days. Run by its own cron job every 10
    // minutes, as the web server's user:
    //   */10 * * * * www-data php /var/www/html/inc/run-maintenance.php > /dev/null 2>&1
    // By hand: --dry-run says what it would do and changes nothing; --inventory
    // lists what the data holds that the limits and checks would refuse or
    // report (broken JSON, names, accounts over their limits, big pictures, the
    // trash), changing nothing, for before and after an update. Only from the
    // command line; inc/ is also blocked from the web in nginx.
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }

    require __DIR__ . '/maintenance.php';

    if (in_array('--inventory', $argv, true)) {
        $labels = ['json' => 'Pliki JSON', 'names' => 'Nazwy nie w UTF-8', 'accounts' => 'Foldery kont ponad limity',
            'pictures' => 'Obrazy ponad limity', 'trash' => 'Kosz', 'operations' => 'Przerwane operacje'];
        foreach (maintenanceInventory(str_replace('\\', '/', dirname(__DIR__)) . '/i') as $section => $lines) {
            echo $labels[$section], ': ', $lines ? '' : 'nic', "\n";
            foreach ($lines as $line)
                echo '  ', $line, "\n";
        }
        exit(0);
    }

    $dryRun = in_array('--dry-run', $argv, true);
    $report = maintenanceRun(time(), $dryRun);
    if (!empty($report['busy'])) {
        echo "Poprzedni przebieg jeszcze trwa.\n";
        exit(0);
    }

    echo ($dryRun ? 'Bez zmian (--dry-run): ' : ''),
        'dokończone operacje ', $report['recovered'],
        ', usunięte z kosza ', $report['removed'], ' (', formatSize($report['bytes']), ')',
        ', zaległe ', $report['backlog'], "\n";
    foreach ($report['problems'] as $problem)
        echo '  ', $problem, "\n";
    exit($report['problems'] ? 1 : 0);
