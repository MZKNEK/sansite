<?php
    // The bot status card: state, availability in the last 24 h and a bar of
    // those 24 hours. Shown in the admin panel (with "check now") and on the
    // public page state/. Needs inc/bot.php and the helpers in inc/gallery.php.

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

    function statusCard($withButton)
    {
        $state = botState();
        $history = botHistory();

        ob_start();
?>
        <div class="status-row">
          <span class="status-dot <?=e($state['status'])?>"></span>
          <div class="status-text">
            <b>Bot <?=e(STATUS_LABELS[$state['status']])?></b>
            <span>Dostępność z 24 h: <?=e(str_replace('.', ',', $state['uptime']))?>% &middot; <?=count($history)?> <?=plural(count($history), 'sprawdzenie', 'sprawdzenia', 'sprawdzeń')?> &middot; ostatnie <?=e(ago($state['checked']))?></span>
          </div>
<?php if ($withButton): ?>
          <button type="button" class="admin-btn" data-action="refresh-status">Sprawdź teraz</button>
<?php endif; ?>
        </div>
        <div class="timeline" aria-label="Dostępność w ostatnich 24 godzinach">
<?php foreach (botTimeline($history) as $part):
        $class = $part['state'] === null ? 'none' : ($part['state'] ? 'ok' : 'fail');
        $label = $part['state'] === null ? 'brak sprawdzeń' : ($part['state'] ? 'działał' : 'nie odpowiadał');
?>
          <span class="<?=$class?>" title="<?=e(date('H:i', $part['from']) . '-' . date('H:i', $part['from'] + 900) . ': ' . $label)?>"></span>
<?php endforeach; ?>
        </div>
        <div class="timeline-legend">
          <span>24 h temu</span>
          <span><i class="ok"></i>działał <i class="fail"></i>nie odpowiadał <i class="none"></i>brak sprawdzeń</span>
          <span>teraz</span>
        </div>
<?php
        return ob_get_clean();
    }
