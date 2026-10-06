<?php
    // Link preview picture of state/ (og:image): the bot status, availability
    // and the 90 day bar, so a link pasted on Discord shows the state of the
    // moment. Kept for a minute; drawn with inc/og.php.
    require __DIR__ . '/../inc/bot.php';
    require __DIR__ . '/../inc/services.php';
    require __DIR__ . '/../inc/gallery.php';
    require __DIR__ . '/../inc/status-card.php';
    require __DIR__ . '/../inc/og.php';

    ogServe('og.png', BOT_CACHE_TTL, 'drawPreview');

    function drawPreview($file)
    {
        $state = botState();
        $status = shownStatus($state);
        $days = botDailyParts(DAYS_SHOWN);
        $health = botHealth();

        $img = ogCanvas();
        $background = color($img, OG_BACKGROUND);
        ogHead($img, 'SAFEGUARD · LV.9 · STATUS', 'Sanakan');

        statusDot($img, 104, 278, 24, $status, $background);
        ogLine($img, 'Bot ' . statusLabel($state), 148);

        // the outages of the bar's days, planned breaks not counted
        $incidents = 0;
        foreach (botIncidents($days[0]['from']) as $incident)
            if (!plannedIncident($incident))
                $incidents++;
        ogStats($img, [
            '24 h' => str_replace('.', ',', $state['uptime']) . '%',
            DAYS_SHOWN . ' dni' => partsUptime($days),
            'awarie (' . DAYS_SHOWN . ' dni)' => $incidents
        ]);

        // in the free top right corner the bot's version and since when it runs...
        $facts = [];
        if (!empty($health['version']))
            $facts['wersja'] = $health['version'];
        if (!empty($health['startedAt']))
            $facts['uruchomiony'] = duration(time() - strtotime($health['startedAt'])) . ' temu';
        $y = 112;
        foreach ($facts as $label => $value) {
            $width = textWidth(OG_BOLD, 22, $value);
            text($img, OG_BOLD, 22, OG_WIDTH - 80, $y, color($img, '#efe2f7'), $value, 0, 'right');
            text($img, OG_REGULAR, 22, OG_WIDTH - 92 - $width, $y, color($img, '#dcddde', 50), $label, 0, 'right');
            $y += 38;
        }

        // ...and under them the other Sanakan sites, a small dot each. This site
        // works when its picture comes, and the wiki is on the same server, so
        // they are left out; the two Skalpelators share a dot, yellow when only
        // one of them answers.
        $sites = [];
        foreach (servicesState(1) as $service) {
            if ($service['key'] === 'site' || $service['key'] === 'wiki')
                continue;
            $name = in_array($service['key'], ['skalpel', 'uskalpel']) ? 'Skalpelatory' : $service['name'];
            $sites[$name][] = $service['up'];
        }
        $x = OG_WIDTH - 80;
        foreach (array_reverse($sites, true) as $name => $ups) {
            $known = array_filter($ups, function ($up) { return $up !== null; });
            $hex = !$known ? '#80848e' : (!in_array(false, $known, true) ? '#23a55a' : (in_array(true, $known, true) ? '#f0b232' : '#d9534f'));
            text($img, OG_REGULAR, 20, $x, $y, color($img, '#dcddde', 50), $name, 0, 'right');
            $x -= textWidth(OG_REGULAR, 20, $name) + 8;
            circle($img, $x - 7, $y - 7, 14, color($img, $hex));
            $x -= 14 + 20;
        }

        // the last days, a block per day
        $partColors = ['ok' => color($img, '#23a55a', 30), 'warn' => color($img, '#f0b232', 25), 'fail' => color($img, '#d9534f', 20), 'none' => color($img, '#dcddde', 117)];
        $gap = 2;
        $width = (OG_WIDTH - 160 - (count($days) - 1) * $gap) / count($days);
        foreach ($days as $i => $part) {
            $left = 80 + $i * ($width + $gap);
            imagefilledrectangle($img, (int)($left * OG_SCALE), 446 * OG_SCALE, (int)(($left + $width) * OG_SCALE), 496 * OG_SCALE, $partColors[partClass($part)]);
        }
        text($img, OG_REGULAR, 18, 80, 526, color($img, '#dcddde', 70), 'ostatnie ' . DAYS_SHOWN . ' dni');
        text($img, OG_REGULAR, 18, OG_WIDTH - 80, 526, color($img, '#dcddde', 70), 'dziś', 0, 'right');

        ogFoot($img, 'sanakan.pl/state', 'stan z ' . date('j.m.Y H:i', $state['checked']));

        return ogSave($img, $file);
    }
