<?php
    // The bot API of the local preview (tools/preview.sh), on a php -S of its
    // own next to the site's, so the site never waits on itself: what
    // api.sanakan.pl answers, made up from data.php. The roles of the made-up
    // accounts, the commands (the moderator ones too), api/health with the
    // version and a ping that moves a little, and api/alive.
    $data = require __DIR__ . '/data.php';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    header('Content-Type: application/json; charset=utf-8');

    // [module, prefix, [[name, description, aliases, attributes, example]]] as the bot sends them
    function previewModules($modules)
    {
        $out = [];
        foreach ($modules as [$module, $prefix, $commands]) {
            $list = [];
            foreach ($commands as [$name, $description, $aliases, $attributes, $example])
                $list[] = ['name' => $name, 'description' => $description, 'aliases' => $aliases, 'attributes' => $attributes, 'example' => $example];
            $out[] = ['name' => $module, 'subModules' => [['prefix' => $prefix, 'commands' => $list]]];
        }

        return $out;
    }

    if (preg_match('~/api/User/discord/(\d+)/permissions$~', $path, $match)) {
        $account = $data['accounts'][$match[1]] ?? null;
        $roles = ['onGuild' => $account !== null && $account[1] !== null];
        foreach (['dev', 'admin', 'semiAdmin', 'tester', 'moderator', 'user'] as $role)
            $roles[$role] = $account !== null && ($account[1] === $role || ($role === 'user' && $account[1] !== null));
        echo json_encode($roles);
    } else if (preg_match('~/api/User/discord/(\d+)/profile$~', $path, $match)) {
        // what Profilator gets of an account: a made-up profile, no pictures nor cards
        $account = $data['accounts'][$match[1]] ?? null;
        if ($account === null || $account[1] === null) {
            http_response_code(404);
            echo json_encode('User not found!');
        } else {
            $list = ['inProgress' => 7, 'completed' => 210, 'skipped' => 3, 'onHold' => 5, 'dropped' => 12, 'inPlan' => 64, 'total' => 301,
                'rating' => 8.1, 'count' => 2950, 'years' => 0, 'months' => 2, 'days' => 3, 'hours' => 11, 'minutes' => 0];
            echo json_encode([
                'code' => 'AQUOgAhG', 'nick' => $account[0], 'avatar' => 'https://cdn.discordapp.com/embed/avatars/' . ((int)substr($match[1], -1) % 6) . '.png',
                'color' => '#e86262', 'rank' => 'Podglądowy', 'level' => 23, 'exp' => 4400, 'top' => 128, 'messages' => 3120,
                'sc' => 42000, 'tc' => 310, 'ac' => 12, 'ct' => 80, 'pc' => 9, 'cards' => 412, 'limit' => 1000, 'karma' => 150,
                'rarities' => ['SS' => 2, 'S' => 11, 'A' => 40, 'B' => 90, 'C' => 120, 'D' => 80, 'E' => 69],
                'scalpels' => 4, 'scissors' => 0, 'ultimates' => 0, 'waifu' => null, 'gallery' => [],
                'anime' => $list, 'manga' => null
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    } else if (strpos($path, '/api/Info/commands/private') !== false) {
        echo json_encode(['prefix' => $data['prefix'], 'modules' => previewModules($data['private'])], JSON_UNESCAPED_UNICODE);
    } else if (strpos($path, '/api/Info/commands') !== false) {
        echo json_encode(['prefix' => $data['prefix'], 'modules' => previewModules($data['commands'])], JSON_UNESCAPED_UNICODE);
    } else if (strpos($path, '/api/health') !== false) {
        $version = end($data['versions']);
        echo json_encode([
            'status' => 'ok',
            'version' => $version[0],
            'startedAt' => gmdate('Y-m-d\TH:i:s\Z', strtotime('today -' . $version[1] . ' days') + 3 * 3600),
            'discord' => ['state' => 'Connected', 'latencyMs' => random_int(28, 64)],
            'shinden' => ['ok' => true, 'latencyMs' => random_int(140, 320)],
            'database' => ['ok' => true, 'latencyMs' => random_int(2, 9)],
            'commands' => ['rejected5min' => 0],
        ]);
    } else if (strpos($path, '/api/alive') !== false) {
        header('Content-Type: text/plain');
        echo 'ok';
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'not found']);
    }
