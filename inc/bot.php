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
    // Changes of the version the bot reports are logged for state/, which shows
    // since when each one ran.
    // The bot's api/health says whether it is connected to Discord, its ping and
    // the state of its database and of Shinden. While a bot without it answers
    // 404, the command list is the check, as before. With the site's key
    // (BOT_APP_KEY, inc/auth.php) the moderator and debug commands are fetched
    // as often as the public ones, for the accounts that may see them on cmd/.
    // The bot also sends the same report here itself every minute (alive/, with
    // BOT_HEARTBEAT_SECRET); while that is fresh the API is not asked for it, so
    // the site sees the bot also when its API cannot be reached, and asks
    // api/health only when the reports stop coming.
    require_once __DIR__ . '/auth.php';

    // The bot API addresses, on the base of botApiBase() (inc/auth.php), which
    // BOT_API_BASE in inc/config.php may point at a test bot.
    function botHealthUrl()
    {
        return botApiBase() . '/api/health';
    }

    function botCommandsUrl()
    {
        return botApiBase() . '/api/Info/commands';
    }

    function botPrivateUrl()
    {
        return botApiBase() . '/api/Info/commands/private';
    }

    function botApiAliveUrl()
    {
        return botApiBase() . '/api/alive';
    }
    // the bot API is asked at most this often after the bot's reports
    const BOT_API_CHECK_EVERY = 50;
    // a failed API check this recent makes the bot "idle" with an issue
    const BOT_API_ISSUE_FOR = 300;
    // with api/health the command list (cmd/ and its changes) is fetched only this often
    const BOT_COMMANDS_TTL = 600;
    const BOT_CACHE_TTL = 60;
    // cron refreshes the state every minute, so a page asks the API itself only
    // when it is older than this (cron late or stopped)
    const BOT_STALE_AFTER = 150;
    // The bot sends its report every minute, trying up to 3 times in about 20 s
    // when the site does not answer. Once the last one is older than this, one
    // report is missing: the bot check (cron, every minute) asks api/health
    // instead, and goes back to the reports when they come again.
    const BOT_HEARTBEAT_FRESH = 90;
    // the addresses the reports came from are kept this long, and noted again
    // at most this often
    const BOT_ADDRESS_KEEP = 90 * 86400;
    const BOT_ADDRESS_NOTE_EVERY = 3600;
    const BOT_HISTORY_SPAN = 86400;
    const BOT_MIN_UPTIME = 99.0;
    const BOT_DAYS_KEEP = 400;
    const BOT_INCIDENTS_KEEP = 200;
    const BOT_MAINTENANCE_KEEP = 100;
    const BOT_CHANGES_KEEP = 300;
    // version changes kept for the log on state/
    const BOT_VERSIONS_KEEP = 50;
    // outages less than this apart are shown as one
    const BOT_INCIDENT_MERGE = 1800;

    // days, months and the times on the pages follow Polish time, not the server's
    date_default_timezone_set('Europe/Warsaw');

    // Kept in inc/data, which the web server and cron share (PHP-FPM often has
    // its own private /tmp) and which outlives a restart; /tmp only when the
    // folder cannot be written.
    function botFile($name)
    {
        $dir = dataDir();
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
    function botRecordCheck($online, $now, $ms = null, $shinden = null, $name = 'status-history.txt')
    {
        $history = [];
        $fp = @fopen(botFile($name), 'c+');
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

        // checks during a planned break do not count, as its outages do not
        $windows = botMaintenanceWindows();
        $counted = 0;
        $up = 0;
        foreach ($history as $entry) {
            if (botInMaintenance($entry[0], $windows))
                continue;
            $counted++;
            if ($entry[1])
                $up++;
        }

        return $counted ? 100 * $up / $counted : 100;
    }

    // The last 24 h in 96 parts of 15 minutes, oldest first; each is ['from' =>
    // time, 'checks', 'down' => checks without an answer, 'planned' => those of
    // them during a planned break, 'state']. The state is null without checks,
    // 'ok' when all answered, 'planned' when only checks in a planned break did
    // not, 'warn' when less than half did not and 'fail' from half on, so one
    // missed minute does not paint the whole quarter of an hour red.
    function botTimeline($history)
    {
        $start = time() - BOT_HISTORY_SPAN;
        $windows = botMaintenanceWindows();
        $parts = [];
        for ($i = 0; $i < 96; $i++)
            $parts[] = ['from' => $start + $i * 900, 'checks' => 0, 'down' => 0, 'planned' => 0, 'state' => null];

        foreach ($history as $check) {
            $i = min(95, max(0, (int)floor(($check[0] - $start) / 900)));
            $parts[$i]['checks']++;
            if (!$check[1]) {
                $parts[$i]['down']++;
                if (botInMaintenance($check[0], $windows))
                    $parts[$i]['planned']++;
            }
        }

        foreach ($parts as &$part) {
            $unplanned = $part['down'] - $part['planned'];
            if (!$part['checks'])
                continue;
            else if (!$part['down'])
                $part['state'] = 'ok';
            else if (!$unplanned)
                $part['state'] = 'planned';
            else
                $part['state'] = $unplanned * 2 < $part['checks'] ? 'warn' : 'fail';
        }
        unset($part);

        return $parts;
    }

    // Counts the checks per day: ['Y-m-d' => [checks, online checks]], without
    // the checks during a planned break. Today is counted again from the 24 h
    // history at every check, so the day always says what the 24 h bar says;
    // only when the history does not reach back to midnight (the day the clocks
    // go back has 25 hours) the check is added instead. The first time, every
    // day the history reaches is filled from it.
    function botRecordDay($online, $now, $name = 'days.json', $historyName = 'status-history.txt')
    {
        $fp = @fopen(botFile($name), 'c+');
        if ($fp === false || !flock($fp, LOCK_EX)) {
            if ($fp !== false)
                fclose($fp);
            return;
        }

        $days = json_decode((string)stream_get_contents($fp), true);
        $first = !is_array($days);
        $days = $first ? [] : $days;
        $today = date('Y-m-d', $now);
        $history = botHistory($historyName);
        $whole = isset($history[0]) && $history[0][0] - 120 <= strtotime('today', $now);

        if ($first || $whole) {
            // the history already has this check in it
            $windows = botMaintenanceWindows();
            $counted = [];
            foreach ($history as $check) {
                $day = date('Y-m-d', $check[0]);
                if (($first || $day === $today) && !botInMaintenance($check[0], $windows))
                    $counted[$day] = [($counted[$day][0] ?? 0) + 1, ($counted[$day][1] ?? 0) + ($check[1] ? 1 : 0)];
            }
            unset($days[$today]);
            foreach ($counted as $day => $count)
                $days[$day] = $count;
        } else if (!botInMaintenance($now)) {
            $days[$today] = [($days[$today][0] ?? 0) + 1, ($days[$today][1] ?? 0) + ($online ? 1 : 0)];
        }

        ksort($days);
        $days = array_slice($days, -BOT_DAYS_KEEP, null, true);

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($days));
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    function botDays($name = 'days.json')
    {
        $days = json_decode((string)@file_get_contents(botFile($name)), true);

        return is_array($days) ? $days : [];
    }

    // The last $count days, oldest first; each is ['from' => time, 'checks', 'up']
    function botDailyParts($count, $name = 'days.json')
    {
        $days = botDays($name);
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

    // the outages as they were recorded, [start, end or null], oldest first
    function botRawIncidents()
    {
        $incidents = json_decode((string)@file_get_contents(botFile('incidents.json')), true);

        return is_array($incidents) ? $incidents : [];
    }

    // whether an outage overlaps a planned break
    function botIncidentPlanned($start, $end, $windows = null)
    {
        $end = $end ?? time();
        foreach ($windows ?? botMaintenanceWindows() as $window)
            if ($start < $window['to'] && $end >= $window['from'])
                return true;

        return false;
    }

    // Outages that lasted into the time since $since or still last, newest first,
    // as [start, end or null, how many outages, seconds down since $since,
    // planned]. Outages less than BOT_INCIDENT_MERGE apart are one, the time the
    // bot answered between them not counted as down; a planned break and an
    // outage outside it stay apart.
    function botIncidents($since)
    {
        $windows = botMaintenanceWindows();
        $now = time();
        $merged = [];
        foreach (botRawIncidents() as [$start, $end]) {
            $planned = botIncidentPlanned($start, $end, $windows);
            $down = max(0, ($end ?? $now) - max($start, $since));
            $last = count($merged) - 1;
            if ($last >= 0 && $merged[$last][1] !== null && $start - $merged[$last][1] <= BOT_INCIDENT_MERGE && $merged[$last][4] === $planned) {
                $merged[$last][1] = $end;
                $merged[$last][2]++;
                $merged[$last][3] += $down;
            } else {
                $merged[] = [$start, $end, 1, $down, $planned];
            }
        }

        $recent = [];
        foreach ($merged as $incident)
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
    // and description. A key is the module prefix and the name, e.g. "pw daily";
    // the second command of the same name (the bot has a few with other
    // parameters) is "zgłoś #2", the third "zgłoś #3", in the order of the list.

    const COMMAND_FIELDS = ['description' => 'opis', 'aliases' => 'aliasy', 'parameters' => 'parametry', 'example' => 'przykład'];

    // what the parameters are joined with; their descriptions have commas of their own
    const COMMAND_PARAMETERS_GLUE = ' · ';

    // the version of commands-index.json; an index of an older one is written
    // again without logging the difference as a change (2: the keys of commands
    // of the same name, the parameters joined with COMMAND_PARAMETERS_GLUE)
    const COMMAND_INDEX_FORMAT = 2;

    // the address of a command on cmd/, e.g. "pw-daily"; a dash the name ends
    // with stays, so "karta-" does not take the address of "karta"
    function commandSlug($key)
    {
        $key = lower($key);
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $key);
        $slug = substr($key, -1) === '-' ? ltrim($slug, '-') : trim($slug, '-');

        return $slug === '' || strpos($slug, 'module-') === 0 ? 'cmd-' . $slug : $slug;
    }

    // The key of a command, "pw daily", or "zgłoś #2" for the second of the same
    // name; $seen counts the names met so far, in the order of the list
    function commandKey($prefix, $name, &$seen)
    {
        $key = trim($prefix . ' ' . $name);
        $seen[$key] = ($seen[$key] ?? 0) + 1;

        return $seen[$key] > 1 ? $key . ' #' . $seen[$key] : $key;
    }

    // every command of an API answer as key => [module, name, description, aliases, parameters, example]
    function botCommandIndex($data)
    {
        $index = [];
        $seen = [];
        foreach ($data['modules'] ?? [] as $module) {
            foreach ($module['subModules'] ?? [] as $submodule) {
                $prefix = trim((string)($submodule['prefix'] ?? ''));
                foreach ($submodule['commands'] ?? [] as $command) {
                    $name = (string)($command['name'] ?? '');
                    $aliases = array_values(array_diff(array_map('strval', $command['aliases'] ?? []), [$name]));
                    $parameters = [];
                    foreach ($command['attributes'] ?? [] as $attr)
                        $parameters[] = (string)($attr['description'] ?? $attr['name'] ?? '');
                    $index[commandKey($prefix, $name, $seen)] = [
                        'module' => (string)($module['name'] ?? ''),
                        'name' => $name,
                        'description' => (string)($command['description'] ?? ''),
                        'aliases' => implode(', ', $aliases),
                        'parameters' => implode(COMMAND_PARAMETERS_GLUE, $parameters),
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
        // an index of an older format differs from the new one by its keys and
        // texts, not by what the bot changed, so it is only written again
        $old = ($stored['format'] ?? 1) === COMMAND_INDEX_FORMAT ? $stored['commands'] ?? null : null;

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
            fwrite($fp, json_encode(['since' => $stored['since'] ?? $now, 'format' => COMMAND_INDEX_FORMAT, 'commands' => $index], JSON_UNESCAPED_UNICODE));
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

    // whether the time falls in a planned break; $windows, read once, saves
    // reading them for every check of a loop
    function botInMaintenance($time, $windows = null)
    {
        foreach ($windows ?? botMaintenanceWindows() as $window)
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
        $incidents = botRawIncidents();
        $last = end($incidents);

        return $last !== false && $last[1] === null ? $last[0] : null;
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
    function botHistory($name = 'status-history.txt')
    {
        $history = [];
        $lines = @file(botFile($name), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
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
        $started = microtime(true);
        [$body, $status] = httpRaw($url, ['method' => 'GET', 'timeout' => 5, 'ignore_errors' => true]);
        $ms = (int)round((microtime(true) - $started) * 1000);

        return [$body, $status, $ms];
    }

    // The bot's own report: [online, Discord ping, health data], or null when
    // this bot has no api/health yet. Not answering at all means offline.
    function botAskHealth()
    {
        [$body, $status, $ms] = botFetch(botHealthUrl());
        $health = $body === false ? null : @json_decode((string)$body, true);
        $answered = is_array($health) && isset($health['status']);
        // an answer of the bot itself means its API works
        botApiRecord($answered || $status === 404, $ms, time());

        if (!$answered)
            return $status === 404 ? null : [false, null, null];

        return botReadHealth($health);
    }

    // ---- Shinden and the database ----------------------------------------------------
    // Whether they answered the bot, by its report, at every check while the
    // bot is up, each in a 24 h history of its own, "time ok [ms]". Without
    // the bot nothing is known about them, so those checks are missing.
    const BOT_DEPENDENCIES = ['shinden' => 'shinden-history.txt', 'database' => 'database-history.txt'];

    function botRecordDependencies($health, $now)
    {
        foreach (BOT_DEPENDENCIES as $key => $name) {
            if (!isset($health[$key]['ok']))
                continue;
            $ok = (bool)$health[$key]['ok'];
            $ms = $ok && isset($health[$key]['latencyMs']) ? (int)$health[$key]['latencyMs'] : null;
            botRecordCheck($ok, $now, $ms, null, $name);
        }
    }

    // the checks of 'shinden' or 'database' in the last 24 h, as botHistory() gives them
    function botDependencyHistory($key)
    {
        return botHistory(BOT_DEPENDENCIES[$key]);
    }

    // ---- The bot API -----------------------------------------------------------------
    // Whether the bot API answers from outside, apart from whether the bot
    // works: after every report the bot sends to alive/ the site asks
    // api/alive (at most every BOT_API_CHECK_EVERY), and while the reports do
    // not come the bot check's own api/health request counts. Every check goes
    // to a 24 h history of its own, "time up [ms]", and is counted per day, as
    // the bot's checks are.

    function botApiRecord($up, $ms, $now)
    {
        botWriteFile(botFile('api-checked.txt'), (string)$now);
        botRecordCheck($up, $now, $up ? $ms : null, null, 'api-history.txt');
        botRecordDay($up, $now, 'api-days.json', 'api-history.txt');
    }

    // asks api/alive, when it was not asked in the last BOT_API_CHECK_EVERY
    function botApiCheck($now)
    {
        $lock = @fopen(botFile('api.lock'), 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock !== false)
                fclose($lock);
            return;
        }

        if ($now - (int)@file_get_contents(botFile('api-checked.txt')) >= BOT_API_CHECK_EVERY) {
            [$body, $status, $ms] = botFetch(botApiAliveUrl());
            botApiRecord($status === 200 && trim((string)$body) === 'ok', $ms, $now);
        }
        fclose($lock);
    }

    // the checks of the bot API in the last 24 h, as botHistory() gives them
    function botApiHistory()
    {
        return botHistory('api-history.txt');
    }

    // the last API check as [time, answered, ms or null], or null when never
    function botApiLast()
    {
        $history = botApiHistory();

        return $history ? end($history) : null;
    }

    // the API did not answer its last check, made less than BOT_API_ISSUE_FOR ago
    function botApiDown($now)
    {
        $last = botApiLast();

        return $last !== null && !$last[1] && $now - $last[0] < BOT_API_ISSUE_FOR;
    }

    // [online, Discord ping, health data] of a health report
    function botReadHealth($health)
    {
        $online = $health['status'] !== 'down' && ($health['discord']['state'] ?? '') === 'Connected';

        return [$online, $online ? (int)($health['discord']['latencyMs'] ?? 0) : null, $health];
    }

    // the secret the bot sends its reports to alive/ with, or '' without one
    function botHeartbeatSecret()
    {
        authConfigured();

        return defined('BOT_HEARTBEAT_SECRET') ? (string)BOT_HEARTBEAT_SECRET : '';
    }

    // a report the bot sent to alive/, kept as ['received' => time, 'health' => report]
    function botSaveHeartbeat($health, $now)
    {
        botWriteFile(botFile('heartbeat.json'), json_encode(['received' => $now, 'health' => $health]));
    }

    // The addresses the bot's reports came from, as [ip => last time]. They are
    // never blocked, by the panel or automatically (inc/autoblock.php).
    function botAddresses()
    {
        $addresses = json_decode((string)@file_get_contents(botFile('addresses.json')), true);

        return is_array($addresses) ? $addresses : [];
    }

    function botNoteAddress($ip, $now)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false)
            return;

        $addresses = botAddresses();
        if (isset($addresses[$ip]) && $now - $addresses[$ip] < BOT_ADDRESS_NOTE_EVERY)
            return;

        $addresses[$ip] = $now;
        foreach ($addresses as $address => $time)
            if ($time < $now - BOT_ADDRESS_KEEP)
                unset($addresses[$address]);
        arsort($addresses);
        botWriteFile(botFile('addresses.json'), json_encode($addresses));
    }

    // whether the bot's reports came from this address (or its IPv6 /64)
    function botIsAddress($ip)
    {
        foreach (array_keys(botAddresses()) as $address)
            if (sameAddress($ip, (string)$address))
                return true;

        return false;
    }

    // when the bot last sent its report, or null when never
    function botHeartbeatTime()
    {
        $beat = json_decode((string)@file_get_contents(botFile('heartbeat.json')), true);

        return isset($beat['received']) ? (int)$beat['received'] : null;
    }

    // the report the bot sent less than BOT_HEARTBEAT_FRESH ago, as botAskHealth
    // gives it, or null when there is none that fresh
    function botHeartbeatReport($now)
    {
        if (botHeartbeatSecret() === '')
            return null;

        $beat = json_decode((string)@file_get_contents(botFile('heartbeat.json')), true);
        if (!isset($beat['received'], $beat['health']['status']) || $now - (int)$beat['received'] >= BOT_HEARTBEAT_FRESH)
            return null;

        return botReadHealth($beat['health']);
    }

    // the command list from the API: [answered with commands, milliseconds]; kept for cmd/
    function botFetchCommands($now)
    {
        [$json, , $ms] = botFetch(botCommandsUrl());
        $data = $json === false ? null : @json_decode($json, true);
        if (empty($data['modules']))
            return [false, $ms];

        botWriteFile(botFile('commands.json'), $json);
        botTrackCommands($data, $now);

        return [true, $ms];
    }

    // The moderator and debug commands, every BOT_COMMANDS_TTL while the bot is
    // up and the site has its key. A failed try keeps the last list and waits as long.
    function botFetchPrivateCommands($now)
    {
        $file = botFile('commands-private.json');
        if (botAppKey() === '' || (is_file($file) && $now - filemtime($file) < BOT_COMMANDS_TTL))
            return;

        $data = botAppGet(botPrivateUrl(), 5);
        if (isset($data['modules']))
            botWriteFile($file, json_encode($data, JSON_UNESCAPED_UNICODE));
        else if (is_file($file))
            @touch($file);
    }

    // When the moderator and debug commands were last fetched, or null
    function botPrivateCommandsTime()
    {
        $file = botFile('commands-private.json');

        return is_file($file) ? filemtime($file) : null;
    }

    // The modules of the moderator and debug commands. A command that is also in
    // the public list is left out, so it is not shown twice.
    function botPrivateModules()
    {
        $data = json_decode((string)@file_get_contents(botFile('commands-private.json')), true);
        if (empty($data['modules']))
            return [];

        $public = botCommandIndex(botCommands() ?? []);
        $modules = [];
        foreach ($data['modules'] as $module) {
            $submodules = [];
            foreach ($module['subModules'] ?? [] as $submodule) {
                $prefix = trim((string)($submodule['prefix'] ?? ''));
                $commands = [];
                foreach ($submodule['commands'] ?? [] as $command) {
                    $name = (string)($command['name'] ?? '');
                    if (!isset($public[trim($prefix . ' ' . $name)]))
                        $commands[] = [
                            'name' => $name,
                            'description' => (string)($command['description'] ?? ''),
                            'aliases' => array_map('strval', $command['aliases'] ?? []),
                            'attributes' => $command['attributes'] ?? [],
                            'example' => (string)($command['example'] ?? '')
                        ];
                }
                if ($commands)
                    $submodules[] = ['prefix' => $prefix, 'prefixAliases' => $submodule['prefixAliases'] ?? [], 'commands' => $commands];
            }
            if ($submodules)
                $modules[] = ['name' => (string)($module['name'] ?? ''), 'subModules' => $submodules];
        }

        return $modules;
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

    // ---- Versions --------------------------------------------------------------------
    // The version from the bot's own report (api/health or the heartbeat). Every
    // time it changes, the change is logged, so state/ can show since when each
    // version ran. The bot's startedAt is used as the moment the version started
    // when it gives one (also after the site has not answered for a while),
    // otherwise the moment the check noticed it.

    function botRecordVersion($version, $now, $startedAt = null)
    {
        $version = trim((string)$version);
        if ($version === '')
            return;

        $fp = @fopen(botFile('versions.json'), 'c+');
        if ($fp === false || !flock($fp, LOCK_EX)) {
            if ($fp !== false)
                fclose($fp);
            return;
        }

        $versions = json_decode((string)stream_get_contents($fp), true);
        $versions = is_array($versions) ? $versions : [];
        $last = $versions ? $versions[count($versions) - 1] : null;

        if (($last['version'] ?? null) !== $version) {
            $started = $startedAt ? strtotime((string)$startedAt) : false;
            // a startedAt from the future or from before the previous change is not believed
            if ($started === false || $started > $now + 60 || ($last !== null && $started <= $last['since']))
                $started = $now;
            $versions[] = ['version' => $version, 'since' => $started, 'seen' => $now];
            $versions = array_slice($versions, -BOT_VERSIONS_KEEP);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($versions, JSON_UNESCAPED_UNICODE));
        }
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    // the version changes, newest first, as ['version', 'since' => when it
    // started, 'seen' => when the site first noticed it]
    function botVersions()
    {
        $versions = json_decode((string)@file_get_contents(botFile('versions.json')), true);

        return is_array($versions) ? array_reverse($versions) : [];
    }

    // the cached state, or null when there is none
    function botCachedState($file)
    {
        $state = is_file($file) ? @json_decode(@file_get_contents($file), true) : null;

        return is_array($state) && isset($state['status'], $state['uptime'], $state['checked']) ? $state : null;
    }

    // ['status' => online|idle|offline, 'uptime' => percent, 'checked' => time,
    // 'ms' => Discord ping (API answer time without api/health), 'issues' => [...]].
    // Cron asks the API every minute ($force). A page asks it itself only when
    // the cached state is older than BOT_STALE_AFTER (cron has stopped), and
    // only one request at a time: the others meanwhile get the old state, so a
    // slow or silent bot cannot hold many PHP workers at once.
    function botState($force = false)
    {
        static $state = null;
        if ($state !== null && !$force)
            return $state;

        $now = time();
        $file = botFile('status.json');
        $state = $force ? null : botCachedState($file);
        if ($state !== null && $now - $state['checked'] < BOT_STALE_AFTER)
            return $state;

        // without any state to show the request waits for the one asking
        $lock = @fopen(botFile('status.lock'), 'c');
        if ($lock !== false && !flock($lock, $state === null ? LOCK_EX : LOCK_EX | LOCK_NB)) {
            fclose($lock);
            if ($state !== null)
                return $state;
            $lock = false;
        }
        // the request that held the lock may have just asked
        $now = time();
        if (!$force) {
            $fresh = botCachedState($file);
            if ($fresh !== null && $now - $fresh['checked'] < BOT_STALE_AFTER) {
                if ($lock !== false)
                    fclose($lock);
                return $state = $fresh;
            }
        }

        $report = botHeartbeatReport($now) ?? botAskHealth();
        if ($report === null) {
            // a bot without api/health: getting the command list means it is up
            @unlink(botFile('health.json'));
            $health = null;
            [$online, $ms] = botFetchCommands($now);
        } else {
            [$online, $ms, $health] = $report;
            if ($health !== null) {
                botWriteFile(botFile('health.json'), json_encode($health));
                botRecordVersion($health['version'] ?? '', $now, $health['startedAt'] ?? null);
            } else
                @unlink(botFile('health.json'));

            // a failed try keeps the last list and waits as long, so an API out
            // of reach is not asked every minute while the reports keep coming
            $tried = botFile('commands-tried.txt');
            $last = max((int)@filemtime(botFile('commands.json')), (int)@filemtime($tried));
            if ($online && $now - $last >= BOT_COMMANDS_TTL && !botFetchCommands($now)[0])
                botWriteFile($tried, (string)$now);
        }
        if ($online)
            botFetchPrivateCommands($now);

        // a Shinden that did not answer has no answer time
        $shinden = $online && !empty($health['shinden']['ok']) && isset($health['shinden']['latencyMs']) ? (int)$health['shinden']['latencyMs'] : null;
        $uptime = botRecordCheck($online, $now, $online ? $ms : null, $shinden);
        if ($online)
            botRecordDependencies($health, $now);
        botRecordDay($online, $now);
        botRecordIncident($online, $now);

        $issues = $online ? botIssues($health) : [];
        if ($online && botApiDown($now))
            $issues[] = 'API bota nie odpowiada';
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

        if ($lock !== false)
            fclose($lock);

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
