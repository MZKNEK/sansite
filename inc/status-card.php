<?php
    // The bot status card of the public page state/: state, availability bars
    // of the last 24 hours, 30 days and 12 months, answer times and the outages
    // of the last 30 days. The admin panel shows only statusSummary(). Needs
    // inc/bot.php and the helpers in inc/gallery.php.

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

    // "230 ms", "1,4 s"
    function milliseconds($ms)
    {
        return $ms < 1000 ? $ms . ' ms' : str_replace('.', ',', (string)round($ms / 1000, 1)) . ' s';
    }

    // average answer time over the answered checks, or null
    function averageResponse($history)
    {
        $times = [];
        foreach ($history as $check)
            if ($check[1] && $check[2] !== null)
                $times[] = $check[2];

        return $times ? (int)round(array_sum($times) / count($times)) : null;
    }

    // the outage overlaps a planned break
    function plannedIncident($incident)
    {
        $end = $incident[1] ?? time();
        foreach (botMaintenanceWindows() as $window)
            if ($incident[0] < $window['to'] && $end >= $window['from'])
                return true;

        return false;
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

    const MONTH_NAMES = ['styczeń', 'luty', 'marzec', 'kwiecień', 'maj', 'czerwiec', 'lipiec',
        'sierpień', 'wrzesień', 'październik', 'listopad', 'grudzień'];

    function percent($up, $checks)
    {
        return str_replace('.', ',', (string)(floor(1000 * $up / $checks) / 10)) . '%';
    }

    // colour of a day or a month: fine, partly down, mostly down, or not checked
    function partClass($part)
    {
        if (!$part['checks'])
            return 'none';
        $uptime = 100 * $part['up'] / $part['checks'];

        return $uptime >= 99.5 ? 'ok' : ($uptime >= 95 ? 'warn' : 'fail');
    }

    function partTitle($label, $part)
    {
        if (!$part['checks'])
            return $label . ': brak sprawdzeń';

        return $label . ': ' . percent($part['up'], $part['checks']) . ' (' . $part['checks'] . ' '
            . plural($part['checks'], 'sprawdzenie', 'sprawdzenia', 'sprawdzeń') . ')';
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

    // one line for the admin panel: state, last check, 24 h availability, answer time
    function statusSummary()
    {
        $state = botState();
        $status = shownStatus($state);
        $average = averageResponse(botHistory());

        ob_start();
?>
        <div class="status-row">
          <span class="status-dot <?=e($status)?>"></span>
          <div class="status-text">
            <b>Bot <?=e(STATUS_LABELS[$status] . botDownText($state))?></b>
            <span>Ostatnie sprawdzenie <?=e(ago($state['checked']))?> &middot; dostępność z 24 h: <?=e(str_replace('.', ',', $state['uptime']))?>%<?=$average === null ? '' : ' &middot; odpowiada średnio w ' . e(milliseconds($average))?></span>
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
        $days = botDailyParts(30);
        $months = botMonthlyParts(12);
        $times = botResponseTimes($history);
        $average = averageResponse($history);
        $slowest = max(array_column($times, 'max') ?: [0]);
        $scale = max(array_column($times, 'avg') ?: [0]);
        // the same 30 days as the bar; planned breaks are listed but not counted
        $incidents = [];
        $unplanned = 0;
        $downtime = 0;
        foreach (botIncidents($days[0]['from']) as $incident) {
            $planned = plannedIncident($incident);
            $incidents[] = [$incident[0], $incident[1], $planned];
            if (!$planned) {
                $unplanned++;
                $downtime += ($incident[1] ?? time()) - max($incident[0], $days[0]['from']);
            }
        }

        ob_start();
?>
        <div class="status-row">
          <span class="status-dot <?=e($status)?>"></span>
          <div class="status-text">
            <b>Bot <?=e(STATUS_LABELS[$status] . botDownText($state))?></b>
            <span>Ostatnie sprawdzenie <?=e(ago($state['checked']))?> &middot; <?=count($history)?> <?=plural(count($history), 'sprawdzenie', 'sprawdzenia', 'sprawdzeń')?> w 24 h</span>
          </div>
        </div>

        <div class="bar">
          <div class="bar-head"><span>Ostatnie 24 godziny</span><b><?=e(str_replace('.', ',', $state['uptime']))?>%</b></div>
          <div class="timeline" aria-label="Dostępność w ostatnich 24 godzinach, po 15 minut">
<?php foreach (botTimeline($history) as $part):
        $class = $part['state'] === null ? 'none' : ($part['state'] ? 'ok' : 'fail');
        $label = $part['state'] === null ? 'brak sprawdzeń' : ($part['state'] ? 'działał' : 'nie odpowiadał');
?>
            <span class="<?=$class?>" title="<?=e(date('H:i', $part['from']) . '-' . date('H:i', $part['from'] + 900) . ': ' . $label)?>"></span>
<?php endforeach; ?>
          </div>
          <div class="bar-ends"><span>24 h temu</span><span>teraz</span></div>
        </div>

        <div class="bar">
          <div class="bar-head"><span>Czas odpowiedzi API, 24 godziny</span><b><?=$average === null ? '–' : 'średnio ' . e(milliseconds($average)) . ' &middot; najdłużej ' . e(milliseconds($slowest))?></b></div>
          <div class="response-chart" aria-label="Średni czas odpowiedzi API w ostatnich 24 godzinach, po 15 minut">
<?php foreach ($times as $part): ?>
            <span title="<?=e(date('H:i', $part['from']) . '-' . date('H:i', $part['from'] + 900) . ': ' . ($part['avg'] === null ? 'brak odpowiedzi' : 'średnio ' . milliseconds($part['avg']) . ', najdłużej ' . milliseconds($part['max'])))?>"><?php if ($part['avg'] !== null): ?><i style="height: <?=max(4, round(100 * $part['avg'] / max(1, $scale)))?>%"></i><?php endif; ?></span>
<?php endforeach; ?>
          </div>
          <div class="bar-ends"><span>24 h temu</span><span>teraz</span></div>
        </div>

        <div class="bar">
          <div class="bar-head"><span>Ostatnie 30 dni</span><b><?=e(partsUptime($days))?></b></div>
          <div class="timeline" aria-label="Dostępność w ostatnich 30 dniach, po dniu">
<?php foreach ($days as $part): ?>
            <span class="<?=partClass($part)?>" title="<?=e(partTitle(date('d.m', $part['from']), $part))?>"></span>
<?php endforeach; ?>
          </div>
          <div class="bar-ends"><span><?=e(date('d.m', $days[0]['from']))?></span><span>dziś</span></div>
        </div>

        <div class="bar">
          <div class="bar-head"><span>Ostatnie 12 miesięcy</span><b><?=e(partsUptime($months))?></b></div>
          <div class="timeline" aria-label="Dostępność w ostatnich 12 miesiącach, po miesiącu">
<?php foreach ($months as $part): ?>
            <span class="<?=partClass($part)?>" title="<?=e(partTitle(MONTH_NAMES[date('n', $part['from']) - 1] . ' ' . date('Y', $part['from']), $part))?>"></span>
<?php endforeach; ?>
          </div>
          <div class="bar-ends"><span><?=e(MONTH_NAMES[date('n', $months[0]['from']) - 1] . ' ' . date('Y', $months[0]['from']))?></span><span>ten miesiąc</span></div>
        </div>

        <div class="timeline-legend">
          <span><i class="ok"></i>działał <i class="warn"></i>częściowo <i class="fail"></i>nie działał <i class="none"></i>brak sprawdzeń</span>
        </div>

        <div class="bar incidents">
          <div class="bar-head"><span>Awarie w ostatnich 30 dniach</span><b><?=$unplanned?><?=$unplanned ? ' &middot; razem ' . e(duration($downtime)) : ''?></b></div>
<?php if (!$incidents): ?>
          <p class="incidents-none">Bez awarii.</p>
<?php else: ?>
          <ul class="incident-list">
<?php foreach (array_slice($incidents, 0, INCIDENTS_SHOWN) as $incident): ?>
            <li class="<?=$incident[2] ? 'planned' : ''?><?=$incident[1] === null ? ' ongoing' : ''?>"><span><?=e(incidentTime($incident))?><?=$incident[2] ? ' <small>przerwa techniczna</small>' : ''?></span><b><?=e(duration(($incident[1] ?? time()) - $incident[0]))?></b></li>
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
