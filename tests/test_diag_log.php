<?php
    // inc/diag.php: the nginx log reading and the summaries it feeds, over a log
    // file pointed at the temporary data folder.

    if (!defined('DIAG_ACCESS_LOG')) define('DIAG_ACCESS_LOG', SITE_DATA_DIR . '/access.log');
    if (!defined('DIAG_SLOW_LOG')) define('DIAG_SLOW_LOG', SITE_DATA_DIR . '/slow.log');

    test('diagParseNginx and diagParseFpm', function () {
        $nginx = "Active connections: 2 \nserver accepts handled requests\n 10 9 15 \nReading: 0 Writing: 1 Waiting: 3 \n";
        $parsed = diagParseNginx($nginx);
        assertSame(2, $parsed['a']);
        assertSame(10, $parsed['acc']);
        assertSame(9, $parsed['hnd']);
        assertSame(0, $parsed['r']);
        assertSame(3, $parsed['k']);
        assertNull(diagParseNginx('nope'));

        $fpm = json_encode(['active processes' => 3, 'total processes' => 5, 'listen queue' => 1, 'max children reached' => 2, 'slow requests' => 4]);
        $parsed = diagParseFpm($fpm);
        assertSame(3, $parsed['act']);
        assertSame(5, $parsed['tot']);
        assertSame(1, $parsed['q']);
        assertNull(diagParseFpm('{}'));
    });

    test('diagReadLog reads the new lines and feeds the summaries', function () {
        file_put_contents(DIAG_ACCESS_LOG, '');
        diagReadLog();   // the first read jumps to the end of the log

        $now = time();
        $line = function ($ip, $uri, $status, $ut, $ua) {
            return json_encode(['t' => '1', 'ip' => $ip, 'm' => 'GET', 'u' => $uri, 's' => (string)$status, 'rt' => '0.100', 'ut' => $ut, 'ua' => $ua, 'cc' => 'PL']);
        };
        // an account's address and a scanner in the same minute
        writeData('addresses', ['111' => ['1.2.3.4' => [$now, $now, 1, 'PL', 'ua']]]);
        file_put_contents(DIAG_ACCESS_LOG, $line('1.2.3.4', '/i/', 200, '0.050', 'Mozilla') . "\n" . $line('5.6.7.8', '/.env', 404, '-', 'curl') . "\n", FILE_APPEND);

        $stats = diagReadLog();
        assertSame(2, $stats['n']);
        assertSame(1, $stats['php']);
        assertSame(1, $stats['s4']);
        assertSame('1.2.3.4', $stats['ips'][0][0]);

        assertTrue(isset(diagScanners()['5.6.7.8']), 'the scanner is remembered');
        assertContains('/i/', implode(' ', array_column(diagPages(), 0)), 'the PHP page time is kept');
        assertSame(1, diagAccountTraffic('111')['n'], 'the account traffic is kept');
    });

    test('diagRecentAddresses and diagPrune', function () {
        $now = time();
        $round = ['t' => $now, 'p' => [], 'log' => ['n' => 5, 'php' => 1, 's4' => 0, 's5' => 0, 'rt' => 10, 'paths' => [], 'ips' => [['9.9.9.9', 5, 1, 'US', 'ua', '/x']]]];
        file_put_contents(diagDayFile($now), json_encode($round) . "\n", FILE_APPEND);
        assertTrue(isset(diagRecentAddresses()['9.9.9.9']));

        $old = diagDayFile(time() - (DIAG_KEEP_DAYS + 2) * 86400);
        file_put_contents($old, "{}\n");
        assertTrue(is_file($old));
        diagPrune();
        assertFalse(is_file($old), 'a day past the keep window');
    });

    test('diagSlowEntries reads the slow log', function () {
        $entry1 = "[05-Oct-2026 10:00:00]  [pool www] pid 1\nscript_filename = /var/www/html/i/index.php\n[0x00007f] sendThumb() /var/www/html/inc/gallery.php:800";
        $entry2 = "[05-Oct-2026 10:01:00]  [pool www] pid 2\nscript_filename = /var/www/html/admin/index.php\n[0x00007f] systemProcesses() /var/www/html/inc/system.php:61";
        file_put_contents(DIAG_SLOW_LOG, $entry1 . "\n\n" . $entry2 . "\n");

        $entries = diagSlowEntries(2);
        assertSame(2, count($entries));
        assertContains('systemProcesses', $entries[0], 'the newest first');
        assertContains('sendThumb', $entries[1]);
    });
