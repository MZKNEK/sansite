<?php
    // The profile of the logged-in account for Profilator (inc/profilator.php),
    // as JSON for profilator.js: 401 without a login, {"error": "none"} when the
    // bot has no profile of the account, 503 when the bot did not answer.
    require __DIR__ . '/../inc/profilator.php';
    installDataErrorHandler();

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');

    $user = authConfigured() ? siteUser() : null;
    if (session_status() === PHP_SESSION_ACTIVE)
        session_write_close();
    if ($user === null) {
        http_response_code(401);
        echo json_encode(['login' => false]);
        exit;
    }

    $data = profilatorData($user['id'], time());
    if ($data === false) {
        echo json_encode(['error' => 'none']);
        exit;
    }
    if ($data === null) {
        http_response_code(503);
        echo json_encode(['error' => 'bot']);
        exit;
    }

    echo json_encode(profilatorView($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
