<?php
    // The "Powiadom mnie, gdy wróci" button of state/ (js/bot-return.js): a
    // POST of {"action": "subscribe" | "unsubscribe", "subscription": the
    // browser's PushSubscription}. Subscribing works only while it is offered
    // (inc/push.php); ending one always does.
    require __DIR__ . '/../inc/push.php';

    const PUSH_MAX_BODY = 4096;

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    function pushAnswer($code, $data)
    {
        http_response_code($code);
        echo json_encode($data);
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        pushAnswer(405, ['error' => 'POST only']);
    }

    $body = (string)file_get_contents('php://input', false, null, 0, PUSH_MAX_BODY + 1);
    $request = strlen($body) > PUSH_MAX_BODY ? null : json_decode($body, true);
    $action = is_array($request) ? ($request['action'] ?? null) : null;
    $subscription = is_array($request['subscription'] ?? null) ? $request['subscription'] : null;
    if (!in_array($action, ['subscribe', 'unsubscribe'], true) || $subscription === null)
        pushAnswer(400, ['error' => 'invalid']);

    if ($action === 'unsubscribe')
        pushAnswer(pushUnsubscribe($subscription['endpoint'] ?? null) ? 200 : 400, ['ok' => true]);

    $result = pushSubscribe($subscription, time());
    $codes = ['ok' => 200, 'invalid' => 400, 'closed' => 409, 'full' => 503, 'error' => 500];
    pushAnswer($codes[$result], $result === 'ok' ? ['ok' => true] : ['error' => $result]);
