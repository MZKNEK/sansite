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
