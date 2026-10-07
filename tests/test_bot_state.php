<?php
    // inc/bot.php: the notice and the planned breaks, the outages, the history
    // and the health report.

    test('botInMaintenance uses the windows', function () {
        $windows = [['id' => 1, 'from' => 1000, 'to' => 2000]];
        assertTrue(botInMaintenance(1000, $windows), 'the start counts');
        assertTrue(botInMaintenance(1999, $windows));
        assertFalse(botInMaintenance(2000, $windows), 'the end does not');
        assertFalse(botInMaintenance(999, $windows));
        assertFalse(botInMaintenance(1500, []));
    });

    test('a notice and a planned break are saved, read and cleared', function () {
        $from = time() - 100;
        $to = time() + 100;
        botSaveNotice(['text' => 'Aktualizacja', 'to' => null,
            'maintenance' => ['from' => $from, 'to' => $to], 'by' => '999', 'set' => time()]);
        assertSame('Aktualizacja', botNotice()['text']);
        assertTrue(botInMaintenance(time()));
        assertContains('Przerwa techniczna', noticeText(botNotice()));
        assertSame(1, count(botMaintenanceWindows()));

        // clearing a break that is going on ends it now
        botSaveNotice(null);
        assertNull(botNotice());
        $windows = botMaintenanceWindows();
        assertSame(1, count($windows));
        assertTrue($windows[0]['to'] <= time(), 'the break was closed');
        assertFalse(botInMaintenance(time()));
    });

    test('botIncidents merges close outages and keeps a planned one apart', function () {
        $now = time();
        file_put_contents(botFile('incidents.json'), json_encode([[$now - 3600, $now - 3000], [$now - 2400, $now - 2000]]));
        $incidents = botIncidents($now - 86400);
        assertSame(1, count($incidents), '600 s apart is one outage');
        assertSame(2, $incidents[0][2], 'two merged');

        // a break around the first one makes it planned, so the two do not merge
        botSaveNotice(['text' => 'Przerwa', 'to' => null,
            'maintenance' => ['from' => $now - 3700, 'to' => $now - 2900], 'by' => '999', 'set' => $now]);
        file_put_contents(botFile('incidents.json'), json_encode([[$now - 3600, $now - 3000], [$now - 2000, $now - 1000]]));
        $incidents = botIncidents($now - 86400);
        assertSame(2, count($incidents));
        assertFalse($incidents[0][4], 'the newest is outside the break');
        assertTrue($incidents[1][4], 'the older one is the planned break');
    });

    test('botHistory parses and botRecordCheck appends', function () {
        $now = time();
        file_put_contents(botFile('status-history.txt'), implode("\n", [
            ($now - 100) . ' 1 40 -',
            ($now - 50) . ' 0',
            'a broken line',
            '',
        ]) . "\n");
        $history = botHistory();
        assertSame(2, count($history));
        assertSame([$now - 100, true, 40, null], $history[0]);
        assertSame([$now - 50, false, null, null], $history[1]);

        assertEquals(66.67, round(botRecordCheck(true, $now, 30, null), 2), '2 of 3 online');
    });

    test('botIssues reads the health report', function () {
        assertSame([], botIssues(['status' => 'ok']));
        assertSame(['bot zgłasza problemy'], botIssues(['status' => 'degraded']));
        $issues = botIssues([
            'status' => 'degraded',
            'database' => ['ok' => false],
            'shinden' => ['ok' => false],
            'discord' => ['latencyMs' => 1500],
            'commands' => ['rejected5min' => 2],
        ]);
        assertSame(4, count($issues));
        assertContains('baza danych', implode(' ', $issues));
    });

    test('botReadHealth picks the online state', function () {
        [$online, $ms] = botReadHealth(['status' => 'ok', 'discord' => ['state' => 'Connected', 'latencyMs' => 42]]);
        assertTrue($online);
        assertSame(42, $ms);

        [$down, $noMs] = botReadHealth(['status' => 'down', 'discord' => ['state' => 'Connected', 'latencyMs' => 42]]);
        assertFalse($down);
        assertNull($noMs);

        [$connecting] = botReadHealth(['status' => 'ok', 'discord' => ['state' => 'Connecting']]);
        assertFalse($connecting);
    });
