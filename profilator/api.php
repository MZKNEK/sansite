<?php
    // Profilator's requests to the bot (inc/profilator.php), always for the logged-in
    // account: a=catalog (anyone), a=me, a=picture&id=, a=current, and with the
    // X-CSRF header a=upload (form: file, use), a=quote, a=preview and a=save
    // (JSON; a=save also with X-Idempotency-Key). The bot's statuses and answers go back as
    // they are, its pictures with their type. a=slots and, with X-CSRF, a=slot (JSON: slot,
    // layout or null, name) are the account's own layouts, kept by the site alone.
    // A site admin (PANEL_ADMINS) may add as=<Discord id> to a=me, picture, current, quote,
    // preview and save to work on another player's profile: a save then goes through only
    // when it costs that player nothing, and is noted in the history.
    require __DIR__ . '/../inc/profilator.php';
    installDataErrorHandler();

    function profilatorJson($status, $value)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // the bot's answer passed on: JSON as it is, a picture with its type, nothing as 503
    function profilatorPass($answer, $picture = false)
    {
        [$body, $status] = $answer;
        if ($body === false || $status === 0)
            profilatorJson(503, ['error' => 'bot']);

        if ($picture && $status === 200) {
            $type = profilatorImageType($body);
            if ($type === null)
                profilatorJson(502, ['error' => 'picture']);
            header('Content-Type: ' . $type);
            header('Cache-Control: private, max-age=86400');
            echo $body;
            exit;
        }

        $decoded = json_decode((string)$body, true);
        profilatorJson($status, $decoded !== null ? $decoded : ['error' => trim((string)$body, " \t\r\n\"")]);
    }

    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');

    $action = (string)($_GET['a'] ?? '');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($action === 'catalog') {
        $catalog = profilatorCatalog(time());
        if ($catalog === null)
            profilatorJson(503, ['error' => 'bot']);
        header('Cache-Control: public, max-age=300');
        profilatorJson(200, $catalog);
    }

    $user = authConfigured() ? siteUser() : null;
    $csrf = $user !== null ? siteCsrf() : '';
    if (session_status() === PHP_SESSION_ACTIVE)
        session_write_close();
    if ($user === null)
        profilatorJson(401, ['login' => false]);

    $id = (string)$user['id'];
    if (profilatorPath($id) === null)
        profilatorJson(400, ['error' => 'account']);

    $changing = in_array($action, ['upload', 'delete', 'quote', 'preview', 'save', 'slot'], true);
    if ($changing && ($method !== 'POST' || !hash_equals($csrf, (string)($_SERVER['HTTP_X_CSRF'] ?? ''))))
        profilatorJson(403, ['error' => 'csrf']);

    // whose profile: the account's own, or the one an admin asked for
    [$owner, $refused] = profilatorOwner($id, $_GET['as'] ?? null, $action);
    if ($refused !== null)
        profilatorJson($refused === 'admin' ? 403 : 400, ['error' => $refused]);

    switch ($action) {
        case 'me':
            [$body, $status] = profilatorBot('GET', profilatorPath($owner), null, [], 30);
            if ($status === 404)
                profilatorJson(200, ['error' => 'none']);
            profilatorPass([$body, $status]);

        case 'picture':
            $picture = (string)($_GET['id'] ?? '');
            if (!preg_match('/^\d{1,20}$/', $picture))
                profilatorJson(400, ['error' => 'picture']);
            profilatorPass(profilatorBot('GET', profilatorPath($owner, 'images/' . $picture)), true);

        case 'delete':
            $picture = (string)($_GET['id'] ?? '');
            if (!preg_match('/^\d{1,20}$/', $picture))
                profilatorJson(400, ['error' => 'picture']);
            profilatorPass(profilatorBot('DELETE', profilatorPath($id, 'images/' . $picture)));

        case 'current':
            profilatorPass(profilatorBot('GET', profilatorPath($owner, 'image'), null, [], 60), true);

        case 'upload':
            $file = $_FILES['file'] ?? null;
            $use = (string)($_POST['use'] ?? 'overlay');
            if (!is_array($file) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']))
                profilatorJson(400, ['error' => 'file']);
            if ($file['size'] > PROFILATOR_MAX_PICTURE)
                profilatorJson(413, ['error' => 'size']);
            if (!in_array($use, PROFILATOR_PICTURE_USES, true))
                profilatorJson(400, ['error' => 'use']);
            $bytes = file_get_contents($file['tmp_name']);
            $type = profilatorImageType($bytes);
            if ($type === null)
                profilatorJson(400, ['error' => 'type']);
            [$form, $contentType] = profilatorMultipart(['use' => $use], 'file', $file['name'] ?? 'obrazek', $bytes, $type);
            profilatorPass(profilatorBot('POST', profilatorPath($id, 'images'), $form, ['Content-Type' => $contentType], 60));

        case 'slots':
            profilatorJson(200, ['slots' => profilatorSlotsOut(profilatorSlots($id))]);

        case 'slot':
            $sent = json_decode((string)file_get_contents('php://input', false, null, 0, PROFILATOR_MAX_LAYOUT * 2 + 1024), true);
            $index = $sent['slot'] ?? null;
            if (!is_int($index) || $index < 0 || $index >= PROFILATOR_SLOTS)
                profilatorJson(400, ['error' => 'slot']);
            $layout = ($sent['layout'] ?? null) === null ? null : profilatorLayoutJson($sent['layout']);
            if ($layout === null && ($sent['layout'] ?? null) !== null)
                profilatorJson(400, ['error' => 'layout']);
            $slots = profilatorSetSlot($id, $index, $layout, $sent['name'] ?? '', time());
            if ($slots === null)
                profilatorJson(503, ['error' => 'store']);
            profilatorJson(200, ['slots' => profilatorSlotsOut($slots)]);

        case 'quote':
        case 'preview':
        case 'save':
            $raw = file_get_contents('php://input', false, null, 0, PROFILATOR_MAX_LAYOUT * 2 + 1024);
            $sent = json_decode((string)$raw, true);
            if ($action === 'save') {
                $layout = profilatorLayoutJson($sent['layout'] ?? null);
                $key = profilatorKey($_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? '');
                $currency = in_array($sent['currency'] ?? 'sc', ['sc', 'tc'], true) ? $sent['currency'] ?? 'sc' : null;
                if ($layout === null || $key === null || $currency === null)
                    profilatorJson(400, ['error' => 'request']);
                if ($owner !== $id) {
                    // another's profile: only when the bot would charge nothing for it
                    [$quote, $quoted] = profilatorBot('POST', profilatorPath($owner, 'quote'), $layout, ['Content-Type' => 'application/json'], 60);
                    if ($quote === false || $quoted === 0)
                        profilatorJson(503, ['error' => 'bot']);
                    if ($quoted !== 200 || !profilatorFree(json_decode((string)$quote, true)))
                        profilatorJson(402, ['error' => 'paid']);
                }
                $body = '{"layout":' . $layout . ',"currency":"' . $currency . '","version":' . (int)($sent['version'] ?? 0) . '}';
                $answer = profilatorBot('PUT', profilatorPath($owner), $body, ['Content-Type' => 'application/json', 'Idempotency-Key' => $key], 60);
                if ($owner !== $id && $answer[1] === 200)
                    addHistory('profilator', 'Zapisano w Profilatorze profil gracza ' . $owner . '.', $user);
                profilatorPass($answer);
            }
            $layout = profilatorLayoutJson($sent);
            if ($layout === null)
                profilatorJson(400, ['error' => 'layout']);
            profilatorPass(profilatorBot('POST', profilatorPath($owner, $action), $layout, ['Content-Type' => 'application/json'], 60), $action === 'preview');
    }

    profilatorJson(404, ['error' => 'action']);
