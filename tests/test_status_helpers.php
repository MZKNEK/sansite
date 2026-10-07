<?php
    // inc/status-card.php: the small helpers the status page and the panel use.

    test('milliseconds and averageResponse', function () {
        assertSame('500 ms', milliseconds(500));
        assertSame('1,5 s', milliseconds(1500));
        assertSame(200, averageResponse([[1, true, 100, null], [2, true, 300, null]], 2));
        assertNull(averageResponse([[1, false, null, null]], 2));
        assertNull(averageResponse([], 2));
    });

    test('plannedIncident', function () {
        assertTrue(plannedIncident([1000, 2000, 1, 1000, true]));
        assertFalse(plannedIncident([1000, 2000, 1, 1000, false]));
    });

    test('ago', function () {
        $now = time();
        assertSame('przed chwilą', ago($now - 10));
        assertSame('2 min temu', ago($now - 120));
        assertSame('2 godz. temu', ago($now - 7200));
        $old = mktime(0, 0, 0, 1, 1, 2020);
        assertSame(date('d.m.Y H:i', $old), ago($old));
    });

    test('checksLastHour counts the recent ones', function () {
        $now = time();
        assertSame(2, checksLastHour([
            [$now - 10, true, null, null],
            [$now - 100, true, null, null],
            [$now - 4000, true, null, null],
        ]));
    });

    test('timelineLabel and partTitle', function () {
        assertSame('brak sprawdzeń', timelineLabel(['state' => null, 'checks' => 0, 'down' => 0, 'planned' => 0]));
        assertSame('działał', timelineLabel(['state' => 'ok', 'checks' => 4, 'down' => 0, 'planned' => 0]));
        assertContains('przerwa techniczna', timelineLabel(['state' => 'planned', 'checks' => 4, 'down' => 2, 'planned' => 2]));
        assertContains('bez odpowiedzi', timelineLabel(['state' => 'fail', 'checks' => 4, 'down' => 2, 'planned' => 0]));

        assertSame('7.10: brak sprawdzeń', partTitle('7.10', ['checks' => 0, 'up' => 0]));
        assertSame('7.10: 50% (2 sprawdzenia)', partTitle('7.10', ['checks' => 2, 'up' => 1]));
    });

    test('historyUptime', function () {
        $now = time();
        assertSame('50%', historyUptime([[$now - 10, true, null, null], [$now - 20, false, null, null]]));
        assertSame('–', historyUptime([]));
    });

    test('healthDetails boxes the report', function () {
        $html = healthDetails(['discord' => ['state' => 'Connected', 'latencyMs' => 42, 'guilds' => 3, 'members' => 100]], null);
        assertContains('Discord', $html);
        assertContains('połączony', $html);
        assertContains('42 ms', $html);

        $down = healthDetails(['discord' => ['state' => 'Disconnected'], 'database' => ['ok' => false]], null);
        assertContains('rozłączony', $down);
        assertContains('Baza danych', $down);
        assertContains('nie odpowiada', $down);
    });
