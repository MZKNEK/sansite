<?php
    // Bot status for the home page: online, idle or offline, see inc/bot.php.
    require __DIR__ . '/inc/bot.php';

    $state = botState();

    // browsers keep the answer until the server cache expires
    $maxAge = max(0, BOT_CACHE_TTL - (time() - $state['checked']));
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=' . $maxAge);

    echo json_encode([
        'status' => $state['status'],
        'uptime' => $state['uptime']
    ]);
