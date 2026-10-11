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

    test('js/safeguard.js lists the same finds as SAFEGUARD_FINDS', function () {
        $js = file_get_contents(dirname(__DIR__) . '/js/safeguard.js');
        assertTrue((bool)preg_match('/var FINDS = \{(.*?)\};/s', $js, $block), 'the FINDS block is in the script');
        preg_match_all('/^\s*(\w+): \'([^\']*)\'/m', $block[1], $keys);
        assertSame(array_keys(SAFEGUARD_FINDS), $keys[1], 'the same keys in the same order');
        foreach ($keys[1] as $i => $key)
            assertSame(SAFEGUARD_FINDS[$key][0], $keys[2][$i], 'the same name for ' . $key);
    });
