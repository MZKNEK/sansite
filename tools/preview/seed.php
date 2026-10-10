<?php
    // The first fill of the local preview's data folder (.preview/data), run
    // by tools/preview.sh before the servers start: 24 hours of the bot's
    // checks with a short outage, 120 days of availability with a planned
    // break, the bot's versions, a week of changes in its commands, the other
    // Sanakan sites, a notice saying this is a preview, an access request and
    // a few made-up pictures in the gallery (i/Podgląd, written down in
    // .preview/created.txt so tools/preview-stop.sh --clean takes them away).
    //   php tools/preview/seed.php
    require __DIR__ . '/config.php';
    $root = str_replace('\\', '/', dirname(__DIR__, 2));
    require $root . '/inc/auth.php';
    require $root . '/inc/bot.php';
    require $root . '/inc/services.php';

    if (!is_dir(SITE_DATA_DIR))
        mkdir(SITE_DATA_DIR, 0750, true);
    mt_srand(13);
    $now = time();
    $minute = $now - $now % 60;

    // a 24 h history, "time state [ms [ms]]", one line a minute, as
    // botRecordCheck() writes it; $down says which minutes did not answer
    function previewHistory($name, $now, $ms, $down = null, $second = null)
    {
        $lines = '';
        for ($t = $now - BOT_HISTORY_SPAN + 60; $t <= $now; $t += 60) {
            $ok = !($down && $down($t));
            $lines .= $t . ' ' . ($ok ? '1 ' . $ms() . ($second ? ' ' . $second() : '') : '0') . "\n";
        }
        file_put_contents(botFile($name), $lines);
    }

    // the bot: 9 minutes gone three hours ago
    $outage = [$minute - 3 * 3600, $minute - 3 * 3600 + 9 * 60];
    $isDown = function ($t) use ($outage) { return $t >= $outage[0] && $t < $outage[1]; };
    previewHistory('status-history.txt', $minute, function () { return mt_rand(28, 64); }, $isDown, function () { return mt_rand(140, 320); });
    // Shinden did not answer the bot for 20 minutes seven hours ago
    previewHistory('shinden-history.txt', $minute, function () { return mt_rand(140, 320); }, function ($t) use ($minute) { return $t >= $minute - 7 * 3600 && $t < $minute - 7 * 3600 + 1200; });
    previewHistory('database-history.txt', $minute, function () { return mt_rand(2, 9); });
    previewHistory('api-history.txt', $minute, function () { return mt_rand(40, 120); }, $isDown);
    file_put_contents(botFile('api-checked.txt'), (string)$minute);
    file_put_contents(botFile('cron.txt'), (string)$minute);

    // 120 days, [checks, answered]: whole days but a few, today from the history
    $days = [];
    $apiDays = [];
    $incidents = [];
    for ($i = 120; $i >= 1; $i--) {
        $day = strtotime('today -' . $i . ' days');
        $lost = in_array($i, [3, 17, 18, 44, 90], true) ? mt_rand(4, 40) : 0;
        $days[date('Y-m-d', $day)] = [1440, 1440 - $lost];
        $apiDays[date('Y-m-d', $day)] = [1440, 1440 - $lost - (in_array($i, [8, 60], true) ? 15 : 0)];
        if ($lost)
            $incidents[] = [$day + 14 * 3600, $day + 14 * 3600 + $lost * 60];
    }
    $today = 0;
    $todayUp = 0;
    foreach (botHistory() as $check)
        if ($check[0] >= strtotime('today')) {
            $today++;
            $todayUp += $check[1] ? 1 : 0;
        }
    $days[date('Y-m-d')] = [$today, $todayUp];
    $apiDays[date('Y-m-d')] = [$today, $todayUp];
    $incidents[] = $outage;
    file_put_contents(botFile('days.json'), json_encode($days));
    file_put_contents(botFile('api-days.json'), json_encode($apiDays));
    file_put_contents(botFile('incidents.json'), json_encode($incidents));

    // a planned break of half an hour two days ago, its checks counted apart
    $break = strtotime('today -2 days') + 22 * 3600;
    file_put_contents(botFile('maintenance.json'), json_encode([['id' => $break - 86400, 'from' => $break, 'to' => $break + 1800]]));

    // the versions as the site noticed them
    $versions = [];
    foreach ($previewData['versions'] as [$version, $ago]) {
        $since = strtotime('today -' . $ago . ' days') + 3 * 3600;
        $versions[] = ['version' => $version, 'since' => $since, 'seen' => $since + 60];
    }
    file_put_contents(botFile('versions.json'), json_encode($versions));

    // the commands a week ago, then now: the history of the changes
    $toApi = function ($modules) use ($previewData) {
        $out = [];
        foreach ($modules as [$module, $prefix, $commands]) {
            $list = [];
            foreach ($commands as [$name, $description, $aliases, $attributes, $example])
                $list[] = ['name' => $name, 'description' => $description, 'aliases' => $aliases, 'attributes' => $attributes, 'example' => $example];
            $out[] = ['name' => $module, 'subModules' => [['prefix' => $prefix, 'commands' => $list]]];
        }
        return ['prefix' => $previewData['prefix'], 'modules' => $out];
    };
    $before = $previewData['commands'];
    foreach ($before as &$module) {
        $module[2] = array_values(array_filter($module[2], function ($command) use ($previewData) { return !in_array($command[0], $previewData['commandsBefore']['drop'], true); }));
        foreach ($module[2] as &$command)
            $command[1] = $previewData['commandsBefore']['describe'][$command[0]] ?? $command[1];
        unset($command);
    }
    unset($module);
    botTrackCommands($toApi($before), $now - 7 * 86400);
    botTrackCommands($toApi($previewData['commands']), $now - 2 * 86400);

    // the other Sanakan sites: up, Alter with a bad day
    $services = [];
    foreach (SERVICES as $key => $service) {
        $serviceDays = [];
        for ($i = 100; $i >= 0; $i--)
            $serviceDays[date('Y-m-d', strtotime('today -' . $i . ' days'))] = [288, $key === 'alter' && $i === 12 ? 250 : 288];
        $services[$key] = ['up' => true, 'since' => $now - 40 * 86400, 'ms' => mt_rand(80, 400), 'checked' => $now, 'days' => $serviceDays];
    }
    file_put_contents(botFile('services.json'), json_encode($services));

    // a notice, so no one takes the preview for the real site
    botSaveNotice(['text' => 'To jest lokalny podgląd strony: wszystkie dane są zmyślone.', 'to' => null, 'maintenance' => null, 'by' => $previewData['admin'], 'set' => $now]);

    // every made-up account logged in once, so the panel knows them by name
    foreach (array_reverse($previewData['accounts'], true) as $id => [$name])
        recordLogin((string)$id, $name, 'https://cdn.discordapp.com/embed/avatars/' . ((int)substr((string)$id, -1) % 6) . '.png', strtolower($name));

    // the guest asks for the gallery
    writeData('requests', ['gallery' => ['100000000000000005' => ['name' => 'Gość', 'note' => 'Chcę zobaczyć galerię (podgląd).', 'time' => $now - 5400]]]);

    // a few made-up pictures in the gallery, drawn with GD when it is there
    $created = [];
    $folder = $root . '/i/Podgląd';
    if (function_exists('imagecreatetruecolor') && !is_dir($folder) && @mkdir($folder, 0755, true)) {
        $created[] = 'i/Podgląd';
        $colours = [[155, 89, 182], [79, 209, 197], [232, 98, 98], [240, 163, 94], [95, 211, 141], [232, 211, 106]];
        foreach ($colours as $i => [$r, $g, $b]) {
            $img = imagecreatetruecolor(448, 650);
            for ($y = 0; $y < 650; $y++) {
                $k = $y / 650;
                imageline($img, 0, $y, 447, $y, imagecolorallocate($img, (int)($r * (1 - $k) + 8 * $k), (int)($g * (1 - $k) + 9 * $k), (int)($b * (1 - $k) + 9 * $k)));
            }
            imagestring($img, 5, 20, 20, 'PODGLAD ' . ($i + 1), imagecolorallocate($img, 255, 255, 255));
            imagepng($img, $folder . '/przyklad-' . ($i + 1) . '.png');
        }
    }
    file_put_contents(dirname(SITE_DATA_DIR) . '/created.txt', implode("\n", $created) . ($created ? "\n" : ''));

    echo "Dane podglądu gotowe w .preview/data\n";
