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
    require __DIR__ . '/inc/og.php';

    // the drawing function and how long its picture is kept
    const OG_PAGES = [
        'home' => ['drawHome', BOT_CACHE_TTL],
        'cmd' => ['drawCommands', BOT_COMMANDS_TTL],
        'zmiany' => ['drawChanges', BOT_COMMANDS_TTL],
        'i' => ['drawGallery', 86400],
        'api' => ['drawApi', 86400],
        'privacy' => ['drawPrivacy', 86400]
    ];
    // as long as cmd/ marks a command new or changed
    const OG_NEW_DAYS = 14;

    $page = $_GET['p'] ?? 'home';
    if (!isset(OG_PAGES[$page]))
        $page = 'home';

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
    // command and what in it, with the sum of the last 30 days above them
    function drawChanges($file)
    {
        botState();
        $index = json_decode((string)@file_get_contents(botFile('commands-index.json')), true)['commands'] ?? [];
        $rows = [];
        foreach (botCommandChanges() as $change) {
            foreach ($change['added'] as $key)
                $rows[] = [$change['time'], 'nowe', $key, $index[$key]['module'] ?? ''];
            foreach ($change['changed'] as $key => $fields)
                $rows[] = [$change['time'], 'zmienione', (string)$key, implode(', ', array_map(function ($field) { return COMMAND_FIELDS[$field] ?? $field; }, array_keys($fields)))];
            foreach ($change['removed'] as $key)
                $rows[] = [$change['time'], 'usunięte', $key, $change['gone'][$key]['module'] ?? ''];
            if (count($rows) >= 4)
                break;
        }
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
        foreach (array_slice($rows, 0, 4) as $i => [$time, $kind, $key, $what]) {
            $y = 362 + $i * 50;
            text($img, OG_MONO, 22, 80, $y, color($img, '#dcddde', 50), date('d.m', $time));
            imagefilledrectangle($img, 170 * OG_SCALE, ($y - 25) * OG_SCALE, 300 * OG_SCALE, ($y + 9) * OG_SCALE, color($img, $kinds[$kind], 95));
            text($img, OG_BOLD, 17, 235 - textWidth(OG_BOLD, 17, $kind) / 2, $y - 2, color($img, $kinds[$kind]), $kind);
            text($img, OG_BOLD, 26, 322, $y, color($img, '#efe2f7'), $key);
            $x = 322 + textWidth(OG_BOLD, 26, $key) + 18;
            if ($what !== '' && $x < OG_WIDTH - 140)
                text($img, OG_REGULAR, 22, $x, $y, color($img, '#dcddde', 50), ogFit(OG_REGULAR, 22, $what, OG_WIDTH - 80 - $x));
        }

        ogFoot($img, 'sanakan.pl/cmd/zmiany', $since ? 'zapisywane od ' . date('j.m.Y', $since) : '');

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
        imagedestroy($layer);

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
