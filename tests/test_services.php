<?php
    // inc/services.php: the other Sanakan sites and their per-day checks.

    test('servicesState reads the checks per day', function () {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('today -1 day'));
        botWriteFile(botFile('services.json'), json_encode([
            'wiki' => ['up' => true, 'since' => time() - 100, 'ms' => 42, 'checked' => time(),
                'days' => [$today => [3, 3], $yesterday => [2, 1]]],
        ]));

        $wiki = null;
        foreach (servicesState(3) as $service)
            if ($service['key'] === 'wiki')
                $wiki = $service;
        assertSame(true, $wiki['up']);
        assertSame(42, $wiki['ms']);
        assertSame(3, count($wiki['days']));
        $last = end($wiki['days']);
        assertSame(3, $last['checks']);
        assertSame(3, $last['up'], 'today is the last part');
    });

    test('servicesDown names the services that do not answer', function () {
        botWriteFile(botFile('services.json'), json_encode([
            'waifu' => ['up' => false, 'since' => time() - 60, 'ms' => null, 'checked' => time(), 'days' => []],
        ]));
        assertContains('Waifu', servicesDown());
        assertFalse(in_array('Wiki', servicesDown(), true), 'the wiki was not checked');
    });

    test('servicesRecord notes the change and the per-day counts', function () {
        $now = time();
        servicesRecord(['wiki' => [true, 40]], $now);
        $wiki = null;
        foreach (servicesState(2) as $service)
            if ($service['key'] === 'wiki')
                $wiki = $service;
        assertTrue($wiki['up']);
        assertSame(40, $wiki['ms']);
        assertSame($now, $wiki['since']);
        assertSame(1, end($wiki['days'])['checks']);

        servicesRecord(['wiki' => [false, null]], $now + 10);
        $wiki = null;
        foreach (servicesState(2) as $service)
            if ($service['key'] === 'wiki')
                $wiki = $service;
        assertSame($now + 10, $wiki['since'], 'a change moves since');
        assertFalse($wiki['up']);
    });
