<?php
    // Link preview pictures (og:image) of the pages, ?p=cmd and so on, drawn
    // with inc/og.php; the status picture is state/og.php. The home page has the
    // logo with the bot's live Discord status dot; the commands and their
    // changes the layout of the status picture with their own numbers; the
    // gallery, the API and the privacy notice a drawing instead, as they give
    // nothing away. Each is kept as long as its data stays the same.
    require __DIR__ . '/inc/bot.php';
    require __DIR__ . '/inc/services.php';
    require __DIR__ . '/inc/gallery.php';
    require __DIR__ . '/inc/status-card.php';
    require __DIR__ . '/inc/verdiff.php';
    require __DIR__ . '/inc/og.php';

    // the drawing function and how long its picture is kept
    const OG_PAGES = [
        'home' => ['drawHome', BOT_CACHE_TTL],
        'cmd' => ['drawCommands', BOT_COMMANDS_TTL],
        'zmiany' => ['drawChanges', BOT_COMMANDS_TTL],
        // how long the current version runs grows on it
        'wersje' => ['drawVersions', BOT_COMMANDS_TTL],
        'i' => ['drawGallery', 86400],
        'api' => ['drawApi', 86400],
        'privacy' => ['drawPrivacy', 86400],
        // the sites are checked every 5 minutes
        'admin' => ['drawAdmin', SERVICE_INTERVAL],
        'account' => ['drawAccount', 86400]
    ];
    // as long as cmd/ marks a command new or changed
    const OG_NEW_DAYS = 14;
    // the changes listed on the picture of cmd/zmiany
    const OG_CHANGE_ROWS = 4;
    // the versions on the line of the version history picture
    const OG_VERSION_NODES = 6;

    $page = $_GET['p'] ?? 'home';
    if (!isset(OG_PAGES[$page]))
        $page = 'home';

    // one version of state/wersje/ (&v=) has a picture of its own, a version
    // the site does not know the one of the whole history
    $version = $page === 'wersje' && isset($_GET['v']) && is_string($_GET['v']) ? knownVersion($_GET['v']) : null;
    if ($version !== null)
        ogServe('og-wersja-' . preg_replace('/[^0-9A-Za-z.-]/', '_', verdiffKey($version['version'])) . '.png', BOT_COMMANDS_TTL,
            function ($file) use ($version) { return drawVersion($file, $version); });
    else
        ogServe('og-' . $page . '.png', OG_PAGES[$page][1], OG_PAGES[$page][0]);

    // The logo in its ring with the status dot of the home page, and the name
    // next to it; a planned break shows as do not disturb
    function drawHome($file)
    {
        $status = botInMaintenance(time()) ? 'maintenance' : botState()['status'];

        $img = ogCanvas();
        if (!ogLogo($img, 300, 315, 172, $status))
            return false;

        text($img, OG_MONO, 26, 540, 252, color($img, '#b670d3'), 'SAFEGUARD · LV.9', 8);
        text($img, OG_BOLD, 80, 540, 366, color($img, '#efe2f7'), 'SANAKAN', 13);
        text($img, OG_MONO, 28, 540, 432, color($img, '#9b59b6'), 'sanakan.pl', 3);

        return ogSave($img, $file);
    }

    // the changes of the last $days days: [added, changed, removed]
    function recentChanges($days)
    {
        $counts = [0, 0, 0];
        foreach (botCommandChanges() as $change) {
            if ($change['time'] < time() - $days * 86400)
                break;
            $counts[0] += count($change['added']);
            $counts[1] += count($change['changed']);
            $counts[2] += count($change['removed']);
        }

        return $counts;
    }

    // The public commands: how many, in how many modules, the prefix, the
    // aliases, the new and changed ones, and the modules as a bar
    function drawCommands($file)
    {
        $data = botCommands();
        $modules = [];
        $aliases = 0;
        foreach ($data['modules'] ?? [] as $module) {
            $count = 0;
            foreach ($module['subModules'] ?? [] as $submodule)
                foreach ($submodule['commands'] ?? [] as $command) {
                    $count++;
                    $aliases += count(array_diff($command['aliases'] ?? [], [$command['name'] ?? '']));
                }
            if ($count)
                $modules[] = [$count, ($module['name'] ?? '') . ' ' . $count];
        }
        usort($modules, function ($a, $b) { return $b[0] <=> $a[0]; });
        $total = array_sum(array_column($modules, 0));
        [$added, $changed] = recentChanges(OG_NEW_DAYS);

        $img = ogCanvas();
        ogHead($img, 'SAFEGUARD · LV.9', 'Polecenia');
        ogLine($img, $total . ' ' . plural($total, 'polecenie', 'polecenia', 'poleceń') . ' w ' . count($modules) . ' ' . plural(count($modules), 'module', 'modułach', 'modułach'));
        ogStats($img, [
            'prefiks' => $data['prefix'] ?? '–',
            'aliasy' => formatCount($aliases),
            'nowe (' . OG_NEW_DAYS . ' dni)' => $added,
            'zmienione (' . OG_NEW_DAYS . ' dni)' => $changed
        ]);
        ogBar($img, $modules);

        $updated = @filemtime(botFile('commands.json'));
        ogFoot($img, 'sanakan.pl/cmd', $updated ? 'stan z ' . date('j.m.Y H:i', $updated) : '');

        return ogSave($img, $file);
    }

    // The latest changes in the commands, newest first: when, what kind, which
    // command and what about it (a new one's description, the new text of what
    // changed, a removed one's module), with the sum of the last 30 days above.
    // New commands of those 30 days always get a row, the rest go to the newest
    // other changes.
    function drawChanges($file)
    {
        botState();
        $index = json_decode((string)@file_get_contents(botFile('commands-index.json')), true)['commands'] ?? [];
        $new = [];
        $other = [];
        foreach (botCommandChanges() as $change) {
            $recent = $change['time'] >= time() - 30 * 86400;
            if (!$recent && count($other) >= OG_CHANGE_ROWS)
                break;
            foreach ($change['added'] as $key)
                if ($recent)
                    $new[] = [$change['time'], 'nowe', $key, '', $index[$key]['description'] ?? ($index[$key]['module'] ?? '')];
            foreach ($change['changed'] as $key => $fields) {
                // the first field that changed, as COMMAND_FIELDS lists them, with its new text
                $field = array_values(array_intersect(array_keys(COMMAND_FIELDS), array_keys($fields)))[0] ?? array_key_first($fields);
                $other[] = [$change['time'], 'zmienione', (string)$key, (COMMAND_FIELDS[$field] ?? $field) . ':', (string)($fields[$field][1] ?? '')];
            }
            foreach ($change['removed'] as $key)
                $other[] = [$change['time'], 'usunięte', $key, '', $change['gone'][$key]['module'] ?? ''];
        }
        // newest first again; on the same time new before changed before removed,
        // as they were collected
        $rows = array_slice($new, 0, OG_CHANGE_ROWS);
        $rows = array_merge($rows, array_slice($other, 0, OG_CHANGE_ROWS - count($rows)));
        $order = ['nowe' => 0, 'zmienione' => 1, 'usunięte' => 2];
        usort($rows, function ($a, $b) use ($order) { return [$b[0], $order[$a[1]]] <=> [$a[0], $order[$b[1]]]; });
        [$added, $changed, $removed] = recentChanges(30);
        $since = botCommandsWatchedSince();

        $img = ogCanvas();
        ogHead($img, 'SAFEGUARD · LV.9 · ZMIANY', 'Polecenia');
        $parts = [];
        if ($added)
            $parts[] = $added . ' ' . plural($added, 'nowe', 'nowe', 'nowych');
        if ($changed)
            $parts[] = $changed . ' ' . plural($changed, 'zmienione', 'zmienione', 'zmienionych');
        if ($removed)
            $parts[] = $removed . ' ' . plural($removed, 'usunięte', 'usunięte', 'usuniętych');
        ogLine($img, $parts ? implode(', ', $parts) . ' w 30 dni' : ($rows ? 'Bez zmian w ostatnich 30 dniach' : 'Lista poleceń się nie zmieniła'));

        $kinds = ['nowe' => '#23a55a', 'zmienione' => '#f0b232', 'usunięte' => '#d9534f'];
        foreach ($rows as $i => [$time, $kind, $key, $label, $what]) {
            $y = 362 + $i * 50;
            text($img, OG_MONO, 22, 80, $y, color($img, '#dcddde', 50), date('j.m', $time));
            imagefilledrectangle($img, 170 * OG_SCALE, ($y - 25) * OG_SCALE, 300 * OG_SCALE, ($y + 9) * OG_SCALE, color($img, $kinds[$kind], 95));
            text($img, OG_BOLD, 17, 235 - textWidth(OG_BOLD, 17, $kind) / 2, $y - 2, color($img, $kinds[$kind]), $kind);
            text($img, OG_BOLD, 26, 322, $y, color($img, '#efe2f7'), $key);
            $x = 322 + textWidth(OG_BOLD, 26, $key) + 18;
            // "opis:" in the accent colour, so it reads as what changed, not as the text
            if ($label !== '' && $x < OG_WIDTH - 140) {
                text($img, OG_REGULAR, 22, $x, $y, color($img, '#b670d3'), $label);
                $x += textWidth(OG_REGULAR, 22, $label) + 8;
            }
            if ($what !== '' && $x < OG_WIDTH - 140)
                text($img, OG_REGULAR, 22, $x, $y, color($img, '#dcddde', 50), ogFit(OG_REGULAR, 22, $what, OG_WIDTH - 80 - $x));
        }

        ogFoot($img, 'sanakan.pl/cmd/zmiany', $since ? 'zapisywane od ' . date('j.m.Y', $since) : '');

        return ogSave($img, $file);
    }

    // The versions of state/wersje/, newest first, each with 'ran' (how long it
    // ran until the next one started, the newest up to now; null without a
    // start) and 'current' (the one the bot reports now)
    function versionRuns()
    {
        $entries = versionEntries();
        $current = botHealth()['version'] ?? ($entries[0]['version'] ?? null);
        $newerSince = null;
        foreach ($entries as &$entry) {
            $entry['ran'] = null;
            if ($entry['since']) {
                $entry['ran'] = max(0, ($newerSince ?? time()) - $entry['since']);
                $newerSince = $entry['since'];
            }
            $entry['current'] = $current !== null && strcasecmp($entry['version'], $current) === 0;
        }
        unset($entry);

        return $entries;
    }

    // the version $wanted as versionRuns() has it, one only the changelog knows
    // without its run, or null
    function knownVersion($wanted)
    {
        foreach (versionRuns() as $entry)
            if (strcasecmp(verdiffKey($entry['version']), verdiffKey($wanted)) === 0)
                return $entry;
        $section = verdiffChanges($wanted);

        return $section === null ? null : ['version' => $section['version'], 'since' => null, 'date' => $section['date'],
            'commit' => $section['commit'], 'changes' => true, 'ran' => null, 'current' => false];
    }

    // up to two facts in the free top right corner, as on the status picture:
    // when the version came (or its date in the changelog) and its commit
    function versionFacts($img, $entry)
    {
        $facts = [];
        if ($entry['since'])
            $facts['wprowadzona'] = date('j.m.Y H:i', $entry['since']);
        else if ($entry['date'])
            $facts['data'] = $entry['date'];
        if ($entry['commit'])
            $facts['commit'] = $entry['commit'];
        $y = 112;
        foreach ($facts as $label => $value) {
            $font = $label === 'commit' ? OG_MONO : OG_BOLD;
            $width = textWidth($font, 22, $value);
            text($img, $font, 22, OG_WIDTH - 80, $y, color($img, '#efe2f7'), $value, 0, 'right');
            text($img, OG_REGULAR, 22, OG_WIDTH - 92 - $width, $y, color($img, '#dcddde', 50), $label, 0, 'right');
            $y += 38;
        }
    }

    // The versions (newest first, as versionRuns()) as commits on a branch at
    // $y, oldest on the left, the one at $mark glowing: up to OG_VERSION_NODES
    // of them on even slots around it, the date above, the number below. The
    // line comes faded from older ones and goes on dotted to the next version.
    function versionBranch($img, $entries, $mark, $y)
    {
        $start = max(0, min($mark - 2, count($entries) - OG_VERSION_NODES));
        $shown = array_reverse(array_slice($entries, $start, OG_VERSION_NODES, true), true);
        if (!$shown)
            return;

        $slot = (OG_WIDTH - 160) / OG_VERSION_NODES;
        $first = OG_VERSION_NODES - count($shown);
        $purple = color($img, '#9b59b6');
        $left = 80 + $slot * ($first + 0.5);
        $right = 80 + $slot * (OG_VERSION_NODES - 0.5);
        if (count($entries) > $start + count($shown))
            for ($x = 80; $x < $left; $x += 4)
                box($img, $x, $y - 1.5, $x + 4, $y + 1.5, color($img, '#9b59b6', (int)(110 - 80 * ($x - 80) / max(1, $left - 80))));
        line($img, $left, $y, $right, $y, 3, $purple);
        for ($x = $right + 24; $x < OG_WIDTH - 80; $x += 14)
            box($img, $x, $y - 1.5, $x + 6, $y + 1.5, color($img, '#9b59b6', 80));

        $slotIndex = $first;
        foreach ($shown as $i => $entry) {
            $x = 80 + $slot * ($slotIndex++ + 0.5);
            $marked = $i === $mark;
            if ($marked) {
                for ($g = 0; $g < 8; $g++)
                    circle($img, $x, $y, 70 - $g * 6, color($img, '#b670d3', 120 - $g * 3));
                circle($img, $x, $y, 30, color($img, '#d2a8e8'));
                ring($img, $x, $y, 30, 3, $purple);
            } else {
                circle($img, $x, $y, 20, color($img, OG_BACKGROUND));
                ring($img, $x, $y, 20, 3, $purple);
            }

            $when = $entry['since'] ? date('j.m.Y', $entry['since']) : (string)$entry['date'];
            text($img, OG_REGULAR, 16, $x - textWidth(OG_REGULAR, 16, $when) / 2, $y - 30, color($img, '#dcddde', $marked ? 40 : 70), $when);
            $name = ogFit(OG_MONO, 20, $entry['version'], $slot - 16);
            text($img, OG_MONO, 20, $x - textWidth(OG_MONO, 20, $name) / 2, $y + 44, color($img, $marked ? '#efe2f7' : '#d2a8e8', $marked ? 0 : 30), $name);
        }
    }

    // The version history: the version running now, how many there were and
    // how long the current one runs, and the last ones as commits on a branch,
    // oldest on the left, the current one glowing at its end
    function drawVersions($file)
    {
        $entries = versionRuns();
        $current = null;
        $recent = 0;
        foreach ($entries as $entry) {
            if ($entry['current'] && $current === null)
                $current = $entry;
            // a version from the built-in changelog has only its date
            $start = $entry['since'] ?: ($entry['date'] ? strtotime($entry['date']) : false);
            if ($start && $start >= time() - 30 * 86400)
                $recent++;
        }
        $current = $current ?? ($entries[0] ?? null);

        $img = ogCanvas();
        ogHead($img, 'SAFEGUARD · LV.9 · WERSJE', 'Wersje');
        ogLine($img, $current !== null ? 'Obecna wersja ' . $current['version'] : 'Jeszcze brak wersji');
        if ($current !== null)
            versionFacts($img, $current);
        ogStats($img, [
            'wersje' => count($entries),
            'nowe (30 dni)' => $recent,
            'obecna działa' => $current !== null && $current['ran'] !== null ? duration($current['ran']) : '–'
        ]);

        $mark = 0;
        foreach ($entries as $i => $entry)
            if ($entry === $current)
                $mark = $i;
        versionBranch($img, $entries, $mark, 484);

        ogFoot($img, 'sanakan.pl/state/wersje', 'stan z ' . date('j.m.Y H:i'));

        return ogSave($img, $file);
    }

    // One version: its number as the name, how many changes it brought, the
    // first of them (the technical ones after the rest) and how long it ran
    function drawVersion($file, $entry)
    {
        $section = verdiffChanges($entry['version']);
        $items = $section !== null ? verdiffItems($section['changes']) : [];
        usort($items, function ($a, $b) { return $a[1] <=> $b[1]; });
        $technical = count(array_filter($items, function ($item) { return $item[1]; }));
        $plain = count($items) - $technical;

        $img = ogCanvas();
        ogHead($img, 'SAFEGUARD · LV.9 · WERSJA', $entry['version']);
        versionFacts($img, $entry);

        // the version the bot runs now is marked next to its name
        if ($entry['current']) {
            $x = 80 + textWidth(OG_BOLD, 64, ogUpper($entry['version']), 18) + 30;
            box($img, $x, 150, $x + 110, 186, color($img, '#23a55a', 95));
            text($img, OG_BOLD, 18, $x + 55 - textWidth(OG_BOLD, 18, 'obecna') / 2, 175, color($img, '#23a55a'), 'obecna');
        }

        $parts = [];
        if ($plain)
            $parts[] = $plain . ' ' . plural($plain, 'zmiana', 'zmiany', 'zmian');
        if ($technical)
            $parts[] = $technical . ' ' . plural($technical, 'techniczna', 'techniczne', 'technicznych');
        ogLine($img, $parts ? implode(', ', $parts) : 'Brak opisu zmian');

        // a row per change; when they do not fit, the last row says how many more
        $rows = count($items) > OG_CHANGE_ROWS ? array_slice($items, 0, OG_CHANGE_ROWS - 1) : $items;
        foreach ($rows as $i => [$text, $isTechnical]) {
            $y = 362 + $i * 50;
            box($img, 84, $y - 16, 96, $y - 4, color($img, $isTechnical ? '#80848e' : '#9b59b6'));
            text($img, OG_REGULAR, 24, 116, $y, color($img, $isTechnical ? '#dcddde' : '#efe2f7', $isTechnical ? 50 : 0), ogFit(OG_REGULAR, 24, $text, OG_WIDTH - 196));
        }
        if (count($items) > count($rows)) {
            $more = count($items) - count($rows);
            text($img, OG_REGULAR, 22, 116, 362 + count($rows) * 50, color($img, '#b670d3'), 'i ' . $more . ' ' . plural($more, 'kolejna', 'kolejne', 'kolejnych'));
        }

        // without changes to list, where it stands among the versions around it
        if (!$items) {
            $entries = versionRuns();
            foreach ($entries as $i => $run)
                if (strcasecmp($run['version'], $entry['version']) === 0)
                    versionBranch($img, $entries, $i, 440);
        }

        $note = '';
        if ($entry['ran'] !== null)
            $note = ($entry['current'] ? 'działa ' : 'działała ') . duration($entry['ran']);
        ogFoot($img, 'sanakan.pl/state/wersje', $note);

        return ogSave($img, $file);
    }

    // The pages without numbers of their own: the SAFEGUARD line and the name,
    // two lines about the page on the left, a drawing on the right and the
    // address at the bottom
    function ogPicturePage($tag, $title, $lines, $address)
    {
        $img = ogCanvas();
        ogHead($img, $tag, $title);
        foreach ($lines as $i => $line)
            text($img, OG_BOLD, 34, 80, 296 + $i * 48, color($img, '#efe2f7'), $line);
        ogFoot($img, $address, '');

        return $img;
    }

    // The name as if coded: cut in strips pushed sideways, with the cyan and
    // pink shadows of the home page logo's glitch, gaps of scan lines through
    // it and three letters lost in noise, so it can be guessed but not read
    function codedTitle($img, $title)
    {
        $size = 64;
        $spacing = 18;
        text($img, OG_BOLD, $size, 73, 196, color($img, '#05d9e8', 70), $title, $spacing);
        text($img, OG_BOLD, $size, 87, 199, color($img, '#ff2a6d', 70), $title, $spacing);

        // the name on a layer of its own, copied onto the picture strip by strip
        $layer = imagecreatetruecolor(OG_WIDTH * OG_SCALE, OG_HEIGHT * OG_SCALE);
        imagealphablending($layer, false);
        imagefill($layer, 0, 0, color($layer, '#000000', 127));
        imagealphablending($layer, true);
        text($layer, OG_BOLD, $size, 80, 196, color($layer, '#efe2f7'), $title, $spacing);
        $top = 130;
        foreach ([[16, 0], [8, -15], [10, 5], [7, 19], [13, -7], [6, 12], [14, 0]] as [$height, $shift]) {
            imagecopy($img, $layer, $shift * OG_SCALE, $top * OG_SCALE, 0, $top * OG_SCALE, OG_WIDTH * OG_SCALE, $height * OG_SCALE);
            $top += $height;
        }
        unset($layer);

        // the scan lines: thin gaps of background across the whole name
        for ($y = 134; $y < 200; $y += 6)
            box($img, 60, $y, 600, $y + 2, color($img, OG_BACKGROUND, 30));

        // noise over the second, fifth and last letter
        mt_srand(11);
        foreach ([1, 4, 6] as $letter) {
            $left = 80 + textWidth(OG_BOLD, $size, implode('', array_slice(ogChars($title), 0, $letter)) . 'X', $spacing) - textWidth(OG_BOLD, $size, 'X');
            for ($y = 136; $y < 200; $y += 8)
                for ($x = $left - 6; $x < $left + 50; $x += 8)
                    if (mt_rand(0, 4))
                        box($img, $x, $y, $x + 8, $y + 8, color($img, ['#141517', '#2a2233', '#46305a', '#6c3483', '#9b59b6', '#b670d3'][mt_rand(0, 5)], mt_rand(0, 25)));
        }
    }

    // The gallery: almost nobody may open it, so it gives nothing away, not even
    // its name clearly; an endless corridor of the Megastructure from BLAME!,
    // a light at its end and someone walking towards it
    function drawGallery($file)
    {
        $img = ogCanvas();
        text($img, OG_MONO, 20, 80, 112, color($img, '#b670d3'), 'SAFEGUARD · LV.9', 6);
        codedTitle($img, 'GALERIA');
        text($img, OG_BOLD, 34, 80, 296, color($img, '#efe2f7'), 'Gdzieś');
        text($img, OG_BOLD, 34, 80, 344, color($img, '#efe2f7'), 'w Megastrukturze');
        ogFoot($img, 'sanakan.pl/i', '');

        // the corridor: edges to the far end, frames getting smaller and darker,
        // cables hanging from them
        $vx = 900;
        $vy = 360;
        foreach ([[640, 140], [1120, 140], [1120, 560], [640, 560]] as [$x, $y])
            line($img, $x, $y, $vx, $vy, 2, color($img, '#9b59b6', 90));
        for ($i = 0; $i < 11; $i++) {
            $k = 0.74 ** $i;
            $w = 480 * $k;
            $h = 420 * $k;
            frame($img, $vx - $w / 2, $vy - $h / 2 + 10 * $k, $vx + $w / 2, $vy + $h / 2 + 10 * $k, max(1, 10 * $k), color($img, '#6c3483', min(120, 20 + $i * 9)));
            if ($i < 7)
                line($img, $vx - $w / 2 + 40 * $k, $vy - $h / 2 + 10 * $k, $vx - $w / 2 + 70 * $k, $vy - $h / 2 + 90 * $k, max(1, 3 * $k), color($img, '#9b59b6', 70 + $i * 6));
        }

        // the light at the end and the figure against it
        for ($i = 0; $i < 8; $i++)
            circle($img, $vx, $vy, 120 - $i * 14, color($img, '#d2a8e8', 122 - $i * 2));
        box($img, $vx - 7, $vy - 12, $vx + 7, $vy + 14, color($img, '#efe2f7'));
        box($img, $vx + 22, $vy + 2, $vx + 28, $vy + 26, color($img, OG_BACKGROUND));
        circle($img, $vx + 25, $vy - 2, 8, color($img, OG_BACKGROUND));

        return ogSave($img, $file);
    }

    // The API without its endpoints: a terminal asking the bot how it is
    function drawApi($file)
    {
        $img = ogPicturePage('SAFEGUARD · LV.9', 'API', ['Dokumentacja', 'API bota Sanakan'], 'sanakan.pl/api');

        box($img, 640, 236, 1120, 520, color($img, '#1c1a21'));
        box($img, 640, 236, 1120, 270, color($img, '#2a2233'));
        foreach (['#d9534f', '#f0b232', '#23a55a'] as $i => $hex)
            circle($img, 658 + $i * 22, 253, 12, color($img, $hex));
        text($img, OG_MONO, 16, 1104, 259, color($img, '#dcddde', 70), 'api.sanakan.pl', 1, 'right');
        corners($img, 640, 236, 1120, 520, 22, color($img, '#9b59b6'));

        $lines = [
            [['> ', '#9b59b6'], ['GET ', '#b670d3'], ['/api/health', '#efe2f7']],
            [['< ', '#9b59b6'], ['200 OK', '#23a55a']],
            [['{', '#dcddde']],
            [['  "status": ', '#d2a8e8'], ['"ok"', '#23a55a'], [',', '#dcddde']],
            [['  "discord": ', '#d2a8e8'], ['"Connected"', '#23a55a']],
            [['}', '#dcddde']]
        ];
        foreach ($lines as $i => $parts) {
            $x = 664;
            foreach ($parts as [$string, $hex]) {
                text($img, OG_MONO, 22, $x, 312 + $i * 33, color($img, $hex), $string);
                // measured up to a following "X", so the spaces at the end count
                $x += textWidth(OG_MONO, 22, $string . 'X') - textWidth(OG_MONO, 22, 'X');
            }
        }
        // the cursor
        box($img, 664, 296 + 6 * 33, 677, 318 + 6 * 33, color($img, '#b670d3'));

        return ogSave($img, $file);
    }

    // The privacy notice without its details, laid out as the home page: a
    // shield with a padlock where the logo is, the name next to it
    function drawPrivacy($file)
    {
        $img = ogCanvas();
        shieldLock($img, 300, 140, 1.25);

        text($img, OG_MONO, 26, 540, 252, color($img, '#b670d3'), 'SAFEGUARD · LV.9', 8);
        text($img, OG_BOLD, 56, 540, 350, color($img, '#efe2f7'), 'PRYWATNOŚĆ', 6);
        text($img, OG_MONO, 28, 540, 420, color($img, '#9b59b6'), 'sanakan.pl/privacy', 3);

        return ogSave($img, $file);
    }

    // a shield with a padlock, its top at $cx, $top, $k times 230 x 276 pixels
    function shieldLock($img, $cx, $top, $k)
    {
        // raised corners at the top, straight sides and a curve down to the
        // point; the same smaller inside it
        $outline = function ($top, $width, $height) use ($cx) {
            $right = $cx + $width / 2;
            $points = [[$cx, $top], [$right, $top + $height * 0.1], [$right, $top + $height * 0.5]];
            for ($i = 1; $i <= 10; $i++) {
                $t = $i / 10;
                $points[] = [(1 - $t) ** 2 * $right + 2 * (1 - $t) * $t * $right + $t * $t * $cx,
                    (1 - $t) ** 2 * ($top + $height * 0.5) + 2 * (1 - $t) * $t * ($top + $height * 0.85) + $t * $t * ($top + $height)];
            }
            foreach (array_reverse($points) as [$x, $y])
                if ($x > $cx)
                    $points[] = [2 * $cx - $x, $y];

            return $points;
        };
        $inside = color($img, '#241a2c');
        $light = color($img, '#efe2f7');
        shape($img, $outline($top, 230 * $k, 276 * $k), color($img, '#9b59b6'));
        shape($img, $outline($top + 14 * $k, 202 * $k, 248 * $k), $inside);

        // the padlock: shackle, body and keyhole
        $y = $top + 108 * $k;
        circle($img, $cx, $y, 76 * $k, $light);
        circle($img, $cx, $y, 48 * $k, $inside);
        box($img, $cx - 38 * $k, $y, $cx - 24 * $k, $y + 20 * $k, $light);
        box($img, $cx + 24 * $k, $y, $cx + 38 * $k, $y + 20 * $k, $light);
        box($img, $cx - 52 * $k, $y + 18 * $k, $cx + 52 * $k, $y + 96 * $k, $light);
        circle($img, $cx, $y + 46 * $k, 20 * $k, $inside);
        shape($img, [[$cx - 5 * $k, $y + 50 * $k], [$cx + 5 * $k, $y + 50 * $k], [$cx + 8 * $k, $y + 76 * $k], [$cx - 8 * $k, $y + 76 * $k]], $inside);
    }

    // The panel gives nothing away either: the Safeguard's radar over the
    // Megastructure, the bot in its centre and the Sanakan sites as blips in the
    // colours of their last check, which state/ shows anyway; a sweep passes
    // over them
    function drawAdmin($file)
    {
        $state = botState();
        $status = botInMaintenance(time()) ? 'maintenance' : $state['status'];
        $sites = servicesState(1);
        $answering = count(array_filter($sites, function ($site) { return $site['up'] === true; })) + ($state['status'] !== 'offline' ? 1 : 0);

        $img = ogCanvas();
        ogHead($img, 'SAFEGUARD · LV.9 · ADMIN', 'Panel');
        ogLine($img, 'Centrum kontroli');
        // the mono font has no Polish letters, so the bot's state is in English as on Discord
        ogStats($img, [
            'węzły' => $answering . '/' . (count($sites) + 1),
            'bot' => ['online' => 'ONLINE', 'idle' => 'IDLE', 'offline' => 'OFFLINE', 'maintenance' => 'DND'][$status],
            'skan' => date('H:i')
        ], 190);

        $cx = 905;
        $cy = 318;
        $r = 215;
        $purple = function ($alpha) use ($img) { return color($img, '#9b59b6', $alpha); };
        circle($img, $cx, $cy, 2 * $r, $purple(118));
        foreach ([0.25, 0.5, 0.75] as $k)
            ring($img, $cx, $cy, 2 * $r * $k, 1, $purple(90));
        ring($img, $cx, $cy, 2 * $r, 2, $purple(30));
        line($img, $cx - $r, $cy, $cx + $r, $cy, 1, $purple(95));
        line($img, $cx, $cy - $r, $cx, $cy + $r, 1, $purple(95));
        // the scale around it: a tick every 10 degrees, longer every 30
        for ($deg = 0; $deg < 360; $deg += 10) {
            $t = deg2rad($deg);
            $length = $deg % 30 ? 7 : 15;
            line($img, $cx + cos($t) * ($r + 6), $cy + sin($t) * ($r + 6), $cx + cos($t) * ($r + 6 + $length), $cy + sin($t) * ($r + 6 + $length), 2, $purple($deg % 30 ? 80 : 30));
        }

        // the sweep: a fan of thin slices, fading behind its bright leading edge
        $lead = -50;
        $span = 85;
        $slices = 48;
        for ($i = 0; $i < $slices; $i++) {
            $a1 = deg2rad($lead - $span + $span * $i / $slices);
            $a2 = deg2rad($lead - $span + $span * ($i + 1) / $slices);
            shape($img, [[$cx, $cy], [$cx + cos($a1) * $r, $cy + sin($a1) * $r], [$cx + cos($a2) * $r, $cy + sin($a2) * $r]], color($img, '#b670d3', (int)(126 - 76 * ($i + 1) / $slices)));
        }
        line($img, $cx, $cy, $cx + cos(deg2rad($lead)) * $r, $cy + sin(deg2rad($lead)) * $r, 3, color($img, '#d2a8e8'));

        // the sites: where each blip stands, as [degrees, part of the radius]
        $places = [[-20, 0.8], [30, 0.5], [78, 0.78], [140, 0.6], [200, 0.8], [250, 0.48]];
        foreach ($sites as $i => $site) {
            [$deg, $k] = $places[$i % count($places)];
            $x = $cx + cos(deg2rad($deg)) * $r * $k;
            $y = $cy + sin(deg2rad($deg)) * $r * $k;
            $hex = $site['up'] === null ? '#80848e' : ($site['up'] ? '#23a55a' : '#d9534f');
            for ($g = 0; $g < 5; $g++)
                circle($img, $x, $y, 36 - $g * 6, color($img, $hex, 116 - $g * 8));
            circle($img, $x, $y, 11, color($img, $hex));
            // the label on the side away from the edge
            $right = $x < $cx + $r * 0.35;
            text($img, OG_REGULAR, 17, $right ? $x + 16 : $x - 16, $y + 6, color($img, '#dcddde', 40), $site['name'], 0, $right ? 'left' : 'right');
        }

        // the bot in the centre with its Discord status, as on the home page
        circle($img, $cx, $cy, 44, color($img, OG_BACKGROUND));
        ring($img, $cx, $cy, 44, 2, color($img, '#b670d3'));
        statusDot($img, $cx, $cy, 12, $status, color($img, OG_BACKGROUND));

        ogFoot($img, 'sanakan.pl/admin', '');

        return ogSave($img, $file);
    }

    // The profile is somebody's own, so the picture is nobody's: the Safeguard's
    // identity card of a resident, the photo a silhouette, the name blacked
    // out, the level unknown and no Net Terminal Gene
    function drawAccount($file)
    {
        $img = ogCanvas();
        ogHead($img, 'SAFEGUARD · LV.9', 'Profil');
        text($img, OG_BOLD, 34, 80, 296, color($img, '#efe2f7'), 'Twoje konto:');
        text($img, OG_BOLD, 34, 80, 344, color($img, '#efe2f7'), 'role, dostęp, urządzenia');
        ogFoot($img, 'sanakan.pl/account', '');

        // the card with its top band
        $left = 640;
        $top = 140;
        $right = 1120;
        $bottom = 510;
        box($img, $left, $top, $right, $bottom, color($img, '#1c1a21'));
        box($img, $left, $top, $right, $top + 40, color($img, '#2a2233'));
        text($img, OG_MONO, 16, $left + 22, $top + 27, color($img, '#b670d3'), 'SAFEGUARD · ID', 3);
        text($img, OG_MONO, 16, $right - 22, $top + 27, color($img, '#dcddde', 70), 'NR ????-????', 1, 'right');
        corners($img, $left, $top, $right, $bottom, 22, color($img, '#9b59b6'));

        // the photo: a silhouette behind scan lines and the scanning beam
        $px1 = $left + 26;
        $py1 = $top + 66;
        $px2 = $px1 + 150;
        $py2 = $py1 + 180;
        box($img, $px1, $py1, $px2, $py2, color($img, '#241a2c'));
        $mid = ($px1 + $px2) / 2;
        circle($img, $mid, $py1 + 66, 70, color($img, '#6c3483'));
        imagefilledarc($img, (int)($mid * OG_SCALE), $py2 * OG_SCALE, 136 * OG_SCALE, 150 * OG_SCALE, 180, 360, color($img, '#6c3483'), IMG_ARC_PIE);
        for ($y = $py1 + 3; $y < $py2; $y += 6)
            box($img, $px1, $y, $px2, $y + 2, color($img, '#241a2c', 40));
        box($img, $px1, $py1 + 104, $px2, $py1 + 123, color($img, '#b670d3', 105));
        box($img, $px1 - 6, $py1 + 112, $px2 + 6, $py1 + 115, color($img, '#d2a8e8'));
        frame($img, $px1, $py1, $px2, $py2, 2, color($img, '#9b59b6', 60));

        // the fields: a label and its value
        $fx = $px2 + 30;
        $label = color($img, '#dcddde', 50);
        text($img, OG_REGULAR, 17, $fx, $py1 + 16, $label, 'nazwa');
        // the name blacked out in uneven blocks
        $x = $fx;
        foreach ([86, 52, 104] as $width) {
            box($img, $x, $py1 + 30, $x + $width, $py1 + 54, color($img, '#46305a'));
            $x += $width + 10;
        }
        text($img, OG_REGULAR, 17, $fx, $py1 + 88, $label, 'poziom Safeguard');
        text($img, OG_MONO, 26, $fx, $py1 + 122, color($img, '#efe2f7'), 'LV.?', 2);
        text($img, OG_REGULAR, 17, $fx, $py1 + 160, $label, 'gen terminala sieciowego');
        text($img, OG_MONO, 24, $fx, $py1 + 192, color($img, '#e86262'), 'NIE WYKRYTO', 2);

        // a barcode along the bottom, the same every time
        mt_srand(42);
        $x = $left + 26;
        while ($x < $right - 30) {
            $width = mt_rand(1, 4) * 1.5;
            box($img, $x, $bottom - 62, $x + $width, $bottom - 26, color($img, '#dcddde', mt_rand(0, 1) ? 30 : 75));
            $x += $width + mt_rand(1, 3) * 2;
        }

        return ogSave($img, $file);
    }
