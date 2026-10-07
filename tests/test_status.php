<?php
    // inc/status-card.php and inc/panel-stats.php: the status wording, the bars
    // and the panel's numbers.

    test('percent and partsUptime', function () {
        assertSame('50%', percent(1, 2));
        assertSame('33,3%', percent(1, 3));
        assertSame('–', partsUptime([['checks' => 0, 'up' => 0]]));
        assertSame('50%', partsUptime([['checks' => 10, 'up' => 5], ['checks' => 10, 'up' => 5]]));
    });

    test('partClass colours a day', function () {
        assertSame('none', partClass(['checks' => 0, 'up' => 0]));
        assertSame('ok', partClass(['checks' => 10, 'up' => 10]));
        assertSame('warn', partClass(['checks' => 100, 'up' => 96]));
        assertSame('fail', partClass(['checks' => 100, 'up' => 90]));
    });

    test('incidentTime formats a range', function () {
        $a = mktime(14, 5, 0, 10, 3, 2026);
        $b = mktime(14, 32, 0, 10, 3, 2026);
        assertSame('3.10 14:05 – 14:32', incidentTime([$a, $b]));
        assertSame('3.10 14:05 – trwa', incidentTime([$a, null]));
    });

    test('statusLabel online and offline', function () {
        assertSame('działa', statusLabel(['status' => 'online', 'uptime' => 100, 'issues' => []]));
        assertSame('nie odpowiada', statusLabel(['status' => 'offline', 'uptime' => 5, 'issues' => []]));
    });

    test('an idle bot says why', function () {
        assertSame('działa, ale Shinden nie odpowiada',
            statusLabel(['status' => 'idle', 'uptime' => 96.4, 'issues' => ['Shinden nie odpowiada']]));
        assertSame('działa, ale w ostatnich 24 h odpowiadał w 96,4%',
            statusLabel(['status' => 'idle', 'uptime' => 96.4, 'issues' => []]));
    });

    test('idleReason tells a single break from repeated ones', function () {
        $now = time();
        $state = ['status' => 'idle', 'uptime' => 96.4, 'issues' => []];

        file_put_contents(botFile('incidents.json'), json_encode([[$now - 3600, $now - 3000]]));
        assertContains('miał przerwę', idleReason($state));

        file_put_contents(botFile('incidents.json'), json_encode([[$now - 1200, $now - 300]]));
        assertContains('niedawno nie odpowiadał', idleReason($state));

        file_put_contents(botFile('incidents.json'), json_encode([[$now - 600, null]]));
        assertContains('nie odpowiada od', idleReason($state));

        file_put_contents(botFile('incidents.json'), json_encode([[$now - 9000, $now - 8800], [$now - 5000, $now - 4800]]));
        assertContains('bywał niedostępny', idleReason($state));
        @unlink(botFile('incidents.json'));
    });

    test('panelStatsFresh wants complete, recent stats', function () {
        $fresh = ['time' => time(), 'gallery' => [], 'data' => [], 'thumbs' => []];
        assertTrue(panelStatsFresh($fresh));
        assertFalse(panelStatsFresh(['time' => time() - PANEL_STATS_TTL - 1, 'gallery' => [], 'data' => [], 'thumbs' => []]));
        $missing = $fresh;
        unset($missing['data']);
        assertFalse(panelStatsFresh($missing));
        assertFalse(panelStatsFresh(null));
    });

    test('galleryStats counts files, bytes and types', function () {
        $base = tempDir();
        mkdir($base . '/sub');
        file_put_contents($base . '/a.webp', str_repeat('x', 100));
        file_put_contents($base . '/b.png', str_repeat('x', 50));
        file_put_contents($base . '/sub/c.webp', str_repeat('x', 30));
        file_put_contents($base . '/.hidden', 'x');
        $stats = galleryStats($base);
        assertSame(3, $stats['files']);
        assertSame(180, $stats['bytes']);
        assertSame(1, $stats['folders']['sub']['files']);
        assertSame(130, $stats['types']['webp']['bytes']);
        assertSame(1, $stats['types']['png']['files']);
    });

    test('panelStats counts and caches', function () {
        $base = tempDir();
        file_put_contents($base . '/a.webp', str_repeat('x', 100));
        $stats = panelStats($base, true);
        assertSame(1, $stats['gallery']['files']);
        assertSame(100, $stats['gallery']['bytes']);
        assertTrue(isset($stats['data']['bytes'], $stats['data']['trash'], $stats['data']['webp']));
        assertTrue(isset($stats['thumbs']['files'], $stats['thumbs']['bytes']));
        assertTrue(panelStatsFresh($stats));
        assertSame($stats['time'], panelStats($base)['time'], 'a fresh file is read, not counted again');
    });
