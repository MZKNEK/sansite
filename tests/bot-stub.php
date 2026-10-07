<?php
    // The canned bot API for tests/http.php, on a server of its own, so the site
    // (also on `php -S`, one request at a time) can ask it without deadlocking
    // itself. Not sent to the server (tests/ is export-ignore).

    header('Content-Type: application/json');
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    if (strpos($path, '/api/Info/commands/private') !== false)
        echo json_encode(['modules' => [['name' => 'Mod', 'subModules' => [['prefix' => 'm', 'commands' => [['name' => 'ban', 'description' => 'mod', 'aliases' => ['ban'], 'attributes' => [], 'example' => '']]]]]]]);
    else if (strpos($path, '/api/Info/commands') !== false)
        echo json_encode(['prefix' => '/', 'modules' => [['name' => 'Mod', 'subModules' => [['prefix' => 'm', 'commands' => [['name' => 'daily', 'description' => 'Codzienne', 'aliases' => ['daily'], 'attributes' => [], 'example' => '']]]]]]]);
    else if (strpos($path, '/api/health') !== false)
        echo json_encode(['status' => 'ok', 'version' => 'test', 'startedAt' => '2026-10-01T00:00:00Z', 'discord' => ['state' => 'Connected', 'latencyMs' => 12]]);
    else if (strpos($path, '/api/alive') !== false)
        echo 'ok';
    else
        echo json_encode(['onGuild' => true]);

    return true;
