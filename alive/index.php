<?php
    // The bot's own report, the same as its api/health, POSTed here every
    // minute by the bot (Heartbeat in its Config.json) with
    // "Authorization: Bearer <BOT_HEARTBEAT_SECRET>". While the last one is
    // fresh inc/check-bot.php uses it instead of asking the bot API, so the
    // status also works when the API cannot be reached from outside. Without
    // BOT_HEARTBEAT_SECRET in inc/config.php this page is not there (404).
    // The address a report comes from is the bot's: it is never blocked in
    // Cloudflare, by the panel or automatically. After answering the bot, the
    // site asks the bot API whether it answers from outside (inc/bot.php).
    require __DIR__ . '/../inc/bot.php';

    const HEARTBEAT_MAX_BODY = 65536;

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    function answer($code, $data)
    {
        http_response_code($code);
        echo json_encode($data);
        exit;
    }

    $secret = botHeartbeatSecret();
    if ($secret === '')
        answer(404, ['error' => 'not found']);

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        answer(405, ['error' => 'POST only']);
    }

    if (!hash_equals('Bearer ' . $secret, (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '')))
        answer(401, ['error' => 'bad secret']);

    $body = (string)file_get_contents('php://input', false, null, 0, HEARTBEAT_MAX_BODY + 1);
    $health = strlen($body) > HEARTBEAT_MAX_BODY ? null : json_decode($body, true);
    if (!is_array($health) || !is_string($health['status'] ?? null))
        answer(400, ['error' => 'not a health report']);

    botSaveHeartbeat($health, time());
    botNoteAddress((string)($_SERVER['REMOTE_ADDR'] ?? ''), time());

    // the bot gets its answer first, so a slow API does not hold it
    echo json_encode(['ok' => true]);
    if (function_exists('fastcgi_finish_request'))
        fastcgi_finish_request();
    botApiCheck(time());
