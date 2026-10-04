<?php
    // Shared access to the bot API, used by status.php, cmd/, state/ and the
    // admin panel. The bot serves the API, so getting the command list back
    // means it is up. The API is asked at most once a minute however many
    // people visit, every check goes to a 24 h history, and the last command
    // list the bot returned is kept, so the commands page still works while
    // the bot is down. A bot that is up now but answered less than 99% of the
    // checks is idle. inc/check-bot.php run by cron every minute fills the
    // history also when nobody visits. Besides the 24 h history every check is
    // counted per day, which gives the 30 day and 12 month availability, and
    // every outage is kept with its start and end.
    const BOT_API_URL = 'https://api.sanakan.pl/api/Info/commands';
    const BOT_CACHE_TTL = 60;
    const BOT_HISTORY_SPAN = 86400;
    const BOT_MIN_UPTIME = 99.0;
    const BOT_DAYS_KEEP = 400;
    const BOT_INCIDENTS_KEEP = 200;

    // days, months and the times on the pages follow Polish time, not the server's
    date_default_timezone_set('Europe/Warsaw');

    // Kept in inc/data, which the web server and cron share (PHP-FPM often has
    // its own private /tmp) and which outlives a restart; /tmp only when the
    // folder cannot be written.
    function botFile($name)
    {
        $dir = __DIR__ . '/data';
        if (is_dir($dir) ? is_writable($dir) : @mkdir($dir, 0750, true))
            return $dir . '/bot-' . $name;

        return sys_get_temp_dir() . '/sanakan-' . $name;
    }

    // writes a temp file and renames it, so a parallel request never reads half a file
    function botWriteFile($file, $content)
    {
        $tmp = $file . '.' . getmypid();
        if (@file_put_contents($tmp, $content) !== false)
            @rename($tmp, $file);
    }

    // Adds the check to the history (one "time state" line per check, older
    // than 24 h dropped) and returns the percent of online checks in it.
    function botRecordCheck($online, $now)
    {
        $history = [];
        $fp = @fopen(botFile('status-history.txt'), 'c+');
        $locked = $fp !== false && flock($fp, LOCK_EX);

        if ($locked) {
            while (($line = fgets($fp)) !== false) {
                $parts = explode(' ', trim($line));
                if (count($parts) == 2 && (int)$parts[0] > $now - BOT_HISTORY_SPAN)
                    $history[] = [(int)$parts[0], $parts[1] === '1'];
            }
        }

        $history[] = [$now, $online];

        if ($locked) {
            ftruncate($fp, 0);
            rewind($fp);
            foreach ($history as $entry)
                fwrite($fp, $entry[0] . ' ' . ($entry[1] ? '1' : '0') . "\n");
            flock($fp, LOCK_UN);
        }
        if ($fp !== false)
            fclose($fp);

        $up = 0;
        foreach ($history as $entry)
            if ($entry[1])
                $up++;

        return 100 * $up / count($history);
    }

    // The last 24 h in 96 parts of 15 minutes, oldest first; each is
    // ['from' => time, 'state' => null (no checks) | true (all fine) | false (some failed)]
    function botTimeline($history)
    {
        $start = time() - BOT_HISTORY_SPAN;
        $parts = [];
        for ($i = 0; $i < 96; $i++)
            $parts[] = ['from' => $start + $i * 900, 'state' => null];

        foreach ($history as $check) {
            $i = min(95, max(0, (int)floor(($check[0] - $start) / 900)));
            $parts[$i]['state'] = ($parts[$i]['state'] ?? true) && $check[1];
        }

        return $parts;
    }

    // Counts the check for its day: ['Y-m-d' => [checks, online checks]]. The
    // first time, the days are filled from the 24 h history instead.
    function botRecordDay($online, $now)
    {
        $fp = @fopen(botFile('days.json'), 'c+');
        if ($fp === false || !flock($fp, LOCK_EX)) {
            if ($fp !== false)
                fclose($fp);
            return;
        }

        $days = json_decode((string)stream_get_contents($fp), true);
        if (!is_array($days)) {
            // the history already has this check in it
            $days = [];
            foreach (botHistory() as $check) {
                $day = date('Y-m-d', $check[0]);
                $days[$day] = [($days[$day][0] ?? 0) + 1, ($days[$day][1] ?? 0) + ($check[1] ? 1 : 0)];
            }
        } else {
            $day = date('Y-m-d', $now);
            $days[$day] = [($days[$day][0] ?? 0) + 1, ($days[$day][1] ?? 0) + ($online ? 1 : 0)];
        }

        ksort($days);
        $days = array_slice($days, -BOT_DAYS_KEEP, null, true);

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($days));
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    function botDays()
    {
        $days = json_decode((string)@file_get_contents(botFile('days.json')), true);

        return is_array($days) ? $days : [];
    }

    // The last $count days, oldest first; each is ['from' => time, 'checks', 'up']
    function botDailyParts($count)
    {
        $days = botDays();
        $parts = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            $from = strtotime('today -' . $i . ' days');
            $day = $days[date('Y-m-d', $from)] ?? [0, 0];
            $parts[] = ['from' => $from, 'checks' => $day[0], 'up' => $day[1]];
        }

        return $parts;
    }

    // The last $count months, this one included, oldest first; same shape as the days
    function botMonthlyParts($count)
    {
        $months = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            $from = strtotime(date('Y-m-01') . ' -' . $i . ' months');
            $months[date('Y-m', $from)] = ['from' => $from, 'checks' => 0, 'up' => 0];
        }

        foreach (botDays() as $day => $counts) {
            $month = substr($day, 0, 7);
            if (isset($months[$month])) {
                $months[$month]['checks'] += $counts[0];
                $months[$month]['up'] += $counts[1];
            }
        }

        return array_values($months);
    }

    // Outages as [start, end]: start is the first check without an answer, end
    // the first check answered again, null while the outage lasts. The first
    // time, they are read from the 24 h history instead.
    function botRecordIncident($online, $now)
    {
        $fp = @fopen(botFile('incidents.json'), 'c+');
        if ($fp === false || !flock($fp, LOCK_EX)) {
            if ($fp !== false)
                fclose($fp);
            return;
        }

        $incidents = json_decode((string)stream_get_contents($fp), true);
        $before = $incidents;
        if (!is_array($incidents)) {
            // the history already has this check in it
            $incidents = [];
            foreach (botHistory() as $check)
                botAddToIncidents($incidents, $check[1], $check[0]);
        } else {
            botAddToIncidents($incidents, $online, $now);
        }

        // most checks change nothing, then the file is not rewritten
        if ($incidents !== $before) {
            $incidents = array_slice($incidents, -BOT_INCIDENTS_KEEP);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($incidents));
        }
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    function botAddToIncidents(&$incidents, $online, $time)
    {
        $last = count($incidents) - 1;
        $open = $last >= 0 && $incidents[$last][1] === null;

        if (!$online && !$open)
            $incidents[] = [$time, null];
        else if ($online && $open)
            $incidents[$last][1] = $time;
    }

    // outages that lasted into the time since $since or still last, newest first
    function botIncidents($since)
    {
        $incidents = json_decode((string)@file_get_contents(botFile('incidents.json')), true);
        if (!is_array($incidents))
            return [];

        $recent = [];
        foreach ($incidents as $incident)
            if ($incident[1] === null || $incident[1] > $since)
                $recent[] = $incident;

        return array_reverse($recent);
    }

    // when inc/check-bot.php last ran from cron, or null when never
    function botCronLast()
    {
        $time = (int)@file_get_contents(botFile('cron.txt'));

        return $time > 0 ? $time : null;
    }

    // the checks of the last 24 h as [time, online], oldest first
    function botHistory()
    {
        $history = [];
        $lines = @file(botFile('status-history.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $parts = explode(' ', trim($line));
            if (count($parts) == 2 && (int)$parts[0] > time() - BOT_HISTORY_SPAN)
                $history[] = [(int)$parts[0], $parts[1] === '1'];
        }

        return $history;
    }

    // ['status' => online|idle|offline, 'uptime' => percent, 'checked' => time],
    // asks the API only when the cached state is older than a minute or $force
    function botState($force = false)
    {
        static $state = null;
        if ($state !== null && !$force)
            return $state;

        $now = time();
        $file = botFile('status.json');
        $state = null;
        if (!$force && is_file($file) && $now - filemtime($file) < BOT_CACHE_TTL)
            $state = @json_decode(@file_get_contents($file), true);

        if (!is_array($state) || !isset($state['status'], $state['uptime'], $state['checked'])) {
            $opts = [
                "http" => [
                    "method" => "GET",
                    "timeout" => 5
                ]
            ];
            $context = stream_context_create($opts);
            $json = @file_get_contents(BOT_API_URL, false, $context);
            $data = $json === false ? null : @json_decode($json, true);

            $online = !empty($data['modules']);
            if ($online)
                botWriteFile(botFile('commands.json'), $json);

            $uptime = botRecordCheck($online, $now);
            botRecordDay($online, $now);
            botRecordIncident($online, $now);

            if (!$online)
                $status = 'offline';
            else if ($uptime < BOT_MIN_UPTIME)
                $status = 'idle';
            else
                $status = 'online';

            $state = [
                'status' => $status,
                // rounded down, so 98.96 is not shown as 99 next to the idle status
                'uptime' => floor($uptime * 10) / 10,
                'checked' => $now
            ];
            botWriteFile($file, json_encode($state));
        }

        return $state;
    }

    // the last command list the bot returned (it may be old when the bot is offline), or null
    function botCommands()
    {
        botState();

        $file = botFile('commands.json');
        $data = is_file($file) ? @json_decode(@file_get_contents($file), true) : null;

        return is_array($data) ? $data : null;
    }
