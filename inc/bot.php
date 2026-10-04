<?php
    // Shared access to the bot API, used by status.php, cmd/, state/ and the
    // admin panel. The bot serves the API, so getting the command list back
    // means it is up. The API is asked at most once a minute however many
    // people visit, every check goes to a 24 h history, and the last command
    // list the bot returned is kept, so the commands page still works while
    // the bot is down. A bot that is up now but answered less than 99% of the
    // checks is idle. inc/check-bot.php run by cron every minute fills the
    // history also when nobody visits. Besides the 24 h history every check is
    // counted per day, which gives the 90 day availability, and
    // every outage is kept with its start and end. Each check also notes how
    // long the API took to answer. The panel can set a notice for the home page
    // and state/, and mark a planned maintenance break. Changes in the command
    // list (new, removed, changed commands) are logged for cmd/ and cmd/zmiany/.
    // The bot's api/health says whether it is connected to Discord, its ping and
    // the state of its database and of Shinden. While a bot without it answers
    // 404, the command list is the check, as before.
    const BOT_HEALTH_URL = 'https://api.sanakan.pl/api/health';
    const BOT_API_URL = 'https://api.sanakan.pl/api/Info/commands';
    // with api/health the command list (cmd/ and its changes) is fetched only this often
    const BOT_COMMANDS_TTL = 600;
    const BOT_CACHE_TTL = 60;
    const BOT_HISTORY_SPAN = 86400;
    const BOT_MIN_UPTIME = 99.0;
    const BOT_DAYS_KEEP = 400;
    const BOT_INCIDENTS_KEEP = 200;
    const BOT_MAINTENANCE_KEEP = 100;
    const BOT_CHANGES_KEEP = 300;

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

    // one history line, "time state [ping [shinden]]" with "-" for a missing
    // time, as [time, online, ping ms or null, Shinden ms or null]; null for a broken line
    function botParseCheck($line)
    {
        $parts = explode(' ', trim($line));
        if (count($parts) < 2 || count($parts) > 4)
            return null;

        $ms = function ($i) use ($parts) { return isset($parts[$i]) && $parts[$i] !== '-' ? (int)$parts[$i] : null; };

        return [(int)$parts[0], $parts[1] === '1', $ms(2), $ms(3)];
    }

    // Adds the check to the history (one line per check, older than 24 h
    // dropped) and returns the percent of online checks in it. $ms is the
    // Discord ping (the API answer time without api/health) and $shinden the
    // answer time of Shinden, null when unknown.
    function botRecordCheck($online, $now, $ms = null, $shinden = null)
    {
        $history = [];
        $fp = @fopen(botFile('status-history.txt'), 'c+');
        $locked = $fp !== false && flock($fp, LOCK_EX);

        if ($locked) {
            while (($line = fgets($fp)) !== false) {
                $check = botParseCheck($line);
                if ($check && $check[0] > $now - BOT_HISTORY_SPAN)
                    $history[] = $check;
            }
        }

        $history[] = [$now, $online, $ms, $shinden];

        if ($locked) {
            ftruncate($fp, 0);
            rewind($fp);
            foreach ($history as $entry) {
                $line = $entry[0] . ' ' . ($entry[1] ? '1' : '0');
                if ($entry[2] !== null || $entry[3] !== null)
                    $line .= ' ' . ($entry[2] ?? '-');
                if ($entry[3] !== null)
                    $line .= ' ' . $entry[3];
                fwrite($fp, $line . "\n");
            }
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

    // The last 24 h in 96 parts of 15 minutes, oldest first, with the average and
    // the longest time of the answered checks: ['from', 'avg', 'max'], both null
    // when nothing answered. $field 2 is the Discord ping, 3 Shinden's time.
    function botResponseTimes($history, $field = 2)
    {
        $start = time() - BOT_HISTORY_SPAN;
        $sums = array_fill(0, 96, [0, 0, null]);
        foreach ($history as $check) {
            if (!$check[1] || ($check[$field] ?? null) === null)
                continue;
            $i = min(95, max(0, (int)floor(($check[0] - $start) / 900)));
            $sums[$i] = [$sums[$i][0] + $check[$field], $sums[$i][1] + 1, max($sums[$i][2] ?? 0, $check[$field])];
        }

        $parts = [];
        foreach ($sums as $i => $sum)
            $parts[] = ['from' => $start + $i * 900, 'avg' => $sum[1] ? (int)round($sum[0] / $sum[1]) : null, 'max' => $sum[2]];

        return $parts;
    }

    // ---- Changes in the commands -----------------------------------------------------
    // The command list of every answer is compared with the one before. What
    // changed goes to a log, newest last: ['time', 'added' => [key...],
    // 'removed' => [key...], 'changed' => [key => [field => [before, after]]]],
    // and the commands themselves to an index, so a removed one keeps its module
    // and description. A key is the module prefix and the name, e.g. "pw daily".

    const COMMAND_FIELDS = ['description' => 'opis', 'aliases' => 'aliasy', 'parameters' => 'parametry', 'example' => 'przykład'];

    // the address of a command on cmd/, e.g. "pw-daily"
    function commandSlug($key)
    {
        $key = function_exists('mb_strtolower') ? mb_strtolower($key, 'UTF-8') : strtolower($key);
        $slug = trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', $key), '-');

        return $slug === '' || strpos($slug, 'module-') === 0 ? 'cmd-' . $slug : $slug;
    }

    // every command of an API answer as key => [module, name, description, aliases, parameters, example]
    function botCommandIndex($data)
    {
        $index = [];
        foreach ($data['modules'] ?? [] as $module) {
            foreach ($module['subModules'] ?? [] as $submodule) {
                $prefix = trim((string)($submodule['prefix'] ?? ''));
                foreach ($submodule['commands'] ?? [] as $command) {
                    $name = (string)($command['name'] ?? '');
                    $aliases = array_values(array_diff(array_map('strval', $command['aliases'] ?? []), [$name]));
                    $parameters = [];
                    foreach ($command['attributes'] ?? [] as $attr)
                        $parameters[] = (string)($attr['description'] ?? $attr['name'] ?? '');
                    $index[trim($prefix . ' ' . $name)] = [
                        'module' => (string)($module['name'] ?? ''),
                        'name' => $name,
                        'description' => (string)($command['description'] ?? ''),
                        'aliases' => implode(', ', $aliases),
                        'parameters' => implode(', ', $parameters),
                        'example' => trim($name . ' ' . ($command['example'] ?? ''))
                    ];
                }
            }
        }

        return $index;
    }

    function botTrackCommands($data, $now)
    {
        $fp = @fopen(botFile('commands-index.json'), 'c+');
        if ($fp === false || !flock($fp, LOCK_EX)) {
            if ($fp !== false)
                fclose($fp);
            return;
        }

        $stored = json_decode((string)stream_get_contents($fp), true);
        $index = botCommandIndex($data);
        $old = $stored['commands'] ?? null;

        $change = ['time' => $now, 'added' => [], 'removed' => [], 'changed' => []];
        if (is_array($old)) {
            $change['added'] = array_values(array_diff(array_keys($index), array_keys($old)));
            $change['removed'] = array_values(array_diff(array_keys($old), array_keys($index)));
            foreach ($index as $key => $command) {
                if (!isset($old[$key]))
                    continue;
                foreach (array_keys(COMMAND_FIELDS) as $field)
                    if (($old[$key][$field] ?? '') !== $command[$field])
                        $change['changed'][$key][$field] = [$old[$key][$field] ?? '', $command[$field]];
            }
        }
        $changed = $change['added'] || $change['removed'] || $change['changed'];

        // an answer missing most commands at once looks like a broken one, not a change
        $broken = is_array($old) && count($old) > 10 && count($change['removed']) > count($old) / 2;

        if (!$broken && (!is_array($old) || $changed)) {
            if ($changed) {
                $log = json_decode((string)@file_get_contents(botFile('commands-changes.json')), true);
                $log = is_array($log) ? $log : [];
                // a removed command is described from the old index
                foreach ($change['removed'] as $key)
                    $change['gone'][$key] = $old[$key];
                $log[] = $change;
                botWriteFile(botFile('commands-changes.json'), json_encode(array_slice($log, -BOT_CHANGES_KEEP), JSON_UNESCAPED_UNICODE));
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode(['since' => $stored['since'] ?? $now, 'commands' => $index], JSON_UNESCAPED_UNICODE));
        }
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    // the logged changes, newest first
    function botCommandChanges()
    {
        $log = json_decode((string)@file_get_contents(botFile('commands-changes.json')), true);

        return is_array($log) ? array_reverse($log) : [];
    }

    // since when changes are watched, or null
    function botCommandsWatchedSince()
    {
        $stored = json_decode((string)@file_get_contents(botFile('commands-index.json')), true);

        return $stored['since'] ?? null;
    }

    // ---- Notice and maintenance ----------------------------------------------------
    // The panel sets one notice: ['text', 'to' => when it disappears or null,
    // 'maintenance' => ['from', 'to'] or null, 'by', 'set']. A maintenance break
    // is also kept in a list of its own, so the outages during it stay marked as
    // planned after the notice is gone.

    // the notice to show now, or null
    function botNotice()
    {
        $notice = json_decode((string)@file_get_contents(botFile('notice.json')), true);
        if (!is_array($notice) || ($notice['text'] ?? '') === '')
            return null;
        if (!empty($notice['to']) && $notice['to'] <= time())
            return null;

        return $notice;
    }

    // $notice null removes it; a break that has not started yet goes away with it,
    // one that is going on ends now
    function botSaveNotice($notice)
    {
        $old = json_decode((string)@file_get_contents(botFile('notice.json')), true);
        $windows = botMaintenanceWindows();
        $now = time();

        if (is_array($old) && !empty($old['maintenance'])) {
            $id = $old['set'];
            foreach ($windows as $i => $window) {
                if (($window['id'] ?? null) !== $id)
                    continue;
                if ($window['from'] > $now)
                    unset($windows[$i]);
                else if ($window['to'] > $now)
                    $windows[$i]['to'] = $now;
            }
        }
        if ($notice !== null && !empty($notice['maintenance']))
            $windows[] = ['id' => $notice['set'], 'from' => $notice['maintenance']['from'], 'to' => $notice['maintenance']['to']];

        botWriteFile(botFile('maintenance.json'), json_encode(array_slice(array_values($windows), -BOT_MAINTENANCE_KEEP)));
        if ($notice === null)
            @unlink(botFile('notice.json'));
        else
            botWriteFile(botFile('notice.json'), json_encode($notice, JSON_UNESCAPED_UNICODE));
    }

    // planned breaks as ['id', 'from', 'to'], oldest first
    function botMaintenanceWindows()
    {
        $windows = json_decode((string)@file_get_contents(botFile('maintenance.json')), true);

        return is_array($windows) ? $windows : [];
    }

    // whether the time falls in a planned break
    function botInMaintenance($time)
    {
        foreach (botMaintenanceWindows() as $window)
            if ($time >= $window['from'] && $time < $window['to'])
                return true;

        return false;
    }

    // "4.10 22:00–23:00", the date again when it ends on another day
    function botTimeRange($from, $to)
    {
        return date('j.m H:i', $from) . (date('Y-m-d', $from) === date('Y-m-d', $to) ? '–' . date('H:i', $to) : ' – ' . date('j.m H:i', $to));
    }

    // the notice as visitors see it, a planned break's time first
    function noticeText($notice)
    {
        if (empty($notice['maintenance']))
            return $notice['text'];

        return 'Przerwa techniczna ' . botTimeRange($notice['maintenance']['from'], $notice['maintenance']['to']) . '. ' . $notice['text'];
    }

    // how long something took: "27 min", "3 godz. 5 min", "2 dni 4 godz."
    function duration($seconds)
    {
        $minutes = max(1, (int)round($seconds / 60));
        if ($minutes < 60)
            return $minutes . ' min';

        $hours = intdiv($minutes, 60);
        if ($hours < 24)
            return $hours . ' godz.' . ($minutes % 60 ? ' ' . ($minutes % 60) . ' min' : '');

        $days = intdiv($hours, 24);
        return $days . ' ' . ($days === 1 ? 'dzień' : 'dni') . ($hours % 24 ? ' ' . ($hours % 24) . ' godz.' : '');
    }

    // when the outage going on now started, or null while the bot answers
    function botDownSince()
    {
        $incidents = botIncidents(0);

        return isset($incidents[0]) && $incidents[0][1] === null ? $incidents[0][0] : null;
    }

    // " od 14:05 (23 min)" for a bot that does not answer, otherwise empty
    function botDownText($state)
    {
        $since = $state['status'] === 'offline' ? botDownSince() : null;
        if ($since === null)
            return '';

        return ' od ' . date(date('Y-m-d', $since) === date('Y-m-d') ? 'H:i' : 'j.m H:i', $since) . ' (' . duration(time() - $since) . ')';
    }

    // the checks of the last 24 h as [time, online, milliseconds], oldest first
    function botHistory()
    {
        $history = [];
        $lines = @file(botFile('status-history.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $check = botParseCheck($line);
            if ($check && $check[0] > time() - BOT_HISTORY_SPAN)
                $history[] = $check;
        }

        return $history;
    }

    // GET with a 5 s limit: [body or false, HTTP status or 0, milliseconds]
    function botFetch($url)
    {
        $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 5, 'ignore_errors' => true]]);
        $started = microtime(true);
        $body = @file_get_contents($url, false, $context);
        $ms = (int)round((microtime(true) - $started) * 1000);

        $status = 0;
        foreach ($http_response_header ?? [] as $line)
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $match))
                $status = (int)$match[1];

        return [$body, $status, $ms];
    }

    // The bot's own report: [online, Discord ping, health data], or null when
    // this bot has no api/health yet. Not answering at all means offline.
    function botAskHealth()
    {
        [$body, $status] = botFetch(BOT_HEALTH_URL);
        if ($body === false && $status === 0)
            return [false, null, null];

        $health = @json_decode((string)$body, true);
        if (!is_array($health) || !isset($health['status']))
            return $status === 404 ? null : [false, null, null];

        $online = $health['status'] !== 'down' && ($health['discord']['state'] ?? '') === 'Connected';

        return [$online, $online ? (int)($health['discord']['latencyMs'] ?? 0) : null, $health];
    }

    // the command list from the API: [answered with commands, milliseconds]; kept for cmd/
    function botFetchCommands($now)
    {
        [$json, , $ms] = botFetch(BOT_API_URL);
        $data = $json === false ? null : @json_decode($json, true);
        if (empty($data['modules']))
            return [false, $ms];

        botWriteFile(botFile('commands.json'), $json);
        botTrackCommands($data, $now);

        return [true, $ms];
    }

    // the last health report of the bot, or null (no api/health, or not answered)
    function botHealth()
    {
        $health = json_decode((string)@file_get_contents(botFile('health.json')), true);

        return is_array($health) ? $health : null;
    }

    // what is wrong while the bot is connected, from its health report, in Polish
    function botIssues($health)
    {
        if (!$health || ($health['status'] ?? '') !== 'degraded')
            return [];

        $issues = [];
        if (isset($health['database']['ok']) && !$health['database']['ok'])
            $issues[] = 'baza danych nie odpowiada';
        if (isset($health['shinden']['ok']) && !$health['shinden']['ok'])
            $issues[] = 'Shinden nie odpowiada';
        if (($health['discord']['latencyMs'] ?? 0) > 1000)
            $issues[] = 'wysoki ping do Discorda (' . str_replace('.', ',', (string)round($health['discord']['latencyMs'] / 1000, 1)) . ' s)';
        if (($health['commands']['rejected5min'] ?? 0) > 0)
            $issues[] = 'odrzuca polecenia (pełna kolejka)';

        return $issues ?: ['bot zgłasza problemy'];
    }

    // ['status' => online|idle|offline, 'uptime' => percent, 'checked' => time,
    // 'ms' => Discord ping (API answer time without api/health), 'issues' => [...]],
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
            $report = botAskHealth();
            if ($report === null) {
                // a bot without api/health: getting the command list means it is up
                @unlink(botFile('health.json'));
                $health = null;
                [$online, $ms] = botFetchCommands($now);
            } else {
                [$online, $ms, $health] = $report;
                if ($health !== null)
                    botWriteFile(botFile('health.json'), json_encode($health));
                else
                    @unlink(botFile('health.json'));

                $commands = botFile('commands.json');
                if ($online && (!is_file($commands) || $now - filemtime($commands) >= BOT_COMMANDS_TTL))
                    botFetchCommands($now);
            }

            $shinden = $online && isset($health['shinden']['latencyMs']) ? (int)$health['shinden']['latencyMs'] : null;
            $uptime = botRecordCheck($online, $now, $online ? $ms : null, $shinden);
            botRecordDay($online, $now);
            botRecordIncident($online, $now);

            $issues = $online ? botIssues($health) : [];
            if (!$online)
                $status = 'offline';
            else if ($issues || $uptime < BOT_MIN_UPTIME)
                $status = 'idle';
            else
                $status = 'online';

            $state = [
                'status' => $status,
                // rounded down, so 98.96 is not shown as 99 next to the idle status
                'uptime' => floor($uptime * 10) / 10,
                'checked' => $now,
                'ms' => $online ? $ms : null,
                'issues' => $issues
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
