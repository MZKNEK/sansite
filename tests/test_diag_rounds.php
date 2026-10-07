<?php
    // inc/diag.php: the availability analysis over the parsed rounds, without a
    // network or a log.

    // one round: the time, the probes (an array each) and anything else
    function dg_round($t, $probes, $extra = [])
    {
        return $extra + ['t' => $t, 'p' => $probes];
    }

    test('diagOnlyPublic, diagOnlyPhp and diagRoundFailed', function () {
        $publicOnly = [dg_round(1, ['pub' => [522, 50], 'pubphp' => [522, 50], 'loc' => [200, 100], 'locphp' => [200, 100]])];
        assertTrue(diagOnlyPublic($publicOnly), 'the server answered, Cloudflare did not');
        assertFalse(diagOnlyPhp($publicOnly));
        assertTrue(diagRoundFailed($publicOnly[0]));

        $phpOnly = [dg_round(1, ['pub' => [502, 50], 'pubphp' => [502, 50], 'loc' => [200, 100], 'locphp' => [502, 50]])];
        assertTrue(diagOnlyPhp($phpOnly), 'the files came, PHP did not');
        assertFalse(diagOnlyPublic($phpOnly));
    });

    test('diagTimeline colours the quarter hours', function () {
        $slot = intdiv(time(), 900) * 900;
        $ok = ['pub' => [200, 100], 'pubphp' => [200, 100]];
        $log = ['n' => 5, 'php' => 1, 's4' => 0, 's5' => 0, 'rt' => 100, 'ips' => [], 'paths' => []];
        $rounds = [
            dg_round($slot + 10, $ok, ['log' => $log]),
            dg_round($slot + 20, $ok, ['log' => $log]),
            dg_round($slot - 900 + 10, ['pub' => [200, 100], 'pubphp' => [200, 3500]]),
            dg_round($slot - 1800 + 10, ['pub' => [522, 50], 'pubphp' => [522, 50]]),
        ];
        $parts = diagTimeline($rounds, ['pub', 'pubphp']);
        assertSame(96, count($parts));
        assertSame('ok', $parts[95]['state']);
        assertSame(2, $parts[95]['rounds']);
        assertSame(10, $parts[95]['requests']);
        assertSame(5, $parts[95]['peak']);
        assertSame('warn', $parts[94]['state']);
        assertSame('fail', $parts[93]['state']);
        assertNull($parts[92]['state'], 'a quarter hour with no round');
    });

    test('diagEpisodes groups the failures of one outage', function () {
        $fail = ['pub' => [522, 10]];
        $ok = ['pub' => [200, 10]];
        $rounds = [
            dg_round(1000, $fail),
            dg_round(1010, $fail),   // 10 s later: the same outage
            dg_round(1020, $ok),
            dg_round(1040, $fail),   // 30 s after the last failure: a new one
        ];
        $episodes = diagEpisodes($rounds);
        assertSame(2, count($episodes));
        assertSame(1040, $episodes[0]['from'], 'the newest first');
        assertContains('Cloudflare', implode(' ', array_keys($episodes[0]['answers']['pub'])));
    });

    test('diagGrowth, diagPeak and diagUnhandled', function () {
        $before = ['tcp' => ['lo' => 10, 'ld' => 5, 'q' => 0], 'ng' => ['acc' => 100, 'hnd' => 90]];
        $during = [
            ['tcp' => ['lo' => 12, 'ld' => 6, 'q' => 3]],
            ['tcp' => ['lo' => 15, 'ld' => 8, 'q' => 2], 'ng' => ['acc' => 110, 'hnd' => 98]],
        ];
        assertSame(5, diagGrowth($before, $during, 'tcp', 'lo'));
        assertSame(3, diagPeak($during, 'tcp', 'q'));
        assertSame(2, diagUnhandled($before, $during), 'accepted 10, handled 8');
    });

    test('diagCpuNow reads the last two rounds', function () {
        $now = time();
        $rounds = [
            ['t' => $now - 10, 'cpu' => ['all' => 1000, 'idle' => 800, 'io' => 50, 'st' => 0]],
            ['t' => $now, 'cpu' => ['all' => 1100, 'idle' => 850, 'io' => 60, 'st' => 0]],
        ];
        $cpu = diagCpuNow($rounds);
        assertSame(40.0, $cpu['busy']);
        assertSame(10.0, $cpu['iowait']);
        assertSame(0.0, $cpu['steal']);

        assertNull(diagCpuNow([$rounds[0], ['t' => $now + 200, 'cpu' => ['all' => 1200, 'idle' => 900, 'io' => 60, 'st' => 0]]]), 'a gap');
        assertNull(diagCpuNow([]));
    });

    test('diagMergeTraffic sums the rounds and picks the busiest', function () {
        $rounds = [
            ['log' => ['n' => 10, 'php' => 2, 's4' => 1, 's5' => 0, 'rt' => 500,
                'ips' => [['1.1.1.1', 6, 2, 'US', 'ua1', '/a'], ['2.2.2.2', 4, 0, 'PL', 'ua2', '/b']],
                'paths' => [['/a', 6], ['/b', 4]]]],
            ['log' => ['n' => 5, 'php' => 1, 's4' => 0, 's5' => 1, 'rt' => 800,
                'ips' => [['1.1.1.1', 5, 1, 'US', 'ua1', '/c']],
                'paths' => [['/a', 5]]]],
        ];
        $total = diagMergeTraffic($rounds, 10);
        assertSame(15, $total['n']);
        assertSame(3, $total['php']);
        assertSame(1, $total['s5']);
        assertSame(800, $total['rt']);
        assertSame('1.1.1.1', $total['ips'][0]['ip']);
        assertSame(11, $total['ips'][0]['n'], '1.1.1.1 came 6 and 5 times');
        assertSame(['/a', 11], $total['paths'][0]);
    });

    test('diagUsage turns the counters into use', function () {
        $step = 300;
        $slot = intdiv(time(), $step) * $step;
        $rounds = [
            ['t' => $slot + 5, 'cpu' => ['all' => 1000, 'idle' => 800, 'io' => 50, 'st' => 10], 'mem' => 1024, 'ld' => 0.5],
            ['t' => $slot + 10, 'cpu' => ['all' => 1100, 'idle' => 850, 'io' => 60, 'st' => 12], 'mem' => 1000, 'ld' => 1.5],
        ];
        $parts = diagUsage($rounds, 2048 * 1048576, $step);
        assertSame(intdiv(86400, $step), count($parts));
        $last = end($parts);
        // 100 jiffies: idle 50, waiting for the disk 10, so busy 40; the host took 2
        assertEquals(40.0, $last['cpu']);
        assertEquals(10.0, $last['io']);
        assertEquals(2.0, $last['st']);
        assertEquals(1.5, $last['load']);
        assertTrue($last['mem'] > 0);
        assertTrue($last['memPeak'] > 0);
    });
