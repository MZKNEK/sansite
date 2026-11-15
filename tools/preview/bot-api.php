<?php
    // The bot API of the local preview (tools/preview.sh), on a php -S of its
    // own next to the site's, so the site never waits on itself: what
    // api.sanakan.pl answers, made up from data.php. The roles of the made-up
    // accounts, the commands (the moderator ones too), api/health with the
    // version and a ping that moves a little, api/alive and api/Profile for Profilator.
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

    // api/Profile of the profile of layers for Profilator: the catalog the bot really gives
    // (profile-catalog.json, written by the bot's ProfileCatalogFixture test), made-up players,
    // their pictures and layouts kept in .preview/data/bot-profile, a rough price
    function previewProfile($data, $path)
    {
        $dir = dirname(__DIR__, 2) . '/.preview/data/bot-profile';
        if (!is_dir($dir))
            mkdir($dir, 0777, true);
        $catalog = json_decode((string)file_get_contents(__DIR__ . '/profile-catalog.json'), true);
        if (preg_match('~/api/Profile/catalog$~', $path)) {
            echo json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        if (!preg_match('~/api/Profile/discord/(\d+)(?:/(images|image|quote|preview)(?:/(\d+))?)?$~', $path, $m)) {
            http_response_code(404);
            echo json_encode('Not found');
            return;
        }
        $id = $m[1];
        $what = $m[2] ?? '';
        $account = $data['accounts'][$id] ?? null;
        if ($account === null || $account[1] === null) {
            http_response_code(404);
            echo json_encode('User not found!');
            return;
        }
        $file = $dir . '/' . $id . '.json';
        $state = is_file($file) ? json_decode((string)file_get_contents($file), true)
            : ['version' => 0, 'layout' => $catalog['presets'][0]['layout'], 'unlocks' => [], 'pictures' => []];
        $save = function () use ($file, &$state) {
            file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        };
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($what === 'images' && $method === 'POST') {
            $upload = $_FILES['file'] ?? null;
            $info = $upload ? @getimagesize($upload['tmp_name']) : false;
            if (!$info) {
                http_response_code(400);
                echo json_encode('Not a picture');
                return;
            }
            $picture = count($state['pictures']) + 1;
            move_uploaded_file($upload['tmp_name'], $dir . '/' . $id . '-' . $picture . '.bin');
            $view = ['id' => $picture, 'use' => $_POST['use'] ?? 'overlay', 'width' => $info[0], 'height' => $info[1], 'animated' => false];
            $state['pictures'][] = $view;
            $save();
            echo json_encode($view);
            return;
        }
        if ($what === 'images') {
            $bytes = @file_get_contents($dir . '/' . $id . '-' . (int)($m[3] ?? 0) . '.bin');
            if ($bytes === false) {
                http_response_code(404);
                echo json_encode('Picture not found!');
                return;
            }
            header('Content-Type: application/octet-stream');
            echo $bytes;
            return;
        }
        if ($what === 'image' || $what === 'preview') {
            header('Content-Type: image/webp');
            readfile(dirname(__DIR__, 2) . '/img/logo.webp');
            return;
        }

        $body = json_decode((string)file_get_contents('php://input'), true);
        $layout = $what === 'quote' ? $body : ($body['layout'] ?? null);
        $needs = [];
        if (is_array($layout)) {
            if (!empty($layout['accent'])) $needs[] = 'accent';
            if (($layout['panels'] ?? 'solid') !== 'solid') $needs[] = 'panels';
            if (!empty($layout['header'])) $needs[] = 'headerImg';
            if (!empty($layout['style'])) $needs[] = 'styleImg';
            if (!empty($layout['ultra'])) $needs[] = 'ultra';
            $kinds = array_count_values(array_column($layout['layers'] ?? [], 't'));
            for ($i = 1; $i <= ($kinds['overlay'] ?? 0); $i++) $needs[] = 'overlay:' . $i;
            for ($i = 1; $i <= ($kinds['sticker'] ?? 0); $i++) $needs[] = 'sticker:' . $i;
            $frame = $layout['avatar']['frame'] ?? 'Base';
            if ($frame === 'custom') $needs[] = 'customFrame';
            else if ($frame !== 'Base' && $frame !== 'None') $needs[] = 'frame:' . $frame;
        }
        $features = array_column($catalog['features'], null, 'key');
        $missing = array_values(array_filter(array_map(function ($key) use ($features) { return $features[$key] ?? null; }, array_diff($needs, $state['unlocks']))));
        $sc = array_sum(array_column($missing, 'sc'));
        $tc = array_sum(array_column($missing, 'tc'));

        if ($what === 'quote') {
            echo json_encode(['errors' => [], 'missing' => $missing, 'sc' => $sc, 'tc' => $tc, 'walletSc' => 42000, 'walletTc' => 310], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ($method === 'PUT') {
            if ((int)($body['version'] ?? -1) !== $state['version']) {
                http_response_code(409);
                echo json_encode('Profile changed in the meantime');
                return;
            }
            $state['layout'] = $layout;
            $state['version']++;
            $state['unlocks'] = array_values(array_unique(array_merge($state['unlocks'], array_column($missing, 'key'))));
            $save();
            $currency = ($body['currency'] ?? 'sc') === 'tc' ? 'tc' : 'sc';
            echo json_encode(['version' => $state['version'], 'currency' => $currency, 'paid' => $currency === 'tc' ? $tc : $sc, 'unlocked' => array_column($missing, 'key')]);
            return;
        }

        $list = function ($statuses, $units, $time, $mean) {
            return ['statuses' => $statuses, 'total' => (string)array_sum($statuses), 'units' => $units, 'time' => $time, 'mean' => $mean,
                'recent' => [['title' => 'Podglądowe anime o długim tytule', 'progress' => '5 / 12', 'ratio' => 0.42, 'cover' => null],
                    ['title' => 'Drugi tytuł', 'progress' => '24 / 24', 'ratio' => 1, 'cover' => null]]];
        };
        echo json_encode([
            'layout' => $state['layout'], 'version' => $state['version'], 'unlocks' => $state['unlocks'], 'pictures' => $state['pictures'], 'cards' => [],
            'data' => [
                'nick' => $account[0], 'rank' => 'Podglądowy', 'avatar' => 'https://cdn.discordapp.com/embed/avatars/' . ((int)substr($id, -1) % 6) . '.png',
                'color' => '#E86262', 'top' => 128, 'level' => 23, 'levelProgress' => 0.4, 'messages' => 3120, 'sc' => 42000, 'tc' => 310, 'ac' => 12, 'ct' => 80, 'pc' => 9,
                'cards' => 412, 'limit' => 1000, 'karma' => 'neutral', 'rarities' => ['SS' => 2, 'S' => 11, 'A' => 40, 'B' => 90, 'C' => 120, 'D' => 80, 'E' => 69],
                'ultimates' => 0, 'customImages' => 4, 'customBorders' => 0, 'waifu' => null, 'gallery' => [],
                'anime' => $list([7, 210, 3, 5, 12, 64], '2 950', '61 dni', '8,10'), 'manga' => null,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (strpos($path, '/api/Profile/') !== false) {
        previewProfile($data, $path);
    } else if (preg_match('~/api/User/discord/(\d+)/permissions$~', $path, $match)) {
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
