<?php
    // inc/bot.php: the stored state on disk: the command changes, the daily
    // counts, the incidents, the bot API, the dependencies and the heartbeat.

    test('botTrackCommands logs new, changed and removed', function () {
        $a = ['modules' => [['name' => 'M', 'subModules' => [['prefix' => 'p', 'commands' => [['name' => 'a', 'description' => 'x']]]]]]];
        $b = ['modules' => [['name' => 'M', 'subModules' => [['prefix' => 'p', 'commands' => [['name' => 'a', 'description' => 'y'], ['name' => 'b', 'description' => 'new']]]]]]];
        $c = ['modules' => [['name' => 'M', 'subModules' => [['prefix' => 'p', 'commands' => [['name' => 'b', 'description' => 'new']]]]]]];

        botTrackCommands($a, 1000);
        assertSame(1000, botCommandsWatchedSince());
        assertSame([], botCommandChanges(), 'the first list is not a change');

        botTrackCommands($b, 2000);
        $changes = botCommandChanges();
        assertSame(1, count($changes));
        assertSame(['p b'], $changes[0]['added']);
        assertSame(['x', 'y'], $changes[0]['changed']['p a']['description']);

        botTrackCommands($c, 3000);
        $changes = botCommandChanges();
        assertSame(2, count($changes), 'newest first');
        assertSame(['p a'], $changes[0]['removed']);
        assertSame('y', $changes[0]['gone']['p a']['description'], 'the text from just before it went');
    });

    test('botTrackCommands writes an index of an older format again without a change', function () {
        $data = ['modules' => [['name' => 'M', 'subModules' => [['prefix' => '', 'commands' => [
            ['name' => 'a', 'description' => 'x', 'attributes' => [['description' => 'p, q'], ['description' => 'r']]],
            ['name' => 'a', 'description' => 'y'],
        ]]]]]];
        // as the first format had it: one key for both, the parameters joined by a comma
        file_put_contents(botFile('commands-index.json'), json_encode(['since' => 500, 'commands' => [
            'a' => ['module' => 'M', 'name' => 'a', 'description' => 'y', 'aliases' => '', 'parameters' => '', 'example' => 'a'],
        ]]));

        botTrackCommands($data, 1000);
        assertSame([], botCommandChanges(), 'the new keys and texts are not a change of the bot');
        assertSame(500, botCommandsWatchedSince(), 'the start of the log stays');
        $stored = json_decode(file_get_contents(botFile('commands-index.json')), true);
        assertSame(COMMAND_INDEX_FORMAT, $stored['format']);
        assertSame(['a', 'a #2'], array_keys($stored['commands']));
        assertSame('p, q · r', $stored['commands']['a']['parameters']);
    });

    test('botRecordDay and the daily parts', function () {
        $day = strtotime('today');
        file_put_contents(botFile('status-history.txt'), implode("\n", [
            ($day + 5) . ' 1 40 -',
            ($day + 10) . ' 0',
            '',
        ]) . "\n");
        botRecordDay(true, time());
        $days = botDays();
        $today = date('Y-m-d');
        assertSame(2, $days[$today][0]);
        assertSame(1, $days[$today][1]);
        $parts = botDailyParts(3);
        assertSame(2, end($parts)['checks']);
    });

    test('botRecordIncident and botDownSince', function () {
        file_put_contents(botFile('incidents.json'), '[]');
        botRecordIncident(false, 1000);
        botRecordIncident(true, 2000);
        assertSame([[1000, 2000]], botRawIncidents());
        assertNull(botDownSince());

        botRecordIncident(false, 3000);
        assertSame(3000, botDownSince());
        assertContains('od', botDownText(['status' => 'offline']));
    });

    test('botApiRecord, botApiLast and botApiDown', function () {
        $now = time();
        botApiRecord(true, 42, $now);
        assertSame([$now, true, 42, null], botApiLast());
        assertFalse(botApiDown($now + 1));

        botApiRecord(false, 50, $now + 2);
        assertTrue(botApiDown($now + 3));
    });

    test('botRecordDependencies and botDependencyHistory', function () {
        $now = time();
        botRecordDependencies(['shinden' => ['ok' => true, 'latencyMs' => 123]], $now);
        $history = botDependencyHistory('shinden');
        assertSame([$now, true, 123, null], end($history));
    });

    test('botPrivateModules drops what is public', function () {
        file_put_contents(botFile('status.json'), json_encode(['status' => 'online', 'uptime' => 100, 'checked' => time(), 'ms' => null, 'issues' => []]));
        file_put_contents(botFile('commands.json'), json_encode(['modules' => [['name' => 'Pub', 'subModules' => [['prefix' => 'p', 'commands' => [['name' => 'daily']]]]]]]));
        file_put_contents(botFile('commands-private.json'), json_encode(['modules' => [['name' => 'Mod', 'subModules' => [['prefix' => 'p', 'commands' => [
            ['name' => 'daily', 'description' => 'public too'],
            ['name' => 'ban', 'description' => 'mod only'],
        ]]]]]]));

        $modules = botPrivateModules();
        assertSame(1, count($modules));
        assertSame('ban', $modules[0]['subModules'][0]['commands'][0]['name']);
    });

    test('botHealth, botCachedState and the heartbeat', function () {
        file_put_contents(botFile('health.json'), json_encode(['status' => 'ok']));
        assertSame('ok', botHealth()['status']);
        unlink(botFile('health.json'));
        assertNull(botHealth());

        file_put_contents(botFile('status.json'), json_encode(['status' => 'online', 'uptime' => 100, 'checked' => time()]));
        assertSame('online', botCachedState(botFile('status.json'))['status']);
        file_put_contents(botFile('status2.json'), json_encode(['status' => 'online']));
        assertNull(botCachedState(botFile('status2.json')), 'an incomplete state');

        $now = time();
        botSaveHeartbeat(['status' => 'ok', 'discord' => ['state' => 'Connected', 'latencyMs' => 10]], $now);
        assertSame($now, botHeartbeatTime());
        assertNull(botHeartbeatReport($now), 'no secret means no fresh report');
    });

    test('botRecordVersion logs a version only when it changes', function () {
        $now = time();
        botRecordVersion('1.0', $now);
        botRecordVersion('1.0', $now + 60, gmdate('c', $now + 30));
        botRecordVersion('1.1', $now + 120, gmdate('c', $now + 100));
        botRecordVersion('1.1', $now + 180);

        $versions = botVersions();
        assertSame(2, count($versions), 'the same version is logged once');
        assertSame('1.1', $versions[0]['version'], 'newest first');
        assertSame('1.0', $versions[1]['version']);
        assertSame($now + 100, $versions[0]['since'], 'startedAt says when it started');
        assertSame($now + 120, $versions[0]['seen'], 'when the site first saw it');
        assertSame($now, $versions[1]['since'], 'without startedAt the check time is used');

        // a startedAt from before the previous change is not believed
        botRecordVersion('1.2', $now + 300, gmdate('c', $now - 500));
        assertSame($now + 300, botVersions()[0]['since']);

        botRecordVersion('', $now + 360);
        assertSame(3, count(botVersions()), 'an empty version is ignored');
    });

    test('botCronLast and the bot addresses', function () {
        assertNull(botCronLast());
        botWriteFile(botFile('cron.txt'), '12345');
        assertSame(12345, botCronLast());

        $now = time();
        botNoteAddress('8.8.8.8', $now);
        assertTrue(botIsAddress('8.8.8.8'));
        assertFalse(botIsAddress('9.9.9.9'));
        botNoteAddress('not-an-ip', $now);
        assertFalse(botIsAddress('not-an-ip'), 'a broken address is not kept');
    });
