<?php
    // The bot status card: state, availability bars of the last 24 hours, 30
    // days and 12 months, and the outages of the last 30 days. Shown in the
    // admin panel (with "check now") and on the public page state/. Needs
    // inc/bot.php and the helpers in inc/gallery.php.

    const STATUS_LABELS = [
        'online' => 'działa',
        'idle' => 'działa, ale bywał niedostępny',
        'offline' => 'nie odpowiada'
    ];

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

    // how long an outage took: "27 min", "3 godz. 5 min", "2 dni 4 godz."
    function duration($seconds)
    {
        $minutes = max(1, (int)round($seconds / 60));
        if ($minutes < 60)
            return $minutes . ' min';

        $hours = intdiv($minutes, 60);
        if ($hours < 24)
            return $hours . ' godz.' . ($minutes % 60 ? ' ' . ($minutes % 60) . ' min' : '');

        $days = intdiv($hours, 24);
        return $days . ' ' . plural($days, 'dzień', 'dni', 'dni') . ($hours % 24 ? ' ' . ($hours % 24) . ' godz.' : '');
    }

    // "3.10 14:05 – 14:32", the date again when it ended on another day
    function incidentTime($incident)
    {
        [$start, $end] = $incident;
        $text = date('j.m H:i', $start) . ' – ';
        if ($end === null)
            return $text . 'trwa';

        return $text . date(date('Y-m-d', $start) === date('Y-m-d', $end) ? 'H:i' : 'j.m H:i', $end);
    }

    function statusCard($withButton)
    {
        $state = botState();
        $history = botHistory();
        $days = botDailyParts(30);
        $months = botMonthlyParts(12);
        // the same 30 days as the bar
        $incidents = botIncidents($days[0]['from']);
        $downtime = 0;
        foreach ($incidents as $incident)
            $downtime += ($incident[1] ?? time()) - max($incident[0], $days[0]['from']);

        ob_start();
?>
        <div class="status-row">
          <span class="status-dot <?=e($state['status'])?>"></span>
          <div class="status-text">
            <b>Bot <?=e(STATUS_LABELS[$state['status']])?></b>
            <span>Ostatnie sprawdzenie <?=e(ago($state['checked']))?> &middot; <?=count($history)?> <?=plural(count($history), 'sprawdzenie', 'sprawdzenia', 'sprawdzeń')?> w 24 h</span>
          </div>
<?php if ($withButton): ?>
          <button type="button" class="admin-btn" data-action="refresh-status">Sprawdź teraz</button>
<?php endif; ?>
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
          <div class="bar-head"><span>Awarie w ostatnich 30 dniach</span><b><?=count($incidents)?><?=$incidents ? ' &middot; razem ' . e(duration($downtime)) : ''?></b></div>
<?php if (!$incidents): ?>
          <p class="incidents-none">Bez awarii.</p>
<?php else: ?>
          <ul class="incident-list">
<?php foreach (array_slice($incidents, 0, INCIDENTS_SHOWN) as $incident): ?>
            <li<?=$incident[1] === null ? ' class="ongoing"' : ''?>><span><?=e(incidentTime($incident))?></span><b><?=e(duration(($incident[1] ?? time()) - $incident[0]))?></b></li>
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
