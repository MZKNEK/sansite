<?php
    // inc/bot.php: the check parsers, command index, times and timelines.

    test('botParseCheck reads a history line', function () {
        assertSame([123, true, 45, null], botParseCheck('123 1 45 -'));
        assertSame([123, false, null, null], botParseCheck('123 0'));
        assertSame([123, true, 45, 67], botParseCheck('123 1 45 67'));
        assertSame([100, false, null, null], botParseCheck('100 0 -'));
        assertNull(botParseCheck(''));
        assertNull(botParseCheck('123'), 'too short');
        assertNull(botParseCheck('1 2 3 4 5'), 'too long');
    });

    test('commandSlug makes an anchor out of a command key', function () {
        assertSame('pw-daily', commandSlug('pw daily'));
        assertSame('daily', commandSlug('daily'));
        assertSame('zwycięzca', commandSlug('zwycięzca'));
        assertSame('cmd-module-x', commandSlug('module x'), 'a slug starting with module- is prefixed');
    });

    test('botCommandIndex flattens the API answer', function () {
        $data = ['modules' => [[
            'name' => 'Mod',
            'subModules' => [[
                'prefix' => 'm',
                'commands' => [[
                    'name' => 'daily',
                    'description' => 'Codzienne',
                    'aliases' => ['daily', 'd'],
                    'attributes' => [['description' => 'ile']],
                    'example' => '3',
                ]],
            ]],
        ]]];
        $index = botCommandIndex($data);
        assertSame(['m daily'], array_keys($index));
        assertSame('Mod', $index['m daily']['module']);
        assertSame('Codzienne', $index['m daily']['description']);
        assertSame('d', $index['m daily']['aliases'], 'the name is dropped from the aliases');
        assertSame('ile', $index['m daily']['parameters']);
        assertSame('daily 3', $index['m daily']['example']);
    });

    test('duration picks the unit', function () {
        assertSame('1 min', duration(30));
        assertSame('2 min', duration(90));
        assertSame('1 godz.', duration(3600));
        assertSame('1 godz. 1 min', duration(3660));
        assertSame('1 dzień', duration(86400));
        assertSame('2 dni', duration(2 * 86400));
    });

    test('botTimeRange and noticeText', function () {
        $from = mktime(22, 0, 0, 10, 4, 2026);
        $to = mktime(23, 0, 0, 10, 4, 2026);
        assertSame('4.10 22:00–23:00', botTimeRange($from, $to));
        assertSame('Przerwa techniczna 4.10 22:00–23:00. Treść',
            noticeText(['text' => 'Treść', 'maintenance' => ['from' => $from, 'to' => $to]]));
        assertSame('Treść', noticeText(['text' => 'Treść']));
    });

    test('botTimeline sums the checks into quarter hours', function () {
        $start = time() - BOT_HISTORY_SPAN;
        $history = [
            [$start + 10, true, null, null],
            [$start + 20, false, null, null],
            [$start + 30, true, null, null],
            [$start + 900 + 10, true, null, null],
            [$start + 900 + 20, true, null, null],
        ];
        $parts = botTimeline($history);
        assertSame(96, count($parts));
        assertSame(3, $parts[0]['checks']);
        assertSame(1, $parts[0]['down']);
        assertSame('warn', $parts[0]['state'], 'one down of three is a warning');
        assertSame(2, $parts[1]['checks']);
        assertSame('ok', $parts[1]['state']);
        assertNull($parts[2]['state'], 'a quarter hour with no checks');
    });

    test('botResponseTimes averages the answered checks', function () {
        $start = time() - BOT_HISTORY_SPAN;
        $history = [
            [$start + 10, true, 100, 200],
            [$start + 20, true, 300, null],
            [$start + 30, false, 999, null],
        ];
        $ping = botResponseTimes($history, 2);
        assertSame(200, $ping[0]['avg'], '(100+300)/2');
        assertSame(300, $ping[0]['max']);
        assertNull($ping[1]['avg']);
        $shinden = botResponseTimes($history, 3);
        assertSame(200, $shinden[0]['avg'], 'only the first check has a Shinden time');
    });
