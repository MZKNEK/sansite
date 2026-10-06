<?php
    // The bot status card of the public page state/: state, the bot's own
    // report, availability bars of the last 24 hours and 90 days, the Discord
    // ping and Shinden's answer time, and the outages of the last 90 days. The admin panel shows only statusSummary(). Needs
    // inc/bot.php, inc/services.php and the helpers in inc/gallery.php.

    const STATUS_LABELS = [
        'online' => 'działa',
        'idle' => 'działa, ale bywał niedostępny',
        'offline' => 'nie odpowiada',
        'maintenance' => 'ma przerwę techniczną'
    ];

    // the state to show: during a planned break it is maintenance, answering or not
    function shownStatus($state)
    {
        return botInMaintenance(time()) ? 'maintenance' : $state['status'];
    }

    // what follows "Bot": the state, what is wrong when the bot reports problems,
    // since when it does not answer
    function statusLabel($state)
    {
        $status = shownStatus($state);
        if ($status === 'idle' && !empty($state['issues']))
            return 'działa, ale ' . implode(', ', $state['issues']);

        return STATUS_LABELS[$status] . botDownText($state);
    }

    // "41 234": thousands split by a space, as in Polish
    function formatCount($n)
    {
        return number_format((int)$n, 0, ',', ' ');
    }

    // The bot's own report (api/health) in boxes: Discord, database, Shinden,
    // commands, and the bot API as the site sees it ($api from botApiLast())
    function healthDetails($health, $api = null)
    {
        $discord = $health['discord'] ?? [];
        $database = $health['database'] ?? [];
        $shinden = $health['shinden'] ?? [];
        $commands = $health['commands'] ?? [];
        $connected = ($discord['state'] ?? '') === 'Connected';
        $states = ['Connecting' => 'łączy się', 'Disconnecting' => 'rozłącza się', 'Disconnected' => 'rozłączony'];
        $percent = function ($value) { return str_replace('.', ',', (string)(float)$value) . '%'; };

        ob_start();
?>
        <div class="health">
          <div class="health-box <?=$connected ? 'ok' : 'fail'?>">
            <h3>Discord</h3>
            <b><?=$connected ? 'połączony' : e($states[$discord['state'] ?? ''] ?? 'rozłączony')?><?=$connected && isset($discord['latencyMs']) ? ' &middot; ping ' . e(milliseconds((int)$discord['latencyMs'])) : ''?></b>
<?php if (!empty($discord['connectedAt'])): ?>
            <span>połączony od <?=e(date('j.m H:i', strtotime($discord['connectedAt'])))?></span>
<?php endif; ?>
            <span>serwery: <?=e(formatCount($discord['guilds'] ?? 0))?> &middot; członkowie: <?=e(formatCount($discord['members'] ?? 0))?></span>
          </div>
<?php if ($database): ?>
          <div class="health-box <?=!empty($database['ok']) ? 'ok' : 'fail'?>">
            <h3>Baza danych</h3>
            <b><?=!empty($database['ok']) ? 'działa' : 'nie odpowiada'?><?=isset($database['latencyMs']) ? ' &middot; ' . e(milliseconds((int)$database['latencyMs'])) : ''?></b>
            <span>5 min: <?=e(formatCount($database['queries5min'] ?? 0))?> zapytań, średnio <?=e(milliseconds((int)($database['avgMs5min'] ?? 0)))?>, najdłużej <?=e(milliseconds((int)($database['maxMs5min'] ?? 0)))?></span>
            <span>błędy w 5 min: <?=e(formatCount($database['errors5min'] ?? 0))?></span>
          </div>
<?php endif; ?>
<?php if ($shinden): ?>
          <div class="health-box <?=!empty($shinden['ok']) ? 'ok' : 'fail'?>">
            <h3>Shinden</h3>
            <b><?=!empty($shinden['ok']) ? 'działa' : 'nie odpowiada'?><?=isset($shinden['latencyMs']) ? ' &middot; ' . e(milliseconds((int)$shinden['latencyMs'])) : ''?></b>
            <span>5 min: <?=e(formatCount($shinden['requests5min'] ?? 0))?> zapytań, błędy <?=e($percent($shinden['errorRate5min'] ?? 0))?><?=!empty($shinden['timeouts5min']) ? ' (' . e(formatCount($shinden['timeouts5min'])) . ' ' . plural((int)$shinden['timeouts5min'], 'timeout', 'timeouty', 'timeoutów') . ')' : ''?></span>
            <span>godzina: <?=e(formatCount($shinden['requestsHour'] ?? 0))?> zapytań, błędy <?=e($percent($shinden['errorRateHour'] ?? 0))?></span>
          </div>
<?php endif; ?>
<?php if ($api): ?>
          <div class="health-box <?=$api[1] ? 'ok' : 'fail'?>">
            <h3>API bota</h3>
            <b><?=$api[1] ? 'odpowiada' . ($api[2] !== null ? ' &middot; ' . e(milliseconds($api[2])) : '') : 'nie odpowiada'?></b>
            <span>sprawdzone <?=e(ago($api[0]))?> z tej strony</span>
          </div>
<?php endif; ?>
<?php if ($commands): ?>
          <div class="health-box <?=!empty($commands['rejected5min']) ? 'warn' : 'ok'?>">
            <h3>Polecenia</h3>
            <b><?=e(formatCount($commands['last5min'] ?? 0))?> w 5 min &middot; <?=e(formatCount($commands['lastHour'] ?? 0))?> w godzinę</b>
            <span>błędy: <?=e(formatCount($commands['errors5min'] ?? 0))?> w 5 min, <?=e(formatCount($commands['errorsHour'] ?? 0))?> w godzinę</span>
            <span>odrzucone (pełna kolejka): <?=e(formatCount($commands['rejected5min'] ?? 0))?> w 5 min, <?=e(formatCount($commands['rejectedHour'] ?? 0))?> w godzinę</span>
          </div>
<?php endif; ?>
        </div>
<?php
        return ob_get_clean();
    }

    // "230 ms", "1,4 s"
    function milliseconds($ms)
    {
        return $ms < 1000 ? $ms . ' ms' : str_replace('.', ',', (string)round($ms / 1000, 1)) . ' s';
    }

    // average time over the answered checks (field 2 the ping, 3 Shinden), or null
    function averageResponse($history, $field = 2)
    {
        $times = [];
        foreach ($history as $check)
            if ($check[1] && ($check[$field] ?? null) !== null)
                $times[] = $check[$field];

        return $times ? (int)round(array_sum($times) / count($times)) : null;
    }

    // the outage overlaps a planned break
    function plannedIncident($incident)
    {
        return $incident[4] ?? botIncidentPlanned($incident[0], $incident[1]);
    }

    function ago($time)
    {
        $seconds = time() - $time;
        if ($seconds < 60)
            return 'przed chwilą';
        if ($seconds < 3600)
            return floor($seconds / 60) . ' min temu';
        if ($seconds < 86400)
            return floor($seconds / 3600) . ' godz. temu';

        return date('d.m.Y H:i', $time);
    }

    // checks in the last hour; cron makes about 60, visits alone far fewer
    function checksLastHour($history)
    {
        $count = 0;
        foreach ($history as $check)
            if ($check[0] > time() - 3600)
                $count++;

        return $count;
    }

    const DAYS_SHOWN = 90;

    function percent($up, $checks)
    {
        return str_replace('.', ',', (string)(floor(1000 * $up / $checks) / 10)) . '%';
    }

    // colour of a day: fine, partly down, mostly down, or not checked
    function partClass($part)
    {
        if (!$part['checks'])
            return 'none';
        $uptime = 100 * $part['up'] / $part['checks'];

        return $uptime >= 99.5 ? 'ok' : ($uptime >= 95 ? 'warn' : 'fail');
    }

    // what a quarter of an hour of the 24 h bar says when pointed at
    function timelineLabel($part)
    {
        if ($part['state'] === null)
            return 'brak sprawdzeń';
        if ($part['state'] === 'ok')
            return 'działał';

        $label = $part['down'] . ' z ' . $part['checks'] . ' ' . plural($part['checks'], 'sprawdzenia', 'sprawdzeń', 'sprawdzeń') . ' bez odpowiedzi';
        if ($part['state'] === 'planned')
            return 'przerwa techniczna, ' . $label;

        return $label . ($part['planned'] ? ' (' . $part['planned'] . ' w przerwie technicznej)' : '');
    }

    function partTitle($label, $part)
    {
        if (!$part['checks'])
            return $label . ': brak sprawdzeń';

        return $label . ': ' . percent($part['up'], $part['checks']) . ' (' . $part['checks'] . ' '
            . plural($part['checks'], 'sprawdzenie', 'sprawdzenia', 'sprawdzeń') . ')';
    }

    // the percent of answered checks in a 24 h history, planned breaks not
    // counted, or a dash
    function historyUptime($history)
    {
        $windows = botMaintenanceWindows();
        $checks = 0;
        $up = 0;
        foreach ($history as $check) {
            if (botInMaintenance($check[0], $windows))
                continue;
            $checks++;
            if ($check[1])
                $up++;
        }

        return $checks ? percent($up, $checks) : '–';
    }

    // a 24 h bar of a history of its own (the bot API, Shinden, the database),
    // with the percent of answered checks
    function historyBar($name, $history)
    {
        ob_start();
?>

        <div class="bar">
          <div class="bar-head"><span><?=e($name)?>, ostatnie 24 godziny</span><b><?=e(historyUptime($history))?></b></div>
          <div class="timeline" aria-label="Dostępność: <?=e($name)?> w ostatnich 24 godzinach, po 15 minut">
<?php foreach (botTimeline($history) as $part): ?>
            <span class="<?=$part['state'] ?? 'none'?>" title="<?=e(date('H:i', $part['from']) . '-' . date('H:i', $part['from'] + 900) . ': ' . timelineLabel($part))?>"></span>
<?php endforeach; ?>
          </div>
          <div class="bar-ends"><span>24 h temu</span><span>teraz</span></div>
        </div>
<?php
        return ob_get_clean();
    }

    // availability over the parts that had checks, or a dash
    function partsUptime($parts)
    {
        $checks = array_sum(array_column($parts, 'checks'));

        return $checks ? percent(array_sum(array_column($parts, 'up')), $checks) : '–';
    }

    const INCIDENTS_SHOWN = 10;

    // "3.10 14:05 – 14:32", the date again when it ended on another day
    function incidentTime($incident)
    {
        [$start, $end] = $incident;
        $text = date('j.m H:i', $start) . ' – ';
        if ($end === null)
            return $text . 'trwa';

        return $text . date(date('Y-m-d', $start) === date('Y-m-d', $end) ? 'H:i' : 'j.m H:i', $end);
    }

    // a column chart of times over the last 24 h (botResponseTimes()), its title
    // with the average and the longest
    function timesChart($title, $times, $average)
    {
        $slowest = max(array_column($times, 'max') ?: [0]);
        $scale = max(array_column($times, 'avg') ?: [0]);

        ob_start();
?>
        <div class="bar">
          <div class="bar-head"><span><?=e($title)?>, 24 godziny</span><b><?=$average === null ? '–' : 'średnio ' . e(milliseconds($average)) . ' &middot; najdłużej ' . e(milliseconds($slowest))?></b></div>
          <div class="response-chart" aria-label="<?=e($title)?> w ostatnich 24 godzinach, średnio po 15 minut">
<?php foreach ($times as $part): ?>
            <span title="<?=e(date('H:i', $part['from']) . '-' . date('H:i', $part['from'] + 900) . ': ' . ($part['avg'] === null ? 'brak pomiaru' : 'średnio ' . milliseconds($part['avg']) . ', najdłużej ' . milliseconds($part['max'])))?>"><?php if ($part['avg'] !== null): ?><i style="height: <?=max(4, round(100 * $part['avg'] / max(1, $scale)))?>%"></i><?php endif; ?></span>
<?php endforeach; ?>
          </div>
          <div class="bar-ends"><span>24 h temu</span><span>teraz</span></div>
        </div>
<?php
        return ob_get_clean();
    }

    // the other Sanakan sites: state, since when, answer time, 90 day availability and bar
    function servicesCard()
    {
        $services = servicesState(DAYS_SHOWN);

        ob_start();
?>
        <ul class="services">
<?php foreach ($services as $service):
        $class = $service['up'] === null ? 'none' : ($service['up'] ? 'online' : 'offline');
        if ($service['up'] === null)
            $text = 'jeszcze nie sprawdzano';
        else if ($service['up'])
            $text = 'działa' . ($service['ms'] !== null ? ' · ' . milliseconds($service['ms']) : '');
        else
            $text = 'nie odpowiada od ' . date(date('Y-m-d', $service['since']) === date('Y-m-d') ? 'H:i' : 'j.m H:i', $service['since']) . ' (' . duration(time() - $service['since']) . ')';
?>
          <li>
            <span class="status-dot <?=$class?>"></span>
            <a class="service-name" href="<?=e($service['url'])?>"><?=e($service['name'])?></a>
            <span class="service-state<?=$service['up'] === false ? ' down' : ''?>"><?=e($text)?></span>
            <b class="service-uptime" title="Dostępność w ostatnich <?=DAYS_SHOWN?> dniach"><?=e(partsUptime($service['days']))?></b>
            <span class="timeline service-bar" aria-label="Dostępność <?=e($service['name'])?> w ostatnich <?=DAYS_SHOWN?> dniach, po dniu">
<?php foreach ($service['days'] as $part): ?>
              <span class="<?=partClass($part)?>" title="<?=e(partTitle(date('d.m', $part['from']), $part))?>"></span>
<?php endforeach; ?>
            </span>
          </li>
<?php endforeach; ?>
        </ul>
<?php
        return ob_get_clean();
    }

    // one line for the admin panel: state, last check, 24 h availability, answer time
    function statusSummary()
    {
        $state = botState();
        $status = shownStatus($state);
        $average = averageResponse(botHistory());
        $health = botHealth();

        ob_start();
?>
        <div class="status-row">
          <span class="status-dot <?=e($status)?>"></span>
          <div class="status-text">
            <b>Bot <?=e(statusLabel($state))?></b>
            <span>Ostatnie sprawdzenie <?=e(ago($state['checked']))?> &middot; dostępność z 24 h: <?=e(str_replace('.', ',', $state['uptime']))?>%<?=$average === null ? '' : ($health ? ' &middot; ping do Discorda średnio ' : ' &middot; odpowiada średnio w ') . e(milliseconds($average))?><?=!empty($health['version']) ? ' &middot; wersja ' . e($health['version']) : ''?></span>
            <span><?=($down = servicesDown()) ? '<b class="warn">Nie odpowiada: ' . e(implode(', ', $down)) . '</b>' : 'Strona i pozostałe serwisy (wiki, Waifu, Alter, Skalpelator, USkalpelator) działają'?></span>
          </div>
        </div>
<?php
        return ob_get_clean();
    }

    function statusCard()
    {
        $state = botState();
        $status = shownStatus($state);
        $history = botHistory();
        $days = botDailyParts(DAYS_SHOWN);
        $health = botHealth();
        $apiHistory = botApiHistory();
        $apiDays = botDailyParts(DAYS_SHOWN, 'api-days.json');
        $shindenTimes = botResponseTimes($history, 3);
        $shindenAverage = averageResponse($history, 3);
        // the same days as the bar; planned breaks are listed but not counted
        $incidents = [];
        $unplanned = 0;
        $downtime = 0;
        foreach (botIncidents($days[0]['from']) as $incident) {
            $planned = plannedIncident($incident);
            $incidents[] = [$incident[0], $incident[1], $planned, $incident[2], $incident[3]];
            if (!$planned) {
                $unplanned++;
                $downtime += $incident[3];
            }
        }
        // the planned ones are listed too, so the header says how many of them there are
        $breaks = count($incidents) - $unplanned;

        ob_start();
?>
        <div class="status-row">
          <span class="status-dot <?=e($status)?>"></span>
          <div class="status-text">
            <b>Bot <?=e(statusLabel($state))?></b>
            <span>Ostatnie sprawdzenie <?=e(ago($state['checked']))?> &middot; <?=count($history)?> <?=plural(count($history), 'sprawdzenie', 'sprawdzenia', 'sprawdzeń')?> w 24 h</span>
<?php if (!empty($health['version']) || !empty($health['startedAt'])): ?>
            <span><?=!empty($health['version']) ? 'Wersja ' . e($health['version']) : ''?><?=!empty($health['version']) && !empty($health['startedAt']) ? ' &middot; ' : ''?><?=!empty($health['startedAt']) ? 'uruchomiony ' . e(date('j.m H:i', strtotime($health['startedAt']))) . ' (' . e(duration(time() - strtotime($health['startedAt']))) . ' temu)' : ''?></span>
<?php endif; ?>
          </div>
        </div>
<?=$health ? healthDetails($health, botApiLast()) : ''?>

        <div class="bar">
          <div class="bar-head"><span>Ostatnie 24 godziny</span><b><?=e(str_replace('.', ',', $state['uptime']))?>%</b></div>
          <div class="timeline" aria-label="Dostępność w ostatnich 24 godzinach, po 15 minut">
<?php foreach (botTimeline($history) as $part): ?>
            <span class="<?=$part['state'] ?? 'none'?>" title="<?=e(date('H:i', $part['from']) . '-' . date('H:i', $part['from'] + 900) . ': ' . timelineLabel($part))?>"></span>
<?php endforeach; ?>
          </div>
          <div class="bar-ends"><span>24 h temu</span><span>teraz</span></div>
        </div>

<?=timesChart($health ? 'Ping do Discorda' : 'Czas odpowiedzi API', botResponseTimes($history), averageResponse($history))?>
<?php if ($shindenAverage !== null): ?>
<?=timesChart('Czas odpowiedzi Shindena', $shindenTimes, $shindenAverage)?>
<?php endif; ?>
<?php foreach (['shinden' => 'Shinden', 'database' => 'Baza danych'] as $key => $name): ?>
<?php if ($dependency = botDependencyHistory($key)): ?>
<?=historyBar($name, $dependency)?>
<?php endif; ?>
<?php endforeach; ?>
<?php if ($apiHistory): ?>
<?=historyBar('API bota', $apiHistory)?>
<?=timesChart('Czas odpowiedzi API bota', botResponseTimes($apiHistory), averageResponse($apiHistory))?>
<?php endif; ?>

        <div class="bar">
          <div class="bar-head"><span>Ostatnie <?=DAYS_SHOWN?> dni</span><b><?=e(partsUptime($days))?></b></div>
          <div class="timeline days" aria-label="Dostępność w ostatnich <?=DAYS_SHOWN?> dniach, po dniu">
<?php foreach ($days as $part): ?>
            <span class="<?=partClass($part)?>" title="<?=e(partTitle(date('d.m', $part['from']), $part))?>"></span>
<?php endforeach; ?>
          </div>
          <div class="bar-ends"><span><?=e(date('d.m', $days[0]['from']))?></span><span>dziś</span></div>
        </div>
<?php if (array_sum(array_column($apiDays, 'checks'))): ?>

        <div class="bar">
          <div class="bar-head"><span>API bota, ostatnie <?=DAYS_SHOWN?> dni</span><b><?=e(partsUptime($apiDays))?></b></div>
          <div class="timeline days" aria-label="Dostępność API bota w ostatnich <?=DAYS_SHOWN?> dniach, po dniu">
<?php foreach ($apiDays as $part): ?>
            <span class="<?=partClass($part)?>" title="<?=e(partTitle(date('d.m', $part['from']), $part))?>"></span>
<?php endforeach; ?>
          </div>
          <div class="bar-ends"><span><?=e(date('d.m', $apiDays[0]['from']))?></span><span>dziś</span></div>
        </div>
<?php endif; ?>

        <div class="timeline-legend">
          <span><i class="ok"></i>działał <i class="warn"></i>częściowo <i class="fail"></i>nie działał <i class="planned"></i>przerwa techniczna <i class="none"></i>brak sprawdzeń</span>
        </div>

        <div class="bar incidents">
          <div class="bar-head"><span>Awarie w ostatnich <?=DAYS_SHOWN?> dniach</span><b><?=$unplanned?><?=$unplanned ? ' &middot; razem ' . e(duration($downtime)) : ''?><?=$breaks ? ' &middot; <span class="planned-count">' . $breaks . ' ' . plural($breaks, 'przerwa techniczna', 'przerwy techniczne', 'przerw technicznych') . '</span>' : ''?></b></div>
<?php if (!$incidents): ?>
          <p class="incidents-none">Bez awarii.</p>
<?php else: ?>
          <ul class="incident-list">
<?php foreach (array_slice($incidents, 0, INCIDENTS_SHOWN) as $incident): ?>
            <li class="<?=$incident[2] ? 'planned' : ''?><?=$incident[1] === null ? ' ongoing' : ''?>"><span><?=e(incidentTime($incident))?><?=$incident[2] ? ' <small>przerwa techniczna</small>' : ''?><?=$incident[3] > 1 ? ' <small>' . $incident[3] . ' ' . plural($incident[3], 'przerwa', 'przerwy', 'przerw') . ' w działaniu</small>' : ''?></span><b<?=$incident[3] > 1 ? ' title="' . e('od początku do końca ' . duration(($incident[1] ?? time()) - $incident[0]) . ', bez odpowiedzi łącznie ' . duration($incident[4])) . '"' : ''?>><?=e(duration($incident[4]))?></b></li>
<?php endforeach; ?>
          </ul>
<?php if (count($incidents) > INCIDENTS_SHOWN): ?>
          <p class="incidents-none">i <?=count($incidents) - INCIDENTS_SHOWN?> <?=plural(count($incidents) - INCIDENTS_SHOWN, 'wcześniejsza', 'wcześniejsze', 'wcześniejszych')?></p>
<?php endif; ?>
<?php endif; ?>
        </div>
<?php
        return ob_get_clean();
    }
