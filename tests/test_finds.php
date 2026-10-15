<?php
    // inc/auth/finds.php: the Safeguard's register, the easter eggs an account
    // found, kept in inc/data/finds.json; js/safeguard.js knows the same keys.

    test('safeguardFinds is empty for a new account and keeps the register order', function () {
        clearData();
        assertSame([], safeguardFinds('111'));
        writeData('finds', ['111' => ['offline' => 30, 'netsphere' => 10, 'nope' => 5, 'gbe' => 'x']]);
        assertSame(['netsphere' => 10, 'offline' => 30], safeguardFinds('111'), 'unknown keys and broken times are left out');
    });

    test('addSafeguardFinds notes known finds and keeps the first time', function () {
        clearData();
        $now = 1760000000;
        assertSame(['gbe' => $now - 60], addSafeguardFinds('111', ['gbe' => $now - 60, 'made-up' => $now], $now));
        assertSame(['gbe' => $now - 60, 'lost' => $now], addSafeguardFinds('111', ['gbe' => $now - 10, 'lost' => $now], $now), 'a later time does not move the first one');
        assertSame(['gbe' => $now - 120, 'lost' => $now], addSafeguardFinds('111', ['gbe' => $now - 120], $now), 'an earlier one from another browser does');
        assertSame([], safeguardFinds('222'), 'one account does not touch another');
    });

    test('addSafeguardFinds counts a time from a wrong clock from now', function () {
        clearData();
        $now = 1760000000;
        $finds = addSafeguardFinds('111', ['console' => $now + 3600, 'silicon' => 5, 'header' => 'soon'], $now);
        assertSame(['console' => $now, 'silicon' => $now, 'header' => $now], $finds);
    });

    test('addSafeguardFinds with nothing known writes nothing', function () {
        clearData();
        assertSame([], addSafeguardFinds('111', 'not an array', 1760000000));
        assertSame([], addSafeguardFinds('111', ['made-up' => 1], 1760000000));
        assertSame([], readData('finds'));
    });

    test('addSafeguardFinds takes the seasonal finds too, listed last', function () {
        clearData();
        $now = 1760000000;
        assertSame(['gbe' => $now, 'xmas' => $now - 5], addSafeguardFinds('111', ['xmas' => $now - 5, 'gbe' => $now], $now));
    });

    test('js/safeguard.js lists the same finds as SAFEGUARD_FINDS and SAFEGUARD_SEASONAL', function () {
        $js = file_get_contents(dirname(__DIR__) . '/js/safeguard.js');
        foreach (['FINDS' => SAFEGUARD_FINDS, 'SEASONAL' => SAFEGUARD_SEASONAL] as $name => $list) {
            assertTrue((bool)preg_match('/var ' . $name . ' = \{(.*?)\};/s', $js, $block), 'the ' . $name . ' block is in the script');
            preg_match_all('/^\s*(\w+): \'([^\']*)\'/m', $block[1], $keys);
            assertSame(array_keys($list), $keys[1], 'the same keys in the same order in ' . $name);
            foreach ($keys[1] as $i => $key)
                assertSame($list[$key][0], $keys[2][$i], 'the same name for ' . $key);
        }
    });

    // the ranking of the hunters (hunters/): '999' is a panel admin, as in test_access.php
    if (!defined('PANEL_ADMINS')) define('PANEL_ADMINS', ['999']);

    test('safeguardRanking puts more finds first, then who got there first', function () {
        clearData();
        writeData('finds', [
            '111' => ['gbe' => 50, 'lost' => 60],
            '222' => ['gbe' => 10, 'lost' => 20, 'console' => 70],
            '333' => ['gbe' => 30, 'lost' => 40],
            '444' => ['xmas' => 5]
        ]);
        $rows = safeguardRanking();
        assertSame(['222', '333', '111', '444'], array_column($rows, 'id'), 'the same count: the one that reached it sooner');
        assertSame([1, 2, 3, 4], array_column($rows, 'place'));
        assertSame([3, 2, 2, 0], array_column($rows, 'count'), 'the seasonal finds stay out of the count');
        assertSame(60, $rows[2]['reached']);
        assertSame(5, $rows[3]['reached'], 'only seasonal: its first');
    });

    test('safeguardRanking lists the panel admins without a place of their own', function () {
        clearData();
        writeData('finds', [
            '111' => ['gbe' => 50],
            '999' => ['gbe' => 1, 'lost' => 2, 'console' => 3],
            '222' => ['gbe' => 10, 'lost' => 20]
        ]);
        $rows = safeguardRanking();
        assertSame(['999', '222', '111'], array_column($rows, 'id'), 'where it would stand');
        assertSame([null, 1, 2], array_column($rows, 'place'), 'the others keep their places');
        assertTrue($rows[0]['admin']);
        $stats = safeguardFindStats($rows);
        assertSame(2, $stats['gbe']['hunters'], 'the admin is not counted');
        assertSame('222', $stats['gbe']['first']['id'], 'nor the first to find it');
        assertSame([['999', null], ['222', 1], ['111', 2]], array_map(function ($got) { return [$got['row']['id'], $got['place']]; }, $stats['gbe']['all']), 'listed, without a place');
        assertSame(['999'], array_map(function ($got) { return $got['row']['id']; }, $stats['console']['all']));
        assertSame(10, $stats['gbe']['time']);
        assertSame(0, $stats['console']['hunters']);
        assertSame(null, $stats['console']['first']);
    });

    test('safeguardFindStats lists everyone who made a find, the first first', function () {
        clearData();
        writeData('finds', [
            '111' => ['gbe' => 50],
            '999' => ['gbe' => 1],
            '222' => ['gbe' => 10, 'lost' => 20],
            '333' => ['gbe' => 30]
        ]);
        $stats = safeguardFindStats(safeguardRanking());
        assertSame(['999', '222', '333', '111'], array_map(function ($got) { return $got['row']['id']; }, $stats['gbe']['all']), 'the admin too');
        assertSame([1, 10, 30, 50], array_column($stats['gbe']['all'], 'time'));
        assertSame([null, 1, 2, 3], array_column($stats['gbe']['all'], 'place'));
        assertSame(3, $stats['gbe']['hunters']);
        assertSame([], $stats['console']['all']);
    });

    test('safeguardHolidays knows the days of the seasonal finds', function () {
        $on = function ($day) {
            return safeguardHolidays(new DateTimeImmutable($day, new DateTimeZone('Europe/Warsaw')));
        };
        assertSame(['xmas', 'newyear'], $on('2026-12-31'), 'the New Year in the middle of Christmas');
        assertSame(['xmas', 'newyear'], $on('2027-01-01'));
        assertSame(['xmas'], $on('2027-01-06'));
        assertSame([], $on('2027-01-07'));
        assertSame(['easter'], $on('2027-03-21'), 'Palm Sunday, Easter on 28 March 2027');
        assertSame(['easter'], $on('2027-03-29'), 'Easter Monday');
        assertSame([], $on('2027-03-30'));
        assertSame(['easter', 'aprilfools'], $on('2029-04-01'), 'Sakura starts after April Fools');
        assertSame(['easter', 'sakura'], $on('2029-04-02'), 'Sakura overlaps Easter Monday');
        assertSame(['birthday'], $on('2026-10-13'));
        assertSame(['halloween'], $on('2026-10-31'));
        assertSame(['friday13'], $on('2026-11-13'));
        assertSame(['birthday', 'friday13'], $on('2028-10-13'), 'the birthday on a Friday');
        assertSame([], $on('2026-10-12'));
    });

    test('seasonal backgrounds honor every day and both boundaries of their events', function () {
        foreach (['sakura' => ['04-02', 14], 'perseids' => ['08-01', 15], 'fireflies' => ['06-15', 16]] as $key => [$date, $length]) {
            $start = new DateTimeImmutable('2026-' . $date . ' 00:00', new DateTimeZone('Europe/Warsaw'));
            assertFalse(in_array($key, safeguardHolidays($start->modify('-1 day')), true));
            for ($day = 0; $day < $length; $day++) {
                $date = $start->modify('+' . $day . ' days');
                assertTrue(in_array($key, safeguardHolidays($date), true), $key . ' on ' . $date->format('Y-m-d'));
                assertSame(0, safeguardHolidayIn($key, $date));
            }
            assertFalse(in_array($key, safeguardHolidays($start->modify('+' . $length . ' days')), true));
            assertSame(1, safeguardHolidayIn($key, $start->modify('-1 day')));
            assertTrue(isset(SAFEGUARD_SEASONAL[$key]));
            assertFalse(isset(SAFEGUARD_FINDS[$key]), 'the event stays outside the permanent count');
        }
    });

    test('safeguardHolidayIn says how soon a seasonal find comes back', function () {
        $day = new DateTimeImmutable('2026-10-12 23:30', new DateTimeZone('Europe/Warsaw'));
        assertSame(1, safeguardHolidayIn('birthday', $day));
        assertSame(19, safeguardHolidayIn('halloween', $day));
        assertSame(32, safeguardHolidayIn('friday13', $day), 'Friday 13 November 2026');
        assertSame(80, safeguardHolidayIn('newyear', $day));
        assertSame(0, safeguardHolidayIn('halloween', $day->modify('+19 days')), 'while it lasts');
        assertSame('wraca jutro', safeguardHolidayWhen(1));
        assertSame('wraca za 19 dni', safeguardHolidayWhen(19));
        assertSame('trwa teraz', safeguardHolidayWhen(0));
        foreach (array_keys(SAFEGUARD_SEASONAL) as $key)
            assertTrue(safeguardHolidayIn($key, $day) !== null, $key . ' comes back within the year and a bit');
    });

    test('safeguardRanking takes the latest name and leaves out who left it', function () {
        clearData();
        writeData('finds', ['111' => ['gbe' => 5], '222' => ['gbe' => 6], '333' => ['gbe' => 7]]);
        writeData('logins', ['111' => ['name' => 'Nowa', 'avatar' => 'a.png']]);
        noteHunter(['id' => '111', 'name' => 'Stara', 'avatar' => 'b.png']);
        noteHunter(['id' => '222', 'name' => 'Druga', 'avatar' => 'c.png']);
        setHunterHidden(['id' => '333', 'name' => 'Ukryta', 'avatar' => ''], true);
        $rows = safeguardRanking();
        assertSame(['111', '222'], array_column($rows, 'id'));
        assertSame(['Nowa', 'Druga'], array_column($rows, 'name'), 'the logins first, then the name of its last find');
        assertTrue(hunterHidden('333'));
        setHunterHidden(['id' => '333', 'name' => 'Ukryta', 'avatar' => ''], false);
        assertSame(false, hunterHidden('333'));
        assertSame(3, count(safeguardRanking()), 'back in it');
        assertSame('Ukryta', readData('hunters')['333']['name']);
    });

    test('safeguardRanking names a hunter the site no longer knows', function () {
        clearData();
        writeData('finds', ['111' => ['gbe' => 5]]);
        $rows = safeguardRanking();
        assertSame('Nieznany łowca', $rows[0]['name']);
        assertSame('', $rows[0]['avatar']);
        assertSame(null, $rows[0]['badge']);
    });

    test('safeguardCheat catches the Builders\' hour outside it and finds made all at once', function () {
        $at = function ($hour, $minute) {
            return (new DateTimeImmutable(sprintf('2026-10-12 %02d:%02d:30', $hour, $minute), new DateTimeZone('Europe/Warsaw')))->getTimestamp();
        };
        assertSame(null, safeguardCheat(['builders' => $at(3, 2)], ['builders']));
        assertSame(null, safeguardCheat(['builders' => $at(15, 6)], ['builders']));
        assertTrue(strpos((string)safeguardCheat(['builders' => $at(12, 0)], ['builders']), '12.10.2026 12:00:30') !== false, 'outside it, with when');
        assertSame(null, safeguardCheat(['builders' => $at(12, 0), 'gbe' => 1], ['gbe']), 'only what was just made is checked');
        // what the console trick leaves: every find in the same second
        $now = 1760000000;
        $burst = ['gbe' => $now, 'lost' => $now, 'console' => $now + 2, 'silicon' => $now + 4];
        assertTrue(strpos((string)safeguardCheat($burst, ['silicon']), '4 wpisy') === 0);
        assertSame(null, safeguardCheat(array_slice($burst, 0, 3), ['console']), 'three at once is still fine');
        assertSame(null, safeguardCheat(['gbe' => $now, 'lost' => $now + 10, 'console' => $now + 20, 'silicon' => $now + 30], ['silicon']), 'one after another');
        assertSame(null, safeguardCheat($burst, []), 'nothing new, nothing to check');
    });

    test('safeguardCheat catches five finds in two minutes, each on a way of its own', function () {
        $now = 1760000000;
        $rush = ['lost' => $now, 'unknown' => $now + 25, 'console' => $now + 50, 'lore' => $now + 75, 'morse' => $now + 100];
        assertSame('5 wpisów zdobyte w ciągu 2 minut.', safeguardCheat($rush, ['morse']));
        assertSame('5 wpisów zdobyte w ciągu 2 minut.', safeguardCheat($rush, ['lost']), 'the first of them too');
        assertSame(null, safeguardCheat(array_slice($rush, 0, 4), ['lore']), 'four is still fine');
        $slower = ['lost' => $now, 'unknown' => $now + 31, 'console' => $now + 62, 'lore' => $now + 93, 'morse' => $now + 124];
        assertSame(null, safeguardCheat($slower, ['morse']), 'five in more than two minutes');
        assertSame(null, safeguardCheat($rush + ['gbe' => $now + 1000], ['gbe']), 'only around what was just made');
    });

    test('safeguardCheat catches a step of the way to the gene made sooner than it can be', function () {
        $door = (new DateTimeImmutable('2026-10-12 03:01:00', new DateTimeZone('Europe/Warsaw')))->getTimestamp();
        $way = ['builders' => $door, 'floor0' => $door + 60, 'maze' => $door + 600, 'paper' => $door + 700, 'gene' => $door + 800, 'admin' => $door + 2000];
        foreach (array_keys($way) as $key)
            assertSame(null, safeguardCheat($way, [$key]), $key);
        assertSame('Wpis „Piętro zero” zdobyty 3 s po poprzednim kroku drogi do genu.', safeguardCheat(['floor0' => $door + 3] + $way, ['floor0']));
        assertSame('Wpis „Labirynt piętra zero” zdobyty 5 s po poprzednim kroku drogi do genu.', safeguardCheat(['maze' => $way['floor0'] + 5] + $way, ['maze']));
        assertTrue(strpos((string)safeguardCheat(['gene' => $way['paper'] + 1] + $way, ['gene']), 'Wpis „Gen terminala sieciowego” zdobyty 1 s') === 0, 'from the last fragment');
        assertTrue(strpos((string)safeguardCheat(['admin' => $way['gene'] + 10] + $way, ['admin']), 'Wpis „Administrator Netsfery” zdobyty 10 s') === 0);
        assertSame(null, safeguardCheat(['floor0' => $door + 3] + $way, ['gbe']), 'only what was just made is checked');
    });

    test('the ranking shows the snake\'s best games of SNAKE_FED and more', function () {
        clearData();
        writeData('snake', ['111' => ['best' => 40, 'time' => 1], '222' => ['best' => SNAKE_FED - 1, 'time' => 1], '333' => ['best' => SNAKE_FED, 'time' => 1], '444' => 'broken']);
        assertSame(['111' => 40, '333' => SNAKE_FED], snakeRecords());
    });

    test('setSafeguardFlag flags an account: last in the ranking, without a place, out of the first finds', function () {
        clearData();
        writeData('finds', [
            '111' => ['gbe' => 50],
            '222' => ['gbe' => 10, 'lost' => 20, 'console' => 30],
            '333' => ['gbe' => 30, 'lost' => 40]
        ]);
        noteHunter(['id' => '222', 'name' => 'Oszust', 'avatar' => 'a.png']);
        assertTrue(setSafeguardFlag('222', 'Za szybko.'));
        assertSame('Za szybko.', safeguardFlag('222')['reason']);
        assertTrue(!isset(safeguardFlag('222')['by']), 'set by the Safeguard');
        assertSame('Oszust', readData('hunters')['222']['name'], 'the hunter keeps its name');
        $rows = safeguardRanking();
        assertSame(['333', '111', '222'], array_column($rows, 'id'));
        assertSame([1, 2, null], array_column($rows, 'place'));
        assertSame('Za szybko.', $rows[2]['cheat']['reason']);
        $stats = safeguardFindStats($rows);
        assertSame('333', $stats['gbe']['first']['id']);
        assertSame(0, $stats['console']['hunters']);
        assertSame([['222', null]], array_map(function ($got) { return [$got['row']['id'], $got['place']]; }, $stats['console']['all']), 'the flagged one listed, without a place');

        noteHunter(['id' => '222', 'name' => 'Oszust 2', 'avatar' => 'a.png']);
        assertTrue(safeguardFlag('222') !== null, 'a new name keeps the flag');
        assertTrue(setSafeguardFlag('222', null, '999'));
        assertSame(null, safeguardFlag('222'));
        assertSame([1, 2, 3], array_column(safeguardRanking(), 'place'));
        assertTrue(setSafeguardFlag('333', 'Ręcznie.', '999'));
        assertSame('999', safeguardFlag('333')['by']);
    });

    test('removeSafeguardFinds takes back one find or all of them', function () {
        clearData();
        writeData('finds', ['111' => ['gbe' => 50, 'lost' => 60], '222' => ['gbe' => 10]]);
        assertTrue(removeSafeguardFinds('111', ['lost']));
        assertSame(['gbe' => 50], safeguardFinds('111'));
        assertTrue(removeSafeguardFinds('111', ['gbe']));
        assertTrue(!isset(readData('finds')['111']), 'the last one taken, the account is gone from the register');
        assertTrue(removeSafeguardFinds('222'));
        assertSame([], readData('finds'));
        assertTrue(removeSafeguardFinds('333', ['gbe']), 'an account without finds');
    });
