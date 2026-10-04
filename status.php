<?php
    // Bot status for the home page: online, idle, offline, or maintenance during
    // a planned break set in the admin panel; see inc/bot.php.
    require __DIR__ . '/inc/bot.php';

    $state = botState();

    // browsers keep the answer until the server cache expires
    $maxAge = max(0, BOT_CACHE_TTL - (time() - $state['checked']));
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=' . $maxAge);

    echo json_encode([
        'status' => botInMaintenance(time()) ? 'maintenance' : $state['status'],
        'uptime' => $state['uptime'],
        // since when it does not answer, e.g. "od 14:05 (23 min)"
        'down' => trim(botDownText($state))
    ], JSON_UNESCAPED_UNICODE);
