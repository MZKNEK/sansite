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
        assertSame(10, $stats['gbe']['time']);
        assertSame(0, $stats['console']['hunters']);
        assertSame(null, $stats['console']['first']);
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
