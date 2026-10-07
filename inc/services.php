<?php
    // The Sanakan sites, checked every 5 minutes by inc/check-bot.php (which
    // cron runs every minute for the bot): whether they answer, how fast, since
    // when they are up or down,
    // and per day how many checks they passed, for the 90 day bars on state/.
    // This site itself is checked every 10 seconds by inc/check-site.php
    // (inc/diag.php), and every 5 minutes here only while that does not run.
    // Kept in inc/data/services.json as
    // [key => ['up', 'since', 'ms', 'checked', 'days' => ['Y-m-d' => [checks, up]]]].
    require_once __DIR__ . '/bot.php';

    const SERVICES = [
        'site' => ['sanakan.pl', 'https://sanakan.pl/'],
        'wiki' => ['Wiki', 'https://wiki.sanakan.pl/'],
        'waifu' => ['Waifu', 'https://waifu.sanakan.pl/'],
        'alter' => ['Alter', 'https://alter.sanakan.pl/'],
        'skalpel' => ['Skalpelator', 'https://skalpel.sanakan.pl/'],
        'uskalpel' => ['USkalpelator', 'https://uskalpel.sanakan.pl/']
    ];
    const SERVICE_TIMEOUT = 8;
    const SERVICE_INTERVAL = 300;
    const SERVICE_DAYS_KEEP = 100;
    // checked by inc/check-site.php; here only when its last check is this old
    const SERVICE_OWN_STALE = 600;

    // [answered with a page (2xx/3xx), milliseconds]
    function serviceCheck($url)
    {
        $started = microtime(true);
        [$body, $status] = httpRaw($url, [
            'method' => 'GET',
            'timeout' => SERVICE_TIMEOUT,
            'ignore_errors' => true,
            'header' => "User-Agent: SanakanStatus (https://sanakan.pl/state/)\r\n"
        ], 65536);
        $ms = (int)round((microtime(true) - $started) * 1000);

        return [$body !== false && $status >= 200 && $status < 400, $ms];
    }

    // whether the last check is SERVICE_INTERVAL old; half a minute earlier, so
    // a cron run a little early does not skip a whole round
    function servicesDue()
    {
        $data = json_decode((string)@file_get_contents(botFile('services.json')), true);
        unset($data['site']);
        $checked = is_array($data) && $data ? min(array_map(function ($entry) { return $entry['checked'] ?? 0; }, $data)) : 0;

        return time() - $checked >= SERVICE_INTERVAL - 30;
    }

    // checks every service once and records the results; this site only when
    // inc/check-site.php has not checked it for a while
    function servicesCheckAll()
    {
        $data = json_decode((string)@file_get_contents(botFile('services.json')), true);
        $results = [];
        foreach (SERVICES as $key => $service)
            if ($key !== 'site' || time() - ($data['site']['checked'] ?? 0) >= SERVICE_OWN_STALE)
                $results[$key] = serviceCheck($service[1]);

        servicesRecord($results, time());
    }

    // records checks as [key => [up, milliseconds]], made at $now
    function servicesRecord($results, $now)
    {
        $fp = @fopen(botFile('services.json'), 'c+');
        if ($fp === false || !flock($fp, LOCK_EX)) {
            if ($fp !== false)
                fclose($fp);
            return;
        }

        $data = json_decode((string)stream_get_contents($fp), true);
        $data = is_array($data) ? $data : [];
        $day = date('Y-m-d', $now);
        foreach ($results as $key => [$up, $ms]) {
            $entry = $data[$key] ?? ['days' => []];
            if (!isset($entry['up']) || $entry['up'] !== $up)
                $entry['since'] = $now;
            $entry['up'] = $up;
            $entry['ms'] = $up ? $ms : null;
            $entry['checked'] = $now;
            $counts = $entry['days'][$day] ?? [0, 0];
            $entry['days'][$day] = [$counts[0] + 1, $counts[1] + ($up ? 1 : 0)];
            ksort($entry['days']);
            $entry['days'] = array_slice($entry['days'], -SERVICE_DAYS_KEEP, null, true);
            $data[$key] = $entry;
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    // every service as ['key', 'name', 'url', 'up' (null: not checked yet),
    // 'since', 'ms', 'days' => the last $count days like botDailyParts()]
    function servicesState($count = 30)
    {
        $data = json_decode((string)@file_get_contents(botFile('services.json')), true);
        $data = is_array($data) ? $data : [];

        $services = [];
        foreach (SERVICES as $key => [$name, $url]) {
            $entry = $data[$key] ?? [];
            $parts = [];
            for ($i = $count - 1; $i >= 0; $i--) {
                $from = strtotime('today -' . $i . ' days');
                $counts = $entry['days'][date('Y-m-d', $from)] ?? [0, 0];
                $parts[] = ['from' => $from, 'checks' => $counts[0], 'up' => $counts[1]];
            }
            $services[] = [
                'key' => $key,
                'name' => $name,
                'url' => $url,
                'up' => $entry['up'] ?? null,
                'since' => $entry['since'] ?? null,
                'ms' => $entry['ms'] ?? null,
                'days' => $parts
            ];
        }

        return $services;
    }

    // the names of the services that do not answer now
    function servicesDown()
    {
        $down = [];
        foreach (servicesState(1) as $service)
            if ($service['up'] === false)
                $down[] = $service['name'];

        return $down;
    }
